<?php
header('Content-Type: text/html; charset=utf-8');
session_start();
require_once '../api/seguranca.php';

$permissoes = verificarPermissao([24]);

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

// ======================================================================
// DADOS DO USUÁRIO
// ======================================================================
$usuario          = $_SESSION['usuario'] ?? [];
$nivel_usuario    = (int)($usuario['nivel'] ?? 3);
$batalhao_usuario = (int)($usuario['batalhao'] ?? 0);

if (!$batalhao_usuario) {
    die("Erro: não foi possível identificar o batalhão do usuário.");
}

// ======================================================================
// FILTROS DO USUÁRIO
// ======================================================================
$id               = $_GET['id'] ?? '';
$id_requisicao    = $_GET['id_requisicao'] ?? '';
$requisitante     = $_GET['requisitante'] ?? '';
$marca            = $_GET['marca'] ?? '';
$destinatario     = $_GET['destinatario'] ?? '';
$obra             = $_GET['obra'] ?? '';
$nmr_empenho      = $_GET['nmr_empenho'] ?? '';
$ano              = $_GET['ano'] ?? '';
$categoria        = $_GET['categoria'] ?? '';
$local            = $_GET['local'] ?? '';
$fornecedor       = $_GET['fornecedor'] ?? '';
$filtro_batalhao  = $_GET['batalhao'] ?? '';
$saldo_real       = $_GET['saldo_real'] ?? ''; // positivo|zerado|negativo

$filtros = [];
$params  = [];
$tipos   = "";

// ======================================================================
// FILTROS EXISTENTES
// ======================================================================
if (!empty($id)) {
    $filtros[] = "e.id = ?";
    $params[]  = (int)$id;
    $tipos    .= "i";
}

if (!empty($id_requisicao)) {
    $filtros[] = "r.id = ?";
    $params[]  = (int)$id_requisicao;
    $tipos    .= "i";
}

if (!empty($requisitante)) {
    $filtros[] = "r.requisitante LIKE ?";
    $params[]  = "%{$requisitante}%";
    $tipos    .= "s";
}

if (!empty($nmr_empenho)) {
    $filtros[] = "e.nmr_empenho LIKE ?";
    $params[]  = "%{$nmr_empenho}%";
    $tipos    .= "s";
}

if (!empty($marca)) {
    $filtros[] = "r.marca LIKE ?";
    $params[]  = "%{$marca}%";
    $tipos    .= "s";
}

if (!empty($destinatario)) {
    $filtros[] = "r.destinatario LIKE ?";
    $params[]  = "%{$destinatario}%";
    $tipos    .= "s";
}

if (!empty($obra)) {
    $filtros[] = "e.obra = ?";
    $params[]  = $obra;
    $tipos    .= "s";
}

if (!empty($categoria)) {
    $filtros[] = "e.categoria = ?";
    $params[]  = $categoria;
    $tipos    .= "s";
}

if (!empty($local)) {
    $filtros[] = "e.local = ?";
    $params[]  = $local;
    $tipos    .= "s";
}

if (!empty($ano)) {
    $filtros[] = "e.ano = ?";
    $params[]  = $ano;
    $tipos    .= "s";
}

if (!empty($fornecedor)) {
    $filtros[] = "r.id_fornecedor = ?";
    $params[]  = (int)$fornecedor;
    $tipos    .= "i";
}

// ======================================================================
// CONTROLE DE ACESSO POR NÍVEL (mantido)
// ======================================================================
if ($nivel_usuario == 1) {

    if (!empty($filtro_batalhao)) {
        $filtros[] = "r.batalhao = ?";
        $params[]  = (int)$filtro_batalhao;
        $tipos    .= "i";
    }

} elseif ($nivel_usuario == 2) {

    $sqlSubs = "SELECT id_om_menor FROM organizacoes_militares_sub WHERE id_om_maior = ?";
    $stmtSubs = $conexao->prepare($sqlSubs);
    $stmtSubs->bind_param("i", $batalhao_usuario);
    $stmtSubs->execute();
    $resSubs = $stmtSubs->get_result();

    $batalhoesPermitidos = [$batalhao_usuario];
    while ($row = $resSubs->fetch_assoc()) {
        $batalhoesPermitidos[] = (int)$row['id_om_menor'];
    }
    $stmtSubs->close();

    if (!empty($filtro_batalhao)) {

        if (!in_array((int)$filtro_batalhao, $batalhoesPermitidos, true)) {
            die("Acesso negado ao batalhão selecionado.");
        }

        $filtros[] = "r.batalhao = ?";
        $params[]  = (int)$filtro_batalhao;
        $tipos    .= "i";

    } else {

        $placeholder = implode(",", array_fill(0, count($batalhoesPermitidos), "?"));
        $filtros[] = "r.batalhao IN ($placeholder)";

        foreach ($batalhoesPermitidos as $b) {
            $params[] = (int)$b;
            $tipos   .= "i";
        }
    }

} else {
    $filtros[] = "r.batalhao = ?";
    $params[]  = (int)$batalhao_usuario;
    $tipos    .= "i";
}

// ======================================================================
// 🔹 FILTRO SALDO REAL (leve) usando EXISTS
// (evita subquery gigante com GROUP BY)
// ======================================================================
// ======================================================================
// 🔹 FILTRO SALDO REAL
// ======================================================================

$condicaoSaldo = '';

if (!empty($saldo_real)) {

$subTotalGasto = "
(
SELECT COALESCE(SUM(pf.valor_total * (1 - COALESCE(p.desconto_empenho,0)/100)),0)
FROM fin_ordemforn o
JOIN fin_ordemforn_pedidos op ON op.id_ordemforn = o.id
JOIN fin_pedidos_forn p ON p.id = op.id_pedido
LEFT JOIN fin_pedidos_forn_itens pf ON pf.id_principal = p.id
WHERE o.id_empenho = e.id
)
";
if ($saldo_real === 'positivo') {
    $condicaoSaldo = "(r.valor_empenhado - $subTotalGasto) > 0";
}
elseif ($saldo_real === 'zerado') {
    $condicaoSaldo = "(r.valor_empenhado - $subTotalGasto) = 0";
}
elseif ($saldo_real === 'negativo') {
    $condicaoSaldo = "(r.valor_empenhado - $subTotalGasto) < 0";
}

}

// ======================================================================
// WHERE FINAL
// ======================================================================
$condicoesBase = !empty($filtros) ? implode(" AND ", $filtros) : '';

if ($condicoesBase && $condicaoSaldo) {
    $condicoes = "WHERE $condicoesBase AND $condicaoSaldo";
} elseif ($condicoesBase) {
    $condicoes = "WHERE $condicoesBase";
} elseif ($condicaoSaldo) {
    $condicoes = "WHERE $condicaoSaldo";
} else {
    $condicoes = "";
}

// ======================================================================
// PAGINAÇÃO
// ======================================================================
$limite = (isset($_GET['limite']) && is_numeric($_GET['limite'])) ? (int)$_GET['limite'] : 10;
$pagina = (isset($_GET['pagina']) && is_numeric($_GET['pagina']) && $_GET['pagina'] > 0) ? (int)$_GET['pagina'] : 1;
$offset = ($pagina - 1) * $limite;

// ======================================================================
// CONTAGEM TOTAL (SEM JOIN pesado de gastos)
// ======================================================================

$condicoesTotal = !empty($filtros) ? "WHERE " . implode(" AND ", $filtros) : "";

$sqlTotal = "
SELECT COUNT(*) AS total
FROM fin_empenhos e
INNER JOIN fin_requisicao r ON e.id_requisicao = r.id
$condicoesTotal
";

$stmtTotal = $conexao->prepare($sqlTotal);
if (!empty($params)) {
    $stmtTotal->bind_param($tipos, ...$params);
}
$stmtTotal->execute();
$totalRegistros = (int)($stmtTotal->get_result()->fetch_assoc()['total'] ?? 0);
$totalPaginas = max(1, (int)ceil($totalRegistros / $limite));
$stmtTotal->close();

// ======================================================================
// BUSCA PRINCIPAL (SEM JOIN pesado de gastos)
// ======================================================================
$sql = "
SELECT 
    e.*,
    r.requisitante,
    r.destinatario,
    r.status_requisicao,
    r.nota_credito,
    r.marca,
    r.id_fornecedor,
    r.batalhao,
    r.valor_empenhado,
	e.id,

    om.nome AS nome_batalhao

FROM fin_empenhos e
INNER JOIN fin_requisicao r ON e.id_requisicao = r.id
LEFT JOIN organizacoes_militares om ON om.id = r.batalhao

$condicoes
ORDER BY e.id DESC
LIMIT ? OFFSET ?
";

$paramsExec = $params;
$tiposExec  = $tipos;

$paramsExec[] = $limite;
$paramsExec[] = $offset;
$tiposExec .= "ii";

$stmt = $conexao->prepare($sql);
$stmt->bind_param($tiposExec, ...$paramsExec);
$stmt->execute();
$res = $stmt->get_result();

// ======================================================================
// Carrega empenhos da página em array + ids
// ======================================================================
$empenhosArray = [];
$idsEmpenhoPagina = [];

while ($row = $res->fetch_assoc()) {
    $empenhosArray[] = $row;
    $idsEmpenhoPagina[] = (int)$row['id'];
}

$stmt->close();

// ======================================================================
// Calcula TOTAL_GASTO só dos empenhos da página (anti MAX_JOIN_SIZE)
// (sem JOIN direto com fin_pedidos_forn_itens)
// ======================================================================
$gastosPorEmpenho = []; // [id_empenho => total_gasto]

if (!empty($idsEmpenhoPagina)) {

    // 1) Pega os pedidos (id_pedido) vinculados aos empenhos da página
    $inEmp = implode(',', array_fill(0, count($idsEmpenhoPagina), '?'));

    $sqlEmpPedidos = "
        SELECT o.id_empenho, op.id_pedido
        FROM fin_ordemforn o
        JOIN fin_ordemforn_pedidos op ON op.id_ordemforn = o.id
        WHERE o.id_empenho IN ($inEmp)
    ";

    $stmtEP = $conexao->prepare($sqlEmpPedidos);
    $tiposEP = str_repeat('i', count($idsEmpenhoPagina));
    $stmtEP->bind_param($tiposEP, ...$idsEmpenhoPagina);
    $stmtEP->execute();
    $rsEP = $stmtEP->get_result();

    $mapEmpenhoPedidos = []; // [id_empenho => [id_pedido, id_pedido...]]
    $idsPedidos = [];        // lista única de pedidos

    while ($row = $rsEP->fetch_assoc()) {
        $idEmp = (int)$row['id_empenho'];
        $idPed = (int)$row['id_pedido'];

        if ($idEmp <= 0 || $idPed <= 0) continue;

        $mapEmpenhoPedidos[$idEmp][] = $idPed;
        $idsPedidos[$idPed] = true; // set
    }
    $stmtEP->close();

    // Se não tem pedidos vinculados, já termina
    if (!empty($idsPedidos)) {

        // 2) Soma por pedido (id_principal = id_pedido)
        $listaPedidos = array_keys($idsPedidos);
        $inPed = implode(',', array_fill(0, count($listaPedidos), '?'));

        $sqlSomaPedidos = "
SELECT 
    p.id,
    COALESCE(SUM(i.valor_total),0) AS total_itens,
    COALESCE(p.desconto_empenho,0) AS desconto
FROM fin_pedidos_forn p
LEFT JOIN fin_pedidos_forn_itens i 
    ON i.id_principal = p.id
WHERE p.id IN ($inPed)
GROUP BY p.id
";

       $stmtSP = $conexao->prepare($sqlSomaPedidos);
$tiposSP = str_repeat('i', count($listaPedidos));
$stmtSP->bind_param($tiposSP, ...$listaPedidos);
$stmtSP->execute();
$rsSP = $stmtSP->get_result();

$totalPorPedido = []; // [id_pedido => total_final]

while ($r = $rsSP->fetch_assoc()) {

    $totalItens = (float)$r['total_itens'];
    $desconto = (float)$r['desconto'];

    // aplica desconto percentual
    $totalFinal = $totalItens * (1 - $desconto / 100);

    $totalPorPedido[(int)$r['id']] = $totalFinal;
}

$stmtSP->close();

        // 3) Agrega por empenho em PHP
        foreach ($mapEmpenhoPedidos as $idEmp => $pedidosDoEmp) {
            $soma = 0.0;
            foreach ($pedidosDoEmp as $idPed) {
                $soma += (float)($totalPorPedido[$idPed] ?? 0);
            }
            $gastosPorEmpenho[(int)$idEmp] = $soma;
        }
    }
}

// ======================================================================
// PAGINAÇÃO (Estilo Frota)
// ======================================================================
if (!function_exists('renderPaginacaoEmpenhos')) {

    function renderPaginacaoEmpenhos($pagina, $totalPaginas, $limite, $queryString, $arquivo = 'includes/fin_empenhos/listagem.php') {

        $maxLinks = 10;

        $makeUrl = function($p) use ($arquivo, $queryString, $limite) {
            return "{$arquivo}?{$queryString}&pagina={$p}&limite={$limite}";
        };

        $html = '<div class="pagination-wrapper">';
        $html .= '<nav><ul class="pagination pagination-sm">';

        if ($pagina > 1) {
            $html .= "<li class='page-item'>
                        <a class='page-link paginacao-empenho' href='#' data-page='" . $makeUrl(1) . "'>&laquo; Primeira</a>
                      </li>";
        }

        if ($pagina > 1) {
            $prev = $pagina - 1;
            $html .= "<li class='page-item'>
                        <a class='page-link paginacao-empenho' href='#' data-page='" . $makeUrl($prev) . "'>&lsaquo;</a>
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
                        <a class='page-link paginacao-empenho' href='#' data-page='" . $makeUrl($i) . "'>{$i}</a>
                      </li>";
        }

        if ($fim < $totalPaginas) {
            $html .= "<li class='page-item disabled'><span class='page-link'>...</span></li>";
        }

        if ($pagina < $totalPaginas) {
            $next = $pagina + 1;
            $html .= "<li class='page-item'>
                        <a class='page-link paginacao-empenho' href='#' data-page='" . $makeUrl($next) . "'>&rsaquo;</a>
                      </li>";
        }

        if ($pagina < $totalPaginas) {
            $html .= "<li class='page-item'>
                        <a class='page-link paginacao-empenho' href='#' data-page='" . $makeUrl($totalPaginas) . "'>Última &raquo;</a>
                      </li>";
        }

        $html .= '</ul></nav></div>';
        return $html;
    }
}


// ======================================================================
// QUERY STRING
// ======================================================================
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

@media (max-width: 576px) {
    .pagination .page-item .page-link {
        min-width: 32px;
        height: 32px;
        font-size: 0.8rem;
        padding: 0.4rem 0.6rem;
    }
}

.btn-group .btn {
  border-radius: 20px;
  transition: all 0.3s ease;
}
    @media (max-width: 768px) {
    .table-responsive table thead {
        display: none;
    }
    .table-responsive table, 
    .table-responsive tbody, 
    .table-responsive tr, 
    .table-responsive td {
        display: block;
        width: 100%;
    }
    .table-responsive tr {
        margin-bottom: 1rem;
        border: 1px solid #dee2e6;
        border-radius: 0.5rem;
        box-shadow: 0 0 10px rgba(0,0,0,0.05);
        padding: 0.75rem;
    }
    .table-responsive td {
        text-align: left;
        border: none;
        border-bottom: 1px solid #f1f1f1;
        padding: 0.5rem 0;
    }
    .table-responsive td:last-child {
        border-bottom: none;
    }
}
</style>

<div class="container">
  <div class="page-inner">
    <div class="d-flex justify-content-between align-items-center py-3">
      <div>
        <h3 class="fw-bold mb-1">Listagem de Empenhos</h3>
        <h6 class="text-muted">Gerenciamento dos empenhos cadastrados no sistema.</h6>
      </div>
        <div>
			<?php if($pode_importar): ?>
            <!-- Botão para abrir modal -->
<button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#modalImportarEmpenhos">
  Importar Empenhos
</button>
			<?php endif; ?>
            <!-- Botão Excel -->
			<?php if($pode_exportar): ?>
<button id="btnExportarExcelEmpenhos" class="btn btn-success">
    <i class="fas fa-file-excel"></i> Exportar Excel
</button>
			<?php endif; ?>
        </div>
    </div>
      <div class="mb-3">
  <button class="btn btn-outline-primary w-100 d-flex justify-content-center align-items-center" onclick="toggleFiltrosEmpenho()">
    <i class="fas fa-search me-2"></i> Filtros
  </button>
</div>

<div id="filtros-container-empenho" style="display: none;" class="mb-3">
  <div class="card shadow-sm border-0">
    <div class="card-body">
      <form method="GET" id="filtroEmpenhoForm">
        <div class="row g-3">
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

            <div class="col-md-2">
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
    <label class="form-label fw-semibold">Fornecedor</label>
    <select name="fornecedor" class="form-select">
        <option value="">Todos</option>
            <?php
              $sql_for = $conexao->query("SELECT id, nome_empresa,cnpj_empresa FROM fin_fornecedores ORDER BY nome_empresa ASC");
              while ($for = $sql_for->fetch_assoc()):
              ?>
                <option value="<?= $for['id'] ?>"><?= htmlspecialchars($for['nome_empresa']) ?> - <?= htmlspecialchars($for['cnpj_empresa']) ?></option>
              <?php endwhile; ?>
                </select>
</div>
            
            <div class="col-md-3">
            <label class="form-label fw-semibold">Nmr do empenho</label>
            <input type="text" class="form-control" name="nmr_empenho" value="<?= htmlspecialchars($_GET['nmr_empenho'] ?? '') ?>">
          </div>
          <div class="col-md-3">
            <label class="form-label fw-semibold">Requisitante</label>
            <input type="text" class="form-control" name="requisitante" value="<?= htmlspecialchars($_GET['requisitante'] ?? '') ?>">
          </div>
            
            <div class="col-md-3">
            <label class="form-label fw-semibold">Marca</label>
            <input type="text" class="form-control" name="marca" value="<?= htmlspecialchars($_GET['marca'] ?? '') ?>">
          </div>

          <div class="col-md-3">
            <label class="form-label fw-semibold">Destinatário</label>
            <input type="text" class="form-control" name="destinatario" value="<?= htmlspecialchars($_GET['destinatario'] ?? '') ?>">
          </div>

          <div class="col-md-2">
            <label class="form-label fw-semibold">Obra</label>
            <input type="text" class="form-control" name="obra" value="<?= htmlspecialchars($_GET['obra'] ?? '') ?>">
          </div>

          <div class="col-md-2">
            <label class="form-label fw-semibold">Ano</label>
            <input type="text" class="form-control" name="ano" value="<?= htmlspecialchars($_GET['ano'] ?? '') ?>">
          </div>
            
          <div class="col-md-2">
            <label class="form-label fw-semibold">Categoria</label>
            <input type="text" class="form-control" name="categoria" value="<?= htmlspecialchars($_GET['categoria'] ?? '') ?>">
          </div>
            
          <div class="col-md-2">
            <label class="form-label fw-semibold">Local</label>
            <input type="text" class="form-control" name="local" value="<?= htmlspecialchars($_GET['local'] ?? '') ?>">
          </div>
            <div class="col-md-2">
    <label class="form-label fw-semibold">Saldo Real</label>
    <select name="saldo_real" class="form-select">
        <option value="">Todos</option>
        <option value="positivo" <?= (($_GET['saldo_real'] ?? '') === 'positivo') ? 'selected' : '' ?>>
            Positivo
        </option>
        <option value="zerado" <?= (($_GET['saldo_real'] ?? '') === 'zerado') ? 'selected' : '' ?>>
            Zerado
        </option>
        <option value="negativo" <?= (($_GET['saldo_real'] ?? '') === 'negativo') ? 'selected' : '' ?>>
            Negativo
        </option>
    </select>
</div>

            
          <div class="col-12 d-flex justify-content-between mt-2">
            <button type="button" id="btnLimparFiltrosEmpenho" class="btn btn-black ms-2">Limpar Filtros</button>
            <button type="submit" class="btn btn-primary px-4">Aplicar</button>
          </div>
            
        </div>
      </form>
    </div>
  </div>
</div>

<div class="mb-3">
  <label for="limiteEmpenho" class="me-2 mb-0">Mostrar</label>
  <select id="limiteEmpenho" name="limite" class="form-select d-inline w-auto">
    <?php
    $limiteAtual = $_GET['limite'] ?? 10;
    foreach ([5, 10, 25, 50, 100, 500] as $opcao) {
        $selected = ($limiteAtual == $opcao) ? 'selected' : '';
        echo "<option value=\"$opcao\" $selected>$opcao</option>";
    }
    ?>
  </select>
  <span class="ms-2">por página</span>
</div>


<div class="paginacao">
    <?= renderPaginacaoEmpenhos($pagina, $totalPaginas, $limite, $queryString, 'includes/fin_empenhos/listagem.php'); ?>
</div>
<?php
// ===============================
// VARIÁVEIS DE SOMA GERAL
// ===============================
$soma_total_empenhado   = 0;
$soma_nao_entregue      = 0;
$soma_entregue          = 0;
$soma_capeador          = 0;
$soma_liquidado         = 0;
$soma_total_utilizado   = 0;
$soma_saldo_siafi       = 0;
$soma_saldo_real        = 0;
$soma_diferenca         = 0;

// ==========================================================
// PRÉ-CARGAS (para não fazer SELECT dentro do loop)
// ==========================================================

// 1) Fornecedores da página (nome/cnpj)
$fornecedorMap = []; // [id_fornecedor => ['nome_empresa'=>..., 'cnpj_empresa'=>...]]
$idsFornecedorPagina = [];

foreach ($empenhosArray as $e) {
  if (!empty($e['id_fornecedor'])) {
    $idsFornecedorPagina[(int)$e['id_fornecedor']] = true;
  }
}

if (!empty($idsFornecedorPagina)) {
  $listaF = array_keys($idsFornecedorPagina);
  $inF = implode(',', array_fill(0, count($listaF), '?'));

  $sqlF = "SELECT id, nome_empresa, cnpj_empresa FROM fin_fornecedores WHERE id IN ($inF)";
  $stmtF = $conexao->prepare($sqlF);
  $tiposF = str_repeat('i', count($listaF));
  $stmtF->bind_param($tiposF, ...$listaF);
  $stmtF->execute();
  $rsF = $stmtF->get_result();

  while ($f = $rsF->fetch_assoc()) {
    $fornecedorMap[(int)$f['id']] = [
      'nome_empresa' => $f['nome_empresa'] ?? '',
      'cnpj_empresa' => $f['cnpj_empresa'] ?? '',
    ];
  }
  $stmtF->close();
}

// 2) Valores por STATUS (Não entregue / Entregue / Capeador / Liquidado)
//    -> anti MAX_JOIN_SIZE: pega pedidos por empenho, soma itens por pedido, soma por status em PHP.
$valoresStatusPorEmpenho = []; // [id_empenho => ['Não entregue'=>x,'Entregue'=>y,'Capeador'=>y,'Liquidado'=>y]]

if (!empty($idsEmpenhoPagina)) {

// 2.1) Mapeia (id_empenho => lista de pedidos com status) // ESSE É O QUE EXIBE

$inEmp = implode(',', array_fill(0, count($idsEmpenhoPagina), '?'));

$sqlPedStatus = "
    SELECT 
        o.id_empenho, 
        op.id_pedido, 
        o.status
    FROM fin_ordemforn o
    JOIN fin_ordemforn_pedidos op 
        ON op.id_ordemforn = o.id
    WHERE o.id_empenho IN ($inEmp)
";

$stmtPS = $conexao->prepare($sqlPedStatus);
$tiposPS = str_repeat('i', count($idsEmpenhoPagina));
$stmtPS->bind_param($tiposPS, ...$idsEmpenhoPagina);
$stmtPS->execute();
$rsPS = $stmtPS->get_result();

$pedidosInfo = []; // [id_pedido => ['id_empenho'=>..,'status'=>..]]
$idsPedidosSet = [];

while ($row = $rsPS->fetch_assoc()) {

    $idEmp = (int)$row['id_empenho'];
    $idPed = (int)$row['id_pedido'];
    $st    = trim((string)($row['status'] ?? ''));

    if ($idEmp <= 0 || $idPed <= 0) continue;

    $pedidosInfo[$idPed] = [
        'id_empenho' => $idEmp,
        'status' => $st
    ];

    $idsPedidosSet[$idPed] = true;
}

$stmtPS->close();



/* =========================================
   2.2) Soma itens por pedido + aplica desconto
========================================= */

$totalPorPedido = []; // [id_pedido => total_final]

if (!empty($idsPedidosSet)) {

    $listaPedidos = array_keys($idsPedidosSet);
    $inPed = implode(',', array_fill(0, count($listaPedidos), '?'));

    $sqlSomaPedidos = "
        SELECT 
            p.id,
            COALESCE(SUM(i.valor_total),0) AS total_itens,
            COALESCE(p.desconto_empenho,0) AS desconto
        FROM fin_pedidos_forn p
        LEFT JOIN fin_pedidos_forn_itens i 
            ON i.id_principal = p.id
        WHERE p.id IN ($inPed)
        GROUP BY p.id
    ";

    $stmtSP = $conexao->prepare($sqlSomaPedidos);
    $tiposSP = str_repeat('i', count($listaPedidos));
    $stmtSP->bind_param($tiposSP, ...$listaPedidos);
    $stmtSP->execute();
    $rsSP = $stmtSP->get_result();

    while ($r = $rsSP->fetch_assoc()) {

        $idPed = (int)$r['id'];
        $totalItens = (float)$r['total_itens'];
        $desconto = (float)$r['desconto']; // exemplo: 20 = 20%

        // aplica desconto percentual
        $totalFinal = $totalItens * (1 - ($desconto / 100.0));

        $totalPorPedido[$idPed] = $totalFinal;
    }

    $stmtSP->close();
}



/* =========================================
   2.3) Agrega por empenho e por status
========================================= */

foreach ($idsEmpenhoPagina as $idEmp) {

    $valoresStatusPorEmpenho[(int)$idEmp] = [
        'Não entregue' => 0.0,
        'Entregue'     => 0.0,
        'Capeador'     => 0.0,
        'Liquidado'    => 0.0,
    ];
}


foreach ($pedidosInfo as $idPed => $info) {

    $idEmp = (int)$info['id_empenho'];
    $stRaw = mb_strtolower(trim((string)$info['status']));
    $valor = (float)($totalPorPedido[$idPed] ?? 0);

    if (!isset($valoresStatusPorEmpenho[$idEmp])) {
        continue;
    }

    if ($stRaw === 'não entregue' || $stRaw === 'nao entregue') {

        $valoresStatusPorEmpenho[$idEmp]['Não entregue'] += $valor;

    } elseif ($stRaw === 'entregue') {

        $valoresStatusPorEmpenho[$idEmp]['Entregue'] += $valor;

    } elseif ($stRaw === 'capeador') {

        $valoresStatusPorEmpenho[$idEmp]['Capeador'] += $valor;

    } elseif ($stRaw === 'liquidado' || $stRaw === 'pago') {

        $valoresStatusPorEmpenho[$idEmp]['Liquidado'] += $valor;

    }
}
}
?>

<?php if (!empty($empenhosArray)): ?>

<!-- =============================== -->
<!--   RESUMO DOS VALORES - EM CARDS -->
<!-- =============================== -->
<div class="card border-0 shadow-sm mb-4">
  <div class="card-body">
    <h5 class="fw-bold mb-3">Resumo dos Valores dos Empenhos Exibidos</h5>

    <div class="row g-3">

      <div class="col-6 col-md-4 col-lg-3 col-xl-2">
        <div class="card text-center shadow-sm h-100">
          <div class="card-body p-2">
            <small class="text-muted">Total Empenhado</small>
            <h6 id="sum_total_empenhado" class="fw-bold mt-1">0,00</h6>
          </div>
        </div>
      </div>

      <div class="col-6 col-md-4 col-lg-3 col-xl-2">
        <div class="card text-center shadow-sm h-100">
          <div class="card-body p-2">
            <small class="text-muted">Sol. não entregue</small>
            <h6 id="sum_nao_entregue" class="fw-bold mt-1">0,00</h6>
          </div>
        </div>
      </div>

      <div class="col-6 col-md-4 col-lg-3 col-xl-2">
        <div class="card text-center shadow-sm h-100">
          <div class="card-body p-2">
            <small class="text-muted">Entregue não liq.</small>
            <h6 id="sum_entregue" class="fw-bold mt-1">0,00</h6>
          </div>
        </div>
      </div>

      <div class="col-6 col-md-4 col-lg-3 col-xl-2">
        <div class="card text-center shadow-sm h-100">
          <div class="card-body p-2">
            <small class="text-muted">Em Capeador</small>
            <h6 id="sum_capeador" class="fw-bold mt-1">0,00</h6>
          </div>
        </div>
      </div>

      <div class="col-6 col-md-4 col-lg-3 col-xl-2">
        <div class="card text-center shadow-sm h-100">
          <div class="card-body p-2">
            <small class="text-muted">Liquidado</small>
            <h6 id="sum_liquidado" class="fw-bold mt-1">0,00</h6>
          </div>
        </div>
      </div>

      <div class="col-6 col-md-4 col-lg-3 col-xl-2">
        <div class="card text-center shadow-sm h-100">
          <div class="card-body p-2">
            <small class="text-muted">Total Utilizado</small>
            <h6 id="sum_total_utilizado" class="fw-bold mt-1">0,00</h6>
          </div>
        </div>
      </div>

      <div class="col-6 col-md-4 col-lg-3 col-xl-2">
        <div class="card text-center shadow-sm h-100">
          <div class="card-body p-2">
            <small class="text-muted">Saldo SIAFI</small>
            <h6 id="sum_siafi" class="fw-bold mt-1">0,00</h6>
          </div>
        </div>
      </div>

      <div class="col-6 col-md-4 col-lg-3 col-xl-2">
        <div class="card text-center shadow-sm h-100">
          <div class="card-body p-2">
            <small class="text-muted">Saldo Real</small>
            <h6 id="sum_real" class="fw-bold mt-1">0,00</h6>
          </div>
        </div>
      </div>

      <div class="col-6 col-md-4 col-lg-3 col-xl-2">
        <div class="card text-center shadow-sm h-100">
          <div class="card-body p-2">
            <small class="text-muted">Diferença</small>
            <h6 id="sum_diferenca" class="fw-bold mt-1">0,00</h6>
          </div>
        </div>
      </div>

    </div>
  </div>
</div>

<div class="row g-3">

<?php foreach ($empenhosArray as $empenho): ?>

<?php
$idEmpenho     = (int)$empenho['id'];
$id_requisicao = (int)$empenho['id_requisicao'];

// ✅ Valor empenhado
$valorEmpenhado2 = (float)($empenho['valor_empenhado'] ?? 0);
$valorEmpenhado  = number_format($valorEmpenhado2, 2, ',', '.');

$soma_total_empenhado += $valorEmpenhado2;


// ✅ Fornecedor
$idFornecedor = (int)($empenho['id_fornecedor'] ?? 0);
$nome_empresa = $fornecedorMap[$idFornecedor]['nome_empresa'] ?? '';
$cnpj_empresa = $fornecedorMap[$idFornecedor]['cnpj_empresa'] ?? '';


// ✅ Valores por status (já pré-calculados)
$valoresStatus = $valoresStatusPorEmpenho[$idEmpenho] ?? [
  'Não entregue' => 0.0,
  'Entregue'     => 0.0,
  'Capeador'     => 0.0,
  'Liquidado'    => 0.0
];


// 🔹 APLICA DESCONTO REAL DO PEDIDO
$valorNaoEntregue = (float)$valoresStatus['Não entregue'];
$valorEntregue    = (float)$valoresStatus['Entregue'];
$valorCapeador    = (float)$valoresStatus['Capeador'];
$valorLiquidado   = (float)$valoresStatus['Liquidado'];


// SOMAS GERAIS
$soma_nao_entregue += $valorNaoEntregue;
$soma_entregue     += $valorEntregue;
$soma_capeador     += $valorCapeador;
$soma_liquidado    += $valorLiquidado;


// 🔹 TOTAL UTILIZADO (já com desconto aplicado no cálculo anterior)
$valorTotalUtilizado = $valorNaoEntregue
                     + $valorEntregue
                     + $valorCapeador
                     + $valorLiquidado;

$soma_total_utilizado += $valorTotalUtilizado;


// ✅ SALDO REAL
$SaldoReal = $valorEmpenhado2 - $valorTotalUtilizado;

$soma_saldo_real += $SaldoReal;


// ===============================
// SIAFI
// ===============================
$nmr_empenho = (string)($empenho['nmr_empenho'] ?? '');
$saldoSiafi = null;

$stmtSaldo = $conexao->prepare("SELECT saldo_empenho FROM fin_siafi_corrente WHERE nmr_empenho = ?");
$stmtSaldo->bind_param("s", $nmr_empenho);
$stmtSaldo->execute();
$linhaSaldo = $stmtSaldo->get_result()->fetch_assoc();
$stmtSaldo->close();

if (!$linhaSaldo) {
  $stmtSaldo = $conexao->prepare("SELECT saldo_empenho FROM fin_siafi_restopagar WHERE nmr_empenho = ?");
  $stmtSaldo->bind_param("s", $nmr_empenho);
  $stmtSaldo->execute();
  $linhaSaldo = $stmtSaldo->get_result()->fetch_assoc();
  $stmtSaldo->close();
}

if ($linhaSaldo) {
  $saldoSiafi = (float)$linhaSaldo['saldo_empenho'];
  $soma_saldo_siafi += $saldoSiafi;
}


// ✅ DIFERENÇA
$diferenca = (!is_null($saldoSiafi)) ? ($saldoSiafi - $SaldoReal) : 0;
$soma_diferenca += $diferenca;
?>
	
<div class="col-12 col-md-6 col-xl-4">
  <div class="card shadow-sm h-100">
    <div class="card-body">

      <h6 class="fw-bold text-primary mb-1">
        Empenho Nº <?= htmlspecialchars($nmr_empenho) ?>
      </h6>

      <p class="text-muted mb-2">
        <strong>OM:</strong> <?= htmlspecialchars($empenho['nome_batalhao'] ?? '') ?>
      </p>

      <p class="mb-1"><strong>Empresa:</strong> <?= htmlspecialchars($nome_empresa) ?></p>
      <p class="mb-1"><strong>CNPJ:</strong> <?= htmlspecialchars($cnpj_empresa) ?></p>
      <p class="mb-1 text-muted"><small>Requisição SALC #<?= htmlspecialchars((string)$id_requisicao) ?></small></p>

      <div class="my-3">
        <span class="badge bg-success">
          Valor Empenhado: R$ <?= $valorEmpenhado ?>
        </span>
      </div>

      <div class="mb-3 small">
        <p><strong>Total empenhado:</strong> R$ <?= $valorEmpenhado ?></p>

        <p><strong>Solicitado e não entregue:</strong> R$
          <?= number_format((float)$valoresStatus['Não entregue'], 2, ',', '.') ?></p>

        <p><strong>Entregue e não liquidado:</strong> R$
          <?= number_format((float)$valoresStatus['Entregue'], 2, ',', '.') ?></p>

        <p><strong>Valor em capeador:</strong> R$
          <?= number_format((float)$valoresStatus['Capeador'], 2, ',', '.') ?></p>

        <p><strong>Valor liquidado:</strong> R$
          <?= number_format((float)$valoresStatus['Liquidado'], 2, ',', '.') ?></p>

        <p><strong>Valor total utilizado:</strong> R$
          <?= number_format($valorTotalUtilizado, 2, ',', '.') ?></p>
      </div>

      <div class="d-flex flex-wrap gap-2 mb-3">
        <div class="border rounded p-2 flex-fill text-center">
          <strong>SIAFI</strong><br>
          <?php if (!is_null($saldoSiafi)): ?>
            <small class="text-success">R$
              <?= number_format($saldoSiafi, 2, ',', '.') ?></small>
          <?php else: ?>
            <small class="text-danger">Não encontrado</small>
          <?php endif; ?>
        </div>

        <div class="border rounded p-2 flex-fill text-center">
          <strong>Real</strong><br>
          <small class="text-muted">R$
            <?= number_format($SaldoReal, 2, ',', '.') ?></small>
        </div>

        <div class="border rounded p-2 flex-fill text-center">
          <strong>Diferença</strong><br>
          <small class="text-primary">R$
            <?= number_format($diferenca, 2, ',', '.') ?></small>
        </div>
      </div>

      <div class="d-flex justify-content-end gap-2">
        <button class="btn btn-sm btn-outline-primary"
          onclick="verEmpenho(<?= $idEmpenho ?>)"
          data-bs-toggle="modal"
          data-bs-target="#modalVerEmpenho">
          <i class="fas fa-eye me-1"></i> Ver
        </button>

<?php if($pode_editar): ?>
        <button class="btn btn-sm btn-outline-warning"
          onclick="editarEmpenho(<?= $id_requisicao ?>)"
          data-bs-toggle="modal"
          data-bs-target="#modalGerarEmpenho">
          <i class="fas fa-edit"></i>
        </button>
		  <?php endif; ?>
      </div>

    </div>
  </div>
</div>

<?php endforeach; ?>

</div>

<script>
document.getElementById("sum_total_empenhado").innerText     = "<?= number_format($soma_total_empenhado, 2, ',', '.') ?>";
document.getElementById("sum_nao_entregue").innerText        = "<?= number_format($soma_nao_entregue, 2, ',', '.') ?>";
document.getElementById("sum_entregue").innerText            = "<?= number_format($soma_entregue, 2, ',', '.') ?>";
document.getElementById("sum_capeador").innerText            = "<?= number_format($soma_capeador, 2, ',', '.') ?>";
document.getElementById("sum_liquidado").innerText           = "<?= number_format($soma_liquidado, 2, ',', '.') ?>";
document.getElementById("sum_total_utilizado").innerText     = "<?= number_format($soma_total_utilizado, 2, ',', '.') ?>";
document.getElementById("sum_siafi").innerText               = "<?= number_format($soma_saldo_siafi, 2, ',', '.') ?>";
document.getElementById("sum_real").innerText                = "<?= number_format($soma_saldo_real, 2, ',', '.') ?>";
document.getElementById("sum_diferenca").innerText           = "<?= number_format($soma_diferenca, 2, ',', '.') ?>";
</script>

<?php else: ?>

<div class="alert alert-info">Nenhum empenho encontrado.</div>

<?php endif; ?>



<div class="paginacao">
    <?= renderPaginacaoEmpenhos($pagina, $totalPaginas, $limite, $queryString, 'includes/fin_empenhos/listagem.php'); ?>
</div>
    

   
      
  </div>
</div>

<!-- Modal Ver Empenho -->
<div class="modal fade" id="modalVerEmpenho" tabindex="-1" aria-labelledby="modalVerEmpenhoLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content border-0 shadow">

      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title">Detalhes do Empenho</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>

      <div class="modal-body p-4 bg-light">

        <!-- ====================== DADOS DO EMPENHO ====================== -->
        <div class="row g-3 bg-white p-3 rounded shadow-sm mb-4">
          <h5 class="fw-bold text-secondary mb-3">Dados do Empenho</h5>

          <div class="col-md-3">
            <label class="form-label">Número</label>
            <p class="form-control-plaintext" id="ver_emp_nmr_empenho"></p>
          </div>

          <div class="col-md-3">
            <label class="form-label">Data</label>
            <p class="form-control-plaintext" id="ver_emp_data_empenho"></p>
          </div>

          <div class="col-md-3">
            <label class="form-label">Ano</label>
            <p class="form-control-plaintext" id="ver_emp_ano"></p>
          </div>

          <div class="col-md-3">
            <label class="form-label">Categoria</label>
            <p class="form-control-plaintext" id="ver_emp_categoria"></p>
          </div>

          <div class="col-md-3">
            <label class="form-label">Local</label>
            <p class="form-control-plaintext" id="ver_emp_local"></p>
          </div>
        </div>

        <!-- ====================== REQUISIÇÃO ====================== -->
        <div class="row g-3 bg-white p-3 rounded shadow-sm mb-4">
          <h5 class="fw-bold text-secondary mb-3">Requisição Vinculada</h5>

          <div class="col-md-3">
            <label class="form-label">Requisitante</label>
            <p class="form-control-plaintext" id="ver_req_requisitante"></p>
          </div>

          <div class="col-md-3">
            <label class="form-label">Destinatário</label>
            <p class="form-control-plaintext" id="ver_req_destinatario"></p>
          </div>

          <div class="col-md-3">
            <label class="form-label">Nota Crédito</label>
            <p class="form-control-plaintext" id="ver_req_nota_credito"></p>
          </div>

          <div class="col-md-3">
            <label class="form-label">Pregão</label>
            <p class="form-control-plaintext" id="ver_req_id_pregao"></p>
          </div>
        </div>

        <!-- ====================== FORNECEDOR ====================== -->
        <div class="row g-3 bg-white p-3 rounded shadow-sm mb-4">
          <h5 class="fw-bold text-secondary mb-3">Fornecedor</h5>

          <div class="col-md-4">
            <label class="form-label">Empresa</label>
            <p class="form-control-plaintext" id="ver_forn_nome_empresa"></p>
          </div>

          <div class="col-md-3">
            <label class="form-label">CNPJ</label>
            <p class="form-control-plaintext" id="ver_forn_cnpj"></p>
          </div>

          <div class="col-md-3">
            <label class="form-label">Contato</label>
            <p class="form-control-plaintext" id="ver_forn_contato"></p>
          </div>
        </div>
          
          <!-- ====================== TOTAIS DO EMPENHO ====================== -->
<div class="bg-white p-3 rounded shadow-sm mb-4">
  <h5 class="fw-bold text-secondary mb-3">Totais do Empenho</h5>

  <div class="row g-3">
    <div class="col-md-3">
      <small class="text-muted">Total Empenhado</small>
      <p class="fw-bold" id="ver_total_empenhado">0,00</p>
    </div>

    <div class="col-md-3">
      <small class="text-muted">Total Utilizado (OFs)</small>
      <p class="fw-bold" id="ver_total_utilizado">0,00</p>
    </div>

    <div class="col-md-3">
      <small class="text-muted">Total Liquidado</small>
      <p class="fw-bold" id="ver_total_liquidado">0,00</p>
    </div>

    <div class="col-md-3">
      <small class="text-muted">Solicitado não entregue</small>
      <p class="fw-bold text-danger" id="ver_solicitado_nao_entregue">0,00</p>
    </div>
  </div>
</div>

        <!-- ====================== SALDOS ====================== -->
        <div class="bg-white p-3 rounded shadow-sm mb-4">
          <h5 class="fw-bold text-secondary mb-3">Valores e Saldos</h5>

          <div class="row g-3">

            <div class="col-md-3">
              <small class="text-muted">Saldo SIAFI</small>
              <p class="fw-bold" id="ver_siafi"></p>
            </div>

            <div class="col-md-3">
              <small class="text-muted">Saldo Real</small>
              <p class="fw-bold" id="ver_real"></p>
            </div>

            <div class="col-md-3">
              <small class="text-muted">Diferença</small>
              <p class="fw-bold text-danger" id="ver_diferenca"></p>
            </div>

          </div>
        </div>

        <!-- ====================== PEDIDOS ====================== -->
        <div class="bg-white p-3 rounded shadow-sm mb-4">
          <h5 class="fw-bold text-secondary mb-3">Pedidos do Empenho</h5>

          <div class="table-responsive">
            <table class="table table-bordered table-sm table-hover">
              <thead class="table-light">
                <tr>
                  <th>#</th>
                  <th>Solicitante</th>
                  <th>Data</th>
                  <th>Situação</th>
                  <th>Local</th>
                  <th class="text-end">Valor Total</th>
                  <th class="text-end">Desconto</th>
                  <th class="text-end">Valor Final</th>
                </tr>
              </thead>
              <tbody id="ver_pedidos_container"></tbody>

              <!-- Rodapé com total -->
              <tfoot>
                <tr class="table-secondary">
                  <th colspan="7" class="text-end fw-bold">Total dos Pedidos:</th>
                  <th class="text-end fw-bold" id="ver_total_pedidos">0,00</th>
                </tr>
              </tfoot>
            </table>
          </div>
        </div>

        <!-- ====================== ORDENS DE FORNECIMENTO ====================== -->
        <div class="bg-white p-3 rounded shadow-sm mb-4">
          <h5 class="fw-bold text-secondary mb-3">Ordens de Fornecimento</h5>

          <div class="table-responsive">
            <table class="table table-bordered table-sm table-hover">
              <thead class="table-light">
                <tr>
                  <th>#</th>
                  <th>Empresa</th>
                  <th>Data Cadastro</th>
                  <th>Entrega Limite</th>
                  <th>Status</th>
                  <th class="text-end">Valor Total</th>
                </tr>
              </thead>
              <tbody id="ver_of_container"></tbody>

              <tfoot>
                <tr class="table-secondary">
                  <th colspan="5" class="text-end fw-bold">Total das OFs:</th>
                  <th class="text-end fw-bold" id="ver_total_ofs">0,00</th>
                </tr>
              </tfoot>

            </table>
          </div>

        </div>

        <div class="text-end mt-3">
          <button type="button" class="btn btn-danger" data-bs-dismiss="modal">Fechar</button>
        </div>

      </div>
    </div>
  </div>
</div>


<?php if($pode_editar): ?>

<!-- Modal de Gerar Empenho -->
<div class="modal fade" id="modalGerarEmpenho" tabindex="-1" aria-labelledby="modalLabelGerarEmpenho" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header bg-success text-white">
        <h5 class="modal-title" id="modalLabelGerarEmpenho">Gerar Empenho</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Fechar"></button>
      </div>
      <div class="modal-body">
        <form method="POST" enctype="multipart/form-data" id="form-gerar-empenho">
            
            <div class="mb-3">
            <label class="form-label">Data do empenho</label>
            <input type="date" class="form-control" id="gerar-data_empenho" name="data_empenho" required>
          </div>

          <div class="mb-3">
            <label class="form-label">Número do Empenho</label>
            <input type="text" class="form-control" id="gerar-nmr-empenho" name="nmr_empenho" required>
          </div>
            
            <div class="mb-3">
            <label class="form-label">Obra</label>
            <input type="text" class="form-control" id="gerar-obra" name="obra" required>
          </div>
            
             <div class="mb-3">
            <label class="form-label">Ano do empenho</label>
            <input type="text" class="form-control" id="gerar-ano" name="ano" required>
          </div>
            
            
             <div class="mb-3">
            <label class="form-label">Categoria</label>
            <input type="text" class="form-control" id="gerar-categoria" name="categoria" required>
          </div>
            
            
                <div class="mb-3">
    <label for="status" class="form-label">Local</label>
    <select class="form-select rounded-pill shadow-sm" id="gerar-local" name="local" required>
      <option value="Sede">Sede</option>
      <option value="Destacamento 1">Destacamento 1</option>
    </select>
  </div>
            
            <div class="mb-3">
    <label for="status" class="form-label">Resto a pagar?</label>
    <select class="form-select rounded-pill shadow-sm" id="gerar-resto_pagar" name="resto_pagar" required>
      <option value="Sim">Sim</option>
      <option value="Não">Não</option>
    </select>
  </div>
            
            
            
          <input type="hidden" id="gerar-id-requisicao" name="id">

          <button type="submit" class="btn btn-success w-100">
            <i class="fas fa-file-invoice-dollar me-1"></i> SALVAR EMPENHO
          </button>

        </form>
      </div>
    </div>
  </div>
</div>

<!-- Modal Editar Empenho -->
<div class="modal fade" id="modalEditarEmpenho" tabindex="-1" aria-labelledby="modalLabelEditarEmpenho" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">

      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title" id="modalLabelEditarEmpenho">Editar Empenho</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>

      <div class="modal-body">
        <form id="form-editar-empenho" method="POST">

          <!-- ID escondido -->
          <input type="hidden" id="editar-id-requisicao" name="id_requisicao">

          <!-- BATALHÃO -->
          <div class="mb-3">
            <label class="form-label fw-semibold">Batalhão</label>
            <select id="editar-batalhao" name="batalhao" class="form-select">
              <?php foreach ($oms_visiveis as $id => $nome): ?>
                <option value="<?= $id ?>"><?= htmlspecialchars($nome) ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <!-- FORNECEDOR -->
          <div class="mb-3">
            <label class="form-label fw-semibold">Fornecedor</label>
            <select id="editar-fornecedor" name="id_fornecedor" class="form-select" required>
              <option value="">Selecione um fornecedor</option>
              <?php
              $sql_for = $conexao->query("SELECT id, nome_empresa FROM fin_fornecedores ORDER BY nome_empresa ASC");
              while ($for = $sql_for->fetch_assoc()):
              ?>
                <option value="<?= $for['id'] ?>"><?= htmlspecialchars($for['nome_empresa']) ?></option>
              <?php endwhile; ?>
            </select>
          </div>

          <!-- CAMPOS -->
          <div class="row">
            <div class="col-md-6 mb-3">
              <label class="form-label">Requisitante</label>
              <input type="text" id="editar-requisitante" name="requisitante" class="form-control" required>
            </div>

            <div class="col-md-6 mb-3">
              <label class="form-label">Natureza da Despesa</label>
              <input type="text" id="editar-naturezadespesa" name="naturezadespesa" class="form-control" required>
            </div>
          </div>

          <div class="row">
            <div class="col-md-6 mb-3">
              <label class="form-label">Item OOG</label>
              <input type="text" id="editar-item_oog" name="item_oog" class="form-control" required>
            </div>

            <div class="col-md-6 mb-3">
              <label class="form-label">Finalidade</label>
              <input type="text" id="editar-finalidade" name="finalidade" class="form-control" required>
            </div>
          </div>

          <div class="row">
            <div class="col-md-6 mb-3">
              <label class="form-label">Destinatário</label>
              <input type="text" id="editar-destinatario" name="destinatario" class="form-control" required>
            </div>

            <div class="col-md-6 mb-3">
              <label class="form-label">Nota Crédito</label>
              <input type="text" id="editar-nota_credito" name="nota_credito" class="form-control" required>
            </div>
          </div>

          <div class="row">
            <div class="col-md-6 mb-3">
              <label class="form-label">Plano Interno</label>
              <input type="text" id="editar-plano_interno" name="plano_interno" class="form-control" required>
            </div>

            <div class="col-md-6 mb-3">
              <label class="form-label">Necessidade de Contrato?</label>
              <select id="editar-necessidade_contrato" name="necessidade_contrato" class="form-select">
                <option value="Sim">Sim</option>
                <option value="Não">Não</option>
              </select>
            </div>
          </div>

          <div class="row">
            <div class="col-md-6 mb-3">
              <label class="form-label">Tipo de Empenho</label>
              <select id="editar-tipo_empenho" name="tipo_empenho" class="form-select">
                <option value="Global">Global</option>
                <option value="Ordinário">Ordinário</option>
              </select>
            </div>

            <div class="col-md-6 mb-3">
              <label class="form-label">Status</label>
              <select id="editar-status_requisicao" name="status_requisicao" class="form-select">
                <option value="Em confecção">Em confecção</option>
                <option value="Entregue na S4">Entregue na S4</option>
                <option value="Entregue na Fisc Adm">Entregue na Fisc Adm</option>
                <option value="Entregue na SALC">Entregue na SALC</option>
                <option value="Empenho gerado">Empenho gerado</option>
              </select>
            </div>
          </div>

          <hr>

          <div class="row">
            <div class="col-md-4 mb-3">
              <label class="form-label">Data do Empenho</label>
              <input type="date" id="editar-data_empenho" name="data_empenho" class="form-control" required>
            </div>

            <div class="col-md-4 mb-3">
              <label class="form-label">Número do Empenho</label>
              <input type="text" id="editar-nmr_empenho" name="nmr_empenho" class="form-control" required>
            </div>

            <div class="col-md-4 mb-3">
              <label class="form-label">Ano</label>
              <input type="text" id="editar-ano" name="ano" class="form-control" required>
            </div>
          </div>

          <div class="mb-3">
            <label class="form-label">Obra</label>
            <input type="text" id="editar-obra" name="obra" class="form-control" required>
          </div>

          <div class="mb-3">
            <label class="form-label">Categoria</label>
            <input type="text" id="editar-categoria" name="categoria" class="form-control" required>
          </div>

          <div class="row">
            <div class="col-md-6 mb-3">
              <label class="form-label">Local</label>
              <select id="editar-local" name="local" class="form-select">
                <option value="Sede">Sede</option>
                <option value="Destacamento 1">Destacamento 1</option>
              </select>
            </div>

            <div class="col-md-6 mb-3">
              <label class="form-label">Resto a pagar?</label>
              <select id="editar-resto_pagar" name="resto_pagar" class="form-select">
                <option value="Sim">Sim</option>
                <option value="Não">Não</option>
              </select>
            </div>
          </div>

          <!-- NOVO CAMPO -->
          <div class="mb-3">
            <label class="form-label">Valor Total Empenhado</label>
            <input type="text" id="editar-valor_empenhado" name="valor_empenhado" class="form-control" required>
          </div>

          <button type="submit" class="btn btn-primary w-100">
            <i class="fas fa-save me-1"></i> Atualizar Empenho
          </button>

        </form>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>
<?php if($pode_importar): ?>
<!-- Modal de Importação de Empenhos -->
<div class="modal fade" id="modalImportarEmpenhos" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form id="formImportarEmpenho" method="POST" enctype="multipart/form-data">
        <div class="modal-header">
          <h5 class="modal-title">Importar Planilha de Empenhos</h5>
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
          <label class="form-label">Selecione o Batalhão dos Empenhos</label>
          <select name="batalhao" id="batalhao_empenhos" class="form-select mb-3" required>
            <option value="">Selecione...</option>
            <?php while ($bat = $resBatalhoes->fetch_assoc()): ?>
              <option value="<?= $bat['id'] ?>">
                <?= htmlspecialchars($bat['abreviatura'] . ' - ' . $bat['nome']) ?>
              </option>
            <?php endwhile; ?>
          </select>

          <!-- UPLOAD DO EXCEL -->
          <label class="form-label">Selecione a planilha (.xlsx)</label>
          <input type="file" name="arquivo" id="arquivo_empenhos" accept=".xlsx" class="form-control mb-3" required>

          <!-- LINK PARA PLANILHA MODELO -->
          <a href="includes/fin_empenhos/planilha_modelo_empenhos.xlsx" class="btn btn-link p-0">
            📥 Baixar modelo de planilha
          </a>

        </div>

        <div class="modal-footer">
	  <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
          <button type="submit" class="btn btn-primary">Importar</button>
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
        </div>

      </form>
    </div>
  </div>
</div>
<?php endif; ?>


<script>
    window.funcaoInicializacao = 'inicializarEmpenho'; 
    
</script>