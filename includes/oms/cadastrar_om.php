<?php
header('Content-Type: application/json');
include_once('../../conexao/config.php');

try {
    // ===================== VALIDAÇÃO DO MÉTODO =====================
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(['status' => 'erro', 'mensagem' => 'Método inválido.']);
        exit;
    }

    // ===================== RECEBENDO DADOS =====================
    $nome = trim($_POST['nome'] ?? '');
    $abreviatura = trim($_POST['abreviatura'] ?? '');
    $nivel = trim($_POST['nivel'] ?? '');

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

    // ===================== VERIFICAR SE JÁ EXISTE =====================
    $stmtVerifica = $conexao->prepare("SELECT id FROM organizacoes_militares WHERE nome = ? OR abreviatura = ?");
    $stmtVerifica->bind_param("ss", $nome, $abreviatura);
    $stmtVerifica->execute();
    $resultado = $stmtVerifica->get_result();

    if ($resultado->num_rows > 0) {
        echo json_encode([
            'status' => 'erro',
            'mensagem' => 'Já existe uma Organização Militar com este nome ou abreviatura.'
        ]);
        exit;
    }

    // ===================== INSERIR NOVA OM =====================
    $stmt = $conexao->prepare("INSERT INTO organizacoes_militares (nome, abreviatura, nivel) VALUES (?, ?, ?)");
    $stmt->bind_param("ssi", $nome, $abreviatura, $nivel);

    if ($stmt->execute()) {
        echo json_encode([
            'status' => 'sucesso',
            'mensagem' => 'Organização Militar cadastrada com sucesso!'
        ]);
    } else {
        echo json_encode([
            'status' => 'erro',
            'mensagem' => 'Erro ao cadastrar Organização Militar. Tente novamente.'
        ]);
    }

} catch (Exception $e) {
    echo json_encode([
        'status' => 'erro',
        'mensagem' => 'Erro inesperado: ' . $e->getMessage()
    ]);
}
?>
