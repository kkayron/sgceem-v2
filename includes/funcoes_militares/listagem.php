<?php
header('Content-Type: text/html; charset=utf-8');
session_start();
include_once('../../conexao/config.php');

// ==============================
// ⚙️ CAPTURA DE FILTROS GET
// ==============================
$nome_funcao = $_GET['nome_funcao'] ?? '';

// ==============================
// ⚙️ PREPARAÇÃO DE FILTROS
// ==============================
$filtros = [];
$params = [];
$tipos = '';

// ==============================
// ⚙️ PAGINAÇÃO
// ==============================
$limite = isset($_GET['limite']) && is_numeric($_GET['limite']) ? (int) $_GET['limite'] : 10;
$pagina = isset($_GET['pagina']) && is_numeric($_GET['pagina']) && $_GET['pagina'] > 0 ? (int) $_GET['pagina'] : 1;
$offset = ($pagina - 1) * $limite;

// ==============================
// 📌 Aplica filtro se existir
// ==============================
if (!empty($nome_funcao)) {
    $filtros[] = "nome LIKE ?";
    $params[] = "%$nome_funcao%";
    $tipos .= "s";
}

// Monta a cláusula WHERE final
$condicoes = !empty($filtros) ? "WHERE " . implode(" AND ", $filtros) : "";

// ==============================
// 📊 TOTAL DE REGISTROS (p/ paginação)
// ==============================
$sqlTotal = "SELECT COUNT(*) AS total FROM funcoes $condicoes";
$stmtTotal = $conexao->prepare($sqlTotal);

if (!empty($params)) {
    $stmtTotal->bind_param($tipos, ...$params);
}

$stmtTotal->execute();
$resultTotal = $stmtTotal->get_result();
$totalRegistros = $resultTotal->fetch_assoc()['total'];

$totalPaginas = $totalRegistros > 0 ? ceil($totalRegistros / $limite) : 1;

// ==============================
// 📋 CONSULTA PRINCIPAL
// ==============================
$sql = "SELECT * FROM funcoes $condicoes ORDER BY id DESC LIMIT ? OFFSET ?";

$paramsQuery = $params;
$tiposQuery = $tipos;

// Adiciona limite e offset
$paramsQuery[] = $limite;
$paramsQuery[] = $offset;
$tiposQuery .= "ii";

$stmt = $conexao->prepare($sql);
$stmt->bind_param($tiposQuery, ...$paramsQuery);
$stmt->execute();

$funcoes = $stmt->get_result();

// ==============================
// 🧭 FUNÇÃO PARA RENDER PAGINAÇÃO
// ==============================
function renderPaginacaoFuncoes($pagina, $totalPaginas, $limite, $queryString, $arquivo = 'includes/funcoes_militares/listagem.php')
{
    if ($totalPaginas <= 1) return "";

    $html = '<nav><ul class="pagination pagination-sm justify-content-center">';

    // Botão anterior
    $anterior = max(1, $pagina - 1);
    $urlAnterior = "{$arquivo}?{$queryString}&pagina={$anterior}&limite={$limite}";
    $disabledAnterior = $pagina <= 1 ? " disabled" : "";

    $html .= "<li class='page-item{$disabledAnterior}'>
                <a class='page-link paginacao-funcoes' href='#' data-page='{$urlAnterior}'>«</a>
              </li>";

    // Números
    for ($i = 1; $i <= $totalPaginas; $i++) {
        $ativo = $i == $pagina ? " active" : "";
        $url = "{$arquivo}?{$queryString}&pagina={$i}&limite={$limite}";

        $html .= "<li class='page-item{$ativo}'>
                    <a class='page-link paginacao-funcoes' href='#' data-page='{$url}'>{$i}</a>
                  </li>";
    }

    // Botão próximo
    $proxima = min($totalPaginas, $pagina + 1);
    $urlProxima = "{$arquivo}?{$queryString}&pagina={$proxima}&limite={$limite}";
    $disabledProxima = $pagina >= $totalPaginas ? " disabled" : "";

    $html .= "<li class='page-item{$disabledProxima}'>
                <a class='page-link paginacao-funcoes' href='#' data-page='{$urlProxima}'>»</a>
              </li>";

    $html .= "</ul></nav>";

    return $html;
}

// ==============================
// 🔗 MONTA QUERY STRING SEM A PÁGINA
// ==============================
$paramsGET = $_GET;
unset($paramsGET['pagina']); // remove página para evitar duplicação

$queryString = http_build_query($paramsGET);
?>


<div class="container">
  <div class="page-inner">
    <div class="d-flex justify-content-between align-items-center py-3">
      <div>
        <h3 class="fw-bold mb-1">Listagem das Funções de Usuários</h3>
        <h6 class="text-muted">Funções de Usuários no Sistema</h6>
      </div>
      <div>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalCadastroFuncao">
          <i class="fa fa-plus me-1"></i> Cadastrar Função
        </button>
      </div>
    </div>

    <!-- Filtros -->
    <div class="mb-3">
      <button class="btn btn-outline-primary w-100 d-flex justify-content-center align-items-center" onclick="toggleFiltrosFuncoes()">
        <i class="fas fa-search me-2"></i> Filtros
      </button>
    </div>

    <div id="filtros-container-funcoes" style="display: none;" class="mb-3">
      <div class="card shadow-sm border-0">
        <div class="card-body">
          <form method="GET" id="filtroFuncoesForm">
            <div class="row g-3">
              <div class="col-md-6">
                <label class="form-label fw-semibold">Nome da Função</label>
                <input type="text" class="form-control" name="nome_funcao" value="<?= htmlspecialchars($nome_funcao ?? '') ?>">
              </div>
              <div class="col-12 d-flex justify-content-between mt-2">
                <button type="button" id="btnLimparFiltrosFuncoes" class="btn btn-outline-secondary d-flex align-items-center gap-2">
                  <i class="fas fa-times-circle"></i> Limpar Filtros
                </button>
                <button type="submit" class="btn btn-primary px-4">Aplicar</button>
              </div>
            </div>
          </form>
        </div>
      </div>
    </div>

    <!-- Select de limite por página -->
    <div class="mb-3">
      <label for="limiteFuncoes" class="me-2 mb-0">Mostrar</label>
      <select id="limiteFuncoes" name="limite" class="form-select d-inline w-auto" onchange="atualizarListaFuncoes({ pagina: 1, limite: this.value })">
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
      <?= renderPaginacaoFuncoes($pagina, $totalPaginas, $limite, $queryString); ?>
    </div>
<!-- LISTAGEM -->
<div class="card border-0 shadow-sm">
  <div class="card-body">
    <div class="list-group">

      <?php if ($funcoes->num_rows > 0): ?>
        <?php while ($funcao = $funcoes->fetch_assoc()): ?>

          <div class="card shadow-sm border-0 mb-3">
            <div class="card-body d-flex justify-content-between align-items-center p-3">

              <div>
                <h6 class="mb-1 fw-semibold text-primary">
                  #<?= htmlspecialchars($funcao['id']) ?> - <?= htmlspecialchars($funcao['nome']) ?>
                </h6>
                <small class="text-muted"><?= htmlspecialchars($funcao['descricao']) ?></small>
              </div>

              <div class="d-flex align-items-center gap-2">

                <!-- Expandir permissões -->
                <button class="btn btn-sm btn-outline-secondary rounded-circle d-flex align-items-center justify-content-center"
                        type="button"
                        data-bs-toggle="collapse"
                        data-bs-target="#permissoesFuncao<?= $funcao['id'] ?>"
                        aria-expanded="false"
                        title="Ver permissões">
                  <i class="fas fa-chevron-down"></i>
                </button>

                <!-- Editar -->
                <button class="btn btn-sm btn-outline-warning"
                        onclick="editarFuncao(<?= $funcao['id'] ?>)"
                        data-bs-toggle="modal"
                        data-bs-target="#modalEditarFuncao">
                  <i class="fas fa-edit me-1"></i> Editar
                </button>

                <!-- Excluir -->
                <button type="button"
                        class="btn btn-sm btn-outline-danger btn-deletar-funcao"
                        data-id="<?= $funcao['id'] ?>"
                        data-bs-toggle="modal"
                        data-bs-target="#modalExcluirFuncao"
                        title="Remover">
                  <i class="fa fa-times me-1"></i> Excluir
                </button>

              </div>
            </div>

            <!-- PERMISSÕES DA FUNÇÃO -->
            <div class="collapse border-top" id="permissoesFuncao<?= $funcao['id'] ?>">
              <div class="card-body bg-light">

                <table class="table table-sm table-bordered align-middle text-center">
                  <thead class="table-secondary">
                    <tr>
                      <th>Página</th>
                      <th>Acessar</th>
                      <th>Editar</th>
                      <th>Deletar</th>
                      <th>Cadastrar</th>
                    </tr>
                  </thead>

                  <tbody>
                    <?php
                    $stmtPerm = $conexao->prepare("
                      SELECT p.id AS pagina_id, p.nome AS pagina_nome,
                             COALESCE(pm.pode_acessar,0) AS pode_acessar,
                             COALESCE(pm.pode_editar,0) AS pode_editar,
                             COALESCE(pm.pode_deletar,0) AS pode_deletar,
                             COALESCE(pm.pode_cadastrar,0) AS pode_cadastrar
                      FROM paginas p
                      LEFT JOIN permissoes pm 
                         ON pm.pagina_id = p.id AND pm.funcao_id = ?
                      ORDER BY p.nome ASC
                    ");
                    $stmtPerm->bind_param("i", $funcao['id']);
                    $stmtPerm->execute();
                    $permissoes = $stmtPerm->get_result();
                    ?>

                    <?php while ($perm = $permissoes->fetch_assoc()): ?>
                      <tr>
                        <td class="fw-semibold text-start">
                          <?= htmlspecialchars($perm['pagina_nome']) ?>
                        </td>

                        <?php foreach (['pode_acessar', 'pode_editar', 'pode_deletar', 'pode_cadastrar'] as $campo): ?>
                          <td>
                            <input type="checkbox"
  class="form-check-input mx-auto chk-permissao-pagina"
  data-funcao="<?= $funcao['id'] ?>"
  data-pagina="<?= $perm['pagina_id'] ?>"
  data-campo="<?= $campo ?>"
  <?= $perm[$campo] ? 'checked' : '' ?>>
                          </td>
                        <?php endforeach; ?>
                      </tr>
                    <?php endwhile; ?>
                  </tbody>
                </table>

              </div>
            </div>

          </div>

        <?php endwhile; ?>

      <?php else: ?>
        <div class="alert alert-info text-center">Nenhuma função encontrada.</div>
      <?php endif; ?>

    </div>
  </div>
</div>



    <!-- Paginação fundo -->
    <div class="paginacao mt-4">
      <?= renderPaginacaoFuncoes($pagina, $totalPaginas, $limite, $queryString); ?>
    </div>
  </div>
</div>


<!-- MODAL DE CADASTRO DE FUNÇÃO -->
<div class="modal fade" id="modalCadastroFuncao" tabindex="-1" aria-labelledby="modalCadastroFuncaoLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-md">
        <div class="modal-content">
            <form id="formCadastroFuncao">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="modalCadastroFuncaoLabel">Cadastrar Função de Usuário</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>

                <div class="modal-body">
                    <div class="row g-3">
                        <!-- Nome da Função -->
                        <div class="col-md-12">
                            <label for="nomeFuncao" class="form-label">Nome da Função</label>
                            <input type="text" class="form-control" id="nomeFuncao" name="nome" placeholder="Ex: Administrador" required>
                        </div>

                        <!-- Descrição da Função -->
                        <div class="col-md-12">
                            <label for="descricaoFuncao" class="form-label">Descrição da Função</label>
                            <textarea class="form-control" id="descricaoFuncao" name="descricao" rows="3" placeholder="Descreva as responsabilidades desta função" required></textarea>
                        </div>
                    </div>

                    <hr class="my-4">

                    <!-- Informações adicionais -->
                    <div class="alert alert-info text-center fs-6" style="font-size: 0.95rem;">
                        Após o cadastro da função, você poderá configurar as permissões de acesso às páginas do sistema.
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="submit" class="btn btn-primary">Salvar Função</button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- MODAL DE EDIÇÃO DE FUNÇÃO -->
<div class="modal fade" id="modalEditarFuncao" tabindex="-1" aria-labelledby="modalEditarFuncaoLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-md">
        <div class="modal-content">
            <form id="formEditarFuncao">
                <div class="modal-header bg-warning text-white">
                    <h5 class="modal-title" id="modalEditarFuncaoLabel">Editar Função de Usuário</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>

                <div class="modal-body">
                    <input type="hidden" id="editarFuncaoId" name="id">

                    <div class="row g-3">
                        <!-- Nome da Função -->
                        <div class="col-md-12">
                            <label for="editarNomeFuncao" class="form-label">Nome da Função</label>
                            <input type="text" class="form-control" id="editarNomeFuncao" name="nome" required>
                        </div>

                        <!-- Descrição da Função -->
                        <div class="col-md-12">
                            <label for="editarDescricaoFuncao" class="form-label">Descrição da Função</label>
                            <textarea class="form-control" id="editarDescricaoFuncao" name="descricao" rows="3" required></textarea>
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

<!-- MODAL DE EXCLUSÃO DE FUNÇÃO -->
<div class="modal fade" id="modalExcluirFuncao" tabindex="-1" aria-labelledby="modalExcluirFuncaoLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content">
            <form id="formExcluirFuncao">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title" id="modalExcluirFuncaoLabel">Excluir Função</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>

                <div class="modal-body text-center">
                    <input type="hidden" id="excluirFuncaoId" name="id">
                    <i class="fas fa-exclamation-triangle fa-3x text-danger mb-3"></i>
                    <h6>Tem certeza que deseja excluir esta função?</h6>
                    <p class="text-muted small">Esta ação não pode ser desfeita. Todos os usuários associados a esta função serão afetados.</p>
                </div>

                <div class="modal-footer justify-content-center">
                    <button type="submit" class="btn btn-danger">Excluir</button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                </div>
            </form>
        </div>
    </div>
</div>

      
      
      
<!-- Script da página de Cadastro de Fornecedores -->
<script>
    window.funcaoInicializacao = 'inicializarFuncoesUsuarios';
 
    
</script>
