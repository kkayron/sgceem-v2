<?php
include_once("../../conexao/config.php");

$id = $_GET['id'] ?? 0;

if (!$id) {
    echo json_encode(['sucesso' => false, 'mensagem' => 'ID não informado']);
    exit;
}

// ======================================================
// BUSCA DADOS DA ENTRADA + INFORMAÇÕES DO BATALHÃO
// ======================================================
$sql = "
    SELECT 
    e.id,
    e.data_entrada,
    e.nota_empenho,
    e.nota_fiscal,
    e.nome_fornecedor,
    e.cnpj_fornecedor,
    e.batalhao,
    e.deposito_id,
    om.nome AS nome_batalhao,
    om.abreviatura AS abreviatura_batalhao
FROM almox_entradas e
LEFT JOIN organizacoes_militares om ON om.id = e.batalhao
WHERE e.id = ?
";
$stmt = $conexao->prepare($sql);
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['sucesso' => false, 'mensagem' => 'Entrada não encontrada']);
    exit;
}

$entrada = $result->fetch_assoc();
$stmt->close();

// ======================================================
// BUSCA OS PRODUTOS ADICIONADOS NESTA ENTRADA
// ======================================================
$sqlItens = "
    SELECT 
        ei.id AS id_item,
        ei.id_produto,
        ei.quant,
        ei.valor_unt,
        ei.valor_total,
        ei.marca,
        ei.modelo,
        p.nome_produto,
        p.categoria_produto
    FROM almox_entradas_itens ei
    JOIN almox_produtos p ON p.id = ei.id_produto
    WHERE ei.id_entrada = ?
";
$stmtItens = $conexao->prepare($sqlItens);
$stmtItens->bind_param("i", $id);
$stmtItens->execute();
$resultItens = $stmtItens->get_result();

$itens = [];
while ($row = $resultItens->fetch_assoc()) {
    $itens[] = $row;
}
$stmtItens->close();

// ======================================================
// RETORNO JSON
// ======================================================
echo json_encode([
    'sucesso' => true,
    'entrada' => $entrada,
    'itens' => $itens
]);
?>
