<?php
include_once('../../conexao/config.php');

// -------------------- FILTROS --------------------
$marcaFilter = isset($_GET['marca']) ? trim($_GET['marca']) : '';
$modeloFilter = isset($_GET['modelo']) ? trim($_GET['modelo']) : '';
$limite = isset($_GET['limite']) && is_numeric($_GET['limite']) ? (int)$_GET['limite'] : 10;
$paginaAtual = isset($_GET['pagina']) && is_numeric($_GET['pagina']) && $_GET['pagina'] > 0 ? (int)$_GET['pagina'] : 1;
$offset = ($paginaAtual - 1) * $limite;

// -------------------- CONDIÇÕES DINÂMICAS --------------------
$condicoes = [];
$params = [];
$tipos = '';

// Se filtrar por nome da marca
if ($marcaFilter !== '') {
    $condicoes[] = "m.marca LIKE ?";
    $params[] = "%{$marcaFilter}%";
    $tipos .= 's';
}

// Se filtrar por modelo: seleciona marcas que possuam modelos com o nome informado
if ($modeloFilter !== '') {
    // Usamos EXISTS para não duplicar marcas no total
    $condicoes[] = "EXISTS (SELECT 1 FROM config_modelos md WHERE md.id_marca = m.id AND md.nome_modelo LIKE ?)";
    $params[] = "%{$modeloFilter}%";
    $tipos .= 's';
}

$where = '';
if (!empty($condicoes)) {
    $where = 'WHERE ' . implode(' AND ', $condicoes);
}

// -------------------- TOTAL DE REGISTROS --------------------
$sqlTotal = "SELECT COUNT(*) AS total FROM config_marcas m $where";
$stmtTotal = $conexao->prepare($sqlTotal);
if ($stmtTotal === false) {
    die('Erro na preparação (total): ' . $conexao->error);
}
if (!empty($params)) {
    $stmtTotal->bind_param($tipos, ...$params);
}
$stmtTotal->execute();
$totalRegistros = $stmtTotal->get_result()->fetch_assoc()['total'] ?? 0;
$totalPaginas = $limite > 0 ? ceil($totalRegistros / $limite) : 1;

// -------------------- CONSULTA PRINCIPAL (marcas paginadas) --------------------
$sql = "SELECT m.id, m.marca
        FROM config_marcas m
        $where
        ORDER BY m.marca ASC
        LIMIT ? OFFSET ?";

$stmt = $conexao->prepare($sql);
if ($stmt === false) {
    die('Erro na preparação (principal): ' . $conexao->error);
}

// Bind parameters para listagem (parametros de filtro + limite + offset)
// Note: ordem de bind é a mesma da construção de $params
if (!empty($params)) {
    // tipos finais acrescentam dois inteiros para LIMIT e OFFSET
    $tiposFinal = $tipos . 'ii';
    $paramsFinal = array_merge($params, [$limite, $offset]);
    // bind_param exige tipos e valores separados (mysqli)
    $stmt->bind_param($tiposFinal, ...$paramsFinal);
} else {
    // Sem filtros: apenas limite e offset
    $stmt->bind_param('ii', $limite, $offset);
}

$stmt->execute();
$marcas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// -------------------- BUSCA DOS MODELOS DAS MARCAS VISIBLE (para o accordion) --------------------
$modelosPorMarca = [];
if (!empty($marcas)) {
    // montar lista de ids
    $ids = array_column($marcas, 'id');
    // placeholders
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    // preparar consulta (ordenar por nome do modelo)
    $sqlModelos = "SELECT id, id_marca, nome_modelo FROM config_modelos WHERE id_marca IN ($placeholders) ORDER BY nome_modelo ASC";
    $stmtModelos = $conexao->prepare($sqlModelos);
    if ($stmtModelos === false) {
        die('Erro na preparação (modelos): ' . $conexao->error);
    }

    // bind dinamicamente: todos os ids são inteiros
    $tiposIds = str_repeat('i', count($ids));
    $stmtModelos->bind_param($tiposIds, ...$ids);
    $stmtModelos->execute();
    $resultModelos = $stmtModelos->get_result();

    while ($row = $resultModelos->fetch_assoc()) {
        $idMarca = $row['id_marca'];
        if (!isset($modelosPorMarca[$idMarca])) {
            $modelosPorMarca[$idMarca] = [];
        }
        $modelosPorMarca[$idMarca][] = $row;
    }
}

// -------------------- Função de Paginação --------------------
function renderPaginacao($pagina, $totalPaginas, $limite, $queryParams = []) {
    if ($totalPaginas <= 1) return '';

    // preserva query string (exceto 'pagina')
    $qs = $_GET;
    unset($qs['pagina']);

    // garante que queryParams externos (como filtros) também entrem
    $qs = array_merge($qs, $queryParams);

    $baseQuery = http_build_query($qs);

    $html = '<nav><ul class="pagination justify-content-center">';

    for ($i = 1; $i <= $totalPaginas; $i++) {
        $active = ($i == $pagina) ? ' active' : '';
        $href = "includes/marcas_modelos/listagem.php?pagina={$i}" . ($baseQuery ? "&{$baseQuery}" : '');
        $html .= "<li class='page-item{$active}'>
                    <a class='page-link' href='{$href}'>{$i}</a>
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
        <h3 class="fw-bold mb-1">Listagem das Marcas e Modelos</h3>
        <h6 class="text-muted">Marcas e Modelos</h6>
      </div>
      <div>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalCadastroMarca">
          <i class="fa fa-plus me-1"></i> Cadastrar Marca
        </button>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalCadastroModelo">
          <i class="fa fa-plus me-1"></i> Cadastrar Modelo
        </button>
      </div>
    </div>

    <!-- ========== FILTROS ========== -->
<div class="mb-3">
  <button class="btn btn-outline-primary w-100 d-flex justify-content-center align-items-center"
          onclick="toggleFiltrosMarcas()">
    <i class="fas fa-search me-2"></i> Filtros
  </button>
</div>

<div id="filtros-container-marcas" style="display: none;" class="mb-3">
  <div class="card shadow-sm border-0">
    <div class="card-body">
      <form method="GET" id="filtroMarcasForm">
        <div class="row g-3">
          <!-- Filtro por nome da marca -->
          <div class="col-md-4">
            <label class="form-label fw-semibold">Marca</label>
            <input type="text" class="form-control rounded-pill" name="marca"
                   value="<?= htmlspecialchars($marca ?? '') ?>"
                   placeholder="Ex: Toyota">
          </div>

          <!-- Filtro por nome do modelo -->
          <div class="col-md-4">
            <label class="form-label fw-semibold">Modelo</label>
            <input type="text" class="form-control rounded-pill" name="modelo"
                   value="<?= htmlspecialchars($modelo ?? '') ?>"
                   placeholder="Ex: Hilux">
          </div>

      

          <!-- Botões -->
          <div class="col-12 d-flex justify-content-between mt-2">
            <button type="button" id="btnLimparFiltrosMarcas"
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
  <label for="limiteMarcas" class="me-2 mb-0">Mostrar</label>

  <select id="limiteMarcas"
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




<!-- Listagem de Marcas e Modelos -->
<div class="card border-0 shadow-sm">
  <div class="card-body">
    <div class="list-group">
      <?php if (!empty($marcas)): ?>
        <?php foreach ($marcas as $marca): ?>
          <?php $idMarca = $marca['id']; ?>
          
          <div class="card shadow-sm border-0 mb-3">
            <div class="card-body d-flex justify-content-between align-items-center p-3">
              <!-- Informações da Marca -->
              <div>
                <h6 class="mb-1 fw-semibold text-primary">
                  #<?= $idMarca ?> - <?= htmlspecialchars($marca['marca']) ?>
                </h6>
                <small class="text-muted">Marca registrada no sistema</small>
              </div>

              <!-- Ações -->
              <div class="d-flex align-items-center gap-2">
                <!-- Botão de expandir modelos -->
                <button class="btn btn-sm btn-outline-secondary rounded-circle d-flex align-items-center justify-content-center"
                        type="button"
                        data-bs-toggle="collapse"
                        data-bs-target="#modelos<?= $idMarca ?>"
                        aria-expanded="false"
                        title="Ver modelos">
                  <i class="fas fa-chevron-down"></i>
                </button>

                <!-- Editar Marca -->
                <button class="btn btn-sm btn-outline-warning"
                        onclick="editarMarca(<?= $idMarca ?>)"
                        data-bs-toggle="modal"
                        data-bs-target="#modalEditarMarca">
                  <i class="fas fa-edit me-1"></i> Editar
                </button>

               <!-- Excluir Marca -->
<button type="button"
        class="btn btn-sm btn-outline-danger btn-deletar-marca"
        data-id="<?= $idMarca ?>"
        title="Excluir marca">
  <i class="fa fa-trash me-1"></i> Excluir
</button>

              </div>
            </div>

            <!-- Accordion com Modelos -->
            <div class="collapse border-top" id="modelos<?= $idMarca ?>">
              <div class="card-body bg-light">
                <?php if (!empty($modelosPorMarca[$idMarca])): ?>
                  <table class="table table-sm table-bordered align-middle text-center">
                    <thead class="table-secondary">
                      <tr>
                        <th>Modelo</th>
                        <th>Ações</th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php foreach ($modelosPorMarca[$idMarca] as $modelo): ?>
                        <tr>
                          <td class="text-start"><?= htmlspecialchars($modelo['nome_modelo']) ?></td>
                          <td class="text-center">
                            <button class="btn btn-sm btn-outline-warning"
                                    onclick="editarModelo(<?= $modelo['id'] ?>)"
                                    data-bs-toggle="modal"
                                    data-bs-target="#modalEditarModelo">
                              <i class="fas fa-edit me-1"></i> Editar
                            </button>

                            <button class="btn btn-sm btn-outline-danger btn-deletar-modelo"
                                    data-id="<?= $modelo['id'] ?>"
                                    data-bs-toggle="modal"
                                    data-bs-target="#modalExcluirModelo">
                              <i class="fa fa-times me-1"></i> Excluir
                            </button>
                          </td>
                        </tr>
                      <?php endforeach; ?>
                    </tbody>
                  </table>
                <?php else: ?>
                  <div class="alert alert-info text-center mb-0">
                    Nenhum modelo cadastrado para esta marca.
                  </div>
                <?php endif; ?>
              </div>
            </div>
          </div>
        <?php endforeach; ?>

        <!-- Paginação -->
        <div class="mt-3">
          <?= renderPaginacao($paginaAtual, $totalPaginas, $limite) ?>
        </div>
      <?php else: ?>
        <div class="alert alert-info text-center mb-0">
          Nenhuma marca encontrada.
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- ===================== MODAL CADASTRAR MODELO ===================== -->
<div class="modal fade" id="modalCadastroModelo" tabindex="-1" aria-labelledby="tituloModalModelo" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow">
      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title" id="tituloModalModelo">
          <i class="fa fa-cube me-2"></i> Cadastrar Modelo
        </h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Fechar"></button>
      </div>

      <form id="formCadastroModelo" autocomplete="off">
        <div class="modal-body">
          <div class="mb-3">
            <label for="marcaModelo" class="form-label fw-semibold">Marca</label>
            <select name="marca_id" id="marcaModelo" class="form-select" required>
              <option value="">Selecione uma marca...</option>
              <!-- Opções carregadas via PHP -->
              <?php
              include_once('conexao/config.php');
              $marcas = $conexao->query("SELECT id, marca FROM config_marcas ORDER BY marca ASC");
              while ($m = $marcas->fetch_assoc()):
              ?>
                <option value="<?= $m['id'] ?>"><?= htmlspecialchars($m['marca']) ?></option>
              <?php endwhile; ?>
            </select>
          </div>

          <div class="mb-3">
            <label for="nomeModelo" class="form-label fw-semibold">Nome do Modelo</label>
            <input type="text" name="modelo" id="nomeModelo" class="form-control" placeholder="Ex: XTR 250, L200, etc." required>
          </div>
        </div>

        <div class="modal-footer border-0">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-primary">
            <i class="fa fa-save me-1"></i> Salvar
          </button>
        </div>
      </form>
    </div>
  </div>
</div>




<!-- MODAL CADASTRO MARCA -->
<div class="modal fade" id="modalCadastroMarca" tabindex="-1" aria-labelledby="modalCadastroMarcaLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-md">
    <div class="modal-content">
      <form id="formCadastroMarca">
        <div class="modal-header bg-primary text-white">
          <h5 class="modal-title" id="modalCadastroMarcaLabel">Cadastrar Marca</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Fechar"></button>
        </div>

        <div class="modal-body">
          <div class="row g-3">
            <!-- Nome da Marca -->
            <div class="col-md-12">
              <label for="marca" class="form-label fw-semibold">Nome da Marca</label>
              <input type="text" class="form-control" id="marca" name="marca" placeholder="Ex: Toyota" required>
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
<!-- MODAL EDITAR MARCA -->
<div class="modal fade" id="modalEditarMarca" tabindex="-1" aria-labelledby="modalEditarMarcaLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-md">
    <div class="modal-content">
      <form id="formEditarMarca">
        <div class="modal-header bg-warning text-white">
          <h5 class="modal-title" id="modalEditarMarcaLabel">Editar Marca</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>

        <div class="modal-body">
          <input type="hidden" name="id" id="editarMarcaId">
          <div class="mb-3">
            <label for="editarNomeMarca" class="form-label">Nome da Marca</label>
            <input type="text" class="form-control" id="editarNomeMarca" name="marca" required>
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

<!-- MODAL EDITAR MODELO -->
<div class="modal fade" id="modalEditarModelo" tabindex="-1" aria-labelledby="modalEditarModeloLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-md">
    <div class="modal-content">
      <form id="formEditarModelo">
        <div class="modal-header bg-warning text-white">
          <h5 class="modal-title" id="modalEditarModeloLabel">Editar Modelo</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <input type="hidden" id="editarModeloId" name="id">

          <div class="mb-3">
            <label for="editarNomeModelo" class="form-label">Nome do Modelo</label>
            <input type="text" class="form-control" id="editarNomeModelo" name="nome_modelo" required>
          </div>

          <div class="mb-3">
            <label for="editarMarcaModelo" class="form-label">Marca</label>
            <select class="form-select" id="editarMarcaModelo" name="id_marca" required>
              <option value="" disabled>Selecione a marca</option>
              <?php
              include_once('../../conexao/config.php');
              $sql = "SELECT id, marca FROM config_marcas ORDER BY marca ASC";
              $res = $conexao->query($sql);
              if ($res && $res->num_rows > 0) {
                while ($row = $res->fetch_assoc()) {
                  echo '<option value="' . $row['id'] . '">' . htmlspecialchars($row['marca']) . '</option>';
                }
              }
              ?>
            </select>
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
    window.funcaoInicializacao = 'inicializarMARCASMODELOS';
    
</script>
