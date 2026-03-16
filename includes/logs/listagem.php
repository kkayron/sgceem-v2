<?php
header('Content-Type: text/html; charset=utf-8');
session_start();
include_once('../../conexao/config.php');

$usuarios = [];
$sql = "SELECT id, postograd, nomeguerra FROM usuarios ORDER BY postograd, nomeguerra";
$result = $conexao->query($sql);
while ($row = $result->fetch_assoc()) {
  $usuarios[] = $row;
}
?>

<style>
.btn-group .btn {
  border-radius: 20px;
  transition: all 0.3s ease;
    margin-left: 10px;
</style>

<div class="container">
  <div class="page-inner">
    <div class="d-flex justify-content-between align-items-center py-3">
      <div>
        <h3 class="fw-bold mb-1">Registro de atividades do sistema</h3>
        <h6 class="text-muted">Atividades no sistema</h6>
      </div>
        <div class="btn-group">
        <form id="form-exportar-pdf" action="includes/logs/exportar_logs_pdf.php" method="POST" target="_blank" >
  <input type="hidden" name="data" id="export-data">
  <input type="hidden" name="acao" id="export-acao">
  <input type="hidden" name="palavra" id="export-palavra">
  <input type="hidden" name="usuario" id="export-usuario">
  <button type="submit" class="btn btn-danger">Exportar PDF</button>
</form>
              <form id="form-exportar-excel" action="includes/logs/exportar_logs_excel.php" method="POST" target="_blank">
  <input type="hidden" name="data" id="export-data-excel">
  <input type="hidden" name="acao" id="export-acao-excel">
  <input type="hidden" name="palavra" id="export-palavra-excel">
  <input type="hidden" name="usuario" id="export-usuario-excel">
  <button type="submit" class="btn btn-success">Exportar Excel</button>
</form>
        </div>
    </div>

    <!-- Lista de logs -->
    <div class="card">
      <div class="card-body">
        <div class="card">
          <div class="card-header">
            <h4 class="card-title">Logs do sistema</h4>
              
          </div>
          <div class="card-body">

            <!-- Filtros -->
            <div class="row mb-3 g-2">
              <div class="col-md-3">
                <input type="date" id="filtro-data-geral" class="form-control form-control-sm" placeholder="Filtrar por data (dd/mm/aaaa)">
              </div>
                <div class="col-md-3">
  <select id="filtro-usuario-geral" class="form-select form-select-sm">
    <option value="">Todos os usuários</option>
    <?php foreach ($usuarios as $usuario): ?>
      <?php $nomeCompleto = $usuario['postograd'] . ' ' . $usuario['nomeguerra']; ?>
      <option value="<?= htmlspecialchars($nomeCompleto) ?>"><?= htmlspecialchars($nomeCompleto) ?></option>
    <?php endforeach; ?>
  </select>
</div>
              <div class="col-md-3">
                <select id="filtro-acao-geral" class="form-select form-select-sm">
                  <option value="">Todas as ações</option>
                  <option value="Editar usuário">Editar usuário</option>
                  <option value="Alterar status de usuário">Alterar status de usuário</option>
                  <option value="Cadastrar usuário">Cadastrar usuário</option>
                  <option value="Deletar usuário">Deletar usuário</option>
                  <option value="Cadastrar Frota">Cadastrar Frota</option>
                </select>
              </div>
              <div class="col-md-3">
                <input type="text" id="filtro-palavra-geral" class="form-control form-control-sm" placeholder="Palavra-chave (descrição, IP, navegador)">
              </div>
                
            </div>
            <!-- Tabela de logs -->
              
            <div class="table-responsive">
              <table class="table table-sm table-striped table-hover text-white" id="tabela-logs">
                <thead class="table-dark">
                  <tr>
                    <th>Data</th>
                    <th>Usuário</th>
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
  </div>
</div>
<!-- Script da página de Cadastro de Vtr/Eqp -->
<script>
    window.funcaoInicializacao = 'inicializarLogs';
</script>
