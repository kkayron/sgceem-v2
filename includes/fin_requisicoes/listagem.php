<?php
header('Content-Type: text/html; charset=utf-8');
session_start();
include_once('../../conexao/config.php');

// ============================================================================
// DADOS DO USUÁRIO
// ============================================================================
$usuario          = $_SESSION['usuario'] ?? [];
$nivel_usuario    = $usuario['nivel'] ?? 3;
$batalhao_usuario = $usuario['batalhao'] ?? null;

if (!$batalhao_usuario) {
    die("Erro: Não foi possível identificar o batalhão do usuário.");
}

// ============================================================================
// RECEBIMENTO DOS FILTROS
// ============================================================================
$id             = $_GET['id'] ?? '';
$requisitante   = $_GET['requisitante'] ?? '';
$destinatario   = $_GET['destinatario'] ?? '';
$nota_credito   = $_GET['nota_credito'] ?? '';
$plano_interno  = $_GET['plano_interno'] ?? '';
$data_ini       = $_GET['data_ini'] ?? '';
$data_fim       = $_GET['data_fim'] ?? '';
$filtro_batalhao = $_GET['batalhao'] ?? "";
$nmr_empenho = $_GET['nmr_empenho'] ?? '';

// ============================================================================
// FILTROS DINÂMICOS
// ============================================================================
$filtros = [];
$params  = [];
$tipos   = "";

// Campos padrão (LIKE)
$campos = [
    'id',
    'requisitante',
    'destinatario',
    'nota_credito',
    'plano_interno',
    'nmr_empenho'
];

foreach ($campos as $campo) {
    if (!empty($_GET[$campo])) {
        $filtros[] = "$campo LIKE ?";
        $params[]  = "%{$_GET[$campo]}%";
        $tipos    .= "s";
    }
}

// Filtro por datas
if (!empty($data_ini)) {
    $filtros[] = "data_requisicao >= ?";
    $params[] = $data_ini;
    $tipos .= "s";
}
if (!empty($data_fim)) {
    $filtros[] = "data_requisicao <= ?";
    $params[] = $data_fim;
    $tipos .= "s";
}

// ============================================================================
// CONTROLE DE ACESSO POR NÍVEL
// ============================================================================
if ($nivel_usuario == 1) {

    // Admin vê tudo, mas pode escolher um batalhão
    if (!empty($filtro_batalhao)) {
        $filtros[] = "fr.batalhao = ?";
        $params[]  = (int)$filtro_batalhao;
        $tipos    .= "i";
    }

} elseif ($nivel_usuario == 2) {

    // N2 vê o próprio + subordinados
    $sqlSubs = "SELECT id_om_menor FROM organizacoes_militares_sub WHERE id_om_maior = ?";
    $stmtSubs = $conexao->prepare($sqlSubs);
    $stmtSubs->bind_param("i", $batalhao_usuario);
    $stmtSubs->execute();
    $resultSubs = $stmtSubs->get_result();

    $batalhoesPermitidos = [$batalhao_usuario];
    while ($row = $resultSubs->fetch_assoc()) {
        $batalhoesPermitidos[] = $row['id_om_menor'];
    }

    if (!empty($filtro_batalhao)) {

        if (!in_array($filtro_batalhao, $batalhoesPermitidos)) {
            die("Acesso negado ao batalhão selecionado.");
        }

        $filtros[] = "fr.batalhao = ?";
        $params[]  = (int)$filtro_batalhao;
        $tipos    .= "i";

    } else {
        // Gera IN dinâmico
        $placeholder = implode(",", array_fill(0, count($batalhoesPermitidos), "?"));
        $filtros[] = "fr.batalhao IN ($placeholder)";
        foreach ($batalhoesPermitidos as $b) {
            $params[] = $b;
            $tipos   .= "i";
        }
    }

} else {

    // N3 vê apenas seu batalhão
    $filtros[] = "fr.batalhao = ?";
    $params[] = $batalhao_usuario;
    $tipos   .= "i";
}

// WHERE FINAL
$condicoes = !empty($filtros) ? "WHERE " . implode(" AND ", $filtros) : "";

// ============================================================================
// PAGINAÇÃO
// ============================================================================
$limite = isset($_GET['limite']) && is_numeric($_GET['limite']) ? (int)$_GET['limite'] : 10;
$pagina = isset($_GET['pagina']) && is_numeric($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
$offset = ($pagina - 1) * $limite;

// ============================================================================
// CONTAGEM TOTAL
// ============================================================================
$sqlTotal = "SELECT COUNT(*) AS total FROM fin_requisicao fr $condicoes";

$stmtTotal = $conexao->prepare($sqlTotal);
if (!empty($params)) {
    $stmtTotal->bind_param($tipos, ...$params);
}
$stmtTotal->execute();
$totalRegistros = $stmtTotal->get_result()->fetch_assoc()['total'];
$totalPaginas = ceil($totalRegistros / $limite);

// ============================================================================
// BUSCA PRINCIPAL (COM NOME DO BATALHÃO)
// ============================================================================
$sql = "
    SELECT 
        fr.*,
        om.abreviatura AS batalhao_nome
    FROM fin_requisicao fr
    JOIN organizacoes_militares om ON om.id = fr.batalhao
    $condicoes
    ORDER BY fr.id DESC
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
$requisicoes = $stmt->get_result();

// ============================================================================
// PAGINAÇÃO
// ============================================================================
function renderPaginacaoRequisicao($pagina, $totalPaginas, $limite, $queryString, $arquivo = 'includes/requisicao/listagem.php') {
    $html = '<div class="pagination-wrapper">';
    $html .= '<nav><ul class="pagination pagination-sm">';

    for ($i = 1; $i <= $totalPaginas; $i++) {
        $ativo = $i == $pagina ? "active" : "";
        $url = "{$arquivo}?{$queryString}&pagina=$i&limite=$limite";

        $html .= "<li class='page-item $ativo'>
                    <a class='page-link paginacao-requisicao' href='#' data-page='{$url}'>$i</a>
                  </li>";
    }

    $html .= '</ul></nav></div>';
    return $html;
}

// Mantém os GET
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
        <h3 class="fw-bold mb-1">Listagem das Requisições</h3>
        <h6 class="text-muted">Listagem dos pregões realizados ou em andamento.</h6>
      </div>
      <div>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalCadastrarEmpenho">
          <i class="fa fa-plus me-1"></i> Cadastrar Empenho
        </button>
          <!-- Botão Excel -->
<button id="btnExportarExcelRequisicao" class="btn btn-success">
  <i class="fas fa-file-excel"></i> Exportar Excel
</button>
      </div>
    </div>

    <div class="mb-3">
  <button class="btn btn-outline-primary w-100 d-flex justify-content-center align-items-center" onclick="toggleFiltrosRequisicao()">
    <i class="fas fa-search me-2"></i> Filtros
  </button>
</div>

<div id="filtros-container-requisicao" style="display: none;" class="mb-3">
  <div class="card shadow-sm border-0">
    <div class="card-body">
      <form method="GET" id="filtroRequisicaoForm">
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
    <label class="form-label fw-semibold">Nº Empenho</label>
    <input type="text" class="form-control" name="nmr_empenho"
           value="<?= htmlspecialchars($_GET['nmr_empenho'] ?? '') ?>">
</div>
          <div class="col-md-2">
            <label class="form-label fw-semibold">Nº Requisição</label>
            <input type="text" class="form-control" name="id" value="<?= htmlspecialchars($_GET['id'] ?? '') ?>">
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
            <label class="form-label fw-semibold">Nota Crédito</label>
            <input type="text" class="form-control" name="nota_credito" value="<?= htmlspecialchars($_GET['nota_credito'] ?? '') ?>">
          </div>
          <div class="col-md-2">
            <label class="form-label fw-semibold">Plano Interno</label>
            <input type="text" class="form-control" name="plano_interno" value="<?= htmlspecialchars($_GET['plano_interno'] ?? '') ?>">
          </div>
          <div class="col-md-3">
            <label class="form-label fw-semibold">Data Inicial</label>
            <input type="date" class="form-control" name="data_ini" value="<?= htmlspecialchars($_GET['data_ini'] ?? '') ?>">
          </div>
          <div class="col-md-3">
            <label class="form-label fw-semibold">Data Final</label>
            <input type="date" class="form-control" name="data_fim" value="<?= htmlspecialchars($_GET['data_fim'] ?? '') ?>">
          </div>
          <div class="col-12 d-flex justify-content-between mt-2">
            <button type="button" id="btnLimparFiltrosRequisicao" class="btn btn-black ms-2">Limpar Filtros</button>
            <button type="submit" class="btn btn-primary px-4">Aplicar</button>
          </div>
        </div>
      </form>
    </div>
  </div>
</div>

<div class="mb-3">
  <label for="limiteRequisicao" class="me-2 mb-0">Mostrar</label>
  <select id="limiteRequisicao" name="limite" class="form-select d-inline w-auto" onchange="atualizarLimiteRequisicao()">
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
  <?= renderPaginacaoRequisicao($pagina, $totalPaginas, $limite, $queryString, 'includes/fin_requisicoes/listagem.php'); ?>
</div>

<div class="card border-0 shadow-sm">
  <div class="card-body">
    <div class="list-group">
      <?php if ($requisicoes->num_rows > 0): ?>
        <?php while ($requisicao = $requisicoes->fetch_assoc()): ?>

          <?php
          // Conta itens cadastrados na requisição
          $stmt = $conexao->prepare("
              SELECT COUNT(*) AS total_itens 
              FROM fin_requisicao_itens 
              WHERE id_requisicao = ?
          ");
          $stmt->bind_param("i", $requisicao['id']);
          $stmt->execute();
          $total_itens = $stmt->get_result()->fetch_assoc()['total_itens'] ?? 0;
          $stmt->close();
          ?>

          <!-- CARD MODERNO -->
          <div class="list-group-item list-group-item-action flex-column mb-3 p-4 border rounded-4 shadow-sm bg-white">

            <!-- Cabeçalho -->
            <div class="d-flex w-100 justify-content-between align-items-center mb-2">
              <h5 class="mb-0 fw-bold text-primary">
                Requisição #<?= $requisicao['id'] ?>
              </h5>

              <!-- Nome do Batalhão -->
              <span class="badge bg-dark text-light px-3 py-2 rounded-pill shadow-sm">
                <?= htmlspecialchars($requisicao['batalhao_nome']) ?>
              </span>

              <button class="btn btn-sm btn-light border"
                      type="button"
                      data-bs-toggle="collapse"
                      data-bs-target="#itensReq<?= $requisicao['id'] ?>">
                <i class="fas fa-chevron-down"></i>
              </button>
            </div>

            <!-- Informações da requisição -->
            <div class="text-secondary small">

              <div class="row g-2">
                <div class="col-md-6">
                  <strong>Requisitante:</strong>
                  <?= htmlspecialchars($requisicao['requisitante']) ?>
                </div>

                <div class="col-md-6">
                  <strong>Destinatário:</strong>
                  <?= htmlspecialchars($requisicao['destinatario']) ?>
                </div>

                <div class="col-md-6">
                  <strong>Nota de Crédito:</strong>
                  <?= htmlspecialchars($requisicao['nota_credito']) ?>
                </div>

                <div class="col-md-6">
                  <strong>Plano Interno:</strong>
                  <?= htmlspecialchars($requisicao['plano_interno']) ?>
                </div>
                  
                <div class="col-md-12">
                  <strong>Finalidade:</strong>
                  <?= htmlspecialchars($requisicao['finalidade']) ?>
                </div>
              </div>
            </div>

            <!-- Total de itens -->
            <div class="alert alert-secondary mt-3 py-2 px-3 rounded-pill fw-bold text-center shadow-sm">
              Itens cadastrados: <?= $total_itens ?>
            </div>

            <!-- Empenho -->
            <?php if ($requisicao['empenho_gerado'] === 'sim'): ?>
              <div class="alert alert-success py-2 px-3 rounded-pill fw-bold text-center shadow-sm">
                Empenho: <?= htmlspecialchars($requisicao['nmr_empenho']) ?>
              </div>
            <?php else: ?>
              <div class="alert alert-danger py-2 px-3 rounded-pill fw-bold text-center shadow-sm">
                Empenho não gerado
              </div>
            <?php endif; ?>

            <!-- Status -->
            <div class="alert alert-primary py-2 px-3 rounded-pill fw-bold text-center shadow-sm">
              <?= htmlspecialchars($requisicao['status_requisicao']) ?>
            </div>

            <!-- Botões -->
            <div class="d-flex gap-2 mt-3 flex-wrap justify-content-end">

              <button class="btn btn-sm btn-outline-primary"
                      onclick="verRequisicao(<?= $requisicao['id'] ?>)"
                      data-bs-toggle="modal"
                      data-bs-target="#modalVerRequisicao">
                <i class="fas fa-eye me-1"></i> Ver
              </button>
                <div class="dropdown">
  <button class="btn btn-sm btn-outline-secondary dropdown-toggle" 
          type="button" 
          id="dropdownMenu<?= $requisicao['id'] ?>" 
          data-bs-toggle="dropdown" 
          aria-expanded="false">
    <i class="fas fa-print me-1"></i> Imprimir
  </button>
  
  <ul class="dropdown-menu dropdown-menu-end shadow-sm rounded" aria-labelledby="dropdownMenu<?= $requisicao['id'] ?>">
 
          <li>
     <a href="#" 
   class="dropdown-item text-danger btnExportarReq" 
   data-id="<?= $requisicao['id'] ?>">
    <i class="fas fa-file-pdf me-2"></i> Imprimir Requisição
</a>
    </li>
           
  </ul>
</div>

              <?php if ($requisicao['empenho_gerado'] !== 'sim'): ?>

                <button class="btn btn-sm btn-outline-warning"
                        onclick="editarRequisicao(<?= $requisicao['id'] ?>)"
                        data-bs-toggle="modal"
                        data-bs-target="#modalEditarRequisicao">
                  <i class="fas fa-edit me-1"></i> Editar
                </button>

                <button class="btn btn-sm btn-outline-success"
                        onclick="editarEmpenho(<?= $requisicao['id'] ?>)"
                        data-bs-toggle="modal"
                        data-bs-target="#modalGerarEmpenho">
                  <i class="fas fa-file-invoice-dollar me-1"></i> Gerar Empenho
                </button>

              <?php else: ?>

                <button class="btn btn-sm btn-outline-success"
                        onclick="editarEmpenho(<?= $requisicao['id'] ?>)"
                        data-bs-toggle="modal"
                        data-bs-target="#modalGerarEmpenho">
                  <i class="fas fa-file-invoice-dollar me-1"></i> Editar Empenho
                </button>

              <?php endif; ?>

              <button class="btn btn-sm btn-outline-danger btn-deletar-requisicao"
                      data-id="<?= $requisicao['id'] ?>"
                      onclick="deletarRequisicao(this)">
                <i class="fa fa-times"></i>
              </button>

            </div>

            <!-- ITENS DA REQUISIÇÃO -->
            <div class="collapse mt-3" id="itensReq<?= $requisicao['id'] ?>">
  <div class="card card-body">

    <?php
    // Agora buscando a descrição do item no pregão
    $stmtItens = $conexao->prepare("
        SELECT 
            fri.*, 
            fpi.descricao_item
        FROM fin_requisicao_itens fri
        LEFT JOIN fin_pregao_itens fpi ON fpi.id = fri.id_item
        WHERE fri.id_requisicao = ?
    ");
    $stmtItens->bind_param("i", $requisicao['id']);
    $stmtItens->execute();
    $resItens = $stmtItens->get_result();

    if ($resItens->num_rows > 0):
    ?>
      <ul class="list-group list-group-flush">
        <?php while ($item = $resItens->fetch_assoc()): ?>
          <li class="list-group-item">
            <strong>Item:</strong> 
            <?= htmlspecialchars($item['descricao_item'] ?? 'Sem descrição') ?>

            — <strong>Quantidade:</strong> 
            <?= htmlspecialchars($item['quant_saida_item'] ?? 0) ?>
          </li>
        <?php endwhile; ?>
      </ul>

    <?php else: ?>
      <div class="text-muted">Nenhum item cadastrado.</div>
    <?php 
    endif;

    $stmtItens->close();
    ?>
  </div>
</div>


          </div> <!-- FIM CARD -->

        <?php endwhile; ?>
      <?php else: ?>
        <div class="alert alert-info text-center">Nenhuma requisição encontrada.</div>
      <?php endif; ?>
    </div>
  </div>
</div>

    <div class="paginacao">
  <?= renderPaginacaoRequisicao($pagina, $totalPaginas, $limite, $queryString, 'includes/fin_requisicoes/listagem.php'); ?>
</div>

  </div>
</div>

</div>

<!-- Modal Cadastrar Empenho -->
<div class="modal fade" id="modalCadastrarEmpenho" tabindex="-1" aria-labelledby="modalLabelCadastrarEmpenho" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      
      <div class="modal-header bg-success text-white">
        <h5 class="modal-title" id="modalLabelCadastrarEmpenho">Cadastrar Empenho</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>

      <div class="modal-body">
        <form id="form-cadastrar-empenho" method="POST">

          <!-- BATALHÃO -->
          <div class="mb-3">
            <label class="form-label fw-semibold">Batalhão</label>
            <select id="empenho-batalhao" name="batalhao" class="form-select">
              <?php foreach ($oms_visiveis as $id => $nome): ?>
                <option value="<?= $id ?>"><?= htmlspecialchars($nome) ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <!-- FORNECEDOR -->
          <div class="mb-3">
            <label class="form-label fw-semibold">Fornecedor</label>
            <select id="empenho-fornecedor" name="id_fornecedor" class="form-select" required>
              <option value="">Selecione um fornecedor</option>

              <?php
              $sql_for = $conexao->query("SELECT id, nome_empresa FROM fin_fornecedores ORDER BY nome_empresa ASC");
              while ($for = $sql_for->fetch_assoc()):
              ?>
                <option value="<?= $for['id'] ?>"><?= htmlspecialchars($for['nome_empresa']) ?></option>
              <?php endwhile; ?>

            </select>
          </div>

          <!-- CAMPOS DO MODAL DE CADASTRAR REQUISIÇÃO -->
          <div class="row">
            <div class="col-md-6 mb-3">
              <label class="form-label">Requisitante</label>
              <input type="text" class="form-control" id="empenho-requisitante" name="requisitante" required>
            </div>

            <div class="col-md-6 mb-3">
              <label class="form-label">Natureza da Despesa</label>
              <input type="text" class="form-control" id="empenho-naturezadespesa" name="naturezadespesa" required>
            </div>
          </div>

          <div class="row">
            <div class="col-md-6 mb-3">
              <label class="form-label">Item OOG</label>
              <input type="text" class="form-control" id="empenho-item_oog" name="item_oog" required>
            </div>

            <div class="col-md-6 mb-3">
              <label class="form-label">Finalidade</label>
              <input type="text" class="form-control" id="empenho-finalidade" name="finalidade" required>
            </div>
          </div>

          <div class="row">
            <div class="col-md-6 mb-3">
              <label class="form-label">Destinatário</label>
              <input type="text" class="form-control" id="empenho-destinatario" name="destinatario" required>
            </div>

            <div class="col-md-6 mb-3">
              <label class="form-label">Nota Crédito</label>
              <input type="text" class="form-control" id="empenho-nota_credito" name="nota_credito" required>
            </div>
          </div>

          <div class="row">
            <div class="col-md-6 mb-3">
              <label class="form-label">Plano Interno</label>
              <input type="text" class="form-control" id="empenho-plano_interno" name="plano_interno" required>
            </div>

            <div class="col-md-6 mb-3">
              <label class="form-label">Necessidade de Contrato?</label>
              <select class="form-select" id="empenho-necessidade_contrato" name="necessidade_contrato">
                <option value="Sim">Sim</option>
                <option value="Não">Não</option>
              </select>
            </div>
          </div>

          <div class="row">
            <div class="col-md-6 mb-3">
              <label class="form-label">Tipo de Empenho</label>
              <select class="form-select" id="empenho-tipo_empenho" name="tipo_empenho">
                <option value="Global">Global</option>
                <option value="Ordinário">Ordinário</option>
              </select>
            </div>

            <div class="col-md-6 mb-3">
              <label class="form-label">Status da Requisição</label>
              <select class="form-select" id="empenho-status_requisicao" name="status_requisicao">
                <option value="Em confecção">Em confecção</option>
                <option value="Entregue na S4">Entregue na S4</option>
                <option value="Entregue na Fisc Adm">Entregue na Fisc Adm</option>
                <option value="Entregue na SALC">Entregue na SALC</option>
                <option value="Empenho gerado">Empenho gerado</option>
              </select>
            </div>
          </div>

          <hr>

          <!-- CAMPOS DO MODAL GERAR EMPENHO -->
          <div class="row">
            <div class="col-md-4 mb-3">
              <label class="form-label">Data do Empenho</label>
              <input type="date" class="form-control" id="empenho-data" name="data_empenho" required>
            </div>

            <div class="col-md-4 mb-3">
              <label class="form-label">Número do Empenho</label>
              <input type="text" class="form-control" id="empenho-numero" name="nmr_empenho" required>
            </div>

            <div class="col-md-4 mb-3">
              <label class="form-label">Ano</label>
              <input type="text" class="form-control" id="empenho-ano" name="ano" required>
            </div>
          </div>

          <div class="mb-3">
            <label class="form-label">Obra</label>
            <input type="text" class="form-control" id="empenho-obra" name="obra" required>
          </div>

          <div class="mb-3">
            <label class="form-label">Categoria</label>
            <input type="text" class="form-control" id="empenho-categoria" name="categoria" required>
          </div>

          <div class="row">
            <div class="col-md-6 mb-3">
              <label class="form-label">Local</label>
              <select class="form-select" id="empenho-local" name="local">
                <option value="Sede">Sede</option>
                <option value="Destacamento 1">Destacamento 1</option>
              </select>
            </div>

            <div class="col-md-6 mb-3">
              <label class="form-label">Resto a pagar?</label>
              <select class="form-select" id="empenho-resto_pagar" name="resto_pagar">
                <option value="Sim">Sim</option>
                <option value="Não">Não</option>
              </select>
            </div>
          </div>

          <!-- NOVO CAMPO -->
          <div class="mb-3">
            <label class="form-label">Valor Total Empenhado</label>
            <input type="text" class="form-control" id="empenho-valor_total" name="valor_empenhado" required>
          </div>

          <button type="submit" class="btn btn-success w-100">
            <i class="fas fa-save me-1"></i> Salvar Empenho
          </button>

        </form>
      </div>

    </div>
  </div>
</div>


<!-- Modal de Edição Requisição -->
<div class="modal fade" id="modalEditarRequisicao" tabindex="-1" aria-labelledby="modalLabelEditarRequisicao" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      <div class="modal-header bg-warning">
        <h5 class="modal-title" id="modalLabelEditarRequisicao">Editar Requisição</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
      </div>
      <div class="modal-body">
        <form method="POST" enctype="multipart/form-data" id="form-requisicao-editar">

          <div class="mb-3">
            <label class="form-label">Requisitante</label>
            <input type="text" class="form-control" id="editar-requisitante" name="requisitante" required>
          </div>

          <div class="mb-3">
            <label class="form-label">Natureza da Despesa</label>
            <input type="text" class="form-control" id="editar-natureza_despesa" name="natureza_despesa" required>
          </div>
            
             <div class="mb-3">
            <label class="form-label">Item OOG</label>
            <input type="text" class="form-control" id="editar-item_oog" name="item_oog" required>
          </div>
            
            <div class="mb-3">
            <label class="form-label">Finalidade da requisição</label>
            <input type="text" class="form-control" id="editar-finalidade" name="finalidade" required>
          </div>
            
          <div class="mb-3">
            <label class="form-label">Destinatário</label>
            <input type="text" class="form-control" id="editar-destinatario" name="destinatario" required>
          </div>

          <div class="mb-3">
            <label class="form-label">Nota Crédito</label>
            <input type="text" class="form-control" id="editar-nota_credito" name="nota_credito" required>
          </div>

          <div class="mb-3">
            <label class="form-label">Plano Interno</label>
            <input type="text" class="form-control" id="editar-plano_interno" name="plano_interno" required>
          </div>
            
            <div class="mb-3">
    <label for="status" class="form-label">Necessidade de contrato?</label>
    <select class="form-select rounded-pill shadow-sm" id="editar-necessidade_contrato" name="necessidade_contrato" required>
      <option value="Sim">Sim</option>
      <option value="Não">Não</option>
    </select>
  </div>
            
            <div class="mb-3">
    <label for="status" class="form-label">Tipo de empenho</label>
    <select class="form-select rounded-pill shadow-sm" id="editar-tipo_empenho" name="tipo_empenho" required>
      <option value="Global">Global</option>
      <option value="Ordinário">Ordinário</option>
    </select>
  </div>
            
          <div class="mb-3">
    <label for="status" class="form-label">Status da Requisição</label>
    <select class="form-select rounded-pill shadow-sm" id="editar-status_requisicao" name="status_requisicao" required>
      <option value="Em confecção">Em confecção</option>
      <option value="Entregue na S4">Entregue na S4</option>
      <option value="Entregue na Fisc Adm">Entregue na Fisc Adm</option>
      <option value="Entregue na SALC">Entregue na SALC</option>
      <option value="Empenho gerado">Empenho gerado</option>
    </select>
  </div>

          <hr>
          <h5 class="fw-bold">Itens da Requisição</h5>

         <div class="mb-3">
            <label class="form-label">Pregão da Requisição</label>
            <select class="form-select" id="editar-pregao" name="id_pregao" required>
              <option value="">Selecione um pregão</option>
                
              <!-- Opções de pregão devem ser carregadas via backend ou JS -->
            </select>
          </div>

<div id="itensRequisicaoContainerEditar" style="display: none;">
  <div class="mb-2">
    <button type="button" class="btn btn-info btn-sm mb-3" id="btnAdicionarItemRequisicaoEditar">
      Adicionar item à requisição
    </button>
  </div>
  <div id="itensRequisicaoLista"></div>
</div>


          <button type="submit" class="btn btn-success">Salvar alterações</button>
          <input type="hidden" id="editar-id-requisicao" name="id">
        </form>
      </div>
    </div>
  </div>
</div>




<!-- Modal do ver requisição -->
<div class="modal fade" id="modalVerRequisicao" tabindex="-1" aria-labelledby="modalVerRequisicaoLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content border-0 shadow">
      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title">Ver Requisição</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Fechar"></button>
      </div>

      <div class="modal-body p-4 bg-light">

        <div class="row g-3 bg-white p-3 rounded shadow-sm mb-4">
          <h5 class="fw-bold text-secondary">Dados da Requisição</h5>
          <div class="col-md-3">
            <label class="form-label">Requisitante</label>
            <p class="form-control-plaintext" id="ver_requisitante"></p>
          </div>
          <div class="col-md-3">
            <label class="form-label">Destinatário</label>
            <p class="form-control-plaintext" id="ver_destinatario"></p>
          </div>
          <div class="col-md-3">
            <label class="form-label">Nota Crédito</label>
            <p class="form-control-plaintext" id="ver_nota_credito"></p>
          </div>
          <div class="col-md-3">
            <label class="form-label">Plano Interno</label>
            <p class="form-control-plaintext" id="ver_plano_interno"></p>
          </div>
          <div class="col-md-3">
            <label class="form-label">Pregão</label>
            <p class="form-control-plaintext" id="ver_id_pregao"></p>
          </div>
        </div>

        <div class="bg-white p-3 rounded shadow-sm mb-4">
          <h5 class="fw-bold text-secondary">Itens da Requisição</h5>
          <div class="table-responsive">
            <table class="table table-bordered table-hover small">
              <thead class="table-light">
                <tr>
                  <th>#</th>
                  <th>Descrição</th>
                  <th>Quantidade</th>
                  <th>Valor Unitário</th>
                  <th>Valor Total</th>
                </tr>
              </thead>
              <tbody id="itensRequisicaoVerContainer">
                <!-- Itens inseridos dinamicamente -->
              </tbody>
            </table>
          </div>
        </div>

        <div class="text-end">
          <button type="button" class="btn btn-danger" data-bs-dismiss="modal">Fechar</button>
        </div>

      </div>
    </div>
  </div>
</div>





<!-- Script da página de Requisição de Vtr/Eqp -->
<script>
    window.funcaoInicializacao = 'inicializarRequisicao';
  
    
</script>


