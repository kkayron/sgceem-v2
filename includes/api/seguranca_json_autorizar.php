<?php
if(session_status() === PHP_SESSION_NONE){
    session_start();
}

header('Content-Type: application/json; charset=utf-8');

// =============================
// 🔒 SESSÃO / EXPIRAÇÃO
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

// aceita tanto $pagina_id quanto $pagina_ids
$paginas = [];

if (isset($pagina_ids) && is_array($pagina_ids)) {
    $paginas = $pagina_ids;
} else {
    $paginas = [$pagina_id];
}

// =============================
// 🔒 TIPO DE VALIDAÇÃO
// =============================
// 'any' = basta ter em uma
// 'all' = precisa ter em todas
$modo = 'any';

// =============================
// 🔒 VERIFICA PERMISSÕES
// =============================
$temPermissao = ($modo === 'all');

foreach ($paginas as $pid) {

    $perm = $_SESSION['permissoes'][$pid]['pode_autorizar'] ?? false;

    if ($modo === 'any' && $perm) {
        $temPermissao = true;
        break;
    }

    if ($modo === 'all' && !$perm) {
        $temPermissao = false;
        break;
    }
}

// =============================
// 🔒 BLOQUEIO
// =============================
if (!$temPermissao) {
    echo json_encode([
        'status' => 'erro',
        'tipo' => 'permissao',
        'mensagem' => 'Sem permissão para autorizar nesta funcionalidade.'
    ]);
    exit;
}

// =============================
// 🔒 EXPORTA PERMISSÕES (opcional)
// pega da primeira página válida
// =============================
$paginaBase = $paginas[0];

$pode_cadastrar = $_SESSION['permissoes'][$paginaBase]['pode_cadastrar'] ?? false;
$pode_editar    = $_SESSION['permissoes'][$paginaBase]['pode_editar'] ?? false;
$pode_deletar   = $_SESSION['permissoes'][$paginaBase]['pode_deletar'] ?? false;
$pode_importar  = $_SESSION['permissoes'][$paginaBase]['pode_importar'] ?? false;
$pode_autorizar = $_SESSION['permissoes'][$paginaBase]['pode_autorizar'] ?? false;