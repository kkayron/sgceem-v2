<?php
include_once('../../conexao/config.php');

if (!isset($_GET['marca_id'])) {
    echo json_encode([]);
    exit;
}

$marcaId = (int) $_GET['marca_id'];

$sql = "SELECT id, nome_modelo FROM config_modelos WHERE id_marca = $marcaId ORDER BY nome_modelo";
$res = $conexao->query($sql);

$modelos = [];
while ($row = $res->fetch_assoc()) {
    $modelos[] = $row;
}

header('Content-Type: application/json');
echo json_encode($modelos);
