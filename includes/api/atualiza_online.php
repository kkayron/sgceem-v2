<?php
session_start();
include_once('../../conexao/config.php');

// 🔒 Verifica login
if (!isset($_SESSION['usuario_id'])) {
    http_response_code(401);
    exit;
}

// 🔒 Verifica se é AJAX
if (
    !isset($_SERVER['HTTP_X_REQUESTED_WITH']) || 
    strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) !== 'xmlhttprequest'
) {
    http_response_code(403);
    exit('Acesso inválido');
}

// 🔒 Rate limit (5s)
if (!isset($_SESSION['last_online_update'])) {
    $_SESSION['last_online_update'] = 0;
}

if (time() - $_SESSION['last_online_update'] < 5) {
    exit;
}

$_SESSION['last_online_update'] = time();

$usuario_id = $_SESSION['usuario_id'];

// Atualiza atividade
$stmt = $conexao->prepare("
    UPDATE usuarios 
    SET ultima_atividade = NOW() 
    WHERE id = ?
");

$stmt->bind_param("i", $usuario_id);
$stmt->execute();
$stmt->close();

echo json_encode(["status" => "ok"]);