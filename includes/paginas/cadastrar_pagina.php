<?php
header('Content-Type: application/json');
session_start();
include_once('../../conexao/config.php');

$nome = trim($_POST['nome'] ?? '');
$descricao = trim($_POST['descricao'] ?? '');
$tipo = intval($_POST['tipo'] ?? 0);
$ordem = intval($_POST['ordem'] ?? 0);
$arquivo = trim($_POST['arquivo'] ?? '');

if (!$nome) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'O nome da página é obrigatório.']);
    exit;
}

if ($tipo <= 0) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Selecione uma página principal.']);
    exit;
}

if ($ordem <= 0) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'A ordem deve ser um número maior que zero.']);
    exit;
}

if (!$arquivo) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'O arquivo da página é obrigatório.']);
    exit;
}


// ==============================
// 1) Verifica se já existe página com o mesmo nome
// ==============================
$stmtCheck = $conexao->prepare("SELECT id FROM paginas WHERE nome = ?");
$stmtCheck->bind_param("s", $nome);
$stmtCheck->execute();
$stmtCheck->store_result();

if ($stmtCheck->num_rows > 0) {
    echo json_encode([
        'status' => 'erro',
        'mensagem' => 'Já existe uma página com este nome.'
    ]);
    exit;
}
$stmtCheck->close();


// ==============================
// 2) Verifica se existe outra página com mesma ordem no mesmo tipo
// ==============================
$stmtCheckOrder = $conexao->prepare("
    SELECT id FROM paginas 
    WHERE tipo = ? AND ordem = ?
");
$stmtCheckOrder->bind_param("ii", $tipo, $ordem);
$stmtCheckOrder->execute();
$resultOrder = $stmtCheckOrder->get_result();

if ($resultOrder->num_rows > 0) {
    $paginaExistente = $resultOrder->fetch_assoc();
    $idExistente = $paginaExistente['id'];

    // Incrementa a ordem do item existente
    $stmtUpdateOrder = $conexao->prepare("
        UPDATE paginas 
        SET ordem = ordem + 1 
        WHERE id = ?
    ");
    $stmtUpdateOrder->bind_param("i", $idExistente);
    $stmtUpdateOrder->execute();
    $stmtUpdateOrder->close();
}

$stmtCheckOrder->close();


// ==============================
// 3) Inserir nova página
// ==============================

$stmtInsert = $conexao->prepare("
    INSERT INTO paginas (nome, descricao, tipo, ordem, arquivo)
    VALUES (?, ?, ?, ?, ?)
");

/*
 TIPOS CORRETOS:
 nome       → string  (s)
 descricao  → string  (s)
 tipo       → int     (i)
 ordem      → int     (i)
 arquivo    → string  (s)

 LOGO: "ssiis"
*/
$stmtInsert->bind_param("ssiis", $nome, $descricao, $tipo, $ordem, $arquivo);

if ($stmtInsert->execute()) {
    echo json_encode([
        'status' => 'sucesso',
        'mensagem' => 'Página cadastrada com sucesso!'
    ]);
} else {
    echo json_encode([
        'status' => 'erro',
        'mensagem' => 'Erro ao cadastrar página.'
    ]);
}

$stmtInsert->close();
?>
