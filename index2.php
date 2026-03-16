<?php
session_start();
if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <title>SGF - Cia E Eqp Mnt</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0, shrink-to-fit=no" />
    <link rel="icon" href="assets/img/kaiadmin/favicon.png" type="image/x-icon">

    <link href="assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    
    <style>
        :root {
            --army-olive: #3A4132;
            --army-dark: #212529;
            --army-accent: #D9A21B;
            --sidebar-width: 260px;
            --sidebar-collapsed: 70px;
        }

        body {
            font-family: "Public Sans", sans-serif;
            background-color: #f4f6f9;
            margin: 0;
            display: flex;
        }

        /* SIDEBAR */
        #sidebar {
            width: var(--sidebar-width);
            height: 100vh;
            background: var(--army-olive);
            position: fixed;
            transition: all 0.3s;
            z-index: 1050;
            overflow-y: auto;
            overflow-x: hidden;
        }

        #sidebar.collapsed { width: var(--sidebar-collapsed); }

        .sidebar-header {
            padding: 15px;
            background: rgba(0,0,0,0.2);
            display: flex;
            align-items: center;
            height: 70px;
        }

        /* MENU LINKS */
        .nav-link {
            color: rgba(255,255,255,0.7);
            padding: 12px 20px;
            display: flex;
            align-items: center;
            transition: 0.2s;
            cursor: pointer;
            text-decoration: none;
            border-left: 3px solid transparent;
        }

        .nav-link:hover, .nav-link.active {
            background: rgba(255,255,255,0.1);
            color: var(--army-accent);
            border-left: 3px solid var(--army-accent);
        }

        .nav-link i { font-size: 1.2rem; min-width: 35px; }

        /* DROPDOWN INTERNO SIDEBAR */
        .submenu {
            background: rgba(0,0,0,0.1);
            list-style: none;
            padding: 0;
            display: none;
        }
        .submenu.show { display: block; }
        .submenu .nav-link { padding-left: 55px; font-size: 0.85rem; }

        /* WRAPPER CONTEÚDO */
        #wrapper {
            flex-grow: 1;
            margin-left: var(--sidebar-width);
            transition: all 0.3s;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
            width: 100%;
        }

        #wrapper.expanded { margin-left: var(--sidebar-collapsed); }

        /* NAVBAR SUPERIOR */
        .top-navbar {
            height: 70px;
            background: #fff;
            display: flex;
            align-items: center;
            padding: 0 20px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
            position: sticky;
            top: 0;
            z-index: 1000;
        }

        /* RESPONSIVIDADE */
        @media (max-width: 768px) {
            #sidebar { left: calc(-1 * var(--sidebar-width)); }
            #sidebar.mobile-show { left: 0; width: var(--sidebar-width); }
            #wrapper { margin-left: 0 !important; }
        }

        /* FOOTER */
        footer {
            background: #fff;
            padding: 15px;
            border-top: 1px solid #dee2e6;
            font-size: 0.8rem;
            text-align: center;
        }

        .link-text { transition: opacity 0.2s; }
        #sidebar.collapsed .link-text, #sidebar.collapsed .dropdown-toggle::after { 
            display: none; 
        }
    </style>
</head>

<body>

    <nav id="sidebar">
        <div class="sidebar-header">
             <img src="assets/img/kaiadmin/logo_light.png" alt="navbar brand" height="38" />
            <span class="ms-2 fw-bold text-white link-text">SGF - EB</span>
        </div>

        <div class="mt-3">
            <a href="index.php" class="nav-link active">
                <i class="bi bi-speedometer2"></i> <span class="link-text">Dashboard</span>
            </a>

            <div>
                <a class="nav-link dropdown-toggle" onclick="toggleSubmenu(this)">
                    <i class="bi bi-truck"></i> <span class="link-text">Frota</span>
                </a>
                <ul class="submenu">
                    <li><a href="#" class="nav-link" data-page="includes/frota/cadastro.php">Controle Odômetro</a></li>
                    <li><a href="#" class="nav-link">Manutenção</a></li>
                    <li><a href="#" class="nav-link">Escala de Uso</a></li>
                </ul>
            </div>

            <div>
                <a class="nav-link dropdown-toggle" onclick="toggleSubmenu(this)">
                    <i class="bi bi-people"></i> <span class="link-text">Efetivo</span>
                </a>
                <ul class="submenu">
                    <li><a href="#" class="nav-link">Listagem</a></li>
                    <li><a href="#" class="nav-link">Movimentações</a></li>
                </ul>
            </div>

            <a href="logout.php" class="nav-link text-danger mt-4">
                <i class="bi bi-box-arrow-left"></i> <span class="link-text">Sair</span>
            </a>
        </div>
    </nav>

    <div id="wrapper">
        <header class="top-navbar">
            <button class="btn btn-outline-dark btn-sm me-3" id="toggleSidebar">
                <i class="bi bi-list fs-5"></i>
            </button>

            <div class="ms-auto d-flex align-items-center">
                <div class="dropdown">
                    <a href="#" class="d-flex align-items-center text-dark text-decoration-none dropdown-toggle" data-bs-toggle="dropdown">
                        <div class="text-end me-2 d-none d-sm-block">
                            <div class="fw-bold small"><?= htmlspecialchars($_SESSION['postograd'] ?? '') ?></div>
                            <div class="text-muted" style="font-size: 0.75rem;"><?= htmlspecialchars($_SESSION['nomeguerra'] ?? '') ?></div>
                        </div>
                        <img src="assets/fotoperfil/<?= htmlspecialchars($_SESSION['foto'] ?? 'default.png') ?>" 
                             alt="perfil" class="rounded-circle" width="38" height="38" style="border: 2px solid var(--army-accent)">
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end border-0 shadow">
                        <li><a class="dropdown-item" href="#"><i class="bi bi-person me-2"></i>Editar Conta</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item text-danger" href="logout.php"><i class="bi bi-power me-2"></i>Sair</a></li>
                    </ul>
                </div>
            </div>
        </header>

        <main class="p-4 flex-grow-1">
            <div id="conteudo-dinamico">
                <?php include_once 'partes/conteudo.php'; ?>
            </div>
        </main>

        <footer>
            Copyright © <?= date('Y') ?>. Todos os direitos reservados - <br class="d-md-none">
            <strong>Sistema desenvolvido pelo 1º Ten Eng IGOR FELIPE ALVES DE CARVALHO</strong>
        </footer>
    </div>

    <script src="assets/js/core/jquery-3.7.1.min.js"></script>
    <script src="assets/js/core/popper.min.js"></script>
    <script src="assets/js/core/bootstrap.min.js"></script>
    
    <?php /* Mantenha todos os seus scripts <script src="..."> aqui exatamente como estavam */ ?>

    <script>
        // Lógica do Sidebar
        const btn = document.getElementById('toggleSidebar');
        const sidebar = document.getElementById('sidebar');
        const wrapper = document.getElementById('wrapper');

        btn.addEventListener('click', () => {
            if (window.innerWidth > 768) {
                sidebar.classList.toggle('collapsed');
                wrapper.classList.toggle('expanded');
            } else {
                sidebar.classList.toggle('mobile-show');
            }
        });

        // Função para submenus
        function toggleSubmenu(el) {
            const submenu = el.nextElementSibling;
            if (sidebar.classList.contains('collapsed')) {
                sidebar.classList.remove('collapsed');
                wrapper.classList.remove('expanded');
            }
            submenu.classList.toggle('show');
        }

        // Fechar menu mobile ao clicar fora
        document.addEventListener('click', (e) => {
            if (window.innerWidth <= 768 && !sidebar.contains(e.target) && !btn.contains(e.target)) {
                sidebar.classList.remove('mobile-show');
            }
        });
    </script>
</body>
</html>