<?php
function verificarPermissao($pagina_ids){

    if(session_status() === PHP_SESSION_NONE){
        session_start();
    }

    // 🔒 TEMPO DE SESSÃO (ex: 30 minutos)
    $tempo_expiracao = 1800;

    if (isset($_SESSION['ultimo_acesso'])) {
        if ((time() - $_SESSION['ultimo_acesso']) > $tempo_expiracao) {

            session_unset();
            session_destroy();
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
                icon: 'warning',
                title: 'Sessão Expirada',
                text: 'Sua sessão expirou por inatividade.',
                confirmButtonText: 'Fazer login novamente',
                allowOutsideClick: false
            }).then(() => {
                window.location.href = 'login.php';
            });
            </script>
            </body>
            </html>
            <?php
            exit;
        }
    }

    // 🔄 Atualiza tempo de atividade
    $_SESSION['ultimo_acesso'] = time();

    // 🔒 Verificação de sessão
    if (!isset($_SESSION['usuario_id']) || empty($_SESSION['usuario'])) {
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
            title: 'Sessão inválida',
            text: 'Faça login novamente.',
            confirmButtonText: 'Ir para login',
            allowOutsideClick: false
        }).then(() => {
            window.location.href = 'login.php';
        });
        </script>
        </body>
        </html>
        <?php
        exit;
    }

    // 🔒 CSRF (mantido)
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    // Garante array
    if (!is_array($pagina_ids)) {
        $pagina_ids = [$pagina_ids];
    }

    $tem_acesso = false;

    $permissoes = [
        'cadastrar' => false,
        'editar'    => false,
        'deletar'   => false,
        'importar'  => false,
        'exportar'  => false
    ];

    foreach ($pagina_ids as $pagina_id) {

        if (!empty($_SESSION['permissoes'][$pagina_id]['pode_editar'])) {

            $tem_acesso = true;

            $permissoes['acessar'] = $permissoes['acessar'] || ($_SESSION['permissoes'][$pagina_id]['pode_acessar'] ?? false);
            $permissoes['editar']    = $permissoes['editar']    || ($_SESSION['permissoes'][$pagina_id]['pode_editar'] ?? false);
            $permissoes['deletar']   = $permissoes['deletar']   || ($_SESSION['permissoes'][$pagina_id]['pode_deletar'] ?? false);
            $permissoes['importar']  = $permissoes['importar']  || ($_SESSION['permissoes'][$pagina_id]['pode_importar'] ?? false);
            $permissoes['exportar']  = $permissoes['exportar']  || ($_SESSION['permissoes'][$pagina_id]['pode_exportar'] ?? false);
        }
    }

    // 🔒 Sem permissão
    if (!$tem_acesso) {
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
            text: 'Você não possui permissão para editar esta página.',
            confirmButtonText: 'Voltar ao Painel',
            allowOutsideClick: false
        }).then(() => {

            const link = document.querySelector('[data-page="partes/home.php"]');

            if(link){
                link.click();
            }else{
                window.location.href = 'index.php';
            }

        });

        </script>
        </body>
        </html>
        <?php
        exit;
    }

    return $permissoes;
}