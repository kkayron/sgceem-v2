<?php
header('Content-Type: application/json; charset=utf-8');
$pagina_id = 46;

require_once('../api/seguranca_json_editar.php');
require_once '../../conexao/config.php';

$id = intval($_GET['id'] ?? 0);
if ($id <= 0) {
    echo json_encode(['sucesso' => false, 'mensagem' => 'ID inválido']);
    exit;
}

// 1) Buscar dados da ordem
$stmtOrdem = $conexao->prepare("
    SELECT * FROM fin_ordemforn WHERE id = ?
");
$stmtOrdem->bind_param("i", $id);
$stmtOrdem->execute();
$resOrdem = $stmtOrdem->get_result();

if (!$resOrdem || $resOrdem->num_rows === 0) {
    echo json_encode(['sucesso' => false, 'mensagem' => 'Ordem não encontrada']);
    exit;
}

$ordem = $resOrdem->fetch_assoc();
$stmtOrdem->close();

// 2) Buscar os pedidos vinculados
$stmtPedidos = $conexao->prepare("
    SELECT p.id
    FROM fin_ordemforn_pedidos op
    JOIN fin_pedidos_forn p ON p.id = op.id_pedido
    WHERE op.id_ordemforn = ?
");
$stmtPedidos->bind_param("i", $id);
$stmtPedidos->execute();
$resPedidos = $stmtPedidos->get_result();

$pedidos = [];
while ($row = $resPedidos->fetch_assoc()) {
    $pedidos[] = [
        'id' => $row['id']
    ];
}
$stmtPedidos->close();

// 3) Retorno final
echo json_encode([
    'sucesso' => true,
    'ordem'   => $ordem,
    'pedidos' => $pedidos
], JSON_UNESCAPED_UNICODE);
