<?php
session_start();
include_once("../../conexao/config.php");

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
// BUSCA PRODUTOS FILTRADOS PELOS BATALHÕES PERMITIDOS
// ======================================================
$sql = "
    SELECT 
        p.id, 
        p.nome_produto, 
        p.categoria_produto,
        p.batalhao,
        om.nome AS nome_batalhao,
        om.abreviatura AS abreviatura_batalhao
    FROM almox_produtos p
    LEFT JOIN organizacoes_militares om ON om.id = p.batalhao
    WHERE p.batalhao IN ($idsPermitidosStr)
    ORDER BY p.nome_produto ASC
";

$result = $conexao->query($sql);

$produtos = [];
while ($row = $result->fetch_assoc()) {
    $produtos[] = [
        'id' => $row['id'],
        'nome_produto' => $row['nome_produto'],
        'categoria_produto' => $row['categoria_produto'],
        'batalhao' => $row['batalhao'],
        'nome_batalhao' => $row['nome_batalhao'],
        'abreviatura_batalhao' => $row['abreviatura_batalhao']
    ];
}

header('Content-Type: application/json; charset=utf-8');
echo json_encode(['sucesso' => true, 'produtos' => $produtos]);
?>
