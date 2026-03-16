<?php
session_start();
header('Content-Type: application/json'); // Importante para o retorno ser interpretado corretamente como JSON

include_once("../../conexao/config.php");
include_once("../../includes/funcoes/log.php");

$id = $_POST['id'] ?? null;

if (!$id) {
    echo json_encode(['success' => false, 'message' => 'ID inválido']);
    exit;
}

// Buscar dados do usuário antes de deletar
$stmt_select = $conexao->prepare("SELECT postograd, nomeguerra, funcao, status FROM usuarios WHERE id = ?");
$stmt_select->bind_param("i", $id);
$stmt_select->execute();
$result = $stmt_select->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Usuário não encontrado']);
    exit;
}

$usuario = $result->fetch_assoc();
$stmt_select->close();

// Deletar o usuário
$stmt_delete = $conexao->prepare("DELETE FROM usuarios WHERE id = ?");
$stmt_delete->bind_param("i", $id);

if ($stmt_delete->execute()) {
    // Registrar log da exclusão
    $usuarioLogado = $_SESSION['usuario_id'] ?? 0;
    $descricao = "Usuário ID $id deletado: {$usuario['postograd']} {$usuario['nomeguerra']}, Função: {$usuario['funcao']}, Status: {$usuario['status']}";
    registrar_log($conexao, $usuarioLogado, 'Deletar usuário', $descricao);

    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Erro ao deletar: ' . $stmt_delete->error]);
}

$stmt_delete->close();
?>
