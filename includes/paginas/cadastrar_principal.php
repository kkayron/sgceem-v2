<?php
header('Content-Type: application/json');
session_start();
include_once('../../conexao/config.php');

$nome = trim($_POST['nome'] ?? '');
$icone = trim($_POST['icone'] ?? '');
$ordem = intval($_POST['ordem'] ?? 0);

if (!$nome) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'O nome do tópico é obrigatório.']);
    exit;
}

if (!$icone) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Selecione um ícone.']);
    exit;
}

if ($ordem <= 0) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'A ordem deve ser maior que zero.']);
    exit;
}

// ==============================
// 1) Verifica se já existe tópico com mesmo nome
// ==============================
$stmtCheck = $conexao->prepare("SELECT id FROM paginas_principal WHERE nome = ?");
$stmtCheck->bind_param("s", $nome);
$stmtCheck->execute();
$stmtCheck->store_result();

if ($stmtCheck->num_rows > 0) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Já existe um tópico com este nome.']);
    exit;
}
$stmtCheck->close();

// ==============================
// 2) Verifica conflito de ordem
// ==============================
$stmtCheckOrder = $conexao->prepare("SELECT id FROM paginas_principal WHERE ordem = ?");
$stmtCheckOrder->bind_param("i", $ordem);
$stmtCheckOrder->execute();
$resultOrder = $stmtCheckOrder->get_result();

if ($resultOrder->num_rows > 0) {
    $existente = $resultOrder->fetch_assoc();
    $idExistente = $existente['id'];

    // Incrementa ordem do existente
    $stmtUpdate = $conexao->prepare("UPDATE paginas_principal SET ordem = ordem + 1 WHERE id = ?");
    $stmtUpdate->bind_param("i", $idExistente);
    $stmtUpdate->execute();
    $stmtUpdate->close();
}
$stmtCheckOrder->close();

// ==============================
// 3) Inserir novo tópico
// ==============================
$stmtInsert = $conexao->prepare("
    INSERT INTO paginas_principal (nome, icone, ordem)
    VALUES (?, ?, ?)
");
$stmtInsert->bind_param("ssi", $nome, $icone, $ordem);

if ($stmtInsert->execute()) {
    echo json_encode(['status' => 'sucesso', 'mensagem' => 'Tópico cadastrado com sucesso!']);
} else {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Erro ao cadastrar tópico.']);
}

$stmtInsert->close();
?>
