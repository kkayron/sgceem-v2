<?php
session_start();

// ID da página correspondente no banco
$pagina_id = intval(3); // <-- altere para o ID correto desta página

// Verifica se usuário está logado
if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit;
}

// Verifica se o usuário tem permissão de acessar a página
if (empty($_SESSION['permissoes'][$pagina_id]['pode_acessar'])) {
    ?>




    <!DOCTYPE html>
    <html lang="pt-BR">
        
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Acesso Negado</title>
        <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
        <style>
          #filtroDashboardForm .form-control,
  #filtroDashboardForm .form-select {
    transition: all 0.2s ease;
  }
  #filtroDashboardForm .form-control:focus,
  #filtroDashboardForm .form-select:focus {
    box-shadow: 0 0 0 0.15rem rgba(25, 135, 84, 0.25);
    border-color: #198754;
  }
        </style>
    </head>
    <body>
        <script>
            Swal.fire({
                icon: 'error',
                title: 'Acesso Negado',
                text: 'Você não possui permissão para acessar esta página.',
                showCancelButton: true,
                confirmButtonText: 'Voltar ao Painel',
                cancelButtonText: 'Ir para Login',
                allowOutsideClick: false,
                allowEscapeKey: false
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = '#partes/conteudo.php';
                } else if (result.isDismissed) {
                    window.location.href = 'login.php';
                }
            });
        </script>
    </body>
    </html>
    <?php
    exit;
}

// Variáveis para as outras permissões
$pode_editar    = $_SESSION['permissoes'][$pagina_id]['pode_editar'] ?? false;
$pode_deletar   = $_SESSION['permissoes'][$pagina_id]['pode_deletar'] ?? false;
$pode_cadastrar = $_SESSION['permissoes'][$pagina_id]['pode_cadastrar'] ?? false;
?>
<?php
header('Content-Type: text/html; charset=utf-8');

include_once('../../conexao/config.php');



// ID e nível da OM do usuário logado
$om_id = $_SESSION['usuario']['batalhao'] ?? 0;
$om_nivel = $_SESSION['usuario']['nivel'] ?? 1;

// ---------------------------
// DEFINIÇÃO DOS BATALHÕES PERMITIDOS
// ---------------------------
$batalhoesPermitidos = [];

if ($om_nivel == 1) {
    // Nível 1 vê todos, inclusive NULL
    $queryBatalhoes = "SELECT id FROM organizacoes_militares";
    $result = $conexao->query($queryBatalhoes);
    while ($r = $result->fetch_assoc()) {
        $batalhoesPermitidos[] = $r['id'];
    }
} elseif ($om_nivel == 2) {
    // Nível 2: própria OM + subordinadas
    $queryBatalhoes = $conexao->prepare("
        SELECT id_om_menor AS id FROM organizacoes_militares_sub WHERE id_om_maior = ?
        UNION SELECT ? AS id
    ");
    $queryBatalhoes->bind_param('ii', $om_id, $om_id);
    $queryBatalhoes->execute();
    $result = $queryBatalhoes->get_result();
    while ($r = $result->fetch_assoc()) {
        $batalhoesPermitidos[] = $r['id'];
    }
} elseif ($om_nivel == 3) {
    // Nível 3: apenas própria OM
    $batalhoesPermitidos[] = $om_id;
}

// ---------------------------
// FILTROS
// ---------------------------
$filtros = [];
$params = [];
$tipos = '';

// Filtros comuns
$campos = ['funcao', 'status'];
foreach ($campos as $campo) {
    if (!empty($_GET[$campo])) {
        $filtros[] = "u.$campo = ?";
        $params[] = $_GET[$campo];
        $tipos .= 's';
    }
}

// Filtro de texto
if (!empty($_GET['texto'])) {
    $texto = '%' . $_GET['texto'] . '%';
    $filtros[] = "(u.nome LIKE ? OR u.login LIKE ? OR u.email LIKE ?)";
    $params = array_merge($params, [$texto, $texto, $texto]);
    $tipos .= 'sss';
}

// ---------------------------
// APLICAÇÃO DA REGRA DE VISUALIZAÇÃO
// ---------------------------

// Nível 1: vê tudo, inclusive solicitações e batalhões nulos
if ($om_nivel != 1) {
    // Nível 2 e 3
    // Apenas usuários dentro dos batalhões permitidos
    if (!empty($batalhoesPermitidos)) {
        $placeholders = implode(',', array_fill(0, count($batalhoesPermitidos), '?'));
        $filtros[] = "u.batalhao IN ($placeholders)";
        $params = array_merge($params, $batalhoesPermitidos);
        $tipos .= str_repeat('i', count($batalhoesPermitidos));
    } else {
        // Se não houver batalhões permitidos, retorna vazio
        $filtros[] = "1 = 0";
    }

    // 🚫 Bloqueia solicitações para níveis 2 e 3
    $filtros[] = "u.solicitacao <> 'sim'";
}

// ---------------------------
// Filtro manual por batalhão
// ---------------------------
if (!empty($_GET['batalhao'])) {
    $filtroBatalhao = (int) $_GET['batalhao'];
    if (in_array($filtroBatalhao, $batalhoesPermitidos) || $om_nivel == 1) {
        $filtros[] = "u.batalhao = ?";
        $params[] = $filtroBatalhao;
        $tipos .= 'i';
    } else {
        $filtros[] = "1 = 0";
    }
}

// ---------------------------
// Paginação
// ---------------------------
$limite = isset($_GET['limite']) && is_numeric($_GET['limite']) ? (int) $_GET['limite'] : 10;
$pagina = 1;

if (isset($_GET['pagina']) && is_numeric($_GET['pagina'])) {
    $pagina = (int) $_GET['pagina'];
} elseif (isset($_POST['pagina']) && is_numeric($_POST['pagina'])) {
    $pagina = (int) $_POST['pagina'];
}

$pagina = max(1, $pagina);

$offset = ($pagina - 1) * $limite;

// ---------------------------
// Consulta total
// ---------------------------
$sqlTotal = "SELECT COUNT(*) AS total FROM usuarios u 
             LEFT JOIN organizacoes_militares om ON u.batalhao = om.id";

if (!empty($filtros)) {
    $sqlTotal .= " WHERE " . implode(" AND ", $filtros);
}

$stmtTotal = $conexao->prepare($sqlTotal);
if (!empty($params)) {
    $stmtTotal->bind_param($tipos, ...$params);
}
$stmtTotal->execute();
$totalRegistros = $stmtTotal->get_result()->fetch_assoc()['total'] ?? 0;
$totalPaginas = max(1, ceil($totalRegistros / $limite));

if ($pagina > $totalPaginas) {
    $pagina = $totalPaginas;
    $offset = ($pagina - 1) * $limite;
}

// ---------------------------
// Consulta principal
// ---------------------------
$sql = "SELECT 
            u.*, 
            om.nome AS nome_om, 
            om.abreviatura AS abreviatura_om,
            f.nome AS nome_funcao
        FROM usuarios u 
        LEFT JOIN organizacoes_militares om ON u.batalhao = om.id
        LEFT JOIN funcoes f ON u.funcao = f.id";


if (!empty($filtros)) {
    $sql .= " WHERE " . implode(" AND ", $filtros);
}

$sql .= " ORDER BY u.id DESC LIMIT ? OFFSET ?";

$paramsListagem = $params;
$tiposListagem = $tipos . 'ii';
$paramsListagem[] = $limite;
$paramsListagem[] = $offset;

$stmt = $conexao->prepare($sql);
$stmt->bind_param($tiposListagem, ...$paramsListagem);
$stmt->execute();
$result = $stmt->get_result();

// ---------------------------
// Lista de batalhões visíveis no filtro
// ---------------------------
if ($om_nivel == 1) {
    $batalhoesQuery = "SELECT id, nome, abreviatura FROM organizacoes_militares ORDER BY abreviatura ASC";
    $stmtB = $conexao->prepare($batalhoesQuery);
} else {
    $placeholders = implode(',', array_fill(0, count($batalhoesPermitidos), '?'));
    $batalhoesQuery = "SELECT id, nome, abreviatura FROM organizacoes_militares WHERE id IN ($placeholders) ORDER BY abreviatura ASC";
    $stmtB = $conexao->prepare($batalhoesQuery);
    if (!empty($batalhoesPermitidos)) {
        $stmtB->bind_param(str_repeat('i', count($batalhoesPermitidos)), ...$batalhoesPermitidos);
    }
}

$stmtB->execute();
$batalhoesArray = $stmtB->get_result()->fetch_all(MYSQLI_ASSOC);

// ==============================
// 🔹 Função de paginação - USUÁRIOS (AJAX)
// ==============================
function renderPaginacaoUsuarios($pagina, $totalPaginas, $limite, $queryString, $arquivo = 'includes/usuarios/listagem.php') {
    if ($totalPaginas <= 1) return '';

    $html  = '<div class="pagination-wrapper">';
    $html .= '<nav><ul class="pagination pagination-sm justify-content-center">';

    // ⏮ Primeira página
    if ($pagina > 1) {
        $url = "{$arquivo}?{$queryString}&pagina=1&limite={$limite}";
        $html .= "<li class='page-item'>
                    <a class='page-link paginacao-usuarios' href='#' data-page='{$url}'>&laquo;</a>
                  </li>";
    }

    // ◀ Anterior
    if ($pagina > 1) {
        $url = "{$arquivo}?{$queryString}&pagina=".($pagina - 1)."&limite={$limite}";
        $html .= "<li class='page-item'>
                    <a class='page-link paginacao-usuarios' href='#' data-page='{$url}'>&lsaquo;</a>
                  </li>";
    }

    // Intervalo de páginas (±4)
    $inicio = max(1, $pagina - 4);
    $fim    = min($totalPaginas, $pagina + 4);

    if ($inicio > 1) {
        $html .= "<li class='page-item disabled'><span class='page-link'>...</span></li>";
    }

    for ($i = $inicio; $i <= $fim; $i++) {
        $ativo = ($i == $pagina) ? 'active' : '';
        $url = "{$arquivo}?{$queryString}&pagina={$i}&limite={$limite}";
        $html .= "<li class='page-item {$ativo}'>
                    <a class='page-link paginacao-usuarios' href='#' data-page='{$url}'>{$i}</a>
                  </li>";
    }

    if ($fim < $totalPaginas) {
        $html .= "<li class='page-item disabled'><span class='page-link'>...</span></li>";
    }

    // ▶ Próxima
    if ($pagina < $totalPaginas) {
        $url = "{$arquivo}?{$queryString}&pagina=".($pagina + 1)."&limite={$limite}";
        $html .= "<li class='page-item'>
                    <a class='page-link paginacao-usuarios' href='#' data-page='{$url}'>&rsaquo;</a>
                  </li>";
    }

    // ⏭ Última página
    if ($pagina < $totalPaginas) {
        $url = "{$arquivo}?{$queryString}&pagina={$totalPaginas}&limite={$limite}";
        $html .= "<li class='page-item'>
                    <a class='page-link paginacao-usuarios' href='#' data-page='{$url}'>&raquo;</a>
                  </li>";
    }

    $html .= '</ul></nav></div>';
    return $html;
}

$paramsGET = $_GET;
unset($paramsGET['pagina']); // evita duplicação
$queryString = http_build_query($paramsGET);


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
        <h3 class="fw-bold mb-1">Listagem de Usuários</h3>
        <h6 class="text-muted">Usuários do Sistema</h6>
      </div>
     <?php if ($pode_cadastrar): ?>
      <div>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalCadastroUsuario">
          <i class="fa fa-user-plus me-1"></i> Cadastrar Usuário
        </button>
         <!-- Botão para abrir modal -->
<button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#modalImportarUsuarios">
  Importar Usuários
</button>
      </div>
          <?php endif; ?>
    </div>

    <!-- Filtro por função -->
<form method="GET" id="filtroUsuariosForm">
  <div class="row g-3 align-items-center mb-4">

<!-- Filtro por função -->
<div class="col-md-4">
  <label for="filtroFuncao" class="col-form-label fw-semibold">Filtrar por função:</label>
  <select id="filtroFuncao" name="funcao" class="form-select">
    <option value="">Todos</option>
    <?php
    // Busca todas as funções disponíveis
    $funcoes_result = $conexao->query("SELECT id, nome FROM funcoes ORDER BY id ASC");

    while ($funcao_row = $funcoes_result->fetch_assoc()) {
      $id_funcao = $funcao_row['id'];
      $nome_funcao = $funcao_row['nome'];

      // Mantém a seleção se houver filtro ativo
      $selected = (isset($_GET['funcao']) && $_GET['funcao'] == $id_funcao) ? 'selected' : '';

      echo "<option value='" . htmlspecialchars($id_funcao) . "' $selected>" . htmlspecialchars($nome_funcao) . "</option>";
    }
    ?>
  </select>
</div>


    <!-- Filtro por batalhão -->
    <div class="col-md-4">
      <label for="filtroBatalhao" class="col-form-label fw-semibold">Filtrar por batalhão:</label>
      <select id="filtroBatalhao" name="batalhao" class="form-select">
        <option value="">Todos</option>
        <?php foreach($batalhoesArray as $b): 
            $selected = (isset($_GET['batalhao']) && $_GET['batalhao'] == $b['id']) ? 'selected' : '';
        ?>
            <option value="<?= $b['id'] ?>" <?= $selected ?>>
                <?= htmlspecialchars($b['abreviatura']) ?> - <?= htmlspecialchars($b['nome']) ?>
            </option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="col-auto">
      <button type="button" class="btn btn-outline-primary" id="btnFiltrar">Filtrar</button>
    </div>
  </div>
</form>


<!-- Lista de usuários -->
<div class="card">
  <div class="card-body">

    <?php 
      // Nível do usuário
      $nivelUsuario = $_SESSION['usuario']['nivel'] ?? 3;
      $temSolicitacao = false;

      if ($nivelUsuario == 1 && $result->num_rows > 0) {
        foreach ($result as $r) {
          if ($r['solicitacao'] === 'sim') {
            $temSolicitacao = true;
            break;
          }
        }
        $result->data_seek(0);
      }
    ?>

    <?php if ($nivelUsuario == 1 && $temSolicitacao): ?>
      <div class="alert alert-warning text-center fw-semibold mb-4 shadow-sm">
        ⚠️ Existem solicitações de cadastro pendentes de aprovação.
      </div>
    <?php endif; ?>

    <?php if ($result->num_rows > 0): ?>
      <div class="list-group list-group-flush">

        <?php while ($row = $result->fetch_assoc()): ?>
          <?php
            $ativo = strtolower($row['status']) === 'sim';
            $statusTexto = $ativo ? 'Ativo' : 'Desativado';
            $statusClasse = $ativo ? 'success' : 'secondary';

            $classeSolicitacao = (
              $nivelUsuario == 1 && $row['solicitacao'] === 'sim'
            ) ? 'border-warning bg-warning-subtle' : 'border-light';
          ?>

          <div class="list-group-item rounded-3 mb-3 shadow-sm border <?= $classeSolicitacao ?>">
            <div class="d-flex align-items-center gap-3 flex-wrap">

              <!-- Foto -->
              <img 
                src="assets/fotoperfil/<?= $row['foto'] ?>" 
                class="rounded-circle" 
                width="60" 
                height="60" 
                alt="Foto">

              <!-- Dados -->
              <div class="flex-grow-1">
                <div class="fw-bold text-uppercase">
                  <?= $row['postograd'] ?> – <?= $row['nomeguerra'] ?>
                  <span class="badge bg-<?= $statusClasse ?> ms-2"><?= $statusTexto ?></span>

                  <?php if ($nivelUsuario == 1 && $row['solicitacao'] === 'sim'): ?>
                    <span class="badge bg-warning text-dark ms-2">Solicitação</span>
                  <?php endif; ?>
                </div>

                <div class="text-muted small">
                  <div><b>Função:</b> <?= $row['nome_funcao'] ?></div>
                  <div><b>Usuário:</b> <?= $row['usuario'] ?></div>
                  <div><b>OM:</b> <?= $row['nome_om'] ?></div>
                </div>
              </div>

              <!-- Ações -->
              <div class="d-flex gap-2 ms-auto">

                <button 
                  type="button"
                  class="btn btn-sm btn-outline-info"
                  title="Ver perfil"
                  data-bs-toggle="modal"
                  data-bs-target="#modalPerfilUsuario"
                  onclick="verPerfilUsuario(<?= (int)$row['id'] ?>)">
                  <i class="fa fa-user"></i>
                </button>

                <?php if ($pode_editar): ?>
                  <button 
                    type="button"
                    class="btn btn-sm btn-outline-primary"
                    title="Editar"
                    data-bs-toggle="modal"
                    data-bs-target="#modalEditarUsuario"
                    onclick="editarUsuario(<?= (int)$row['id'] ?>)">
                    <i class="fa fa-edit"></i>
                  </button>
                <?php endif; ?>

                <?php if ($pode_deletar): ?>
                  <button 
                    type="button"
                    class="btn btn-sm <?= $ativo ? 'btn-outline-warning' : 'btn-outline-success' ?>"
                    data-id="<?= (int)$row['id'] ?>"
                    data-status="<?= $ativo ? 'desativar' : 'ativar' ?>"
                    onclick="alterarStatusUsuario(this)"
                    title="<?= $ativo ? 'Desativar usuário' : 'Ativar usuário' ?>">
                    <i class="fa <?= $ativo ? 'fa-ban' : 'fa-check' ?>"></i>
                  </button>

                  <?php if (isset($_SESSION['funcao_id']) && $_SESSION['funcao_id'] === '1'): ?>
                    <button 
                      type="button"
                      class="btn btn-sm btn-outline-danger btn-deletar-usuario"
                      data-id="<?= (int)$row['id'] ?>"
                      title="Remover">
                      <i class="fa fa-times"></i>
                    </button>
                  <?php endif; ?>
                <?php endif; ?>

              </div>
            </div>
          </div>

        <?php endwhile; ?>
      </div>

      <!-- PAGINAÇÃO -->
      <?php if ($totalPaginas > 1): ?>
        <!-- Paginação usuários -->
<div class="paginacao mb-3">
  <?= renderPaginacaoUsuarios(
        $pagina,
        $totalPaginas,
        $limite,
        $queryString,
        'includes/usuarios/listagem.php'
     ); ?>
</div>

      <?php endif; ?>

    <?php else: ?>
      <div class="text-muted text-center py-4">
        Nenhum usuário encontrado.
      </div>
    <?php endif; ?>

  </div>
</div>


    <!-- Modal de Cadastro -->
    <div class="modal fade" id="modalCadastroUsuario" tabindex="-1" aria-labelledby="modalLabel" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="modalLabel">Cadastrar Usuário</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
          </div>
          <div class="modal-body">
              <form method="POST" enctype="multipart/form-data" id="form-usuario">
  <div class="text-center mb-4">
    <img src="assets/fotoperfil/66758a7f047ac.png" alt="Foto" class="avatar-img rounded-circle" style="width: 120px; height: 120px; object-fit: cover;">
    <div class="mt-2">
      <label for="foto" class="form-label"><b>Enviar foto</b></label>
      <input type="file" class="form-control" id="foto" name="foto" accept="image/*">
    </div>
  </div>

  <div class="mb-3">
    <label for="postograd" class="form-label">Posto/Grad</label>
    <select class="form-select rounded-pill shadow-sm" id="postograd" name="postograd" required>
      <option value="" disabled selected>Selecione o posto/grad</option>
      <option value="Gen">General</option>
      <option value="Cel">Coronel</option>
      <option value="TC">Tenente Coronel</option>
      <option value="Maj">Major</option>
      <option value="Capitão">Capitão</option>
      <option value="1º Ten">1º Tenente</option>
      <option value="2º Ten">2º Tenente</option>
      <option value="Asp Of">Aspirante a Oficial</option>
      <option value="Sten">Subtenente</option>
      <option value="1º Sgt">1º Sargento</option>
      <option value="2º Sgt">2º Sargento</option>
      <option value="3º Sgt">3º Sargento</option>
      <option value="Cb">Cabo</option>
      <option value="Sd">Soldado</option>
    </select>
  </div>

  <div class="mb-3">
    <label for="nomeguerra" class="form-label">Nome de Guerra</label>
    <input type="text" class="form-control" id="nomeguerra" name="nomeguerra" required>
  </div>
  <div class="mb-3">
    <label for="nomecompleto" class="form-label">Nome Completo</label>
    <input type="text" class="form-control" id="nomecompleto" name="nomecompleto" required>
  </div>

  <div class="mb-3">
    <label for="usuario" class="form-label">Usuário</label>
    <input type="text" class="form-control" id="usuario" name="usuario" required>
  </div>

<div class="mb-3">
  <label for="funcao" class="form-label fw-semibold">Função</label>
  <select class="form-select rounded-pill shadow-sm" id="funcao" name="funcao" required>
      <option value="" disabled selected>Selecione uma função</option>
      <?php
      // Busca todas as funções ativas
      $sql = "SELECT id, nome FROM funcoes ORDER BY id ASC";
      $res = $conexao->query($sql);

      // Se for um formulário de edição, mantém a seleção atual
      $funcaoSelecionada = $usuario['funcao'] ?? ($_POST['funcao'] ?? '');

      if ($res && $res->num_rows > 0) {
          while ($row = $res->fetch_assoc()) {
              $id = $row['id'];
              $nome = htmlspecialchars($row['nome']);
              $selected = ($id == $funcaoSelecionada) ? 'selected' : '';
              echo "<option value='$id' $selected>$nome</option>";
          }
      }
      ?>
  </select>
</div>


                  
<div class="mb-3">
    <label for="batalhao" class="form-label">Batalhão</label>
    <select class="form-select rounded-pill shadow-sm" id="batalhao" name="batalhao" required>
        <option value="" disabled selected>Selecione o batalhão</option>
        <?php foreach($batalhoesArray as $b): ?>
            <option value="<?= $b['id'] ?>">
                <?= htmlspecialchars($b['abreviatura']) ?> - <?= htmlspecialchars($b['nome']) ?>
            </option>
        <?php endforeach; ?>
    </select>
</div>

  <div class="mb-3">
    <label for="senha" class="form-label">Senha</label>
    <input type="password" class="form-control" id="senha" name="senha" required>
  </div>

  <button type="submit" class="btn btn-success">Salvar</button>
</form>
              
              <input type="hidden" id="edit-id" name="id">
              

          </div>
        </div>
      </div>
    </div>
    <!-- Modal de EdiÃ§Ã£o -->
      
      <div class="modal fade" id="modalEditarUsuario" tabindex="-1" aria-labelledby="modalEditarLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      <form id="form-editar-usuario" enctype="multipart/form-data">
        <div class="modal-header">
          <h5 class="modal-title" id="modalEditarLabel">Editar Usuário</h5>
        </div>
        <div class="modal-body">
          <input type="hidden" name="id" id="editar-id">

          <div class="mb-3 text-center">
            <img id="editar-foto-preview" src="" class="rounded-circle" style="width:120px; height:120px; object-fit:cover;">
            <div class="mt-2">
              <label for="editar-foto">Foto</label>
              <input type="file" class="form-control" id="editar-foto" name="foto">
            </div>
          </div>

          <div class="mb-3">
            <label>Posto/Grad</label>
            <input type="text" class="form-control" id="editar-postograd" name="postograd" required>
          </div>

          <div class="mb-3">
            <label>Nome de Guerra</label>
            <input type="text" class="form-control" id="editar-nomeguerra" name="nomeguerra" required>
          </div>

   <div class="mb-3">
            <label>Nome Completo</label>
           <input type="text" class="form-control" id="editar-nomecompleto" name="nomecompleto" required>
          </div>
            
          <div class="mb-3">
            <label>Usuário</label>
            <input type="text" class="form-control" id="editar-usuario" name="usuario" required>
          </div>

           <div class="mb-3">
  <label for="editar-funcao" class="form-label fw-semibold">Função</label>
  <select class="form-select rounded-pill shadow-sm" id="editar-funcao" name="funcao" required>
      <option value="" disabled>Selecione uma função</option>
      <?php
      // Busca todas as funções disponíveis
      $sqlFuncoes = "SELECT id, nome FROM funcoes ORDER BY id ASC";
      $resFuncoes = $conexao->query($sqlFuncoes);

      // Função atualmente atribuída ao usuário
      $funcaoAtual = $usuario['funcao'] ?? '';

      if ($resFuncoes && $resFuncoes->num_rows > 0) {
          while ($row = $resFuncoes->fetch_assoc()) {
              $id = $row['id'];
              $nome = htmlspecialchars($row['nome']);
              $selected = ($id == $funcaoAtual) ? 'selected' : '';
              echo "<option value='$id' $selected>$nome</option>";
          }
      }
      ?>
  </select>
</div>

            
<div class="mb-3">
    <label for="editar-batalhao" class="form-label">Batalhão</label>
    <select class="form-select rounded-pill shadow-sm" id="editar-batalhao" name="batalhao" required>
        <option value="" disabled selected>Selecione o batalhão</option>
        <?php foreach($batalhoesArray as $b): ?>
            <option value="<?= $b['id'] ?>">
                <?= htmlspecialchars($b['abreviatura']) ?> - <?= htmlspecialchars($b['nome']) ?>
            </option>
        <?php endforeach; ?>
    </select>
</div>


          <div class="mb-3">
            <label>Senha (preencha se quiser alterar)</label>
            <input type="password" class="form-control" id="editar-senha" name="senha">
          </div>

          <div class="mb-3">
            <label>Status</label>
            <select class="form-select" id="editar-status" name="status">
              <option value="sim">Ativo</option>
              <option value="não">Desativado</option>
            </select>
          </div>

        </div>
        <div class="modal-footer">
          <button type="submit" class="btn btn-success">Salvar Alterações</button>
        </div>
      </form>
    </div>
  </div>
</div>
<!-- Modal do perfil do usuário -->
<div class="modal fade" id="modalPerfilUsuario" tabindex="-1" aria-labelledby="modalPerfilUsuarioLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-centered">
    <div class="modal-content bg-dark text-white">
      
      <div class="modal-header">
        <h5 class="modal-title">Perfil do Usuário</h5>
        <div class="ms-auto d-flex align-items-center">
          <a href="#" id="btnExportarExcel" class="btn btn-success btn-sm me-2" target="_blank">📄 Excel</a>
          <a href="#" id="btnExportarPDF" class="btn btn-danger btn-sm me-2" target="_blank">📄 PDF</a>
        
        </div>
      </div>

      <div class="modal-body">
        
        <!-- Foto do usuário -->
        <div class="text-center mb-4">
          <img id="perfil-foto" src="" class="rounded-circle shadow" width="120" height="120" alt="Foto do usuário">
        </div>

       <!-- Informações básicas -->
<div class="row mb-4 text-center">
  <div class="col-md-4">
    <strong>Posto/Grad:</strong>
    <span id="usuario-posto"></span>
  </div>
  <div class="col-md-4">
    <strong>Nome de Guerra:</strong>
    <span id="usuario-nome-guerra"></span>
  </div>
  <div class="col-md-4">
    <strong>Função:</strong>
    <span id="usuario-funcao"></span>
  </div>
</div>


        <hr>

        <!-- Logs -->
        <h6 class="mb-3">Logs de Alterações</h6>

        <!-- Filtros -->
        <div class="row mb-3 g-2">
          <div class="col-md-3">
            <label for="filtro-data" class="form-label visually-hidden">Filtrar por data</label>
            <input type="date" id="filtro-data" class="form-control form-control-sm" placeholder="Filtrar por data (dd/mm/aaaa)">
          </div>
          <div class="col-md-3">
            <label for="filtro-acao" class="form-label visually-hidden">Filtrar por ação</label>
            <select id="filtro-acao" class="form-select form-select-sm">
              <option value="">Todas as ações</option>
              <option value="Editar usuário">Editar usuário</option>
              <option value="Alterar status de usuário">Alterar status de usuário</option>
              <option value="Cadastrar usuário">Cadastrar usuário</option>
              <option value="Deletar usuário">Deletar usuário</option>
              <!-- Adicione outras ações conforme necessário -->
            </select>
          </div>
          <div class="col-md-6">
            <label for="filtro-palavra" class="form-label visually-hidden">Filtrar por palavra-chave</label>
            <input type="text" id="filtro-palavra" class="form-control form-control-sm" placeholder="Palavra-chave (descrição, IP, navegador)">
          </div>
        </div>

        <!-- Tabela de Logs -->
        <div class="table-responsive">
          <table class="table table-sm table-striped table-hover text-white" id="tabela-logs">
            <thead class="table-dark">
              <tr>
                <th>Data</th>
                <th>Ação</th>
                <th>Descrição</th>
                <th>IP</th>
                <th>Navegador</th>
              </tr>
            </thead>
            <tbody></tbody>
          </table>
        </div>

        <!-- Paginação -->
        <div class="d-flex justify-content-end mt-3">
          <div class="paginacao-logs"></div>
        </div>

      </div>
    </div>
  </div>
</div>
      <!-- Modal de Importação de Usuários -->
<div class="modal fade" id="modalImportarUsuarios" tabindex="-1" aria-labelledby="modalImportarUsuariosLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
        <form id="formImportar" action="#" method="POST" enctype="multipart/form-data">
        <div class="modal-header">
          <h5 class="modal-title" id="modalImportarUsuariosLabel">Importar Usuários via Planilha</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label for="arquivo" class="form-label">Selecione a planilha (.xlsx)</label>
            <input type="file" name="arquivo" id="arquivo" accept=".xlsx" class="form-control" required>
          </div>
          <div class="mt-3">
            <a href="includes/usuarios/Cadastrar_Usuarios.xlsx" class="btn btn-link">
              📥 Baixar modelo de planilha
            </a>
          </div>
        </div>
        <div class="modal-footer">
          <button type="submit" class="btn btn-primary">Importar</button>
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
        </div>
      </form>
    </div>
  </div>
</div>
      
  </div>
</div>
<!-- Script da página de Cadastro de Vtr/Eqp -->
<script>
    window.funcaoInicializacao = 'inicializarUsuarios';
</script>