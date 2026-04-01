<?php
header('Content-Type: application/json; charset=utf-8');

$pagina_id = 2;

require_once('../api/seguranca_json_cadastrar.php');
require_once('../../conexao/config.php');

if (!isset($_GET['marca_id'])) {
    echo json_encode([]);
    exit;
}

$marcaId = (int) $_GET['marca_id'];

$stmt = $conexao->prepare("
    SELECT id, nome_modelo 
    FROM config_modelos 
    WHERE id_marca = ? 
    ORDER BY nome_modelo
");

$stmt->bind_param("i", $marcaId);
$stmt->execute();

$result = $stmt->get_result();

$modelos = [];

while ($row = $result->fetch_assoc()) {
    $modelos[] = $row;
}

echo json_encode($modelos);