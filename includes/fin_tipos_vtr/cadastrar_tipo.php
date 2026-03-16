<?php
header('Content-Type: application/json; charset=utf-8');
ob_start();
error_reporting(E_ALL & ~E_NOTICE);

require_once('../../conexao/config.php');

$response = [];

try {

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode([
            'status' => 'erro',
            'mensagem' => 'Método inválido.'
        ]);
        exit;
    }

    // ========= RECEBE CAMPOS =========
    $abreviatura = trim($_POST['abreviatura'] ?? '');
    $descricao   = trim($_POST['descricao'] ?? '');
    $tipo        = trim($_POST['tipo'] ?? '');

    // ========= VALIDAÇÕES =========
    if (empty($abreviatura) || empty($descricao) || empty($tipo)) {
        echo json_encode([
            'status' => 'erro',
            'mensagem' => 'Todos os campos são obrigatórios.'
        ]);
        exit;
    }

    if (!in_array($tipo, ['Vtr', 'Eqp'])) {
        echo json_encode([
            'status' => 'erro',
            'mensagem' => 'Categoria inválida.'
        ]);
        exit;
    }

    // ========= DUPLICIDADE ABREVIATURA =========
    $stmt = $conexao->prepare("SELECT id FROM config_tiposvtreqp WHERE abreviatura = ?");
    $stmt->bind_param("s", $abreviatura);
    $stmt->execute();
    $r = $stmt->get_result();

    if ($r->num_rows > 0) {
        echo json_encode([
            'status' => 'erro',
            'mensagem' => 'Já existe essa abreviatura cadastrada.'
        ]);
        exit;
    }

    // ========= DUPLICIDADE DESCRIÇÃO =========
    $stmt2 = $conexao->prepare("SELECT id FROM config_tiposvtreqp WHERE descricao = ?");
    $stmt2->bind_param("s", $descricao);
    $stmt2->execute();
    $r2 = $stmt2->get_result();

    if ($r2->num_rows > 0) {
        echo json_encode([
            'status' => 'erro',
            'mensagem' => 'Já existe essa descrição cadastrada.'
        ]);
        exit;
    }

    // ========= INSERT =========
    $stmt3 = $conexao->prepare("
        INSERT INTO config_tiposvtreqp (abreviatura, descricao, tipo)
        VALUES (?, ?, ?)
    ");
    $stmt3->bind_param("sss", $abreviatura, $descricao, $tipo);

    if ($stmt3->execute()) {
        $response = [
            'status' => 'sucesso',
            'mensagem' => 'Tipo cadastrado com sucesso!'
        ];
    } else {
        $response = [
            'status' => 'erro',
            'mensagem' => 'Erro ao cadastrar no banco.'
        ];
    }

} catch (Throwable $e) {
    $response = [
        'status'  => 'erro',
        'mensagem'=> 'Erro inesperado: ' . $e->getMessage()
    ];
}

// LIMPA QUALQUER LIXO DE BUFFER ANTES DO JSON
ob_clean();
echo json_encode($response);
exit;
