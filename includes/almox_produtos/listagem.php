<?php
header('Content-Type: text/html; charset=utf-8');
session_start();
require_once '../../conexao/config.php';

require_once '../api/seguranca.php';

$permissoes = verificarPermissao([30]);

$pode_cadastrar = $permissoes['cadastrar'];
$pode_editar    = $permissoes['editar'];
$pode_deletar   = $permissoes['deletar'];
$pode_importar  = $permissoes['importar'];
$pode_exportar  = $permissoes['exportar'];

// ============================
// BATALHÕES PERMITIDOS AO USUÁRIO
// ============================
$id_om_usuario = $_SESSION['usuario']['batalhao'] ?? 0;

// Busca o nível do usuário
$sqlNivel = "SELECT nivel FROM organizacoes_militares WHERE id = $id_om_usuario";
$resNivel = $conexao->query($sqlNivel);
$nivelUsuario = $resNivel->fetch_assoc()['nivel'] ?? 0;

// Monta lista de OMs que ele pode visualizar
if ($nivelUsuario == 1) {
    $sqlBatalhoes = "SELECT id FROM organizacoes_militares";
} else {
    $sqlBatalhoes = "
        SELECT om.id
        FROM organizacoes_militares om
        WHERE om.id = $id_om_usuario
        OR om.id IN (
            SELECT id_om_menor FROM organizacoes_militares_sub WHERE id_om_maior = $id_om_usuario
        )
    ";
}
$resBatalhoes = $conexao->query($sqlBatalhoes);
$batalhoesPermitidos = [];
while ($bat = $resBatalhoes->fetch_assoc()) {
    $batalhoesPermitidos[] = (int)$bat['id'];
}

if (empty($batalhoesPermitidos)) {
    echo "<div class='alert alert-warning'>Nenhum batalhão disponível para este usuário.</div>";
    exit;
}

// ============================
// FILTROS VIA GET
// ============================
$id               = $_GET['id'] ?? '';
$nome_produto     = $_GET['nome_produto'] ?? '';
$codigo_produto   = $_GET['codigo_produto'] ?? '';
$categoria_produto= $_GET['categoria_produto'] ?? '';
$data_ini         = $_GET['data_ini'] ?? '';
$data_fim         = $_GET['data_fim'] ?? '';
$batalhaoFiltro   = $_GET['batalhao'] ?? ''; // filtro opcional pelo usuário

// ============================
// LIMITES E PAGINAÇÃO
// ============================
$limite = isset($_GET['limite']) && is_numeric($_GET['limite']) ? (int)$_GET['limite'] : 10;
$pagina = isset($_GET['pagina']) && is_numeric($_GET['pagina']) && $_GET['pagina'] > 0 ? (int)$_GET['pagina'] : 1;
$offset = ($pagina - 1) * $limite;

// ============================
// MONTAGEM DOS FILTROS
// ============================
$filtros = [];
$params = [];
$tipos = '';

// Campos dinâmicos (mesma lógica do seu exemplo)
$campos = [
    'p.id' => 'id',
    'p.nome_produto' => 'nome_produto',
    'p.codigo_produto' => 'codigo_produto',
    'p.categoria_produto' => 'categoria_produto',
    'p.data_inclusao >=' => 'data_ini',
    'p.data_inclusao <=' => 'data_fim'
];

foreach ($campos as $coluna => $parametro) {
    if (!empty($_GET[$parametro])) {
        $valor = $_GET[$parametro];
        if (str_contains($coluna, '>=')) {
            $filtros[] = str_replace(' >=', ' >=', $coluna) . ' ?';
            $params[] = $valor;
            $tipos .= 's';
        } elseif (str_contains($coluna, '<=')) {
            $filtros[] = str_replace(' <=', ' <=', $coluna) . ' ?';
            $params[] = $valor;
            $tipos .= 's';
        } elseif ($parametro === 'id') {
            $filtros[] = "$coluna = ?";
            $params[] = (int)$valor;
            $tipos .= 'i';
        } else {
            $filtros[] = "$coluna LIKE ?";
            $params[] = '%' . $valor . '%';
            $tipos .= 's';
        }
    }
}

// ============================
// Filtro de batalhão (se informado pelo usuário e permitido) 
// senão aplica listagem apenas dos batalhões permitidos (mesma lógica das fichas)
// ============================
if (!empty($batalhaoFiltro) && in_array((int)$batalhaoFiltro, $batalhoesPermitidos)) {
    $filtros[] = "p.batalhao = ?";
    $params[] = (int)$batalhaoFiltro;
    $tipos .= 'i';
} else {
    // aplica limitação por todos os batalhões permitidos
    $placeholders = implode(',', array_fill(0, count($batalhoesPermitidos), '?'));
    $filtros[] = "p.batalhao IN ($placeholders)";
    // acrescenta os ids aos params e tipos
    foreach ($batalhoesPermitidos as $batId) {
        $params[] = $batId;
    }
    $tipos .= str_repeat('i', count($batalhoesPermitidos));
}

$whereSQL = !empty($filtros) ? 'WHERE ' . implode(' AND ', $filtros) : '';

// ============================
// CONTAGEM TOTAL
// ============================
$sqlTotal = "SELECT COUNT(*) as total FROM almox_produtos p $whereSQL";
$stmtTotal = $conexao->prepare($sqlTotal);
if ($tipos !== '') {
    $stmtTotal->bind_param($tipos, ...$params);
}
$stmtTotal->execute();
$resultTotal = $stmtTotal->get_result();
$totalRegistros = $resultTotal->fetch_assoc()['total'];
$stmtTotal->close();
$totalPaginas = ceil($totalRegistros / $limite);

// ============================
// CONSULTA PRINCIPAL
// ============================
$sql = "
  SELECT 
    p.id,
    p.batalhao,
    p.data_inclusao,
    p.nome_produto,
    p.codigo_produto,
    p.categoria_produto,
    p.obs_produto,
    p.unidade,
    p.estoque_minimo,
    om.nome AS nome_om, 
    om.abreviatura AS abreviatura_om
  FROM almox_produtos p
  LEFT JOIN organizacoes_militares om ON p.batalhao = om.id
  $whereSQL
  ORDER BY p.id DESC
  LIMIT ? OFFSET ?
";

$params[] = $limite;
$params[] = $offset;
$tipos .= 'ii';

$stmt = $conexao->prepare($sql);
$stmt->bind_param($tipos, ...$params);
$stmt->execute();
$result = $stmt->get_result();

// ============================
// Função de paginação (mesma signature que você usa)
// ============================
function renderPaginacaoAlmox($pagina, $totalPaginas, $limite, $queryString, $arquivo = 'includes/almox_produtos/listagem.php') {
  $html = '<div class="pagination-wrapper">';
  $html .= '<nav><ul class="pagination pagination-sm justify-content-center">';
  if ($totalPaginas > 1) {
    for ($i = 1; $i <= $totalPaginas; $i++) {
      $ativo = $i == $pagina ? 'active' : '';
      $url = "{$arquivo}?{$queryString}&pagina=$i&limite=$limite";
      $html .= "<li class='page-item $ativo'><a class='page-link paginacao-almox' href='#' data-page='{$url}'>$i</a></li>";
    }
  }
  $html .= '</ul></nav></div>';
  return $html;
}

// ============================
// Mantém filtros na paginação
// ============================
$paramsGET = $_GET;
unset($paramsGET['pagina']);
$queryString = http_build_query($paramsGET);
?>


<style>
.btn-group .btn {
  border-radius: 20px;
  transition: all 0.3s ease;
}
</style>

<div class="container">
  <div class="page-inner">
    <div class="d-flex justify-content-between align-items-center py-3">
      <div>
        <h3 class="fw-bold mb-1">Listagem dos Produtos</h3>
        <h6 class="text-muted">Produtos cadastrados</h6>
      </div>
        <div>
			<?php if($pode_cadastrar): ?>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalCadastroProduto">
          <i class="fa fa-user-plus me-1"></i> Cadastrar Produto
        </button>
    <?php endif; ?>
      </div>
    </div>
   <!-- Botão para mostrar/ocultar filtros -->
<div class="mb-3">
  <button class="btn btn-outline-primary w-100 d-flex justify-content-center align-items-center" onclick="toggleFiltrosALMOX()">
    <i class="fas fa-search me-2"></i> Filtros
  </button>
</div>

<!-- Filtros -->
<div id="filtros-container-almox" style="display: none;" class="mb-3">
  <div class="card shadow-sm border-0">
    <div class="card-body">
      <form method="GET" id="filtroAlmoxForm">
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
          <div class="col-md-3">
            <label class="form-label fw-semibold">Nome do Produto</label>
            <input type="text" class="form-control" name="nome_produto" value="<?= htmlspecialchars($nome_produto ?? '') ?>">
          </div>
          <div class="col-md-3">
            <label class="form-label fw-semibold">Código do Produto</label>
            <input type="text" class="form-control" name="codigo_produto" value="<?= htmlspecialchars($codigo_produto ?? '') ?>">
          </div>
   <!-- Categoria -->
<div  class="col-md-3">
  <label for="categoria_produto" class="form-label">Categoria:</label>
  <select name="categoria_produto" id="categoria_produto" class="form-select">
    <option value="">Todas</option>
    <?php
    // ==========================================
    // BATALHÕES PERMITIDOS (MESMO PADRÃO DA LISTAGEM DE FICHAS)
    // ==========================================
    $id_om_usuario = $_SESSION['usuario']['batalhao'] ?? 0;

    // Busca o nível do usuário
    $sqlNivel = "SELECT nivel FROM organizacoes_militares WHERE id = $id_om_usuario";
    $resNivel = $conexao->query($sqlNivel);
    $nivelUsuario = $resNivel->fetch_assoc()['nivel'] ?? 0;

    // Monta lista de OMs que o usuário pode visualizar
    if ($nivelUsuario == 1) {
        $sqlBatalhoes = "SELECT id FROM organizacoes_militares";
    } else {
        $sqlBatalhoes = "
            SELECT om.id
            FROM organizacoes_militares om
            WHERE om.id = $id_om_usuario
            OR om.id IN (
                SELECT id_om_menor FROM organizacoes_militares_sub WHERE id_om_maior = $id_om_usuario
            )
        ";
    }
    $resBatalhoes = $conexao->query($sqlBatalhoes);
    $batalhoesPermitidos = [];
    while ($bat = $resBatalhoes->fetch_assoc()) {
        $batalhoesPermitidos[] = (int)$bat['id'];
    }

    if (!empty($batalhoesPermitidos)) {
        // Cria placeholders (?) para o bind
        $placeholders = implode(',', array_fill(0, count($batalhoesPermitidos), '?'));
        $tipos = str_repeat('i', count($batalhoesPermitidos));

        // Consulta categorias dentro dos batalhões permitidos
        $stmt = $conexao->prepare("SELECT DISTINCT categoria_produto FROM almox_produtos WHERE batalhao IN ($placeholders) ORDER BY categoria_produto ASC");
        $stmt->bind_param($tipos, ...$batalhoesPermitidos);
        $stmt->execute();
        $res = $stmt->get_result();

        while ($row = $res->fetch_assoc()):
    ?>
      <option value="<?= htmlspecialchars($row['categoria_produto']) ?>" <?= (isset($_GET['categoria_produto']) && $_GET['categoria_produto'] === $row['categoria_produto']) ? 'selected' : '' ?>>
        <?= htmlspecialchars($row['categoria_produto']) ?>
      </option>
    <?php
        endwhile;
        $stmt->close();
    } else {
        echo '<option value="">Nenhum batalhão disponível</option>';
    }
    ?>
  </select>
</div>
          <div class="col-12 d-flex justify-content-between mt-2">
            <button type="button" id="btnLimparFiltrosAlmox" class="btn btn-black ms-2">Limpar Filtros</button>
            <button type="submit" class="btn btn-primary px-4">Aplicar</button>
          </div>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Select de limite por página -->
<div class="mb-3">
  <label for="limiteAlmox" class="me-2 mb-0">Mostrar</label>
  <select id="limiteAlmox" name="limite" class="form-select d-inline w-auto" onchange="atualizarLimiteAlmox()">
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
<div class="paginacao">
  <?= renderPaginacaoAlmox($pagina, $totalPaginas, $limite, $queryString, 'includes/almox_produtos/listagem.php'); ?>
</div>

<!-- Lista de Produtos -->
<!-- Lista de Produtos -->
<div class="card border-0 shadow-sm">
  <div class="card-body">
    <?php if ($result->num_rows > 0): ?>
      <div class="list-group">
        <?php while ($prod = $result->fetch_assoc()): ?>
          <div class="border rounded-4 p-4 mb-4 bg-white shadow-sm hover-shadow transition-all">
            <!-- Cabeçalho -->
            <div class="d-flex justify-content-between align-items-start mb-3">
              <div>
                <h5 class="fw-bold text-primary mb-1">
                  <i class="fas fa-box me-2 text-secondary"></i>
                  <?= htmlspecialchars($prod['nome_produto']) ?>
                </h5>
                <div class="text-muted small">
                  Código: <span class="fw-semibold"><?= htmlspecialchars($prod['codigo_produto']) ?></span>
                </div>
              </div>
              <div class="text-end">
                <span class="badge bg-light text-dark border">
                  <i class="fas fa-calendar-alt me-1"></i>
                  <?= htmlspecialchars(date('d/m/Y', strtotime($prod['data_inclusao']))) ?>
                </span>
              </div>
            </div>

            <!-- Categoria e Unidade -->
            <div class="mb-3">
              <span class="badge bg-secondary me-2">
                <i class="fas fa-tag me-1"></i>
                <?= htmlspecialchars($prod['categoria_produto'] ?: 'Sem categoria') ?>
              </span>
              <span class="badge bg-info text-dark me-2">
                <i class="fas fa-ruler me-1"></i>
                <?= htmlspecialchars($prod['unidade']) ?>
              </span>
              <span class="badge bg-warning text-dark">
                <i class="fas fa-cubes me-1"></i>
                Mínimo: <?= (int)$prod['estoque_minimo'] ?>
              </span>
            </div>

            <!-- Observações -->
            <?php if (!empty($prod['obs_produto'])): ?>
              <div class="mb-3">
                <strong class="text-muted">Observação:</strong><br>
                <span class="fst-italic"><?= nl2br(htmlspecialchars($prod['obs_produto'])) ?></span>
              </div>
            <?php endif; ?>

            <!-- Batalhão -->
            <div class="mb-3">
              <strong><i class="fas fa-building me-1 text-success"></i> Organização Militar:</strong>
              <span class="text-muted">
                <?= htmlspecialchars($prod['nome_om']) ?>
                <?php if (!empty($prod['abreviatura_om'])): ?>
                  (<?= htmlspecialchars($prod['abreviatura_om']) ?>)
                <?php endif; ?>
              </span>
            </div>

            <!-- Botões -->
            <div class="d-flex gap-2 flex-wrap justify-content-end mt-3">
			<?php if($pode_editar): ?>
              <button class="btn btn-sm btn-outline-warning d-flex align-items-center"
                      onclick="editarProdutoAlmox(<?= $prod['id'] ?>)"
                      data-bs-toggle="modal"
                      data-bs-target="#modalEditarProduto">
                <i class="fas fa-edit me-1"></i> Editar
              </button>
<?php endif; ?>
			<?php if($pode_deletar): ?>
              <button type="button"
                      class="btn btn-sm btn-outline-danger d-flex align-items-center"
                      data-id="<?= $prod['id'] ?>"
                      onclick="deletarPRODUTO(this)"
                      data-bs-toggle="tooltip"
                      title="Excluir produto">
                <i class="fas fa-trash-alt me-1"></i> Excluir
              </button>
				<?php endif; ?>
            </div>
          </div>
        <?php endwhile; ?>
      </div>
    <?php else: ?>
      <div class="alert alert-light border text-center py-4">
        <i class="fas fa-info-circle me-2 text-muted"></i> Nenhum produto encontrado.
      </div>
    <?php endif; ?>
  </div>
</div>


<!-- Paginação inferior -->
<div class="paginacao">
  <?= renderPaginacaoAlmox($pagina, $totalPaginas, $limite, $queryString, 'includes/almox_produtos/listagem.php'); ?>
</div>








<!-- Modal de Cadastro de Produto -->
<div class="modal fade" id="modalCadastroProduto" tabindex="-1" aria-labelledby="modalLabelProduto" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="modalLabelProduto">Cadastrar Produto</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
      </div>
      <div class="modal-body">
       <form method="POST" enctype="multipart/form-data" id="form-cadastrar-produtos">
   <div class="mb-3">
    <label class="form-label fw-semibold">Batalhão</label>
    <select name="batalhao" class="form-select">
        <?php foreach ($oms_visiveis as $id => $nome): 
            $sel = ($batalhao_filtro == $id) ? 'selected' : '';
        ?>
            <option value="<?= $id ?>" <?= $sel ?>><?= htmlspecialchars($nome) ?></option>
        <?php endforeach; ?>
    </select>
</div>
<div class="mb-3">
              <label for="data_inclusao" class="form-label">Data de inclusão</label>
              <input type="date" class="form-control" id="data_inclusao" name="data_inclusao" required>
            </div>
          <div class="mb-3">
            <label for="nome_produto" class="form-label">Nome do Produto</label>
            <input type="text" class="form-control" id="nome_produto" name="nome_produto" required>
          </div>

          <div class="mb-3">
            <label for="codigo_produto" class="form-label">Código do Produto</label>
            <input type="text" class="form-control" id="codigo_produto" name="codigo_produto" required>
          </div>

          <div class="mb-3">
            <label for="categoria_produto" class="form-label">Categoria</label>
            <select class="form-select rounded-pill shadow-sm" id="categoria_produto" name="categoria_produto" required>
              <option value="" disabled selected>Selecione a categoria</option>
              <option value="Peças">Peças</option>
              <option value="Filtros">Filtros</option>
              <option value="Lubrificantes">Lubrificantes</option>
              <option value="Ferramental">Ferramental</option>
              <option value="Baterias">Baterias</option>
              <option value="Vidros">Vidros</option>
              <option value="Vidros">Vidros</option>
              <option value="Serviço">Serviço</option>
              <option value="Materiais diversos">Materiais diversos</option>
            </select>
          </div>


          <div class="mb-3">
            <label for="estoque_minimo" class="form-label">Estoque Mínimo</label>
            <input type="number" class="form-control" id="estoque_minimo" name="estoque_minimo" required>
          </div>
           
           <div class="mb-3">
            <label for="estoque_minimo" class="form-label">Unidade de medida</label>
            <input type="text" class="form-control" id="unidade" name="unidade" required>
          </div>

          <div class="mb-3">
            <label for="obs_produto" class="form-label">Observações</label>
            <textarea class="form-control" id="obs_produto" name="obs_produto" rows="3"></textarea>
          </div>

          <button type="submit" class="btn btn-success">Cadastrar Produto</button>
          <input type="hidden" id="edit-id" name="id">
        </form>
      </div>
    </div>
  </div>
</div>

      
      <!-- Modal de Edição de Produto -->
<div class="modal fade" id="modalEditarProduto" tabindex="-1" aria-labelledby="modalEditarProdutoLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="modalEditarProdutoLabel">Editar Produto</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
      </div>
      <div class="modal-body">
        <form method="POST" enctype="multipart/form-data" id="form-editar-produto">
               <div class="mb-3">
    <label class="form-label fw-semibold">Batalhão</label>
                   <input type="text" class="form-control" id="nome_batalhao_edit" name="batalhao" readonly>
</div>
<div class="mb-3">
              <label for="data_inclusao_edit" class="form-label">Data de inclusão</label>
              <input type="date" class="form-control" id="data_inclusao_edit" name="data_inclusao" required>
            </div>
          <div class="mb-3">
            <label for="nome_produto_edit" class="form-label">Nome do Produto</label>
            <input type="text" class="form-control" id="nome_produto_edit" name="nome_produto" required>
          </div>

          <div class="mb-3">
            <label for="codigo_produto_edit" class="form-label">Código do Produto</label>
            <input type="text" class="form-control" id="codigo_produto_edit" name="codigo_produto"  readonly>
          </div>

          <div class="mb-3">
            <label for="categoria_produto_edit" class="form-label">Categoria</label>
            <select class="form-select rounded-pill shadow-sm" id="categoria_produto_edit" name="categoria_produto" required>
              <option value="" disabled selected>Selecione a categoria</option>
              <option value="Peças">Peças</option>
              <option value="Filtros">Filtros</option>
              <option value="Lubrificantes">Lubrificantes</option>
              <option value="Ferramental">Ferramental</option>
              <option value="Baterias">Baterias</option>
              <option value="Vidros">Vidros</option>
              <option value="Vidros">Vidros</option>
              <option value="Serviço">Serviço</option>
              <option value="Materiais diversos">Materiais diversos</option>
            </select>
          </div>


          <div class="mb-3">
            <label for="estoque_minimo_edit" class="form-label">Estoque Mínimo</label>
            <input type="number" class="form-control" id="estoque_minimo_edit" name="estoque_minimo" required>
          </div>
            
            
          <div class="mb-3">
            <label for="unidade_edit" class="form-label">Unidade de medida</label>
            <input type="text" class="form-control" id="unidade_edit" name="unidade" required>
          </div>

          <div class="mb-3">
            <label for="obs_produto_edit" class="form-label">Observações</label>
            <textarea class="form-control" id="obs_produto_edit" name="obs_produto" rows="3"></textarea>
          </div>

          <button type="button" class="btn btn-danger" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-success">Salvar Alterações</button>
          <input type="hidden" id="edit-id-produto" name="id">
        </form>
      </div>
    </div>
  </div>
</div>

<!-- Script da página de Cadastro de Fornecedores -->
<script>
    window.funcaoInicializacao = 'inicializarAlmoxProdutos';
    
    
</script>
