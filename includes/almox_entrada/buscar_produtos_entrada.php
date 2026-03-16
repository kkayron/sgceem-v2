<?php
include_once("../../conexao/config.php");

$id = $_GET['id'] ?? 0;

if (!$id) {
    echo json_encode(['sucesso' => false, 'mensagem' => 'ID não informado']);
    exit;
}

// Busca os produtos adicionados nesta entrada
$sql = "SELECT ei.id AS id_item, p.id AS id_produto, p.nome_produto, p.codigo_produto, p.categoria_produto,
               ei.quant, ei.valor_unt, ei.valor_total, ei.marca, ei.modelo
        FROM almox_entradas_itens ei
        JOIN almox_produtos p ON p.id = ei.id_produto
        WHERE ei.id_entrada = ?";
$stmt = $conexao->prepare($sql);
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();

$produtos = [];
while ($row = $result->fetch_assoc()) {
    $produtos[] = $row;
}

echo json_encode(['sucesso' => true, 'produtos' => $produtos]);
