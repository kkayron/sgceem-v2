<?php
header('Content-Type: application/json');
include_once('../../conexao/config.php');

try {
    // ===================== VALIDAÇÃO DO MÉTODO =====================
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(['status' => 'erro', 'mensagem' => 'Método inválido.']);
        exit;
    }

    // ===================== RECEBER DADOS =====================
    $id = $_POST['id'] ?? null;
    $nome = trim($_POST['nome'] ?? '');
    $abreviatura = trim($_POST['abreviatura'] ?? '');
    $nivel = trim($_POST['nivel'] ?? '');

    if (empty($id) || !is_numeric($id)) {
        echo json_encode(['status' => 'erro', 'mensagem' => 'ID inválido.']);
        exit;
    }

    // ===================== VALIDAÇÃO DE CAMPOS =====================
    if (empty($nome) || empty($abreviatura) || empty($nivel)) {
        echo json_encode([
            'status' => 'erro',
            'mensagem' => 'Preencha todos os campos obrigatórios (Nome, Abreviatura e Nível).'
        ]);
        exit;
    }

    if (!is_numeric($nivel) || $nivel < 1) {
        echo json_encode([
            'status' => 'erro',
            'mensagem' => 'O campo Nível deve ser um número válido maior ou igual a 1.'
        ]);
        exit;
    }

    // ===================== VERIFICAR EXISTÊNCIA DA OM =====================
    $stmtExiste = $conexao->prepare("SELECT id FROM organizacoes_militares WHERE id = ?");
    $stmtExiste->bind_param("i", $id);
    $stmtExiste->execute();
    $resExiste = $stmtExiste->get_result();

    if ($resExiste->num_rows === 0) {
        echo json_encode(['status' => 'erro', 'mensagem' => 'Organização Militar não encontrada.']);
        exit;
    }

    // ===================== CHECAR DUPLICIDADE =====================
    $stmtDup = $conexao->prepare("SELECT id FROM organizacoes_militares WHERE (nome = ? OR abreviatura = ?) AND id != ?");
    $stmtDup->bind_param("ssi", $nome, $abreviatura, $id);
    $stmtDup->execute();
    $resDup = $stmtDup->get_result();

    if ($resDup->num_rows > 0) {
        echo json_encode([
            'status' => 'erro',
            'mensagem' => 'Já existe outra Organização Militar com este nome ou abreviatura.'
        ]);
        exit;
    }

    // ===================== ATUALIZAR OM =====================
    $stmt = $conexao->prepare("UPDATE organizacoes_militares SET nome = ?, abreviatura = ?, nivel = ? WHERE id = ?");
    $stmt->bind_param("ssii", $nome, $abreviatura, $nivel, $id);

    if ($stmt->execute()) {
        echo json_encode([
            'status' => 'sucesso',
            'mensagem' => 'Organização Militar atualizada com sucesso!'
        ]);
    } else {
        echo json_encode([
            'status' => 'erro',
            'mensagem' => 'Erro ao atualizar Organização Militar. Tente novamente.'
        ]);
    }

} catch (Exception $e) {
    echo json_encode([
        'status' => 'erro',
        'mensagem' => 'Erro inesperado: ' . $e->getMessage()
    ]);
}
?>
