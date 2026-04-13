<?php
session_start();

require_once '../api/seguranca.php';

$permissoes = verificarPermissao(57);

$pode_cadastrar = $permissoes['cadastrar'];
$pode_editar    = $permissoes['editar'];
$pode_deletar   = $permissoes['deletar'];
$pode_importar  = $permissoes['importar'];


if (!isset($_SESSION['usuario_id'])) {
  http_response_code(401);
  echo "<div class='alert alert-danger'>Sessão expirada. Faça login novamente.</div>";
  exit;
}

include_once('../../conexao/config.php');
mysqli_set_charset($conexao, "utf8mb4");

// =========================
// SESSÃO
// =========================
$id_om_usuario = (int)($_SESSION['usuario']['batalhao'] ?? 0);
$nivel_usuario = (int)($_SESSION['usuario']['nivel'] ?? 3);

// =========================
// OMs VISÍVEIS (com abreviatura)
/// =========================
$oms_visiveis = [];
if ($nivel_usuario === 1) {
  $sql_oms = "SELECT id, nome, abreviatura FROM organizacoes_militares ORDER BY nome";
} elseif ($nivel_usuario === 2) {
  $sql_oms = "
    SELECT om.id, om.nome, om.abreviatura
    FROM organizacoes_militares om
    JOIN organizacoes_militares_sub sub ON om.id = sub.id_om_menor
    WHERE sub.id_om_maior = $id_om_usuario
    UNION
    SELECT id, nome, abreviatura FROM organizacoes_militares WHERE id = $id_om_usuario
    ORDER BY nome
  ";
} else {
  $sql_oms = "SELECT id, nome, abreviatura FROM organizacoes_militares WHERE id = $id_om_usuario";
}
$res = $conexao->query($sql_oms);
while ($r = $res->fetch_assoc()) {
  $id = (int)$r['id'];
  $nome = trim((string)$r['nome']);
  $abrev = trim((string)($r['abreviatura'] ?? ''));
  $oms_visiveis[$id] = $abrev ? ($abrev . " — " . $nome) : $nome;
}

$ids_om_visiveis = array_keys($oms_visiveis);
$inOms = !empty($ids_om_visiveis) ? implode(',', array_map('intval', $ids_om_visiveis)) : '0';

// =========================
// FILTROS (GET)
// =========================
$filtro_om      = $_GET['batalhao'] ?? 'todos';
$filtro_periodo = (int)($_GET['periodo'] ?? 30);
if (!in_array($filtro_periodo, [30, 90, 180], true)) $filtro_periodo = 30;

$filtro_id_snapshot     = $_GET['id'] ?? '';
$filtro_data_ref        = $_GET['data_ref'] ?? '';
$filtro_id_frota        = $_GET['id_frota'] ?? '';
$filtro_tipo            = $_GET['tipo'] ?? '';
$filtro_ativo           = $_GET['ativo'] ?? '';
$filtro_acervo          = $_GET['acervo'] ?? '';
$filtro_marca           = $_GET['marca'] ?? ''; // esperado: ID config_marcas.id (se sua snapshot guarda ID)
$filtro_confiabilidade  = $_GET['confiabilidade'] ?? '';
$filtro_disponibilidade = $_GET['disponibilidade'] ?? '';
$filtro_destino         = $_GET['destino'] ?? '';

// datas
$data_fim = date('Y-m-d');
$data_ini = date('Y-m-d', strtotime("-" . ($filtro_periodo - 1) . " days"));

// =========================
// HELPERS
// =========================
function brDate($d){
  if (!$d) return '';
  $p = explode('-', $d);
  return (count($p)===3) ? ($p[2].'/'.$p[1].'/'.$p[0]) : $d;
}
function pct($parte, $total){
  return ($total > 0) ? round(($parte / $total) * 100, 2) : 0;
}

// =========================
// SELECTS: opções automáticas
// =========================
function loadDistinct($conexao, $col, $inOms, $limit = 0, $order = 'ASC'){
  $vals = [];
  $order = strtoupper($order) === 'DESC' ? 'DESC' : 'ASC';
  $lim = ($limit > 0) ? "LIMIT " . (int)$limit : "";

  $sql = "
    SELECT DISTINCT $col AS v
    FROM frota_snapshot_diario
    WHERE om_id IN ($inOms)
      AND $col IS NOT NULL
      AND TRIM($col) <> ''
    ORDER BY v $order
    $lim
  ";
  $r = $conexao->query($sql);
  if ($r) {
    while ($row = $r->fetch_assoc()) {
      $v = (string)$row['v'];
      if ($v !== '') $vals[] = $v;
    }
  }
  return $vals;
}

// ⚠️ id pode crescer muito — pego só os últimos 200
$opts_id_snapshot     = loadDistinct($conexao, 'id', $inOms, 200, 'DESC');
$opts_data_ref        = loadDistinct($conexao, 'data_ref', $inOms, 0, 'DESC');
$opts_tipo            = loadDistinct($conexao, 'tipo', $inOms);
$opts_ativo           = loadDistinct($conexao, 'ativo', $inOms);
$opts_acervo          = loadDistinct($conexao, 'acervo', $inOms);
$opts_confiabilidade  = loadDistinct($conexao, 'confiabilidade', $inOms);
$opts_disponibilidade = loadDistinct($conexao, 'disponibilidade', $inOms);
$opts_destino         = loadDistinct($conexao, 'destino', $inOms);

// marcas (config_marcas)
$marcas = [];
$rMar = $conexao->query("SELECT id, marca FROM config_marcas ORDER BY marca");
if ($rMar) {
  while ($m = $rMar->fetch_assoc()) {
    $marcas[(int)$m['id']] = (string)$m['marca'];
  }
}

// frota (id => prefixo_sga) filtrado pelas OMs visíveis
$frotas = [];
$rF = $conexao->query("SELECT id, prefixo_sga FROM frota WHERE batalhao IN ($inOms) ORDER BY prefixo_sga");
if ($rF) {
  while ($f = $rF->fetch_assoc()) {
    $frotas[(int)$f['id']] = (string)$f['prefixo_sga'];
  }
}

// =========================
// WHERE + PARAMS (prepared)
// =========================
$where = [];
$params = [];
$types  = '';

if ($filtro_om === 'todos') {
  if (empty($ids_om_visiveis)) {
    echo "<div class='alert alert-warning'>Nenhuma OM visível para o seu usuário.</div>";
    exit;
  }
  $where[] = "om_id IN (" . implode(',', array_map('intval', $ids_om_visiveis)) . ")";
} else {
  $om = (int)$filtro_om;
  if (!isset($oms_visiveis[$om])) {
    http_response_code(403);
    echo "<div class='alert alert-danger'>OM não permitida.</div>";
    exit;
  }
  $where[] = "om_id = " . $om;
}

$where[] = "data_ref BETWEEN ? AND ?";
$params[] = $data_ini; $types .= 's';
$params[] = $data_fim; $types .= 's';

if ($filtro_id_snapshot !== '' && ctype_digit((string)$filtro_id_snapshot)) {
  $where[] = "id = ?";
  $params[] = (int)$filtro_id_snapshot; $types .= 'i';
}

if ($filtro_data_ref !== '') {
  $where[] = "data_ref = ?";
  $params[] = (string)$filtro_data_ref; $types .= 's';
}

if ($filtro_id_frota !== '' && ctype_digit((string)$filtro_id_frota)) {
  $where[] = "id_frota = ?";
  $params[] = (int)$filtro_id_frota; $types .= 'i';
}

if ($filtro_tipo !== '') { $where[] = "tipo = ?"; $params[] = (string)$filtro_tipo; $types .= 's'; }
if ($filtro_ativo !== '') { $where[] = "ativo = ?"; $params[] = (string)$filtro_ativo; $types .= 's'; }
if ($filtro_acervo !== '') { $where[] = "acervo = ?"; $params[] = (string)$filtro_acervo; $types .= 's'; }

// marca: se sua snapshot guarda ID (int), bind como i; se guarda texto, bind como s
if ($filtro_marca !== '') {
  if (ctype_digit((string)$filtro_marca)) {
    $where[] = "marca = ?";
    $params[] = (int)$filtro_marca; $types .= 'i';
  } else {
    $where[] = "marca = ?";
    $params[] = (string)$filtro_marca; $types .= 's';
  }
}

if ($filtro_confiabilidade !== '') { $where[] = "confiabilidade = ?"; $params[] = (string)$filtro_confiabilidade; $types .= 's'; }
if ($filtro_disponibilidade !== '') { $where[] = "disponibilidade = ?"; $params[] = (string)$filtro_disponibilidade; $types .= 's'; }
if ($filtro_destino !== '') { $where[] = "destino = ?"; $params[] = (string)$filtro_destino; $types .= 's'; }

$whereSql = implode(" AND ", $where);

// =========================
// QUERY AGREGADA POR DIA (3 status)
// =========================
$sql = "
SELECT
  data_ref,

  SUM(CASE WHEN disponibilidade = 'Disponível' THEN 1 ELSE 0 END) AS qt_disponivel,
  SUM(CASE WHEN disponibilidade = 'Disponível com restrição' THEN 1 ELSE 0 END) AS qt_restricao,
  SUM(CASE WHEN disponibilidade NOT IN ('Disponível', 'Disponível com restrição') THEN 1 ELSE 0 END) AS qt_indisponivel,

  COUNT(*) AS total
FROM frota_snapshot_diario
WHERE $whereSql
GROUP BY data_ref
ORDER BY data_ref DESC
";

$stmt = $conexao->prepare($sql);
if (!$stmt) {
  echo "<div class='alert alert-danger'>Erro prepare: ".htmlspecialchars($conexao->error)."</div>";
  exit;
}
if ($types !== '') $stmt->bind_param($types, ...$params);
$stmt->execute();
$res = $stmt->get_result();

$rows = [];
while ($row = $res->fetch_assoc()) {
  $tot  = (int)$row['total'];
  $disp = (int)$row['qt_disponivel'];
  $rest = (int)$row['qt_restricao'];
  $ind  = (int)$row['qt_indisponivel'];

  $rows[] = [
    'data_ref' => (string)$row['data_ref'],
    'tot'      => $tot,
    'disp'     => $disp,
    'rest'     => $rest,
    'ind'      => $ind,
    'p_disp'   => pct($disp, $tot),
    'p_rest'   => pct($rest, $tot),
    'p_ind'    => pct($ind, $tot),
  ];
}
$stmt->close();

// =========================
// RESUMO
// =========================
$qtDiasComRegistro = count($rows);
$somaTot = 0; $somaDisp = 0; $somaRest = 0; $somaInd = 0;

foreach ($rows as $r) {
  $somaTot  += $r['tot'];
  $somaDisp += $r['disp'];
  $somaRest += $r['rest'];
  $somaInd  += $r['ind'];
}

$idxMedioDisp = ($somaTot > 0) ? round(($somaDisp / $somaTot) * 100, 2) : 0;
$idxMedioRest = ($somaTot > 0) ? round(($somaRest / $somaTot) * 100, 2) : 0;
$idxMedioInd  = ($somaTot > 0) ? round(($somaInd  / $somaTot) * 100, 2) : 0;

// evolução (Disponível)
$idxUltimoDia = null; $idxPrimeiroDia = null;
$dataUltimoDia = null; $dataPrimeiroDia = null;

if (!empty($rows)) {
  $idxUltimoDia  = (float)$rows[0]['p_disp'];
  $dataUltimoDia = (string)$rows[0]['data_ref'];
  $last = $rows[count($rows)-1];
  $idxPrimeiroDia  = (float)$last['p_disp'];
  $dataPrimeiroDia = (string)$last['data_ref'];
}

$deltaPP = null;
$seta = '';
$classe = 'text-muted';
$textoEvolucao = 'Sem dados suficientes.';

if ($idxUltimoDia !== null && $idxPrimeiroDia !== null) {
  $deltaPP = round($idxUltimoDia - $idxPrimeiroDia, 2);

  if ($deltaPP > 0.01) { $seta = '↑'; $classe = 'text-success'; }
  elseif ($deltaPP < -0.01) { $seta = '↓'; $classe = 'text-danger'; }
  else { $seta = '→'; $classe = 'text-secondary'; }

  $textoEvolucao = sprintf(
    "%s Evolução (Disponível): %s%% (%s) → %s%% (%s) | Variação: %s p.p.",
    $seta,
    number_format($idxPrimeiroDia, 2, ',', '.'),
    brDate($dataPrimeiroDia),
    number_format($idxUltimoDia, 2, ',', '.'),
    brDate($dataUltimoDia),
    number_format($deltaPP, 2, ',', '.')
  );
}

// payload gráfico
$labelsGraf = [];
$serieDisp  = [];
$serieInd   = [];
$serieRest  = [];

if (!empty($rows)) {
  $rowsAsc = array_reverse($rows);
  foreach ($rowsAsc as $r) {
    $labelsGraf[] = brDate($r['data_ref']);
    $serieDisp[]  = (float)$r['p_disp'];
    $serieInd[]   = (float)$r['p_ind'];
    $serieRest[]  = (float)$r['p_rest'];
  }
}

$chartPayload = [
  'labels' => $labelsGraf,
  'datasets' => [
    ['label' => 'Disponível (%)', 'values' => $serieDisp],
    ['label' => 'Indisponível (%)', 'values' => $serieInd],
    ['label' => 'Disp. com restrição (%)', 'values' => $serieRest],
  ]
];

$alertClass = 'alert-secondary';
if ($classe === 'text-success') $alertClass = 'alert-success';
if ($classe === 'text-danger')  $alertClass = 'alert-danger';
?>

<div data-bloco="historico_disponibilidade">

  <!-- ✅ AÇÕES (padrão bloco_financeiro) -->
  <div class="d-flex flex-wrap gap-2 justify-content-end mb-2">
    <?php if ($nivel_usuario === 1): ?>
      <button id="btnGerarSnapshot" class="btn btn-success btn-sm" type="button">
        <i class="fas fa-database me-1"></i> Registrar Histórico Atual
      </button>
    <?php endif; ?>
  </div>

  <!-- ✅ FILTROS (COLLAPSE estilo modelo) -->
  <div class="card shadow-sm border-0 mb-3">
    <div class="card-body">

      <div class="d-flex justify-content-between align-items-center">
        <div class="fw-bold">
          <i class="fas fa-filter me-1 text-primary"></i> Filtros
        </div>
        <button class="btn btn-outline-primary btn-sm" type="button"
                data-bs-toggle="collapse" data-bs-target="#collapseFiltrosHistorico"
                aria-expanded="false" aria-controls="collapseFiltrosHistorico">
          <i class="fas fa-sliders-h me-1"></i> Mostrar/ocultar
        </button>
      </div>

      <div class="collapse mt-3" id="collapseFiltrosHistorico">
        <form id="filtroHistoricoDispForm" class="row g-2 align-items-end" onsubmit="return false;">

          <!-- OM -->
          <div class="col-12 col-md-4">
            <label class="form-label fw-bold">OM</label>
            <select name="batalhao" class="form-select">
              <option value="todos" <?= ($filtro_om === 'todos') ? 'selected' : '' ?>>Todas (visíveis)</option>
              <?php foreach ($oms_visiveis as $id => $label): ?>
                <option value="<?= (int)$id ?>" <?= ((string)$id === (string)$filtro_om) ? 'selected' : '' ?>>
                  <?= htmlspecialchars($label) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <!-- Período -->
          <div class="col-12 col-md-2">
            <label class="form-label fw-bold">Período</label>
            <select name="periodo" class="form-select">
              <option value="30"  <?= $filtro_periodo===30?'selected':'' ?>>30 dias</option>
              <option value="90"  <?= $filtro_periodo===90?'selected':'' ?>>90 dias</option>
              <option value="180" <?= $filtro_periodo===180?'selected':'' ?>>180 dias</option>
            </select>
          </div>

          <!-- data_ref -->
          <div class="col-12 col-md-3">
            <label class="form-label fw-bold">Data do histórico</label>
            <select name="data_ref" class="form-select">
              <option value="">Todas</option>
              <?php foreach ($opts_data_ref as $v): ?>
                <option value="<?= htmlspecialchars($v) ?>" <?= ($filtro_data_ref === $v) ? 'selected' : '' ?>>
                  <?= htmlspecialchars($v) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <!-- id snapshot -->
          <div class="col-12 col-md-3">
            <label class="form-label fw-bold">ID snapshot</label>
            <select name="id" class="form-select">
              <option value="">Todos</option>
              <?php foreach ($opts_id_snapshot as $v): ?>
                <option value="<?= htmlspecialchars($v) ?>" <?= ((string)$filtro_id_snapshot === (string)$v) ? 'selected' : '' ?>>
                  <?= htmlspecialchars($v) ?>
                </option>
              <?php endforeach; ?>
            </select>
            <div class="small text-muted">Mostrando últimos 200</div>
          </div>

          <!-- frota -->
          <div class="col-12 col-md-4">
            <label class="form-label fw-bold">Frota (prefixo SGA)</label>
            <select name="id_frota" class="form-select">
              <option value="">Todas</option>
              <?php foreach ($frotas as $id => $pref): ?>
                <option value="<?= (int)$id ?>" <?= ((string)$filtro_id_frota === (string)$id) ? 'selected' : '' ?>>
                  <?= htmlspecialchars($pref) ?> (ID <?= (int)$id ?>)
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <!-- marca -->
          <div class="col-12 col-md-4">
            <label class="form-label fw-bold">Marca</label>
            <select name="marca" class="form-select">
              <option value="">Todas</option>
              <?php foreach ($marcas as $id => $nome): ?>
                <option value="<?= (int)$id ?>" <?= ((string)$filtro_marca === (string)$id) ? 'selected' : '' ?>>
                  <?= htmlspecialchars($nome) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <!-- tipo -->
          <div class="col-12 col-md-4">
            <label class="form-label fw-bold">Tipo</label>
            <select name="tipo" class="form-select">
              <option value="">Todos</option>
              <?php foreach ($opts_tipo as $v): ?>
                <option value="<?= htmlspecialchars($v) ?>" <?= ($filtro_tipo === $v) ? 'selected' : '' ?>>
                  <?= htmlspecialchars($v) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <!-- ativo -->
          <div class="col-12 col-md-3">
            <label class="form-label fw-bold">Ativo</label>
            <select name="ativo" class="form-select">
              <option value="">Todos</option>
              <?php foreach ($opts_ativo as $v): ?>
                <option value="<?= htmlspecialchars($v) ?>" <?= ($filtro_ativo === $v) ? 'selected' : '' ?>>
                  <?= htmlspecialchars($v) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <!-- acervo -->
          <div class="col-12 col-md-3">
            <label class="form-label fw-bold">Acervo</label>
            <select name="acervo" class="form-select">
              <option value="">Todos</option>
              <?php foreach ($opts_acervo as $v): ?>
                <option value="<?= htmlspecialchars($v) ?>" <?= ($filtro_acervo === $v) ? 'selected' : '' ?>>
                  <?= htmlspecialchars($v) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <!-- confiabilidade -->
          <div class="col-12 col-md-3">
            <label class="form-label fw-bold">Confiabilidade</label>
            <select name="confiabilidade" class="form-select">
              <option value="">Todas</option>
              <?php foreach ($opts_confiabilidade as $v): ?>
                <option value="<?= htmlspecialchars($v) ?>" <?= ($filtro_confiabilidade === $v) ? 'selected' : '' ?>>
                  <?= htmlspecialchars($v) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <!-- disponibilidade -->
          <div class="col-12 col-md-3">
            <label class="form-label fw-bold">Disponibilidade</label>
            <select name="disponibilidade" class="form-select">
              <option value="">Todas</option>
              <?php foreach ($opts_disponibilidade as $v): ?>
                <option value="<?= htmlspecialchars($v) ?>" <?= ($filtro_disponibilidade === $v) ? 'selected' : '' ?>>
                  <?= htmlspecialchars($v) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <!-- destino -->
          <div class="col-12 col-md-4">
            <label class="form-label fw-bold">Destino</label>
            <select name="destino" class="form-select">
              <option value="">Todos</option>
              <?php foreach ($opts_destino as $v): ?>
                <option value="<?= htmlspecialchars($v) ?>" <?= ($filtro_destino === $v) ? 'selected' : '' ?>>
                  <?= htmlspecialchars($v) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <!-- ações -->
          <div class="col-12 col-md-4 d-flex align-items-end gap-2">
            <button type="button" class="btn btn-primary w-100"
              onclick="(function(){
                const f=document.getElementById('filtroHistoricoDispForm');
                f.dispatchEvent(new Event('submit', {cancelable:true, bubbles:true}));
              })();">
              <i class="fas fa-filter me-1"></i> Aplicar
            </button>

            <button type="button" class="btn btn-outline-secondary w-100"
              onclick="(function(){
                const f=document.getElementById('filtroHistoricoDispForm');
                // reseta selects (menos batalhao e periodo)
                f.querySelectorAll('select').forEach(sel=>{
                  if(sel.name!=='batalhao' && sel.name!=='periodo') sel.value='';
                });
                f.dispatchEvent(new Event('submit', {cancelable:true, bubbles:true}));
              })();">
              <i class="fas fa-eraser me-1"></i> Limpar
            </button>
          </div>

        </form>
      </div>

      <div class="mt-3 small text-muted">
        <i class="fas fa-info-circle me-1"></i>
        As opções dos filtros são carregadas automaticamente do histórico (DISTINCT) + tabelas auxiliares.
      </div>

    </div>
  </div>

  <!-- ✅ KPI / EVOLUÇÃO / GRÁFICO / TABELA (padrão visual já no seu histórico) -->
  <div class="card shadow-sm border-0 mb-4 overflow-hidden">
    <div class="card-header bg-primary text-white d-flex align-items-center justify-content-between flex-wrap gap-2">
      <div class="d-flex align-items-center">
        <i class="fas fa-history me-2"></i>
        <strong>Histórico de Disponibilidade</strong>
      </div>
      <span class="badge bg-light text-dark">
        <?= brDate($data_ini) ?> → <?= brDate($data_fim) ?> (<?= (int)$filtro_periodo ?> dias)
      </span>
    </div>

    <div class="card-body bg-light">

      <!-- KPIs -->
      <div class="row g-3 mb-3 text-center">
        <div class="col-12 col-md-3">
          <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
              <div class="small text-muted fw-semibold">Dias com registro</div>
              <div class="fs-4 fw-bold"><?= (int)$qtDiasComRegistro ?></div>
              <div class="small text-muted">no período</div>
            </div>
          </div>
        </div>

        <div class="col-12 col-md-3">
          <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
              <div class="small text-muted fw-semibold">Média Disponível</div>
              <div class="fs-4 fw-bold text-success"><?= number_format($idxMedioDisp, 2, ',', '.') ?>%</div>
              <div class="small text-muted">(% no período)</div>
            </div>
          </div>
        </div>

        <div class="col-12 col-md-3">
          <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
              <div class="small text-muted fw-semibold">Média Restrição</div>
              <div class="fs-4 fw-bold text-warning"><?= number_format($idxMedioRest, 2, ',', '.') ?>%</div>
              <div class="small text-muted">(% no período)</div>
            </div>
          </div>
        </div>

        <div class="col-12 col-md-3">
          <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
              <div class="small text-muted fw-semibold">Média Indisponível</div>
              <div class="fs-4 fw-bold text-danger"><?= number_format($idxMedioInd, 2, ',', '.') ?>%</div>
              <div class="small text-muted">(% no período)</div>
            </div>
          </div>
        </div>
      </div>

      <!-- Evolução -->
      <div class="alert <?= $alertClass ?> d-flex align-items-center justify-content-between flex-wrap gap-2 shadow-sm border-0 mb-3">
        <div class="fw-bold">
          <i class="fas fa-chart-line me-2"></i>Comparativo de evolução
        </div>
        <div class="fw-semibold">
          <?= htmlspecialchars($textoEvolucao) ?>
        </div>
      </div>

      <!-- Gráfico -->
      <div class="card shadow-sm border-0 mb-3">
        <div class="card-header bg-white d-flex align-items-center justify-content-between flex-wrap gap-2">
          <div class="fw-bold">
            <i class="fas fa-chart-line me-2 text-primary"></i>Disponibilidade (%) por dia
          </div>
          <div class="small text-muted">3 linhas: Disponível / Indisponível / Restrição</div>
        </div>

        <div class="card-body bg-light">
          <div class="position-relative" style="height: 340px;">
            <canvas id="dispLineChart"></canvas>
          </div>
          <script type="application/json" id="dispLineChartData"><?= json_encode($chartPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?></script>
        </div>
      </div>

      <!-- Tabela -->
      <?php if (empty($rows)): ?>
        <div class="alert alert-warning mb-0 shadow-sm border-0">
          <div class="fw-bold"><i class="fas fa-exclamation-triangle me-1"></i>Nenhum dado encontrado</div>
          <div class="small text-muted mt-1">
            Verifique se a tabela <b>frota_snapshot_diario</b> possui registros no período.
          </div>
        </div>
      <?php else: ?>

        <div class="card shadow-sm border-0">
          <div class="card-header bg-dark text-white d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div class="fw-bold"><i class="fas fa-table me-2"></i>Registros diários</div>
            <div class="small text-white-50">Mais recente → mais antigo</div>
          </div>

          <div class="card-body p-0">
            <div class="table-responsive">
              <table class="table table-sm table-hover align-middle mb-0">
                <thead class="table-light">
                  <tr>
                    <th>Data</th>
                    <th class="text-end">Disp.</th>
                    <th class="text-end">Indisp.</th>
                    <th class="text-end">Restr.</th>
                    <th class="text-end">Total</th>
                    <th class="text-end">% Disp</th>
                    <th class="text-end">% Indisp</th>
                    <th class="text-end">% Restr</th>
                    <?php if ($nivel_usuario === 1): ?>
                      <th class="text-end">Ações</th>
                    <?php endif; ?>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($rows as $r): ?>
                    <tr>
                      <td class="fw-semibold"><?= brDate($r['data_ref']) ?></td>
                      <td class="text-end"><?= (int)$r['disp'] ?></td>
                      <td class="text-end"><?= (int)$r['ind'] ?></td>
                      <td class="text-end"><?= (int)$r['rest'] ?></td>
                      <td class="text-end"><?= (int)$r['tot'] ?></td>

                      <td class="text-end"><span class="badge bg-success"><?= number_format((float)$r['p_disp'], 2, ',', '.') ?>%</span></td>
                      <td class="text-end"><span class="badge bg-danger"><?= number_format((float)$r['p_ind'], 2, ',', '.') ?>%</span></td>
                      <td class="text-end"><span class="badge bg-warning text-dark"><?= number_format((float)$r['p_rest'], 2, ',', '.') ?>%</span></td>

                      <?php if ($nivel_usuario === 1): ?>
                        <td class="text-end">
                          <button
                            type="button"
                            class="btn btn-sm btn-outline-danger btnDelSnapshot"
                            data-date="<?= htmlspecialchars($r['data_ref']) ?>"
                            data-om="<?= ($filtro_om === 'todos' ? '' : (int)$filtro_om) ?>"
                            title="Deletar histórico desta data">
                            <i class="fas fa-trash"></i>
                          </button>
                        </td>
                      <?php endif; ?>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </div>

          <div class="card-footer bg-white small text-muted">
            Fonte: <b>frota_snapshot_diario</b> (agregado por <code>data_ref</code>) — filtros aplicados nas colunas da tabela.
          </div>
        </div>

      <?php endif; ?>

    </div>
  </div>

</div>