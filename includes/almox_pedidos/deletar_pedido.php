<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

$pagina_id = 32;

require_once('../api/seguranca_json_deletar.php');
include_once("../../conexao/config.php");
include_once("../../includes/funcoes/log.php");


$id = $_POST['id'] ?? null;

if (!$id) {
    echo json_encode(['success' => false, 'message' => 'ID inválido']);
    exit;
}

// Buscar dados do pedido antes de deletar
$stmt_select = $conexao->prepare("SELECT id, militar_solicitante, id_os FROM almox_pedidos_princ WHERE id = ?");
$stmt_select->bind_param("i", $id);
$stmt_select->execute();
$result = $stmt_select->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Pedido não encontrado']);
    exit;
}

$pedido = $result->fetch_assoc();
$stmt_select->close();

$usuarioLogado = $_SESSION['usuario_id'] ?? 0;

// Deletar itens do pedido
$stmt_delete_itens = $conexao->prepare("DELETE FROM almox_pedidos_itens WHERE id_pedido_principal = ?");
$stmt_delete_itens->bind_param("i", $id);
$stmt_delete_itens->execute();
$stmt_delete_itens->close();

// Deletar pedido principal
$stmt_delete_pedido = $conexao->prepare("DELETE FROM almox_pedidos_princ WHERE id = ?");

if ($stmt_delete_pedido->bind_param("i", $id) && $stmt_delete_pedido->execute()) {
    $descricao = "Pedido Almox ID {$pedido['id']} deletado: Solicitante: {$pedido['militar_solicitante']}, OS: {$pedido['id_os']}. Itens relacionados também deletados.";
    registrar_log($conexao, $usuarioLogado, 'Deletar Pedido Almox', $descricao, $id);

    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Erro ao deletar: ' . $stmt_delete_pedido->error]);
}

$stmt_delete_pedido->close();
?>
