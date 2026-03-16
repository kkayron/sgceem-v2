<?php
header('Content-Type: text/html; charset=utf-8');
session_start();
require_once '../../conexao/config.php';

// ============================
// BATALHÕES PERMITIDOS AO USUÁRIO
// ============================
$id_om_usuario = $_SESSION['usuario']['batalhao'] ?? 0;

// Busca o nível do usuário
$sqlNivel = "SELECT nivel FROM organizacoes_militares WHERE id = $id_om_usuario";
$resNivel = $conexao->query($sqlNivel);
$nivelUsuario = $resNivel->fetch_assoc()['nivel'] ?? 0;

// Monta lista de OMs que ele pode visualizar
if ($nivelUsuario == 1) {
    $sqlBatalhoes = "SELECT id FROM organizacoes_militares";
} else {
    $sqlBatalhoes = "
        SELECT om.id
        FROM organizacoes_militares om
        WHERE om.id = $id_om_usuario
        OR om.id IN (
            SELECT id_om_menor FROM organizacoes_militares_sub WHERE id_om_maior = $id_om_usuario
        )
    ";
}
$resBatalhoes = $conexao->query($sqlBatalhoes);
$batalhoesPermitidos = [];
while ($bat = $resBatalhoes->fetch_assoc()) {
    $batalhoesPermitidos[] = (int)$bat['id'];
}

if (empty($batalhoesPermitidos)) {
    echo "<div class='alert alert-warning'>Nenhum batalhão disponível para este usuário.</div>";
    exit;
}

// ============================
// FILTROS VIA GET
// ============================
$id             = $_GET['id'] ?? '';
$tipo           = $_GET['tipo'] ?? '';
$modelo         = $_GET['modelo'] ?? '';
$marca          = $_GET['marca'] ?? '';
$ativo          = $_GET['ativo'] ?? '';
$prefixo_sga    = $_GET['prefixo_sga'] ?? '';
$statusFicha    = $_GET['status'] ?? '';
$statusViatura  = $_GET['situacao_viatura'] ?? '';
$origem         = $_GET['origem'] ?? '';
$destino        = $_GET['destino'] ?? '';
$data_ini       = $_GET['data_ini'] ?? '';
$batalhaoFiltro = $_GET['batalhao'] ?? '';

// ============================
// LIMITES E PAGINAÇÃO
// ============================
$limite = isset($_GET['limite']) && is_numeric($_GET['limite']) ? (int)$_GET['limite'] : 10;
$pagina = isset($_GET['pagina']) && is_numeric($_GET['pagina']) && $_GET['pagina'] > 0 ? (int)$_GET['pagina'] : 1;
$offset = ($pagina - 1) * $limite;

// ============================
// MONTAGEM DOS FILTROS SQL
// ============================
$filtrosSQL = [];
$tipos = '';
$params = [];

if ($tipo) {
    $filtrosSQL[] = "f.tipo LIKE ?";
    $params[] = "%{$tipo}%";
    $tipos .= 's';
}
if ($modelo) {
    $filtrosSQL[] = "m.nome_modelo LIKE ?";
    $params[] = "%{$modelo}%";
    $tipos .= 's';
}
if ($ativo) {
    $filtrosSQL[] = "f.ativo LIKE ?";
    $params[] = "%{$ativo}%";
    $tipos .= 's';
}
if ($marca) {
    $filtrosSQL[] = "mc.marca LIKE ?";
    $params[] = "%{$marca}%";
    $tipos .= 's';
}
if ($prefixo_sga) {
    $filtrosSQL[] = "f.prefixo_sga LIKE ?";
    $params[] = "%{$prefixo_sga}%";
    $tipos .= 's';
}

// 🔹 Filtro de batalhão (se definido)
if ($batalhaoFiltro && in_array((int)$batalhaoFiltro, $batalhoesPermitidos)) {
    $filtrosSQL[] = "f.batalhao = ?";
    $params[] = (int)$batalhaoFiltro;
    $tipos .= 'i';
} else {
    // Filtra apenas os batalhões permitidos
    $placeholders = implode(',', array_fill(0, count($batalhoesPermitidos), '?'));
    $filtrosSQL[] = "f.batalhao IN ($placeholders)";
    $params = array_merge($params, $batalhoesPermitidos);
    $tipos .= str_repeat('i', count($batalhoesPermitidos));
}

$whereSQL = $filtrosSQL ? 'WHERE ' . implode(' AND ', $filtrosSQL) : '';

// ============================
// CONTAGEM TOTAL
// ============================
$sqlCount = "
    SELECT COUNT(*) AS total
    FROM frota f
    LEFT JOIN config_modelos m ON f.modelo = m.id
    LEFT JOIN config_marcas mc ON f.marca = mc.id
    $whereSQL
";
$stmtCount = $conexao->prepare($sqlCount);
$stmtCount->bind_param($tipos, ...$params);
$stmtCount->execute();
$totalRegistros = $stmtCount->get_result()->fetch_assoc()['total'];
$stmtCount->close();
$totalPaginas = ceil($totalRegistros / $limite);

// ============================
// CONSULTA PRINCIPAL (com JOIN)
// ============================
$sqlFrota = "
    SELECT 
        f.id, 
        f.prefixo_sga, 
        f.tipo, 
        f.ativo, 
        f.batalhao,
        mc.marca AS nome_marca,
        m.nome_modelo AS nome_modelo
    FROM frota f
    LEFT JOIN config_modelos m ON f.modelo = m.id
    LEFT JOIN config_marcas mc ON f.marca = mc.id
    $whereSQL
    ORDER BY f.prefixo_sga ASC
    LIMIT ? OFFSET ?
";
$params[] = $limite;
$params[] = $offset;
$tipos .= 'ii';

$stmtFrota = $conexao->prepare($sqlFrota);
$stmtFrota->bind_param($tipos, ...$params);
$stmtFrota->execute();
$resViaturas = $stmtFrota->get_result();

$viaturas = [];
while ($vtr = $resViaturas->fetch_assoc()) {
    $viaturas[] = $vtr;
}
$stmtFrota->close();

// ============================
// CALCULA SITUAÇÃO DA VIATURA
// ============================
function calcularSituacaoViatura($ficha) {
    if (!$ficha || empty($ficha['status'])) return 'A disposição';
    if ($ficha['status'] === 'Encerrada') return 'A disposição';
    if ($ficha['status'] === 'Aberta') {
        $hoje = date('Y-m-d');
        if (!empty($ficha['data_prevista']) && $ficha['data_prevista'] < $hoje) {
            return 'Verificar se já retornou';
        }
        return 'Em serviço';
    }
    return 'A disposição';
}

// ============================
// FILTRAR FICHAS DAS VIATURAS
// ============================
$viaturasFiltradas = [];

foreach ($viaturas as $v) {
    $fichas = [];

    if ($data_ini) {
        $stmtFicha = $conexao->prepare("
            SELECT * FROM sta_fichas
            WHERE id_viatura = ?
              AND data_abertura <= ? AND data_prevista >= ?
            ORDER BY data_abertura DESC, id DESC
        ");
        $stmtFicha->bind_param("iss", $v['id'], $data_ini, $data_ini);
    } else {
        $stmtFicha = $conexao->prepare("
            SELECT * FROM sta_fichas
            WHERE id_viatura = ? AND status = 'Aberta'
            ORDER BY data_abertura DESC, id DESC
        ");
        $stmtFicha->bind_param("i", $v['id']);
    }

    $stmtFicha->execute();
    $resFicha = $stmtFicha->get_result();

    while ($f = $resFicha->fetch_assoc()) {
        if ($id && $f['id'] != $id) continue;
        if ($statusFicha && $f['status'] !== $statusFicha) continue;
        if ($origem && stripos($f['local_apresentar'] ?? '', $origem) === false) continue;
        if ($destino && stripos($f['destino'] ?? '', $destino) === false) continue;
        $fichas[] = $f;
    }
    $stmtFicha->close();

    // Determina situação
    $situacaoAtual = 'A disposição';
    $hoje = date('Y-m-d');
    foreach ($fichas as $ficha) {
        if ($ficha['status'] === 'Aberta') {
            if (!empty($ficha['data_prevista']) && $ficha['data_prevista'] < $hoje) {
                $situacaoAtual = 'Verificar se já retornou';
            } else {
                $situacaoAtual = 'Em serviço';
                break;
            }
        }
    }

    if ($statusViatura && $situacaoAtual !== $statusViatura) continue;

    $v['fichas'] = $fichas;
    $v['situacao'] = $situacaoAtual;
    $viaturasFiltradas[] = $v;
}

// ============================
// PAGINAÇÃO (PADRÃO FROTA)
// ============================
function renderPaginacaoFichas($pagina, $totalPaginas, $limite, $queryString, $arquivo = 'includes/sta_fichas/emprego.php') {

    $maxLinks = 10; // Máximo de páginas exibidas

    $makeUrl = function($p) use ($arquivo, $queryString, $limite) {
        return "{$arquivo}?{$queryString}&pagina={$p}&limite={$limite}";
    };

    $html = '<div class="pagination-wrapper">';
    $html .= '<nav><ul class="pagination pagination-sm justify-content-center">';

    // ==========================================
    // 🔹 PRIMEIRA página
    // ==========================================
    if ($pagina > 1) {
        $html .= "<li class='page-item'>
                    <a class='page-link paginacao-fichas' href='#' data-page='".$makeUrl(1)."'>&laquo; Primeira</a>
                  </li>";
    }

    // ==========================================
    // 🔹 ANTERIOR
    // ==========================================
    if ($pagina > 1) {
        $prev = $pagina - 1;
        $html .= "<li class='page-item'>
                    <a class='page-link paginacao-fichas' href='#' data-page='".$makeUrl($prev)."'>&lsaquo;</a>
                  </li>";
    }

    // ==========================================
    // 🔹 Cálculo das páginas exibidas
    // ==========================================
    $inicio = max(1, $pagina - floor($maxLinks / 2));
    $fim    = min($totalPaginas, $inicio + $maxLinks - 1);

    // Ajuste quando está no final
    if (($fim - $inicio) < ($maxLinks - 1)) {
        $inicio = max(1, $fim - $maxLinks + 1);
    }

    // ==========================================
    // 🔹 Reticências antes
    // ==========================================
    if ($inicio > 1) {
        $html .= "<li class='page-item disabled'><span class='page-link'>...</span></li>";
    }

    // ==========================================
    // 🔹 Loop das páginas
    // ==========================================
    for ($i = $inicio; $i <= $fim; $i++) {
        $ativo = ($i == $pagina) ? 'active' : '';
        $html .= "<li class='page-item {$ativo}'>
                    <a class='page-link paginacao-fichas' href='#' data-page='".$makeUrl($i)."'>$i</a>
                  </li>";
    }

    // ==========================================
    // 🔹 Reticências depois
    // ==========================================
    if ($fim < $totalPaginas) {
        $html .= "<li class='page-item disabled'><span class='page-link'>...</span></li>";
    }

    // ==========================================
    // 🔹 PRÓXIMA
    // ==========================================
    if ($pagina < $totalPaginas) {
        $next = $pagina + 1;
        $html .= "<li class='page-item'>
                    <a class='page-link paginacao-fichas' href='#' data-page='".$makeUrl($next)."'>&rsaquo;</a>
                  </li>";
    }

    // ==========================================
    // 🔹 ÚLTIMA
    // ==========================================
    if ($pagina < $totalPaginas) {
        $html .= "<li class='page-item'>
                    <a class='page-link paginacao-fichas' href='#' data-page='".$makeUrl($totalPaginas)."'>Última &raquo;</a>
                  </li>";
    }

    $html .= '</ul></nav></div>';
    return $html;
}


// ============================
// QUERY STRING PARA PAGINAÇÃO
// ============================
$paramsGET = $_GET;
unset($paramsGET['pagina']);
$queryString = http_build_query($paramsGET);
?>





<div class="container">
  <div class="page-inner">
    <div class="d-flex justify-content-between align-items-center py-3">
      <div>
        <h3 class="fw-bold mb-1">Emprego das Vtr/Eqp</h3>
        <h6 class="text-muted">Viaturas/Equipamentos a disposição</h6>
      </div>
        <div>
         <!-- Botão para abrir modal -->

      </div>
    </div>


    <!-- Lista de usuários -->
    <div class="card">
      <div class="card-body">
          <!-- Botão de filtros -->
<!-- Botão para mostrar filtros -->
<div class="mb-3">
  <button class="btn btn-outline-primary w-100 d-flex justify-content-center align-items-center" onclick="toggleFiltrosFichas()">
    <i class="fas fa-search me-2"></i> Filtros
  </button>
</div>

<!-- Container dos filtros -->
<div id="filtros-container-fichas" style="display: none;" class="mb-3">
  <div class="card shadow-sm border-0">
    <div class="card-body">
<?php
// ============================
// Determina os batalhões que o usuário pode visualizar
// ============================
$id_om_usuario = $_SESSION['usuario']['batalhao'] ?? 0;

// Busca o nível do usuário
$sqlNivel = "SELECT nivel FROM organizacoes_militares WHERE id = $id_om_usuario";
$resNivel = $conexao->query($sqlNivel);
$nivelUsuario = $resNivel->fetch_assoc()['nivel'] ?? 0;

// Monta lista de OMs que o usuário pode visualizar
if ($nivelUsuario == 1) {
    $sqlBatalhoes = "SELECT id FROM organizacoes_militares";
} else {
    $sqlBatalhoes = "
        SELECT om.id
        FROM organizacoes_militares om
        WHERE om.id = $id_om_usuario
        OR om.id IN (
            SELECT id_om_menor FROM organizacoes_militares_sub WHERE id_om_maior = $id_om_usuario
        )
    ";
}
$resBatalhoes = $conexao->query($sqlBatalhoes);
$batalhoesPermitidos = [];
while ($bat = $resBatalhoes->fetch_assoc()) {
    $batalhoesPermitidos[] = (int)$bat['id'];
}

if (empty($batalhoesPermitidos)) {
    echo "<div class='alert alert-warning'>Nenhum batalhão disponível para este usuário.</div>";
    exit;
}

// Constrói placeholders para filtros SQL
$placeholders = implode(',', array_fill(0, count($batalhoesPermitidos), '?'));
$tiposBatalhao = str_repeat('i', count($batalhoesPermitidos));

// ============================
// Pré-carrega dados da frota (limitados ao batalhão permitido)
// ============================

// PREFIXOS
$stmtPrefixo = $conexao->prepare("SELECT DISTINCT prefixo_sga FROM frota WHERE batalhao IN ($placeholders) ORDER BY prefixo_sga ASC");
$stmtPrefixo->bind_param($tiposBatalhao, ...$batalhoesPermitidos);
$stmtPrefixo->execute();
$resPrefixo = $stmtPrefixo->get_result();
$prefixos = [];
while ($p = $resPrefixo->fetch_assoc()) {
  $prefixos[] = $p['prefixo_sga'];
}
$stmtPrefixo->close();

// ATIVOS
$stmtAtivo = $conexao->prepare("SELECT DISTINCT ativo FROM frota WHERE batalhao IN ($placeholders) ORDER BY ativo ASC");
$stmtAtivo->bind_param($tiposBatalhao, ...$batalhoesPermitidos);
$stmtAtivo->execute();
$resAtivo = $stmtAtivo->get_result();
$ativo = [];
while ($a = $resAtivo->fetch_assoc()) {
  $ativo[] = $a['ativo'];
}
$stmtAtivo->close();

// =============================
// MARCAS
// =============================
$stmtMarca = $conexao->prepare("
    SELECT DISTINCT cm.id, cm.marca
    FROM frota f
    INNER JOIN config_marcas cm ON f.marca = cm.id
    WHERE f.batalhao IN ($placeholders)
    ORDER BY cm.marca ASC
");
$stmtMarca->bind_param($tiposBatalhao, ...$batalhoesPermitidos);
$stmtMarca->execute();
$resMarca = $stmtMarca->get_result();

$marca = [];
while ($m = $resMarca->fetch_assoc()) {
    $marca[] = [
        'id' => $m['id'],
        'nome' => $m['marca']
    ];
}
$stmtMarca->close();

// =============================
// MODELOS
// =============================
$stmtModelo = $conexao->prepare("
    SELECT DISTINCT cmo.id, cmo.nome_modelo
    FROM frota f
    INNER JOIN config_modelos cmo ON f.modelo = cmo.id
    WHERE f.batalhao IN ($placeholders)
    ORDER BY cmo.nome_modelo ASC
");
$stmtModelo->bind_param($tiposBatalhao, ...$batalhoesPermitidos);
$stmtModelo->execute();
$resModelo = $stmtModelo->get_result();

$modelo = [];
while ($mo = $resModelo->fetch_assoc()) {
    $modelo[] = [
        'id' => $mo['id'],
        'nome' => $mo['nome_modelo']
    ];
}
$stmtModelo->close();


// ============================
// OMs Visíveis para Filtro
// ============================
function getOMsVisiveis($conexao, $id_om_usuario, $nivel_usuario) {
    $oms = [];
    if ($nivel_usuario == 1) {
        $sql = "SELECT id, nome, abreviatura FROM organizacoes_militares ORDER BY nome";
        $res = $conexao->query($sql);
    } elseif ($nivel_usuario == 2) {
        $sql = "
            SELECT id, nome, abreviatura FROM organizacoes_militares
            WHERE id = ? OR id IN (SELECT id_om_menor FROM organizacoes_militares_sub WHERE id_om_maior = ?)
            ORDER BY nome
        ";
        $stmt = $conexao->prepare($sql);
        $stmt->bind_param("ii", $id_om_usuario, $id_om_usuario);
        $stmt->execute();
        $res = $stmt->get_result();
    } else {
        $sql = "SELECT id, nome, abreviatura FROM organizacoes_militares WHERE id = ?";
        $stmt = $conexao->prepare($sql);
        $stmt->bind_param("i", $id_om_usuario);
        $stmt->execute();
        $res = $stmt->get_result();
    }

    while ($r = $res->fetch_assoc()) {
        $oms[$r['id']] = $r['abreviatura'] ?: $r['nome'];
    }

    return $oms;
}

$oms_visiveis = getOMsVisiveis($conexao, $id_om_usuario, $nivelUsuario);
$batalhao_filtro = $_GET['batalhao'] ?? '';
?>

<form method="GET" id="filtroFichaForm">
  <div class="row g-3">

    <div class="col-md-3">
      <label class="form-label fw-semibold">Data selecionada</label>
      <input type="date" class="form-control" name="data_ini" value="<?= $_GET['data_ini'] ?? '' ?>">
    </div>

    <!-- FILTRO DE BATALHÃO -->
    <div class="col-md-3">
      <label class="form-label fw-semibold">Batalhão</label>
      <select name="batalhao" class="form-select">
        <option value="">Todos</option>
        <?php foreach ($oms_visiveis as $id => $nome): 
          $sel = ($batalhao_filtro == $id) ? 'selected' : ''; ?>
          <option value="<?= $id ?>" <?= $sel ?>><?= htmlspecialchars($nome) ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="col-md-2">
      <label class="form-label fw-semibold">Tipo</label>
      <select name="tipo" class="form-select">
        <option value="">Todos</option>
        <option value="Eqp" <?= ($_GET['tipo'] ?? '') == 'Eqp' ? 'selected' : '' ?>>Equipamento</option>
        <option value="Vtr" <?= ($_GET['tipo'] ?? '') == 'Vtr' ? 'selected' : '' ?>>Viatura</option>
      </select>
    </div>

    <div class="col-md-2">
      <label class="form-label fw-semibold">Ativo</label>
      <select name="ativo" class="form-select">
        <option value="">Todos</option>
        <?php foreach ($ativo as $a): ?>
          <option value="<?= htmlspecialchars($a) ?>" <?= ($_GET['ativo'] ?? '') === $a ? 'selected' : '' ?>><?= htmlspecialchars($a) ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="col-md-2">
      <label class="form-label fw-semibold">Prefixo SGA</label>
      <select name="prefixo_sga" class="form-select">
        <option value="">Todos</option>
        <?php foreach ($prefixos as $p): ?>
          <option value="<?= htmlspecialchars($p) ?>" <?= ($_GET['prefixo_sga'] ?? '') === $p ? 'selected' : '' ?>><?= htmlspecialchars($p) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
<div class="col-md-2">
  <label class="form-label fw-semibold">Marca</label>
  <select name="marca" class="form-select">
    <option value="">Todas</option>
    <?php foreach ($marca as $m): ?>
      <option value="<?= htmlspecialchars($m['id']) ?>" 
        <?= (isset($_GET['marca']) && $_GET['marca'] == $m['id']) ? 'selected' : '' ?>>
        <?= htmlspecialchars($m['nome']) ?>
      </option>
    <?php endforeach; ?>
  </select>
</div>

<div class="col-md-2">
  <label class="form-label fw-semibold">Modelo</label>
  <select name="modelo" class="form-select">
    <option value="">Todos</option>
    <?php foreach ($modelo as $mo): ?>
      <option value="<?= htmlspecialchars($mo['id']) ?>" 
        <?= (isset($_GET['modelo']) && $_GET['modelo'] == $mo['id']) ? 'selected' : '' ?>>
        <?= htmlspecialchars($mo['nome']) ?>
      </option>
    <?php endforeach; ?>
  </select>
</div>


    <div class="col-md-2">
      <label class="form-label fw-semibold">Status da Ficha</label>
      <select name="status" class="form-select">
        <option value="">Todos</option>
        <option value="Aberta" <?= ($_GET['status'] ?? '') == 'Aberta' ? 'selected' : '' ?>>Aberta</option>
        <option value="Encerrada" <?= ($_GET['status'] ?? '') == 'Encerrada' ? 'selected' : '' ?>>Encerrada</option>
      </select>
    </div>

    <div class="col-md-4">
      <label class="form-label fw-semibold">Situação da Viatura</label>
      <select name="situacao_viatura" class="form-select">
        <option value="">Todas</option>
        <option value="Em serviço" <?= ($_GET['situacao_viatura'] ?? '') == 'Em serviço' ? 'selected' : '' ?>>Em serviço</option>
        <option value="Verificar se já retornou" <?= ($_GET['situacao_viatura'] ?? '') == 'Verificar se já retornou' ? 'selected' : '' ?>>Verificar se já retornou</option>
        <option value="A disposição" <?= ($_GET['situacao_viatura'] ?? '') == 'A disposição' ? 'selected' : '' ?>>A disposição</option>
      </select>
    </div>

    <div class="col-md-4">
      <label class="form-label fw-semibold">Origem (Apresentar-se em)</label>
      <input type="text" class="form-control" name="origem" value="<?= htmlspecialchars($_GET['origem'] ?? '') ?>" placeholder="Ex: 2º BEC">
    </div>

    <div class="col-md-4">
      <label class="form-label fw-semibold">Destino</label>
      <input type="text" class="form-control" name="destino" value="<?= htmlspecialchars($_GET['destino'] ?? '') ?>" placeholder="Ex: Teresina-PI">
    </div>

    <div class="col-12 d-flex justify-content-between mt-3">
      <button type="button" id="btnLimparFiltrosFichas" class="btn btn-outline-secondary">Limpar Filtros</button>
      <button type="submit" class="btn btn-primary px-4">Aplicar Filtros</button>
    </div>
  </div>
</form>

    </div>
  </div>
</div>




<!-- Select de limite por página -->
<div class="mb-3">
  <label for="limiteFICHAS" class="me-2 mb-0">Mostrar</label>
  <select id="limiteFICHAS" name="limite" class="form-select d-inline w-auto">
    <?php
    $limiteAtual = $_GET['limite'] ?? 10;
    foreach ([5, 10, 25, 50, 100] as $opcao) {
      $selected = ($limiteAtual == $opcao) ? 'selected' : '';
      echo "<option value=\"$opcao\" $selected>$opcao</option>";
    }
    ?>
  </select>
  <span class="ms-2">por página</span>
</div>
          <?= renderPaginacaoFichas($pagina ?? 1, $totalPaginas ?? 1, $limite ?? 10, $queryString ?? '') ?>

 
          
          <div class="row g-4">
  <?php foreach ($viaturasFiltradas as $v): ?>
    <div class="col-12">
      <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
        <div class="card-body p-4">
          <div class="row align-items-center justify-content-between mb-3">
            <!-- Identificação da viatura -->
            <div class="col-md-6">
              <h5 class="fw-bold text-primary mb-1">
                <?= htmlspecialchars($v['prefixo_sga']) ?>
              </h5>
              <div class="text-muted small">
                <i class="fas fa-car me-1"></i> <?= htmlspecialchars($v['tipo']) ?> |
                <i class="fas fa-industry ms-2 me-1"></i> <?= htmlspecialchars($v['nome_marca']) ?> |
                <i class="fas fa-cogs ms-2 me-1"></i> <?= htmlspecialchars($v['nome_modelo']) ?>
              </div>
            </div>

            <!-- Situação + Ações principais -->
            <div class="col-md-6 text-md-end mt-3 mt-md-0">
              <span class="badge bg-dark fs-6 py-2 px-3 mb-2">
                <?= htmlspecialchars($v['situacao']) ?>
              </span>
              <button 
                class="btn btn-sm btn-outline-primary ms-2" 
                onclick="verTodasFichas(<?= $v['id'] ?>)" 
                data-bs-toggle="modal" 
                data-bs-target="#modalTodasFichas">
                <i class="fas fa-list me-1"></i> Todas Fichas
              </button>
            </div>
          </div>

          <!-- Lista de fichas -->
          <?php if (!empty($v['fichas'])): ?>
            <div class="table-responsive mt-3">
              <table class="table table-sm align-middle mb-0">
                <thead class="table-light">
                  <tr>
                    <th>#Ficha</th>
                    <th>Abertura</th>
                    <th>Encerramento Previsto</th>
                    <th>Origem</th>
                    <th>Destino</th>
                    <th>Status</th>
                    <th class="text-end">Ações</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($v['fichas'] as $ficha): 
                    $fichaId = $ficha['id'] ?? null;
                    $dataAbertura = isset($ficha['data_abertura']) ? date('d/m/Y', strtotime($ficha['data_abertura'])) : '--';
                    $dataPrevista = isset($ficha['data_prevista']) ? date('d/m/Y', strtotime($ficha['data_prevista'])) : '--';
                    $origemVal = $ficha['local_apresentar'] ?? '—';
                    $destinoVal = $ficha['destino'] ?? '—';
                    $status = $ficha['status'] ?? '—';
                    $statusBadge = $status === 'Aberta' ? 'warning' : ($status === 'Encerrada' ? 'success' : 'secondary');
                  ?>
                  <tr>
                    <td><span class="fw-semibold text-dark">#<?= $fichaId ?></span></td>
                    <td><?= $dataAbertura ?></td>
                    <td><?= $dataPrevista ?></td>
                    <td><?= htmlspecialchars($origemVal) ?></td>
                    <td><?= htmlspecialchars($destinoVal) ?></td>
                    <td><span class="badge bg-<?= $statusBadge ?>"><?= $status ?></span></td>
                    <td class="text-end">
                      <button class="btn btn-sm btn-outline-primary" onclick="verFicha(<?= $fichaId ?>)" data-bs-toggle="modal" data-bs-target="#modalVerFICHA">
                        <i class="fas fa-eye"></i>
                      </button>
                      <button class="btn btn-sm btn-outline-warning ms-1" onclick="editarFICHA(<?= $fichaId ?>)" data-bs-toggle="modal" data-bs-target="#modalEditarFICHA">
                        <i class="fas fa-edit"></i>
                      </button>
                      <button 
                        type="button" 
                        class="btn btn-sm btn-outline-danger ms-1"
                        data-id="<?= $fichaId ?>"
                        onclick="deletarFicha(this)"
                        data-bs-toggle="tooltip"
                        title="Remover Ficha">
                        <i class="fa fa-times"></i>
                      </button>
                    </td>
                  </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php else: ?>
            <div class="alert alert-light border mt-3 mb-0 py-2">
              <i class="fas fa-info-circle text-muted me-1"></i>
              Nenhuma ficha encontrada para esta viatura.
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  <?php endforeach; ?>
</div>




<?= renderPaginacaoFichas($pagina ?? 1, $totalPaginas ?? 1, $limite ?? 10, $queryString ?? '') ?>

      
   




<!-- Modal de Edição da Ficha STA -->
<div class="modal fade" id="modalEditarFICHA" tabindex="-1" aria-labelledby="modalEditarFICHA_Label" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <form method="POST" enctype="multipart/form-data" id="form-editar-ficha">
        <div class="modal-header">
          <h5 class="modal-title" id="modalEditarFICHA_Label">Editar Ficha de Vtr/Eqp</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
        </div>
        <div class="modal-body">
          <input type="hidden" name="id" id="editar-ficha-id">

          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label">Viatura/Equipamento</label>
              <select class="form-select" name="id_viatura" id="editar-id_viatura" required>
                <option value="" disabled selected>Selecione</option>
                <?php
                $viaturas = $conexao->query("SELECT id, prefixo_sga, modelo FROM frota ORDER BY prefixo_sga ASC");
                while ($vtr = $viaturas->fetch_assoc()):
                ?>
                <option value="<?= $vtr['id'] ?>"><?= $vtr['prefixo_sga'] ?> - <?= $vtr['modelo'] ?></option>
                <?php endwhile; ?>
                </select>
            </div>

            <div class="col-md-6">
              <label class="form-label">Organização Militar (OM)</label>
              <select class="form-select" name="id_om" id="editar-id_om" required>
                <option value="" disabled selected>Selecione</option>
                <?php
                $oms = $conexao->query("SELECT id, nome FROM organizacoes_militares ORDER BY nome ASC");
                while ($om = $oms->fetch_assoc()):
                ?>
                <option value="<?= $om['id'] ?>"><?= $om['nome'] ?></option>
                <?php endwhile; ?>
                </select>
            </div>

            <div class="col-md-6">
              <label class="form-label">Data de Abertura</label>
              <input type="date" name="data_abertura" id="editar-data_abertura" class="form-control" required>
            </div>
              
               <div class="col-md-6">
              <label class="form-label">Data prevista para retorno</label>
              <input type="date" name="data_prevista" id="editar-data_prevista" class="form-control" required>
            </div>

            <div class="col-md-6">
              <label class="form-label">Solicitante</label>
              <input type="text" name="solicitante" id="editar-solicitante" class="form-control" required>
            </div>

            <div class="col-md-6">
              <label class="form-label">Motorista</label>
              <input type="text" name="motorista" id="editar-motorista" class="form-control" required>
            </div>

            <div class="col-md-6">
              <label class="form-label">Subunidade</label>
              <input type="text" name="subunidade" id="editar-subunidade" class="form-control" required>
            </div>

            <div class="col-md-6">
              <label class="form-label">Destino especificado</label>
              <input type="text" name="destino" id="editar-destino" class="form-control" required>
            </div>

            <div class="col-md-6">
              <label class="form-label">Cidade/UF destino</label>
              <input type="text" name="cidade" id="editar-cidade" class="form-control" required>
            </div>

            <div class="col-md-6">
              <label class="form-label">Chefe a se Apresentar</label>
              <input type="text" name="chefe_apresentar" id="editar-chefe_apresentar" class="form-control" required>
            </div>

            <div class="col-md-4">
              <label class="form-label">Local a se Apresentar</label>
              <input type="text" name="local_apresentar" id="editar-local_apresentar" class="form-control" required>
            </div>

            <div class="col-md-2">
              <label class="form-label">Horário</label>
              <input type="time" name="horario_apresentar" id="editar-horario_apresentar" class="form-control" required>
            </div>

            <div class="col-md-6">
              <label class="form-label">Natureza</label>
              <input type="text" name="natureza" id="editar-natureza" class="form-control" required>
            </div>

            <div class="col-md-6">
              <label class="form-label">Status</label>
              <select class="form-select" name="status" id="editar-status" required>
                <option value="">Selecione</option>
                <option value="Aberta">Aberta</option>
                <option value="Encerrada">Encerrada</option>
              </select>
            </div>

            <div class="col-12">
              <div class="p-3 mb-4 border rounded bg-light">
                <h5 class="text-uppercase fw-bold text-danger mb-3">
                  PREENCHIMENTO SOMENTE APÓS O RETORNO DA VTR/EQP DA MISSÃO
                </h5>

                <div class="row g-3">
                  <div class="col-md-4">
                    <label class="form-label">Data Saída</label>
                    <input type="date" name="data_saida" id="editar-data_saida" class="form-control">
                  </div>
                  <div class="col-md-2">
                    <label class="form-label">Hora Saída</label>
                    <input type="time" name="hora_saida" id="editar-hora_saida" class="form-control">
                  </div>
                  <div class="col-md-2">
                    <label class="form-label">Odômetro Saída</label>
                    <input type="number" name="odo_saida" id="editar-odo_saida" class="form-control" step="any">
                  </div>

                  <div class="col-md-4">
                    <label class="form-label">Data Retorno</label>
                    <input type="date" name="data_retorno" id="editar-data_retorno" class="form-control">
                  </div>
                  <div class="col-md-2">
                    <label class="form-label">Hora Retorno</label>
                    <input type="time" name="hora_retorno" id="editar-hora_retorno" class="form-control">
                  </div>
                  <div class="col-md-2">
                    <label class="form-label">Odômetro Retorno</label>
                    <input type="number" name="odo_retorno" id="editar-odo_retorno" class="form-control" step="any">
                  </div>

                  <div class="col-md-12">
                    <label class="form-label">Observações Pós-Emprego</label>
                    <textarea name="observacoes_pos_emprego" id="editar-observacoes_pos_emprego" class="form-control" rows="3"></textarea>
                  </div>
                </div>
              </div>
            </div>
          </div> <!-- row -->
        </div> <!-- modal-body -->

        <div class="modal-footer justify-content-between">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-success">Salvar Alterações</button>
        </div>
      </form>
    </div>
  </div>
</div>


<!-- Modal de Ver Ficha STA -->
<div class="modal fade" id="modalVerFICHA" tabindex="-1" aria-labelledby="modalVerFICHA_Label" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content border-0 shadow">
      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title">Ver Ficha de Viatura/Equipamento</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Fechar"></button>
      </div>

      <div class="modal-body p-4 bg-light">

        <!-- Título (OM) -->
        <h4 class="text-center fw-bold text-dark mb-4" id="tituloOM"></h4>

        <!-- Dados da Viatura -->
        <div class="row g-3 bg-white p-3 rounded shadow-sm mb-4">
            
          <h5 class="fw-bold text-secondary">Dados da Viatura/Equipamento</h5>
              <!-- Foto da viatura -->
        <div class="text-center mb-4">
          <img id="fotoViaturaFicha" src="" alt="Foto da Viatura" class="img-thumbnail shadow-sm" style="max-width: 100%; max-height: 100px; object-fit: cover;">
        </div>
          <div class="col-md-3">
            <label class="form-label">Prefixo</label>
            <p class="form-control-plaintext" id="verf_prefixo"></p>
          </div>
          <div class="col-md-3">
            <label class="form-label">Chassi</label>
            <p class="form-control-plaintext" id="verf_chassi"></p>
          </div>
          <div class="col-md-3">
            <label class="form-label">Marca</label>
            <p class="form-control-plaintext" id="verf_marca"></p>
          </div>
          <div class="col-md-3">
            <label class="form-label">Modelo</label>
            <p class="form-control-plaintext" id="verf_modelo"></p>
          </div>
        </div>

        <!-- Dados Principais -->
        <div class="row g-3 bg-white p-3 rounded shadow-sm mb-4">
          <h5 class="fw-bold text-secondary">Dados da Ficha</h5>
          <div class="col-md-4">
            <label class="form-label">Organização Militar</label>
            <p class="form-control-plaintext" id="verf_om"></p>
          </div>
          <div class="col-md-4">
            <label class="form-label">Data de Abertura</label>
            <p class="form-control-plaintext" id="verf_data_abertura"></p>
          </div>
          <div class="col-md-4">
            <label class="form-label">Data Prevista para retorno</label>
            <p class="form-control-plaintext" id="verf_data_prevista"></p>
          </div>
          <div class="col-md-4">
            <label class="form-label">Status</label>
            <p class="form-control-plaintext" id="verf_status"></p>
          </div>
        </div>

        <!-- Dados da Missão -->
        <div class="row g-3 bg-white p-3 rounded shadow-sm mb-4">
          <h5 class="fw-bold text-secondary">Dados da Missão</h5>
          <div class="col-md-3">
            <label class="form-label">Solicitante</label>
            <p class="form-control-plaintext" id="verf_solicitante"></p>
          </div>
          <div class="col-md-3">
            <label class="form-label">Motorista</label>
            <p class="form-control-plaintext" id="verf_motorista"></p>
          </div>
          <div class="col-md-3">
            <label class="form-label">Subunidade</label>
            <p class="form-control-plaintext" id="verf_subunidade"></p>
          </div>
          <div class="col-md-3">
            <label class="form-label">Natureza</label>
            <p class="form-control-plaintext" id="verf_natureza"></p>
          </div>
          <div class="col-md-4">
            <label class="form-label">Destino</label>
            <p class="form-control-plaintext" id="verf_destino"></p>
          </div>
          <div class="col-md-4">
            <label class="form-label">Cidade/UF</label>
            <p class="form-control-plaintext" id="verf_cidade"></p>
          </div>
          <div class="col-md-4">
            <label class="form-label">Chefe a se Apresentar</label>
            <p class="form-control-plaintext" id="verf_chefe_apresentar"></p>
          </div>
          <div class="col-md-4">
            <label class="form-label">Local a se Apresentar</label>
            <p class="form-control-plaintext" id="verf_local_apresentar"></p>
          </div>
          <div class="col-md-4">
            <label class="form-label">Horário</label>
            <p class="form-control-plaintext" id="verf_horario_apresentar"></p>
          </div>
        </div>

        <!-- Dados Pós-Emprego -->
        <div class="row g-3 bg-white p-3 rounded shadow-sm mb-4">
          <h5 class="fw-bold text-danger">Pós-Emprego da Vtr/Eqp</h5>
          <div class="col-md-3">
            <label class="form-label">Data Saída</label>
            <p class="form-control-plaintext" id="verf_data_saida"></p>
          </div>
          <div class="col-md-2">
            <label class="form-label">Hora Saída</label>
            <p class="form-control-plaintext" id="verf_hora_saida"></p>
          </div>
          <div class="col-md-2">
            <label class="form-label">Odômetro Saída</label>
            <p class="form-control-plaintext" id="verf_odo_saida"></p>
          </div>
          <div class="col-md-3">
            <label class="form-label">Data Retorno</label>
            <p class="form-control-plaintext" id="verf_data_retorno"></p>
          </div>
          <div class="col-md-2">
            <label class="form-label">Hora Retorno</label>
            <p class="form-control-plaintext" id="verf_hora_retorno"></p>
          </div>
          <div class="col-md-2">
            <label class="form-label">Odômetro Retorno</label>
            <p class="form-control-plaintext" id="verf_odo_retorno"></p>
          </div>
          <div class="col-md-12">
            <label class="form-label">Observações Pós-Emprego</label>
            <p class="form-control-plaintext" id="verf_observacoes"></p>
          </div>
        </div>

        <!-- Logs -->
        <div class="bg-white p-3 rounded shadow-sm mb-4">
          <h5 class="fw-bold text-secondary">Histórico de Alterações</h5>
          <div id="verf_logsContainer">
            <p class="text-muted">Nenhum log disponível.</p>
          </div>
        </div>

        <!-- Botão -->
        <div class="text-end">
          <button type="button" class="btn btn-danger" data-bs-dismiss="modal">Fechar</button>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Modal de Ver Todas Fichas da Viatura -->
<div class="modal fade" id="modalTodasFichas" tabindex="-1" aria-labelledby="modalTodasFichas_Label" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content border-0 shadow">
      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title">Fichas da Viatura/Equipamento</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Fechar"></button>
      </div>

      <div class="modal-body p-4 bg-light">

        <!-- Dados da Viatura -->
        <div class="row g-3 bg-white p-3 rounded shadow-sm mb-4">
          <h5 class="fw-bold text-secondary">Dados da Viatura/Equipamento</h5>
          <div class="text-center mb-4">
            <img id="fotoViaturaTodasFichas" src="" alt="Foto da Viatura" class="img-thumbnail shadow-sm" style="max-width: 100%; max-height: 100px; object-fit: cover;">
          </div>
          <div class="col-md-3">
            <label class="form-label">Prefixo</label>
            <p class="form-control-plaintext" id="todasf_prefixo"></p>
          </div>
          <div class="col-md-3">
            <label class="form-label">Chassi</label>
            <p class="form-control-plaintext" id="todasf_chassi"></p>
          </div>
          <div class="col-md-3">
            <label class="form-label">Marca</label>
            <p class="form-control-plaintext" id="todasf_marca"></p>
          </div>
          <div class="col-md-3">
            <label class="form-label">Modelo</label>
            <p class="form-control-plaintext" id="todasf_modelo"></p>
          </div>
        </div>

        <!-- Fichas Listadas -->
        <div class="bg-white p-3 rounded shadow-sm mb-4">
          <h5 class="fw-bold text-primary">Fichas Cadastradas</h5>
          <div id="todasFichasContainer">
            <p class="text-muted">Carregando fichas...</p>
          </div>
        </div>

        <!-- Botão -->
        <div class="text-end">
          <button type="button" class="btn btn-danger" data-bs-dismiss="modal">Fechar</button>
        </div>

      </div>
    </div>
  </div>
</div>


     
   
      
  </div>
</div>
<script>
    window.funcaoInicializacao = 'inicializarEmpregoFichas';    
</script>