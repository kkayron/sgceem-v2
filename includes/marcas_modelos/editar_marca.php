<?php
header('Content-Type: application/json; charset=utf-8');
include_once('../../conexao/config.php');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(['status' => 'erro', 'mensagem' => 'Método inválido.']);
        exit;
    }

    $id = $_POST['id'] ?? '';
    $marca = trim($_POST['marca'] ?? '');

    if (empty($id) || !is_numeric($id)) {
        echo json_encode(['status' => 'erro', 'mensagem' => 'ID inválido.']);
        exit;
    }

    if (empty($marca)) {
        echo json_encode(['status' => 'erro', 'mensagem' => 'O campo "Nome da Marca" é obrigatório.']);
        exit;
    }

    // Verifica duplicidade (exceto a própria marca)
    $stmtVerifica = $conexao->prepare("SELECT id FROM config_marcas WHERE marca = ? AND id != ?");
    $stmtVerifica->bind_param("si", $marca, $id);
    $stmtVerifica->execute();
    $resultado = $stmtVerifica->get_result();

    if ($resultado->num_rows > 0) {
        echo json_encode(['status' => 'erro', 'mensagem' => 'Já existe uma marca com este nome.']);
        exit;
    }

    // Atualiza a marca
    $stmt = $conexao->prepare("UPDATE config_marcas SET marca = ? WHERE id = ?");
    $stmt->bind_param("si", $marca, $id);

    if ($stmt->execute()) {
        echo json_encode(['status' => 'sucesso', 'mensagem' => 'Marca atualizada com sucesso!']);
    } else {
        echo json_encode(['status' => 'erro', 'mensagem' => 'Erro ao atualizar a marca.']);
    }

} catch (Exception $e) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Erro inesperado: ' . $e->getMessage()]);
}
?>
