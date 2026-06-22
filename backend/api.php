<?php
// ==========================================
// CENTRAL API GATEWAY (Bramus Router)
// ==========================================

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

header('Content-Type: application/json; charset=utf-8');

// ==========================================
// CORS SEGURO: Restringe para origens confiáveis
// ==========================================
$allowed_origins = [
    'http://localhost:5173',  // Vite dev
    'http://localhost:3000',  // Vite dev alternate
    'http://localhost:8000',  // PHP embutido
    'http://127.0.0.1:5173',
    'http://127.0.0.1:3000',
    'http://127.0.0.1:8000',
];

if (!empty($_ENV['FRONTEND_URL'])) {
    $allowed_origins[] = rtrim($_ENV['FRONTEND_URL'], '/');
}

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (in_array($origin, $allowed_origins) || (str_contains($origin, 'vercel.app'))) {
    header('Access-Control-Allow-Origin: ' . $origin);
} else {
    header('Access-Control-Allow-Origin: ' . $allowed_origins[0]);
}
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Authorization, Content-Type, Accept');
header('Access-Control-Max-Age: 86400');

// Preflight CORS (Navegadores enviam OPTIONS antes de PUT/DELETE)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/bootstrap.php';

// Inicializar o Roteador
$router = new \Bramus\Router\Router();

// Define a base path já que o Vite faz proxy via /api ou acessamos via /api.php localmente
$basePath = (strpos($_SERVER['REQUEST_URI'], '/api.php') === 0) ? '/api.php' : '/api';
$router->setBasePath($basePath);

// Middleware de Segurança (Protege todas as rotas em /v1, EXCETO /v1/auth)
$router->before('GET|POST|PUT|DELETE', '/v1/(?!auth).*', function() {
    $headers = function_exists('getallheaders') ? getallheaders() : [];
    if (empty($headers)) {
        foreach ($_SERVER as $name => $value) {
            if (substr($name, 0, 5) == 'HTTP_') {
                $headers[str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))))] = $value;
            }
        }
    }
    
    $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    
    if (preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
        $jwt = $matches[1];
        $secret_key = $_ENV['APP_KEY'] ?? null;
        if (!$secret_key) {
            http_response_code(500);
            echo json_encode(['status' => 'erro', 'mensagem' => 'Erro de Configuração: APP_KEY ausente.']);
            exit();
        }
        
        try {
            $decoded = \Firebase\JWT\JWT::decode($jwt, new \Firebase\JWT\Key($secret_key, 'HS256'));
            
            // Auditoria A6: Checagem de Blacklist
            $blacklist_file = __DIR__ . '/../logs/jwt_blacklist.json';
            if (file_exists($blacklist_file)) {
                $blacklist = json_decode(file_get_contents($blacklist_file), true);
                if (isset($blacklist[$jwt])) {
                    http_response_code(401);
                    echo json_encode(['status' => 'erro', 'mensagem' => 'Sessão encerrada (Token na Blacklist).']);
                    exit();
                }
            }
            
            // Armazena dados do usuário no router/global para uso nas rotas
            $GLOBALS['usuario_logado'] = (array) $decoded->data;
            return; // Token válido, pode passar
        } catch (\Exception $e) {
            http_response_code(401);
            echo json_encode(['status' => 'erro', 'mensagem' => 'Token JWT inválido ou expirado.']);
            exit();
        }
    }
    
    // Auditoria M2: Removido fallback de sessão legacy insegura
    
    http_response_code(401);
    echo json_encode(['status' => 'erro', 'mensagem' => 'Acesso bloqueado. Requer autenticação.']);
    exit();
});

// ==========================================
// ROTA DE TELEMETRIA (WebSockets / Polling)
// ==========================================
$router->get('/sys/version', function() {
    $file = __DIR__ . '/../logs/db_version.txt';
    $version = file_exists($file) ? file_get_contents($file) : time();
    echo json_encode(['status' => 'sucesso', 'version' => (int)$version]);
});

// ==========================================
// GRUPO DE ROTAS: AUTH (Sem proteção de Middleware)
// ==========================================
$router->post('/v1/auth/login', function() {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown'; // Auditoria B2: Declarado apenas uma vez
    $rate_limit_file = __DIR__ . '/../logs/rate_limit_' . md5($ip) . '.json';
    
    // Bloqueio de Força Bruta (Máx 5 tentativas em 15 min)
    if (file_exists($rate_limit_file)) {
        $attempts = json_decode(file_get_contents($rate_limit_file), true);
        if ($attempts['count'] >= 5 && (time() - $attempts['time']) < 900) {
            $GLOBALS['logger']->alert("IP Bloqueado por Força Bruta: $ip");
            http_response_code(429); echo json_encode(['status' => 'erro', 'mensagem' => 'Muitas tentativas. Tente novamente em 15 minutos.']); exit;
        }
        if ((time() - $attempts['time']) >= 900) unlink($rate_limit_file); // Reset
    }

    $json = file_get_contents('php://input');
    $data = json_decode($json, true);
    
    $usuario = $data['usuario'] ?? '';
    $senha = $data['senha'] ?? '';
    
    if (empty($usuario) || empty($senha)) {
        http_response_code(400);
        echo json_encode(['status' => 'erro', 'mensagem' => 'Preencha todos os campos.']);
        exit;
    }
    
    // Usar Eloquent para validar usuário no NOVO SCHEMA
    $user = \Illuminate\Database\Capsule\Manager::table('usuarios as u')
        ->select('u.*', 'r.name as role_name')
        ->leftJoin('roles as r', 'r.id', '=', 'u.role_id')
        ->where('u.usuario', $usuario)
        ->first();
        
    if (!$user || !password_verify($senha, $user->senha)) {
        sleep(1); // Proteção básica contra Brute Force (Atrasa tentativas em massa)
        
        // Adiciona falha no arquivo do limitador
        $attempts = file_exists($rate_limit_file) ? json_decode(file_get_contents($rate_limit_file), true) : ['count' => 0, 'time' => time()];
        $attempts['count']++;
        $attempts['time'] = time();
        file_put_contents($rate_limit_file, json_encode($attempts));
        
        $GLOBALS['logger']->warning('Tentativa de login falha', ['ip' => $ip, 'usuario_tentado' => $usuario]);
        http_response_code(401);
        echo json_encode(['status' => 'erro', 'mensagem' => 'Credenciais inválidas.']);
        exit;
    }
    
    // Sucesso no login, zera as tentativas do IP
    if (file_exists($rate_limit_file)) unlink($rate_limit_file);
    
    // Verifica se o Modo Manutenção está ativo
    $manutencao = \Illuminate\Database\Capsule\Manager::table('config_sistema')->where('chave', 'modo_manutencao')->value('valor');
    if ($manutencao == '1' && $user->role_id != 16) {
        $GLOBALS['logger']->warning('Login bloqueado pelo Modo Manutenção', ['usuario_id' => $user->id]);
        http_response_code(503);
        echo json_encode(['status' => 'erro', 'mensagem' => 'O sistema encontra-se em Manutenção Temporária. Apenas o nível de Desenvolvedor pode acessar no momento.']);
        exit;
    }
    
    // Status 0: Acabou de se cadastrar e está pendente
    if ($user->status == 0) {
        http_response_code(403);
        echo json_encode(['status' => 'erro', 'mensagem' => 'Sua conta está em análise. Aguarde a liberação do Administrador para entrar no sistema.']);
        exit;
    }
    // Status diferente de 1 e 0: Foi punido/desativado
    if ($user->status != 1) {
        http_response_code(403);
        echo json_encode(['status' => 'erro', 'mensagem' => 'Acesso revogado/desativado. Procure a Administração.']);
        exit;
    }
    
    // Gerar JWT
    $secret_key = $_ENV['APP_KEY'] ?? null;
    if (!$secret_key) {
        http_response_code(500);
        echo json_encode(['status' => 'erro', 'mensagem' => 'Erro crítico de segurança: APP_KEY não configurada no servidor.']);
        exit();
    }
    
    $issuedat_claim = time(); 
    $notbefore_claim = $issuedat_claim; 
    $expire_claim = $issuedat_claim + 3600 * 8; // 8 horas
    
    $token = array(
        "iss" => "sgceem",
        "aud" => "sgceem_frontend",
        "iat" => $issuedat_claim,
        "nbf" => $notbefore_claim,
        "exp" => $expire_claim,
        "data" => array(
            "id" => $user->id,
            "usuario" => $user->usuario,
            "role_id" => $user->role_id,
            "nivel" => 1 // Sempre 1 pois é single-tenant
        )
    );
    $jwt = \Firebase\JWT\JWT::encode($token, $secret_key, 'HS256');
    
    $GLOBALS['logger']->info('Login JWT realizado com sucesso', ['usuario_id' => $user->id]);
    
    echo json_encode([
        'status' => 'sucesso',
        'mensagem' => 'Login aprovado',
        'token' => $jwt,
        'usuario' => [
            'id' => $user->id,
            'nomecompleto' => $user->nomecompleto,
            'nomeguerra' => $user->nomeguerra ?? $user->nomecompleto,
            'postograd' => $user->postograd ?? '',
            'role_name' => $user->role_name,
            'role_id' => $user->role_id,
            'foto' => $user->foto,
            'nivel' => 1
        ]
    ]);
});

// Auditoria A6: Rota de Logout (Blacklist de Token)
$router->post('/v1/auth/logout', function() {
    $headers = function_exists('getallheaders') ? getallheaders() : [];
    $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    
    if (preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
        $jwt = $matches[1];
        
        $blacklist_file = __DIR__ . '/../logs/jwt_blacklist.json';
        $blacklist = file_exists($blacklist_file) ? json_decode(file_get_contents($blacklist_file), true) : [];
        $blacklist[$jwt] = time(); // Salva o token com o timestamp de revogação
        
        // Limpeza simples da blacklist (remove tokens revogados há mais de 8 horas)
        foreach ($blacklist as $token => $timestamp) {
            if (time() - $timestamp > 28800) unset($blacklist[$token]);
        }
        
        file_put_contents($blacklist_file, json_encode($blacklist));
        $GLOBALS['logger']->info('Usuário fez logout. Token invalidado.');
    }
    
    echo json_encode(['status' => 'sucesso', 'mensagem' => 'Logout efetuado com segurança.']);
});

// ==========================================
// GRUPO DE ROTAS: V1 (Protegidas)
// ==========================================
$router->mount('/v1', function() use ($router) {
    
    // Rota: Teste de API e Banco
    $router->get('/teste', function() {
        echo json_encode(['status' => 'sucesso', 'mensagem' => 'Motor V8 (Laravel Eloquent + Router) funcionando perfeitamente!']);
    });

    // ==========================================
    // ROTA: Edição de Perfil (Atualização de Dados e Foto)
    // ==========================================
    $router->post('/me/perfil', function() {
        $user_id = $GLOBALS['usuario_logado']['id'];
        
        try {
            $updateData = [];
            
            if (!empty($_POST['nomecompleto'])) $updateData['nomecompleto'] = $_POST['nomecompleto'];
            if (!empty($_POST['nomeguerra'])) $updateData['nomeguerra'] = $_POST['nomeguerra'];
            if (!empty($_POST['postograd'])) $updateData['postograd'] = $_POST['postograd'];
            if (!empty($_POST['senha'])) {
                $updateData['senha'] = password_hash($_POST['senha'], PASSWORD_BCRYPT);
            }
            
            if (isset($_FILES['foto']) && $_FILES['foto']['error'] == 0) {
                $file = $_FILES['foto'];
                $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                if (in_array($ext, ['jpg', 'jpeg', 'png'])) {
                    $newName = uniqid() . '.' . $ext;
                    $destPath = __DIR__ . '/../frontend_app/public/assets/fotoperfil/' . $newName;
                    if (move_uploaded_file($file['tmp_name'], $destPath)) {
                        $updateData['foto'] = $newName;
                    }
                }
            }
            
            if (!empty($updateData)) {
                \Illuminate\Database\Capsule\Manager::table('usuarios')->where('id', $user_id)->update($updateData);
            }
            
            $userUpdated = \Illuminate\Database\Capsule\Manager::table('usuarios as u')
                ->select('u.*', 'r.name as role_name')
                ->leftJoin('roles as r', 'r.id', '=', 'u.role_id')
                ->where('u.id', $user_id)
                ->first();
                
            $usuario = [
                'id' => $userUpdated->id,
                'nomecompleto' => $userUpdated->nomecompleto,
                'nomeguerra' => $userUpdated->nomeguerra ?? $userUpdated->nomecompleto,
                'postograd' => $userUpdated->postograd ?? '',
                'role_name' => $userUpdated->role_name,
                'role_id' => $userUpdated->role_id,
                'foto' => $userUpdated->foto,
                'nivel' => 1
            ];
            
            echo json_encode(['status' => 'sucesso', 'usuario' => $usuario]);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['status' => 'erro', 'mensagem' => 'Falha ao atualizar perfil: ' . $e->getMessage()]);
        }
    });

    // ==========================================
    // ROTA PYTHON: Extrator de IA (PDF/XML)
    // ==========================================
    $router->post('/extract_document', function() {
        if (!isset($_FILES['documento'])) {
            http_response_code(400); echo json_encode(['status'=>'error', 'message'=>'Nenhum arquivo enviado.']); exit;
        }
        
        $file = $_FILES['documento'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        if (!in_array($ext, ['pdf', 'xml'])) {
            http_response_code(400); echo json_encode(['status'=>'error', 'message'=>'Formato inválido. Envie PDF ou XML.']); exit;
        }
        
        // Auditoria M5: Limite de tamanho de upload (5MB)
        $maxSize = 5 * 1024 * 1024;
        if ($file['size'] > $maxSize) {
            http_response_code(400); echo json_encode(['status'=>'error', 'message'=>'Arquivo excede o limite de 5MB.']); exit;
        }
        
        $tmp_path = '/tmp/' . uniqid('doc_') . '.' . $ext;
        if (!move_uploaded_file($file['tmp_name'], $tmp_path)) {
            http_response_code(500); echo json_encode(['status'=>'error', 'message'=>'Falha ao salvar arquivo temporário.']); exit;
        }
        
        // Auditoria C5: Path do Python via .env (não hardcoded)
        $python_env = $_ENV['PYTHON_PATH'] ?? __DIR__ . '/../venv/bin/python3';
        $script_path = __DIR__ . '/extractor.py';
        
        $command = escapeshellarg($python_env) . " " . escapeshellarg($script_path) . " " . escapeshellarg($tmp_path) . " 2>&1";
        $output = shell_exec($command);
        unlink($tmp_path); // Limpa o temp
        
        if (!$output) {
            http_response_code(500); echo json_encode(['status'=>'error', 'message'=>'Falha no processamento da IA Python.']); exit;
        }
        
        $result = json_decode($output, true);
        if ($result === null && json_last_error() !== JSON_ERROR_NONE) {
            http_response_code(500); 
            echo json_encode(['status'=>'error', 'message'=>'Erro Crítico Python: ' . substr($output, 0, 500)]); 
            exit;
        }
        echo json_encode($result);
    });

    // ==========================================
    
    // Retorna a lista de funções (roles) para o form de cadastro (ESCONDENDO ADMIN E GODMODE)
    $router->get('/auth/roles', function() {
        $roles = \Illuminate\Database\Capsule\Manager::table('roles')
            ->whereNotIn('id', [1, 16]) // Oculta funções sensíveis da lista pública
            ->get();
        echo json_encode(['status' => 'sucesso', 'dados' => $roles]);
    });

    // Rota para cadastrar novo usuário
    $router->post('/auth/usuarios', function() {
        $json = file_get_contents('php://input');
        $data = json_decode($json, true);
        
        $nomeguerra = $data['nomeguerra'] ?? '';
        $postograd = $data['postograd'] ?? '';
        $role_id = $data['role_id'] ?? '';
        $senha = $data['senha'] ?? '';
        
        // O nome de usuário (login) costuma ser o nome de guerra minúsculo ou uma combinação
        $usuario = strtolower(str_replace(' ', '', $nomeguerra));
        
        if (empty($nomeguerra) || empty($role_id) || empty($senha)) {
            http_response_code(400);
            echo json_encode(['status' => 'erro', 'mensagem' => 'Preencha os campos obrigatórios.']);
            exit;
        }
        
        // ==== POLÍTICA DE SEGURANÇA MILITAR ====
        // 1. Ninguém pode se auto-cadastrar como Super Admin (1) ou God Mode (16) via API pública.
        if ($role_id == 1 || $role_id == 16) {
            $GLOBALS['logger']->alert('Tentativa de intrusão no Cadastro com Role Mestre', ['ip' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1']);
            http_response_code(403);
            echo json_encode(['status' => 'erro', 'mensagem' => 'Tentativa de escalonamento de privilégio bloqueada.']);
            exit;
        }
        
        // Verifica se usuário já existe
        $exists = \Illuminate\Database\Capsule\Manager::table('usuarios')->where('usuario', $usuario)->exists();
        if ($exists) {
            $usuario = strtolower(str_replace(' ', '', $postograd . $nomeguerra));
        }
        
        $senhaHash = password_hash($senha, PASSWORD_BCRYPT);
        
        // 2. Status = 0 (Pendente de Aprovação). O usuário não pode logar até o Admin aprovar.
        $id = \Illuminate\Database\Capsule\Manager::table('usuarios')->insertGetId([
            'nomecompleto' => $postograd . ' ' . $nomeguerra,
            'usuario' => $usuario,
            'senha' => $senhaHash,
            'role_id' => $role_id,
            'nomeguerra' => $nomeguerra,
            'postograd' => $postograd,
            'status' => 0 // NOVO PADRÃO: PENDENTE
        ]);
        
        $GLOBALS['logger']->info('Novo usuário aguardando aprovação', ['usuario' => $usuario, 'role_id' => $role_id]);
        
        echo json_encode(['status' => 'sucesso', 'mensagem' => 'Cadastro realizado! Seu usuário é: ' . $usuario . '. Aguarde a aprovação do Administrador para acessar.', 'usuario_gerado' => $usuario]);
    });

    
    // ==========================================
    // ROTA ESCONDIDA (GOD MODE)
    // ==========================================
    $router->get('/godmode/usuarios', function() {
        $user_jwt = $GLOBALS['usuario_logado'];
        // Apenas o role_id = 16 (Desenvolvedor) pode acessar o God Mode
        if ($user_jwt['role_id'] != 16) {
            http_response_code(403);
            echo json_encode(['status' => 'erro', 'mensagem' => 'Acesso negado.']);
            exit;
        }
        
        $usuarios = \Illuminate\Database\Capsule\Manager::table('usuarios')->get();
        echo json_encode(['status' => 'sucesso', 'dados' => $usuarios]);
    });

    
    // ==========================================
    // ROTAS DE RBAC (Admin Normal)
    require_once __DIR__ . '/includes/api/crud_api.php';

    require_once __DIR__ . '/includes/api/admin_api.php';
    require_once __DIR__ . '/includes/api/godmode_api.php';

    // ==========================================
    $router->get('/modules', function() {
        // Bloqueio de Segurança: Apenas Admin/GodMode configuram módulos/permissões
        if ($GLOBALS['usuario_logado']['role_id'] != 1 && $GLOBALS['usuario_logado']['role_id'] != 16) {
            http_response_code(403); echo json_encode(['status' => 'erro']); exit;
        }
        $modules = \Illuminate\Database\Capsule\Manager::table('modules')->get();
        echo json_encode(['status' => 'sucesso', 'dados' => $modules]);
    });

    $router->get('/usuarios', function() {
        if ($GLOBALS['usuario_logado']['role_id'] != 1 && $GLOBALS['usuario_logado']['role_id'] != 16) {
            http_response_code(403); echo json_encode(['status' => 'erro']); exit;
        }
        $usuarios = \Illuminate\Database\Capsule\Manager::table('usuarios')->get();
        echo json_encode(['status' => 'sucesso', 'dados' => $usuarios]);
    });

    $router->get('/permissoes', function() {
        if ($GLOBALS['usuario_logado']['role_id'] != 1 && $GLOBALS['usuario_logado']['role_id'] != 16) {
            http_response_code(403); echo json_encode(['status' => 'erro']); exit;
        }
        $permissoes = \Illuminate\Database\Capsule\Manager::table('role_permissions')->get();
        echo json_encode(['status' => 'sucesso', 'dados' => $permissoes]);
    });

    $router->post('/permissoes', function() {
        if ($GLOBALS['usuario_logado']['role_id'] != 1 && $GLOBALS['usuario_logado']['role_id'] != 16) {
            $GLOBALS['logger']->alert('TENTATIVA DE HACK: Usuário comum tentou alterar matriz de permissões!', ['usuario' => $GLOBALS['usuario_logado']]);
            http_response_code(403); echo json_encode(['status' => 'erro']); exit;
        }
        
        $json = file_get_contents('php://input');
        $data = json_decode($json, true);
        
        $role_id = $data['role_id'] ?? null;
        $permissoes = $data['permissoes'] ?? [];
        
        if (!$role_id) {
            http_response_code(400);
            echo json_encode(['status' => 'erro', 'mensagem' => 'role_id obrigatório']);
            exit;
        }

        // Deleta permissões antigas desta role para re-inserir
        \Illuminate\Database\Capsule\Manager::table('role_permissions')->where('role_id', $role_id)->delete();

        foreach ($permissoes as $p) {
            // Só insere se tiver pelo menos uma permissão ativa
            if (!empty($p['can_view']) || !empty($p['can_create']) || !empty($p['can_edit']) || !empty($p['can_delete'])) {
                \Illuminate\Database\Capsule\Manager::table('role_permissions')->insert([
                    'role_id' => $role_id,
                    'module_id' => $p['module_id'],
                    'can_view' => $p['can_view'] ? 1 : 0,
                    'can_create' => $p['can_create'] ? 1 : 0,
                    'can_edit' => $p['can_edit'] ? 1 : 0,
                    'can_delete' => $p['can_delete'] ? 1 : 0,
                ]);
            }
        }
        
        notificar_alteracao_bd();
        echo json_encode(['status' => 'sucesso']);
    });

    // ==========================================
    // ROTAS DO LAYOUT DO DASHBOARD (Widgets)
    // ==========================================
    $router->get('/widgets', function() {
        // Sem restrição de role_id para o GET, pois a Home precisa disso pra todos os cargos
        $widgets = \Illuminate\Database\Capsule\Manager::table('widget_permissions')->get();
        echo json_encode(['status' => 'sucesso', 'dados' => $widgets]);
    });

    $router->post('/widgets', function() {
        if ($GLOBALS['usuario_logado']['role_id'] != 1 && $GLOBALS['usuario_logado']['role_id'] != 16) {
            http_response_code(403); echo json_encode(['status' => 'erro']); exit;
        }
        
        $json = file_get_contents('php://input');
        $data = json_decode($json, true);
        $role_id = $data['role_id'] ?? null;
        $widgets = $data['widgets'] ?? [];
        
        if (!$role_id) {
            http_response_code(400); echo json_encode(['status' => 'erro']); exit;
        }

        \Illuminate\Database\Capsule\Manager::table('widget_permissions')->where('role_id', $role_id)->delete();
        
        foreach ($widgets as $w) {
            \Illuminate\Database\Capsule\Manager::table('widget_permissions')->insert([
                'role_id' => $role_id,
                'widget_key' => $w['widget_key'],
                'is_visible' => $w['is_visible'] ? 1 : 0
            ]);
        }
        
        notificar_alteracao_bd();
        echo json_encode(['status' => 'sucesso']);
    });

    // ROTAS DE DASHBOARD E RELATÓRIOS
    // ==========================================
    $router->get('/dashboard/frota', function() {
        require_once __DIR__ . '/Controllers/DashboardController.php';
        $controller = new \App\Controllers\DashboardController();
        $controller->frota();
    });
    
    $router->get('/dashboard/financeiro', function() {
        require_once __DIR__ . '/Controllers/DashboardController.php';
        $controller = new \App\Controllers\DashboardController();
        $controller->financeiro();
    });

    $router->get('/dashboard/preventiva', function() {
        require_once __DIR__ . '/Controllers/DashboardController.php';
        $controller = new \App\Controllers\DashboardController();
        $controller->preventiva();
    });

    // ==========================================
    // Rota: Menu Lateral Dinâmico
    // ==========================================
    $router->get('/menu', function() {
        try {
            $user_jwt = $GLOBALS['usuario_logado'];
            $role_id = $user_jwt['role_id'] ?? 3;
            
            $permissoes = [];
            $permissoes_globais = [];
            if ($role_id != 1 && $role_id != 16) {
                $perms = \Illuminate\Database\Capsule\Manager::table('role_permissions')
                    ->where('role_id', $role_id)
                    ->get();
                $all_modules_raw = \Illuminate\Database\Capsule\Manager::table('modules')->get();
                $all_modules = [];
                foreach($all_modules_raw as $m) $all_modules[$m->id] = $m;
                
                foreach($perms as $p) {
                    $permissoes[$p->module_id] = $p->can_view;
                    if (isset($all_modules[$p->module_id])) {
                        $path = $all_modules[$p->module_id]->path;
                        $permissoes_globais[$path] = [
                            'can_create' => $p->can_create == 1,
                            'can_edit' => $p->can_edit == 1,
                            'can_delete' => $p->can_delete == 1,
                        ];
                    }
                }
            } else {
                $all_modules_raw = \Illuminate\Database\Capsule\Manager::table('modules')->get();
                foreach($all_modules_raw as $m) {
                    $permissoes_globais[$m->path] = [
                        'can_create' => true,
                        'can_edit' => true,
                        'can_delete' => true,
                    ];
                }
            }

            $can_view = function($module_id) use ($role_id, $permissoes) {
                if ($role_id == 1 || $role_id == 16) return true; // O Admin e o Desenvolvedor (GodMode) veem tudo!
                return !empty($permissoes[$module_id]);
            };

            $menu_agrupado = [];

            if ($can_view(1)) {
                $menu_agrupado[] = [
                    'id' => 1,
                    'nome' => 'Gestão de Frota',
                    'icone' => 'fas fa-car',
                    'filhos' => [
                        ['id' => 101, 'nome' => 'Dashboard', 'rota' => 'dashboard'],
                        ['id' => 102, 'nome' => 'Viaturas', 'rota' => 'frota'],
                    ]
                ];
            }

            if ($can_view(2)) {
                $menu_agrupado[] = [
                    'id' => 2,
                    'nome' => 'Financeiro',
                    'icone' => 'fas fa-dollar-sign',
                    'filhos' => [
                        ['id' => 201, 'nome' => 'Dashboard', 'rota' => 'dashboard_3'],
                        ['id' => 202, 'nome' => 'Empenhos', 'rota' => 'includes/fin_empenhos/listagem'],
                        ['id' => 203, 'nome' => 'Notas Fiscais', 'rota' => 'includes/fin_notas_fiscais/listagem'],
                    ]
                ];
            }

            if ($can_view(3)) {
                $menu_agrupado[] = [
                    'id' => 3,
                    'nome' => 'Almoxarifado',
                    'icone' => 'fas fa-boxes',
                    'filhos' => [
                        ['id' => 300, 'nome' => 'Dashboard', 'rota' => 'dashboard_4'],
                        ['id' => 301, 'nome' => 'Estoque', 'rota' => 'includes/almox_produtos/listagem'],
                    ]
                ];
            }

            if ($can_view(4)) {
                $menu_agrupado[] = [
                    'id' => 4,
                    'nome' => 'Ordens de Serviço',
                    'icone' => 'fas fa-wrench',
                    'filhos' => [
                        ['id' => 400, 'nome' => 'Dashboard', 'rota' => 'dashboard_5'],
                        ['id' => 401, 'nome' => 'Controle de OS', 'rota' => 'includes/os/listagem'],
                    ]
                ];
            }

            if ($role_id == 1 || $role_id == 16) {
                $menu_agrupado[] = [
                    'id' => 99,
                    'nome' => 'Administração',
                    'icone' => 'fas fa-cogs',
                    'filhos' => [
                        ['id' => 990, 'nome' => 'Gestão de Usuários', 'rota' => 'usuarios_listagem'],
                        ['id' => 992, 'nome' => 'Permissões de Acesso', 'rota' => 'admin_permissoes'],
                        ['id' => 993, 'nome' => 'Configurações Globais', 'rota' => 'admin_config'],
                    ]
                ];
            }
            
            echo json_encode([
                'status' => 'sucesso',
                'menu' => $menu_agrupado,
                'permissoes_globais' => $permissoes_globais
            ]);
            
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['status' => 'erro', 'mensagem' => 'Falha ao processar permissões de menu.']);
        }
    });




});

// Configurar Rota 404 para API
$router->set404(function() {
    http_response_code(404);
    echo json_encode(['status' => 'erro', 'mensagem' => 'Rota da API não encontrada.']);
});

// Executar Roteamento
$router->run();

