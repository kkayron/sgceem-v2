<?php
include_once('../../conexao/config.php');

$id = $_GET['id'] ?? null;

if (!$id) {
    echo json_encode(['erro' => true, 'mensagem' => 'ID inválido']);
    exit;
}


$stmt = $conexao->prepare("SELECT id, postograd, nomeguerra, batalhao, usuario, funcao, status, nomecompleto, foto FROM usuarios WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$usuario = $result->fetch_assoc();
$stmt->close();

if ($usuario) {
    echo json_encode($usuario);
} else {
    echo json_encode(['erro' => true, 'mensagem' => 'Usuário não encontrado']);
}
?>
