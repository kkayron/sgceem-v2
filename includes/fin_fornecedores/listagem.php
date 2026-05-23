<?php
header('Content-Type: text/html; charset=utf-8');
session_start();
require_once '../api/seguranca.php';

$permissoes = verificarPermissao([21]);

$pode_cadastrar = $permissoes['cadastrar'];
$pode_editar    = $permissoes['editar'];
$pode_deletar   = $permissoes['deletar'];
$pode_importar  = $permissoes['importar'];
$pode_exportar  = $permissoes['exportar'];

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
// FILTROS VIA GET
// =============================
$id = $_GET['id'] ?? '';
$nome_empresa = $_GET['nome_empresa'] ?? '';
$cnpj_empresa = $_GET['cnpj_empresa'] ?? '';
$categoria_empresa = $_GET['categoria_empresa'] ?? '';
$data_ini = $_GET['data_ini'] ?? '';
$data_fim = $_GET['data_fim'] ?? '';
$batalhao_filtro = $_GET['batalhao'] ?? '';

// Inicializa arrays de filtros
$filtros = [];
$params = [];
$tipos = '';

// Campos padrão
$campos = [
  'f.id' => 'id',
  'f.nome_empresa' => 'nome_empresa',
  'f.cnpj_empresa' => 'cnpj_empresa',
  'f.categoria_empresa' => 'categoria_empresa',
  'f.data_cadastro >= ' => 'data_ini',
  'f.data_cadastro <= ' => 'data_fim'
];

foreach ($campos as $coluna => $parametro) {
  if (!empty($_GET[$parametro])) {
    $valor = $_GET[$parametro];

    if (str_contains($coluna, '>=')) {
      $filtros[] = str_replace(' >= ', ' >=', $coluna) . ' ?';
      $params[] = $valor;
      $tipos .= 's';

    } elseif (str_contains($coluna, '<=')) {
      $filtros[] = str_replace(' <= ', ' <=', $coluna) . ' ?';
      $params[] = $valor;
      $tipos .= 's';

    } elseif ($coluna == 'f.id') {
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

// =============================
// CONTROLE DE NÍVEL E PERMISSÕES POR BATALHÃO
// =============================
if ($nivel_usuario == 1) {

    if (!empty($batalhao_filtro)) {
        $filtros[] = "f.batalhao = ?";
        $params[] = (int)$batalhao_filtro;
        $tipos .= 'i';
    }

} elseif ($nivel_usuario == 2) {

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
            $filtros[] = "f.batalhao = ?";
            $params[] = (int)$batalhao_filtro;
            $tipos .= 'i';
        } else {
            die("Acesso negado ao batalhão selecionado.");
        }
    } else {
        $placeholders = implode(',', array_fill(0, count($batalhoesPermitidos), '?'));
        $filtros[] = "f.batalhao IN ($placeholders)";
        $params = array_merge($params, $batalhoesPermitidos);
        $tipos .= str_repeat('i', count($batalhoesPermitidos));
    }

} else {
    // Nível 3 vê somente o próprio batalhão
    $filtros[] = "f.batalhao = ?";
    $params[] = $batalhao_usuario;
    $tipos .= 'i';
}

// =============================
// Condições finais
// =============================
$condicoes = !empty($filtros) ? 'WHERE ' . implode(' AND ', $filtros) : '';

// =============================
// PAGINAÇÃO
// =============================
$limite = isset($_GET['limite']) && is_numeric($_GET['limite']) ? (int)$_GET['limite'] : 10;
$pagina = isset($_GET['pagina']) && is_numeric($_GET['pagina']) && $_GET['pagina'] > 0 ? (int)$_GET['pagina'] : 1;
$offset = ($pagina - 1) * $limite;

// =============================
// CONTAGEM TOTAL
// =============================
$sqlTotal = "SELECT COUNT(*) AS total FROM fin_fornecedores f $condicoes";

$stmtTotal = $conexao->prepare($sqlTotal);
if (!empty($params)) {
    $stmtTotal->bind_param($tipos, ...$params);
}
$stmtTotal->execute();

$resultTotal = $stmtTotal->get_result();
$totalRegistros = $resultTotal->fetch_assoc()['total'];
$totalPaginas = ceil($totalRegistros / $limite);

// =============================
// CONSULTA PRINCIPAL (AJUSTADA)
// =============================

$sql = "
  SELECT 
    f.id,
    f.nome_empresa,
    f.cnpj_empresa,
    f.data_cadastro,
    f.categoria_empresa,
    f.contato_nome,
    f.contato_numero,
    f.contato_email,
    f.batalhao,
    om.abreviatura AS batalhao_nome
  FROM fin_fornecedores f
  JOIN organizacoes_militares om ON om.id = f.batalhao
  $condicoes
  ORDER BY f.id DESC
  LIMIT ? OFFSET ?
";

// Cria cópia SEPARADA dos parâmetros
$paramsExec = $params;
$tiposExec = $tipos;

// Adiciona limit e offset na cópia
$paramsExec[] = $limite;
$paramsExec[] = $offset;
$tiposExec .= "ii";

$stmt = $conexao->prepare($sql);
$stmt->bind_param($tiposExec, ...$paramsExec);
$stmt->execute();
$result = $stmt->get_result();

// =============================
// Função de paginação
// =============================
function renderPaginacaoForn($pagina, $totalPaginas, $limite, $queryString, $arquivo = 'includes/fin_fornecedores/listagem.php') {

  $html = '<div class="pagination-wrapper">';
  $html .= '<nav><ul class="pagination pagination-sm">';

  for ($i = 1; $i <= $totalPaginas; $i++) {
      $ativo = $i == $pagina ? 'active' : '';
      $url = "{$arquivo}?{$queryString}&pagina=$i&limite=$limite";

      $html .= "
        <li class='page-item $ativo'>
          <a class='page-link paginacao-forn' href='#' data-page='{$url}'>$i</a>
        </li>
      ";
  }

  $html .= '</ul></nav></div>';
  return $html;
}

// Query string limpa para manter filtros
$paramsGET = $_GET;
unset($paramsGET['pagina']);
$queryString = http_build_query($paramsGET);
$filtrosVisiveis = !empty($_GET);
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
        <h3 class="fw-bold mb-1">Listagem de Fornecedores</h3>
        <h6 class="text-muted">Fornecedores cadastrados</h6>
      </div>
        <div>
			<?php if($pode_cadastrar): ?>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalCadastroFornecedor">
          <i class="fa fa-user-plus me-1"></i> Cadastrar Fornecedor
        </button>
			<?php endif; ?>
			
			<?php if($pode_importar): ?>
         <!-- Botão para abrir modal -->
<button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#modalImportarFornecedores">
  Importar Fornecedores
</button>
			<?php endif; ?>
      </div>
    </div>
   <!-- Botão para mostrar/ocultar filtros -->
<div class="mb-3">
 <button class="btn btn-outline-primary w-100 d-flex justify-content-center align-items-center" onclick="toggleFiltrosFORN()">
    <i class="fas fa-search me-2"></i> Filtros
  </button>
</div>

    <!-- Lista de usuários -->
    <div id="filtros-container-forn" style="display: none;" class="mb-3">
  <div class="card shadow-sm border-0">
    <div class="card-body">
      <form method="GET" id="filtroFornForm">
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
          <div class="col-md-3">
            <label class="form-label fw-semibold">Nome da Empresa</label>
            <input type="text" class="form-control" name="nome_empresa" value="<?= htmlspecialchars($nome_empresa ?? '') ?>">
          </div>
          <div class="col-md-3">
            <label class="form-label fw-semibold">CNPJ</label>
            <input type="text" class="form-control" name="cnpj_empresa" value="<?= htmlspecialchars($cnpj_empresa ?? '') ?>">
          </div>
          <div class="col-md-3">
            <label class="form-label fw-semibold">Categoria</label>
            <input type="text" class="form-control" name="categoria_empresa" value="<?= htmlspecialchars($categoria_empresa ?? '') ?>">
          </div>
          <div class="col-md-3">
            <label class="form-label fw-semibold">Data Cadastro (Início)</label>
            <input type="date" class="form-control" name="data_ini" value="<?= htmlspecialchars($data_ini ?? '') ?>">
          </div>
          <div class="col-md-3">
            <label class="form-label fw-semibold">Data Cadastro (Fim)</label>
            <input type="date" class="form-control" name="data_fim" value="<?= htmlspecialchars($data_fim ?? '') ?>">
          </div>
          <div class="col-12 d-flex justify-content-between mt-2">
            <button type="button" id="btnLimparFiltrosForn" class="btn btn-black ms-2">Limpar Filtros</button>
            <button type="submit" class="btn btn-primary px-4">Aplicar</button>
          </div>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Select de limite por página -->
<div class="mb-3">
  <label for="limiteForn" class="me-2 mb-0">Mostrar</label>
  <select id="limiteForn" name="limite" class="form-select d-inline w-auto" onchange="atualizarLimiteForn()">
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
<div class="paginacao">
  <?= renderPaginacaoForn($pagina, $totalPaginas, $limite, $queryString, 'includes/fin_fornecedores/listagem.php'); ?>
</div>

<div class="card border-0 shadow-sm rounded-3">
  <div class="card-body">

    <?php while ($forn = $result->fetch_assoc()): ?>
      <div class="p-4 mb-4 rounded-4 shadow-sm border position-relative bg-white fornecedor-card">

        <!-- Linha superior -->
        <div class="d-flex justify-content-between align-items-start mb-3">
          <div>
            <h4 class="fw-bold mb-0 text-dark">
              <?= htmlspecialchars($forn['nome_empresa']) ?>
            </h4>
            <span class="text-muted small">
              CNPJ: <?= htmlspecialchars($forn['cnpj_empresa']) ?>
            </span>
          </div>

          <div class="text-end">
            <span class="badge bg-primary px-3 py-2 shadow-sm">
              <?= htmlspecialchars($forn['batalhao_nome'] ?? $forn['batalhao_nome'] ?? '—') ?>
            </span>
            <div class="small text-muted mt-1">
              Cadastrado em: <?= htmlspecialchars(date('d/m/Y', strtotime($forn['data_cadastro']))) ?>
            </div>
          </div>
        </div>

        <!-- Categoria -->
        <div class="mb-3">
          <span class="badge bg-secondary px-3 py-2">
            <?= htmlspecialchars($forn['categoria_empresa'] ?? 'Sem categoria') ?>
          </span>
        </div>

        <!-- Informações de contato -->
        <div class="row mb-3">
          <div class="col-md-4 mb-2">
            <strong class="text-dark">Contato:</strong><br>
            <span><?= htmlspecialchars($forn['contato_nome'] ?? '—') ?></span>
          </div>

          <div class="col-md-4 mb-2">
            <strong class="text-dark">Telefone:</strong><br>
            <span><?= htmlspecialchars($forn['contato_numero'] ?? '—') ?></span>
          </div>

          <div class="col-md-4 mb-2">
            <strong class="text-dark">Email:</strong><br>
            <span><?= htmlspecialchars($forn['contato_email'] ?? '—') ?></span>
          </div>
        </div>

        <!-- Ações -->
        <div class="d-flex gap-2 flex-wrap">
			
			<?php if($pode_editar): ?>
          <button class="btn btn-sm btn-primary px-3 shadow-sm d-flex align-items-center"
        onclick="editarFORN(<?= $forn['id'] ?>)"
        data-bs-toggle="modal"
        data-bs-target="#modalEditarFORN">
    <i class="fa-solid fa-pen-to-square me-1"></i> Editar
</button>
<?php endif; ?>
			
			<?php if($pode_deletar): ?>
          <button type="button"
                  class="btn btn-sm btn-danger px-3 shadow-sm d-flex align-items-center"
                  data-id="<?= $forn['id'] ?>"
				  data-token="<?= $_SESSION['csrf_token'] ?>"
                  onclick="deletarFORN(this)">
           <i class="fas fa-trash-alt me-1"></i> Excluir
          </button>
			<?php endif; ?>
        </div>

      </div>
    <?php endwhile; ?>

  </div>
</div>



<!-- Paginação inferior -->
<div class="paginacao">
  <?= renderPaginacaoForn($pagina, $totalPaginas, $limite, $queryString, 'includes/fin_fornecedores/listagem.php'); ?>
</div>
<?php if($pode_cadastrar): ?>
<!-- Modal de Cadastro -->
    <div class="modal fade" id="modalCadastroFornecedor" tabindex="-1" aria-labelledby="modalLabel" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="modalLabel">Cadastrar Fornecedor</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
          </div>
          <div class="modal-body">
              <form method="POST" enctype="multipart/form-data" id="form-fornecedor-cadastrar">
<div class="mb-3">
    <label class="form-label fw-semibold">Batalhão</label>
    <select name="batalhao" class="form-select">
        <?php foreach ($oms_visiveis as $id => $nome): 
            $sel = ($batalhao_filtro == $id) ? 'selected' : '';
        ?>
            <option value="<?= $id ?>" <?= $sel ?>><?= htmlspecialchars($nome) ?></option>
        <?php endforeach; ?>
    </select>
</div>
 <div class="mb-3">
    <label for="categoria_empresa" class="form-label">Categoria do fornecedor</label>
    <select class="form-select rounded-pill shadow-sm" id="categoria_empresa" name="categoria_empresa" required>
      <option value="" disabled selected>Selecione a categoria do fornecedor</option>
      <option value="Peças">Peças</option>
      <option value="Serviço">Serviço</option>
      <option value="Lubrificantes">Lubrificantes</option>
      <option value="Pneus">Pneus</option>
      <option value="Geral">Geral</option>
    </select>
  </div>
  
  <div class="mb-3">
    <label for="nome_empresa" class="form-label">Nome da Empresa</label>
    <input type="text" class="form-control" id="nome_empresa" name="nome_empresa" required>
  </div>
                  
<div class="mb-3">
  <label for="cnpj_empresa" class="form-label">CNPJ da empresa</label>
  <input type="text" class="form-control" id="cnpj_empresa" name="cnpj_empresa" required maxlength="18" placeholder="00.000.000/0000-00">
</div>
 <div class="mb-3">
    <label for="contato_nome" class="form-label">Nome do contato</label>
    <input type="text" class="form-control" id="contato_nome" name="contato_nome" required>
  </div>
                  
  
 <div class="mb-3">
    <label for="contato_numero" class="form-label">Número do contato</label>
    <input type="text" class="form-control" id="contato_numero" name="contato_numero" required>
  </div>
                  
                   <div class="mb-3">
    <label for="contato_email" class="form-label">E-mail da empresa</label>
    <input type="email" class="form-control" id="contato_email" name="contato_email" required>
  </div>
	  <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
  <button type="submit" class="btn btn-success">Cadastrar Fornecedor</button>
              <input type="hidden" id="edit-id" name="id">
</form>
              
              

          </div>
        </div>
      </div>
    </div>
      <?php endif; ?>
      
			<?php if($pode_editar): ?>
  <!-- Modal de Edição -->
    <div class="modal fade" id="modalEditarFORN" tabindex="-1" aria-labelledby="modalEditarFORNLabel" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="modalLabel">Editar Fornecedor</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
          </div>
          <div class="modal-body">
              <form method="POST" enctype="multipart/form-data" id="form-editar-forn">
      <div class="mb-3">
    <label class="form-label fw-semibold">Batalhão</label>
                   <input type="text" class="form-control" id="nome_batalhao_edit" name="batalhao" readonly>
</div>
 <div class="mb-3">
    <label for="categoria_empresa" class="form-label">Categoria do fornecedor</label>
    <select class="form-select rounded-pill shadow-sm" id="categoria_empresa" name="categoria_empresa" required>
      <option value="" disabled selected>Selecione a categoria do fornecedor</option>
      <option value="Peças">Peças</option>
      <option value="Serviço">Serviço</option>
      <option value="Lubrificantes">Lubrificantes</option>
      <option value="Pneus">Pneus</option>
      <option value="Geral">Geral</option>
    </select>
  </div>
  
  <div class="mb-3">
    <label for="nome_empresa" class="form-label">Nome da Empresa</label>
    <input type="text" class="form-control" id="nome_empresa" name="nome_empresa" required>
  </div>
                  
<div class="mb-3">
  <label for="cnpj_empresa" class="form-label">CNPJ da empresa</label>
  <input type="text" class="form-control" id="cnpj_empresa" name="cnpj_empresa" required maxlength="18" placeholder="00.000.000/0000-00">
</div>
 <div class="mb-3">
    <label for="contato_nome" class="form-label">Nome do contato</label>
    <input type="text" class="form-control" id="contato_nome" name="contato_nome" required>
  </div>
                  
  
 <div class="mb-3">
    <label for="contato_numero" class="form-label">Número do contato</label>
    <input type="text" class="form-control" id="contato_numero" name="contato_numero" required>
  </div>
                  
                   <div class="mb-3">
    <label for="contato_email" class="form-label">E-mail da empresa</label>
    <input type="email" class="form-control" id="contato_email" name="contato_email" required>
  </div>

  <button type="button" class="btn btn-danger" data-bs-dismiss="modal">Cancelar</button>
				  
	  <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <button type="submit" class="btn btn-success">Salvar alterações</button>
              <input type="hidden" id="edit-id" name="id">
</form>
              
              

          </div>
        </div>
      </div>
    </div>
      <?php endif; ?>
			<?php if($pode_importar): ?>
      <!-- Modal de Importação -->
<div class="modal fade" id="modalImportarFornecedores" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form id="formImportarFornecedores" method="POST" enctype="multipart/form-data">
        <div class="modal-header">
          <h5 class="modal-title">Importar Planilha de Fornecedores</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>

        <div class="modal-body">
          <?php
          // ============================
          // MESMA LÓGICA DE BATALHÕES QUE O USUÁRIO PODE VER
          // ============================
          $id_om_usuario = $_SESSION['usuario']['batalhao'] ?? 0;

          $sqlNivel = "SELECT nivel FROM organizacoes_militares WHERE id = $id_om_usuario";
          $resNivel = $conexao->query($sqlNivel);
          $nivelUsuario = $resNivel->fetch_assoc()['nivel'] ?? 0;

          if ($nivelUsuario == 1) {
              // Administrador - vê todas OMs
              $sqlBatalhoes = "SELECT id, nome, abreviatura FROM organizacoes_militares ORDER BY nome";
          } else {
              // Usuário comum - vê apenas sua OM e subordinadas
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

          <!-- SELEÇÃO DO BATALHÃO PARA IMPORTAR -->
          <label class="form-label">Selecione o Batalhão dos Fornecedores</label>
          <select name="batalhao" id="batalhao_importacao" class="form-select mb-3" required>
            <option value="">Selecione...</option>
            <?php while ($bat = $resBatalhoes->fetch_assoc()): ?>
              <option value="<?= $bat['id'] ?>">
                <?= htmlspecialchars($bat['abreviatura'] . ' - ' . $bat['nome']) ?>
              </option>
            <?php endwhile; ?>
          </select>

          <!-- ARQUIVO -->
          <label class="form-label">Selecione a planilha (.xlsx)</label>
          <input type="file" name="arquivo" id="arquivo" accept=".xlsx" class="form-control mb-3" required>

          <!-- MODELO DA PLANILHA -->
          <a href="includes/fin_fornecedores/planilha_modelo_fornecedores.xlsx" class="btn btn-link p-0">
            📥 Baixar modelo de planilha
          </a>
        </div>

        <div class="modal-footer">
	  <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
          <button type="submit" class="btn btn-primary">Importar</button>
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
        </div>

      </form>
    </div>
  </div>
</div>
<?php endif; ?>
<!-- Script da página de Cadastro de Fornecedores -->
<script>
    window.funcaoInicializacao = 'inicializarFinFornecedores';
</script>
<script>
document.getElementById('cnpj_empresa').addEventListener('input', function (e) {
    let value = e.target.value.replace(/\D/g, ''); // remove tudo que não é número
    if (value.length > 14) value = value.slice(0, 14); // limita a 14 dígitos
    let formatted = '';

    if (value.length > 12) {
        formatted = value.replace(/^(\d{2})(\d{3})(\d{3})(\d{4})(\d{2}).*/, "$1.$2.$3/$4-$5");
    } else if (value.length > 8) {
        formatted = value.replace(/^(\d{2})(\d{3})(\d{3})(\d{0,4}).*/, "$1.$2.$3/$4");
    } else if (value.length > 5) {
        formatted = value.replace(/^(\d{2})(\d{3})(\d{0,3}).*/, "$1.$2.$3");
    } else if (value.length > 2) {
        formatted = value.replace(/^(\d{2})(\d{0,3}).*/, "$1.$2");
    } else {
        formatted = value;
    }

    e.target.value = formatted;
});
</script>