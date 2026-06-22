<?php
$pdo = new PDO("mysql:host=127.0.0.1;dbname=sgceem_v2", "root", "");
$stmt = $pdo->query("SHOW TRIGGERS");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
