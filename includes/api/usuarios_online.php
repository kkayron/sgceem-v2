<?php
session_start();
include_once('../../conexao/config.php');

$sql = "
SELECT nomeguerra, postograd
FROM usuarios
WHERE ultima_atividade >= NOW() - INTERVAL 1 MINUTE
ORDER BY nomeguerra
";

$result = $conexao->query($sql);

$usuarios = [];

while ($row = $result->fetch_assoc()) {
    $usuarios[] = $row['postograd'] . " " . $row['nomeguerra'];
}

echo json_encode($usuarios);
?>