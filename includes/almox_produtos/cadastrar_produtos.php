<?php
session_start();
header('Content-Type: application/json');
include_once("../../conexao/config.php");
include_once("../../includes/funcoes/log.php");

function post($key) {
    return isset($_POST[$key]) ? trim($_POST[$key]) : null;
}

$usuario_id = $_SESSION['usuario_id'] ?? null;

$data_inclusao     = date('Y-m-d');
$nome_produto      = post('nome_produto');
$batalhao          = post('batalhao');
$codigo_produto    = post('codigo_produto');
$unidade           = post('unidade');
$categoria_produto = post('categoria_produto');
$obs_produto       = post('obs_produto');
$data_inclusao     = post('data_inclusao');
$estoque_minimo    = post('estoque_minimo');

// =============================
// Validação básica
// =============================
if (!$nome_produto || !$codigo_produto) {
    echo json_encode([
        "status" => "erro",
        "mensagem" => "Preencha todos os campos obrigatórios."
    ]);
    exit;
}

// =============================
// Verificação de duplicidade
// =============================
$sqlVerifica = "SELECT id FROM almox_produtos WHERE codigo_produto = ? AND batalhao = ? LIMIT 1";
$stmtVerifica = $conexao->prepare($sqlVerifica);
if ($stmtVerifica) {
    $stmtVerifica->bind_param("si", $codigo_produto, $batalhao);
    $stmtVerifica->execute();
    $stmtVerifica->bind_result($id_existente);
    $existe = $stmtVerifica->fetch();
    $stmtVerifica->close();

    if ($existe) {
        echo json_encode([
            "status" => "erro",
            "mensagem" => "Já existe um produto com este código neste batalhão."
        ]);
        exit;
    }
} else {
    echo json_encode([
        "status" => "erro",
        "mensagem" => "Erro na verificação de duplicidade: " . $conexao->error
    ]);
    exit;
}

// =============================
// Inserção no banco
// =============================
$sql = "INSERT INTO almox_produtos (
    batalhao, data_inclusao, nome_produto, codigo_produto, categoria_produto, obs_produto, estoque_minimo, unidade
) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";

$stmt = $conexao->prepare($sql);
if (!$stmt) {
    echo json_encode([
        "status" => "erro",
        "mensagem" => "Erro ao preparar inserção: " . $conexao->error
    ]);
    exit;
}

$stmt->bind_param(
    "ssssssis",
    $batalhao,
    $data_inclusao,
    $nome_produto,
    $codigo_produto,
    $categoria_produto,
    $obs_produto,
    $estoque_minimo,
    $unidade
);

if ($stmt->execute()) {
    $id_produto = $stmt->insert_id;

    registrar_log($conexao, $usuario_id, 'Cadastro de produto', "Produto cadastrado: ID $id_produto - $nome_produto");

    echo json_encode([
        "status" => "sucesso",
        "mensagem" => "Produto cadastrado com sucesso!"
    ]);
} else {
    echo json_encode([
        "status" => "erro",
        "mensagem" => "Erro ao cadastrar produto: " . $stmt->error
    ]);
}
?>
