<?php
// ==========================================
// FUNÇÕES EXCLUSIVAS DO ADMIN MASTER E GOD MODE (ROLE 1 E 16)
// ==========================================
// AUDITORIA 20/06/2026: Hardened por Comitê Técnico

// Terminal SQL de Emergência (SOMENTE LEITURA - BLINDAGEM REFORÇADA)
$router->post('/admin/master/sql', function() {
    $user_jwt = $GLOBALS['usuario_logado'];
    if ($user_jwt['role_id'] != 1 && $user_jwt['role_id'] != 16) {
        http_response_code(403);
        echo json_encode(['status' => 'erro', 'mensagem' => 'Acesso negado.']); exit;
    }
    
    $json = file_get_contents('php://input');
    $data = json_decode($json, true);
    $query = trim($data['query'] ?? '');
    
    if (empty($query)) {
        echo json_encode(['status' => 'erro', 'mensagem' => 'Query vazia.']); exit;
    }
    
    // ==========================================
    // BLINDAGEM REFORÇADA (Auditoria C2)
    // 1. Rejeitar múltiplos statements (stacked queries)
    // 2. Blacklist de keywords destrutivas em qualquer posição
    // 3. Validar primeiro token como comando de leitura
    // ==========================================
    
    // Anti-Stacked Queries: proibir ponto-e-vírgula
    if (strpos($query, ';') !== false) {
        $GLOBALS['logger']->alert('TENTATIVA DE STACKED QUERY NO TERMINAL SQL', [
            'query' => $query, 'admin_id' => $user_jwt['id']
        ]);
        http_response_code(403);
        echo json_encode(['status' => 'erro', 'mensagem' => 'Múltiplos comandos (;) não são permitidos.']);
        exit;
    }
    
    // Blacklist global de keywords destrutivas
    $blacklist = [
        'insert', 'update', 'delete', 'drop', 'alter', 'create', 'truncate',
        'grant', 'revoke', 'into outfile', 'into dumpfile', 'load_file',
        'load data', 'rename', 'replace', 'call', 'exec', 'execute',
        'set ', 'lock ', 'unlock'
    ];
    $queryLower = strtolower($query);
    foreach ($blacklist as $kw) {
        if (str_contains($queryLower, $kw)) {
            $GLOBALS['logger']->alert('KEYWORD DESTRUTIVA DETECTADA NO TERMINAL SQL', [
                'keyword' => $kw, 'query' => $query, 'admin_id' => $user_jwt['id']
            ]);
            http_response_code(403);
            echo json_encode(['status' => 'erro', 'mensagem' => "Keyword proibida detectada: '$kw'. Apenas leitura é permitida."]);
            exit;
        }
    }
    
    // Whitelist de primeiro token
    $comandos_permitidos = ['select', 'show', 'describe', 'desc', 'explain'];
    $primeiro_token = strtolower(strtok($query, " \t\n\r"));
    
    if (!in_array($primeiro_token, $comandos_permitidos)) {
        $GLOBALS['logger']->alert('ADMIN MASTER TENTOU EXECUTAR COMANDO DESTRUTIVO', [
            'query' => $query, 'admin_id' => $user_jwt['id']
        ]);
        http_response_code(403);
        echo json_encode(['status' => 'erro', 'mensagem' => 'Apenas comandos de leitura (SELECT, SHOW, DESCRIBE) são permitidos pelo terminal.']);
        exit;
    }
    
    // Limitar resultado para evitar dump massivo
    if ($primeiro_token === 'select' && !preg_match('/\blimit\b/i', $query)) {
        $query .= ' LIMIT 500';
    }
    
    try {
        $resultado = \Illuminate\Database\Capsule\Manager::select($query);
        
        $GLOBALS['logger']->warning('ADMIN MASTER EXECUTOU SQL (READ-ONLY)', ['query' => $query, 'admin_id' => $user_jwt['id']]);
        
        echo json_encode([
            'status' => 'sucesso', 
            'mensagem' => 'Executado com sucesso.',
            'dados' => $resultado
        ]);
    } catch (\Exception $e) {
        echo json_encode(['status' => 'erro', 'mensagem' => $e->getMessage()]);
    }
});

// Gerador de Backup SQL Dump (Auditoria C3: shell_exec sanitizado)
$router->get('/admin/master/dump', function() {
    $user_jwt = $GLOBALS['usuario_logado'];
    if ($user_jwt['role_id'] != 1 && $user_jwt['role_id'] != 16) {
        http_response_code(403);
        echo "Acesso Negado"; exit;
    }
    
    // Credenciais do .env (nunca hardcoded)
    $dbName = $_ENV['DB_DATABASE'] ?? '';
    $dbUser = $_ENV['DB_USERNAME'] ?? '';
    $dbPass = $_ENV['DB_PASSWORD'] ?? '';
    $dbHost = $_ENV['DB_HOST'] ?? '127.0.0.1';
    
    // Sanitização contra Command Injection (Auditoria C3)
    $passArg = $dbPass ? '-p' . escapeshellarg($dbPass) : '';
    $filename = 'backup_sgceem_' . date('Y_m_d_His') . '.sql';
    
    $cmd = sprintf(
        'mysqldump -h %s -u %s %s %s 2>/dev/null',
        escapeshellarg($dbHost),
        escapeshellarg($dbUser),
        $passArg,
        escapeshellarg($dbName)
    );
    $output = shell_exec($cmd);
    
    if (!$output) {
        echo "Falha ao gerar o Dump SQL. O mysqldump está instalado e no PATH?"; exit;
    }
    
    $GLOBALS['logger']->warning('ADMIN MASTER GEROU BACKUP DO BANCO', ['admin_id' => $user_jwt['id']]);
    
    header('Content-Type: application/sql');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    echo $output;
});

// ==========================================
// ROTA DE LEITURA DO .ENV (SOMENTE LEITURA - Auditoria C1)
// A rota POST de edição do .env foi REMOVIDA por representar
// risco crítico de Remote Code Execution.
// O .env deve ser editado EXCLUSIVAMENTE via terminal/SSH.
// ==========================================
$router->get('/admin/master/env', function() {
    $user_jwt = $GLOBALS['usuario_logado'];
    if ($user_jwt['role_id'] != 1 && $user_jwt['role_id'] != 16) {
        http_response_code(403); echo json_encode(['status'=>'erro']); exit;
    }
    
    // Retorna apenas chaves não-sensíveis para visualização
    $safe_keys = ['APP_ENV', 'APP_DEBUG', 'APP_URL', 'DB_CONNECTION', 'DB_HOST', 'DB_PORT', 'DB_DATABASE'];
    $env_file = __DIR__ . '/../../../.env';
    
    $result = [];
    if (file_exists($env_file)) {
        $lines = file($env_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            if (str_starts_with(trim($line), '#')) continue;
            $parts = explode('=', $line, 2);
            if (count($parts) === 2) {
                $key = trim($parts[0]);
                // Mascarar valores sensíveis
                if (in_array($key, ['DB_PASSWORD', 'APP_KEY', 'DB_USERNAME'])) {
                    $result[$key] = '********';
                } elseif (in_array($key, $safe_keys)) {
                    $result[$key] = trim($parts[1]);
                }
            }
        }
    }
    
    echo json_encode(['status' => 'sucesso', 'env' => $result]);
});

// NOTA DE AUDITORIA: A rota POST /admin/master/env foi ELIMINADA.
// Motivo: Permitia reescrita completa do .env via HTTP, habilitando
// Remote Code Execution, exfiltração de dados e quebra total de autenticação.
