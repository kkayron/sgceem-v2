<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_start();

require_once '../conexao/config.php';
require_once 'vendor/autoload.php';

use Dompdf\Dompdf;

if (!isset($_SESSION['usuario_id'])) {
  echo 'Sessão expirada. Faça login novamente.';
  exit;
}

// sessão
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
   FILTROS RECEBIDOS (OM + FROTA)
=========================== */
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

/* ===========================
   WHERE OM
=========================== */
if ($filtro_om === 'todos') {
  $ids = array_keys($oms_visiveis);
  $where_om = !empty($ids) ? "batalhao IN (" . implode(',', array_map('intval', $ids)) . ")" : "1=0";
} else {
  $where_om = "batalhao = " . (int)$filtro_om;
}

/* ===========================
   WHERE FROTA (com filtros)
   - Preventiva: sempre Vtr/Eqp
=========================== */
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

/* ===========================
   Preventiva (cálculo)
=========================== */
include_once('../includes/preventiva/calculo_manutencao.php');

/**
 * Buscar frota já filtrada
 */
$sql_frota = "
  SELECT id, tipo, prefixo_sga, nome_sioc, status_odometro
  FROM frota
  WHERE $whereFrotaSql
";
$stmtF = $conexao->prepare($sql_frota);
if ($paramsFrota) $stmtF->bind_param($typesFrota, ...$paramsFrota);
$stmtF->execute();
$result_frota = $stmtF->get_result();

$frota = [];
if ($result_frota && $result_frota->num_rows > 0) {
  while ($row = $result_frota->fetch_assoc()) {
    $row['id'] = (int)$row['id'];
    $frota[] = $row;
  }
}
$stmtF->close();

/**
 * Contagem
 */
$contagem = [
  'Vtr' => ['Manutenção em dia'=>0,'Muito próxima'=>0,'Próxima'=>0,'Manutenção vencida'=>0,'Em manutenção'=>0,'Sem dados'=>0,'Total'=>0],
  'Eqp' => ['Manutenção em dia'=>0,'Muito próxima'=>0,'Próxima'=>0,'Manutenção vencida'=>0,'Em manutenção'=>0,'Sem dados'=>0,'Total'=>0]
];

// Lista detalhada
$detalhes = [
  'Vtr' => [],
  'Eqp' => []
];

if (!empty($frota)) {

  if (function_exists('calcularManutencaoPreventivaBulk')) {
    $map = calcularManutencaoPreventivaBulk($conexao, $frota);

    foreach ($frota as $item) {
      $tipoBruto = strtolower(trim($item['tipo']));
      $tipo = in_array($tipoBruto, ['vtr','viatura','veiculo'], true) ? 'Vtr' : 'Eqp';

      $id = (int)$item['id'];
      $status = $map[$id]['status'] ?? 'Sem dados';

      if (!isset($contagem[$tipo][$status])) $status = 'Sem dados';
      $contagem[$tipo][$status]++;
      $contagem[$tipo]['Total']++;

      $detalhes[$tipo][] = [
        'id' => $id,
        'prefixo' => $item['prefixo_sga'] ?? '-',
        'nome' => $item['nome_sioc'] ?? '-',
        'status' => $status,
      ];
    }

  } else {

    $cache = [];
    foreach ($frota as $item) {
      $tipoBruto = strtolower(trim($item['tipo']));
      $tipo = in_array($tipoBruto, ['vtr','viatura','veiculo'], true) ? 'Vtr' : 'Eqp';

      $id = (int)$item['id'];

      if (!isset($cache[$id])) {
        $cache[$id] = calcularManutencaoPreventiva($conexao, $item);
      }

      $status = $cache[$id]['status'] ?? 'Sem dados';
      if (!isset($contagem[$tipo][$status])) $status = 'Sem dados';

      $contagem[$tipo][$status]++;
      $contagem[$tipo]['Total']++;

      $detalhes[$tipo][] = [
        'id' => $id,
        'prefixo' => $item['prefixo_sga'] ?? '-',
        'nome' => $item['nome_sioc'] ?? '-',
        'status' => $status,
      ];
    }
  }
}

/* ===========================
   Helpers PDF
=========================== */
function nomeOM($filtro_om, $oms_visiveis) {
  if ($filtro_om === 'todos') return 'Todos';
  $id = (int)$filtro_om;
  return $oms_visiveis[$id] ?? ('OM ID ' . $id);
}

function tabelaResumo($titulo, $arr) {
  $ordem = ['Manutenção em dia','Muito próxima','Próxima','Em manutenção','Sem dados','Manutenção vencida','Total'];
  $html = "<h3>{$titulo}</h3><table><thead><tr><th>Situação</th><th>Qtd</th></tr></thead><tbody>";
  foreach ($ordem as $k) {
    $v = (int)($arr[$k] ?? 0);
    $html .= "<tr><td>".htmlspecialchars($k)."</td><td style='text-align:right;'>{$v}</td></tr>";
  }
  $html .= "</tbody></table>";
  return $html;
}

function tabelaDetalhes($titulo, $linhas) {
  $html = "<h3>{$titulo}</h3>";
  if (empty($linhas)) {
    return $html . "<p style='color:#777;'>Nenhum item encontrado.</p>";
  }

  usort($linhas, function($a,$b){
    $c = strcmp((string)$a['status'], (string)$b['status']);
    if ($c !== 0) return $c;
    return strcmp((string)$a['prefixo'], (string)$b['prefixo']);
  });

  $html .= "<table><thead><tr>
              <th>ID</th><th>Prefixo</th><th>Nome</th><th>Status Preventiva</th>
            </tr></thead><tbody>";
  foreach ($linhas as $it) {
    $html .= "<tr>
      <td>".(int)$it['id']."</td>
      <td>".htmlspecialchars((string)$it['prefixo'])."</td>
      <td>".htmlspecialchars((string)$it['nome'])."</td>
      <td>".htmlspecialchars((string)$it['status'])."</td>
    </tr>";
  }
  $html .= "</tbody></table>";
  return $html;
}

function labelFiltro($label, $valor) {
  $valor = trim((string)$valor);
  if ($valor === '') return '';
  return "<span class='badge'><strong>{$label}:</strong> ".htmlspecialchars($valor)."</span>";
}

/* ===========================
   HTML DO PDF
=========================== */
$omNome = nomeOM($filtro_om, $oms_visiveis);

// montar string amigável de filtros
$badgesFiltros = '';
$badgesFiltros .= labelFiltro('Prefixo', $filtro_prefixo);
$badgesFiltros .= labelFiltro('Tipo', $filtro_tipo);
$badgesFiltros .= labelFiltro('Ativo', $filtro_ativo);
$badgesFiltros .= labelFiltro('Acervo', $filtro_acervo);
$badgesFiltros .= labelFiltro('Marca', $filtro_marca);
$badgesFiltros .= labelFiltro('Confiabilidade', $filtro_confiabilidade);
$badgesFiltros .= labelFiltro('Disponibilidade', $filtro_disponibilidade);
$badgesFiltros .= labelFiltro('Destino', $filtro_destino);

$temFiltrosFrota = ($filtro_prefixo.$filtro_tipo.$filtro_ativo.$filtro_acervo.$filtro_marca.$filtro_confiabilidade.$filtro_disponibilidade.$filtro_destino) !== '';

$html = '<!DOCTYPE html><html><head><meta charset="UTF-8"><style>
body { font-family:"Segoe UI", Roboto, Arial, sans-serif; color:#333; margin:18px; font-size:12px; }
header { text-align:center; border-bottom:2px solid #0d6efd; padding-bottom:10px; margin-bottom:16px; }
header img { width:70px; margin-bottom:4px; }
header h1 { margin:0; font-size:18px; color:#0d6efd; }
header p { margin:2px 0; font-size:12px; color:#555; }
.meta { margin:10px 0 14px; padding:10px; border:1px solid #eee; border-radius:8px; background:#fafafa; }
h2 { color:#0d6efd; margin:10px 0 6px; border-bottom:1px solid #ddd; padding-bottom:3px; font-size:14px;}
h3 { color:#0d6efd; margin:12px 0 6px; font-size:13px; }
table { width:100%; border-collapse:collapse; margin-top:6px; }
th, td { border:1px solid #dee2e6; padding:6px; text-align:left; vertical-align:top; }
th { background:#0d6efd; color:#fff; font-size:12px; }
td { font-size:11px; }
.badges { margin-top:6px; }
.badge { display:inline-block; padding:4px 8px; border-radius:999px; font-size:11px; margin-right:6px; margin-top:6px; border:1px solid #ddd; background:#fff; }
footer { position:fixed; bottom:10px; left:0; right:0; text-align:center; font-size:10px; color:#666; border-top:1px solid #ddd; padding-top:5px; }
.small { font-size:10px; color:#666; }
</style></head><body>';

$html .= '<header>
  <img src="https://gceem.22web.org/uploads/logo_exercito.png" alt="Logo">
  <h1>Relatório — Manutenção Preventiva</h1>
  <p>Sistema de Gerenciamento da Cia E Eqp Mnt</p>
</header>';

$html .= '<div class="meta">
  <div><strong>OM:</strong> '.htmlspecialchars($omNome).'</div>
  <div class="badges">
    <span class="badge"><strong>Viaturas:</strong> '.(int)$contagem['Vtr']['Total'].'</span>
    <span class="badge"><strong>Equipamentos:</strong> '.(int)$contagem['Eqp']['Total'].'</span>
    <span class="badge"><strong>Total itens:</strong> '.((int)$contagem['Vtr']['Total'] + (int)$contagem['Eqp']['Total']).'</span>
  </div>';

if ($temFiltrosFrota) {
  $html .= '<div class="badges" style="margin-top:4px;">
    <div class="small"><strong>Filtros da frota aplicados:</strong></div>
    '.$badgesFiltros.'
  </div>';
} else {
  $html .= '<div class="small" style="margin-top:6px;">
    Filtros aplicados: apenas OM.
  </div>';
}

$html .= '</div>';

$html .= '<h2>Resumo por situação</h2>';
$html .= tabelaResumo('Viaturas', $contagem['Vtr']);
$html .= '<div style="height:10px;"></div>';
$html .= tabelaResumo('Equipamentos', $contagem['Eqp']);

$html .= '<h2>Detalhamento (itens)</h2>';
$html .= tabelaDetalhes('Viaturas', $detalhes['Vtr']);
$html .= '<div style="height:10px;"></div>';
$html .= tabelaDetalhes('Equipamentos', $detalhes['Eqp']);

$html .= '<footer>Gerado automaticamente em '.date("d/m/Y H:i").' | Sistema de Gestão da Cia E Eqp Mnt</footer>';
$html .= '</body></html>';

// PDF
$dompdf = new Dompdf(['enable_remote' => true]);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

$nomeArquivo = "Dashboard_Preventiva_" . preg_replace('/[^a-zA-Z0-9_\-]/', '_', $omNome) . "_" . date('Ymd_His') . ".pdf";
$dompdf->stream($nomeArquivo, ['Attachment'=>false]);
exit;