<?php
include '../../conexao/config.php';

header('Content-Type: application/json');

$id = intval($_GET['id'] ?? 0);
$retorno = ['sucesso' => false];

if ($id <= 0) {
    echo json_encode($retorno);
    exit;
}

// ================================
// BUSCA DADOS DO FORNECEDOR + OM
// ================================
$sql = "
    SELECT 
        f.*,
        om.nome AS nome_batalhao,
        om.abreviatura AS abreviatura_batalhao
    FROM fin_fornecedores f
    LEFT JOIN organizacoes_militares om ON om.id = f.batalhao
    WHERE f.id = ?
";

$stmt = $conexao->prepare($sql);
$stmt->bind_param("i", $id);
$stmt->execute();
$res = $stmt->get_result();

if ($res && $res->num_rows > 0) {
    $retorno['forn'] = $res->fetch_assoc();
    $retorno['sucesso'] = true;
}

echo json_encode($retorno, JSON_UNESCAPED_UNICODE);
