<?php
header('Content-Type: text/html; charset=utf-8');
if (session_status() === PHP_SESSION_NONE) session_start();
require_once '../api/seguranca.php';

$permissoes = verificarPermissao([17, 25]);

$pode_cadastrar = $permissoes['cadastrar'];
$pode_editar    = $permissoes['editar'];
$pode_deletar   = $permissoes['deletar'];
$pode_importar  = $permissoes['importar'];
$pode_exportar  = $permissoes['exportar'];
$pode_autorizar  = $permissoes['autorizar'];

if (!isset($_SESSION['usuario_id'])) {
  http_response_code(401);
  echo "<div class='alert alert-danger'>Sessão expirada. Faça login novamente.</div>";
  exit;
}

// BLOQUEAR ACESSO DIRETO VIA URL
if (
    !isset($_SERVER['HTTP_X_REQUESTED_WITH']) ||
    strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) !== 'xmlhttprequest'
) {
    http_response_code(403);
    echo "<div class='alert alert-danger'>Acesso direto não permitido.</div>";
    exit;
}

include_once('../../conexao/config.php');

// ============================
// DADOS DO USUÁRIO LOGADO
// ============================
$id_om_usuario = (int)($_SESSION['usuario']['batalhao'] ?? 0);

$sqlNivel = "SELECT nivel FROM organizacoes_militares WHERE id = ?";
$stmtNivel = $conexao->prepare($sqlNivel);
$stmtNivel->bind_param("i", $id_om_usuario);
$stmtNivel->execute();
$resNivel = $stmtNivel->get_result();
$nivel_usuario = (int)($resNivel->fetch_assoc()['nivel'] ?? 0);
$stmtNivel->close();

// ============================
// MONTA LISTA DE BATALHÕES VISÍVEIS
// ============================
$batalhoesPermitidos = [];

if ($nivel_usuario == 1) {
    $sql = "SELECT id FROM organizacoes_militares";
    $res = $conexao->query($sql);
    while ($r = $res->fetch_assoc()) $batalhoesPermitidos[] = (int)$r['id'];
} elseif ($nivel_usuario == 2) {
    $batalhoesPermitidos[] = $id_om_usuario;
    $sql = "SELECT id_om_menor FROM organizacoes_militares_sub WHERE id_om_maior = ?";
    $stmt = $conexao->prepare($sql);
    $stmt->bind_param("i", $id_om_usuario);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()) $batalhoesPermitidos[] = (int)$r['id_om_menor'];
    $stmt->close();
} else {
    $batalhoesPermitidos[] = $id_om_usuario;
}

// ============================
// FILTROS DE PESQUISA
// ============================
$id          = $_GET['id'] ?? '';
$solicitante = $_GET['solicitante'] ?? '';
$secao_rspns = $_GET['secao_rspns'] ?? '';
$data_ini    = $_GET['data_ini'] ?? '';
$data_fim    = $_GET['data_fim'] ?? '';
$batalhaoFiltro = $_GET['batalhao'] ?? '';
$prefixo_vtr = $_GET['prefixo'] ?? '';
$chassi_vtr  = $_GET['chassi_vtr'] ?? '';
$placa_vtr   = $_GET['placa_vtr'] ?? '';

$filtros = [];
$params  = [];
$tipos   = '';

$limite = (isset($_GET['limite']) && is_numeric($_GET['limite'])) ? (int)$_GET['limite'] : 10;
$pagina = (isset($_GET['pagina']) && is_numeric($_GET['pagina']) && $_GET['pagina'] > 0) ? (int)$_GET['pagina'] : 1;
$offset = ($pagina - 1) * $limite;

// ============================
// Filtros padrões (tabela p)
// ============================
if (!empty($id)) {
    $filtros[] = "p.id = ?";
    $params[] = (int)$id;
    $tipos .= 'i';
}
if (!empty($solicitante)) {
    $filtros[] = "p.solicitante LIKE ?";
    $params[] = "%$solicitante%";
    $tipos .= 's';
}
if (!empty($secao_rspns)) {
    $filtros[] = "p.secao_rspns LIKE ?";
    $params[] = "%$secao_rspns%";
    $tipos .= 's';
}
// ============================
// FILTRO DE DATA (padrão: últimos 90 dias)
// ============================

// Se o usuário NÃO informou nenhuma data, aplica últimos 90 dias
if (empty($data_ini) && empty($data_fim)) {
    $data_ini = date('Y-m-d', strtotime('-90 days'));
    $data_fim = date('Y-m-d');
}

// Data inicial
if (!empty($data_ini)) {
    $filtros[] = "p.data_pedido >= ?";
    $params[]  = $data_ini;
    $tipos    .= 's';
}

// Data final
if (!empty($data_fim)) {
    $filtros[] = "p.data_pedido <= ?";
    $params[]  = $data_fim;
    $tipos    .= 's';
}



// ============================
// FILTRO DE BATALHÃO
// ============================
if (!empty($batalhaoFiltro) && in_array((int)$batalhaoFiltro, $batalhoesPermitidos, true)) {
    $filtros[] = "p.batalhao = ?";
    $params[] = (int)$batalhaoFiltro;
    $tipos .= 'i';
} elseif (!empty($batalhoesPermitidos)) {
    $placeholders = implode(',', array_fill(0, count($batalhoesPermitidos), '?'));
    $filtros[] = "p.batalhao IN ($placeholders)";
    $params = array_merge($params, $batalhoesPermitidos);
    $tipos .= str_repeat('i', count($batalhoesPermitidos));
}

// ============================
// FILTRO VIATURA (pesado) → vamos fazer por EXISTS (leve)
// ============================
$temFiltroVtr = (!empty($prefixo_vtr) || !empty($chassi_vtr) || !empty($placa_vtr));

if ($temFiltroVtr) {
    // Monta EXISTS com os mesmos campos que você filtrava na frota
    $sqlExists = "
      EXISTS (
        SELECT 1
        FROM frota f
        LEFT JOIN os_principal os ON os.id = p.id_os
        WHERE f.id = COALESCE(p.id_vtr, os.id_frota)
    ";

    if (!empty($prefixo_vtr)) {
        $sqlExists .= " AND (f.prefixo_sga LIKE ? OR f.prefixo_velho LIKE ? OR f.nome_sioc LIKE ?) ";
        $params[] = "%$prefixo_vtr%";
        $params[] = "%$prefixo_vtr%";
        $params[] = "%$prefixo_vtr%";
        $tipos .= 'sss';
    }

    if (!empty($chassi_vtr)) {
        $sqlExists .= " AND f.chassi LIKE ? ";
        $params[] = "%$chassi_vtr%";
        $tipos .= 's';
    }

    if (!empty($placa_vtr)) {
        $sqlExists .= " AND f.placa LIKE ? ";
        $params[] = "%$placa_vtr%";
        $tipos .= 's';
    }

    $sqlExists .= " )";
    $filtros[] = $sqlExists;
}

$condicoes = !empty($filtros) ? 'WHERE ' . implode(' AND ', $filtros) : '';

// ============================
// TOTAL DE REGISTROS (sem JOIN pesado)
// ============================
$sqlTotal = "
    SELECT COUNT(*) as total
    FROM fin_pedidos_forn p
    $condicoes
";
$stmtTotal = $conexao->prepare($sqlTotal);
if (!empty($params)) $stmtTotal->bind_param($tipos, ...$params);
$stmtTotal->execute();
$resultTotal = $stmtTotal->get_result();
$totalRegistros = (int)($resultTotal->fetch_assoc()['total'] ?? 0);
$totalPaginas = (int)ceil($totalRegistros / $limite);
$stmtTotal->close();

// ============================
// CONSULTA PRINCIPAL (sem JOIN nos itens)
// ============================
$sql = "
  SELECT 
    p.id,
    p.solicitante,
    p.secao_rspns,
    p.data_pedido,
    p.autorizacao,
    p.batalhao,
    p.id_os,
    p.id_vtr,
    p.local_pedido,
    p.situacao_pedido,

    om.nome        AS nome_batalhao,
    om.abreviatura AS abreviatura_batalhao,

    -- dados de viatura: somente para exibir, sem amarrar na tabela de itens
    f.prefixo_sga  AS prefixo_sga,
    f.chassi       AS chassi

  FROM fin_pedidos_forn p
  LEFT JOIN organizacoes_militares om ON om.id = p.batalhao
  LEFT JOIN os_principal os ON os.id = p.id_os
  LEFT JOIN frota f ON f.id = COALESCE(p.id_vtr, os.id_frota)
  $condicoes
  ORDER BY p.id DESC
  LIMIT ? OFFSET ?
";

$paramsExec = $params;
$tiposExec  = $tipos . 'ii';
$paramsExec[] = $limite;
$paramsExec[] = $offset;

$stmt = $conexao->prepare($sql);
$stmt->bind_param($tiposExec, ...$paramsExec);
$stmt->execute();
$pedidos = $stmt->get_result();
$stmt->close();

// ============================
// RESUMO DE ITENS (COUNT e SUM) SÓ DOS PEDIDOS DA PÁGINA
// ============================
$idsPagina = [];
$pedidosArray = [];

while ($row = $pedidos->fetch_assoc()) {
    $idsPagina[] = (int)$row['id'];
    $pedidosArray[] = $row;
}

// resumo por pedido
$resumoItens = []; // [id_pedido => ['total_itens'=>x, 'valor_total'=>y]]

if (!empty($idsPagina)) {
    $in = implode(',', array_fill(0, count($idsPagina), '?'));

    $sqlResumo = "
      SELECT 
        id_principal,
        COUNT(*) AS total_itens,
        COALESCE(SUM(valor_total),0) AS valor_total
      FROM fin_pedidos_forn_itens
      WHERE id_principal IN ($in)
      GROUP BY id_principal
    ";

    $stmtResumo = $conexao->prepare($sqlResumo);
    $tiposResumo = str_repeat('i', count($idsPagina));
    $stmtResumo->bind_param($tiposResumo, ...$idsPagina);
    $stmtResumo->execute();
    $r = $stmtResumo->get_result();

    while ($x = $r->fetch_assoc()) {
        $resumoItens[(int)$x['id_principal']] = [
            'total_itens' => (int)$x['total_itens'],
            'valor_total' => (float)$x['valor_total'],
        ];
    }
    $stmtResumo->close();
}

// ============================
// FUNÇÃO PAGINAÇÃO (Estilo Frota) - mantida
// ============================
function renderPaginacaoPedidos($pagina, $totalPaginas, $limite, $queryString, $arquivo = 'includes/fin_pedidos/listagem.php') {
    $maxLinks = 10;

    $makeUrl = function($p) use ($arquivo, $queryString, $limite) {
        return "{$arquivo}?{$queryString}&pagina={$p}&limite={$limite}";
    };

    $html = '<div class="pagination-wrapper">';
    $html .= '<nav><ul class="pagination pagination-sm">';

    if ($pagina > 1) {
        $html .= "<li class='page-item'>
                    <a class='page-link paginacao-pedidos' href='#' data-page='" . $makeUrl(1) . "'>&laquo; Primeira</a>
                  </li>";
    }

    if ($pagina > 1) {
        $prev = $pagina - 1;
        $html .= "<li class='page-item'>
                    <a class='page-link paginacao-pedidos' href='#' data-page='" . $makeUrl($prev) . "'>&lsaquo;</a>
                  </li>";
    }

    $inicio = max(1, $pagina - floor($maxLinks / 2));
    $fim    = min($totalPaginas, $inicio + $maxLinks - 1);

    if (($fim - $inicio) < ($maxLinks - 1)) {
        $inicio = max(1, $fim - $maxLinks + 1);
    }

    if ($inicio > 1) {
        $html .= "<li class='page-item disabled'><span class='page-link'>...</span></li>";
    }

    for ($i = $inicio; $i <= $fim; $i++) {
        $ativo = ($i == $pagina) ? 'active' : '';
        $html .= "<li class='page-item {$ativo}'>
                    <a class='page-link paginacao-pedidos' href='#' data-page='" . $makeUrl($i) . "'>$i</a>
                  </li>";
    }

    if ($fim < $totalPaginas) {
        $html .= "<li class='page-item disabled'><span class='page-link'>...</span></li>";
    }

    if ($pagina < $totalPaginas) {
        $next = $pagina + 1;
        $html .= "<li class='page-item'>
                    <a class='page-link paginacao-pedidos' href='#' data-page='" . $makeUrl($next) . "'>&rsaquo;</a>
                  </li>";
    }

    if ($pagina < $totalPaginas) {
        $html .= "<li class='page-item'>
                    <a class='page-link paginacao-pedidos' href='#' data-page='" . $makeUrl($totalPaginas) . "'>Última &raquo;</a>
                  </li>";
    }

    $html .= '</ul></nav></div>';
    return $html;
}

// ============================
// Mantém query string dos filtros
// ============================
$paramsGET = $_GET;
unset($paramsGET['pagina']);
$queryString = http_build_query($paramsGET);
?>


<style>
     .pagination-wrapper {
    display: flex;
    justify-content: center;
    margin: 1rem 0;
}

.pagination {
    display: flex;
    list-style: none;
    padding-left: 0;
    gap: 0.5rem;
}

.pagination .page-item .page-link {
    display: flex;
    align-items: center;
    justify-content: center;
    min-width: 38px;
    height: 38px;
    padding: 0.5rem 0.75rem;
    font-size: 0.9rem;
    color: #6c757d;
    background-color: #fff;
    border: 1px solid #dee2e6;
    border-radius: 0.375rem;
    transition: all 0.3s ease-in-out;
}

.pagination .page-item .page-link:hover {
    background-color: #f8f9fa;
    color: #495057;
    text-decoration: none;
    transform: translateY(-2px);
    box-shadow: 0 4px 6px rgba(0,0,0,0.1);
}

.pagination .page-item.active .page-link {
    background-color: #0d6efd;
    color: #fff;
    border-color: #0d6efd;
    font-weight: bold;
    box-shadow: 0 4px 8px rgba(13, 110, 253, 0.4);
}
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
</style>

<div class="container">
  <div class="page-inner">
    <div class="d-flex justify-content-between align-items-center py-3">
      <div>
        <h3 class="fw-bold mb-1">Listagem dos Pedidos aos Fornecedores </h3>
        <h6 class="text-muted">Para melhorar o desempenho do sistema, são exibidos inicialmente apenas os pedidos dos últimos 90 dias.
Caso precise consultar períodos anteriores, utilize o filtro de datas.</h6>
      </div>
      <div>
		  <?php if($pode_cadastrar): ?>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalCadastrarPedido">
          <i class="fa fa-plus me-1"></i> Cadastrar Pedido
        </button>
		  <?php endif; ?>
      </div>
    </div>

    <div class="mb-3">
  <button class="btn btn-outline-primary w-100 d-flex justify-content-center align-items-center" onclick="toggleFiltrosPedidos()">
    <i class="fas fa-search me-2"></i> Filtros
  </button>
</div>

<div id="filtros-container-pedidos" style="display: none;" class="mb-3">
  <div class="card shadow-sm border-0">
    <div class="card-body">
      <form method="GET" id="filtroPedidosForm">
        <div class="row g-3">
          <div class="col-md-2">
            <label class="form-label fw-semibold">ID do Pedido</label>
            <input type="text" class="form-control" name="id" value="<?= htmlspecialchars($_GET['id'] ?? '') ?>">
          </div>
          <div class="col-md-3">
            <label class="form-label fw-semibold">Solicitante</label>
            <input type="text" class="form-control" name="solicitante" value="<?= htmlspecialchars($_GET['solicitante'] ?? '') ?>">
          </div>
          <div class="col-md-3">
            <label class="form-label fw-semibold">Seção Responsável</label>
            <input type="text" class="form-control" name="secao_rspns" value="<?= htmlspecialchars($_GET['secao_rspns'] ?? '') ?>">
          </div>
          <div class="col-md-3">
            <label class="form-label fw-semibold">Data Inicial</label>
            <input type="date" class="form-control" name="data_ini" value="<?= htmlspecialchars($_GET['data_ini'] ?? '') ?>">
          </div>
          <div class="col-md-3">
            <label class="form-label fw-semibold">Data Final</label>
            <input type="date" class="form-control" name="data_fim" value="<?= htmlspecialchars($_GET['data_fim'] ?? '') ?>">
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
            <?php
// ============================
// VIATURAS VISÍVEIS (PREFIXO / PLACA)
// ============================
$viaturas = [];

if (!empty($oms_visiveis)) {
    $ids_oms = array_keys($oms_visiveis);
    $placeholders = implode(',', array_fill(0, count($ids_oms), '?'));

    $sql_vtr = "
        SELECT id, prefixo_sga, placa
        FROM frota
        WHERE batalhao IN ($placeholders)
        ORDER BY prefixo_sga
    ";

    $stmt_vtr = $conexao->prepare($sql_vtr);
    $stmt_vtr->bind_param(str_repeat('i', count($ids_oms)), ...$ids_oms);
    $stmt_vtr->execute();
    $res_vtr = $stmt_vtr->get_result();

    while ($v = $res_vtr->fetch_assoc()) {
        $viaturas[] = $v;
    }
    $stmt_vtr->close();
}

// valores atuais do filtro
$prefixo_filtro = $_GET['prefixo'] ?? '';
$placa_filtro   = $_GET['placa'] ?? '';
?>
<div class="col-md-3">
    <label class="form-label fw-semibold">Prefixo da Viatura</label>
    <select name="prefixo" class="form-select">
        <option value="">Todos</option>
        <?php foreach ($viaturas as $v): 
            if (empty($v['prefixo_sga'])) continue;
            $sel = ($prefixo_filtro === $v['prefixo_sga']) ? 'selected' : '';
        ?>
            <option value="<?= htmlspecialchars($v['prefixo_sga']) ?>" <?= $sel ?>>
                <?= htmlspecialchars($v['prefixo_sga']) ?>
            </option>
        <?php endforeach; ?>
    </select>
</div>

            
            
          <div class="col-12 d-flex justify-content-between mt-2">
            <button type="button" id="btnLimparFiltrosPedidos" class="btn btn-black ms-2">Limpar Filtros</button>
            <button type="submit" class="btn btn-primary px-4">Aplicar</button>
          </div>
        </div>
      </form>
    </div>
  </div>
</div>


<div class="mb-3">
  <label for="limitePedidoForn" class="me-2 mb-0">Mostrar</label>
  <select id="limitePedidoForn" name="limite" class="form-select d-inline w-auto" onchange="atualizarLimitePedido()">
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


  <div class="paginacao">
  <?= renderPaginacaoPedidos($pagina, $totalPaginas, $limite, $queryString, 'includes/fin_pedidos/listagem.php'); ?>
</div>

<div class="card border-0 shadow-sm">
  <div class="card-body">
    <div class="list-group">

      <?php if (!empty($pedidosArray)): ?>
        <?php foreach ($pedidosArray as $pedido): ?>

          <?php
            // RESUMO VEM DO PHP PRINCIPAL (1 query só)
            $total_itens = $resumoItens[(int)$pedido['id']]['total_itens'] ?? 0;

            // Badge de status
            $status = strtolower($pedido['situacao_pedido'] ?? 'indefinido');
            $badgeClass = match ($status) {
              'aberto'     => 'bg-warning text-dark',
              'em análise' => 'bg-primary',
              'atendido'   => 'bg-success',
              'cancelado'  => 'bg-danger',
              default      => 'bg-secondary'
            };

            $autorizado = ($pedido['autorizacao'] ?? '') === 'sim';
          ?>

          <div class="list-group-item list-group-item-action flex-column align-items-start mb-3 p-3 border-0 shadow-sm rounded-3">

            <!-- TOPO -->
            <div class="d-flex justify-content-between align-items-center mb-2">
              <div>
                <h6 class="mb-0 fw-semibold text-primary">
                  <i class="fas fa-file-invoice-dollar me-1 text-secondary"></i>
                  Pedido #<?= $pedido['id'] ?> —
                  <?= htmlspecialchars($pedido['abreviatura_batalhao'] ?? 'OM não informada') ?>
                </h6>
                <small class="text-muted">
                  <i class="far fa-calendar-alt me-1"></i>
                  <?= !empty($pedido['data_pedido']) ? date('d/m/Y', strtotime($pedido['data_pedido'])) : '—' ?>
                </small>
              </div>

              <span class="badge <?= $badgeClass ?> px-3 py-2 rounded-pill fs-6">
                <?= ucfirst($status) ?>
              </span>
            </div>

            <!-- CONTEXTO -->
            <div class="border-start ps-3 mb-2">
              <p class="mb-1 text-secondary small">
                <i class="fas fa-car me-1"></i>
                <strong>Prefixo/VTR:</strong>
                <?= htmlspecialchars($pedido['prefixo_sga'] ?? 'Não informado') ?>
              </p>
              <p class="mb-1 text-secondary small">
                <i class="fas fa-barcode me-1"></i>
                <strong>Chassi/Vtr:</strong>
                <?= htmlspecialchars($pedido['chassi'] ?? 'Não informado') ?>
              </p>
            </div>

            <!-- DADOS -->
            <div class="d-flex flex-column small text-body-secondary mb-3">
              <div><strong>Local:</strong> <?= htmlspecialchars($pedido['local_pedido'] ?? 'Local não selecionado') ?></div>
              <div><strong>Solicitante:</strong> <?= htmlspecialchars($pedido['solicitante'] ?? '') ?></div>
              <div><strong>Seção:</strong> <?= htmlspecialchars($pedido['secao_rspns'] ?? '') ?></div>
            </div>

            <!-- RESUMO -->
            <div class="bg-light border rounded-3 text-center py-2 px-3 mb-3">
              <i class="fas fa-boxes text-primary me-1"></i>
              <strong><?= $total_itens ?></strong> <?= $total_itens == 1 ? 'item' : 'itens' ?>
            </div>

            <!-- AÇÕES -->
            <div class="d-flex align-items-center gap-2 flex-wrap justify-content-end">
<?php if ($pode_autorizar): ?>

<button
    type="button"
    class="btn btn-sm btn-autorizar <?= $autorizado ? 'btn-success' : 'btn-danger' ?>"
    data-id="<?= (int)$pedido['id'] ?>"
    data-token="<?= htmlspecialchars($_SESSION['csrf_token']) ?>"
    data-status="<?= $autorizado ? 'sim' : 'nao' ?>"
>
    <?php if ($autorizado): ?>
        <i class="fas fa-check-circle me-1"></i> Autorizado
    <?php else: ?>
        <i class="fas fa-ban me-1"></i> Não autorizado
    <?php endif; ?>
</button>

<?php endif; ?>
              <!-- IMPRIMIR -->
              <div class="dropdown">
                <button class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
                  <i class="fas fa-print me-1"></i> Imprimir
                </button>

                <ul class="dropdown-menu dropdown-menu-end shadow-sm rounded">
                  <li>
                    <a href="#"
                       class="dropdown-item text-danger btnExportarPDFpedidoFornecedor"
                       data-id="<?= $pedido['id'] ?>">
                      <i class="fas fa-file-pdf me-2"></i> Pedido
                    </a>
                  </li>
                </ul>
              </div>

             <!-- VER ITENS -->
<button class="btn btn-sm btn-outline-secondary btn-ver-itens-pedido"
        data-id="<?= $pedido['id'] ?>"
        data-bs-toggle="collapse"
        data-bs-target="#itensPedido<?= $pedido['id'] ?>">
  <i class="fas fa-eye me-1"></i> Ver itens
</button>

 <?php if($pode_editar): ?>
              <!-- EDITAR -->
              <button class="btn btn-sm btn-outline-warning"
                      onclick="editarPedido(<?= $pedido['id'] ?>)"
                      data-bs-toggle="modal"
                      data-bs-target="#modalEditarPedido">
                <i class="fas fa-edit me-1"></i> Editar
              </button>
<?php endif; ?>
 <?php if($pode_deletar): ?>
              <!-- REMOVER -->
              <button class="btn btn-sm btn-outline-danger"
                      data-id="<?= $pedido['id'] ?>"
					  data-token="<?= htmlspecialchars($_SESSION['csrf_token']) ?>"
                      onclick="deletarPedido(this)">
                <i class="fa fa-times"></i> Remover
              </button>
<?php endif; ?>
            </div>

 <div class="collapse mt-3 itens-pedido-container"
     id="itensPedido<?= $pedido['id'] ?>"
     data-loaded="0"
     data-pedido-id="<?= $pedido['id'] ?>">
  <div class="card card-body border-0 bg-light itens-body">
    <div class="text-muted small mb-0">
     <b>Carregando itens...</b>    </div>
  </div>
</div>

          </div>

        <?php endforeach; ?>
      <?php else: ?>
        <div class="alert alert-info text-center">Nenhum pedido encontrado.</div>
      <?php endif; ?>

    </div>
  </div>
</div>


<div class="paginacao mt-3">
  <?= renderPaginacaoPedidos($pagina, $totalPaginas, $limite, $queryString, 'includes/fin_pedidos/listagem.php'); ?>
</div>

  </div>
</div>

</div>
 <?php if($pode_editar): ?>
<!-- Modal de Edição dos Pedidos -->
<div class="modal fade" id="modalEditarPedido" tabindex="-1" aria-labelledby="modalEditarPedidoLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-xl">
    <div class="modal-content">
      <div class="modal-header bg-warning text-dark">
        <h5 class="modal-title" id="modalEditarPedidoLabel">Editar Pedido ao Fornecedor</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
      </div>
      <div class="modal-body">
        <form method="POST" enctype="multipart/form-data" id="form-pedido-editar">
          <input type="hidden" name="id" id="editar-id-pedido">

          <div class="row g-3">
            <div class="col-md-4">
              <label class="form-label">Data do Pedido</label>
              <input type="date" class="form-control" name="data_pedido" id="editar-data_pedido" required>
            </div>
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
    <div class="col-md-4">
                  <label class="form-label">Batalhão</label>
                  <select name="batalhao" id="editar-batalhao" class="form-select" required>
                    <option value="">Selecione...</option>
                    <?php while ($bat = $resBatalhoes->fetch_assoc()): ?>
                      <option value="<?= $bat['id'] ?>" <?= ($bat['id']==$id_om_usuario)?'selected':'' ?>>
                        <?= htmlspecialchars($bat['abreviatura'] . ' - ' . $bat['nome']) ?>
                      </option>
                    <?php endwhile; ?>
                  </select>
                </div>
            <div class="col-md-4">
              <label class="form-label">Solicitante</label>
              <input type="text" class="form-control" name="solicitante" id="editar-solicitante" required>
            </div>
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
              
              <div class="col-md-4">
  <label class="form-label">Viatura (Prefixo SGA)</label>
  <select class="form-select" name="id_vtr" id="editar-id_vtr" required>
                    <option value="" disabled selected>Selecione a viatura/equipamento</option>
                       <?php foreach ($viaturas as $vtr): ?>
                      <option value="<?= $vtr['id'] ?>">
                        <?= htmlspecialchars($vtr['prefixo_sga'] . ' - ' . $vtr['prefixo_velho'] . ' - ' . $vtr['nome_marca'] . ' - ' . $vtr['nome_modelo'] . ' - ' . $vtr['ano'] . ' - ' . $vtr['nome_om']) ?>
                      </option>
                    <?php endforeach; ?>
  </select>
</div>
            <div class="col-md-4">
  <label class="form-label">Ordem de Serviço (OS)</label>
  <select class="form-select" name="id_os" id="editar-id_os" required>
    <option value="" disabled selected>Selecione a OS</option>
    <?php
    // ------------------------------
    // Filtra as OS conforme batalhões permitidos
    // ------------------------------
    $condicaoBatalhoes = '';
    if (!empty($batalhoesPermitidos)) {
        // Monta lista de IDs válidos (ex: 1,2,3)
        $ids = implode(',', array_map('intval', $batalhoesPermitidos));
        $condicaoBatalhoes = "AND os.batalhao IN ($ids)";
    } else {
        // Se não houver batalhões, garante que não trará nenhuma OS
        $condicaoBatalhoes = "AND os.batalhao = 0";
    }

    // ------------------------------
    // Consulta OS com nome do batalhão
    // ------------------------------
    $sqlOS = "
      SELECT 
        os.id, 
        os.prefixo_sga, 
        os.problema, 
        om.nome AS nome_batalhao
      FROM os_principal os
      INNER JOIN organizacoes_militares om ON os.batalhao = om.id
      WHERE os.status IN ('Em andamento', 'Aguardando Peças', 'Aguardando Suprimento')
      $condicaoBatalhoes
      ORDER BY os.id ASC
    ";

    $resOS = $conexao->query($sqlOS);
    if ($resOS && $resOS->num_rows > 0):
      while ($os = $resOS->fetch_assoc()):
    ?>
        <option value="<?= $os['id'] ?>">
          OS #<?= htmlspecialchars($os['id']) ?> — 
          <?= htmlspecialchars($os['prefixo_sga']) ?> — 
          Problema: <?= htmlspecialchars($os['problema']) ?> - <?= htmlspecialchars($os['nome_batalhao']) ?>
        </option>
    <?php
      endwhile;
    else:
      echo '<option disabled>Nenhuma OS disponível para seu batalhão</option>';
    endif;
    ?>
    <option value="Sem OS">Sem OS</option>
  </select>
</div>
            <div class="col-md-4">
              <label class="form-label">Seção Responsável</label>
              <select class="form-select" name="secao_rspns" id="editar-secao_rspns" required>
                <option value="" disabled>Selecione</option>
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
            <div class="col-md-4">
              <label class="form-label">Situação do Pedido</label>
              <select class="form-select" name="situacao_pedido" id="editar-situacao_pedido" required>
    <option value="" disabled selected>Selecione a situação</option>
    <option value="Não enviado a empresa">Não enviado a empresa</option>
    <option value="Agd Fornecimento">Agd Fornecimento</option>
    <option value="Empresa entregou">Empresa entregou</option>
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
                  <div class="col-md-4">
  <label class="form-label">Local do pedido</label>
  <select class="form-select" name="local_pedido" id="editar-local_pedido" required>
    <option value="" disabled selected>Selecione o local do pedido</option>
                    <?php foreach ($destinos as $dest): ?>
                      <option value="<?= htmlspecialchars($dest['destino']) ?>">
                        <?= htmlspecialchars($dest['destino'] . ' - ' . $dest['nome_batalhao']) ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
</div>
            <div class="col-md-2">
              <label class="form-label">Desconto no Empenho (%)</label>
              <input type="number" class="form-control" name="desconto_empenho" id="editar-desconto_empenho" step="any">
            </div>
          </div>

          <hr>
          <h5 class="fw-bold mt-4">Itens do Pedido</h5>
          <div id="itensPedidoContainerEditar" class="mb-3"></div>
          <div class="text-end mb-3">
            <button type="button" class="btn btn-info btn-sm" onclick="adicionarItemPedidoEditar()">Adicionar item</button>
          </div>
            <div class="alert alert-secondary text-end fw-bold mt-3" id="editar-somaTotalItens">
  Valor total dos itens: R$ 0,00 / Valor total do desconto: R$ 0,00 / Valor final com desconto: R$ 0,00
</div>
	        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
          <button type="submit" class="btn btn-warning w-100 mt-4">Salvar Alterações</button>
        </form>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>

 <?php if($pode_cadastrar): ?>

<!-- Modal de Cadastro dos pedidos -->

<div class="modal fade" id="modalCadastrarPedido" tabindex="-1" aria-labelledby="modalCadastrarPedidoLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-xl">
    <div class="modal-content">
      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title" id="modalCadastrarPedidoLabel">Cadastrar Pedido ao Fornecedor</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Fechar"></button>
      </div>
      <div class="modal-body">
        <form method="POST" enctype="multipart/form-data" id="form-pedido-cadastrar">

          <div class="row g-3">
              
            <div class="col-md-4">
              <label class="form-label">Data do Pedido</label>
              <input type="date" class="form-control" name="data_pedido" required>
            </div>
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
    <div class="col-md-4">
                  <label class="form-label">Batalhão</label>
                  <select name="batalhao" id="batalhao" class="form-select" required>
                    <option value="">Selecione...</option>
                    <?php while ($bat = $resBatalhoes->fetch_assoc()): ?>
                      <option value="<?= $bat['id'] ?>" <?= ($bat['id']==$id_om_usuario)?'selected':'' ?>>
                        <?= htmlspecialchars($bat['abreviatura'] . ' - ' . $bat['nome']) ?>
                      </option>
                    <?php endwhile; ?>
                  </select>
                </div>
            <div class="col-md-4">
              <label class="form-label">Solicitante</label>
              <input type="text" class="form-control" name="solicitante" required>
            </div>
              
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
              
              <div class="col-md-4">
  <label class="form-label">Viatura (Prefixo SGA)</label>
  <select class="form-select" name="id_vtr" required>
                    <option value="" disabled selected>Selecione a viatura/equipamento</option>
                       <?php foreach ($viaturas as $vtr): ?>
                      <option value="<?= $vtr['id'] ?>">
                        <?= htmlspecialchars($vtr['prefixo_sga'] . ' - ' . $vtr['prefixo_velho'] . ' - ' . $vtr['nome_marca'] . ' - ' . $vtr['nome_modelo'] . ' - ' . $vtr['ano'] . ' - ' . $vtr['nome_om']) ?>
                      </option>
                    <?php endforeach; ?>
  </select>
</div>
              
              <div class="col-md-4">
  <label class="form-label">Ordem de Serviço (OS)</label>
  <select class="form-select" name="id_os" required>
    <option value="" disabled selected>Selecione a OS</option>
    <?php
    // ------------------------------
    // Filtra as OS conforme batalhões permitidos
    // ------------------------------
    $condicaoBatalhoes = '';
    if (!empty($batalhoesPermitidos)) {
        // Monta lista de IDs válidos (ex: 1,2,3)
        $ids = implode(',', array_map('intval', $batalhoesPermitidos));
        $condicaoBatalhoes = "AND os.batalhao IN ($ids)";
    } else {
        // Se não houver batalhões, garante que não trará nenhuma OS
        $condicaoBatalhoes = "AND os.batalhao = 0";
    }

    // ------------------------------
    // Consulta OS com nome do batalhão
    // ------------------------------
    $sqlOS = "
      SELECT 
        os.id, 
        os.prefixo_sga, 
        os.problema, 
        om.nome AS nome_batalhao
      FROM os_principal os
      INNER JOIN organizacoes_militares om ON os.batalhao = om.id
      WHERE os.status IN ('Em andamento', 'Aguardando Peças', 'Aguardando Suprimento')
      $condicaoBatalhoes
      ORDER BY os.id ASC
    ";

    $resOS = $conexao->query($sqlOS);
    if ($resOS && $resOS->num_rows > 0):
      while ($os = $resOS->fetch_assoc()):
    ?>
        <option value="<?= $os['id'] ?>">
          OS #<?= htmlspecialchars($os['id']) ?> — 
          <?= htmlspecialchars($os['prefixo_sga']) ?> — 
          Problema: <?= htmlspecialchars($os['problema']) ?> - <?= htmlspecialchars($os['nome_batalhao']) ?>
        </option>
    <?php
      endwhile;
    else:
      echo '<option disabled>Nenhuma OS disponível para seu batalhão</option>';
    endif;
    ?>
    <option value="Sem OS">Sem OS</option>
  </select>
</div>

              <div class="col-md-4">
  <label class="form-label">Seção Responsável</label>
  <select class="form-select" name="secao_rspns" required>
        <option value="" disabled selected>Selecione a seção responsável</option>
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
           <div class="col-md-4">
  <label class="form-label">Situação do Pedido</label>
  <select class="form-select" name="situacao_pedido" required>
    <option value="" disabled selected>Selecione a situação</option>
    <option value="Não enviado a empresa">Não enviado a empresa</option>
    <option value="Agd Fornecimento">Agd Fornecimento</option>
    <option value="Empresa entregou">Empresa entregou</option>
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
                  SELECT
                  cd.id,
                  cd.destino,
                  cd.batalhao,
                  om.nome AS nome_batalhao
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
                  <div class="col-md-4">
  <label class="form-label">Local do pedido</label>
  <select class="form-select" name="local_pedido" required>
    <option value="" disabled selected>
        Selecione o local do pedido
    </option>

    <?php foreach ($destinos as $dest): ?>

        <option value="<?= $dest['id'] ?>">
            <?= htmlspecialchars(
                $dest['destino'] . ' - ' . $dest['nome_batalhao']
            ) ?>
        </option>

    <?php endforeach; ?>
</select>
</div>
           <div class="col-md-2">
  <label class="form-label">Desconto no Empenho (%)</label>
  <input type="number" class="form-control" name="desconto_empenho" id="desconto_empenho" step="any" value="0">
</div>

          <hr>
          <h5 class="fw-bold mt-4">Itens do Pedido</h5>

          <div id="itensPedidoContainer" class="mb-3"></div>
          <div>
              
             
              
            <button type="button" class="btn btn-info btn-sm" onclick="adicionarItemPedido()">Adicionar item</button>
          </div>
            
            <div class="alert alert-secondary text-end fw-bold mt-3" id="somaTotalItens">
  Valor total dos itens: R$ 0,00 / Valor total do desconto: R$ 0,00 / Valor final com desconto: R$ 0,00
</div>
	        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
          <button type="submit" class="btn btn-success w-100 mt-4">Cadastrar Pedido</button>
        </form>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>


<!-- Script da página de Requisição de Vtr/Eqp -->
<script>
    window.funcaoInicializacao = 'inicializarPedidosFornecedores';
document.querySelectorAll('.btn-toggle-autorizacao-fin').forEach(botao => {
  botao.addEventListener('click', function () {
    toggleAutorizacaoPedidoFornecedor(this);
  });
});
</script>


