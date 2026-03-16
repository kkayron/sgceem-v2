<?php
include '../../conexao/config.php';
header('Content-Type: application/json');

$id = intval($_GET['id'] ?? 0);
$retorno = ['sucesso' => false];

if ($id <= 0) {
    echo json_encode($retorno);
    exit;
}

// Buscar dados principais da OS com nome do batalhão
$sql = "SELECT os.*, om.nome AS nome_batalhao 
        FROM os_principal os
        LEFT JOIN organizacoes_militares om ON om.id = os.batalhao
        WHERE os.id = $id";
$res = $conexao->query($sql);

if ($res && $res->num_rows > 0) {
    $os = $res->fetch_assoc();
    $retorno['os'] = $os;

    // Buscar locais permitidos da OM da OS e nome do batalhão
    $batalhao_id = intval($os['batalhao']);
    $retorno['locais'] = [];
    if ($batalhao_id > 0) {
        $sqlLocais = "SELECT cd.destino, om.nome AS nome_batalhao
                      FROM config_destinos cd
                      JOIN organizacoes_militares om ON om.id = cd.batalhao
                      WHERE cd.batalhao = $batalhao_id
                      ORDER BY cd.destino ASC";
        $locaisRes = $conexao->query($sqlLocais);
        while ($l = $locaisRes->fetch_assoc()) {
            $retorno['locais'][] = $l;
        }
    }

    // Falhas identificadas
    $retorno['falhas'] = [];
    $falhas = $conexao->query("SELECT * FROM os_falhas WHERE id_osprincipal = $id");
    while ($f = $falhas->fetch_assoc()) {
        $retorno['falhas'][] = $f;
    }

    // Pessoal utilizado
    $retorno['pessoal'] = [];
    $pessoal = $conexao->query("SELECT * FROM os_pessoal WHERE id_osprincipal = $id");
    while ($p = $pessoal->fetch_assoc()) {
        $retorno['pessoal'][] = $p;
    }

    // Serviços realizados
    $retorno['servicos'] = [];
    $servicos = $conexao->query("SELECT * FROM os_rlzdmnt WHERE id_osprincipal = $id");
    while ($s = $servicos->fetch_assoc()) {
        $retorno['servicos'][] = $s;
    }

    // Materiais utilizados
    $retorno['materiais'] = [];
    $materiais = $conexao->query("SELECT * FROM os_itens WHERE id_osprincipal = $id");
    while ($m = $materiais->fetch_assoc()) {
        $retorno['materiais'][] = $m;
    }

    $retorno['sucesso'] = true;
}

echo json_encode($retorno, JSON_UNESCAPED_UNICODE);
