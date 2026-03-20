<?php

require_once __DIR__ . '/response.php';

function verificar_sessao()
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (!isset($_SESSION['usuario_id']) || empty($_SESSION['usuario'])) {
        http_response_code(401);
        api_response('erro', 'Sessão inválida ou expirada.');
    }

    return $_SESSION['usuario'];
}