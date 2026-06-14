<?php
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
$id = $_GET['id'] ?? '';
$viatura = $_GET['viatura'] ?? '';
$subunidade = $_GET['subunidade'] ?? '';
$motorista = $_GET['motorista'] ?? '';
$status = $_GET['status'] ?? '';
$solicitante = $_GET['solicitante'] ?? '';
$data_ini = $_GET['data_ini'] ?? '';
$data_fim = $_GET['data_fim'] ?? '';
$batalhao = $_GET['batalhao'] ?? ''; // novo filtro

$filtros = [];
$params = [];
$tipos = '';

// ============================
// LIMITES E PAGINAÇÃO
// ============================
$limite = isset($_GET['limite']) && is_numeric($_GET['limite']) ? (int)$_GET['limite'] : 10;
$pagina = isset($_GET['pagina']) && is_numeric($_GET['pagina']) && $_GET['pagina'] > 0 ? (int)$_GET['pagina'] : 1;
$offset = ($pagina - 1) * $limite;

// ============================
// FILTROS DINÂMICOS
// ============================
$campos = [
    'f.id' => 'id',
    'fr.id' => 'viatura',
    'f.subunidade' => 'subunidade',
    'f.motorista' => 'motorista',
    'f.solicitante' => 'solicitante',
    'f.status' => 'status',
    'f.data_abertura >=' => 'data_ini',
    'f.data_abertura <=' => 'data_fim',
    'f.batalhao' => 'batalhao'
];

foreach ($campos as $coluna => $parametro) {

    if (!isset($_GET[$parametro]) || $_GET[$parametro] === '') {
        continue;
    }

    $valor = trim($_GET[$parametro]);

    // Datas
    if (str_contains($coluna, '>=')) {

        $filtros[] = "$coluna ?";
        $params[] = $valor;
        $tipos .= 's';

    } elseif (str_contains($coluna, '<=')) {

        $filtros[] = "$coluna ?";
        $params[] = $valor;
        $tipos .= 's';

    // Campos numéricos
    } elseif (
        $parametro === 'id' ||
        $parametro === 'batalhao'
    ) {

        $filtros[] = "$coluna = ?";
        $params[] = (int)$valor;
        $tipos .= 'i';

    // Viatura
    } elseif ($parametro === 'viatura') {

        $filtros[] = "(
            fr.prefixo_sga LIKE ?
            OR fr.modelo LIKE ?
            OR fr.placa LIKE ?
            OR fr.id = ?
        )";

        $like = "%{$valor}%";

        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
        $params[] = (int)$valor;

        $tipos .= 'sssi';

    // Texto
    } else {

        $filtros[] = "$coluna LIKE ?";
        $params[] = "%{$valor}%";
        $tipos .= 's';
    }
}

// ============================
// FILTRO DE BATALHÕES PERMITIDOS
// ============================
$placeholders = implode(',', array_fill(0, count($batalhoesPermitidos), '?'));
$filtros[] = "f.batalhao IN ($placeholders)";
$params = array_merge($params, $batalhoesPermitidos);
$tipos .= str_repeat('i', count($batalhoesPermitidos));

// ============================
// CONDIÇÃO FINAL
// ============================
$condicoes = !empty($filtros) ? 'WHERE ' . implode(' AND ', $filtros) : '';

// ============================
// CONSULTA TOTAL
// ============================
$sqlTotal = "SELECT COUNT(*) as total FROM sta_fichas f LEFT JOIN frota fr ON f.id_viatura = fr.id $condicoes";
$stmtTotal = $conexao->prepare($sqlTotal);
$stmtTotal->bind_param($tipos, ...$params);
$stmtTotal->execute();
$resultTotal = $stmtTotal->get_result();
$totalRegistros = $resultTotal->fetch_assoc()['total'];
$totalPaginas = ceil($totalRegistros / $limite);

// ============================
// CONSULTA PRINCIPAL
// ============================
$sql = "
    SELECT f.*, om.nome AS nome_om, fr.prefixo_sga AS prefixo_sga_lista
    FROM sta_fichas f
    LEFT JOIN organizacoes_militares om ON f.batalhao = om.id
    LEFT JOIN frota fr ON f.id_viatura = fr.id
    $condicoes
    ORDER BY f.id DESC
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
// PAGINAÇÃO (OTIMIZADA - estilo OS)
// ============================
function renderPaginacaoFichas($pagina, $totalPaginas, $limite, $queryString, $arquivo = 'includes/sta_fichas/listagem.php') {
    if ($totalPaginas <= 1) return '';

    // evita quebrar URL quando queryString vier vazio
    $qs = trim($queryString);
    $qsPrefix = ($qs !== '') ? ($qs . '&') : '';

    $html  = '<div class="pagination-wrapper">';
    $html .= '<nav><ul class="pagination pagination-sm justify-content-center">';

    // ===============================
    // Botões: Primeira / Anterior
    // ===============================
    if ($pagina > 1) {
        $url = "{$arquivo}?{$qsPrefix}pagina=1&limite={$limite}";
        $html .= "<li class='page-item'><a class='page-link paginacao-fichas' href='#' data-page='{$url}'>&laquo;</a></li>";

        $url = "{$arquivo}?{$qsPrefix}pagina=" . ($pagina - 1) . "&limite={$limite}";
        $html .= "<li class='page-item'><a class='page-link paginacao-fichas' href='#' data-page='{$url}'>&lsaquo;</a></li>";
    }

    // ===============================
    // Janela de páginas (mostra poucas)
    // ===============================
    $inicio = max(1, $pagina - 4);
    $fim    = min($totalPaginas, $pagina + 4);

    // Se quiser fixar sempre 9 páginas no miolo quando possível:
    // (ajusta bordas para manter janela cheia)
    $janela = 9; // total de números exibidos
    $qtdAtual = $fim - $inicio + 1;
    if ($qtdAtual < $janela) {
        $faltam = $janela - $qtdAtual;
        $inicio = max(1, $inicio - $faltam);
        $fim    = min($totalPaginas, $inicio + $janela - 1);
        // se bateu no fim, puxa o início novamente
        $inicio = max(1, $fim - $janela + 1);
    }

    // Ellipsis no começo
    if ($inicio > 1) {
        $html .= "<li class='page-item disabled'><span class='page-link'>...</span></li>";
    }

    // Números
    for ($i = $inicio; $i <= $fim; $i++) {
        $ativo = ($i == $pagina) ? 'active' : '';
        $url = "{$arquivo}?{$qsPrefix}pagina={$i}&limite={$limite}";
        $html .= "<li class='page-item {$ativo}'><a class='page-link paginacao-fichas' href='#' data-page='{$url}'>{$i}</a></li>";
    }

    // Ellipsis no fim
    if ($fim < $totalPaginas) {
        $html .= "<li class='page-item disabled'><span class='page-link'>...</span></li>";
    }

    // ===============================
    // Botões: Próxima / Última
    // ===============================
    if ($pagina < $totalPaginas) {
        $url = "{$arquivo}?{$qsPrefix}pagina=" . ($pagina + 1) . "&limite={$limite}";
        $html .= "<li class='page-item'><a class='page-link paginacao-fichas' href='#' data-page='{$url}'>&rsaquo;</a></li>";

        $url = "{$arquivo}?{$qsPrefix}pagina={$totalPaginas}&limite={$limite}";
        $html .= "<li class='page-item'><a class='page-link paginacao-fichas' href='#' data-page='{$url}'>&raquo;</a></li>";
    }

    $html .= '</ul></nav></div>';
    return $html;
}

// ============================
// QUERY STRING PARA PAGINAÇÃO
// ============================
$paramsGET = $_GET;
unset($paramsGET['pagina']);
$queryString = http_build_query($paramsGET);
?>






<div class="container">
  <div class="page-inner">
    <div class="d-flex justify-content-between align-items-center py-3">
      <div>
        <h3 class="fw-bold mb-1">Fichas de Viatura e Equipamentos</h3>
        <h6 class="text-muted">Listagem das fichas de viaturas e Equipamentos cadastradas no sistema</h6>
      </div>
        <div>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalCadastroVTR">
          <i class="fa fa-user-plus me-1"></i> Abrir Ficha de Vtr/Eqp
        </button>
         <!-- Botão para abrir modal -->
<button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#modalImportarSTA">
  Importar Fichas
</button>
      </div>
    </div>


    <!-- Lista de usuários -->
    <div class="card">
      <div class="card-body">
          <!-- Botão de filtros -->
<!-- Botão para mostrar filtros -->
<div class="mb-3">
  <button class="btn btn-outline-primary w-100 d-flex justify-content-center align-items-center" onclick="toggleFiltrosFichas()">
    <i class="fas fa-search me-2"></i> Filtros
  </button>
</div>

<!-- Container dos filtros -->
<div id="filtros-container-fichas" style="display: none;" class="mb-3">
  <div class="card shadow-sm border-0">
    <div class="card-body">
      <form method="GET" id="filtroFichaForm">
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
            <label class="form-label fw-semibold">Nmr da Ficha</label>
            <input type="text" class="form-control" name="id"
       value="<?= htmlspecialchars($_GET['id'] ?? '') ?>">
          </div>
          <div class="col-md-2">
            <label class="form-label fw-semibold">Viatura</label>
            <input type="text" class="form-control" name="viatura"
       value="<?= htmlspecialchars($_GET['viatura'] ?? '') ?>">
          </div>
          <div class="col-md-3">
            <label class="form-label fw-semibold">Solicitante</label>
          <input type="text" class="form-control" name="solicitante"
       value="<?= htmlspecialchars($_GET['solicitante'] ?? '') ?>">
          </div>
          <div class="col-md-3">
            <label class="form-label fw-semibold">Subunidade</label>
         <input type="text" class="form-control" name="subunidade"
       value="<?= htmlspecialchars($_GET['subunidade'] ?? '') ?>">
          </div>
          <div class="col-md-2">
            <label class="form-label fw-semibold">Status</label>
<select name="status" class="form-select">
  <option value="">Todos</option>
  <option value="Aberta" <?= ($_GET['status'] ?? '') === 'Aberta' ? 'selected' : '' ?>>Aberta</option>
  <option value="Não autorizada" <?= ($_GET['status'] ?? '') === 'Não autorizada' ? 'selected' : '' ?>>Não autorizada</option>
  <option value="Encerrada" <?= ($_GET['status'] ?? '') === 'Encerrada' ? 'selected' : '' ?>>Encerrada</option>
</select>
          </div>
          <div class="col-md-2">
            <label class="form-label fw-semibold">Data Abertura</label>
            <input type="date" class="form-control" name="data_abertura"
       value="<?= htmlspecialchars($_GET['data_abertura'] ?? '') ?>">
          </div>

          <div class="col-12 d-flex justify-content-between mt-2">
            <button type="button" id="btnLimparFiltrosFichas" class="btn btn-black ms-2">Limpar Filtros</button>
            <button type="submit" class="btn btn-primary px-4">Aplicar</button>
          </div>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Select de limite por página -->
<div class="mb-3">
  <label for="limiteFICHAS" class="me-2 mb-0">Mostrar</label>
  <select id="limiteFICHAS" name="limite" class="form-select d-inline w-auto">
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
  <?= renderPaginacaoFichas($pagina, $totalPaginas, $limite, $queryString, 'includes/sta_fichas/listagem.php'); ?>
</div>

<div class="barra-selecao-fichas">
  <div class="form-check mb-0">
    <input class="form-check-input" type="checkbox" id="checkAllFichas">
    <label class="form-check-label" for="checkAllFichas">
      Selecionar tudo (página)
    </label>
  </div>

  <div class="barra-selecao-acoes">
    <button type="button" class="btn btn-sm btn-outline-secondary" id="btnLimparSelecao" disabled>
      Limpar
    </button>

    <button type="button" class="btn btn-sm btn-danger" id="btnImprimirSelecionadas" disabled>
      <i class="fas fa-file-pdf me-1"></i> Imprimir selecionadas
      <span class="badge bg-light text-dark ms-1" id="qtdSelecionadas">0</span>
    </button>
  </div>
</div>

<div class="lista-fichas">
  <?php while ($row = $result->fetch_assoc()): ?>
    <?php
      $prefixo_sga = $row['prefixo_sga_lista'] ?? '—';

      $autorizado = strtolower(trim($row['autorizado'] ?? 'não'));

      if ($autorizado === 'sim') {
          $classeAutorizacao = 'success';
          $textoAutorizacao = 'Autorizada';
          $iconeAutorizacao = 'fa-check';
      } elseif ($autorizado === 'negado') {
          $classeAutorizacao = 'danger';
          $textoAutorizacao = 'Negada';
          $iconeAutorizacao = 'fa-times';
      } else {
          $classeAutorizacao = 'warning';
          $textoAutorizacao = 'Pendente';
          $iconeAutorizacao = 'fa-clock';
      }

      $statusClass = match ($row['status']) {
    'Aberta' => 'warning',
    'Não autorizada' => 'danger',
    'Encerrada' => 'success',
    default => 'secondary'
};
	
	$fichaPendenteAvaliacao = ($autorizado === 'não');
$classeFichaPendente = $fichaPendenteAvaliacao ? ' ficha-pendente-avaliacao' : '';
    ?>

    <div class="ficha-item<?= $classeFichaPendente ?>">
      <div class="ficha-checkbox">
        <input
          class="form-check-input ficha-check"
          type="checkbox"
          value="<?= (int)$row['id'] ?>"
          aria-label="Selecionar ficha <?= (int)$row['id'] ?>"
        >
      </div>

      <div class="ficha-conteudo">
        <div class="ficha-topo">
          <div>
            <h6 class="ficha-titulo">
              Ficha #<?= (int)$row['id'] ?>
            </h6>
            <div class="ficha-prefixo">
              <?= htmlspecialchars($prefixo_sga) ?>
            </div>
          </div>

          <div class="ficha-badges">
            <span class="badge bg-<?= $statusClass ?>">
              <?= htmlspecialchars($row['status'] ?? '—') ?>
            </span>

            <span class="badge bg-secondary">
              <?= htmlspecialchars($row['natureza'] ?? '—') ?>
            </span>
          </div>
        </div>
		  <?php if ($fichaPendenteAvaliacao): ?>
  <div class="aviso-pendente-avaliacao">
    <i class="fas fa-exclamation-triangle me-1"></i>
    Solicitação pendente de avaliação
  </div>
<?php endif; ?>

        <div class="ficha-dados">
          <div>
            <small>Data</small>
            <strong><?= !empty($row['data_abertura']) ? date('d/m/Y', strtotime($row['data_abertura'])) : '—' ?></strong>
          </div>

          <div>
            <small>Solicitante</small>
            <strong><?= htmlspecialchars($row['solicitante'] ?? '—') ?></strong>
          </div>

          <div>
            <small>Subunidade</small>
            <strong><?= htmlspecialchars($row['subunidade'] ?? '—') ?></strong>
          </div>

          <div>
            <small>Destino</small>
            <strong><?= htmlspecialchars($row['destino'] ?? '—') ?></strong>
          </div>
        </div>
      </div>

      <div class="ficha-acoes">
        <button type="button"
                class="btn btn-sm btn-outline-<?= $classeAutorizacao ?> d-flex align-items-center btn-autorizar-ficha"
                data-id="<?= (int)$row['id'] ?>"
                data-autorizado="<?= htmlspecialchars($autorizado, ENT_QUOTES, 'UTF-8') ?>"
                data-observacao="<?= htmlspecialchars($row['observacao_autorizacao'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                onclick="abrirModalAutorizacaoFicha(this)"
                data-bs-toggle="modal"
                data-bs-target="#modalAutorizarFicha"
                title="Alterar autorização">
          <i class="fas <?= $iconeAutorizacao ?> me-1"></i>
          <?= $textoAutorizacao ?>
        </button>

        <div class="dropdown">
          <button class="btn btn-sm btn-outline-secondary dropdown-toggle d-flex align-items-center"
                  type="button"
                  id="dropdownMenu<?= (int)$row['id'] ?>"
                  data-bs-toggle="dropdown"
                  aria-expanded="false">
            <i class="fas fa-print me-1"></i> Imprimir
          </button>

          <ul class="dropdown-menu dropdown-menu-end shadow-sm rounded" aria-labelledby="dropdownMenu<?= (int)$row['id'] ?>">
            <li>
              <a href="#"
                 class="dropdown-item text-danger btnExportarPDFsta"
                 data-id="<?= (int)$row['id'] ?>">
                <i class="fas fa-file-pdf me-2"></i> Imprimir Ficha
              </a>
            </li>
          </ul>
        </div>

        <button class="btn btn-sm btn-outline-primary d-flex align-items-center"
                onclick="verFicha(<?= (int)$row['id'] ?>)"
                data-bs-toggle="modal"
                data-bs-target="#modalVerFICHA">
          <i class="fas fa-eye me-1"></i> Ver
        </button>

        <button class="btn btn-sm btn-outline-warning d-flex align-items-center"
                onclick="editarFICHA(<?= (int)$row['id'] ?>)"
                data-bs-toggle="modal"
                data-bs-target="#modalEditarFICHA">
          <i class="fas fa-edit me-1"></i> Editar
        </button>

        <button type="button"
                class="btn btn-sm btn-outline-danger d-flex align-items-center btn-deletar-ficha"
                data-id="<?= (int)$row['id'] ?>"
                onclick="deletarFicha(this)"
                title="Remover Ficha">
          <i class="fa fa-times"></i>
        </button>
      </div>
    </div>
  <?php endwhile; ?>
</div>

<div class="paginacao mt-3">
  <?= renderPaginacaoFichas($pagina, $totalPaginas, $limite, $queryString, 'includes/sta_fichas/listagem.php'); ?>
</div>

<style>
  .barra-selecao-fichas {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: .75rem;
    margin-bottom: 1rem;
    padding: .75rem 1rem;
    background: #fff;
    border: 1px solid #e9ecef;
    border-radius: .9rem;
    box-shadow: 0 .25rem .75rem rgba(0,0,0,.04);
  }

  .barra-selecao-acoes {
    display: flex;
    gap: .5rem;
    align-items: center;
  }

  .lista-fichas {
    display: flex;
    flex-direction: column;
    gap: .85rem;
  }

  .ficha-item {
    display: grid;
    grid-template-columns: auto minmax(0, 1fr) auto;
    align-items: center;
    gap: 1rem;
    padding: 1rem;
    background: #fff;
    border: 1px solid #e9ecef;
    border-radius: 1rem;
    box-shadow: 0 .25rem .75rem rgba(0,0,0,.04);
    transition: all .2s ease-in-out;
  }

  .ficha-item:hover {
    background-color: #fbfbfc;
    transform: translateY(-2px);
    box-shadow: 0 .5rem 1.2rem rgba(0,0,0,.07);
  }

  .ficha-checkbox {
    display: flex;
    align-items: center;
    justify-content: center;
  }

  .ficha-check {
    transform: scale(1.15);
  }

  .ficha-conteudo {
    min-width: 0;
  }

  .ficha-topo {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: .75rem;
    margin-bottom: .6rem;
  }

  .ficha-titulo {
    margin: 0;
    font-weight: 700;
    color: #0d6efd;
  }

  .ficha-prefixo {
    margin-top: .2rem;
    font-size: .8rem;
    color: #6c757d;
  }

  .ficha-badges {
    display: flex;
    flex-wrap: wrap;
    justify-content: flex-end;
    gap: .35rem;
  }

  .ficha-dados {
    display: grid;
    grid-template-columns: repeat(4, minmax(110px, 1fr));
    gap: .6rem;
  }

  .ficha-dados small {
    display: block;
    color: #6c757d;
    font-size: .72rem;
    text-transform: uppercase;
    letter-spacing: .03em;
    margin-bottom: .15rem;
  }

  .ficha-dados strong {
    display: block;
    color: #212529;
    font-size: .88rem;
    font-weight: 600;
    line-height: 1.25;
    word-break: break-word;
  }

  .ficha-acoes {
    display: grid;
    grid-template-columns: repeat(2, auto);
    gap: .45rem;
    justify-content: end;
    align-items: center;
  }

  .ficha-acoes .btn {
    min-height: 31px;
    justify-content: center;
    white-space: nowrap;
  }

  .ficha-acoes .btn-deletar-ficha {
    width: 36px;
    padding-left: .5rem;
    padding-right: .5rem;
  }

  @media (max-width: 1200px) {
    .ficha-item {
      grid-template-columns: auto 1fr;
    }

    .ficha-acoes {
      grid-column: 2 / 3;
      grid-template-columns: repeat(5, auto);
      justify-content: start;
      margin-top: .25rem;
    }
  }

  @media (max-width: 768px) {
    .barra-selecao-fichas {
      flex-direction: column;
      align-items: stretch;
    }

    .barra-selecao-acoes {
      display: grid;
      grid-template-columns: 1fr 1fr;
      width: 100%;
    }

    .barra-selecao-acoes .btn {
      width: 100%;
    }

    .ficha-item {
      grid-template-columns: 1fr;
      gap: .75rem;
    }

    .ficha-checkbox {
      justify-content: flex-start;
    }

    .ficha-check {
      transform: scale(1.25);
    }

    .ficha-topo {
      flex-direction: column;
      align-items: flex-start;
    }

    .ficha-badges {
      justify-content: flex-start;
    }

    .ficha-dados {
      grid-template-columns: 1fr 1fr;
    }

    .ficha-acoes {
      grid-column: auto;
      width: 100%;
      grid-template-columns: 1fr 1fr;
      justify-content: stretch;
    }

    .ficha-acoes .btn,
    .ficha-acoes .dropdown,
    .ficha-acoes .dropdown .btn {
      width: 100%;
    }

    .ficha-acoes .btn-deletar-ficha {
      width: 100%;
    }
  }

  @media (max-width: 480px) {
    .ficha-dados {
      grid-template-columns: 1fr;
    }

    .ficha-acoes {
      grid-template-columns: 1fr;
    }
  }
	
	.ficha-pendente-avaliacao {
  background: #fff8e1 !important;
  border-color: #ffe08a !important;
}

.ficha-pendente-avaliacao:hover {
  background: #fff3cd !important;
}

.aviso-pendente-avaliacao {
  display: inline-flex;
  align-items: center;
  width: fit-content;
  margin-bottom: .65rem;
  padding: .35rem .65rem;
  border-radius: 999px;
  background: #fff3cd;
  border: 1px solid #ffda6a;
  color: #7a5a00;
  font-size: .78rem;
  font-weight: 600;
}
</style>

      
      <!-- Modal de Cadastro da Ficha STA -->
<div class="modal fade" id="modalCadastroVTR" tabindex="-1" aria-labelledby="modalCadastroVTRLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <form method="POST" enctype="multipart/form-data" id="form-ficha-sta">
        <div class="modal-header">
          <h5 class="modal-title" id="modalCadastroVTRLabel">Abrir Ficha de Vtr/Eqp</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
        </div>
        <div class="modal-body">
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

// =======================================================
// MONTA ARRAY DE BATALHÕES PERMITIDOS PARA CONSULTAS
// =======================================================
$batalhoesPermitidos = [];
$sqlPermitidos = $conexao->query($sqlBatalhoes);
while ($batPermitido = $sqlPermitidos->fetch_assoc()) {
    $batalhoesPermitidos[] = (int)$batPermitido['id'];
}
?>


                <div class="col-md-6">
                  <label class="form-label">Batalhão</label>
                  <select name="id_om" id="batalhao_importacao" class="form-select" required>
                    <option value="">Selecione...</option>
                    <?php while ($bat = $resBatalhoes->fetch_assoc()): ?>
                      <option value="<?= $bat['id'] ?>" <?= ($bat['id']==$id_om_usuario)?'selected':'' ?>>
                        <?= htmlspecialchars($bat['abreviatura'] . ' - ' . $bat['nome']) ?>
                      </option>
                    <?php endwhile; ?>
                  </select>
                </div>
              
                <?php
              // ==== Viaturas ====
              $viaturas = [];
              if (!empty($batalhoesPermitidos)) {
                  $placeholders = implode(',', array_fill(0, count($batalhoesPermitidos), '?'));
                  $sqlViaturas = "
                      SELECT f.id, f.prefixo_sga, f.prefixo_velho,
                             cm.marca AS nome_marca, md.nome_modelo AS nome_modelo,
                             f.ano, om.nome AS nome_om
                      FROM frota f
                      LEFT JOIN config_marcas cm ON f.marca = cm.id
                      LEFT JOIN config_modelos md ON f.modelo = md.id
                      LEFT JOIN organizacoes_militares om ON f.batalhao = om.id
                      WHERE f.batalhao IN ($placeholders)
                      ORDER BY f.prefixo_sga ASC
                  ";
                  $stmtViaturas = $conexao->prepare($sqlViaturas);
                  $tipos = str_repeat('i', count($batalhoesPermitidos));
                  $stmtViaturas->bind_param($tipos, ...$batalhoesPermitidos);
                  $stmtViaturas->execute();
                  $resViaturas = $stmtViaturas->get_result();
                  while ($row = $resViaturas->fetch_assoc()) {
                      $viaturas[] = $row;
                  }
                  $stmtViaturas->close();
              }
              ?>              
              
              <div class="col-md-6">
  <label  class="form-label">Viatura (Prefixo SGA)</label>
  <select class="form-select" name="id_viatura" required>
                    <option value="" disabled selected>Selecione a viatura/equipamento</option>
                       <?php foreach ($viaturas as $vtr): ?>
                      <option value="<?= $vtr['id'] ?>">
                        <?= htmlspecialchars($vtr['prefixo_sga'] . ' - ' . $vtr['prefixo_velho'] . ' - ' . $vtr['nome_marca'] . ' - ' . $vtr['nome_modelo'] . ' - ' . $vtr['ano'] . ' - ' . $vtr['nome_om']) ?>
                      </option>
                    <?php endforeach; ?>
  </select>
</div>
            

      

                

            <div class="col-md-6">
              <label class="form-label">Data de Inicio de Deslocamento</label>
              <input type="date" name="data_abertura" class="form-control" required>
            </div>
              
              <div class="col-md-6">
              <label class="form-label">Data Prevista para Retorno</label>
              <input type="date" name="data_prevista" class="form-control" required>
            </div>

            <div class="col-md-6">
              <label class="form-label">Solicitante</label>
              <input type="text" name="solicitante" class="form-control" required>
            </div>

            <div class="col-md-6">
              <label class="form-label">Motorista</label>
              <input type="text" name="motorista" class="form-control" required>
            </div>

            <div class="col-md-6">
              <label class="form-label">Subunidade</label>
              <input type="text" name="subunidade" class="form-control" required>
            </div>

            <div class="col-md-6">
              <label class="form-label">Destino especificado</label>
              <input type="text" name="destino" class="form-control" required>
            </div>
              
              <div class="col-md-6">
              <label class="form-label">Cidade/UF destino</label>
              <input type="text" name="cidade" class="form-control" required>
            </div>

            <div class="col-md-6">
              <label class="form-label">Chefe a se Apresentar</label>
              <input type="text" name="chefe_apresentar" class="form-control" required>
            </div>

            <div class="col-md-4">
              <label class="form-label">Local a se Apresentar</label>
              <input type="text" name="local_apresentar" class="form-control" required>
            </div>

            <div class="col-md-2">
              <label class="form-label">Horário</label>
              <input type="time" name="horario_apresentar" class="form-control" required>
            </div>

            <div class="col-md-6">
              <label class="form-label">Natureza/Missão</label>
              <input type="text" name="natureza" class="form-control" required>
            </div>

            <div class="col-md-6">
              <label class="form-label">Status</label>
              <select class="form-select" name="status" required>
                <option value="" disabled selected>Selecione</option>
                <option value="Aberta">Aberta</option>
                <option value="Não autorizada">Não autorizada</option>
                <option value="Encerrada">Encerrada</option>
              </select>
            </div>
              <div class="col-12">
               <div class="p-3 mb-4 border rounded bg-light">
             <h5 class="text-uppercase fw-bold text-danger mb-3">
      PREENCHIMENTO SOMENTE APÓS O RETORNO DA VTR/EQP DA MISSÃO    </h5>

            <!-- CAMPOS OPCIONAIS (pós missão) -->
            <div class="col-md-4">
              <label class="form-label">Data Saída</label>
              <input type="date" name="data_saida" class="form-control">
            </div>
            <div class="col-md-2">
              <label class="form-label">Hora Saída</label>
              <input type="time" name="hora_saida" class="form-control">
            </div>
            <div class="col-md-2">
              <label class="form-label">Odômetro Saída</label>
              <input type="number" name="odo_saida" class="form-control" step="any">
            </div>

            <div class="col-md-4">
              <label class="form-label">Data Retorno</label>
              <input type="date" name="data_retorno" class="form-control">
            </div>
            <div class="col-md-2">
              <label class="form-label">Hora Retorno</label>
              <input type="time" name="hora_retorno" class="form-control">
            </div>
            <div class="col-md-2">
              <label class="form-label">Odômetro Retorno</label>
              <input type="number" name="odo_retorno" class="form-control" step="any">
            </div>

            <div class="col-md-12">
              <label class="form-label">Observações Pós-Emprego</label>
              <textarea name="observacoes_pos_emprego" class="form-control" rows="3"></textarea>
            </div>
                  </div>
                  </div>
          </div>
        </div>
        <div class="modal-footer justify-content-between">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" id="btnCadastrarFicha" class="btn btn-success">
    Cadastrar Ficha
</button>
        </div>
      </form>
    </div>
  </div>
</div>




<!-- Modal de Edição da Ficha STA -->
<div class="modal fade" id="modalEditarFICHA" tabindex="-1" aria-labelledby="modalEditarFICHA_Label" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <form method="POST" enctype="multipart/form-data" id="form-editar-ficha">
        <div class="modal-header">
          <h5 class="modal-title" id="modalEditarFICHA_Label">Editar Ficha de Vtr/Eqp</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
        </div>
        <div class="modal-body">
          <input type="hidden" name="id" id="editar-ficha-id">

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

// =======================================================
// MONTA ARRAY DE BATALHÕES PERMITIDOS PARA CONSULTAS
// =======================================================
$batalhoesPermitidos = [];
$sqlPermitidos = $conexao->query($sqlBatalhoes);
while ($batPermitido = $sqlPermitidos->fetch_assoc()) {
    $batalhoesPermitidos[] = (int)$batPermitido['id'];
}
?>


                <div class="col-md-6">
                  <label class="form-label">Batalhão</label>
                  <select name="id_om" id="editar-id_om" class="form-select" required>
                    <option value="">Selecione...</option>
                    <?php while ($bat = $resBatalhoes->fetch_assoc()): ?>
                      <option value="<?= $bat['id'] ?>" <?= ($bat['id']==$id_om_usuario)?'selected':'' ?>>
                        <?= htmlspecialchars($bat['abreviatura'] . ' - ' . $bat['nome']) ?>
                      </option>
                    <?php endwhile; ?>
                  </select>
                </div>
              
                <?php
              // ==== Viaturas ====
              $viaturas = [];
              if (!empty($batalhoesPermitidos)) {
                  $placeholders = implode(',', array_fill(0, count($batalhoesPermitidos), '?'));
                  $sqlViaturas = "
                      SELECT f.id, f.prefixo_sga, f.prefixo_velho,
                             cm.marca AS nome_marca, md.nome_modelo AS nome_modelo,
                             f.ano, om.nome AS nome_om
                      FROM frota f
                      LEFT JOIN config_marcas cm ON f.marca = cm.id
                      LEFT JOIN config_modelos md ON f.modelo = md.id
                      LEFT JOIN organizacoes_militares om ON f.batalhao = om.id
                      WHERE f.batalhao IN ($placeholders)
                      ORDER BY f.prefixo_sga ASC
                  ";
                  $stmtViaturas = $conexao->prepare($sqlViaturas);
                  $tipos = str_repeat('i', count($batalhoesPermitidos));
                  $stmtViaturas->bind_param($tipos, ...$batalhoesPermitidos);
                  $stmtViaturas->execute();
                  $resViaturas = $stmtViaturas->get_result();
                  while ($row = $resViaturas->fetch_assoc()) {
                      $viaturas[] = $row;
                  }
                  $stmtViaturas->close();
              }
              ?>              
              
              <div class="col-md-6">
  <label  class="form-label">Viatura (Prefixo SGA)</label>
  <select class="form-select" name="id_viatura" id="editar-id_viatura" required>
                    <option value="" disabled selected>Selecione a viatura/equipamento</option>
                       <?php foreach ($viaturas as $vtr): ?>
                      <option value="<?= $vtr['id'] ?>">
                        <?= htmlspecialchars($vtr['prefixo_sga'] . ' - ' . $vtr['prefixo_velho'] . ' - ' . $vtr['nome_marca'] . ' - ' . $vtr['nome_modelo'] . ' - ' . $vtr['ano'] . ' - ' . $vtr['nome_om']) ?>
                      </option>
                    <?php endforeach; ?>
  </select>
</div>

            <div class="col-md-6">
              <label class="form-label">Data de Abertura</label>
              <input type="date" name="data_abertura" id="editar-data_abertura" class="form-control" required>
            </div>
              
               <div class="col-md-6">
              <label class="form-label">Data prevista para retorno</label>
              <input type="date" name="data_prevista" id="editar-data_prevista" class="form-control" required>
            </div>

            <div class="col-md-6">
              <label class="form-label">Solicitante</label>
              <input type="text" name="solicitante" id="editar-solicitante" class="form-control" required>
            </div>

            <div class="col-md-6">
              <label class="form-label">Motorista</label>
              <input type="text" name="motorista" id="editar-motorista" class="form-control" required>
            </div>

            <div class="col-md-6">
              <label class="form-label">Subunidade</label>
              <input type="text" name="subunidade" id="editar-subunidade" class="form-control" required>
            </div>

            <div class="col-md-6">
              <label class="form-label">Destino especificado</label>
              <input type="text" name="destino" id="editar-destino" class="form-control" required>
            </div>

            <div class="col-md-6">
              <label class="form-label">Cidade/UF destino</label>
              <input type="text" name="cidade" id="editar-cidade" class="form-control" required>
            </div>

            <div class="col-md-6">
              <label class="form-label">Chefe a se Apresentar</label>
              <input type="text" name="chefe_apresentar" id="editar-chefe_apresentar" class="form-control" required>
            </div>

            <div class="col-md-4">
              <label class="form-label">Local a se Apresentar</label>
              <input type="text" name="local_apresentar" id="editar-local_apresentar" class="form-control" required>
            </div>

            <div class="col-md-2">
              <label class="form-label">Horário</label>
              <input type="time" name="horario_apresentar" id="editar-horario_apresentar" class="form-control" required>
            </div>

            <div class="col-md-6">
              <label class="form-label">Natureza</label>
              <input type="text" name="natureza" id="editar-natureza" class="form-control" required>
            </div>

            <div class="col-md-6">
              <label class="form-label">Status</label>
              <select class="form-select" name="status" id="editar-status" required>
                <option value="">Selecione</option>
                <option value="Aberta">Aberta</option>
                <option value="Não autorizada">Não autorizada</option>
                <option value="Encerrada">Encerrada</option>
              </select>
            </div>

            <div class="col-12">
              <div class="p-3 mb-4 border rounded bg-light">
                <h5 class="text-uppercase fw-bold text-danger mb-3">
                  PREENCHIMENTO SOMENTE APÓS O RETORNO DA VTR/EQP DA MISSÃO
                </h5>

                <div class="row g-3">
                  <div class="col-md-4">
                    <label class="form-label">Data Saída</label>
                    <input type="date" name="data_saida" id="editar-data_saida" class="form-control">
                  </div>
                  <div class="col-md-2">
                    <label class="form-label">Hora Saída</label>
                    <input type="time" name="hora_saida" id="editar-hora_saida" class="form-control">
                  </div>
                  <div class="col-md-2">
                    <label class="form-label">Odômetro Saída</label>
                    <input type="number" name="odo_saida" id="editar-odo_saida" class="form-control" step="any">
                  </div>

                  <div class="col-md-4">
                    <label class="form-label">Data Retorno</label>
                    <input type="date" name="data_retorno" id="editar-data_retorno" class="form-control">
                  </div>
                  <div class="col-md-2">
                    <label class="form-label">Hora Retorno</label>
                    <input type="time" name="hora_retorno" id="editar-hora_retorno" class="form-control">
                  </div>
                  <div class="col-md-2">
                    <label class="form-label">Odômetro Retorno</label>
                    <input type="number" name="odo_retorno" id="editar-odo_retorno" class="form-control" step="any">
                  </div>

                  <div class="col-md-12">
                    <label class="form-label">Observações Pós-Emprego</label>
                    <textarea name="observacoes_pos_emprego" id="editar-observacoes_pos_emprego" class="form-control" rows="3"></textarea>
                  </div>
                </div>
              </div>
            </div>
          </div> <!-- row -->
        </div> <!-- modal-body -->

        <div class="modal-footer justify-content-between">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-success">Salvar Alterações</button>
        </div>
      </form>
    </div>
  </div>
</div>


<!-- Modal de Ver Ficha STA -->
<div class="modal fade" id="modalVerFICHA" tabindex="-1" aria-labelledby="modalVerFICHA_Label" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content border-0 shadow">
      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title">Ver Ficha de Viatura/Equipamento</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Fechar"></button>
      </div>

      <div class="modal-body p-4 bg-light">

        <!-- Título (OM) -->
        <h4 class="text-center fw-bold text-dark mb-4" id="tituloOM"></h4>

        <!-- Dados da Viatura -->
        <div class="row g-3 bg-white p-3 rounded shadow-sm mb-4">
            
          <h5 class="fw-bold text-secondary">Dados da Viatura/Equipamento</h5>
              <!-- Foto da viatura -->
        <div class="text-center mb-4">
          <img id="fotoViaturaFicha" src="" alt="Foto da Viatura" class="img-thumbnail shadow-sm" style="max-width: 100%; max-height: 100px; object-fit: cover;">
        </div>
          <div class="col-md-3">
            <label class="form-label">Prefixo</label>
            <p class="form-control-plaintext" id="verf_prefixo"></p>
          </div>
          <div class="col-md-3">
            <label class="form-label">Chassi</label>
            <p class="form-control-plaintext" id="verf_chassi"></p>
          </div>
          <div class="col-md-3">
            <label class="form-label">Marca</label>
            <p class="form-control-plaintext" id="verf_marca"></p>
          </div>
          <div class="col-md-3">
            <label class="form-label">Modelo</label>
            <p class="form-control-plaintext" id="verf_modelo"></p>
          </div>
        </div>

        <!-- Dados Principais -->
        <div class="row g-3 bg-white p-3 rounded shadow-sm mb-4">
          <h5 class="fw-bold text-secondary">Dados da Ficha</h5>
          <div class="col-md-4">
            <label class="form-label">Organização Militar</label>
            <p class="form-control-plaintext" id="verf_om"></p>
          </div>
          <div class="col-md-4">
            <label class="form-label">Data de Abertura</label>
            <p class="form-control-plaintext" id="verf_data_abertura"></p>
          </div>
          <div class="col-md-4">
            <label class="form-label">Data Prevista para retorno</label>
            <p class="form-control-plaintext" id="verf_data_prevista"></p>
          </div>
          <div class="col-md-4">
            <label class="form-label">Status</label>
            <p class="form-control-plaintext" id="verf_status"></p>
          </div>
        </div>

        <!-- Dados da Missão -->
        <div class="row g-3 bg-white p-3 rounded shadow-sm mb-4">
          <h5 class="fw-bold text-secondary">Dados da Missão</h5>
          <div class="col-md-3">
            <label class="form-label">Solicitante</label>
            <p class="form-control-plaintext" id="verf_solicitante"></p>
          </div>
          <div class="col-md-3">
            <label class="form-label">Motorista</label>
            <p class="form-control-plaintext" id="verf_motorista"></p>
          </div>
          <div class="col-md-3">
            <label class="form-label">Subunidade</label>
            <p class="form-control-plaintext" id="verf_subunidade"></p>
          </div>
          <div class="col-md-3">
            <label class="form-label">Natureza</label>
            <p class="form-control-plaintext" id="verf_natureza"></p>
          </div>
          <div class="col-md-4">
            <label class="form-label">Destino</label>
            <p class="form-control-plaintext" id="verf_destino"></p>
          </div>
          <div class="col-md-4">
            <label class="form-label">Cidade/UF</label>
            <p class="form-control-plaintext" id="verf_cidade"></p>
          </div>
          <div class="col-md-4">
            <label class="form-label">Chefe a se Apresentar</label>
            <p class="form-control-plaintext" id="verf_chefe_apresentar"></p>
          </div>
          <div class="col-md-4">
            <label class="form-label">Local a se Apresentar</label>
            <p class="form-control-plaintext" id="verf_local_apresentar"></p>
          </div>
          <div class="col-md-4">
            <label class="form-label">Horário</label>
            <p class="form-control-plaintext" id="verf_horario_apresentar"></p>
          </div>
        </div>

        <!-- Dados Pós-Emprego -->
        <div class="row g-3 bg-white p-3 rounded shadow-sm mb-4">
          <h5 class="fw-bold text-danger">Pós-Emprego da Vtr/Eqp</h5>
          <div class="col-md-3">
            <label class="form-label">Data Saída</label>
            <p class="form-control-plaintext" id="verf_data_saida"></p>
          </div>
          <div class="col-md-2">
            <label class="form-label">Hora Saída</label>
            <p class="form-control-plaintext" id="verf_hora_saida"></p>
          </div>
          <div class="col-md-2">
            <label class="form-label">Odômetro Saída</label>
            <p class="form-control-plaintext" id="verf_odo_saida"></p>
          </div>
          <div class="col-md-3">
            <label class="form-label">Data Retorno</label>
            <p class="form-control-plaintext" id="verf_data_retorno"></p>
          </div>
          <div class="col-md-2">
            <label class="form-label">Hora Retorno</label>
            <p class="form-control-plaintext" id="verf_hora_retorno"></p>
          </div>
          <div class="col-md-2">
            <label class="form-label">Odômetro Retorno</label>
            <p class="form-control-plaintext" id="verf_odo_retorno"></p>
          </div>
          <div class="col-md-12">
            <label class="form-label">Observações Pós-Emprego</label>
            <p class="form-control-plaintext" id="verf_observacoes"></p>
          </div>
        </div>

        <!-- Logs -->
        <div class="bg-white p-3 rounded shadow-sm mb-4">
          <h5 class="fw-bold text-secondary">Histórico de Alterações</h5>
          <div id="verf_logsContainer">
            <p class="text-muted">Nenhum log disponível.</p>
          </div>
        </div>

        <!-- Botão -->
        <div class="text-end">
          <button type="button" class="btn btn-danger" data-bs-dismiss="modal">Fechar</button>
        </div>
      </div>
    </div>
  </div>
</div>

     
   
      
  </div>
</div>
	  
	  <div class="modal fade" id="modalAutorizarFicha" tabindex="-1" aria-labelledby="modalAutorizarFichaLabel" aria-hidden="true">
  <div class="modal-dialog modal-md modal-dialog-centered">
    <div class="modal-content">
      <form id="form-autorizar-ficha">
        <div class="modal-header">
          <h5 class="modal-title" id="modalAutorizarFichaLabel">Autorização da Ficha</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
        </div>

        <div class="modal-body">
          <input type="hidden" name="id" id="autorizar-ficha-id">

          <div class="mb-3">
            <label class="form-label fw-semibold">Situação da autorização</label>
            <select name="autorizado" id="autorizar-ficha-status" class="form-select" required>
              <option value="não">Pendente</option>
              <option value="sim">Autorizar</option>
              <option value="negado">Negar</option>
            </select>
          </div>

          <div class="mb-3">
            <label class="form-label fw-semibold">Observação</label>
            <textarea name="observacao_autorizacao"
                      id="observacao-autorizacao"
                      class="form-control"
                      rows="3"
                      placeholder="Informe uma observação, se necessário"></textarea>
          </div>

          <div class="alert alert-info mb-0">
            Ao autorizar, a ficha ficará liberada para uso/impressão. Ao negar, ela permanecerá registrada, mas não autorizada.
          </div>
        </div>

        <div class="modal-footer justify-content-between">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-success">
            <i class="fas fa-save me-1"></i> Salvar Autorização
          </button>
        </div>
      </form>
    </div>
  </div>
</div>
	  
	  <!-- Modal de Importação STA -->
<div class="modal fade" id="modalImportarSTA" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form id="formImportarSTA" method="POST" enctype="multipart/form-data">
        <div class="modal-header">
          <h5 class="modal-title">Importar Planilha de Fichas STA</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>

        <div class="modal-body">
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
                      SELECT id_om_menor 
                      FROM organizacoes_militares_sub 
                      WHERE id_om_maior = $id_om_usuario
                  )
                  ORDER BY om.nome
              ";
          }

          $resBatalhoes = $conexao->query($sqlBatalhoes);
          ?>

          <label class="form-label">Selecione o Batalhão das Fichas</label>
          <select name="batalhao" id="batalhao_importacao_sta" class="form-select mb-3" required>
            <option value="">Selecione...</option>
            <?php while ($bat = $resBatalhoes->fetch_assoc()): ?>
              <option value="<?= $bat['id'] ?>">
                <?= htmlspecialchars($bat['abreviatura'] . ' - ' . $bat['nome']) ?>
              </option>
            <?php endwhile; ?>
          </select>

          <label class="form-label">Selecione a planilha (.xlsx)</label>
          <input type="file" name="arquivo" accept=".xlsx" class="form-control mb-3" required>

          <a href="includes/sta/planilha_modelo_sta.xlsx" class="btn btn-link p-0">
            📥 Baixar modelo de planilha
          </a>
        </div>

        <div class="modal-footer">
          <button type="submit" class="btn btn-primary">Importar</button>
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
        </div>
      </form>
    </div>
  </div>
</div>
<script>
    window.funcaoInicializacao = 'inicializarFichas';    
</script>