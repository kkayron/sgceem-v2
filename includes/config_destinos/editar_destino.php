<?php
header('Content-Type: application/json; charset=utf-8');
include_once('../../conexao/config.php');

$pagina_id = 35;

require_once('../api/seguranca_json_editar.php');

if (empty($_SERVER['HTTP_REFERER'])) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Acesso direto não permitido']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Método inválido.']);
    exit;
}

$id = $_POST['id_destino'] ?? null;
$batalhao = $_POST['batalhao'] ?? null;
$destino = trim($_POST['destino'] ?? '');

if (empty($id) || !is_numeric($id)) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'ID inválido.']);
    exit;
}

if (empty($batalhao) || !is_numeric($batalhao)) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Selecione um batalhão válido.']);
    exit;
}

if (empty($destino)) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'O campo Destino é obrigatório.']);
    exit;
}

// Verifica duplicidade
$stmt = $conexao->prepare("
    SELECT id 
    FROM config_destinos 
    WHERE batalhao = ? AND destino = ? AND id != ?
");
$stmt->bind_param("isi", $batalhao, $destino, $id);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows > 0) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Já existe este destino neste batalhão.']);
    exit;
}
$stmt->close();

// Atualiza registro
$stmt = $conexao->prepare("
    UPDATE config_destinos SET batalhao = ?, destino = ? WHERE id = ?
");
$stmt->bind_param("isi", $batalhao, $destino, $id);

if ($stmt->execute()) {
    echo json_encode(['status' => 'sucesso', 'mensagem' => 'Destino atualizado com sucesso!']);
} else {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Erro ao atualizar o destino.']);
}

$stmt->close();
$conexao->close();
