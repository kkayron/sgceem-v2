<?php
// ===================== CABEÇALHOS =====================
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, must-revalidate');
header('Expires: 0');

// ===================== SUPRESSÃO DE ERROS DE SAÍDA =====================
// Impede qualquer aviso, espaço ou erro de sair fora do JSON
ob_start();
ini_set('display_errors', 0);
error_reporting(E_ALL);

include_once('../../conexao/config.php');

$response = [];

try {
    // ===================== VERIFICA MÉTODO =====================
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        $response = [
            'status' => 'erro',
            'mensagem' => 'Método inválido.'
        ];
        echo json_encode($response);
        exit;
    }

    // ===================== RECEBE OS DADOS =====================
    $marca = trim($_POST['marca'] ?? '');

    // ===================== VALIDA CAMPOS =====================
    if (empty($marca)) {
        $response = [
            'status' => 'erro',
            'mensagem' => 'O campo "Nome da Marca" é obrigatório.'
        ];
        echo json_encode($response);
        exit;
    }

    // ===================== VERIFICA DUPLICIDADE =====================
    $stmtVerifica = $conexao->prepare("SELECT id FROM config_marcas WHERE marca = ?");
    $stmtVerifica->bind_param("s", $marca);
    $stmtVerifica->execute();
    $resultado = $stmtVerifica->get_result();

    if ($resultado && $resultado->num_rows > 0) {
        $response = [
            'status' => 'erro',
            'mensagem' => 'Já existe uma marca cadastrada com este nome.'
        ];
        echo json_encode($response);
        exit;
    }

    // ===================== INSERE NOVA MARCA =====================
    $stmt = $conexao->prepare("INSERT INTO config_marcas (marca) VALUES (?)");
    $stmt->bind_param("s", $marca);

    if ($stmt->execute()) {
        $response = [
            'status' => 'sucesso',
            'mensagem' => 'Marca cadastrada com sucesso!'
        ];
    } else {
        $response = [
            'status' => 'erro',
            'mensagem' => 'Erro ao cadastrar a marca. Tente novamente.'
        ];
    }

    $stmt->close();
    $stmtVerifica->close();
    $conexao->close();

} catch (Throwable $e) {
    $response = [
        'status' => 'erro',
        'mensagem' => 'Erro inesperado: ' . $e->getMessage()
    ];
}

// ===================== LIMPA QUALQUER SAÍDA EXTRA =====================
ob_clean();
echo json_encode($response);
exit;
