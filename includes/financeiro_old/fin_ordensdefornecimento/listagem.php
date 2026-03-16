<?php
header('Content-Type: text/html; charset=utf-8');
if (session_status() === PHP_SESSION_NONE) session_start();
include_once('../../conexao/config.php');

// ==============================
// Dados do usuário logado
// ==============================
$id_om_usuario = $_SESSION['usuario']['batalhao'] ?? null;
$nivel_usuario = $_SESSION['usuario']['nivel'] ?? null;

// ==============================
// Filtros básicos
// ==============================
$id = $_GET['id'] ?? '';
$data_ini = $_GET['data_ini'] ?? '';
$data_fim = $_GET['data_fim'] ?? '';
$nome_responsavel = $_GET['nome_responsavel'] ?? '';
$batalhaoFiltro = $_GET['batalhao'] ?? '';

$filtros = [];
$params = [];
$tipos = '';

$limite = isset($_GET['limite']) && is_numeric($_GET['limite']) ? (int)$_GET['limite'] : 10;
$pagina = isset($_GET['pagina']) && is_numeric($_GET['pagina']) && $_GET['pagina'] > 0 ? (int)$_GET['pagina'] : 1;
$offset = ($pagina - 1) * $limite;

// ==============================
// Filtros padrão
// ==============================
$campos = [
    'o.id' => 'id',
    'o.nome_responsavel' => 'nome_responsavel',
    'o.data_entrega_limite >=' => 'data_ini',
    'o.data_entrega_limite <=' => 'data_fim'
];

foreach ($campos as $coluna => $parametro) {
    if (!empty($_GET[$parametro])) {
        $valor = $_GET[$parametro];

        if ($coluna === 'o.id') {
            $filtros[] = "$coluna = ?";
            $params[] = (int)$valor;
            $tipos .= 'i';
        } elseif (str_contains($coluna, '>=')) {
            $filtros[] = str_replace(' >=', ' >=', $coluna) . ' ?';
            $params[] = $valor;
            $tipos .= 's';
        } elseif (str_contains($coluna, '<=')) {
            $filtros[] = str_replace(' <=', ' <=', $coluna) . ' ?';
            $params[] = $valor;
            $tipos .= 's';
        } else {
            $filtros[] = "$coluna LIKE ?";
            $params[] = "%$valor%";
            $tipos .= 's';
        }
    }
}

// ==============================
// Filtro direto por batalhão
// ==============================
if (!empty($batalhaoFiltro)) {
    $filtros[] = "o.batalhao = ?";
    $params[] = $batalhaoFiltro;
    $tipos .= 'i';
}

// ==============================
// Controle de visualização por OM
// ==============================
// Nível 1 vê tudo, os demais só a OM e subordinadas
if ($nivel_usuario != 1 && $id_om_usuario) {
    $sqlSubs = "SELECT id_om_menor FROM organizacoes_militares_sub WHERE id_om_maior = ?";
    $stmtSubs = $conexao->prepare($sqlSubs);
    $stmtSubs->bind_param("i", $id_om_usuario);
    $stmtSubs->execute();
    $resSubs = $stmtSubs->get_result();

    $idsVisiveis = [$id_om_usuario];
    while ($row = $resSubs->fetch_assoc()) {
        $idsVisiveis[] = $row['id_om_menor'];
    }
    $stmtSubs->close();

    $placeholders = implode(',', array_fill(0, count($idsVisiveis), '?'));
    $filtros[] = "o.batalhao IN ($placeholders)";
    $params = array_merge($params, $idsVisiveis);
    $tipos .= str_repeat('i', count($idsVisiveis));
}

$condicoes = !empty($filtros) ? 'WHERE ' . implode(' AND ', $filtros) : '';

// ==============================
// Consulta total
// ==============================
$sqlTotal = "SELECT COUNT(*) as total FROM fin_ordemforn o $condicoes";
$stmtTotal = $conexao->prepare($sqlTotal);
if (!empty($params)) {
    $stmtTotal->bind_param($tipos, ...$params);
}
$stmtTotal->execute();
$resultTotal = $stmtTotal->get_result();
$totalRegistros = $resultTotal->fetch_assoc()['total'];
$totalPaginas = ceil($totalRegistros / $limite);

// ==============================
// Consulta principal
// ==============================
$sql = "
  SELECT 
    o.*, 
    om.nome AS nome_om,
    om.abreviatura AS abreviatura_om
  FROM fin_ordemforn o
  LEFT JOIN organizacoes_militares om ON o.batalhao = om.id
  $condicoes
  ORDER BY o.id DESC
  LIMIT ? OFFSET ?
";
$params[] = $limite;
$params[] = $offset;
$tipos .= 'ii';

$stmt = $conexao->prepare($sql);
$stmt->bind_param($tipos, ...$params);
$stmt->execute();
$ordens = $stmt->get_result();

// ==============================
// Paginação
// ==============================
function renderPaginacao($pagina, $totalPaginas, $limite, $queryString, $arquivo = 'includes/fin_ordensdefornecimento/listagem.php') {
    $html = '<div class="pagination-wrapper">';
    $html .= '<nav><ul class="pagination pagination-sm">';

    for ($i = 1; $i <= $totalPaginas; $i++) {
        $ativo = $i == $pagina ? 'active' : '';
        $url = "{$arquivo}?{$queryString}&pagina=$i&limite=$limite";
        $html .= "<li class='page-item $ativo'><a class='page-link paginacao' href='#' data-page='{$url}'>$i</a></li>";
    }

    $html .= '</ul></nav></div>';
    return $html;
}

$paramsGET = $_GET;
unset($paramsGET['pagina']);
$queryString = http_build_query($paramsGET);
?>

<style>
  .list-group-item {
    transition: box-shadow 0.3s;
    border-radius: 0.75rem;
  }
  .list-group-item:hover {
    box-shadow: 0 0 10px rgba(0,0,0,0.1);
  }
  .bg-light {
    background-color: #f8f9fa !important;
  }
  .text-primary {
    color: #0d6efd !important;
  }
  .fw-semibold {
    font-weight: 600;
  }
  .btn-outline-secondary, .btn-outline-warning {
    min-width: 100px;
  }
  .badge {
    background: linear-gradient(to right, #007bff, #00c6ff);
    color: #fff;
    font-size: 0.9rem;
  }
</style>


<div class="container">
  <div class="page-inner">
    <div class="d-flex justify-content-between align-items-center py-3">
      <div>
        <h3 class="fw-bold mb-1">Listagem das Ordens de Fornecimento</h3>
        <h6 class="text-muted">Listagem das ordens de fornecimento cadastradas.</h6>
      </div>
      <div>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalCadastrarOrdem">
          <i class="fa fa-plus me-1"></i> Nova Ordem de Fornecimento
        </button>
      </div>
    </div>

        <div class="mb-3">
      <button class="btn btn-outline-primary w-100 d-flex justify-content-center align-items-center" onclick="toggleFiltrosOF()">
        <i class="fas fa-search me-2"></i> Filtros
      </button>
    </div>

    <div id="filtros-container-of" style="display: none;" class="mb-3">
      <div class="card shadow-sm border-0">
        <div class="card-body">
          <form method="GET" id="filtroPregaoForm">
            <div class="row g-3">
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
              <div class="col-md-2">
                <label class="form-label fw-semibold">ID</label>
                <input type="text" class="form-control" name="id" value="<?= htmlspecialchars($_GET['id'] ?? '') ?>">
              </div>
              <div class="col-md-4">
                <label class="form-label fw-semibold">Responsável</label>
                <input type="text" class="form-control" name="nome_responsavel" value="<?= htmlspecialchars($_GET['nome_responsavel'] ?? '') ?>">
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
                <button type="button" id="btnLimparFiltrosOF" class="btn btn-black">Limpar</button>
                <button type="submit" class="btn btn-primary">Aplicar</button>
              </div>
            </div>
          </form>
        </div>
      </div>
    </div>
      

    <div class="mb-3">
      <label class="me-2">Mostrar</label>
      <select id="limiteOF" name="limite" class="form-select d-inline w-auto" onchange="atualizarLimiteOF()">
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
      <?= renderPaginacao($pagina, $totalPaginas, $limite, $queryString, 'includes/fin_ordensdefornecimento/listagem.php'); ?>
    </div>

<div class="card border-0 shadow-sm">
  <div class="card-body">
    <div class="list-group">
      <?php if ($ordens->num_rows > 0): ?>
        <?php while ($ordem = $ordens->fetch_assoc()): ?>
          <?php
// Buscar pedidos vinculados à ordem atual
$stmtPedidos = $conexao->prepare("
  SELECT p.*, f.prefixo_sga
  FROM fin_ordemforn_pedidos op
  JOIN fin_pedidos_forn p ON p.id = op.id_pedido
  LEFT JOIN frota f ON p.id_vtr = f.id
  WHERE op.id_ordemforn = ?
");
$stmtPedidos->bind_param("i", $ordem['id']);
$stmtPedidos->execute();
$resPedidos = $stmtPedidos->get_result();

// Calcular valor total da ordem de fornecimento
$total_ordemforn = 0.0;

$stmtValorTotal = $conexao->prepare("
  SELECT SUM(fi.valor_total) as total
  FROM fin_ordemforn_pedidos op
  JOIN fin_pedidos_forn_itens fi ON fi.id_principal = op.id_pedido
  WHERE op.id_ordemforn = ?
");
$stmtValorTotal->bind_param("i", $ordem['id']);
$stmtValorTotal->execute();
$resValorTotal = $stmtValorTotal->get_result();
if ($resValorTotal && $rowValor = $resValorTotal->fetch_assoc()) {
  $total_ordemforn = $rowValor['total'] ?? 0;
}
$stmtValorTotal->close();

// Buscar número do empenho associado (se existir)
$nmr_empenho = 'Empenho não vinculado';
if (!empty($ordem['id_empenho'])) {
  $stmtEmpenho = $conexao->prepare("
    SELECT nmr_empenho 
    FROM fin_empenhos 
    WHERE id = ?
  ");
  $stmtEmpenho->bind_param("i", $ordem['id_empenho']);
  $stmtEmpenho->execute();
  $resEmpenho = $stmtEmpenho->get_result();
  if ($resEmpenho && $empenho = $resEmpenho->fetch_assoc()) {
    $nmr_empenho = $empenho['nmr_empenho'];
  }
  $stmtEmpenho->close();
}
?>


          <div class="list-group-item list-group-item-action flex-column mb-4 p-4 border rounded-3 shadow-sm">
            <div class="d-flex justify-content-between align-items-start">
              <div class="d-flex flex-column small text-body-secondary">
                <h6 class="fw-bold text-primary mb-2">
                  Ordem de Fornecimento #<?= $ordem['id']  ?> - <?= $ordem['abreviatura_om']  ?></h6> 
                  <h6><b>Empenho:</b> <?= htmlspecialchars($nmr_empenho) ?></h6>
                
                 <div>
  <strong>Data da Requisição:</strong>
  <?= !empty($ordem['data_cadastro']) ? (new DateTime($ordem['data_cadastro']))->format('d/m/Y') : '—' ?>
</div>

<div>
  <strong>Data Limite de Entrega:</strong>
  <?= !empty($ordem['data_entrega_limite']) ? (new DateTime($ordem['data_entrega_limite']))->format('d/m/Y') : '—' ?>
</div>
               <div><strong>Nome da empresa:</strong> <?= htmlspecialchars($ordem['empresa_nome']) ?></div>
               <div><strong>CNPJ da empresa:</strong> <?= htmlspecialchars($ordem['empresa_cnpj']) ?></div>
               <div><strong>Responsável:</strong> <?= htmlspecialchars($ordem['nome_responsavel']) ?> — <?= htmlspecialchars($ordem['contato_responsavel']) ?></div>
                <div><strong>  Valor Total da Ordem: R$ <?= number_format($total_ordemforn, 2, ',', '.') ?> </strong></div>
              </div>
          <span class="badge rounded-pill bg-<?= $ordem['status'] === 'Agd entrega' ? 'warning' : ($ordem['status'] === 'Concluída' ? 'success' : 'secondary') ?> mt-3">
            <?= htmlspecialchars($ordem['status']) ?>
          </span>
              <div class="d-flex ms-auto align-items-center gap-2 mt-3 flex-wrap">
                                    <!-- Imprimir -->
            <div class="dropdown">
  <button class="btn btn-sm btn-outline-secondary dropdown-toggle" 
          type="button" 
          id="dropdownMenu<?= $ordem['id'] ?>" 
          data-bs-toggle="dropdown" 
          aria-expanded="false">
    <i class="fas fa-print me-1"></i> Imprimir
  </button>
  
  <ul class="dropdown-menu dropdown-menu-end shadow-sm rounded" aria-labelledby="dropdownMenu<?= $ordem['id'] ?>">
    <li>
     <a href="#" 
   class="dropdown-item text-danger btnExportarPDFordem" 
   data-id="<?= $ordem['id'] ?>">
    <i class="fas fa-file-pdf me-2"></i> Imprimir OF
</a>
    </li>
          <li>
     <a href="#" 
   class="dropdown-item text-danger btnExportarPDFofpedidos" 
   data-id="<?= $ordem['id'] ?>">
    <i class="fas fa-file-pdf me-2"></i> Imprimir OF e Pedidos
</a>
    </li>
  </ul>
</div>
                <button class="btn btn-outline-secondary btn-sm" data-bs-toggle="collapse" data-bs-target="#pedidosOrdem<?= $ordem['id'] ?>">
                  <i class="fa fa-eye me-1"></i> Ver Pedidos
                </button>
               <button 
  class="btn btn-outline-warning btn-sm" 
  data-bs-toggle="modal" 
  data-bs-target="#modalEditarOrdem"
  onclick="editarOrdem(<?= $ordem['id'] ?>)">
  <i class="fas fa-edit me-1"></i> Editar
</button>
                  <button type="button"
                class="btn btn-sm btn-outline-danger d-flex align-items-center btn-deletar-of"
                data-id="<?= $ordem['id'] ?>"
                onclick="deletarOF(this)"
                data-bs-toggle="tooltip"
                title="Remover">
                <i class="fa fa-times"></i>
              </button>
              </div>
            </div>

            <div class="collapse mt-3" id="pedidosOrdem<?= $ordem['id'] ?>">
  <?php if ($resPedidos->num_rows > 0): ?>
    <?php while ($pedido = $resPedidos->fetch_assoc()): ?>
      <?php
        $stmtItens = $conexao->prepare("SELECT * FROM fin_pedidos_forn_itens WHERE id_principal = ?");
        $stmtItens->bind_param("i", $pedido['id']);
        $stmtItens->execute();
        $resItens = $stmtItens->get_result();

        // Armazena os itens e calcula o valor total
        $itens = [];
        $valorTotalPedido = 0;
        while ($item = $resItens->fetch_assoc()) {
            $itens[] = $item;
            $valorTotalPedido += (float) $item['valor_total'];
        }
        $totalItens = count($itens);
      ?>

      <div class="border rounded p-3 mb-3 bg-light">
        <h6 class="fw-semibold text-primary mb-2">
          Pedido #<?= $pedido['id'] ?> —
          Itens no pedido: <?= $totalItens ?> —
          Valor total do pedido: R$ <?= number_format($valorTotalPedido, 2, ',', '.') ?>
        </h6>

        <div class="text-muted small mb-2">
          <div><strong>Local:</strong> <?= htmlspecialchars($pedido['local_pedido']) ?></div>
          <div><strong>Situação:</strong> <?= htmlspecialchars($pedido['situacao_pedido']) ?></div>
        </div>

        <ul class="list-group list-group-flush">
          <?php foreach ($itens as $item): ?>
            <li class="list-group-item">
              <strong><?= htmlspecialchars($item['descricao_item']) ?></strong>
              — Qtd: <?= $item['quant_solicitada'] ?>
              — Valor: R$ <?= number_format($item['valor_unt'], 2, ',', '.') ?>
            </li>
          <?php endforeach; ?>
        </ul>
        <?php $stmtItens->close(); ?>
      </div>
    <?php endwhile; ?>
  <?php else: ?>
    <div class="alert alert-info">Nenhum pedido vinculado.</div>
  <?php endif; ?>
  <?php $stmtPedidos->close(); ?>
</div>

          </div>
        <?php endwhile; ?>
      <?php else: ?>
        <div class="alert alert-info text-center">Nenhuma ordem de fornecimento encontrada.</div>
      <?php endif; ?>
    </div>
  </div>
</div>



    <div class="paginacao mt-3">
      <?= renderPaginacao($pagina, $totalPaginas, $limite, $queryString, 'includes/fin_ordemforn/listagem.php'); ?>
    </div>
  </div>
</div>
<!-- Modal de Cadastro da Ordem de Fornecimento -->
<div class="modal fade" id="modalCadastrarOrdem" tabindex="-1" aria-labelledby="modalCadastrarOrdemLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-xl">
    <div class="modal-content">
      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title" id="modalCadastrarOrdemLabel">Cadastrar Ordem de Fornecimento</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Fechar"></button>
      </div>

      <div class="modal-body">
        <form method="POST" id="form-ordem-cadastrar">
          
          <!-- Empenho -->
          <div class="row mb-3">
            <div class="col-md-12">
              <label class="form-label">Empenho</label>
              <select class="form-select" id="id_empenho" name="id_empenho" required>
                <option value="" disabled selected>Selecione o empenho</option>
                <?php
                $r = $conexao->query("SELECT id, nmr_empenho FROM fin_empenhos ORDER BY id DESC");
                while($e = $r->fetch_assoc()):
                ?>
                  <option value="<?= $e['id'] ?>"><?= htmlspecialchars($e['nmr_empenho']) ?></option>
                <?php endwhile; ?>
              </select>
            </div>

            <div class="col-md-4">
              <label class="form-label">Empresa</label>
              <input type="text" class="form-control" id="empresa_nome" name="empresa_nome" readonly required>
            </div>

            <div class="col-md-4">
              <label class="form-label">CNPJ</label>
              <input type="text" class="form-control" id="empresa_cnpj" name="empresa_cnpj" readonly required>
            </div>

            <div class="col-md-4">
              <label class="form-label">E-mail</label>
              <input type="email" class="form-control" id="empresa_email" name="empresa_email" readonly>
            </div>
          </div>

          <!-- Dados da Ordem -->
          <div class="row g-3">
            <?php
            $id_om_usuario = $_SESSION['usuario']['batalhao'] ?? 0;
            $sqlNivel = "SELECT nivel FROM organizacoes_militares WHERE id = $id_om_usuario";
            $resNivel = $conexao->query($sqlNivel);
            $nivelUsuario = $resNivel->fetch_assoc()['nivel'] ?? 0;

            if ($nivelUsuario == 1) {
                $sqlBatalhoes = "SELECT id, nome, abreviatura FROM organizacoes_militares ORDER BY nome";
            } else {
                $sqlBatalhoes = "
                    SELECT om.id, om.nome, om.abreviatura
                    FROM organizacoes_militares om
                    WHERE om.id = $id_om_usuario
                    OR om.id IN (
                        SELECT id_om_menor FROM organizacoes_militares_sub WHERE id_om_maior = $id_om_usuario
                    )
                    ORDER BY om.nome
                ";
            }
            $resBatalhoes = $conexao->query($sqlBatalhoes);
            ?>
            
            <div class="col-md-4">
              <label class="form-label">Batalhão</label>
              <select name="batalhao" id="batalhao" class="form-select" required>
                <option value="">Selecione...</option>
                <?php while ($bat = $resBatalhoes->fetch_assoc()): ?>
                  <option value="<?= $bat['id'] ?>" <?= ($bat['id']==$id_om_usuario)?'selected':'' ?>>
                    <?= htmlspecialchars($bat['abreviatura'] . ' - ' . $bat['nome']) ?>
                  </option>
                <?php endwhile; ?>
              </select>
            </div>

            <div class="col-md-4">
              <label class="form-label">Data de Cadastro</label>
              <input type="date" class="form-control" name="data_cadastro" required>
            </div>

            <div class="col-md-4">
              <label class="form-label">Data Limite de Entrega</label>
              <input type="date" class="form-control" name="data_entrega_limite" required>
            </div>

            <div class="col-md-4">
              <label class="form-label">Status</label>
              <select class="form-select" name="status" required>
                <option value="" disabled selected>Selecione o status</option>
                <option value="Entregue">Entregue</option>
                <option value="Não entregue">Não entregue</option>
                <option value="Capeador">Capeador</option>
                <option value="Liquidado">Liquidado</option>
                <option value="Pago">Pago</option>
              </select>
            </div>

            <div class="col-md-6">
              <label class="form-label">Local de Entrega</label>
              <input type="text" class="form-control" name="local_entrega" required>
            </div>

            <div class="col-md-6">
              <label class="form-label">Responsável pela Entrega</label>
              <input type="text" class="form-control" name="nome_responsavel" required>
            </div>

            <div class="col-md-6">
              <label class="form-label">Contato do Responsável</label>
              <input type="text" class="form-control" name="contato_responsavel" required>
            </div>

            <div class="col-md-12">
              <label class="form-label">Observação Final</label>
              <textarea class="form-control" name="observacao_final" rows="3"></textarea>
            </div>
          </div>

          <hr>
          <h5 class="fw-bold mt-4">Pedidos Vinculados</h5>

          <div id="pedidosContainer" class="mb-3">
            <div class="row g-2 align-items-end pedido-linha">
              <div class="col-md-10">
                <label class="form-label">Pedido</label>
                <select class="form-select" name="pedidos[]">
                  <option value="" selected disabled>Selecione um pedido</option>
                  <?php
                  $id_om_usuario = $_SESSION['usuario']['batalhao'] ?? null;
                  $nivel_usuario = $_SESSION['usuario']['nivel'] ?? null;

                  $where = '';
                  if ($nivel_usuario != 1 && $id_om_usuario) {
                    $idsVisiveis = [(int)$id_om_usuario];
                    $sqlSubs = "SELECT id_om_menor FROM organizacoes_militares_sub WHERE id_om_maior = ?";
                    $stmtSubs = $conexao->prepare($sqlSubs);
                    $stmtSubs->bind_param('i', $id_om_usuario);
                    $stmtSubs->execute();
                    $resSubs = $stmtSubs->get_result();
                    while ($r = $resSubs->fetch_assoc()) {
                      $idsVisiveis[] = (int)$r['id_om_menor'];
                    }
                    $stmtSubs->close();
                    $idsVisiveis = array_map('intval', $idsVisiveis);
                    $where = 'AND p.batalhao IN (' . implode(',', $idsVisiveis) . ')';
                  }

                  $sqlPedidos = "
                    SELECT 
                      p.id, 
                      p.solicitante,
                      p.batalhao,
                      om.nome AS nome_om,
                      COALESCE((
                        SELECT SUM(valor_total) 
                        FROM fin_pedidos_forn_itens 
                        WHERE id_principal = p.id
                      ), 0) AS total_pedido
                    FROM fin_pedidos_forn p
                    LEFT JOIN organizacoes_militares om ON om.id = p.batalhao
                    WHERE p.id NOT IN (
                      SELECT id_pedido FROM fin_ordemforn_pedidos
                    )
                    $where
                    ORDER BY p.id DESC
                  ";

                  $resPedidos = $conexao->query($sqlPedidos);

                  if (!$resPedidos || $resPedidos->num_rows === 0):
                  ?>
                    <option value="" disabled>Nenhum pedido disponível</option>
                  <?php
                  else:
                    while ($p = $resPedidos->fetch_assoc()):
                      $nomeOm = $p['nome_om'] ? htmlspecialchars($p['nome_om']) : 'Sem OM';
                      $solicitante = htmlspecialchars($p['solicitante'] ?? '—');
                      $total = number_format((float)$p['total_pedido'], 2, ',', '.');
                  ?>
                      <option value="<?= $p['id'] ?>">
                        Pedido #<?= $p['id'] ?> — 
                        <?= $nomeOm ?> — 
                        <?= $solicitante ?> — 
                        Total: R$ <?= $total ?>
                      </option>
                  <?php
                    endwhile;
                  endif;
                  ?>
                </select>
              </div>
              <div class="col-md-2">
                <button type="button" class="btn btn-outline-secondary w-100" onclick="adicionarPedidoSelect()">+ Pedido</button>
              </div>
            </div>
          </div>

          <!-- Botão de envio -->
          <div class="d-grid">
            <button type="submit" class="btn btn-success mt-3">Cadastrar Ordem</button>
          </div>

        </form>
      </div>
    </div>
  </div>
</div>


<!-- Modal de Edição da Ordem de Fornecimento -->
<div class="modal fade" id="modalEditarOrdem" tabindex="-1" aria-labelledby="modalEditarOrdemLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-xl">
    <div class="modal-content">
      <div class="modal-header bg-warning text-white">
        <h5 class="modal-title" id="modalEditarOrdemLabel">Editar Ordem de Fornecimento</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Fechar"></button>
      </div>
      <div class="modal-body">
        <form method="POST" id="form-ordem-editar">

          <input type="hidden" name="id" id="editar-id_ordem">

          <div class="row mb-3">
            <div class="col-md-12">
              <label class="form-label">Empenho</label>
              <select class="form-select" id="editar-id_empenho" name="id_empenho" readonly required>
                <option value="" disabled selected>Selecione o empenho</option>
                <?php
                $r = $conexao->query("SELECT id, nmr_empenho FROM fin_empenhos ORDER BY id DESC");
                while($e = $r->fetch_assoc()):
                ?>
                  <option value="<?= $e['id'] ?>">
                    <?= htmlspecialchars($e['nmr_empenho']) ?>
                  </option>
                <?php endwhile; ?>
              </select>
            </div>

            <div class="col-md-4">
              <label class="form-label">Empresa</label>
              <input type="text" class="form-control" id="editar-empresa_nome" name="empresa_nome" readonly required>
            </div>
            <div class="col-md-4">
              <label class="form-label">CNPJ</label>
              <input type="text" class="form-control" id="editar-empresa_cnpj" name="empresa_cnpj" readonly required>
            </div>
            <div class="col-md-4">
              <label class="form-label">E‑mail</label>
              <input type="email" class="form-control" id="editar-empresa_email" name="empresa_email" readonly>
            </div>
          </div>
            

          <div class="row g-3">
              <?php
            // ============================
            // BATALHÕES QUE O USUÁRIO PODE VISUALIZAR
            // ============================
            $id_om_usuario = $_SESSION['usuario']['batalhao'] ?? 0;
            $sqlNivel = "SELECT nivel FROM organizacoes_militares WHERE id = $id_om_usuario";
            $resNivel = $conexao->query($sqlNivel);
            $nivelUsuario = $resNivel->fetch_assoc()['nivel'] ?? 0;

            if ($nivelUsuario == 1) {
                $sqlBatalhoes = "SELECT id, nome, abreviatura FROM organizacoes_militares ORDER BY nome";
            } else {
                $sqlBatalhoes = "
                    SELECT om.id, om.nome, om.abreviatura
                    FROM organizacoes_militares om
                    WHERE om.id = $id_om_usuario
                    OR om.id IN (
                        SELECT id_om_menor FROM organizacoes_militares_sub WHERE id_om_maior = $id_om_usuario
                    )
                    ORDER BY om.nome
                ";
            }
            $resBatalhoes = $conexao->query($sqlBatalhoes);
            ?>
            
            <div class="col-md-4">
              <label class="form-label">Batalhão</label>
              <select name="batalhao" id="editar-batalhao" class="form-select" required>
                <option value="">Selecione...</option>
                <?php while ($bat = $resBatalhoes->fetch_assoc()): ?>
                  <option value="<?= $bat['id'] ?>" <?= ($bat['id']==$id_om_usuario)?'selected':'' ?>>
                    <?= htmlspecialchars($bat['abreviatura'] . ' - ' . $bat['nome']) ?>
                  </option>
                <?php endwhile; ?>
              </select>
            </div>
              
            <div class="col-md-4">
              <label class="form-label">Data de Cadastro</label>
              <input type="date" class="form-control" id="editar-data_cadastro" name="data_cadastro" required>
            </div>

            <div class="col-md-4">
              <label class="form-label">Data Limite de Entrega</label>
              <input type="date" class="form-control" id="editar-data_entrega_limite" name="data_entrega_limite" required>
            </div>

            <div class="col-md-4">
              <label class="form-label">Status</label>
              <select class="form-select" id="editar-status" name="status" required>
                <option value="" disabled selected>Selecione o status</option>
                <option value="Entregue">Entregue</option>
                <option value="Não entregue">Não entregue</option>
                <option value="Capeador">Capeador</option>
                <option value="Liquidado">Liquidado</option>
                <option value="Pago">Pago</option>
              </select>
            </div>

            <div class="col-md-6">
              <label class="form-label">Local de Entrega</label>
              <input type="text" class="form-control" id="editar-local_entrega" name="local_entrega" required>
            </div>

            <div class="col-md-6">
              <label class="form-label">Responsável pela Entrega</label>
              <input type="text" class="form-control" id="editar-nome_responsavel" name="nome_responsavel" required>
            </div>

            <div class="col-md-6">
              <label class="form-label">Contato do Responsável</label>
              <input type="text" class="form-control" id="editar-contato_responsavel" name="contato_responsavel" required>
            </div>

            <div class="col-md-12">
              <label class="form-label">Observação Final</label>
              <textarea class="form-control" id="editar-observacao_final" name="observacao_final" rows="3"></textarea>
            </div>
          </div>

          <hr>
          <h5 class="fw-bold mt-4">Pedidos Vinculados</h5>

          <div id="editarPedidosContainer" class="mb-3">
            <!-- Os selects de pedidos serão inseridos via JS -->
          </div>

          <div class="mb-3">
            <button type="button" class="btn btn-outline-secondary w-100" onclick="adicionarPedidoEdit()">+ Pedido</button>
          </div>

          <div class="d-grid">
            <button type="submit" class="btn btn-warning mt-3">Salvar Alterações</button>
          </div>

        </form>
      </div>
    </div>
  </div>
</div>


<!-- Script da página de Ordens de Fornecimento -->
<script>
    window.funcaoInicializacao = 'inicializarOF';
    
window.selectPedidosDisponiveisTemplate = `<?php

$id_om_usuario = $_SESSION['usuario']['batalhao'] ?? null;
$nivel_usuario = $_SESSION['usuario']['nivel'] ?? null;

// Monta cláusula WHERE para OMs visíveis (se necessário)
$where = '';
if ($nivel_usuario != 1 && $id_om_usuario) {
    $idsVisiveis = [(int)$id_om_usuario];

    $sqlSubs = "SELECT id_om_menor FROM organizacoes_militares_sub WHERE id_om_maior = ?";
    $stmtSubs = $conexao->prepare($sqlSubs);
    $stmtSubs->bind_param('i', $id_om_usuario);
    $stmtSubs->execute();
    $resSubs = $stmtSubs->get_result();
    while ($r = $resSubs->fetch_assoc()) {
        $idsVisiveis[] = (int)$r['id_om_menor'];
    }
    $stmtSubs->close();

    // monta lista segura de inteiros para IN (...)
    $idsVisiveis = array_map('intval', $idsVisiveis);
    $where = 'WHERE p.batalhao IN (' . implode(',', $idsVisiveis) . ')';
}

// Consulta pedidos (aplica filtro de OMs visíveis quando necessário)
$sql = "
  SELECT 
    p.id, 
    p.solicitante,
    p.batalhao,
    om.nome AS nome_om,
    COALESCE((
      SELECT SUM(valor_total) 
      FROM fin_pedidos_forn_itens 
      WHERE id_principal = p.id
    ), 0) AS total_pedido
  FROM fin_pedidos_forn p
  LEFT JOIN organizacoes_militares om ON om.id = p.batalhao
  $where
  ORDER BY p.id DESC
";

$resPedidos = $conexao->query($sql);

if (!$resPedidos || $resPedidos->num_rows === 0) {
    echo '<option value=\"\" disabled>Nenhum pedido disponível</option>';
} else {
    while ($p = $resPedidos->fetch_assoc()) {
        $nomeOm = $p['nome_om'] ? htmlspecialchars($p['nome_om']) : 'Sem OM';
        $solicitante = htmlspecialchars($p['solicitante'] ?? '—');
        $total = number_format((float)$p['total_pedido'], 2, ',', '.');
        echo '<option value=\"' . $p['id'] . '\">Pedido #' . $p['id'] . ' — ' . $nomeOm . ' — ' . $solicitante . ' — Total: R$ ' . $total . '</option>';
    }
}
?>`;

  
</script>