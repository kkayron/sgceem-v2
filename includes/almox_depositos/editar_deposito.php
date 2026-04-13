<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
$pagina_id = 50;

require_once('../api/seguranca_json_editar.php');

include_once("../../conexao/config.php");
include_once("../../includes/funcoes/log.php");

$id = $_POST['id'] ?? null;
if (!$id) {
    echo json_encode(["status" => "erro", "mensagem" => "ID do depósito não recebido."]);
    exit;
}

$usuario_id = $_SESSION['usuario_id'] ?? 0;

// ===============================
// Busca dados antigos
// ===============================
$consulta = $conexao->prepare("SELECT * FROM almox_depositos WHERE id = ?");
$consulta->bind_param("i", $id);
$consulta->execute();
$old = $consulta->get_result()->fetch_assoc();

if (!$old) {
    echo json_encode(["status" => "erro", "mensagem" => "Depósito não encontrado."]);
    exit;
}

// ===============================
// Novos dados
// ===============================
$nome_deposito        = $_POST['nome_deposito'] ?? '';
$local_deposito       = $_POST['local_deposito'] ?? '';
$observacoes_deposito = $_POST['observacoes_deposito'] ?? '';

// ===============================
// Atualização
// ===============================
$update = $conexao->prepare("
    UPDATE almox_depositos
    SET nome_deposito = ?, local_deposito = ?, observacoes_deposito = ?
    WHERE id = ?
");

if (!$update) {
    echo json_encode(["status" => "erro", "mensagem" => "Erro no prepare: " . $conexao->error]);
    exit;
}

$update->bind_param(
    "sssi",
    $nome_deposito,
    $local_deposito,
    $observacoes_deposito,
    $id
);

if (!$update->execute()) {
    echo json_encode(["status" => "erro", "mensagem" => "Erro ao atualizar: " . $update->error]);
    exit;
}

// ===============================
// LOG DE ALTERAÇÕES
// ===============================
$alteracoes = [];

$novos = [
    'nome_deposito'        => $nome_deposito,
    'local_deposito'       => $local_deposito,
    'observacoes_deposito' => $observacoes_deposito
];

foreach ($novos as $campo => $novo_valor) {
    $antigo = $old[$campo] ?? '';
    if ($novo_valor != $antigo) {
        $alteracoes[] = ucfirst($campo) . ": '$antigo' → '$novo_valor'";
    }
}

if (!empty($alteracoes)) {
    registrar_log(
        $conexao,
        $usuario_id,
        'Cadastro de depósito',
        'Edição do depósito ID ' . $id . ': ' . implode(', ', $alteracoes)
    );
}

echo json_encode([
    "status" => "sucesso",
    "mensagem" => "Depósito atualizado com sucesso."
]);
