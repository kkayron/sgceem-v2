<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

if (!isset($_SESSION['usuario_id'])) {
  http_response_code(401);
  echo "Sessão expirada. Faça login novamente.";
  exit;
}

require_once '../conexao/config.php';
require_once 'vendor/autoload.php';

use Dompdf\Dompdf;

// =========================
// sessão
// =========================
$id_om_usuario = (int)($_SESSION['usuario']['batalhao'] ?? 0);
$nivel_usuario = (int)($_SESSION['usuario']['nivel'] ?? 3);

// =========================
// OMs visíveis (igual ao dashboard)
// =========================
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

// =========================
// filtros GET (mesmos do bloco)
// =========================
$filtro_om = $_GET['batalhao'] ?? 'todos';

$filtro_prefixo         = trim($_GET['prefixo_sga'] ?? '');
$filtro_tipo            = trim($_GET['tipo'] ?? '');
$filtro_ativo           = trim($_GET['ativo'] ?? '');
$filtro_acervo          = trim($_GET['acervo'] ?? '');
$filtro_marca           = trim($_GET['marca'] ?? '');
$filtro_confiabilidade  = trim($_GET['confiabilidade'] ?? '');
$filtro_disponibilidade = trim($_GET['disponibilidade'] ?? '');
$filtro_destino         = trim($_GET['destino'] ?? '');

// WHERE OM
if ($filtro_om === 'todos') {
  $ids = array_keys($oms_visiveis);
  $where_om = !empty($ids) ? "batalhao IN (" . implode(',', array_map('intval', $ids)) . ")" : "1=0";
} else {
  $where_om = "batalhao = " . (int)$filtro_om;
}

// WHERE FROTA com binds
$filtrosFrota = [];
$paramsFrota  = [];
$typesFrota   = '';
$filtrosFrota[] = $where_om;

if ($filtro_prefixo !== '') { $filtrosFrota[]="prefixo_sga LIKE ?"; $paramsFrota[]="%".$filtro_prefixo."%"; $typesFrota.='s'; }
if ($filtro_tipo !== '') { $filtrosFrota[]="tipo = ?"; $paramsFrota[]=$filtro_tipo; $typesFrota.='s'; }
if ($filtro_ativo !== '') { $filtrosFrota[]="ativo = ?"; $paramsFrota[]=$filtro_ativo; $typesFrota.='s'; }
if ($filtro_acervo !== '') { $filtrosFrota[]="acervo = ?"; $paramsFrota[]=$filtro_acervo; $typesFrota.='s'; }
if ($filtro_marca !== '') { $filtrosFrota[]="marca = ?"; $paramsFrota[]=$filtro_marca; $typesFrota.='s'; }
if ($filtro_confiabilidade !== '') { $filtrosFrota[]="confiabilidade = ?"; $paramsFrota[]=$filtro_confiabilidade; $typesFrota.='s'; }
if ($filtro_disponibilidade !== '') { $filtrosFrota[]="disponibilidade = ?"; $paramsFrota[]=$filtro_disponibilidade; $typesFrota.='s'; }
if ($filtro_destino !== '') { $filtrosFrota[]="destino = ?"; $paramsFrota[]=$filtro_destino; $typesFrota.='s'; }

$whereFrotaSql = implode(" AND ", $filtrosFrota);

// IDs frota filtrada (para OS/Fichas)
$idsFrotaFiltrada = [];
$sqlIds = "SELECT id FROM frota WHERE $whereFrotaSql";
$stmtIds = $conexao->prepare($sqlIds);
if ($paramsFrota) $stmtIds->bind_param($typesFrota, ...$paramsFrota);
$stmtIds->execute();
$resIds = $stmtIds->get_result();
while ($row = $resIds->fetch_assoc()) $idsFrotaFiltrada[] = (int)$row['id'];
$stmtIds->close();

$where_in_frota = !empty($idsFrotaFiltrada)
  ? "IN (" . implode(',', array_map('intval', $idsFrotaFiltrada)) . ")"
  : "IN (0)";

function pctNum($parte, $total){
  return $total > 0 ? round(($parte / $total) * 100, 2) : 0;
}

// =========================
// OS
// =========================
$sql_os = "
  SELECT status, COUNT(*) qtd
  FROM os_principal
  WHERE $where_om
    AND id_frota $where_in_frota
  GROUP BY status
";
$res_os = $conexao->query($sql_os);

$total_os = 0; $em_andamento = 0; $concluidas = 0; $outras_os = 0;
if ($res_os) {
  while ($row = $res_os->fetch_assoc()) {
    $q = (int)$row['qtd'];
    $st = (string)$row['status'];
    $total_os += $q;
    if (in_array($st, ['Em andamento','Aguardando Peças','Aguardando Suprimento','Aguardando Descarga'], true)) $em_andamento += $q;
    elseif (in_array($st, ['Concluída','Eqp/Vtr descarregado'], true)) $concluidas += $q;
    else $outras_os += $q;
  }
}

// =========================
// Fichas
// =========================
$sql_f = "
  SELECT status, COUNT(*) qtd
  FROM sta_fichas
  WHERE $where_om
    AND id_viatura $where_in_frota
  GROUP BY status
";
$res_f = $conexao->query($sql_f);

$total_fichas = 0; $abertas = 0; $encerradas = 0;
if ($res_f) {
  while ($row = $res_f->fetch_assoc()) {
    $q = (int)$row['qtd'];
    $st = (string)$row['status'];
    $total_fichas += $q;
    if ($st === 'Aberta') $abertas += $q;
    if ($st === 'Encerrada') $encerradas += $q;
  }
}

// =========================
// Totais frota
// =========================
$sql_totais = "
SELECT
  COUNT(CASE WHEN tipo = 'Vtr' THEN 1 END) AS total_vtr,
  SUM(CASE WHEN tipo = 'Vtr' AND confiabilidade = 'Confiável' THEN 1 ELSE 0 END) AS confiaveis_vtr,
  SUM(CASE WHEN tipo = 'Vtr' AND confiabilidade = 'Não confiável' THEN 1 ELSE 0 END) AS nao_confiaveis_vtr,
  SUM(CASE WHEN tipo = 'Vtr' AND disponibilidade = 'Disponível' THEN 1 ELSE 0 END) AS disponiveis_vtr,
  SUM(CASE WHEN tipo = 'Vtr' AND disponibilidade != 'Disponível' THEN 1 ELSE 0 END) AS indisp_vtr,

  COUNT(CASE WHEN tipo = 'Eqp' THEN 1 END) AS total_eqp,
  SUM(CASE WHEN tipo = 'Eqp' AND confiabilidade = 'Confiável' THEN 1 ELSE 0 END) AS confiaveis_eqp,
  SUM(CASE WHEN tipo = 'Eqp' AND confiabilidade = 'Não confiável' THEN 1 ELSE 0 END) AS nao_confiaveis_eqp,
  SUM(CASE WHEN tipo = 'Eqp' AND disponibilidade = 'Disponível' THEN 1 ELSE 0 END) AS disponiveis_eqp,
  SUM(CASE WHEN tipo = 'Eqp' AND disponibilidade != 'Disponível' THEN 1 ELSE 0 END) AS indisp_eqp
FROM frota
WHERE $whereFrotaSql
";
$stmtTot = $conexao->prepare($sql_totais);
if ($paramsFrota) $stmtTot->bind_param($typesFrota, ...$paramsFrota);
$stmtTot->execute();
$totais = $stmtTot->get_result()->fetch_assoc();
$stmtTot->close();

$total_viaturas = (int)($totais['total_vtr'] ?? 0);
$total_equipamentos = (int)($totais['total_eqp'] ?? 0);
$total_ativos = $total_viaturas + $total_equipamentos;

$confiaveis_vtr = (int)($totais['confiaveis_vtr'] ?? 0);
$confiaveis_eqp = (int)($totais['confiaveis_eqp'] ?? 0);
$nao_confiaveis_vtr = (int)($totais['nao_confiaveis_vtr'] ?? 0);
$nao_confiaveis_eqp = (int)($totais['nao_confiaveis_eqp'] ?? 0);

$disponiveis_vtr = (int)($totais['disponiveis_vtr'] ?? 0);
$indisp_vtr = (int)($totais['indisp_vtr'] ?? 0);
$disponiveis_eqp = (int)($totais['disponiveis_eqp'] ?? 0);
$indisp_eqp = (int)($totais['indisp_eqp'] ?? 0);

$total_disp_geral = $disponiveis_vtr + $disponiveis_eqp;
$total_indisp_geral = $indisp_vtr + $indisp_eqp;

// =========================
// Destinos VTR/EQP
// =========================
$destinos_viaturas = [];
$destinos_equipamentos = [];

$sql_destinos = "
  SELECT UPPER(TRIM(tipo)) AS tipo_normalizado,
         COALESCE(NULLIF(TRIM(destino),''), 'Sem destino') AS destino,
         COUNT(*) AS total
  FROM frota
  WHERE $whereFrotaSql
  GROUP BY UPPER(TRIM(tipo)), COALESCE(NULLIF(TRIM(destino),''), 'Sem destino')
  ORDER BY total DESC
";
$stmtD = $conexao->prepare($sql_destinos);
if ($paramsFrota) $stmtD->bind_param($typesFrota, ...$paramsFrota);
$stmtD->execute();
$res_d = $stmtD->get_result();
while ($row = $res_d->fetch_assoc()) {
  $dest = (string)$row['destino'];
  $tipo = (string)$row['tipo_normalizado'];
  if ($tipo === 'VTR') $destinos_viaturas[$dest] = (int)$row['total'];
  if ($tipo === 'EQP' || $tipo === 'EQUIPAMENTO') $destinos_equipamentos[$dest] = (int)$row['total'];
}
$stmtD->close();

// =========================
// Top destinos indisponíveis
// =========================
$top_destinos_indisp = [];
$sql_top_dest = "
  SELECT COALESCE(NULLIF(TRIM(destino),''), 'Sem destino') AS destino, COUNT(*) AS total
  FROM frota
  WHERE $whereFrotaSql
    AND disponibilidade <> 'Disponível'
  GROUP BY COALESCE(NULLIF(TRIM(destino),''), 'Sem destino')
  ORDER BY total DESC
  LIMIT 10
";
$stmtTD = $conexao->prepare($sql_top_dest);
if ($paramsFrota) $stmtTD->bind_param($typesFrota, ...$paramsFrota);
$stmtTD->execute();
$resTD = $stmtTD->get_result();
while ($r = $resTD->fetch_assoc()) $top_destinos_indisp[] = ['destino' => $r['destino'], 'total' => (int)$r['total']];
$stmtTD->close();

// =========================
// Monta “texto dos filtros” pro cabeçalho do PDF
// =========================
$omNome = 'Todos';
if ($filtro_om !== 'todos') $omNome = $oms_visiveis[(int)$filtro_om] ?? ('OM #' . (int)$filtro_om);

$linhasFiltros = [];
$linhasFiltros[] = "OM: ".$omNome;
if ($filtro_prefixo !== '') $linhasFiltros[] = "Prefixo: ".$filtro_prefixo;
if ($filtro_tipo !== '') $linhasFiltros[] = "Tipo: ".$filtro_tipo;
if ($filtro_ativo !== '') $linhasFiltros[] = "Ativo: ".$filtro_ativo;
if ($filtro_acervo !== '') $linhasFiltros[] = "Acervo: ".$filtro_acervo;
if ($filtro_marca !== '') $linhasFiltros[] = "Marca: ".$filtro_marca;
if ($filtro_confiabilidade !== '') $linhasFiltros[] = "Confiabilidade: ".$filtro_confiabilidade;
if ($filtro_disponibilidade !== '') $linhasFiltros[] = "Disponibilidade: ".$filtro_disponibilidade;
if ($filtro_destino !== '') $linhasFiltros[] = "Destino: ".$filtro_destino;

$filtrosTxt = implode(" | ", $linhasFiltros);

// =========================
// HTML do PDF (estilo semelhante ao seu modelo)
// =========================
function bar($pct){
  $pct = max(0, min(100, (float)$pct));
  return '<div class="bar"><div class="fill" style="width:'.$pct.'%"></div></div>';
}

function tabelaSimples($titulo, $linhas){
  $html = '<h2>'.htmlspecialchars($titulo).'</h2>';
  $html .= '<table><thead><tr><th>Item</th><th>Total</th></tr></thead><tbody>';
  foreach ($linhas as $l) {
    $html .= '<tr><td>'.htmlspecialchars($l['label']).'</td><td>'.htmlspecialchars((string)$l['value']).'</td></tr>';
  }
  $html .= '</tbody></table>';
  return $html;
}

function tabelaKeyValue($titulo, $arrAssoc){
  $html = '<h2>'.htmlspecialchars($titulo).'</h2>';
  if (empty($arrAssoc)) {
    return $html . '<p class="muted">Nenhum registro encontrado.</p>';
  }
  $html .= '<table><thead><tr><th>Destino</th><th>Total</th></tr></thead><tbody>';
  foreach ($arrAssoc as $k => $v) {
    $html .= '<tr><td>'.htmlspecialchars((string)$k).'</td><td>'.(int)$v.'</td></tr>';
  }
  $html .= '</tbody></table>';
  return $html;
}

$html = '<!DOCTYPE html><html><head><meta charset="UTF-8"><style>
  body{font-family:Segoe UI, Roboto, Arial, sans-serif; color:#333; margin:20px; font-size:12px;}
  header{ text-align:center; border-bottom:2px solid #0d6efd; padding-bottom:10px; margin-bottom:16px;}
  header img{ width:70px; margin-bottom:5px;}
  header h1{ margin:0; font-size:20px; color:#0d6efd;}
  header p{ margin:2px 0; font-size:11px; color:#555;}
  .meta{ margin:10px 0 14px; padding:10px; background:#f8f9fa; border:1px solid #e5e5e5; border-radius:6px;}
  .grid{ width:100%; border-collapse:separate; border-spacing:10px 10px;}
  .card{ border:1px solid #e5e5e5; border-radius:8px; padding:10px; background:#fff;}
  .card .k{ color:#6c757d; font-size:11px;}
  .card .v{ font-size:20px; font-weight:bold; margin-top:2px;}
  h2{ color:#0d6efd; margin:14px 0 6px; border-bottom:1px solid #ddd; padding-bottom:3px;}
  table{ width:100%; border-collapse:collapse; margin-top:6px;}
  th, td{ border:1px solid #dee2e6; padding:6px; text-align:left; vertical-align:top;}
  th{ background:#0d6efd; color:#fff; font-size:12px;}
  td{ font-size:11px;}
  .muted{ color:#888; font-size:11px;}
  .bar{ height:10px; background:#e9ecef; border-radius:10px; overflow:hidden; margin-top:4px;}
  .fill{ height:10px; background:#0d6efd;}
  footer{ position:fixed; bottom:10px; left:0; right:0; text-align:center; font-size:10px; color:#666; border-top:1px solid #ddd; padding-top:5px;}
</style></head><body>';

$html .= '<header>
  <img src="https://gceem.22web.org/uploads/logo_exercito.png" alt="Logo">
  <h1>Relatório da Frota (Resumo)</h1>
  <p>Indicadores de manutenção, confiabilidade e disponibilidade</p>
</header>';

$html .= '<div class="meta">
  <div><strong>Filtros:</strong> '.htmlspecialchars($filtrosTxt).'</div>
  <div class="muted">Gerado em '.date("d/m/Y H:i").'</div>
</div>';

// Cards resumo
$html .= '<table class="grid"><tr>
  <td class="card" width="25%"><div class="k">OS (Total)</div><div class="v">'.(int)$total_os.'</div></td>
  <td class="card" width="25%"><div class="k">Backlog (Em andamento)</div><div class="v">'.(int)$em_andamento.'</div>'.bar(pctNum($em_andamento, $total_os)).'</td>
  <td class="card" width="25%"><div class="k">Concluídas</div><div class="v">'.(int)$concluidas.'</div>'.bar(pctNum($concluidas, $total_os)).'</td>
  <td class="card" width="25%"><div class="k">Indisponíveis (Vtr+Eqp)</div><div class="v">'.(int)$total_indisp_geral.'</div></td>
</tr></table>';

$html .= tabelaSimples('Indicadores (Manutenção e Operação)', [
  ['label'=>'Total Ativos (Vtr + Eqp)', 'value'=>$total_ativos],
  ['label'=>'Total Viaturas', 'value'=>$total_viaturas],
  ['label'=>'Total Equipamentos', 'value'=>$total_equipamentos],
  ['label'=>'Disponíveis (Geral)', 'value'=>$total_disp_geral],
  ['label'=>'Indisponíveis (Geral)', 'value'=>$total_indisp_geral],
  ['label'=>'Confiáveis (Vtr + Eqp)', 'value'=>$confiaveis_vtr + $confiaveis_eqp],
  ['label'=>'Não confiáveis (Vtr + Eqp)', 'value'=>$nao_confiaveis_vtr + $nao_confiaveis_eqp],
  ['label'=>'Fichas (Total)', 'value'=>$total_fichas],
  ['label'=>'Fichas Abertas', 'value'=>$abertas],
  ['label'=>'Fichas Encerradas', 'value'=>$encerradas],
]);

// OS por situação (tabela + barras)
$html .= '<h2>Ordens de Serviço — distribuição</h2>';
$html .= '<table><thead><tr><th>Status</th><th>Total</th><th>%</th></tr></thead><tbody>';
$osLinhas = [
  ['s'=>'Em andamento/Aguardando', 'v'=>$em_andamento],
  ['s'=>'Concluídas', 'v'=>$concluidas],
  ['s'=>'Outras', 'v'=>$outras_os],
];
foreach ($osLinhas as $l) {
  $p = pctNum($l['v'], $total_os);
  $html .= '<tr>
    <td>'.htmlspecialchars($l['s']).'</td>
    <td>'.(int)$l['v'].'</td>
    <td>'.$p.'%'.bar($p).'</td>
  </tr>';
}
$html .= '</tbody></table>';

// Disponibilidade geral
$totalGeral = $total_disp_geral + $total_indisp_geral;
$pDisp = pctNum($total_disp_geral, $totalGeral);
$pInd = pctNum($total_indisp_geral, $totalGeral);

$html .= '<h2>Disponibilidade — visão geral</h2>';
$html .= '<table><thead><tr><th>Item</th><th>Total</th><th>%</th></tr></thead><tbody>
  <tr><td>Disponíveis</td><td>'.(int)$total_disp_geral.'</td><td>'.$pDisp.'%'.bar($pDisp).'</td></tr>
  <tr><td>Indisponíveis</td><td>'.(int)$total_indisp_geral.'</td><td>'.$pInd.'%'.bar($pInd).'</td></tr>
</tbody></table>';

// Top destinos indisponíveis
$html .= '<h2>Top destinos com indisponíveis</h2>';
if (!empty($top_destinos_indisp)) {
  $html .= '<table><thead><tr><th>Destino</th><th>Indisponíveis</th></tr></thead><tbody>';
  foreach ($top_destinos_indisp as $it) {
    $html .= '<tr><td>'.htmlspecialchars($it['destino']).'</td><td>'.(int)$it['total'].'</td></tr>';
  }
  $html .= '</tbody></table>';
} else {
  $html .= '<p class="muted">Sem dados para os filtros atuais.</p>';
}

// Destinos VTR / EQP (tabelas)
$html .= tabelaKeyValue('Viaturas por destino', $destinos_viaturas);
$html .= tabelaKeyValue('Equipamentos por destino', $destinos_equipamentos);

$html .= '<footer>Gerado automaticamente | Sistema de Gestão da Cia E Eqp Mnt</footer>';
$html .= '</body></html>';

// =========================
// PDF
// =========================
$dompdf = new Dompdf(['enable_remote' => true]);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

$nome = "Relatorio_Frota_Resumo_" . date('Y-m-d_H-i') . ".pdf";
$dompdf->stream($nome, ['Attachment' => false]);
exit;