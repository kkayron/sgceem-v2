<?php
// includes/fin_empenhos/buscar_empenho_ver.php
include '../../conexao/config.php';

$id = intval($_GET['id'] ?? 0);
if (!$id) {
    echo json_encode(['sucesso' => false, 'mensagem' => 'ID inválido']);
    exit;
}

/* ====================== BUSCAR EMPENHO ====================== */
$stmt = $conexao->prepare("SELECT * FROM fin_empenhos WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$empenho = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$empenho) {
    echo json_encode(['sucesso' => false, 'mensagem' => 'Empenho não encontrado']);
    exit;
}

/* ====================== BUSCAR REQUISIÇÃO ====================== */
$stmt = $conexao->prepare("SELECT * FROM fin_requisicao WHERE id = ?");
$stmt->bind_param("i", $empenho['id_requisicao']);
$stmt->execute();
$requisicao = $stmt->get_result()->fetch_assoc();
$stmt->close();

/* ====================== BUSCAR FORNECEDOR ====================== */
$fornecedor = [];
if (!empty($requisicao['id_fornecedor'])) {
    $stmt = $conexao->prepare("SELECT * FROM fin_fornecedores WHERE id = ?");
    $stmt->bind_param("i", $requisicao['id_fornecedor']);
    $stmt->execute();
    $fornecedor = $stmt->get_result()->fetch_assoc() ?: [];
    $stmt->close();
}

/* ============================================================
   SALDO SIAFI = pode vir da tabela corrente ou resto a pagar
   ============================================================ */
$saldo_siafi = 0;

$stmt = $conexao->prepare("SELECT saldo_empenho FROM fin_siafi_corrente WHERE nmr_empenho = ?");
$stmt->bind_param("s", $empenho['nmr_empenho']);
$stmt->execute();
$r1 = $stmt->get_result()->fetch_assoc();
$saldo_siafi += floatval($r1['saldo_empenho'] ?? 0);
$stmt->close();

$stmt = $conexao->prepare("SELECT saldo_empenho FROM fin_siafi_restopagar WHERE nmr_empenho = ?");
$stmt->bind_param("s", $empenho['nmr_empenho']);
$stmt->execute();
$r2 = $stmt->get_result()->fetch_assoc();
$saldo_siafi += floatval($r2['saldo_empenho'] ?? 0);
$stmt->close();

/* ============================================================
   BUSCAR ORDENS → PEDIDOS → ITENS
   ============================================================ */

// Buscar ordens vinculadas ao empenho
$stmt = $conexao->prepare("SELECT id FROM fin_ordemforn WHERE id_empenho = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$ordensRows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$ordemIds = array_column($ordensRows, 'id');
$pedidoIds = [];
$pedidosPorOrdem = [];
$pedidos = [];
$ordens = [];

/* --- MAPEAR ORDEM → PEDIDOS --- */
if (!empty($ordemIds)) {
    $in = implode(",", array_fill(0, count($ordemIds), "?"));
    $types = str_repeat("i", count($ordemIds));

    $stmt = $conexao->prepare("SELECT id_ordemforn, id_pedido FROM fin_ordemforn_pedidos WHERE id_ordemforn IN ($in)");
    $stmt->bind_param($types, ...$ordemIds);
    $stmt->execute();
    $rel = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    foreach ($rel as $r) {
        $pedidosPorOrdem[$r['id_ordemforn']][] = $r['id_pedido'];
        $pedidoIds[] = $r['id_pedido'];
    }
    $pedidoIds = array_values(array_unique($pedidoIds));
}

/* --- BUSCAR PEDIDOS + SOMA DOS ITENS --- */
if (!empty($pedidoIds)) {
    $in = implode(",", array_fill(0, count($pedidoIds), "?"));
    $types = str_repeat("i", count($pedidoIds));

    $sql = "
        SELECT p.*, COALESCE(SUM(pi.valor_total), 0) AS valor_total
        FROM fin_pedidos_forn p
        LEFT JOIN fin_pedidos_forn_itens pi ON pi.id_principal = p.id
        WHERE p.id IN ($in)
        GROUP BY p.id
    ";

    $stmt = $conexao->prepare($sql);
    $stmt->bind_param($types, ...$pedidoIds);
    $stmt->execute();
    $pedidos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

$pedidoValorMap = [];
foreach ($pedidos as $p) {
    $pedidoValorMap[$p['id']] = floatval($p['valor_total'] ?? 0);
}

/* --- MONTAR ORDENS COM TOTAL --- */
if (!empty($ordemIds)) {
    $in = implode(",", array_fill(0, count($ordemIds), "?"));
    $types = str_repeat("i", count($ordemIds));

    $stmt = $conexao->prepare("SELECT * FROM fin_ordemforn WHERE id IN ($in)");
    $stmt->bind_param($types, ...$ordemIds);
    $stmt->execute();
    $ordensData = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    foreach ($ordensData as $of) {
        $ofId = $of['id'];
        $ofTotal = 0;

        if (!empty($pedidosPorOrdem[$ofId])) {
            foreach ($pedidosPorOrdem[$ofId] as $pid) {
                $ofTotal += $pedidoValorMap[$pid] ?? 0;
            }
        }

        $of['valor_total'] = $ofTotal;
        $ordens[] = $of;
    }
}

/* ============================================================
   CÁLCULOS FINAIS (CORRETOS)
   ============================================================ */
$total_empenhado = floatval($requisicao['valor_empenhado'] ?? 0);

$total_utilizado = 0;
foreach ($ordens as $o) {
    $total_utilizado += floatval($o['valor_total'] ?? 0);
}

$saldo_real = $total_empenhado - $total_utilizado;
$diferenca = $saldo_real - $saldo_siafi;

/* ============================================================
   SOMATÓRIOS DOS PEDIDOS
   ============================================================ */
$total_pedidos = 0;
foreach ($pedidos as $p) {
    $total_pedidos += floatval($p['valor_total'] ?? 0);
}

/* ============================================================
   FUNÇÃO DE FORMATAÇÃO
   ============================================================ */
function fmt($v) {
    return number_format(floatval($v), 2, ',', '.');
}

/* ============================================================
   RETORNO JSON
   ============================================================ */
$ret = [
    'sucesso' => true,
    'empenho' => $empenho,
    'requisicao' => $requisicao,
    'fornecedor' => $fornecedor,
    'pedidos' => $pedidos,
    'ordens' => $ordens,
    'saldos' => [
        'siafi' => fmt($saldo_siafi),
        'real' => fmt($saldo_real),
        'diferenca' => fmt($diferenca)
    ],
    'totais' => [
        'pedidos' => fmt($total_pedidos),
        'ofs' => fmt($total_utilizado),
        'empenhado' => fmt($total_empenhado),
        'utilizado' => fmt($total_utilizado),
        'saldo_siafi' => fmt($saldo_siafi),
        'saldo_real' => fmt($saldo_real),
        'diferenca' => fmt($diferenca)
    ]
];

header("Content-Type: application/json; charset=utf-8");
echo json_encode($ret);
exit;

?>
