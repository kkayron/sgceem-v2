<?php
/*******************************************************************************
 * GERAR CALENDÁRIO DE MANUTENÇÃO PREVENTIVA – EXCEL (XLS via HTML)
 * - Calendário por semanas (primeira coluna = semana atual)
 *
 * CORES / LETRAS:
 *   Verde   + "M" = semana da manutenção preventiva (data prevista)
 *   Amarelo forte + "P" = 3 semanas anteriores (manutenções próximas)
 *   Azul          = "Em manutenção"/"Aguardando" (semana atual)
 *   Vermelho      = "Manutenção vencida" (semana atual)
 *
 * REGRA CORRETA DA DATA DA MANUTENÇÃO:
 *   data_prevista = DATA MAIS PRÓXIMA DE ACONTECER (a partir de hoje) entre:
 *     - data_limite_tempo (dd/mm/yyyy)
 *     - data_prev_odometro (dd/mm/yyyy)
 *   Observações:
 *   - Se existir uma data futura e outra no passado, escolhe a futura.
 *   - Se as duas forem futuras, escolhe a menor (mais próxima).
 *   - Se as duas forem passadas, escolhe a maior (menos antiga / mais recente no passado).
 *******************************************************************************/

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_start();

require_once '../conexao/config.php';

if (!isset($_SESSION['usuario_id'])) {
  die("Sessão expirada. Faça login novamente.");
}

/* =============================
   HEADER EXCEL
============================= */
header("Content-Type: application/vnd.ms-excel; charset=UTF-8");
header("Content-Disposition: attachment; filename=calendario_preventiva_" . date('Ymd_His') . ".xls");
header("Pragma: no-cache");
header("Expires: 0");
echo "\xEF\xBB\xBF"; // BOM UTF-8

/* =============================
   SESSÃO / PERMISSÃO OM
============================= */
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

/* =============================
   FILTROS RECEBIDOS
============================= */
$filtro_om = $_GET['batalhao'] ?? 'todos';

// filtros frota
$filtro_prefixo         = trim($_GET['prefixo_sga'] ?? '');
$filtro_tipo            = trim($_GET['tipo'] ?? '');
$filtro_ativo           = trim($_GET['ativo'] ?? '');
$filtro_acervo          = trim($_GET['acervo'] ?? '');
$filtro_marca           = trim($_GET['marca'] ?? '');
$filtro_confiabilidade  = trim($_GET['confiabilidade'] ?? '');
$filtro_disponibilidade = trim($_GET['disponibilidade'] ?? '');
$filtro_destino         = trim($_GET['destino'] ?? '');

/* =============================
   WHERE OM
============================= */
if ($filtro_om === 'todos') {
  $ids = array_keys($oms_visiveis);
  $where_om = !empty($ids) ? "batalhao IN (" . implode(',', array_map('intval', $ids)) . ")" : "1=0";
} else {
  $where_om = "batalhao = " . (int)$filtro_om;
}

/* =============================
   WHERE FROTA (com filtros)
============================= */
$filtrosFrota = [];
$paramsFrota  = [];
$typesFrota   = '';

$filtrosFrota[] = $where_om;
$filtrosFrota[] = "tipo IN ('Vtr','Eqp')";

// prefixo_sga (LIKE)
if ($filtro_prefixo !== '') {
  $filtrosFrota[] = "prefixo_sga LIKE ?";
  $paramsFrota[]  = "%" . $filtro_prefixo . "%";
  $typesFrota    .= 's';
}

if ($filtro_tipo !== '') {
  $filtrosFrota[] = "tipo = ?";
  $paramsFrota[]  = $filtro_tipo;
  $typesFrota    .= 's';
}

if ($filtro_ativo !== '') {
  $filtrosFrota[] = "ativo = ?";
  $paramsFrota[]  = $filtro_ativo;
  $typesFrota    .= 's';
}

if ($filtro_acervo !== '') {
  $filtrosFrota[] = "acervo = ?";
  $paramsFrota[]  = $filtro_acervo;
  $typesFrota    .= 's';
}

if ($filtro_marca !== '') {
  $filtrosFrota[] = "marca = ?";
  $paramsFrota[]  = $filtro_marca;
  $typesFrota    .= 's';
}

if ($filtro_confiabilidade !== '') {
  $filtrosFrota[] = "confiabilidade = ?";
  $paramsFrota[]  = $filtro_confiabilidade;
  $typesFrota    .= 's';
}

if ($filtro_disponibilidade !== '') {
  $filtrosFrota[] = "disponibilidade = ?";
  $paramsFrota[]  = $filtro_disponibilidade;
  $typesFrota    .= 's';
}

if ($filtro_destino !== '') {
  $filtrosFrota[] = "destino = ?";
  $paramsFrota[]  = $filtro_destino;
  $typesFrota    .= 's';
}

$whereFrotaSql = implode(" AND ", $filtrosFrota);

/* =============================
   CÁLCULO PREVENTIVA
============================= */
include_once('../includes/preventiva/calculo_manutencao.php');

/**
 * Buscar frota já filtrada
 */
$sql_frota = "
  SELECT id, tipo, prefixo_sga, nome_sioc, status_odometro
  FROM frota
  WHERE $whereFrotaSql
  ORDER BY tipo ASC, prefixo_sga ASC
";
$stmtF = $conexao->prepare($sql_frota);
if ($stmtF === false) die("Erro prepare: " . $conexao->error);
if ($paramsFrota) $stmtF->bind_param($typesFrota, ...$paramsFrota);
$stmtF->execute();
$result_frota = $stmtF->get_result();

$frota = [];
while ($row = $result_frota->fetch_assoc()) {
  $row['id'] = (int)$row['id'];
  $frota[] = $row;
}
$stmtF->close();

/* =============================
   FUNÇÕES AUXILIARES
============================= */
function omNome($filtro_om, $oms_visiveis) {
  if ($filtro_om === 'todos') return 'Todos';
  $id = (int)$filtro_om;
  return $oms_visiveis[$id] ?? ('OM ID ' . $id);
}

function normalizarDataYmd($v) {
  $v = trim((string)$v);
  if ($v === '' || $v === '0000-00-00' || $v === '--') return null;

  if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $v)) return $v;

  if (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $v)) {
    [$d,$m,$y] = explode('/', $v);
    return "$y-$m-$d";
  }

  $ts = strtotime($v);
  if ($ts) return date('Y-m-d', $ts);

  return null;
}

function dataBr($ymd) {
  if (!$ymd) return '--';
  return date('d/m/Y', strtotime($ymd));
}

function weekKey($dateYmd) {
  $ts = strtotime($dateYmd);
  return date('o-\WW', $ts);
}

/**
 * ✅ DATA MAIS PRÓXIMA DA DATA ATUAL ENTRE DUAS DATAS
 * Regras:
 * - Se só uma existe => ela
 * - Se uma >= hoje e outra < hoje => pega a futura (>= hoje)
 * - Se ambas >= hoje => pega a menor (mais próxima)
 * - Se ambas < hoje  => pega a maior (menos antiga, mais recente no passado)
 */
function dataMaisProximaYmd($d1, $d2, $hojeYmd) {
  $a = normalizarDataYmd($d1);
  $b = normalizarDataYmd($d2);

  if (!$a && !$b) return null;
  if ($a && !$b) return $a;
  if (!$a && $b) return $b;

  $ta = strtotime($a);
  $tb = strtotime($b);
  $th = strtotime($hojeYmd);

  $aFut = ($ta >= $th);
  $bFut = ($tb >= $th);

  if ($aFut && !$bFut) return $a;
  if ($bFut && !$aFut) return $b;

  if ($aFut && $bFut) {
    // as duas no futuro -> menor
    return ($ta <= $tb) ? $a : $b;
  }

  // as duas no passado -> maior (mais recente)
  return ($ta >= $tb) ? $a : $b;
}

/* =============================
   SEMANAS
============================= */
date_default_timezone_set('America/Sao_Paulo');
$hoje = date('Y-m-d');
$inicioSemanaAtual = date('Y-m-d', strtotime('monday this week', strtotime($hoje)));
$semanasExibir = 52;

$weeks = [];
$mesColspan = [];

for ($i=0; $i<$semanasExibir; $i++) {
  $ini = date('Y-m-d', strtotime("+{$i} week", strtotime($inicioSemanaAtual)));
  $fim = date('Y-m-d', strtotime("+6 day", strtotime($ini)));
  $mes = date('m', strtotime($ini));
  $ano = date('Y', strtotime($ini));

  $weeks[] = [
    'i' => $i,
    'ini' => $ini,
    'fim' => $fim,
    'ano' => $ano,
    'mes' => $mes,
    'key' => weekKey($ini),
  ];

  $k = $ano . '-' . $mes;
  $mesColspan[$k] = ($mesColspan[$k] ?? 0) + 1;
}

$mesNome = [
  "01"=>"JAN","02"=>"FEV","03"=>"MAR","04"=>"ABR","05"=>"MAI","06"=>"JUN",
  "07"=>"JUL","08"=>"AGO","09"=>"SET","10"=>"OUT","11"=>"NOV","12"=>"DEZ"
];

/* =============================
   CALCULAR STATUS + DATA PREVISTA
============================= */
$itens = [];

if (!empty($frota)) {

  $mapBulk = null;
  if (function_exists('calcularManutencaoPreventivaBulk')) {
    $mapBulk = calcularManutencaoPreventivaBulk($conexao, $frota);
  }

  foreach ($frota as $item) {

    if ($mapBulk !== null) {
      $calc = $mapBulk[(int)$item['id']] ?? [];
    } else {
      $calc = calcularManutencaoPreventiva($conexao, $item);
    }

    $status = (string)($calc['status'] ?? 'Sem dados');

    // ✅ data prevista = data MAIS PRÓXIMA de acontecer (entre tempo x odômetro)
    $dataPrev = dataMaisProximaYmd(
      $calc['data_limite_tempo'] ?? null,
      $calc['data_prev_odometro'] ?? null,
      $hoje
    );

    $itens[] = [
      'id' => (int)$item['id'],
      'tipo' => (string)($item['tipo'] ?? ''),
      'prefixo' => (string)($item['prefixo_sga'] ?? ''),
      'nome' => (string)($item['nome_sioc'] ?? ''),
      'status' => $status,
      'data_prevista' => $dataPrev,
    ];
  }
}

/* =============================
   HTML (XLS)
============================= */
$omLabel = omNome($filtro_om, $oms_visiveis);

// cores
$C_VERDE = "#58D68D";
$C_AMARELO_FORTE = "#F4D03F";
$C_AZUL = "#85C1E9";
$C_VERMELHO = "#F1948A";
$C_CINZA = "#F2F3F4";

echo "<table border='1' style='border-collapse:collapse; font-family:Arial; font-size:12px;'>";

echo "<tr>
  <th colspan='".(count($weeks) + 4)."' style='background:#0d6efd; color:#fff; font-size:16px; padding:10px; text-align:center;'>
    CALENDÁRIO — PRÓXIMAS MANUTENÇÕES PREVENTIVAS (Semanal) | OM: ".htmlspecialchars($omLabel)."
  </th>
</tr>";

echo "<tr>
  <td colspan='".(count($weeks) + 4)."' style='padding:8px; background:#FAFAFA;'>
    <strong>Gerado em:</strong> ".date('d/m/Y H:i')." &nbsp; | &nbsp;
    <strong>Semana atual:</strong> ".date('d/m/Y', strtotime($inicioSemanaAtual))." a ".date('d/m/Y', strtotime("+6 day", strtotime($inicioSemanaAtual)))."
    <br><br>
    <strong>Legenda:</strong>
    <span style='display:inline-block; width:14px; height:14px; background:$C_VERDE; border:1px solid #333; vertical-align:middle;'></span> <strong>M</strong> Manutenção (semana da execução) &nbsp;&nbsp;
    <span style='display:inline-block; width:14px; height:14px; background:$C_AMARELO_FORTE; border:1px solid #333; vertical-align:middle;'></span> <strong>P</strong> Próximas (3 semanas antes) &nbsp;&nbsp;
    <span style='display:inline-block; width:14px; height:14px; background:$C_AZUL; border:1px solid #333; vertical-align:middle;'></span> Em manutenção/Aguardando (semana atual) &nbsp;&nbsp;
    <span style='display:inline-block; width:14px; height:14px; background:$C_VERMELHO; border:1px solid #333; vertical-align:middle;'></span> Vencida (semana atual)
  </td>
</tr>";

echo "<tr style='text-align:center; font-weight:bold;'>";
echo "<th rowspan='3' style='background:#D6EAF8; width:140px;'>Prefixo</th>";
echo "<th rowspan='3' style='background:#D6EAF8; width:110px;'>Data Prevista</th>";
echo "<th rowspan='3' style='background:#D6EAF8; width:180px;'>Status</th>";
echo "<th rowspan='3' style='background:#D6EAF8; width:25px;'>S</th>";

foreach ($mesColspan as $key => $span) {
  [$anoM, $mesM] = explode("-", $key);
  $label = ($mesNome[$mesM] ?? $mesM) . " / $anoM";
  echo "<th colspan='{$span}' style='background:#AED6F1; padding:6px;'>{$label}</th>";
}
echo "</tr>";

echo "<tr style='background:#EBF5FB; text-align:center; font-weight:bold;'>";
foreach ($weeks as $w) {
  echo "<th style='padding:4px;'>S".($w['i']+1)."</th>";
}
echo "</tr>";

echo "<tr style='background:#F8F9F9; text-align:center; font-size:10px;'>";
foreach ($weeks as $w) {
  $lbl = date('d/m', strtotime($w['ini'])) . "–" . date('d/m', strtotime($w['fim']));
  echo "<th style='padding:4px;'>$lbl</th>";
}
echo "</tr>";

if (empty($itens)) {
  echo "<tr><td colspan='".(count($weeks) + 4)."' style='padding:10px; color:#777;'>
    Nenhum item encontrado com os filtros aplicados.
  </td></tr>";
  echo "</table>";
  exit;
}

usort($itens, function($a,$b){
  $prio = function($st){
    $st = mb_strtolower(trim((string)$st));
    if (strpos($st, 'vencid') !== false) return 1;
    if (strpos($st, 'aguard') !== false || strpos($st, 'em manuten') !== false) return 2;
    if (strpos($st, 'muito próx') !== false || strpos($st, 'muito prox') !== false) return 3;
    if (strpos($st, 'próxim') !== false || strpos($st, 'proxim') !== false) return 4;
    return 9;
  };

  $pa = $prio($a['status']);
  $pb = $prio($b['status']);
  if ($pa !== $pb) return $pa <=> $pb;

  $da = $a['data_prevista'] ?: '9999-12-31';
  $db = $b['data_prevista'] ?: '9999-12-31';
  if ($da !== $db) return strcmp($da, $db);

  return strcmp((string)$a['prefixo'], (string)$b['prefixo']);
});

foreach ($itens as $it) {

  $prefixo = htmlspecialchars((string)$it['prefixo']);
  $status  = (string)$it['status'];
  $statusLower = mb_strtolower(trim($status));
  $dataPrev = $it['data_prevista']; // Y-m-d ou null

  $dueWeekIndex = null;
  if ($dataPrev) {
    $dueWeekKey = weekKey($dataPrev);
    foreach ($weeks as $w) {
      if ($w['key'] === $dueWeekKey) {
        $dueWeekIndex = (int)$w['i'];
        break;
      }
    }
  }

  $isOverdueByDate = ($dataPrev && strtotime($dataPrev) < strtotime($inicioSemanaAtual));
  $isVencida = (strpos($statusLower, 'vencid') !== false) || $isOverdueByDate;
  $isAguardando = (strpos($statusLower, 'aguard') !== false) || (strpos($statusLower, 'em manuten') !== false);

  echo "<tr>";

  echo "<td style='padding:6px; font-weight:bold; white-space:nowrap;'>$prefixo</td>";
  echo "<td style='padding:6px; text-align:center;'>".htmlspecialchars(dataBr($dataPrev))."</td>";

  $statusStyle = "padding:6px;";
  if ($isVencida) $statusStyle .= " background:$C_VERMELHO; font-weight:bold;";
  else if ($isAguardando) $statusStyle .= " background:$C_AZUL; font-weight:bold;";
  echo "<td style='{$statusStyle}'>".htmlspecialchars($status)."</td>";

  echo "<td style='text-align:center; background:#FCF3CF; font-weight:bold;'>S</td>";

  foreach ($weeks as $w) {

    $bg = "";
    $txt = "";

    // prioridade máxima: vencida / aguardando => pintar semana atual
    if ($w['i'] === 0) {
      if ($isVencida) $bg = $C_VERMELHO;
      elseif ($isAguardando) $bg = $C_AZUL;
    }

    // depois pinta conforme semana prevista
    if ($bg === "" && $dueWeekIndex !== null) {
      if ($w['i'] === $dueWeekIndex) {
        $bg = $C_VERDE;
        $txt = "M";
      } else if ($w['i'] >= ($dueWeekIndex - 3) && $w['i'] < $dueWeekIndex) {
        $bg = $C_AMARELO_FORTE;
        $txt = "P";
      }
    }

    // sem data prevista => cinza
    if ($bg === "" && !$dataPrev) {
      $bg = $C_CINZA;
    }

    $style = "height:18px; min-width:18px; background:{$bg}; text-align:center; font-weight:bold;";
    echo "<td style='{$style}'>".htmlspecialchars($txt)."</td>";
  }

  echo "</tr>";
}

echo "</table>";
exit;
?>