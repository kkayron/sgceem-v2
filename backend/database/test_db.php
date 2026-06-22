<?php
$pdo = new PDO("mysql:host=127.0.0.1;dbname=sgceem_v2", "root", "");
$stmt = $pdo->query("DESCRIBE fin_notas_fiscais");
$result = $stmt->fetchAll(PDO::FETCH_ASSOC);
print_r($result);
