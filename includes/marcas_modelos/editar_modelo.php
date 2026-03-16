<?php
header('Content-Type: application/json; charset=utf-8');
include_once('../../conexao/config.php');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(['status' => 'erro', 'mensagem' => 'Método inválido.']);
        exit;
    }

    $id = $_POST['id'] ?? null;
    $nome_modelo = trim($_POST['nome_modelo'] ?? ''); // <-- espera nome_modelo vindo do form
    $id_marca = $_POST['id_marca'] ?? null;

    if (empty($id) || !is_numeric($id)) {
        echo json_encode(['status' => 'erro', 'mensagem' => 'ID inválido.']);
        exit;
    }

    if (empty($nome_modelo) || empty($id_marca) || !is_numeric($id_marca)) {
        echo json_encode(['status' => 'erro', 'mensagem' => 'Preencha todos os campos obrigatórios.']);
        exit;
    }

    // Verifica duplicidade (mesmo nome_modelo e mesma marca, exceto o próprio registro)
    $stmtVerifica = $conexao->prepare("SELECT id FROM config_modelos WHERE nome_modelo = ? AND id_marca = ? AND id <> ?");
    $stmtVerifica->bind_param("sii", $nome_modelo, $id_marca, $id);
    $stmtVerifica->execute();
    $resultado = $stmtVerifica->get_result();

    if ($resultado->num_rows > 0) {
        echo json_encode(['status' => 'erro', 'mensagem' => 'Já existe um modelo com este nome para esta marca.']);
        exit;
    }

    // Atualiza
    $stmt = $conexao->prepare("UPDATE config_modelos SET nome_modelo = ?, id_marca = ? WHERE id = ?");
    $stmt->bind_param("sii", $nome_modelo, $id_marca, $id);

    if ($stmt->execute()) {
        echo json_encode(['status' => 'sucesso', 'mensagem' => 'Modelo atualizado com sucesso!']);
    } else {
        echo json_encode(['status' => 'erro', 'mensagem' => 'Erro ao atualizar o modelo.']);
    }

} catch (Exception $e) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Erro inesperado: ' . $e->getMessage()]);
}
?>
