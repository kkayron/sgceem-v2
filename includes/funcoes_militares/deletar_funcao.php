<?php
// Evita qualquer saída antes do JSON
ob_start();
session_start();
include_once('../../conexao/config.php');

// Configura retorno JSON
header('Content-Type: application/json; charset=utf-8');

// Recebe ID da função
$id = intval($_POST['id'] ?? 0);

if ($id <= 0) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'ID da função inválido.']);
    exit;
}

// Verifica se existem usuários vinculados a essa função
$stmtCheck = $conexao->prepare("SELECT COUNT(*) as total FROM usuarios WHERE funcao = ?");
if (!$stmtCheck) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Erro ao preparar consulta: '.$conexao->error]);
    exit;
}
$stmtCheck->bind_param("i", $id);
$stmtCheck->execute();
$resultCheck = $stmtCheck->get_result();
$totalUsuarios = $resultCheck->fetch_assoc()['total'];
$stmtCheck->close();

if ($totalUsuarios > 0) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Não é possível deletar esta função, pois existem usuários vinculados.']);
    exit;
}

// Deletar função
$stmt = $conexao->prepare("DELETE FROM funcoes WHERE id = ?");
if (!$stmt) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Erro ao preparar exclusão: '.$conexao->error]);
    exit;
}
$stmt->bind_param("i", $id);

if ($stmt->execute()) {
    echo json_encode(['status' => 'sucesso', 'mensagem' => 'Função deletada com sucesso!']);
} else {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Erro ao deletar função: '.$stmt->error]);
}
$stmt->close();

ob_end_flush();
exit;
