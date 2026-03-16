<?php
header('Content-Type: text/html; charset=utf-8');
session_start();
include_once('../../conexao/config.php');

// =============================
// DADOS DO USUÁRIO
// =============================
$usuario = $_SESSION['usuario'] ?? [];
$nivel_usuario = $usuario['nivel'] ?? 3;
$batalhao_usuario = $usuario['batalhao'] ?? null;

if (!$batalhao_usuario) {
    die("Erro: Batalhão do usuário não identificado.");
}

// =============================
// BUSCA CONTAGEM DE ITENS (DEIXEI IGUAL AO SEU CÓDIGO ORIGINAL)
// =============================
$stmt = $conexao->prepare("SELECT COUNT(*) AS total_itens FROM fin_pregao_itens WHERE id_pregao = ?");
$stmt->bind_param("i", $pregao['id']);
$stmt->execute();
$res = $stmt->get_result();
$contagem = $res->fetch_assoc();
$total_itens = $contagem['total_itens'] ?? 0;
$stmt->close();

// =============================
// BUSCA TODOS OS FORNECEDORES (MANTEVE IGUAL AO ORIGINAL)
// =============================
$fornecedores = [];
$sql = "SELECT id, nome_empresa, cnpj_empresa FROM fin_fornecedores ORDER BY nome_empresa ASC";
$result = $conexao->query($sql);

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $fornecedores[] = $row;
    }
}

// =============================
// FILTROS VIA GET
// =============================
$id = $_GET['id'] ?? '';
$nmr_pregao = $_GET['nmr_pregao'] ?? '';
$ano_pregao = $_GET['ano_pregao'] ?? '';
$ug_licitacao = $_GET['ug_licitacao'] ?? '';
$uasg_licitacao = $_GET['uasg_licitacao'] ?? '';
$tipo_pregao = $_GET['tipo_pregao'] ?? '';
$data_ini = $_GET['data_ini'] ?? '';
$data_fim = $_GET['data_fim'] ?? '';
$batalhao_filtro = $_GET['batalhao'] ?? '';

// =============================
// FILTROS BASE
// =============================
$filtros = [];
$params = [];
$tipos = '';

$campos = [
    'id' => 'id',
    'nmr_pregao' => 'nmr_pregao',
    'ano_pregao' => 'ano_pregao',
    'ug_licitacao' => 'ug_licitacao',
    'uasg_licitacao' => 'uasg_licitacao',
    'tipo_pregao' => 'tipo_pregao',
    'data_homologacao >=' => 'data_ini',
    'data_homologacao <=' => 'data_fim'
];

foreach ($campos as $coluna => $parametro) {
    if (!empty($_GET[$parametro])) {
        $valor = $_GET[$parametro];

        if ($coluna === 'id') {
            $filtros[] = "$coluna = ?";
            $params[] = (int)$valor;
            $tipos .= 'i';

        } elseif (str_contains($coluna, '>=')) {
            $filtros[] = "data_homologacao >= ?";
            $params[] = $valor;
            $tipos .= 's';

        } elseif (str_contains($coluna, '<=')) {
            $filtros[] = "data_homologacao <= ?";
            $params[] = $valor;
            $tipos .= 's';

        } else {
            $filtros[] = "$coluna LIKE ?";
            $params[] = '%' . $valor . '%';
            $tipos .= 's';
        }
    }
}

// =============================
// CONTROLE DE ACESSO POR BATALHÃO (MESMA LÓGICA DOS FORNECEDORES)
// =============================
if ($nivel_usuario == 1) {

    // Admin vê tudo, mas se escolher batalhão filtra
    if (!empty($batalhao_filtro)) {
        $filtros[] = "batalhao = ?";
        $params[] = (int)$batalhao_filtro;
        $tipos .= 'i';
    }

} elseif ($nivel_usuario == 2) {

    // Busca os subordinados do usuário
    $sqlSubs = "SELECT id_om_menor FROM organizacoes_militares_sub WHERE id_om_maior = ?";
    $stmtSubs = $conexao->prepare($sqlSubs);
    $stmtSubs->bind_param("i", $batalhao_usuario);
    $stmtSubs->execute();
    $resultSubs = $stmtSubs->get_result();

    $batalhoesPermitidos = [$batalhao_usuario];
    while ($row = $resultSubs->fetch_assoc()) {
        $batalhoesPermitidos[] = $row['id_om_menor'];
    }

    if (!empty($batalhao_filtro)) {
        if (in_array($batalhao_filtro, $batalhoesPermitidos)) {
            $filtros[] = "batalhao = ?";
            $params[] = (int)$batalhao_filtro;
            $tipos .= 'i';
        } else {
            die("Acesso negado ao batalhão selecionado.");
        }
    } else {
        // Gera IN dinâmico
        $placeholders = implode(',', array_fill(0, count($batalhoesPermitidos), '?'));
        $filtros[] = "batalhao IN ($placeholders)";
        $params = array_merge($params, $batalhoesPermitidos);
        $tipos .= str_repeat('i', count($batalhoesPermitidos));
    }

} else {

    // Nível 3 vê somente o batalhão próprio
    $filtros[] = "batalhao = ?";
    $params[] = $batalhao_usuario;
    $tipos .= 'i';
}

// =============================
// WHERE FINAL
// =============================
$condicoes = !empty($filtros) ? 'WHERE ' . implode(' AND ', $filtros) : '';

// =============================
// PAGINAÇÃO (mantida igual)
// =============================
$limite = isset($_GET['limite']) && is_numeric($_GET['limite']) ? (int)$_GET['limite'] : 10;
$pagina = isset($_GET['pagina']) && is_numeric($_GET['pagina']) && $_GET['pagina'] > 0 ? (int)$_GET['pagina'] : 1;
$offset = ($pagina - 1) * $limite;

// =============================
// CONTAGEM TOTAL
// =============================
$sqlTotal = "SELECT COUNT(*) AS total FROM fin_pregao $condicoes";

$stmtTotal = $conexao->prepare($sqlTotal);
if (!empty($params)) {
    $stmtTotal->bind_param($tipos, ...$params);
}
$stmtTotal->execute();

$resultTotal = $stmtTotal->get_result();
$totalRegistros = $resultTotal->fetch_assoc()['total'];
$totalPaginas = ceil($totalRegistros / $limite);

// =============================
// CONSULTA PRINCIPAL
// =============================
$sql = "
    SELECT 
        p.*,
        om.abreviatura AS batalhao_nome
    FROM fin_pregao p
    JOIN organizacoes_militares om ON om.id = p.batalhao
    $condicoes
    ORDER BY p.id DESC
    LIMIT ? OFFSET ?
";

$paramsExec = $params;
$tiposExec = $tipos;

$paramsExec[] = $limite;
$paramsExec[] = $offset;
$tiposExec .= "ii";

$stmt = $conexao->prepare($sql);
$stmt->bind_param($tiposExec, ...$paramsExec);
$stmt->execute();
$result = $stmt->get_result();

// =============================
// FUNÇÃO DE PAGINAÇÃO (mantida)
// =============================
function renderPaginacaoPregao($pagina, $totalPaginas, $limite, $queryString, $arquivo = 'includes/pregao/listagem.php') {
    $html = '<div class="pagination-wrapper">';
    $html .= '<nav><ul class="pagination pagination-sm">';

    for ($i = 1; $i <= $totalPaginas; $i++) {
        $ativo = $i == $pagina ? 'active' : '';
        $url = "{$arquivo}?{$queryString}&pagina=$i&limite=$limite";
        $html .= "<li class='page-item $ativo'>
                    <a class='page-link paginacao-pregao' href='#' data-page='{$url}'>$i</a>
                  </li>";
    }

    $html .= '</ul></nav></div>';
    return $html;
}

$paramsGET = $_GET;
unset($paramsGET['pagina']);
$queryString = http_build_query($paramsGET);
?>



<style>
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
        <h3 class="fw-bold mb-1">Listagem dos Pregões</h3>
        <h6 class="text-muted">Listagem dos pregões realizados ou em andamento.</h6>
      </div>
      <div>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalCadastroPREGAO">
          <i class="fa fa-plus me-1"></i> Cadastrar Pregão
        </button>
          
          <!-- Botão Excel -->
<!-- Botão Excel -->
<button id="btnExportarExcelPregao" class="btn btn-success">
  <i class="fas fa-file-excel"></i> Exportar Excel
</button>

      </div>
    </div>

    <div class="mb-3">
      <button class="btn btn-outline-primary w-100 d-flex justify-content-center align-items-center" onclick="toggleFiltrosPregao()">
        <i class="fas fa-search me-2"></i> Filtros
      </button>
    </div>

    <div id="filtros-container-pregao" style="display: none;" class="mb-3">
      <div class="card shadow-sm border-0">
        <div class="card-body">
          <form method="GET" id="filtroPregaoForm">
            <div class="row g-3">
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

            <div class="col-md-2">
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
                <label class="form-label fw-semibold">Número</label>
                <input type="text" class="form-control" name="nmr_pregao" value="<?= htmlspecialchars($nmr_pregao) ?>">
              </div>
              <div class="col-md-2">
                <label class="form-label fw-semibold">Ano</label>
                <input type="text" class="form-control" name="ano_pregao" value="<?= htmlspecialchars($ano_pregao) ?>">
              </div>
              <div class="col-md-2">
                <label class="form-label fw-semibold">UG</label>
                <input type="text" class="form-control" name="ug_licitacao" value="<?= htmlspecialchars($ug_licitacao) ?>">
              </div>
              <div class="col-md-2">
                <label class="form-label fw-semibold">UASG</label>
                <input type="text" class="form-control" name="uasg_licitacao" value="<?= htmlspecialchars($uasg_licitacao) ?>">
              </div>
              <div class="col-md-2">
                <label class="form-label fw-semibold">Tipo</label>
                <input type="text" class="form-control" name="tipo_pregao" value="<?= htmlspecialchars($tipo_pregao) ?>">
              </div>
              <div class="col-md-2">
                <label class="form-label fw-semibold">Data Inicial</label>
                <input type="date" class="form-control" name="data_ini" value="<?= htmlspecialchars($data_ini) ?>">
              </div>
              <div class="col-md-2">
                <label class="form-label fw-semibold">Data Final</label>
                <input type="date" class="form-control" name="data_fim" value="<?= htmlspecialchars($data_fim) ?>">
              </div>
              <div class="col-12 d-flex justify-content-between mt-2">
                <button type="button" id="btnLimparFiltrosPregao" class="btn btn-black ms-2">Limpar Filtros</button>
                <button type="submit" class="btn btn-primary px-4">Aplicar</button>
              </div>
            </div>
          </form>
        </div>
      </div>
    </div>

    <div class="mb-3">
      <label for="limitePregao" class="me-2 mb-0">Mostrar</label>
      <select id="limitePregao" name="limite" class="form-select d-inline w-auto" onchange="atualizarLimitePregao()">
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
      <?= renderPaginacaoPregao($pagina, $totalPaginas, $limite, $queryString, 'includes/pregao/listagem.php'); ?>
    </div>

<div class="card border-0 shadow-sm">
  <div class="card-body">
    <div class="list-group">
      <?php if ($result->num_rows > 0): ?>
        <?php while ($pregao = $result->fetch_assoc()): ?>
          <?php
          // Contar itens
          $stmt = $conexao->prepare("SELECT COUNT(*) AS total_itens FROM fin_pregao_itens WHERE id_pregao = ?");
          $stmt->bind_param("i", $pregao['id']);
          $stmt->execute();
          $res = $stmt->get_result();
          $contagem = $res->fetch_assoc();
          $total_itens = $contagem['total_itens'] ?? 0;
          $stmt->close();

          // Situação do pregão
          $hoje = new DateTime();
          $validade = $pregao['data_validade'] ? new DateTime($pregao['data_validade']) : null;
          $situacao = '';
          $classe = '';

          if ($validade) {
              $diferenca = (int)$hoje->diff($validade)->format('%r%a');
              if ($diferenca < 0) {
                  $situacao = 'Pregão não vigente';
                  $classe = 'danger';
              } elseif ($diferenca <= 90) {
                  $situacao = 'Próximo do pregão vencer';
                  $classe = 'warning';
              } else {
                  $situacao = 'Pregão vigente';
                  $classe = 'success';
              }
          } else {
              $situacao = 'Data de validade indefinida';
              $classe = 'secondary';
          }
          ?>
          
          <div class="list-group-item list-group-item-action flex-column align-items-start mb-3 p-3 border rounded-3 shadow-sm">
            <div class="d-flex w-100 justify-content-between align-items-center">
              <h6 class="mb-0 fw-semibold text-primary">
                Pregão #<?= htmlspecialchars($pregao['nmr_pregao']) ?>/<?= htmlspecialchars($pregao['ano_pregao']) ?>
              </h6>
              <small class="text-muted">
                <?= $pregao['data_homologacao'] ? date('d/m/Y', strtotime($pregao['data_homologacao'])) : '—' ?>
              </small>
            </div>
<p class="mb-2 text-secondary">Descrição: <?= htmlspecialchars($pregao['descricao_pregao']) ?> </p>
            <p class="mb-2 text-secondary"><?= htmlspecialchars($pregao['tipo_pregao']) ?></p>
              

            <div class="d-flex flex-column small text-body-secondary">
              <div><strong>OM Cadastrante:</strong> <?= htmlspecialchars($pregao['batalhao_nome'] ?? 'Não informado') ?></div>
              <div><strong>UG:</strong> <?= htmlspecialchars($pregao['ug_licitacao']) ?></div>
              <div><strong>UASG:</strong> <?= htmlspecialchars($pregao['uasg_licitacao']) ?></div>
              <div><strong>NUP:</strong> <?= htmlspecialchars($pregao['nup_licitacao']) ?></div>
              <div><strong>Validade:</strong> <?= $pregao['data_validade'] ? date('d/m/Y', strtotime($pregao['data_validade'])) : '—' ?></div>
              <div><strong>Continuidade:</strong> <?= htmlspecialchars($pregao['continuidade_pregao']) ?></div>
            </div>

            <div class="alert alert-secondary mt-3 mb-2 py-2 px-3 rounded-pill fw-bold text-center">
              Itens cadastrados no pregão: <?= $total_itens ?>
            </div>

            <div class="alert alert-<?= $classe ?> mt-1 mb-2 py-2 px-3 rounded-pill fw-bold text-center">
              <?= $situacao ?>
            </div>

            <div class="d-flex ms-auto align-items-center gap-2 mt-3 flex-wrap">
              <button class="btn btn-sm btn-outline-primary" onclick="verPregao(<?= $pregao['id'] ?>)" data-bs-toggle="modal" data-bs-target="#modalVerPregao">
                <i class="fas fa-eye me-1"></i> Ver
              </button>
              <button class="btn btn-sm btn-outline-warning" onclick="editarPregao(<?= $pregao['id'] ?>)" data-bs-toggle="modal" data-bs-target="#modalEditarPregao">
                <i class="fas fa-edit me-1"></i> Editar
              </button>
              <button type="button"
                class="btn btn-sm btn-outline-danger d-flex align-items-center btn-deletar-pregao"
                data-id="<?= $pregao['id'] ?>"
                onclick="deletarPregao(this)"
                data-bs-toggle="tooltip"
                title="Remover">
                <i class="fa fa-times"></i>
              </button>
            </div>
          </div>
        <?php endwhile; ?>
      <?php else: ?>
        <div class="alert alert-info text-center" role="alert">
          Não há pregões cadastrados.
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>





    <div class="paginacao">
      <?= renderPaginacaoPregao($pagina, $totalPaginas, $limite, $queryString, 'includes/pregao/listagem.php'); ?>
    </div>
  </div>
</div>

</div>




<!-- Modal de Cadastro -->
    <div class="modal fade" id="modalCadastroPREGAO" tabindex="-1" aria-labelledby="modalLabel" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="modalLabel">Cadastrar Pregão</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
          </div>
          <div class="modal-body">
              <form method="POST" enctype="multipart/form-data" id="form-pregao-cadastrar">
                  <div class="mb-3">
    <label class="form-label fw-semibold">Batalhão Cadastrante</label>
    <select name="batalhao" class="form-select">
        <?php foreach ($oms_visiveis as $id => $nome): 
            $sel = ($batalhao_filtro == $id) ? 'selected' : '';
        ?>
            <option value="<?= $id ?>" <?= $sel ?>><?= htmlspecialchars($nome) ?></option>
        <?php endforeach; ?>
    </select>
</div>
                  <div class="mb-3">
    <label for="data_homologacao" class="form-label">Data de homologação</label>
    <input type="date" class="form-control" id="data_homologacao" name="data_homologacao" required>
  </div>
                  
                  <div class="mb-3">
    <label for="data_validade" class="form-label">Data de validade</label>
    <input type="date" class="form-control" id="data_validade" name="data_validade" required>
  </div>

 <div class="mb-3">
    <label for="tipo_pregao" class="form-label">Tipo de pregão</label>
    <select class="form-select rounded-pill shadow-sm" id="tipo_pregao" name="tipo_pregao" required>
      <option value="" disabled selected>Selecione o tipo de pregão</option>
      <option value="Pregão do Batalhão">Pregão do Batalhão</option>
      <option value="Carona">Carona</option>
      <option value="Pregão Participante">Pregão Participante</option>
      <option value="Inexigibilidade">Inexigibilidade</option>
      <option value="Dispensa de Licitação">Dispensa de Licitação</option>
      <option value="Contrato">Contrato</option>
    </select>
  </div>


  <div class="mb-3">
    <label for="nmr_pregao" class="form-label">Número do pregão</label>
    <input type="text" class="form-control" id="nmr_pregao" name="nmr_pregao" required>
  </div>
                  
  <div class="mb-3">
    <label for="ano_pregao" class="form-label">Ano do pregão</label>
    <input type="text" class="form-control" id="ano_pregao" name="ano_pregao" required>
  </div>
                  
   <div class="mb-3">
    <label for="descricao_pregao" class="form-label">Descrição do pregão</label>
    <input type="text" class="form-control" id="descricao_pregao" name="descricao_pregao" required>
  </div>
 <div class="mb-3">
    <label for="ug_licitacao" class="form-label">UG licitação</label>
    <input type="text" class="form-control" id="ug_licitacao" name="ug_licitacao" required>
  </div>
 <div class="mb-3">
    <label for="uasg_licitacao" class="form-label">UASG licitação</label>
    <input type="text" class="form-control" id="uasg_licitacao" name="uasg_licitacao" required>
  </div>
 <div class="mb-3">
    <label for="nup_licitacao" class="form-label">NUP da licitação</label>
    <input type="text" class="form-control" id="nup_licitacao" name="nup_licitacao" required>
  </div>
            
  <div class="mb-3">
    <label for="continuidade_pregao" class="form-label">Pregão terá continuidade?</label>
    <select class="form-select rounded-pill shadow-sm" id="continuidade_pregao" name="continuidade_pregao" required>
      <option value="" disabled selected>Selecione</option>
      <option value="Sim">Sim</option>
      <option value="Não">Não</option>
    </select>
  </div>
          
<hr>

<h5 class="fw-bold">Itens do Pregão</h5>

<div id="itensPregaoContainer" class="mb-2"></div>

<div class="row"><button type="button" class="btn btn-info btn-sm mb-3" onclick="adicionarItemPregao()">Adicionar item ao pregão</button></div>

<button type="submit" class="btn btn-success">Cadastrar pregão</button>
<input type="hidden" id="edit-id" name="id">
</form>
              
              

          </div>
        </div>
      </div>
    </div>




<!-- Modal de Edição -->
<div class="modal fade" id="modalEditarPregao" tabindex="-1" aria-labelledby="modalLabelEditar" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      <div class="modal-header bg-warning">
        <h5 class="modal-title" id="modalLabelEditar">Editar Pregão</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
      </div>
      <div class="modal-body">
        <form method="POST" enctype="multipart/form-data" id="form-pregao-editar">
  <div class="mb-3">
    <label class="form-label fw-semibold">Batalhão Cadastrante</label>
                   <input type="text" class="form-control" id="editar-nome_batalhao" name="batalhao" readonly>
</div>
          <div class="mb-3">
            <label class="form-label">Data de homologação</label>
            <input type="date" class="form-control" id="editar-data_homologacao" name="data_homologacao" required>
          </div>

          <div class="mb-3">
            <label class="form-label">Data de validade</label>
            <input type="date" class="form-control" id="editar-data_validade" name="data_validade" required>
          </div>

          <div class="mb-3">
            <label class="form-label">Tipo de pregão</label>
            <select class="form-select rounded-pill shadow-sm" id="editar-tipo_pregao" name="tipo_pregao" required>
              <option value="" disabled selected>Selecione o tipo de pregão</option>
              <option value="Pregão do Batalhão">Pregão do Batalhão</option>
              <option value="Carona">Carona</option>
              <option value="Pregão Participante">Pregão Participante</option>
              <option value="Inexigibilidade">Inexigibilidade</option>
              <option value="Dispensa de Licitação">Dispensa de Licitação</option>
              <option value="Contrato">Contrato</option>
            </select>
          </div>

          <div class="mb-3">
            <label class="form-label">Número do pregão</label>
            <input type="text" class="form-control" id="editar-nmr_pregao" name="nmr_pregao" required>
          </div>

          <div class="mb-3">
            <label class="form-label">Ano do pregão</label>
            <input type="text" class="form-control" id="editar-ano_pregao" name="ano_pregao" required>
          </div>
            
            <div class="mb-3">
            <label class="form-label">Descrição do pregão</label>
            <input type="text" class="form-control" id="editar-descricao_pregao" name="descricao_pregao" required>
          </div>

          <div class="mb-3">
            <label class="form-label">UG licitação</label>
            <input type="text" class="form-control" id="editar-ug_licitacao" name="ug_licitacao" required>
          </div>

          <div class="mb-3">
            <label class="form-label">UASG licitação</label>
            <input type="text" class="form-control" id="editar-uasg_licitacao" name="uasg_licitacao" required>
          </div>

          <div class="mb-3">
            <label class="form-label">NUP da licitação</label>
            <input type="text" class="form-control" id="editar-nup_licitacao" name="nup_licitacao" required>
          </div>

          <div class="mb-3">
            <label class="form-label">Pregão terá continuidade?</label>
            <select class="form-select rounded-pill shadow-sm" id="editar-continuidade_pregao" name="continuidade_pregao" required>
              <option value="" disabled selected>Selecione</option>
              <option value="Sim">Sim</option>
              <option value="Não">Não</option>
            </select>
          </div>

          <hr>
          <h5 class="fw-bold">Itens do Pregão</h5>
          
          <div id="itensPregaoContainerEditar" class="mb-2"></div>
            <div class="row"><button type="button" class="btn btn-info btn-sm mb-3" onclick="adicionarItemPregaoEditar()">Adicionar item ao pregão</button></div>

          <button type="submit" class="btn btn-success">Salvar alterações</button>
          <input type="hidden" id="editar-id" name="id">
        </form>
      </div>
    </div>
  </div>
</div>


<!-- Modal de Ver pregão -->

<div class="modal fade" id="modalVerPregao" tabindex="-1" aria-labelledby="modalVerPregaoLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content border-0 shadow">
      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title">Ver Pregão</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Fechar"></button>
      </div>

      <div class="modal-body p-4 bg-light">

        <!-- Dados do Pregão -->
        <div class="row g-3 bg-white p-3 rounded shadow-sm mb-4">
          <h5 class="fw-bold text-secondary">Dados do Pregão</h5>
          <div class="col-md-3">
            <label class="form-label">Número</label>
            <p class="form-control-plaintext" id="ver_nmr_pregao"></p>
          </div>
          <div class="col-md-3">
            <label class="form-label">Ano</label>
            <p class="form-control-plaintext" id="ver_ano_pregao"></p>
          </div>
          <div class="col-md-3">
            <label class="form-label">Tipo</label>
            <p class="form-control-plaintext" id="ver_tipo_pregao"></p>
          </div>
          <div class="col-md-3">
            <label class="form-label">UG</label>
            <p class="form-control-plaintext" id="ver_ug_licitacao"></p>
          </div>
          <div class="col-md-3">
            <label class="form-label">UASG</label>
            <p class="form-control-plaintext" id="ver_uasg_licitacao"></p>
          </div>
          <div class="col-md-3">
            <label class="form-label">NUP</label>
            <p class="form-control-plaintext" id="ver_nup_licitacao"></p>
          </div>
          <div class="col-md-3">
            <label class="form-label">Data Homologação</label>
            <p class="form-control-plaintext" id="ver_data_homologacao"></p>
          </div>
          <div class="col-md-3">
            <label class="form-label">Data Validade</label>
            <p class="form-control-plaintext" id="ver_data_validade"></p>
          </div>
          <div class="col-md-3">
            <label class="form-label">Continuidade</label>
            <p class="form-control-plaintext" id="ver_continuidade_pregao"></p>
          </div>
        </div>

        <!-- Itens do Pregão -->
        <div class="bg-white p-3 rounded shadow-sm mb-4">
          <h5 class="fw-bold text-secondary">Itens do Pregão</h5>
          <div class="table-responsive">
            <table class="table table-bordered table-hover small">
              <thead class="table-light">
                <tr>
                  <th>#</th>
                  <th>Descrição</th>
                  <th>Saldo</th>
                  <th>Total Requisitado</th>
                  <th>Disponível</th>
                </tr>
              </thead>
              <tbody id="itensPregaoVerContainer">
                <!-- Itens inseridos dinamicamente -->
              </tbody>
            </table>
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






<script>
  window.selectFornecedorTemplate = `
    <?php foreach($fornecedores as $fornecedor): ?>
      <option value="<?= $fornecedor['id'] ?>">
        <?= addslashes(htmlspecialchars($fornecedor['nome_empresa'])) ?> - <?= addslashes(htmlspecialchars($fornecedor['cnpj_empresa'])) ?>
      </option>
    <?php endforeach; ?>
  `;
</script>

<!-- Script da página de Cadastro de Vtr/Eqp -->
<script>
    window.funcaoInicializacao = 'inicializarCadastrarPregao';
</script>

