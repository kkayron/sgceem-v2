<?php
header('Content-Type: text/html; charset=utf-8');
session_start();
require_once '../../conexao/config.php';

$id_usuario_logado = $_SESSION['usuario_id'] ?? 0;

if (!$id_usuario_logado) {
    echo "<div class='alert alert-danger'>Usuário não identificado.</div>";
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
$batalhao = $_GET['batalhao'] ?? '';

$filtros = [];
$params = [];
$tipos = '';

// ============================
// FILTRO FIXO: SOMENTE FICHAS DO USUÁRIO
// ============================
$filtros[] = "f.criador = ?";
$params[] = (int)$id_usuario_logado;
$tipos .= 'i';

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
    if (!empty($_GET[$parametro])) {
        $valor = $_GET[$parametro];

        if (str_contains($coluna, '>=')) {
            $filtros[] = "$coluna ?";
            $params[] = $valor;
            $tipos .= 's';
        } elseif (str_contains($coluna, '<=')) {
            $filtros[] = "$coluna ?";
            $params[] = $valor;
            $tipos .= 's';
        } elseif ($parametro === 'id' || $parametro === 'viatura' || $parametro === 'batalhao') {
            $filtros[] = "$coluna = ?";
            $params[] = (int)$valor;
            $tipos .= 'i';
        } else {
            $filtros[] = "$coluna LIKE ?";
            $params[] = '%' . $valor . '%';
            $tipos .= 's';
        }
    }
}

// ============================
// CONDIÇÃO FINAL
// ============================
$condicoes = 'WHERE ' . implode(' AND ', $filtros);

// ============================
// CONSULTA TOTAL
// ============================
$sqlTotal = "
    SELECT COUNT(*) AS total
    FROM sta_fichas f
    LEFT JOIN frota fr ON f.id_viatura = fr.id
    $condicoes
";

$stmtTotal = $conexao->prepare($sqlTotal);
$stmtTotal->bind_param($tipos, ...$params);
$stmtTotal->execute();
$resultTotal = $stmtTotal->get_result();
$totalRegistros = $resultTotal->fetch_assoc()['total'] ?? 0;
$totalPaginas = ceil($totalRegistros / $limite);

// ============================
// CONSULTA PRINCIPAL
// ============================
$sql = "
    SELECT 
        f.*, 
        om.nome AS nome_om, 
        fr.prefixo_sga AS prefixo_sga_lista
    FROM sta_fichas f
    LEFT JOIN organizacoes_militares om ON f.batalhao = om.id
    LEFT JOIN frota fr ON f.id_viatura = fr.id
    $condicoes
    ORDER BY f.id DESC
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
        <h3 class="fw-bold mb-1">Minhas solicitações de viaturas/equipamentos</h3>
        <h6 class="text-muted">Listagem de suas solicitações de viaturas e equipamentos</h6>
      </div>
        <div>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalCadastroVTR">
          <i class="fa fa-user-plus me-1"></i> Solicitar Ficha de Vtr/Eqp
        </button>
         <!-- Botão para abrir modal -->

      </div>
    </div>
	
<div class="card">
  <div class="card-body">

    <div class="mb-3">
      <button class="btn btn-outline-primary w-100 d-flex justify-content-center align-items-center" onclick="toggleFiltrosFichas()">
        <i class="fas fa-search me-2"></i> Filtros
      </button>
    </div>

    <div id="filtros-container-fichas" style="display: none;" class="mb-3">
      <div class="card shadow-sm border-0">
        <div class="card-body">
          <form method="GET" id="filtroFichaForm">
            <div class="row g-3">

              <div class="col-md-2">
                <label class="form-label fw-semibold">Nº da Ficha</label>
                <input type="text" class="form-control" name="id" value="<?= htmlspecialchars($_GET['id'] ?? '') ?>">
              </div>

              <div class="col-md-2">
                <label class="form-label fw-semibold">Viatura</label>
                <input type="text" class="form-control" name="viatura" value="<?= htmlspecialchars($_GET['viatura'] ?? '') ?>">
              </div>

              <div class="col-md-3">
                <label class="form-label fw-semibold">Solicitante</label>
                <input type="text" class="form-control" name="solicitante" value="<?= htmlspecialchars($_GET['solicitante'] ?? '') ?>">
              </div>

              <div class="col-md-3">
                <label class="form-label fw-semibold">Subunidade</label>
                <input type="text" class="form-control" name="subunidade" value="<?= htmlspecialchars($_GET['subunidade'] ?? '') ?>">
              </div>

              <div class="col-md-2">
                <label class="form-label fw-semibold">Status</label>
                <select name="status" class="form-select">
                  <option value="">Todos</option>
                  <option value="Aberta" <?= ($_GET['status'] ?? '') === 'Aberta' ? 'selected' : '' ?>>Aberta</option>
                  <option value="Encerrada" <?= ($_GET['status'] ?? '') === 'Encerrada' ? 'selected' : '' ?>>Encerrada</option>
                </select>
              </div>

              <div class="col-md-2">
                <label class="form-label fw-semibold">Autorização</label>
                <select name="autorizado" class="form-select">
                  <option value="">Todos</option>
                  <option value="sim" <?= ($_GET['autorizado'] ?? '') === 'sim' ? 'selected' : '' ?>>Autorizada</option>
                  <option value="não" <?= ($_GET['autorizado'] ?? '') === 'não' ? 'selected' : '' ?>>Pendente</option>
                  <option value="negado" <?= ($_GET['autorizado'] ?? '') === 'negado' ? 'selected' : '' ?>>Negada</option>
                </select>
              </div>

              <div class="col-md-2">
                <label class="form-label fw-semibold">Data Inicial</label>
                <input type="date" class="form-control" name="data_ini" value="<?= htmlspecialchars($_GET['data_ini'] ?? '') ?>">
              </div>

              <div class="col-md-2">
                <label class="form-label fw-semibold">Data Final</label>
                <input type="date" class="form-control" name="data_fim" value="<?= htmlspecialchars($_GET['data_fim'] ?? '') ?>">
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
      <?= renderPaginacaoFichas($pagina, $totalPaginas, $limite, $queryString, 'includes/sta_fichas/listagem_solicitacoes.php'); ?>
    </div>

    <?php if ($result->num_rows == 0): ?>
      <div class="alert alert-warning text-center">
        Nenhuma solicitação encontrada.
      </div>
    <?php endif; ?>

    <div class="d-flex flex-column gap-3">
      <?php while ($row = $result->fetch_assoc()): ?>
        <?php
          $prefixo_sga = $row['prefixo_sga_lista'] ?? '—';

          $autorizado = strtolower(trim($row['autorizado'] ?? 'não'));

          if ($autorizado === 'sim') {
              $badgeAutorizado = 'success';
              $textoAutorizado = 'Autorizada';
          } elseif ($autorizado === 'negado') {
              $badgeAutorizado = 'danger';
              $textoAutorizado = 'Não autorizado';
          } else {
              $badgeAutorizado = 'warning';
              $textoAutorizado = 'Pendente';
          }

          $statusBadge = ($row['status'] === 'Aberta') ? 'warning' : 'success';
        ?>

        <div class="ficha-item border rounded shadow-sm p-3 bg-white d-flex flex-wrap align-items-center justify-content-between">

          <div class="flex-grow-1 me-3">
            <div class="d-flex flex-wrap align-items-center mb-1">
              <h6 class="fw-bold text-primary mb-0 me-2">
                Solicitação #<?= (int)$row['id'] ?>
              </h6>
              <small class="text-muted">— <?= htmlspecialchars($prefixo_sga) ?></small>
            </div>

            <div class="d-flex flex-wrap text-muted small">
              <div class="me-3">
                <strong>Data:</strong>
                <?= !empty($row['data_abertura']) ? date('d/m/Y', strtotime($row['data_abertura'])) : '—' ?>
              </div>

              <div class="me-3">
                <strong>Solicitante:</strong>
                <?= htmlspecialchars($row['solicitante'] ?? '—') ?>
              </div>

              <div class="me-3">
                <strong>Subunidade:</strong>
                <?= htmlspecialchars($row['subunidade'] ?? '—') ?>
              </div>

              <div class="me-3">
                <strong>Destino:</strong>
                <?= htmlspecialchars($row['destino'] ?? '—') ?>
              </div>

              <div class="me-3">
                <strong>OM:</strong>
                <?= htmlspecialchars($row['nome_om'] ?? '—') ?>
              </div>
            </div>

            <div class="mt-2">
              <span class="badge bg-<?= $statusBadge ?> me-2">
                <?= htmlspecialchars($row['status'] ?? '—') ?>
              </span>

              <span class="badge bg-<?= $badgeAutorizado ?> me-2">
                <?= $textoAutorizado ?>
              </span>

              <span class="badge bg-secondary">
                <?= htmlspecialchars($row['natureza'] ?? '—') ?>
              </span>
            </div>
          </div>

          <div class="d-flex flex-wrap gap-2 mt-2 mt-md-0">


            <?php if ($autorizado !== 'sim' && $autorizado !== 'negado'): ?>
              <button class="btn btn-sm btn-outline-warning d-flex align-items-center"
                      onclick="editarFICHA(<?= (int)$row['id'] ?>)"
                      data-bs-toggle="modal"
                      data-bs-target="#modalEditarFICHA">
                <i class="fas fa-edit me-1"></i> Editar
              </button>

<button type="button"
        class="btn btn-sm btn-outline-danger d-flex align-items-center btn-deletar-solicitacao-ficha"
        data-id="<?= (int)$row['id'] ?>"
        onclick="deletarSolicitacaoFicha(this)"
        title="Remover Solicitação">
  <i class="fa fa-times"></i>
</button>
            <?php endif; ?>

            <?php if ($autorizado === 'sim'): ?>
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
            <?php endif; ?>

          </div>
        </div>
      <?php endwhile; ?>
    </div>

    <div class="paginacao mt-3">
      <?= renderPaginacaoFichas($pagina, $totalPaginas, $limite, $queryString, 'includes/sta_fichas/listagem_solicitacoes.php'); ?>
    </div>

  </div>
</div>
</div>

<style>
  .ficha-item {
    transition: all 0.2s ease-in-out;
  }

  .ficha-item:hover {
    background-color: #f8f9fa;
    transform: translateY(-2px);
  }

  @media (max-width: 768px) {
    .ficha-item {
      flex-direction: column;
      align-items: flex-start;
    }

    .ficha-item .d-flex.flex-wrap.gap-2 {
      justify-content: flex-start !important;
    }
  }
</style>
	

<!-- Modal de Cadastro da Ficha STA -->
<div class="modal fade" id="modalCadastroVTR" tabindex="-1" aria-labelledby="modalCadastroVTRLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <form method="POST" enctype="multipart/form-data" id="form-ficha-sta">
        <div class="modal-header">
          <h5 class="modal-title" id="modalCadastroVTRLabel">Solicitação de Ficha de Vtr/Eqp</h5>
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
          </div>
        </div>
        <div class="modal-footer justify-content-between">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-success">Solicitar Vtr/Eqp</button>
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
	
	

<script>
    window.funcaoInicializacao = 'inicializarSolicitacaoFichas';    
</script>	