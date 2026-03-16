<?php
header('Content-Type: application/json; charset=utf-8');
include_once('../../conexao/config.php');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        echo json_encode(['status' => 'erro', 'mensagem' => 'Método inválido.']);
        exit;
    }

    $id = $_GET['id'] ?? null;

    if (empty($id) || !is_numeric($id)) {
        echo json_encode(['status' => 'erro', 'mensagem' => 'ID inválido.']);
        exit;
    }

    $stmt = $conexao->prepare("SELECT id, marca FROM config_marcas WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $resultado = $stmt->get_result();

    if ($resultado->num_rows === 0) {
        echo json_encode(['status' => 'erro', 'mensagem' => 'Marca não encontrada.']);
        exit;
    }

    $marca = $resultado->fetch_assoc();

    echo json_encode([
        'status' => 'sucesso',
        'marca' => $marca
    ]);

} catch (Exception $e) {
    echo json_encode([
        'status' => 'erro',
        'mensagem' => 'Erro inesperado: ' . $e->getMessage()
    ]);
}
?>
