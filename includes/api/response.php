<?php

function api_response($status, $mensagem, $dados = null)
{
    header('Content-Type: application/json; charset=utf-8');

    echo json_encode([
        'status' => $status,
        'mensagem' => $mensagem,
        'dados' => $dados
    ]);

    exit;
}