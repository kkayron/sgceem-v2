<?php
// Evita qualquer saída antes do JSON
ob_start();
session_start();
include_once('../../conexao/config.php');

// Configura retorno JSON
header('Content-Type: application/json; charset=utf-8');

// Recebe os dados do POST
$id        = intval($_POST['id'] ?? 0);
$nome      = trim($_POST['nome'] ?? '');
$descricao = trim($_POST['descricao'] ?? '');

// Valida ID
if ($id <= 0) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'ID da função inválido.']);
    exit;
}

// Valida nome
if (!$nome) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'O nome da função é obrigatório.']);
    exit;
}

// Verifica se já existe outra função com o mesmo nome
$stmtCheck = $conexao->prepare("SELECT id FROM funcoes WHERE nome = ? AND id != ?");
$stmtCheck->bind_param("si", $nome, $id);
$stmtCheck->execute();
$stmtCheck->store_result();

if ($stmtCheck->num_rows > 0) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Já existe outra função com este nome.']);
    exit;
}

// Atualiza no banco
$stmt = $conexao->prepare("UPDATE funcoes SET nome = ?, descricao = ? WHERE id = ?");
$stmt->bind_param("ssi", $nome, $descricao, $id);

if ($stmt->execute()) {
    echo json_encode(['status' => 'sucesso', 'mensagem' => 'Função atualizada com sucesso!']);
} else {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Erro ao atualizar função.']);
}

$stmt->close();
ob_end_flush();
exit;
