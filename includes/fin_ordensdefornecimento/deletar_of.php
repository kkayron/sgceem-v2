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

// Buscar dados da ordem antes de deletar
$stmt_select = $conexao->prepare("
    SELECT o.id, o.empresa_nome, o.status, e.nmr_empenho
    FROM fin_ordemforn o
    LEFT JOIN fin_empenhos e ON o.id_empenho = e.id
    WHERE o.id = ?
");
$stmt_select->bind_param("i", $id);
$stmt_select->execute();
$result = $stmt_select->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Ordem de fornecimento não encontrada']);
    exit;
}

$ordem = $result->fetch_assoc();
$stmt_select->close();

$usuarioLogado = $_SESSION['usuario_id'] ?? 0;

// Deletar pedidos vinculados
$stmt_delete_pedidos = $conexao->prepare("DELETE FROM fin_ordemforn_pedidos WHERE id_ordemforn = ?");
$stmt_delete_pedidos->bind_param("i", $id);
$stmt_delete_pedidos->execute();
$stmt_delete_pedidos->close();

// Deletar a ordem principal
$stmt_delete_ordem = $conexao->prepare("DELETE FROM fin_ordemforn WHERE id = ?");
$stmt_delete_ordem->bind_param("i", $id);

if ($stmt_delete_ordem->execute()) {
    $descricao = "Ordem de Fornecimento ID {$ordem['id']} deletada — Empresa: {$ordem['empresa_nome']} — Empenho: {$ordem['nmr_empenho']} — Status: {$ordem['status']}";
    registrar_log($conexao, $usuarioLogado, 'Deletar Ordem de Fornecimento', $descricao, $id);

    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Erro ao deletar: ' . $stmt_delete_ordem->error]);
}

$stmt_delete_ordem->close();
?>
