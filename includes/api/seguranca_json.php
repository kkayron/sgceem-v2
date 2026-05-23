<?php
if(session_status() === PHP_SESSION_NONE){
    session_start();
}

header('Content-Type: application/json; charset=utf-8');

// =============================
// 🔒 EXPIRAÇÃO DE SESSÃO
// =============================
if (isset($_SESSION['ultimo_acesso'], $_SESSION['tempo_expiracao'])) {

    if ((time() - $_SESSION['ultimo_acesso']) > $_SESSION['tempo_expiracao']) {

        session_unset();
        session_destroy();

        http_response_code(401);

        echo json_encode([
            'status' => 'erro',
            'tipo' => 'sessao_expirada',
            'mensagem' => 'Sessão expirada por inatividade.'
        ]);
        exit;
    }

    $_SESSION['ultimo_acesso'] = time();
}

// =============================
// 🔒 VALIDA USUÁRIO
// =============================
if (!isset($_SESSION['usuario_id']) || empty($_SESSION['usuario'])) {
    echo json_encode([
        'status' => 'erro',
        'tipo' => 'sessao',
        'mensagem' => 'Sessão inválida'
    ]);
    exit;
}

// =============================
// 🔒 NORMALIZA PÁGINAS
// =============================
if (!isset($pagina_id) && !isset($pagina_ids)) {
    echo json_encode([
        'status' => 'erro',
        'mensagem' => 'Página(s) não definida(s)'
    ]);
    exit;
}

$paginas = [];

if (isset($pagina_ids) && is_array($pagina_ids)) {
    $paginas = $pagina_ids;
} else {
    $paginas = [$pagina_id];
}

// =============================
// 🔒 MODO DE VALIDAÇÃO
// =============================
// 'any' = basta ter acesso em uma
// 'all' = precisa ter em todas
$modo = 'any';

// =============================
// 🔒 VERIFICA PERMISSÃO DE ACESSO
// =============================
$temAcesso = ($modo === 'all');

foreach ($paginas as $pid) {

    $perm = $_SESSION['permissoes'][$pid]['pode_acessar'] ?? false;

    if ($modo === 'any' && $perm) {
        $temAcesso = true;
        break;
    }

    if ($modo === 'all' && !$perm) {
        $temAcesso = false;
        break;
    }
}

// =============================
// 🔒 BLOQUEIO
// =============================
if (!$temAcesso) {
    echo json_encode([
        'status' => 'erro',
        'tipo' => 'permissao',
        'mensagem' => 'Você não possui permissão para acessar esta funcionalidade.'
    ]);
    exit;
}

// =============================
// 🔒 EXPORTAR PERMISSÕES
// =============================
// usa a primeira página como base
$paginaBase = $paginas[0];

$pode_cadastrar = $_SESSION['permissoes'][$paginaBase]['pode_cadastrar'] ?? false;
$pode_editar    = $_SESSION['permissoes'][$paginaBase]['pode_editar'] ?? false;
$pode_deletar   = $_SESSION['permissoes'][$paginaBase]['pode_deletar'] ?? false;
$pode_importar  = $_SESSION['permissoes'][$paginaBase]['pode_importar'] ?? false;
$pode_acessar   = $_SESSION['permissoes'][$paginaBase]['pode_acessar'] ?? false;