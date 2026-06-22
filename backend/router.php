<?php
// router.php para servidor embutido do PHP
$path = parse_url($_SERVER["REQUEST_URI"], PHP_URL_PATH);

// Se o arquivo físico existir, serve o arquivo (ex: imagens)
if (file_exists(__DIR__ . $path) && is_file(__DIR__ . $path)) {
    return false;
}

// Se não, envia tudo para a API
require_once __DIR__ . '/api.php';
