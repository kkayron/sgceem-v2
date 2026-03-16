<?php
require '../../conexao/config.php';

// Buscar todas as marcas
$marcas = [];
$sql = "SELECT id, marca FROM config_marcas ORDER BY marca";
$res = $conexao->query($sql);

if ($res && $res->num_rows > 0) {
    while ($row = $res->fetch_assoc()) {
        $marcas[] = $row;
    }
}

echo json_encode([
    'sucesso' => true,
    'marcas' => $marcas
]);
