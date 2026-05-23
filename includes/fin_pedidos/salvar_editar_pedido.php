<?php
session_start();
$pagina_ids = [17, 25];
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

include_once("../../conexao/config.php");
include_once("../funcoes/log_pedido_financeiro.php");

header('Content-Type: application/json; charset=utf-8');

$id_pedido = $_POST['id'] ?? null;
if (!$id_pedido || !is_numeric($id_pedido)) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'ID do Pedido não recebido ou inválido.']);
    exit;
}

$usuarioLogado = $_SESSION['usuario_id'] ?? 0;

/**
 * 1) Buscar pedido atual
 */
$stmtOld = $conexao->prepare("SELECT * FROM fin_pedidos_forn WHERE id = ?");
$stmtOld->bind_param('i', $id_pedido);
$stmtOld->execute();
$old = $stmtOld->get_result()->fetch_assoc();
$stmtOld->close();

if (!$old) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Pedido não encontrado.']);
    exit;
}

$autorizado = strtolower(trim($old['autorizacao'] ?? '')) === 'sim';
$situacao_pedido = $_POST['situacao_pedido'] ?? $old['situacao_pedido'];

/**
 * ===============================
 * 🔒 CASO PEDIDO JÁ AUTORIZADO
 * ===============================
 */
if ($autorizado) {

    // Atualiza SOMENTE a situação
    $stmt = $conexao->prepare("
        UPDATE fin_pedidos_forn
        SET situacao_pedido = ?
        WHERE id = ?
    ");
    $stmt->bind_param('si', $situacao_pedido, $id_pedido);

    if (!$stmt->execute()) {
        echo json_encode([
            'status' => 'erro',
            'mensagem' => 'Erro ao atualizar situação do pedido.'
        ]);
        exit;
    }
    $stmt->close();

    // Log específico
    registrar_log_financeiro(
        $conexao,
        $usuarioLogado,
        'ALTERAR SITUAÇÃO PEDIDO',
        "Situação do Pedido ID {$id_pedido} alterada para '{$situacao_pedido}' (pedido já autorizado).",
        $old['id_vtr'] ?: null,
        $id_pedido,
        $old['id_os'] ?: null
    );

    echo json_encode([
        'status' => 'sucesso',
        'mensagem' => 'Situação do pedido atualizada com sucesso.'
    ]);
    exit;
}

/**
 * ===============================
 * ✏️ PEDIDO NÃO AUTORIZADO
 * ===============================
 */

// 2) Atualizar pedido principal
$update = $conexao->prepare("
    UPDATE fin_pedidos_forn
    SET 
      data_pedido      = ?, 
      solicitante      = ?, 
      id_vtr           = ?, 
      id_os            = ?, 
      secao_rspns      = ?, 
      situacao_pedido  = ?, 
      local_pedido     = ?, 
      desconto_empenho = ?
    WHERE id = ?
");

$data_pedido      = $_POST['data_pedido']      ?? '';
$solicitante      = $_POST['solicitante']      ?? '';
$id_vtr           = (int)($_POST['id_vtr']      ?? 0);
$id_os            = (int)($_POST['id_os']       ?? 0);
$secao_rspns      = $_POST['secao_rspns']      ?? '';
$local_pedido     = $_POST['local_pedido']     ?? '';
$desconto_empenho = (float)($_POST['desconto_empenho'] ?? 0);

$update->bind_param(
    'ssiisssdi',
    $data_pedido,
    $solicitante,
    $id_vtr,
    $id_os,
    $secao_rspns,
    $situacao_pedido,
    $local_pedido,
    $desconto_empenho,
    $id_pedido
);

if (!$update->execute()) {
    echo json_encode([
        'status' => 'erro',
        'mensagem' => 'Erro ao atualizar o pedido: ' . $update->error
    ]);
    exit;
}
$update->close();

/**
 * 3) Remover itens antigos
 */
$conexao->query("DELETE FROM fin_pedidos_forn_itens WHERE id_principal = $id_pedido");

/**
 * 4) Inserir novos itens
 */
$itens_log = [];
if (!empty($_POST['itens']) && is_array($_POST['itens'])) {

    $sqlItem = "
      INSERT INTO fin_pedidos_forn_itens
        (id_principal, codigo_item, descricao_item, quant_solicitada, und_solicitada, valor_unt, valor_total)
      VALUES (?, ?, ?, ?, ?, ?, ?)
    ";
    $stmtItem = $conexao->prepare($sqlItem);

    foreach ($_POST['itens'] as $it) {
        $cod   = $it['codigo_item'] ?? '';
        $desc  = $it['descricao_item'] ?? '';
        $qtd   = (float)($it['quant_solicitada'] ?? 0);
        $und   = $it['und_solicitada'] ?? '';
        $unit  = (float)($it['valor_unt'] ?? 0);
        $tot   = (float)($it['valor_total'] ?? 0);

        $stmtItem->bind_param(
            'issdsdd',
            $id_pedido,
            $cod,
            $desc,
            $qtd,
            $und,
            $unit,
            $tot
        );
        $stmtItem->execute();

        $itens_log[] = "Item {$cod} ({$desc}) qtd {$qtd}, unit {$unit}, total {$tot}";
    }
    $stmtItem->close();
}

/**
 * 5) Log completo
 */
$descricao = "Pedido ID {$id_pedido} editado.";
if ($itens_log) {
    $descricao .= " Itens: " . implode("; ", $itens_log);
}

		// 🔒 NOVO TOKEN
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));

registrar_log_financeiro(
    $conexao,
    $usuarioLogado,
    'EDITAR PEDIDO',
    $descricao,
    $id_vtr ?: null,
    $id_pedido,
    $id_os ?: null
);

/**
 * 6) Retorno
 */
echo json_encode([
    'status' => 'sucesso',
    'mensagem' => 'Pedido atualizado com sucesso!'
]);
exit;
