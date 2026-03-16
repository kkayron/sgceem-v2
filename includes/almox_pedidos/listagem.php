<?php
header('Content-Type: text/html; charset=utf-8');
if (session_status() === PHP_SESSION_NONE) session_start();
include_once('../../conexao/config.php');

// ======================================================
// ✅ Diagnóstico/erros (deixe ligado enquanto testa)
// ======================================================
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

function debug_box($titulo, $data) {
    echo "<div class='alert alert-warning' style='white-space:pre-wrap;font-family:monospace;'>";
    echo "<b>" . htmlspecialchars($titulo) . "</b>\n\n";
    echo htmlspecialchars(print_r($data, true));
    echo "</div>";
}

function die_sql($titulo, $sql, $tipos = '', $params = []) {
    echo "<div class='alert alert-danger' style='white-space:pre-wrap; font-family:monospace;'>";
    echo "<b>" . htmlspecialchars($titulo) . "</b>\n\n";
    echo "SQL:\n$sql\n\n";
    if ($tipos !== '') echo "TIPOS: $tipos\n\n";
    if (!empty($params)) echo "PARAMS:\n" . print_r($params, true) . "\n";
    echo "</div>";
    exit;
}

// ======================================================
// ✅ Helpers: OMs visíveis e Prefixos visíveis
// ======================================================
function getIdsOmsVisiveis(mysqli $conexao, int $nivel, int $om_usuario): array {
    if ($nivel === 1) {
        $ids = [];
        $res = $conexao->query("SELECT id FROM organizacoes_militares");
        while ($r = $res->fetch_assoc()) $ids[] = (int)$r['id'];
        return array_values(array_unique($ids));
    }

    if ($om_usuario <= 0) return [];

    $ids = [$om_usuario];
    $sqlSubs = "SELECT id_om_menor FROM organizacoes_militares_sub WHERE id_om_maior = ?";
    $stmt = $conexao->prepare($sqlSubs);
    if (!$stmt) return array_values(array_unique($ids));

    $stmt->bind_param("i", $om_usuario);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()) $ids[] = (int)$r['id_om_menor'];
    $stmt->close();

    return array_values(array_unique($ids));
}

function getOmsVisiveisParaSelect(mysqli $conexao, int $nivel, int $om_usuario): array {
    $ids = getIdsOmsVisiveis($conexao, $nivel, $om_usuario);
    if (empty($ids)) return [];

    $ph = implode(',', array_fill(0, count($ids), '?'));
    $tipos = str_repeat('i', count($ids));

    $sql = "SELECT id, nome, abreviatura FROM organizacoes_militares WHERE id IN ($ph) ORDER BY nome";
    $stmt = $conexao->prepare($sql);
    if (!$stmt) return [];

    $stmt->bind_param($tipos, ...$ids);
    $stmt->execute();
    $res = $stmt->get_result();

    $oms = [];
    while ($r = $res->fetch_assoc()) {
        $id = (int)$r['id'];
        $oms[$id] = ($r['abreviatura'] ?: $r['nome']);
    }
    $stmt->close();

    return $oms;
}

function getPrefixosVisiveis(mysqli $conexao, int $nivel, int $om_usuario): array {
    if ($nivel === 1) {
        $sql = "
            SELECT DISTINCT prefixo_sga
            FROM frota
            WHERE prefixo_sga IS NOT NULL AND prefixo_sga <> ''
            ORDER BY prefixo_sga
        ";
        $res = $conexao->query($sql);
        $out = [];
        while ($r = $res->fetch_assoc()) $out[] = $r['prefixo_sga'];
        return $out;
    }

    if ($om_usuario <= 0) return [];

    $ids = getIdsOmsVisiveis($conexao, $nivel, $om_usuario);
    if (empty($ids)) return [];

    $ph = implode(',', array_fill(0, count($ids), '?'));
    $tipos = str_repeat('i', count($ids));

    $sql = "
        SELECT DISTINCT f.prefixo_sga
        FROM frota f
        WHERE f.batalhao IN ($ph)
          AND f.prefixo_sga IS NOT NULL
          AND f.prefixo_sga <> ''
        ORDER BY f.prefixo_sga
    ";

    $stmt = $conexao->prepare($sql);
    if (!$stmt) return [];

    $stmt->bind_param($tipos, ...$ids);
    $stmt->execute();
    $res = $stmt->get_result();

    $out = [];
    while ($r = $res->fetch_assoc()) $out[] = $r['prefixo_sga'];
    $stmt->close();

    return $out;
}

try {
    // ======================================================
    // ✅ Sessão do usuário (forçando tipos corretos)
    // ======================================================
    $usuario = $_SESSION['usuario'] ?? [];
    $funcao_id = (int)($_SESSION['funcao_id'] ?? 0);

    $nivel_usuario = isset($usuario['nivel']) ? (int)$usuario['nivel'] : 3;
    $batalhao_usuario = isset($usuario['batalhao']) ? (int)$usuario['batalhao'] : 0;

    if ($batalhao_usuario <= 0) {
        echo "<div class='alert alert-danger'>Erro: batalhão do usuário não identificado na sessão.</div>";
        exit;
    }

    // ======================================================
    // ✅ Dados para os filtros (SEM rodar SQL no meio do HTML)
    // ======================================================
    $oms_visiveis  = getOmsVisiveisParaSelect($conexao, $nivel_usuario, $batalhao_usuario);
    $prefixos_sga  = getPrefixosVisiveis($conexao, $nivel_usuario, $batalhao_usuario);

    // ======================================================
    // ✅ Filtros recebidos
    // ======================================================
    $id            = $_GET['id'] ?? '';
    $solicitante   = $_GET['solicitante'] ?? '';
    $secao         = $_GET['secao'] ?? '';
    $status        = $_GET['status'] ?? '';
    $data_ini      = $_GET['data_ini'] ?? '';
    $data_fim      = $_GET['data_fim'] ?? '';
    $batalhao_filtro = isset($_GET['batalhao']) ? (int)$_GET['batalhao'] : 0;
    $prefixo_sga_filtro   = $_GET['prefixo_sga'] ?? '';
    $chassi_vtr    = $_GET['chassi'] ?? '';

    // ======================================================
    // ✅ Paginação (com trava)
    // ======================================================
    $limite = (isset($_GET['limite']) && is_numeric($_GET['limite'])) ? (int)$_GET['limite'] : 10;
    $limite = max(1, min(100, $limite));

    $pagina = (isset($_GET['pagina']) && is_numeric($_GET['pagina']) && (int)$_GET['pagina'] > 0) ? (int)$_GET['pagina'] : 1;
    $offset = ($pagina - 1) * $limite;

    // ======================================================
    // ✅ Montagem dos filtros
    // ======================================================
    $filtros = [];
    $params  = [];
    $tipos   = '';

    if ($id !== '' && is_numeric($id)) {
        $filtros[] = "p.id = ?";
        $params[] = (int)$id;
        $tipos .= 'i';
    }

    if ($solicitante !== '') {
        $filtros[] = "p.militar_solicitante LIKE ?";
        $params[] = "%$solicitante%";
        $tipos .= 's';
    }

    if ($secao !== '') {
        $filtros[] = "p.secao_solicitante LIKE ?";
        $params[] = "%$secao%";
        $tipos .= 's';
    }

    if ($status !== '') {
        $filtros[] = "p.status_pedido LIKE ?";
        $params[] = "%$status%";
        $tipos .= 's';
    }

    if ($data_ini !== '') {
        $filtros[] = "p.data_pedido >= CONCAT(?, ' 00:00:00')";
        $params[]  = $data_ini;
        $tipos    .= 's';
    }
    if ($data_fim !== '') {
        $filtros[] = "p.data_pedido < DATE_ADD(CONCAT(?, ' 00:00:00'), INTERVAL 1 DAY)";
        $params[]  = $data_fim;
        $tipos    .= 's';
    }

    if ($prefixo_sga_filtro !== '') {
        $filtros[] = "os.prefixo_sga LIKE ?";
        $params[] = "%$prefixo_sga_filtro%";
        $tipos .= 's';
    }
    if ($chassi_vtr !== '') {
        $filtros[] = "f.chassi LIKE ?";
        $params[] = "%$chassi_vtr%";
        $tipos .= 's';
    }

    // ======================================================
    // ✅ Regra de visibilidade por nível
    // ======================================================
    if ($nivel_usuario === 1) {
        if ($batalhao_filtro > 0) {
            $filtros[] = "p.batalhao = ?";
            $params[] = $batalhao_filtro;
            $tipos .= 'i';
        }
    } elseif ($nivel_usuario === 2) {
        $permitidos = getIdsOmsVisiveis($conexao, $nivel_usuario, $batalhao_usuario);

        if ($batalhao_filtro > 0) {
            if (!in_array($batalhao_filtro, $permitidos, true)) {
                echo "<div class='alert alert-danger'>Você não tem permissão para visualizar esse batalhão.</div>";
                exit;
            }
            $filtros[] = "p.batalhao = ?";
            $params[] = $batalhao_filtro;
            $tipos .= 'i';
        } else {
            if (empty($permitidos)) {
                $filtros[] = "1=0";
            } else {
                $ph = implode(',', array_fill(0, count($permitidos), '?'));
                $filtros[] = "p.batalhao IN ($ph)";
                foreach ($permitidos as $idPerm) {
                    $params[] = (int)$idPerm;
                    $tipos .= 'i';
                }
            }
        }
    } else {
        // ✅ Nível 3: SEMPRE a própria OM
        $filtros[] = "p.batalhao = ?";
        $params[] = $batalhao_usuario;
        $tipos .= 'i';
    }

    $condicoes = !empty($filtros) ? 'WHERE ' . implode(' AND ', $filtros) : '';

    // ======================================================
    // ✅ Total
    // ======================================================
    $sqlTotal = "
        SELECT COUNT(*) AS total
        FROM almox_pedidos_princ p
        LEFT JOIN os_principal os ON p.id_os = os.id
        LEFT JOIN frota f ON f.id = os.id_frota
        $condicoes
    ";

    $stmtTotal = $conexao->prepare($sqlTotal);
    if (!$stmtTotal) die_sql("ERRO prepare TOTAL", $sqlTotal, $tipos, $params);

    if (!empty($params)) $stmtTotal->bind_param($tipos, ...$params);
    $stmtTotal->execute();
    $resTotal = $stmtTotal->get_result();
    $totalRegistros = (int)($resTotal->fetch_assoc()['total'] ?? 0);
    $stmtTotal->close();

    $totalPaginas = max((int)ceil($totalRegistros / $limite), 1);
    if ($pagina > $totalPaginas) {
        $pagina = $totalPaginas;
        $offset = ($pagina - 1) * $limite;
    }

    // ======================================================
    // ✅ Lista
    // ======================================================
    $sql = "
        SELECT 
            p.*,
            os.prefixo_sga,
            os.data_abertura,
            os.problema,
            os.id_frota,
            om.nome AS nome_batalhao,
            om.abreviatura AS abreviatura_batalhao,
            f.chassi AS chassi
        FROM almox_pedidos_princ p
        LEFT JOIN os_principal os ON p.id_os = os.id
        LEFT JOIN organizacoes_militares om ON om.id = p.batalhao 
        LEFT JOIN frota f ON f.id = os.id_frota
        $condicoes
        ORDER BY p.id DESC
        LIMIT ? OFFSET ?
    ";

    $paramsFinal = $params;
    $paramsFinal[] = $limite;
    $paramsFinal[] = $offset;
    $tiposFinal = $tipos . "ii";

    $stmt = $conexao->prepare($sql);
    if (!$stmt) die_sql("ERRO prepare LISTA", $sql, $tiposFinal, $paramsFinal);

    $stmt->bind_param($tiposFinal, ...$paramsFinal);
    $stmt->execute();
    $pedidos = $stmt->get_result();

    // ======================================================
    // ✅ QueryString paginação
    // ======================================================
    $paramsGET = $_GET;
    unset($paramsGET['pagina']);
    $queryString = http_build_query($paramsGET);

    // ======================================================
    // ✅ Função paginação
    // ======================================================
    function renderPaginacaoPedidosAlmox($pagina, $totalPaginas, $limite, $queryString, $arquivo = 'includes/almox_pedidos/listagem.php') {
        if ($totalPaginas <= 1) return '';
        $html = '<div class="pagination-wrapper"><nav><ul class="pagination pagination-sm">';
        for ($i = 1; $i <= $totalPaginas; $i++) {
            $ativo = ($i == $pagina) ? 'active' : '';
            $url = "{$arquivo}?{$queryString}&pagina={$i}&limite={$limite}";
            $html .= "<li class='page-item {$ativo}'><a class='page-link paginacao-pedidos' href='#' data-page='{$url}'>{$i}</a></li>";
        }
        $html .= '</ul></nav></div>';
        return $html;
    }

    // ✅ Debug opcional (descomente para ver o que está sendo aplicado no nível 3)
    // debug_box("DEBUG NIVEL/OM", ['nivel'=>$nivel_usuario,'om'=>$batalhao_usuario,'condicoes'=>$condicoes,'tipos'=>$tipos,'params'=>$params]);

} catch (Throwable $e) {
    echo "<div class='alert alert-danger' style='white-space:pre-wrap; font-family:monospace;'>";
    echo "<b>ERRO FATAL/EXCEPTION</b>\n\n";
    echo htmlspecialchars($e->getMessage()) . "\n\n";
    echo "Arquivo: " . htmlspecialchars($e->getFile()) . "\n";
    echo "Linha: " . (int)$e->getLine() . "\n";
    echo "</div>";
    exit;
}
?>



<style>
     .pagination-wrapper {
    display: flex;
    justify-content: center;
    margin: 1rem 0;
}

.pagination {
    display: flex;
    list-style: none;
    padding-left: 0;
    gap: 0.5rem;
}

.pagination .page-item .page-link {
    display: flex;
    align-items: center;
    justify-content: center;
    min-width: 38px;
    height: 38px;
    padding: 0.5rem 0.75rem;
    font-size: 0.9rem;
    color: #6c757d;
    background-color: #fff;
    border: 1px solid #dee2e6;
    border-radius: 0.375rem;
    transition: all 0.3s ease-in-out;
}

.pagination .page-item .page-link:hover {
    background-color: #f8f9fa;
    color: #495057;
    text-decoration: none;
    transform: translateY(-2px);
    box-shadow: 0 4px 6px rgba(0,0,0,0.1);
}

.pagination .page-item.active .page-link {
    background-color: #0d6efd;
    color: #fff;
    border-color: #0d6efd;
    font-weight: bold;
    box-shadow: 0 4px 8px rgba(13, 110, 253, 0.4);
}
    .btn-group-responsive {
  display: flex;
  gap: 8px;
  flex-wrap: wrap;
  margin-top: 10px;
}
@media (min-width: 768px) {
  .btn-group-responsive {
    flex: 0 0 auto;
    margin-left: auto;
  }
  .btn-icon {
    display: none;
  }
}
@media (max-width: 767px) {
  .btn-text {
    display: none;
  }
  .btn-group-responsive {
    margin-top: 10px;
    width: 100%;
  }
}
.btn-group .btn {
  border-radius: 20px;
  transition: all 0.3s ease;
    margin-left: 0;
    }
  .filtro-label { font-size: 0.85rem; font-weight: 600; color: #555; }
  .filtros-container.hidden { display: none; }
  .pagination-wrapper { display: flex; justify-content: flex-end; margin-top: 1rem; }
    #filtros-container-os {
  overflow: hidden;
  transition: height 0.3s ease, opacity 0.3s ease;
}
    .modal-xl .modal-body {
  max-height: 80vh;
  overflow-y: auto;
}
</style>

<div class="container">
  <div class="page-inner">
    <div class="d-flex justify-content-between align-items-center py-3">
      <div>
        <h3 class="fw-bold mb-1">Listagem dos Pedidos ao Almox</h3>
        <h6 class="text-muted">Listagem dos pedidos realizados ou em andamento.</h6>
      </div>
      <div>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalCadastrarPedidoAlmox">
          <i class="fa fa-plus me-1"></i> Cadastrar Pedido
        </button>
      </div>
    </div>

    <div class="mb-3">
  <button class="btn btn-outline-primary w-100 d-flex justify-content-center align-items-center" onclick="toggleFiltrosPedidos()">
    <i class="fas fa-search me-2"></i> Filtros
  </button>
</div>
      
      

<div id="filtros-container-pedidos" style="display: none;" class="mb-3">
  <div class="card shadow-sm border-0">
    <div class="card-body">
      <form method="GET" id="filtroPedidosAlmoxForm">
        <div class="row g-3">
          <div class="col-md-2">
            <label class="form-label fw-semibold">ID do Pedido</label>
            <input type="text" class="form-control" name="id" value="<?= htmlspecialchars($_GET['id'] ?? '') ?>">
          </div>
          <div class="col-md-3">
            <label class="form-label fw-semibold">Solicitante</label>
            <input type="text" class="form-control" name="solicitante" value="<?= htmlspecialchars($_GET['solicitante'] ?? '') ?>">
          </div>
          <div class="col-md-3">
            <label class="form-label fw-semibold">Seção Solicitante</label>
            <input type="text" class="form-control" name="secao" value="<?= htmlspecialchars($_GET['secao'] ?? '') ?>">
          </div>
          <div class="col-md-3">
            <label class="form-label fw-semibold">Status</label>
            <input type="text" class="form-control" name="status" value="<?= htmlspecialchars($_GET['status'] ?? '') ?>">
          </div>
          <div class="col-md-3">
            <label class="form-label fw-semibold">Data Inicial</label>
            <input type="date" class="form-control" name="data_ini" value="<?= htmlspecialchars($_GET['data_ini'] ?? '') ?>">
          </div>
          <div class="col-md-3">
            <label class="form-label fw-semibold">Data Final</label>
            <input type="date" class="form-control" name="data_fim" value="<?= htmlspecialchars($_GET['data_fim'] ?? '') ?>">
          </div>
           
            <div class="col-md-3">
    <label class="form-label fw-semibold">Batalhão</label>
<select name="batalhao" class="form-select">
  <option value="">Todos</option>
  <?php
    $batalhao_filtro_html = $_GET['batalhao'] ?? '';
    foreach ($oms_visiveis as $id => $nome):
      $sel = ((string)$batalhao_filtro_html === (string)$id) ? 'selected' : '';
  ?>
    <option value="<?= (int)$id ?>" <?= $sel ?>><?= htmlspecialchars($nome) ?></option>
  <?php endforeach; ?>
</select>
</div>
            <div class="col-md-3">
  <label class="form-label fw-semibold">
    <i class="fas fa-truck me-1"></i> Prefixo VTR
  </label>
<select name="prefixo_sga" class="form-select">
  <option value="">Todos</option>
  <?php $prefixo_filtro = $_GET['prefixo_sga'] ?? ''; ?>
  <?php foreach ($prefixos_sga as $p): ?>
    <option value="<?= htmlspecialchars($p) ?>" <?= ($prefixo_filtro === $p ? 'selected' : '') ?>>
      <?= htmlspecialchars($p) ?>
    </option>
  <?php endforeach; ?>
</select>
</div>
            <div class="col-md-3">
  <label class="form-label fw-semibold">
    <i class="fas fa-barcode me-1"></i> Chassi
  </label>
  <input type="text"
         class="form-control"
         name="chassi"
         placeholder="Número do chassi"
         value="<?= htmlspecialchars($_GET['chassi'] ?? '') ?>">
</div>

          <div class="col-12 d-flex justify-content-between mt-2">
            <button type="button" id="btnLimparFiltrosPedidosAlmox" class="btn btn-black ms-2">Limpar Filtros</button>
            <button type="submit" class="btn btn-primary px-4">Aplicar</button>
          </div>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Limite de itens -->
<div class="mb-3">
  <label for="limitePedidoAlmox" class="me-2 mb-0">Mostrar</label>
  <select id="limitePedidoAlmox" name="limite" class="form-select d-inline w-auto" onchange="atualizarLimitePedidoAlmox()">
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
  <?= renderPaginacaoPedidosAlmox($pagina, $totalPaginas, $limite, $queryString, 'includes/almox_pedidos/listagem.php'); ?>
</div>

<!-- LISTAGEM -->
<div class="card border-0 shadow-sm">
  <div class="card-body">
    <div class="list-group">
      <?php if ($pedidos->num_rows > 0): ?>
        <?php while ($pedido = $pedidos->fetch_assoc()): ?>
          <?php
          // Conta itens
          $stmt = $conexao->prepare("SELECT COUNT(*) AS total_itens FROM almox_pedidos_itens WHERE id_pedido_principal = ?");
          $stmt->bind_param("i", $pedido['id']);
          $stmt->execute();
          $res = $stmt->get_result();
          $contagem = $res->fetch_assoc();
          $total_itens = $contagem['total_itens'] ?? 0;
          $stmt->close();

          // Define cor do status
          $status = $pedido['status_pedido'] ?? 'Indefinido';
          $badgeClass = match(strtolower($status)) {
              'pendente'   => 'bg-warning text-dark',
              'aprovado'   => 'bg-primary',
              'atendido'   => 'bg-success',
              'cancelado'  => 'bg-danger',
              default      => 'bg-secondary'
          };

          // Nome do batalhão
          $nomeOM = $pedido['nome_batalhao'] ?? 'Organização Militar não informada';
          $siglaOM = $pedido['abreviatura_batalhao'] ?? '';
          ?>

          <div class="list-group-item list-group-item-action flex-column align-items-start mb-3 p-3 border-0 shadow-sm rounded-3">
            <div class="d-flex justify-content-between align-items-center mb-2">
              <div>
                <h6 class="mb-0 fw-semibold text-primary">
                  <i class="fas fa-clipboard-list me-1 text-secondary"></i>
                  Pedido #<?= htmlspecialchars($pedido['id']) ?> - 
                </h6>
                <small class="text-muted">
                  <i class="far fa-calendar-alt me-1"></i>
                  <?= !empty($pedido['data_pedido']) ? date('d/m/Y', strtotime($pedido['data_pedido'])) : '-' ?>
                </small>
              </div>
              <span class="badge <?= $badgeClass ?> px-3 py-2 rounded-pill fs-6">
                <?= htmlspecialchars(ucfirst($status)) ?>
              </span>
            </div>

            <div class="border-start ps-3 mb-2">
              <p class="mb-1 text-body-secondary small">
                <i class="fas fa-building me-1 text-primary"></i>
                <strong><?= htmlspecialchars($siglaOM) ?></strong> — <?= htmlspecialchars($nomeOM) ?>
              </p>
              <p class="mb-1 text-secondary small">
                <i class="fas fa-tools me-1"></i> OS: <?= htmlspecialchars($pedido['id_os'] ?? '-') ?> 
                — <i class="fas fa-car me-1"></i> Prefixo: <?= htmlspecialchars($pedido['prefixo_sga'] ?? '-') ?>
              </p>
              <p class="mb-1 text-secondary small">
                <i class="fas fa-barcode me-1"></i> Chassi: <?= htmlspecialchars($pedido['chassi'] ?? '-') ?>
              </p>
            </div>

            <div class="d-flex flex-column small text-body-secondary mb-3">
              <div><strong>Local:</strong> <?= htmlspecialchars($pedido['local_pedido'] ?? '-') ?></div>
              <div><strong>Solicitante:</strong> <?= htmlspecialchars($pedido['militar_solicitante'] ?? '-') ?></div>
              <div><strong>Seção:</strong> <?= htmlspecialchars($pedido['secao_solicitante'] ?? '-') ?></div>
              <div><strong>Problema da OS:</strong> <?= htmlspecialchars($pedido['problema'] ?? '-') ?></div>
            </div>

            <div class="bg-light border rounded-3 text-center py-2 px-3 mb-3">
              <i class="fas fa-boxes text-primary me-1"></i> 
              <strong><?= $total_itens ?></strong> <?= $total_itens == 1 ? 'item' : 'itens' ?> no pedido
            </div>

            <div class="d-flex align-items-center gap-2 flex-wrap justify-content-end">
                <?php
$funcoesPermitidas = [1, 8, 9, 10];
$podeAutorizar = in_array($_SESSION['funcao_id'] ?? 0, $funcoesPermitidas);
?>
<button
  class="btn btn-sm 
    <?= $pedido['autorizacao'] === 'sim' ? 'btn-success' : 'btn-danger' ?>
    <?= $podeAutorizar ? 'btn-toggle-autorizacao-almox' : '' ?>"
  
  data-id="<?= $pedido['id'] ?>"
  data-autorizacao="<?= $pedido['autorizacao'] ?>"
  <?= !$podeAutorizar ? 'disabled' : '' ?>
>

  <?php if ($pedido['autorizacao'] === 'sim'): ?>
    <i class="fas fa-check-circle me-1"></i> Autorizado
  <?php else: ?>
    <i class="fas fa-ban me-1"></i> Não autorizado
  <?php endif; ?>

</button>



                
              <button 
                class="btn btn-sm btn-outline-secondary" 
                type="button" 
                data-bs-toggle="collapse" 
                data-bs-target="#itensPedidoAlmox<?= $pedido['id'] ?>" 
                aria-expanded="false" 
                aria-controls="itensPedidoAlmox<?= $pedido['id'] ?>">
                <i class="fas fa-eye me-1"></i> Ver itens
              </button>

              <!-- Imprimir -->
              <div class="dropdown">
                <button class="btn btn-sm btn-outline-secondary dropdown-toggle" 
                        type="button" 
                        id="dropdownMenu<?= $pedido['id'] ?>" 
                        data-bs-toggle="dropdown" 
                        aria-expanded="false">
                  <i class="fas fa-print me-1"></i> Imprimir
                </button>
                <ul class="dropdown-menu dropdown-menu-end shadow-sm rounded" aria-labelledby="dropdownMenu<?= $pedido['id'] ?>">
                  <li>
                    <a href="#" 
                       class="dropdown-item text-danger btnExportarPDFpedido" 
                       data-id="<?= $pedido['id'] ?>">
                      <i class="fas fa-file-pdf me-2"></i> Imprimir Pedido
                    </a>
                  </li>
                </ul>
              </div>

              <button
                class="btn btn-sm btn-outline-warning"
                onclick="editarPedidoAlmox(<?= $pedido['id'] ?>)"
                data-bs-toggle="modal"
                data-bs-target="#modalEditarPedidoAlmox"
              >
                <i class="fas fa-edit me-1"></i> Editar
              </button>

              <button 
                type="button"
                class="btn btn-sm btn-outline-danger d-flex align-items-center gap-1 btn-deletar-pedido-almox"
                data-id="<?= $pedido['id'] ?>"
                onclick="deletarPedidoAlmox(this)"
                data-bs-toggle="tooltip"
                title="Remover Pedido"
              >
                <i class="fa fa-times"></i> 
                <span class="d-none d-md-inline">Remover</span>
              </button>
            </div>

            <div class="collapse mt-3" id="itensPedidoAlmox<?= $pedido['id'] ?>">
              <div class="card card-body border-0 bg-light">
                <?php
                // Busca os itens do pedido com JOIN
                $stmtItens = $conexao->prepare("
                    SELECT 
                        pi.id,
                        pi.quant_solicitada,
                        p.nome_produto,
                        ei.marca,
                        ei.modelo
                    FROM almox_pedidos_itens pi
                    LEFT JOIN almox_produtos p ON pi.id_produto = p.id
                    LEFT JOIN almox_entradas_itens ei ON pi.id_entrada = ei.id
                    WHERE pi.id_pedido_principal = ?
                ");
                $stmtItens->bind_param("i", $pedido['id']);
                $stmtItens->execute();
                $resItens = $stmtItens->get_result();
                ?>

                <?php if ($resItens->num_rows > 0): ?>
                  <ul class="list-group list-group-flush">
                    <?php while($item = $resItens->fetch_assoc()): ?>
                      <li class="list-group-item bg-transparent">
                        <div class="d-flex justify-content-between">
                          <div>
                            <strong><?= htmlspecialchars($item['nome_produto'] ?? 'N/A') ?></strong><br>
                            <small class="text-muted">
                              Marca: <?= htmlspecialchars($item['marca'] ?? 'N/A') ?> |
                              Modelo: <?= htmlspecialchars($item['modelo'] ?? 'N/A') ?>
                            </small>
                          </div>
                          <div class="text-end">
                            <span class="badge bg-light text-dark border">
                              Qtd: <?= htmlspecialchars($item['quant_solicitada'] ?? 0) ?>
                            </span>
                          </div>
                        </div>
                      </li>
                    <?php endwhile; ?>
                  </ul>
                <?php else: ?>
                  <p class="text-muted">Nenhum item encontrado.</p>
                <?php endif; ?>
                <?php $stmtItens->close(); ?>
              </div>
            </div>
          </div>
        <?php endwhile; ?>
      <?php else: ?>
        <div class="alert alert-info text-center">Nenhum pedido encontrado.</div>
      <?php endif; ?>
    </div>
  </div>
</div>



<div class="paginacao mt-3">
  <?= renderPaginacaoPedidosAlmox($pagina, $totalPaginas, $limite, $queryString, 'includes/almox_pedidos/listagem.php'); ?>
</div>
      
      


<!-- Modal de Edição dos Pedidos do Almoxarifado -->
<div class="modal fade" id="modalEditarPedidoAlmox" tabindex="-1" aria-labelledby="modalEditarPedidoAlmoxLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-xl">
    <div class="modal-content">
      <div class="modal-header bg-warning text-dark">
        <h5 class="modal-title" id="modalEditarPedidoAlmoxLabel">Editar Pedido ao Almoxarifado</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
      </div>

      <div class="modal-body">
        <form method="POST" enctype="multipart/form-data" id="form-pedido-almox-editar">
          <input type="hidden" name="id_pedido" id="editar-id-pedido">

          <div class="row g-3">
            <div class="col-md-4">
              <label class="form-label">Data do Pedido</label>
              <input type="date" class="form-control" name="data_pedido" id="editar-data-pedido" required>
            </div>

            <?php
            // ============================
            // BATALHÕES QUE O USUÁRIO PODE VISUALIZAR
            // ============================
            $id_om_usuario = $_SESSION['usuario']['batalhao'] ?? 0;
            $sqlNivel = "SELECT nivel FROM organizacoes_militares WHERE id = $id_om_usuario";
            $resNivel = $conexao->query($sqlNivel);
            $nivelUsuario = $resNivel->fetch_assoc()['nivel'] ?? 0;

            if ($nivelUsuario == 1) {
                $sqlBatalhoesEditar = "SELECT id, nome, abreviatura FROM organizacoes_militares ORDER BY nome";
            } else {
                $sqlBatalhoesEditar = "
                    SELECT om.id, om.nome, om.abreviatura
                    FROM organizacoes_militares om
                    WHERE om.id = $id_om_usuario
                    OR om.id IN (
                        SELECT id_om_menor FROM organizacoes_militares_sub WHERE id_om_maior = $id_om_usuario
                    )
                    ORDER BY om.nome
                ";
            }
            $resBatalhoesEditar = $conexao->query($sqlBatalhoesEditar);
            ?>
            <div class="col-md-4">
              <label class="form-label">Batalhão</label>
              <select name="batalhao" id="editar-batalhao" class="form-select" required>
                <option value="">Selecione...</option>
                <?php while ($bat = $resBatalhoesEditar->fetch_assoc()): ?>
                  <option value="<?= $bat['id'] ?>" <?= ($bat['id']==$id_om_usuario)?'selected':'' ?>>
                    <?= htmlspecialchars($bat['abreviatura'] . ' - ' . $bat['nome']) ?>
                  </option>
                <?php endwhile; ?>
              </select>
            </div>

            <?php
            // ==== Destinos Permitidos ====
            $batalhoesPermitidosEditar = [];
            if ($nivelUsuario == 1) {
                $sqlEditar = "SELECT id FROM organizacoes_militares";
                $resEditar = $conexao->query($sqlEditar);
                while ($row = $resEditar->fetch_assoc()) {
                    $batalhoesPermitidosEditar[] = $row['id'];
                }
            } else {
                $batalhoesPermitidosEditar[] = $id_om_usuario;
                $sqlSubsEditar = "SELECT id_om_menor FROM organizacoes_militares_sub WHERE id_om_maior = ?";
                $stmtSubsEditar = $conexao->prepare($sqlSubsEditar);
                $stmtSubsEditar->bind_param("i", $id_om_usuario);
                $stmtSubsEditar->execute();
                $resSubsEditar = $stmtSubsEditar->get_result();
                while ($r = $resSubsEditar->fetch_assoc()) {
                    $batalhoesPermitidosEditar[] = $r['id_om_menor'];
                }
                $stmtSubsEditar->close();
            }

            $destinosEditar = [];
            if (!empty($batalhoesPermitidosEditar)) {
                $placeholdersEditar = implode(',', array_fill(0, count($batalhoesPermitidosEditar), '?'));
                $sqlDestinosEditar = "
                    SELECT cd.destino, om.nome AS nome_batalhao
                    FROM config_destinos cd
                    JOIN organizacoes_militares om ON cd.batalhao = om.id
                    WHERE cd.batalhao IN ($placeholdersEditar)
                    ORDER BY cd.destino ASC
                ";
                $stmtDestinosEditar = $conexao->prepare($sqlDestinosEditar);
                $tiposEditar = str_repeat('i', count($batalhoesPermitidosEditar));
                $stmtDestinosEditar->bind_param($tiposEditar, ...$batalhoesPermitidosEditar);
                $stmtDestinosEditar->execute();
                $resDestinosEditar = $stmtDestinosEditar->get_result();
                while ($row = $resDestinosEditar->fetch_assoc()) {
                    $destinosEditar[] = $row;
                }
                $stmtDestinosEditar->close();
            }
            ?>

            <div class="col-md-4">
              <label class="form-label">Local do Pedido</label>
              <select class="form-select" name="local_pedido" id="editar-local-pedido" required>
                <option value="" disabled selected>Selecione o local do pedido</option>
                <?php foreach ($destinosEditar as $dest): ?>
                  <option value="<?= htmlspecialchars($dest['destino']) ?>">
                    <?= htmlspecialchars($dest['destino'] . ' - ' . $dest['nome_batalhao']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="col-md-4">
              <label class="form-label">Solicitante</label>
              <input type="text" class="form-control" name="militar_solicitante" id="editar-militar-solicitante" required>
            </div>

            <div class="col-md-4">
              <label class="form-label">Ordem de Serviço (OS)</label>
              <select class="form-select" name="id_os" id="editar-id-os" required>
                <option value="" disabled selected>Selecione a OS</option>
                <?php
                // ------------------------------
                // Filtra as OS conforme batalhões permitidos
                // ------------------------------
                $condicaoBatalhoesEditar = '';
                if (!empty($batalhoesPermitidosEditar)) {
                    $idsEditar = implode(',', array_map('intval', $batalhoesPermitidosEditar));
                    $condicaoBatalhoesEditar = "AND os.batalhao IN ($idsEditar)";
                } else {
                    $condicaoBatalhoesEditar = "AND os.batalhao = 0";
                }

                // ------------------------------
                // Consulta OS com nome do batalhão
                // ------------------------------
                $sqlOSEditar = "
                  SELECT 
                    os.id, 
                    os.prefixo_sga, 
                    os.problema, 
                    om.nome AS nome_batalhao
                  FROM os_principal os
                  INNER JOIN organizacoes_militares om ON os.batalhao = om.id
                  WHERE os.status IN ('Em andamento', 'Aguardando Peças', 'Aguardando Suprimento')
                  $condicaoBatalhoesEditar
                  ORDER BY os.id ASC
                ";

                $resOSEditar = $conexao->query($sqlOSEditar);
                if ($resOSEditar && $resOSEditar->num_rows > 0):
                  while ($os = $resOSEditar->fetch_assoc()):
                ?>
                    <option value="<?= $os['id'] ?>">
                      OS #<?= htmlspecialchars($os['id']) ?> — 
                      <?= htmlspecialchars($os['prefixo_sga']) ?> — 
                      Problema: <?= htmlspecialchars($os['problema']) ?> - <?= htmlspecialchars($os['nome_batalhao']) ?>
                    </option>
                <?php
                  endwhile;
                else:
                  echo '<option disabled>Nenhuma OS disponível para seu batalhão</option>';
                endif;
                ?>
                <option value="Sem OS">Sem OS</option>
              </select>
            </div>

            <div class="col-md-4">
              <label class="form-label">Seção Responsável</label>
              <select class="form-select" name="secao_rspns" id="editar-secao-responsavel" required>
                <option value="" disabled selected>Selecione a seção responsável</option>
                <option value="Mecânica Leve">Mecânica Leve</option>
                <option value="Mecânica Pesada">Mecânica Pesada</option>
                <option value="Borracharia">Borracharia</option>
                <option value="Elétrica">Elétrica</option>
                <option value="Solda">Solda</option>
                <option value="Lubrificação">Lubrificação</option>
                <option value="Pintura">Pintura</option>
                <option value="Outra oficina">Outra oficina</option>
              </select>
            </div>

            <div class="col-md-4">
              <label class="form-label">Situação do Pedido</label>
              <select class="form-select" name="status_pedido" id="editar-status-pedido" required>
                <option value="" disabled selected>Selecione a situação</option>
                <option value="Não enviado ao almox">Não enviado ao almox</option>
                <option value="Agd Fornecimento">Agd Fornecimento</option>
                <option value="Retirado do almox">Retirado do almox</option>
              </select>
            </div>
          </div>

          <hr>
          <h5 class="fw-bold mt-4">Itens do Pedido</h5>
          <div id="itensPedidoAlmoxContainerEditar" class="mb-3"></div>

          <div>
            <button type="button" class="btn btn-info btn-sm" onclick="adicionarItemPedidoAlmoxEditar()">Adicionar item</button>
          </div>

          <div class="alert alert-secondary text-end fw-bold mt-3" id="somaTotalItensAlmoxEditar">
            Valor total dos itens: R$ 0,00
          </div>

          <button type="submit" class="btn btn-warning w-100 mt-4">Salvar Alterações</button>
        </form>
      </div>
    </div>
  </div>
</div>

<!-- Modal de Cadastro dos Pedidos do Almoxarifado -->
<div class="modal fade" id="modalCadastrarPedidoAlmox" tabindex="-1" aria-labelledby="modalCadastrarPedidoAlmoxLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-xl">
    <div class="modal-content">
      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title" id="modalCadastrarPedidoAlmoxLabel">Cadastrar Pedido ao Almoxarifado</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Fechar"></button>
      </div>
      <div class="modal-body">
        <form method="POST" enctype="multipart/form-data" id="form-pedido-almox-cadastrar">
<div class="row g-3">
    
            <div class="col-md-4">
              <label class="form-label">Data do Pedido</label>
              <input type="date" class="form-control" name="data_pedido" required>
            </div>
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
                  <select name="batalhao" id="batalhao" class="form-select" required>
                    <option value="">Selecione...</option>
                    <?php while ($bat = $resBatalhoes->fetch_assoc()): ?>
                      <option value="<?= $bat['id'] ?>" <?= ($bat['id']==$id_om_usuario)?'selected':'' ?>>
                        <?= htmlspecialchars($bat['abreviatura'] . ' - ' . $bat['nome']) ?>
                      </option>
                    <?php endwhile; ?>
                  </select>
                </div>
    
    <?php
                // ==== Destinos Permitidos ====
                $batalhoesPermitidos = [];
                if ($nivel_usuario == 1) {
                    $sql = "SELECT id FROM organizacoes_militares";
                    $res = $conexao->query($sql);
                    while ($row = $res->fetch_assoc()) {
                        $batalhoesPermitidos[] = $row['id'];
                    }
                } else {
                    $batalhoesPermitidos[] = $id_om_usuario;
                    $sqlSubs = "SELECT id_om_menor FROM organizacoes_militares_sub WHERE id_om_maior = ?";
                    $stmtSubs = $conexao->prepare($sqlSubs);
                    $stmtSubs->bind_param("i", $id_om_usuario);
                    $stmtSubs->execute();
                    $resSubs = $stmtSubs->get_result();
                    while ($r = $resSubs->fetch_assoc()) {
                        $batalhoesPermitidos[] = $r['id_om_menor'];
                    }
                    $stmtSubs->close();
                }

                $destinos = [];
                if (!empty($batalhoesPermitidos)) {
                    $placeholders = implode(',', array_fill(0, count($batalhoesPermitidos), '?'));
                    $sqlDestinos = "
                        SELECT cd.destino, om.nome AS nome_batalhao
                        FROM config_destinos cd
                        JOIN organizacoes_militares om ON cd.batalhao = om.id
                        WHERE cd.batalhao IN ($placeholders)
                        ORDER BY cd.destino ASC
                    ";
                    $stmtDestinos = $conexao->prepare($sqlDestinos);
                    $tipos = str_repeat('i', count($batalhoesPermitidos));
                    $stmtDestinos->bind_param($tipos, ...$batalhoesPermitidos);
                    $stmtDestinos->execute();
                    $resDestinos = $stmtDestinos->get_result();
                    while ($row = $resDestinos->fetch_assoc()) {
                        $destinos[] = $row;
                    }
                    $stmtDestinos->close();
                }
                ?>

                <div class="col-md-4">
                  <label for="local_os" class="form-label">Local do pedido</label>
                  <select class="form-select" id="local_pedido" name="local_pedido" required>
                    <option value="" disabled selected>Selecione o local do pedido</option>
                    <?php foreach ($destinos as $dest): ?>
                      <option value="<?= htmlspecialchars($dest['destino']) ?>">
                        <?= htmlspecialchars($dest['destino'] . ' - ' . $dest['nome_batalhao']) ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </div>

            <div class="col-md-4">
              <label class="form-label">Solicitante</label>
              <input type="text" class="form-control" name="militar_solicitante" required>
            </div>

            
<div class="col-md-4">
  <label class="form-label">Ordem de Serviço (OS)</label>
  <select class="form-select" name="id_os" required>
    <option value="" disabled selected>Selecione a OS</option>
    <?php
    // ------------------------------
    // Filtra as OS conforme batalhões permitidos
    // ------------------------------
    $condicaoBatalhoes = '';
    if (!empty($batalhoesPermitidos)) {
        // Monta lista de IDs válidos (ex: 1,2,3)
        $ids = implode(',', array_map('intval', $batalhoesPermitidos));
        $condicaoBatalhoes = "AND os.batalhao IN ($ids)";
    } else {
        // Se não houver batalhões, garante que não trará nenhuma OS
        $condicaoBatalhoes = "AND os.batalhao = 0";
    }

    // ------------------------------
    // Consulta OS com nome do batalhão
    // ------------------------------
    $sqlOS = "
      SELECT 
        os.id, 
        os.prefixo_sga, 
        os.problema, 
        om.nome AS nome_batalhao
      FROM os_principal os
      INNER JOIN organizacoes_militares om ON os.batalhao = om.id
      WHERE os.status IN ('Em andamento', 'Aguardando Peças', 'Aguardando Suprimento')
      $condicaoBatalhoes
      ORDER BY os.id ASC
    ";

    $resOS = $conexao->query($sqlOS);
    if ($resOS && $resOS->num_rows > 0):
      while ($os = $resOS->fetch_assoc()):
    ?>
        <option value="<?= $os['id'] ?>">
          OS #<?= htmlspecialchars($os['id']) ?> — 
          <?= htmlspecialchars($os['prefixo_sga']) ?> — 
          Problema: <?= htmlspecialchars($os['problema']) ?> - <?= htmlspecialchars($os['nome_batalhao']) ?>
        </option>
    <?php
      endwhile;
    else:
      echo '<option disabled>Nenhuma OS disponível para seu batalhão</option>';
    endif;
    ?>
    <option value="Sem OS">Sem OS</option>
  </select>
</div>


            <div class="col-md-4">
              <label class="form-label">Seção Responsável</label>
              <select class="form-select" name="secao_rspns" required>
                <option value="" disabled selected>Selecione a seção responsável</option>
                    <option value="Mecânica Leve">Mecânica Leve</option>
                    <option value="Mecânica Pesada">Mecânica Pesada</option>
                    <option value="Borracharia">Borracharia</option>
                    <option value="Elétrica">Elétrica</option>
                    <option value="Solda">Solda</option>
                    <option value="Lubrificação">Lubrificação</option>
                    <option value="Pintura">Pintura</option>
                    <option value="Outra oficina">Outra oficina</option>
              </select>
            </div>

            <div class="col-md-4">
              <label class="form-label">Situação do Pedido</label>
              <select class="form-select" name="status_pedido" required>
                <option value="" disabled selected>Selecione a situação</option>
                <option value="Não enviado ao almox">Não enviado ao almox</option>
                <option value="Agd Fornecimento">Agd Fornecimento</option>
                <option value="Retirado do almox">Retirado do almox</option>
              </select>
            </div>


            

          <hr>
          <h5 class="fw-bold mt-4">Itens do Pedido</h5>
          <div id="itensPedidoAlmoxContainer" class="mb-3"></div>
          <div>
            <button type="button" class="btn btn-info btn-sm" onclick="adicionarItemPedidoAlmox()">Adicionar item</button>
          </div>

          <div class="alert alert-secondary text-end fw-bold mt-3" id="somaTotalItensAlmox">
            Valor total dos itens: R$ 0,00
          </div>

          <button type="submit" class="btn btn-success w-100 mt-4">Cadastrar Pedido</button>
        </form>
      </div>
    </div>
  </div>
</div>


<!-- Script da página de Requisição de Vtr/Eqp -->
<script>
    window.funcaoInicializacao = 'inicializarAlmoxPedidos';
document.querySelectorAll('.btn-toggle-autorizacao-almox').forEach(botao => {
  botao.addEventListener('click', function () {
    toggleAutorizacaoPedidoAlmox(this);
  });
});
    
</script>


