<?php
header('Content-Type: application/json; charset=utf-8');

$pagina_id = 14;

require_once('../api/seguranca_json_editar.php');
require_once('../../conexao/config.php');

if (!isset($_GET['marca_id'])) {
    echo json_encode([]);
    exit;
}

$id_marca = $_GET['marca_id'] ?? null;

if (!$id_marca) {
    echo json_encode(['sucesso' => false, 'mensagem' => 'ID da marca não informado.']);
    exit;
}

$modelos = [];
$stmt = $conexao->prepare("SELECT id, nome_modelo FROM config_modelos WHERE id_marca = ? ORDER BY nome_modelo");
$stmt->bind_param("i", $id_marca);
$stmt->execute();
$res = $stmt->get_result();

if ($res && $res->num_rows > 0) {
    while ($row = $res->fetch_assoc()) {
        $modelos[] = $row;
    }
}

echo json_encode([
    'sucesso' => true,
    'modelos' => $modelos
]);
