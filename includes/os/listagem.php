<?php
header('Content-Type: text/html; charset=utf-8');
session_start();

require_once '../api/seguranca.php';

$permissoes = verificarPermissao(15);

$pode_cadastrar = $permissoes['cadastrar'];
$pode_editar    = $permissoes['editar'];
$pode_deletar   = $permissoes['deletar'];
$pode_importar  = $permissoes['importar'];

include_once('../../conexao/config.php');

?>
<?php
if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['usuario_id'])) {
    http_response_code(403);
    exit('Acesso negado.');
}
include_once('../../conexao/config.php');

/**
 * (Opcional) Em alguns hosts isso ajuda, em outros é bloqueado.
 * Não resolve sozinho se a query for pesada, mas é seguro tentar.
 */
@$conexao->query("SET SESSION SQL_BIG_SELECTS=1");

// ==========================
// 🔹 Dados do usuário logado
// ==========================
$id_om_usuario = isset($_SESSION['usuario']['batalhao']) ? (int) $_SESSION['usuario']['batalhao'] : null;
$nivel_usuario = isset($_SESSION['usuario']['nivel']) ? (int) $_SESSION['usuario']['nivel'] : 3;

// ==========================
// 🔹 Filtros via GET
// ==========================
$id_os           = $_GET['id_os'] ?? '';
$status          = $_GET['status'] ?? '';
$secao           = $_GET['secao'] ?? '';
$tipomnt         = $_GET['tipomnt'] ?? '';
$solicitante     = $_GET['solicitante'] ?? '';
$prefixo         = $_GET['prefixo'] ?? '';
$data_ini        = $_GET['data_ini'] ?? '';
$data_fim        = $_GET['data_fim'] ?? '';
$batalhao_filtro = isset($_GET['batalhao']) ? (int) $_GET['batalhao'] : '';

// ==========================
// 🔹 Paginação
// ==========================
$limite = (isset($_GET['limite']) && is_numeric($_GET['limite'])) ? (int)$_GET['limite'] : 10;
$limite = max(1, min(100, $limite)); // trava para não deixarem gigante
$pagina = (isset($_GET['pagina']) && is_numeric($_GET['pagina']) && $_GET['pagina'] > 0) ? (int)$_GET['pagina'] : 1;
$offset = ($pagina - 1) * $limite;

// ==========================
// 🔹 Inicializa variáveis de filtros
// ==========================
$filtros = [];
$params  = [];
$tipos   = '';

// ==========================
// 🔹 Filtros dinâmicos
// ==========================
if ($id_os !== '' && is_numeric($id_os)) {
    $filtros[] = "os.id = ?";
    $params[]  = (int)$id_os;
    $tipos    .= 'i';
}

if (!empty($status)) {
    $filtros[] = "os.status = ?";
    $params[]  = $status;
    $tipos    .= 's';
}

if (!empty($tipomnt)) {
    $filtros[] = "os.tipo_mnt = ?";
    $params[]  = $tipomnt;
    $tipos    .= 's';
}

if (!empty($secao)) {
    $filtros[] = "os.secao_rspns LIKE ?";
    $params[]  = '%' . $secao . '%';
    $tipos    .= 's';
}

if (!empty($solicitante)) {
    $filtros[] = "os.solicitante LIKE ?";
    $params[]  = '%' . $solicitante . '%';
    $tipos    .= 's';
}

if (!empty($prefixo)) {
    $filtros[] = "COALESCE(f.prefixo_sga, os.prefixo_sga) LIKE ?";
    $params[]  = '%' . $prefixo . '%';
    $tipos    .= 's';
}

if (!empty($data_ini)) {
    // inclui a partir do início do dia
    $filtros[] = "os.data_abertura >= CONCAT(?, ' 00:00:00')";
    $params[]  = $data_ini; // YYYY-MM-DD
    $tipos    .= 's';
}

if (!empty($data_fim)) {
    // inclui o dia inteiro (usa < dia seguinte)
    $filtros[] = "os.data_abertura < DATE_ADD(CONCAT(?, ' 00:00:00'), INTERVAL 1 DAY)";
    $params[]  = $data_fim; // YYYY-MM-DD
    $tipos    .= 's';
}

// ==============================
// 🔹 Controle de visualização por OM
// ==============================
$idsVisiveis = [];

if ($nivel_usuario === 1) {
    // Nível 1 → todas as OMs
    $sqlAllOms = "SELECT id FROM organizacoes_militares";
    $resAll = $conexao->query($sqlAllOms);
    if ($resAll) {
        while ($r = $resAll->fetch_assoc()) {
            $idsVisiveis[] = (int)$r['id'];
        }
    }
} else {
    // Nível 2 ou 3 → própria + subordinadas
    if ($id_om_usuario !== null) {
        $idsVisiveis[] = (int)$id_om_usuario;

        $sqlSubs = "SELECT id_om_menor FROM organizacoes_militares_sub WHERE id_om_maior = ?";
        $stmtSubs = $conexao->prepare($sqlSubs);
        if ($stmtSubs) {
            $stmtSubs->bind_param("i", $id_om_usuario);
            $stmtSubs->execute();
            $resSubs = $stmtSubs->get_result();
            while ($r = $resSubs->fetch_assoc()) {
                $idsVisiveis[] = (int)$r['id_om_menor'];
            }
            $stmtSubs->close();
        }
    }
}

// ==============================
// 🔹 Filtro manual por batalhão
// ==============================
if (!empty($batalhao_filtro)) {
    if (!empty($idsVisiveis) && in_array($batalhao_filtro, $idsVisiveis, true)) {
        $filtros[] = "os.batalhao = ?";
        $params[]  = $batalhao_filtro;
        $tipos    .= 'i';
    } else {
        $filtros[] = "1=0";
    }
} else {
    if (!empty($idsVisiveis)) {
        $placeholders = implode(',', array_fill(0, count($idsVisiveis), '?'));
        $filtros[] = "os.batalhao IN ($placeholders)";
        foreach ($idsVisiveis as $idVis) {
            $params[] = $idVis;
            $tipos   .= 'i';
        }
    } else {
        $filtros[] = "1=0";
    }
}

// ==============================
// 🔹 WHERE final
// ==============================
$whereSQL = '';
if (!empty($filtros)) {
    $whereSQL = 'WHERE ' . implode(' AND ', $filtros);
}

// ======================================================
// ✅ 1) CONTAGEM TOTAL (mais leve: sem joins desnecessários)
// ======================================================
$sqlTotal = "
    SELECT COUNT(*) AS total
    FROM os_principal os
    JOIN frota f ON os.id_frota = f.id
    $whereSQL
";
$stmtTotal = $conexao->prepare($sqlTotal);
if ($stmtTotal === false) {
    die("Erro ao preparar contagem: " . $conexao->error);
}
if (!empty($params)) {
    $stmtTotal->bind_param($tipos, ...$params);
}
$stmtTotal->execute();
$resTotal = $stmtTotal->get_result();
$totalRegistros = (int)($resTotal->fetch_assoc()['total'] ?? 0);
$stmtTotal->close();

$totalPaginas = max((int)ceil($totalRegistros / $limite), 1);
if ($pagina > $totalPaginas) {
    $pagina = $totalPaginas;
    $offset = ($pagina - 1) * $limite;
}

// ======================================================
// ✅ 2) PRIMEIRO: pegar SÓ os IDs da página (query leve)
// ======================================================
$sqlIds = "
    SELECT os.id
    FROM os_principal os
    JOIN frota f ON os.id_frota = f.id
    $whereSQL
    ORDER BY os.id DESC
    LIMIT ? OFFSET ?
";
$paramsIds = $params;
$tiposIds  = $tipos . "ii";
$paramsIds[] = $limite;
$paramsIds[] = $offset;

$stmtIds = $conexao->prepare($sqlIds);
if ($stmtIds === false) {
    die("Erro ao preparar IDs: " . $conexao->error);
}
$stmtIds->bind_param($tiposIds, ...$paramsIds);
$stmtIds->execute();
$resIds = $stmtIds->get_result();

$idsPagina = [];
while ($row = $resIds->fetch_assoc()) {
    $idsPagina[] = (int)$row['id'];
}
$stmtIds->close();

// Se não tem IDs, já encerra a listagem (sem estourar join size)
$result = null;

if (!empty($idsPagina)) {
    // ======================================================
    // ✅ 3) AGORA: buscar detalhes APENAS desses IDs
    //     - sem subquery agrupada varrendo tabela inteira
    //     - subqueries correlacionadas (rápidas com índice)
    // ======================================================
    $ph = implode(',', array_fill(0, count($idsPagina), '?'));
    $tiposDetalhe = str_repeat('i', count($idsPagina));

   $sqlDetalhe = "
    SELECT 
        os.id AS os_id,
        os.data_abertura, 
        os.odometro_horimetro,
        os.status, 
        os.problema,
        os.batalhao,
        os.data_encerramento,
        os.observacao,
        os.solicitante, 
        os.secao_rspns, 
        os.causa_indisponibilidade,

        COALESCE(f.prefixo_sga, os.prefixo_sga) AS prefixo_sga,
        f.tipo,

        cmarca.marca AS marca_nome,
        cmod.nome_modelo AS modelo_nome,

        (
            SELECT cm.odometro
            FROM controle_medicoes cm
            WHERE cm.viatura_id = f.id
              AND cm.data = CURDATE()
            LIMIT 1
        ) AS odometro,

        (
            SELECT GROUP_CONCAT(DISTINCT r.rlzd_mnt ORDER BY r.rlzd_mnt SEPARATOR ' | ')
            FROM os_rlzdmnt r
            WHERE r.id_osprincipal = os.id
        ) AS servicos_realizados,

        (
            SELECT GROUP_CONCAT(DISTINCT i.itens_utilizados ORDER BY i.itens_utilizados SEPARATOR ' | ')
            FROM os_itens i
            WHERE i.id_osprincipal = os.id
        ) AS materiais_utilizados

    FROM os_principal os
    LEFT JOIN frota f ON os.id_frota = f.id
    LEFT JOIN config_marcas cmarca ON f.marca = cmarca.id
    LEFT JOIN config_modelos cmod  ON f.modelo = cmod.id

    WHERE os.id IN ($ph)
    ORDER BY os.id DESC
";

    $stmt = $conexao->prepare($sqlDetalhe);
    if ($stmt === false) {
        die("Erro ao preparar detalhes: " . $conexao->error);
    }
    $stmt->bind_param($tiposDetalhe, ...$idsPagina);
    $stmt->execute();
    $result = $stmt->get_result();
    // não feche $stmt aqui se você usa $result depois em HTML; feche depois de consumir.
}

// ==============================
// 🔹 Função de paginação
// ==============================
function renderPaginacaoOS($pagina, $totalPaginas, $limite, $queryString, $arquivo = 'includes/os/listagem.php') {
    if ($totalPaginas <= 1) return '';

    $html  = '<div class="pagination-wrapper">';
    $html .= '<nav><ul class="pagination pagination-sm justify-content-center">';

    if ($pagina > 1) {
        $url = "{$arquivo}?{$queryString}&pagina=1&limite={$limite}";
        $html .= "<li class='page-item'><a class='page-link paginacao-os' href='#' data-page='{$url}'>&laquo;</a></li>";

        $url = "{$arquivo}?{$queryString}&pagina=".($pagina-1)."&limite={$limite}";
        $html .= "<li class='page-item'><a class='page-link paginacao-os' href='#' data-page='{$url}'>&lsaquo;</a></li>";
    }

    $inicio = max(1, $pagina - 4);
    $fim    = min($totalPaginas, $pagina + 4);

    if ($inicio > 1) $html .= "<li class='page-item disabled'><span class='page-link'>...</span></li>";

    for ($i = $inicio; $i <= $fim; $i++) {
        $ativo = ($i == $pagina) ? 'active' : '';
        $url = "{$arquivo}?{$queryString}&pagina={$i}&limite={$limite}";
        $html .= "<li class='page-item {$ativo}'><a class='page-link paginacao-os' href='#' data-page='{$url}'>{$i}</a></li>";
    }

    if ($fim < $totalPaginas) $html .= "<li class='page-item disabled'><span class='page-link'>...</span></li>";

    if ($pagina < $totalPaginas) {
        $url = "{$arquivo}?{$queryString}&pagina=".($pagina+1)."&limite={$limite}";
        $html .= "<li class='page-item'><a class='page-link paginacao-os' href='#' data-page='{$url}'>&rsaquo;</a></li>";

        $url = "{$arquivo}?{$queryString}&pagina={$totalPaginas}&limite={$limite}";
        $html .= "<li class='page-item'><a class='page-link paginacao-os' href='#' data-page='{$url}'>&raquo;</a></li>";
    }

    $html .= '</ul></nav></div>';
    return $html;
}

// ==============================
// 🔹 Mantém query string dos filtros (para paginação)
// ==============================
$paramsGET = $_GET;
unset($paramsGET['pagina']);
$queryString = http_build_query($paramsGET);

$filtrosVisiveis = !empty($_GET);

// A partir daqui segue seu HTML (tabela) usando:
// - $result (pode ser null se vazio)
// - $totalRegistros, $totalPaginas, $pagina, etc.
?>




<style>
    .btn-group-responsive {
  display: flex;
  gap: 8px;
  flex-wrap: wrap;
  margin-top: 10px;
}
@media (min-width: 768px) {
  .btn-group-responsive {
    flex: 0 0 auto;
    margin-left: auto;
  }
  .btn-icon {
    display: none;
  }
}
@media (max-width: 767px) {
  .btn-text {
    display: none;
  }
  .btn-group-responsive {
    margin-top: 10px;
    width: 100%;
  }
}
.btn-group .btn {
  border-radius: 20px;
  transition: all 0.3s ease;
    margin-left: 0;
    }
  .filtro-label { font-size: 0.85rem; font-weight: 600; color: #555; }
  .filtros-container.hidden { display: none; }
  .pagination-wrapper { display: flex; justify-content: flex-end; margin-top: 1rem; }
    #filtros-container-os {
  overflow: hidden;
  transition: height 0.3s ease, opacity 0.3s ease;
}
    .modal-xl .modal-body {
  max-height: 80vh;
  overflow-y: auto;
}
    .card h6 {
    font-size: 1rem;
}
.card p {
    font-size: 0.875rem;
}
.list-group-item {
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}
.list-group-item:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 18px rgba(0,0,0,0.12);
}
.badge {
    font-size: 0.75rem;
    padding: 0.35em 0.65em;
}
</style>

<div class="container">
  <div class="page-inner">
    
    <div class="d-flex justify-content-between align-items-center py-3">
      <div>
        <h3 class="fw-bold mb-1">Listagem das ordens de serviço</h3>
        <h6 class="text-muted">Listagem das ordens de serviços realizadas ou em andamento.</h6>
      </div>
      <div>
		  <?php if ($pode_cadastrar): ?>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalCadastroOS">
          <i class="fa fa-user-plus me-1"></i> Abrir Ordem de Serviço
        </button>
		 <?php endif; ?>
         <!-- Botão para abrir modal -->
		  <?php if ($pode_importar): ?>
<button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#modalImportarOS">
  Importar OS
</button>
		 <?php endif; ?>
           <button id="btnExportarExcelOS" class="btn btn-success">
  <i class="fas fa-file-excel"></i> Exportar Excel
</button>
      </div>
    </div>

   <!-- Botão para mostrar/ocultar filtros -->
<div class="mb-3">
  <button class="btn btn-outline-primary w-100 d-flex justify-content-center align-items-center" onclick="toggleFiltrosOS()">
    <i class="fas fa-search me-2"></i> Filtros
  </button>
</div>

<div id="filtros-container-os" style="display: none;" class="mb-3">
  <div class="card shadow-sm border-0">
    <div class="card-body">
      <form method="GET" id="filtroOSForm">
        <div class="row g-3">

            <!-- Número da OS -->
            <div class="col-md-2">
              <label class="form-label fw-semibold">Número da OS</label>
              <input type="text" class="form-control" name="id_os" value="<?= htmlspecialchars($id_os) ?>">
            </div>

            <!-- Status -->
            <div class="col-md-2">
              <label class="form-label fw-semibold">Status</label>
              <select class="form-select" name="status">
                <option value="">Todos</option>
                <option <?= $status == 'Em andamento' ? 'selected' : '' ?>>Em andamento</option>
                <option <?= $status == 'Concluída' ? 'selected' : '' ?>>Concluída</option>
                <option <?= $status == 'Aguardando Peças' ? 'selected' : '' ?>>Aguardando Peças</option>
                <option <?= $status == 'Aguardando Suprimento' ? 'selected' : '' ?>>Aguardando Suprimento</option>
                <option <?= $status == 'Aguardando Descarga' ? 'selected' : '' ?>>Aguardando Descarga</option>
                <option <?= $status == 'Eqp/Vtr descarregado' ? 'selected' : '' ?>>Eqp/Vtr descarregado</option>
              </select>
            </div>
            
                        <div class="col-md-2">
              <label class="form-label fw-semibold">Oficina</label>
              <select class="form-select" name="secao">
                <option value="">Todas oficinas</option>
                <option <?= $secao == 'Mecânica Leve' ? 'selected' : '' ?>>Mecânica Leve</option>
                <option <?= $secao == 'Mecânica Pesada' ? 'selected' : '' ?>>Mecânica Pesada</option>
                <option <?= $secao == 'Borracharia' ? 'selected' : '' ?>>Borracharia</option>
                <option <?= $secao == 'Elétrica' ? 'selected' : '' ?>>Elétrica</option>
                <option <?= $secao == 'Solda' ? 'selected' : '' ?>>Solda</option>
                <option <?= $secao == 'Lubrificação' ? 'selected' : '' ?>>Lubrificação</option>
                <option <?= $secao == 'Pintura' ? 'selected' : '' ?>>Pintura</option>
                <option <?= $secao == 'Outra oficina' ? 'selected' : '' ?>>Outra oficina</option>
              </select>
            </div>
            
             <div class="col-md-2">
              <label class="form-label fw-semibold">Tipo Mnt</label>
              <select class="form-select" name="tipomnt">
                <option value="">Todos tipos de manutenção</option>
                <option <?= $tipomnt == 'Manutenção Preventiva' ? 'selected' : '' ?>>Manutenção Preventiva</option>
                <option <?= $tipomnt == 'Manutenção Preditiva' ? 'selected' : '' ?>>Manutenção Preditiva</option>
                <option <?= $tipomnt == 'Manutenção Corretiva' ? 'selected' : '' ?>>Manutenção Corretiva</option>
              </select>
            </div>


            <!-- Solicitante -->
            <div class="col-md-2">
              <label class="form-label fw-semibold">Solicitante</label>
              <input type="text" class="form-control" name="solicitante" value="<?= htmlspecialchars($solicitante) ?>">
            </div>

            <!-- Prefixo -->
            <div class="col-md-2">
              <label class="form-label fw-semibold">Prefixo</label>
              <input type="text" class="form-control" name="prefixo" value="<?= htmlspecialchars($prefixo) ?>">
            </div>

            <!-- Data Inicial -->
            <div class="col-md-2">
              <label class="form-label fw-semibold">Data Inicial</label>
              <input type="date" class="form-control" name="data_ini" value="<?= htmlspecialchars($data_ini) ?>">
            </div>

            <!-- Data Final -->
            <div class="col-md-2">
              <label class="form-label fw-semibold">Data Final</label>
              <input type="date" class="form-control" name="data_fim" value="<?= htmlspecialchars($data_fim) ?>">
            </div>

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

            <!-- Botões -->
            <div class="col-12 d-flex justify-content-between mt-2">
              <button type="button" id="btnLimparFiltrosOS" class="btn btn-black ms-2">Limpar Filtros</button>
              <button type="submit" class="btn btn-primary px-4">Aplicar</button>
            </div>

        </div>
      </form>
    </div>
  </div>
</div>


<!-- Select de limite por página -->
<div class="mb-3">
  <label for="limiteOS" class="me-2 mb-0">Mostrar</label>
  <select id="limiteOS" name="limite" class="form-select d-inline w-auto" onchange="atualizarLimiteOS()">
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
  <!-- Paginação superior -->
<div class="paginacao mb-3">
  <?= renderPaginacaoOS($pagina, $totalPaginas, $limite, $queryString, 'includes/os/listagem.php'); ?>
</div>

<div class="row g-3">
  <?php if ($result && $result->num_rows > 0): ?>
  <?php while ($os = $result->fetch_assoc()): ?>
    <div class="col-12 col-md-6 col-lg-4">
      <div class="card border-0 shadow-sm h-100 rounded-4">
        <div class="card-body d-flex flex-column">
          <!-- Cabeçalho -->
          <div class="d-flex justify-content-between align-items-start mb-2">
            <div>
              <h6 class="fw-bold text-primary mb-1">
                OS #<?= $os['os_id'] ?> - <?= htmlspecialchars($os['prefixo_sga']) ?>
              </h6>
<?php
if (!function_exists('dataValida')) {
    function dataValida($data){
        return !empty($data)
            && $data !== '0000-00-00'
            && $data !== '0000-00-00 00:00:00';
    }
}
?>

<ul class="list-unstyled small text-body-secondary mb-3">

<li>
    <small class="text-muted">
        <b>Abertura:</b>
        <?= dataValida($os['data_abertura']) 
            ? date('d/m/Y', strtotime($os['data_abertura'])) 
            : 'Não informada' ?>
    </small>
</li>

<?php if (dataValida($os['data_encerramento'])): ?>
<li>
    <small class="text-muted">
        <b>Encerramento:</b>
        <?= date('d/m/Y', strtotime($os['data_encerramento'])) ?>
    </small>
</li>
<?php endif; ?>

</ul>
            
            </div>
            <span class="badge rounded-pill bg-<?= $os['status'] === 'Aberta' ? 'warning' : ($os['status'] === 'Concluída' ? 'success' : 'secondary') ?> text-dark">
              <?= htmlspecialchars($os['status']) ?>
            </span>
          </div>

          <!-- Marca e Modelo -->
          <p class="mb-2 text-secondary fst-italic">
            <?= htmlspecialchars($os['marca_nome'] ?? '—') ?> - <?= htmlspecialchars($os['modelo_nome'] ?? '—') ?>
          </p>

          <!-- Informações detalhadas -->
          <ul class="list-unstyled small text-body-secondary mb-3">
            <li><i class="fas fa-user me-1"></i> <strong>Solicitante:</strong> <?= $os['solicitante'] ?? '—' ?></li>
            <li><i class="fas fa-cogs me-1"></i> <strong>Problema:</strong> <?= $os['problema'] ?? '—' ?></li>
            <li><i class="fas fa-exclamation-triangle me-1"></i> <strong>Causa indisponibilidade:</strong> <?= $os['causa_indisponibilidade'] ?? '—' ?></li>
            <li><i class="fas fa-building me-1"></i> <strong>Seção responsável:</strong> <?= $os['secao_rspns'] ?? '—' ?></li>
            <li>
              <i class="fas fa-tachometer-alt me-1"></i> 
              <strong>Odômetro/Horímetro entrada:</strong> 
              <?= isset($os['odometro_horimetro']) 
                  ? number_format($os['odometro_horimetro'], 2, ',', '.') . 
                    (isset($os['tipo']) && $os['tipo'] === 'Eqp' ? ' h' : ' Km') 
                  : '—' ?>
            </li>
            <li>
              <?php if ($os['tipo'] === 'Eqp'): ?>
                <i class="fas fa-hourglass-half me-1"></i>
                <strong>Horímetro atual:</strong> <?= $os['horimetro'] ?? '—' ?> h
              <?php elseif ($os['tipo'] === 'Vtr'): ?>
                <i class="fas fa-road me-1"></i>
                <strong>Odômetro atual:</strong> <?= $os['odometro'] ?? '—' ?> km
              <?php else: ?>
                <i class="fas fa-road me-1"></i>
                <strong>Odômetro atual:</strong> <?= $os['odometro'] ?? '—' ?> km |
                <i class="fas fa-hourglass-half me-1"></i>
                <strong>Horímetro atual:</strong> <?= $os['horimetro'] ?? '—' ?> h
              <?php endif; ?>
            </li>
          </ul>
            
            <?php if (!empty($os['servicos_realizados'])): ?>
  <div class="mb-2">
    <small class="fw-bold text-success">
      <i class="fas fa-tools me-1"></i> Serviços realizados:
    </small>
    <div class="small text-body-secondary">
      <?= htmlspecialchars($os['servicos_realizados']) ?>
    </div>
  </div>
<?php endif; ?>

<?php if (!empty($os['materiais_utilizados'])): ?>
  <div class="mb-2">
    <small class="fw-bold text-primary">
      <i class="fas fa-box-open me-1"></i> Materiais / Peças utilizadas:
    </small>
    <div class="small text-body-secondary">
      <?= htmlspecialchars($os['materiais_utilizados']) ?>
    </div>
  </div>
<?php endif; ?>

<?php if (!empty($os['materiais_utilizados'])): ?>
  <div class="mb-2">
    <small class="fw-bold text-primary">
      <i class="fas fa-box-open me-1"></i> Materiais / Peças utilizadas:
    </small>
    <div class="small text-body-secondary">
      <?= htmlspecialchars($os['materiais_utilizados']) ?>
    </div>
  </div>
<?php endif; ?>
            <?php if (!empty($os['observacao'])): ?>
  <div class="mb-2">
    <small class="fw-bold text-primary">
      <i class="fas fa-info-circle me-1"></i> Observações registradas:
    </small>
    <div class="small text-body-secondary">
      <?= htmlspecialchars($os['observacao']) ?>
    </div>
  </div>
<?php endif; ?>


          <!-- Ações -->
          <div class="mt-auto d-flex flex-wrap gap-2">
            <!-- Ver OS -->
            <button class="btn btn-sm btn-outline-primary d-flex align-items-center"
                    onclick="verOS(<?= $os['os_id'] ?>)"
                    data-bs-toggle="modal"
                    data-bs-target="#modalVerOS">
              <i class="fas fa-eye me-1"></i> Ver OS
            </button>

            <!-- Editar OS -->
            <button class="btn btn-sm btn-outline-warning d-flex align-items-center"
                    onclick="editarOS(<?= $os['os_id'] ?>)"
                    data-bs-toggle="modal"
                    data-bs-target="#modalEditarOS">
              <i class="fas fa-edit me-1"></i> Editar
            </button>

            <!-- Imprimir -->
            <div class="dropdown">
              <button class="btn btn-sm btn-outline-secondary dropdown-toggle"
                      type="button"
                      id="dropdownMenu<?= $os['os_id'] ?>"
                      data-bs-toggle="dropdown"
                      aria-expanded="false">
                <i class="fas fa-print me-1"></i> Imprimir
              </button>
              <ul class="dropdown-menu dropdown-menu-end shadow-sm rounded" aria-labelledby="dropdownMenu<?= $os['os_id'] ?>">
                <li>
                  <a href="#" class="dropdown-item text-danger btnExportarPDFos" data-id="<?= $os['os_id'] ?>">
                    <i class="fas fa-file-pdf me-2"></i> OS p/ preenchimento
                  </a>
                </li>
                <li>
                  <a href="#" class="dropdown-item text-primary btnExportarPDFveros" data-id="<?= $os['os_id'] ?>">
                    <i class="fas fa-eye me-2"></i> Ver OS preenchida
                  </a>
                </li>
              </ul>
            </div>

            <!-- Remover -->
            <button type="button" 
                    class="btn btn-sm btn-outline-danger"
                    data-id="<?= $os['os_id'] ?>"
                    onclick="deletarOS(this)"
                    data-bs-toggle="tooltip"
                    title="Remover">
              <i class="fas fa-times"></i>
            </button>
          </div>
        </div>
      </div>
    </div>
    <?php endwhile; ?>
<?php else: ?>

<div class="col-12">
  <div class="alert alert-light border text-center py-4">
    <i class="fas fa-folder-open fa-2x text-secondary mb-2"></i><br>
    <strong>Nenhuma ordem de serviço encontrada.</strong>
  </div>
</div>

<?php endif; ?>
<!-- Paginação inferior -->
<div class="paginacao mt-3">
  <?= renderPaginacaoOS($pagina, $totalPaginas, $limite, $queryString, 'includes/os/listagem.php'); ?>
</div>
  </div>





<!-- Modal de Cadastro -->
<div class="modal fade" id="modalCadastroOS" tabindex="-1" aria-labelledby="modalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-xl">
    <div class="modal-content">
      
      <div class="modal-header bg-success text-white">
        <h5 class="modal-title" id="modalLabel">
          <i class="bi bi-gear-fill me-2"></i> Abrir Ordem de Serviço
        </h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Fechar"></button>
      </div>

      <div class="modal-body bg-light">
        <form method="POST" enctype="multipart/form-data" id="form-os-abrir" class="needs-validation" novalidate>
          
          <!-- ======================== BLOCO 1: DADOS GERAIS ======================== -->
          <div class="card mb-3 shadow-sm">
            <div class="card-header fw-bold bg-body-secondary">
              <i class="bi bi-building me-2"></i>Dados da Organização Militar
            </div>
            <div class="card-body">
              <?php
              // ============================
              // BATALHÕES QUE O USUÁRIO PODE VISUALIZAR
              // ============================
              $id_om_usuario = $_SESSION['usuario']['batalhao'] ?? 0;
              $sqlNivel = "SELECT nivel FROM organizacoes_militares WHERE id = $id_om_usuario";
              $resNivel = $conexao->query($sqlNivel);
              $nivelUsuario = $resNivel->fetch_assoc()['nivel'] ?? 0;

              if ($nivelUsuario == 1) {
                  $sqlBatalhoes = "SELECT id, nome, abreviatura FROM organizacoes_militares ORDER BY nome";
              } else {
                  $sqlBatalhoes = "
                      SELECT om.id, om.nome, om.abreviatura
                      FROM organizacoes_militares om
                      WHERE om.id = $id_om_usuario
                      OR om.id IN (
                          SELECT id_om_menor FROM organizacoes_militares_sub WHERE id_om_maior = $id_om_usuario
                      )
                      ORDER BY om.nome
                  ";
              }
              $resBatalhoes = $conexao->query($sqlBatalhoes);
              ?>

              <div class="row g-3">
                <div class="col-md-6">
                  <label class="form-label">Batalhão</label>
                  <select name="batalhao" id="batalhao_importacao" class="form-select rounded-pill shadow-sm" required>
                    <option value="">Selecione...</option>
                    <?php while ($bat = $resBatalhoes->fetch_assoc()): ?>
                      <option value="<?= $bat['id'] ?>" <?= ($bat['id']==$id_om_usuario)?'selected':'' ?>>
                        <?= htmlspecialchars($bat['abreviatura'] . ' - ' . $bat['nome']) ?>
                      </option>
                    <?php endwhile; ?>
                  </select>
                </div>

                <?php
                // ==== Destinos Permitidos ====
                $batalhoesPermitidos = [];
                if ($nivel_usuario == 1) {
                    $sql = "SELECT id FROM organizacoes_militares";
                    $res = $conexao->query($sql);
                    while ($row = $res->fetch_assoc()) {
                        $batalhoesPermitidos[] = $row['id'];
                    }
                } else {
                    $batalhoesPermitidos[] = $id_om_usuario;
                    $sqlSubs = "SELECT id_om_menor FROM organizacoes_militares_sub WHERE id_om_maior = ?";
                    $stmtSubs = $conexao->prepare($sqlSubs);
                    $stmtSubs->bind_param("i", $id_om_usuario);
                    $stmtSubs->execute();
                    $resSubs = $stmtSubs->get_result();
                    while ($r = $resSubs->fetch_assoc()) {
                        $batalhoesPermitidos[] = $r['id_om_menor'];
                    }
                    $stmtSubs->close();
                }

                $destinos = [];
                if (!empty($batalhoesPermitidos)) {
                    $placeholders = implode(',', array_fill(0, count($batalhoesPermitidos), '?'));
                    $sqlDestinos = "
                        SELECT cd.destino, om.nome AS nome_batalhao
                        FROM config_destinos cd
                        JOIN organizacoes_militares om ON cd.batalhao = om.id
                        WHERE cd.batalhao IN ($placeholders)
                        ORDER BY cd.destino ASC
                    ";
                    $stmtDestinos = $conexao->prepare($sqlDestinos);
                    $tipos = str_repeat('i', count($batalhoesPermitidos));
                    $stmtDestinos->bind_param($tipos, ...$batalhoesPermitidos);
                    $stmtDestinos->execute();
                    $resDestinos = $stmtDestinos->get_result();
                    while ($row = $resDestinos->fetch_assoc()) {
                        $destinos[] = $row;
                    }
                    $stmtDestinos->close();
                }
                ?>

                <div class="col-md-6">
                  <label for="local_os" class="form-label">Local da OS</label>
                  <select class="form-select rounded-pill shadow-sm" id="local_os" name="local_os" required>
                    <option value="" disabled selected>Selecione o local da manutenção</option>
                    <?php foreach ($destinos as $dest): ?>
                      <option value="<?= htmlspecialchars($dest['destino']) ?>">
                        <?= htmlspecialchars($dest['destino'] . ' - ' . $dest['nome_batalhao']) ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </div>
              </div>
            </div>
          </div>

          <!-- ======================== BLOCO 2: VIATURA/EQUIPAMENTO ======================== -->
          <div class="card mb-3 shadow-sm">
            <div class="card-header fw-bold bg-body-secondary">
              <i class="bi bi-truck-front me-2"></i>Dados da Viatura/Equipamento
            </div>
            <div class="card-body">
              <?php
              // ==== Viaturas ====
              $viaturas = [];
              if (!empty($batalhoesPermitidos)) {
                  $placeholders = implode(',', array_fill(0, count($batalhoesPermitidos), '?'));
                  $sqlViaturas = "
                      SELECT f.id, f.prefixo_sga, f.prefixo_velho,
                             cm.marca AS nome_marca, md.nome_modelo AS nome_modelo,
                             f.ano, om.nome AS nome_om
                      FROM frota f
                      LEFT JOIN config_marcas cm ON f.marca = cm.id
                      LEFT JOIN config_modelos md ON f.modelo = md.id
                      LEFT JOIN organizacoes_militares om ON f.batalhao = om.id
                      WHERE f.batalhao IN ($placeholders)
                      ORDER BY f.prefixo_sga ASC
                  ";
                  $stmtViaturas = $conexao->prepare($sqlViaturas);
                  $tipos = str_repeat('i', count($batalhoesPermitidos));
                  $stmtViaturas->bind_param($tipos, ...$batalhoesPermitidos);
                  $stmtViaturas->execute();
                  $resViaturas = $stmtViaturas->get_result();
                  while ($row = $resViaturas->fetch_assoc()) {
                      $viaturas[] = $row;
                  }
                  $stmtViaturas->close();
              }
              ?>

              <div class="row g-3">
                <div class="col-md-8">
                  <label for="id_frota" class="form-label">Viatura/Equipamento</label>
                  <select class="form-select rounded-pill shadow-sm" id="id_frota" name="id_frota" required>
                    <option value="" disabled selected>Selecione a viatura/equipamento</option>
                    <?php foreach ($viaturas as $vtr): ?>
                      <option value="<?= $vtr['id'] ?>">
                        <?= htmlspecialchars($vtr['prefixo_sga'] . ' - ' . $vtr['prefixo_velho'] . ' - ' . $vtr['nome_marca'] . ' - ' . $vtr['nome_modelo'] . ' - ' . $vtr['ano'] . ' - ' . $vtr['nome_om']) ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="col-md-4">
                  <label for="odometro_horimetro" class="form-label">Odômetro/Horímetro</label>
                  <input type="text" class="form-control" id="odometro_horimetro" name="odometro_horimetro" required>
                </div>
              </div>
            </div>
          </div>

          <!-- ======================== BLOCO 3: DETALHES DA OS ======================== -->
          <div class="card mb-3 shadow-sm">
            <div class="card-header fw-bold bg-body-secondary">
              <i class="bi bi-tools me-2"></i>Detalhes do Serviço
            </div>
            <div class="card-body">
              <div class="row g-3">
                <div class="col-md-6">
                  <label for="solicitante" class="form-label">Solicitante</label>
                  <input type="text" class="form-control" id="solicitante" name="solicitante" required>
                </div>
                <div class="col-md-6">
                  <label for="secao_rspns" class="form-label">Oficina Responsável</label>
                  <select class="form-select rounded-pill shadow-sm" id="secao_rspns" name="secao_rspns" required>
                    <option value="" disabled selected>Selecione</option>
                    <option value="Mecânica Leve">Mecânica Leve</option>
                    <option value="Mecânica Pesada">Mecânica Pesada</option>
                    <option value="Borracharia">Borracharia</option>
                    <option value="Elétrica">Elétrica</option>
                    <option value="Solda">Solda</option>
                    <option value="Lubrificação">Lubrificação</option>
                    <option value="Pintura">Pintura</option>
                    <option value="Outra oficina">Outra oficina</option>
                  </select>
                </div>
                <div class="col-12">
                  <label for="problema" class="form-label">Problema Apresentado</label>
                  <input type="text" class="form-control" id="problema" name="problema" required>
                </div>
                <div class="col-md-4">
                  <label for="causa_indisponibilidade" class="form-label">Causa Indisponibilidade?</label>
                  <select class="form-select rounded-pill shadow-sm" id="causa_indisponibilidade" name="causa_indisponibilidade" required>
                    <option value="" disabled selected>Selecione</option>
                    <option value="Sim">Sim</option>
                    <option value="Não">Não</option>
                  </select>
                </div>
                <div class="col-md-4">
                  <label for="tipo_mnt" class="form-label">Tipo de Manutenção</label>
                  <select class="form-select rounded-pill shadow-sm" id="tipo_mnt" name="tipo_mnt" required>
      <option value="" disabled selected>Selecione o tipo mnt</option>
      <option value="Manutenção Preventiva">Manutenção Preventiva</option>
      <option value="Manutenção Preditiva">Manutenção Preditiva</option>
      <option value="Manutenção Corretiva">Manutenção Corretiva</option>
                  </select>
                </div>
                <div class="col-md-4">
                  <label for="status" class="form-label">Status da OS</label>
                  <select class="form-select rounded-pill shadow-sm" id="status" name="status" required>
                    <option value="Em andamento">Em andamento</option>
                    <option value="Aguardando Peças">Aguardando Peças</option>
                    <option value="Aguardando Suprimento">Aguardando Suprimento</option>
                    <option value="Aguardando Descarga">Aguardando Descarga</option>
                    <option value="Eqp/Vtr descarregado">Eqp/Vtr descarregado</option>
                  </select>
                </div>
              </div>
            </div>
          </div>

          <!-- ======================== BOTÃO FINAL ======================== -->
          <div class="text-end">
            <button type="submit" class="btn btn-success btn-lg rounded-pill">
              <i class="bi bi-check-circle me-2"></i> Abrir Ordem de Serviço
            </button>
            <input type="hidden" id="edit-id" name="id">
          </div>
        </form>
      </div>
    </div>
  </div>
</div>


<!-- Modal de Edição -->
<div class="modal fade" id="modalEditarOS" tabindex="-1" aria-labelledby="modalEditarOSLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header bg-warning">
        <h5 class="modal-title">Editar Ordem de Serviço</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
      </div>
      <div class="modal-body p-0">
        <form class="container my-3 border p-4 bg-white rounded shadow-sm" id="form-editar-os">
          <h4 class="text-center fw-bold" id="nomeBatalhaoOS"></h4>

          <div class="row g-2 mb-3">
            <div class="col-md-2">
              <label class="form-label">OS nº</label>
              <input type="text" class="form-control" name="os_numero" readonly>
            </div>
            <div class="col-md-3">
              <label class="form-label">Data de Abertura</label>
              <input type="date" class="form-control" name="data_abertura" readonly>
            </div>
            <div class="col-md-4">
              <label class="form-label">Solicitante</label>
              <input type="text" class="form-control" name="solicitante" readonly>
            </div>
            <div class="col-md-3">
              <label class="form-label">Status da OS</label>
              <select class="form-select" name="situacao_os">
                <option value="Em andamento">Em andamento</option>
                <option value="Concluída">Concluída</option>
                <option value="Aguardando Peças">Aguardando Peças</option>
                <option value="Aguardando Suprimento">Aguardando Suprimento</option>
                <option value="Aguardando Descarga">Aguardando Descarga</option>
                <option value="Eqp/Vtr descarregado">Eqp/Vtr descarregado</option>
              </select>
            </div>
          </div>

          <div class="row g-2 mb-3">
            <div class="col-md-3">
              <label class="form-label">Prefixo EQP/VTR</label>
              <input type="text" class="form-control" name="prefixo" readonly>
            </div>
            <div class="col-md-2">
              <label class="form-label">ODO/HOR</label>
              <input type="number" class="form-control" name="odometro">
            </div>
            <div class="col-md-3">
              <label class="form-label">Tipo MNT</label>
              <select class="form-select" name="tipo_mnt">
      <option value="" disabled selected>Selecione o tipo mnt</option>
      <option value="Manutenção Preventiva">Manutenção Preventiva</option>
      <option value="Manutenção Preditiva">Manutenção Preditiva</option>
      <option value="Manutenção Corretiva">Manutenção Corretiva</option>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label">Falhas Apresentadas/Serviço Solicitado</label>
              <textarea class="form-control" rows="1" name="falhas_solicitadas"></textarea>
            </div>
              
          </div>
            

          <div class="row g-2 mb-3">
            <div class="col-md-3">
              <label class="form-label">Local da manutenção</label>
              <select class="form-select" name="local_mnt">
                     <option value="" disabled selected>Selecione o local da manutenção</option>
      <option value="Sede">Sede</option>
      <option value="Destacamento 1">Destacamento 1</option>
      <option value="Destacamento 2">Destacamento 2</option>
              </select>
            </div>
              
              <div class="col-md-3">
              <label class="form-label">Seção Responsável</label>
    <select class="form-select rounded-pill shadow-sm" id="secao_rspns" name="secao_rspns" required>
      <option value="" disabled selected>Selecione a oficina responsável</option>
      <option value="Mecânica Leve">Mecânica Leve</option>
      <option value="Mecânica Pesada">Mecânica Pesada</option>
      <option value="Borracharia">Borracharia</option>
      <option value="Elétrica">Elétrica</option>
      <option value="Solda">Solda</option>
      <option value="Lubrificação">Lubrificação</option>
      <option value="Pintura">Pintura</option>
      <option value="Outra oficina">Outra oficina</option>
    </select>
            </div>
              
              
          </div>
            
            <div class="row g-2 mb-3">
  <div class="col-md-12">
    <label class="form-label fw-bold">
      <i class="fas fa-info-circle me-1"></i> Observações
    </label>
    <textarea class="form-control shadow-sm" rows="3" name="observacoes"
      placeholder="Ex.: Pendências, peças aguardando, detalhe de diagnóstico, etc."></textarea>
  </div>
</div>
            

          <hr class="my-3">

          <!-- 1. Falhas -->
          <h5 class="fw-bold">1. Falhas Identificadas Durante MNT</h5>
          <div id="falhasContainer" class="mb-2"></div>
          <button type="button" class="btn btn-info btn-sm mb-3" onclick="adicionarFalha()">Adicionar falha</button>

          <!-- 2. Pessoal -->
          <h5 class="fw-bold">2. Pessoal Utilizado na Manutenção</h5>
          <div id="pessoalContainer" class="mb-2"></div>
          <button type="button" class="btn btn-info btn-sm mb-3" onclick="adicionarPessoal()">Adicionar pessoal</button>

          <!-- 3. Serviços -->
          <h5 class="fw-bold">3. Serviços Realizados na Manutenção</h5>
          <div id="servicosContainer" class="mb-2"></div>
          <button type="button" class="btn btn-info btn-sm mb-3" onclick="adicionarServico()">Adicionar serviço</button>

          <!-- 4. Materiais -->
          <h5 class="fw-bold">4. Materiais/Peças Utilizados</h5>
          <div id="materiaisContainer" class="mb-2"></div>
          <button type="button" class="btn btn-info btn-sm mb-3" onclick="adicionarMaterial()">Adicionar material/peça</button>
            <hr>
          <!-- Valores gastos -->
   <div class="row g-2">
  <div class="col">
    <label class="form-label fw-bold">Valor gasto na ND30 (Materiais)</label>
    <input type="number" id="valorND30" name="valorND30" class="form-control" readonly>
  </div>
  <div class="col">
    <label class="form-label fw-bold">Valor gasto na ND39 (Serviços)</label>
    <input type="number" id="valorND39" name="valorND39" class="form-control" readonly>
  </div>
  <div class="col">
    <label class="form-label fw-bold">Valor Gasto Total</label>
    <input type="number" id="valorTotalGasto" name="valorTotalGasto" class="form-control" readonly>
  </div>
</div>

          <hr>

          <!-- Preventiva -->
          <div class="form-check form-check-inline">
            <input class="form-check-input" type="checkbox" id="manutencaoPreventiva" name="manutencao_preventiva">
            <label class="form-check-label" for="manutencaoPreventiva">É um serviço de manutenção preventiva?</label>
          </div>

          <div id="dadosPreventiva" class="border p-3 mt-2 bg-light" style="display: none;">
            <div class="mb-2">
              <label class="form-label">Qual o tempo máximo para próxima manutenção?</label>
              <select class="form-select" name="proxima_mnt_tempo">
   <option value="0.00">Não é manutenção preventiva</option>
   <option value="12.00">12 meses</option>
  <option value="11.00">11 meses</option>
  <option value="10.00">10 meses</option>
  <option value="9.00">9 meses</option>
  <option value="8.00">8 meses</option>
  <option value="7.00">7 meses</option>
  <option value="6.00">6 meses</option>
  <option value="5.00">5 meses</option>
  <option value="4.00">4 meses</option>
  <option value="3.00">3 meses</option>
  <option value="2.00">2 meses</option>
  <option value="1.00">1 mês</option>
              </select>
            </div>
            <div class="mb-2">
              <label class="form-label">Quilometragem ou horas para próxima manutenção preventiva</label>
              <select class="form-select" name="proxima_mnt_valor">
   <option value="0.00">Não é manutenção preventiva</option>
                <option value="10000.00">10000km</option>
                <option value="5000.00">5000km</option>
                <option value="2500.00">2500km</option>
                <option value="500.00">500hrs</option>
                <option value="250.00">250hrs</option>
                <option value="200.00">200hrs</option>
                <option value="150.00">150hrs</option>
                <option value="100.00">100hrs</option>
                <option value="50.00">50hrs</option>
              </select>
            </div>
            <div>
              <label class="form-label">Quais foram as trocas realizadas e quantidade: (Ex: OM 10l - OH 15L)</label>
              <textarea class="form-control" rows="2" name="trocas_realizadas"></textarea>
            </div>
          </div>
               <div class="row g-2">
                        <div class="col-md-3">
              <label class="form-label">Data de Encerramento da OS</label>
              <input type="date" class="form-control" name="data_encerramento">
                       </div>
               </div>

          <!-- Botões -->
          <div class="text-end mt-4">
            <button type="button" class="btn btn-danger" data-bs-dismiss="modal">Cancelar</button>
            <button type="submit" class="btn btn-success">Salvar alterações</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>



<!-- Modal de Ver OS -->
<div class="modal fade" id="modalVerOS" tabindex="-1" aria-labelledby="modalVerOSLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content border-0 shadow">
      <div class="modal-header bg-primary text-white d-flex justify-content-between align-items-center">
    <h5 class="modal-title">Ver Ordem de Serviço</h5>
    <div>
        <!-- Botão de baixar PDF -->
        <button type="button" class="btn btn-light btn-sm me-2" id="btnBaixarPDF">
            <i class="fas fa-file-pdf me-1"></i> Baixar PDF
        </button>
        <!-- Botão de fechar modal -->
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Fechar"></button>
    </div>
</div>


      <div class="modal-body p-4 bg-light">

        <!-- Foto da viatura -->
        <div class="text-center mb-4">
          <img id="fotoViatura" src="" alt="Foto da Viatura" class="img-thumbnail shadow-sm" style="max-width: 100%; max-height: 200px; object-fit: cover;">
        </div>

        <!-- Dados da Viatura -->
        <div class="row g-3 bg-white p-3 rounded shadow-sm mb-4">
          <h5 class="fw-bold text-secondary">Dados da Viatura</h5>
          <div class="col-md-3">
            <label class="form-label">Prefixo</label>
            <p class="form-control-plaintext" id="viatura_prefixo"></p>
          </div>
          <div class="col-md-3">
            <label class="form-label">Chassi</label>
            <p class="form-control-plaintext" id="viatura_chassi"></p>
          </div>
          <div class="col-md-3">
            <label class="form-label">Marca</label>
            <p class="form-control-plaintext" id="viatura_marca"></p>
          </div>
          <div class="col-md-3">
            <label class="form-label">Modelo</label>
            <p class="form-control-plaintext" id="viatura_modelo"></p>
          </div>
        </div>

        <!-- Título -->
        <h4 class="text-center fw-bold text-dark mb-4" id="tituloBatalhao">
          
        </h4>

        <!-- Dados da OS -->
        <div class="row g-3 bg-white p-3 rounded shadow-sm mb-4">
          <h5 class="fw-bold text-secondary">Dados da Ordem de Serviço</h5>
          <div class="col-md-2">
            <label class="form-label">OS nº</label>
            <p class="form-control-plaintext" id="os_numero"></p>
          </div>
          <div class="col-md-3">
            <label class="form-label">Data de Abertura</label>
            <p class="form-control-plaintext" id="data_abertura"></p>
          </div>
          <div class="col-md-4">
            <label class="form-label">Solicitante</label>
            <p class="form-control-plaintext" id="solicitante2"></p>
          </div>
          <div class="col-md-3">
            <label class="form-label">Status da OS</label>
            <p class="form-control-plaintext" id="situacao_os"></p>
          </div>
          <div class="col-md-2">
            <label class="form-label">ODO/HOR</label>
            <p class="form-control-plaintext" id="odometro2"></p>
          </div>
          <div class="col-md-3">
            <label class="form-label">Tipo MNT</label>
            <p class="form-control-plaintext" id="tipo_mnt2"></p>
          </div>
          <div class="col-md-4">
            <label class="form-label">Falhas Apresentadas / Serviço Solicitado</label>
            <p class="form-control-plaintext" id="falhas_solicitadas"></p>
          </div>
          <div class="col-md-3">
            <label class="form-label">Local da manutenção</label>
            <p class="form-control-plaintext" id="local_mnt"></p>
          </div>
        </div>

        <!-- Seções -->
        <div class="bg-white p-3 rounded shadow-sm mb-4">
          <h5 class="fw-bold text-secondary">Materiais/Peças Utilizados</h5>
          <div id="materiaisContainer2"></div>
        </div>

        <div class="bg-white p-3 rounded shadow-sm mb-4">
          <h5 class="fw-bold text-secondary">Falhas Identificadas Durante MNT</h5>
          <div id="falhasContainer2"></div>
        </div>

        <div class="bg-white p-3 rounded shadow-sm mb-4">
          <h5 class="fw-bold text-secondary">Pessoal Utilizado na Manutenção</h5>
          <div id="pessoalContainer2"></div>
        </div>

        <div class="bg-white p-3 rounded shadow-sm mb-4">
          <h5 class="fw-bold text-secondary">Serviços Realizados na Manutenção</h5>
          <div id="servicosContainer2"></div>
        </div>

        <!-- Valores -->
        <div class="bg-white p-3 rounded shadow-sm mb-4">
          <h5 class="fw-bold text-secondary">Valores</h5>
          <div class="row g-3">
            <div class="col-md-4">
              <label class="form-label">Valor ND30 (Materiais)</label>
              <p class="form-control-plaintext" id="valorND302"></p>
            </div>
            <div class="col-md-4">
              <label class="form-label">Valor ND39 (Serviços)</label>
              <p class="form-control-plaintext" id="valorND392"></p>
            </div>
            <div class="col-md-4">
              <label class="form-label">Valor Total</label>
              <p class="form-control-plaintext" id="valorTotalGasto2"></p>
            </div>
          </div>
        </div>

        <!-- Pedidos -->
        <div class="bg-white p-3 rounded shadow-sm mb-4">
          <h5 class="fw-bold text-secondary">Pedidos Relacionados</h5>
          <div id="pedidosContainer">
            <p class="text-muted">Nenhum pedido cadastrado até o momento.</p>
          </div>
        </div>

        <!-- Logs -->
        <div class="bg-white p-3 rounded shadow-sm mb-3">
          <h5 class="fw-bold text-secondary">Histórico de Alterações</h5>
          <div id="logsContainer"></div>
        </div>

        <!-- Botão -->
        <div class="text-end">
          <button type="button" class="btn btn-danger" data-bs-dismiss="modal">Fechar</button>
        </div>

      </div>
    </div>
  </div>
</div>
    
    
    <!-- Modal de Importação -->
<div class="modal fade" id="modalImportarOS" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form id="formImportarOS" method="POST" enctype="multipart/form-data">
        <div class="modal-header">
          <h5 class="modal-title">Importar Planilha de Ordens de Serviço</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
<?php
          // ============================
          // MESMA LÓGICA DE BATALHÕES QUE O USUÁRIO PODE VER
          // ============================
          $id_om_usuario = $_SESSION['usuario']['batalhao'] ?? 0;

          $sqlNivel = "SELECT nivel FROM organizacoes_militares WHERE id = $id_om_usuario";
          $resNivel = $conexao->query($sqlNivel);
          $nivelUsuario = $resNivel->fetch_assoc()['nivel'] ?? 0;

          if ($nivelUsuario == 1) {
              $sqlBatalhoes = "SELECT id, nome, abreviatura FROM organizacoes_militares ORDER BY nome";
          } else {
              $sqlBatalhoes = "
                  SELECT om.id, om.nome, om.abreviatura
                  FROM organizacoes_militares om
                  WHERE om.id = $id_om_usuario
                  OR om.id IN (
                      SELECT id_om_menor FROM organizacoes_militares_sub WHERE id_om_maior = $id_om_usuario
                  )
                  ORDER BY om.nome
              ";
          }
          $resBatalhoes = $conexao->query($sqlBatalhoes);
          ?>

          <!-- SELECIONAR BATALHÃO -->
          <label class="form-label">Selecione o Batalhão das Ordens de Serviço</label>
          <select name="batalhao" id="batalhao_importacao" class="form-select mb-3" required>
            <option value="">Selecione...</option>
            <?php while ($bat = $resBatalhoes->fetch_assoc()): ?>
              <option value="<?= $bat['id'] ?>">
                <?= htmlspecialchars($bat['abreviatura'] . ' - ' . $bat['nome']) ?>
              </option>
            <?php endwhile; ?>
          </select>

          <label class="form-label">Selecione a planilha (.xlsx)</label>
          <input type="file" name="arquivo" id="arquivo" accept=".xlsx" class="form-control mb-3" required>

          <a href="includes/os/planilha_modelo_os.xlsx" class="btn btn-link p-0">
            📥 Baixar modelo de planilha
          </a>
        </div>
        <div class="modal-footer">
          <button type="submit" class="btn btn-primary">Importar</button>
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
        </div>
      </form>
    </div>
  </div>
</div>





<!-- Script da página de Cadastro de Vtr/Eqp -->
<script>
    window.funcaoInicializacao = 'inicializarOrdemServico';
</script>