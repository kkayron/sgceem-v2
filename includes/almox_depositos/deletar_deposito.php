<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

include_once("../../conexao/config.php");
include_once("../../includes/funcoes/log.php");

$id = $_POST['id'] ?? null;

if (!$id) {
    echo json_encode(['success' => false, 'message' => 'ID inválido.']);
    exit;
}

$usuarioLogado = $_SESSION['usuario_id'] ?? 0;

// ===============================
// BUSCAR DADOS DO DEPÓSITO
// ===============================
$stmt_dep = $conexao->prepare("
    SELECT d.*, o.nome AS nome_batalhao
    FROM almox_depositos d
    LEFT JOIN organizacoes_militares o ON o.id = d.batalhao
    WHERE d.id = ?
");
$stmt_dep->bind_param("i", $id);
$stmt_dep->execute();
$result = $stmt_dep->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Depósito não encontrado.']);
    exit;
}

$deposito = $result->fetch_assoc();
$stmt_dep->close();

// ===============================
// TRANSAÇÃO (SEGURANÇA)
// ===============================
$conexao->begin_transaction();

try {

    // ===============================
    // DELETA ENTRADAS DO DEPÓSITO
    // ===============================
    $stmt_entradas = $conexao->prepare("
        DELETE FROM almox_entradas WHERE deposito_id = ?
    ");
    $stmt_entradas->bind_param("i", $id);
    $stmt_entradas->execute();
    $qt_entradas = $stmt_entradas->affected_rows;
    $stmt_entradas->close();

    // ===============================
    // DELETA O DEPÓSITO
    // ===============================
    $stmt_del = $conexao->prepare("
        DELETE FROM almox_depositos WHERE id = ?
    ");
    $stmt_del->bind_param("i", $id);
    $stmt_del->execute();

    if ($stmt_del->affected_rows === 0) {
        throw new Exception('Falha ao deletar depósito.');
    }

    $stmt_del->close();

    // ===============================
    // COMMIT
    // ===============================
    $conexao->commit();

    // ===============================
    // LOG
    // ===============================
    $descricao = "Depósito deletado (ID {$id}) | Nome: {$deposito['nome_deposito']} | "
               . "Batalhão: {$deposito['nome_batalhao']} | "
               . "Entradas removidas: {$qt_entradas}";

    registrar_log(
        $conexao,
        $usuarioLogado,
        'Deletar Depósito',
        $descricao,
        $id
    );

    echo json_encode(['success' => true]);

} catch (Exception $e) {

    // ===============================
    // ROLLBACK
    // ===============================
    $conexao->rollback();

    echo json_encode([
        'success' => false,
        'message' => 'Erro ao deletar depósito: ' . $e->getMessage()
    ]);
}
