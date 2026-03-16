<?php
// php_cad_fornecedor.php
session_start();

// evita que warnings/notices quebrem o JSON
ini_set('display_errors', 0);
error_reporting(0);

// Usa output buffering para capturar qualquer saída indevida e limpá-la antes de enviar JSON
ob_start();

header('Content-Type: application/json; charset=utf-8');

include_once("../../conexao/config.php");
include_once("../../includes/funcoes/log.php");

// pequena função helper para responder e sair (limpa buffer antes)
function resposta_json_and_exit($arr) {
    // limpa qualquer saída anterior que possa ter sido gerada
    if (ob_get_length()) {
        ob_clean();
    }
    echo json_encode($arr, JSON_UNESCAPED_UNICODE);
    exit;
}

function post($key) {
    return isset($_POST[$key]) ? trim($_POST[$key]) : null;
}

function limparCNPJ($cnpj) {
    if ($cnpj === null) return '';
    return preg_replace('/\D/', '', $cnpj);
}

// RECEBE OS DADOS DO FORM
$categoria_empresa = post('categoria_empresa');
$nome_empresa     = post('nome_empresa');
$cnpj_empresa     = limparCNPJ(post('cnpj_empresa'));
$contato_nome     = post('contato_nome');
$contato_numero   = post('contato_numero');
$contato_email    = post('contato_email');
$data_cadastro    = date('Y-m-d');
$aberta_por       = $_SESSION['usuario_id'] ?? null;
$batalhao         = post('batalhao');

// VALIDAÇÕES BÁSICAS
if (!$batalhao) {
    resposta_json_and_exit([
        "status" => "erro",
        "mensagem" => "Erro: batalhão não informado no cadastro."
    ]);
}

if (!$nome_empresa) {
    resposta_json_and_exit([
        "status" => "erro",
        "mensagem" => "Informe o nome da empresa."
    ]);
}

if (!$cnpj_empresa) {
    resposta_json_and_exit([
        "status" => "erro",
        "mensagem" => "Informe o CNPJ da empresa."
    ]);
}

// forçar tipos
$batalhao = intval($batalhao);

// ===============================================================
// VERIFICA SE JÁ EXISTE O MESMO CNPJ NO MESMO BATALHÃO
// ===============================================================
$sql_verifica = "SELECT id FROM fin_fornecedores WHERE cnpj_empresa = ? AND batalhao = ? LIMIT 1";
$stmt_verifica = $conexao->prepare($sql_verifica);
if (!$stmt_verifica) {
    resposta_json_and_exit([
        "status" => "erro",
        "mensagem" => "Erro ao preparar verificação: " . $conexao->error
    ]);
}

$stmt_verifica->bind_param("si", $cnpj_empresa, $batalhao);
if (!$stmt_verifica->execute()) {
    resposta_json_and_exit([
        "status" => "erro",
        "mensagem" => "Erro ao executar verificação: " . $stmt_verifica->error
    ]);
}

$result_verifica = $stmt_verifica->get_result();
if ($result_verifica && $result_verifica->num_rows > 0) {
    resposta_json_and_exit([
        "status" => "erro",
        "mensagem" => "Já existe um fornecedor com este CNPJ cadastrado para este batalhão."
    ]);
}
$stmt_verifica->close();

// =============================
// INSERT DO FORNECEDOR
// =============================
$sql = "INSERT INTO fin_fornecedores (
    nome_empresa, 
    cnpj_empresa, 
    data_cadastro, 
    categoria_empresa, 
    contato_nome, 
    contato_numero, 
    contato_email,
    batalhao
) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";

$stmt = $conexao->prepare($sql);
if (!$stmt) {
    resposta_json_and_exit([
        "status" => "erro",
        "mensagem" => "Erro ao preparar SQL: " . $conexao->error
    ]);
}

$stmt->bind_param(
    "sssssssi",
    $nome_empresa,
    $cnpj_empresa,
    $data_cadastro,
    $categoria_empresa,
    $contato_nome,
    $contato_numero,
    $contato_email,
    $batalhao
);

if (!$stmt->execute()) {
    resposta_json_and_exit([
        "status" => "erro",
        "mensagem" => "Erro ao salvar no banco: " . $stmt->error
    ]);
}

// sucesso
$novo_id = $stmt->insert_id;
$descricao = "Novo fornecedor cadastrado: {$nome_empresa} - {$cnpj_empresa} - Batalhão {$batalhao}";
registrar_log($conexao, $aberta_por, 'Cadastrar fornecedor', $descricao);

$resposta = [
    "status" => "sucesso",
    "mensagem" => "Fornecedor cadastrado com sucesso!",
    "id" => $novo_id
];

// limpa buffer e envia JSON
if (ob_get_length()) ob_clean();
echo json_encode($resposta, JSON_UNESCAPED_UNICODE);
exit;
?>
