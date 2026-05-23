<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

include_once("../../conexao/config.php");
include_once("../../includes/funcoes/log.php");

// =============================
// FUNÇÕES AUXILIARES
// =============================
function resposta_json_and_exit($arr) {
    if (ob_get_length()) ob_clean();
    echo json_encode($arr, JSON_UNESCAPED_UNICODE);
    exit;
}

function post($key) {
    return isset($_POST[$key]) ? trim($_POST[$key]) : null;
}

function limparCNPJ($cnpj) {
    return preg_replace('/\D/', '', $cnpj ?? '');
}

// =============================
// 🔒 PERMISSÃO
// =============================
$pagina_id = 21;
require_once('../api/seguranca_json_cadastrar.php');

// =============================
// 🔒 CSRF
// =============================
if (
    empty($_POST['csrf_token']) ||
    empty($_SESSION['csrf_token']) ||
    !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
) {
    http_response_code(403);
    resposta_json_and_exit(['status' => 'erro', 'mensagem' => 'Token inválido']);
}

// =============================
// 🔒 DADOS DO USUÁRIO
// =============================
$usuarioLogado    = $_SESSION['usuario_id'];
$nivel_usuario    = $_SESSION['usuario']['nivel'] ?? 3;
$batalhao_usuario = $_SESSION['usuario']['batalhao'] ?? 0;

// =============================
// RECEBE DADOS
// =============================
$categoria_empresa = post('categoria_empresa');
$nome_empresa      = post('nome_empresa');
$cnpj_empresa      = limparCNPJ(post('cnpj_empresa'));
$contato_nome      = post('contato_nome');
$contato_numero    = post('contato_numero');
$contato_email     = post('contato_email');
$batalhao          = intval(post('batalhao'));
$data_cadastro     = date('Y-m-d');

// =============================
// VALIDAÇÕES BÁSICAS
// =============================
if (!$batalhao) {
    resposta_json_and_exit(['status' => 'erro', 'mensagem' => 'Batalhão não informado']);
}

if (!$nome_empresa) {
    resposta_json_and_exit(['status' => 'erro', 'mensagem' => 'Informe o nome da empresa']);
}

if (!$cnpj_empresa || strlen($cnpj_empresa) !== 14) {
    resposta_json_and_exit(['status' => 'erro', 'mensagem' => 'CNPJ inválido']);
}

if ($contato_email && !filter_var($contato_email, FILTER_VALIDATE_EMAIL)) {
    resposta_json_and_exit(['status' => 'erro', 'mensagem' => 'Email inválido']);
}

// =============================
// 🔒 VALIDAÇÃO POR BATALHÃO
// =============================
if ($nivel_usuario == 1) {
    // Admin → pode tudo

} elseif ($nivel_usuario == 2) {

    $sqlSubs = "SELECT id_om_menor FROM organizacoes_militares_sub WHERE id_om_maior = ?";
    $stmtSubs = $conexao->prepare($sqlSubs);
    $stmtSubs->bind_param("i", $batalhao_usuario);
    $stmtSubs->execute();
    $resultSubs = $stmtSubs->get_result();

    $batalhoesPermitidos = [$batalhao_usuario];

    while ($row = $resultSubs->fetch_assoc()) {
        $batalhoesPermitidos[] = (int)$row['id_om_menor'];
    }

    if (!in_array($batalhao, $batalhoesPermitidos)) {
        http_response_code(403);
        resposta_json_and_exit([
            'status' => 'erro',
            'mensagem' => 'Sem permissão para cadastrar neste batalhão'
        ]);
    }

} else {
    // Nível 3
    if ($batalhao !== $batalhao_usuario) {
        http_response_code(403);
        resposta_json_and_exit([
            'status' => 'erro',
            'mensagem' => 'Sem permissão para cadastrar neste batalhão'
        ]);
    }
}

// =============================
// VERIFICA DUPLICIDADE
// =============================
$stmt_verifica = $conexao->prepare(
    "SELECT id FROM fin_fornecedores WHERE cnpj_empresa = ? AND batalhao = ? LIMIT 1"
);

$stmt_verifica->bind_param("si", $cnpj_empresa, $batalhao);
$stmt_verifica->execute();
$result_verifica = $stmt_verifica->get_result();

if ($result_verifica->num_rows > 0) {
    resposta_json_and_exit([
        "status" => "erro",
        "mensagem" => "Já existe fornecedor com este CNPJ neste batalhão"
    ]);
}
$stmt_verifica->close();

// =============================
// INSERT
// =============================
$stmt = $conexao->prepare("
    INSERT INTO fin_fornecedores (
        nome_empresa, cnpj_empresa, data_cadastro,
        categoria_empresa, contato_nome, contato_numero,
        contato_email, batalhao
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
");

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
        "mensagem" => "Erro ao salvar: " . $stmt->error
    ]);
}

// =============================
// LOG
// =============================
$novo_id = $stmt->insert_id;

$descricao = "Fornecedor cadastrado: {$nome_empresa} | CNPJ: {$cnpj_empresa} | Batalhão: {$batalhao}";
registrar_log($conexao, $usuarioLogado, 'Cadastrar fornecedor', $descricao);

// 🔒 NOVO TOKEN
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));

// =============================
// RESPOSTA
// =============================
resposta_json_and_exit([
    "status" => "sucesso",
    "mensagem" => "Fornecedor cadastrado com sucesso",
    "id" => $novo_id
]);
?>