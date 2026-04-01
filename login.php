<?php session_start(); ?>
<?php
$manutencao = false;

if ($manutencao) {
    header("Location: manutencao.php");
    exit;
}
?>
<?php
require_once 'conexao/config.php';
$batalhoes = $conexao->query("SELECT id, nome, abreviatura FROM organizacoes_militares ORDER BY abreviatura ASC");
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login - SGCEEM</title>

<!-- Bootstrap -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<!-- Font Awesome -->
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">

<style>
  * { box-sizing: border-box; }

  body { 
    margin: 0; 
    font-family: "Segoe UI", sans-serif; 
    background: linear-gradient(135deg, #e8ebf0, #f5f7fa); 
    height: 100vh; 
    display: flex; 
    overflow: hidden; 
  }

  /* Painel esquerdo */
  .left-panel { 
    flex: 1; 
    position: relative; 
    display: flex; 
    flex-direction: column; 
    justify-content: center; 
    padding: 30px 25px; /* menor padding */
    color: white; 
    z-index: 1; 
  }
  .left-panel::before { 
    content: ""; 
    position: absolute; 
    top:0; left:0; width:100%; height:100%; 
    background: url("imgnovas/tablet_oficina.jpeg") center/cover no-repeat; 
    filter: brightness(0.45); 
    z-index: 0; 
  }
  .left-panel * { position: relative; z-index: 2; }
  .left-panel h4 { 
    font-size: 28px; /* menor para caber na tela */
    font-weight: 700; 
    text-shadow: 0 3px 10px rgba(0,0,0,0.5); 
    margin-bottom: 15px;
  }
  .left-panel p { 
    font-size: 15px; 
    max-width: 380px; 
    color: rgba(255,255,255,0.9); 
    text-shadow: 0 2px 8px rgba(0,0,0,0.4); 
    margin-bottom: 10px;
  }

  /* Cards de funcionalidades */
  .servicos { margin-top: 30px; }
  .servicos h5 {
    color: #fff;
    font-weight: 600;
    text-shadow: 0 2px 6px rgba(0,0,0,0.5);
    margin-bottom: 15px;
  }

  .cards-container {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
    gap: 15px;
  }

  .card-servico {
    background: rgba(255,255,255,0.12);
    border: 1px solid rgba(255,255,255,0.2);
    backdrop-filter: blur(6px);
    border-radius: 16px;
    text-align: center;
    padding: 20px 12px;
    color: #fff;
    transition: all 0.3s ease;
  }

  .card-servico:hover {
    transform: translateY(-5px);
    background: rgba(255,255,255,0.18);
    box-shadow: 0 6px 20px rgba(0,0,0,0.3);
  }

  .card-servico i {
    font-size: 28px;
    margin-bottom: 10px;
    opacity: 0.95;
  }

  .card-servico h6 {
    font-weight: 600;
    font-size: 14px;
    margin-bottom: 6px;
  }

  .card-servico p {
    font-size: 12px;
    color: rgba(255,255,255,0.85);
  }

  /* Painel direito */
  .right-panel { 
    flex: 1; 
    display: flex; 
    align-items: center; 
    justify-content: center; 
    padding: 30px 25px; 
    background: linear-gradient(135deg, #e8ebf0, #f5f7fa); 
  }

  .login-box { 
    width: 100%; 
    max-width: 380px; 
    background: rgba(255,255,255,0.95); 
    padding: 35px 30px; 
    border-radius: 20px; 
    box-shadow: 0 8px 24px rgba(0,0,0,0.08); 
    text-align: center; 
    animation: fadeSlide 0.8s ease-out; 
  }
  .login-box h2 { 
    color: #004aad; 
    font-size: 26px; 
    margin-bottom: 20px; 
  }
  .form-control { border-radius: 10px; padding: 12px 14px; }
  .btn-primary { 
    background-color: #004aad; 
    border: none; 
    width: 100%; 
    border-radius: 10px; 
    padding: 12px; 
    margin-top: 10px; 
    transition: all 0.3s ease; 
  }
  .btn-primary:hover { 
    background-color: #003a8a; 
    transform: translateY(-1px); 
  }
  .btn-outline-secondary { 
    width: 100%; 
    border-radius: 10px; 
    padding: 12px; 
    margin-top: 10px; 
  }
  .alert { 
    background: #ff6b6b; 
    color: white; 
    border-radius: 8px; 
    margin-bottom: 15px; 
    padding: 10px; 
    font-weight: 500; 
  }
  .footer { 
    margin-top: 25px; 
    font-size: 0.8rem; 
    color: #777; 
  }

  @keyframes fadeSlide { 
    from { opacity: 0; transform: translateY(25px); } 
    to { opacity: 1; transform: translateY(0); } 
  }

  /* Responsividade */
  @media (max-width: 1200px) {
    .left-panel h4 { font-size: 24px; }
    .cards-container { grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); }
  }

  @media (max-width: 900px) {
    body { flex-direction: column; }
    .left-panel { display: none; }
  }
</style>

</head>

<body>

<div class="left-panel">
  <h4 class="mb-3">Sistema de Gerenciamento da Cia E Eqp Mnt</h4>
  <p>Otimizado para controle das viaturas, máquinas e equipes técnicas dos batalhões de engenharia.</p>
  <p>Acesse sua conta ou solicite seu cadastro.</p>

  <!-- Cards Modernos -->
  <div class="servicos mt-4">
    <h5 class="fw-semibold mb-3">Principais Funcionalidades</h5>

    <div class="cards-container">
      <div class="card-servico">
        <i class="fas fa-tools text-info"></i>
        <h6>Controle de Ordens de Serviço</h6>
        <p>Gerencie abertura, execução e finalização das manutenções com eficiência.</p>
      </div>

      <div class="card-servico">
        <i class="fas fa-file-invoice-dollar text-success"></i>
        <h6>Controle de Empenhos</h6>
        <p>Monitore empenhos e despesas com total transparência e organização.</p>
      </div>

      <div class="card-servico">
        <i class="fas fa-truck text-warning"></i>
        <h6>Gerenciamento da Frota</h6>
        <p>Supervisione viaturas, máquinas e equipamentos sob sua gestão.</p>
      </div>

      <div class="card-servico">
        <i class="fas fa-users-cog text-danger"></i>
        <h6>Gestão de Equipes</h6>
        <p>Administre equipes técnicas e controle funções e permissões.</p>
      </div>

      <div class="card-servico">
        <i class="fas fa-chart-line text-primary"></i>
        <h6>Relatórios Operacionais</h6>
        <p>Gere relatórios e gráficos detalhados sobre desempenho e consumo.</p>
      </div>
        <div class="card-servico">
  <i class="fas fa-warehouse text-warning"></i>
  <h6>Controle de Estoque</h6>
  <p>Gerencie peças, materiais e insumos no almoxarifado com relatórios e movimentações detalhadas.</p>
</div>
    </div>
  </div>
</div>

<div class="right-panel">
  <form action="valida_login.php" method="POST" class="login-box">
    <img src="imagens/icon_png_mnt_2.png" width="71" height="59" alt=""/>
    <h2>SGCEEM</h2>
    <p>Acesso Restrito</p>

    <?php if(isset($_SESSION['cadastro_sucesso'])): ?>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
    Swal.fire({
        icon: 'success',
        title: 'Sucesso',
        text: '<?= $_SESSION['cadastro_sucesso'] ?>',
        confirmButtonText: 'OK'
    });
    </script>
    <?php unset($_SESSION['cadastro_sucesso']); endif; ?>

    <?php if(isset($_SESSION['cadastro_erro'])): ?>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
    Swal.fire({
        icon: 'error',
        title: 'Erro',
        text: '<?= $_SESSION['cadastro_erro'] ?>',
        confirmButtonText: 'OK'
    });
    </script>
    <?php unset($_SESSION['cadastro_erro']); endif; ?>

    <?php if(isset($_SESSION['login_erro'])): ?>
      <div class="alert"><?php echo $_SESSION['login_erro']; unset($_SESSION['login_erro']); ?></div>
    <?php endif; ?>

    <div class="mb-3"><input type="text" name="usuario" class="form-control" placeholder="Usuário" required></div>
    <div class="mb-3"><input type="password" name="senha" class="form-control" placeholder="Senha" required></div>
    <input type="submit" class="btn btn-primary" value="Entrar">

    <button type="button" class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#modalSolicitarCadastro">
      Solicitar Cadastro
    </button>

    <div class="footer">© SGCEEM 2024 - Desenvolvido pelo 1º Ten <b>Felipe Alves</b> de Carvalho - TU Eng 2020 AMAN</div>
  </form>
</div>

<!-- Modal de Solicitação -->
<div class="modal fade" id="modalSolicitarCadastro" tabindex="-1" aria-labelledby="modalCadastroLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="modalCadastroLabel">Solicitar Cadastro</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
      </div>
      <div class="modal-body">
        <form method="POST" enctype="multipart/form-data" action="solicitar_cadastro.php">
          <div class="text-center mb-4">
            <img src="assets/fotoperfil/default.png" alt="Foto" class="rounded-circle" style="width:100px;height:100px;object-fit:cover;">
            <div class="mt-2">
              <label for="foto" class="form-label"><b>Enviar foto</b></label>
              <input type="file" class="form-control" id="foto" name="foto" accept="image/*">
            </div>
          </div>

          <div class="row">
            <div class="col-md-6 mb-3">
              <label for="postograd" class="form-label">Posto/Graduação</label>
              <select class="form-select" id="postograd" name="postograd" required>
                <option value="" disabled selected>Selecione</option>
                <option>Gen</option><option>Cel</option><option>TC</option><option>Maj</option>
                <option>Capitão</option><option>1º Ten</option><option>2º Ten</option><option>Asp Of</option>
                <option>Sten</option><option>1º Sgt</option><option>2º Sgt</option><option>3º Sgt</option>
                <option>Cb</option><option>Sd</option>
              </select>
            </div>

              <div class="col-md-6 mb-3">
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
            
          </div>

          <div class="mb-3">
            <label for="batalhao" class="form-label">Batalhão</label>
            <select class="form-select" id="batalhao" name="batalhao" required>
              <option value="" disabled selected>Selecione o Batalhão</option>
              <?php while ($b = $batalhoes->fetch_assoc()): ?>
                <option value="<?= $b['id'] ?>"><?= htmlspecialchars($b['abreviatura']) ?> - <?= htmlspecialchars($b['nome']) ?></option>
              <?php endwhile; ?>
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
            <label for="senha" class="form-label">Senha</label>
            <input type="password" class="form-control" id="senha" name="senha" required>
          </div>

          <input type="hidden" name="status" value="não">
          <input type="hidden" name="solicitacao" value="sim">

          <div class="text-end">
            <button type="submit" class="btn btn-success">Enviar Solicitação</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
