<?php
if(session_status() === PHP_SESSION_NONE){
    session_start();
}

header('Content-Type: application/json; charset=utf-8');

if (!isset($pagina_id)) {
    echo json_encode([
        'status' => 'erro',
        'mensagem' => 'Página não definida para verificação de permissão.'
    ]);
    exit;
}

if (!isset($_SESSION['usuario_id']) || empty($_SESSION['usuario'])) {
    echo json_encode([
        'status' => 'erro',
        'tipo' => 'sessao',
        'mensagem' => 'Sessão inválida'
    ]);
    exit;
}

if (empty($_SESSION['permissoes'][$pagina_id]['pode_editar'])) {
    echo json_encode([
        'status' => 'erro',
        'tipo' => 'permissao',
        'mensagem' => 'Você não possui permissão para editar esta funcionalidade.'
    ]);
    exit;
}

/* Permissões disponíveis para uso posterior */

$pode_cadastrar = $_SESSION['permissoes'][$pagina_id]['pode_cadastrar'] ?? false;
$pode_editar    = $_SESSION['permissoes'][$pagina_id]['pode_editar'] ?? false;
$pode_deletar   = $_SESSION['permissoes'][$pagina_id]['pode_deletar'] ?? false;
$pode_importar  = $_SESSION['permissoes'][$pagina_id]['pode_importar'] ?? false;