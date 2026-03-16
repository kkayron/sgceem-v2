<?php
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, must-revalidate');
header('Expires: 0');

ob_start();
ini_set('display_errors', 0);
error_reporting(E_ALL);

include_once('../../conexao/config.php');

$response = [];

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        $response = ['status' => 'erro', 'mensagem' => 'Método inválido.'];
        echo json_encode($response);
        exit;
    }

    $marca_id = intval($_POST['marca_id'] ?? 0);
    $modelo   = trim($_POST['modelo'] ?? '');

    if ($marca_id <= 0 || empty($modelo)) {
        $response = ['status' => 'erro', 'mensagem' => 'Preencha todos os campos obrigatórios.'];
        echo json_encode($response);
        exit;
    }

    // Verifica duplicidade (mesma marca + modelo)
    $stmtVerifica = $conexao->prepare("SELECT id FROM config_modelos WHERE id_marca = ? AND nome_modelo = ?");
    $stmtVerifica->bind_param("is", $marca_id, $modelo);
    $stmtVerifica->execute();
    $res = $stmtVerifica->get_result();

    if ($res && $res->num_rows > 0) {
        $response = ['status' => 'erro', 'mensagem' => 'Já existe este modelo cadastrado para esta marca.'];
        echo json_encode($response);
        exit;
    }

    $stmt = $conexao->prepare("INSERT INTO config_modelos (id_marca, nome_modelo) VALUES (?, ?)");
    $stmt->bind_param("is", $marca_id, $modelo);

    if ($stmt->execute()) {
        $response = ['status' => 'sucesso', 'mensagem' => 'Modelo cadastrado com sucesso!'];
    } else {
        $response = ['status' => 'erro', 'mensagem' => 'Erro ao cadastrar o modelo.'];
    }

    $stmt->close();
    $stmtVerifica->close();
    $conexao->close();

} catch (Throwable $e) {
    $response = ['status' => 'erro', 'mensagem' => 'Erro inesperado: ' . $e->getMessage()];
}

ob_clean();
echo json_encode($response);
exit;
