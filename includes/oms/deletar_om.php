<?php
header('Content-Type: application/json');
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

    // ===================== INÍCIO DA TRANSAÇÃO =====================
    $conexao->begin_transaction();

    // 1️⃣ Deletar vínculos de subordinação onde esta OM é a "maior"
    $stmtSub = $conexao->prepare("DELETE FROM organizacoes_militares_sub WHERE id_om_maior = ?");
    $stmtSub->bind_param("i", $id);
    $stmtSub->execute();

    // 2️⃣ Deletar vínculos onde esta OM é a "menor" (caso exista em alguma relação)
    $stmtSub2 = $conexao->prepare("DELETE FROM organizacoes_militares_sub WHERE id_om_menor = ?");
    $stmtSub2->bind_param("i", $id);
    $stmtSub2->execute();

    // 3️⃣ Deletar a própria OM
    $stmtOM = $conexao->prepare("DELETE FROM organizacoes_militares WHERE id = ?");
    $stmtOM->bind_param("i", $id);
    $stmtOM->execute();

    // ===================== CONFIRMAR =====================
    $conexao->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Organização Militar e suas relações foram excluídas com sucesso.'
    ]);

} catch (Exception $e) {
    // Reverter se der erro
    if ($conexao->in_transaction()) {
        $conexao->rollback();
    }

    echo json_encode([
        'success' => false,
        'message' => 'Erro ao excluir: ' . $e->getMessage()
    ]);
}
?>
