<?php
// Evita qualquer saída antes do JSON
ob_start();
session_start();
include_once('../../conexao/config.php');

// Configura retorno JSON
header('Content-Type: application/json; charset=utf-8');

$nome = trim($_POST['nome'] ?? '');
$descricao = trim($_POST['descricao'] ?? '');

// Validação
if (!$nome) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'O nome da função é obrigatório.']);
    exit;
}

// Verifica se já existe função com o mesmo nome
$stmtCheck = $conexao->prepare("SELECT id FROM funcoes WHERE nome = ?");
if (!$stmtCheck) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Erro ao preparar consulta: '.$conexao->error]);
    exit;
}
$stmtCheck->bind_param("s", $nome);
$stmtCheck->execute();
$stmtCheck->store_result();

if ($stmtCheck->num_rows > 0) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Já existe uma função com este nome.']);
    exit;
}
$stmtCheck->close();

// Inserir no banco
$stmt = $conexao->prepare("INSERT INTO funcoes (nome, descricao) VALUES (?, ?)");
if (!$stmt) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Erro ao preparar insert: '.$conexao->error]);
    exit;
}
$stmt->bind_param("ss", $nome, $descricao);

if ($stmt->execute()) {
    echo json_encode(['status' => 'sucesso', 'mensagem' => 'Função cadastrada com sucesso!']);
} else {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Erro ao cadastrar função: '.$stmt->error]);
}
$stmt->close();

// Limpa qualquer buffer antes de enviar JSON
ob_end_flush();
exit;
