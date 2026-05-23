<?php

session_start();

header('Content-Type: application/json');

include_once('../../conexao/config.php');

$id = intval($_GET['id'] ?? 0);

if (!$id) {

    echo json_encode([
        'sucesso' => false,
        'mensagem' => 'ID inválido'
    ]);

    exit;
}


// ==========================
// BUSCAR FROTA + OM
// ==========================
$stmt = $conexao->prepare("
    SELECT
        f.id,
        f.batalhao,
        f.batalhao_origem,

        om.nome,
        om.abreviatura,

        om_origem.nome AS nome_origem,
        om_origem.abreviatura AS abreviatura_origem

    FROM frota f

    LEFT JOIN organizacoes_militares om
        ON om.id = f.batalhao

    LEFT JOIN organizacoes_militares om_origem
        ON om_origem.id = f.batalhao_origem

    WHERE f.id = ?
");
$stmt->bind_param("i", $id);

$stmt->execute();

$res = $stmt->get_result();

$frota = $res->fetch_assoc();

$stmt->close();


if (!$frota) {

    echo json_encode([
        'sucesso' => false,
        'mensagem' => 'Ativo não encontrado'
    ]);

    exit;
}


$batalhaoOrigem = (int)$frota['batalhao_origem'];

if ($batalhaoOrigem <= 0) {
    $batalhaoOrigem = (int)$frota['batalhao'];
}

echo json_encode([

    'sucesso' => true,

    'batalhao_atual' =>
        $frota['abreviatura'] ?: $frota['nome'],

    'batalhao_origem_id' =>
        $batalhaoOrigem

]);
?>