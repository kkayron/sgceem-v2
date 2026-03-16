<?php
include '../../conexao/config.php';

$id = intval($_GET['id'] ?? 0);
if (!$id) {
    echo json_encode(['sucesso' => false, 'mensagem' => 'ID inválido']);
    exit;
}

// 1) Busca dados completos da requisição
$sqlReq = "
    SELECT *
    FROM fin_requisicao
    WHERE id = ?
";
$stmt1 = $conexao->prepare($sqlReq);
$stmt1->bind_param("i", $id);
$stmt1->execute();
$resReq = $stmt1->get_result();
$req = $resReq->fetch_assoc();
$stmt1->close();

if (!$req) {
    echo json_encode(['sucesso' => false, 'mensagem' => 'Requisição não encontrada']);
    exit;
}

// 2) Busca dados do empenho
$sqlEmp = "
    SELECT *
    FROM fin_empenhos
    WHERE id_requisicao = ?
";
$stmt2 = $conexao->prepare($sqlEmp);
$stmt2->bind_param("i", $id);
$stmt2->execute();
$resEmp = $stmt2->get_result();
$emp = $resEmp->fetch_assoc();
$stmt2->close();

echo json_encode([
    'sucesso' => true,
    'requisicao' => $req,
    'empenho' => $emp
]);
?>
