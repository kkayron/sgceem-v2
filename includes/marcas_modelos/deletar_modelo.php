<?php
header('Content-Type: application/json; charset=utf-8');
include_once('../../conexao/config.php');

try {
    // ===================== VALIDAÇÃO DO MÉTODO =====================
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(['success' => false, 'message' => 'Método inválido.']);
        exit;
    }

    $id = $_POST['id'] ?? null;
    if (empty($id) || !is_numeric($id)) {
        echo json_encode(['success' => false, 'message' => 'ID inválido.']);
        exit;
    }

    // ===================== VERIFICA SE MODELO ESTÁ EM USO NA FROTA =====================
    $stmtVerifica = $conexao->prepare("SELECT COUNT(*) AS total FROM frota WHERE modelo = ?");
    $stmtVerifica->bind_param("i", $id);
    $stmtVerifica->execute();
    $res = $stmtVerifica->get_result()->fetch_assoc();

    if ($res['total'] > 0) {
        echo json_encode([
            'success' => false,
            'message' => 'Não é possível excluir este modelo, pois há veículos cadastrados utilizando-o.'
        ]);
        exit;
    }

    // ===================== EXCLUI O MODELO =====================
    $stmt = $conexao->prepare("DELETE FROM config_modelos WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();

    if ($stmt->affected_rows > 0) {
        echo json_encode([
            'success' => true,
            'message' => 'Modelo excluído com sucesso.'
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Modelo não encontrado ou já foi excluído.'
        ]);
    }

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Erro inesperado: ' . $e->getMessage()
    ]);
}
?>
