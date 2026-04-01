<?php
/*******************************************************************************
 * GERAR DASHBOARD FINANCEIRO – EXCEL (XLS via HTML)
 * - Resumo em cima + listagem completa dos empenhos embaixo
 * - Colunas: nmr_empenho, ano, categoria, resto, OM, valor empenhado,
 *            liquidado, em aberto, saldo real (controle), saldo SIAFI, diferença, MAPA
 *
 * REGRA MAPA (baseado em DIF = saldoSiafi - saldoReal):
 *   DIF > 0  => "Nota não liquidada"
 *   DIF < 0  => "Nota faltando no controle"
 *   DIF = 0  => "Sem alteração"
 *******************************************************************************/

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_start();

if (!isset($_SESSION['usuario_id'])) {
  die("Sessão expirada. Faça login novamente.");
}

require_once '../conexao/config.php';

// tenta liberar joins grandes (nem todo host permite, mas não atrapalha)
@mysqli_query($conexao, "SET SESSION SQL_BIG_SELECTS=1");

date_default_timezone_set('America/Sao_Paulo');

/* =============================
   HEADER EXCEL
============================= */
header("Content-Type: application/vnd.ms-excel; charset=UTF-8");
header("Content-Disposition: attachment; filename=dashboard_financeiro_" . date('Ymd_His') . ".xls");
header("Pragma: no-cache");
header("Expires: 0");
echo "\xEF\xBB\xBF"; // BOM UTF-8

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

// para MAPA
function mapaStatus($dif) {
  // dif = saldoSiafi - saldoReal
  if ($dif > 0) return "Nota não liquidada";
  if ($dif < 0) return "Nota faltando no controle";
  return "Sem alteração";
}

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
   2) SOMAR STATUS (SEM JOIN GIGANTE) — ANTI MAX_JOIN_SIZE
=========================== */
$mapaStatusPorEmpenho = []; // [id_empenho][bucket] = valor
foreach ($idsEmpenhos as $idEmp) $mapaStatusPorEmpenho[$idEmp] = $BUCKETS;

if (!empty($idsEmpenhos)) {

  $chunkSizeEmp = 200;
  $chunks = array_chunk($idsEmpenhos, $chunkSizeEmp);

  foreach ($chunks as $chunkEmp) {

    $listaEmp = implode(',', array_map('intval', $chunkEmp));

    // (a) empenho -> status -> pedido
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

    $pedidoMeta = []; // [id_pedido] => array de pares [id_empenho, bucket]
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

    // (b) soma itens por pedido (em chunks)
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
$mapaSiafi = []; // [nmr_empenho] => float
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
  saldo_empenho
FROM (
  SELECT nmr_empenho, saldo_empenho 
  FROM fin_siafi_corrente 
  WHERE nmr_empenho IN ($placeholders)

  UNION ALL

  SELECT nmr_empenho, saldo_empenho 
  FROM fin_siafi_restopagar 
  WHERE nmr_empenho IN ($placeholders)
) x
GROUP BY nmr_empenho
";
      $stmtS = $conexao->prepare($sqlSiafi);
      if ($stmtS) {
        $stmtS->bind_param($typesNums . $typesNums, ...array_merge($nch, $nch));
        $stmtS->execute();
        $rS = $stmtS->get_result();
        while ($s = $rS->fetch_assoc()) {
          $mapaSiafi[(string)$s['nmr_empenho']] = (float)$s['saldo_empenho'];
        }
        $stmtS->close();
      }
    }
  }
}

/* ===========================
   4) CALCULAR LINHAS + RESUMO
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

foreach ($empenhos as $e) {
  $idEmp = (int)$e['id'];
  $nmr = trim((string)$e['nmr_empenho']);

  $valorEmpenhado = (float)$e['valor_empenhado'];
  $b = $mapaStatusPorEmpenho[$idEmp] ?? $BUCKETS;

  $naoEnt = (float)$b['Não entregue'];
  $entr   = (float)$b['Entregue'];
  $cap    = (float)$b['Capeador'];
  $liq    = (float)$b['Liquidado'];

  $emAberto = $naoEnt + $entr + $cap; // em aberto
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
   HTML XLS
=========================== */
$omLabel = ($filtro_om === 'todos') ? 'Todos' : omNomeById((int)$filtro_om, $oms_visiveis);

echo "<table border='1' style='border-collapse:collapse; font-family:Arial; font-size:12px;'>";

// Título
echo "<tr>
  <th colspan='12' style='background:#198754; color:#fff; font-size:16px; padding:10px; text-align:center;'>
    DASHBOARD FINANCEIRO / EMPENHOS | OM: ".htmlspecialchars($omLabel)."
  </th>
</tr>";

// Metadados
echo "<tr>
  <td colspan='12' style='padding:8px; background:#FAFAFA;'>
    <strong>Gerado em:</strong> ".date('d/m/Y H:i')."
    &nbsp; | &nbsp; <strong>Ano:</strong> ".htmlspecialchars($filtro_ano !== '' ? $filtro_ano : 'Todos')."
    &nbsp; | &nbsp; <strong>Categoria:</strong> ".htmlspecialchars($filtro_categoria !== '' ? $filtro_categoria : 'Todas')."
    &nbsp; | &nbsp; <strong>Restos:</strong> ".htmlspecialchars($filtro_resto !== '' ? $filtro_resto : 'Todos')."
    &nbsp; | &nbsp; <strong>Total de empenhos:</strong> ".number_format($totalRegistros)."
  </td>
</tr>";

// RESUMO
echo "<tr><th colspan='12' style='background:#D1E7DD; padding:6px; text-align:left;'>RESUMO</th></tr>";

echo "<tr style='background:#F8F9FA; font-weight:bold;'>
  <td colspan='6' style='padding:6px;'>Indicador</td>
  <td colspan='6' style='padding:6px; text-align:right;'>Valor</td>
</tr>";

echo "<tr><td colspan='6' style='padding:6px;'>Total Empenhado</td><td colspan='6' style='padding:6px; text-align:right;'>".fmtMoney($soma_total_empenhado)."</td></tr>";
echo "<tr><td colspan='6' style='padding:6px;'>Total Liquidado (".fmtPct($porc_liquidado).")</td><td colspan='6' style='padding:6px; text-align:right;'>".fmtMoney($soma_liquidado)."</td></tr>";
echo "<tr><td colspan='6' style='padding:6px;'>Em Aberto (Req/Ent/Capeador)</td><td colspan='6' style='padding:6px; text-align:right;'>".fmtMoney($soma_nao_entregue + $soma_entregue + $soma_capeador)."</td></tr>";
echo "<tr><td colspan='6' style='padding:6px;'>Saldo Real (Controle)</td><td colspan='6' style='padding:6px; text-align:right;'>".fmtMoney($soma_saldo_real)."</td></tr>";
echo "<tr><td colspan='6' style='padding:6px;'>Saldo SIAFI (Corrente + Restos)</td><td colspan='6' style='padding:6px; text-align:right;'>".fmtMoney($soma_saldo_siafi)."</td></tr>";
echo "<tr><td colspan='6' style='padding:6px; font-weight:bold; background:#FFF3CD;'>Diferença (SIAFI − Controle)</td><td colspan='6' style='padding:6px; text-align:right; font-weight:bold; background:#FFF3CD;'>".fmtMoney($soma_diferenca)."</td></tr>";

// Legenda MAPA
echo "<tr>
  <td colspan='12' style='padding:8px; background:#F1F3F5;'>
    <strong>MAPA (por empenho):</strong>
    DIF = (Saldo SIAFI − Saldo Controle) &nbsp; | &nbsp;
    DIF &gt; 0: <strong>Nota não liquidada</strong> &nbsp; | &nbsp;
    DIF &lt; 0: <strong>Nota faltando no controle</strong> &nbsp; | &nbsp;
    DIF = 0: <strong>Sem alteração</strong>
  </td>
</tr>";

// Espaço
echo "<tr><td colspan='12' style='background:#FFFFFF; height:10px;'></td></tr>";

// LISTAGEM
echo "<tr><th colspan='12' style='background:#0D6EFD; color:#fff; padding:6px; text-align:left;'>LISTAGEM DE EMPENHOS</th></tr>";

echo "<tr style='background:#E9ECEF; font-weight:bold; text-align:center;'>
  <td style='padding:6px;'>OM</td>
  <td style='padding:6px;'>Nº Empenho</td>
  <td style='padding:6px;'>Ano</td>
  <td style='padding:6px;'>Categoria</td>
  <td style='padding:6px;'>Restos</td>
  <td style='padding:6px;'>Total Empenhado</td>
  <td style='padding:6px;'>Total Liquidado</td>
  <td style='padding:6px;'>Em Aberto</td>
  <td style='padding:6px;'>Saldo Real (Controle)</td>
  <td style='padding:6px;'>Saldo SIAFI</td>
  <td style='padding:6px;'>Diferença</td>
  <td style='padding:6px;'>MAPA</td>
</tr>";

if (empty($linhas)) {
  echo "<tr><td colspan='12' style='padding:10px; color:#777;'>Nenhum empenho encontrado com os filtros aplicados.</td></tr>";
  echo "</table>";
  exit;
}

foreach ($linhas as $ln) {
  $dif = $ln['dif'];

  // cor por MAPA
  $bgMapa = "#FFFFFF";
  if ($ln['mapa'] === "Nota não liquidada") $bgMapa = "#F8D7DA";         // vermelho claro
  elseif ($ln['mapa'] === "Nota faltando no controle") $bgMapa = "#FFF3CD"; // amarelo claro
  elseif ($ln['mapa'] === "Sem alteração") $bgMapa = "#D1E7DD";          // verde claro
  elseif ($ln['mapa'] === "Sem SIAFI") $bgMapa = "#E2E3E5";              // cinza

  echo "<tr>
    <td style='padding:6px;'>".htmlspecialchars($ln['om'])."</td>
    <td style='padding:6px; white-space:nowrap;'>".htmlspecialchars($ln['nmr'])."</td>
    <td style='padding:6px; text-align:center;'>".htmlspecialchars($ln['ano'])."</td>
    <td style='padding:6px; text-align:center;'>".htmlspecialchars($ln['categoria'])."</td>
    <td style='padding:6px; text-align:center;'>".htmlspecialchars($ln['resto'])."</td>

    <td style='padding:6px; text-align:right;'>".fmtMoney($ln['empenhado'])."</td>
    <td style='padding:6px; text-align:right;'>".fmtMoney($ln['liquidado'])."</td>
    <td style='padding:6px; text-align:right;'>".fmtMoney($ln['aberto'])."</td>
    <td style='padding:6px; text-align:right;'>".fmtMoney($ln['saldo_real'])."</td>

    <td style='padding:6px; text-align:right;'>".($ln['saldo_siafi'] === null ? "--" : fmtMoney($ln['saldo_siafi']))."</td>
    <td style='padding:6px; text-align:right;'>".($dif === null ? "--" : fmtMoney($dif))."</td>

    <td style='padding:6px; background:$bgMapa; font-weight:bold;'>".htmlspecialchars($ln['mapa'])."</td>
  </tr>";
}

// Rodapé (totais)
echo "<tr style='background:#212529; color:#fff; font-weight:bold;'>
  <td colspan='5' style='padding:8px; text-align:right;'>TOTAIS</td>
  <td style='padding:8px; text-align:right;'>".fmtMoney($soma_total_empenhado)."</td>
  <td style='padding:8px; text-align:right;'>".fmtMoney($soma_liquidado)."</td>
  <td style='padding:8px; text-align:right;'>".fmtMoney($soma_nao_entregue + $soma_entregue + $soma_capeador)."</td>
  <td style='padding:8px; text-align:right;'>".fmtMoney($soma_saldo_real)."</td>
  <td style='padding:8px; text-align:right;'>".fmtMoney($soma_saldo_siafi)."</td>
  <td style='padding:8px; text-align:right;'>".fmtMoney($soma_diferenca)."</td>
  <td style='padding:8px; text-align:center;'>—</td>
</tr>";

echo "</table>";
exit;
?>