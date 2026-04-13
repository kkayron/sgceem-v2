<?php
header('Content-Type: application/json; charset=utf-8');

$pagina_id = 32;

require_once('../api/seguranca_json_cadastrar.php');
if (session_status() === PHP_SESSION_NONE) session_start();
include_once('../../conexao/config.php');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Requisição inválida.');
    }

    // -------------------------------------------------
    // Função para buscar responsável do batalhão
    // -------------------------------------------------
    function buscarResponsavel($conexao, $funcao, $batalhao) {
        $sql = "SELECT nomecompleto, postograd 
                FROM usuarios 
                WHERE funcao = ? AND batalhao = ? AND status = 'sim' 
                LIMIT 1";
        $stmt = $conexao->prepare($sql);
        $stmt->bind_param("si", $funcao, $batalhao);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($user = $result->fetch_assoc()) {
            return $user['nomecompleto'] . " - " . $user['postograd'];
        }
        return "Não há usuário cadastrado com a função neste batalhão";
    }

    // -------------------------------------------------
    // Dados do pedido
    // -------------------------------------------------
    $data_pedido         = $_POST['data_pedido'] ?? null;
    $militar_solicitante = $_POST['militar_solicitante'] ?? null;
    $id_os               = !empty($_POST['id_os']) ? intval($_POST['id_os']) : null;
    $secao_solicitante   = $_POST['secao_solicitante'] ?? ($_POST['secao_rspns'] ?? null);
    $local_pedido        = $_POST['local_pedido'] ?? null;
    $status_pedido       = $_POST['status_pedido'] ?? null;
    $batalhao            = !empty($_POST['batalhao']) ? intval($_POST['batalhao']) : null;

    if (!$data_pedido || !$militar_solicitante || !$secao_solicitante || !$local_pedido || !$status_pedido || !$batalhao) {
        throw new Exception('Campos obrigatórios não preenchidos.');
    }

    // -------------------------------------------------
    // Validação OS
    // -------------------------------------------------
    if ($id_os) {
        $stmtOS = $conexao->prepare("SELECT id FROM os_principal WHERE id = ? AND batalhao = ?");
        $stmtOS->bind_param("ii", $id_os, $batalhao);
        $stmtOS->execute();
        if ($stmtOS->get_result()->num_rows === 0) {
            throw new Exception('A OS selecionada não pertence ao batalhão informado.');
        }
        $stmtOS->close();
    }

    // -------------------------------------------------
    // Validação Local
    // -------------------------------------------------
    $stmtLocal = $conexao->prepare("
        SELECT id 
        FROM config_destinos 
        WHERE TRIM(destino) = TRIM(?) AND batalhao = ?
    ");
    $stmtLocal->bind_param("si", $local_pedido, $batalhao);
    $stmtLocal->execute();
    if ($stmtLocal->get_result()->num_rows === 0) {
        throw new Exception('O local do pedido não pertence ao batalhão informado.');
    }
    $stmtLocal->close();

    // -------------------------------------------------
    // Responsáveis
    // -------------------------------------------------
    $cmt_ceem      = buscarResponsavel($conexao, '8', $batalhao);
    $ch_suprimento = buscarResponsavel($conexao, '10', $batalhao);
    $ch_controle   = buscarResponsavel($conexao, '9', $batalhao);

    // -------------------------------------------------
    // Início da transação
    // -------------------------------------------------
    $conexao->begin_transaction();

    if (empty($_POST['itens']) || !is_array($_POST['itens'])) {
        throw new Exception('Nenhum item informado no pedido.');
    }

    // -------------------------------------------------
    // AGRUPAR ITENS + VALIDAR DEPÓSITO ÚNICO
    // -------------------------------------------------
    $itensAgrupados = [];
    $deposito_id_pedido = null;

    $stmtEntradaDeposito = $conexao->prepare("
        SELECT deposito_id 
        FROM almox_entradas 
        WHERE id = ? AND batalhao = ?
    ");

    foreach ($_POST['itens'] as $item) {

        if (empty($item['id_entrada_produto'])) continue;

        [$id_entrada, $id_produto] = explode('|', $item['id_entrada_produto']);
        $id_entrada = intval($id_entrada);
        $id_produto = intval($id_produto);
        $quant = floatval($item['quant_solicitada'] ?? 0);

        if ($id_entrada <= 0 || $id_produto <= 0 || $quant <= 0) continue;

        // Descobrir depósito da entrada
        $stmtEntradaDeposito->bind_param("ii", $id_entrada, $batalhao);
        $stmtEntradaDeposito->execute();
        $resDep = $stmtEntradaDeposito->get_result();

        if ($resDep->num_rows === 0) {
            throw new Exception("Entrada {$id_entrada} inválida ou não pertence ao batalhão.");
        }

        $deposito_item = intval($resDep->fetch_assoc()['deposito_id']);

        // Primeira entrada define o depósito do pedido
        if ($deposito_id_pedido === null) {
            $deposito_id_pedido = $deposito_item;
        }

        // Bloqueia itens de outro depósito
        if ($deposito_item !== $deposito_id_pedido) {
            throw new Exception('Não é permitido cadastrar pedido com itens de depósitos diferentes.');
        }

        $chave = $id_entrada . '|' . $id_produto;
        if (!isset($itensAgrupados[$chave])) {
            $itensAgrupados[$chave] = [
                'id_entrada' => $id_entrada,
                'id_produto' => $id_produto,
                'quant'      => 0
            ];
        }

        $itensAgrupados[$chave]['quant'] += $quant;
    }

    if (empty($itensAgrupados)) {
        throw new Exception('Nenhum item válido informado.');
    }

    // -------------------------------------------------
    // Inserir pedido principal (COM deposito_id)
    // -------------------------------------------------
    $stmtPedido = $conexao->prepare("
        INSERT INTO almox_pedidos_princ
        (data_pedido, id_os, militar_solicitante, secao_solicitante, local_pedido,
         status_pedido, cmt_ceem, ch_suprimento, ch_controle, batalhao, deposito_id)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    $stmtPedido->bind_param(
        'sisssssssii',
        $data_pedido,
        $id_os,
        $militar_solicitante,
        $secao_solicitante,
        $local_pedido,
        $status_pedido,
        $cmt_ceem,
        $ch_suprimento,
        $ch_controle,
        $batalhao,
        $deposito_id_pedido
    );

    $stmtPedido->execute();
    $id_pedido = $stmtPedido->insert_id;
    $stmtPedido->close();

    // -------------------------------------------------
    // Statements auxiliares
    // -------------------------------------------------
    $stmtQuantEntrada = $conexao->prepare("
        SELECT quant 
        FROM almox_entradas_itens 
        WHERE id_entrada = ? AND id_produto = ?
    ");

    $stmtSumSolicitado = $conexao->prepare("
        SELECT COALESCE(SUM(ai.quant_solicitada),0) AS ja_solicitado
        FROM almox_pedidos_itens ai
        INNER JOIN almox_pedidos_princ ap ON ap.id = ai.id_pedido_principal
        WHERE ai.id_entrada = ? AND ai.id_produto = ?
        AND ap.status_pedido NOT IN ('Cancelado')
    ");

    $stmtInserirItem = $conexao->prepare("
        INSERT INTO almox_pedidos_itens
        (id_pedido_principal, id_produto, id_entrada, quant_solicitada)
        VALUES (?, ?, ?, ?)
    ");

    // -------------------------------------------------
    // Inserção dos itens
    // -------------------------------------------------
    foreach ($itensAgrupados as $ia) {

        $stmtQuantEntrada->bind_param("ii", $ia['id_entrada'], $ia['id_produto']);
        $stmtQuantEntrada->execute();
        $row = $stmtQuantEntrada->get_result()->fetch_assoc();

        if (!$row) {
            throw new Exception("Produto inválido na entrada {$ia['id_entrada']}.");
        }

        $quant_total = floatval($row['quant']);

        $stmtSumSolicitado->bind_param("ii", $ia['id_entrada'], $ia['id_produto']);
        $stmtSumSolicitado->execute();
        $ja_solicitado = floatval($stmtSumSolicitado->get_result()->fetch_assoc()['ja_solicitado']);

        $saldo = $quant_total - $ja_solicitado;

        if ($ia['quant'] > $saldo) {
            throw new Exception("Saldo insuficiente para o produto {$ia['id_produto']}.");
        }

        $stmtInserirItem->bind_param(
            'iiii',
            $id_pedido,
            $ia['id_produto'],
            $ia['id_entrada'],
            $ia['quant']
        );
        $stmtInserirItem->execute();
    }

    // -------------------------------------------------
    // Commit
    // -------------------------------------------------
    $conexao->commit();

    echo json_encode([
        'status' => 'sucesso',
        'mensagem' => 'Pedido cadastrado com sucesso!',
        'id' => $id_pedido
    ]);

} catch (Exception $e) {

    if ($conexao->in_transaction) {
        $conexao->rollback();
    }

    echo json_encode([
        'status' => 'erro',
        'mensagem' => $e->getMessage()
    ]);
}
