<?php session_start(); ?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login - SGCEEM</title>

<!-- Bootstrap -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

<style>
  * { box-sizing: border-box; }

  body {
    margin: 0;
    font-family: "Segoe UI", sans-serif;
    background: linear-gradient(135deg, #e8ebf0, #f5f7fa);
    height: 100vh;
    display: flex;
  }

  .left-panel {
    flex: 1;
    background: radial-gradient(circle at top left, #004aad, #002b5b);
    color: white;
    display: flex;
    flex-direction: column;
    justify-content: center;
    padding: 60px;
  }

  .left-panel img {
    width: 60px;
    height: 60px;
    margin-bottom: 20px;
  }

  .left-panel h1 {
    font-size: 40px;
    font-weight: 700;
    margin-bottom: 10px;
  }

  .left-panel p {
    font-size: 16px;
    max-width: 400px;
    color: rgba(255,255,255,0.8);
  }

  .right-panel {
    flex: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 40px;
  }

  .login-box {
    width: 100%;
    max-width: 380px;
    background: rgba(255,255,255,0.9);
    padding: 40px 35px;
    border-radius: 20px;
    box-shadow: 0 8px 24px rgba(0,0,0,0.08);
    text-align: center;
  }

  .login-box h2 {
    color: #004aad;
    font-size: 28px;
    margin-bottom: 25px;
  }

  .form-control {
    border-radius: 10px;
    padding: 12px 14px;
  }

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
</style>
</head>

<body>

<div class="left-panel">
  <img src="imagens/icon_png_mnt.png" alt="SGCEEM">
  <h1>SGCEEM</h1>
  <p>Sistema de Gerenciamento da Companhia de Equipamentos e Engenharia de Manutenção.  
  Acesse sua conta ou solicite seu cadastro.</p>
</div>

<div class="right-panel">
  <form action="valida_login.php" method="POST" class="login-box">
    <h2>Acesso ao Sistema</h2>

    <?php if(isset($_SESSION['login_erro'])): ?>
      <div class="alert"><?php echo $_SESSION['login_erro']; unset($_SESSION['login_erro']); ?></div>
    <?php endif; ?>

    <div class="mb-3">
      <input type="text" name="usuario" class="form-control" placeholder="Usuário" required>
    </div>

    <div class="mb-3">
      <input type="password" name="senha" class="form-control" placeholder="Senha" required>
    </div>

    <input type="submit" class="btn btn-primary" value="Entrar">

    <button type="button" class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#modalSolicitarCadastro">
      Solicitar Cadastro
    </button>

    <div class="footer">
      Desenvolvido pelo 1º Ten Igor <b>Felipe Alves</b> de Carvalho - TU 2020 AMAN
    </div>
  </form>
</div>

<!-- Modal Solicitar Cadastro -->
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
            <img src="assets/fotoperfil/default.png" alt="Foto" class="rounded-circle" style="width: 100px; height: 100px; object-fit: cover;">
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
              <label for="funcao" class="form-label">Função</label>
              <select class="form-select" id="funcao" name="funcao" required>
                <option value="" disabled selected>Selecione</option>
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
