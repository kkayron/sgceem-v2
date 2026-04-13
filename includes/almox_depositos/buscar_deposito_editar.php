<?php
include_once("../../conexao/config.php");

header('Content-Type: application/json; charset=utf-8');
$pagina_id = 50;

require_once('../api/seguranca_json_editar.php');
// ===============================
// Validação do ID
// ===============================
if (!isset($_GET['id']) || empty($_GET['id'])) {
    echo json_encode(["sucesso" => false, "mensagem" => "ID do depósito não informado."]);
    exit;
}

$id = intval($_GET['id']);

// ===============================
// Consulta com JOIN do batalhão
// ===============================
$sql = "
    SELECT 
        d.id,
        d.nome_deposito,
        d.local_deposito,
        d.observacoes_deposito,
        d.batalhao,
        o.nome AS nome_batalhao
    FROM almox_depositos d
    LEFT JOIN organizacoes_militares o ON o.id = d.batalhao
    WHERE d.id = ?
    LIMIT 1
";

$stmt = $conexao->prepare($sql);
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    echo json_encode([
        "sucesso" => true,
        "deposito" => $row
    ]);
} else {
    echo json_encode([
        "sucesso" => false,
        "mensagem" => "Depósito não encontrado."
    ]);
}

$stmt->close();
$conexao->close();
