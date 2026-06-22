<?php
// ==========================================
// FUNÇÕES EXCLUSIVAS DO ADMIN NORMAL E SUPERIOR (ROLE 1 e 16)
// ==========================================

// Configurações do Sistema (Ler)
$router->get('/admin/config', function() {
    $user_jwt = $GLOBALS['usuario_logado'];
    if ($user_jwt['role_id'] != 1 && $user_jwt['role_id'] != 16) {
        http_response_code(403);
        echo json_encode(['status' => 'erro', 'mensagem' => 'Acesso negado.']); exit;
    }
    $configs = \Illuminate\Database\Capsule\Manager::table('config_sistema')->get();
    echo json_encode(['status' => 'sucesso', 'dados' => $configs]);
});

// Configurações do Sistema (Salvar)
$router->post('/admin/config', function() {
    $user_jwt = $GLOBALS['usuario_logado'];
    if ($user_jwt['role_id'] != 1 && $user_jwt['role_id'] != 16) {
        http_response_code(403);
        echo json_encode(['status' => 'erro', 'mensagem' => 'Acesso negado.']); exit;
    }
    
    $json = file_get_contents('php://input');
    $data = json_decode($json, true);
    
    foreach ($data as $chave => $valor) {
        \Illuminate\Database\Capsule\Manager::table('config_sistema')
            ->where('chave', $chave)
            ->update(['valor' => $valor]);
    }
    
    $GLOBALS['logger']->info('Configuração de sistema atualizada pelo Admin', ['admin_id' => $user_jwt['id'], 'data' => $data]);
    echo json_encode(['status' => 'sucesso']);
});

// Reset de Senha Simples
$router->post('/admin/reset-senha', function() {
    $user_jwt = $GLOBALS['usuario_logado'];
    if ($user_jwt['role_id'] != 1 && $user_jwt['role_id'] != 16) {
        http_response_code(403);
        echo json_encode(['status' => 'erro', 'mensagem' => 'Acesso negado.']); exit;
    }
    
    $json = file_get_contents('php://input');
    $data = json_decode($json, true);
    $alvo_id = $data['usuario_id'] ?? 0;
    
    if (!$alvo_id) {
        echo json_encode(['status' => 'erro', 'mensagem' => 'Usuário inválido']); exit;
    }
    
    // Gera senha temporária aleatória de 10 caracteres
    $senha_temporaria = substr(str_shuffle('abcdefghijkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789!@#'), 0, 10);
    $nova_senha_hash = password_hash($senha_temporaria, PASSWORD_BCRYPT);
    \Illuminate\Database\Capsule\Manager::table('usuarios')->where('id', $alvo_id)->update(['senha' => $nova_senha_hash]);
    
    $GLOBALS['logger']->info('Admin resetou senha de um usuário', ['admin_id' => $user_jwt['id'], 'alvo_id' => $alvo_id]);
    echo json_encode(['status' => 'sucesso', 'mensagem' => 'Senha resetada para: ' . $senha_temporaria . ' (anote agora, não será exibida novamente)']);
});

// Auditoria Rápida (Últimos 50 logs do Monolog)
$router->get('/admin/auditoria', function() {
    $user_jwt = $GLOBALS['usuario_logado'];
    if ($user_jwt['role_id'] != 1 && $user_jwt['role_id'] != 16) {
        http_response_code(403);
        echo json_encode(['status' => 'erro', 'mensagem' => 'Acesso negado.']); exit;
    }
    
    $log_file = __DIR__ . '/../../../logs/operacional.log';
    if (!file_exists($log_file)) {
        echo json_encode(['status' => 'sucesso', 'logs' => []]); exit;
    }
    
    $lines = file($log_file);
    // Pegar as ultimas 50 linhas
    $lines = array_slice($lines, -50);
    $lines = array_reverse($lines); // Mais recentes primeiro
    
    echo json_encode(['status' => 'sucesso', 'logs' => $lines]);
});
