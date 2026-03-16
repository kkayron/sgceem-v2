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

// Buscar dados do pregão antes de deletar
$stmt_select = $conexao->prepare("SELECT id, nmr_pregao, ano_pregao, tipo_pregao FROM fin_pregao WHERE id = ?");
$stmt_select->bind_param("i", $id);
$stmt_select->execute();
$result = $stmt_select->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Pregão não encontrado']);
    exit;
}

$pregao = $result->fetch_assoc();
$stmt_select->close();

$usuarioLogado = $_SESSION['usuario_id'] ?? 0;

// Deletar itens do pregão
$stmt_delete_itens = $conexao->prepare("DELETE FROM fin_pregao_itens WHERE id_pregao = ?");
$stmt_delete_itens->bind_param("i", $id);
$stmt_delete_itens->execute();
$stmt_delete_itens->close();

// Deletar o pregão principal
$stmt_delete_pregao = $conexao->prepare("DELETE FROM fin_pregao WHERE id = ?");
$stmt_delete_pregao->bind_param("i", $id);

if ($stmt_delete_pregao->execute()) {
    $descricao = "Pregão ID {$pregao['id']} deletado: Número: {$pregao['nmr_pregao']}/{$pregao['ano_pregao']}, Tipo: {$pregao['tipo_pregao']}";
    registrar_log($conexao, $usuarioLogado, 'Deletar Pregão', $descricao, $id);

    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Erro ao deletar: ' . $stmt_delete_pregao->error]);
}

$stmt_delete_pregao->close();
?>
