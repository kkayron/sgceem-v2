<?php
function verificarPermissao($pagina_ids){

    if(session_status() === PHP_SESSION_NONE){
        session_start();
    }

    if (!isset($_SESSION['usuario_id']) || empty($_SESSION['usuario'])) {
        http_response_code(401);
        exit('Sessão inválida.');
    }

    // Garante array
    if (!is_array($pagina_ids)) {
        $pagina_ids = [$pagina_ids];
    }

    // Controle de acesso geral
    $tem_acesso = false;

    // Permissões acumuladas
    $permissoes = [
        'cadastrar' => false,
        'editar'    => false,
        'deletar'   => false,
        'importar'  => false,
        'exportar'  => false
    ];

    foreach ($pagina_ids as $pagina_id) {

        if (!empty($_SESSION['permissoes'][$pagina_id]['pode_acessar'])) {

            $tem_acesso = true;

            // Combina permissões (OR lógico)
            $permissoes['cadastrar'] = $permissoes['cadastrar'] || ($_SESSION['permissoes'][$pagina_id]['pode_cadastrar'] ?? false);
            $permissoes['editar']    = $permissoes['editar']    || ($_SESSION['permissoes'][$pagina_id]['pode_editar'] ?? false);
            $permissoes['deletar']   = $permissoes['deletar']   || ($_SESSION['permissoes'][$pagina_id]['pode_deletar'] ?? false);
            $permissoes['importar']  = $permissoes['importar']  || ($_SESSION['permissoes'][$pagina_id]['pode_importar'] ?? false);
            $permissoes['exportar']  = $permissoes['exportar']  || ($_SESSION['permissoes'][$pagina_id]['pode_exportar'] ?? false);
        }
    }

    // Se não tem acesso a nenhuma
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
            text: 'Você não possui permissão para acessar esta página.',
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