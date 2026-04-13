<?php
header('Content-Type: text/html; charset=utf-8');
session_start();
include_once('../../conexao/config.php');

require_once '../api/seguranca.php';

$permissoes = verificarPermissao(50);

$pode_cadastrar = $permissoes['cadastrar'];
$pode_editar    = $permissoes['editar'];
$pode_deletar   = $permissoes['deletar'];
$pode_importar  = $permissoes['importar'];

// ======================================================
// IDENTIFICAÇÃO DO USUÁRIO E SUAS OMs VISÍVEIS
// ======================================================
$id_om_usuario = $_SESSION['usuario']['batalhao'] ?? 0;
$nivel_usuario = $_SESSION['usuario']['nivel'] ?? 0;

// Monta lista de OMs/batalhões permitidos
$idsPermitidos = [];

if ($id_om_usuario) {
    $idsPermitidos[] = (int)$id_om_usuario;
}

// Se não for nível 1 (admin), buscar subordinadas
if ($nivel_usuario != 1 && $id_om_usuario) {
    $sqlSub = "SELECT id_om_menor FROM organizacoes_militares_sub WHERE id_om_maior = ?";
    $stmtSub = $conexao->prepare($sqlSub);
    $stmtSub->bind_param('i', $id_om_usuario);
    $stmtSub->execute();
    $resSub = $stmtSub->get_result();
    while ($r = $resSub->fetch_assoc()) {
        $idsPermitidos[] = (int)$r['id_om_menor'];
    }
    $stmtSub->close();
} else {
    // Nível 1 pode ver todos
    $resAll = $conexao->query("SELECT id FROM organizacoes_militares");
    while ($r = $resAll->fetch_assoc()) {
        $idsPermitidos[] = (int)$r['id'];
    }
}

$idsPermitidos = array_unique($idsPermitidos);
$idsPermitidosStr = implode(',', $idsPermitidos);

// ======================================================
// FILTROS GET
// ======================================================
$batalhao        = $_GET['batalhao'] ?? '';
$nota_empenho    = $_GET['nota_empenho'] ?? '';
$nota_fiscal     = $_GET['nota_fiscal'] ?? '';
$nome_fornecedor = $_GET['nome_fornecedor'] ?? '';
$data_entrada    = $_GET['data_entrada'] ?? '';
$deposito_id = $_GET['deposito_id'] ?? '';

// ======================================================
// FILTROS DINÂMICOS (SEMPRE COM ALIAS e.)
// ======================================================
$filtros = [];
$params  = [];
$tipos   = '';

// Restringe às OMs autorizadas
if (!empty($idsPermitidosStr)) {
    $filtros[] = "e.batalhao IN ($idsPermitidosStr)";
}

if (!empty($batalhao)) {
    $filtros[] = "e.batalhao = ?";
    $params[]  = (int)$batalhao;
    $tipos    .= 'i';
}

if (!empty($deposito_id)) {
    $filtros[] = "e.deposito_id = ?";
    $params[]  = (int)$deposito_id;
    $tipos    .= 'i';
}

if (!empty($nota_empenho)) {
    $filtros[] = "e.nota_empenho LIKE ?";
    $params[]  = "%$nota_empenho%";
    $tipos    .= 's';
}

if (!empty($nota_fiscal)) {
    $filtros[] = "e.nota_fiscal LIKE ?";
    $params[]  = "%$nota_fiscal%";
    $tipos    .= 's';
}

if (!empty($nome_fornecedor)) {
    $filtros[] = "e.nome_fornecedor LIKE ?";
    $params[]  = "%$nome_fornecedor%";
    $tipos    .= 's';
}

if (!empty($data_entrada)) {
    $filtros[] = "e.data_entrada = ?";
    $params[]  = $data_entrada;
    $tipos    .= 's';
}

$condicoes = '';
if (!empty($filtros)) {
    $condicoes = 'WHERE ' . implode(' AND ', $filtros);
}

// ======================================================
// PAGINAÇÃO
// ======================================================
$limite = isset($_GET['limite']) && is_numeric($_GET['limite']) ? (int)$_GET['limite'] : 10;
$pagina = isset($_GET['pagina']) && is_numeric($_GET['pagina']) && $_GET['pagina'] > 0 ? (int)$_GET['pagina'] : 1;
$offset = ($pagina - 1) * $limite;

// ======================================================
// TOTAL DE REGISTROS
// ======================================================
$sqlTotal = "
    SELECT COUNT(*) AS total
    FROM almox_entradas e
    $condicoes
";
$stmtTotal = $conexao->prepare($sqlTotal);

if (!empty($params)) {
    $stmtTotal->bind_param($tipos, ...$params);
}

$stmtTotal->execute();
$resultTotal   = $stmtTotal->get_result();
$totalRegistros = $resultTotal->fetch_assoc()['total'] ?? 0;
$totalPaginas   = (int)ceil($totalRegistros / $limite);
$stmtTotal->close();

// ======================================================
// CONSULTA PRINCIPAL
// ======================================================
$sql = "
    SELECT 
        e.*,
        om.nome AS nome_om,
        om.abreviatura AS abreviatura_om,
        d.nome_deposito
    FROM almox_entradas e
    LEFT JOIN organizacoes_militares om ON om.id = e.batalhao
    LEFT JOIN almox_depositos d ON d.id = e.deposito_id
    $condicoes
    ORDER BY e.id DESC
    LIMIT ? OFFSET ?
";

$paramsConsulta = $params;
$tiposConsulta  = $tipos . 'ii';
$paramsConsulta[] = $limite;
$paramsConsulta[] = $offset;

$stmt = $conexao->prepare($sql);
$stmt->bind_param($tiposConsulta, ...$paramsConsulta);
$stmt->execute();
$entradas = $stmt->get_result();

// ======================================================
// FUNÇÃO DE PAGINAÇÃO
// ======================================================
function renderPaginacaoEntradas($pagina, $totalPaginas, $limite, $queryString, $arquivo = 'includes/almox_entrada/listagem.php') {
    if ($totalPaginas <= 1) return '';

    $html = '<nav><ul class="pagination pagination-sm">';
    for ($i = 1; $i <= $totalPaginas; $i++) {
        $ativo = $i == $pagina ? 'active' : '';
        $url = "{$arquivo}?{$queryString}&pagina=$i&limite=$limite";
        $html .= "
            <li class='page-item $ativo'>
                <a class='page-link paginacao-entradas' href='#' data-page='{$url}'>$i</a>
            </li>
        ";
    }
    $html .= '</ul></nav>';
    return $html;
}

// ======================================================
// QUERY STRING PARA PAGINAÇÃO
// ======================================================
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
        <h3 class="fw-bold mb-1">Listagem das Entradas</h3>
        <h6 class="text-muted">Entradas de produtos no estoque</h6>
      </div>
        <div>
			<?php if ($pode_cadastrar): ?>
         <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalCadastroEntradaAlmox">
          <i class="fa fa-plus me-1"></i> Cadastrar entrada
        </button>
			
          <?php endif; ?>
    
      </div>
    </div>
   <!-- Filtros -->
<div class="mb-3">
  <button class="btn btn-outline-primary w-100 d-flex justify-content-center align-items-center" onclick="toggleFiltrosEntradas()">
    <i class="fas fa-search me-2"></i> Filtros
  </button>
</div>

<div id="filtros-container-entradas" style="display: none;" class="mb-3">
  <div class="card shadow-sm border-0">
    <div class="card-body">
      <form method="GET" id="filtroEntradasForm">
        <div class="row g-3">
<div class="col-md-3">
    <label class="form-label fw-semibold">Nota de Empenho</label>
    <select class="form-select" name="nota_empenho">
        <option value="" disabled selected>Selecione</option>
        <?php
        $empenho = $conexao->query("SELECT id, nmr_empenho FROM fin_empenhos ORDER BY id ASC");
        while ($emp = $empenho->fetch_assoc()):
        ?>
            <option value="<?= $emp['id'] ?>" <?= (isset($nota_empenho) && $nota_empenho == $emp['id']) ? 'selected' : '' ?>>
                <?= $emp['nmr_empenho'] ?>
            </option>
        <?php endwhile; ?>
		<option value="0">
                Outro Empenho
            </option>
    </select>
</div>
            <!-- Batalhão / OM -->
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
            <?php
$deposito_filtro = $_GET['deposito_id'] ?? '';

// Busca depósitos conforme batalhões visíveis
$idsOms = array_keys($oms_visiveis);
$idsOmsStr = implode(',', array_map('intval', $idsOms));

$sqlDepositos = "
    SELECT id, nome_deposito, batalhao
    FROM almox_depositos
    WHERE batalhao IN ($idsOmsStr)
    ORDER BY nome_deposito ASC
";
$resDepositos = $conexao->query($sqlDepositos);
?>
<div class="col-md-3">
    <label class="form-label fw-semibold">Depósito</label>
    <select name="deposito_id" class="form-select">
        <option value="">Todos</option>

        <?php while ($dep = $resDepositos->fetch_assoc()): ?>
            <?php
                // Se batalhão estiver selecionado, filtra visualmente
                if (!empty($batalhao_filtro) && $dep['batalhao'] != $batalhao_filtro) {
                    continue;
                }
                $sel = ($deposito_filtro == $dep['id']) ? 'selected' : '';
            ?>
            <option value="<?= $dep['id'] ?>" <?= $sel ?>>
                <?= htmlspecialchars($dep['nome_deposito']) ?>
                — <?= htmlspecialchars($oms_visiveis[$dep['batalhao']] ?? '') ?>
            </option>
        <?php endwhile; ?>

    </select>
</div>

            

          <div class="col-md-3">
            <label class="form-label fw-semibold">Nota Fiscal</label>
            <input type="text" class="form-control" name="nota_fiscal" value="<?= htmlspecialchars($nota_fiscal ?? '') ?>">
          </div>
          <div class="col-md-3">
            <label class="form-label fw-semibold">Fornecedor</label>
            <input type="text" class="form-control" name="nome_fornecedor" value="<?= htmlspecialchars($nome_fornecedor ?? '') ?>">
          </div>
          <div class="col-md-3">
            <label class="form-label fw-semibold">Data da Entrada</label>
            <input type="date" class="form-control" name="data_entrada" value="<?= htmlspecialchars($data_entrada ?? '') ?>">
          </div>
          <div class="col-12 d-flex justify-content-between mt-2">
            <button type="button" id="btnLimparFiltrosEntradas" class="btn btn-outline-secondary d-flex align-items-center gap-2">
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
  <label for="limiteEntradas" class="me-2 mb-0">Mostrar</label>
  <select id="limiteEntradas" name="limite" class="form-select d-inline w-auto" onchange="atualizarListaEntradas({ pagina: 1, limite: this.value })">
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
  <?= renderPaginacaoEntradas($pagina, $totalPaginas, $limite, $queryString); ?>
</div>

<!-- Listagem -->
<div class="card border-0 shadow-sm">
  <div class="card-body">
    <?php if ($entradas->num_rows > 0): ?>
      <div class="row g-3">
        <?php while ($entrada = $entradas->fetch_assoc()): ?>

          <?php
          // Busca nome e abreviatura do batalhão
          $sqlOm = "SELECT nome, abreviatura FROM organizacoes_militares WHERE id = ?";
          $stmtOm = $conexao->prepare($sqlOm);
          $stmtOm->bind_param("i", $entrada['batalhao']);
          $stmtOm->execute();
          $resOm = $stmtOm->get_result();
          $batalhao = $resOm->fetch_assoc();
          $stmtOm->close();

          // Busca empenho
          $empenho_id = $entrada['nota_empenho'] ?? 0;
          $nmr_empenho = "Empenho não cadastrado";
          if ($empenho_id) {
              $empenho = $conexao->query("SELECT nmr_empenho FROM fin_empenhos WHERE id = $empenho_id LIMIT 1");
              if ($empenho && $empenho->num_rows > 0) {
                  $nmr_empenho = $empenho->fetch_assoc()['nmr_empenho'];
              }
          }
          ?>

          <div class="col-12">
            <div class="card border-0 shadow-sm rounded-3 hover-shadow-sm">
              <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-start flex-wrap">
                  
                  <div>
                    <h5 class="fw-bold text-primary mb-1">
                      #<?= htmlspecialchars($entrada['id']) ?> — <?= htmlspecialchars($entrada['nome_fornecedor']) ?>
                    </h5>
                    <div class="text-muted small mb-2">
                      <i class="fa fa-building me-1"></i> 
                      <?= htmlspecialchars($batalhao['abreviatura'] ?? 'Batalhão não definido') ?> — 
                      <?= htmlspecialchars($batalhao['nome'] ?? '') ?>
                    </div>
                      <div class="text-muted small mb-2">
  <i class="fa fa-warehouse me-1"></i>
  <strong>Depósito:</strong>
  <?= htmlspecialchars($entrada['nome_deposito'] ?? 'Não informado') ?>
</div>
                    <div class="text-muted small">
                      <i class="fa fa-file-invoice me-1"></i> <strong>Nota Fiscal:</strong> <?= htmlspecialchars($entrada['nota_fiscal']) ?> |
                      <i class="fa fa-clipboard-list me-1"></i> <strong>Empenho:</strong> <?= htmlspecialchars($nmr_empenho) ?>
                    </div>
                    <div class="text-muted small mt-1">
                      <i class="fa fa-calendar me-1"></i> <strong>Data:</strong> <?= date('d/m/Y', strtotime($entrada['data_entrada'])) ?>
                    </div>
                  </div>

                  <div class="d-flex gap-2 mt-2 mt-sm-0">
                    <button class="btn btn-sm btn-outline-secondary rounded-circle d-flex align-items-center justify-content-center"
                            type="button"
                            data-bs-toggle="collapse"
                            data-bs-target="#itensEntrada<?= $entrada['id'] ?>"
                            aria-expanded="false"
                            title="Ver Itens">
                      <i class="fas fa-chevron-down"></i>
                    </button>

			<?php if ($pode_editar): ?>
                    <button class="btn btn-sm btn-outline-warning"
                            onclick="editarEntradaAlmox(<?= $entrada['id'] ?>)"
                            data-bs-toggle="modal"
                            data-bs-target="#modalEditarEntradaAlmox">
                      <i class="fas fa-edit me-1"></i> Editar
                    </button>
					  <?php endif; ?>

			<?php if ($pode_deletar): ?>
                    <button type="button"
                            class="btn btn-sm btn-outline-danger btn-deletar-entrada"
                            data-id="<?= $entrada['id'] ?>"
                            title="Remover">
                      <i class="fa fa-trash me-1"></i> Excluir
                    </button>
					  <?php endif; ?>
                  </div>
                </div>
              </div>

              <div class="collapse border-top bg-light" id="itensEntrada<?= $entrada['id'] ?>">
                <div class="card-body">
                  <?php
                  $sqlItens = "
                    SELECT i.quant, i.valor_unt, i.valor_total, i.marca, i.modelo, 
                           p.nome_produto, p.codigo_produto 
                    FROM almox_entradas_itens i
                    INNER JOIN almox_produtos p ON p.id = i.id_produto
                    WHERE i.id_entrada = ?
                    ORDER BY i.id ASC
                  ";
                  $stmtItens = $conexao->prepare($sqlItens);
                  $stmtItens->bind_param("i", $entrada['id']);
                  $stmtItens->execute();
                  $itens = $stmtItens->get_result();
                  ?>

                  <?php if ($itens->num_rows > 0): ?>
                    <div class="table-responsive">
                      <table class="table table-sm align-middle mb-0">
                        <thead class="table-light">
                          <tr>
                            <th>Produto</th>
                            <th>Código</th>
                            <th>Marca</th>
                            <th>Modelo</th>
                            <th class="text-end">Qtd</th>
                            <th class="text-end">Unitário (R$)</th>
                            <th class="text-end">Total (R$)</th>
                          </tr>
                        </thead>
                        <tbody>
                          <?php while ($item = $itens->fetch_assoc()): ?>
                            <tr>
                              <td><?= htmlspecialchars($item['nome_produto']) ?></td>
                              <td><?= htmlspecialchars($item['codigo_produto']) ?></td>
                              <td><?= htmlspecialchars($item['marca']) ?></td>
                              <td><?= htmlspecialchars($item['modelo']) ?></td>
                              <td class="text-end"><?= (int)$item['quant'] ?></td>
                              <td class="text-end"><?= number_format($item['valor_unt'], 2, ',', '.') ?></td>
                              <td class="text-end fw-semibold"><?= number_format($item['valor_total'], 2, ',', '.') ?></td>
                            </tr>
                          <?php endwhile; ?>
                        </tbody>
                      </table>
                    </div>
                  <?php else: ?>
                    <div class="text-muted">Nenhum item encontrado nesta entrada.</div>
                  <?php endif; ?>

                  <?php $stmtItens->close(); ?>
                </div>
              </div>
            </div>
          </div>

        <?php endwhile; ?>
      </div>
    <?php else: ?>
      <div class="alert alert-info text-center py-4">
        <i class="fa fa-info-circle me-2"></i> Nenhuma entrada encontrada.
      </div>
    <?php endif; ?>
  </div>
</div>


<!-- Paginação fundo -->
<div class="paginacao mt-4">
  <?= renderPaginacaoEntradas($pagina, $totalPaginas, $limite, $queryString); ?>
</div>

			<?php if ($pode_cadastrar): ?>
<!-- MODAL DE CADASTRO DE ENTRADA DO ALMOXARIFADO -->
<div class="modal fade" id="modalCadastroEntradaAlmox" tabindex="-1" aria-labelledby="modalCadastroEntradaAlmoxLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      <form id="formCadastroEntradaAlmox">
        <div class="modal-header bg-success text-white">
          <h5 class="modal-title" id="modalCadastroEntradaAlmoxLabel">Cadastrar Entrada no Almoxarifado</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Fechar"></button>
        </div>

          <div class="modal-body">
          <div class="row g-3">
                <div class="mb-3">
  <label class="form-label fw-semibold">Batalhão</label>
  <select name="batalhao" id="batalhaoEntrada" class="form-select" required>
    <?php foreach ($oms_visiveis as $id => $nome): ?>
      <option value="<?= $id ?>"><?= htmlspecialchars($nome) ?></option>
    <?php endforeach; ?>
  </select>
</div>
              
              <?php
function getDepositosVisiveis($conexao, $oms_visiveis) {
    $depositos = [];

    if (empty($oms_visiveis)) {
        return $depositos;
    }

    // IDs dos batalhões permitidos
    $ids = array_keys($oms_visiveis);

    // Cria placeholders (?, ?, ?)
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $tipos = str_repeat('i', count($ids));

    $sql = "
        SELECT id, nome_deposito, batalhao
        FROM almox_depositos
        WHERE batalhao IN ($placeholders)
        ORDER BY nome_deposito ASC
    ";

    $stmt = $conexao->prepare($sql);
    $stmt->bind_param($tipos, ...$ids);
    $stmt->execute();
    $res = $stmt->get_result();

    while ($row = $res->fetch_assoc()) {
        $depositos[] = $row;
    }

    $stmt->close();

    return $depositos;
}

// 🔹 Depósitos que o usuário pode visualizar
$depositos_visiveis = getDepositosVisiveis($conexao, $oms_visiveis);
?>


<div class="mb-3">
  <label class="form-label fw-semibold">Depósito</label>
  <select name="deposito_id" class="form-select" required>
    <option value="">Selecione o depósito</option>

    <?php foreach ($depositos_visiveis as $dep): ?>
      <option value="<?= $dep['id'] ?>">
        <?= htmlspecialchars($dep['nome_deposito']) ?>
        — <?= htmlspecialchars($oms_visiveis[$dep['batalhao']] ?? '') ?>
      </option>
    <?php endforeach; ?>

  </select>
</div>


            <!-- Dados da Entrada -->
            <div class="col-md-4">
              <label for="dataEntrada" class="form-label">Data da Entrada</label>
              <input type="date" class="form-control" id="dataEntrada" name="data_entrada" required>
            </div>

            <div class="col-md-4">
              <label for="notaEmpenho" class="form-label">Nota de Empenho</label>
                              <select class="form-select" id="notaEmpenho" name="nota_empenho" required>
                <option value="" disabled selected>Selecione</option>
                <?php
                $empenho = $conexao->query("SELECT id, nmr_empenho FROM fin_empenhos ORDER BY id ASC");
                while ($emp = $empenho->fetch_assoc()):
                ?>
                <option value="<?= $emp['id'] ?>"><?= $emp['nmr_empenho'] ?></option>
                <?php endwhile; ?>				  
		<option value="0">
                Outro Empenho
            </option>
              </select>
            </div>

            <div class="col-md-4">
              <label for="notaFiscal" class="form-label">Nota Fiscal</label>
              <input type="text" class="form-control" id="notaFiscal" name="nota_fiscal" required>
            </div>

            <div class="col-md-6">
              <label for="nomeFornecedor" class="form-label">Nome do Fornecedor</label>
              <input type="text" class="form-control" id="nomeFornecedor" name="nome_fornecedor" required>
            </div>

            <div class="col-md-6">
              <label for="cnpjFornecedor" class="form-label">CNPJ do Fornecedor</label>
              <input type="text" class="form-control" id="cnpjFornecedor" name="cnpj_fornecedor" required>
            </div>
          </div>

          <hr class="my-4">

          <!-- Botões para adicionar produtos -->
          <div class="d-flex gap-2 justify-content-center mb-3">
            <button type="button" class="btn btn-outline-primary" id="btnAddProdutoExistente">Adicionar Produto Existente</button>
          </div>

          <!-- Resumo Total da Entrada -->
          <div id="resumoTotalEntrada" class="alert alert-success text-center fs-5 fw-bold" style="display: none;">
            VALOR TOTAL DA ENTRADA: R$ <span id="valorTotalEntrada">0,00</span>
          </div>

          <!-- Container dos produtos adicionados -->
          <div id="containerProdutosEntrada"></div>
        </div>

        <div class="modal-footer">
          <button type="submit" class="btn btn-success">Salvar Entrada</button>
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
        </div>
      </form>
    </div>
  </div>
</div>
<?php endif; ?>
	  
			<?php if ($pode_editar): ?>

<!-- MODAL DE EDIÇÃO DE ENTRADA DO ALMOXARIFADO -->
<div class="modal fade" id="modalEditarEntradaAlmox" tabindex="-1" aria-labelledby="modalEditarEntradaAlmoxLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      <form id="formEditarEntradaAlmox">
        <div class="modal-header bg-warning text-dark">
          <h5 class="modal-title" id="modalEditarEntradaAlmoxLabel">Editar Entrada no Almoxarifado</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
        </div>

        <div class="modal-body">
          <input type="hidden" id="idEntradaAlmox" name="id_entrada">

          <div class="row g-3">
                <div class="mb-3">
    <label class="form-label fw-semibold">Batalhão</label>
                   <input type="text" class="form-control" id="nome_batalhao_edit" name="batalhao" readonly>
</div>
           <div class="col-md-6">
  <label class="form-label fw-semibold">Depósito</label>
  <select name="deposito_id" id="depositoEntradaEdit" class="form-select" required>
    <option value="">Selecione o depósito</option>

    <?php foreach ($depositos_visiveis as $dep): ?>
      <option value="<?= $dep['id'] ?>"
              data-batalhao="<?= $dep['batalhao'] ?>">
        <?= htmlspecialchars($dep['nome_deposito']) ?>
        — <?= htmlspecialchars($oms_visiveis[$dep['batalhao']] ?? '') ?>
      </option>
    <?php endforeach; ?>
  </select>
</div>

            <!-- Dados da Entrada -->
            <div class="col-md-4">
              <label for="dataEntradaEdit" class="form-label">Data da Entrada</label>
              <input type="date" class="form-control" id="dataEntradaEdit" name="data_entrada" required>
            </div>

            <div class="col-md-4">
              <label for="notaEmpenhoEdit" class="form-label">Nota de Empenho</label>
              <select class="form-select" id="notaEmpenhoEdit" name="nota_empenho" required>
                <option value="" disabled selected>Selecione</option>
                <?php
                $empenho = $conexao->query("SELECT id, nmr_empenho FROM fin_empenhos ORDER BY id ASC");
                while ($emp = $empenho->fetch_assoc()):
                ?>
                <option value="<?= $emp['id'] ?>"><?= $emp['nmr_empenho'] ?></option>
                <?php endwhile; ?>
		<option value="0">
                Outro Empenho
            </option>
              </select>
            </div>

            <div class="col-md-4">
              <label for="notaFiscalEdit" class="form-label">Nota Fiscal</label>
              <input type="text" class="form-control" id="notaFiscalEdit" name="nota_fiscal" required>
            </div>

            <div class="col-md-6">
              <label for="nomeFornecedorEdit" class="form-label">Nome do Fornecedor</label>
              <input type="text" class="form-control" id="nomeFornecedorEdit" name="nome_fornecedor" required>
            </div>

            <div class="col-md-6">
              <label for="cnpjFornecedorEdit" class="form-label">CNPJ do Fornecedor</label>
              <input type="text" class="form-control" id="cnpjFornecedorEdit" name="cnpj_fornecedor" required>
            </div>
          </div>

          <hr class="my-4">

          <!-- Botão para adicionar produtos -->
          <div class="d-flex gap-2 justify-content-center mb-3">
            <button type="button" class="btn btn-outline-primary" id="btnAddProdutoExistenteEdit">Adicionar Produto Existente</button>
          </div>

          <!-- Resumo Total da Entrada -->
          <div id="resumoTotalEntradaEdit" class="alert alert-warning text-center fs-5 fw-bold" style="display: none;">
            VALOR TOTAL DA ENTRADA: R$ <span id="valorTotalEntradaEdit">0,00</span>
          </div>

          <!-- Container dos produtos adicionados -->
          <div id="containerProdutosEntradaEdit"></div>
        </div>

        <div class="modal-footer">
          <button type="submit" class="btn btn-warning">Salvar Alterações</button>
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
        </div>
      </form>
    </div>
  </div>
</div>
<?php endif; ?>
      
      
      
<!-- Script da página de Cadastro de Fornecedores -->
<script>
    window.funcaoInicializacao = 'inicializarAlmoxEntradas';
   $('#modalCadastroEntradaAlmox').on('shown.bs.modal', function () {
  window.inicializarCadastroEntradaAlmox();
});   
</script>
