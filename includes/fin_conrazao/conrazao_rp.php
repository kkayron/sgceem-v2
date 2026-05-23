<?php
header('Content-Type: text/html; charset=utf-8');
session_start();

require_once '../api/seguranca.php';

$permissoes = verificarPermissao([27]);

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

// Obtem o filtro da URL e decodifica
$filtro_funcao = isset($_GET['funcao']) ? urldecode($_GET['funcao']) : 'todos';

// Aplica o filtro na consulta
if ($filtro_funcao !== 'todos') {
    $stmt = $conexao->prepare("SELECT * FROM usuarios WHERE funcao = ? ORDER BY id DESC");
    $stmt->bind_param("s", $filtro_funcao);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    $sql = "SELECT * FROM usuarios ORDER BY id DESC";
    $result = $conexao->query($sql);
}
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
        <h3 class="fw-bold mb-1">Enviar CONRAZAO dos anos anteriores (Resto a pagar)</h3>
        <h6 class="text-muted">Conta contábil: <b>631100000</b></h6>
      </div>
    </div>


    <!-- Lista de usuários -->
    <div class="card shadow-sm border-0 mb-4">
  <div class="card-body">
 <form method="POST" enctype="multipart/form-data" id="form-conrazao-rp">
  <div class="mb-3">
    <label class="form-label fw-semibold">Arquivo CONRAZAO RP (.txt)</label>
    <input type="file" name="arquivo" accept=".txt" class="form-control" required>
  </div>
  <div class="text-end">
	  
	  <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <button type="submit" class="btn btn-primary">Enviar Dados</button>
  </div>
</form>

<hr>

<h5 class="fw-bold mt-4">Resultado do envio</h5>
<div id="resultado-envio-conrazao-rp"></div>
  </div>
</div>

   
      
  </div>
</div>

<script>
    window.funcaoInicializacao = 'inicializarConrazaoRP';    
</script>
