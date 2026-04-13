<?php
header('Content-Type: application/json; charset=utf-8');
session_start();
include_once("../../conexao/config.php");

$pagina_id = 30;

require_once('../api/seguranca_json.php');

// ======================================================
// IDENTIFICAÇÃO DO USUÁRIO E SUAS OMs VISÍVEIS
// ======================================================
$id_om_usuario = $_SESSION['usuario']['batalhao'] ?? 0;
$nivel_usuario = $_SESSION['usuario']['nivel'] ?? 0;

$idsPermitidos = [$id_om_usuario];

// Se não for nível 1 (admin), buscar subordinadas
if ($nivel_usuario != 1 && $id_om_usuario) {
    $sqlSub = "SELECT id_om_menor FROM organizacoes_militares_sub WHERE id_om_maior = ?";
    $stmtSub = $conexao->prepare($sqlSub);
    $stmtSub->bind_param('i', $id_om_usuario);
    $stmtSub->execute();
    $resSub = $stmtSub->get_result();
    while ($r = $resSub->fetch_assoc()) {
        $idsPermitidos[] = (int)$r['id_om_menor'];
    }
    $stmtSub->close();
} else {
    // Nível 1 (Administrador) pode ver todos
    $resAll = $conexao->query("SELECT id FROM organizacoes_militares");
    while ($r = $resAll->fetch_assoc()) {
        $idsPermitidos[] = (int)$r['id'];
    }
}

$idsPermitidos = array_unique($idsPermitidos);
$idsPermitidosStr = implode(',', array_map('intval', $idsPermitidos));

// ======================================================
// CONSULTA DE PRODUTOS (filtrando pelas OMs permitidas)
// ======================================================
// OBS: a tabela almox_produtos deve ter o campo "batalhao" (ou "id_om")
$sql = "
    SELECT 
        p.id,
        p.codigo_produto,
        p.nome_produto,
        p.categoria_produto,
        p.obs_produto,
        p.estoque_minimo,
        p.unidade,
        o.nome AS nome_batalhao,
        o.abreviatura AS abreviatura_batalhao
    FROM almox_produtos p
    INNER JOIN organizacoes_militares o ON o.id = p.batalhao
    WHERE p.batalhao IN ($idsPermitidosStr)
    ORDER BY o.nome, p.nome_produto
";

$result = $conexao->query($sql);

// ======================================================
// MONTA RETORNO JSON
// ======================================================
$produtos = [];
while ($row = $result->fetch_assoc()) {
    $produtos[] = [
        "id" => $row['id'],
        "codigo" => $row['codigo_produto'],
        "nome" => $row['nome_produto'],
        "categoria" => $row['categoria_produto'],
        "obs" => $row['obs_produto'],
        "estoque_minimo" => $row['estoque_minimo'],
        "unidade" => $row['unidade'],
        "batalhao_nome" => $row['nome_batalhao'],
        "batalhao_abrev" => $row['abreviatura_batalhao']
    ];
}

echo json_encode($produtos);
?>
