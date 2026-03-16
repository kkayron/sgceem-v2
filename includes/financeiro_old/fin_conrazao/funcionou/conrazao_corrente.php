<?php
header('Content-Type: text/html; charset=utf-8');
session_start();
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
        <h3 class="fw-bold mb-1">Enviar CONRAZAO do ano corrente</h3>
        <h6 class="text-muted">Página de upload</h6>
      </div>
    </div>


    <!-- Lista de usuários -->
    <div class="card shadow-sm border-0 mb-4">
  <div class="card-body">
    <h5 class="card-title">Importar Saldos SIAFI (Arquivo .TXT)</h5>
    <form action="includes/fin_conrazao/importar_corrente.php" method="POST" enctype="multipart/form-data">
      <div class="row g-3 align-items-center">
        <div class="col-md-9">
          <label for="arquivoSiafi" class="form-label fw-semibold">Selecionar Arquivo TXT</label>
          <input type="file" class="form-control" name="arquivo" id="arquivoSiafi" accept=".txt" required>
        </div>
        <div class="col-md-3 mt-4 text-end">
          <button type="submit" class="btn btn-success">Importar</button>
        </div>
      </div>
    </form>
  </div>
</div>

   
      
  </div>
</div>
