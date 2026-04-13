<?php
session_start();
require_once '../api/seguranca_deletar.php';

$permissoes = verificarPermissao(31);

$pode_cadastrar = $permissoes['cadastrar'];
$pode_editar    = $permissoes['editar'];
$pode_deletar   = $permissoes['deletar'];
$pode_importar  = $permissoes['importar'];
include_once("../../conexao/config.php");

$id = $_POST['id'] ?? 0;

if (!$id) {
    echo json_encode(['success' => false, 'message' => 'ID da entrada não informado']);
    exit;
}

try {
    // 1) Deleta os itens relacionados à entrada
    $sqlDelItens = "DELETE FROM almox_entradas_itens WHERE id_entrada = ?";
    $stmt = $conexao->prepare($sqlDelItens);
    $stmt->bind_param("i", $id);
    if (!$stmt->execute()) {
        throw new Exception("Erro ao deletar itens da entrada: " . $stmt->error);
    }
    $stmt->close();

    // 2) Deleta a própria entrada
    $sqlDelEntrada = "DELETE FROM almox_entradas WHERE id = ?";
    $stmt = $conexao->prepare($sqlDelEntrada);
    $stmt->bind_param("i", $id);
    if (!$stmt->execute()) {
        throw new Exception("Erro ao deletar entrada: " . $stmt->error);
    }
    $stmt->close();

    echo json_encode(['success' => true]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
