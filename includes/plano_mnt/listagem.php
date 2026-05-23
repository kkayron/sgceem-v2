<?php
header('Content-Type: text/html; charset=utf-8');
session_start();
require_once '../../conexao/config.php';

// ============================
// FILTROS VIA GET
// ============================
$id = $_GET['id'] ?? '';
$id_marca = $_GET['id_marca'] ?? '';
$id_modelo = $_GET['id_modelo'] ?? '';
$descricao = $_GET['descricao'] ?? '';
$tipo_controle = $_GET['tipo_controle'] ?? '';
$ativo = $_GET['ativo'] ?? '';

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
if (!empty($id)) {
    $filtros[] = "mp.id = ?";
    $params[] = (int)$id;
    $tipos .= 'i';
}

if (!empty($id_marca)) {
    $filtros[] = "mp.id_marca = ?";
    $params[] = (int)$id_marca;
    $tipos .= 'i';
}

if (!empty($id_modelo)) {
    $filtros[] = "mp.id_modelo = ?";
    $params[] = (int)$id_modelo;
    $tipos .= 'i';
}

if (!empty($descricao)) {
    $filtros[] = "mp.descricao LIKE ?";
    $params[] = '%' . $descricao . '%';
    $tipos .= 's';
}

if (!empty($tipo_controle)) {
    $filtros[] = "mp.tipo_controle = ?";
    $params[] = $tipo_controle;
    $tipos .= 's';
}

if ($ativo !== '') {
    $filtros[] = "mp.ativo = ?";
    $params[] = (int)$ativo;
    $tipos .= 'i';
}

$condicoes = !empty($filtros) ? 'WHERE ' . implode(' AND ', $filtros) : '';

// ============================
// CONSULTA TOTAL
// ============================
$sqlTotal = "
    SELECT COUNT(*) AS total
    FROM mnt_planos mp
    INNER JOIN config_marcas cm ON cm.id = mp.id_marca
    INNER JOIN config_modelos cmo ON cmo.id = mp.id_modelo
    $condicoes
";

$stmtTotal = $conexao->prepare($sqlTotal);
if (!empty($params)) {
    $stmtTotal->bind_param($tipos, ...$params);
}
$stmtTotal->execute();
$resultTotal = $stmtTotal->get_result();
$totalRegistros = (int)($resultTotal->fetch_assoc()['total'] ?? 0);
$totalPaginas = ceil($totalRegistros / $limite);

// ============================
// CONSULTA PRINCIPAL
// ============================
$sql = "
    SELECT 
        mp.*,
        cm.marca,
        cmo.nome_modelo
    FROM mnt_planos mp
    INNER JOIN config_marcas cm ON cm.id = mp.id_marca
    INNER JOIN config_modelos cmo ON cmo.id = mp.id_modelo
    $condicoes
    ORDER BY cm.marca ASC, cmo.nome_modelo ASC, mp.descricao ASC
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
// MARCAS PARA FILTRO
// ============================
$marcas = [];
$resMarcas = $conexao->query("SELECT id, marca FROM config_marcas ORDER BY marca ASC");
while ($m = $resMarcas->fetch_assoc()) {
    $marcas[] = $m;
}

// ============================
// MODELOS PARA FILTRO
// ============================
$modelos = [];
$sqlModelos = "
    SELECT 
        mo.id,
        mo.nome_modelo,
        ma.marca
    FROM config_modelos mo
    INNER JOIN config_marcas ma ON ma.id = mo.id_marca
    ORDER BY ma.marca ASC, mo.nome_modelo ASC
";
$resModelos = $conexao->query($sqlModelos);
while ($mo = $resModelos->fetch_assoc()) {
    $modelos[] = $mo;
}

// ============================
// PAGINAÇÃO
// ============================
function renderPaginacaoPlanosMnt($pagina, $totalPaginas, $limite, $queryString, $arquivo = 'includes/manutencao_planos/listagem.php') {
    if ($totalPaginas <= 1) return '';

    $qs = trim($queryString);
    $qsPrefix = ($qs !== '') ? ($qs . '&') : '';

    $html  = '<div class="pagination-wrapper">';
    $html .= '<nav><ul class="pagination pagination-sm justify-content-center">';

    if ($pagina > 1) {
        $url = "{$arquivo}?{$qsPrefix}pagina=1&limite={$limite}";
        $html .= "<li class='page-item'><a class='page-link paginacao-planos-mnt' href='#' data-page='{$url}'>&laquo;</a></li>";

        $url = "{$arquivo}?{$qsPrefix}pagina=" . ($pagina - 1) . "&limite={$limite}";
        $html .= "<li class='page-item'><a class='page-link paginacao-planos-mnt' href='#' data-page='{$url}'>&lsaquo;</a></li>";
    }

    $inicio = max(1, $pagina - 4);
    $fim = min($totalPaginas, $pagina + 4);

    $janela = 9;
    $qtdAtual = $fim - $inicio + 1;

    if ($qtdAtual < $janela) {
        $faltam = $janela - $qtdAtual;
        $inicio = max(1, $inicio - $faltam);
        $fim = min($totalPaginas, $inicio + $janela - 1);
        $inicio = max(1, $fim - $janela + 1);
    }

    if ($inicio > 1) {
        $html .= "<li class='page-item disabled'><span class='page-link'>...</span></li>";
    }

    for ($i = $inicio; $i <= $fim; $i++) {
        $ativoClasse = ($i == $pagina) ? 'active' : '';
        $url = "{$arquivo}?{$qsPrefix}pagina={$i}&limite={$limite}";
        $html .= "<li class='page-item {$ativoClasse}'><a class='page-link paginacao-planos-mnt' href='#' data-page='{$url}'>{$i}</a></li>";
    }

    if ($fim < $totalPaginas) {
        $html .= "<li class='page-item disabled'><span class='page-link'>...</span></li>";
    }

    if ($pagina < $totalPaginas) {
        $url = "{$arquivo}?{$qsPrefix}pagina=" . ($pagina + 1) . "&limite={$limite}";
        $html .= "<li class='page-item'><a class='page-link paginacao-planos-mnt' href='#' data-page='{$url}'>&rsaquo;</a></li>";

        $url = "{$arquivo}?{$qsPrefix}pagina={$totalPaginas}&limite={$limite}";
        $html .= "<li class='page-item'><a class='page-link paginacao-planos-mnt' href='#' data-page='{$url}'>&raquo;</a></li>";
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
        <h3 class="fw-bold mb-1">Planos de Manutenção Agendada</h3>
        <h6 class="text-muted">Listagem dos planos preventivos cadastrados por marca e modelo</h6>
      </div>

      <div>
		  <button type="button" class="btn btn-success btn-sm"
  onclick="(function(){
    var f = document.getElementById('filtroPlanoMntForm');
    if (!f) {
      alert('Formulário de filtros não encontrado.');
      return;
    }

    var params = new URLSearchParams(new FormData(f));
    window.open('excel/exportar_planos_mnt.php?' + params.toString(), '_blank', 'noopener');
  })();">
  <i class="fas fa-file-excel me-1"></i> Exportar Planos
</button>
		  <button type="button" class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#modalImportarPlanoMnt">
  <i class="fas fa-file-excel me-1"></i> Importar Planos
</button>
        <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalCadastroPlanoMnt">
          <i class="fa fa-plus me-1"></i> Novo Plano
        </button>
      </div>
    </div>

    <div class="card">
      <div class="card-body">

        <div class="mb-3">
          <button class="btn btn-outline-primary w-100 d-flex justify-content-center align-items-center" onclick="toggleFiltrosPlanosMnt()">
            <i class="fas fa-search me-2"></i> Filtros
          </button>
        </div>

        <div id="filtros-container-planos-mnt" style="display: none;" class="mb-3">
          <div class="card shadow-sm border-0">
            <div class="card-body">
              <form method="GET" id="filtroPlanoMntForm">
                <div class="row g-3">

                  <div class="col-md-2">
                    <label class="form-label fw-semibold">ID</label>
                    <input type="text" class="form-control" name="id" value="<?= htmlspecialchars($id) ?>">
                  </div>

                  <div class="col-md-3">
                    <label class="form-label fw-semibold">Marca</label>
                    <select name="id_marca" class="form-select">
                      <option value="">Todas</option>
                      <?php foreach ($marcas as $marcaItem): ?>
                        <option value="<?= (int)$marcaItem['id'] ?>" <?= ($id_marca == $marcaItem['id']) ? 'selected' : '' ?>>
                          <?= htmlspecialchars($marcaItem['marca']) ?>
                        </option>
                      <?php endforeach; ?>
                    </select>
                  </div>

                  <div class="col-md-3">
                    <label class="form-label fw-semibold">Modelo</label>
                    <select name="id_modelo" class="form-select">
                      <option value="">Todos</option>
                      <?php foreach ($modelos as $modeloItem): ?>
                        <option value="<?= (int)$modeloItem['id'] ?>" <?= ($id_modelo == $modeloItem['id']) ? 'selected' : '' ?>>
                          <?= htmlspecialchars($modeloItem['marca'] . ' - ' . $modeloItem['nome_modelo']) ?>
                        </option>
                      <?php endforeach; ?>
                    </select>
                  </div>

                  <div class="col-md-4">
                    <label class="form-label fw-semibold">Descrição</label>
                    <input type="text" class="form-control" name="descricao" value="<?= htmlspecialchars($descricao) ?>">
                  </div>

                  <div class="col-md-3">
                    <label class="form-label fw-semibold">Tipo de Controle</label>
                    <select name="tipo_controle" class="form-select">
                      <option value="">Todos</option>
                      <option value="odometro" <?= ($tipo_controle === 'odometro') ? 'selected' : '' ?>>Odômetro</option>
                      <option value="horimetro" <?= ($tipo_controle === 'horimetro') ? 'selected' : '' ?>>Horímetro</option>
                      <option value="tempo" <?= ($tipo_controle === 'tempo') ? 'selected' : '' ?>>Tempo</option>
                      <option value="odometro_tempo" <?= ($tipo_controle === 'odometro_tempo') ? 'selected' : '' ?>>Odômetro + Tempo</option>
                      <option value="horimetro_tempo" <?= ($tipo_controle === 'horimetro_tempo') ? 'selected' : '' ?>>Horímetro + Tempo</option>
                    </select>
                  </div>

                  <div class="col-md-2">
                    <label class="form-label fw-semibold">Situação</label>
                    <select name="ativo" class="form-select">
                      <option value="">Todos</option>
                      <option value="1" <?= ($ativo === '1') ? 'selected' : '' ?>>Ativo</option>
                      <option value="0" <?= ($ativo === '0') ? 'selected' : '' ?>>Inativo</option>
                    </select>
                  </div>

                  <div class="col-12 d-flex justify-content-between mt-2">
                    <button type="button" id="btnLimparFiltrosPlanosMnt" class="btn btn-black ms-2">Limpar Filtros</button>
                    <button type="submit" class="btn btn-primary px-4">Aplicar</button>
                  </div>

                </div>
              </form>
            </div>
          </div>
        </div>

        <div class="mb-3">
          <label for="limitePlanosMnt" class="me-2 mb-0">Mostrar</label>
          <select id="limitePlanosMnt" name="limite" class="form-select d-inline w-auto">
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
          <?= renderPaginacaoPlanosMnt($pagina, $totalPaginas, $limite, $queryString); ?>
        </div>

        <div class="lista-planos-mnt">
          <?php if ($result->num_rows > 0): ?>
            <?php while ($row = $result->fetch_assoc()): ?>
              <?php
                $ativoPlano = (int)$row['ativo'] === 1;

                $classeAtivo = $ativoPlano ? 'success' : 'secondary';
                $textoAtivo = $ativoPlano ? 'Ativo' : 'Inativo';

                $tipoLabel = match ($row['tipo_controle']) {
                    'odometro' => 'Odômetro',
                    'horimetro' => 'Horímetro',
                    'tempo' => 'Tempo',
                    'odometro_tempo' => 'Odômetro + Tempo',
                    'horimetro_tempo' => 'Horímetro + Tempo',
                    default => '—'
                };

                $tipoBadge = match ($row['tipo_controle']) {
                    'odometro' => 'primary',
                    'horimetro' => 'info',
                    'tempo' => 'warning',
                    'odometro_tempo' => 'dark',
                    'horimetro_tempo' => 'dark',
                    default => 'secondary'
                };

                $valorInicial = $row['valor_inicial'] !== null ? number_format((float)$row['valor_inicial'], 2, ',', '.') : '—';
                $intervaloValor = $row['intervalo_valor'] !== null ? number_format((float)$row['intervalo_valor'], 2, ',', '.') : '—';
                $intervaloDias = !empty($row['intervalo_dias']) ? (int)$row['intervalo_dias'] . ' dias' : '—';
                $alertaValor = $row['alerta_antes_valor'] !== null ? number_format((float)$row['alerta_antes_valor'], 2, ',', '.') : '—';
                $alertaDias = !empty($row['alerta_antes_dias']) ? (int)$row['alerta_antes_dias'] . ' dias' : '—';
              ?>

              <div class="plano-mnt-item <?= !$ativoPlano ? 'plano-mnt-inativo' : '' ?>">
                <div class="plano-mnt-conteudo">
                  <div class="plano-mnt-topo">
                    <div>
                      <h6 class="plano-mnt-titulo">
                        Plano #<?= (int)$row['id'] ?> - <?= htmlspecialchars($row['descricao']) ?>
                      </h6>

                      <div class="plano-mnt-subtitulo">
                        <?= htmlspecialchars($row['marca']) ?> / <?= htmlspecialchars($row['nome_modelo']) ?>
                      </div>
                    </div>

                    <div class="plano-mnt-badges">
                      <span class="badge bg-<?= $tipoBadge ?>">
                        <?= htmlspecialchars($tipoLabel) ?>
                      </span>

                      <span class="badge bg-<?= $classeAtivo ?>">
                        <?= $textoAtivo ?>
                      </span>
                    </div>
                  </div>

                  <div class="plano-mnt-dados">
                    <div>
                      <small>Valor Inicial</small>
                      <strong><?= $valorInicial ?></strong>
                    </div>

                    <div>
                      <small>Intervalo Valor</small>
                      <strong><?= $intervaloValor ?></strong>
                    </div>

                    <div>
                      <small>Intervalo Tempo</small>
                      <strong><?= $intervaloDias ?></strong>
                    </div>

                    <div>
                      <small>Alerta Valor</small>
                      <strong><?= $alertaValor ?></strong>
                    </div>

                    <div>
                      <small>Alerta Tempo</small>
                      <strong><?= $alertaDias ?></strong>
                    </div>
                  </div>
                </div>

                <div class="plano-mnt-acoes">
                  <button class="btn btn-sm btn-outline-primary d-flex align-items-center"
                          onclick="verPlanoMnt(<?= (int)$row['id'] ?>)"
                          data-bs-toggle="modal"
                          data-bs-target="#modalVerPlanoMnt">
                    <i class="fas fa-eye me-1"></i> Ver
                  </button>

                  <button class="btn btn-sm btn-outline-warning d-flex align-items-center"
                          onclick="editarPlanoMnt(<?= (int)$row['id'] ?>)"
                          data-bs-toggle="modal"
                          data-bs-target="#modalEditarPlanoMnt">
                    <i class="fas fa-edit me-1"></i> Editar
                  </button>

                  <button type="button"
                          class="btn btn-sm btn-outline-danger d-flex align-items-center btn-deletar-plano-mnt"
                          data-id="<?= (int)$row['id'] ?>"
                          onclick="deletarPlanoMnt(this)"
                          title="Remover Plano">
                    <i class="fa fa-times"></i>
                  </button>
                </div>
              </div>
            <?php endwhile; ?>
          <?php else: ?>
            <div class="alert alert-info mb-0">
              Nenhum plano de manutenção encontrado.
            </div>
          <?php endif; ?>
        </div>

        <div class="paginacao mt-3">
          <?= renderPaginacaoPlanosMnt($pagina, $totalPaginas, $limite, $queryString); ?>
        </div>

      </div>
    </div>
  </div>
</div>

<style>
  .lista-planos-mnt {
    display: flex;
    flex-direction: column;
    gap: .85rem;
  }

  .plano-mnt-item {
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto;
    align-items: center;
    gap: 1rem;
    padding: 1rem;
    background: #fff;
    border: 1px solid #e9ecef;
    border-radius: 1rem;
    box-shadow: 0 .25rem .75rem rgba(0,0,0,.04);
    transition: all .2s ease-in-out;
  }

  .plano-mnt-item:hover {
    background-color: #fbfbfc;
    transform: translateY(-2px);
    box-shadow: 0 .5rem 1.2rem rgba(0,0,0,.07);
  }

  .plano-mnt-inativo {
    background: #f8f9fa;
    opacity: .78;
  }

  .plano-mnt-conteudo {
    min-width: 0;
  }

  .plano-mnt-topo {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: .75rem;
    margin-bottom: .6rem;
  }

  .plano-mnt-titulo {
    margin: 0;
    font-weight: 700;
    color: #0d6efd;
  }

  .plano-mnt-subtitulo {
    margin-top: .2rem;
    font-size: .82rem;
    color: #6c757d;
  }

  .plano-mnt-badges {
    display: flex;
    flex-wrap: wrap;
    justify-content: flex-end;
    gap: .35rem;
  }

  .plano-mnt-dados {
    display: grid;
    grid-template-columns: repeat(5, minmax(110px, 1fr));
    gap: .6rem;
  }

  .plano-mnt-dados small {
    display: block;
    color: #6c757d;
    font-size: .72rem;
    text-transform: uppercase;
    letter-spacing: .03em;
    margin-bottom: .15rem;
  }

  .plano-mnt-dados strong {
    display: block;
    color: #212529;
    font-size: .88rem;
    font-weight: 600;
    line-height: 1.25;
    word-break: break-word;
  }

  .plano-mnt-acoes {
    display: grid;
    grid-template-columns: repeat(3, auto);
    gap: .45rem;
    justify-content: end;
    align-items: center;
  }

  .plano-mnt-acoes .btn {
    min-height: 31px;
    justify-content: center;
    white-space: nowrap;
  }

  .plano-mnt-acoes .btn-deletar-plano-mnt {
    width: 36px;
    padding-left: .5rem;
    padding-right: .5rem;
  }

  @media (max-width: 1200px) {
    .plano-mnt-item {
      grid-template-columns: 1fr;
    }

    .plano-mnt-acoes {
      grid-template-columns: repeat(3, auto);
      justify-content: start;
      margin-top: .25rem;
    }

    .plano-mnt-dados {
      grid-template-columns: repeat(3, minmax(110px, 1fr));
    }
  }

  @media (max-width: 768px) {
    .plano-mnt-topo {
      flex-direction: column;
      align-items: flex-start;
    }

    .plano-mnt-badges {
      justify-content: flex-start;
    }

    .plano-mnt-dados {
      grid-template-columns: 1fr 1fr;
    }

    .plano-mnt-acoes {
      width: 100%;
      grid-template-columns: 1fr 1fr 1fr;
      justify-content: stretch;
    }

    .plano-mnt-acoes .btn {
      width: 100%;
    }

    .plano-mnt-acoes .btn-deletar-plano-mnt {
      width: 100%;
    }
  }

  @media (max-width: 480px) {
    .plano-mnt-dados {
      grid-template-columns: 1fr;
    }

    .plano-mnt-acoes {
      grid-template-columns: 1fr;
    }
  }
</style>

<!-- Modal de Cadastro do Plano de Manutenção -->
<div class="modal fade" id="modalCadastroPlanoMnt" tabindex="-1" aria-labelledby="modalCadastroPlanoMntLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <form method="POST" id="form-plano-mnt">
        <div class="modal-header">
          <h5 class="modal-title" id="modalCadastroPlanoMntLabel">Cadastrar Plano de Manutenção</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
        </div>

        <div class="modal-body">
          <div class="row g-3">

            <?php
            $marcasMnt = [];
            $resMarcasMnt = $conexao->query("SELECT id, marca FROM config_marcas ORDER BY marca ASC");
            while ($m = $resMarcasMnt->fetch_assoc()) {
                $marcasMnt[] = $m;
            }

            $modelosMnt = [];
            $sqlModelosMnt = "
                SELECT id, id_marca, nome_modelo
                FROM config_modelos
                ORDER BY nome_modelo ASC
            ";
            $resModelosMnt = $conexao->query($sqlModelosMnt);
            while ($mo = $resModelosMnt->fetch_assoc()) {
                $modelosMnt[] = $mo;
            }
            ?>

            <div class="col-md-6">
              <label class="form-label">Marca</label>
              <select name="id_marca" id="mnt_id_marca" class="form-select" required>
                <option value="">Selecione...</option>
                <?php foreach ($marcasMnt as $marca): ?>
                  <option value="<?= (int)$marca['id'] ?>">
                    <?= htmlspecialchars($marca['marca']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="col-md-6">
              <label class="form-label">Modelo</label>
              <select name="id_modelo" id="mnt_id_modelo" class="form-select" required>
                <option value="">Selecione a marca primeiro...</option>
                <?php foreach ($modelosMnt as $modelo): ?>
                  <option value="<?= (int)$modelo['id'] ?>" data-marca="<?= (int)$modelo['id_marca'] ?>">
                    <?= htmlspecialchars($modelo['nome_modelo']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="col-md-12">
              <label class="form-label">Descrição da Manutenção</label>
              <input type="text" name="descricao" class="form-control" required placeholder="Ex: Troca de óleo do motor">
            </div>

            <div class="col-md-6">
              <label class="form-label">Tipo de Controle</label>
              <select name="tipo_controle" id="mnt_tipo_controle" class="form-select" required>
                <option value="">Selecione...</option>
                <option value="odometro">Odômetro</option>
                <option value="horimetro">Horímetro</option>
                <option value="tempo">Tempo</option>
                <option value="odometro_tempo">Odômetro + Tempo</option>
                <option value="horimetro_tempo">Horímetro + Tempo</option>
              </select>
            </div>

            <div class="col-md-6">
              <label class="form-label">Situação</label>
              <select name="ativo" class="form-select" required>
                <option value="1" selected>Ativo</option>
                <option value="0">Inativo</option>
              </select>
            </div>

            <div class="col-12">
              <div class="p-3 border rounded bg-light">
                <h6 class="fw-bold text-primary mb-3">
                  <i class="fas fa-gauge-high me-1"></i> Controle por Odômetro/Horímetro
                </h6>

                <div class="row g-3">
                  <div class="col-md-4">
                    <label class="form-label">Valor Inicial</label>
                    <input type="number" name="valor_inicial" class="form-control campo-valor-mnt" step="0.01" min="0" placeholder="Ex: 250">
                  </div>

                  <div class="col-md-4">
                    <label class="form-label">Intervalo</label>
                    <input type="number" name="intervalo_valor" class="form-control campo-valor-mnt" step="0.01" min="0" placeholder="Ex: 250">
                  </div>

                  <div class="col-md-4">
                    <label class="form-label">Alertar Antes</label>
                    <input type="number" name="alerta_antes_valor" class="form-control campo-valor-mnt" step="0.01" min="0" placeholder="Ex: 50">
                  </div>
                </div>
              </div>
            </div>

            <div class="col-12">
              <div class="p-3 border rounded bg-light">
                <h6 class="fw-bold text-warning mb-3">
                  <i class="fas fa-calendar-days me-1"></i> Controle por Tempo
                </h6>

                <div class="row g-3">
                  <div class="col-md-6">
                    <label class="form-label">Intervalo em Dias</label>
                    <input type="number" name="intervalo_dias" class="form-control campo-tempo-mnt" min="0" placeholder="Ex: 180">
                  </div>

                  <div class="col-md-6">
                    <label class="form-label">Alertar Antes em Dias</label>
                    <input type="number" name="alerta_antes_dias" class="form-control campo-tempo-mnt" min="0" placeholder="Ex: 15">
                  </div>
                </div>
              </div>
            </div>

            <div class="col-12">
              <div class="alert alert-info mb-0">
                <strong>Observação:</strong> o plano será aplicado automaticamente a todos os ativos da frota que possuam a marca e o modelo selecionados.
              </div>
            </div>

          </div>
        </div>

        <div class="modal-footer justify-content-between">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-success">
            <i class="fas fa-save me-1"></i> Cadastrar Plano
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Modal de Edição do Plano de Manutenção -->
<div class="modal fade" id="modalEditarPlanoMnt" tabindex="-1" aria-labelledby="modalEditarPlanoMntLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <form method="POST" id="form-editar-plano-mnt">
        <input type="hidden" name="id" id="edit_mnt_id">

        <div class="modal-header">
          <h5 class="modal-title" id="modalEditarPlanoMntLabel">Editar Plano de Manutenção</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
        </div>

        <div class="modal-body">
          <div class="row g-3">

            <div class="col-md-6">
              <label class="form-label">Marca</label>
              <select name="id_marca" id="edit_mnt_id_marca" class="form-select" required>
                <option value="">Selecione...</option>
                <?php foreach ($marcasMnt as $marca): ?>
                  <option value="<?= (int)$marca['id'] ?>">
                    <?= htmlspecialchars($marca['marca']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="col-md-6">
              <label class="form-label">Modelo</label>
              <select name="id_modelo" id="edit_mnt_id_modelo" class="form-select" required disabled>
                <option value="">Selecione a marca primeiro...</option>
              </select>
            </div>

            <div class="col-md-12">
              <label class="form-label">Descrição da Manutenção</label>
              <input type="text" name="descricao" id="edit_mnt_descricao" class="form-control" required>
            </div>

            <div class="col-md-6">
              <label class="form-label">Tipo de Controle</label>
              <select name="tipo_controle" id="edit_mnt_tipo_controle" class="form-select" required>
                <option value="">Selecione...</option>
                <option value="odometro">Odômetro</option>
                <option value="horimetro">Horímetro</option>
                <option value="tempo">Tempo</option>
                <option value="odometro_tempo">Odômetro + Tempo</option>
                <option value="horimetro_tempo">Horímetro + Tempo</option>
              </select>
            </div>

            <div class="col-md-6">
              <label class="form-label">Situação</label>
              <select name="ativo" id="edit_mnt_ativo" class="form-select" required>
                <option value="1">Ativo</option>
                <option value="0">Inativo</option>
              </select>
            </div>

            <div class="col-12">
              <div class="p-3 border rounded bg-light">
                <h6 class="fw-bold text-primary mb-3">
                  <i class="fas fa-gauge-high me-1"></i> Controle por Odômetro/Horímetro
                </h6>

                <div class="row g-3">
                  <div class="col-md-4">
                    <label class="form-label">Valor Inicial</label>
                    <input type="number" name="valor_inicial" id="edit_mnt_valor_inicial" class="form-control campo-edit-valor-mnt" step="0.01" min="0">
                  </div>

                  <div class="col-md-4">
                    <label class="form-label">Intervalo</label>
                    <input type="number" name="intervalo_valor" id="edit_mnt_intervalo_valor" class="form-control campo-edit-valor-mnt" step="0.01" min="0">
                  </div>

                  <div class="col-md-4">
                    <label class="form-label">Alertar Antes</label>
                    <input type="number" name="alerta_antes_valor" id="edit_mnt_alerta_antes_valor" class="form-control campo-edit-valor-mnt" step="0.01" min="0">
                  </div>
                </div>
              </div>
            </div>

            <div class="col-12">
              <div class="p-3 border rounded bg-light">
                <h6 class="fw-bold text-warning mb-3">
                  <i class="fas fa-calendar-days me-1"></i> Controle por Tempo
                </h6>

                <div class="row g-3">
                  <div class="col-md-6">
                    <label class="form-label">Intervalo em Dias</label>
                    <input type="number" name="intervalo_dias" id="edit_mnt_intervalo_dias" class="form-control campo-edit-tempo-mnt" min="0">
                  </div>

                  <div class="col-md-6">
                    <label class="form-label">Alertar Antes em Dias</label>
                    <input type="number" name="alerta_antes_dias" id="edit_mnt_alerta_antes_dias" class="form-control campo-edit-tempo-mnt" min="0">
                  </div>
                </div>
              </div>
            </div>

            <div class="col-12">
              <div class="alert alert-warning mb-0">
                <strong>Atenção:</strong> ao alterar o intervalo deste plano, os avisos serão recalculados com base na última manutenção realizada de cada ativo.
              </div>
            </div>

          </div>
        </div>

        <div class="modal-footer justify-content-between">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-success">
            <i class="fas fa-save me-1"></i> Salvar Alterações
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Modal Ver Plano de Manutenção -->
<div class="modal fade" id="modalVerPlanoMnt" tabindex="-1" aria-labelledby="modalVerPlanoMntLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content">

      <div class="modal-header">
        <h5 class="modal-title" id="modalVerPlanoMntLabel">
          Detalhes do Plano de Manutenção
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
      </div>

      <div class="modal-body">
        <div id="conteudo-ver-plano-mnt">
          <div class="text-center py-5">
            <div class="spinner-border text-primary" role="status"></div>
            <p class="mt-3 text-muted">Carregando dados do plano...</p>
          </div>
        </div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
          Fechar
        </button>
      </div>

    </div>
  </div>
</div>

<div class="modal fade" id="modalImportarPlanoMnt" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form id="formImportarPlanoMnt" method="POST" enctype="multipart/form-data">
        <div class="modal-header">
          <h5 class="modal-title">Importar Planilha de Planos de Manutenção</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>

        <div class="modal-body">
          <div class="alert alert-info">
            A planilha deve conter as colunas:
            <br>
            <strong>Marca, Modelo, Descrição, Tipo Controle, Valor Inicial, Intervalo Valor, Intervalo Dias, Alerta Valor, Alerta Dias, Ativo</strong>
          </div>

          <label class="form-label">Selecione a planilha (.xlsx)</label>
          <input type="file" name="arquivo" id="arquivoPlanoMnt" accept=".xlsx" class="form-control mb-3" required>

          <a href="includes/plano_mnt/planilha_modelo_planos_mnt.xlsx" class="btn btn-link p-0">
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
    window.funcaoInicializacao = 'inicializarPlanoMnt';    
	window.modelosPlanoMnt = <?= json_encode($modelosMnt, JSON_UNESCAPED_UNICODE) ?>;
</script>