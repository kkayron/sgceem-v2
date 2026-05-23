<?php
// ==============================
// 🔹 Segurança
// ==============================
header('Content-Type: text/html; charset=utf-8');
session_start();

require_once '../api/seguranca.php';

$permissoes = verificarPermissao([14]);

$pode_cadastrar = $permissoes['cadastrar'];
$pode_editar    = $permissoes['editar'];
$pode_deletar   = $permissoes['deletar'];
$pode_importar  = $permissoes['importar'];
$pode_exportar  = $permissoes['exportar'];
$pode_autorizar  = $permissoes['autorizar'];

if (!isset($_SESSION['usuario_id'])) {
  http_response_code(401);
  echo "<div class='alert alert-danger'>Sessão expirada. Faça login novamente.</div>";
  exit;
}

// BLOQUEAR ACESSO DIRETO VIA URL
if (
    !isset($_SERVER['HTTP_X_REQUESTED_WITH']) ||
    strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) !== 'xmlhttprequest'
) {
    http_response_code(403);
    echo "<div class='alert alert-danger'>Acesso direto não permitido.</div>";
    exit;
}

include_once('../../conexao/config.php');

// ==============================
// 🔹 Dados do usuário logado
// ==============================
$id_om_usuario = $_SESSION['usuario']['batalhao'] ?? null;
$nivel_usuario = $_SESSION['usuario']['nivel'] ?? 3;

// ==============================
// 🔹 Inicializa filtros
// ==============================
$filtros = [];
$params = [];
$tipos = '';

// Paginação
$limite = isset($_GET['limite']) && is_numeric($_GET['limite']) ? (int) $_GET['limite'] : 10;
$pagina = isset($_GET['pagina']) && is_numeric($_GET['pagina']) && $_GET['pagina'] > 0 ? (int) $_GET['pagina'] : 1;
$offset = ($pagina - 1) * $limite;

// Campos filtráveis
$campos = ['ativo', 'tipo', 'marca', 'modelo', 'ano', 'confiabilidade', 'subunidade', 'acervo', 'destino', 'disponibilidade'];

// ==============================
// 🔹 Filtros dinâmicos
// ==============================
foreach ($campos as $campo) {
    if (!empty($_GET[$campo])) {
        $filtros[] = "f.$campo = ?";
        $params[] = $_GET[$campo];
        $tipos .= 's';
    }
}

// ==============================
// 🔹 Filtro textual
// ==============================
if (!empty($_GET['texto'])) {
    $texto = '%' . $_GET['texto'] . '%';
    $filtros[] = "(f.prefixo_sga LIKE ? OR f.placa LIKE ? OR f.tipo LIKE ?)";
    $params = array_merge($params, [$texto, $texto, $texto]);
    $tipos .= 'sss';
}

// ==============================
// 🔹 Controle de visualização por OM
// ==============================
$idsVisiveis = [];

if ($nivel_usuario == 1) {
    // Nível 1 → todas as OMs
    $sqlOmg = "SELECT id FROM organizacoes_militares";
    $res = $conexao->query($sqlOmg);
    while ($row = $res->fetch_assoc()) {
        $idsVisiveis[] = $row['id'];
    }
} else {
    // Nível 2 ou 3 → OM própria + subordinadas
    $idsVisiveis[] = $id_om_usuario;
    $sqlSubs = "SELECT id_om_menor FROM organizacoes_militares_sub WHERE id_om_maior = ?";
    $stmtSubs = $conexao->prepare($sqlSubs);
    $stmtSubs->bind_param("i", $id_om_usuario);
    $stmtSubs->execute();
    $resSubs = $stmtSubs->get_result();
    while ($r = $resSubs->fetch_assoc()) {
        $idsVisiveis[] = $r['id_om_menor'];
    }
    $stmtSubs->close();
}

// ==============================
// 🔹 Filtro de batalhão (manual)
// ==============================
if (!empty($_GET['batalhao'])) {
    $batalhaoFiltro = (int) $_GET['batalhao'];

    // Só aplica se o batalhão estiver entre os visíveis
    if (in_array($batalhaoFiltro, $idsVisiveis)) {
        $filtros[] = "f.batalhao = ?";
        $params[] = $batalhaoFiltro;
        $tipos .= 'i';
    } else {
        // Caso tente filtrar um batalhão não autorizado
        $filtros[] = "1=0"; // Nenhum resultado
    }
} else {
    // Se não filtrou, aplica restrição de OMs visíveis
    if (!empty($idsVisiveis)) {
        $placeholders = implode(',', array_fill(0, count($idsVisiveis), '?'));
        $filtros[] = "f.batalhao IN ($placeholders)";
        $params = array_merge($params, $idsVisiveis);
        $tipos .= str_repeat('i', count($idsVisiveis));
    }
}

// ==============================
// 🔹 Filtro do batalhão proprietário (manual)
// ==============================
if (!empty($_GET['batalhao_origem'])) {
    $batalhaoFiltro2 = (int) $_GET['batalhao_origem'];
	$filtros[] = "f.batalhao_origem = ?";
	$params[] = $batalhaoFiltro2;
    $tipos .= 'i';
} 
// ==============================
// 🔹 Consulta total (para paginação)
// ==============================
$sqlTotal = "SELECT COUNT(*) AS total FROM frota f";
if (!empty($filtros)) {
    $sqlTotal .= " WHERE " . implode(" AND ", $filtros);
}

$stmtTotal = $conexao->prepare($sqlTotal);
if (!empty($params)) {
    $stmtTotal->bind_param($tipos, ...$params);
}
$stmtTotal->execute();
$resultTotal = $stmtTotal->get_result();
$totalRegistros = $resultTotal->fetch_assoc()['total'] ?? 0;
$totalPaginas = max(ceil($totalRegistros / $limite), 1);

// ==============================
// 🔹 Consulta principal
// ==============================
$sql = "SELECT 
            f.*, 
            cm.marca AS nome_marca, 
            md.nome_modelo AS nome_modelo,
            om.nome AS nome_om,
            om.abreviatura AS sigla_om,
            om_origem.abreviatura AS sigla_om_origem
        FROM frota f
        LEFT JOIN config_marcas cm ON f.marca = cm.id
        LEFT JOIN config_modelos md ON f.modelo = md.id
        LEFT JOIN organizacoes_militares om ON f.batalhao = om.id
	    LEFT JOIN organizacoes_militares om_origem ON f.batalhao_origem = om_origem.id";


if (!empty($filtros)) {
    $sql .= " WHERE " . implode(" AND ", $filtros);
}

$sql .= " ORDER BY f.prefixo_sga ASC LIMIT ? OFFSET ?";
$params[] = $limite;
$params[] = $offset;
$tipos .= 'ii';

$stmt = $conexao->prepare($sql);
$stmt->bind_param($tipos, ...$params);
$stmt->execute();
$result = $stmt->get_result();



// ==============================
// 🔹 Função de paginação (Responsiva)
// ==============================
function renderPaginacaoFROTA($pagina, $totalPaginas, $limite, $queryString, $arquivo = 'includes/frota/listagem.php') {

    // define quantidade de links do "miolo" conforme tela (mobile vs desktop)
    // Obs: usamos CSS pra alternar o que aparece, mas o PHP já limita o bloco.
    $maxLinksDesktop = 10;
    $maxLinksMobile  = 3; // miolo bem compacto (atual + vizinhos)

    $makeUrl = function($p) use ($arquivo, $queryString, $limite) {
        return "{$arquivo}?{$queryString}&pagina={$p}&limite={$limite}";
    };

    // helper para criar item
    $li = function($label, $page, $disabled = false, $active = false, $extraClass = '') use ($makeUrl) {
        $cls = "page-item";
        if ($disabled) $cls .= " disabled";
        if ($active)   $cls .= " active";
        if ($extraClass) $cls .= " {$extraClass}";

        if ($disabled) {
            return "<li class='{$cls}'><span class='page-link'>{$label}</span></li>";
        }

        return "<li class='{$cls}'><a class='page-link paginacao-frota' href='#' data-page='".$makeUrl($page)."'>{$label}</a></li>";
    };

    // cálculo do miolo (desktop e mobile)
    $calcRange = function($maxLinks) use ($pagina, $totalPaginas) {
        $inicio = max(1, $pagina - floor($maxLinks / 2));
        $fim    = min($totalPaginas, $inicio + $maxLinks - 1);

        if (($fim - $inicio) < ($maxLinks - 1)) {
            $inicio = max(1, $fim - $maxLinks + 1);
        }
        return [$inicio, $fim];
    };

    [$inicioDesktop, $fimDesktop] = $calcRange($maxLinksDesktop);
    [$inicioMobile,  $fimMobile]  = $calcRange($maxLinksMobile);

    $html  = '<div class="pagination-wrapper">';
    $html .= '<nav aria-label="Paginação Frota">';
    $html .= '<ul class="pagination pagination-sm flex-wrap justify-content-center gap-1">';

    // ===============================
    // 🔹 PRIMEIRA e ANTERIOR
    // ===============================
    $html .= ($pagina > 1)
        ? $li('&laquo;', 1, false, false, 'd-inline')   // ícone sempre
        : $li('&laquo;', 1, true,  false, 'd-inline');

    $html .= ($pagina > 1)
        ? $li('&lsaquo;', $pagina - 1, false, false, 'd-inline')
        : $li('&lsaquo;', 1, true, false, 'd-inline');

    // ===============================
    // 🔹 BLOCO DESKTOP (mais páginas)
    // ===============================
    $html .= "<span class='d-none d-md-inline'>";

    // sempre 1
    $html .= $li('1', 1, false, $pagina == 1);

    if ($inicioDesktop > 2) {
        $html .= $li('...', 1, true);
    }

    for ($i = max(2, $inicioDesktop); $i <= min($fimDesktop, $totalPaginas - 1); $i++) {
        $html .= $li((string)$i, $i, false, $pagina == $i);
    }

    if ($fimDesktop < $totalPaginas - 1) {
        $html .= $li('...', 1, true);
    }

    if ($totalPaginas > 1) {
        $html .= $li((string)$totalPaginas, $totalPaginas, false, $pagina == $totalPaginas);
    }

    $html .= "</span>";

    // ===============================
    // 🔹 BLOCO MOBILE (compacto)
    // ===============================
    $html .= "<span class='d-inline d-md-none'>";

    // 1 sempre
    $html .= $li('1', 1, false, $pagina == 1);

    if ($inicioMobile > 2) {
        $html .= $li('...', 1, true);
    }

    // miolo: evita repetir 1 e última
    for ($i = max(2, $inicioMobile); $i <= min($fimMobile, $totalPaginas - 1); $i++) {
        $html .= $li((string)$i, $i, false, $pagina == $i);
    }

    if ($fimMobile < $totalPaginas - 1) {
        $html .= $li('...', 1, true);
    }

    if ($totalPaginas > 1) {
        $html .= $li((string)$totalPaginas, $totalPaginas, false, $pagina == $totalPaginas);
    }

    $html .= "</span>";

    // ===============================
    // 🔹 PRÓXIMA e ÚLTIMA
    // ===============================
    $html .= ($pagina < $totalPaginas)
        ? $li('&rsaquo;', $pagina + 1, false, false, 'd-inline')
        : $li('&rsaquo;', $totalPaginas, true, false, 'd-inline');

    $html .= ($pagina < $totalPaginas)
        ? $li('&raquo;', $totalPaginas, false, false, 'd-inline')
        : $li('&raquo;', $totalPaginas, true, false, 'd-inline');

    $html .= '</ul></nav></div>';

    return $html;
}
// ==============================
// 🔹 Mantém query string dos filtros
// ==============================
$params = $_GET;
unset($params['pagina']);
$queryString = http_build_query($params);
$filtrosVisiveis = !empty($_GET);
?>



<style>
.frota-item {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: 20px;
  padding: 15px;
  border-bottom: 1px solid #ddd;
}
.frota-info {
  display: flex;
  align-items: center;
  gap: 20px;
  flex: 1 1 100%;
}
.frota-item img {
  width: 100;
  height: 100;
  object-fit: cover;
  border-radius: 50%;
  border: 2px solid #ccc;
}
.btn-group-responsive {
  display: flex;
  gap: 8px;
  flex-wrap: wrap;
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
    margin-left: 10px;
</style>

<div class="container">
  <div class="page-inner">
    <div class="d-flex justify-content-between align-items-center py-3">
      <div>
        <h3 class="fw-bold mb-1">Listagem da Frota</h3>
        <h6 class="text-muted">Vtr/Eqp cadastrados</h6>
      </div>
        
        
        <div class="btn-group">
            <?php if ($pode_exportar): ?>
            <button id="btnExportarExcelFrota" class="btn btn-success">
            <i class="fas fa-file-excel"></i> Exportar Excel
            </button>
            <?php endif; ?>
        </div>
    </div>

    <div class="card">
   <!-- Botão para mostrar/ocultar filtros -->
        
<div class="mb-3">
  <button class="btn btn-outline-primary w-100 d-flex justify-content-center align-items-center" onclick="toggleFiltros()">
    <i class="fas fa-search me-2"></i> Filtros
  </button>
</div>
        

<!-- Área dos filtros -->
<div class="d-flex justify-content-between align-items-center mb-3">
  <div id="filtros-container" style="display: none;">
    <div class="card shadow-sm border-0">
      <div class="card-body">
        <form method="GET" id="filtroFrotaForm">
          <div class="row g-3">

            <?php
            // ====================================
            // 🔹 Recupera as OMs autorizadas
            // ====================================
            $id_om_usuario = $_SESSION['usuario']['batalhao'] ?? 0;
            $nivel_usuario = $_SESSION['usuario']['nivel'] ?? 3;

            $oms_visiveis = [];

            if ($nivel_usuario == 1) {
                // Nível 1: todas as OMs
                $sql_oms = "SELECT id, nome, abreviatura FROM organizacoes_militares ORDER BY nome";
                $stmt_oms = $conexao->prepare($sql_oms);
            } elseif ($nivel_usuario == 2) {
                // Nível 2: sua OM e subordinadas
                $sql_oms = "
                    SELECT om.id, om.nome, om.abreviatura
                    FROM organizacoes_militares om
                    JOIN organizacoes_militares_sub sub ON om.id = sub.id_om_menor
                    WHERE sub.id_om_maior = ?
                    UNION
                    SELECT id, nome, abreviatura FROM organizacoes_militares WHERE id = ?
                    ORDER BY nome
                ";
                $stmt_oms = $conexao->prepare($sql_oms);
                $stmt_oms->bind_param("ii", $id_om_usuario, $id_om_usuario);
            } else {
                // Nível 3: apenas sua OM
                $sql_oms = "SELECT id, nome, abreviatura FROM organizacoes_militares WHERE id = ?";
                $stmt_oms = $conexao->prepare($sql_oms);
                $stmt_oms->bind_param("i", $id_om_usuario);
            }

            $stmt_oms->execute();
            $res_oms = $stmt_oms->get_result();
            while ($r = $res_oms->fetch_assoc()) {
                $oms_visiveis[$r['id']] = $r['abreviatura'] ?: $r['nome'];
            }
            $stmt_oms->close();

            // ====================================
            // 🔹 Função para gerar selects
            // ====================================
            function gerarSelect($conexao, $coluna, $label) {
                // Marca
                if ($coluna === 'marca') {
                    $sql = "SELECT id, marca AS nome FROM config_marcas ORDER BY nome";
                    $stmt = $conexao->prepare($sql);
                    $stmt->execute();
                    $result = $stmt->get_result();

                    echo '<div class="col-md-3">';
                    echo "<label class=\"form-label fw-semibold\">$label</label>";
                    echo "<select name=\"$coluna\" class=\"form-select\">";
                    echo "<option value=\"\">Todos</option>";
                    while ($row = $result->fetch_assoc()) {
                        $id = htmlspecialchars($row['id']);
                        $nome = htmlspecialchars($row['nome']);
                        $sel = (isset($_GET[$coluna]) && $_GET[$coluna] == $id) ? 'selected' : '';
                        echo "<option value=\"$id\" $sel>$nome</option>";
                    }
                    echo "</select></div>";
                    return;
                }

                // Modelo
                if ($coluna === 'modelo') {
                    $sql = "SELECT id, nome_modelo AS nome FROM config_modelos ORDER BY nome";
                    $stmt = $conexao->prepare($sql);
                    $stmt->execute();
                    $result = $stmt->get_result();

                    echo '<div class="col-md-3">';
                    echo "<label class=\"form-label fw-semibold\">$label</label>";
                    echo "<select name=\"$coluna\" class=\"form-select\">";
                    echo "<option value=\"\">Todos</option>";
                    while ($row = $result->fetch_assoc()) {
                        $id = htmlspecialchars($row['id']);
                        $nome = htmlspecialchars($row['nome']);
                        $sel = (isset($_GET[$coluna]) && $_GET[$coluna] == $id) ? 'selected' : '';
                        echo "<option value=\"$id\" $sel>$nome</option>";
                    }
                    echo "</select></div>";
                    return;
                }

                // Padrão: valores da tabela frota
                $stmt = $conexao->prepare("SELECT DISTINCT $coluna FROM frota WHERE $coluna IS NOT NULL AND $coluna != '' ORDER BY $coluna");
                $stmt->execute();
                $result = $stmt->get_result();

                echo '<div class="col-md-3">';
                echo "<label class=\"form-label fw-semibold\">$label</label>";
                echo "<select name=\"$coluna\" class=\"form-select\">";
                echo "<option value=\"\">Todos</option>";
                while ($row = $result->fetch_assoc()) {
                    $valor = htmlspecialchars($row[$coluna]);
                    $sel = (isset($_GET[$coluna]) && $_GET[$coluna] === $valor) ? 'selected' : '';
                    echo "<option value=\"$valor\" $sel>$valor</option>";
                }
                echo "</select></div>";
            }

            // ====================================
            // 🔹 Select de batalhões (OMs visíveis)
            // ====================================
            echo '<div class="col-md-3">';
            echo '<label class="form-label fw-semibold">Organização Militar</label>';
            echo '<select name="batalhao" class="form-select">';
            echo '<option value="">Todos</option>';
            foreach ($oms_visiveis as $id => $nome) {
                $sel = (isset($_GET['batalhao']) && $_GET['batalhao'] == $id) ? 'selected' : '';
                echo "<option value=\"$id\" $sel>$nome</option>";
            }
            echo '</select>';
            echo '</div>';

			  // ====================================
// 🔹 Select de OMs proprietárias
// que possuem ativos emprestados
// para OMs visíveis ao usuário
// ====================================
echo '<div class="col-md-3">';
echo '<label class="form-label fw-semibold">OM Proprietária</label>';
echo '<select name="batalhao_origem" class="form-select">';
echo '<option value="">Todas</option>';

$idsOmsVisiveis = array_keys($oms_visiveis);

if (!empty($idsOmsVisiveis)) {

    $placeholders = implode(',', array_fill(0, count($idsOmsVisiveis), '?'));
    $types = str_repeat('i', count($idsOmsVisiveis));

    $sqlOmsOrigem = "
        SELECT DISTINCT
            om.id,
            om.nome,
            om.abreviatura
        FROM frota f
        INNER JOIN organizacoes_militares om
            ON om.id = f.batalhao_origem
        WHERE f.batalhao IN ($placeholders)
          AND f.batalhao_origem IS NOT NULL
          AND f.batalhao_origem <> 0
          AND f.batalhao <> f.batalhao_origem
        ORDER BY om.nome
    ";

    $stmtOrigem = $conexao->prepare($sqlOmsOrigem);

    $paramsOrigem = array_merge([$types], $idsOmsVisiveis);
    $tmpOrigem = [];

    foreach ($paramsOrigem as $k => $v) {
        $tmpOrigem[$k] = &$paramsOrigem[$k];
    }

    call_user_func_array([$stmtOrigem, 'bind_param'], $tmpOrigem);

    $stmtOrigem->execute();
    $resOrigem = $stmtOrigem->get_result();

    while ($om = $resOrigem->fetch_assoc()) {

        $id = (int)$om['id'];

        $nome = htmlspecialchars(
            $om['abreviatura'] ?: $om['nome']
        );

        $sel = (
            isset($_GET['batalhao_origem'])
            && (int)$_GET['batalhao_origem'] === $id
        ) ? 'selected' : '';

        echo "<option value=\"{$id}\" {$sel}>{$nome}</option>";
    }

    $stmtOrigem->close();
}

echo '</select>';
echo '</div>';
            // ====================================
            // 🔹 Outros filtros padrão
            // ====================================
            gerarSelect($conexao, 'ativo', 'Ativo');
            gerarSelect($conexao, 'tipo', 'Tipo');
            gerarSelect($conexao, 'marca', 'Marca');
            gerarSelect($conexao, 'modelo', 'Modelo');
            gerarSelect($conexao, 'ano', 'Ano');
            gerarSelect($conexao, 'confiabilidade', 'Confiabilidade/Status');
            gerarSelect($conexao, 'subunidade', 'Subunidade');
            gerarSelect($conexao, 'acervo', 'Acervo');
            gerarSelect($conexao, 'destino', 'Destino');
            gerarSelect($conexao, 'disponibilidade', 'Disponibilidade');
            ?>

            <!-- Busca geral -->
            <div class="col-md-3">
              <label class="form-label fw-semibold">Busca geral</label>
              <input type="text" name="texto" class="form-control" placeholder="Prefixo, placa, etc." value="<?= htmlspecialchars($_GET['texto'] ?? '') ?>">
            </div>

            <!-- Botões -->
            <div class="col-12 d-flex justify-content-between">
              <button type="button" id="btnLimparFiltros" class="btn btn-black ms-2">Limpar Filtros</button>
              <button type="submit" class="btn btn-primary px-4">Aplicar</button>
            </div>

          </div>
        </form>
      </div>
    </div>
  </div>
</div>

        
             <div style="">
    <label for="limite" class="me-2 mb-0">Mostrar</label>
    <select id="limite" name="limite" class="form-select d-inline w-auto" onchange="atualizarLimite()">
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

  <div class="card-body">
         <!-- Paginação superior -->
<div class="paginacao d-flex justify-content-center mt-2">
  <?= renderPaginacaoFROTA($pagina, $totalPaginas, $limite, $queryString, 'includes/frota/listagem.php'); ?>
</div>
    <?php
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {

        $imagem = !empty($row['foto_capa']) ? 'uploads/frotas/' . $row['foto_capa'] : 'uploads/frotas/sem-imagem.png';
        $id = (int)$row['id'];

        $badgeClass = ($row['disponibilidade'] === 'Indisponível') ? 'badge-danger'
                    : (($row['disponibilidade'] === 'Disponível com restrição') ? 'badge-warning' : 'badge-success');

        $btnEditar = '';
        if (!empty($pode_editar)) {
            $btnEditar = '
                <button type="button" class="btn btn-warning btn-sm"
                        data-bs-toggle="modal" data-bs-target="#modalEditarFrota"
                        onclick="editarFrota(' . $id . ')">
                    <i class="fa fa-edit"></i>
                </button>';
        }
		
		$btnEmprestimo = '';

if (!empty($pode_editar)) {

    $usuario_batalhao = $_SESSION['usuario']['batalhao'] ?? 0;

    $batalhaoAtual = (int)($row['batalhao'] ?? 0);
    $batalhaoOrigem = (int)($row['batalhao_origem'] ?? 0);

    // Se batalhao_origem estiver vazio, NULL ou 0,
    // considera o batalhao atual como origem
    if ($batalhaoOrigem <= 0) {
        $batalhaoOrigem = $batalhaoAtual;
    }

    if ($batalhaoAtual === $batalhaoOrigem) {

        $btnEmprestimo = '
            <button type="button"
                    class="btn btn-primary btn-sm"
                    data-bs-toggle="modal"
                    data-bs-target="#modalEmprestimoFrota"
                    onclick="abrirModalEmprestimo(' . $id . ', \'emprestimo\')"
                    title="Realizar empréstimo">
                <i class="fa fa-share"></i>
            </button>';

    } else {

        $btnEmprestimo = '
            <button type="button"
                    class="btn btn-success btn-sm"
                    data-bs-toggle="modal"
                    data-bs-target="#modalEmprestimoFrota"
                    onclick="abrirModalEmprestimo(' . $id . ', \'devolucao\')"
                    title="Realizar devolução">
                <i class="fa fa-undo"></i>
            </button>';
    }
}
        
        $btnDeletar = '';
         if (!empty($pode_deletar)) {
            $btnDeletar = '
                <button type="button"
                        class="btn btn-sm btn-danger"
                        data-id="' . $id . '"
                        data-token="' . htmlspecialchars($_SESSION['csrf_token']) . '"
                        onclick="deletarFrota(this)"
                        data-bs-toggle="tooltip"
                        title="Remover">
                    <i class="fa fa-times"></i>
                </button>';
        }
		
		$infoOmProprietaria = '';

$batalhaoAtual = (int)($row['batalhao'] ?? 0);
$batalhaoOrigem = (int)($row['batalhao_origem'] ?? 0);

if ($batalhaoOrigem > 0 && $batalhaoAtual !== $batalhaoOrigem) {
    $infoOmProprietaria = '
        <b>Ativo emprestado oriundo do:</b>
        <span class="badge badge-secondary">' . htmlspecialchars($row['sigla_om_origem'] ?? '-') . '</span>
        |
    ';
}

        echo '
        <div class="frota-item">
            <div class="frota-info">
                <img src="' . htmlspecialchars($imagem) . '" alt="Imagem de ' . htmlspecialchars($row['prefixo_sga']) . '">
                <div>
                    <h5 class="mb-1">' . htmlspecialchars($row['prefixo_sga']) . ' |
                        <span class="badge ' . $badgeClass . '">' . htmlspecialchars($row['disponibilidade']) . '</span>
						 
						 
                    </h5>
                    <h6><b>Status/Confiabilidade:</b> ' . htmlspecialchars($row['confiabilidade']) . '</h6>
					 <h6>
                <b>OM:</b>
                <span class="badge badge-secondary">' . htmlspecialchars($row['sigla_om'] ?? '-') . '</span>
                |
                ' . $infoOmProprietaria . '
            </h6>
                    <h6><b>Local:</b> ' . htmlspecialchars($row['destino']) . '</h6>
                    <p class="mb-0 text-muted">
                        <b>Placa:</b> ' . htmlspecialchars($row['placa']) . ' |
                        <b>Tipo:</b> ' . htmlspecialchars($row['tipo']) . ' |
                        <b>Acervo:</b> ' . htmlspecialchars($row['acervo']) . ' |
                        <b>Marca:</b> ' . htmlspecialchars($row['nome_marca'] ?? '-') . ' |
                        <b>Modelo:</b> ' . htmlspecialchars($row['nome_modelo'] ?? '-') . '
                    </p>
                    <p class="mb-0 text-muted">
                        <b>Chassi:</b> ' . htmlspecialchars($row['chassi']) . ' |
                        <b>EB:</b> ' . htmlspecialchars($row['nmr_eb']) . ' |
                        <b>Patrimonio:</b> ' . htmlspecialchars($row['nmr_patrimonio']) . '
                    </p>
                </div>
            </div>

            <div class="btn-group-responsive">
                <button class="btn btn-primary btn-sm"
                        onclick="visualizarFrota(' . $id . ')"
                        data-bs-toggle="modal"
                        data-bs-target="#modalPerfilViatura">
                    <span class="btn-icon"><i class="fas fa-eye"></i></span>
                    <span class="btn-text">Visualizar</span>
                </button>

                ' . $btnEditar . '
                
                ' . $btnDeletar . '

                ' . $btnEmprestimo . '
                
            </div>
        </div>';
    }
} else {
    echo '<div class="alert alert-warning text-center">Não foi encontrado nenhuma viatura/equipamento com os filtros selecionados.</div>';
}
?>
      
      
  </div>
                 <!-- Paginação INFERIOR -->
<div class="paginacao">
  <?= renderPaginacaoFROTA($pagina, $totalPaginas, $limite, $queryString, 'includes/frota/listagem.php'); ?>
</div>
</div>
     
  </div>
</div>

<?php if($pode_editar): ?>

<!-- Modal de Edição de Viatura/Equipamento -->
<div class="modal fade" id="modalEditarFrota" tabindex="-1" aria-labelledby="modalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-xl">
    <div class="modal-content">
      <form id="form-editar-frota" enctype="multipart/form-data">
        <div class="modal-header">
          <h5 class="modal-title" id="modalLabel">Editar Viatura/Equipamento</h5>
          <button type="button" class="btn-close btn-close-black" data-bs-dismiss="modal" aria-label="Fechar"></button>
        </div>
        <div class="modal-body">
          <input type="hidden" name="id" id="editar-id-frota">

          <!-- Foto -->
          <div class="text-center mb-4">
            <img id="editar-foto-preview-frota" 
                 src="https://cdn-icons-png.flaticon.com/512/3600/3600953.png" 
                 alt="Foto da Viatura" 
                 class="rounded-circle" width="100" height="100">
            <p class="mt-2">Enviar foto</p>
            <input type="file" name="foto_capa" id="editar-foto-frota" 
                   class="form-control" accept="image/*" onchange="previewImagemCapa(event)">
          </div>

          <!-- Separador: Informações básicas -->
          <div class="col-md-12 mb-3" style="width: 85%; text-align: center; position: relative; left: 50px; background-color: hsla(0,0%,91%,1); border-radius: 15px; padding: 10px 0;">
            <b>Informações básicas do ativo</b>
          </div>

          <!-- Informações básicas -->
          <div class="row g-3">
            <div class="col-md-6">
              <label for="editar-ativo" class="form-label">Tipo do ativo</label>
              <select name="ativo" id="editar-ativo" class="form-select" required>
                <option value="">Selecione...</option>
                <?php
                $sqlTipos = "SELECT id, abreviatura, descricao, tipo FROM config_tiposvtreqp ORDER BY descricao";
                $resultTipos = $conexao->query($sqlTipos);
                while ($tipo = $resultTipos->fetch_assoc()):
                ?>
                  <option value="<?= $tipo['abreviatura'] ?>">
                    <?= htmlspecialchars($tipo['abreviatura'] . ' - ' . $tipo['descricao'] . ' (' . $tipo['tipo'] . ')') ?>
                  </option>
                <?php endwhile; ?>
              </select>
            </div>

            <div class="col-md-6">
              <label for="editar-tipo" class="form-label">Categoria</label>
              <select name="tipo" id="editar-tipo" class="form-select" required>
                <option value="">Selecione...</option>
                <option value="Eqp">Equipamento</option>
                <option value="Vtr">Viatura</option>
              </select>
            </div>

            <div class="col-md-6"><label for="editar-prefixo_velho" class="form-label">Prefixo Velho</label>
              <input type="text" name="prefixo_velho" id="editar-prefixo_velho" class="form-control"></div>

            <div class="col-md-6"><label for="editar-prefixo_sga" class="form-label">Prefixo SGA</label>
              <input type="text" name="prefixo_sga" id="editar-prefixo_sga" class="form-control"></div>

            <div class="col-md-6"><label for="editar-nome_sioc" class="form-label">Nome SIOC</label>
              <input type="text" name="nome_sioc" id="editar-nome_sioc" class="form-control"></div>

            <div class="col-md-6"><label for="editar-nmr_patrimonio" class="form-label">Nº Patrimônio</label>
              <input type="text" name="nmr_patrimonio" id="editar-nmr_patrimonio" class="form-control"></div>

            <div class="col-md-6"><label for="editar-nmr_eb" class="form-label">Nº EB</label>
              <input type="text" name="nmr_eb" id="editar-nmr_eb" class="form-control"></div>

            <div class="col-md-6"><label for="editar-chassi" class="form-label">Chassi</label>
              <input type="text" name="chassi" id="editar-chassi" class="form-control"></div>

            <div class="col-md-6"><label for="editar-acervo" class="form-label">Acervo</label>
              <input type="text" name="acervo" id="editar-acervo" class="form-control"></div>

            <div class="col-md-6"><label for="editar-ano" class="form-label">Ano</label>
              <input type="text" name="ano" id="editar-ano" class="form-control"></div>

            

            <!-- Marca e Modelo -->
            <div class="col-md-6">
              <label for="editar-marca" class="form-label">Marca</label>
              <select name="marca" id="editar-marca" class="form-select" required>
                <option value="">Selecione...</option>
              </select>
            </div>

            <div class="col-md-6">
              <label for="editar-modelo" class="form-label">Modelo</label>
              <select name="modelo" id="editar-modelo" class="form-select" required>
                <option value="">Selecione uma marca primeiro</option>
              </select>
            </div>

            <div class="col-md-6"><label for="editar-capacidade_tanque" class="form-label">Capacidade do Tanque</label>
              <input type="text" name="capac_tanque" id="editar-capacidade_tanque" class="form-control"></div>

            <div class="col-md-6"><label for="editar-consumo" class="form-label">Consumo</label>
              <input type="text" name="consumo" id="editar-consumo" class="form-control"></div>

            <div class="col-md-6"><label for="editar-ordem_fragmentaria" class="form-label">Ordem Fragmentária</label>
              <input type="text" name="ordem_fragmentaria" id="editar-ordem_fragmentaria" class="form-control"></div>

            <div class="col-md-6"><label for="editar-placa" class="form-label">Placa</label>
              <input type="text" name="placa" id="editar-placa" class="form-control"></div>

            <div class="col-md-6"><label for="editar-subunidade" class="form-label">Subunidade</label>
              <input type="text" name="subunidade" id="editar-subunidade" class="form-control"></div>

            <div class="col-md-6"><label for="editar-renavam" class="form-label">Renavam</label>
              <input type="text" name="renavam" id="editar-renavam" class="form-control"></div>

            <div class="col-md-6"><label for="editar-trem" class="form-label">Trem</label>
              <input type="text" name="trem" id="editar-trem" class="form-control"></div>
          </div>

          <!-- Separador: Informações temporárias -->
          <div class="col-md-12 mt-4 mb-3" style="width: 85%; text-align: center; position: relative; left: 50px; background-color: hsla(0,0%,91%,1); border-radius: 15px; padding: 10px 0;">
            <b>Informações temporárias do ativo</b>
          </div>

          <div class="row g-3">
            <div class="col-md-6">
              <label for="editar-confiabilidade" class="form-label">Status/Confiabilidade</label>
              <select name="confiabilidade" id="editar-confiabilidade" class="form-select" required></select>
            </div>

            <div class="col-md-6">
              <label for="editar-disponibilidade" class="form-label">Disponibilidade</label>
              <select name="disponibilidade" id="editar-disponibilidade" class="form-select" required></select>
            </div>

            <div class="col-md-6"><label for="editar-missao" class="form-label">Missão</label>
              <input type="text" name="missao" id="editar-missao" class="form-control"></div>

            <div class="col-md-6"><label for="editar-emprego_atual" class="form-label">Emprego Atual</label>
              <input type="text" name="emprego_atual" id="editar-emprego_atual" class="form-control"></div>
              
           <!-- Destino -->
<div class="col-md-6">
  <label for="editar-destino" class="form-label">Destino</label>
  <select name="destino" id="editar-destino" class="form-select" required>
    <option value="">Selecione...</option>

    <?php
    // ====================================
    // 🔹 Recupera as OMs autorizadas
    // ====================================
    $id_om_usuario = $_SESSION['usuario']['batalhao'] ?? 0;
    $nivel_usuario = $_SESSION['usuario']['nivel'] ?? 3;

    $oms_visiveis = [];

    if ($nivel_usuario == 1) {
        // Nível 1: todas as OMs
        $sql_oms = "SELECT id, nome, abreviatura FROM organizacoes_militares ORDER BY nome";
        $stmt_oms = $conexao->prepare($sql_oms);
    } elseif ($nivel_usuario == 2) {
        // Nível 2: sua OM e subordinadas
        $sql_oms = "
            SELECT om.id, om.nome, om.abreviatura
            FROM organizacoes_militares om
            JOIN organizacoes_militares_sub sub ON om.id = sub.id_om_menor
            WHERE sub.id_om_maior = ?
            UNION
            SELECT id, nome, abreviatura FROM organizacoes_militares WHERE id = ?
            ORDER BY nome
        ";
        $stmt_oms = $conexao->prepare($sql_oms);
        $stmt_oms->bind_param("ii", $id_om_usuario, $id_om_usuario);
    } else {
        // Nível 3: apenas sua OM
        $sql_oms = "SELECT id, nome, abreviatura FROM organizacoes_militares WHERE id = ?";
        $stmt_oms = $conexao->prepare($sql_oms);
        $stmt_oms->bind_param("i", $id_om_usuario);
    }

    $stmt_oms->execute();
    $res_oms = $stmt_oms->get_result();
    while ($r = $res_oms->fetch_assoc()) {
        $oms_visiveis[(int)$r['id']] = $r['abreviatura'] ?: $r['nome'];
    }
    $stmt_oms->close();

    // ✅ CORREÇÃO: pegar os IDs (chaves), não os valores (abreviatura/nome)
    $oms_filtradas = array_values(array_unique(array_map('intval', array_keys($oms_visiveis))));

    if (!empty($oms_filtradas)) {

        $placeholders = implode(',', array_fill(0, count($oms_filtradas), '?'));
        $types = str_repeat('i', count($oms_filtradas));

        // (Sem JOIN) usa o mapa $oms_visiveis para mostrar o batalhão
        $sqlDestino = "
            SELECT id, batalhao, destino
            FROM config_destinos
            WHERE batalhao IN ($placeholders)
            ORDER BY destino ASC
        ";

        $stmt = $conexao->prepare($sqlDestino);

        $params = array_merge([$types], $oms_filtradas);
        $tmp = [];
        foreach ($params as $k => $v) $tmp[$k] = &$params[$k];
        call_user_func_array([$stmt, 'bind_param'], $tmp);

        $stmt->execute();
        $resultDestino = $stmt->get_result();

        while ($destino = $resultDestino->fetch_assoc()):
            $omLabel = $oms_visiveis[(int)$destino['batalhao']] ?? '';
            $label = $destino['destino'] . ($omLabel ? " — " . $omLabel : "");
    ?>
            <option value="<?= htmlspecialchars($destino['destino']) ?>">
              <?= htmlspecialchars($label) ?>
            </option>
    <?php
        endwhile;

        $stmt->close();

    } else {
        echo '<option value="" disabled>Nenhum destino disponível</option>';
    }
    ?>
  </select>
</div>

            <div class="col-md-12">
              <label for="editar-obs_encmat" class="form-label">Obs. EncMat</label>
              <textarea name="obs_encmat" id="editar-obs_encmat" rows="2" class="form-control"></textarea>
            </div>
          </div>
        </div>

        <div class="modal-footer">
        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
          <button type="submit" class="btn btn-success">Salvar Alterações</button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php endif; ?>


<!-- Modal do perfil da viatura -->
<div class="modal fade" id="modalPerfilViatura" tabindex="-1" aria-labelledby="modalPerfilViaturaLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-centered modal-fullscreen-lg-down">
    <div class="modal-content bg-white text-black">

      <div class="modal-header">
        <h5 class="modal-title">Livro da Viatura</h5>
        <div class="ms-auto d-flex align-items-center">
          
          <button id="btnExportarPDFLivro" class="btn btn-primary mb-3">
    <i class="fas fa-file-pdf"></i> Exportar Livro da Viatura
</button>
          <button type="button" class="btn-close btn-close-black" data-bs-dismiss="modal" aria-label="Fechar"></button>
        </div>
      </div>

      <div class="modal-body">
        
        <!-- Cabeçalho com foto e infos -->
        <div class="text-center mb-4">
          <img id="foto-viatura" src="" class="rounded-circle shadow" width="120" height="120" alt="Foto da viatura">
        </div>

        <div class="row mb-4 text-center">
          <div class="col-md-3"><strong>Prefixo:</strong> <span id="viatura-prefixo"></span></div>
          <div class="col-md-3"><strong>Modelo:</strong> <span id="viatura-modelo"></span></div>
          <div class="col-md-3"><strong>Placa:</strong> <span id="viatura-placa"></span></div>
          <div class="col-md-3"><strong>Marca:</strong> <span id="viatura-marca"></span></div>
        </div>
        <div class="row mb-4 text-center">
          <div class="col-md-3"><strong>Tipo:</strong> <span id="viatura-tipo"></span></div>
          <div class="col-md-3"><strong>Status:</strong> <span id="viatura-status"></span></div>
          <div class="col-md-3"><strong>Localização Atual:</strong> <span id="viatura-localizacao"></span></div>
          <div class="col-md-3"><strong>Odômetro:</strong> <span id="viatura-odometro"></span></div>
        </div>
        <div class="row mb-4 text-center">
          <div class="col-md-3"><strong>Horímetro:</strong> <span id="viatura-horimetro"></span></div>
          <div class="col-md-9"><strong>Última OS:</strong> <span id="viatura-os"></span></div>
        </div>

        <hr>

        <!-- Abas -->
        <ul class="nav nav-tabs" id="tabsPerfilViatura" role="tablist">
        
          <li class="nav-item"><button class="nav-link" id="tab-os" data-bs-toggle="tab" data-bs-target="#os-viatura" type="button" role="tab">Ordens de Serviço</button></li>
          <li class="nav-item"><button class="nav-link" id="tab-pedidos" data-bs-toggle="tab" data-bs-target="#pedidos-viatura" type="button" role="tab">Pedidos ao Almox</button></li>
          <li class="nav-item"><button class="nav-link" id="tab-forn" data-bs-toggle="tab" data-bs-target="#fornecimentos-viatura" type="button" role="tab">Ordens de Fornecimento</button></li>
          <li class="nav-item"><button class="nav-link" id="tab-medicoes" data-bs-toggle="tab" data-bs-target="#medicoes-viatura" type="button" role="tab">Odômetro / Horímetro</button></li>
          <li class="nav-item"><button class="nav-link" id="tab-fichas" data-bs-toggle="tab" data-bs-target="#fichas-viatura" type="button" role="tab">Fichas de Serviço</button></li>
          <li class="nav-item"><button class="nav-link" id="tab-logs" data-bs-toggle="tab" data-bs-target="#logs-viatura" type="button" role="tab">Logs</button></li>
          <li class="nav-item"><button class="nav-link" id="tab-tudo" data-bs-toggle="tab" data-bs-target="#tudo-viatura" type="button" role="tab">Ver Tudo</button></li>
        </ul>

        <!-- Conteúdo das abas -->
        <div class="tab-content mt-3">
          
    
          <!-- Aba OS -->
          <div class="tab-pane fade" id="os-viatura" role="tabpanel">
            <div id="conteudo-os-viatura">
              <p>🔧 Listagem das ordens de serviço vinculadas.</p>
            </div>
          </div>

          <!-- Aba Pedidos -->
          <div class="tab-pane fade" id="pedidos-viatura" role="tabpanel">
            <div id="conteudo-pedidos-viatura">
              <p>📦 Pedidos realizados para esta viatura.</p>
            </div>
          </div>

          <!-- Aba Fornecimentos -->
          <div class="tab-pane fade" id="fornecimentos-viatura" role="tabpanel">
            <div id="conteudo-fornecimentos-viatura">
              <p>🚚 Ordens de fornecimento vinculadas à viatura.</p>
            </div>
          </div>

       

          <!-- Aba Medições -->
          <div class="tab-pane fade" id="medicoes-viatura" role="tabpanel">
            <div id="conteudo-medicoes-viatura">
              <p>⏱ Controle de odômetro e horímetro.</p>
            </div>
          </div>

          <!-- Aba Fichas -->
          <div class="tab-pane fade" id="fichas-viatura" role="tabpanel">
            <div id="conteudo-fichas-viatura">
              <p>📑 Fichas de serviço da viatura.</p>
            </div>
          </div>

          <!-- Aba Logs -->
          <div class="tab-pane fade" id="logs-viatura" role="tabpanel">
            <div class="table-responsive">
              <table class="table table-sm table-striped table-hover text-white">
                <thead class="table-dark">
                  <tr>
                    <th>Data</th>
                    <th>Ação</th>
                    <th>Descrição</th>
                    <th>Responsável</th>
                  </tr>
                </thead>
                <tbody id="tabela-logs-viatura"></tbody>
              </table>
            </div>
          </div>

          <!-- Aba Ver Tudo -->
          <div class="tab-pane fade" id="tudo-viatura" role="tabpanel">
            <h6>📖 Resumo Completo da Viatura</h6>
            <div id="conteudo-tudo-viatura">
              <p class="text-muted">Todas as informações consolidadas da viatura aparecerão aqui.</p>
            </div>
          </div>

        </div>

      </div>
    </div>
  </div>
</div>

<?php if($pode_editar): ?>

<div class="modal fade"
     id="modalEmprestimoFrota"
     tabindex="-1"
     aria-hidden="true">

    <div class="modal-dialog modal-dialog-centered">

        <div class="modal-content">

            <form id="form-emprestimo-frota">

                <div class="modal-header">
                    <h5 class="modal-title" id="titulo-modal-emprestimo">
                        Empréstimo de ativo
                    </h5>

                    <button type="button"
                            class="btn-close"
                            data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">

                    <input type="hidden" name="id" id="emprestimo-id">
                    <input type="hidden" name="acao" id="emprestimo-acao">
                    <input type="hidden" name="batalhao_origem" id="emprestimo-batalhao-origem">
					
					
					<!-- BATALHÃO ATUAL -->
<div class="alert alert-secondary mb-3">
    <div class="fw-bold mb-1">
        Batalhão atual do ativo
    </div>

    <div id="emprestimo-batalhao-atual-texto">
        Carregando...
    </div>
</div>

<!-- BATALHÃO DESTINO -->
<div class="mb-3">
    <label class="form-label">
        Batalhão destino
    </label>

    <select name="batalhao_destino"
            id="emprestimo-batalhao-destino"
            class="form-select"
            required>

        <option value="">
            Selecione...
        </option>

        <?php
        $sqlOms = "
            SELECT id, nome, abreviatura
            FROM organizacoes_militares
            ORDER BY nome
        ";

        $resOms = $conexao->query($sqlOms);

        while($om = $resOms->fetch_assoc()):
        ?>

            <option value="<?= $om['id'] ?>">
                <?= htmlspecialchars($om['abreviatura'] ?: $om['nome']) ?>
            </option>

        <?php endwhile; ?>

    </select>

    <div id="texto-destino-devolucao"
         class="form-text text-muted d-none">
        Na devolução, o destino será automaticamente o batalhão de origem do ativo.
    </div>
</div>

                    <div class="mb-3">
                        <label class="form-label">
                            Observações
                        </label>

                        <textarea name="observacoes"
                                  class="form-control"
                                  rows="4"></textarea>
                    </div>

                </div>

                <div class="modal-footer">

                    <input type="hidden"
                           name="csrf_token"
                           value="<?= $_SESSION['csrf_token'] ?>">

                    <button type="submit"
                            class="btn btn-primary"
                            id="btn-submit-emprestimo">
                        Confirmar
                    </button>

                </div>

            </form>

        </div>

    </div>

</div>

<?php endif; ?>

<script>
    window.funcaoInicializacao = 'inicializarListagemFrota';
    // PRÉVIA FOTO NO EDITAR
    
    function previewImagemCapa(event) {
    const input = event.target;
    const preview = document.getElementById('editar-foto-preview-frota');

    if (input.files && input.files[0]) {
      const reader = new FileReader();

      reader.onload = function(e) {
        preview.src = e.target.result;
      };

      reader.readAsDataURL(input.files[0]);
    } else {
      // Caso o usuário cancele, volta para a imagem padrão
      preview.src = "https://cdn-icons-png.flaticon.com/512/3600/3600953.png";
    }
  }
    
</script>
