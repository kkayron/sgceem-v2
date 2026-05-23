<?php
// buscar_pedido.php
session_start();
header('Content-Type: application/json');

$pagina_ids = [17, 25];
require_once('../api/seguranca_json.php');

include_once("../../conexao/config.php");

$id = $_GET['id'] ?? null;
if (!$id || !is_numeric($id)) {
  echo json_encode(['sucesso' => false, 'mensagem' => 'ID inválido']);
  exit;
}

// Consulta principal do pedido
$stmtPedido = $conexao->prepare("SELECT * FROM fin_pedidos_forn WHERE id = ?");
$stmtPedido->bind_param("i", $id);
$stmtPedido->execute();
$resultPedido = $stmtPedido->get_result();

if ($resultPedido->num_rows === 0) {
  echo json_encode(['sucesso' => false, 'mensagem' => 'Pedido não encontrado']);
  exit;
}

$pedido = $resultPedido->fetch_assoc();
$stmtPedido->close();

// Consulta dos itens do pedido
$stmtItens = $conexao->prepare("SELECT * FROM fin_pedidos_forn_itens WHERE id_principal = ?");
$stmtItens->bind_param("i", $id);
$stmtItens->execute();
$resultItens = $stmtItens->get_result();

$itens = [];
while ($item = $resultItens->fetch_assoc()) {
  $itens[] = $item;
}
$stmtItens->close();

echo json_encode([
  'sucesso' => true,
  'pedido' => $pedido,
  'itens' => $itens
]);
