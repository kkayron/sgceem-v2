<?php
header('Content-Type: application/json');
session_start();
include_once('../../conexao/config.php');

$id = intval($_GET['id'] ?? 0);
if ($id <= 0) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'ID inválido.']);
    exit;
}

$stmt = $conexao->prepare("SELECT * FROM paginas_principal WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$resultado = $stmt->get_result();

if ($topico = $resultado->fetch_assoc()) {
    echo json_encode(['status' => 'sucesso', 'topico' => $topico]);
} else {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Tópico não encontrado.']);
}

$stmt->close();
?>
