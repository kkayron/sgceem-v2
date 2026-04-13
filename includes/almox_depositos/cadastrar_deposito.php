<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
$pagina_id = 50;

require_once('../api/seguranca_json_cadastrar.php');

include_once("../../conexao/config.php");
include_once("../../includes/funcoes/log.php");

function post($key) {
    return isset($_POST[$key]) ? trim($_POST[$key]) : null;
}

$usuario_id = $_SESSION['usuario_id'] ?? null;

// =============================
// Captura dos dados
// =============================
$batalhao             = post('batalhao');
$nome_deposito        = post('nome_deposito');
$local_deposito       = post('local_deposito');
$observacoes_deposito = post('observacoes_deposito');

// =============================
// Validação básica
// =============================
if (!$batalhao || !$nome_deposito) {
    echo json_encode([
        "status" => "erro",
        "mensagem" => "Preencha todos os campos obrigatórios."
    ]);
    exit;
}

// =============================
// Verificação de duplicidade
// =============================
$sqlVerifica = "
    SELECT id
    FROM almox_depositos
    WHERE nome_deposito = ?
      AND batalhao = ?
    LIMIT 1
";

$stmtVerifica = $conexao->prepare($sqlVerifica);
if (!$stmtVerifica) {
    echo json_encode([
        "status" => "erro",
        "mensagem" => "Erro na verificação de duplicidade: " . $conexao->error
    ]);
    exit;
}

$stmtVerifica->bind_param("si", $nome_deposito, $batalhao);
$stmtVerifica->execute();
$stmtVerifica->bind_result($id_existente);
$existe = $stmtVerifica->fetch();
$stmtVerifica->close();

if ($existe) {
    echo json_encode([
        "status" => "erro",
        "mensagem" => "Já existe um depósito com este nome neste batalhão."
    ]);
    exit;
}

// =============================
// Inserção no banco
// =============================
$sql = "
    INSERT INTO almox_depositos (
        batalhao,
        nome_deposito,
        local_deposito,
        observacoes_deposito
    ) VALUES (?, ?, ?, ?)
";

$stmt = $conexao->prepare($sql);
if (!$stmt) {
    echo json_encode([
        "status" => "erro",
        "mensagem" => "Erro ao preparar inserção: " . $conexao->error
    ]);
    exit;
}

$stmt->bind_param(
    "isss",
    $batalhao,
    $nome_deposito,
    $local_deposito,
    $observacoes_deposito
);

if ($stmt->execute()) {

    $id_deposito = $stmt->insert_id;

    registrar_log(
        $conexao,
        $usuario_id,
        'Cadastro de depósito',
        "Depósito cadastrado: ID $id_deposito - $nome_deposito"
    );

    echo json_encode([
        "status" => "sucesso",
        "mensagem" => "Depósito cadastrado com sucesso!"
    ]);

} else {

    echo json_encode([
        "status" => "erro",
        "mensagem" => "Erro ao cadastrar depósito: " . $stmt->error
    ]);
}
