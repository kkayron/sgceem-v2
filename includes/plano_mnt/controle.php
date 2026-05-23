<?php
header('Content-Type: text/html; charset=utf-8');
session_start();
require_once '../../conexao/config.php';

// ============================
// OMs PERMITIDAS AO USUÁRIO
// ============================
$id_om_usuario = $_SESSION['usuario']['batalhao'] ?? 0;
$nivel_usuario = $_SESSION['usuario']['nivel'] ?? 3;

$batalhoesPermitidos = [];

if ($nivel_usuario == 1) {
    $sqlOms = "SELECT id FROM organizacoes_militares";
    $resOms = $conexao->query($sqlOms);
} elseif ($nivel_usuario == 2) {
    $sqlOms = "
        SELECT id FROM organizacoes_militares
        WHERE id = ?
        OR id IN (
            SELECT id_om_menor 
            FROM organizacoes_militares_sub 
            WHERE id_om_maior = ?
        )
    ";
    $stmtOms = $conexao->prepare($sqlOms);
    $stmtOms->bind_param("ii", $id_om_usuario, $id_om_usuario);
    $stmtOms->execute();
    $resOms = $stmtOms->get_result();
} else {
    $sqlOms = "SELECT id FROM organizacoes_militares WHERE id = ?";
    $stmtOms = $conexao->prepare($sqlOms);
    $stmtOms->bind_param("i", $id_om_usuario);
    $stmtOms->execute();
    $resOms = $stmtOms->get_result();
}

while ($om = $resOms->fetch_assoc()) {
    $batalhoesPermitidos[] = (int)$om['id'];
}

if (empty($batalhoesPermitidos)) {
    echo "<div class='alert alert-warning'>Nenhuma OM disponível para este usuário.</div>";
    exit;
}

// ============================
// GET / FILTROS
// ============================
$id_marca = $_GET['id_marca'] ?? '';
$id_modelo = $_GET['id_modelo'] ?? '';
$tipo_controle = $_GET['tipo_controle'] ?? '';
$status_alerta = $_GET['status_alerta'] ?? '';
$prefixo = trim($_GET['prefixo'] ?? '');
$descricao = trim($_GET['descricao'] ?? '');
$batalhao = $_GET['batalhao'] ?? '';

$limite = isset($_GET['limite']) && is_numeric($_GET['limite']) ? (int)$_GET['limite'] : 10;
$pagina = isset($_GET['pagina']) && is_numeric($_GET['pagina']) && $_GET['pagina'] > 0 ? (int)$_GET['pagina'] : 1;
$offset = ($pagina - 1) * $limite;

// ============================
// WHERE
// ============================
$filtros = [];
$params = [];
$tipos = '';

if ($id_marca !== '') {
    $filtros[] = "mp.id_marca = ?";
    $params[] = (int)$id_marca;
    $tipos .= 'i';
}

if ($id_modelo !== '') {
    $filtros[] = "mp.id_modelo = ?";
    $params[] = (int)$id_modelo;
    $tipos .= 'i';
}

if ($tipo_controle !== '') {
    $filtros[] = "mp.tipo_controle = ?";
    $params[] = $tipo_controle;
    $tipos .= 's';
}

if ($descricao !== '') {
    $filtros[] = "mp.descricao LIKE ?";
    $params[] = "%{$descricao}%";
    $tipos .= 's';
}

if ($prefixo !== '') {
    $filtros[] = "(f.prefixo_sga LIKE ? OR f.prefixo_velho LIKE ?)";
    $params[] = "%{$prefixo}%";
    $params[] = "%{$prefixo}%";
    $tipos .= 'ss';
}

if ($batalhao !== '') {
    $filtros[] = "f.batalhao = ?";
    $params[] = (int)$batalhao;
    $tipos .= 'i';
}

$placeholders = implode(',', array_fill(0, count($batalhoesPermitidos), '?'));
$filtros[] = "f.batalhao IN ($placeholders)";
$params = array_merge($params, $batalhoesPermitidos);
$tipos .= str_repeat('i', count($batalhoesPermitidos));

$filtros[] = "mp.ativo = 1";

$condicoes = 'WHERE ' . implode(' AND ', $filtros);

// ============================
// CONSULTA BASE
// ============================
$sql = "
    SELECT 
        mp.id AS id_plano,
        mp.descricao,
        mp.tipo_controle,
        mp.valor_inicial,
        mp.intervalo_valor,
        mp.intervalo_dias,
        mp.alerta_antes_valor,
        mp.alerta_antes_dias,

        cm.marca AS nome_marca,
        cmo.nome_modelo,

        f.id AS id_frota,
        f.prefixo_sga,
        f.prefixo_velho,
        f.disponibilidade,
        f.confiabilidade,
        f.status AS status_frota,
        f.status_odometro,
        f.batalhao,

        om.abreviatura AS om_abreviatura,
        om.nome AS om_nome,

        (
            SELECT cmed.odometro
            FROM controle_medicoes cmed
            WHERE cmed.viatura_id = f.id
            ORDER BY cmed.data DESC, cmed.id DESC
            LIMIT 1
        ) AS odometro_atual,

        (
            SELECT cmed.data
            FROM controle_medicoes cmed
            WHERE cmed.viatura_id = f.id
            ORDER BY cmed.data DESC, cmed.id DESC
            LIMIT 1
        ) AS data_medicao,

        (
            SELECT me.odometro_horimetro_execucao
            FROM mnt_execucoes me
            WHERE me.id_plano = mp.id
              AND me.id_frota = f.id
            ORDER BY me.data_execucao DESC, me.id DESC
            LIMIT 1
        ) AS ultima_execucao_valor,

        (
            SELECT me.data_execucao
            FROM mnt_execucoes me
            WHERE me.id_plano = mp.id
              AND me.id_frota = f.id
            ORDER BY me.data_execucao DESC, me.id DESC
            LIMIT 1
        ) AS ultima_execucao_data,

        (
            SELECT me.id_osprincipal
            FROM mnt_execucoes me
            WHERE me.id_plano = mp.id
              AND me.id_frota = f.id
            ORDER BY me.data_execucao DESC, me.id DESC
            LIMIT 1
        ) AS ultima_os

    FROM mnt_planos mp
    INNER JOIN config_marcas cm ON cm.id = mp.id_marca
    INNER JOIN config_modelos cmo ON cmo.id = mp.id_modelo
    INNER JOIN frota f ON f.marca = mp.id_marca AND f.modelo = mp.id_modelo
    LEFT JOIN organizacoes_militares om ON om.id = f.batalhao
    $condicoes
    ORDER BY f.prefixo_sga ASC, mp.descricao ASC
";

$stmt = $conexao->prepare($sql);
$stmt->bind_param($tipos, ...$params);
$stmt->execute();
$res = $stmt->get_result();

// ============================
// FUNÇÕES
// ============================
function fmtNum($v) {
    if ($v === null || $v === '') return '—';
    return number_format((float)$v, 2, ',', '.');
}

function diasEntreHoje($data) {
    if (!$data) return null;
    $hoje = new DateTime(date('Y-m-d'));
    $d = new DateTime($data);
    return (int)$hoje->diff($d)->format('%r%a');
}

function labelTipoControle($tipo) {
    return match ($tipo) {
        'odometro' => 'Odômetro',
        'horimetro' => 'Horímetro',
        'tempo' => 'Tempo',
        'odometro_tempo' => 'Odômetro + Tempo',
        'horimetro_tempo' => 'Horímetro + Tempo',
        default => '—'
    };
}

function calcularStatusMnt($row) {
    $tipo = $row['tipo_controle'];

    $usaValor = in_array($tipo, ['odometro', 'horimetro', 'odometro_tempo', 'horimetro_tempo']);
    $usaTempo = in_array($tipo, ['tempo', 'odometro_tempo', 'horimetro_tempo']);

    $statusFinal = 'Em dia';
    $classeFinal = 'success';
    $mensagem = [];

    $proximaValor = null;
    $faltaValor = null;
    $proximaData = null;
    $faltaDias = null;

    if ($usaValor) {
        $atual = is_numeric($row['odometro_atual']) ? (float)$row['odometro_atual'] : null;
        $intervalo = is_numeric($row['intervalo_valor']) ? (float)$row['intervalo_valor'] : null;
        $base = is_numeric($row['ultima_execucao_valor']) ? (float)$row['ultima_execucao_valor'] : null;

        if ($base === null && is_numeric($row['valor_inicial'])) {
            $proximaValor = (float)$row['valor_inicial'];
        } elseif ($base !== null && $intervalo !== null) {
            $proximaValor = $base + $intervalo;
        }

        if ($atual === null) {
            $statusFinal = 'Sem medição';
            $classeFinal = 'secondary';
            $mensagem[] = 'Sem odômetro/horímetro atual';
        } elseif ($proximaValor === null) {
            $statusFinal = 'Sem histórico';
            $classeFinal = 'secondary';
            $mensagem[] = 'Sem base para cálculo';
        } else {
            $faltaValor = $proximaValor - $atual;
            $alertaValor = is_numeric($row['alerta_antes_valor']) ? (float)$row['alerta_antes_valor'] : 0;

            if ($faltaValor < 0) {
                $statusFinal = 'Vencida';
                $classeFinal = 'danger';
                $mensagem[] = 'Vencida há ' . fmtNum(abs($faltaValor));
            } elseif ($faltaValor <= $alertaValor) {
                if ($statusFinal !== 'Vencida') {
                    $statusFinal = 'Próxima';
                    $classeFinal = 'warning';
                }
                $mensagem[] = 'Faltam ' . fmtNum($faltaValor);
            } else {
                $mensagem[] = 'Faltam ' . fmtNum($faltaValor);
            }
        }
    }

    if ($usaTempo) {
        $intervaloDias = is_numeric($row['intervalo_dias']) ? (int)$row['intervalo_dias'] : null;
        $ultimaData = $row['ultima_execucao_data'] ?? null;

        if (!$ultimaData || !$intervaloDias) {
            if ($statusFinal === 'Em dia') {
                $statusFinal = 'Sem histórico';
                $classeFinal = 'secondary';
            }
            $mensagem[] = 'Sem data de última execução';
        } else {
            $proximaData = date('Y-m-d', strtotime($ultimaData . " +{$intervaloDias} days"));
            $faltaDias = diasEntreHoje($proximaData);
            $alertaDias = is_numeric($row['alerta_antes_dias']) ? (int)$row['alerta_antes_dias'] : 0;

            if ($faltaDias < 0) {
                $statusFinal = 'Vencida';
                $classeFinal = 'danger';
                $mensagem[] = 'Vencida há ' . abs($faltaDias) . ' dias';
            } elseif ($faltaDias <= $alertaDias && $statusFinal !== 'Vencida') {
                $statusFinal = 'Próxima';
                $classeFinal = 'warning';
                $mensagem[] = 'Faltam ' . $faltaDias . ' dias';
            } else {
                $mensagem[] = 'Faltam ' . $faltaDias . ' dias';
            }
        }
    }

    return [
        'status' => $statusFinal,
        'classe' => $classeFinal,
        'mensagem' => implode(' | ', $mensagem),
        'proxima_valor' => $proximaValor,
        'falta_valor' => $faltaValor,
        'proxima_data' => $proximaData,
        'falta_dias' => $faltaDias
    ];
}

// ============================
// MONTA RESULTADOS + FILTRA STATUS
// ============================
$linhas = [];

while ($row = $res->fetch_assoc()) {
    $calc = calcularStatusMnt($row);
    $row['calc'] = $calc;

    if ($status_alerta !== '' && $calc['status'] !== $status_alerta) {
        continue;
    }

    $linhas[] = $row;
}

$totalRegistros = count($linhas);
$totalPaginas = max(1, ceil($totalRegistros / $limite));
$linhasPagina = array_slice($linhas, $offset, $limite);

// ============================
// MARCAS / MODELOS / OMS FILTRO
// ============================
$marcas = [];
$resMarcas = $conexao->query("SELECT id, marca FROM config_marcas ORDER BY marca");
while ($m = $resMarcas->fetch_assoc()) $marcas[] = $m;

$modelos = [];
$resModelos = $conexao->query("
    SELECT mo.id, mo.id_marca, mo.nome_modelo, ma.marca
    FROM config_modelos mo
    INNER JOIN config_marcas ma ON ma.id = mo.id_marca
    ORDER BY ma.marca, mo.nome_modelo
");
while ($m = $resModelos->fetch_assoc()) $modelos[] = $m;

$oms = [];
if (!empty($batalhoesPermitidos)) {
    $phOms = implode(',', array_fill(0, count($batalhoesPermitidos), '?'));
    $sqlListaOms = "
        SELECT id, nome, abreviatura
        FROM organizacoes_militares
        WHERE id IN ($phOms)
        ORDER BY nome
    ";
    $stmtListaOms = $conexao->prepare($sqlListaOms);
    $tiposOms = str_repeat('i', count($batalhoesPermitidos));
    $stmtListaOms->bind_param($tiposOms, ...$batalhoesPermitidos);
    $stmtListaOms->execute();
    $resListaOms = $stmtListaOms->get_result();

    while ($om = $resListaOms->fetch_assoc()) {
        $oms[] = $om;
    }
}

// ============================
// PAGINAÇÃO
// ============================
function renderPaginacaoControleMnt($pagina, $totalPaginas, $limite, $queryString, $arquivo = 'includes/plano_mnt/controle.php') {
    if ($totalPaginas <= 1) return '';

    $qs = trim($queryString);
    $qsPrefix = ($qs !== '') ? ($qs . '&') : '';

    $html  = '<div class="pagination-wrapper">';
    $html .= '<nav><ul class="pagination pagination-sm justify-content-center">';

    if ($pagina > 1) {
        $url = "{$arquivo}?{$qsPrefix}pagina=1&limite={$limite}";
        $html .= "<li class='page-item'><a class='page-link paginacao-controle-mnt' href='#' data-page='{$url}'>&laquo;</a></li>";

        $url = "{$arquivo}?{$qsPrefix}pagina=" . ($pagina - 1) . "&limite={$limite}";
        $html .= "<li class='page-item'><a class='page-link paginacao-controle-mnt' href='#' data-page='{$url}'>&lsaquo;</a></li>";
    }

    $inicio = max(1, $pagina - 4);
    $fim = min($totalPaginas, $pagina + 4);

    for ($i = $inicio; $i <= $fim; $i++) {
        $active = $i == $pagina ? 'active' : '';
        $url = "{$arquivo}?{$qsPrefix}pagina={$i}&limite={$limite}";
        $html .= "<li class='page-item {$active}'><a class='page-link paginacao-controle-mnt' href='#' data-page='{$url}'>{$i}</a></li>";
    }

    if ($pagina < $totalPaginas) {
        $url = "{$arquivo}?{$qsPrefix}pagina=" . ($pagina + 1) . "&limite={$limite}";
        $html .= "<li class='page-item'><a class='page-link paginacao-controle-mnt' href='#' data-page='{$url}'>&rsaquo;</a></li>";

        $url = "{$arquivo}?{$qsPrefix}pagina={$totalPaginas}&limite={$limite}";
        $html .= "<li class='page-item'><a class='page-link paginacao-controle-mnt' href='#' data-page='{$url}'>&raquo;</a></li>";
    }

    $html .= '</ul></nav></div>';
    return $html;
}

$paramsGET = $_GET;
unset($paramsGET['pagina']);
$queryString = http_build_query($paramsGET);
?>

<div class="container">
  <div class="page-inner">

    <div class="d-flex justify-content-between align-items-center py-3">
      <div>
        <h3 class="fw-bold mb-1">Controle de Manutenções Agendadas</h3>
        <h6 class="text-muted">Alertas calculados com base nos planos, frota, medições e ordens de serviço</h6>
      </div>
		  <!-- BARRA AÇÕES -->
  <div class="d-flex flex-wrap gap-2 justify-content-end mb-2">
<button type="button" class="btn btn-success btn-sm"
  onclick="(function(){
    var f = document.getElementById('filtroControleMntForm');
    if (!f) {
      alert('Formulário de filtros não encontrado.');
      return;
    }

    var params = new URLSearchParams(new FormData(f));
    window.open('excel/gerar_calendario_mnt.php?' + params.toString(), '_blank', 'noopener');
  })();">
  <i class="fas fa-file-excel me-1"></i> Exportar Calendário
</button>
		</div>
    </div>

    <div class="card">
      <div class="card-body">

        <div class="mb-3">
          <button class="btn btn-outline-primary w-100 d-flex justify-content-center align-items-center" onclick="toggleFiltrosControleMnt()">
            <i class="fas fa-search me-2"></i> Filtros
          </button>
        </div>

        <div id="filtros-container-controle-mnt" style="display:none;" class="mb-3">
          <div class="card shadow-sm border-0">
            <div class="card-body">
              <form method="GET" id="filtroControleMntForm">
                <div class="row g-3">

                  <div class="col-md-3">
                    <label class="form-label fw-semibold">OM</label>
                    <select name="batalhao" class="form-select">
                      <option value="">Todas</option>
                      <?php foreach ($oms as $om): ?>
                        <option value="<?= (int)$om['id'] ?>" <?= ($batalhao == $om['id']) ? 'selected' : '' ?>>
                          <?= htmlspecialchars(($om['abreviatura'] ?: $om['nome'])) ?>
                        </option>
                      <?php endforeach; ?>
                    </select>
                  </div>

                  <div class="col-md-3">
                    <label class="form-label fw-semibold">Marca</label>
                    <select name="id_marca" class="form-select">
                      <option value="">Todas</option>
                      <?php foreach ($marcas as $m): ?>
                        <option value="<?= (int)$m['id'] ?>" <?= ($id_marca == $m['id']) ? 'selected' : '' ?>>
                          <?= htmlspecialchars($m['marca']) ?>
                        </option>
                      <?php endforeach; ?>
                    </select>
                  </div>

                  <div class="col-md-3">
                    <label class="form-label fw-semibold">Modelo</label>
                    <select name="id_modelo" class="form-select">
                      <option value="">Todos</option>
                      <?php foreach ($modelos as $m): ?>
                        <option value="<?= (int)$m['id'] ?>" <?= ($id_modelo == $m['id']) ? 'selected' : '' ?>>
                          <?= htmlspecialchars($m['marca'] . ' - ' . $m['nome_modelo']) ?>
                        </option>
                      <?php endforeach; ?>
                    </select>
                  </div>

                  <div class="col-md-3">
                    <label class="form-label fw-semibold">Status</label>
                    <select name="status_alerta" class="form-select">
                      <option value="">Todos</option>
                      <?php foreach (['Vencida', 'Próxima', 'Em dia', 'Sem histórico', 'Sem medição'] as $st): ?>
                        <option value="<?= $st ?>" <?= ($status_alerta === $st) ? 'selected' : '' ?>>
                          <?= $st ?>
                        </option>
                      <?php endforeach; ?>
                    </select>
                  </div>

                  <div class="col-md-3">
                    <label class="form-label fw-semibold">Tipo Controle</label>
                    <select name="tipo_controle" class="form-select">
                      <option value="">Todos</option>
                      <option value="odometro" <?= $tipo_controle === 'odometro' ? 'selected' : '' ?>>Odômetro</option>
                      <option value="horimetro" <?= $tipo_controle === 'horimetro' ? 'selected' : '' ?>>Horímetro</option>
                      <option value="tempo" <?= $tipo_controle === 'tempo' ? 'selected' : '' ?>>Tempo</option>
                      <option value="odometro_tempo" <?= $tipo_controle === 'odometro_tempo' ? 'selected' : '' ?>>Odômetro + Tempo</option>
                      <option value="horimetro_tempo" <?= $tipo_controle === 'horimetro_tempo' ? 'selected' : '' ?>>Horímetro + Tempo</option>
                    </select>
                  </div>

                  <div class="col-md-3">
                    <label class="form-label fw-semibold">Prefixo</label>
                    <input type="text" name="prefixo" class="form-control" value="<?= htmlspecialchars($prefixo) ?>">
                  </div>

                  <div class="col-md-6">
                    <label class="form-label fw-semibold">Descrição do Plano</label>
                    <input type="text" name="descricao" class="form-control" value="<?= htmlspecialchars($descricao) ?>">
                  </div>

                  <div class="col-12 d-flex justify-content-between">
                    <button type="button" id="btnLimparFiltrosControleMnt" class="btn btn-black">Limpar Filtros</button>
                    <button type="submit" class="btn btn-primary px-4">Aplicar</button>
                  </div>

                </div>
              </form>
            </div>
          </div>
        </div>

        <div class="mb-3">
          <label for="limiteControleMnt" class="me-2 mb-0">Mostrar</label>
          <select id="limiteControleMnt" class="form-select d-inline w-auto">
            <?php foreach ([5, 10, 25, 50, 100] as $op): ?>
              <option value="<?= $op ?>" <?= $limite == $op ? 'selected' : '' ?>><?= $op ?></option>
            <?php endforeach; ?>
          </select>
          <span class="ms-2">por página</span>
        </div>

        <div class="paginacao">
          <?= renderPaginacaoControleMnt($pagina, $totalPaginas, $limite, $queryString); ?>
        </div>

        <?php
$totalVencidas = 0;
$totalProximas = 0;
$totalEmDia = 0;
$totalSemHistorico = 0;

foreach ($linhas as $l) {
    $st = $l['calc']['status'] ?? '';

    if ($st === 'Vencida') $totalVencidas++;
    elseif ($st === 'Próxima') $totalProximas++;
    elseif ($st === 'Em dia') $totalEmDia++;
    else $totalSemHistorico++;
}
?>

<div class="controle-mnt-resumo mb-3">
  <div class="resumo-card resumo-danger">
    <small>Vencidas</small>
    <strong><?= $totalVencidas ?></strong>
  </div>

  <div class="resumo-card resumo-warning">
    <small>Próximas</small>
    <strong><?= $totalProximas ?></strong>
  </div>

  <div class="resumo-card resumo-success">
    <small>Em dia</small>
    <strong><?= $totalEmDia ?></strong>
  </div>

  <div class="resumo-card resumo-secondary">
    <small>Sem histórico/medição</small>
    <strong><?= $totalSemHistorico ?></strong>
  </div>
</div>

<div class="lista-controle-mnt">

<?php if (empty($linhasPagina)): ?>
  <div class="alert alert-info mb-0">
    Nenhum alerta de manutenção encontrado.
  </div>
<?php endif; ?>

<?php foreach ($linhasPagina as $row): ?>
  <?php
    $calc = $row['calc'];
    $omNome = $row['om_abreviatura'] ?: $row['om_nome'] ?: '—';

    $classeItem = match ($calc['status']) {
        'Vencida' => 'controle-mnt-vencida',
        'Próxima' => 'controle-mnt-proxima',
        'Sem histórico', 'Sem medição' => 'controle-mnt-sem-historico',
        default => 'controle-mnt-ok'
    };

    $iconeStatus = match ($calc['status']) {
        'Vencida' => 'fas fa-exclamation-triangle',
        'Próxima' => 'fas fa-clock',
        'Em dia' => 'fas fa-check-circle',
        default => 'fas fa-info-circle'
    };

    $labelTipo = labelTipoControle($row['tipo_controle']);

    $atual = is_numeric($row['odometro_atual']) ? (float)$row['odometro_atual'] : null;
    $ultima = is_numeric($row['ultima_execucao_valor']) ? (float)$row['ultima_execucao_valor'] : null;
    $proxima = is_numeric($calc['proxima_valor']) ? (float)$calc['proxima_valor'] : null;

    $percentual = null;
    if ($atual !== null && $ultima !== null && $proxima !== null && $proxima > $ultima) {
        $percentual = (($atual - $ultima) / ($proxima - $ultima)) * 100;
        $percentual = max(0, min(100, $percentual));
    } elseif ($atual !== null && $proxima !== null && $ultima === null && $proxima > 0) {
        $percentual = ($atual / $proxima) * 100;
        $percentual = max(0, min(100, $percentual));
    }

    $progressClass = match ($calc['status']) {
        'Vencida' => 'bg-danger',
        'Próxima' => 'bg-warning',
        'Em dia' => 'bg-success',
        default => 'bg-secondary'
    };

    $prefixo = $row['prefixo_sga'] ?: $row['prefixo_velho'] ?: 'Sem prefixo';
  ?>

  <div class="controle-mnt-card <?= $classeItem ?>">

    <div class="controle-mnt-prioridade">
      <div class="prioridade-icone bg-<?= $calc['classe'] ?>">
        <i class="<?= $iconeStatus ?>"></i>
      </div>
    </div>

    <div class="controle-mnt-main">

      <div class="controle-mnt-header">
        <div>
          <h6 class="controle-mnt-title">
            <?= htmlspecialchars($prefixo) ?>
            <span><?= htmlspecialchars($row['descricao']) ?></span>
          </h6>

          <div class="controle-mnt-meta">
            <span>
              <i class="fas fa-industry me-1"></i>
              <?= htmlspecialchars($row['nome_marca']) ?> / <?= htmlspecialchars($row['nome_modelo']) ?>
            </span>

            <span>
              <i class="fas fa-building me-1"></i>
              <?= htmlspecialchars($omNome) ?>
            </span>

            <span>
              <i class="fas fa-layer-group me-1"></i>
              <?= htmlspecialchars($labelTipo) ?>
            </span>
          </div>
        </div>

        <div class="controle-mnt-status-area">
          <span class="badge bg-<?= $calc['classe'] ?> status-alerta">
            <?= htmlspecialchars($calc['status']) ?>
          </span>

          <?php if (!empty($row['ultima_os'])): ?>
            <span class="badge bg-light text-dark border">
              Última OS #<?= (int)$row['ultima_os'] ?>
            </span>
          <?php else: ?>
            <span class="badge bg-light text-dark border">
              Sem OS
            </span>
          <?php endif; ?>
        </div>
      </div>

      <div class="controle-mnt-grid">

        <div class="info-box">
          <small>Atual</small>
          <strong><?= fmtNum($row['odometro_atual']) ?></strong>
          <span>
            <?= $row['data_medicao'] ? date('d/m/Y', strtotime($row['data_medicao'])) : 'Sem medição' ?>
          </span>
        </div>

        <div class="info-box">
          <small>Última execução</small>
          <strong><?= fmtNum($row['ultima_execucao_valor']) ?></strong>
          <span>
            <?= $row['ultima_execucao_data'] ? date('d/m/Y', strtotime($row['ultima_execucao_data'])) : 'Sem histórico' ?>
          </span>
        </div>

        <div class="info-box">
          <small>Próxima por valor</small>
          <strong><?= fmtNum($calc['proxima_valor']) ?></strong>
          <span>
            <?= $calc['falta_valor'] !== null ? 'Faltam ' . fmtNum($calc['falta_valor']) : '—' ?>
          </span>
        </div>

        <div class="info-box">
          <small>Próxima por data</small>
          <strong>
            <?= $calc['proxima_data'] ? date('d/m/Y', strtotime($calc['proxima_data'])) : '—' ?>
          </strong>
          <span>
            <?= $calc['falta_dias'] !== null ? $calc['falta_dias'] . ' dias' : '—' ?>
          </span>
        </div>

        <div class="info-box destaque">
          <small>Disponibilidade</small>
          <strong><?= htmlspecialchars($row['disponibilidade'] ?: '—') ?></strong>
          <span><?= htmlspecialchars($row['status_frota'] ?: 'Status não informado') ?></span>
        </div>

      </div>

      <div class="controle-mnt-progresso">
        <div class="d-flex justify-content-between align-items-center mb-1">
          <small class="text-muted fw-semibold">Progresso até a próxima manutenção</small>
          <small class="text-muted">
            <?= $percentual !== null ? number_format($percentual, 0, ',', '.') . '%' : 'Sem cálculo' ?>
          </small>
        </div>

        <div class="progress" style="height: 8px;">
          <div class="progress-bar <?= $progressClass ?>"
               role="progressbar"
               style="width: <?= $percentual !== null ? $percentual : 0 ?>%;">
          </div>
        </div>
      </div>

      <?php if (!empty($calc['mensagem'])): ?>
        <div class="controle-mnt-alerta mt-2">
          <i class="<?= $iconeStatus ?> me-1"></i>
          <?= htmlspecialchars($calc['mensagem']) ?>
        </div>
      <?php endif; ?>

    </div>

    <div class="controle-mnt-actions">
      <button type="button"
              class="btn btn-sm btn-outline-primary"
              onclick="verPlanoMntControle(<?= (int)$row['id_plano'] ?>, <?= (int)$row['id_frota'] ?>)"
              data-bs-toggle="modal"
              data-bs-target="#modalVerPlanoMntControle">
        <i class="fas fa-eye me-1"></i> Ver
      </button>

      <button type="button"
              class="btn btn-sm btn-outline-success"
              onclick="abrirOSPlanoMnt?.(<?= (int)$row['id_frota'] ?>, <?= (int)$row['id_plano'] ?>)">
        <i class="fas fa-tools me-1"></i> OS
      </button>
    </div>

  </div>

<?php endforeach; ?>

</div>
		  
        <div class="paginacao mt-3">
          <?= renderPaginacaoControleMnt($pagina, $totalPaginas, $limite, $queryString); ?>
        </div>

      </div>
    </div>
  </div>
</div>

<style>
	.controle-mnt-resumo {
  display: grid;
  grid-template-columns: repeat(4, minmax(140px, 1fr));
  gap: .75rem;
}

.resumo-card {
  padding: .9rem 1rem;
  border-radius: 1rem;
  background: #fff;
  border: 1px solid #e9ecef;
  box-shadow: 0 .25rem .75rem rgba(0,0,0,.04);
}

.resumo-card small {
  display: block;
  color: #6c757d;
  font-size: .75rem;
  text-transform: uppercase;
  letter-spacing: .04em;
  font-weight: 700;
}

.resumo-card strong {
  display: block;
  font-size: 1.55rem;
  line-height: 1;
  margin-top: .35rem;
}

.resumo-danger {
  border-left: 5px solid #dc3545;
}

.resumo-warning {
  border-left: 5px solid #ffc107;
}

.resumo-success {
  border-left: 5px solid #198754;
}

.resumo-secondary {
  border-left: 5px solid #6c757d;
}

.lista-controle-mnt {
  display: flex;
  flex-direction: column;
  gap: .85rem;
}

.controle-mnt-card {
  display: grid;
  grid-template-columns: auto minmax(0, 1fr) auto;
  gap: 1rem;
  align-items: stretch;
  padding: 1rem;
  background: #fff;
  border: 1px solid #e9ecef;
  border-radius: 1.1rem;
  box-shadow: 0 .25rem .75rem rgba(0,0,0,.04);
  transition: .2s ease-in-out;
}

.controle-mnt-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 .5rem 1.2rem rgba(0,0,0,.08);
}

.controle-mnt-vencida {
  border-left: 6px solid #dc3545;
  background: #fff5f5;
}

.controle-mnt-proxima {
  border-left: 6px solid #ffc107;
  background: #fffaf0;
}

.controle-mnt-ok {
  border-left: 6px solid #198754;
}

.controle-mnt-sem-historico {
  border-left: 6px solid #6c757d;
  background: #f8f9fa;
}

.controle-mnt-prioridade {
  display: flex;
  align-items: flex-start;
}

.prioridade-icone {
  width: 42px;
  height: 42px;
  border-radius: 999px;
  display: grid;
  place-items: center;
  color: #fff;
  font-size: 1rem;
}

.controle-mnt-main {
  min-width: 0;
}

.controle-mnt-header {
  display: flex;
  justify-content: space-between;
  gap: .75rem;
  align-items: flex-start;
  margin-bottom: .75rem;
}

.controle-mnt-title {
  margin: 0;
  font-weight: 800;
  color: #0d6efd;
  line-height: 1.25;
}

.controle-mnt-title span {
  color: #212529;
  font-weight: 700;
}

.controle-mnt-meta {
  display: flex;
  flex-wrap: wrap;
  gap: .55rem 1rem;
  margin-top: .35rem;
  color: #6c757d;
  font-size: .8rem;
}

.controle-mnt-status-area {
  display: flex;
  flex-wrap: wrap;
  justify-content: flex-end;
  gap: .35rem;
}

.status-alerta {
  font-size: .78rem;
  padding: .45rem .65rem;
}

.controle-mnt-grid {
  display: grid;
  grid-template-columns: repeat(5, minmax(120px, 1fr));
  gap: .65rem;
}

.info-box {
  padding: .65rem .75rem;
  background: rgba(255,255,255,.75);
  border: 1px solid #edf0f2;
  border-radius: .85rem;
}

.info-box small {
  display: block;
  color: #6c757d;
  font-size: .68rem;
  text-transform: uppercase;
  letter-spacing: .04em;
  font-weight: 700;
  margin-bottom: .2rem;
}

.info-box strong {
  display: block;
  color: #212529;
  font-size: .95rem;
  font-weight: 800;
  line-height: 1.2;
}

.info-box span {
  display: block;
  color: #6c757d;
  font-size: .74rem;
  margin-top: .15rem;
}

.info-box.destaque {
  background: #f8fbff;
}

.controle-mnt-progresso {
  margin-top: .8rem;
}

.controle-mnt-alerta {
  display: inline-flex;
  align-items: center;
  width: fit-content;
  padding: .35rem .65rem;
  border-radius: 999px;
  background: rgba(255,255,255,.75);
  border: 1px solid #e9ecef;
  color: #495057;
  font-size: .78rem;
  font-weight: 700;
}

.controle-mnt-actions {
  display: grid;
  grid-template-columns: 1fr;
  gap: .45rem;
  align-content: start;
  min-width: 90px;
}

.controle-mnt-actions .btn {
  white-space: nowrap;
}

@media (max-width: 1200px) {
  .controle-mnt-card {
    grid-template-columns: auto minmax(0, 1fr);
  }

  .controle-mnt-actions {
    grid-column: 2 / 3;
    grid-template-columns: repeat(2, auto);
    justify-content: start;
  }

  .controle-mnt-grid {
    grid-template-columns: repeat(3, minmax(120px, 1fr));
  }
}

@media (max-width: 768px) {
  .controle-mnt-resumo {
    grid-template-columns: 1fr 1fr;
  }

  .controle-mnt-card {
    grid-template-columns: 1fr;
  }

  .controle-mnt-prioridade {
    display: none;
  }

  .controle-mnt-header {
    flex-direction: column;
  }

  .controle-mnt-status-area {
    justify-content: flex-start;
  }

  .controle-mnt-grid {
    grid-template-columns: 1fr 1fr;
  }

  .controle-mnt-actions {
    grid-column: auto;
    grid-template-columns: 1fr 1fr;
  }

  .controle-mnt-actions .btn {
    width: 100%;
  }
}

@media (max-width: 480px) {
  .controle-mnt-resumo,
  .controle-mnt-grid,
  .controle-mnt-actions {
    grid-template-columns: 1fr;
  }
}
</style>

<!-- Modal Ver Plano pelo Controle de Manutenção -->
<div class="modal fade" id="modalVerPlanoMntControle" tabindex="-1" aria-labelledby="modalVerPlanoMntControleLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content">

      <div class="modal-header">
        <h5 class="modal-title" id="modalVerPlanoMntControleLabel">
          Detalhes do Plano de Manutenção
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
      </div>

      <div class="modal-body">
        <div id="conteudo-ver-plano-mnt-controle">
          <div class="text-center py-5">
            <div class="spinner-border text-primary" role="status"></div>
            <p class="mt-3 text-muted">Carregando dados do plano...</p>
          </div>
        </div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
          Fechar
        </button>
      </div>

    </div>
  </div>
</div>

<script>
    window.funcaoInicializacao = 'inicializarControleMnt';    
</script>