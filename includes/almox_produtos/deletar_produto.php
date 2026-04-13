<?php
session_start();
include_once("../../conexao/config.php");
$pagina_id = 30;

require_once('../api/seguranca_json_deletar.php');


include_once("../../includes/funcoes/log.php");

header('Content-Type: application/json; charset=utf-8');

$id = $_POST['id'] ?? null;

if (!$id) {
    echo json_encode(['success' => false, 'message' => 'ID inválido']);
    exit;
}

// Buscar dados do produto antes de deletar
$stmt_select = $conexao->prepare("SELECT * FROM almox_produtos WHERE id = ?");
$stmt_select->bind_param("i", $id);
$stmt_select->execute();
$result = $stmt_select->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Produto não encontrado']);
    exit;
}

$produto = $result->fetch_assoc();
$stmt_select->close();

$usuarioLogado = $_SESSION['usuario_id'] ?? 0;

// Deletar produto
$stmt_delete = $conexao->prepare("DELETE FROM almox_produtos WHERE id = ?");
$stmt_delete->bind_param("i", $id);

if ($stmt_delete->execute()) {
    $descricao = "Produto ID $id deletado: Nome: {$produto['nome_produto']}, Código: {$produto['codigo_produto']}, Categoria: {$produto['categoria_produto']}, Estoque Mínimo: {$produto['estoque_minimo']}";
    registrar_log($conexao, $usuarioLogado, 'Deletar Produto', $descricao, $id);

    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Erro ao deletar: ' . $stmt_delete->error]);
}

$stmt_delete->close();
?>
