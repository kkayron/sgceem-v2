<?php
// ==============================
// 🔹 Segurança
// ==============================
header('Content-Type: text/html; charset=utf-8');
session_start();

require_once '../api/seguranca.php';

$permissoes = verificarPermissao([14]);

$pode_editar = $permissoes['editar'];

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
$id_om_usuario = (int)($_SESSION['usuario']['batalhao'] ?? 0);

if ($id_om_usuario <= 0) {
    echo "<div class='alert alert-danger'>Batalhão do usuário não identificado.</div>";
    exit;
}

// Buscar nível da OM do usuário
$nivel_om_usuario = 3;

$stmtNivelOm = $conexao->prepare("
    SELECT nivel
    FROM organizacoes_militares
    WHERE id = ?
    LIMIT 1
");

$stmtNivelOm->bind_param("i", $id_om_usuario);
$stmtNivelOm->execute();

$resNivelOm = $stmtNivelOm->get_result();

if ($omUsuario = $resNivelOm->fetch_assoc()) {
    $nivel_om_usuario = (int)$omUsuario['nivel'];
}

$stmtNivelOm->close();

// ==============================
// 🔹 Inicializa filtros
// ==============================
$filtros = [];
$params = [];
$tipos = '';

// ==============================
// 🔹 Regra dos ativos emprestados
// ==============================

// Só considera ativos com batalhao_origem válido
$filtros[] = "f.batalhao_origem IS NOT NULL";
$filtros[] = "f.batalhao_origem <> 0";

// Só considera ativos realmente emprestados
$filtros[] = "f.batalhao <> f.batalhao_origem";

if ($nivel_om_usuario != 1) {
    // OM nível 2 ou 3 vê apenas os ativos emprestados pelo próprio batalhão
    $filtros[] = "f.batalhao_origem = ?";
    $params[] = $id_om_usuario;
    $tipos .= 'i';
}

// Paginação
$limite = isset($_GET['limite']) && is_numeric($_GET['limite'])
    ? (int)$_GET['limite']
    : 10;

$pagina = isset($_GET['pagina']) && is_numeric($_GET['pagina']) && $_GET['pagina'] > 0
    ? (int)$_GET['pagina']
    : 1;

$offset = ($pagina - 1) * $limite;

// Campos filtráveis
$campos = [
    'ativo',
    'tipo',
    'marca',
    'modelo',
    'ano',
    'confiabilidade',
    'subunidade',
    'acervo',
    'destino',
    'disponibilidade'
];

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

    $filtros[] = "
        (
            f.prefixo_sga LIKE ?
            OR f.placa LIKE ?
            OR f.tipo LIKE ?
            OR f.nome_sioc LIKE ?
            OR f.nmr_eb LIKE ?
            OR f.nmr_patrimonio LIKE ?
        )
    ";

    $params = array_merge($params, [
        $texto,
        $texto,
        $texto,
        $texto,
        $texto,
        $texto
    ]);

    $tipos .= 'ssssss';
}

// Filtro OM atual do ativo
if (!empty($_GET['batalhao'])) {
    $batalhaoFiltro = (int)$_GET['batalhao'];

    if ($batalhaoFiltro > 0) {
        $filtros[] = "f.batalhao = ?";
        $params[] = $batalhaoFiltro;
        $tipos .= 'i';
    }
}

// ==============================
// 🔹 Consulta total
// ==============================
$sqlTotal = "
    SELECT COUNT(*) AS total
    FROM frota f
    WHERE " . implode(" AND ", $filtros);

$stmtTotal = $conexao->prepare($sqlTotal);

if (!empty($params)) {
    $stmtTotal->bind_param($tipos, ...$params);
}

$stmtTotal->execute();

$resultTotal = $stmtTotal->get_result();

$totalRegistros = $resultTotal->fetch_assoc()['total'] ?? 0;

$stmtTotal->close();

$totalPaginas = max(ceil($totalRegistros / $limite), 1);

// ==============================
// 🔹 Consulta principal
// ==============================
$sql = "
    SELECT
        f.*,

        cm.marca AS nome_marca,
        md.nome_modelo AS nome_modelo,

        om_atual.nome AS nome_om_atual,
        om_atual.abreviatura AS sigla_om_atual,

        om_origem.nome AS nome_om_origem,
        om_origem.abreviatura AS sigla_om_origem

    FROM frota f

    LEFT JOIN config_marcas cm
        ON f.marca = cm.id

    LEFT JOIN config_modelos md
        ON f.modelo = md.id

    LEFT JOIN organizacoes_militares om_atual
        ON f.batalhao = om_atual.id

    LEFT JOIN organizacoes_militares om_origem
        ON f.batalhao_origem = om_origem.id

    WHERE " . implode(" AND ", $filtros) . "

    ORDER BY f.prefixo_sga ASC

    LIMIT ? OFFSET ?
";

$paramsConsulta = $params;
$tiposConsulta = $tipos;

$paramsConsulta[] = $limite;
$paramsConsulta[] = $offset;
$tiposConsulta .= 'ii';

$stmt = $conexao->prepare($sql);
$stmt->bind_param($tiposConsulta, ...$paramsConsulta);
$stmt->execute();

$result = $stmt->get_result();

// ==============================
// 🔹 Função de paginação
// ==============================
function renderPaginacaoAtivosEmprestados(
    $pagina,
    $totalPaginas,
    $limite,
    $queryString,
    $arquivo = 'includes/frota/ativos_emprestados.php'
) {
    $maxLinksDesktop = 10;
    $maxLinksMobile  = 3;

    $makeUrl = function($p) use ($arquivo, $queryString, $limite) {
        return "{$arquivo}?{$queryString}&pagina={$p}&limite={$limite}";
    };

    $li = function($label, $page, $disabled = false, $active = false, $extraClass = '') use ($makeUrl) {
        $cls = "page-item";

        if ($disabled) $cls .= " disabled";
        if ($active)   $cls .= " active";
        if ($extraClass) $cls .= " {$extraClass}";

        if ($disabled) {
            return "<li class='{$cls}'><span class='page-link'>{$label}</span></li>";
        }

        return "<li class='{$cls}'>
                    <a class='page-link paginacao-frota'
                       href='#'
                       data-page='" . $makeUrl($page) . "'>
                        {$label}
                    </a>
                </li>";
    };

    $calcRange = function($maxLinks) use ($pagina, $totalPaginas) {
        $inicio = max(1, $pagina - floor($maxLinks / 2));
        $fim = min($totalPaginas, $inicio + $maxLinks - 1);

        if (($fim - $inicio) < ($maxLinks - 1)) {
            $inicio = max(1, $fim - $maxLinks + 1);
        }

        return [$inicio, $fim];
    };

    [$inicioDesktop, $fimDesktop] = $calcRange($maxLinksDesktop);
    [$inicioMobile, $fimMobile] = $calcRange($maxLinksMobile);

    $html  = '<div class="pagination-wrapper">';
    $html .= '<nav aria-label="Paginação Ativos Emprestados">';
    $html .= '<ul class="pagination pagination-sm flex-wrap justify-content-center gap-1">';

    $html .= ($pagina > 1)
        ? $li('&laquo;', 1)
        : $li('&laquo;', 1, true);

    $html .= ($pagina > 1)
        ? $li('&lsaquo;', $pagina - 1)
        : $li('&lsaquo;', 1, true);

    $html .= "<span class='d-none d-md-inline'>";

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

    $html .= "<span class='d-inline d-md-none'>";

    $html .= $li('1', 1, false, $pagina == 1);

    if ($inicioMobile > 2) {
        $html .= $li('...', 1, true);
    }

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

    $html .= ($pagina < $totalPaginas)
        ? $li('&rsaquo;', $pagina + 1)
        : $li('&rsaquo;', $totalPaginas, true);

    $html .= ($pagina < $totalPaginas)
        ? $li('&raquo;', $totalPaginas)
        : $li('&raquo;', $totalPaginas, true);

    $html .= '</ul></nav></div>';

    return $html;
}

// ==============================
// 🔹 Query string
// ==============================
$paramsQuery = $_GET;
unset($paramsQuery['pagina']);
$queryString = http_build_query($paramsQuery);
?>
<div class="container">

<div class="card shadow-sm border-0">

    <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center flex-wrap gap-2">
        
    <div class="d-flex justify-content-between align-items-center py-3">
            <h5 class="mb-0">
                <i class="fa fa-share me-2"></i>
                Ativos Emprestados
            </h5>
            <small>
                Ativos pertencentes ao seu batalhão que estão em outra OM
            </small>
        </div>

        <span class="badge bg-light text-dark">
            <?= (int)$totalRegistros ?> registro(s)
        </span>
    </div>

    <div class="card-body">
		<!-- Botão abrir filtros -->
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">

    <button type="button"
            class="btn btn-dark"
            onclick="toggleFiltrosEmprestados()">
        <i class="fa fa-filter me-1"></i>
        Filtros
    </button>

</div>

<!-- Área dos filtros -->
<div id="filtros-emprestados-container"
     style="<?= !empty($_GET) ? 'display:block;' : 'display:none;' ?>">

    <div class="card shadow-sm border-0 mb-3">

        <div class="card-body">

            <form method="GET" id="filtroAtivosEmprestadosForm">

                <div class="row g-3">

                    <?php
                    // ==============================
                    // 🔹 Função para selects simples
                    // ==============================
                    if (!function_exists('gerarSelectEmprestados')) {

                        function gerarSelectEmprestados($conexao, $coluna, $label) {

                            if ($coluna === 'marca') {

                                $sql = "
                                    SELECT id, marca AS nome
                                    FROM config_marcas
                                    ORDER BY marca
                                ";

                                $campoValor = 'id';
                                $campoTexto = 'nome';

                            } elseif ($coluna === 'modelo') {

                                $sql = "
                                    SELECT id, nome_modelo AS nome
                                    FROM config_modelos
                                    ORDER BY nome_modelo
                                ";

                                $campoValor = 'id';
                                $campoTexto = 'nome';

                            } else {

                                $sql = "
                                    SELECT DISTINCT {$coluna} AS valor
                                    FROM frota
                                    WHERE {$coluna} IS NOT NULL
                                    AND {$coluna} != ''
                                    ORDER BY {$coluna}
                                ";

                                $campoValor = 'valor';
                                $campoTexto = 'valor';
                            }

                            $stmtFiltro = $conexao->prepare($sql);
                            $stmtFiltro->execute();
                            $resultFiltro = $stmtFiltro->get_result();

                            echo '<div class="col-md-3">';
                            echo '<label class="form-label fw-semibold">' . htmlspecialchars($label) . '</label>';
                            echo '<select name="' . htmlspecialchars($coluna) . '" class="form-select">';
                            echo '<option value="">Todos</option>';

                            while ($opt = $resultFiltro->fetch_assoc()) {

                                $valor = $opt[$campoValor];
                                $texto = $opt[$campoTexto];

                                $selected = (
                                    isset($_GET[$coluna]) &&
                                    (string)$_GET[$coluna] === (string)$valor
                                ) ? 'selected' : '';

                                echo '<option value="' . htmlspecialchars($valor) . '" ' . $selected . '>';
                                echo htmlspecialchars($texto);
                                echo '</option>';
                            }

                            echo '</select>';
                            echo '</div>';

                            $stmtFiltro->close();
                        }
                    }
                    ?>

                    <!-- OM Atual -->
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">
                            OM Atual do Ativo
                        </label>

                        <select name="batalhao" class="form-select">
                            <option value="">Todas</option>

                            <?php
                            $sqlOmsFiltro = "
                                SELECT id, nome, abreviatura
                                FROM organizacoes_militares
                                ORDER BY nome
                            ";

                            $stmtOmsFiltro = $conexao->prepare($sqlOmsFiltro);
                            $stmtOmsFiltro->execute();
                            $resOmsFiltro = $stmtOmsFiltro->get_result();

                            while ($omFiltro = $resOmsFiltro->fetch_assoc()) {

                                $omId = (int)$omFiltro['id'];

                                $omNome = $omFiltro['abreviatura']
                                    ?: $omFiltro['nome'];

                                $selected = (
                                    isset($_GET['batalhao']) &&
                                    (int)$_GET['batalhao'] === $omId
                                ) ? 'selected' : '';

                                echo '<option value="' . $omId . '" ' . $selected . '>';
                                echo htmlspecialchars($omNome);
                                echo '</option>';
                            }

                            $stmtOmsFiltro->close();
                            ?>
                        </select>
                    </div>

                    <?php
                    gerarSelectEmprestados($conexao, 'ativo', 'Ativo');
                    gerarSelectEmprestados($conexao, 'tipo', 'Tipo');
                    gerarSelectEmprestados($conexao, 'marca', 'Marca');
                    gerarSelectEmprestados($conexao, 'modelo', 'Modelo');
                    gerarSelectEmprestados($conexao, 'ano', 'Ano');
                    gerarSelectEmprestados($conexao, 'confiabilidade', 'Confiabilidade/Status');
                    gerarSelectEmprestados($conexao, 'subunidade', 'Subunidade');
                    gerarSelectEmprestados($conexao, 'acervo', 'Acervo');
                    gerarSelectEmprestados($conexao, 'destino', 'Destino');
                    gerarSelectEmprestados($conexao, 'disponibilidade', 'Disponibilidade');
                    ?>

                    <!-- Busca geral -->
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">
                            Busca geral
                        </label>

                        <input type="text"
                               name="texto"
                               class="form-control"
                               placeholder="Prefixo, placa, EB, patrimônio..."
                               value="<?= htmlspecialchars($_GET['texto'] ?? '') ?>">
                    </div>

                    <!-- Botões -->
                    <div class="col-12 d-flex justify-content-between">

                        <button type="button"
                                class="btn btn-black"
                                onclick="limparFiltrosEmprestados()">
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
		

        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">

            <div>
                <label for="limite" class="me-2 mb-0">Mostrar</label>

                <select id="limite"
                        name="limite"
                        class="form-select d-inline w-auto"
                        onchange="atualizarLimite()">

                    <?php
                    $limiteAtual = $_GET['limite'] ?? 10;

                    foreach ([5, 10, 25, 50, 100] as $opcao) {
                        $selected = ($limiteAtual == $opcao) ? 'selected' : '';
                        echo "<option value='{$opcao}' {$selected}>{$opcao}</option>";
                    }
                    ?>

                </select>

                <span class="ms-2">por página</span>
            </div>

        </div>

        <!-- Paginação superior -->
        <div class="paginacao d-flex justify-content-center mt-2 mb-3">
            <?= renderPaginacaoAtivosEmprestados(
                $pagina,
                $totalPaginas,
                $limite,
                $queryString,
                'includes/frota/ativos_emprestados.php'
            ); ?>
        </div>

        <?php if ($result->num_rows > 0): ?>

            <?php while ($row = $result->fetch_assoc()): ?>

                <?php
                $id = (int)$row['id'];

                $imagem = !empty($row['foto_capa'])
                    ? 'uploads/frotas/' . $row['foto_capa']
                    : 'uploads/frotas/sem-imagem.png';

                $badgeClass = ($row['disponibilidade'] === 'Indisponível')
                    ? 'badge-danger'
                    : (($row['disponibilidade'] === 'Disponível com restrição')
                        ? 'badge-warning'
                        : 'badge-success');

                $omAtual = $row['sigla_om_atual']
                    ?: $row['nome_om_atual']
                    ?: '-';

                $omOrigem = $row['sigla_om_origem']
                    ?: $row['nome_om_origem']
                    ?: '-';

                $btnDevolver = '';

                if (!empty($pode_editar)) {
                    $btnDevolver = '
                        <button type="button"
                                class="btn btn-success btn-sm"
                                data-bs-toggle="modal"
                                data-bs-target="#modalEmprestimoFrota"
                                onclick="abrirModalEmprestimo(' . $id . ', \'devolucao\')"
                                title="Realizar devolução">
                            <span class="btn-icon"><i class="fa fa-undo"></i></span>
                            <span class="btn-text">Devolver</span>
                        </button>';
                }
                ?>

                <div class="frota-item">

                    <div class="frota-info">

                        <img src="<?= htmlspecialchars($imagem) ?>"
                             alt="Imagem de <?= htmlspecialchars($row['prefixo_sga']) ?>">

                        <div>

                            <h5 class="mb-1">
                                <?= htmlspecialchars($row['prefixo_sga']) ?>

                                <span class="badge <?= $badgeClass ?>">
                                    <?= htmlspecialchars($row['disponibilidade']) ?>
                                </span>

                                <span class="badge bg-info text-dark">
                                    Emprestado
                                </span>
                            </h5>

                            <h6>
                                <b>Status/Confiabilidade:</b>
                                <?= htmlspecialchars($row['confiabilidade']) ?>
                            </h6>

                            <h6>
                                <b>OM Proprietária:</b>
                                <?= htmlspecialchars($omOrigem) ?>
                                |
                                <b>OM Atual:</b>
                                <?= htmlspecialchars($omAtual) ?>
                            </h6>

                            <h6>
                                <b>Local:</b>
                                <?= htmlspecialchars($row['destino']) ?>
                            </h6>

                            <p class="mb-0 text-muted">
                                <b>Placa:</b> <?= htmlspecialchars($row['placa']) ?> |
                                <b>Tipo:</b> <?= htmlspecialchars($row['tipo']) ?> |
                                <b>Acervo:</b> <?= htmlspecialchars($row['acervo']) ?> |
                                <b>Marca:</b> <?= htmlspecialchars($row['nome_marca'] ?? '-') ?> |
                                <b>Modelo:</b> <?= htmlspecialchars($row['nome_modelo'] ?? '-') ?>
                            </p>

                            <p class="mb-0 text-muted">
                                <b>Chassi:</b> <?= htmlspecialchars($row['chassi']) ?> |
                                <b>EB:</b> <?= htmlspecialchars($row['nmr_eb']) ?> |
                                <b>Patrimônio:</b> <?= htmlspecialchars($row['nmr_patrimonio']) ?>
                            </p>

                        </div>

                    </div>
                </div>

            <?php endwhile; ?>

        <?php else: ?>

            <div class="alert alert-warning text-center">
                Nenhum ativo emprestado pelo seu batalhão foi encontrado.
            </div>

        <?php endif; ?>

        <!-- Paginação inferior -->
        <div class="paginacao mt-3">
            <?= renderPaginacaoAtivosEmprestados(
                $pagina,
                $totalPaginas,
                $limite,
                $queryString,
                'includes/frota/ativos_emprestados.php'
            ); ?>
        </div>

    </div>

</div>
</div>

<?php
$stmt->close();
?>

<script>
	 window.funcaoInicializacao = 'inicializarAtivosEmprestados';
</script>