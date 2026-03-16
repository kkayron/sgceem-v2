<?php
include_once '../../conexao/config.php';
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  echo json_encode(['status' => 'erro', 'mensagem' => 'Método inválido.']);
  exit;
}

$batalhao = $_POST['batalhao'] ?? '';
$destino  = trim($_POST['destino'] ?? '');

if (empty($batalhao) || !is_numeric($batalhao)) {
  echo json_encode(['status' => 'erro', 'mensagem' => 'Selecione um batalhão válido.']);
  exit;
}

if (empty($destino)) {
  echo json_encode(['status' => 'erro', 'mensagem' => 'O campo "Destino" é obrigatório.']);
  exit;
}

// Verifica duplicidade
$stmt = $conexao->prepare("SELECT id FROM config_destinos WHERE batalhao = ? AND destino = ?");
$stmt->bind_param("is", $batalhao, $destino);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows > 0) {
  echo json_encode(['status' => 'erro', 'mensagem' => 'Já existe um destino com este nome neste batalhão.']);
  exit;
}
$stmt->close();

// Inserção
$stmt = $conexao->prepare("INSERT INTO config_destinos (batalhao, destino) VALUES (?, ?)");
$stmt->bind_param("is", $batalhao, $destino);

if ($stmt->execute()) {
  echo json_encode(['status' => 'sucesso', 'mensagem' => 'Destino cadastrado com sucesso!']);
} else {
  echo json_encode(['status' => 'erro', 'mensagem' => 'Erro ao cadastrar o destino.']);
}

$stmt->close();
$conexao->close();
