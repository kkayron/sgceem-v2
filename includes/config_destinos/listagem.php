<?php
header('Content-Type: text/html; charset=utf-8');
session_start();
require_once '../../conexao/config.php';

require_once '../api/seguranca.php';

$permissoes = verificarPermissao([35]);

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
$id         = $_GET['id'] ?? '';
$destino    = $_GET['destino'] ?? '';
$batalhaoFiltro = $_GET['batalhao'] ?? '';

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

if (!empty($id)) {
    $filtros[] = "d.id = ?";
    $params[] = (int)$id;
    $tipos .= 'i';
}

if (!empty($destino)) {
    $filtros[] = "d.destino LIKE ?";
    $params[] = "%$destino%";
    $tipos .= 's';
}

// ============================
// FILTRO DE BATALHÃO (respeitando permissões)
// ============================
if (!empty($batalhaoFiltro) && in_array((int)$batalhaoFiltro, $batalhoesPermitidos)) {
    $filtros[] = "d.batalhao = ?";
    $params[] = (int)$batalhaoFiltro;
    $tipos .= 'i';
} else {
    // Aplica limitação apenas aos batalhões que o usuário pode ver
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
$sqlTotal = "SELECT COUNT(*) as total FROM config_destinos d $whereSQL";
$stmtTotal = $conexao->prepare($sqlTotal);
if ($tipos !== '') {
    $stmtTotal->bind_param($tipos, ...$params);
}
$stmtTotal->execute();
$resultTotal = $stmtTotal->get_result();
$totalRegistros = $resultTotal->fetch_assoc()['total'] ?? 0;
$stmtTotal->close();

$totalPaginas = ceil($totalRegistros / $limite);

// ============================
// CONSULTA PRINCIPAL
// ============================
$sql = "
    SELECT 
        d.id,
        d.batalhao,
        d.destino,
        om.nome AS nome_om,
        om.abreviatura AS abreviatura_om
    FROM config_destinos d
    LEFT JOIN organizacoes_militares om ON d.batalhao = om.id
    $whereSQL
    ORDER BY d.id DESC, d.destino ASC
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
// Função de paginação
// ============================
function renderPaginacaoDestinos($pagina, $totalPaginas, $limite, $queryString, $arquivo = 'includes/config_destinos/listagem.php') {
    $html = '<div class="pagination-wrapper mt-3">';
    $html .= '<nav><ul class="pagination pagination-sm justify-content-center">';
    if ($totalPaginas > 1) {
        for ($i = 1; $i <= $totalPaginas; $i++) {
            $ativo = $i == $pagina ? 'active' : '';
            $url = "{$arquivo}?{$queryString}&pagina=$i&limite=$limite";
            $html .= "<li class='page-item $ativo'><a class='page-link paginacao-destinos' href='#' data-page='{$url}'>$i</a></li>";
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
        <h3 class="fw-bold mb-1">Listagem dos Destinos</h3>
        <h6 class="text-muted">Produtos cadastrados</h6>
      </div>
        <div>
			<?php if($pode_cadastrar): ?>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalCadastroDestino">
          <i class="fa fa-user-plus me-1"></i> Cadastrar Destino
        </button>
    <?php endif; ?>
      </div>
    </div>
  <!-- Botão para mostrar/ocultar filtros -->
<div class="mb-3">
  <button class="btn btn-outline-primary w-100 d-flex justify-content-center align-items-center" onclick="toggleFiltrosDestinos()">
    <i class="fas fa-search me-2"></i> Filtros
  </button>
</div>

<!-- Filtros -->
<div id="filtros-container-destinos" style="display: none;" class="mb-3">
  <div class="card shadow-sm border-0">
    <div class="card-body">
      <form method="GET" id="filtroDestinosForm">
        <div class="row g-3">
          <?php
          // ======================================
          // 🔹 Recupera OMs visíveis para o usuário
          // ======================================
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
                      SELECT id, nome, abreviatura FROM organizacoes_militares
                      WHERE id = ? OR id IN (
                          SELECT id_om_menor FROM organizacoes_militares_sub WHERE id_om_maior = ?
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
          $destino_filtro = $_GET['destino'] ?? '';
          $id_filtro = $_GET['id'] ?? '';
          ?>

          <!-- Batalhão -->
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

          <!-- Nome do Destino -->
          <div class="col-md-4">
            <label class="form-label fw-semibold">Nome do Destino</label>
            <input type="text" class="form-control" name="destino" value="<?= htmlspecialchars($destino_filtro) ?>" placeholder="Ex: Companhia de Engenharia">
          </div>

          <!-- ID -->
          <div class="col-md-2">
            <label class="form-label fw-semibold">ID</label>
            <input type="number" class="form-control" name="id" value="<?= htmlspecialchars($id_filtro) ?>" placeholder="Ex: 12">
          </div>


          <!-- Botões -->
          <div class="col-12 d-flex justify-content-between mt-2">
            <button type="button" id="btnLimparFiltrosDestinos" class="btn btn-black ms-2">Limpar Filtros</button>
            <button type="submit" class="btn btn-primary px-4">Aplicar</button>
          </div>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Select de limite por página -->
<div class="mb-3">
  <label for="limiteDestinos" class="me-2 mb-0">Mostrar</label>
  <select id="limiteDestinos" name="limite" class="form-select d-inline w-auto" onchange="atualizarLimiteDestinos()">
    <?php
    $limiteAtual = $_GET['limite'] ?? 10;
    foreach ([1, 5, 10, 25, 50, 100] as $opcao) {
      $selected = ($limiteAtual == $opcao) ? 'selected' : '';
      echo "<option value=\"$opcao\" $selected>$opcao</option>";
    }
    ?>
  </select>
  <span class="ms-2">por página</span>
</div>


<!-- LISTA DE DESTINOS -->
<div class="card border-0 shadow-sm">
  <div class="card-body">
    <?php if ($result->num_rows > 0): ?>
      <div class="list-group">
        <?php while ($dest = $result->fetch_assoc()): ?>
          <div class="border rounded-4 p-4 mb-4 bg-white shadow-sm hover-shadow transition-all">
            
            <!-- Cabeçalho -->
            <div class="d-flex justify-content-between align-items-start mb-3">
              <div>
                <h5 class="fw-bold text-primary mb-1">
                  <i class="fas fa-map-marker-alt me-2 text-secondary"></i>
                  <?= htmlspecialchars($dest['destino']) ?>
                </h5>
                <div class="text-muted small">
                  ID: <span class="fw-semibold"><?= htmlspecialchars($dest['id']) ?></span>
                </div>
              </div>
              <div class="text-end">
                <span class="badge bg-light text-dark border">
                  <i class="fas fa-building me-1"></i>
                  <?= htmlspecialchars($dest['abreviatura_om'] ?: $dest['nome_om'] ?: 'Sem identificação') ?>
                </span>
              </div>
            </div>

            <!-- Batalhão -->
            <div class="mb-3">
              <strong class="text-muted"><i class="fas fa-flag me-1 text-success"></i> Organização Militar:</strong>
              <span class="text-dark fw-semibold">
                <?= htmlspecialchars($dest['nome_om'] ?: 'Não informado') ?>
                <?php if (!empty($dest['abreviatura_om'])): ?>
                  (<?= htmlspecialchars($dest['abreviatura_om']) ?>)
                <?php endif; ?>
              </span>
            </div>

            <!-- Botões -->
            <div class="d-flex gap-2 flex-wrap justify-content-end mt-3">
				
			<?php if($pode_editar): ?>
              <button class="btn btn-sm btn-outline-warning d-flex align-items-center"
                      onclick="editarDestino(<?= $dest['id'] ?>)"
                      data-bs-toggle="modal"
                      data-bs-target="#modalEditarDestino">
                <i class="fas fa-edit me-1"></i> Editar
              </button>
				<?php endif; ?>
			<?php if($pode_deletar): ?>
             <button type="button"
    class="btn btn-sm btn-outline-danger d-flex align-items-center btn-deletar-destino"
    data-id="<?= $dest['id'] ?>"
    onclick="deletarDestino(this)">
  <i class="fas fa-trash-alt me-1"></i> Excluir
</button>
				<?php endif; ?>
            </div>
          </div>
        <?php endwhile; ?>
      </div>

      <?= renderPaginacaoDestinos($pagina, $totalPaginas, $limite, $queryString) ?>

    <?php else: ?>
      <div class="alert alert-light border text-center py-4">
        <i class="fas fa-info-circle me-2 text-muted"></i> Nenhum destino encontrado.
      </div>
    <?php endif; ?>
  </div>
</div>
<?php if($pode_cadastrar): ?>
<!-- Modal de Cadastro de Destino -->
<div class="modal fade" id="modalCadastroDestino" tabindex="-1" aria-labelledby="modalCadastroDestinoLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow-lg">
      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title" id="modalCadastroDestinoLabel">
          <i class="fa fa-user-plus me-2"></i> Cadastrar Destino
        </h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Fechar"></button>
      </div>

      <form id="formCadastroDestino">
        <div class="modal-body">
          <!-- Seleção do Batalhão -->
          <div class="mb-3">
            <label for="batalhao" class="form-label fw-semibold">Batalhão <span class="text-danger">*</span></label>
            <select id="batalhao" name="batalhao" class="form-select" required>
              <option value="">Selecione...</option>
              <?php
              // ========== LISTA DE BATALHÕES VISÍVEIS ==========
              $id_om_usuario = $_SESSION['usuario']['batalhao'] ?? null;
              $nivel_usuario = $_SESSION['usuario']['nivel'] ?? 3;

              function getOMsVisiveisModal($conexao, $id_om_usuario, $nivel_usuario) {
                  $oms = [];

                  if ($nivel_usuario == 1) {
                      $sql = "SELECT id, nome, abreviatura FROM organizacoes_militares ORDER BY nome";
                      $res = $conexao->query($sql);
                      while ($r = $res->fetch_assoc()) {
                          $oms[$r['id']] = $r['abreviatura'] ?: $r['nome'];
                      }
                  } elseif ($nivel_usuario == 2) {
                      $sql = "
                          SELECT id, nome, abreviatura FROM organizacoes_militares
                          WHERE id = ? OR id IN (
                              SELECT id_om_menor FROM organizacoes_militares_sub WHERE id_om_maior = ?
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

              $oms_visiveis = getOMsVisiveisModal($conexao, $id_om_usuario, $nivel_usuario);

              foreach ($oms_visiveis as $id => $nome):
              ?>
                <option value="<?= $id ?>"><?= htmlspecialchars($nome) ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <!-- Nome do Destino -->
          <div class="mb-3">
            <label for="destino" class="form-label fw-semibold">Nome do Destino <span class="text-danger">*</span></label>
            <input type="text" class="form-control" id="destino" name="destino" placeholder="Ex: Seção de Transporte" required>
          </div>
        </div>

        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-primary">
            <i class="fa fa-save me-1"></i> Salvar
          </button>
        </div>
      </form>
    </div>
  </div>
</div>
	  <?php endif; ?>
	  <?php if($pode_editar): ?>
<!-- Modal Editar Destino -->
<div class="modal fade" id="modalEditarDestino" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">

      <form id="formEditarDestino">
        <div class="modal-header">
          <h5 class="modal-title">Editar Destino</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>

        <div class="modal-body">

          <input type="hidden" id="id_destino_editar" name="id_destino">

          <div class="mb-3">
            <label class="form-label fw-semibold">Batalhão</label>
            <select name="batalhao" id="batalhao_destino_editar" class="form-select" required>
              <option value="">Selecione...</option>

              <?php
              foreach ($oms_visiveis as $id => $nome):
              ?>
                <option value="<?= $id ?>"><?= htmlspecialchars($nome) ?></option>
              <?php endforeach; ?>

            </select>
          </div>

          <div class="mb-3">
            <label class="form-label fw-semibold">Destino</label>
            <input type="text" class="form-control" name="destino" id="nome_destino_editar" required>
          </div>

        </div>

        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-primary">Salvar Alterações</button>
        </div>
      </form>

    </div>
  </div>
</div>

	  <?php endif; ?>

<!-- Script da página de Cadastro de Fornecedores -->
<script>
    window.funcaoInicializacao = 'inicializarDestinos';
    
    
</script>
