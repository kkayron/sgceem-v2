<?php
session_start();
require_once '../../conexao/config.php';
require_once '../../includes/funcoes/log.php';

header('Content-Type: application/json; charset=utf-8');

$id = $_POST['id'] ?? null;

if (!$id) {
  echo json_encode(['success' => false, 'message' => 'ID inválido']);
  exit;
}

// Buscar dados da ficha antes de deletar
$stmt_select = $conexao->prepare("SELECT id, motorista, destino FROM sta_fichas WHERE id = ?");
$stmt_select->bind_param("i", $id);
$stmt_select->execute();
$result = $stmt_select->get_result();

if ($result->num_rows === 0) {
  echo json_encode(['success' => false, 'message' => 'Ficha não encontrada']);
  exit;
}

$ficha = $result->fetch_assoc();
$stmt_select->close();

$usuarioLogado = $_SESSION['usuario_id'] ?? 0;

// Deletar a ficha
$stmt_delete = $conexao->prepare("DELETE FROM sta_fichas WHERE id = ?");
$stmt_delete->bind_param("i", $id);

if ($stmt_delete->execute()) {
  $descricao = "Ficha ID {$ficha['id']} deletada: Motorista: {$ficha['motorista']}, Destino: {$ficha['destino']}.";
  registrar_log($conexao, $usuarioLogado, 'Deletar Ficha', $descricao, $id);

  echo json_encode(['success' => true]);
} else {
  echo json_encode(['success' => false, 'message' => 'Erro ao deletar: ' . $stmt_delete->error]);
}

$stmt_delete->close();
