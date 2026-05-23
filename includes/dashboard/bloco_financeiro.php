<?php
session_start();

require_once '../api/seguranca.php';

$permissoes = verificarPermissao(56);

$pode_cadastrar = $permissoes['cadastrar'];
$pode_editar    = $permissoes['editar'];
$pode_deletar   = $permissoes['deletar'];
$pode_importar  = $permissoes['importar'];
$pode_exportar  = $permissoes['exportar'];

if (!isset($_SESSION['usuario_id'])) {
  http_response_code(401);
  echo "<div class='alert alert-danger'>Sessão expirada. Faça login novamente.</div>";
  exit;
}


include_once('../../conexao/config.php');


// tenta liberar joins grandes (nem todo host permite, mas não atrapalha)
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

// OM
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

// Ano
if ($filtro_ano !== '') {
  $filtros[] = "e.ano = ?";
  $params[]  = (int)$filtro_ano;
  $tipos    .= 'i';
}

// Categoria
if ($filtro_categoria !== '') {
  $filtros[] = "e.categoria = ?";
  $params[]  = (string)$filtro_categoria;
  $tipos    .= 's';
}

// Restos a pagar
if ($filtro_resto === 'Sim') {
  $filtros[] = "e.resto_pagar = 'Sim'";
} elseif ($filtro_resto === 'Não') {
  $filtros[] = "e.resto_pagar = 'Não'";
}

$where_final = $filtros ? ('WHERE ' . implode(' AND ', $filtros)) : '';

/* ===========================
   HELPERS
=========================== */
function fmt($v) { return 'R$ ' . number_format((float)($v ?? 0), 2, ',', '.'); }
function pct($v) { return number_format((float)$v, 2, ',', '.') . '%'; }

$STATUS_LABELS = [
  'não entregue' => 'Não entregue',
  'nao entregue' => 'Não entregue',
  'entregue'     => 'Entregue',
  'capeador'     => 'Capeador',
  'liquidado'    => 'Liquidado',
  'pago'         => 'Liquidado', // pago entra em liquidado
];

$BUCKETS = ['Não entregue'=>0,'Entregue'=>0,'Capeador'=>0,'Liquidado'=>0];

/* ===========================
   1) Buscar empenhos + valor empenhado (sem N+1)
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
";
$stmtEmp = $conexao->prepare($sqlEmp);
if ($stmtEmp === false) {
  echo "<div class='alert alert-danger'>Erro prepare empenhos: ".htmlspecialchars($conexao->error)."</div>";
  exit;
}
if ($params) $stmtEmp->bind_param($tipos, ...$params);
$stmtEmp->execute();
$resEmp = $stmtEmp->get_result();

$empenhos = [];
$idsEmpenhos = [];
while ($row = $resEmp->fetch_assoc()) {
  $row['id'] = (int)$row['id'];
  $row['valor_empenhado'] = (float)$row['valor_empenhado'];
  $empenhos[] = $row;
  $idsEmpenhos[] = (int)$row['id'];
}
$stmtEmp->close();

/* ===========================
   2) SOMAR STATUS (SEM JOIN GIGANTE) — ANTI MAX_JOIN_SIZE
   Estratégia:
   - Em lotes de empenhos:
     (a) pega pedidos por (id_empenho, status, id_pedido)
     (b) soma itens por pedido
     (c) acumula no mapaStatus por bucket
=========================== */
$mapaStatus = []; // [id_empenho][bucket] = valor
foreach ($idsEmpenhos as $idEmp) $mapaStatus[$idEmp] = $BUCKETS;

if (!empty($idsEmpenhos)) {

  $chunkSize = 200; // ajuste se quiser (100~300). Quanto menor, menos chance de estourar.
  $chunks = array_chunk($idsEmpenhos, $chunkSize);

  foreach ($chunks as $chunkEmp) {

    $listaEmp = implode(',', array_map('intval', $chunkEmp));

    // (a) pega vinculação empenho -> status -> pedido (join pequeno)
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
    if (!$resLink) {
      // se der erro, não quebra toda a página, só avisa
      echo "<div class='alert alert-danger'>Erro ao carregar ordens/pedidos: ".htmlspecialchars($conexao->error)."</div>";
      break;
    }

    $pedidoMeta = []; // [id_pedido] => array de pares [id_empenho, bucketLabel]
    $pedidoIds = [];

    while ($lk = $resLink->fetch_assoc()) {
      $idEmp = (int)$lk['id_empenho'];
      $stRaw = (string)$lk['status'];
      $idPed = (int)$lk['id_pedido'];

      if ($idPed <= 0) continue;

      // normaliza status -> bucket
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

      // um pedido normalmente pertence a uma ordem, mas por segurança guardamos a relação
      $pedidoMeta[$idPed][] = [$idEmp, $label];
    }

    if (empty($pedidoIds)) continue;

    // (b) soma itens por pedido (query simples e indexável)
    // para evitar IN gigante, também fazemos em chunks de pedidos
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
      if (!$resItens) {
        echo "<div class='alert alert-danger'>Erro ao somar itens dos pedidos: ".htmlspecialchars($conexao->error)."</div>";
        continue;
      }

      while ($it = $resItens->fetch_assoc()) {
        $idPed = (int)$it['id_principal'];
        $total = (float)$it['total'];

        if ($total <= 0) continue;
        if (!isset($pedidoMeta[$idPed])) continue;

        // (c) acumula para cada (empenho, status) ligado a esse pedido
        foreach ($pedidoMeta[$idPed] as $pair) {
          [$idEmp, $label] = $pair;
          if (!isset($mapaStatus[$idEmp])) $mapaStatus[$idEmp] = $BUCKETS;
          if (!isset($mapaStatus[$idEmp][$label])) $mapaStatus[$idEmp][$label] = 0;
          $mapaStatus[$idEmp][$label] += $total;
        }
      }
    }
  }
}

/* ===========================
   3) Buscar saldo SIAFI em BULK (corrente + resto) por nmr_empenho
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
        SELECT nmr_empenho, saldo_empenho
        FROM (
          SELECT nmr_empenho, saldo_empenho 
          FROM fin_siafi_corrente 
          WHERE nmr_empenho IN ($placeholders)

          UNION ALL

          SELECT nmr_empenho, saldo_empenho 
          FROM fin_siafi_restopagar 
          WHERE nmr_empenho IN ($placeholders)
        ) x
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
   4) Somatórios finais
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

foreach ($empenhos as $e) {
  $idEmp = (int)$e['id'];

  $valorEmpenhado = (float)$e['valor_empenhado'];
  $soma_total_empenhado += $valorEmpenhado;

  $b = $mapaStatus[$idEmp] ?? $BUCKETS;

  $soma_nao_entregue += (float)$b['Não entregue'];
  $soma_entregue     += (float)$b['Entregue'];
  $soma_capeador     += (float)$b['Capeador'];
  $soma_liquidado    += (float)$b['Liquidado'];

  $utilizado = (float)$b['Não entregue'] + (float)$b['Entregue'] + (float)$b['Capeador'] + (float)$b['Liquidado'];
  $soma_total_utilizado += $utilizado;

  $saldoReal = $valorEmpenhado - $utilizado;
  $soma_saldo_real += $saldoReal;

  $saldoSiafi = null;
  $nmr = (string)$e['nmr_empenho'];
  if ($nmr !== '' && isset($mapaSiafi[$nmr])) {
    $saldoSiafi = (float)$mapaSiafi[$nmr];
    $soma_saldo_siafi += $saldoSiafi;
  }

  $soma_diferenca += ($saldoSiafi !== null) ? ($saldoSiafi - $saldoReal) : 0;
}

/* percentuais */
if ($soma_total_empenhado > 0) {
  $porc_liquidado = ($soma_liquidado / $soma_total_empenhado) * 100;
  $porc_req       = ($soma_nao_entregue / $soma_total_empenhado) * 100;
  $porc_entregue  = ($soma_entregue / $soma_total_empenhado) * 100;
  $porc_capeador  = ($soma_capeador / $soma_total_empenhado) * 100;
} else {
  $porc_liquidado = $porc_req = $porc_entregue = $porc_capeador = 0;
}

/* helper UI */
function kpiCard($title, $value, $icon, $class = 'primary', $sub = '') {
  $subHtml = $sub !== '' ? "<div class='small text-muted mt-1'>$sub</div>" : "";
  return "
  <div class='col-12 col-md-4 col-lg-3 mb-3'>
    <div class='card border-0 shadow-sm h-100'>
      <div class='card-body'>
        <div class='d-flex align-items-start justify-content-between'>
          <div>
            <div class='small text-muted fw-semibold'>$title</div>
            <div class='fs-5 fw-bold text-$class'>$value</div>
            $subHtml
          </div>
          <div class='text-$class' style='font-size:1.6rem; opacity:.9;'>
            <i class='$icon'></i>
          </div>
        </div>
      </div>
    </div>
  </div>";
}
?>

<div data-bloco="financeiro">

  <!-- AÇÕES -->
  <div class="d-flex flex-wrap gap-2 justify-content-end mb-2">
	  <?php if($pode_exportar): ?>
    <button type="button" class="btn btn-danger btn-sm"
      onclick="(function(){
        var f=document.getElementById('filtroFinanceiroForm');
        if(!f){ alert('Formulário de filtros não encontrado.'); return; }
        var params=new URLSearchParams(new FormData(f));
        window.open('pdf/gerar_dashboard_financeiro.php?'+params.toString(), '_blank', 'noopener');
      })();">
      <i class="fas fa-file-pdf me-1"></i> Exportar PDF
    </button>

    <button type="button" class="btn btn-success btn-sm"
      onclick="(function(){
        var f=document.getElementById('filtroFinanceiroForm');
        if(!f){ alert('Formulário de filtros não encontrado.'); return; }
        var params=new URLSearchParams(new FormData(f));
        window.open('excel/gerar_dashboard_financeiro.php?'+params.toString(), '_blank', 'noopener');
      })();">
      <i class="fas fa-file-excel me-1"></i> Exportar Excel
    </button>
	  <?php endif; ?>
  </div>

  <!-- ✅ FILTRO (COM COLLAPSE) -->
  <div class="card shadow-sm border-0 mb-3">
    <div class="card-body">

      <div class="d-flex justify-content-between align-items-center">
        <div class="fw-bold">
          <i class="fas fa-filter me-1 text-primary"></i> Filtros
        </div>
        <button type="button" class="btn btn-outline-primary btn-sm"
                data-bs-toggle="collapse" data-bs-target="#collapseFiltrosFinanceiro"
                aria-expanded="false" aria-controls="collapseFiltrosFinanceiro">
          <i class="fas fa-sliders-h me-1"></i> Mostrar/ocultar
        </button>
      </div>

      <div class="collapse mt-3" id="collapseFiltrosFinanceiro">
        <form id="filtroFinanceiroForm" class="row g-2 align-items-end" onsubmit="return false;">
          <div class="col-md-4">
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

          <div class="col-md-2">
            <label class="form-label fw-bold">Ano</label>
            <select name="ano" class="form-select">
              <option value="">Todos</option>
              <?php
                $ano_atual = (int)date('Y');
                for ($i=0; $i<10; $i++):
                  $a = $ano_atual - $i;
              ?>
                <option value="<?= $a ?>" <?= ((string)$filtro_ano === (string)$a) ? 'selected' : '' ?>><?= $a ?></option>
              <?php endfor; ?>
            </select>
          </div>

          <div class="col-md-3">
            <label class="form-label fw-bold">Categoria</label>
            <select name="categoria" class="form-select">
              <option value="">Todas</option>
              <option value="Peças" <?= ($filtro_categoria === 'Peças') ? 'selected' : '' ?>>Peças</option>
              <option value="Serviços" <?= ($filtro_categoria === 'Serviços') ? 'selected' : '' ?>>Serviços</option>
            </select>
          </div>

          <div class="col-md-2">
            <label class="form-label fw-bold">Restos</label>
            <select name="resto" class="form-select">
              <option value="" <?= ($filtro_resto === '') ? 'selected' : '' ?>>Todos</option>
              <option value="Sim" <?= ($filtro_resto === 'Sim') ? 'selected' : '' ?>>Sim</option>
              <option value="Não" <?= ($filtro_resto === 'Não') ? 'selected' : '' ?>>Não</option>
            </select>
          </div>

          <div class="col-md-1 d-flex gap-2">
            <button type="button" class="btn btn-primary w-100" title="Aplicar"
              onclick="(function(){
                const f=document.getElementById('filtroFinanceiroForm');
                f.dispatchEvent(new Event('submit', {cancelable:true, bubbles:true}));
              })();">
              <i class="fas fa-filter"></i>
            </button>
          </div>

          <div class="col-12">
            <button type="button" class="btn btn-outline-secondary btn-sm"
              onclick="(function(btn){
                const f=btn.closest('form');
                f.querySelectorAll('input,select').forEach(el=>{ if(el.name!=='batalhao'){ el.value=''; }});
                f.dispatchEvent(new Event('submit', {cancelable:true, bubbles:true}));
              })(this);">
              <i class="fas fa-eraser me-1"></i> Limpar
            </button>
          </div>
        </form>
      </div>

    </div>
  </div>

  <!-- ✅ KPIs -->
  <div class="row">
    <?= kpiCard('Total Empenhado', fmt($soma_total_empenhado), 'fas fa-coins', 'success', "Empenhos: <b>".number_format($totalRegistros)."</b>"); ?>
    <?= kpiCard('Total Liquidado', fmt($soma_liquidado), 'fas fa-check-circle', 'primary', pct($porc_liquidado)." do total"); ?>
    <?= kpiCard('Em Aberto', fmt($soma_nao_entregue + $soma_entregue + $soma_capeador), 'fas fa-hourglass-half', 'warning', "Req: ".pct($porc_req)." | Ent: ".pct($porc_entregue)." | Cap: ".pct($porc_capeador)); ?>
    <?= kpiCard('Saldo Real (Controle)', fmt($soma_saldo_real), 'fas fa-balance-scale', 'info', 'Empenhado − Utilizado'); ?>
    <?= kpiCard('Saldo SIAFI', fmt($soma_saldo_siafi), 'fas fa-university', 'secondary', 'Corrente + Restos'); ?>
    <?= kpiCard('Diferença', fmt($soma_diferenca), 'fas fa-exchange-alt', 'danger', 'SIAFI − Saldo Real'); ?>
  </div>

  <!-- ✅ RESUMO DETALHADO -->
  <div class="card shadow-sm border-0 mb-4">
    <div class="card-body">

      <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-2">
        <h6 class="mb-0 fw-bold">
          <i class="fas fa-file-invoice-dollar me-2 text-success"></i>Resumo Financeiro / Empenhos
        </h6>
        <span class="badge bg-light text-dark">
          Utilizado = Não entregue + Entregue + Capeador + Liquidado
        </span>
      </div>

      <div class="table-responsive">
        <table class="table table-sm table-hover align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th>Indicador</th>
              <th class="text-end">Valor</th>
            </tr>
          </thead>
          <tbody>
            <tr>
              <td class="fw-semibold">Total Empenhado</td>
              <td class="text-end fw-bold"><?= fmt($soma_total_empenhado) ?></td>
            </tr>
            <tr>
              <td class="text-danger fw-semibold">Não entregue</td>
              <td class="text-end"><?= fmt($soma_nao_entregue) ?></td>
            </tr>
            <tr>
              <td class="text-info fw-semibold">Entregue</td>
              <td class="text-end"><?= fmt($soma_entregue) ?></td>
            </tr>
            <tr>
              <td class="text-warning fw-semibold">Capeador</td>
              <td class="text-end"><?= fmt($soma_capeador) ?></td>
            </tr>
            <tr>
              <td class="text-success fw-semibold">Liquidado</td>
              <td class="text-end"><?= fmt($soma_liquidado) ?></td>
            </tr>

            <tr class="table-light">
              <td class="fw-bold">Total Utilizado</td>
              <td class="text-end fw-bold"><?= fmt($soma_total_utilizado) ?></td>
            </tr>

            <tr>
              <td class="text-primary fw-semibold">Saldo Real (Controle)</td>
              <td class="text-end"><?= fmt($soma_saldo_real) ?></td>
            </tr>
            <tr>
              <td class="text-muted fw-semibold">Saldo SIAFI (Corrente + Restos)</td>
              <td class="text-end"><?= fmt($soma_saldo_siafi) ?></td>
            </tr>
            <tr>
              <td class="text-danger fw-semibold">Diferença (SIAFI − Saldo Real)</td>
              <td class="text-end"><?= fmt($soma_diferenca) ?></td>
            </tr>
          </tbody>
        </table>
      </div>

      <?php
        $pReqEnt = $porc_req + $porc_entregue;
        $pCap    = $porc_capeador;
        $pLiq    = $porc_liquidado;

        $sumP = $pReqEnt + $pCap + $pLiq;
        if ($sumP > 100 && $sumP > 0) {
          $f = 100 / $sumP;
          $pReqEnt *= $f; $pCap *= $f; $pLiq *= $f;
        }
      ?>
      <div class="mt-3">
        <div class="small text-muted mb-1">Distribuição (sobre o total empenhado)</div>
        <div class="progress" style="height: 18px;">
          <div class="progress-bar bg-success" role="progressbar" style="width: <?= (float)$pLiq ?>%;">
            Liquidado <?= pct($porc_liquidado) ?>
          </div>
          <div class="progress-bar bg-warning text-dark" role="progressbar" style="width: <?= (float)$pCap ?>%;">
            Capeador <?= pct($porc_capeador) ?>
          </div>
          <div class="progress-bar bg-secondary" role="progressbar" style="width: <?= (float)$pReqEnt ?>%;">
            Req/Ent <?= pct($porc_req + $porc_entregue) ?>
          </div>
        </div>
      </div>

    </div>
  </div>

</div>

<script>
(function(){
  const form = document.getElementById('filtroFinanceiroForm');
  if(!form) return;

  if (form.dataset.bound === '1') return;
  form.dataset.bound = '1';

  form.addEventListener('submit', function(e){
    e.preventDefault();

    const params = new URLSearchParams(new FormData(form));
    const el = document.getElementById('dashFinanceiro');
    const endpoint = form.getAttribute('data-endpoint') || 'bloco_financeiro.php';

    if (el) {
      el.innerHTML = `
        <div class="card shadow-sm">
          <div class="card-body text-muted">
            <i class="fas fa-spinner fa-spin me-2"></i>Atualizando...
          </div>
        </div>`;
    }

    if (window.dashboardReload) {
      window.dashboardReload('financeiro', params);
      return;
    }

    fetch(endpoint + '?' + params.toString(), { credentials:'same-origin' })
      .then(r => r.text())
      .then(html => { if(el) el.innerHTML = html; })
      .catch(() => { if(el) el.innerHTML = "<div class='alert alert-danger'>Erro ao atualizar.</div>"; });
  });
})();
</script>