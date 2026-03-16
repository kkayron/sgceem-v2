<?php
// Evita qualquer saída antes do JSON
ob_start();
session_start();
include_once('../../conexao/config.php');

// Configura retorno JSON
header('Content-Type: application/json; charset=utf-8');

// Recebe ID da função
$id = intval($_GET['id'] ?? $_POST['id'] ?? 0);

if ($id <= 0) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'ID da função inválido.']);
    exit;
}

// Busca função no banco
$stmt = $conexao->prepare("SELECT id, nome, descricao FROM funcoes WHERE id = ?");
if (!$stmt) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Erro ao preparar consulta: '.$conexao->error]);
    exit;
}
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Função não encontrada.']);
    exit;
}

$funcao = $result->fetch_assoc();

echo json_encode(['status' => 'sucesso', 'funcao' => $funcao]);

$stmt->close();
ob_end_flush();
exit;
