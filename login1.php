<?php session_start(); ?>
<?php
require_once 'conexao/config.php';
$manutencaosql = "SELECT valor FROM manutencao WHERE id = 1 LIMIT 1";
$resmanut = $conexao->query($manutencaosql);
if ($resmanut && $resmanut->num_rows > 0) {
    while ($manut = $resmanut->fetch_assoc()) {
        $valor = $manut['valor'];
    }
}

$batalhoes = $conexao->query("SELECT id, nome, abreviatura FROM organizacoes_militares ORDER BY abreviatura ASC");
$manutencao = $valor;

if ($manutencao == "sim") {
    header("Location: manutencao.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SGCEEM - Acesso Restrito</title>

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">

    <style>
        :root {
            --primary-color: #1a2a3a; /* Azul Militar */
            --accent-color: #3e5c33;  /* Verde Oliva Tático */
            --text-light: #f8f9fa;
            --glass-bg: rgba(255, 255, 255, 0.1);
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: #f0f2f5;
            height: 100vh;
            display: flex;
            margin: 0;
            overflow: hidden;
        }

        /* Painel Esquerdo - Estilo Industrial/Militar */
        .left-panel {
            flex: 1.2;
            position: relative;
            display: flex;
            flex-direction: column;
            justify-content: center;
            padding: 60px;
            overflow-y: auto;
        }

        .left-panel::before {
            content: "";
            position: absolute;
            top: 0; left: 0; width: 100%; height: 100%;
            background: url("imgnovas/tablet_oficina.jpeg") center/cover no-repeat;
            filter: brightness(0.3) contrast(1.1);
            z-index: -1;
        }

        .left-panel h4 {
            font-weight: 700;
            letter-spacing: -1px;
            color: #fff;
            font-size: 2.2rem;
            border-left: 5px solid var(--accent-color);
            padding-left: 15px;
            margin-bottom: 20px;
        }

        .left-panel p {
            font-size: 1.1rem;
            color: rgba(255, 255, 255, 0.8);
            max-width: 500px;
        }

        /* Grid de Funcionalidades */
        .cards-container {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
            margin-top: 40px;
        }

        .card-servico {
            background: var(--glass-bg);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            padding: 20px;
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }

        .card-servico:hover {
            transform: translateY(-8px);
            background: rgba(255, 255, 255, 0.2);
            box-shadow: 0 15px 30px rgba(0,0,0,0.3);
        }

        .card-servico i {
            font-size: 24px;
            margin-bottom: 15px;
            display: block;
            color: #7bed9f; /* Cor vibrante para ícones */
        }

        .card-servico h6 {
            color: #fff;
            font-weight: 600;
            margin-bottom: 8px;
        }

        .card-servico p {
            font-size: 0.85rem;
            margin: 0;
            line-height: 1.4;
        }

        /* Painel Direito - Formulário Clean */
        .right-panel {
            flex: 0.8;
            background: #ffffff;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px;
            box-shadow: -10px 0 30px rgba(0,0,0,0.05);
        }

        .login-box {
            width: 100%;
            max-width: 400px;
            animation: fadeInRight 0.8s ease-out;
        }

        .login-box img {
            margin-bottom: 20px;
            filter: drop-shadow(0 4px 6px rgba(0,0,0,0.1));
        }

        .login-box h2 {
            font-weight: 800;
            color: var(--primary-color);
            letter-spacing: 2px;
        }

        .form-control {
            height: 50px;
            border-radius: 8px;
            border: 1px solid #e0e4e8;
            padding: 10px 20px;
            transition: all 0.3s;
        }

        .form-control:focus {
            box-shadow: 0 0 0 4px rgba(26, 42, 58, 0.1);
            border-color: var(--primary-color);
        }

        .btn-primary {
            height: 50px;
            background: var(--primary-color);
            border: none;
            font-weight: 600;
            border-radius: 8px;
            transition: all 0.3s;
        }

        .btn-primary:hover {
            background: #0d161f;
            transform: scale(1.02);
        }

        .btn-outline-secondary {
            border: 2px solid #e0e4e8;
            color: #6c757d;
            height: 50px;
            font-weight: 600;
            margin-top: 15px;
        }

        .footer {
            margin-top: 40px;
            font-size: 0.75rem;
            color: #adb5bd;
            line-height: 1.6;
        }

        /* Animações */
        @keyframes fadeInRight {
            from { opacity: 0; transform: translateX(30px); }
            to { opacity: 1; transform: translateX(0); }
        }

        /* Responsividade */
        @media (max-width: 1024px) {
            .cards-container { grid-template-columns: 1fr; }
            .left-panel { padding: 30px; }
        }

        @media (max-width: 768px) {
            body { overflow-y: auto; flex-direction: column; }
            .left-panel { display: none; }
            .right-panel { flex: 1; height: 100vh; }
        }
    </style>
</head>

<body>

<div class="left-panel">
    <h4>SGCEEM</h4>
    <p class="mb-4">Sistema de Gerenciamento da Cia E Eqp Mnt. Tecnologia avançada aplicada à logística e manutenção da frota de engenharia do Exército Brasileiro.</p>

    <div class="cards-container">
        <div class="card-servico">
            <i class="fas fa-microchip"></i>
            <h6>Ordem de Serviço</h6>
            <p>Fluxo automatizado de manutenção corretiva e preventiva.</p>
        </div>
        <div class="card-servico">
            <i class="fas fa-shield-halved"></i>
            <h6>Controle de Frota</h6>
            <p>Monitoramento em tempo real da disponibilidade operativa.</p>
        </div>
        <div class="card-servico">
            <i class="fas fa-boxes-stacked"></i>
            <h6>Almoxarifado</h6>
            <p>Gestão inteligente de estoque e suprimentos técnicos.</p>
        </div>
        <div class="card-servico">
            <i class="fas fa-chart-pie"></i>
            <h6>Inteligência</h6>
            <p>Relatórios dinâmicos para suporte à decisão do Comando.</p>
        </div>
    </div>
</div>

<div class="right-panel">
    <div class="login-box">
        <div class="text-center">
            <img src="imagens/icon_png_mnt_2.png" width="80" alt="Logo Mnt">
            <h2>SGCEEM</h2>
            <p class="text-muted mb-4 text-uppercase fw-bold" style="font-size: 0.8rem; letter-spacing: 1px;">CONSTRUIR &amp; MANUTENIR</p>
        </div>

        <?php if(isset($_SESSION['login_erro'])): ?>
            <div class="alert alert-danger text-center py-2" style="font-size: 0.9rem; border-radius: 8px;">
                <i class="fas fa-exclamation-circle me-2"></i><?php echo $_SESSION['login_erro']; unset($_SESSION['login_erro']); ?>
            </div>
        <?php endif; ?>

        <form action="valida_login.php" method="POST">
            <div class="mb-3">
                <label class="form-label text-muted small fw-bold">USUÁRIO</label>
                <div class="input-group">
                    <span class="input-group-text bg-transparent border-end-0"><i class="fas fa-user text-muted"></i></span>
                    <input type="text" name="usuario" class="form-control border-start-0" placeholder="Insira seu login" required>
                </div>
            </div>
            
            <div class="mb-4">
                <label class="form-label text-muted small fw-bold">SENHA</label>
                <div class="input-group">
                    <span class="input-group-text bg-transparent border-end-0"><i class="fas fa-lock text-muted"></i></span>
                    <input type="password" name="senha" class="form-control border-start-0" placeholder="••••••••" required>
                </div>
            </div>

            <button type="submit" class="btn btn-primary w-100 shadow-sm">
                ACESSAR SISTEMA <i class="fas fa-arrow-right ms-2"></i>
            </button>

            <button type="button" class="btn btn-outline-secondary w-100" data-bs-toggle="modal" data-bs-target="#modalSolicitarCadastro">
                SOLICITAR CREDENCIAIS
            </button>
        </form>

        <div class="footer text-center">
            <hr>
            © 2026 <b>SGCEEM</b><br>
            Desenvolvido pelo 1º Ten <b>Felipe Alves</b> de Carvalho<br>
            <span class="badge bg-light text-dark border">TU Eng 2020 AMAN</span>
        </div>
    </div>
</div>

<div class="modal fade" id="modalSolicitarCadastro" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title fw-bold"><i class="fas fa-user-plus me-2"></i>Solicitar Cadastro no Sistema</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <form method="POST" enctype="multipart/form-data" action="solicitar_cadastro.php">
                    <div class="text-center mb-4">
                        <div class="position-relative d-inline-block">
                            <img src="assets/fotoperfil/default.png" alt="Foto" class="rounded-circle border" style="width:110px;height:110px;object-fit:cover;">
                            <label for="foto" class="position-absolute bottom-0 end-0 bg-primary text-white rounded-circle p-2" style="cursor:pointer;">
                                <i class="fas fa-camera"></i>
                            </label>
                            <input type="file" class="d-none" id="foto" name="foto" accept="image/*">
                        </div>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Posto/Graduação</label>
                            <select class="form-select" name="postograd" required>
                                <option value="" disabled selected>Selecione</option>
                                <option>Gen</option><option>Cel</option><option>TC</option><option>Maj</option>
                                <option>Capitão</option><option>1º Ten</option><option>2º Ten</option><option>Asp Of</option>
                                <option>Sten</option><option>1º Sgt</option><option>2º Sgt</option><option>3º Sgt</option>
                                <option>Cb</option><option>Sd</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Função</label>
                            <select class="form-select" name="funcao" required>
                                <option value="" disabled selected>Selecione uma função</option>
                                <?php
                                $sql = "SELECT id, nome FROM funcoes ORDER BY id ASC";
                                $res = $conexao->query($sql);
                                if ($res && $res->num_rows > 0) {
                                    while ($row = $res->fetch_assoc()) {
                                        echo "<option value='{$row['id']}'>".htmlspecialchars($row['nome'])."</option>";
                                    }
                                }
                                ?>
                            </select>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label fw-semibold">Batalhão</label>
                            <select class="form-select" name="batalhao" required>
                                <option value="" disabled selected>Selecione a OM</option>
                                <?php 
                                $batalhoes->data_seek(0);
                                while ($b = $batalhoes->fetch_assoc()): ?>
                                    <option value="<?= $b['id'] ?>"><?= htmlspecialchars($b['abreviatura']) ?> - <?= htmlspecialchars($b['nome']) ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Nome de Guerra</label>
                            <input type="text" class="form-control" name="nomeguerra" placeholder="Ex: SILVA" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Usuário (Login)</label>
                            <input type="text" class="form-control" name="usuario" placeholder="Ex: silva.2024" required>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label fw-semibold">Nome Completo</label>
                            <input type="text" class="form-control" name="nomecompleto" required>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label fw-semibold">Senha de Acesso</label>
                            <input type="password" class="form-control" name="senha" required>
                        </div>
                    </div>

                    <input type="hidden" name="status" value="não">
                    <input type="hidden" name="solicitacao" value="sim">

                    <div class="mt-4">
                        <button type="submit" class="btn btn-primary w-100 py-2 fw-bold">ENVIAR SOLICITAÇÃO AO ADMINISTRADOR</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<?php
// SweetAlert de Feedback
if(isset($_SESSION['cadastro_sucesso'])){
    echo "<script>Swal.fire('Sucesso', '".$_SESSION['cadastro_sucesso']."', 'success');</script>";
    unset($_SESSION['cadastro_sucesso']);
}
if(isset($_SESSION['cadastro_erro'])){
    echo "<script>Swal.fire('Erro', '".$_SESSION['cadastro_erro']."', 'error');</script>";
    unset($_SESSION['cadastro_erro']);
}
?>

</body>
</html>