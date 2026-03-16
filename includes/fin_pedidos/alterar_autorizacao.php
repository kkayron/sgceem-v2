<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_start();
include_once("../../conexao/config.php");
include_once("../../includes/funcoes/log_pedido_financeiro.php");

header('Content-Type: application/json; charset=utf-8');

$id = $_POST['id'] ?? null;
$autorizacao = $_POST['autorizacao'] ?? null;

if (!$id || !in_array($autorizacao, ['sim', 'nao'])) {
    echo json_encode(['success' => false, 'message' => 'Dados inválidos']);
    exit;
}

// ============================
// BUSCAR DADOS ATUAIS DO PEDIDO
// ============================
$stmt_select = $conexao->prepare("
    SELECT 
        p.id,
        p.solicitante,
        p.id_os,
        p.autorizacao,
        os.id_frota
    FROM fin_pedidos_forn p
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

// ============================
// ATUALIZAR AUTORIZAÇÃO
// ============================
$stmt_update = $conexao->prepare("
    UPDATE fin_pedidos_forn 
    SET autorizacao = ?
    WHERE id = ?
");
$stmt_update->bind_param("si", $autorizacao, $id);

if ($stmt_update->execute()) {

    $usuarioLogado = $_SESSION['usuario_id'] ?? 0;

    $descricao = "Autorização do Pedido Financeiro ID {$pedido['id']} alterada de '{$pedido['autorizacao']}' para '{$autorizacao}'. "
               . "Solicitante: {$pedido['solicitante']}, "
               . "OS: {$pedido['id_os']}, "
               . "Frota: {$pedido['id_frota']}.";

    $id_os     = $pedido['id_os'];
    $id_frota  = $pedido['id_frota'];

    // ============================
    // REGISTRAR LOG (FINANCEIRO)
    // ============================
    registrar_log_financeiro(
        $conexao,
        $usuarioLogado,
        'Alterar Autorização Pedido Financeiro',
        $descricao,
        $id_frota,
        $id,        // pedido_financeiro_id
        $id_os
    );

    echo json_encode([
        'success' => true,
        'id_frota' => $id_frota
    ]);

} else {
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao atualizar autorização: ' . $stmt_update->error
    ]);
}

$stmt_update->close();
