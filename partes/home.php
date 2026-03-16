<?php
header('Content-Type: text/html; charset=utf-8');
session_start();
include_once('../conexao/config.php');

// ID da página correspondente no banco
$pagina_id = intval(48); // <--- ajuste conforme o ID da página

// Verifica login e permissão de acesso
if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit;
}

// Mostrar aviso apenas após login
$mostrarAviso = false;

if (!empty($_SESSION['login_recente'])) {
    $mostrarAviso = true;
    unset($_SESSION['login_recente']); // evita repetir
}

if (empty($_SESSION['permissoes'][$pagina_id]['pode_acessar'])) {
    ?>
    <!DOCTYPE html>
    <html lang="pt-BR">
    <head>
        <meta charset="UTF-8">
        <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    </head>
    <body>
        <script>
            Swal.fire({
                icon: 'error',
                title: 'Acesso Negado',
                text: 'Você não possui permissão para acessar esta página.',
                confirmButtonText: 'Voltar ao Painel',
                allowOutsideClick: false
            }).then(() => window.location.href = 'index.php#partes/conteudo.php');
        </script>
    </body>
    </html>
    <?php
    exit;
}

// Permissões específicas
$pode_cadastrar = $_SESSION['permissoes'][$pagina_id]['pode_cadastrar'] ?? false;
$pode_editar    = $_SESSION['permissoes'][$pagina_id]['pode_editar'] ?? false;
$pode_deletar   = $_SESSION['permissoes'][$pagina_id]['pode_deletar'] ?? false;
?>
<?php

// ===============================
// 🔐 Proteção de acesso
// ===============================
if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit;
}

// ===============================
// 🔹 Dados do usuário
// ===============================
$nomeGuerra = $_SESSION['nomeguerra'] ?? 'Usuário';
$postoGrad  = $_SESSION['postograd']  ?? '';
$funcao     = $_SESSION['funcao_nome'] ?? '';
$foto       = $_SESSION['foto'] ?? '';
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>GCEEM | Início</title>

    <!-- Bootstrap -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>
        body {
            background: #f4f6f9;
        }
        .welcome-card {
            max-width: 720px;
            margin: 110px auto;
        }
        .user-photo {
            width: 90px;
            height: 90px;
            object-fit: cover;
            border-radius: 50%;
            border: 3px solid #dee2e6;
        }
    </style>
</head>
<body>

<div class="container">
    <div class="card shadow-sm welcome-card">
        <div class="card-body text-center p-5">

            <?php if (!empty($foto)) : ?>
                <img src="assets/fotoperfil/<?= htmlspecialchars($foto) ?>" 
                     class="user-photo mb-3" 
                     alt="Foto do usuário">
            <?php endif; ?>

            <h3 class="fw-bold text-primary mb-2">
                Bem-vindo ao SGCEEM
            </h3>

            <p class="fs-5 mb-1">
                <?= htmlspecialchars($postoGrad) ?> <strong><?= htmlspecialchars($nomeGuerra) ?></strong>
            </p>

            <?php if ($funcao) : ?>
                <p class="text-muted mb-3">
                    <?= htmlspecialchars($funcao) ?>
                </p>
            <?php endif; ?>

            <hr class="my-4">

            <p class="text-muted">
                Sistema de Gestão e Controle Administrativo, Financeiro e Operacional da Cia E Eqp Mnt.
            </p>

            <p class="small text-muted mb-0">
                Utilize o menu para acessar os módulos disponíveis.
            </p>

        </div>
    </div>
</div>

</body>
<?php if ($mostrarAviso): ?>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
Swal.fire({
    icon: 'info',
    title: 'Aviso do Sistema',
    html: `
        <b>Sistema SGCEEM</b><br><br>
        O acesso a este sistema é restrito a usuários autorizados.<br>
        Todas as ações realizadas são registradas para controle administrativo.
    `,
    confirmButtonText: 'Entendido',
    confirmButtonColor: '#0d6efd'
});
</script>
<?php endif; ?>
</html>
