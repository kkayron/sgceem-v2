<?php
session_start();
header('Content-Type: application/json');
include_once("../../conexao/config.php");
include_once("../../includes/funcoes/log.php");

// Função para validar e obter campo
function post($key) {
    return isset($_POST[$key]) ? trim($_POST[$key]) : null;
}

// Função auxiliar para buscar usuário por função
function buscarResponsavel($conexao, $funcao) {
    $sql = "SELECT nomecompleto, postograd FROM usuarios 
            WHERE funcao = ? AND status = 'sim' LIMIT 1";
    $stmt = $conexao->prepare($sql);
    $stmt->bind_param("s", $funcao);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($user = $result->fetch_assoc()) {
        return $user['nomecompleto'] . " - " . $user['postograd'];
    }
    return "Não há usuário cadastrado com a função";
}

// ===========================
// RECEBE OS DADOS DO FORM
// ===========================
$categoria_empresa = post('categoria_empresa');
$nome_empresa = post('nome_empresa');

function limparCNPJ($cnpj) {
    return preg_replace('/\D/', '', $cnpj);
}
$cnpj_empresa = limparCNPJ(post('cnpj_empresa'));

$contato_nome = post('contato_nome');
$data_cadastro = date('Y-m-d');
$contato_numero = post('contato_numero');
$contato_email = post('contato_email');
$aberta_por = $_SESSION['usuario_id'] ?? null;

$batalhao = post('batalhao');  // <-- NOVO (vem do formulário)

if (!$batalhao) {
    echo json_encode([
        "status" => "erro",
        "mensagem" => "Erro: batalhão não informado no cadastro."
    ]);
    exit;
}

// ===============================================================
// VERIFICA SE JÁ EXISTE O MESMO CNPJ CADASTRADO PARA O MESMO BATALHÃO
// ===============================================================
$sql_verifica = "
    SELECT id 
    FROM fin_fornecedores 
    WHERE cnpj_empresa = ? 
    AND batalhao = ?
    LIMIT 1
";

$stmt_verifica = $conexao->prepare($sql_verifica);
$stmt_verifica->bind_param("si", $cnpj_empresa, $batalhao);
$stmt_verifica->execute();
$result_verifica = $stmt_verifica->get_result();

if ($result_verifica->num_rows > 0) {
    echo json_encode([
        "status" => "erro",
        "mensagem" => "Já existe um fornecedor com este CNPJ cadastrado para este batalhão."
    ]);
    exit;
}

// OBS: Se existir o mesmo CNPJ em OUTRO batalhão → PERMITIDO cadastrar.

// =============================
// BUSCA RESPONSÁVEIS (MANTIDO)
// =============================
$cmt_ceem = buscarResponsavel($conexao, 'Cmt Cia E Eqp Mnt');
$ch_suprimento = buscarResponsavel($conexao, 'Ch Financeiro');
$ch_controle = buscarResponsavel($conexao, 'Ch Seç Ctrl');

// =============================
// INSERT DO FORNECEDOR (AJUSTADO PARA INCLUIR BATALHAO)
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

if ($stmt->execute()) {

    $descricao = "Novo fornecedor cadastrado: {$nome_empresa} - {$cnpj_empresa} - Batalhão {$batalhao}";
    registrar_log($conexao, $aberta_por, 'Cadastrar fornecedor', $descricao);

    echo json_encode([
        "status" => "sucesso",
        "mensagem" => "Fornecedor cadastrado com sucesso!"
    ]);
} else {
    echo json_encode([
        "status" => "erro",
        "mensagem" => "Erro ao salvar no banco: " . $stmt->error
    ]);
}
?>
