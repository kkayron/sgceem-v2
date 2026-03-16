<?php
include '../../conexao/config.php';
header('Content-Type: application/json');

$id = intval($_GET['id'] ?? 0);
$retorno = ['sucesso' => false];

if ($id <= 0) {
    echo json_encode($retorno);
    exit;
}

/*
    Busca dos itens do pregão + fornecedor + saldo calculado:

    saldo_disponivel = saldo_item (original) - SUM(quant_saida_item)
*/

$sql = "
    SELECT 
        pi.id,
        pi.descricao_item,
        pi.saldo_item,
        pi.valor_unt,
        f.nome_empresa AS fornecedor,
        pi.saldo_item - COALESCE(SUM(ri.quant_saida_item), 0) AS saldo_disponivel
    FROM fin_pregao_itens pi
    LEFT JOIN fin_fornecedores f 
        ON f.id = pi.id_fornecedor
    LEFT JOIN fin_requisicao_itens ri 
        ON ri.id_item = pi.id
    WHERE pi.id_pregao = ?
    GROUP BY 
        pi.id,
        pi.descricao_item,
        pi.saldo_item,
        pi.valor_unt,
        f.nome_empresa
    ORDER BY pi.descricao_item ASC
";

$stmt = $conexao->prepare($sql);
$stmt->bind_param("i", $id);
$stmt->execute();
$res = $stmt->get_result();

if ($res && $res->num_rows > 0) {
    $retorno['itens'] = [];

    while ($row = $res->fetch_assoc()) {
        $retorno['itens'][] = [
            'id'             => (int)    $row['id'],
            'descricao_item' => $row['descricao_item'],
            'saldo_item'     => (float)  $row['saldo_disponivel'],
            'valor_unt'      => (float)  $row['valor_unt'],
            'fornecedor'     => $row['fornecedor'] ?? 'N/D'
        ];
    }

    $retorno['sucesso'] = true;
}

echo json_encode($retorno, JSON_UNESCAPED_UNICODE);
?>
