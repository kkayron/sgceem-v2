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
  $where_om = !empty($ids)
    ? "batalhao IN (" . implode(',', array_map('intval', $ids)) . ")"
    : "1=0";
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
   OPTIONS DOS SELECTS (igual resumo)
=========================== */
$opts = [
  'tipo' => [],
  'ativo' => [],
  'acervo' => [],
  'marca' => [],
  'confiabilidade' => [],
  'disponibilidade' => [],
  'destino' => []
];

$sql_opts = "SELECT tipo, ativo, acervo, marca, confiabilidade, disponibilidade, destino
             FROM frota
             WHERE $where_om AND tipo IN ('Vtr','Eqp')";
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

/* ===========================
   Preventiva (cálculo)
=========================== */
include_once('../../includes/preventiva/calculo_manutencao.php');

/* ===========================
   Helpers UI
=========================== */
function linha_status($nome, $valor, $classe = '') {
  return "
  <div class='d-flex px-3 py-2 border-bottom $classe'>
    <div class='w-75 fw-semibold'>$nome</div>
    <div class='w-25 text-end'>$valor</div>
  </div>";
}

/**
 * ✅ Bucket COMPLETO (agora inclui: em_manutencao e sem_dados)
 * - usado apenas nos collapses por atributo
 */
function bucketPreventivaCompleto($status) {
  $s = mb_strtolower(trim((string)$status));
  if ($s === '') return 'sem_dados';

  if (strpos($s, 'vencid') !== false) return 'vencida';

  // "muito próxima" é uma categoria separada
  if (strpos($s, 'muito próx') !== false || strpos($s, 'muito prox') !== false) return 'muito_proxima';
  if (strpos($s, 'próxim') !== false || strpos($s, 'proxim') !== false) return 'proxima';

  if (strpos($s, 'em manuten') !== false || strpos($s, 'agend') !== false || strpos($s, 'aguard') !== false) return 'em_manutencao';

  if (strpos($s, 'em dia') !== false) return 'emdia';

  if (strpos($s, 'sem dados') !== false) return 'sem_dados';

  // fallback: se vier algo diferente, joga em sem dados
  return 'sem_dados';
}

function initResumoAtributoCompleto() {
  return [
    'emdia' => 0,
    'muito_proxima' => 0,
    'proxima' => 0,
    'em_manutencao' => 0,
    'sem_dados' => 0,
    'vencida' => 0,
    'total' => 0
  ];
}

/* ===========================
   1) Buscar frota filtrada
=========================== */
$sql_frota = "
  SELECT
    id, tipo, prefixo_sga, nome_sioc, status_odometro,
    ativo, acervo, confiabilidade, disponibilidade, destino
  FROM frota
  WHERE $whereFrotaSql
";
$stmtF = $conexao->prepare($sql_frota);
if ($stmtF === false) {
  echo "<div class='alert alert-danger'>Erro prepare frota: ".htmlspecialchars($conexao->error)."</div>";
  exit;
}
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

/* ===========================
   2) Contadores (mantido)
=========================== */
$contagem = [
  'Vtr' => ['Manutenção em dia'=>0,'Muito próxima'=>0,'Próxima'=>0,'Manutenção vencida'=>0,'Em manutenção'=>0,'Sem dados'=>0,'Total'=>0],
  'Eqp' => ['Manutenção em dia'=>0,'Muito próxima'=>0,'Próxima'=>0,'Manutenção vencida'=>0,'Em manutenção'=>0,'Sem dados'=>0,'Total'=>0]
];

/* ===========================
   ✅ 2.1) Resumos por atributo (AGORA COMPLETO)
=========================== */
$resumoPor = [
  'ativo'           => [],
  'acervo'          => [],
  'confiabilidade'  => [],
  'disponibilidade' => [],
  'destino'         => [],
];

/* ===========================
   3) Rodar cálculo na frota filtrada
=========================== */
if (!empty($frota)) {

  $map = null;
  if (function_exists('calcularManutencaoPreventivaBulk')) {
    $map = calcularManutencaoPreventivaBulk($conexao, $frota);
  }

  $cache = [];
  foreach ($frota as $item) {

    $tipoBruto = strtolower(trim((string)$item['tipo']));
    $tipo = in_array($tipoBruto, ['vtr','viatura','veiculo'], true) ? 'Vtr' : 'Eqp';
    $id = (int)$item['id'];

    // bulk ou individual com cache
    if ($map !== null) {
      $calc = $map[$id] ?? [];
    } else {
      if (!isset($cache[$id])) $cache[$id] = calcularManutencaoPreventiva($conexao, $item);
      $calc = $cache[$id];
    }

    $statusOriginal = (string)($calc['status'] ?? 'Sem dados');

    // contagem padrão (QUADRO PRINCIPAL)
    $statusQuadro = $statusOriginal;
    if (!isset($contagem[$tipo][$statusQuadro])) $statusQuadro = 'Sem dados';
    $contagem[$tipo][$statusQuadro]++;
    $contagem[$tipo]['Total']++;

    // ✅ resumo por atributos (COLLAPSES) — agora inclui TODAS as categorias
    $bucket = bucketPreventivaCompleto($statusOriginal);

    foreach ($resumoPor as $campo => $_) {
      $valor = trim((string)($item[$campo] ?? ''));
      if ($valor === '') $valor = '(Vazio)';

      if (!isset($resumoPor[$campo][$valor])) {
        $resumoPor[$campo][$valor] = initResumoAtributoCompleto();
      }

      $resumoPor[$campo][$valor][$bucket]++;
      $resumoPor[$campo][$valor]['total']++;
    }
  }

  // ordenar cada agrupamento por total desc e depois nome
  foreach ($resumoPor as $campo => $mapa) {
    uksort($mapa, function($a, $b) use ($mapa) {
      $ta = (int)($mapa[$a]['total'] ?? 0);
      $tb = (int)($mapa[$b]['total'] ?? 0);
      if ($ta !== $tb) return $tb <=> $ta;
      return strcasecmp((string)$a, (string)$b);
    });
    $resumoPor[$campo] = $mapa;
  }
}
?>

<div data-bloco="preventiva">

  <!-- ✅ BARRA AÇÕES -->
  <div class="d-flex flex-wrap gap-2 justify-content-end mb-2">
    <button type="button" class="btn btn-danger btn-sm"
      onclick="(function(){
        var f=document.getElementById('filtroPreventivaForm');
        if(!f){ alert('Formulário de filtros não encontrado.'); return; }
        var params=new URLSearchParams(new FormData(f));
        window.open('pdf/gerar_dashboard_preventiva.php?'+params.toString(),'_blank','noopener');
      })();">
      <i class="fas fa-file-pdf me-1"></i> Exportar PDF
    </button>

    <button type="button" class="btn btn-success btn-sm"
      onclick="(function(){
        var f=document.getElementById('filtroPreventivaForm');
        if(!f){ alert('Formulário de filtros não encontrado.'); return; }
        var params=new URLSearchParams(new FormData(f));
        window.open('excel/gerar_calendario_preventiva.php?'+params.toString(),'_blank','noopener');
      })();">
      <i class="fas fa-file-excel me-1"></i> Exportar Excel
    </button>
  </div>

  <!-- FILTROS DO BLOCO -->
  <div class="card shadow-sm border-0 mb-3">
    <div class="card-body">

      <div class="d-flex justify-content-between align-items-center">
        <div class="fw-bold">
          <i class="fas fa-filter me-1 text-primary"></i> Filtros
        </div>
        <button class="btn btn-outline-primary btn-sm" type="button"
                data-bs-toggle="collapse" data-bs-target="#collapseFiltrosPreventiva"
                aria-expanded="false" aria-controls="collapseFiltrosPreventiva">
          <i class="fas fa-sliders-h me-1"></i> Mostrar/ocultar
        </button>
      </div>

      <div class="collapse mt-3" id="collapseFiltrosPreventiva">
        <form id="filtroPreventivaForm" class="row g-2" onsubmit="return false;">

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

          <!-- Prefixo SGA -->
          <div class="col-12 col-md-4">
            <label class="form-label fw-bold">Prefixo SGA</label>
            <input type="text" name="prefixo_sga" class="form-control"
                   value="<?= htmlspecialchars($filtro_prefixo) ?>"
                   placeholder="Ex.: EB12345">
          </div>

          <!-- Tipo -->
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

          <!-- Ativo -->
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

          <!-- Acervo -->
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

          <!-- Marca -->
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

          <!-- Confiabilidade -->
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

          <!-- Disponibilidade -->
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

          <!-- Destino -->
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

          <!-- Ações -->
          <div class="col-12 col-md-4 d-flex align-items-end gap-2">
            <button type="button" class="btn btn-primary w-100"
              onclick="(function(){
                const f=document.getElementById('filtroPreventivaForm');
                f.dispatchEvent(new Event('submit', {cancelable:true, bubbles:true}));
              })();">
              <i class="fas fa-filter me-1"></i> Aplicar filtros
            </button>

            <button type="button" class="btn btn-outline-secondary w-100"
              onclick="(function(btn){
                const f=btn.closest('form');
                f.querySelectorAll('input,select').forEach(el=>{
                  if(el.name!=='batalhao'){ el.value=''; }
                });
                f.dispatchEvent(new Event('submit', {cancelable:true, bubbles:true}));
              })(this);">
              <i class="fas fa-eraser me-1"></i> Limpar
            </button>
          </div>

        </form>
      </div>

    </div>
  </div>

  <div class="card shadow-sm mb-4" style="background-color: rgba(240, 240, 240, 0.8); border: none;">
    <div class="card-body">

      <h5 class="card-title mb-4 fw-bold text-dark">
        <i class="fas fa-tools me-2 text-danger"></i>Manutenção Preventiva
      </h5>

      <div class="row">
        <div class="col-md-6 mb-4">
          <div class="card shadow-sm overflow-hidden">
            <div class="d-flex">
              <div class="d-flex justify-content-center align-items-center" style="background-color: #007bff; width: 90px;">
                <i class="fas fa-car-side text-white" style="font-size: 2rem;"></i>
              </div>
              <div class="flex-grow-1 p-3">
                <h6 class="fw-bold text-center border-bottom pb-2 mb-3">MANUTENÇÃO VIATURAS</h6>

                <div class="d-flex fw-bold bg-secondary text-white px-3 py-2">
                  <div class="w-75">Situação</div>
                  <div class="w-25 text-end">Qtd</div>
                </div>

                <?= linha_status('<span class="text-success">Manutenção em Dia</span>', $contagem['Vtr']['Manutenção em dia']); ?>
                <?= linha_status('<span class="text-warning">Manutenção Muito Próxima</span>', $contagem['Vtr']['Muito próxima'], 'bg-light'); ?>
                <?= linha_status('<span class="text-info">Manutenção Próxima</span>', $contagem['Vtr']['Próxima']); ?>
                <?= linha_status('<span class="text-primary">Agendada/Em manutenção</span>', $contagem['Vtr']['Em manutenção'], 'bg-light'); ?>
                <?= linha_status('<span class="text-dark">Sem dados</span>', $contagem['Vtr']['Sem dados']); ?>
                <?= linha_status('<span class="text-danger">Manutenção Vencida</span>', $contagem['Vtr']['Manutenção vencida'], 'bg-light'); ?>
                <?= linha_status('<b>Total</b>', '<b>'.$contagem['Vtr']['Total'].'</b>', 'border-top mt-2'); ?>
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
                <h6 class="fw-bold text-center border-bottom pb-2 mb-3">MANUTENÇÃO EQUIPAMENTOS</h6>

                <div class="d-flex fw-bold bg-secondary text-white px-3 py-2">
                  <div class="w-75">Situação</div>
                  <div class="w-25 text-end">Qtd</div>
                </div>

                <?= linha_status('<span class="text-success">Manutenção em Dia</span>', $contagem['Eqp']['Manutenção em dia']); ?>
                <?= linha_status('<span class="text-warning">Manutenção Muito Próxima</span>', $contagem['Eqp']['Muito próxima'], 'bg-light'); ?>
                <?= linha_status('<span class="text-info">Manutenção Próxima</span>', $contagem['Eqp']['Próxima']); ?>
                <?= linha_status('<span class="text-primary">Agendada/Em manutenção</span>', $contagem['Eqp']['Em manutenção'], 'bg-light'); ?>
                <?= linha_status('<span class="text-dark">Sem dados</span>', $contagem['Eqp']['Sem dados']); ?>
                <?= linha_status('<span class="text-danger">Manutenção Vencida</span>', $contagem['Eqp']['Manutenção vencida'], 'bg-light'); ?>
                <?= linha_status('<b>Total</b>', '<b>'.$contagem['Eqp']['Total'].'</b>', 'border-top mt-2'); ?>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- ✅ RESUMO POR ATRIBUTOS (ACCORDION/COLLAPSE) — AGORA COM TODAS AS CATEGORIAS -->
      <div class="card shadow-sm border-0 mt-3">
        <div class="card-body">
          <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
            <h6 class="mb-0 fw-bold">
              <i class="fas fa-list-check me-2 text-primary"></i>Resumo por atributos da frota (todas as situações)
            </h6>
            <span class="badge bg-light text-dark">
              Categorias: <b>Em dia</b>, <b>Muito próxima</b>, <b>Próxima</b>, <b>Agendada/Em manutenção</b>, <b>Sem dados</b>, <b>Vencida</b>
            </span>
          </div>

          <div class="accordion mt-3" id="accResumoPreventiva">
            <?php
              $labels = [
                'ativo'           => 'Ativo',
                'acervo'          => 'Acervo',
                'confiabilidade'  => 'Confiabilidade',
                'disponibilidade' => 'Disponibilidade',
                'destino'         => 'Destino',
              ];
              $iAcc = 0;

              foreach ($resumoPor as $campo => $mapa):
                $iAcc++;

                $accId = "accPrev_" . $campo;
                $collapseId = "colPrev_" . $campo;

                $tot = initResumoAtributoCompleto();
                foreach ($mapa as $v => $c) {
                  $tot['emdia']         += (int)$c['emdia'];
                  $tot['muito_proxima'] += (int)$c['muito_proxima'];
                  $tot['proxima']       += (int)$c['proxima'];
                  $tot['em_manutencao'] += (int)$c['em_manutencao'];
                  $tot['sem_dados']     += (int)$c['sem_dados'];
                  $tot['vencida']       += (int)$c['vencida'];
                  $tot['total']         += (int)$c['total'];
                }
            ?>
              <div class="accordion-item">
                <h2 class="accordion-header" id="<?= htmlspecialchars($accId) ?>">
                  <button type="button"
                          class="accordion-button <?= ($iAcc === 1 ? '' : 'collapsed') ?>"
                          data-bs-toggle="collapse"
                          data-bs-target="#<?= htmlspecialchars($collapseId) ?>"
                          aria-expanded="<?= ($iAcc === 1 ? 'true' : 'false') ?>"
                          aria-controls="<?= htmlspecialchars($collapseId) ?>">
                    <span class="fw-bold"><?= htmlspecialchars($labels[$campo] ?? $campo) ?></span>
                    <span class="ms-2 text-muted small">
                      — Total: <?= (int)$tot['total'] ?>
                      | Em dia: <?= (int)$tot['emdia'] ?>
                      | Muito próx.: <?= (int)$tot['muito_proxima'] ?>
                      | Próx.: <?= (int)$tot['proxima'] ?>
                      | Agd/Manut.: <?= (int)$tot['em_manutencao'] ?>
                      | Sem dados: <?= (int)$tot['sem_dados'] ?>
                      | Vencidas: <?= (int)$tot['vencida'] ?>
                    </span>
                  </button>
                </h2>

                <div id="<?= htmlspecialchars($collapseId) ?>"
                     class="accordion-collapse collapse <?= ($iAcc === 1 ? 'show' : '') ?>"
                     aria-labelledby="<?= htmlspecialchars($accId) ?>"
                     data-bs-parent="#accResumoPreventiva">
                  <div class="accordion-body p-0">

                    <?php if (empty($mapa)): ?>
                      <div class="p-3 text-muted">
                        Sem dados para este agrupamento com os filtros atuais.
                      </div>
                    <?php else: ?>
                      <div class="table-responsive">
                        <table class="table table-sm table-hover align-middle mb-0">
                          <thead class="table-light">
                            <tr>
                              <th style="min-width:220px;"><?= htmlspecialchars($labels[$campo] ?? $campo) ?></th>
                              <th class="text-center text-success">Em dia</th>
                              <th class="text-center text-warning">Muito próx.</th>
                              <th class="text-center text-info">Próxima</th>
                              <th class="text-center text-primary">Agd/Manut.</th>
                              <th class="text-center text-dark">Sem dados</th>
                              <th class="text-center text-danger">Vencida</th>
                              <th class="text-center">Total</th>
                            </tr>
                          </thead>
                          <tbody>
                            <?php foreach ($mapa as $valor => $c): ?>
                              <tr>
                                <td class="fw-semibold"><?= htmlspecialchars((string)$valor) ?></td>
                                <td class="text-center text-success fw-bold"><?= (int)$c['emdia'] ?></td>
                                <td class="text-center text-warning fw-bold"><?= (int)$c['muito_proxima'] ?></td>
                                <td class="text-center text-info fw-bold"><?= (int)$c['proxima'] ?></td>
                                <td class="text-center text-primary fw-bold"><?= (int)$c['em_manutencao'] ?></td>
                                <td class="text-center text-dark fw-bold"><?= (int)$c['sem_dados'] ?></td>
                                <td class="text-center text-danger fw-bold"><?= (int)$c['vencida'] ?></td>
                                <td class="text-center"><?= (int)$c['total'] ?></td>
                              </tr>
                            <?php endforeach; ?>
                          </tbody>
                          <tfoot class="table-light">
                            <tr>
                              <th class="text-end">Total</th>
                              <th class="text-center text-success"><?= (int)$tot['emdia'] ?></th>
                              <th class="text-center text-warning"><?= (int)$tot['muito_proxima'] ?></th>
                              <th class="text-center text-info"><?= (int)$tot['proxima'] ?></th>
                              <th class="text-center text-primary"><?= (int)$tot['em_manutencao'] ?></th>
                              <th class="text-center text-dark"><?= (int)$tot['sem_dados'] ?></th>
                              <th class="text-center text-danger"><?= (int)$tot['vencida'] ?></th>
                              <th class="text-center"><?= (int)$tot['total'] ?></th>
                            </tr>
                          </tfoot>
                        </table>
                      </div>
                    <?php endif; ?>

                  </div>
                </div>
              </div>
            <?php endforeach; ?>
          </div>

        </div>
      </div>
      <!-- /RESUMO POR ATRIBUTOS -->

    </div>
  </div>

</div>