<?php
header('Content-Type: application/json');
session_start();
include_once('../../conexao/config.php');

$id = intval($_POST['id'] ?? 0);
$nome = trim($_POST['nome'] ?? '');
$descricao = trim($_POST['descricao'] ?? '');
$tipo = intval($_POST['tipo'] ?? 0);
$ordem = intval($_POST['ordem'] ?? 1);
$arquivo = trim($_POST['arquivo'] ?? ''); 

if ($id <= 0 || !$nome) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Dados inválidos para edição.']);
    exit;
}

if (!$arquivo) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'O arquivo é obrigatório.']);
    exit;
}

// ============================
// 🔎 1. Verifica nome duplicado
// ============================
$stmtCheck = $conexao->prepare("SELECT id FROM paginas WHERE nome = ? AND id != ?");
$stmtCheck->bind_param("si", $nome, $id);
$stmtCheck->execute();
$stmtCheck->store_result();

if ($stmtCheck->num_rows > 0) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Já existe outra página com este nome.']);
    exit;
}
$stmtCheck->close();

// ============================
// 🔎 2. Verificar se existe outro com a mesma ordem no mesmo tipo
// ============================
$stmtOrdem = $conexao->prepare("
    SELECT id 
    FROM paginas 
    WHERE tipo = ? AND ordem = ? AND id != ?
");
$stmtOrdem->bind_param("iii", $tipo, $ordem, $id);
$stmtOrdem->execute();
$resultOrdem = $stmtOrdem->get_result();
$paginaConflito = $resultOrdem->fetch_assoc();
$stmtOrdem->close();

// ============================
// 🔄 3. Se existir conflito, mover o outro para a próxima ordem
// ============================
if ($paginaConflito) {
    $novaOrdemOutro = $ordem + 1;

    $stmtMove = $conexao->prepare("UPDATE paginas SET ordem = ? WHERE id = ?");
    $stmtMove->bind_param("ii", $novaOrdemOutro, $paginaConflito['id']);
    $stmtMove->execute();
    $stmtMove->close();
}

// ============================
// ✏ 4. Atualiza a página atual
// ============================
$stmt = $conexao->prepare("
    UPDATE paginas 
    SET nome = ?, descricao = ?, arquivo = ?, tipo = ?, ordem = ?
    WHERE id = ?
");
$stmt->bind_param("sssiii", $nome, $descricao, $arquivo, $tipo, $ordem, $id);

// ============================
// 📌 Execução final
// ============================
if ($stmt->execute()) {
    echo json_encode(['status' => 'sucesso', 'mensagem' => 'Página atualizada com sucesso!']);
} else {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Erro ao atualizar página.']);
}

$stmt->close();
?>
