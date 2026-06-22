<?php
require_once __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;
use Illuminate\Database\Capsule\Manager as Capsule;

// Carregar variáveis de ambiente do .env na raiz
$dotenv = Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

// Inicializar Eloquent ORM
$capsule = new Capsule;

$capsule->addConnection([
    'driver'    => $_ENV['DB_CONNECTION'] ?? 'mysql',
    'host'      => $_ENV['DB_HOST'] ?? '127.0.0.1',
    'port'      => $_ENV['DB_PORT'] ?? 3306,
    'database'  => $_ENV['DB_DATABASE'] ?? 'sgceem_v2',
    'username'  => $_ENV['DB_USERNAME'] ?? 'root',
    'password'  => $_ENV['DB_PASSWORD'] ?? '',
    'charset'   => $_ENV['DB_CONNECTION'] == 'pgsql' ? 'utf8' : 'utf8mb4',
    'collation' => $_ENV['DB_CONNECTION'] == 'pgsql' ? '' : 'utf8mb4_unicode_ci',
    'prefix'    => '',
    'sslmode'   => $_ENV['DB_CONNECTION'] == 'pgsql' ? 'require' : 'prefer',
    'options'   => [
        \PDO::ATTR_EMULATE_PREPARES => false,
        \PDO::ATTR_STRINGIFY_FETCHES => false,
    ],
]);

// Configurar o Eloquent para estar disponível globalmente (via métodos estáticos)
$capsule->setAsGlobal();

// Inicializar (Boot) o Eloquent
$capsule->bootEloquent();

// ==========================================
// INICIALIZAR AUDITORIA MILITAR (MONOLOG)
// ==========================================
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

$logger = new Logger('SGCEEM_AUDIT');
// Grava logs de segurança e operações na pasta /logs/ (será criada automaticamente)
$logger->pushHandler(new StreamHandler(__DIR__ . '/../logs/operacional.log', Logger::INFO));

// Exemplo de uso em qualquer arquivo: $GLOBALS['logger']->info('Login realizado', ['user' => $id]);
$GLOBALS['logger'] = $logger;
