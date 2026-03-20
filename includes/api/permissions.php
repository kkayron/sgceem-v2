<?php

require_once __DIR__ . '/response.php';

function verificar_permissao($pagina_id, $acao)
{
    if (!isset($_SESSION['permissoes'][$pagina_id])) {
        api_response('erro', 'Permissões não encontradas.');
    }

    $permissoes = $_SESSION['permissoes'][$pagina_id];

    if (empty($permissoes[$acao])) {
        http_response_code(403);
        api_response('erro', 'Você não possui permissão para esta ação.');
    }

    return true;
}