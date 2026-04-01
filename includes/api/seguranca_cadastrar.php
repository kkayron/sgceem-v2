<?php
function verificarPermissao($pagina_id){

    if(session_status() === PHP_SESSION_NONE){
        session_start();
    }

    if (!isset($_SESSION['usuario_id']) || empty($_SESSION['usuario'])) {
        http_response_code(401);
        exit('Sessão inválida.');
    }

    if (empty($_SESSION['permissoes'][$pagina_id]['pode_cadastrar'])) {
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
        link.click(); // usa o sistema SPA
    }else{
        // fallback caso não encontre
        window.location.href = 'index.php';
    }

});
</script>

</body>
</html>
        <?php
        exit;
    }

    return [
        'cadastrar' => $_SESSION['permissoes'][$pagina_id]['pode_cadastrar'] ?? false,
        'editar'    => $_SESSION['permissoes'][$pagina_id]['pode_editar'] ?? false,
        'deletar'   => $_SESSION['permissoes'][$pagina_id]['pode_deletar'] ?? false,
        'importar'  => $_SESSION['permissoes'][$pagina_id]['pode_importar'] ?? false
    ];
}