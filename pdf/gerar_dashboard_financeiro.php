<?php
/*******************************************************************************
 * GERAR DASHBOARD FINANCEIRO – PDF (Relatório sofisticado)
 * - Resumo (KPIs + barras) + Listagem completa de empenhos + “Mapa” por diferença
 * - MAPA (dif = saldoSiafi - saldoReal):
 *     > 0  => Nota não liquidada
 *     < 0  => Nota faltando no controle
 *     = 0  => Sem alteração
 *
 * REQUER:
 *   composer require dompdf/dompdf
 *   arquivo vendor/autoload.php acessível
 *******************************************************************************/

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_start();

if (!isset($_SESSION['usuario_id'])) {
  die("Sessão expirada. Faça login novamente.");
}

require_once '../conexao/config.php';
require_once __DIR__ . '/../pdf/vendor/autoload.php'; // ajuste se seu vendor estiver em outro caminho

use Dompdf\Dompdf;
use Dompdf\Options;

date_default_timezone_set('America/Sao_Paulo');

// tenta liberar joins grandes (nem todo host permite; não atrapalha)
@mysqli_query($conexao, "SET SESSION SQL_BIG_SELECTS=1");

/* ===========================
   SESSÃO / PERMISSÃO OM
=========================== */
$id_om_usuario = (int)($_SESSION['usuario']['batalhao'] ?? 0);
$nivel_usuario = (int)($_SESSION['usuario']['nivel'] ?? 3);

// OMs visíveis
$oms_visiveis = [];
if ($nivel_usuario === 1) {
  $sql_oms = "SELECT id, nome FROM organizacoes_militares ORDER BY nome";
} elseif ($nivel_usuario === 2) {
  $sql_oms = "
    SELECT om.id, om.nome
    FROM organizacoes_militares om
    JOIN organizacoes_militares_sub sub ON om.id = sub.id_om_menor
    WHERE sub.id_om_maior = $id_om_usuario
    UNION
    SELECT id, nome FROM organizacoes_militares WHERE id = $id_om_usuario
    ORDER BY nome
  ";
} else {
  $sql_oms = "SELECT id, nome FROM organizacoes_militares WHERE id = $id_om_usuario";
}
$res = $conexao->query($sql_oms);
while ($r = $res->fetch_assoc()) $oms_visiveis[(int)$r['id']] = $r['nome'];

/* ===========================
   FILTROS
=========================== */
$filtro_om        = $_GET['batalhao'] ?? 'todos';
$filtro_ano       = $_GET['ano'] ?? '';
$filtro_categoria = $_GET['categoria'] ?? '';
$filtro_resto     = $_GET['resto'] ?? '';

$filtros = [];
$params  = [];
$tipos   = '';

if ($filtro_om !== 'todos') {
  $filtros[] = "r.batalhao = ?";
  $params[]  = (int)$filtro_om;
  $tipos    .= 'i';
} else {
  $ids_array = array_keys($oms_visiveis ?? []);
  if (!empty($ids_array)) {
    $filtros[] = "r.batalhao IN (" . implode(',', array_map('intval', $ids_array)) . ")";
  } else {
    $filtros[] = "1=0";
  }
}

if ($filtro_ano !== '') {
  $filtros[] = "e.ano = ?";
  $params[]  = (int)$filtro_ano;
  $tipos    .= 'i';
}

if ($filtro_categoria !== '') {
  $filtros[] = "e.categoria = ?";
  $params[]  = (string)$filtro_categoria;
  $tipos    .= 's';
}

if ($filtro_resto === 'Sim') {
  $filtros[] = "e.resto_pagar = 'Sim'";
} elseif ($filtro_resto === 'Não') {
  $filtros[] = "e.resto_pagar = 'Não'";
}

$where_final = $filtros ? ('WHERE ' . implode(' AND ', $filtros)) : '';

/* ===========================
   HELPERS
=========================== */
function fmtMoney($v) { return 'R$ ' . number_format((float)($v ?? 0), 2, ',', '.'); }
function fmtPct($v) { return number_format((float)$v, 2, ',', '.') . '%'; }

function omNomeById($id, $oms_visiveis) {
  $id = (int)$id;
  return $oms_visiveis[$id] ?? ("OM " . $id);
}

function mapaStatus($dif) {
  if ($dif > 0) return "Nota não liquidada";
  if ($dif < 0) return "Nota faltando no controle";
  return "Sem alteração";
}

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

/* Buckets */
$STATUS_LABELS = [
  'não entregue' => 'Não entregue',
  'nao entregue' => 'Não entregue',
  'entregue'     => 'Entregue',
  'capeador'     => 'Capeador',
  'liquidado'    => 'Liquidado',
  'pago'         => 'Liquidado',
];

$BUCKETS = ['Não entregue'=>0,'Entregue'=>0,'Capeador'=>0,'Liquidado'=>0];

/* ===========================
   1) EMPENHOS + valor empenhado
=========================== */
$sqlEmp = "
  SELECT
    e.id,
    e.id_requisicao,
    e.nmr_empenho,
    e.ano,
    e.categoria,
    e.resto_pagar,
    r.batalhao,
    COALESCE(r.valor_empenhado, 0) AS valor_empenhado
  FROM fin_empenhos e
  JOIN fin_requisicao r ON r.id = e.id_requisicao
  $where_final
  ORDER BY e.ano DESC, e.nmr_empenho ASC
";
$stmtEmp = $conexao->prepare($sqlEmp);
if ($stmtEmp === false) die("Erro prepare empenhos: " . $conexao->error);
if ($params) $stmtEmp->bind_param($tipos, ...$params);
$stmtEmp->execute();
$resEmp = $stmtEmp->get_result();

$empenhos = [];
$idsEmpenhos = [];
while ($row = $resEmp->fetch_assoc()) {
  $row['id'] = (int)$row['id'];
  $row['batalhao'] = (int)$row['batalhao'];
  $row['valor_empenhado'] = (float)$row['valor_empenhado'];
  $empenhos[] = $row;
  $idsEmpenhos[] = (int)$row['id'];
}
$stmtEmp->close();

/* ===========================
   2) STATUS (ANTI MAX_JOIN_SIZE): link + soma itens por pedido
=========================== */
$mapaStatusPorEmpenho = [];
foreach ($idsEmpenhos as $idEmp) $mapaStatusPorEmpenho[$idEmp] = $BUCKETS;

if (!empty($idsEmpenhos)) {

  $chunkSizeEmp = 200;
  $chunks = array_chunk($idsEmpenhos, $chunkSizeEmp);

  foreach ($chunks as $chunkEmp) {

    $listaEmp = implode(',', array_map('intval', $chunkEmp));

    $sqlLink = "
      SELECT
        o.id_empenho,
        LOWER(o.status) AS status,
        op.id_pedido
      FROM fin_ordemforn o
      JOIN fin_ordemforn_pedidos op ON op.id_ordemforn = o.id
      WHERE o.id_empenho IN ($listaEmp)
    ";
    $resLink = $conexao->query($sqlLink);
    if (!$resLink) break;

    $pedidoMeta = []; // [id_pedido] => [[id_empenho, bucket], ...]
    $pedidoIds = [];

    while ($lk = $resLink->fetch_assoc()) {
      $idEmp = (int)$lk['id_empenho'];
      $stRaw = (string)$lk['status'];
      $idPed = (int)$lk['id_pedido'];
      if ($idPed <= 0) continue;

      $label = $STATUS_LABELS[$stRaw] ?? null;
      if (!$label) {
        if (strpos($stRaw, 'nao') !== false || strpos($stRaw, 'não') !== false) $label = 'Não entregue';
        elseif (strpos($stRaw, 'entreg') !== false) $label = 'Entregue';
        elseif (strpos($stRaw, 'cape') !== false) $label = 'Capeador';
        elseif (strpos($stRaw, 'liquid') !== false || strpos($stRaw, 'pago') !== false) $label = 'Liquidado';
        else $label = null;
      }
      if (!$label) continue;

      if (!isset($pedidoMeta[$idPed])) {
        $pedidoMeta[$idPed] = [];
        $pedidoIds[] = $idPed;
      }
      $pedidoMeta[$idPed][] = [$idEmp, $label];
    }

    if (empty($pedidoIds)) continue;

    $pedidoChunks = array_chunk($pedidoIds, 400);
    foreach ($pedidoChunks as $pch) {
      $listaPed = implode(',', array_map('intval', $pch));

      $sqlItens = "
        SELECT id_principal, COALESCE(SUM(valor_total),0) AS total
        FROM fin_pedidos_forn_itens
        WHERE id_principal IN ($listaPed)
        GROUP BY id_principal
      ";
      $resItens = $conexao->query($sqlItens);
      if (!$resItens) continue;

      while ($it = $resItens->fetch_assoc()) {
        $idPed = (int)$it['id_principal'];
        $total = (float)$it['total'];
        if ($total <= 0) continue;
        if (!isset($pedidoMeta[$idPed])) continue;

        foreach ($pedidoMeta[$idPed] as $pair) {
          [$idEmp, $label] = $pair;
          if (!isset($mapaStatusPorEmpenho[$idEmp])) $mapaStatusPorEmpenho[$idEmp] = $BUCKETS;
          $mapaStatusPorEmpenho[$idEmp][$label] += $total;
        }
      }
    }
  }
}

/* ===========================
   3) SALDO SIAFI (BULK)
=========================== */
$mapaSiafi = [];
if (!empty($empenhos)) {

  $nums = [];
  foreach ($empenhos as $e) {
    $n = trim((string)$e['nmr_empenho']);
    if ($n !== '') $nums[$n] = true;
  }
  $nums = array_keys($nums);

  if (!empty($nums)) {
    $numChunks = array_chunk($nums, 400);

    foreach ($numChunks as $nch) {
      $placeholders = implode(',', array_fill(0, count($nch), '?'));
      $typesNums = str_repeat('s', count($nch));

      $sqlSiafi = "
        SELECT
          nmr_empenho,
          MAX(
            CAST(REPLACE(REPLACE(saldo_empenho, '.', ''), ',', '.') AS DECIMAL(18,2))
          ) AS saldo_num
        FROM (
          SELECT nmr_empenho, saldo_empenho FROM fin_siafi_corrente WHERE nmr_empenho IN ($placeholders)
          UNION ALL
          SELECT nmr_empenho, saldo_empenho FROM fin_siafi_restopagar WHERE nmr_empenho IN ($placeholders)
        ) x
        GROUP BY nmr_empenho
      ";
      $stmtS = $conexao->prepare($sqlSiafi);
      if ($stmtS) {
        $stmtS->bind_param($typesNums . $typesNums, ...array_merge($nch, $nch));
        $stmtS->execute();
        $rS = $stmtS->get_result();
        while ($s = $rS->fetch_assoc()) {
          $mapaSiafi[(string)$s['nmr_empenho']] = (float)$s['saldo_num'];
        }
        $stmtS->close();
      }
    }
  }
}

/* ===========================
   4) LINHAS + RESUMO
=========================== */
$totalRegistros = count($empenhos);

$soma_total_empenhado = 0;
$soma_total_utilizado = 0;

$soma_nao_entregue = 0;
$soma_entregue     = 0;
$soma_capeador     = 0;
$soma_liquidado    = 0;

$soma_saldo_real   = 0;
$soma_saldo_siafi  = 0;
$soma_diferenca    = 0;

$linhas = [];
$mapaCount = ['Nota não liquidada'=>0,'Nota faltando no controle'=>0,'Sem alteração'=>0,'Sem SIAFI'=>0];

foreach ($empenhos as $e) {
  $idEmp = (int)$e['id'];
  $nmr = trim((string)$e['nmr_empenho']);

  $valorEmpenhado = (float)$e['valor_empenhado'];
  $b = $mapaStatusPorEmpenho[$idEmp] ?? $BUCKETS;

  $naoEnt = (float)$b['Não entregue'];
  $entr   = (float)$b['Entregue'];
  $cap    = (float)$b['Capeador'];
  $liq    = (float)$b['Liquidado'];

  $emAberto = $naoEnt + $entr + $cap;
  $utilizado = $emAberto + $liq;

  $saldoReal = $valorEmpenhado - $utilizado;

  $saldoSiafi = null;
  if ($nmr !== '' && isset($mapaSiafi[$nmr])) {
    $saldoSiafi = (float)$mapaSiafi[$nmr];
  }

  $dif = null;
  $mapaTxt = '';
  if ($saldoSiafi !== null) {
    $dif = $saldoSiafi - $saldoReal;
    $mapaTxt = mapaStatus($dif);
  } else {
    $mapaTxt = "Sem SIAFI";
  }

  // contadores mapa
  if (!isset($mapaCount[$mapaTxt])) $mapaCount[$mapaTxt] = 0;
  $mapaCount[$mapaTxt]++;

  // somatórios
  $soma_total_empenhado += $valorEmpenhado;
  $soma_nao_entregue += $naoEnt;
  $soma_entregue     += $entr;
  $soma_capeador     += $cap;
  $soma_liquidado    += $liq;

  $soma_total_utilizado += $utilizado;
  $soma_saldo_real += $saldoReal;

  if ($saldoSiafi !== null) {
    $soma_saldo_siafi += $saldoSiafi;
    $soma_diferenca += $dif;
  }

  $linhas[] = [
    'om' => omNomeById((int)$e['batalhao'], $oms_visiveis),
    'nmr' => $nmr,
    'ano' => (string)($e['ano'] ?? ''),
    'categoria' => (string)($e['categoria'] ?? ''),
    'resto' => (string)($e['resto_pagar'] ?? ''),
    'empenhado' => $valorEmpenhado,
    'liquidado' => $liq,
    'aberto' => $emAberto,
    'saldo_real' => $saldoReal,
    'saldo_siafi' => $saldoSiafi,
    'dif' => $dif,
    'mapa' => $mapaTxt,
  ];
}

if ($soma_total_empenhado > 0) {
  $porc_liquidado = ($soma_liquidado / $soma_total_empenhado) * 100;
  $porc_req       = ($soma_nao_entregue / $soma_total_empenhado) * 100;
  $porc_entregue  = ($soma_entregue / $soma_total_empenhado) * 100;
  $porc_capeador  = ($soma_capeador / $soma_total_empenhado) * 100;
} else {
  $porc_liquidado = $porc_req = $porc_entregue = $porc_capeador = 0;
}

/* ===========================
   Cabeçalho do relatório (labels)
=========================== */
$omLabel = ($filtro_om === 'todos') ? 'Todos' : omNomeById((int)$filtro_om, $oms_visiveis);
$anoLabel = ($filtro_ano !== '') ? $filtro_ano : 'Todos';
$catLabel = ($filtro_categoria !== '') ? $filtro_categoria : 'Todas';
$restoLabel = ($filtro_resto !== '') ? $filtro_resto : 'Todos';
$geradoEm = date('d/m/Y H:i');

/* ===========================
   HTML DO PDF
=========================== */
$barTotal = max(1, (float)$soma_total_empenhado);
$wLiq = max(0, min(100, ($soma_liquidado / $barTotal) * 100));
$wCap = max(0, min(100, ($soma_capeador / $barTotal) * 100));
$wReqEnt = max(0, min(100, (($soma_nao_entregue + $soma_entregue) / $barTotal) * 100));

$css = <<<CSS
<style>
  @page { margin: 110px 36px 70px 36px; }
  body { font-family: DejaVu Sans, Arial, sans-serif; color:#111827; font-size: 10.5px; }

  /* Header / Footer via fixed */
  .header { position: fixed; top: -90px; left: 0; right: 0; height: 90px; }
  .footer { position: fixed; bottom: -50px; left: 0; right: 0; height: 50px; color:#6B7280; }

  .brand {
    background: linear-gradient(135deg,#0ea5e9,#22c55e);
    color:#fff; border-radius: 10px;
    padding: 14px 16px;
  }
  .brand .title { font-size: 16px; font-weight: 800; letter-spacing: .3px; }
  .brand .sub { font-size: 10px; opacity: .95; margin-top: 3px; }

  .meta { margin-top: 8px; background:#F3F4F6; border-radius: 10px; padding: 10px 12px; }
  .meta .row { display: table; width: 100%; }
  .meta .cell { display: table-cell; width: 33.33%; vertical-align: top; font-size: 10px; color:#374151; }
  .meta b { color:#111827; }

  .section-title {
    margin: 18px 0 8px;
    font-size: 12px; font-weight: 800; color:#111827;
  }
  .hint { color:#6B7280; font-size: 9.5px; margin-top: 2px; }

  .kpis { width: 100%; border-collapse: separate; border-spacing: 10px; margin-top: 6px; }
  .kpi {
    background:#FFFFFF; border: 1px solid #E5E7EB; border-radius: 12px;
    padding: 10px 12px;
  }
  .kpi .label { color:#6B7280; font-size: 9px; font-weight: 700; text-transform: uppercase; letter-spacing: .4px; }
  .kpi .value { font-size: 14px; font-weight: 900; margin-top: 2px; }
  .kpi .sub { color:#6B7280; font-size: 9px; margin-top: 3px; }
  .v-success { color:#16a34a; }
  .v-primary { color:#2563eb; }
  .v-warning { color:#d97706; }
  .v-danger  { color:#dc2626; }
  .v-muted   { color:#4b5563; }

  .bar {
    width:100%; background:#E5E7EB; border-radius: 999px; overflow: hidden; height: 16px;
    border: 1px solid #E5E7EB;
  }
  .seg { height: 16px; float:left; color:#111827; font-size: 8.5px; line-height: 16px; text-align:center; white-space:nowrap; overflow:hidden; }
  .seg.liq { background:#86efac; }
  .seg.cap { background:#fde68a; }
  .seg.req { background:#bfdbfe; }

  table.data { width: 100%; border-collapse: collapse; margin-top: 8px; }
  table.data th, table.data td { border: 1px solid #E5E7EB; padding: 6px 6px; }
  table.data th { background:#111827; color:#fff; font-size: 9.5px; text-transform: uppercase; letter-spacing: .35px; }
  table.data td { font-size: 9.8px; }
  .right { text-align:right; }
  .center { text-align:center; }
  .nowrap { white-space: nowrap; }

  .tag { display:inline-block; padding: 2px 6px; border-radius: 999px; font-size: 9px; font-weight: 800; }
  .t-ok { background:#DCFCE7; color:#166534; border: 1px solid #86EFAC; }
  .t-warn { background:#FEF9C3; color:#854D0E; border: 1px solid #FDE68A; }
  .t-bad { background:#FEE2E2; color:#991B1B; border: 1px solid #FCA5A5; }
  .t-miss { background:#E5E7EB; color:#374151; border: 1px solid #D1D5DB; }

  .summary-table { width:100%; border-collapse: collapse; margin-top: 6px; }
  .summary-table td { border: 1px solid #E5E7EB; padding: 7px 8px; }
  .summary-table tr:nth-child(odd) td { background:#F9FAFB; }
  .summary-table .lab { font-weight: 800; color:#111827; }
  .summary-table .val { text-align:right; font-weight: 800; }

  .footline { border-top: 1px solid #E5E7EB; padding-top: 8px; font-size: 9px; }
  .pagenum:before { content: counter(page); }
  .pagecount:before { content: counter(pages); }

  .break { page-break-before: always; }
</style>
CSS;

$header = <<<HTML
<div class="header">
  <div class="brand">
    <div class="title">RELATÓRIO FINANCEIRO • EMPENHOS</div>
    <div class="sub">Controle interno x SIAFI • Mapa de divergências • Auditoria de saldos</div>
  </div>
  <div class="meta">
    <div class="row">
      <div class="cell"><b>OM:</b> {$omLabel}<br><b>Ano:</b> {$anoLabel}</div>
      <div class="cell"><b>Categoria:</b> {$catLabel}<br><b>Restos:</b> {$restoLabel}</div>
      <div class="cell"><b>Gerado em:</b> {$geradoEm}<br><b>Empenhos:</b> {$totalRegistros}</div>
    </div>
  </div>
</div>
HTML;

$footer = <<<HTML
<div class="footer">
  <div class="footline">
    <span>Documento gerado automaticamente • Controle Financeiro</span>
    <span style="float:right;">Página <span class="pagenum"></span>/<span class="pagecount"></span></span>
  </div>
</div>
HTML;

/* KPIs */
$kpiTotal = fmtMoney($soma_total_empenhado);
$kpiLiq   = fmtMoney($soma_liquidado);
$kpiAberto= fmtMoney($soma_nao_entregue + $soma_entregue + $soma_capeador);
$kpiSaldoR= fmtMoney($soma_saldo_real);
$kpiSaldoS= fmtMoney($soma_saldo_siafi);
$kpiDif   = fmtMoney($soma_diferenca);

$mapBad = (int)($mapaCount['Nota não liquidada'] ?? 0);
$mapWarn = (int)($mapaCount['Nota faltando no controle'] ?? 0);
$mapOk = (int)($mapaCount['Sem alteração'] ?? 0);
$mapNo = (int)($mapaCount['Sem SIAFI'] ?? 0);

$porc_req_ent = $porc_req + $porc_entregue;

$thisNao = fmtMoney($soma_nao_entregue);
$thisEnt = fmtMoney($soma_entregue);
$thisCap = fmtMoney($soma_capeador);
$thisUtil = fmtMoney($soma_total_utilizado);

$kpis = <<<HTML
<div class="section-title">Visão Geral</div>
<div class="hint">Em aberto = Não entregue + Entregue + Capeador. Utilizado = Em aberto + Liquidado. Diferença = (Saldo SIAFI − Saldo Controle).</div>

<table class="kpis">
  <tr>
    <td class="kpi">
      <div class="label">Total Empenhado</div>
      <div class="value v-success">{$kpiTotal}</div>
      <div class="sub">Base para percentuais</div>
    </td>
    <td class="kpi">
      <div class="label">Total Liquidado</div>
      <div class="value v-primary">{$kpiLiq}</div>
      <div class="sub">{$porc_liquidado}% do total</div>
    </td>
    <td class="kpi">
      <div class="label">Em Aberto</div>
      <div class="value v-warning">{$kpiAberto}</div>
      <div class="sub">Req: {$porc_req}% • Ent: {$porc_entregue}% • Cap: {$porc_capeador}%</div>
    </td>
  </tr>
  <tr>
    <td class="kpi">
      <div class="label">Saldo Real (Controle)</div>
      <div class="value v-muted">{$kpiSaldoR}</div>
      <div class="sub">Empenhado − Utilizado</div>
    </td>
    <td class="kpi">
      <div class="label">Saldo SIAFI</div>
      <div class="value v-muted">{$kpiSaldoS}</div>
      <div class="sub">Corrente + Restos</div>
    </td>
    <td class="kpi">
      <div class="label">Diferença (SIAFI − Controle)</div>
      <div class="value v-danger">{$kpiDif}</div>
      <div class="sub">Mapa: OK {$mapOk} • Não liquidada {$mapBad} • Faltando {$mapWarn} • Sem SIAFI {$mapNo}</div>
    </td>
  </tr>
</table>

<div class="section-title">Distribuição (sobre o total empenhado)</div>
<div class="bar">
  <div class="seg liq" style="width: {$wLiq}%;">Liquidado {$porc_liquidado}%</div>
  <div class="seg cap" style="width: {$wCap}%;">Capeador {$porc_capeador}%</div>
  <div class="seg req" style="width: {$wReqEnt}%;">Req/Ent {$porc_req_ent}%</div>
</div>

<div class="section-title">Resumo detalhado</div>
<table class="summary-table">
  <tr><td class="lab">Total Empenhado</td><td class="val">{$kpiTotal}</td></tr>
  <tr><td class="lab">Não entregue</td><td class="val">{$thisNao}</td></tr>
  <tr><td class="lab">Entregue</td><td class="val">{$thisEnt}</td></tr>
  <tr><td class="lab">Capeador</td><td class="val">{$thisCap}</td></tr>
  <tr><td class="lab">Liquidado</td><td class="val">{$kpiLiq}</td></tr>
  <tr><td class="lab">Total Utilizado</td><td class="val">{$thisUtil}</td></tr>
  <tr><td class="lab">Saldo Real (Controle)</td><td class="val">{$kpiSaldoR}</td></tr>
  <tr><td class="lab">Saldo SIAFI</td><td class="val">{$kpiSaldoS}</td></tr>
  <tr><td class="lab">Diferença (SIAFI − Controle)</td><td class="val">{$kpiDif}</td></tr>
</table>
HTML;

/* Tabela listagem */
$rowsHtml = '';
foreach ($linhas as $ln) {
  $mapa = $ln['mapa'];
  $tagClass = 't-miss';
  if ($mapa === 'Sem alteração') $tagClass = 't-ok';
  elseif ($mapa === 'Nota faltando no controle') $tagClass = 't-warn';
  elseif ($mapa === 'Nota não liquidada') $tagClass = 't-bad';
  elseif ($mapa === 'Sem SIAFI') $tagClass = 't-miss';

  $saldoSiafi = ($ln['saldo_siafi'] === null) ? '--' : fmtMoney($ln['saldo_siafi']);
  $dif = ($ln['dif'] === null) ? '--' : fmtMoney($ln['dif']);

  $rowsHtml .= "
    <tr>
      <td>".h($ln['om'])."</td>
      <td class='nowrap'>".h($ln['nmr'])."</td>
      <td class='center'>".h($ln['ano'])."</td>
      <td class='center'>".h($ln['categoria'])."</td>
      <td class='center'>".h($ln['resto'])."</td>
      <td class='right'>".h(fmtMoney($ln['empenhado']))."</td>
      <td class='right'>".h(fmtMoney($ln['liquidado']))."</td>
      <td class='right'>".h(fmtMoney($ln['aberto']))."</td>
      <td class='right'>".h(fmtMoney($ln['saldo_real']))."</td>
      <td class='right'>".h($saldoSiafi)."</td>
      <td class='right'>".h($dif)."</td>
      <td class='center'><span class='tag {$tagClass}'>".h($mapa)."</span></td>
    </tr>
  ";
}

$listagem = <<<HTML
<div class="break"></div>
<div class="section-title">Listagem de empenhos</div>
<div class="hint">MAPA: DIF = (Saldo SIAFI − Saldo Controle). DIF &gt; 0: Nota não liquidada • DIF &lt; 0: Nota faltando no controle • DIF = 0: Sem alteração.</div>

<table class="data">
  <thead>
    <tr>
      <th>OM</th>
      <th>Nº Empenho</th>
      <th>Ano</th>
      <th>Categoria</th>
      <th>Restos</th>
      <th>Total Empenhado</th>
      <th>Liquidado</th>
      <th>Em aberto</th>
      <th>Saldo controle</th>
      <th>Saldo SIAFI</th>
      <th>Diferença</th>
      <th>Mapa</th>
    </tr>
  </thead>
  <tbody>
    {$rowsHtml}
  </tbody>
</table>
HTML;

/* Documento final */
$html = "<html><head><meta charset='UTF-8'>{$css}</head><body>{$header}{$footer}{$kpis}{$listagem}</body></html>";

/* ===========================
   GERAR PDF
=========================== */
$options = new Options();
$options->set('isRemoteEnabled', true);   // se um dia você usar imagens remotas (logo)
$options->set('isHtml5ParserEnabled', true);
$options->set('defaultFont', 'DejaVu Sans');

$dompdf = new Dompdf($options);
$dompdf->setPaper('A4', 'landscape'); // paisagem fica excelente pra tabela grande
$dompdf->loadHtml($html);
$dompdf->render();

// nome do arquivo
$nome = "dashboard_financeiro_" . date('Ymd_His') . ".pdf";
$dompdf->stream($nome, ["Attachment" => true]);
exit;