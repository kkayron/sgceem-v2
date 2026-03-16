<?php
header('Content-Type: application/json');
session_start();
include_once('../../conexao/config.php');

$id = intval($_POST['id'] ?? 0);
$nome = trim($_POST['nome'] ?? '');
$icone = trim($_POST['icone'] ?? '');
$ordem = intval($_POST['ordem'] ?? 1);

if ($id <= 0 || !$nome) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Dados inválidos para edição.']);
    exit;
}

if (!$icone) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'O ícone é obrigatório.']);
    exit;
}

// ============================
// 🔎 1. Verifica nome duplicado
// ============================
$stmtCheck = $conexao->prepare("SELECT id FROM paginas_principal WHERE nome = ? AND id != ?");
$stmtCheck->bind_param("si", $nome, $id);
$stmtCheck->execute();
$stmtCheck->store_result();

if ($stmtCheck->num_rows > 0) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Já existe outro tópico com este nome.']);
    exit;
}
$stmtCheck->close();

// ============================
// 🔎 2. Verifica conflito de ordem
// ============================
$stmtOrdem = $conexao->prepare("SELECT id FROM paginas_principal WHERE ordem = ? AND id != ?");
$stmtOrdem->bind_param("ii", $ordem, $id);
$stmtOrdem->execute();
$resultOrdem = $stmtOrdem->get_result();
$topicoConflito = $resultOrdem->fetch_assoc();
$stmtOrdem->close();

// ============================
// 🔄 3. Se houver conflito, mover o outro
// ============================
if ($topicoConflito) {
    $novaOrdemOutro = $ordem + 1;
    $stmtMove = $conexao->prepare("UPDATE paginas_principal SET ordem = ? WHERE id = ?");
    $stmtMove->bind_param("ii", $novaOrdemOutro, $topicoConflito['id']);
    $stmtMove->execute();
    $stmtMove->close();
}

// ============================
// ✏ 4. Atualiza o tópico
// ============================
$stmt = $conexao->prepare("
    UPDATE paginas_principal
    SET nome = ?, icone = ?, ordem = ?
    WHERE id = ?
");
$stmt->bind_param("ssii", $nome, $icone, $ordem, $id);

// ============================
// 📌 Execução final
// ============================
if ($stmt->execute()) {
    echo json_encode(['status' => 'sucesso', 'mensagem' => 'Tópico atualizado com sucesso!']);
} else {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Erro ao atualizar tópico.']);
}

$stmt->close();
?>
