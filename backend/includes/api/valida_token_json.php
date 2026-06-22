<?php
// 🔒 Iniciar sessão se necessário
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 🔒 Só permite POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'status' => 'erro',
        'mensagem' => 'Método não permitido.'
    ]);
    exit;
}

// 🔒 Bloquear acesso direto (exigir AJAX)
if (
    empty($_SERVER['HTTP_X_REQUESTED_WITH']) ||
    strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) !== 'xmlhttprequest'
) {
    http_response_code(403);
    echo json_encode([
        'status' => 'erro',
        'mensagem' => 'Acesso direto não permitido.'
    ]);
    exit;
}

// 🔒 Verificar existência do token
if (
    empty($_POST['csrf_token']) ||
    empty($_SESSION['csrf_token'])
) {
    http_response_code(403);
    echo json_encode([
        'status' => 'erro',
        'mensagem' => 'Token ausente.'
    ]);
    exit;
}

// 🔒 Verificar validade
if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    http_response_code(403);
    echo json_encode([
        'status' => 'erro',
        'mensagem' => 'Token inválido.'
    ]);
    exit;
}

// 🔒 Verificar expiração (20 min)
$csrf_expira = 1200;

if (
    empty($_SESSION['csrf_token_time']) ||
    (time() - $_SESSION['csrf_token_time']) > $csrf_expira
) {
    http_response_code(403);
    echo json_encode([
        'status' => 'erro',
        'mensagem' => 'Token expirado.'
    ]);
    exit;
}

// 🔄 ROTACIONAR TOKEN (ANTI-REPLAY)
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
$_SESSION['csrf_token_time'] = time();
?>