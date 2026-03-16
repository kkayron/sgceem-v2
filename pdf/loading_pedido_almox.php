<?php
$id = $_GET['id'] ?? null;
if (!$id) die('Pedido inválido');
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="utf-8">
  <title>Gerando PDF...</title>

  <style>
    body {
      margin: 0;
      height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      background: #f4f6f9;
      font-family: Arial, Helvetica, sans-serif;
      color: #333;
    }

    .loader-container {
      text-align: center;
      background: #fff;
      padding: 40px 50px;
      border-radius: 12px;
      box-shadow: 0 10px 30px rgba(0,0,0,0.08);
      max-width: 420px;
      width: 100%;
    }

    .logo {
      margin-bottom: 20px;
    }

    .logo img {
      max-height: 60px;
    }

    .spinner {
      width: 48px;
      height: 48px;
      border: 5px solid #e0e0e0;
      border-top-color: #0d6efd;
      border-radius: 50%;
      animation: spin 1s linear infinite;
      margin: 0 auto 20px;
    }

    @keyframes spin {
      to { transform: rotate(360deg); }
    }

    h3 {
      margin: 0 0 8px;
      font-size: 18px;
      font-weight: bold;
    }

    p {
      margin: 0;
      font-size: 14px;
      color: #666;
    }

    .hint {
      margin-top: 12px;
      font-size: 12px;
      color: #999;
    }
  </style>
</head>
<body>

  <div class="loader-container">

    <!-- LOGO DO SISTEMA -->
    <div class="logo">
      <img src="../assets/img/kaiadmin/logo_dark.png" alt="Logo do Sistema">
    </div>

    <!-- SPINNER -->
    <div class="spinner"></div>

    <!-- TEXTO -->
    <h3>Gerando PDF do pedido</h3>
    <p>Aguarde um instante, estamos preparando o documento.</p>
    <div class="hint">Não feche esta janela.</div>

  </div>

  <script>
    setTimeout(() => {
      window.location.href = "gerar_pdf_pedido.php?id=<?= (int)$id ?>";
    }, 400);
  </script>

</body>
</html>
