<?php
include_once('../../conexao/config.php');

// -------------------- FILTROS --------------------
$nome = $_GET['nome'] ?? '';
$abreviatura = $_GET['abreviatura'] ?? '';
$nivel = $_GET['nivel'] ?? '';
$limite = isset($_GET['limite']) ? (int)$_GET['limite'] : 10;
$paginaAtual = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
$offset = ($paginaAtual - 1) * $limite;

// -------------------- CONDIÇÕES DINÂMICAS --------------------
$condicoes = [];
$params = [];
$tipos = '';

if (!empty($nome)) {
    $condicoes[] = "nome LIKE ?";
    $params[] = "%$nome%";
    $tipos .= 's';
}

if (!empty($abreviatura)) {
    $condicoes[] = "abreviatura LIKE ?";
    $params[] = "%$abreviatura%";
    $tipos .= 's';
}

if (!empty($nivel)) {
    $condicoes[] = "nivel = ?";
    $params[] = $nivel;
    $tipos .= 's';
}

$where = '';
if (!empty($condicoes)) {
    $where = 'WHERE ' . implode(' AND ', $condicoes);
}

// -------------------- TOTAL DE REGISTROS --------------------
$sqlTotal = "SELECT COUNT(*) AS total FROM organizacoes_militares $where";
$stmtTotal = $conexao->prepare($sqlTotal);
if (!empty($params)) {
    $stmtTotal->bind_param($tipos, ...$params);
}
$stmtTotal->execute();
$totalRegistros = $stmtTotal->get_result()->fetch_assoc()['total'] ?? 0;
$totalPaginas = ceil($totalRegistros / $limite);

// -------------------- CONSULTA PRINCIPAL --------------------
$sql = "SELECT * FROM organizacoes_militares $where ORDER BY nivel ASC, nome ASC LIMIT ?, ?";
$stmt = $conexao->prepare($sql);

if (!empty($params)) {
    $tiposFinal = $tipos . 'ii';
    $paramsFinal = array_merge($params, [$offset, $limite]);
    $stmt->bind_param($tiposFinal, ...$paramsFinal);
} else {
    $stmt->bind_param('ii', $offset, $limite);
}

$stmt->execute();
$oms = $stmt->get_result();

// -------------------- LISTAGEM PARA RELAÇÃO DE SUBORDINAÇÃO --------------------
$todasOMs = $conexao->query("SELECT id, nome, abreviatura FROM organizacoes_militares ORDER BY nome ASC")->fetch_all(MYSQLI_ASSOC);

// -------------------- FUNÇÃO DE PAGINAÇÃO --------------------
function renderPaginacaoOM($pagina, $total, $limite, $queryString = '')
{
    if ($total <= 1) return '';

    $html = '<nav><ul class="pagination justify-content-center">';
    for ($i = 1; $i <= $total; $i++) {
        $active = ($i == $pagina) ? 'active' : '';
        $html .= "<li class='page-item $active'><a class='page-link' href='?pagina=$i&$queryString'>$i</a></li>";
    }
    $html .= '</ul></nav>';
    return $html;
}
?>

<div class="container">
  <div class="page-inner">
    <div class="d-flex justify-content-between align-items-center py-3">
      <div>
        <h3 class="fw-bold mb-1">Listagem das Organizações Militares</h3>
        <h6 class="text-muted">Organizações Militares</h6>
      </div>
      <div>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalCadastroOM">
          <i class="fa fa-plus me-1"></i> Cadastrar OM
        </button>
      </div>
    </div>

    <!-- ========== FILTROS ========== -->
<div class="mb-3">
  <button class="btn btn-outline-primary w-100 d-flex justify-content-center align-items-center" onclick="toggleFiltrosOM()">
    <i class="fas fa-search me-2"></i> Filtros
  </button>
</div>

<div id="filtros-container-oms" style="display: none;" class="mb-3">
  <div class="card shadow-sm border-0">
    <div class="card-body">
      <form method="GET" id="filtroOMForm">
        <div class="row g-3">
          <div class="col-md-4">
            <label class="form-label fw-semibold">Nome</label>
            <input type="text" class="form-control" name="nome" value="<?= htmlspecialchars($nome) ?>">
          </div>
          <div class="col-md-4">
            <label class="form-label fw-semibold">Abreviatura</label>
            <input type="text" class="form-control" name="abreviatura" value="<?= htmlspecialchars($abreviatura) ?>">
          </div>
          <div class="col-md-4">
            <label class="form-label fw-semibold">Nível</label>
            <input type="text" class="form-control" name="nivel" value="<?= htmlspecialchars($nivel) ?>">
          </div>
          <div class="col-12 d-flex justify-content-between mt-2">
            <button type="button" id="btnLimparFiltrosOM" class="btn btn-outline-secondary d-flex align-items-center gap-2">
              <i class="fas fa-times-circle"></i> Limpar Filtros
            </button>
            <button type="submit" class="btn btn-primary px-4">Aplicar</button>
          </div>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- ===== LIMITE ===== -->
<div class="mb-3">
  <label for="limiteOM" class="me-2 mb-0">Mostrar</label>
  <select id="limiteOM" name="limite" class="form-select d-inline w-auto" onchange="atualizarListaOM({ pagina: 1, limite: this.value })">
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

<!-- Paginação topo -->
<div class="paginacao mb-3">
  <?= renderPaginacaoOM($paginaAtual, $totalPaginas, $limite); ?>
</div>

<!-- Listagem -->
<div class="card border-0 shadow-sm">
  <div class="card-body">
    <div class="list-group">
      <?php if ($oms->num_rows > 0): ?>
        <?php while ($om = $oms->fetch_assoc()): ?>
          <?php
            $idOM = $om['id'];
            $nivel = $om['nivel'];
          ?>
          <div class="card shadow-sm border-0 mb-3">
            <div class="card-body d-flex justify-content-between align-items-center p-3">
              <div>
                <h6 class="mb-1 fw-semibold text-primary">
                  #<?= $idOM ?> - <?= htmlspecialchars($om['nome']) ?>
                </h6>
                <small class="text-muted">
                  <?= htmlspecialchars($om['abreviatura']) ?> • Nível <?= $nivel ?>
                </small>
              </div>

              <div class="d-flex align-items-center gap-2">
                <?php if ($nivel == 2): ?>
                  <button class="btn btn-sm btn-outline-secondary rounded-circle d-flex align-items-center justify-content-center"
                          type="button"
                          data-bs-toggle="collapse"
                          data-bs-target="#subordinadas<?= $idOM ?>"
                          aria-expanded="false"
                          title="Gerenciar visualizações">
                    <i class="fas fa-chevron-down"></i>
                  </button>
                <?php endif; ?>

                <button class="btn btn-sm btn-outline-warning"
                        onclick="editarOM(<?= $idOM ?>)"
                        data-bs-toggle="modal"
                        data-bs-target="#modalEditarOM">
                  <i class="fas fa-edit me-1"></i> Editar
                </button>

                <button type="button"
                        class="btn btn-sm btn-outline-danger btn-deletar-om"
                        data-id="<?= $idOM ?>"
                        data-bs-toggle="modal"
                        data-bs-target="#modalExcluirOM"
                        title="Remover">
                  <i class="fa fa-times me-1"></i> Excluir
                </button>
              </div>
            </div>

            <?php if ($nivel == 2): ?>
              <div class="collapse border-top" id="subordinadas<?= $idOM ?>">
                <div class="card-body bg-light">
                  <table class="table table-sm table-bordered align-middle text-center">
                    <thead class="table-secondary">
                      <tr>
                        <th>Organização Militar</th>
                        <th>Pode Visualizar</th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php
                        $stmtSubs = $conexao->prepare("SELECT id_om_menor FROM organizacoes_militares_sub WHERE id_om_maior = ?");
                        $stmtSubs->bind_param("i", $idOM);
                        $stmtSubs->execute();
                        $subs = $stmtSubs->get_result()->fetch_all(MYSQLI_ASSOC);
                        $subsIds = array_column($subs, 'id_om_menor');
                      ?>

                      <?php foreach ($todasOMs as $outraOM): ?>
                        <?php if ($outraOM['id'] == $idOM) continue; // não listar a si mesma ?>
                        <tr>
                          <td class="text-start"><?= htmlspecialchars($outraOM['nome']) ?> (<?= htmlspecialchars($outraOM['abreviatura']) ?>)</td>
                          <td>
                            <input type="checkbox"
                                   class="form-check-input mx-auto"
                                   <?= in_array($outraOM['id'], $subsIds) ? 'checked' : '' ?>
                                   onchange="atualizarVisualizacaoOM(<?= $idOM ?>, <?= $outraOM['id'] ?>, this.checked)">
                          </td>
                        </tr>
                      <?php endforeach; ?>
                    </tbody>
                  </table>
                </div>
              </div>
            <?php endif; ?>
          </div>
        <?php endwhile; ?>
      <?php else: ?>
        <div class="alert alert-info text-center">Nenhuma Organização Militar encontrada.</div>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- Paginação fundo -->
<div class="paginacao mt-4">
  <?= renderPaginacaoOM($paginaAtual, $totalPaginas, $limite); ?>
</div>



<!-- MODAL CADASTRO -->
<div class="modal fade" id="modalCadastroOM" tabindex="-1" aria-labelledby="modalCadastroOMLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-md">
    <div class="modal-content">
      <form id="formCadastroOM">
        <div class="modal-header bg-primary text-white">
          <h5 class="modal-title" id="modalCadastroOMLabel">Cadastrar Organização Militar</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Fechar"></button>
        </div>
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-md-12">
              <label class="form-label">Nome</label>
              <input type="text" class="form-control" name="nome" required>
            </div>
            <div class="col-md-6">
              <label class="form-label">Abreviatura</label>
              <input type="text" class="form-control" name="abreviatura" required>
            </div>
            <div class="col-md-6">
              <label class="form-label">Nível</label>
              <input type="text" class="form-control" name="nivel" required>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="submit" class="btn btn-primary">Salvar</button>
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- MODAL EDIÇÃO -->
<div class="modal fade" id="modalEditarOM" tabindex="-1" aria-labelledby="modalEditarOMLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-md">
    <div class="modal-content">
      <form id="formEditarOM">
        <div class="modal-header bg-warning text-white">
          <h5 class="modal-title" id="modalEditarOMLabel">Editar Organização Militar</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <input type="hidden" name="id" id="editarOMId">
          <div class="row g-3">
            <div class="col-md-12">
              <label class="form-label">Nome</label>
              <input type="text" class="form-control" id="editarNomeOM" name="nome" required>
            </div>
            <div class="col-md-6">
              <label class="form-label">Abreviatura</label>
              <input type="text" class="form-control" id="editarAbreviaturaOM" name="abreviatura" required>
            </div>
            <div class="col-md-6">
              <label class="form-label">Nível</label>
              <input type="text" class="form-control" id="editarNivelOM" name="nivel" required>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="submit" class="btn btn-warning">Salvar Alterações</button>
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- MODAL EXCLUSÃO -->
<div class="modal fade" id="modalExcluirOM" tabindex="-1" aria-labelledby="modalExcluirOMLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-sm">
    <div class="modal-content">
      <form id="formExcluirOM">
        <div class="modal-header bg-danger text-white">
          <h5 class="modal-title">Excluir Organização Militar</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body text-center">
          <input type="hidden" name="id" id="excluirOMId">
          <i class="fas fa-exclamation-triangle fa-3x text-danger mb-3"></i>
          <h6>Tem certeza que deseja excluir?</h6>
          <p class="text-muted small">Esta ação não pode ser desfeita.</p>
        </div>
        <div class="modal-footer justify-content-center">
          <button type="submit" class="btn btn-danger">Excluir</button>
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
        </div>
      </form>
    </div>
  </div>
</div>
      
      <script>
// Atualizar relação de subordinação
function atualizarVisualizacaoOM(idOmMaior, idOmMenor, ativo) {
  fetch('includes/oms/atualizar_subordinacao.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: `id_om_maior=${idOmMaior}&id_om_menor=${idOmMenor}&ativo=${ativo ? 1 : 0}`
  })
  .then(r => r.json())
  .then(resp => {
    if (!resp.sucesso) {
      alert('Erro ao atualizar: ' + resp.mensagem);
    }
  })
  .catch(err => console.error('Erro:', err));
}
</script>
      
<!-- Script da página de Cadastro de Fornecedores -->
<script>
    window.funcaoInicializacao = 'inicializarOMS';
 
    
</script>
