<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

$pagina_id = 32;

require_once('../api/seguranca_json.php');

include_once("../../conexao/config.php");
include_once("../../includes/funcoes/log_pedido_almox.php");


$id = $_POST['id'] ?? null;
$autorizacao = $_POST['autorizacao'] ?? null;

if (!$id || !in_array($autorizacao, ['sim', 'nao'])) {
    echo json_encode(['success' => false, 'message' => 'Dados inválidos']);
    exit;
}

// Buscar dados atuais do pedido + id_frota da OS
$stmt_select = $conexao->prepare("
    SELECT 
        p.id,
        p.militar_solicitante,
        p.id_os,
        p.autorizacao,
        os.id_frota
    FROM almox_pedidos_princ p
    LEFT JOIN os_principal os 
        ON os.id = p.id_os
    WHERE p.id = ?
");
$stmt_select->bind_param("i", $id);
$stmt_select->execute();
$result = $stmt_select->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Pedido não encontrado']);
    exit;
}

$pedido = $result->fetch_assoc();
$stmt_select->close();

// Atualizar autorização
$stmt_update = $conexao->prepare("
    UPDATE almox_pedidos_princ 
    SET autorizacao = ?
    WHERE id = ?
");
$stmt_update->bind_param("si", $autorizacao, $id);

if ($stmt_update->execute()) {

    $usuarioLogado = $_SESSION['usuario_id'] ?? 0;

    $descricao = "Autorização do Pedido Almox ID {$pedido['id']} alterada de '{$pedido['autorizacao']}' para '{$autorizacao}'. "
               . "Solicitante: {$pedido['militar_solicitante']}, "
               . "OS: {$pedido['id_os']}, "
               . "Frota: {$pedido['id_frota']}.";
    
    $id_os = $pedido['id_os'];
    $id_frota = $pedido['id_frota'];

    registrar_log(
        $conexao,
        $usuarioLogado,
        'Alterar Autorização Pedido Almox',
        $descricao,
        $id_frota, $id, $id_os 
    );

    echo json_encode([
        'success' => true,
        'id_frota' => $pedido['id_frota']
    ]);

} else {
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao atualizar autorização: ' . $stmt_update->error
    ]);
}

$stmt_update->close();
