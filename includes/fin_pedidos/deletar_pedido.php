<?php
session_start();

header('Content-Type: application/json; charset=utf-8');
$pagina_ids = [17, 25];
require_once('../api/seguranca_json_deletar.php');

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
include_once("../../includes/funcoes/log_pedido_financeiro.php");


$id = $_POST['id'] ?? null;

if (!$id || !is_numeric($id)) {
    echo json_encode(['success' => false, 'message' => 'ID do pedido inválido.']);
    exit;
}

// Buscar dados do pedido antes de deletar
$stmt_select = $conexao->prepare("SELECT id, id_vtr, id_os, solicitante, data_pedido FROM fin_pedidos_forn WHERE id = ?");
$stmt_select->bind_param("i", $id);
$stmt_select->execute();
$result = $stmt_select->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Pedido não encontrado.']);
    exit;
}

$pedido = $result->fetch_assoc();
$id_vtr = $pedido['id_vtr'] ?? null;
$id_os = $pedido['id_os'] ?? null;

$stmt_select->close();

$usuarioLogado = $_SESSION['usuario_id'] ?? 0;

// Deletar itens do pedido
$stmt_delete_itens = $conexao->prepare("DELETE FROM fin_pedidos_forn_itens WHERE id_principal = ?");
$stmt_delete_itens->bind_param("i", $id);
$stmt_delete_itens->execute();
$stmt_delete_itens->close();

// Deletar o pedido principal
$stmt_delete_pedido = $conexao->prepare("DELETE FROM fin_pedidos_forn WHERE id = ?");
$stmt_delete_pedido->bind_param("i", $id);

if ($stmt_delete_pedido->execute()) {
    $descricao = "Pedido ID {$pedido['id']} deletado: Solicitante: {$pedido['solicitante']} - Data: {$pedido['data_pedido']}";
    registrar_log_financeiro(
    $conexao,
    $usuarioLogado,
    'Deletar Pedido',
    $descricao,
    $id_vtr,        // frota_id (não se aplica aqui)
    $id,         // pedido_financeiro_id
    $id_os          // os_id (se quiser, pode passar se existir)
);

		// 🔒 NOVO TOKEN
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));

    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Erro ao deletar pedido: ' . $stmt_delete_pedido->error]);
}

$stmt_delete_pedido->close();
?>
