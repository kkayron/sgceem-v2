<?php
header('Content-Type: application/json; charset=utf-8');

$pagina_id = 32;

require_once('../api/seguranca_json_editar.php');
if (session_status() === PHP_SESSION_NONE) session_start();
include_once('../../conexao/config.php');

try {
    if (empty($_GET['id'])) throw new Exception('ID do pedido não informado.');
    $idPedido = intval($_GET['id']);

    // ===============================
    // Buscar pedido principal
    // ===============================
    $stmtPedido = $conexao->prepare("
        SELECT id, batalhao, data_pedido, id_os, militar_solicitante,
               secao_solicitante, status_pedido, local_pedido, autorizacao
        FROM almox_pedidos_princ
        WHERE id = ?
        LIMIT 1
    ");
    $stmtPedido->bind_param('i', $idPedido);
    $stmtPedido->execute();
    $resPedido = $stmtPedido->get_result();

    if ($resPedido->num_rows === 0) throw new Exception('Pedido não encontrado.');
    $pedido = $resPedido->fetch_assoc();
    $stmtPedido->close();

    // ===============================
    // Buscar itens do pedido
    // ===============================
    $stmtItens = $conexao->prepare("
        SELECT i.id_produto, i.id_entrada, i.quant_solicitada,
               p.nome_produto, ei.valor_unt
        FROM almox_pedidos_itens i
        JOIN almox_produtos p ON i.id_produto = p.id
        JOIN almox_entradas_itens ei 
             ON ei.id_entrada = i.id_entrada AND ei.id_produto = i.id_produto
        WHERE i.id_pedido_principal = ?
    ");
    $stmtItens->bind_param('i', $idPedido);
    $stmtItens->execute();
    $resItens = $stmtItens->get_result();

    $itens = [];
    while ($row = $resItens->fetch_assoc()) {
        $itens[] = [
            'id_entrada' => intval($row['id_entrada']),
            'id_produto' => intval($row['id_produto']),
            'nome_produto' => $row['nome_produto'],
            'quant_solicitada' => floatval($row['quant_solicitada']),
            'valor_unt' => floatval($row['valor_unt'])
        ];
    }
    $stmtItens->close();

    // ===============================
    // Buscar produtos disponíveis com saldo atualizado + saldo do pedido
    // ===============================
    $sqlProdutos = "
    SELECT 
        ei.id_entrada,
        ei.id_produto,
        MAX(p.nome_produto) AS nome_produto,
        (MAX(ei.quant) - COALESCE(SUM(pi.quant_solicitada), 0)) AS saldo_atual,
        MAX(ei.valor_unt) AS valor_unt
    FROM almox_entradas_itens ei
    JOIN almox_produtos p 
        ON p.id = ei.id_produto
    LEFT JOIN almox_pedidos_itens pi 
        ON pi.id_entrada = ei.id_entrada 
       AND pi.id_produto = ei.id_produto
    GROUP BY 
        ei.id_entrada,
        ei.id_produto
";

    $resProd = $conexao->query($sqlProdutos);

    $produtos = [];
    while ($p = $resProd->fetch_assoc()) {
        $saldo = floatval($p['saldo_atual']);

        // Adiciona o saldo do próprio pedido (itens que já estão nesse pedido)
        foreach ($itens as $it) {
            if ($it['id_entrada'] == $p['id_entrada'] && $it['id_produto'] == $p['id_produto']) {
                $saldo += $it['quant_solicitada'];
            }
        }

        $produtos[] = [
            'id_entrada' => intval($p['id_entrada']),
            'id_produto' => intval($p['id_produto']),
            'nome_produto' => $p['nome_produto'],
            'saldo' => $saldo,
            'valor_unt' => floatval($p['valor_unt'])
        ];
    }

    // ===============================
    // Retorna JSON
    // ===============================
    echo json_encode([
        'sucesso' => true,
        'pedido' => $pedido,
        'itens' => $itens,
        'produtos' => $produtos
    ]);

} catch (Exception $e) {
    echo json_encode([
        'sucesso' => false,
        'mensagem' => $e->getMessage()
    ]);
}
