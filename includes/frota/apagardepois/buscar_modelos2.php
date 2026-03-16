<?php
require '../../conexao/config.php';

$id_marca = $_GET['id_marca'] ?? null;

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
