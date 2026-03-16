<?php
$logPath = __DIR__ . '/log.txt';
file_put_contents($logPath, "Teste de escrita em " . date('Y-m-d H:i:s') . "\n", FILE_APPEND);
echo "Arquivo de log gerado com sucesso.";