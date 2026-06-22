<?php
require __DIR__ . '/vendor/autoload.php';

use Illuminate\Database\Capsule\Manager as DB;

$dbHost = '127.0.0.1';
$dbUser = 'root';
$dbPass = ''; 
$dbName = 'sgceem_v2'; 

try {
    $pdo = new PDO("mysql:host=$dbHost;dbname=$dbName", $dbUser, $dbPass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Drop foreign key
    $pdo->exec("ALTER TABLE fin_notas_fiscais DROP FOREIGN KEY fin_notas_fiscais_ibfk_1");
    
    // Modify column
    $pdo->exec("ALTER TABLE fin_notas_fiscais MODIFY COLUMN empenho_id VARCHAR(100)");
    
    echo "Sucesso: Chave Estrangeira Removida e Coluna Alterada para VARCHAR(100)!";
} catch (\Exception $e) {
    echo "Erro: " . $e->getMessage() . "\n";
    
    try {
        $pdo2 = new PDO("mysql:host=$dbHost", $dbUser, $dbPass);
        $stmt = $pdo2->query("SHOW DATABASES LIKE '%sgceem%'");
        $db = $stmt->fetchColumn();
        if ($db) {
            $pdo2->exec("USE `$db`");
            $pdo2->exec("ALTER TABLE fin_notas_fiscais DROP FOREIGN KEY fin_notas_fiscais_ibfk_1");
            $pdo2->exec("ALTER TABLE fin_notas_fiscais MODIFY COLUMN empenho_id VARCHAR(100)");
            echo "Sucesso no banco $db!";
        }
    } catch (\Exception $e2) {
        echo "Erro 2: " . $e2->getMessage();
    }
}
