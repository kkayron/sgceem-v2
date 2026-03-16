<?php
/**
 * backup_full.php
 * - Baixa um ZIP com: (1) todos os arquivos do sistema + (2) dump do banco atual
 * - Acesso permitido APENAS para funcao_id = 1 (Desenvolvedor)
 *
 * Coloque este arquivo na raiz do sistema (recomendado) ou ajuste $baseDir e o require do config.
 */

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
date_default_timezone_set('America/Sao_Paulo');

/* =========================
   1) AUTORIZAÇÃO (SEU PADRÃO)
   ========================= */

if (empty($_SESSION['usuario_id'])) {
    http_response_code(403);
    exit('Acesso negado (não autenticado).');
}

if ((int)($_SESSION['funcao_id'] ?? 0) !== 1) {
    http_response_code(403);
    exit('Acesso negado (somente desenvolvedor).');
}

/* =========================
   2) CONFIG DO BANCO
   ========================= */

// Ajuste o caminho se seu config.php estiver em outro lugar
require_once __DIR__ . '/conexao/config.php';

/**
 * Seu config normalmente define:
 * $dbHost, $dbUsername, $dbPassword, $dbName
 * Se não definir, ajuste manualmente aqui.
 */
$dbHost = $dbHost ?? 'localhost';
$dbUser = $dbUsername ?? 'root';
$dbPass = $dbPassword ?? '';
$dbName = $dbName ?? '';

if (!$dbName) {
    exit("Não consegui identificar \$dbName no seu config.php. Ajuste as variáveis do banco.");
}

/* =========================
   3) PARAMETROS
   ========================= */

$baseDir = realpath(__DIR__); // pasta do sistema (onde está esse arquivo)
if (!$baseDir) exit("Erro: baseDir inválido.");

$timestamp = date('Y-m-d_H-i-s');
$tmpDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . "backup_" . md5($baseDir . $timestamp . random_int(1, 999999));
if (!mkdir($tmpDir, 0700, true)) exit("Falha ao criar pasta temporária.");

$dumpFile = $tmpDir . DIRECTORY_SEPARATOR . "database_{$dbName}_{$timestamp}.sql";
$zipFile  = $tmpDir . DIRECTORY_SEPARATOR . "backup_full_{$timestamp}.zip";

/* =========================
   4) DUMP DO BANCO
   ========================= */

function findMysqldump(): ?string {
    $isWin = (stripos(PHP_OS, 'WIN') === 0);

    // 1) tenta PATH
    $cmd = $isWin ? 'where mysqldump' : 'which mysqldump';
    @exec($cmd, $out, $code);
    if ($code === 0 && !empty($out[0])) return trim($out[0]);

    // 2) caminhos comuns
    $candidates = $isWin ? [
        'C:\xampp\mysql\bin\mysqldump.exe',
        'C:\xampp2\mysql\bin\mysqldump.exe',
        'C:\Program Files\MySQL\MySQL Server 8.0\bin\mysqldump.exe',
        'C:\Program Files\MySQL\MySQL Server 5.7\bin\mysqldump.exe',
    ] : [
        '/usr/bin/mysqldump',
        '/usr/local/bin/mysqldump',
        '/bin/mysqldump',
    ];

    foreach ($candidates as $p) if (file_exists($p)) return $p;
    return null;
}

function shellQuoteSafe(string $str): string {
    if (stripos(PHP_OS, 'WIN') === 0) {
        return '"' . str_replace('"', '""', $str) . '"';
    }
    return escapeshellarg($str);
}

$mysqldump = findMysqldump();

if ($mysqldump) {
    // OBS: senha na linha de comando pode ficar visível em "ps" no Linux.
    // Se isso for preocupação, posso te mandar versão com arquivo temporário .cnf.
    $cmd  = shellQuoteSafe($mysqldump);
    $cmd .= " --host=" . shellQuoteSafe($dbHost);
    $cmd .= " --user=" . shellQuoteSafe($dbUser);

    if ($dbPass !== '') {
        $cmd .= " --password=" . shellQuoteSafe($dbPass);
    }

    $cmd .= " --routines --triggers --events";
    $cmd .= " --single-transaction --quick";
    $cmd .= " " . shellQuoteSafe($dbName);
    $cmd .= " > " . shellQuoteSafe($dumpFile);

    @exec($cmd, $out, $code);

    if ($code !== 0 || !file_exists($dumpFile) || filesize($dumpFile) < 10) {
        $mysqldump = null; // força fallback
    }
}

if (!$mysqldump) {
    // Fallback PHP (não é tão completo quanto mysqldump, mas salva tabelas e dados)
    $mysqli = new mysqli($dbHost, $dbUser, $dbPass, $dbName);
    if ($mysqli->connect_error) exit("Falha ao conectar no banco: " . $mysqli->connect_error);
    $mysqli->set_charset('utf8mb4');

    $fh = fopen($dumpFile, 'w');
    if (!$fh) exit("Falha ao criar dump local.");

    fwrite($fh, "-- Backup (fallback PHP) gerado em {$timestamp}\n");
    fwrite($fh, "SET FOREIGN_KEY_CHECKS=0;\n\n");

    $tablesRes = $mysqli->query("SHOW TABLES");
    while ($tRow = $tablesRes->fetch_array()) {
        $table = $tRow[0];

        $createRes = $mysqli->query("SHOW CREATE TABLE `{$table}`");
        $createRow = $createRes->fetch_assoc();
        $createSql = $createRow['Create Table'] ?? '';
        fwrite($fh, "DROP TABLE IF EXISTS `{$table}`;\n");
        fwrite($fh, $createSql . ";\n\n");

        $dataRes = $mysqli->query("SELECT * FROM `{$table}`", MYSQLI_USE_RESULT);
        $fields = $dataRes->fetch_fields();

        while ($row = $dataRes->fetch_assoc()) {
            $cols = [];
            $vals = [];
            foreach ($fields as $f) {
                $col = $f->name;
                $cols[] = "`{$col}`";
                $val = $row[$col];
                $vals[] = ($val === null) ? "NULL" : ("'" . $mysqli->real_escape_string($val) . "'");
            }
            fwrite($fh, "INSERT INTO `{$table}` (" . implode(',', $cols) . ") VALUES (" . implode(',', $vals) . ");\n");
        }

        fwrite($fh, "\n");
        $dataRes->free();
    }

    fwrite($fh, "SET FOREIGN_KEY_CHECKS=1;\n");
    fclose($fh);
    $mysqli->close();
}

/* =========================
   5) CRIA ZIP (DUMP + ARQUIVOS)
   ========================= */

$zip = new ZipArchive();
if ($zip->open($zipFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    exit("Falha ao criar ZIP.");
}

// adiciona dump
$zip->addFile($dumpFile, basename($dumpFile));

// evita incluir temporários e o próprio backup_full.php (opcional)
$excluir = [
    realpath($zipFile),
    realpath($dumpFile),
    realpath(__FILE__),
];

// Opcional: excluir pastas pesadas
$pastasIgnoradas = [
    'vendor',
    'node_modules',
    '.git',
];

// percorre arquivos
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($baseDir, FilesystemIterator::SKIP_DOTS),
    RecursiveIteratorIterator::SELF_FIRST
);

foreach ($iterator as $file) {
    $path = $file->getPathname();
    $real = realpath($path);

    if ($real && in_array($real, $excluir, true)) continue;
    if (strpos($path, $tmpDir) === 0) continue;

    $relPath = ltrim(str_replace($baseDir, '', $path), DIRECTORY_SEPARATOR);
    $relPathNorm = str_replace('\\', '/', $relPath);

    // pula pastas ignoradas
    foreach ($pastasIgnoradas as $p) {
        if ($relPathNorm === $p || str_starts_with($relPathNorm, $p . '/')) {
            continue 2;
        }
    }

    if ($file->isDir()) {
        $zip->addEmptyDir($relPathNorm);
    } else {
        $zip->addFile($path, $relPathNorm);
    }
}

$zip->close();

/* =========================
   6) DOWNLOAD + LIMPEZA
   ========================= */

if (!file_exists($zipFile)) exit("ZIP não encontrado.");

header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="' . basename($zipFile) . '"');
header('Content-Length: ' . filesize($zipFile));
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');

readfile($zipFile);

// limpeza
@unlink($zipFile);
@unlink($dumpFile);
@rmdir($tmpDir);

exit;