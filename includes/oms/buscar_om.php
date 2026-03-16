<?php
header('Content-Type: application/json');
include_once('../../conexao/config.php');

try {
    // ===================== VALIDAÇÃO =====================
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        echo json_encode(['status' => 'erro', 'mensagem' => 'Método inválido.']);
        exit;
    }

    $id = $_GET['id'] ?? null;

    if (empty($id) || !is_numeric($id)) {
        echo json_encode(['status' => 'erro', 'mensagem' => 'ID inválido.']);
        exit;
    }

    // ===================== BUSCAR OM =====================
    $stmt = $conexao->prepare("SELECT id, nome, abreviatura, nivel FROM organizacoes_militares WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $resultado = $stmt->get_result();

    if ($resultado->num_rows === 0) {
        echo json_encode(['status' => 'erro', 'mensagem' => 'Organização Militar não encontrada.']);
        exit;
    }

    $om = $resultado->fetch_assoc();

    echo json_encode([
        'status' => 'sucesso',
        'om' => $om
    ]);

} catch (Exception $e) {
    echo json_encode([
        'status' => 'erro',
        'mensagem' => 'Erro inesperado: ' . $e->getMessage()
    ]);
}
?>
