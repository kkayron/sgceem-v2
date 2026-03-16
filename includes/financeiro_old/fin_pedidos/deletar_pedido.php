<?php
session_start();
include_once("../../conexao/config.php");
include_once("../../includes/funcoes/log.php");

header('Content-Type: application/json; charset=utf-8');

$id = $_POST['id'] ?? null;

if (!$id || !is_numeric($id)) {
    echo json_encode(['success' => false, 'message' => 'ID do pedido inválido.']);
    exit;
}

// Buscar dados do pedido antes de deletar
$stmt_select = $conexao->prepare("SELECT id, solicitante, data_pedido FROM fin_pedidos_forn WHERE id = ?");
$stmt_select->bind_param("i", $id);
$stmt_select->execute();
$result = $stmt_select->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Pedido não encontrado.']);
    exit;
}

$pedido = $result->fetch_assoc();
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
    registrar_log($conexao, $usuarioLogado, 'Deletar Pedido', $descricao, $id);

    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Erro ao deletar pedido: ' . $stmt_delete_pedido->error]);
}

$stmt_delete_pedido->close();
?>
