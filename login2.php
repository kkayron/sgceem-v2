<?php session_start(); ?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8">
  <title>Login - GCEEM</title>
  <link rel="stylesheet" href="assets/css/bootstrap.min.css">
  <style>
    body {
      background-color: #1e1e2f;
      color: #f1f1f1;
      display: flex;
      justify-content: center;
      align-items: center;
      height: 100vh;
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    }
    .card {
      width: 100%;
      max-width: 460px;
      padding: 2rem;
      border-radius: 12px;
      background-color: #2a2a3c;
      box-shadow: 0 0 20px rgba(0, 0, 0, 0.5);
    }
    .logo {
      text-align: center;
      margin-bottom: 1.5rem;
    }
    .logo img {
      width: 100%;
      max-width: 538px;
      height: auto;
    }
    .form-label {
      color: #ddd;
    }
    .form-control {
      background-color: #1e1e2f;
      border: 1px solid #444;
      color: #fff;
    }
    .form-control:focus {
      background-color: #1e1e2f;
      color: #fff;
      border-color: #0d6efd;
      box-shadow: none;
    }
    .btn-primary {
      background-color: #0d6efd;
      border-color: #0d6efd;
    }
    .btn-primary:hover {
      background-color: #0b5ed7;
      border-color: #0b5ed7;
    }
    .alert-danger {
      background-color: #ff4d4d;
      color: #fff;
      border: none;
    }
  </style>
</head>
<body>
  <div class="card">
    <div class="logo">
      <img src="assets/img/kaiadmin/logo_light.png" alt="Logo GCEEM">
    </div>
    <h4 class="text-center mb-4" style="color: white;">Login no Sistema</h4>

    <?php if (isset($_SESSION['login_erro'])): ?>
      <div class="alert alert-danger"><?php echo $_SESSION['login_erro']; unset($_SESSION['login_erro']); ?></div>
    <?php endif; ?>

    <form action="valida_login.php" method="POST">
      <div class="mb-3">
        <label for="usuario" class="form-label">Usuário</label>
        <input type="text" name="usuario" id="usuario" class="form-control" required autofocus>
      </div>
      <div class="mb-3">
        <label for="senha" class="form-label">Senha</label>
        <input type="password" name="senha" id="senha" class="form-control" required>
      </div>
      <div class="d-grid">
        <button type="submit" class="btn btn-primary">Entrar</button>
      </div>
    </form>
  </div>
    <script>
  // Limpa sessionStorage ao abrir o login
  sessionStorage.removeItem('paginaAtual');
</script>
</body>
</html>