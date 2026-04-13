<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

$pagina_id = 32;

require_once('../api/seguranca_json_editar.php');
include '../../conexao/config.php';
include '../funcoes/log_pedido_almox.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Requisição inválida.');
    }

    $id_pedido = $_POST['id_pedido'] ?? null;
    if (!$id_pedido) {
        throw new Exception('ID do pedido não informado.');
    }

    $usuarioLogado = $_SESSION['usuario_id'] ?? 0;

    // ======================================================
    // BUSCAR DADOS ATUAIS DO PEDIDO
    // ======================================================
    $stmtOld = $conexao->prepare("
        SELECT * 
        FROM almox_pedidos_princ 
        WHERE id = ?
    ");
    $stmtOld->bind_param("i", $id_pedido);
    $stmtOld->execute();
    $old = $stmtOld->get_result()->fetch_assoc();
    $stmtOld->close();

    if (!$old) {
        throw new Exception('Pedido não encontrado.');
    }

    $autorizado = ($old['autorizacao'] === 'sim');

    // ======================================================
    // DADOS NOVOS
    // ======================================================
    $status_pedido = $_POST['status_pedido'] ?? '';

    if (!$status_pedido) {
        throw new Exception('Status do pedido é obrigatório.');
    }

    // ======================================================
    // 🔒 SE AUTORIZADO → SÓ ATUALIZA STATUS
    // ======================================================
    if ($autorizado) {

        if ($status_pedido !== $old['status_pedido']) {

            $upd = $conexao->prepare("
                UPDATE almox_pedidos_princ
                SET status_pedido = ?
                WHERE id = ?
            ");
            $upd->bind_param("si", $status_pedido, $id_pedido);
            $upd->execute();
            $upd->close();

            $msg = "Ed. Pedido Almox #{$id_pedido}: "
                 . "Status '{$old['status_pedido']}' → '{$status_pedido}'";

            registrar_log(
                $conexao,
                $usuarioLogado,
                'Editar Pedido Almox (restrito)',
                $msg,
                $id_pedido
            );
        }

        echo json_encode([
            'status'   => 'sucesso',
            'mensagem' => 'Pedido autorizado: apenas o status foi atualizado.'
        ]);
        exit;
    }

    // ======================================================
    // 🔓 NÃO AUTORIZADO → EDIÇÃO COMPLETA
    // ======================================================
    $data_pedido         = $_POST['data_pedido'] ?? '';
    $militar_solicitante = $_POST['militar_solicitante'] ?? '';
    $id_os               = !empty($_POST['id_os']) ? intval($_POST['id_os']) : null;
    $secao_solicitante   = $_POST['secao_rspns'] ?? '';
    $local_pedido        = $_POST['local_pedido'] ?? '';
    $batalhao            = !empty($_POST['batalhao']) ? intval($_POST['batalhao']) : null;
    $odometro            = $_POST['odometro'] ?? null;

    if (
        !$data_pedido || !$militar_solicitante || !$secao_solicitante ||
        !$local_pedido || !$batalhao
    ) {
        throw new Exception('Campos obrigatórios não preenchidos.');
    }

    // ======================================================
    // VALIDAR DEPÓSITO ÚNICO DOS ITENS
    // ======================================================
    $depositosEncontrados = [];

    $stmtDepositoEntrada = $conexao->prepare("
        SELECT deposito_id 
        FROM almox_entradas 
        WHERE id = ?
    ");

    foreach ($_POST['itens'] ?? [] as $it) {
        if (empty($it['id_entrada_produto'])) continue;

        [$id_entrada] = explode('|', $it['id_entrada_produto']);
        $id_entrada = (int)$id_entrada;

        $stmtDepositoEntrada->bind_param("i", $id_entrada);
        $stmtDepositoEntrada->execute();
        $res = $stmtDepositoEntrada->get_result()->fetch_assoc();

        if (!$res) {
            throw new Exception("Entrada {$id_entrada} não encontrada.");
        }

        $depositosEncontrados[$res['deposito_id']] = true;
    }
    $stmtDepositoEntrada->close();

    if (count($depositosEncontrados) > 1) {
        throw new Exception(
            'Não é permitido itens de depósitos diferentes.'
        );
    }

    $novoDepositoId = count($depositosEncontrados) === 1
        ? array_key_first($depositosEncontrados)
        : null;

    // ======================================================
    // ATUALIZAR PEDIDO PRINCIPAL
    // ======================================================
    $upd = $conexao->prepare("
        UPDATE almox_pedidos_princ
        SET data_pedido = ?,
            militar_solicitante = ?,
            id_os = ?,
            secao_solicitante = ?,
            status_pedido = ?,
            local_pedido = ?,
            batalhao = ?,
            odometro = ?,
            deposito_id = ?
        WHERE id = ?
    ");

    $upd->bind_param(
        'ssissssiii',
        $data_pedido,
        $militar_solicitante,
        $id_os,
        $secao_solicitante,
        $status_pedido,
        $local_pedido,
        $batalhao,
        $odometro,
        $novoDepositoId,
        $id_pedido
    );
    $upd->execute();
    $upd->close();

    // ======================================================
    // REMOVER E REINSERIR ITENS
    // ======================================================
    $conexao->query(
        "DELETE FROM almox_pedidos_itens WHERE id_pedido_principal = $id_pedido"
    );

    $stmtInsert = $conexao->prepare("
        INSERT INTO almox_pedidos_itens
        (id_pedido_principal, id_produto, id_entrada, quant_solicitada)
        VALUES (?, ?, ?, ?)
    ");

    foreach ($_POST['itens'] ?? [] as $it) {
        if (empty($it['id_entrada_produto'])) continue;

        [$id_entrada, $id_produto] = explode('|', $it['id_entrada_produto']);
        $quant = floatval($it['quant_solicitada'] ?? 0);

        if ($quant <= 0) continue;

        $stmtInsert->bind_param(
            'iiii',
            $id_pedido,
            $id_produto,
            $id_entrada,
            $quant
        );
        $stmtInsert->execute();
    }
    $stmtInsert->close();

    registrar_log(
        $conexao,
        $usuarioLogado,
        'Editar Pedido Almox',
        "Pedido #{$id_pedido} editado completamente",
        $id_pedido
    );

    echo json_encode([
        'status'   => 'sucesso',
        'mensagem' => 'Pedido atualizado com sucesso!'
    ]);

} catch (Exception $e) {
    echo json_encode([
        'status'   => 'erro',
        'mensagem' => $e->getMessage()
    ]);
}
