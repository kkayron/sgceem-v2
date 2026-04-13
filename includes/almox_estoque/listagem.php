<?php
header('Content-Type: text/html; charset=utf-8');
if (session_status() === PHP_SESSION_NONE) session_start();
include_once('../../conexao/config.php');

require_once '../api/seguranca.php';

$permissoes = verificarPermissao(33);

$pode_cadastrar = $permissoes['cadastrar'];
$pode_editar    = $permissoes['editar'];
$pode_deletar   = $permissoes['deletar'];
$pode_importar  = $permissoes['importar'];
$pode_exportar  = $permissoes['exportar'];

// =============================
// Dados do usuário
// =============================
$usuario = $_SESSION['usuario'] ?? [];
$nivel_usuario = $usuario['nivel'] ?? 3;
$batalhao_usuario = $usuario['batalhao'] ?? null;

if (!$batalhao_usuario) {
  echo "<div class='alert alert-danger'>Batalhão não identificado.</div>";
  exit;
}

// =============================
// Filtros
// =============================
$id = $_GET['id'] ?? '';
$nome_produto = $_GET['nome_produto'] ?? '';
$codigo_produto = $_GET['codigo_produto'] ?? '';
$categoria_produto = $_GET['categoria_produto'] ?? '';
$data_ini = $_GET['data_ini'] ?? '';
$data_fim = $_GET['data_fim'] ?? '';
$batalhao_filtro = $_GET['batalhao'] ?? '';
$deposito_filtro = $_GET['deposito'] ?? '';

$filtros = [];
$params = [];
$tipos = '';

// =============================
// Paginação
// =============================
$limite = isset($_GET['limite']) ? (int)$_GET['limite'] : 10;
$pagina = isset($_GET['pagina']) && $_GET['pagina'] > 0 ? (int)$_GET['pagina'] : 1;
$offset = ($pagina - 1) * $limite;

// =============================
// Filtros dinâmicos
// =============================
$campos = [
  'p.id' => 'id',
  'p.nome_produto' => 'nome_produto',
  'p.codigo_produto' => 'codigo_produto',
  'p.categoria_produto' => 'categoria_produto',
  'p.data_inclusao >=' => 'data_ini',
  'p.data_inclusao <=' => 'data_fim'
];

foreach ($campos as $coluna => $param) {
  if (!empty($_GET[$param])) {
    if ($coluna === 'p.id') {
      $filtros[] = "$coluna = ?";
      $params[] = (int)$_GET[$param];
      $tipos .= 'i';
    } elseif (str_contains($coluna, '>=')) {
      $filtros[] = "$coluna ?";
      $params[] = $_GET[$param];
      $tipos .= 's';
    } elseif (str_contains($coluna, '<=')) {
      $filtros[] = "$coluna ?";
      $params[] = $_GET[$param];
      $tipos .= 's';
    } else {
      $filtros[] = "$coluna LIKE ?";
      $params[] = '%' . $_GET[$param] . '%';
      $tipos .= 's';
    }
  }
}

// =============================
// Controle por nível
// =============================
if ($nivel_usuario == 1) {

  if (!empty($batalhao_filtro)) {
    $filtros[] = "p.batalhao = ?";
    $params[] = (int)$batalhao_filtro;
    $tipos .= 'i';
  }

} elseif ($nivel_usuario == 2) {

  $sqlSubs = "SELECT id_om_menor FROM organizacoes_militares_sub WHERE id_om_maior = ?";
  $stmtSubs = $conexao->prepare($sqlSubs);
  $stmtSubs->bind_param("i", $batalhao_usuario);
  $stmtSubs->execute();
  $resSubs = $stmtSubs->get_result();

  $permitidos = [$batalhao_usuario];
  while ($r = $resSubs->fetch_assoc()) {
    $permitidos[] = $r['id_om_menor'];
  }

  if (!empty($batalhao_filtro)) {
    if (!in_array($batalhao_filtro, $permitidos)) {
      echo "<div class='alert alert-danger'>Sem permissão.</div>";
      exit;
    }
    $filtros[] = "p.batalhao = ?";
    $params[] = (int)$batalhao_filtro;
    $tipos .= 'i';
  } else {
    $place = implode(',', array_fill(0, count($permitidos), '?'));
    $filtros[] = "p.batalhao IN ($place)";
    $params = array_merge($params, $permitidos);
    $tipos .= str_repeat('i', count($permitidos));
  }

} else {
  $filtros[] = "p.batalhao = ?";
  $params[] = $batalhao_usuario;
  $tipos .= 'i';
}

$where = $filtros ? 'WHERE ' . implode(' AND ', $filtros) : '';

// =============================
// Total de registros (produto + depósito)
// =============================
$sqlTotal = "
  SELECT COUNT(DISTINCT CONCAT(p.id,'-',e.deposito_id)) total
  FROM almox_produtos p
  JOIN almox_entradas_itens ei ON ei.id_produto = p.id
  JOIN almox_entradas e ON e.id = ei.id_entrada
  $where
";

$stmtTotal = $conexao->prepare($sqlTotal);
if ($params) $stmtTotal->bind_param($tipos, ...$params);
$stmtTotal->execute();
$total = $stmtTotal->get_result()->fetch_assoc()['total'];
$totalPaginas = ceil($total / $limite);

// =============================
// CONSULTA PRINCIPAL (ESTOQUE POR DEPÓSITO)
// =============================
$sql = "
SELECT
  p.id,
  p.nome_produto,
  p.codigo_produto,
  p.categoria_produto,
  p.unidade,
  p.estoque_minimo,

  om.nome AS nome_batalhao,
  d.nome_deposito,

  IFNULL(ent.total_entradas, 0) AS total_entradas,
  IFNULL(sai.total_saidas, 0) AS total_saidas,

  (IFNULL(ent.total_entradas, 0) - IFNULL(sai.total_saidas, 0)) AS estoque_atual

FROM almox_produtos p

JOIN organizacoes_militares om
  ON om.id = p.batalhao

-- ENTRADAS (POR PRODUTO + DEPÓSITO)
JOIN (
  SELECT
    ei.id_produto,
    e.deposito_id,
    SUM(ei.quant) AS total_entradas
  FROM almox_entradas_itens ei
  JOIN almox_entradas e ON e.id = ei.id_entrada
  " . (!empty($deposito_filtro) ? "WHERE e.deposito_id = ?" : "") . "
  GROUP BY ei.id_produto, e.deposito_id
) ent
  ON ent.id_produto = p.id

JOIN almox_depositos d
  ON d.id = ent.deposito_id

-- SAÍDAS (POR PRODUTO + DEPÓSITO)
LEFT JOIN (
  SELECT
    pi.id_produto,
    e.deposito_id,
    SUM(pi.quant_solicitada) AS total_saidas
  FROM almox_pedidos_itens pi
  JOIN almox_entradas e ON e.id = pi.id_entrada
  " . (!empty($deposito_filtro) ? "WHERE e.deposito_id = ?" : "") . "
  GROUP BY pi.id_produto, e.deposito_id
) sai
  ON sai.id_produto = ent.id_produto
 AND sai.deposito_id = ent.deposito_id

$where

ORDER BY p.id DESC
LIMIT ? OFFSET ?
";


$paramsDeposito = [];
$tiposDeposito = '';

if (!empty($deposito_filtro)) {
    // entra duas vezes: ent + sai
    $paramsDeposito[] = (int)$deposito_filtro;
    $paramsDeposito[] = (int)$deposito_filtro;
    $tiposDeposito = 'ii';
}


$paramsFinal = array_merge($paramsDeposito, $params, [$limite, $offset]);
$tiposFinal  = $tiposDeposito . $tipos . 'ii';

$stmt = $conexao->prepare($sql);
$stmt->bind_param($tiposFinal, ...$paramsFinal);
$stmt->execute();
$result = $stmt->get_result();

// =============================
// FUNÇÃO DE PAGINAÇÃO (DEVE VIR ANTES DO USO)
// =============================
function renderPaginacaoAlmox($pagina, $totalPaginas, $limite, $queryString, $arquivo = 'includes/almox_estoque/listagem.php') {
    if ($totalPaginas <= 1) return '';

    $html = '<div class="pagination-wrapper mt-3">';
    $html .= '<nav><ul class="pagination pagination-sm justify-content-center">';

    for ($i = 1; $i <= $totalPaginas; $i++) {
        $ativo = ($i == $pagina) ? 'active' : '';
        $url = "{$arquivo}?{$queryString}&pagina=$i&limite=$limite";

        $html .= "
            <li class='page-item $ativo'>
                <a class='page-link paginacao-almox'
                   href='#'
                   data-page='{$url}'>
                   $i
                </a>
            </li>
        ";
    }

    $html .= '</ul></nav></div>';
    return $html;
}


// =============================
// Query string
// =============================
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
        <h3 class="fw-bold mb-1">Listagem de Estoque dos Produtos</h3>
        <h6 class="text-muted">Estoque atual</h6>
      </div>
        <div>
			<?php if ($pode_exportar): ?>
<!-- BOTÃO DE EXPORTAR PDF -->
<button type="button" class="btn btn-danger" onclick="baixarPDF()">
  <i class="fa fa-file-pdf me-1"></i> Baixar PDF
</button>
    <?php endif; ?>
      </div>
    </div>
   <!-- Botão para mostrar/ocultar filtros -->
<!-- Botão de Filtros -->
<div class="mb-3">
  <button class="btn btn-outline-primary w-100 d-flex justify-content-center align-items-center" onclick="toggleFiltrosEstoque()">
    <i class="fas fa-search me-2"></i> Filtros
  </button>
</div>

<!-- Filtros -->
<div id="filtros-container-estoque" style="display: none;" class="mb-3">
  <div class="card shadow-sm border-0">
    <div class="card-body">
      <form method="GET" id="filtroEstoqueForm">
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
    <select name="batalhao" id="batalhao" class="form-select">
        <option value="">Todos</option>
        <?php foreach ($oms_visiveis as $id => $nome): 
            $sel = ($batalhao_filtro == $id) ? 'selected' : '';
        ?>
            <option value="<?= $id ?>" <?= $sel ?>><?= htmlspecialchars($nome) ?></option>
        <?php endforeach; ?>
    </select>
</div>
            <?php
// =============================
// DEPÓSITOS VISÍVEIS (MESMA REGRA DO BATALHÃO)
// =============================
$deposito_filtro = $_GET['deposito'] ?? '';
$batalhao_filtro = $_GET['batalhao'] ?? '';

$depositos = [];

if ($nivel_usuario == 1) {

    // 🔸 Nível 1: vê todos os depósitos
    if (!empty($batalhao_filtro)) {
        $sqlDep = "
            SELECT id, nome_deposito
            FROM almox_depositos
            WHERE batalhao = ?
            ORDER BY nome_deposito
        ";
        $stmtDep = $conexao->prepare($sqlDep);
        $stmtDep->bind_param("i", $batalhao_filtro);
    } else {
        $sqlDep = "
            SELECT id, nome_deposito
            FROM almox_depositos
            ORDER BY nome_deposito
        ";
        $stmtDep = $conexao->prepare($sqlDep);
    }

} elseif ($nivel_usuario == 2) {

    // 🔸 Nível 2: OM do usuário + subordinadas
    $sqlSubs = "
        SELECT id_om_menor
        FROM organizacoes_militares_sub
        WHERE id_om_maior = ?
    ";
    $stmtSubs = $conexao->prepare($sqlSubs);
    $stmtSubs->bind_param("i", $id_om_usuario);
    $stmtSubs->execute();
    $resSubs = $stmtSubs->get_result();

    $omsPermitidas = [$id_om_usuario];
    while ($r = $resSubs->fetch_assoc()) {
        $omsPermitidas[] = $r['id_om_menor'];
    }
    $stmtSubs->close();

    // Se usuário filtrou batalhão, valida
    if (!empty($batalhao_filtro)) {
        if (!in_array($batalhao_filtro, $omsPermitidas)) {
            $depositos = [];
            goto renderDepositos;
        }
        $omsPermitidas = [$batalhao_filtro];
    }

    $placeholders = implode(',', array_fill(0, count($omsPermitidas), '?'));
    $sqlDep = "
        SELECT id, nome_deposito
        FROM almox_depositos
        WHERE batalhao IN ($placeholders)
        ORDER BY nome_deposito
    ";
    $stmtDep = $conexao->prepare($sqlDep);
    $stmtDep->bind_param(str_repeat('i', count($omsPermitidas)), ...$omsPermitidas);

} else {

    // 🔸 Nível 3: apenas sua OM
    if (!empty($batalhao_filtro) && $batalhao_filtro != $id_om_usuario) {
        $depositos = [];
        goto renderDepositos;
    }

    $sqlDep = "
        SELECT id, nome_deposito
        FROM almox_depositos
        WHERE batalhao = ?
        ORDER BY nome_deposito
    ";
    $stmtDep = $conexao->prepare($sqlDep);
    $stmtDep->bind_param("i", $id_om_usuario);
}

// Executa consulta se existir
$stmtDep->execute();
$resDep = $stmtDep->get_result();

while ($d = $resDep->fetch_assoc()) {
    $depositos[] = $d;
}

$stmtDep->close();

renderDepositos:
?>
<div class="col-md-3">
  <label class="form-label fw-semibold">Depósito</label>
  <select name="deposito" id="deposito" class="form-select">
    <option value="">Todos</option>

    <?php foreach ($depositos as $dep): 
        $sel = ($deposito_filtro == $dep['id']) ? 'selected' : '';
    ?>
      <option value="<?= $dep['id'] ?>" <?= $sel ?>>
        <?= htmlspecialchars($dep['nome_deposito']) ?>
      </option>
    <?php endforeach; ?>
  </select>
</div>

            
          <div class="col-md-3">
            <label class="form-label fw-semibold">Nome do Produto</label>
            <input type="text" class="form-control" id="nome_produto" name="nome_produto" value="<?= htmlspecialchars($nome_produto ?? '') ?>">
          </div>
          <div class="col-md-3">
            <label class="form-label fw-semibold">Código do Produto</label>
            <input type="text" class="form-control" id="codigo_produto" name="codigo_produto" value="<?= htmlspecialchars($codigo_produto ?? '') ?>">
          </div>
          <div class="col-md-3">
            <label class="form-label fw-semibold">Categoria</label>
            <input type="text" class="form-control" id="categoria_produto" name="categoria_produto" value="<?= htmlspecialchars($categoria_produto ?? '') ?>">
          </div>
          
          <div class="col-12 d-flex justify-content-between mt-2">
            <button type="button" id="btnLimparFiltrosEstoqueAlmox" class="btn btn-black ms-2">Limpar Filtros</button>
            <button type="submit" class="btn btn-primary px-4">Aplicar</button>
          </div>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Select de limite por página -->
<div class="mb-3">
  <label for="limiteEstoqueAlmox" class="me-2 mb-0">Mostrar</label>
  <select id="limiteEstoqueAlmox" name="limite" class="form-select d-inline w-auto">
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
<div class="paginacao mb-3">
  <?= renderPaginacaoAlmox($pagina, $totalPaginas, $limite, $queryString, 'includes/almox_estoque/listagem.php'); ?>
</div>

<!-- Lista de Produtos por Depósito -->
<div class="card border-0 shadow-sm w-100">
  <div class="card-body p-0">
    <div class="list-group list-group-flush w-100">

      <?php while ($prod = $result->fetch_assoc()): ?>
        <?php
          $estoqueAtual   = (int) $prod['estoque_atual'];
          $estoqueMinimo  = (int) $prod['estoque_minimo'];

          $estoqueStatus = $estoqueAtual <= $estoqueMinimo ? 'danger' : 'success';
          $badgeEstoque  = $estoqueAtual <= $estoqueMinimo
            ? 'Estoque Crítico'
            : 'Estoque OK';
        ?>

        <div class="list-group-item py-3 px-4 w-100 border-0 border-bottom">

          <!-- Linha principal -->
          <div class="d-flex flex-wrap align-items-center justify-content-between w-100 gap-3">

            <!-- Produto -->
            <div class="flex-grow-1" style="min-width:260px;">
              <h6 class="mb-1 fw-bold text-primary">
                <?= htmlspecialchars($prod['nome_produto']) ?>
              </h6>

              <div class="d-flex flex-wrap gap-2 align-items-center">
                <small class="text-muted">
                  <i class="bi bi-upc-scan me-1"></i>
                  <?= htmlspecialchars($prod['codigo_produto']) ?>
                </small>

                <span class="badge bg-light text-dark border">
                  <i class="bi bi-tags me-1"></i>
                  <?= htmlspecialchars($prod['categoria_produto'] ?? 'Sem categoria') ?>
                </span>
              </div>

              <!-- Depósito -->
              <div class="mt-2">
                <span class="badge bg-secondary">
                  <i class="bi bi-box-seam me-1"></i>
                  Depósito: <?= htmlspecialchars($prod['nome_deposito']) ?>
                </span>
              </div>
            </div>

            <!-- Quantidades -->
            <div class="d-flex justify-content-around text-center flex-grow-1" style="min-width:280px;">
              <div>
                <i class="bi bi-box-arrow-in-down text-success fs-5"></i><br>
                <span class="fw-semibold text-success fs-6">
                  <?= (int) $prod['total_entradas'] ?>
                </span><br>
                <small class="text-muted">Entradas</small>
              </div>

              <div>
                <i class="bi bi-box-arrow-up text-danger fs-5"></i><br>
                <span class="fw-semibold text-danger fs-6">
                  <?= (int) $prod['total_saidas'] ?>
                </span><br>
                <small class="text-muted">Saídas</small>
              </div>

              <div>
                <i class="bi bi-archive text-dark fs-5"></i><br>
                <span class="fw-bold fs-5">
                  <?= $estoqueAtual ?>
                </span><br>
                <span class="badge rounded-pill bg-<?= $estoqueStatus ?> px-3">
                  <?= $badgeEstoque ?>
                </span>
              </div>
            </div>

            <!-- Organização Militar -->
            <div class="flex-grow-1 text-end text-muted small" style="min-width:220px;">
              <div class="mb-2">
                <span class="badge bg-info text-dark">
                  <i class="bi bi-building me-1"></i>
                  <?= htmlspecialchars($prod['nome_batalhao']) ?>
                </span>
              </div>

              <div>
                <strong>Estoque Mínimo:</strong> <?= $estoqueMinimo ?>
              </div>
            </div>

          </div>
        </div>
      <?php endwhile; ?>

    </div>
  </div>
</div>




<!-- Paginação inferior -->
<div class="paginacao mt-3">
  <?= renderPaginacaoAlmox($pagina, $totalPaginas, $limite, $queryString, 'includes/almox_estoque/listagem.php'); ?>
</div>





<!-- Script da página de Cadastro de Fornecedores -->
<script>
    window.funcaoInicializacao = 'inicializarAlmoxEstoque';
    
function baixarPDF() {
    // Captura todos os campos de filtro da página de listagem
    const filtros = {};

    // Identifique abaixo todos os IDs dos filtros da sua página:
    const campos = [
        'id', 
        'nome_produto', 
        'deposito', 
        'codigo_produto', 
        'categoria_produto', 
        'data_ini', 
        'data_fim',
        'batalhao'
    ];

    campos.forEach(campo => {
        const el = document.getElementById(campo);
        if (el && el.value.trim() !== '') {
            filtros[campo] = el.value.trim();
        }
    });

    // Caso os filtros estejam sendo usados via URL (como ?nome_produto=...), capturamos também:
    const urlParams = new URLSearchParams(window.location.search);
    urlParams.forEach((valor, chave) => {
        if (!filtros[chave]) filtros[chave] = valor;
    });

    // Monta a URL com os filtros ativos
    const params = new URLSearchParams(filtros).toString();
    const url = `pdf/gerar_estoque_almox.php?${params}`;

    // Abre o PDF (ou baixa automaticamente dependendo do navegador)
    window.open(url, '_blank');
}
    
</script>
