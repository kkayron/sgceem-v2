<?php session_start(); ?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login - SGCEEM</title>

<style>
  * {
    margin: 0; padding: 0; box-sizing: border-box;
    font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
  }

  body {
    height: 100vh;
    display: flex;
    justify-content: center;
    align-items: center;
    overflow: hidden;
    background: #0b141e;
    color: #f0f2f5;
  }

  .container {
    display: flex;
    width: 100%;
    height: 100vh;
  }

  /* Painel esquerdo - institucional */
  .left-panel {
    flex: 1;
    position: relative;
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: center;
    padding: 60px;
    animation: fadeInLeft 1s ease-out;
    color: #fff;
  }

  .left-panel::before {
    content: "";
    position: absolute;
    top:0; left:0; width:100%; height:100%;
    background: url("imgnovas/background.jpg") center/cover no-repeat;
    filter: brightness(0.35) blur(1px);
    z-index: 0;
  }

  .left-panel * {
    position: relative;
    z-index: 1;
    text-align: center;
  }

  .left-panel h1 {
    font-size: 40px;
    font-weight: 700;
    color: #00b4d8;
    margin-bottom: 15px;
    letter-spacing: 1px;
    text-shadow: 0 2px 8px rgba(0,0,0,0.6);
  }

  .left-panel p {
    max-width: 420px;
    line-height: 1.6;
    font-size: 16px;
    color: #dce3eb;
    text-shadow: 0 1px 6px rgba(0,0,0,0.5);
  }

  /* Painel direito - login */
  .right-panel {
    flex: 1;
    display: flex;
    justify-content: center;
    align-items: center;
    background: radial-gradient(circle at top right, #18222f, #0e141b);
    animation: fadeInRight 1s ease-out;
  }

  form {
    background: rgba(255,255,255,0.03);
    border: 1px solid rgba(255,255,255,0.08);
    padding: 50px 45px;
    border-radius: 14px;
    width: 100%;
    max-width: 380px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.4);
    text-align: center;
    animation: fadeSlide 0.8s ease-out;
  }

  form h2 {
    color: #00b4d8;
    font-size: 26px;
    font-weight: 600;
    margin-bottom: 25px;
  }

  .input-group {
    margin-bottom: 20px;
    text-align: left;
  }

  .input-group label {
    display: block;
    margin-bottom: 6px;
    color: #a7b2bd;
    font-size: 14px;
    font-weight: 500;
  }

  .input-group input {
    width: 100%;
    padding: 12px 14px;
    border: 1px solid #2b3643;
    background: #1c2532;
    color: #f0f2f5;
    border-radius: 8px;
    transition: all 0.25s ease;
    font-size: 15px;
  }

  .input-group input:focus {
    border-color: #00b4d8;
    background: #243140;
    outline: none;
    box-shadow: 0 0 6px rgba(0,180,216,0.3);
  }

  .btn-login, .btn-cadastro {
    width: 100%;
    padding: 12px;
    border: none;
    border-radius: 8px;
    font-weight: bold;
    font-size: 15px;
    color: #fff;
    cursor: pointer;
    transition: all 0.25s ease;
    position: relative;
    overflow: hidden;
    margin-top: 10px;
  }

  .btn-login {
    background: #00b4d8;
  }

  .btn-login:hover {
    background: #009ac0;
    box-shadow: 0 4px 14px rgba(0,180,216,0.3);
    transform: translateY(-1px);
  }

  .btn-cadastro {
    background: transparent;
    border: 1px solid #00b4d8;
    color: #00b4d8;
  }

  .btn-cadastro:hover {
    background: #00b4d8;
    color: #fff;
    box-shadow: 0 4px 14px rgba(0,180,216,0.3);
    transform: translateY(-1px);
  }

  .btn-login.loading {
    color: transparent;
    pointer-events: none;
  }

  .btn-login.loading::after {
    content: "";
    position: absolute;
    top: 50%; left: 50%;
    width: 22px; height: 22px;
    margin: -11px;
    border: 3px solid rgba(255,255,255,0.4);
    border-top-color: #fff;
    border-radius: 50%;
    animation: spin 0.8s linear infinite;
  }

  @keyframes spin { to { transform: rotate(360deg); } }

  .alert {
    background: #e63946;
    color: #fff;
    padding: 10px;
    border-radius: 8px;
    margin-bottom: 18px;
    font-weight: 500;
    text-align: center;
  }

  .footer {
    text-align: center;
    margin-top: 25px;
    font-size: 0.85rem;
    color: #7a8793;
  }

  /* Animações */
  @keyframes fadeInLeft {
    from { opacity: 0; transform: translateX(-40px); }
    to { opacity: 1; transform: translateX(0); }
  }

  @keyframes fadeInRight {
    from { opacity: 0; transform: translateX(40px); }
    to { opacity: 1; transform: translateX(0); }
  }

  @keyframes fadeSlide {
    from { opacity: 0; transform: translateY(25px); }
    to { opacity: 1; transform: translateY(0); }
  }

  @media (max-width: 900px) {
    .container { flex-direction: column; }
    .left-panel { display: none; }
    .right-panel { flex: 1; background: #0e141b; }
    form { box-shadow: none; border: none; }
  }
</style>
</head>
<body>

<div class="container">
  <!-- Painel Esquerdo -->
  <div class="left-panel">
    <h1>SGCEEM</h1>
    <p>
      Sistema de Gerenciamento da Companhia de Equipamentos e Manutenção —  
      otimizado para controle das viaturas, máquinas e equipes técnicas dos batalhões de engenharia.
    </p>
  </div>

  <!-- Painel Direito -->
  <div class="right-panel">
    <form action="valida_login.php" method="POST" id="loginForm">
      <h2>Acesso Restrito</h2>

      <?php if(isset($_SESSION['login_erro'])): ?>
      <div class="alert"><?php echo $_SESSION['login_erro']; unset($_SESSION['login_erro']); ?></div>
      <?php endif; ?>

      <div class="input-group">
        <label for="usuario">Usuário</label>
        <input type="text" id="usuario" name="usuario" placeholder="Digite seu usuário" required autofocus>
      </div>

      <div class="input-group">
        <label for="senha">Senha</label>
        <input type="password" id="senha" name="senha" placeholder="Digite sua senha" required>
      </div>

      <button type="submit" class="btn-login" id="btnLogin">Entrar</button>

      <button type="button" class="btn-cadastro" onclick="window.location.href='solicitar_cadastro.php'">
        Solicitar cadastro
      </button>

      <div class="footer">
        Desenvolvido pelo 1º Ten <b>Igor Felipe Alves</b> de Carvalho - TU 2020 AMAN
      </div>
    </form>
  </div>
</div>

<script>
  const form = document.getElementById('loginForm');
  const btn = document.getElementById('btnLogin');

  form.addEventListener('submit', function() {
    btn.classList.add('loading');
  });

  sessionStorage.removeItem('paginaAtual');
</script>

</body>
</html>
