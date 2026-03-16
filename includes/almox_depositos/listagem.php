<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
header('Content-Type: text/html; charset=utf-8');
session_start();
require_once '../../conexao/config.php';

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
            SELECT id_om_menor 
            FROM organizacoes_militares_sub 
            WHERE id_om_maior = $id_om_usuario
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
$nome_deposito    = $_GET['nome_deposito'] ?? '';
$local_deposito   = $_GET['local_deposito'] ?? '';
$observacoes      = $_GET['observacoes_deposito'] ?? '';
$batalhaoFiltro   = $_GET['batalhao'] ?? '';

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
$params  = [];
$tipos   = '';

// Campos dinâmicos
$campos = [
    'd.id'                 => 'id',
    'd.nome_deposito'      => 'nome_deposito',
    'd.local_deposito'     => 'local_deposito',
    'd.observacoes_deposito' => 'observacoes_deposito'
];

foreach ($campos as $coluna => $parametro) {
    if (!empty($_GET[$parametro])) {
        $valor = $_GET[$parametro];

        if ($parametro === 'id') {
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
// FILTRO DE BATALHÃO
// ============================
if (!empty($batalhaoFiltro) && in_array((int)$batalhaoFiltro, $batalhoesPermitidos)) {
    $filtros[] = "d.batalhao = ?";
    $params[] = (int)$batalhaoFiltro;
    $tipos .= 'i';
} else {
    $placeholders = implode(',', array_fill(0, count($batalhoesPermitidos), '?'));
    $filtros[] = "d.batalhao IN ($placeholders)";
    foreach ($batalhoesPermitidos as $batId) {
        $params[] = $batId;
    }
    $tipos .= str_repeat('i', count($batalhoesPermitidos));
}

$whereSQL = !empty($filtros) ? 'WHERE ' . implode(' AND ', $filtros) : '';

// ============================
// CONTAGEM TOTAL
// ============================
$sqlTotal = "SELECT COUNT(*) AS total FROM almox_depositos d $whereSQL";
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
        d.id,
        d.batalhao,
        d.nome_deposito,
        d.local_deposito,
        d.observacoes_deposito,
        om.nome AS nome_om,
        om.abreviatura AS abreviatura_om
    FROM almox_depositos d
    LEFT JOIN organizacoes_militares om ON d.batalhao = om.id
    $whereSQL
    ORDER BY d.id DESC
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
// FUNÇÃO DE PAGINAÇÃO
// ============================
function renderPaginacaoAlmox(
    $pagina,
    $totalPaginas,
    $limite,
    $queryString,
    $arquivo = 'includes/almox_depositos/listagem.php'
) {
    $html = '<div class="pagination-wrapper">';
    $html .= '<nav><ul class="pagination pagination-sm justify-content-center">';

    if ($totalPaginas > 1) {
        for ($i = 1; $i <= $totalPaginas; $i++) {
            $ativo = $i == $pagina ? 'active' : '';
            $url = "{$arquivo}?{$queryString}&pagina=$i&limite=$limite";
            $html .= "
                <li class='page-item $ativo'>
                    <a class='page-link paginacao-almox' href='#' data-page='{$url}'>$i</a>
                </li>
            ";
        }
    }

    $html .= '</ul></nav></div>';
    return $html;
}

// ============================
// MANTÉM FILTROS NA PAGINAÇÃO
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
        <h3 class="fw-bold mb-1">Listagem dos Depósitos do Almox Peças</h3>
        <h6 class="text-muted">Depósitos cadastrados</h6>
      </div>
        <div>
        <button type="button"
        class="btn btn-primary"
        data-bs-toggle="modal"
        data-bs-target="#modalCadastroDeposito">
  <i class="fa fa-warehouse me-1"></i> Cadastrar Depósito
</button>

    
      </div>
    </div>
   <!-- Botão para mostrar/ocultar filtros -->
<div class="mb-3">
  <button class="btn btn-outline-primary w-100 d-flex justify-content-center align-items-center"
          onclick="toggleFiltrosALMOX()">
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
        $sql = "SELECT id, nome, abreviatura FROM organizacoes_militares ORDER BY nome";
        $res = $conexao->query($sql);
        while ($r = $res->fetch_assoc()) {
            $oms[$r['id']] = $r['abreviatura'] ?: $r['nome'];
        }
    } elseif ($nivel_usuario == 2) {
        $sql = "
            SELECT id, nome, abreviatura
            FROM organizacoes_militares
            WHERE id = ? 
               OR id IN (
                    SELECT id_om_menor 
                    FROM organizacoes_militares_sub 
                    WHERE id_om_maior = ?
               )
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
$batalhao_filtro = $_GET['batalhao'] ?? '';
?>

          <!-- Batalhão -->
          <div class="col-md-3">
            <label class="form-label fw-semibold">Batalhão</label>
            <select name="batalhao" class="form-select">
              <option value="">Todos</option>
              <?php foreach ($oms_visiveis as $id => $nome): ?>
                <option value="<?= $id ?>" <?= ($batalhao_filtro == $id) ? 'selected' : '' ?>>
                  <?= htmlspecialchars($nome) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <!-- Nome do Depósito -->
          <div class="col-md-3">
            <label class="form-label fw-semibold">Nome do Depósito</label>
            <input type="text"
                   class="form-control"
                   name="nome_deposito"
                   value="<?= htmlspecialchars($_GET['nome_deposito'] ?? '') ?>">
          </div>

          <!-- Local do Depósito -->
          <div class="col-md-3">
            <label class="form-label fw-semibold">Local</label>
            <input type="text"
                   class="form-control"
                   name="local_deposito"
                   value="<?= htmlspecialchars($_GET['local_deposito'] ?? '') ?>">
          </div>

          <!-- Observações -->
          <div class="col-md-3">
            <label class="form-label fw-semibold">Observações</label>
            <input type="text"
                   class="form-control"
                   name="observacoes_deposito"
                   value="<?= htmlspecialchars($_GET['observacoes_deposito'] ?? '') ?>">
          </div>

          <!-- Botões -->
          <div class="col-12 d-flex justify-content-between mt-2">
            <button type="button"
                    id="btnLimparFiltrosAlmox"
                    class="btn btn-black ms-2">
              Limpar Filtros
            </button>
            <button type="submit"
                    class="btn btn-primary px-4">
              Aplicar
            </button>
          </div>

        </div>
      </form>
    </div>
  </div>
</div>

<!-- Select de limite por página -->
<div class="mb-3">
  <label for="limiteAlmox" class="me-2 mb-0">Mostrar</label>
  <select id="limiteAlmox"
          name="limite"
          class="form-select d-inline w-auto"
          onchange="atualizarLimiteAlmox()">
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

<!-- Lista de Depósitos -->
<div class="card border-0 shadow-sm">
  <div class="card-body">
    <?php if ($result->num_rows > 0): ?>
      <div class="list-group">
        <?php while ($dep = $result->fetch_assoc()): ?>
          <div class="border rounded-4 p-4 mb-4 bg-white shadow-sm hover-shadow transition-all">

            <!-- Cabeçalho -->
            <div class="d-flex justify-content-between align-items-start mb-3">
              <div>
                <h5 class="fw-bold text-primary mb-1">
                  <i class="fas fa-warehouse me-2 text-secondary"></i>
                  <?= htmlspecialchars($dep['nome_deposito']) ?>
                </h5>
                <?php if (!empty($dep['local_deposito'])): ?>
                  <div class="text-muted small">
                    <i class="fas fa-map-marker-alt me-1"></i>
                    <?= htmlspecialchars($dep['local_deposito']) ?>
                  </div>
                <?php endif; ?>
              </div>

              <div class="text-end">
                <span class="badge bg-light text-dark border">
                  <i class="fas fa-hashtag me-1"></i>
                  ID <?= (int)$dep['id'] ?>
                </span>
              </div>
            </div>

            <!-- Observações -->
            <?php if (!empty($dep['observacoes_deposito'])): ?>
              <div class="mb-3">
                <strong class="text-muted">Observações:</strong><br>
                <span class="fst-italic">
                  <?= nl2br(htmlspecialchars($dep['observacoes_deposito'])) ?>
                </span>
              </div>
            <?php endif; ?>

            <!-- Organização Militar -->
            <div class="mb-3">
              <strong>
                <i class="fas fa-building me-1 text-success"></i>
                Organização Militar:
              </strong>
              <span class="text-muted">
                <?= htmlspecialchars($dep['nome_om']) ?>
                <?php if (!empty($dep['abreviatura_om'])): ?>
                  (<?= htmlspecialchars($dep['abreviatura_om']) ?>)
                <?php endif; ?>
              </span>
            </div>

            <!-- Botões -->
            <div class="d-flex gap-2 flex-wrap justify-content-end mt-3">
              <button class="btn btn-sm btn-outline-warning d-flex align-items-center"
                      onclick="editarDepositoAlmox(<?= (int)$dep['id'] ?>)"
                      data-bs-toggle="modal"
                      data-bs-target="#modalEditarDeposito">
                <i class="fas fa-edit me-1"></i> Editar
              </button>

              <button type="button"
        class="btn btn-sm btn-outline-danger d-flex align-items-center btn-deletar-deposito"
        data-id="<?= $dep['id'] ?>"
        onclick="deletarDEPOSITO(this)"
        data-bs-toggle="tooltip"
        title="Excluir depósito">
  <i class="fas fa-trash-alt me-1"></i> Excluir
</button>

            </div>

          </div>
        <?php endwhile; ?>
      </div>
    <?php else: ?>
      <div class="alert alert-light border text-center py-4">
        <i class="fas fa-info-circle me-2 text-muted"></i>
        Nenhum depósito encontrado.
      </div>
    <?php endif; ?>
  </div>
</div>



<!-- Paginação inferior -->
<div class="paginacao">
  <?= renderPaginacaoAlmox($pagina, $totalPaginas, $limite, $queryString, 'includes/almox_produtos/listagem.php'); ?>
</div>

      
      <!-- Modal de Cadastro de Depósito -->
<div class="modal fade"
     id="modalCadastroDeposito"
     tabindex="-1"
     aria-labelledby="modalLabelDeposito"
     aria-hidden="true">

  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">

      <!-- Cabeçalho -->
      <div class="modal-header">
        <h5 class="modal-title" id="modalLabelDeposito">
          <i class="fas fa-warehouse me-2"></i> Cadastrar Depósito
        </h5>
        <button type="button"
                class="btn-close"
                data-bs-dismiss="modal"
                aria-label="Fechar"></button>
      </div>

      <!-- Corpo -->
      <div class="modal-body">
        <form method="POST"
              id="form-cadastrar-deposito">

          <!-- Batalhão -->
          <div class="mb-3">
            <label class="form-label fw-semibold">Batalhão</label>
            <select name="batalhao" class="form-select" required>
              <?php foreach ($oms_visiveis as $id => $nome): ?>
                <option value="<?= $id ?>">
                  <?= htmlspecialchars($nome) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <!-- Nome do Depósito -->
          <div class="mb-3">
            <label for="nome_deposito" class="form-label fw-semibold">
              Nome do Depósito
            </label>
            <input type="text"
                   class="form-control"
                   id="nome_deposito"
                   name="nome_deposito"
                   required>
          </div>

          <!-- Local do Depósito -->
          <div class="mb-3">
            <label for="local_deposito" class="form-label fw-semibold">
              Local do Depósito
            </label>
            <input type="text"
                   class="form-control"
                   id="local_deposito"
                   name="local_deposito">
          </div>

          <!-- Observações -->
          <div class="mb-3">
            <label for="observacoes_deposito" class="form-label fw-semibold">
              Observações
            </label>
            <textarea class="form-control"
                      id="observacoes_deposito"
                      name="observacoes_deposito"
                      rows="3"></textarea>
          </div>

          <!-- Ações -->
          <div class="d-flex justify-content-end gap-2 mt-4">
            <button type="button"
                    class="btn btn-secondary"
                    data-bs-dismiss="modal">
              Cancelar
            </button>

            <button type="submit"
                    class="btn btn-success">
              <i class="fas fa-save me-1"></i> Cadastrar Depósito
            </button>
          </div>

          <!-- Hidden para edição futura -->
          <input type="hidden" id="edit-id-deposito" name="id">

        </form>
      </div>

    </div>
  </div>
</div>
      <!-- Modal de Edição de Depósitos -->
<div class="modal fade" id="modalEditarDeposito" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">

      <form id="form-editar-deposito">

        <div class="modal-header">
          <h5 class="modal-title">Editar Depósito</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>

        <div class="modal-body">

          <input type="hidden" name="id" id="edit-id-deposito">

          <div class="row g-3">

            <div class="col-md-6">
              <label class="form-label">Batalhão</label>
              <input type="text" class="form-control" id="nome_batalhao_edit" disabled>
              <input type="hidden" name="batalhao">
            </div>

            <div class="col-md-6">
              <label class="form-label">Nome do Depósito</label>
              <input type="text" class="form-control" name="nome_deposito" required>
            </div>

            <div class="col-md-6">
              <label class="form-label">Local do Depósito</label>
              <input type="text" class="form-control" name="local_deposito">
            </div>

            <div class="col-md-6">
              <label class="form-label">Observações</label>
              <textarea class="form-control" name="observacoes_deposito"></textarea>
            </div>

          </div>

        </div>

        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-primary">Salvar alterações</button>
        </div>

      </form>

    </div>
  </div>
</div>


<!-- Script da página de Cadastro de Fornecedores -->
<script>
    window.funcaoInicializacao = 'inicializarAlmoxDepositos';
    
    
</script>
