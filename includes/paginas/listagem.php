<?php
// includes/paginas/listagem.php
include_once('../../conexao/config.php');

// -------------------- FILTROS --------------------
$nome_pagina = $_GET['nome_pagina'] ?? '';
$limite = isset($_GET['limite']) && is_numeric($_GET['limite']) ? (int) $_GET['limite'] : 10;
$paginaAtual = isset($_GET['pagina']) && is_numeric($_GET['pagina']) && $_GET['pagina'] > 0 ? (int) $_GET['pagina'] : 1;
$offset = ($paginaAtual - 1) * $limite;

// ==============================
// Montagem do WHERE + parâmetros
// ==============================
$where = "";
$params = [];
$tipos = "";

// filtro por nome da página (subpágina)
if (!empty($nome_pagina)) {
    $where .= "WHERE p.nome LIKE ?";
    $params[] = "%$nome_pagina%";
    $tipos .= "s";
}

// ==============================
// Consulta total para paginação (mesma lógica de antes)
// ==============================
$sqlTotal = "SELECT COUNT(*) AS total FROM paginas p $where";
$stmtTotal = $conexao->prepare($sqlTotal);
if ($stmtTotal === false) {
    die("Erro prepare total: " . $conexao->error);
}
if (!empty($params)) {
    // bind dinâmico
    $bind_types = $tipos;
    $bind_values = $params;
    $refs = [];
    foreach ($bind_values as $k => $v) $refs[$k] = &$bind_values[$k];
    array_unshift($refs, $bind_types);
    call_user_func_array([$stmtTotal, 'bind_param'], $refs);
}
$stmtTotal->execute();
$resTotal = $stmtTotal->get_result();
$totalRegistros = $resTotal->fetch_assoc()['total'] ?? 0;
$totalPaginas = max(ceil($totalRegistros / $limite), 1);
$stmtTotal->close();

// ==============================
// Consulta principal (traz subpáginas + dados do grupo)
// ORDER: por grupo (pp.id) e depois por p.nome (ajusta pra exibição agrupada)
// ==============================
$sql = "
SELECT p.*, pp.nome AS principal_nome, pp.icone AS principal_icone, pp.id AS principal_id
FROM paginas p
JOIN paginas_principal pp ON pp.id = p.tipo
{$where}
ORDER BY pp.id ASC, p.ordem DESC
LIMIT ? OFFSET ?
";

$params2 = $params;
$params2[] = $limite;
$params2[] = $offset;
$tipos2 = $tipos . "ii";

$stmt = $conexao->prepare($sql);
if ($stmt === false) {
    die("Erro prepare principal: " . $conexao->error);
}

// bind dinâmico (precisa passar por referência)
$bind_values = $params2;
$refs = [];
foreach ($bind_values as $k => $v) $refs[$k] = &$bind_values[$k];
array_unshift($refs, $tipos2);
call_user_func_array([$stmt, 'bind_param'], $refs);

$stmt->execute();
$result = $stmt->get_result();

// Agrupa os resultados por principal (tipo)
$grupos = []; // chave = principal_id -> ['principal_nome','principal_icone','paginas'=>[...]]
while ($row = $result->fetch_assoc()) {
    $pid = (int)$row['principal_id'];
    if (!isset($grupos[$pid])) {
        $grupos[$pid] = [
            'principal_nome' => $row['principal_nome'],
            'principal_icone' => $row['principal_icone'],
            'paginas' => []
        ];
    }
    // adicionar página
    $grupos[$pid]['paginas'][] = $row;
}
$stmt->close();

// ==============================
// Funções cadastradas (usado para permissões - se for necessário em JS/ops)
// ==============================
$funcoes = $conexao->query("SELECT id, nome FROM funcoes ORDER BY nome ASC");

// ==============================
// Função de paginação (mantida como antes)
// ==============================
function renderPaginacaoPaginas($pagina, $total, $limite)
{
    if ($total <= 1) return '';

    $params = $_GET;
    unset($params['pagina']);
    $queryString = http_build_query($params);

    $html = '<nav><ul class="pagination justify-content-center">';

    for ($i = 1; $i <= $total; $i++) {

        $active = ($i == $pagina) ? 'active' : '';

        $url = "includes/paginas/listagem.php?pagina=$i&$queryString";

        $html .= "
        <li class='page-item $active'>
            <a href='#'
               class='page-link paginacao-paginas'
               data-page='$url'>$i</a>
        </li>";
    }

    $html .= '</ul></nav>';
    return $html;
}
?>



<div class="container">
  <div class="page-inner">
    <div class="d-flex justify-content-between align-items-center py-3">
      <div>
        <h3 class="fw-bold mb-1">Listagem das Páginas do Sistema</h3>
        <h6 class="text-muted">Páginas do Sistema</h6>
      </div>
      <div>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalCadastroPaginaPrincipal">
          <i class="fa fa-plus me-1"></i> Cadastrar Tópico
        </button>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalCadastroPagina">
          <i class="fa fa-plus me-1"></i> Cadastrar Página
        </button>
      </div>
    </div>

    <!-- Filtros -->
<div class="mb-3">
  <button class="btn btn-outline-primary w-100 d-flex justify-content-center align-items-center" onclick="toggleFiltrosPaginas()">
    <i class="fas fa-search me-2"></i> Filtros
  </button>
</div>

<div id="filtros-container-paginas" style="display: none;" class="mb-3">
  <div class="card shadow-sm border-0">
    <div class="card-body">
      <form method="GET" id="filtroPaginasForm">
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label fw-semibold">Nome da Página</label>
            <input type="text" class="form-control" name="nome_pagina" value="<?= htmlspecialchars($nome_pagina ?? '') ?>">
          </div>
          <div class="col-12 d-flex justify-content-between mt-2">
            <button type="button" id="btnLimparFiltrosPaginas" class="btn btn-outline-secondary d-flex align-items-center gap-2">
              <i class="fas fa-times-circle"></i> Limpar Filtros
            </button>
            <button type="submit" class="btn btn-primary px-4">Aplicar</button>
          </div>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Select de limite -->
<div class="mb-3">
  <label for="limitePaginas" class="me-2 mb-0">Mostrar</label>
  <select id="limitePaginas" name="limite" class="form-select d-inline w-auto" onchange="atualizarListaPaginas({ pagina: 1, limite: this.value })">
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
<?= renderPaginacaoPaginas($paginaAtual, $totalPaginas, $limite); ?>
</div>

<!-- Listagem -->
<!-- Listagem agrupada por paginas_principal -->
<div class="card border-0 shadow-sm">
  <div class="card-body">
    <div class="list-group">

      <?php if (!empty($grupos)): ?>

        <?php foreach ($grupos as $principal_id => $grupo): ?>
          <?php
            // IDs únicos para collapse do grupo
            $collapseGrupoId = "grupo{$principal_id}";
          ?>
          <div class="card shadow-sm border-0 mb-3">
            <div class="card-body p-2 d-flex justify-content-between align-items-center">
              <div class="d-flex align-items-center gap-3">
                <button class="btn btn-sm btn-outline-secondary rounded-circle d-flex align-items-center justify-content-center"
                        type="button"
                        data-bs-toggle="collapse"
                        data-bs-target="#<?= $collapseGrupoId ?>"
                        aria-expanded="false"
                        title="Abrir grupo">
                  <i class="fas fa-chevron-down"></i>
                </button>

                <div>
                  <h6 class="mb-0 fw-semibold text-primary">
                    <?php if (!empty($grupo['principal_icone'])): ?>
                      <i class="<?= htmlspecialchars($grupo['principal_icone']) ?> me-1"></i>
                    <?php endif; ?>
                    <?= htmlspecialchars($grupo['principal_nome']) ?>
                    <small class="text-muted">(<span class="badge bg-light text-dark"><?= count($grupo['paginas']) ?></span>)</small>
                  </h6>
                </div>
              </div>

              <div class="d-flex align-items-center gap-2">
               <button class="btn btn-sm btn-outline-warning"
        onclick="editarPaginaPrincipal(<?= (int)$principal_id ?>)">
  <i class="fas fa-edit me-1"></i> Editar
</button>


               
              </div>
            </div>

            <!-- Collapse do grupo: lista de páginas desse grupo -->
            <div class="collapse" id="<?= $collapseGrupoId ?>">
              <div class="card-body">

                <?php foreach ($grupo['paginas'] as $pagina): 
                    $paginaId = (int)$pagina['id'];
                    $collapsePermId = "permissoesPagina{$paginaId}";
                ?>
                  <div class="card mb-2 shadow-none border">
                    <div class="card-body d-flex justify-content-between align-items-center p-3">
                      <div>
                        <h6 class="mb-1 fw-semibold">
                          #<?= $paginaId ?> - <?= htmlspecialchars($pagina['nome']) ?>
                        </h6>
                        <small class="text-muted"><?= htmlspecialchars($pagina['descricao']) ?> <span class="ms-2"><em><?= htmlspecialchars($pagina['arquivo']) ?></em></span></small>
                      </div>

                      <div class="d-flex align-items-center gap-2">
                        <!-- botão para abrir permissões da página (collapse interno) -->
                        <button class="btn btn-sm btn-outline-secondary rounded-circle d-flex align-items-center justify-content-center"
                                type="button"
                                data-bs-toggle="collapse"
                                data-bs-target="#<?= $collapsePermId ?>"
                                aria-expanded="false"
                                title="Ver permissões">
                          <i class="fas fa-chevron-down"></i>
                        </button>

                        <button class="btn btn-sm btn-outline-warning"
                                onclick="editarPagina(<?= $paginaId ?>)"
                                data-bs-toggle="modal"
                                data-bs-target="#modalEditarPagina">
                          <i class="fas fa-edit me-1"></i> Editar
                        </button>

                        <button type="button"
                                class="btn btn-sm btn-outline-danger btn-deletar-pagina"
                                data-id="<?= $paginaId ?>"
                                data-bs-toggle="modal"
                                data-bs-target="#modalExcluirPagina"
                                title="Remover página">
                          <i class="fa fa-times me-1"></i> Excluir
                        </button>
                      </div>
                    </div>

                    <!-- Permissões (collapse do item) -->
                    <div class="collapse border-top" id="<?= $collapsePermId ?>">
                      <div class="card-body bg-light">

                        <?php
                        // Busca as permissões por função para esta página
                        $stmtPerm = $conexao->prepare("
                          SELECT f.id AS funcao_id, f.nome AS funcao_nome,
                                 COALESCE(pm.pode_acessar,0) AS pode_acessar,
                                 COALESCE(pm.pode_editar,0) AS pode_editar,
                                 COALESCE(pm.pode_deletar,0) AS pode_deletar,
                                 COALESCE(pm.pode_cadastrar,0) AS pode_cadastrar,
                                 COALESCE(pm.pode_importar,0) AS pode_importar,
                                 COALESCE(pm.pode_exportar,0) AS pode_exportar,
                                 COALESCE(pm.pode_autorizar,0) AS pode_autorizar
                          FROM funcoes f
                          LEFT JOIN permissoes pm ON pm.funcao_id = f.id AND pm.pagina_id = ?
                          ORDER BY f.nome ASC
                        ");
                        $stmtPerm->bind_param("i", $paginaId);
                        $stmtPerm->execute();
                        $permissoesRes = $stmtPerm->get_result();
                        ?>

                        <table class="table table-sm table-bordered align-middle text-center mb-0">
                          <thead class="table-secondary">
                            <tr>
                              <th>Função</th>
                              <th>Acessar</th>
                              <th>Editar</th>
                              <th>Deletar</th>
                              <th>Cadastrar</th>
                              <th>Importar</th>
                              <th>Exportar</th>
                              <th>Autorizar</th>
                            </tr>
                          </thead>
                          <tbody>
                            <?php while ($perm = $permissoesRes->fetch_assoc()): ?>
                              <tr>
                                <td class="fw-semibold text-start"><?= htmlspecialchars($perm['funcao_nome']) ?></td>
                                <?php foreach (['pode_acessar','pode_editar','pode_deletar','pode_cadastrar','pode_importar','pode_exportar', 'pode_autorizar'] as $campo): ?>
                                  <td>
                                    <input type="checkbox"
                                           class="form-check-input mx-auto"
                                           <?= $perm[$campo] ? 'checked' : '' ?>
                                           onchange="atualizarPermissao(<?= (int)$perm['funcao_id'] ?>, <?= $paginaId ?>, '<?= $campo ?>', this.checked)">
                                  </td>
                                <?php endforeach; ?>
                              </tr>
                            <?php endwhile; ?>
                          </tbody>
                        </table>

                        <?php $stmtPerm->close(); ?>

                      </div>
                    </div>
                    <!-- /Permissões -->

                  </div>
                <?php endforeach; ?>

              </div>
            </div>
            <!-- /collapse grupo -->

          </div>
        <?php endforeach; ?>

      <?php else: ?>
        <div class="alert alert-info text-center">Nenhuma página encontrada.</div>
      <?php endif; ?>

    </div>

    <!-- PAGINAÇÃO -->
    <div class="mt-3">
      <?= renderPaginacaoPaginas($paginaAtual, $totalPaginas, $limite) ?>
    </div>

  </div>
</div>




<!-- =================== MODAIS =================== -->

<!-- MODAL DE CADASTRO DE PÁGINA PRINCIPAL -->
<div class="modal fade" id="modalCadastroPaginaPrincipal" tabindex="-1" aria-labelledby="modalCadastroPaginaPrincipalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-md">
    <div class="modal-content">
      <form id="formCadastroPaginaPrincipal">
        <div class="modal-header bg-success text-white">
          <h5 class="modal-title" id="modalCadastroPaginaPrincipalLabel">Cadastrar Tópico Principal</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Fechar"></button>
        </div>
        <div class="modal-body">

          <!-- Nome do Tópico -->
          <div class="mb-3">
            <label class="form-label">Nome do Tópico</label>
            <input type="text" class="form-control" name="nome" required>
          </div>

          <!-- Seleção de Ícone -->
          <div class="mb-3">
            <label class="form-label">Ícone</label>
            <select class="form-select" name="icone" required>
              <option value="">Selecione um ícone</option>
              <?php
              $iconList = [
                  'fas fa-home' => 'Home',
                  'fas fa-cogs' => 'Configurações',
                  'fas fa-users' => 'Usuários',
                  'fas fa-chart-line' => 'Relatórios'
              ];
              foreach ($iconList as $classe => $nome) {
                  echo "<option value='{$classe}'>{$nome}</option>";
              }
              ?>
            </select>
          </div>

          <!-- Ordem -->
          <div class="mb-3">
            <label class="form-label">Ordem no Menu</label>
            <input type="number"
                   class="form-control"
                   name="ordem"
                   min="1"
                   step="1"
                   placeholder="Ex: 1, 2, 3..."
                   required>
          </div>

        </div>
        <div class="modal-footer">
          <button type="submit" class="btn btn-success">Salvar Tópico</button>
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
        </div>
      </form>
    </div>
  </div>
</div>
      
<!-- MODAL DE EDIÇÃO DE TÓPICO PRINCIPAL -->
<div class="modal fade" id="modalEditarPaginaPrincipal" tabindex="-1" aria-labelledby="modalEditarPaginaPrincipalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-md">
    <div class="modal-content">
      <form id="formEditarPaginaPrincipal">
        <div class="modal-header bg-warning text-white">
          <h5 class="modal-title" id="modalEditarPaginaPrincipalLabel">Editar Tópico Principal</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <input type="hidden" name="id" id="editarPaginaPrincipalId">

          <div class="mb-3">
            <label class="form-label">Nome do Tópico</label>
            <input type="text" class="form-control" id="editarNomePaginaPrincipal" name="nome" required>
          </div>

          <div class="mb-3">
  <label class="form-label">Ícone</label>
  <select class="form-select" id="editarIconePaginaPrincipal" name="icone" required>
    <option value="">Selecione um ícone</option>

    <?php
    // Lista representativa de ícones da Font Awesome (solid e brands)
    $iconList = [
      'fas fa-home' => 'Home',
      'fas fa-cogs' => 'Configurações',
      'fas fa-users' => 'Usuários',
      'fas fa-chart-line' => 'Relatórios',
      'fas fa-chart-pie' => 'Gráfico de Pizza',
      'fas fa-chart-bar' => 'Gráfico de Barras',
      'fas fa-table' => 'Tabela',
      'fas fa-file' => 'Arquivo',
      'fas fa-folder' => 'Pasta',
      'fas fa-book' => 'Livro',
      'fas fa-book-open' => 'Livro Aberto',
      'fas fa-envelope' => 'Email',
      'fas fa-bell' => 'Notificações',
      'fas fa-key' => 'Chave',
      'fas fa-lock' => 'Cadeado',
      'fas fa-unlock' => 'Desbloquear',
      'fas fa-shield-alt' => 'Escudo',
      'fas fa-wrench' => 'Ferramenta / Ajuste',
      'fas fa-truck' => 'Caminhão / Frota',
      'fas fa-shopping-cart' => 'Carrinho',
      'fas fa-dollar-sign' => 'Dinheiro / Financeiro',
      'fas fa-globe' => 'Globo / Internet',
      'fas fa-cog' => 'Configurações (simples)',
      'fas fa-calendar' => 'Calendário',
      'fas fa-clock' => 'Relógio / Tempo',
      'fas fa-map-marker-alt' => 'Localização',
      'fas fa-phone' => 'Telefone',
      'fas fa-file-alt' => 'Documento',
      'fas fa-chart-area' => 'Gráfico de Área',
      'fab fa-github' => 'GitHub (marca)',
      'fab fa-twitter' => 'Twitter (marca)',
      'fab fa-linkedin' => 'LinkedIn (marca)',
      'fab fa-google' => 'Google (marca)'
    ];

    foreach ($iconList as $classe => $nome) {
      echo "<option value=\"{$classe}\">{$nome} ({$classe})</option>";
    }
    ?>
  </select>
</div>


          <div class="mb-3">
            <label class="form-label">Ordem no Menu</label>
            <input type="number"
                   class="form-control"
                   name="ordem"
                   id="editarOrdemPaginaPrincipal"
                   min="1"
                   step="1"
                   placeholder="Ex: 1, 2, 3..."
                   required>
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

      
      
<!-- MODAL DE CADASTRO -->
<div class="modal fade" id="modalCadastroPagina" tabindex="-1" aria-labelledby="modalCadastroPaginaLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-md">
    <div class="modal-content">
      <form id="formCadastroPagina">
        <div class="modal-header bg-primary text-white">
          <h5 class="modal-title" id="modalCadastroPaginaLabel">Cadastrar Página do Sistema</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Fechar"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label">Nome da Página</label>
            <input type="text" class="form-control" name="nome" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Descrição</label>
            <textarea class="form-control" name="descricao" rows="3"></textarea>
          </div>
         <?php
// Buscar páginas principais para o SELECT
$principalQuery = $conexao->query("SELECT id, nome FROM paginas_principal ORDER BY nome ASC");
?>

<div class="mb-3">
    <label class="form-label">Tipo (Página Principal)</label>
    <select class="form-select" name="tipo" required>
        <option value="">Selecione uma Página Principal</option>

        <?php while ($pp = $principalQuery->fetch_assoc()): ?>
            <option value="<?= $pp['id'] ?>">
                <?= htmlspecialchars($pp['nome']) ?>
            </option>
        <?php endwhile; ?>

    </select>
</div>

<div class="mb-3">
    <label class="form-label">Ordem no Menu</label>
    <input type="number"
           class="form-control"
           name="ordem"
           min="1"
           step="1"
           placeholder="Ex: 1, 2, 3..."
           required>
</div>
 <div class="mb-3">
            <label class="form-label">URL da página</label>
            <input type="text" class="form-control" name="arquivo" required>
          </div>

        </div>
        <div class="modal-footer">
          <button type="submit" class="btn btn-primary">Salvar Página</button>
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- MODAL DE EDIÇÃO -->
<div class="modal fade" id="modalEditarPagina" tabindex="-1" aria-labelledby="modalEditarPaginaLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-md">
    <div class="modal-content">
      <form id="formEditarPagina">
        <div class="modal-header bg-warning text-white">
          <h5 class="modal-title" id="modalEditarPaginaLabel">Editar Página</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <input type="hidden" name="id" id="editarPaginaId">
          <div class="mb-3">
            <label class="form-label">Nome da Página</label>
            <input type="text" class="form-control" id="editarNomePagina" name="nome" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Descrição</label>
            <textarea class="form-control" id="editarDescricaoPagina" name="descricao" rows="3"></textarea>
          </div>
            <?php
// Buscar páginas principais
$principalQuery = $conexao->query("SELECT id, nome FROM paginas_principal ORDER BY nome ASC");
?>

<div class="mb-3">
  <label class="form-label">Tópico Principal</label>
  <select class="form-select" id="editarTipoPagina" name="tipo">
    <option value="">Selecione um tópico...</option>
    <?php while ($pp = $principalQuery->fetch_assoc()): ?>
      <option value="<?= $pp['id'] ?>"><?= htmlspecialchars($pp['nome']) ?></option>
    <?php endwhile; ?>
  </select>
</div>
            
            <div class="mb-3">
    <label class="form-label">Ordem no Menu</label>
    <input type="number"
           class="form-control"
           name="ordem"
           id="editarOrdemPagina"
           min="1"
           step="1"
           placeholder="Ex: 1, 2, 3..."
           required>
</div>
            
          <div class="mb-3">
            <label class="form-label">URL da página</label>
            <input type="text" class="form-control" id="editarArquivo" name="arquivo" required>
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


      
<!-- Script da página de Cadastro de Fornecedores -->
<script>
    window.funcaoInicializacao = 'inicializarPaginasPermissoes';
 
    
</script>
