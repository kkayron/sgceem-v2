<?php
header('Content-Type: text/html; charset=utf-8');
session_start();
require_once('../../conexao/config.php');

// ID da página correspondente no banco
$pagina_id = intval(13); // <--- ajuste conforme o ID da página

// Verifica login e permissão de acesso
if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit;
}

if (empty($_SESSION['permissoes'][$pagina_id]['pode_acessar'])) {
    ?>
    <!DOCTYPE html>
    <html lang="pt-BR">
    <head>
        <meta charset="UTF-8">
        <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    </head>
    <body>
        <script>
            Swal.fire({
                icon: 'error',
                title: 'Acesso Negado',
                text: 'Você não possui permissão para acessar esta página.',
                confirmButtonText: 'Voltar ao Painel',
                allowOutsideClick: false
            }).then(() => window.location.href = 'index.php#partes/conteudo.php');
        </script>
    </body>
    </html>
    <?php
    exit;
}

// Permissões específicas
$pode_cadastrar = $_SESSION['permissoes'][$pagina_id]['pode_cadastrar'] ?? false;
$pode_editar    = $_SESSION['permissoes'][$pagina_id]['pode_editar'] ?? false;
$pode_deletar   = $_SESSION['permissoes'][$pagina_id]['pode_deletar'] ?? false;
?>
<style>
.btn-group .btn {
  border-radius: 20px;
  transition: all 0.3s ease;
}
@media (max-width: 576px) {
  .badge.text-truncate {
    max-width: 80px;
  }
}
.alert-odometro-antigo {
    background-color: #f8d7da;
    border-color: #f5c6cb;
    color: #721c24;
    padding: 10px;
    border-radius: 5px;
    margin-bottom: 15px;
}
</style>

<div class="container">
  <div class="page-inner">
    <div class="d-flex justify-content-between align-items-center py-3">
      <div>
        <h3 class="fw-bold mb-1">Controle de Odômetros</h3>
        <h6 class="text-muted">Acompanhamento das medições de cada viatura/equipamento</h6>
      </div>
    </div>

    <div class="card shadow-sm border-0">
     <div class="card-body">
<?php

// ====================================
// 🔹 Captura dados da sessão do usuário
// ====================================
$id_om_usuario = $_SESSION['usuario']['batalhao'] ?? 0;
$nivel_usuario = $_SESSION['usuario']['nivel'] ?? 3; // padrão: restrito

// ====================================
// 🔹 Monta lista de OMs acessíveis
// ====================================
$oms_visiveis = [];

if ($nivel_usuario == 1) {
    // Nível 1 → todas as OMs
    $sql_oms = "SELECT id, nome, abreviatura FROM organizacoes_militares ORDER BY nome";
} elseif ($nivel_usuario == 2) {
    // Nível 2 → sua OM + subordinadas
    $sql_oms = "
        SELECT om.id, om.nome, om.abreviatura
        FROM organizacoes_militares om
        JOIN organizacoes_militares_sub sub ON om.id = sub.id_om_menor
        WHERE sub.id_om_maior = ?
        UNION
        SELECT id, nome, abreviatura FROM organizacoes_militares WHERE id = ?
        ORDER BY nome
    ";
} else {
    // Nível 3 → apenas sua OM
    $sql_oms = "SELECT id, nome, abreviatura FROM organizacoes_militares WHERE id = ?";
}

$stmt_oms = $conexao->prepare($sql_oms);

if ($nivel_usuario == 2) {
    $stmt_oms->bind_param("ii", $id_om_usuario, $id_om_usuario);
} elseif ($nivel_usuario == 3) {
    $stmt_oms->bind_param("i", $id_om_usuario);
}

$stmt_oms->execute();
$res_oms = $stmt_oms->get_result();
while ($r = $res_oms->fetch_assoc()) {
    $oms_visiveis[$r['id']] = $r['abreviatura'] ?: $r['nome'];
}
$stmt_oms->close();

// ====================================
// 🔹 Define filtro de OMs permitidas
// ====================================
if (!empty($oms_visiveis)) {
    $ids_oms = implode(',', array_keys($oms_visiveis));
    $whereBatalhao = "f.batalhao IN ($ids_oms)";
} else {
    $whereBatalhao = "1=0"; // sem acesso
}

// ====================================
// 🔹 Filtros adicionais
// ====================================
$camposFiltro = [
    'batalhao','ativo','tipo','prefixo_sga','acervo','marca','modelo','ano','confiabilidade',
    'missao','emprego_atual','subunidade','destino','disponibilidade'
];

$where = [$whereBatalhao];
$params = [];
$tipos = '';

foreach ($camposFiltro as $campo) {
    if (!empty($_GET[$campo])) {
        $where[] = "f.$campo = ?";
        $params[] = $_GET[$campo];
        $tipos .= 's';
    }
}

if (!empty($_GET['pesquisa'])) {
    $pesquisa = '%' . $_GET['pesquisa'] . '%';
    $where[] = "(f.prefixo_sga LIKE ? OR mo.nome_modelo LIKE ?)";
    $params[] = $pesquisa;
    $params[] = $pesquisa;
    $tipos .= 'ss';
}

$whereSQL = 'WHERE ' . implode(' AND ', $where);
         
         // ====================================
// 🔹 Paginação
// ====================================
$pagina = isset($_GET['pagina']) ? max(1, intval($_GET['pagina'])) : 1;
$limite = isset($_GET['limite']) ? max(5, intval($_GET['limite'])) : 20;
$offset = ($pagina - 1) * $limite;

         
// ====================================
// 🔹 Conta total de registros (para paginação)
// ====================================
$sqlCount = "
    SELECT COUNT(*) AS total
    FROM frota f
    LEFT JOIN config_marcas m ON f.marca = m.id
    LEFT JOIN config_modelos mo ON f.modelo = mo.id
    LEFT JOIN organizacoes_militares om ON f.batalhao = om.id
    $whereSQL
";

$stmtCount = $conexao->prepare($sqlCount);

if (!empty($params)) {
    $stmtCount->bind_param($tipos, ...$params);
}

$stmtCount->execute();
$totalRegistros = $stmtCount->get_result()->fetch_assoc()['total'] ?? 0;

$totalPaginas = max(1, ceil($totalRegistros / $limite));

// ====================================
// 🔹 Consulta principal
// ====================================
$sqlViaturas = "
  SELECT 
      f.id, 
      f.prefixo_sga, 
      f.tipo, 
      f.status_odometro,
      m.marca AS nome_marca,
      mo.nome_modelo AS nome_modelo, 
      f.disponibilidade, 
      f.confiabilidade,
      om.nome AS nome_batalhao,
      om.abreviatura AS abreviatura_batalhao
  FROM frota f
  LEFT JOIN config_marcas m ON f.marca = m.id
  LEFT JOIN config_modelos mo ON f.modelo = mo.id
  LEFT JOIN organizacoes_militares om ON f.batalhao = om.id
  $whereSQL
  ORDER BY f.prefixo_sga
  LIMIT $limite OFFSET $offset
";

$stmtViaturas = $conexao->prepare($sqlViaturas);

if (!empty($params)) {
    $stmtViaturas->bind_param($tipos, ...$params);
}

$stmtViaturas->execute();
$resultViaturas = $stmtViaturas->get_result();

// ====================================
// 🔹 Buscar TODAS as viaturas (sem paginação) para gerar alertas
// ====================================
$sqlTodas = "
    SELECT f.id, f.prefixo_sga, f.status_odometro, f.confiabilidade
    FROM frota f
    LEFT JOIN config_marcas m ON f.marca = m.id
    LEFT JOIN config_modelos mo ON f.modelo = mo.id
    LEFT JOIN organizacoes_militares om ON f.batalhao = om.id
    $whereSQL
";

$stmtTodas = $conexao->prepare($sqlTodas);

if (!empty($params)) {
    $stmtTodas->bind_param($tipos, ...$params);
}

$stmtTodas->execute();
$resTodas = $stmtTodas->get_result();
         
         
// ====================================
// 🔹 Verifica alertas de odômetros antigos
// ====================================
$alertas = [];
$viaturasOdometroAntigo = [];

while ($vtr = $resTodas->fetch_assoc()) {

    $statusOdometro = $vtr['status_odometro'] ?: 'Funciona';
    $conf = strtoupper(trim($vtr['confiabilidade'] ?? ''));

    if ($statusOdometro === 'Funciona' 
        && in_array($conf, ['CONFIÁVEL','NÃO CONFIÁVEL','EMPRESTADO'])) {

        $sqlUlt = "SELECT odometro, data 
                   FROM controle_medicoes 
                   WHERE viatura_id = ? 
                   ORDER BY data DESC LIMIT 1";

        $stmtUlt = $conexao->prepare($sqlUlt);
        $stmtUlt->bind_param("i", $vtr['id']);
        $stmtUlt->execute();
        $resUlt = $stmtUlt->get_result()->fetch_assoc();

        $dataUltimo = $resUlt['data'] ?? null;

        if (empty($dataUltimo) || strtotime($dataUltimo) <= strtotime('-15 days')) {
            $alertas[] = $vtr['prefixo_sga'];
            $viaturasOdometroAntigo[] = $vtr['id'];
        }
    }
}

// ====================================
// 🔹 Reset pointer (caso precise exibir tabela depois)
// ====================================
$resultViaturas->data_seek(0);
         
 // ==============================
// 🔹 Paginação estilo Frota
// ==============================
function renderPaginacaoOdometro($pagina, $totalPaginas, $limite, $queryString, $arquivo = 'includes/odometro/controle.php') {

    $maxLinks = 10;

    $makeUrl = function($p) use ($arquivo, $queryString, $limite) {
        return "{$arquivo}?{$queryString}&pagina={$p}&limite={$limite}";
    };

    $html = '<div class="pagination-wrapper">';
    $html .= '<nav><ul class="pagination pagination-sm justify-content-center">';

    if ($pagina > 1) {
        $html .= "<li class='page-item'><a class='page-link paginacao-odometro' href='#' data-page='".$makeUrl(1)."'>&laquo; Primeira</a></li>";
        $html .= "<li class='page-item'><a class='page-link paginacao-odometro' href='#' data-page='".$makeUrl($pagina-1)."'>&lsaquo;</a></li>";
    }

    $inicio = max(1, $pagina - floor($maxLinks/2));
    $fim    = min($totalPaginas, $inicio + $maxLinks - 1);

    if (($fim - $inicio) < ($maxLinks - 1)) {
        $inicio = max(1, $fim - $maxLinks + 1);
    }

    if ($inicio > 1) {
        $html .= "<li class='page-item disabled'><span class='page-link'>...</span></li>";
    }

    for ($i = $inicio; $i <= $fim; $i++) {
        $active = ($i == $pagina) ? "active" : "";
        $html .= "<li class='page-item $active'><a class='page-link paginacao-odometro' href='#' data-page='".$makeUrl($i)."'>$i</a></li>";
    }

    if ($fim < $totalPaginas) {
        $html .= "<li class='page-item disabled'><span class='page-link'>...</span></li>";
    }

    if ($pagina < $totalPaginas) {
        $html .= "<li class='page-item'><a class='page-link paginacao-odometro' href='#' data-page='".$makeUrl($pagina+1)."'>&rsaquo;</a></li>";
        $html .= "<li class='page-item'><a class='page-link paginacao-odometro' href='#' data-page='".$makeUrl($totalPaginas)."'>Última &raquo;</a></li>";
    }

    $html .= '</ul></nav></div>';
    return $html;
}        
         
         // ====================================
// 🔹 Mantém filtros na paginação
// ====================================
$paramsGET = $_GET;
unset($paramsGET['pagina']); // não duplicar
$queryString = http_build_query($paramsGET);

?>



<?php if (!empty($alertas)): ?>
    <div class="alert alert-odometro-antigo">
        <strong>Atenção:</strong> Viaturas/Equipamentos sem odômetro/horímetro atualizado há mais de 15 dias: 
        <?= implode(', ', $alertas) ?>
    </div>
<?php endif; ?>

<!-- Botões de ação -->
<div class="d-flex justify-content-between align-items-center mb-3">
  <button class="btn btn-outline-primary" type="button" data-bs-toggle="collapse" data-bs-target="#filtrosAvancados">
    Filtros Avançados
  </button>
    <?php if ($pode_editar): ?>
  <button type="button" id="AcessarEditar" class="btn btn-secondary btn-sm">Atualizar odômetros</button>
     <?php endif; ?>
</div>

<!-- Painel de Filtros -->
<div class="collapse  mb-3" id="filtrosAvancados">
  <form id="formFiltroData" method="get">
    <div class="row g-2">
      <?php
foreach ($camposFiltro as $campo) {
    echo '<div class="col-md-3">';
    echo '<label class="form-label">' . ucfirst(str_replace('_',' ',$campo)) . '</label>';
    echo '<select name="'.$campo.'" class="form-select">';
    echo '<option value="">Todos</option>';

    if ($campo === 'batalhao') {
        // 🔹 Apenas OMs visíveis
        foreach ($oms_visiveis as $id => $nomeOM) {
            $valor = htmlspecialchars($id);
            $selecionado = (isset($_GET['batalhao']) && $_GET['batalhao'] == $id) ? 'selected' : '';
            echo '<option value="'.$valor.'" '.$selecionado.'>'.$nomeOM.'</option>';
        }
    } elseif ($campo === 'marca') {
        // 🔹 Marcas apenas das OMs visíveis
        if (!empty($oms_visiveis)) {
            $ids = implode(',', array_keys($oms_visiveis));
            $query = "
                SELECT DISTINCT m.id, m.marca
                FROM frota f
                JOIN config_marcas m ON f.marca = m.id
                WHERE f.marca <> '' AND f.batalhao IN ($ids)
                ORDER BY m.marca
            ";
            $res = $conexao->query($query);
            while ($opt = $res->fetch_assoc()) {
                $valor = htmlspecialchars($opt['id']);
                $selecionado = (isset($_GET['marca']) && $_GET['marca'] == $opt['id']) ? 'selected' : '';
                echo '<option value="'.$valor.'" '.$selecionado.'>'.$opt['marca'].'</option>';
            }
        }
    } elseif ($campo === 'modelo') {
        // 🔹 Modelos apenas das OMs visíveis
        if (!empty($oms_visiveis)) {
            $ids = implode(',', array_keys($oms_visiveis));
            $query = "
                SELECT DISTINCT mo.id, mo.nome_modelo
                FROM frota f
                JOIN config_modelos mo ON f.modelo = mo.id
                WHERE f.modelo <> '' AND f.batalhao IN ($ids)
                ORDER BY mo.nome_modelo
            ";
            $res = $conexao->query($query);
            while ($opt = $res->fetch_assoc()) {
                $valor = htmlspecialchars($opt['id']);
                $selecionado = (isset($_GET['modelo']) && $_GET['modelo'] == $opt['id']) ? 'selected' : '';
                echo '<option value="'.$valor.'" '.$selecionado.'>'.$opt['nome_modelo'].'</option>';
            }
        }
    } else {
        // 🔹 Demais campos → apenas valores das OMs visíveis
        if (!empty($oms_visiveis)) {
            $ids = implode(',', array_keys($oms_visiveis));
            $query = "SELECT DISTINCT $campo FROM frota WHERE $campo <> '' AND batalhao IN ($ids) ORDER BY $campo";
            $res = $conexao->query($query);
            while ($opt = $res->fetch_assoc()) {
                $valor = htmlspecialchars($opt[$campo]);
                $selecionado = (isset($_GET[$campo]) && $_GET[$campo] === $opt[$campo]) ? 'selected' : '';
                echo '<option value="'.$valor.'" '.$selecionado.'>'.$valor.'</option>';
            }
        }
    }

    echo '</select></div>';
}
?>


<!-- 🔹 Campo de pesquisa -->
      <div class="col-md-3">
        <label class="form-label">Pesquisar</label>
        <input type="text" name="pesquisa" class="form-control" placeholder="Buscar por prefixo ou modelo"
               value="<?= htmlspecialchars($_GET['pesquisa'] ?? '') ?>">
      </div>
    </div>

    <div class="mt-3 text-end d-flex justify-content-end gap-2">
      <button type="submit" class="btn btn-primary">Aplicar Filtros</button>
      <button type="button" id="btnLimparFiltrosdoCtrlODO" class="btn btn-outline-secondary">Limpar Filtros</button>
    </div>
  </form>
</div>

         <?= renderPaginacaoOdometro($pagina, $totalPaginas, $limite, $queryString) ?>

<!-- Accordion das Viaturas -->
<div class="accordion" id="accordionViaturas">
<?php while ($viatura = $resultViaturas->fetch_assoc()):
    $viatura_id = $viatura['id'];
    $statusOdometro = $viatura['status_odometro'] ?: 'Funciona';

    // Último registro do odômetro apenas se Funciona
    if ($statusOdometro === 'Funciona') {
        $sqlUltimo = "SELECT odometro, data FROM controle_medicoes WHERE viatura_id = ? ORDER BY data DESC LIMIT 1";
        $stmtUltimo = $conexao->prepare($sqlUltimo);
        $stmtUltimo->bind_param("i", $viatura_id);
        $stmtUltimo->execute();
        $resUltimo = $stmtUltimo->get_result()->fetch_assoc();

        $ultimoOdometro = $resUltimo['odometro'] ?? null;
        $dataUltimo = $resUltimo['data'] ?? null;

        $odometroExibicao = $ultimoOdometro !== null 
            ? number_format($ultimoOdometro,0,",",".") . (strtoupper($viatura['tipo'] ?? '') === 'EQP' ? " H" : " Km") 
            : "-";
        $dataExibicao = $dataUltimo ? date("d/m/Y", strtotime($dataUltimo)) : "-";
    } else {
        // Se Não Funciona ou Não Possui, exibe status
        $odometroExibicao = $statusOdometro;
        $dataExibicao = "-";
    }

    $disponibilidade = $viatura['disponibilidade'] ?? '-';
    $confiabilidade = $viatura['confiabilidade'] ?? '-';

    switch ($disponibilidade) {
        case 'Disponível': $badgeDisp = 'bg-success text-white'; break;
        case 'Indisponível': $badgeDisp = 'bg-danger text-white'; break;
        case 'Disponível com restrição': $badgeDisp = 'bg-warning text-dark'; break;
        default: $badgeDisp = 'bg-secondary text-white'; break;
    }

    $linhaAntiga = in_array($viatura_id, $viaturasOdometroAntigo);
?>
    <div class="accordion-item mb-2 border rounded shadow-sm <?= $linhaAntiga ? 'alert-odometro-antigo' : '' ?>">
        <h2 class="accordion-header" id="heading<?= $viatura_id ?>">
            <button class="accordion-button <?= $linhaAntiga ? '' : 'collapsed' ?>" type="button" 
                    data-bs-toggle="collapse" data-bs-target="#collapse<?= $viatura_id ?>"
                    aria-expanded="<?= $linhaAntiga ? 'true' : 'false' ?>"
                    <?= $linhaAntiga ? 'style="background-color:#f8d7da;"' : '' ?>>
                
                <div class="d-flex w-100 justify-content-between align-items-center">

                    <div class="d-flex flex-column">
                        <span class="fw-bold"><?= htmlspecialchars($viatura['prefixo_sga']) ?></span>
                        <small class="text-muted"><?= htmlspecialchars($viatura['nome_marca'] ?? '-') ?> <?= htmlspecialchars($viatura['nome_modelo'] ?? '-') ?></small>
                    </div>

                    <div class="text-center">
                        <div class="small text-muted">Último registro</div>
                        <div class="fw-semibold"><?= $odometroExibicao ?></div>
                        <div class="small text-muted"><?= $dataExibicao ?></div>
                    </div>

                    <div class="d-flex flex-column align-items-end gap-1">
                        <div class="d-flex flex-wrap justify-content-end gap-1">
                            <span class="badge <?= $badgeDisp ?>"><?= htmlspecialchars($disponibilidade) ?></span>
                            <span class="badge bg-warning text-dark text-truncate" style="max-width:120px;" title="<?= htmlspecialchars($confiabilidade) ?>">
                                <?= htmlspecialchars($confiabilidade) ?>
                            </span>
                            <span class="badge bg-dark"><?= htmlspecialchars(strtoupper($viatura['tipo'] ?: 'Indef')) ?></span>
                        </div>
                    </div>

                </div>
            </button>
        </h2>

        <div id="collapse<?= $viatura_id ?>" class="accordion-collapse collapse <?= $linhaAntiga ? 'show' : '' ?>" data-bs-parent="#accordionViaturas">
            <div class="accordion-body" id="accordionBody<?= $viatura_id ?>">
                <?php
                $_GET['viatura_id'] = $viatura_id;
                include 'controle_accordion.php';
                ?>
            </div>
        </div>
    </div>
<?php endwhile; ?>
</div>
</div>

    </div>
  </div>
</div>

<script>
  window.funcaoInicializacao = 'inicializarAtualizarOdometro';
</script>
