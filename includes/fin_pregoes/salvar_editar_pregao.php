<?php
session_start();
$pagina_id = 24;
require_once('../api/seguranca_json_editar.php');

//CSRF
if (
    empty($_POST['csrf_token']) ||
    empty($_SESSION['csrf_token']) ||
    !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'Token inválido'
    ]);
    exit;
}

include '../../conexao/config.php';
include '../funcoes/log.php';

$id_pregao = $_POST['id'] ?? null;
if (!$id_pregao) {
    echo 'ID do Pregão não recebido.';
    exit;
}

$usuarioLogado = $_SESSION['usuario_id'] ?? 0;

// 1. Atualiza pregão principal
$old_pregao = $conexao->query("SELECT * FROM fin_pregao WHERE id = $id_pregao")->fetch_assoc();

$update = $conexao->prepare("
    UPDATE fin_pregao 
    SET nmr_pregao = ?, ano_pregao = ?, ug_licitacao = ?, uasg_licitacao = ?, 
        nup_licitacao = ?, tipo_pregao = ?, data_homologacao = ?, data_validade = ?, continuidade_pregao = ?, descricao_pregao = ?
    WHERE id = ?
");
$update->bind_param(
    'ssssssssssi',
    $_POST['nmr_pregao'],
    $_POST['ano_pregao'],
    $_POST['ug_licitacao'],
    $_POST['uasg_licitacao'],
    $_POST['nup_licitacao'],
    $_POST['tipo_pregao'],
    $_POST['data_homologacao'],
    $_POST['data_validade'],
    $_POST['continuidade_pregao'],
    $_POST['descricao_pregao'],
    $id_pregao
);
$update->execute();
$update->close();

// 2. Montar log
$alteracoes = [];
foreach ($_POST as $campo => $valor) {
    if (isset($old_pregao[$campo]) && $valor != $old_pregao[$campo]) {
        $alteracoes[] = ucfirst($campo) . ": '{$old_pregao[$campo]}' → '$valor'";
    }
}

// 3. Preparar itens recebidos
$itensRecebidos = $_POST['itens'] ?? [];
$idsRecebidos = [];

// 4. Pega todos os itens antigos do pregão
$resItensAntigos = $conexao->query("SELECT * FROM fin_pregao_itens WHERE id_pregao = $id_pregao");
$itensAntigos = [];
while ($row = $resItensAntigos->fetch_assoc()) {
    $itensAntigos[$row['id']] = $row;
}

// 5. Processar itens recebidos
$itens_log = [];
foreach ($itensRecebidos as $item) {
    $id_item = intval($item['id'] ?? 0);
    $id_fornecedor = intval($item['id_fornecedor']);
    $descricao = $item['descricao_item'];
    $saldo = floatval($item['saldo_item']);
    $valor_unt = floatval($item['valor_unt']);
    $valor_total = floatval($item['valor_total']);
    $nmr_item = $item['nmr_item_pregao'];

    if ($id_item && isset($itensAntigos[$id_item])) {
        // Atualização de item existente
        $stmtUsado = $conexao->prepare("
            SELECT COALESCE(SUM(quant_saida_item), 0) as usado 
            FROM fin_requisicao_itens 
            WHERE id_item = ?
        ");
        $stmtUsado->bind_param("i", $id_item);
        $stmtUsado->execute();
        $resUsado = $stmtUsado->get_result()->fetch_assoc();
        $stmtUsado->close();

        $quant_usada = floatval($resUsado['usado']);

        if ($saldo < $quant_usada) {
            echo "Erro: O item $nmr_item já possui $quant_usada unidades utilizadas em requisições. O novo saldo ($saldo) é insuficiente.";
            exit;
        }

        $stmtUpdateItem = $conexao->prepare("
            UPDATE fin_pregao_itens 
            SET id_fornecedor = ?, descricao_item = ?, saldo_item = ?, valor_unt = ?, valor_total = ?, nmr_item_pregao = ?
            WHERE id = ?
        ");
        $stmtUpdateItem->bind_param("issddsi", $id_fornecedor, $descricao, $saldo, $valor_unt, $valor_total, $nmr_item, $id_item);
        $stmtUpdateItem->execute();
        $stmtUpdateItem->close();

        $idsRecebidos[] = $id_item;
        $itens_log[] = "Atualizado item #$id_item — '$descricao'";
    } else {
        // Novo item
        $stmtInsertItem = $conexao->prepare("
            INSERT INTO fin_pregao_itens 
            (id_fornecedor, id_pregao, descricao_item, saldo_item, valor_unt, valor_total, nmr_item_pregao)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmtInsertItem->bind_param(
            'iissdds',
            $id_fornecedor,
            $id_pregao,
            $descricao,
            $saldo,
            $valor_unt,
            $valor_total,
            $nmr_item
        );
        $stmtInsertItem->execute();
        $stmtInsertItem->close();

        $itens_log[] = "Novo item — '$descricao'";
    }
}

// 6. Excluir itens que estavam antes e não estão mais — somente se não estiverem em uso
foreach ($itensAntigos as $id_antigo => $item_antigo) {
    if (!in_array($id_antigo, $idsRecebidos)) {
        // Verifica se o item está vinculado a alguma requisição
        $stmtUsado = $conexao->prepare("
            SELECT COUNT(*) as total 
            FROM fin_requisicao_itens 
            WHERE id_item = ?
        ");
        $stmtUsado->bind_param("i", $id_antigo);
        $stmtUsado->execute();
        $usado = $stmtUsado->get_result()->fetch_assoc()['total'];
        $stmtUsado->close();

        if ($usado > 0) {
            // Apenas registra no log, mas **não dá erro e não tenta excluir**
            $itens_log[] = "Item '{$item_antigo['descricao_item']}' mantido (vinculado a $usado requisição(ões)).";
        } else {
            $conexao->query("DELETE FROM fin_pregao_itens WHERE id = $id_antigo");
            $itens_log[] = "Item #$id_antigo removido.";
        }
    }
}

		// 🔒 NOVO TOKEN
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));	

// 7. Log
$descricaoLog = "Alterações no Pregão ID $id_pregao: ";
if (!empty($alteracoes)) {
    $descricaoLog .= implode("; ", $alteracoes) . ". ";
}
if (!empty($itens_log)) {
    $descricaoLog .= "Itens: " . implode("; ", $itens_log);
}
registrar_log($conexao, $usuarioLogado, 'Editar Pregão', $descricaoLog, $id_pregao);

// 8. Retorno como JSON com possíveis mensagens
echo json_encode([
    'status' => 'sucesso',
    'mensagem' => 'Pregão atualizado com sucesso.',
    'detalhes' => $itens_log
]);

?>
