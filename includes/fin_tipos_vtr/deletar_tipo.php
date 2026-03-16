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
    
    // ===================== PUXA OS DADOS DO TIPO =====================
    
        $stmt = $conexao->prepare("
        SELECT id, abreviatura, descricao, tipo
        FROM config_tiposvtreqp
        WHERE id = ?
    ");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $resultado = $stmt->get_result();

    if ($resultado->num_rows === 0) {
        echo json_encode(['status' => 'erro', 'mensagem' => 'Tipo não encontrado.']);
        exit;
    }

    $tipo = $resultado->fetch_assoc();
    

    // ===================== VERIFICA VÍNCULO COM FROTA =====================
    // 🔴 Ajuste o campo se na frota for id_tipo ao invés de tipo
    $stmtVerificaFrota = $conexao->prepare("
        SELECT COUNT(*) AS total 
        FROM frota 
        WHERE ativo = ?
    ");
    $stmtVerificaFrota->bind_param("s", $tipo['abreviatura']);
    $stmtVerificaFrota->execute();
    $resultado = $stmtVerificaFrota->get_result()->fetch_assoc();

    if ($resultado['total'] > 0) {
        echo json_encode([
            'success' => false,
            'message' => 'Não é possível excluir esta categoria, pois ela está vinculada a uma ou mais viaturas/equipamentos.'
        ]);
        exit;
    }

    // ===================== DELETAR TIPO =====================
    $stmt = $conexao->prepare("
        DELETE FROM config_tiposvtreqp 
        WHERE id = ?
    ");
    $stmt->bind_param("i", $id);

    if ($stmt->execute()) {
        echo json_encode([
            'success' => true,
            'message' => 'Categoria excluída com sucesso.'
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Erro ao excluir a categoria.'
        ]);
    }

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Erro inesperado: ' . $e->getMessage()
    ]);
}
