<?php
session_start();
include_once("../../conexao/config.php");
include_once("../../includes/funcoes/log.php");

header('Content-Type: application/json; charset=utf-8');

$id = $_POST['id'] ?? null;

if (!$id) {
    echo json_encode(['success' => false, 'message' => 'ID inválido']);
    exit;
}

// Buscar dados do Fornecedor antes de deletar
$stmt_select = $conexao->prepare("SELECT id, nome_empresa, cnpj_empresa, data_cadastro, categoria_empresa, contato_nome, contato_numero, contato_email FROM fin_fornecedores WHERE id = ?");
$stmt_select->bind_param("i", $id);
$stmt_select->execute();
$result = $stmt_select->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Fornecedor não encontrado']);
    exit;
}

$forn = $result->fetch_assoc();
$stmt_select->close();

$usuarioLogado = $_SESSION['usuario_id'] ?? 0;


// Deletar OS principal
$stmt_delete_os = $conexao->prepare("DELETE FROM fin_fornecedores WHERE id = ?");
$stmt_delete_os->bind_param("i", $id);

if ($stmt_delete_os->execute()) {
    $descricao = "Fornecedor ID $id deletado: Empresa: {$forn['nome_empresa']}, CNPJ: {$forn['cnpj_empresa']}, Data de Cadastro: {$forn['data_cadastro']}, Categoria da empresa: {$forn['categoria_empresa']}, Nome do contato: {$forn['contato_nome']}, Número do contato: {$forn['contato_numero']}, E-mail da empresa: {$forn['contato_email']}";
    registrar_log($conexao, $usuarioLogado, 'Deletar Fornecedor', $descricao, $id);

    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Erro ao deletar: ' . $stmt_delete_os->error]);
}

$stmt_delete_os->close();
?>
