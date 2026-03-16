<?php
session_start();
require_once 'conexao/config.php';

/* =========================
   MANUTENÇÃO
========================= */
$valor = 'não';
$manutencaosql = "SELECT valor FROM manutencao WHERE id = 1 LIMIT 1";
$resmanut = $conexao->query($manutencaosql);

if ($resmanut && $resmanut->num_rows > 0) {
    $manut = $resmanut->fetch_assoc();
    $valor = $manut['valor'] ?? 'não';
}

if ($valor === "sim") {
    header("Location: manutencao.php");
    exit;
}

/* =========================
   BATALHÕES
========================= */
$batalhoes = $conexao->query("SELECT id, nome, abreviatura FROM organizacoes_militares ORDER BY abreviatura ASC");
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login - SGCEEM</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">

<style>
  :root{
    --mil-green-1:#1f2d1f;
    --mil-green-2:#2e4a2f;
    --mil-green-3:#3f5f3d;
    --mil-green-4:#6e8b5e;
    --mil-gold:#c7a84a;
    --mil-gold-soft:#e0c97a;
    --mil-light:#f4f1e8;
    --mil-card:#ffffff;
    --mil-muted:#cfd6c9;
    --mil-border:rgba(255,255,255,0.16);
    --mil-shadow:0 20px 60px rgba(0,0,0,.28);
  }

  *{
    box-sizing:border-box;
  }

  body{
    margin:0;
    min-height:100vh;
    font-family:"Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
    background:
      linear-gradient(rgba(17,25,17,.72), rgba(17,25,17,.78)),
      url("imgnovas/tablet_oficina.jpeg") center center / cover no-repeat fixed;
    color:#fff;
    overflow-x:hidden;
  }

  .page-wrapper{
    min-height:100vh;
    display:grid;
    grid-template-columns: 1.15fr 0.85fr;
	   align-items:center;
    backdrop-filter: blur(2px);
  }

.left-panel{
  position:relative;
  padding:30px 38px;
    display:flex;
    flex-direction:column;
    justify-content:center;
  }

  .brand-badge{
    display:inline-flex;
    align-items:center;
    gap:12px;
    background:rgba(255,255,255,.08);
    border:1px solid rgba(255,255,255,.12);
    padding:10px 18px;
    border-radius:999px;
    width:fit-content;
    margin-bottom:22px;
    box-shadow:0 10px 25px rgba(0,0,0,.18);
    backdrop-filter:blur(10px);
  }

  .brand-badge i{
    color:var(--mil-gold-soft);
    font-size:1.1rem;
  }

  .brand-badge span{
    font-size:.95rem;
    letter-spacing:.08em;
    text-transform:uppercase;
    font-weight:700;
  }

.hero-title{
  font-size:clamp(1.7rem, 3vw, 2.6rem);
    font-weight:800;
    line-height:1.08;
    margin-bottom:18px;
    max-width:760px;
    text-shadow:0 4px 18px rgba(0,0,0,.35);
  }

  .hero-title .gold{
    color:var(--mil-gold-soft);
  }

  .hero-subtitle{
    max-width:720px;
    font-size:.98rem;
    line-height:1.7;
    color:rgba(255,255,255,.88);
    margin-bottom:18px;
  }

  .hero-infos{
    display:flex;
    flex-wrap:wrap;
    gap:14px;
    margin-bottom:34px;
  }

  .hero-chip{
    display:flex;
    align-items:center;
    gap:10px;
    background:rgba(255,255,255,.08);
    border:1px solid rgba(255,255,255,.12);
    border-radius:999px;
    padding:8px 14px;
    color:#fff;
    font-size:.85rem;
    backdrop-filter:blur(8px);
  }

  .hero-chip i{
    color:var(--mil-gold-soft);
  }

  .features-grid{
    display:grid;
    grid-template-columns:repeat(3, minmax(0,1fr));
    gap:16px;
    max-width:860px;
  }

  .feature-card{
    background:rgba(255,255,255,.10);
    border:1px solid var(--mil-border);
    border-radius:18px;
    padding:16px 14px;
    box-shadow:0 12px 30px rgba(0,0,0,.14);
    backdrop-filter:blur(10px);
    transition:.28s ease;
    min-height:130px;
  }

  .feature-card:hover{
    transform:translateY(-6px);
    background:rgba(255,255,255,.14);
    box-shadow:0 18px 36px rgba(0,0,0,.22);
  }

  .feature-icon{
    width:42px;
    height:42px;
    border-radius:14px;
    display:flex;
    align-items:center;
    justify-content:center;
    background:linear-gradient(135deg, rgba(199,168,74,.24), rgba(255,255,255,.10));
    border:1px solid rgba(255,255,255,.12);
    margin-bottom:10px;
    font-size:1.1rem;
    color:var(--mil-gold-soft);
  }

  .feature-card h6{
    font-size:1rem;
    font-weight:700;
    margin-bottom:8px;
  }

  .feature-card p{
    font-size:.90rem;
    line-height:1.5;
    color:rgba(255,255,255,.82);
    margin:0;
  }

  .right-panel{
    display:flex;
    align-items:center;
    justify-content:center;
    padding:34px 26px;
  }

  .login-shell{
    width:100%;
    max-width:450px;
  }

  .login-box{
    position:relative;
    background:linear-gradient(180deg, rgba(255,255,255,.96), rgba(246,244,238,.98));
    color:#1b1f1b;
    border-radius:28px;
    padding:26px 24px 20px;
    box-shadow:var(--mil-shadow);
    border:1px solid rgba(255,255,255,.55);
    overflow:hidden;
    animation:fadeSlide .7s ease;
  }

  .login-box::before{
    content:"";
    position:absolute;
    inset:0 auto auto 0;
    width:100%;
    height:7px;
    background:linear-gradient(90deg, var(--mil-green-2), var(--mil-gold), var(--mil-green-4));
  }

  .logo-wrap{
    width:64px;
    height:64px;
    margin:0 auto 14px;
    border-radius:22px;
    background:linear-gradient(135deg, #eef2e8, #dfe8d6);
    display:flex;
    align-items:center;
    justify-content:center;
    border:1px solid rgba(46,74,47,.12);
    box-shadow:0 10px 25px rgba(0,0,0,.08);
  }

  .logo-wrap img{
    max-width:58px;
    max-height:58px;
    object-fit:contain;
  }

  .login-title{
    text-align:center;
    font-weight:800;
    font-size:2rem;
    color:var(--mil-green-2);
    margin-bottom:4px;
    letter-spacing:.04em;
  }

  .login-subtitle{
    text-align:center;
    color:#556155;
    font-size:.96rem;
    margin-bottom:22px;
  }

  .section-label{
    font-size:.75rem;
    text-transform:uppercase;
    letter-spacing:.14em;
    color:#7a836f;
    font-weight:800;
    margin-bottom:14px;
    text-align:center;
  }

  .form-floating > .form-control,
  .form-floating > .form-select{
    border-radius:16px;
    border:1px solid #d5dccf;
    min-height:48px;
    box-shadow:none !important;
  }

  .form-floating > label{
    color:#667062;
  }

  .form-control:focus,
  .form-select:focus{
    border-color:var(--mil-green-4);
    box-shadow:0 0 0 .22rem rgba(110,139,94,.16) !important;
  }

  .input-icon-group{
    position:relative;
  }

  .input-icon-group .input-icon{
    position:absolute;
    right:16px;
    top:50%;
    transform:translateY(-50%);
    color:#7a836f;
    z-index:5;
    pointer-events:none;
  }

  .btn-militar{
    width:100%;
    border:none;
    border-radius:16px;
    padding:11px 16px;
    font-weight:800;
    letter-spacing:.03em;
    background:linear-gradient(135deg, var(--mil-green-2), var(--mil-green-4));
    color:#fff;
    transition:.25s ease;
    box-shadow:0 10px 24px rgba(46,74,47,.24);
  }

  .btn-militar:hover{
    transform:translateY(-2px);
    filter:brightness(1.03);
    color:#fff;
  }

  .btn-cadastro{
    width:100%;
    border-radius:16px;
    padding:13px 18px;
    font-weight:700;
    border:1px solid #cfd7c8;
    color:var(--mil-green-2);
    background:#fff;
    transition:.25s ease;
  }

  .btn-cadastro:hover{
    background:#f4f7f1;
    border-color:var(--mil-green-4);
    color:var(--mil-green-2);
  }

  .login-divider{
    display:flex;
    align-items:center;
    gap:12px;
    color:#8a9283;
    font-size:.86rem;
    margin:16px 0;
  }

  .login-divider::before,
  .login-divider::after{
    content:"";
    flex:1;
    height:1px;
    background:#d9dfd4;
  }

  .login-alert{
    border:none;
    border-radius:14px;
    background:#b43232;
    color:#fff;
    font-weight:600;
    padding:12px 14px;
    margin-bottom:16px;
  }

  .institutional-note{
    margin-top:18px;
    padding-top:16px;
    border-top:1px solid #e2e7dd;
    text-align:center;
    font-size:.84rem;
    color:#667062;
    line-height:1.6;
  }

  .institutional-note strong{
    color:var(--mil-green-2);
  }

  .modal-content{
    border:none;
    border-radius:24px;
    overflow:hidden;
    box-shadow:0 20px 60px rgba(0,0,0,.2);
  }

  .modal-header{
    background:linear-gradient(135deg, var(--mil-green-2), var(--mil-green-4));
    color:#fff;
    border:none;
    padding:18px 22px;
  }

  .modal-header .btn-close{
    filter:invert(1);
  }

  .modal-body{
    padding:24px;
    background:#f8f8f4;
  }

  .cadastro-avatar{
    width:110px;
    height:110px;
    object-fit:cover;
    border-radius:50%;
    border:4px solid #dde4d7;
    box-shadow:0 8px 18px rgba(0,0,0,.08);
  }

  .modal .form-control,
  .modal .form-select{
    border-radius:14px;
    min-height:48px;
    border:1px solid #d5dccf;
  }

  .btn-enviar{
    background:linear-gradient(135deg, #2f6d39, #4d8755);
    color:#fff;
    border:none;
    border-radius:14px;
    padding:12px 20px;
    font-weight:700;
  }

  .btn-enviar:hover{
    color:#fff;
    filter:brightness(1.03);
  }

  @keyframes fadeSlide{
    from{
      opacity:0;
      transform:translateY(24px);
    }
    to{
      opacity:1;
      transform:translateY(0);
    }
  }

  @media (max-width: 1200px){
    .features-grid{
      grid-template-columns:repeat(2, minmax(0,1fr));
    }
  }

  @media (max-width: 992px){
    .page-wrapper{
      grid-template-columns:1fr;
    }

    .left-panel{
      min-height:auto;
      padding:30px 24px 12px;
    }

    .right-panel{
      padding:18px 18px 28px;
    }

    .hero-title{
      font-size:2rem;
    }
  }

  @media (max-width: 768px){
    .left-panel{
      display:none;
    }

    .right-panel{
      min-height:100vh;
    }

    .login-box{
      padding:28px 20px 22px;
      border-radius:22px;
    }
  }
</style>
</head>
<body>

<div class="page-wrapper">

  <!-- Painel esquerdo -->
  <section class="left-panel">
    <div class="brand-badge">
      <i class="fas fa-shield-halved"></i>
      <span>Sistema Militar de Gestão</span>
    </div>

    <h1 class="hero-title">
      <span class="gold">SGCEEM</span><br>
      Sistema de Gerenciamento da Cia E Eqp Mnt
    </h1>

    <p class="hero-subtitle">
      Plataforma voltada ao controle operacional, administrativo e logístico de viaturas, máquinas,
      ordens de serviço, equipes técnicas, almoxarifado e relatórios gerenciais dos batalhões de engenharia.
    </p>

    <div class="hero-infos">
      <div class="hero-chip"><i class="fas fa-lock"></i> Acesso restrito e controlado</div>
      <div class="hero-chip"><i class="fas fa-screwdriver-wrench"></i> Gestão de manutenção</div>
      <div class="hero-chip"><i class="fas fa-chart-column"></i> Controle operacional</div>
    </div>

    <div class="features-grid">
      <div class="feature-card">
        <div class="feature-icon"><i class="fas fa-tools"></i></div>
        <h6>Ordens de Serviço</h6>
        <p>Controle de abertura, acompanhamento, execução e encerramento das manutenções.</p>
      </div>

      <div class="feature-card">
        <div class="feature-icon"><i class="fas fa-truck-front"></i></div>
        <h6>Gestão da Frota</h6>
        <p>Supervisão de viaturas, máquinas, equipamentos e disponibilidade operacional.</p>
      </div>

      <div class="feature-card">
        <div class="feature-icon"><i class="fas fa-file-invoice-dollar"></i></div>
        <h6>Controle Financeiro</h6>
        <p>Monitoramento de empenhos, despesas, saldos e registros administrativos.</p>
      </div>

      <div class="feature-card">
        <div class="feature-icon"><i class="fas fa-warehouse"></i></div>
        <h6>Almoxarifado</h6>
        <p>Gestão de estoque, peças, materiais, entradas, saídas e movimentações detalhadas.</p>
      </div>

      <div class="feature-card">
        <div class="feature-icon"><i class="fas fa-users-gear"></i></div>
        <h6>Equipes Técnicas</h6>
        <p>Organização de usuários, funções, permissões e setores de atuação.</p>
      </div>

      <div class="feature-card">
        <div class="feature-icon"><i class="fas fa-chart-line"></i></div>
        <h6>Relatórios Gerenciais</h6>
        <p>Indicadores e análises para apoio à decisão e melhoria do desempenho logístico.</p>
      </div>
    </div>
  </section>

  <!-- Painel direito -->
  <section class="right-panel">
    <div class="login-shell">
      <form action="valida_login.php" method="POST" class="login-box">
        <div class="logo-wrap">
          <img src="imagens/icon_png_mnt_2.png" alt="Logo SGCEEM">
        </div>

        <div class="section-label">Acesso ao sistema</div>
        <h2 class="login-title">SGCEEM</h2>
        <p class="login-subtitle">Ambiente restrito para usuários autorizados</p>

        <?php if (isset($_SESSION['login_erro'])): ?>
          <div class="login-alert">
            <i class="fas fa-circle-exclamation me-2"></i>
            <?= htmlspecialchars($_SESSION['login_erro']) ?>
          </div>
          <?php unset($_SESSION['login_erro']); ?>
        <?php endif; ?>

        <div class="mb-3 input-icon-group">
          <div class="form-floating">
            <input type="text" name="usuario" id="usuario" class="form-control" placeholder="Usuário" required>
            <label for="usuario">Usuário</label>
          </div>
          <span class="input-icon"><i class="fas fa-user"></i></span>
        </div>

        <div class="mb-3 input-icon-group">
          <div class="form-floating">
            <input type="password" name="senha" id="senha" class="form-control" placeholder="Senha" required>
            <label for="senha">Senha</label>
          </div>
          <span class="input-icon"><i class="fas fa-lock"></i></span>
        </div>

        <button type="submit" class="btn btn-militar">
          <i class="fas fa-right-to-bracket me-2"></i> Entrar no Sistema
        </button>

        <div class="login-divider">ou</div>

        <button type="button" class="btn btn-cadastro" data-bs-toggle="modal" data-bs-target="#modalSolicitarCadastro">
          <i class="fas fa-id-card me-2"></i> Solicitar Cadastro
        </button>

        <div class="institutional-note">
          © SGCEEM 2024<br>
          Desenvolvido pelo <strong>1º Ten Felipe Alves</strong> de Carvalho - TU Eng 2020 AMAN
        </div>
      </form>
    </div>
  </section>
</div>

<!-- MODAL SOLICITAR CADASTRO -->
<div class="modal fade" id="modalSolicitarCadastro" tabindex="-1" aria-labelledby="modalCadastroLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-xl">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title fw-bold" id="modalCadastroLabel">
          <i class="fas fa-id-badge me-2"></i> Solicitar Cadastro
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
      </div>

      <div class="modal-body">
        <form method="POST" enctype="multipart/form-data" action="solicitar_cadastro.php">
          <div class="text-center mb-4">
            <img src="assets/fotoperfil/default.png" alt="Foto" class="cadastro-avatar">
            <div class="mt-3">
              <label for="foto" class="form-label fw-semibold">Enviar foto</label>
              <input type="file" class="form-control" id="foto" name="foto" accept="image/*">
            </div>
          </div>

          <div class="row g-3">
            <div class="col-md-6">
              <label for="postograd" class="form-label fw-semibold">Posto/Graduação</label>
              <select class="form-select" id="postograd" name="postograd" required>
                <option value="" disabled selected>Selecione</option>
                <option>Gen</option>
                <option>Cel</option>
                <option>TC</option>
                <option>Maj</option>
                <option>Capitão</option>
                <option>1º Ten</option>
                <option>2º Ten</option>
                <option>Asp Of</option>
                <option>Sten</option>
                <option>1º Sgt</option>
                <option>2º Sgt</option>
                <option>3º Sgt</option>
                <option>Cb</option>
                <option>Sd</option>
              </select>
            </div>

            <div class="col-md-6">
              <label for="funcao" class="form-label fw-semibold">Função</label>
              <select class="form-select" id="funcao" name="funcao" required>
                <option value="" disabled selected>Selecione uma função</option>
                <?php
                $sqlFuncoes = "SELECT id, nome FROM funcoes ORDER BY id ASC";
                $resFuncoes = $conexao->query($sqlFuncoes);

                if ($resFuncoes && $resFuncoes->num_rows > 0) {
                    while ($row = $resFuncoes->fetch_assoc()) {
                        $id = (int) $row['id'];
                        $nome = htmlspecialchars($row['nome']);
                        echo "<option value='{$id}'>{$nome}</option>";
                    }
                }
                ?>
              </select>
            </div>

            <div class="col-md-6">
              <label for="batalhao" class="form-label fw-semibold">Batalhão</label>
              <select class="form-select" id="batalhao" name="batalhao" required>
                <option value="" disabled selected>Selecione o Batalhão</option>
                <?php if ($batalhoes && $batalhoes->num_rows > 0): ?>
                  <?php while ($b = $batalhoes->fetch_assoc()): ?>
                    <option value="<?= (int)$b['id'] ?>">
                      <?= htmlspecialchars($b['abreviatura']) ?> - <?= htmlspecialchars($b['nome']) ?>
                    </option>
                  <?php endwhile; ?>
                <?php endif; ?>
              </select>
            </div>

            <div class="col-md-6">
              <label for="nomeguerra" class="form-label fw-semibold">Nome de Guerra</label>
              <input type="text" class="form-control" id="nomeguerra" name="nomeguerra" required>
            </div>

            <div class="col-md-12">
              <label for="nomecompleto" class="form-label fw-semibold">Nome Completo</label>
              <input type="text" class="form-control" id="nomecompleto" name="nomecompleto" required>
            </div>

            <div class="col-md-6">
              <label for="usuarioCadastro" class="form-label fw-semibold">Usuário</label>
              <input type="text" class="form-control" id="usuarioCadastro" name="usuario" required>
            </div>

            <div class="col-md-6">
              <label for="senhaCadastro" class="form-label fw-semibold">Senha</label>
              <input type="password" class="form-control" id="senhaCadastro" name="senha" required>
            </div>
          </div>

          <input type="hidden" name="status" value="não">
          <input type="hidden" name="solicitacao" value="sim">

          <div class="text-end mt-4">
            <button type="submit" class="btn btn-enviar">
              <i class="fas fa-paper-plane me-2"></i> Enviar Solicitação
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<?php if (isset($_SESSION['cadastro_sucesso'])): ?>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
Swal.fire({
    icon: 'success',
    title: 'Sucesso',
    text: <?= json_encode($_SESSION['cadastro_sucesso']) ?>,
    confirmButtonText: 'OK',
    confirmButtonColor: '#2e4a2f'
});
</script>
<?php unset($_SESSION['cadastro_sucesso']); ?>
<?php endif; ?>

<?php if (isset($_SESSION['cadastro_erro'])): ?>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
Swal.fire({
    icon: 'error',
    title: 'Erro',
    text: <?= json_encode($_SESSION['cadastro_erro']) ?>,
    confirmButtonText: 'OK',
    confirmButtonColor: '#7a1f1f'
});
</script>
<?php unset($_SESSION['cadastro_erro']); ?>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>