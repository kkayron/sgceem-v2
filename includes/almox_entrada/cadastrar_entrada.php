<?php
session_start();
require_once '../api/seguranca_cadastrar.php';

$permissoes = verificarPermissao(31);

$pode_cadastrar = $permissoes['cadastrar'];
$pode_editar    = $permissoes['editar'];
$pode_deletar   = $permissoes['deletar'];
$pode_importar  = $permissoes['importar'];

include '../../conexao/config.php';
include '../funcoes/log.php';

$usuarioLogado = $_SESSION['usuario_id'] ?? 0;

// ===============================
// Campos principais da entrada
// ===============================
$nota_empenho     = $_POST['nota_empenho']     ?? '';
$nota_fiscal      = $_POST['nota_fiscal']      ?? '';
$nome_fornecedor  = $_POST['nome_fornecedor']  ?? '';
$cnpj_fornecedor  = $_POST['cnpj_fornecedor']  ?? '';
$data_entrada     = $_POST['data_entrada']     ?? '';
$batalhao         = intval($_POST['batalhao']  ?? 0);
$deposito_id      = intval($_POST['deposito_id'] ?? 0);

// ===============================
// Validação básica
// ===============================
if (!$batalhao || !$deposito_id) {
    http_response_code(400);
    echo "Batalhão ou depósito não informado.";
    exit;
}

// ======================================================
// 1) Validar se o depósito pertence ao batalhão
// ======================================================
$stmtDep = $conexao->prepare("
    SELECT id 
    FROM almox_depositos 
    WHERE id = ? AND batalhao = ?
    LIMIT 1
");
$stmtDep->bind_param("ii", $deposito_id, $batalhao);
$stmtDep->execute();
$resDep = $stmtDep->get_result();

if ($resDep->num_rows === 0) {
    http_response_code(403);
    echo "Erro: O depósito selecionado não pertence ao batalhão informado.";
    exit;
}
$stmtDep->close();

// ======================================================
// 2) Validação: produtos do mesmo batalhão
// ======================================================
$itens = $_POST['itens'] ?? [];

if (empty($itens)) {
    http_response_code(400);
    echo "Nenhum item informado.";
    exit;
}

$idsProdutos = array_filter(array_map('intval', array_column($itens, 'id_produto')));

if (empty($idsProdutos)) {
    http_response_code(400);
    echo "Nenhum produto válido informado.";
    exit;
}

$idsStr = implode(',', $idsProdutos);

$sqlCheck = "SELECT DISTINCT batalhao FROM almox_produtos WHERE id IN ($idsStr)";
$resCheck = $conexao->query($sqlCheck);

$batalhoesProdutos = [];
while ($row = $resCheck->fetch_assoc()) {
    $batalhoesProdutos[] = (int)$row['batalhao'];
}

if (count($batalhoesProdutos) > 1 || !in_array($batalhao, $batalhoesProdutos)) {
    http_response_code(403);
    echo "Erro: Existem produtos de batalhões diferentes ou fora do batalhão selecionado.";
    exit;
}

// ======================================================
// 3) Inserir entrada principal (COM DEPÓSITO)
// ======================================================
$stmt = $conexao->prepare("
    INSERT INTO almox_entradas (
        batalhao, deposito_id, data_entrada, nota_empenho, nota_fiscal, nome_fornecedor, cnpj_fornecedor
    ) VALUES (?, ?, ?, ?, ?, ?, ?)
");

$stmt->bind_param(
    "iisssss",
    $batalhao,
    $deposito_id,
    $data_entrada,
    $nota_empenho,
    $nota_fiscal,
    $nome_fornecedor,
    $cnpj_fornecedor
);

$stmt->execute();
$id_entrada = $conexao->insert_id;
$stmt->close();

$itens_log = [];

// ======================================================
// 4) Inserir itens da entrada
// ======================================================
foreach ($itens as $item) {
    $id_produto   = intval($item['id_produto'] ?? 0);
    $quant        = floatval($item['quant'] ?? 0);
    $valor_unt    = floatval($item['valor_unt'] ?? 0);
    $valor_total  = floatval($item['valor_total'] ?? 0);
    $marca        = trim($item['marca'] ?? '');
    $modelo       = trim($item['modelo'] ?? '');

    if (!$id_produto || $quant <= 0) continue;

    $stmtItem = $conexao->prepare("
        INSERT INTO almox_entradas_itens (
            id_entrada, id_produto, quant, valor_unt, valor_total, marca, modelo
        ) VALUES (?, ?, ?, ?, ?, ?, ?)
    ");

    $stmtItem->bind_param(
        "iidddss",
        $id_entrada,
        $id_produto,
        $quant,
        $valor_unt,
        $valor_total,
        $marca,
        $modelo
    );

    $stmtItem->execute();
    $stmtItem->close();

    $itens_log[] = "Produto ID $id_produto, Quantidade $quant, Valor Unitário $valor_unt";
}

// ======================================================
// 5) Registrar log
// ======================================================
$descricao = "Cadastro da Entrada ID $id_entrada | Depósito ID $deposito_id";
if (!empty($itens_log)) {
    $descricao .= ". Itens: " . implode("; ", $itens_log);
}

registrar_log(
    $conexao,
    $usuarioLogado,
    'Cadastrar Entrada Almoxarifado',
    $descricao,
    $id_entrada
);

echo 'ok';
?>
