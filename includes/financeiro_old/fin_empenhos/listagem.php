<?php
header('Content-Type: text/html; charset=utf-8');
session_start();
include_once('../../conexao/config.php');

// ======================================================================
// DADOS DO USUÁRIO
// ======================================================================
$usuario          = $_SESSION['usuario'] ?? [];
$nivel_usuario    = $usuario['nivel'] ?? 3;
$batalhao_usuario = $usuario['batalhao'] ?? null;

if (!$batalhao_usuario) {
    die("Erro: não foi possível identificar o batalhão do usuário.");
}

// ======================================================================
// FILTROS DO USUÁRIO
// ======================================================================
$id               = $_GET['id'] ?? '';
$id_requisicao    = $_GET['id_requisicao'] ?? '';
$requisitante     = $_GET['requisitante'] ?? '';
$destinatario     = $_GET['destinatario'] ?? '';
$status_requisicao = $_GET['status_requisicao'] ?? '';
$filtro_batalhao  = $_GET['batalhao'] ?? '';

$filtros = [];
$params  = [];
$tipos   = "";

// ID do empenho
if (!empty($id)) {
    $filtros[] = "e.id = ?";
    $params[]  = $id;
    $tipos    .= "i";
}

// ID requisição vinculada
if (!empty($id_requisicao)) {
    $filtros[] = "r.id = ?";
    $params[]  = $id_requisicao;
    $tipos    .= "i";
}

// Campos LIKE
if (!empty($requisitante)) {
    $filtros[] = "r.requisitante LIKE ?";
    $params[]  = "%{$requisitante}%";
    $tipos    .= "s";
}

if (!empty($destinatario)) {
    $filtros[] = "r.destinatario LIKE ?";
    $params[]  = "%{$destinatario}%";
    $tipos    .= "s";
}

// Status
if (!empty($status_requisicao)) {
    $filtros[] = "r.status_requisicao = ?";
    $params[]  = $status_requisicao;
    $tipos    .= "s";
}

// ======================================================================
// CONTROLE DE ACESSO POR NÍVEL (FILTRA POR r.batalhao)
// ======================================================================

if ($nivel_usuario == 1) {

    // Admin vê tudo, mas pode escolher um batalhão
    if (!empty($filtro_batalhao)) {
        $filtros[] = "r.batalhao = ?";
        $params[]  = (int)$filtro_batalhao;
        $tipos    .= "i";
    }

} elseif ($nivel_usuario == 2) {

    // N2 vê seu batalhão + todos subordinados
    $sqlSubs = "SELECT id_om_menor FROM organizacoes_militares_sub WHERE id_om_maior = ?";
    $stmtSubs = $conexao->prepare($sqlSubs);
    $stmtSubs->bind_param("i", $batalhao_usuario);
    $stmtSubs->execute();
    $resSubs = $stmtSubs->get_result();

    $batalhoesPermitidos = [$batalhao_usuario];
    while ($row = $resSubs->fetch_assoc()) {
        $batalhoesPermitidos[] = $row['id_om_menor'];
    }

    $stmtSubs->close();

    if (!empty($filtro_batalhao)) {

        if (!in_array($filtro_batalhao, $batalhoesPermitidos)) {
            die("Acesso negado ao batalhão selecionado.");
        }

        $filtros[] = "r.batalhao = ?";
        $params[]  = (int)$filtro_batalhao;
        $tipos    .= "i";

    } else {

        // monta IN dinâmico
        $placeholder = implode(",", array_fill(0, count($batalhoesPermitidos), "?"));
        $filtros[]   = "r.batalhao IN ($placeholder)";

        foreach ($batalhoesPermitidos as $b) {
            $params[] = $b;
            $tipos   .= "i";
        }
    }

} else {

    // N3 vê apenas seu batalhão
    $filtros[] = "r.batalhao = ?";
    $params[]  = $batalhao_usuario;
    $tipos    .= "i";
}

// ======================================================================
// WHERE FINAL
// ======================================================================
$condicoes = !empty($filtros) ? "WHERE " . implode(" AND ", $filtros) : "";


// ======================================================================
// PAGINAÇÃO
// ======================================================================
$limite = isset($_GET['limite']) && is_numeric($_GET['limite']) ? (int)$_GET['limite'] : 10;
$pagina = isset($_GET['pagina']) && is_numeric($_GET['pagina']) && $_GET['pagina'] > 0
    ? (int)$_GET['pagina'] : 1;

$offset = ($pagina - 1) * $limite;


// ======================================================================
// CONTAGEM TOTAL
// ======================================================================
$sqlTotal = "
    SELECT COUNT(*) AS total
    FROM fin_empenhos e
    INNER JOIN fin_requisicao r ON e.id_requisicao = r.id
    $condicoes
";

$stmtTotal = $conexao->prepare($sqlTotal);
if (!empty($params)) {
    $stmtTotal->bind_param($tipos, ...$params);
}
$stmtTotal->execute();
$totalRegistros = $stmtTotal->get_result()->fetch_assoc()['total'];
$totalPaginas = ceil($totalRegistros / $limite);


// ======================================================================
// BUSCA PRINCIPAL
// ======================================================================
$sql = "
    SELECT 
        e.*, 
        r.requisitante, 
        r.destinatario, 
        r.status_requisicao, 
        r.nota_credito,
        r.id_fornecedor,
        r.batalhao,
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
$empenhos = $stmt->get_result();


// ======================================================================
// PAGINAÇÃO
// ======================================================================
function renderPaginacaoEmpenhos($pagina, $totalPaginas, $limite, $queryString, $arquivo = 'includes/fin_empenhos/listagem.php') {
    $html = '<div class="pagination-wrapper">';
    $html .= '<nav><ul class="pagination pagination-sm">';

    for ($i = 1; $i <= $totalPaginas; $i++) {
        $ativo = $i == $pagina ? "active" : "";
        $url = "{$arquivo}?{$queryString}&pagina=$i&limite=$limite";

        $html .= "<li class='page-item $ativo'>
                    <a class='page-link paginacao-empenho' href='#' data-page='{$url}'>$i</a>
                  </li>";
    }

    $html .= '</ul></nav></div>';
    return $html;
}

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
            <label class="form-label fw-semibold">ID Empenho</label>
            <input type="text" class="form-control" name="id" value="<?= htmlspecialchars($_GET['id'] ?? '') ?>">
          </div>

          <div class="col-md-2">
            <label class="form-label fw-semibold">Número da Requisição</label>
            <input type="text" class="form-control" name="id_requisicao" value="<?= htmlspecialchars($_GET['id_requisicao'] ?? '') ?>">
          </div>

          <div class="col-md-3">
            <label class="form-label fw-semibold">Requisitante</label>
            <input type="text" class="form-control" name="requisitante" value="<?= htmlspecialchars($_GET['requisitante'] ?? '') ?>">
          </div>

          <div class="col-md-3">
            <label class="form-label fw-semibold">Destinatário</label>
            <input type="text" class="form-control" name="destinatario" value="<?= htmlspecialchars($_GET['destinatario'] ?? '') ?>">
          </div>

          <div class="col-md-2">
            <label class="form-label fw-semibold">Status Requisição</label>
            <input type="text" class="form-control" name="status_requisicao" value="<?= htmlspecialchars($_GET['status_requisicao'] ?? '') ?>">
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
$soma_total_empenhado = 0;
$soma_nao_entregue = 0;
$soma_entregue = 0;
$soma_capeador = 0;
$soma_liquidado = 0;
$soma_total_utilizado = 0;
$soma_saldo_siafi = 0;
$soma_saldo_real = 0;
$soma_diferenca = 0;
?>

<?php if ($empenhos->num_rows > 0): ?>

<!-- =============================== -->
<!--   RESUMO DOS VALORES - EM CARDS -->
<!-- =============================== -->

<div class="card border-0 shadow-sm mb-4">
  <div class="card-body">
    <h5 class="fw-bold mb-3">Resumo dos Valores dos Empenhos Exibidos</h5>

    <div class="row g-3">

      <!-- Total Empenhado -->
      <div class="col-6 col-md-4 col-lg-3 col-xl-2">
        <div class="card text-center shadow-sm h-100">
          <div class="card-body p-2">
            <small class="text-muted">Total Empenhado</small>
            <h6 id="sum_total_empenhado" class="fw-bold mt-1">0,00</h6>
          </div>
        </div>
      </div>

      <!-- Solicitado não entregue -->
      <div class="col-6 col-md-4 col-lg-3 col-xl-2">
        <div class="card text-center shadow-sm h-100">
          <div class="card-body p-2">
            <small class="text-muted">Sol. não entregue</small>
            <h6 id="sum_nao_entregue" class="fw-bold mt-1">0,00</h6>
          </div>
        </div>
      </div>

      <!-- Entregue não liquidado -->
      <div class="col-6 col-md-4 col-lg-3 col-xl-2">
        <div class="card text-center shadow-sm h-100">
          <div class="card-body p-2">
            <small class="text-muted">Entregue não liq.</small>
            <h6 id="sum_entregue" class="fw-bold mt-1">0,00</h6>
          </div>
        </div>
      </div>

      <!-- Em Capeador -->
      <div class="col-6 col-md-4 col-lg-3 col-xl-2">
        <div class="card text-center shadow-sm h-100">
          <div class="card-body p-2">
            <small class="text-muted">Em Capeador</small>
            <h6 id="sum_capeador" class="fw-bold mt-1">0,00</h6>
          </div>
        </div>
      </div>

      <!-- Liquidado -->
      <div class="col-6 col-md-4 col-lg-3 col-xl-2">
        <div class="card text-center shadow-sm h-100">
          <div class="card-body p-2">
            <small class="text-muted">Liquidado</small>
            <h6 id="sum_liquidado" class="fw-bold mt-1">0,00</h6>
          </div>
        </div>
      </div>

      <!-- Total Utilizado -->
      <div class="col-6 col-md-4 col-lg-3 col-xl-2">
        <div class="card text-center shadow-sm h-100">
          <div class="card-body p-2">
            <small class="text-muted">Total Utilizado</small>
            <h6 id="sum_total_utilizado" class="fw-bold mt-1">0,00</h6>
          </div>
        </div>
      </div>

      <!-- Saldo SIAFI -->
      <div class="col-6 col-md-4 col-lg-3 col-xl-2">
        <div class="card text-center shadow-sm h-100">
          <div class="card-body p-2">
            <small class="text-muted">Saldo SIAFI</small>
            <h6 id="sum_siafi" class="fw-bold mt-1">0,00</h6>
          </div>
        </div>
      </div>

      <!-- Saldo Real -->
      <div class="col-6 col-md-4 col-lg-3 col-xl-2">
        <div class="card text-center shadow-sm h-100">
          <div class="card-body p-2">
            <small class="text-muted">Saldo Real</small>
            <h6 id="sum_real" class="fw-bold mt-1">0,00</h6>
          </div>
        </div>
      </div>

      <!-- Diferença -->
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

<?php while ($empenho = $empenhos->fetch_assoc()): ?>

<?php
$id_requisicao = $empenho['id_requisicao'];

/* ====================== TOTAL EMPENHADO ====================== */
$stmtValor = $conexao->prepare("
    SELECT SUM(ri.quant_saida_item * pi.valor_unt) AS total_empenhado
    FROM fin_requisicao_itens ri
    JOIN fin_pregao_itens pi ON ri.id_item = pi.id
    WHERE ri.id_requisicao = ?
");
$stmtValor->bind_param("i", $id_requisicao);
$stmtValor->execute();
$res = $stmtValor->get_result();
$dadosValor = $res->fetch_assoc();
$valorEmpenhado2 = floatval($dadosValor['total_empenhado'] ?? 0);
$valorEmpenhado = number_format($valorEmpenhado2, 2, ',', '.');
$stmtValor->close();

/* SOMA GERAL */
$soma_total_empenhado += $valorEmpenhado2;

/* ====================== FORNECEDOR ====================== */
$stmtF = $conexao->prepare("
    SELECT nome_empresa, cnpj_empresa
    FROM fin_fornecedores
    WHERE id = ?
");
$stmtF->bind_param("i", $empenho['id_fornecedor']);
$stmtF->execute();
$forn = $stmtF->get_result()->fetch_assoc();
$stmtF->close();

$nome_empresa = $forn['nome_empresa'] ?? '';
$cnpj_empresa = $forn['cnpj_empresa'] ?? '';

/* ====================== VALORES POR STATUS ====================== */
$valoresStatus = [
  'Não entregue' => 0,
  'Entregue' => 0,
  'Capeador' => 0,
  'Liquidado' => 0
];

$stmtStatus = $conexao->prepare("
  SELECT o.status, SUM(pf.valor_total) AS total
  FROM fin_ordemforn_pedidos op
  JOIN fin_ordemforn o ON o.id = op.id_ordemforn
  JOIN fin_pedidos_forn_itens pf ON pf.id_principal = op.id_pedido
  WHERE o.id_empenho = ?
  GROUP BY o.status
");
$stmtStatus->bind_param("i", $empenho['id']);
$stmtStatus->execute();
$resStatus = $stmtStatus->get_result();

while ($row = $resStatus->fetch_assoc()) {
  $status = strtolower($row['status']);
  $valor = floatval($row['total']);

  if ($status === 'não entregue') $valoresStatus['Não entregue'] += $valor;
  elseif ($status === 'entregue') $valoresStatus['Entregue'] += $valor;
  elseif ($status === 'capeador') $valoresStatus['Capeador'] += $valor;
  elseif ($status === 'liquidado' || $status === 'pago') $valoresStatus['Liquidado'] += $valor;
}

$stmtStatus->close();

/* SOMAS GERAIS */
$soma_nao_entregue += $valoresStatus['Não entregue'];
$soma_entregue += $valoresStatus['Entregue'];
$soma_capeador += $valoresStatus['Capeador'];
$soma_liquidado += $valoresStatus['Liquidado'];

$valorTotalUtilizado = array_sum($valoresStatus);
$soma_total_utilizado += $valorTotalUtilizado;

/* ====================== SALDO REAL ====================== */
$SaldoReal = $valorEmpenhado2 - $valorTotalUtilizado;
$soma_saldo_real += $SaldoReal;

/* ====================== SIAFI ====================== */
$nmr_empenho = $empenho['nmr_empenho'];
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
  $saldoSiafi = floatval(str_replace(',', '.', str_replace('.', '', $linhaSaldo['saldo_empenho'])));
  $soma_saldo_siafi += $saldoSiafi;
}

/* ====================== DIFERENÇA ====================== */
$diferenca = (!is_null($saldoSiafi)) ? ($saldoSiafi - $SaldoReal) : 0;
$soma_diferenca += $diferenca;
?>

<!-- ====================== CARD DO EMPENHO ====================== -->
<div class="col-12 col-md-6 col-xl-4">
  <div class="card shadow-sm h-100">
    <div class="card-body">

      <h6 class="fw-bold text-primary mb-1">
        Empenho Nº <?= htmlspecialchars($empenho['nmr_empenho']) ?>
      </h6>

      <p class="text-muted mb-2">
        <strong>OM:</strong> <?= htmlspecialchars($empenho['nome_batalhao']) ?>
      </p>

      <p class="mb-1"><strong>Empresa:</strong> <?= htmlspecialchars($nome_empresa) ?></p>
      <p class="mb-1"><strong>CNPJ:</strong> <?= htmlspecialchars($cnpj_empresa) ?></p>
      <p class="mb-1 text-muted"><small>Requisição SALC #<?= htmlspecialchars($id_requisicao) ?></small></p>

      <div class="my-3">
        <span class="badge bg-success">
          Valor Empenhado: R$ <?= $valorEmpenhado ?>
        </span>
      </div>

      <div class="mb-3 small">
        <p><strong>Total empenhado:</strong> R$ <?= $valorEmpenhado ?></p>

        <p><strong>Solicitado e não entregue:</strong> R$
          <?= number_format($valoresStatus['Não entregue'], 2, ',', '.') ?></p>

        <p><strong>Entregue e não liquidado:</strong> R$
          <?= number_format($valoresStatus['Entregue'], 2, ',', '.') ?></p>

        <p><strong>Valor em capeador:</strong> R$
          <?= number_format($valoresStatus['Capeador'], 2, ',', '.') ?></p>

        <p><strong>Valor liquidado:</strong> R$
          <?= number_format($valoresStatus['Liquidado'], 2, ',', '.') ?></p>

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
        onclick="verEmpenho(<?= $empenho['id'] ?>)"
        data-bs-toggle="modal"
        data-bs-target="#modalVerEmpenho">
  <i class="fas fa-eye me-1"></i> Ver
</button>
        <button class="btn btn-sm btn-outline-warning"
                onclick="editarEmpenho(<?= $id_requisicao ?>)"
                data-bs-toggle="modal"
                data-bs-target="#modalGerarEmpenho">
          <i class="fas fa-edit"></i>
        </button>
      </div>

    </div>
  </div>
</div>

<?php endwhile; ?>

</div>

<!-- ATUALIZA A TABELA DE SOMAS -->
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
                </tr>
              </thead>
              <tbody id="ver_pedidos_container"></tbody>

              <!-- Rodapé com total -->
              <tfoot>
                <tr class="table-secondary">
                  <th colspan="5" class="text-end fw-bold">Total dos Pedidos:</th>
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





<script>
    window.funcaoInicializacao = 'inicializarEmpenho'; 
    
</script>