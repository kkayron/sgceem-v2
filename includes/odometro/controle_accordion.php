<?php
require_once '../../conexao/config.php';

if (!isset($_GET['viatura_id'])) {
    echo "<p class='text-muted'>Viatura não informada</p>";
    exit;
}

$viatura_id = intval($_GET['viatura_id']);
$limite = 5;
$pagina = isset($_GET["page_$viatura_id"]) ? max(1,intval($_GET["page_$viatura_id"])) : 1;
$offset = ($pagina - 1) * $limite;

$where = ["viatura_id = ?"];
$params = [$viatura_id];
$tipos = "i";

$camposFiltro = ['ativo','tipo','prefixo_sga','acervo','marca','modelo','ano','confiabilidade','missao','emprego_atual','subunidade','destino','disponibilidade'];

foreach ($camposFiltro as $campo) {
    if (!empty($_GET[$campo])) {
        $where[] = "$campo = ?";
        $params[] = $_GET[$campo];
        $tipos .= 's';
    }
}

if (!empty($_GET['pesquisa'])) {
    $pesquisa = '%' . $_GET['pesquisa'] . '%';
    $where[] = "(prefixo_sga LIKE ? OR modelo LIKE ?)";
    $params[] = $pesquisa;
    $params[] = $pesquisa;
    $tipos .= 'ss';
}

$whereSQL = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

// Contagem total para paginação
$sqlCount = "SELECT COUNT(*) as total FROM controle_medicoes cm
             JOIN frota f ON f.id = cm.viatura_id
             $whereSQL";
$stmtCount = $conexao->prepare($sqlCount);
$stmtCount->bind_param($tipos, ...$params);
$stmtCount->execute();
$totalRegistros = $stmtCount->get_result()->fetch_assoc()['total'];
$totalPaginas = ceil($totalRegistros / $limite);

// Busca histórico de medições
$sqlHist = "SELECT cm.odometro, cm.data 
            FROM controle_medicoes cm
            JOIN frota f ON f.id = cm.viatura_id
            $whereSQL
            ORDER BY cm.data DESC
            LIMIT ? OFFSET ?";
$paramsHist = array_merge($params, [$limite, $offset]);
$tiposHist = $tipos . "ii";

$stmtHist = $conexao->prepare($sqlHist);
$stmtHist->bind_param($tiposHist, ...$paramsHist);
$stmtHist->execute();
$resHist = $stmtHist->get_result();
?>

<h6 class="fw-bold">Histórico de Medições</h6>

<?php if ($resHist->num_rows > 0): ?>
  <ul class="list-group list-group-flush">
<?php while ($h = $resHist->fetch_assoc()): ?>
    <li class="list-group-item d-flex justify-content-between align-items-center">
        <span><?= date("d/m/Y", strtotime($h['data'])) ?></span>
        <span><?= number_format($h['odometro'],0,",",".") ?> Km</span>
        <?php if ($pode_deletar): ?>
        <button type="button" 
                class="btn btn-sm btn-danger btn-deletar-medicao"
                data-viatura="<?= $viatura_id ?>"
                data-data="<?= $h['data'] ?>"
                title="Excluir Medição">
            <i class="fas fa-trash"></i>
        </button>
        <?php endif; ?>
    </li>
<?php endwhile; ?>
</ul>


    <?php if ($totalPaginas > 1): ?>
        <nav class="mt-2">
            <ul class="pagination justify-content-center mb-0">
                <?php
                $queryParams = $_GET;
                unset($queryParams["page_$viatura_id"]);

                for ($p = 1; $p <= $totalPaginas; $p++):
                    $active = $p == $pagina ? 'active' : '';
                    $queryParams["page_$viatura_id"] = $p;
                    $queryString = http_build_query($queryParams);
                ?>
                    <li class="page-item <?= $active ?>">
                        <a href="#" class="page-link accordion-page" 
                           data-viatura="<?= $viatura_id ?>" 
                           data-page-url="/gceemv2/includes/odometro/controle_accordion.php?<?= $queryString ?>">
                           <?= $p ?>
                        </a>
                    </li>
                <?php endfor; ?>
            </ul>
        </nav>
    <?php endif; ?>

<?php else: ?>
    <p class="text-muted">Nenhum registro encontrado</p>
<?php endif; ?>

