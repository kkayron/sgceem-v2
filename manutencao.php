<?php session_start(); ?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>SGCEEM | Sistema em Atualização</title>

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

  /* Painel esquerdo (igual ao login) */
  .left-panel { 
    flex: 1; 
    position: relative; 
    display: flex; 
    flex-direction: column; 
    justify-content: center; 
    padding: 30px 25px; 
    color: white; 
    z-index: 1; 
  }
  .left-panel::before { 
    content: ""; 
    position: absolute; 
    top:0; left:0; width:100%; height:100%; 
    background: url("imgnovas/background.jpg") center/cover no-repeat; 
    filter: brightness(0.45); 
    z-index: 0; 
  }
  .left-panel * { position: relative; z-index: 2; }

  .left-panel h4 { 
    font-size: 28px; 
    font-weight: 700; 
    text-shadow: 0 3px 10px rgba(0,0,0,0.5); 
    margin-bottom: 15px;
  }

  .left-panel p { 
    font-size: 15px; 
    max-width: 420px; 
    color: rgba(255,255,255,0.9); 
    text-shadow: 0 2px 8px rgba(0,0,0,0.4); 
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

  .status-box { 
    width: 100%; 
    max-width: 400px; 
    background: rgba(255,255,255,0.96); 
    padding: 40px 30px; 
    border-radius: 20px; 
    box-shadow: 0 8px 24px rgba(0,0,0,0.08); 
    text-align: center; 
    animation: fadeSlide 0.8s ease-out; 
  }

  .status-box h2 { 
    color: #004aad; 
    font-size: 26px; 
    margin-bottom: 10px; 
  }

  .status-box p {
    color: #555;
    font-size: 14px;
  }

  .icon-tools {
    font-size: 4rem;
    color: #004aad;
    margin: 25px 0;
  }

  .footer { 
    margin-top: 30px; 
    font-size: 0.8rem; 
    color: #777; 
  }

  @keyframes fadeSlide { 
    from { opacity: 0; transform: translateY(25px); } 
    to { opacity: 1; transform: translateY(0); } 
  }

  /* Responsividade */
  @media (max-width: 900px) {
    body { flex-direction: column; }
    .left-panel { display: none; }
  }
</style>
</head>

<body>

<!-- Painel esquerdo -->
<div class="left-panel">
  <h4>Sistema de Gerenciamento da Cia E Eqp Mnt</h4>
  <p>
    O SGCEEM encontra-se temporariamente indisponível para realização de
    atualizações técnicas e operacionais.
  </p>
  <p class="mt-2">
    Essas melhorias visam aumentar a segurança, estabilidade e desempenho
    do sistema.
  </p>

  <ul class="mt-4">
    <li>⚙️ Ajustes internos e correções</li>
    <li>🔒 Atualizações de segurança</li>
    <li>📊 Melhorias nos relatórios</li>
    <li>🚚 Otimizações na gestão de frota e estoque</li>
  </ul>

  <p class="mt-3">
    Agradecemos a compreensão.
  </p>
</div>

<!-- Painel direito -->
<div class="right-panel">
  <div class="status-box">
    <img src="imagens/icon_png_mnt_2.png" width="71" height="59" alt="SGCEEM">

    <h2 class="mt-3">SGCEEM</h2>
    <p><strong>Sistema em Atualização</strong></p>

    <div class="icon-tools">
      <i class="fas fa-tools"></i>
    </div>

    <p>
      Estamos realizando uma manutenção programada.<br>
      O acesso será restabelecido em breve.
    </p>

    <div class="mt-4">
      <div class="spinner-border text-primary" role="status"></div>
    </div>

    <p class="mt-3 text-muted">Por favor, aguarde…</p>

    <a href="login.php" class="btn btn-outline-secondary mt-3 w-100">
      Voltar
    </a>

    <div class="footer">
      © SGCEEM 2024<br>
      Desenvolvido pelo 1º Ten <b>Felipe Alves</b> de Carvalho – TU Eng 2020 AMAN
    </div>
  </div>
</div>

</body>
</html>
