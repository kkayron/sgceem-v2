<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
session_start();

if (!isset($_SESSION['usuario_id'])) {
  http_response_code(401);
  echo "<div class='alert alert-danger'>Sessão expirada. Faça login novamente.</div>";
  exit;
}

include_once('../../conexao/config.php');

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
   FILTROS RECEBIDOS
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
=========================== */
$filtrosFrota = [];
$paramsFrota  = [];
$typesFrota   = '';

$filtrosFrota[] = $where_om;

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
   IDs frota filtrada
=========================== */
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

/* ===========================
   HELPERS
=========================== */
function pct($parte, $total) {
  return $total > 0 ? number_format(($parte / $total) * 100, 2, ',', '.') : '0,00';
}
function pct_val($parte, $total) {
  return $total > 0 ? number_format(($parte / $total) * 100, 2, ',', '.') : '0,00';
}

/* ========= OS (GROUP BY) ========= */
$sql_os = "
  SELECT status, COUNT(*) qtd
  FROM os_principal
  WHERE $where_om
    AND id_frota $where_in_frota
  GROUP BY status
";
$res_os = $conexao->query($sql_os);

$total_os = 0;
$em_andamento = 0;
$concluidas = 0;
$outras_os = 0;

if ($res_os) {
  while ($row = $res_os->fetch_assoc()) {
    $q = (int)$row['qtd'];
    $st = (string)$row['status'];
    $total_os += $q;

    if (in_array($st, ['Em andamento', 'Aguardando Peças', 'Aguardando Suprimento', 'Aguardando Descarga'], true)) {
      $em_andamento += $q;
    } elseif (in_array($st, ['Concluída', 'Eqp/Vtr descarregado'], true)) {
      $concluidas += $q;
    } else {
      $outras_os += $q;
    }
  }
}

/* ========= Fichas (GROUP BY) ========= */
$sql_f = "
  SELECT status, COUNT(*) qtd
  FROM sta_fichas
  WHERE $where_om
    AND id_viatura $where_in_frota
  GROUP BY status
";
$res_f = $conexao->query($sql_f);

$total_fichas = 0;
$abertas = 0;
$encerradas = 0;

if ($res_f) {
  while ($row = $res_f->fetch_assoc()) {
    $q = (int)$row['qtd'];
    $st = (string)$row['status'];
    $total_fichas += $q;
    if ($st === 'Aberta') $abertas += $q;
    if ($st === 'Encerrada') $encerradas += $q;
  }
}

/* ========= Totais frota ========= */
$sql_totais = "
SELECT
  COUNT(CASE WHEN tipo = 'Vtr' THEN 1 END) AS total_vtr,
  SUM(CASE WHEN tipo = 'Vtr' AND confiabilidade = 'Confiável' THEN 1 ELSE 0 END) AS confiaveis_vtr,
  SUM(CASE WHEN tipo = 'Vtr' AND confiabilidade = 'Não confiável' THEN 1 ELSE 0 END) AS nao_confiaveis_vtr,
  SUM(CASE WHEN tipo = 'Vtr' AND disponibilidade = 'Disponível' THEN 1 ELSE 0 END) AS disponiveis_vtr,
  SUM(CASE WHEN tipo = 'Vtr' AND disponibilidade != 'Disponível' THEN 1 ELSE 0 END) AS indisp_vtr,
  SUM(CASE WHEN tipo = 'Vtr' AND confiabilidade = 'Emprestado' THEN 1 ELSE 0 END) AS emprestadas_vtr,
  SUM(CASE WHEN tipo = 'Vtr' AND confiabilidade = 'Em processo de descarga' THEN 1 ELSE 0 END) AS em_descarga_vtr,
  SUM(CASE WHEN tipo = 'Vtr' AND confiabilidade = 'Desfeita (Leiloada ou recolhida)' THEN 1 ELSE 0 END) AS desfeitas_vtr,
  SUM(CASE WHEN tipo = 'Vtr' AND confiabilidade = 'Descarregado' THEN 1 ELSE 0 END) AS descarregadas_vtr,

  COUNT(CASE WHEN tipo = 'Eqp' THEN 1 END) AS total_eqp,
  SUM(CASE WHEN tipo = 'Eqp' AND confiabilidade = 'Confiável' THEN 1 ELSE 0 END) AS confiaveis_eqp,
  SUM(CASE WHEN tipo = 'Eqp' AND confiabilidade = 'Não confiável' THEN 1 ELSE 0 END) AS nao_confiaveis_eqp,
  SUM(CASE WHEN tipo = 'Eqp' AND disponibilidade = 'Disponível' THEN 1 ELSE 0 END) AS disponiveis_eqp,
  SUM(CASE WHEN tipo = 'Eqp' AND disponibilidade != 'Disponível' THEN 1 ELSE 0 END) AS indisp_eqp,
  SUM(CASE WHEN tipo = 'Eqp' AND confiabilidade = 'Emprestado' THEN 1 ELSE 0 END) AS emprestadas_eqp,
  SUM(CASE WHEN tipo = 'Eqp' AND confiabilidade = 'Em processo de descarga' THEN 1 ELSE 0 END) AS em_descarga_eqp,
  SUM(CASE WHEN tipo = 'Eqp' AND confiabilidade = 'Desfeita (Leiloada ou recolhida)' THEN 1 ELSE 0 END) AS desfeitas_eqp,
  SUM(CASE WHEN tipo = 'Eqp' AND confiabilidade = 'Descarregado' THEN 1 ELSE 0 END) AS descarregadas_eqp
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

$desfeitas = (int)$totais['desfeitas_vtr'] + (int)$totais['desfeitas_eqp'];
$descarregadas = (int)$totais['descarregadas_vtr'] + (int)$totais['descarregadas_eqp'];
$em_descarga = (int)$totais['em_descarga_vtr'] + (int)$totais['em_descarga_eqp'];
$emprestadas = (int)$totais['emprestadas_vtr'] + (int)$totais['emprestadas_eqp'];

$confiaveis_vtr = (int)$totais['confiaveis_vtr'];
$confiaveis_eqp = (int)$totais['confiaveis_eqp'];
$nao_confiaveis_vtr = (int)$totais['nao_confiaveis_vtr'];
$nao_confiaveis_eqp = (int)$totais['nao_confiaveis_eqp'];

$disponiveis_vtr = (int)$totais['disponiveis_vtr'];
$indisp_vtr = (int)$totais['indisp_vtr'];
$disponiveis_eqp = (int)$totais['disponiveis_eqp'];
$indisp_eqp = (int)$totais['indisp_eqp'];

$total_vtr_considered = $confiaveis_vtr + $nao_confiaveis_vtr + $emprestadas + $em_descarga;
$total_eqp_considered = $confiaveis_eqp + $nao_confiaveis_eqp + $emprestadas + $em_descarga;

// agregados úteis
$total_disp_geral = $disponiveis_vtr + $disponiveis_eqp;
$total_indisp_geral = $indisp_vtr + $indisp_eqp;
$total_nao_confiaveis_geral = $nao_confiaveis_vtr + $nao_confiaveis_eqp;

/* ========= Destinos ========= */
$destinos_viaturas = [];
$destinos_equipamentos = [];

$sql_destinos = "
SELECT UPPER(TRIM(tipo)) AS tipo_normalizado, destino, COUNT(*) AS total
FROM frota
WHERE $whereFrotaSql
GROUP BY tipo_normalizado, destino
";
$stmtD = $conexao->prepare($sql_destinos);
if ($paramsFrota) $stmtD->bind_param($typesFrota, ...$paramsFrota);
$stmtD->execute();
$res_d = $stmtD->get_result();

while ($row = $res_d->fetch_assoc()) {
  $dest = trim((string)$row['destino']) ?: 'Sem destino';
  $tipo = (string)$row['tipo_normalizado'];
  if ($tipo === 'VTR') $destinos_viaturas[$dest] = (int)$row['total'];
  if ($tipo === 'EQP' || $tipo === 'EQUIPAMENTO') $destinos_equipamentos[$dest] = (int)$row['total'];
}
$stmtD->close();

/* ========= Top destinos indisponíveis ========= */
$top_destinos_indisp = [];
$sql_top_dest = "
  SELECT COALESCE(NULLIF(TRIM(destino),''), 'Sem destino') AS destino, COUNT(*) AS total
  FROM frota
  WHERE $whereFrotaSql
    AND disponibilidade <> 'Disponível'
  GROUP BY COALESCE(NULLIF(TRIM(destino),''), 'Sem destino')
  ORDER BY total DESC
  LIMIT 7
";
$stmtTD = $conexao->prepare($sql_top_dest);
if ($paramsFrota) $stmtTD->bind_param($typesFrota, ...$paramsFrota);
$stmtTD->execute();
$resTD = $stmtTD->get_result();
while ($r = $resTD->fetch_assoc()) $top_destinos_indisp[] = ['destino' => $r['destino'], 'total' => (int)$r['total']];
$stmtTD->close();

// opções selects
$opts = [
  'tipo' => [],
  'ativo' => [],
  'acervo' => [],
  'marca' => [],
  'confiabilidade' => [],
  'disponibilidade' => [],
  'destino' => []
];

$sql_opts = "SELECT tipo, ativo, acervo, marca, confiabilidade, disponibilidade, destino FROM frota WHERE $where_om";
$res_opts = $conexao->query($sql_opts);
if ($res_opts) {
  while ($o = $res_opts->fetch_assoc()) {
    foreach ($opts as $k => $v) {
      $val = trim((string)($o[$k] ?? ''));
      if ($val !== '') $opts[$k][$val] = true;
    }
  }
}
foreach ($opts as $k => $arr) {
  $vals = array_keys($arr);
  sort($vals, SORT_NATURAL | SORT_FLAG_CASE);
  $opts[$k] = $vals;
}

$loop_index = 0;
$loop_index2 = 0;
?>

<div data-bloco="resumo">

  <!-- ✅ BARRA AÇÕES -->
  <div class="d-flex flex-wrap gap-2 justify-content-end mb-2">
    <button type="button" class="btn btn-danger btn-sm"
      onclick="
        (function(){
          var f = document.getElementById('filtroResumoForm');
          if(!f){ alert('Formulário de filtros não encontrado.'); return; }
          var params = new URLSearchParams(new FormData(f));
          window.open('pdf/gerar_dashboard_resumo.php?' + params.toString(), '_blank');
        })();
      ">
      <i class="fas fa-file-pdf me-1"></i> Exportar PDF
    </button>
  </div>

  <!-- FILTROS (COLLAPSE) -->
  <div class="card shadow-sm border-0 mb-3">
    <div class="card-body">

      <div class="d-flex justify-content-between align-items-center">
        <div class="fw-bold">
          <i class="fas fa-filter me-1 text-primary"></i> Filtros
        </div>
        <button class="btn btn-outline-primary btn-sm" type="button"
                data-bs-toggle="collapse" data-bs-target="#collapseFiltrosResumo"
                aria-expanded="false" aria-controls="collapseFiltrosResumo">
          <i class="fas fa-sliders-h me-1"></i> Mostrar/ocultar
        </button>
      </div>

      <div class="collapse mt-3" id="collapseFiltrosResumo">
        <form id="filtroResumoForm" class="row g-2">

          <!-- Batalhão -->
          <div class="col-12 col-md-4">
            <label class="form-label fw-bold">Batalhão</label>
            <select name="batalhao" class="form-select">
              <option value="todos" <?= ($filtro_om === 'todos') ? 'selected' : '' ?>>Todos</option>
              <?php foreach ($oms_visiveis as $id => $nome): ?>
                <option value="<?= (int)$id ?>" <?= ((string)$id === (string)$filtro_om) ? 'selected' : '' ?>>
                  <?= htmlspecialchars($nome) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <!-- prefixo_sga -->
          <div class="col-12 col-md-4">
            <label class="form-label fw-bold">Prefixo SGA</label>
            <input type="text" name="prefixo_sga" class="form-control"
                   value="<?= htmlspecialchars($filtro_prefixo) ?>"
                   placeholder="Ex.: EB12345">
          </div>

          <!-- tipo -->
          <div class="col-12 col-md-4">
            <label class="form-label fw-bold">Tipo</label>
            <select name="tipo" class="form-select">
              <option value="">Todos</option>
              <?php foreach ($opts['tipo'] as $v): ?>
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
              <?php foreach ($opts['ativo'] as $v): ?>
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
              <?php foreach ($opts['acervo'] as $v): ?>
                <option value="<?= htmlspecialchars($v) ?>" <?= ($filtro_acervo === $v) ? 'selected' : '' ?>>
                  <?= htmlspecialchars($v) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <!-- marca -->
          <div class="col-12 col-md-3">
            <label class="form-label fw-bold">Marca</label>
            <select name="marca" class="form-select">
              <option value="">Todas</option>
              <?php foreach ($opts['marca'] as $v): ?>
                <option value="<?= htmlspecialchars($v) ?>" <?= ($filtro_marca === $v) ? 'selected' : '' ?>>
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
              <?php foreach ($opts['confiabilidade'] as $v): ?>
                <option value="<?= htmlspecialchars($v) ?>" <?= ($filtro_confiabilidade === $v) ? 'selected' : '' ?>>
                  <?= htmlspecialchars($v) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <!-- disponibilidade -->
          <div class="col-12 col-md-4">
            <label class="form-label fw-bold">Disponibilidade</label>
            <select name="disponibilidade" class="form-select">
              <option value="">Todas</option>
              <?php foreach ($opts['disponibilidade'] as $v): ?>
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
              <?php foreach ($opts['destino'] as $v): ?>
                <option value="<?= htmlspecialchars($v) ?>" <?= ($filtro_destino === $v) ? 'selected' : '' ?>>
                  <?= htmlspecialchars($v) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <!-- ações -->
          <div class="col-12 col-md-4 d-flex align-items-end gap-2">
            <button class="btn btn-primary w-100" type="submit">
              <i class="fas fa-filter me-1"></i> Aplicar filtros
            </button>
            <a class="btn btn-outline-secondary w-100" href="#" onclick="
              const f=this.closest('form');
              f.querySelectorAll('input,select').forEach(el=>{ if(el.name!=='batalhao'){ el.value=''; }});
              f.dispatchEvent(new Event('submit', {cancelable:true, bubbles:true}));
              return false;">
              <i class="fas fa-eraser me-1"></i> Limpar
            </a>
          </div>

        </form>
      </div>

      <?php
        $temFiltrosFrota = ($filtro_prefixo.$filtro_tipo.$filtro_ativo.$filtro_acervo.$filtro_marca.$filtro_confiabilidade.$filtro_disponibilidade.$filtro_destino) !== '';
      ?>
      <div class="mt-3 small text-muted">
        <i class="fas fa-info-circle me-1"></i>
        <?= $temFiltrosFrota ? "Filtros da frota aplicados aos indicadores do painel." : "Sem filtros adicionais da frota (apenas OM)." ?>
      </div>

    </div>
  </div>

  <!-- ✅ MANUTENÇÃO — VISÃO RÁPIDA (mesmo do seu último ajuste) -->
  <div class="card shadow-sm border-0 mb-4">
    <div class="card-header bg-primary text-white d-flex align-items-center justify-content-between">
      <div class="d-flex align-items-center">
        <i class="fas fa-wrench me-2"></i>
        <strong>Manutenção — visão rápida</strong>
      </div>
      <span class="badge bg-light text-dark">
        OS: <?= (int)$total_os ?> | Backlog: <?= (int)$em_andamento ?>
      </span>
    </div>
    <div class="card-body bg-light">
      <div class="row g-3 text-center">
        <div class="col-6 col-md-3">
          <div class="bg-white rounded shadow-sm p-3 h-100">
            <div class="text-muted small">Backlog (Em andamento)</div>
            <div class="fs-4 fw-bold"><?= (int)$em_andamento ?></div>
            <div class="small text-muted"><?= pct($em_andamento, $total_os) ?>% das OS</div>
          </div>
        </div>
        <div class="col-6 col-md-3">
          <div class="bg-white rounded shadow-sm p-3 h-100">
            <div class="text-muted small">Concluídas</div>
            <div class="fs-4 fw-bold"><?= (int)$concluidas ?></div>
            <div class="small text-muted"><?= pct($concluidas, $total_os) ?>% das OS</div>
          </div>
        </div>
        <div class="col-6 col-md-3">
          <div class="bg-white rounded shadow-sm p-3 h-100">
            <div class="text-muted small">Indisponíveis (Vtr+Eqp)</div>
            <div class="fs-4 fw-bold"><?= (int)$total_indisp_geral ?></div>
            <div class="small text-muted">Disponíveis: <?= (int)$total_disp_geral ?></div>
          </div>
        </div>
        <div class="col-6 col-md-3">
          <div class="bg-white rounded shadow-sm p-3 h-100">
            <div class="text-muted small">Não confiáveis</div>
            <div class="fs-4 fw-bold"><?= (int)$total_nao_confiaveis_geral ?></div>
            <div class="small text-muted">Confiáveis: <?= (int)($confiaveis_vtr + $confiaveis_eqp) ?></div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- ======= DAQUI PRA BAIXO: mantém o seu conteúdo original (OS/Fichas, Controle Frota, Disponibilidade, Destinos) ======= -->

  <!-- CARDS OS / FICHAS -->
  <div class="row">
    <div class="col-md-6 mb-4">
      <div class="card card-stats card-round overflow-hidden shadow-sm">
        <div class="d-flex" style="min-height: 150px;">
          <div class="d-flex justify-content-center align-items-center bg-primary" style="width: 90px;">
            <i class="fas fa-tools text-white" style="font-size: 2.5rem;"></i>
          </div>
          <div class="flex-grow-1 p-3">
            <p class="card-category mb-1 text-muted">Controle de Ordens de Serviço</p>
            <div class="d-flex justify-content-between">
              <span class="fw-bold">Total de Ordens:</span> <span><?= $total_os ?></span>
            </div>
            <div class="d-flex justify-content-between">
              <span class="fw-bold text-warning">Em Andamento / Aguardando:</span>
              <span><?= $em_andamento ?> (<?= pct($em_andamento, $total_os) ?>%)</span>
            </div>
            <div class="d-flex justify-content-between">
              <span class="fw-bold text-success">Concluídas:</span>
              <span><?= $concluidas ?> (<?= pct($concluidas, $total_os) ?>%)</span>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="col-md-6 mb-4">
      <div class="card card-stats card-round overflow-hidden shadow-sm">
        <div class="d-flex" style="min-height: 150px;">
          <div class="d-flex justify-content-center align-items-center bg-primary" style="width: 90px;">
            <i class="fas fa-route text-white" style="font-size: 2.5rem;"></i>
          </div>
          <div class="flex-grow-1 p-3">
            <p class="card-category mb-1 text-muted">Controle de Fichas de Viatura</p>
            <div class="d-flex justify-content-between">
              <span class="fw-bold">Total de Fichas:</span> <span><?= $total_fichas ?></span>
            </div>
            <div class="d-flex justify-content-between">
              <span class="fw-bold text-warning">Em Deslocamento:</span>
              <span><?= $abertas ?> (<?= pct($abertas, $total_fichas) ?>%)</span>
            </div>
            <div class="d-flex justify-content-between">
              <span class="fw-bold text-success">Encerradas:</span>
              <span><?= $encerradas ?> (<?= pct($encerradas, $total_fichas) ?>%)</span>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- CONTROLE FROTA + DISPONIBILIDADE + DESTINOS (seu original) -->
  <?php $loop_index=0; $loop_index2=0; ?>

  <div class="card shadow-sm border-0 rounded-3 mb-4">
    <div class="card-header bg-warning text-white rounded-top-3 d-flex align-items-center">
      <i class="bi bi-truck-front-fill fs-4 me-2"></i>
      <h5 class="mb-0 fw-semibold">Controle da Frota</h5>
    </div>
    <div class="card-body bg-light">

      <div class="mb-4">
        <h6 class="text-secondary fw-bold mb-3">
          <i class="bi bi-clipboard-data me-2 text-dark"></i>Ativos Cadastrados no Sistema
        </h6>

        <div class="row g-3 text-center">
          <div class="col-6 col-md-3">
            <div class="bg-white rounded shadow-sm p-3 h-100">
              <i class="bi bi-truck fs-3 text-warning"></i>
              <div class="fw-bold mt-2">Viaturas</div>
              <div class="fs-5"><?= $total_viaturas ?></div>
            </div>
          </div>
          <div class="col-6 col-md-3">
            <div class="bg-white rounded shadow-sm p-3 h-100">
              <i class="bi bi-cpu-fill fs-3 text-warning"></i>
              <div class="fw-bold mt-2">Equipamentos</div>
              <div class="fs-5"><?= $total_equipamentos ?></div>
            </div>
          </div>
          <div class="col-6 col-md-3">
            <div class="bg-white rounded shadow-sm p-3 h-100">
              <i class="bi bi-stack fs-3 text-warning"></i>
              <div class="fw-bold mt-2">Total</div>
              <div class="fs-5"><?= $total_ativos ?></div>
            </div>
          </div>
        </div>

        <div class="row g-3 text-center mt-3">
          <div class="col-6 col-md-3">
            <div class="bg-white rounded shadow-sm p-3 h-100">
              <i class="bi bi-x-octagon-fill fs-3 text-danger"></i>
              <div class="fw-bold mt-2">Desfazimentos</div>
              <div class="fs-5"><?= $desfeitas ?></div>
            </div>
          </div>
          <div class="col-6 col-md-3">
            <div class="bg-white rounded shadow-sm p-3 h-100">
              <i class="bi bi-box-arrow-down fs-3 text-secondary"></i>
              <div class="fw-bold mt-2">Descarregados</div>
              <div class="fs-5"><?= $descarregadas ?></div>
            </div>
          </div>
          <div class="col-6 col-md-3">
            <div class="bg-white rounded shadow-sm p-3 h-100">
              <i class="bi bi-hourglass-split fs-3 text-primary"></i>
              <div class="fw-bold mt-2">Em Descarga</div>
              <div class="fs-5"><?= $em_descarga ?></div>
            </div>
          </div>
          <div class="col-6 col-md-3">
            <div class="bg-white rounded shadow-sm p-3 h-100">
              <i class="bi bi-arrow-left-right fs-3 text-success"></i>
              <div class="fw-bold mt-2">Emprestados</div>
              <div class="fs-5"><?= $emprestadas ?></div>
            </div>
          </div>
        </div>
      </div>

      <div>
        <h6 class="text-secondary fw-bold mb-3">
          <i class="bi bi-speedometer2 me-2 text-dark"></i>Frota Atual
        </h6>
        <div class="row g-3 text-center">
          <div class="col-6 col-md-4">
            <div class="bg-white rounded shadow-sm p-3 h-100">
              <i class="bi bi-check-circle-fill fs-3 text-success"></i>
              <div class="fw-bold mt-2">Confiáveis</div>
              <div class="fs-5">Viaturas: <?= $confiaveis_vtr ?></div>
              <div class="fs-6">Equipamentos: <?= $confiaveis_eqp ?></div>
              <div class="fw-bold">Total: <?= $confiaveis_vtr + $confiaveis_eqp ?></div>
            </div>
          </div>
          <div class="col-6 col-md-4">
            <div class="bg-white rounded shadow-sm p-3 h-100">
              <i class="bi bi-exclamation-triangle-fill fs-3 text-danger"></i>
              <div class="fw-bold mt-2">Não Confiáveis</div>
              <div class="fs-5">Viaturas: <?= $nao_confiaveis_vtr ?></div>
              <div class="fs-6">Equipamentos: <?= $nao_confiaveis_eqp ?></div>
              <div class="fw-bold">Total: <?= $nao_confiaveis_vtr + $nao_confiaveis_eqp ?></div>
            </div>
          </div>
          <div class="col-12 col-md-4">
            <div class="bg-white rounded shadow-sm p-3 h-100">
              <i class="bi bi-graph-up-arrow fs-3 text-primary"></i>
              <div class="fw-bold mt-2">Total da Frota Atual</div>
              <div class="fs-5">Viaturas: <?= $confiaveis_vtr + $nao_confiaveis_vtr ?></div>
              <div class="fs-6">Equipamentos: <?= $confiaveis_eqp + $nao_confiaveis_eqp ?></div>
              <div class="fw-bold">Total: <?= ($confiaveis_vtr + $nao_confiaveis_vtr) + ($confiaveis_eqp + $nao_confiaveis_eqp) ?></div>
            </div>
          </div>
        </div>
      </div>

    </div>
  </div>

  <div class="card shadow-sm mb-4" style="background-color: rgba(240, 240, 240, 0.8); border: none;">
    <div class="card-body">
      <h5 class="card-title mb-4 fw-bold text-dark">
        <i class="fas fa-chart-pie me-2 text-primary"></i>Disponibilidade da Frota atual
      </h5>
      <div class="row">
        <div class="col-md-6 mb-4">
          <div class="card card-stats card-round overflow-hidden shadow-sm">
            <div class="d-flex" style="min-height: 150px;">
              <div class="d-flex justify-content-center align-items-center" style="background-color: #007bff; width: 90px;">
                <i class="fas fa-car-side text-white" style="font-size: 2.5rem;"></i>
              </div>
              <div class="flex-grow-1 p-3">
                <p class="card-category mb-1 text-muted">Disponibilidade Viaturas / Frota</p>
                <div class="d-flex justify-content-between">
                  <span class="fw-bold">Total Vtr:</span> <span><?= $total_viaturas ?></span>
                </div>
                <div class="d-flex justify-content-between">
                  <span class="fw-bold text-success">Disponíveis:</span>
                  <span><?= $disponiveis_vtr ?> (<?= pct_val($disponiveis_vtr, $total_vtr_considered) ?>%)</span>
                </div>
                <div class="d-flex justify-content-between">
                  <span class="fw-bold text-danger">Indisponíveis:</span>
                  <span><?= $indisp_vtr ?> (<?= pct_val($indisp_vtr, $total_vtr_considered) ?>%)</span>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div class="col-md-6 mb-4">
          <div class="card card-stats card-round overflow-hidden shadow-sm">
            <div class="d-flex" style="min-height: 150px;">
              <div class="d-flex justify-content-center align-items-center" style="background-color: #007bff; width: 90px;">
                <i class="fas fa-tractor text-white" style="font-size: 2.5rem;"></i>
              </div>
              <div class="flex-grow-1 p-3">
                <p class="card-category mb-1 text-muted">Disponibilidade Equipamentos</p>
                <div class="d-flex justify-content-between">
                  <span class="fw-bold">Total:</span> <span><?= $total_equipamentos ?></span>
                </div>
                <div class="d-flex justify-content-between">
                  <span class="fw-bold text-success">Disponíveis:</span>
                  <span><?= $disponiveis_eqp ?> (<?= pct_val($disponiveis_eqp, $total_eqp_considered) ?>%)</span>
                </div>
                <div class="d-flex justify-content-between">
                  <span class="fw-bold text-danger">Indisponíveis:</span>
                  <span><?= $indisp_eqp ?> (<?= pct_val($indisp_eqp, $total_eqp_considered) ?>%)</span>
                </div>
              </div>
            </div>
          </div>
        </div>

      </div>
    </div>
  </div>

  <div class="card shadow-sm mb-4" style="background-color: rgba(240, 240, 240, 0.8); border: none;">
    <div class="card-body">
      <h5 class="card-title mb-4 fw-bold text-dark">
        <i class="fas fa-chart-pie me-2 text-primary"></i>Vtr/Eqp por destino
      </h5>
      <div class="row">
        <div class="col-md-6 mb-4">
          <div class="card shadow-sm overflow-hidden">
            <div class="d-flex">
              <div class="d-flex justify-content-center align-items-center" style="background-color: #007bff; width: 90px;">
                <i class="fas fa-car-side text-white" style="font-size: 2rem;"></i>
              </div>
              <div class="flex-grow-1 p-3">
                <h6 class="fw-bold text-center border-bottom pb-2 mb-3">QUANT VIATURAS POR DESTINO</h6>
                <div class="d-flex fw-bold bg-secondary text-white px-3 py-2">
                  <div class="w-50">LOCAL</div>
                  <div class="w-50 text-end">TOTAL</div>
                </div>
                <?php foreach ($destinos_viaturas as $dest => $qtd): ?>
                  <div class="d-flex px-3 py-2 border-bottom <?= ($loop_index++ % 2 == 0) ? '' : 'bg-light' ?>">
                    <div class="w-50 fw-semibold"><?= htmlspecialchars($dest) ?></div>
                    <div class="w-50 text-end"><?= (int)$qtd ?></div>
                  </div>
                <?php endforeach; ?>
                <?php if (empty($destinos_viaturas)): ?>
                  <div class="p-3 text-muted">Nenhum registro com os filtros aplicados.</div>
                <?php endif; ?>
              </div>
            </div>
          </div>
        </div>

        <div class="col-md-6 mb-4">
          <div class="card shadow-sm overflow-hidden">
            <div class="d-flex">
              <div class="d-flex justify-content-center align-items-center" style="background-color: #000; width: 90px;">
                <i class="fas fa-tractor text-white" style="font-size: 2rem;"></i>
              </div>
              <div class="flex-grow-1 p-3">
                <h6 class="fw-bold text-center border-bottom pb-2 mb-3">QUANT EQUIPAMENTOS POR DESTINO</h6>
                <div class="d-flex fw-bold bg-secondary text-white px-3 py-2">
                  <div class="w-50">LOCAL</div>
                  <div class="w-50 text-end">TOTAL</div>
                </div>
                <?php foreach ($destinos_equipamentos as $dest => $qtd): ?>
                  <div class="d-flex px-3 py-2 border-bottom <?= ($loop_index2++ % 2 == 0) ? '' : 'bg-light' ?>">
                    <div class="w-50 fw-semibold"><?= htmlspecialchars($dest) ?></div>
                    <div class="w-50 text-end"><?= (int)$qtd ?></div>
                  </div>
                <?php endforeach; ?>
                <?php if (empty($destinos_equipamentos)): ?>
                  <div class="p-3 text-muted">Nenhum registro com os filtros aplicados.</div>
                <?php endif; ?>
              </div>
            </div>
          </div>
        </div>

      </div>

      <!-- ✅ opcional: mostra top destinos indisponíveis também no HTML -->
      <div class="mt-4">
        <h6 class="fw-bold mb-2"><i class="fas fa-exclamation-triangle me-1 text-warning"></i>Top destinos com indisponíveis</h6>
        <?php if (!empty($top_destinos_indisp)): ?>
          <div class="bg-white rounded shadow-sm p-3">
            <?php foreach ($top_destinos_indisp as $it): ?>
              <div class="d-flex justify-content-between border-bottom py-1">
                <span><?= htmlspecialchars($it['destino']) ?></span>
                <strong><?= (int)$it['total'] ?></strong>
              </div>
            <?php endforeach; ?>
          </div>
        <?php else: ?>
          <div class="text-muted">Sem dados para os filtros atuais.</div>
        <?php endif; ?>
      </div>

    </div>
  </div>

</div>