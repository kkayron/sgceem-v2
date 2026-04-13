<?php
session_start();
include_once('../../conexao/config.php');

// 🔒 Verifica login
if (!isset($_SESSION['usuario_id'])) {
    http_response_code(401);
    exit;
}

// 🔒 Permite apenas AJAX
if (
    !isset($_SERVER['HTTP_X_REQUESTED_WITH']) || 
    strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) !== 'xmlhttprequest'
) {
    http_response_code(403);
    exit('Acesso inválido');
}

$sql = "
SELECT nomeguerra, postograd
FROM usuarios
WHERE ultima_atividade >= NOW() - INTERVAL 1 MINUTE
ORDER BY nomeguerra
";

$result = $conexao->query($sql);

$usuarios = [];

while ($row = $result->fetch_assoc()) {
    $usuarios[] = $row['postograd'] . " " . $row['nomeguerra'];
}

header('Content-Type: application/json');
echo json_encode($usuarios);