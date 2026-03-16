<?php
include '../../conexao/config.php';

$id = intval($_GET['id']);
$retorno = ['sucesso' => false];

// ===== BUSCAR EMPENHO =====
$sql = "SELECT * FROM fin_empenhos WHERE id = ?";
$stmt = $conexao->prepare($sql);
$stmt->bind_param("i", $id);
$stmt->execute();
$empenho = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$empenho) {
  echo json_encode(['sucesso' => false]);
  exit;
}

// ===== BUSCAR REQUISIÇÃO =====
$sql = "SELECT * FROM fin_requisicao WHERE id = ?";
$stmt = $conexao->prepare($sql);
$stmt->bind_param("i", $empenho['id_requisicao']);
$stmt->execute();
$requisicao = $stmt->get_result()->fetch_assoc();
$stmt->close();

// ===== BUSCAR FORNECEDOR =====
$sql = "SELECT * FROM fin_fornecedores WHERE id = ?";
$stmt = $conexao->prepare($sql);
$stmt->bind_param("i", $requisicao['id_fornecedor']);
$stmt->execute();
$fornecedor = $stmt->get_result()->fetch_assoc() ?: [];
$stmt->close();


// ===== SALDOS SIAFI / REAL =====
$saldo_siafi = 0;
$saldo_real = 0;

$stmt = $conexao->prepare("SELECT saldo_empenho FROM fin_siafi_corrente WHERE nmr_empenho = ?");
$stmt->bind_param("s", $empenho['nmr_empenho']);
$stmt->execute();
$res = $stmt->get_result()->fetch_assoc();
$saldo_siafi = $res['saldo_empenho'] ?? 0;

$stmt = $conexao->prepare("SELECT saldo_empenho FROM fin_siafi_restopagar WHERE nmr_empenho = ?");
$stmt->bind_param("s", $empenho['nmr_empenho']);
$stmt->execute();
$res = $stmt->get_result()->fetch_assoc();
$saldo_real = $res['saldo_empenho'] ?? 0;

$diferenca = $saldo_real - $saldo_siafi;


// ===== PEDIDOS RELACIONADOS =====

// 1) Buscar as ordens de fornecimento vinculadas ao empenho
$sql = "SELECT id FROM fin_ordemforn WHERE id_empenho = ?";
$stmt = $conexao->prepare($sql);
$stmt->bind_param("i", $empenho['id']);
$stmt->execute();
$ordens = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$pedidos = [];

if (!empty($ordens)) {

    $ordemIds = array_column($ordens, 'id');
    $placeholders = implode(",", array_fill(0, count($ordemIds), "?"));

    // 3) Buscar pedidos associados às ordens
    $sql = "SELECT id_pedido FROM fin_ordemforn_pedidos WHERE id_ordemforn IN ($placeholders)";
    $stmt = $conexao->prepare($sql);
    $stmt->bind_param(str_repeat("i", count($ordemIds)), ...$ordemIds);
    $stmt->execute();
    $pedidoIds = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    if (!empty($pedidoIds)) {
        $pedidoIdList = array_column($pedidoIds, 'id_pedido');
        $placeholders = implode(",", array_fill(0, count($pedidoIdList), "?"));

        $sql = "SELECT * FROM fin_pedidos_forn WHERE id IN ($placeholders)";
        $stmt = $conexao->prepare($sql);
        $stmt->bind_param(str_repeat("i", count($pedidoIdList)), ...$pedidoIdList);
        $stmt->execute();
        $pedidos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    }
}


// ===== ORDENS DE FORNECIMENTO =====
$sql = "SELECT * FROM fin_ordemforn WHERE id_empenho = ?";
$stmt = $conexao->prepare($sql);
$stmt->bind_param("i", $id);
$stmt->execute();
$ordens = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();


// ==================================================================
// ============= CÁLCULOS DAS SOMAS PARA O MODAL ====================
// ==================================================================

$total_pedidos = 0;
foreach ($pedidos as $p) {
    $total_pedidos += floatval($p['valor_total'] ?? 0);
}

// Somatórios do empenho (ajuste os nomes se forem diferentes)
$total_empenhado            = floatval($empenho['vlr_empenho'] ?? 0);
$solicitado_nao_entregue    = floatval($empenho['vlr_solicitado_nao_entregue'] ?? 0);
$entregue_nao_liquidado     = floatval($empenho['vlr_entregue_nao_liquidado'] ?? 0);
$valor_capeador             = floatval($empenho['vlr_capeador'] ?? 0);
$valor_liquidado            = floatval($empenho['vlr_liquidado'] ?? 0);
$total_utilizado            = floatval($empenho['vlr_total_utilizado'] ?? 0);

// ==================================================================


$retorno = [
  'sucesso'     => true,
  'empenho'     => $empenho,
  'requisicao'  => $requisicao,
  'fornecedor'  => $fornecedor,

  'saldos'      => [
    'siafi'     => number_format($saldo_siafi, 2, ',', '.'),
    'real'      => number_format($saldo_real, 2, ',', '.'),
    'diferenca' => number_format($diferenca, 2, ',', '.')
  ],

  'pedidos'     => $pedidos,
  'ordens'      => $ordens,

  // ========= RETORNO DAS SOMAS PARA O MODAL =========
  'totais' => [
      'pedidos' => number_format($total_pedidos, 2, ',', '.'),
      'empenhado' => number_format($total_empenhado, 2, ',', '.'),
      'solicitado_nao_entregue' => number_format($solicitado_nao_entregue, 2, ',', '.'),
      'entregue_nao_liquidado' => number_format($entregue_nao_liquidado, 2, ',', '.'),
      'valor_capeador' => number_format($valor_capeador, 2, ',', '.'),
      'valor_liquidado' => number_format($valor_liquidado, 2, ',', '.'),
      'total_utilizado' => number_format($total_utilizado, 2, ',', '.'),
      'saldo_siafi' => number_format($saldo_siafi, 2, ',', '.'),
      'saldo_real' => number_format($saldo_real, 2, ',', '.'),
      'diferenca' => number_format($diferenca, 2, ',', '.')
  ]
];

echo json_encode($retorno);
