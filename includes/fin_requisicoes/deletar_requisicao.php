<?php
session_start();
include_once("../../conexao/config.php");
include_once("../../includes/funcoes/log.php");

header('Content-Type: application/json; charset=utf-8');

$id = $_POST['id'] ?? null;

if (!$id) {
    echo json_encode(['success' => false, 'message' => 'ID inválido']);
    exit;
}

// Buscar dados da requisição antes de deletar
$stmt_select = $conexao->prepare("SELECT id, requisitante, destinatario FROM fin_requisicao WHERE id = ?");
$stmt_select->bind_param("i", $id);
$stmt_select->execute();
$result = $stmt_select->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Requisição não encontrada']);
    exit;
}

$requisicao = $result->fetch_assoc();
$stmt_select->close();

$usuarioLogado = $_SESSION['usuario_id'] ?? 0;

// Deletar itens da requisição
$stmt_delete_itens = $conexao->prepare("DELETE FROM fin_requisicao_itens WHERE id_requisicao = ?");
$stmt_delete_itens->bind_param("i", $id);
$stmt_delete_itens->execute();
$stmt_delete_itens->close();

// Deletar empenho relacionado
$stmt_delete_empenho = $conexao->prepare("DELETE FROM fin_empenhos WHERE id_requisicao = ?");
$stmt_delete_empenho->bind_param("i", $id);
$stmt_delete_empenho->execute();
$stmt_delete_empenho->close();

// Deletar a requisição principal
$stmt_delete_requisicao = $conexao->prepare("DELETE FROM fin_requisicao WHERE id = ?");
$stmt_delete_requisicao->bind_param("i", $id);

if ($stmt_delete_requisicao->execute()) {
    $descricao = "Requisição ID {$requisicao['id']} deletada: Requisitante: {$requisicao['requisitante']}, Destinatário: {$requisicao['destinatario']}. Empenho relacionado também deletado.";
    registrar_log($conexao, $usuarioLogado, 'Deletar Requisição', $descricao, $id);

    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Erro ao deletar: ' . $stmt_delete_requisicao->error]);
}

$stmt_delete_requisicao->close();
?>
