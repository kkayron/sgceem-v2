<?php session_start(); ?>
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

<style>
/* ======== ESTILO IDÊNTICO AO PAINEL SGCEEM ======== */

body {
    margin: 0;
    font-family: "Inter", sans-serif;
    background: #f4f6f9; /* igual ao painel */
    display: flex;
    align-items: center;
    justify-content: center;
    height: 100vh;
}

/* Card central do login */
.login-card {
    width: 100%;
    max-width: 420px;
    background: #fff;
    border-radius: 14px;
    box-shadow: 0 8px 20px rgba(0,0,0,0.12);
    overflow: hidden;
    animation: fadeIn 0.4s ease;
}

.login-header {
    background: #0f1f3d; /* mesmo azul da sidebar */
    color: #fff;
    text-align: center;
    padding: 32px 20px;
}

.login-header img {
    height: 55px;
    margin-bottom: 10px;
}

.login-header h1 {
    margin: 0;
    font-size: 25px;
    font-weight: 700;
}

.login-header span {
    font-size: 13px;
    opacity: 0.8;
}

.login-body {
    padding: 35px;
    text-align: center;
}

input.form-control {
    height: 45px;
    border-radius: 10px;
    font-size: 15px;
}

input.form-control:focus {
    border-color: #1765ff;
}

.btn-login {
    width: 100%;
    height: 45px;
    border-radius: 10px;
    background: #1765ff;
    color: white;
    border: none;
    font-size: 16px;
    margin-top: 10px;
    font-weight: 600;
}

.btn-login:hover {
    background: #0f4dcc;
}

/* Botão solicitar cadastro */
.btn-secondary-custom {
    width: 100%;
    margin-top: 12px;
    border-radius: 10px;
    padding: 12px;
}

/* Alert erro */
.alert-custom {
    background: #ffe1e1;
    border: 1px solid #ffb3b3;
    color: #b30000;
    padding: 10px;
    border-radius: 8px;
}

/* Rodapé */
.footer {
    margin-top: 20px;
    font-size: 12px;
    color: #777;
}

/* Animação */
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(25px); }
    to   { opacity: 1; transform: translateY(0); }
}
</style>

</head>

<body>

<div class="login-card">

    <!-- Cabeçalho -->
    <div class="login-header">
        <img src="assets/img/kaiadmin/logo_light.png">
        
    </div>

    <!-- Formulário -->
    <div class="login-body">
        <form action="valida_login.php" method="POST">

            <?php if(isset($_SESSION['login_erro'])): ?>
                <div class="alert-custom mb-3"><?= $_SESSION['login_erro']; ?></div>
            <?php unset($_SESSION['login_erro']); endif; ?>

            <input type="text" name="usuario" class="form-control mb-3" placeholder="Usuário" required>
            <input type="password" name="senha" class="form-control mb-3" placeholder="Senha" required>

            <button type="submit" class="btn-login">Entrar</button>

            <button type="button" class="btn btn-outline-secondary btn-secondary-custom"
                data-bs-toggle="modal" data-bs-target="#modalSolicitarCadastro">
                Solicitar Cadastro
            </button>

            <div class="footer">
                © SGCEEM 2024 - Desenvolvido pelo 1º Ten <b>Felipe Alves</b>
            </div>
        </form>
    </div>

</div>

<!-- Modal Solicitação Cadastro -->
<div class="modal fade" id="modalSolicitarCadastro" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">

      <div class="modal-header">
        <h5 class="modal-title">Solicitar Cadastro</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <div class="modal-body">

        <form method="POST" enctype="multipart/form-data" action="solicitar_cadastro.php">

          <div class="text-center mb-4">
            <img src="assets/fotoperfil/default.png" class="rounded-circle" width="110" height="110">
            <div class="mt-2">
              <input type="file" class="form-control" name="foto" accept="image/*">
            </div>
          </div>

          <div class="row">
            <div class="col-md-6 mb-3">
              <label>Posto/Graduação</label>
              <select class="form-select" name="postograd" required>
                <option disabled selected>Selecione</option>
                <option>Gen</option><option>Cel</option><option>TC</option><option>Maj</option>
                <option>Capitão</option><option>1º Ten</option><option>2º Ten</option>
                <option>Asp Of</option><option>Sten</option><option>1º Sgt</option>
                <option>2º Sgt</option><option>3º Sgt</option><option>Cb</option><option>Sd</option>
              </select>
            </div>

            <div class="col-md-6 mb-3">
              <label>Função</label>
              <select class="form-select" name="funcao" required>
                <option disabled selected>Selecione</option>
                <option>Gerente de Frota</option>
                <option>Cmt Cia E Eqp Mnt</option>
                <option>Ch Seç Ctrl</option>
                <option>Ch Almox Peças</option>
                <option>Ch STA</option>
                <option>Adj/Aux</option>
                <option>Leitor</option>
              </select>
            </div>
          </div>

          <div class="mb-3">
            <label>Batalhão</label>
            <select class="form-select" name="batalhao" required>
              <option disabled selected>Selecione o Batalhão</option>
              <?php while ($b = $batalhoes->fetch_assoc()): ?>
                <option value="<?= $b['id'] ?>">
                    <?= $b['abreviatura'] ?> - <?= $b['nome'] ?>
                </option>
              <?php endwhile; ?>
            </select>
          </div>

          <div class="mb-3">
            <label>Nome de Guerra</label>
            <input type="text" class="form-control" name="nomeguerra" required>
          </div>

          <div class="mb-3">
            <label>Nome Completo</label>
            <input type="text" class="form-control" name="nomecompleto" required>
          </div>

          <div class="mb-3">
            <label>Usuário</label>
            <input type="text" class="form-control" name="usuario" required>
          </div>

          <div class="mb-3">
            <label>Senha</label>
            <input type="password" class="form-control" name="senha" required>
          </div>

          <input type="hidden" name="status" value="não">
          <input type="hidden" name="solicitacao" value="sim">

          <div class="text-end">
            <button type="submit" class="btn btn-success px-4">Enviar Solicitação</button>
          </div>

        </form>
      </div>
    </div>
  </div>
</div>


<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
