<?php
header('Content-Type: application/json; charset=utf-8');
include_once('../../conexao/config.php');

try {
    // ===================== VALIDAÇÃO =====================
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(['success' => false, 'message' => 'Método inválido.']);
        exit;
    }

    $id = $_POST['id'] ?? null;

    if (empty($id) || !is_numeric($id)) {
        echo json_encode(['success' => false, 'message' => 'ID inválido.']);
        exit;
    }

    // ===================== VERIFICA SE EXISTE FROTA VINCULADA =====================
    $stmtVerificaFrota = $conexao->prepare("SELECT COUNT(*) AS total FROM frota WHERE marca = ?");
    $stmtVerificaFrota->bind_param("i", $id);
    $stmtVerificaFrota->execute();
    $resultado = $stmtVerificaFrota->get_result()->fetch_assoc();

    if ($resultado['total'] > 0) {
        echo json_encode([
            'success' => false,
            'message' => 'Não é possível excluir esta marca, pois ela está vinculada a uma ou mais frotas cadastradas.'
        ]);
        exit;
    }

    // ===================== INÍCIO DA TRANSAÇÃO =====================
    $conexao->begin_transaction();

    // 1️⃣ Deletar modelos vinculados à marca
    $stmtModelos = $conexao->prepare("DELETE FROM config_modelos WHERE id_marca = ?");
    $stmtModelos->bind_param("i", $id);
    $stmtModelos->execute();

    // 2️⃣ Deletar a própria marca
    $stmtMarca = $conexao->prepare("DELETE FROM config_marcas WHERE id = ?");
    $stmtMarca->bind_param("i", $id);
    $stmtMarca->execute();

    // ===================== CONFIRMAR TRANSAÇÃO =====================
    $conexao->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Marca e seus modelos vinculados foram excluídos com sucesso.'
    ]);

} catch (Exception $e) {
    if ($conexao->in_transaction()) {
        $conexao->rollback();
    }

    echo json_encode([
        'success' => false,
        'message' => 'Erro ao excluir: ' . $e->getMessage()
    ]);
}
?>
