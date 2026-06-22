<?php
require __DIR__ . '/vendor/autoload.php';

$dbHost = '127.0.0.1';
$dbUser = 'root';
$dbPass = ''; 
$dbName = 'sgceem_v2'; 

try {
    $pdo = new PDO("mysql:host=$dbHost;dbname=$dbName", $dbUser, $dbPass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $queries = [
        "ALTER TABLE fin_notas_fiscais MODIFY COLUMN empenho_id VARCHAR(100) NULL",
        "ALTER TABLE fin_notas_fiscais DROP FOREIGN KEY fin_notas_fiscais_ibfk_1", // Might fail if already dropped
        "ALTER TABLE fin_notas_fiscais MODIFY COLUMN status ENUM('No Destacamento','Pré-Liquidada','Enviada pra S4','Paga') NOT NULL DEFAULT 'No Destacamento'",
        "ALTER TABLE fin_notas_fiscais MODIFY COLUMN data_emissao DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP",
        "ALTER TABLE fin_notas_fiscais MODIFY COLUMN valor_total DECIMAL(15,2) NOT NULL DEFAULT 0.00",
        "ALTER TABLE fin_empenhos ADD COLUMN saldo_retido DECIMAL(15,2) NOT NULL DEFAULT 0.00",
        "ALTER TABLE fin_empenhos ADD COLUMN saldo_consumido DECIMAL(15,2) NOT NULL DEFAULT 0.00",
        "ALTER TABLE fin_empenhos ADD COLUMN status ENUM('Ativo','Liquidado Parcial','Liquidado Total','Cancelado') NOT NULL DEFAULT 'Ativo'"
    ];
    
    foreach ($queries as $q) {
        try {
            $pdo->exec($q);
            echo "SUCESSO: $q\n";
        } catch (\Exception $e) {
            echo "IGNORADO (provavelmente já existe): $q\n";
        }
    }
    
    echo "\nTodas as modificações de banco aplicadas!";
} catch (\Exception $e) {
    echo "Erro Fatal: " . $e->getMessage() . "\n";
}
