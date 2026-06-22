<?php
header('Content-Type: text/html; charset=utf-8');
session_start();
require_once(__DIR__ . '/../../../database/conexao/config.php');

// ----------------------
// IDENTIFICAÇÃO DO USUÁRIO E SUAS OMs VISÍVEIS
// ----------------------
$id_om_usuario = $_SESSION['usuario']['batalhao'] ?? 0;
$nivel_usuario = $_SESSION['usuario']['nivel'] ?? 0;

// Lista de OMs permitidas
$idsPermitidos = [$id_om_usuario];

// Se não for nível 1 (admin), buscar subordinadas
if ($nivel_usuario != 1 && $id_om_usuario) {
    $sqlSub = "SELECT id_om_menor FROM organizacoes_militares_sub WHERE id_om_maior = ?";
    $stmtSub = $conexao->prepare($sqlSub);
    $stmtSub->bind_param('i', $id_om_usuario);
    $stmtSub->execute();
    $resSub = $stmtSub->get_result();
    while ($r = $resSub->fetch_assoc()) {
        $idsPermitidos[] = (int)$r['id_om_menor'];
    }
    $stmtSub->close();
} else {
    // Nível 1 pode ver todos
    $resAll = $conexao->query("SELECT id FROM organizacoes_militares");
    while ($r = $resAll->fetch_assoc()) {
        $idsPermitidos[] = (int)$r['id'];
    }
}

$idsPermitidos = array_unique($idsPermitidos);
$idsPermitidosStr = implode(',', array_map('intval', $idsPermitidos));

// ----------------------
// Filtros via GET
// ----------------------
$filtrosGET = [
    'batalhao'      => $_GET['batalhao'] ?? '',
    'id'            => $_GET['id'] ?? '',
    'tipo'          => $_GET['tipo'] ?? '',
    'modelo'        => $_GET['modelo'] ?? '',
    'marca'         => $_GET['marca'] ?? '',
    'ativo'         => $_GET['ativo'] ?? '',
    'prefixo_sga'   => $_GET['prefixo_sga'] ?? '',
    'nmr_patrimonio'=> $_GET['nmr_patrimonio'] ?? '',
    'chassi'        => $_GET['chassi'] ?? '',
    'acervo'        => $_GET['acervo'] ?? '',
    'emprego_atual' => $_GET['emprego_atual'] ?? '',
    'subunidade'    => $_GET['subunidade'] ?? '',
    'disponibilidade'=> $_GET['disponibilidade'] ?? '',
    'confiabilidade'=> $_GET['confiabilidade'] ?? '',
    'status_manut'  => $_GET['status_manut'] ?? '',
];

// ----------------------
// Limite e paginação
// ----------------------
$limite = isset($_GET['limite']) && is_numeric($_GET['limite']) ? (int)$_GET['limite'] : 10;
$pagina = isset($_GET['pagina']) && is_numeric($_GET['pagina']) && $_GET['pagina']>0 ? (int)$_GET['pagina'] : 1;
$offset = ($pagina-1)*$limite;

// ----------------------
// Construir filtros SQL
// ----------------------
$filtrosSQL = ["f.batalhao IN ($idsPermitidosStr)"];
$params = [];
$tipos = '';

foreach ($filtrosGET as $campo => $valor) {
    if ($valor === '' || $campo === 'status_manut') continue; // status_manut NÃO entra no SQL

    switch ($campo) {
        case 'batalhao': $f = 'f.batalhao'; break;
        case 'tipo': $f = 'f.tipo'; break;
        case 'modelo': $f = 'f.modelo'; break;
        case 'marca': $f = 'f.marca'; break;
        case 'ativo': $f = 'f.ativo'; break;
        case 'prefixo_sga': $f = 'f.prefixo_sga'; break;
        case 'nmr_patrimonio': $f = 'f.nmr_patrimonio'; break;
        case 'chassi': $f = 'f.chassi'; break;
        case 'acervo': $f = 'f.acervo'; break;
        case 'emprego_atual': $f = 'f.emprego_atual'; break;
        case 'subunidade': $f = 'f.subunidade'; break;
        case 'disponibilidade': $f = 'f.disponibilidade'; break;
        case 'confiabilidade': $f = 'f.confiabilidade'; break;
        default: $f = ''; break;
    }

    if ($f) {
        if (in_array($campo, ['batalhao','marca','modelo','id'])) {
            $filtrosSQL[] = "$f = ?";
            $params[] = (int)$valor;
            $tipos .= 'i';
        } elseif (in_array($campo, ['nmr_patrimonio','chassi'])) {
            $filtrosSQL[] = "$f LIKE ?";
            $params[] = "%$valor%";
            $tipos .= 's';
        } else {
            $filtrosSQL[] = "$f LIKE ?";
            $params[] = "%$valor%";
            $tipos .= 's';
        }
    }
}

// ----------------------
// WHERE SQL final
// ----------------------
$whereSQL = 'WHERE ' . implode(' AND ', $filtrosSQL);

// ------------------------------------------------------
// CONSULTA SEM LIMIT — NECESSÁRIO PARA FILTRAR manut
//-------------------------------------------------------
$sqlFrotaSemLimit = "
    SELECT f.*, m.marca AS marca_nome, mo.nome_modelo AS modelo_nome, om.nome AS nome_om
    FROM frota f
    LEFT JOIN config_marcas m ON f.marca = m.id
    LEFT JOIN config_modelos mo ON f.modelo = mo.id
    LEFT JOIN organizacoes_militares om ON om.id = f.batalhao
    $whereSQL
    ORDER BY f.prefixo_sga ASC
";

$stmtAll = $conexao->prepare($sqlFrotaSemLimit);
if (!empty($params)) $stmtAll->bind_param($tipos, ...$params);
$stmtAll->execute();
$resAll = $stmtAll->get_result();

// ----------------------
// Calcular manutenção + filtrar antes da paginação
// ----------------------
require_once 'calculo_manutencao.php';

$viaturasFiltradas = [];
while ($v = $resAll->fetch_assoc()) {
    $manut = calcularManutencaoPreventiva($conexao, $v);

    if ($filtrosGET['status_manut'] && strtolower($manut['status']) !== strtolower($filtrosGET['status_manut'])) {
        continue;
    }

    $v['manut'] = $manut;
    $viaturasFiltradas[] = $v;
}

$totalRegistros = count($viaturasFiltradas);
$totalPaginas = ceil($totalRegistros / $limite);

// ----------------------
// Paginação manual
// ----------------------
$viaturas = array_slice($viaturasFiltradas, $offset, $limite);

// ---------------------------------------------------------
// PAGINAÇÃO FICHAS
// ---------------------------------------------------------
function renderPaginacaoFICHAS($pagina, $totalPaginas, $limite, $queryString, $arquivo = 'includes/preventiva/listagem.php')
{
    if ($totalPaginas < 2) {
        return ''; // sem paginação
    }

    // Máximo de links exibidos
    $maxLinks = 10;

    // Função de montagem de URL
    $makeUrl = function($p) use ($arquivo, $queryString, $limite) {
        return "{$arquivo}?{$queryString}&pagina={$p}&limite={$limite}";
    };

    $html = '<div class="pagination-wrapper">';
    $html .= '<nav><ul class="pagination pagination-sm justify-content-center">';

    // =======================
    //     PRIMEIRA PÁGINA
    // =======================
    if ($pagina > 1) {
        $html .= "<li class='page-item'>
                    <a class='page-link paginacao-fichas' href='#' data-page='".$makeUrl(1)."'>&laquo; Primeira</a>
                  </li>";
    }

    // =======================
    //        ANTERIOR
    // =======================
    if ($pagina > 1) {
        $html .= "<li class='page-item'>
                    <a class='page-link paginacao-fichas' href='#' data-page='".$makeUrl($pagina - 1)."'>&lsaquo;</a>
                  </li>";
    }

    // =======================
    // Cálculo da janela
    // =======================
    $inicio = max(1, $pagina - floor($maxLinks / 2));
    $fim = min($totalPaginas, $inicio + $maxLinks - 1);

    // Ajuste se chegar perto do fim
    if (($fim - $inicio + 1) < $maxLinks) {
        $inicio = max(1, $fim - $maxLinks + 1);
    }

    // =======================
    //  Reticências antes
    // =======================
    if ($inicio > 1) {
        $html .= "<li class='page-item disabled'><span class='page-link'>...</span></li>";
    }

    // =======================
    //  Links numéricos
    // =======================
    for ($i = $inicio; $i <= $fim; $i++) {
        $active = ($i == $pagina) ? "active" : "";
        $html .= "<li class='page-item {$active}'>
                    <a class='page-link paginacao-fichas' href='#' data-page='".$makeUrl($i)."'>$i</a>
                  </li>";
    }

    // =======================
    //  Reticências depois
    // =======================
    if ($fim < $totalPaginas) {
        $html .= "<li class='page-item disabled'><span class='page-link'>...</span></li>";
    }

    // =======================
    //        PRÓXIMA
    // =======================
    if ($pagina < $totalPaginas) {
        $html .= "<li class='page-item'>
                    <a class='page-link paginacao-fichas' href='#' data-page='".$makeUrl($pagina + 1)."'>&rsaquo;</a>
                  </li>";
    }

    // =======================
    //        ÚLTIMA
    // =======================
    if ($pagina < $totalPaginas) {
        $html .= "<li class='page-item'>
                    <a class='page-link paginacao-fichas' href='#' data-page='".$makeUrl($totalPaginas)."'>Última &raquo;</a>
                  </li>";
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
        <h3 class="fw-bold mb-1">Controle da manutenção preventiva</h3>
        <h6 class="text-muted">Viaturas/Equipamentos a disposição</h6>
      </div>
        <div>
           <!-- Botão PDF -->
<button id="btnExportarPDFpreventiva" class="btn btn-danger" >
  <i class="fas fa-file-pdf"></i> Exportar PDF
</button>

<!-- Botão Excel -->
<button id="btnExportarExcelPreventiva" class="btn btn-success" >
  <i class="fas fa-file-excel"></i> Exportar Excel
</button>

         <!-- Botão para abrir modal -->

      </div>
    </div>


    <!-- Lista de usuários -->
    <div class="card">
      <div class="card-body">
<?php
// ==========================================================
// FILTROS AJUSTADOS CONFORME PERMISSÕES DO USUÁRIO
// ==========================================================

// Carregar dados da sessão (corrigido)
$nivel_usuario = $_SESSION['usuario']['nivel'] ?? 0;
$id_om_usuario = $_SESSION['usuario']['batalhao'] ?? 0;

// Definir OMs que o usuário pode visualizar
$idsVisiveis = [];

if ($nivel_usuario == 1) {
    // Nível 1 (admin) vê tudo
    $whereOM = "1=1";
} else {
    // Nível inferior: apenas OM própria e subordinadas
    $idsVisiveis = [$id_om_usuario];

    $sqlSubs = "SELECT id_om_menor FROM organizacoes_militares_sub WHERE id_om_maior = ?";
    $stmtSubs = $conexao->prepare($sqlSubs);
    $stmtSubs->bind_param("i", $id_om_usuario);
    $stmtSubs->execute();
    $resSubs = $stmtSubs->get_result();
    while ($row = $resSubs->fetch_assoc()) {
        $idsVisiveis[] = (int)$row['id_om_menor'];
    }
    $stmtSubs->close();

    // Garantir que há IDs válidos
    if (empty($idsVisiveis)) {
        $idsVisiveis = [$id_om_usuario];
    }

    // Montar cláusula WHERE
    $placeholders = implode(',', array_fill(0, count($idsVisiveis), '?'));
    $whereOM = "f.batalhao IN ($placeholders)";
}

// ==========================================================
// Filtros com base nas OMs permitidas
// ==========================================================

// Função auxiliar para executar consultas preparadas
function consultaFiltro($conexao, $sql, $idsVisiveis = [], $nivel_usuario = 0) {
    if ($nivel_usuario == 1) {
        // Substitui "WHERE CLAUSULA_OM" por "WHERE 1=1" para manter sintaxe SQL válida
        return $conexao->query(str_replace('WHERE CLAUSULA_OM', 'WHERE 1=1', $sql));
    } else {
        // Gera placeholders ? conforme quantidade de batalhões visíveis
        $placeholders = implode(',', array_fill(0, count($idsVisiveis), '?'));
        $sql = str_replace('WHERE CLAUSULA_OM', "WHERE f.batalhao IN ($placeholders)", $sql);
        
        $stmt = $conexao->prepare($sql);
        if (!$stmt) {
            die("Erro ao preparar statement: " . $conexao->error);
        }

        // Faz bind dos parâmetros inteiros
        $stmt->bind_param(str_repeat('i', count($idsVisiveis)), ...$idsVisiveis);
        $stmt->execute();
        return $stmt->get_result();
    }
}


// Prefixos SGA
$sqlPrefixos = "SELECT DISTINCT prefixo_sga FROM frota f WHERE CLAUSULA_OM ORDER BY prefixo_sga ASC";
$resPrefixos = consultaFiltro($conexao, $sqlPrefixos, $idsVisiveis, $nivel_usuario);
$prefixos = $resPrefixos->fetch_all(MYSQLI_ASSOC);

// Marcas
$sqlMarcas = "
    SELECT DISTINCT m.marca AS nome_marcas, m.id AS id_marca 
    FROM frota f 
    LEFT JOIN config_marcas m ON f.marca = m.id
    WHERE CLAUSULA_OM
    ORDER BY m.marca ASC
";
$resMarcas = consultaFiltro($conexao, $sqlMarcas, $idsVisiveis, $nivel_usuario);
$marcas = $resMarcas->fetch_all(MYSQLI_ASSOC);

// Modelos
$sqlModelos = "
    SELECT DISTINCT mo.nome_modelo, mo.id AS id_modelos
    FROM frota f
    LEFT JOIN config_modelos mo ON f.modelo = mo.id
    WHERE CLAUSULA_OM
    ORDER BY mo.nome_modelo ASC
";
$resModelos = consultaFiltro($conexao, $sqlModelos, $idsVisiveis, $nivel_usuario);
$modelos = $resModelos->fetch_all(MYSQLI_ASSOC);

// Ativos
$sqlAtivos = "SELECT DISTINCT ativo FROM frota f WHERE CLAUSULA_OM ORDER BY ativo ASC";
$resAtivos = consultaFiltro($conexao, $sqlAtivos, $idsVisiveis, $nivel_usuario);
$ativos = $resAtivos->fetch_all(MYSQLI_ASSOC);

// Status da manutenção preventiva (fixo)
$statusManutencao = [
    'Manutenção vencida',
    'Muito próxima',
    'Próxima',
    'Manutenção em dia',
    'Sem dados',
    'Em manutenção'
];
?>



<!-- Botão de filtros -->
<div class="mb-3">
  <button class="btn btn-outline-primary w-100 d-flex justify-content-center align-items-center" onclick="toggleFiltrosFichas()">
    <i class="fas fa-search me-2"></i> Filtros
  </button>
</div>

<!-- Container dos filtros -->
<div id="filtros-container-fichas" style="display: none;" class="mb-3">
  <div class="card shadow-sm border-0">
    <div class="card-body">
      <form method="GET" id="filtroFichaForm" class="row g-3">
    <!-- Batalhão / OM -->
            <?php
// 🔹 Recupera OMs visíveis para o usuário
$id_om_usuario = $_SESSION['usuario']['batalhao'] ?? null;
$nivel_usuario = $_SESSION['usuario']['nivel'] ?? 3;

function getOMsVisiveis($conexao, $id_om_usuario, $nivel_usuario) {
    $oms = [];

    if ($nivel_usuario == 1) {
        // Nível 1 vê todas
        $sql = "SELECT id, nome, abreviatura FROM organizacoes_militares ORDER BY nome";
        $res = $conexao->query($sql);
        while ($r = $res->fetch_assoc()) {
            $oms[$r['id']] = $r['abreviatura'] ?: $r['nome'];
        }
    } elseif ($nivel_usuario == 2) {
        // Nível 2 vê sua OM e subordinadas
        $sql = "
            SELECT id, nome, abreviatura FROM organizacoes_militares
            WHERE id = ? OR id IN (SELECT id_om_menor FROM organizacoes_militares_sub WHERE id_om_maior = ?)
            ORDER BY nome
        ";
        $stmt = $conexao->prepare($sql);
        $stmt->bind_param("ii", $id_om_usuario, $id_om_usuario);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($r = $res->fetch_assoc()) {
            $oms[$r['id']] = $r['abreviatura'] ?: $r['nome'];
        }
        $stmt->close();
    } else {
        // Nível 3 vê apenas sua OM
        $sql = "SELECT id, nome, abreviatura FROM organizacoes_militares WHERE id = ?";
        $stmt = $conexao->prepare($sql);
        $stmt->bind_param("i", $id_om_usuario);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($r = $res->fetch_assoc()) {
            $oms[$r['id']] = $r['abreviatura'] ?: $r['nome'];
        }
        $stmt->close();
    }

    return $oms;
}

$oms_visiveis = getOMsVisiveis($conexao, $id_om_usuario, $nivel_usuario);

// 🔹 Seleção de batalhão (filtro)
$batalhao_filtro = $_GET['batalhao'] ?? '';
?>

            <div class="col-md-3">
    <label class="form-label fw-semibold">Batalhão</label>
    <select name="batalhao" class="form-select">
        <option value="">Todos</option>
        <?php foreach ($oms_visiveis as $id => $nome): 
            $sel = ($batalhao_filtro == $id) ? 'selected' : '';
        ?>
            <option value="<?= $id ?>" <?= $sel ?>><?= htmlspecialchars($nome) ?></option>
        <?php endforeach; ?>
    </select>
</div>

        <div class="col-md-2">
          <label class="form-label fw-semibold">Tipo</label>
          <select name="tipo" class="form-select">
            <option value="">Todos</option>
            <option value="Vtr" <?= ($_GET['tipo'] ?? '') === 'Vtr' ? 'selected' : '' ?>>Viatura</option>
            <option value="Eqp" <?= ($_GET['tipo'] ?? '') === 'Eqp' ? 'selected' : '' ?>>Equipamento</option>
          </select>
        </div>

        <div class="col-md-2">
          <label class="form-label fw-semibold">Ativo</label>
          <select name="ativo" class="form-select">
            <option value="">Todos</option>
            <?php foreach ($ativos as $a): ?>
              <option value="<?= htmlspecialchars($a['ativo']) ?>" <?= (isset($_GET['ativo']) && $_GET['ativo'] === $a['ativo']) ? 'selected' : '' ?>>
                <?= htmlspecialchars($a['ativo']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="col-md-2">
          <label class="form-label fw-semibold">Prefixo SGA</label>
          <select name="prefixo_sga" class="form-select">
            <option value="">Todos</option>
            <?php foreach ($prefixos as $p): ?>
              <option value="<?= htmlspecialchars($p['prefixo_sga']) ?>" <?= (isset($_GET['prefixo_sga']) && $_GET['prefixo_sga'] === $p['prefixo_sga']) ? 'selected' : '' ?>>
                <?= htmlspecialchars($p['prefixo_sga']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

      <div class="col-md-2">
  <label class="form-label fw-semibold">Marca</label>
<select name="marca" class="form-select">
  <option value="">Todas</option>
  <?php foreach ($marcas as $m): ?>
    <option value="<?= $m['id_marca'] ?>" <?= (isset($_GET['marca']) && $_GET['marca'] == $m['id_marca']) ? 'selected' : '' ?>>
      <?= htmlspecialchars($m['nome_marcas']) ?>
    </option>
  <?php endforeach; ?>
</select>
</div>

        <div class="col-md-2">
          <label class="form-label fw-semibold">Modelo</label>
          <select name="modelo" class="form-select">
            <option value="">Todos</option>
            <?php foreach ($modelos as $mo): ?>
              <option value="<?= htmlspecialchars($mo['id_modelos']) ?>" <?= (isset($_GET['modelo']) && $_GET['modelo'] === $mo['nome_modelo']) ? 'selected' : '' ?>>
                <?= htmlspecialchars($mo['nome_modelo']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="col-md-2">
          <label class="form-label fw-semibold">Número do patrimônio</label>
          <input type="text" class="form-control" name="nmr_patrimonio" value="<?= $_GET['nmr_patrimonio'] ?? '' ?>">
        </div>

        <div class="col-md-2">
          <label class="form-label fw-semibold">Chassi</label>
          <input type="text" class="form-control" name="chassi" value="<?= $_GET['chassi'] ?? '' ?>">
        </div>

        <div class="col-md-2">
          <label class="form-label fw-semibold">Status manutenção</label>
          <select name="status_manut" class="form-select">
            <option value="">Todos</option>
            <?php foreach ($statusManutencao as $s): ?>
              <option value="<?= $s ?>" <?= (isset($_GET['status_manut']) && $_GET['status_manut'] === $s) ? 'selected' : '' ?>>
                <?= $s ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="col-12 d-flex justify-content-between mt-3">
          <button type="button" id="btnLimparFiltrosFichas" class="btn btn-outline-secondary">Limpar Filtros</button>
          <button type="submit" class="btn btn-primary px-4">Aplicar Filtros</button>
        </div>

      </form>
    </div>
  </div>
</div>


<!-- Select de limite por página -->
<div class="mb-3">
  <label for="limiteFichas" class="me-2 mb-0">Mostrar</label>
  <select id="limiteFichas" name="limite" class="form-select d-inline w-auto">
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

<?= renderPaginacaoFICHAS($pagina ?? 1, $totalPaginas ?? 1, $limite ?? 10, $queryString ?? '') ?>
          
<div class="row g-3">
<?php if (empty($viaturasFiltradas)): ?>
    <div class="col-12">
        <div class="alert alert-warning text-center py-4 shadow-sm border-0 rounded-3">
            <i class="fas fa-exclamation-circle me-2"></i>
            <strong>Nenhuma viatura/equipamento encontrado</strong> para este filtro ou para as OMs permitidas.
        </div>
    </div>
<?php else: ?>
    <?php foreach ($viaturas as $v): 
        $manut = $v['manut'];

        // Datas formatadas
        $dataLimiteTempo = $manut['data_limite_tempo'] ?? '--';
        $dataLimiteOdo   = $manut['data_limite_odo'] ?? '--';
        $dataPrevOdo     = $manut['data_prev_odometro'] ?? '--';
    ?>
        <div class="col-12">
            <div class="card shadow-sm border-0 rounded-3 mb-3">
                <div class="card-body">
                    <div class="row align-items-center gy-3">
                        
                        <!-- Foto -->
                        <div class="col-md-2 text-center">
                            <img src="uploads/frotas/<?= htmlspecialchars($v['foto_capa'] ?: 'base.jpg') ?>" 
                                 class="img-fluid rounded shadow-sm" style="max-height:90px; object-fit:cover;">
                        </div>

                        <!-- Informações básicas -->
                        <div class="col-md-3">
                            <h5 class="fw-bold text-primary mb-1">
                                <?= htmlspecialchars($v['prefixo_sga']) ?>
                            </h5>
                            <p class="text-muted small mb-0">
                                <i class="fas fa-car me-1"></i> 
                                <?= htmlspecialchars($v['marca_nome'] ?? 'N/D') ?> 
                                <?= htmlspecialchars($v['modelo_nome'] ?? '') ?>
                            </p>
                        </div>

                        <!-- Dados da OS -->
                        <?php if (!empty($manut['os'])): ?>
                            <div class="col-md-3 small">
                                <div class="bg-light rounded p-2 h-100">
                                    <span class="fw-semibold d-block text-secondary mb-1">
                                        <i class="fas fa-tools me-1"></i> OS #<?= htmlspecialchars($manut['os']['id']) ?>
                                    </span>
                                    <div class="text-muted">
                                        <i class="fas fa-calendar-alt me-1"></i> Abertura: <?= date('d/m/Y', strtotime($manut['os']['data_abertura'])) ?><br>
                                        <i class="fas fa-tachometer-alt me-1"></i> Odo na manutenção: <?= htmlspecialchars($manut['os_odometro_momento']) ?><br>
                                        <i class="fas fa-clock me-1"></i> Intervalo tempo: <?= htmlspecialchars($manut['os_prox_tempo_max']) ?> meses<br>
                                        <i class="fas fa-road me-1"></i> Intervalo odo/hor: <?= htmlspecialchars($manut['os_prox_odo_max']) ?>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- Limites calculados -->
                        <div class="col-md-2 small">
                            <div class="bg-light rounded p-2 h-100">
                                <span class="fw-semibold text-secondary d-block mb-1">
                                    <i class="fas fa-chart-line me-1"></i> Próximos limites
                                </span>
                                <div class="text-muted">
                                    <i class="fas fa-calendar me-1"></i> Tempo: <?= $dataLimiteTempo ?><br>
                                    <i class="fas fa-road me-1"></i> Odo/Hor: <?= $dataLimiteOdo ?><br>
                                    <i class="fas fa-hourglass-half me-1"></i> Previsão Odo: <?= $dataPrevOdo ?>
                                </div>
                            </div>
                        </div>

                        <!-- Status -->
                        <div class="col-md-2 text-center">
                            <div class="fw-semibold mb-1 fs-6">
                                <?= is_numeric($manut['dias_restantes']) 
                                    ? round($manut['dias_restantes']) . ' dias' 
                                    : htmlspecialchars($manut['dias_restantes']) ?>
                            </div>
                            <span class="badge px-3 py-2 bg-<?=
                                $manut['os_em_andamento'] ? 'secondary' :
                                (strpos($manut['status'], 'vencida') !== false ? 'danger' :
                                (strpos($manut['status'], 'próxima') !== false ? 'warning' :
                                (strpos($manut['status'], 'Sem dados') !== false ? 'dark' :
                                (strpos($manut['status'], 'Dados não cadastrados') !== false ? 'dark' : 'success'))))
                            ?> rounded-pill">
                                <?= htmlspecialchars($manut['status']) ?>
                            </span>
                            <small class="d-block mt-2 text-muted">
                                <i class="fas fa-tachometer-alt me-1"></i> Atual: <?= $manut['odometro_atual'] ?? '--' ?><br>
                                <i class="fas fa-road me-1"></i> Faltam: <?= $manut['km_restante'] ?? '--' ?><br>
                                <i class="fas fa-chart-area me-1"></i> Média/dia: <?= $manut['km_por_dia'] ? round($manut['km_por_dia'],2) : '--' ?>
                            </small>
                        </div>
                    </div>

                    <!-- Resumo + Botões -->
                    <div class="mt-3 d-flex justify-content-between align-items-center flex-wrap">
                        
                        <!-- Resumo do realizado -->
                        <?php if (!empty($manut['resumo_realizado'])): ?>
                            <div class="text-start text-muted small me-2 flex-grow-1">
                                <i class="fas fa-check-circle text-success me-1"></i> 
                                <span class="fst-italic">Realizado na última manutenção: <?= htmlspecialchars($manut['resumo_realizado']) ?></span>
                            </div>
                        <?php endif; ?>

                        <!-- Botões -->
                        <div class="text-end">
                            <?php if (!empty($manut['os'])): ?>
                                <!-- Botões para OS -->
                            <?php else: ?>
                                <span class="text-muted small fst-italic">
                                    Nenhuma OS preventiva encontrada
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
<?php endif; ?>
</div>


<?= renderPaginacaoFichas($pagina ?? 1, $totalPaginas ?? 1, $limite ?? 10, $queryString ?? '') ?>


  </div>
</div>
<script>
    window.funcaoInicializacao = 'inicializarPreventiva';    
</script>