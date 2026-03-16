<?php
header('Content-Type: application/json');
session_start();
include_once('../../conexao/config.php');

$id = intval($_POST['id'] ?? 0);

if ($id <= 0) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Página inválida.']);
    exit;
}

// Remove permissões associadas antes
$conexao->query("DELETE FROM permissoes WHERE pagina_id = $id");

// Deleta a página
$stmt = $conexao->prepare("DELETE FROM paginas WHERE id = ?");
$stmt->bind_param("i", $id);

if ($stmt->execute()) {
    echo json_encode(['status' => 'sucesso', 'mensagem' => 'Página excluída com sucesso!']);
} else {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Erro ao excluir página.']);
}

$stmt->close();
?>
