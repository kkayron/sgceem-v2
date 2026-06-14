<?php
require_once '../api/seguranca.php';

$permissoes = verificarPermissao([49]);

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

// -------------------- FILTROS --------------------
$abreviatura = isset($_GET['abreviatura']) ? trim($_GET['abreviatura']) : '';
$descricao   = isset($_GET['descricao']) ? trim($_GET['descricao']) : '';
$tipo        = isset($_GET['tipo']) ? trim($_GET['tipo']) : '';

$limite = isset($_GET['limite']) && is_numeric($_GET['limite']) ? (int)$_GET['limite'] : 10;
$paginaAtual = isset($_GET['pagina']) && is_numeric($_GET['pagina']) && $_GET['pagina'] > 0 ? (int)$_GET['pagina'] : 1;
$offset = ($paginaAtual - 1) * $limite;

// -------------------- CONDIÇÕES DINÂMICAS --------------------
$condicoes = [];
$params = [];
$tipos = '';

if ($abreviatura !== '') {
    $condicoes[] = "t.abreviatura LIKE ?";
    $params[] = "%{$abreviatura}%";
    $tipos .= 's';
}

if ($descricao !== '') {
    $condicoes[] = "t.descricao LIKE ?";
    $params[] = "%{$descricao}%";
    $tipos .= 's';
}

if ($tipo !== '') {
    $condicoes[] = "t.tipo LIKE ?";
    $params[] = "%{$tipo}%";
    $tipos .= 's';
}

$where = '';
if (!empty($condicoes)) {
    $where = 'WHERE ' . implode(' AND ', $condicoes);
}

// -------------------- TOTAL DE REGISTROS --------------------
$sqlTotal = "SELECT COUNT(*) AS total FROM config_tiposvtreqp t $where";
$stmtTotal = $conexao->prepare($sqlTotal);
if ($stmtTotal === false) die('Erro total: ' . $conexao->error);

if (!empty($params)) {
    $stmtTotal->bind_param($tipos, ...$params);
}
$stmtTotal->execute();
$totalRegistros = $stmtTotal->get_result()->fetch_assoc()['total'] ?? 0;
$totalPaginas = $limite > 0 ? ceil($totalRegistros / $limite) : 1;

// -------------------- CONSULTA PRINCIPAL --------------------
$sql = "SELECT t.id, t.abreviatura, t.descricao, t.tipo
        FROM config_tiposvtreqp t
        $where
        ORDER BY t.abreviatura ASC
        LIMIT ? OFFSET ?";

$stmt = $conexao->prepare($sql);
if ($stmt === false) die('Erro principal: ' . $conexao->error);

if (!empty($params)) {
    $tiposFinal = $tipos . 'ii';
    $paramsFinal = array_merge($params, [$limite, $offset]);
    $stmt->bind_param($tiposFinal, ...$paramsFinal);
} else {
    $stmt->bind_param('ii', $limite, $offset);
}

$stmt->execute();
$tiposListados = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// -------------------- Função de Paginação --------------------
function renderPaginacaoTipos($pagina, $totalPaginas) {
    if ($totalPaginas <= 1) return '';

    $qs = $_GET;
    unset($qs['pagina']);
    $baseQS = http_build_query($qs);

    $html = '<nav><ul class="pagination justify-content-center">';
    for ($i = 1; $i <= $totalPaginas; $i++) {
        $active = ($i == $pagina) ? ' active' : '';
        $href = "includes/fin_tipos_vtr/listagem.php?pagina={$i}" . ($baseQS ? "&{$baseQS}" : '');
        $html .= "<li class='page-item{$active}'><a class='page-link' href='{$href}'>{$i}</a></li>";
    }
    $html .= '</ul></nav>';
    return $html;
}

// ============================
// Mantém filtros na paginação
// ============================
$paramsGET = $_GET;
unset($paramsGET['pagina']);
$queryString = http_build_query($paramsGET);

?>



<div class="container">
  <div class="page-inner">
    <div class="d-flex justify-content-between align-items-center py-3">
      <div>
        <h3 class="fw-bold mb-1">Listagem dos Tipos de Vtr/Eqp</h3>
        <h6 class="text-muted">Tipos de Vtr/Eqp</h6>
      </div>
      <div>
		  <?php if($pode_cadastrar): ?>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalCadastroTipo">
          <i class="fa fa-plus me-1"></i> Cadastrar Tipo
        </button>
		  <?php endif; ?>
      </div>
    </div>

    <!-- ========== FILTROS ========== -->
<div class="mb-3">
  <button class="btn btn-outline-primary w-100 d-flex justify-content-center align-items-center"
          onclick="toggleFiltrosTipoRV()">
    <i class="fas fa-search me-2"></i> Filtros
  </button>
</div>

<div id="filtros-container-tiporv" style="display: none;" class="mb-3">
  <div class="card shadow-sm border-0">
    <div class="card-body">
      <form method="GET" id="filtroTipoRVForm">
        <div class="row g-3">

          <!-- Filtro por Abreviatura -->
          <div class="col-md-4">
            <label class="form-label fw-semibold">Abreviatura</label>
            <input type="text" class="form-control rounded-pill"
                   name="abreviatura"
                   value="<?= htmlspecialchars($_GET['abreviatura'] ?? '') ?>"
                   placeholder="Ex: RV">
          </div>

          <!-- Filtro por Descrição -->
          <div class="col-md-4">
            <label class="form-label fw-semibold">Descrição</label>
            <input type="text" class="form-control rounded-pill"
                   name="descricao"
                   value="<?= htmlspecialchars($_GET['descricao'] ?? '') ?>"
                   placeholder="Ex: Requisição de Viatura">
          </div>

          <!-- Filtro por Tipo -->
          <div class="col-md-4">
            <label class="form-label fw-semibold">Tipo</label>
            <input type="text" class="form-control rounded-pill"
                   name="tipo"
                   value="<?= htmlspecialchars($_GET['tipo'] ?? '') ?>"
                   placeholder="Eqp ou Vtr">
          </div>
            

          <!-- Botões -->
          <div class="col-12 d-flex justify-content-between mt-2">
            <button type="button" id="btnLimparFiltrosTipoRV"
                    class="btn btn-outline-secondary d-flex align-items-center gap-2">
              <i class="fas fa-times-circle"></i> Limpar Filtros
            </button>

            <button type="submit" class="btn btn-primary px-4">
              <i class="fas fa-filter me-1"></i> Aplicar
            </button>
          </div>

        </div>
      </form>
    </div>
  </div>
</div>
      
      <!-- ===== LIMITE ===== -->
<div class="mb-3">
  <label for="limiteTipoRV" class="me-2 mb-0">Mostrar</label>

  <select id="limiteTipoRV"
          name="limite"
          class="form-select d-inline w-auto">
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

      
      <!-- Paginação -->
<div class="mt-3">
  <?= renderPaginacaoTipos($paginaAtual, $totalPaginas) ?>
</div>

<div class="card border-0 shadow-sm">
  <div class="card-body">
    <div class="list-group">

      <?php if (!empty($tiposListados)): ?>
        <?php foreach ($tiposListados as $item): ?>
          <div class="card shadow-sm border-0 mb-3">
            <div class="card-body d-flex justify-content-between align-items-center p-3">
              
              <div>
                <h6 class="mb-1 fw-semibold text-primary">
                  #<?= $item['id'] ?> - <?= htmlspecialchars($item['abreviatura']) ?>
                </h6>
                <small class="text-muted"><?= htmlspecialchars($item['descricao']) ?></small>
              </div>

              <div class="d-flex align-items-center gap-2">
                <!-- Botão accordion -->
                <button class="btn btn-sm btn-outline-secondary rounded-circle"
                        type="button"
                        data-bs-toggle="collapse"
                        data-bs-target="#tipo<?= $item['id'] ?>">
                  <i class="fas fa-chevron-down"></i>
                </button>

		  <?php if($pode_editar): ?>
                <!-- Editar -->
                <button class="btn btn-sm btn-outline-warning"
                        onclick="editarTipoRV(<?= $item['id'] ?>)"
                        data-bs-toggle="modal"
                        data-bs-target="#modalEditarTipoRV">
                  <i class="fas fa-edit me-1"></i> Editar
                </button>
<?php endif; ?>
                <!-- Excluir -->
				  <?php if($pode_deletar): ?>
                <button
  type="button"
  class="btn btn-sm btn-outline-danger"
  data-id="<?= $item['id'] ?>"
  data-token="<?= htmlspecialchars($_SESSION['csrf_token']) ?>"
  onclick="deletarTipoVtrEqp(this)">
  <i class="fas fa-trash"></i>
</button>
<?php endif; ?>


              </div>
            </div>

            <!-- Accordion detalhes -->
            <div class="collapse border-top" id="tipo<?= $item['id'] ?>">
              <div class="card-body bg-light">
                <table class="table table-sm table-bordered text-center">
                  <tr>
                    <th class="w-25">Abreviatura</th>
                    <td><?= htmlspecialchars($item['abreviatura']) ?></td>
                  </tr>
                  <tr>
                    <th>Descrição</th>
                    <td><?= htmlspecialchars($item['descricao']) ?></td>
                  </tr>
                  <tr>
                    <th>Tipo</th>
                    <td><?= htmlspecialchars($item['tipo']) ?></td>
                  </tr>
                </table>
              </div>
            </div>

          </div>
        <?php endforeach; ?>
      <?php else: ?>
        <div class="alert alert-info text-center">Nenhum registro encontrado.</div>
      <?php endif; ?>

    </div>
  </div>
</div>

<!-- Paginação -->
<div class="mt-3">
  <?= renderPaginacaoTipos($paginaAtual, $totalPaginas) ?>
</div>
<?php if($pode_cadastrar): ?>
<!-- MODAL CADASTRO TIPO VTR/EQP -->
<div class="modal fade" id="modalCadastroTipo" tabindex="-1" aria-labelledby="modalCadastroTipoLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-md">
    <div class="modal-content">
      <form id="formCadastroTipo">
        <div class="modal-header bg-primary text-white">
          <h5 class="modal-title" id="modalCadastroTipoLabel">Cadastrar Tipo Vtr/Eqp</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Fechar"></button>
        </div>

        <div class="modal-body">
          <div class="row g-3">

  <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>">
            <div class="col-md-12">
              <label for="abreviatura" class="form-label fw-semibold">Abreviatura</label>
              <input type="text" class="form-control" id="abreviatura" name="abreviatura" placeholder="Ex: MN" required>
            </div>
              
            <div class="col-md-12">
              <label for="descricao" class="form-label fw-semibold">Descrição</label>
              <input type="text" class="form-control" id="descricao" name="descricao" placeholder="Ex: Motoniveladora" required>
            </div>

            <div class="col-md-12">
              <label for="tipo" class="form-label fw-semibold">Categoria</label>
              <select class="form-select" id="tipo" name="tipo" required>
                <option value="" disabled selected>Selecione</option>
                <option value="Eqp">Equipamento</option>
                <option value="Vtr">Viatura</option>
              </select>
            </div>

          </div>
        </div>

        <div class="modal-footer">
          <button type="submit" class="btn btn-primary">
            <i class="fa fa-save me-1"></i> Salvar
          </button>
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
            <i class="fa fa-times me-1"></i> Cancelar
          </button>
        </div>

      </form>
    </div>
  </div>
</div>
	  <?php endif; ?>
<?php if($pode_editar): ?>
      <!-- MODAL EDITAR TIPO VTR/EQP -->
<div class="modal fade" id="modalEditarTipoRV" tabindex="-1" aria-labelledby="modalEditarTipoRVLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-md">
    <div class="modal-content">
      <form id="formEditarTipoRV">

        <div class="modal-header bg-warning text-dark">
          <h5 class="modal-title" id="modalEditarTipoRVLabel">
            Editar Tipo de VTR/EQP
          </h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
        </div>

        <div class="modal-body">
          <div class="row g-3">

            <!-- ID oculto -->
            <input type="hidden" id="editarTipoRVId" name="id">
  <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>">

            <!-- Abreviatura -->
            <div class="col-md-12">
              <label class="form-label fw-semibold">Abreviatura</label>
              <input type="text" class="form-control"
                     id="editarAbreviatura"
                     name="abreviatura"
                     required>
            </div>

            <!-- Descrição -->
            <div class="col-md-12">
              <label class="form-label fw-semibold">Descrição</label>
              <input type="text" class="form-control"
                     id="editarDescricao"
                     name="descricao"
                     required>
            </div>

            <div class="col-md-12">
              <label for="tipo" class="form-label fw-semibold">Categoria</label>
              <select class="form-select" id="editarTipo" name="tipo" required>
                <option value="" disabled selected>Selecione</option>
                <option value="Eqp">Equipamento</option>
                <option value="Vtr">Viatura</option>
              </select>
            </div>

          </div>
        </div>

        <div class="modal-footer">
          <button type="submit" class="btn btn-warning">
            <i class="fa fa-save me-1"></i> Salvar Alterações
          </button>
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
            <i class="fa fa-times me-1"></i> Cancelar
          </button>
        </div>

      </form>
    </div>
  </div>
</div>
	  <?php endif; ?>
<!-- Script da página de Cadastro de Fornecedores -->
<script>
    window.funcaoInicializacao = 'inicializarTipoVtrEqp';
    
</script>
