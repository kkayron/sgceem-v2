<?php
include_once("../../conexao/config.php");

header('Content-Type: application/json; charset=utf-8');

// ===============================
// Validação de ID
// ===============================
if (!isset($_GET['id']) || empty($_GET['id'])) {
    echo json_encode(["sucesso" => false, "mensagem" => "ID do produto não informado."]);
    exit;
}

$id = intval($_GET['id']);

// ===============================
// Consulta principal com JOIN do batalhão
// ===============================
$sql = "
    SELECT 
        p.id, 
        p.data_inclusao, 
        p.codigo_produto, 
        p.nome_produto, 
        p.categoria_produto, 
        p.obs_produto, 
        p.estoque_minimo, 
        p.unidade,
        p.batalhao,
        d.nome AS nome_batalhao,
        d.abreviatura AS abreviatura_batalhao
    FROM almox_produtos p
    LEFT JOIN organizacoes_militares d ON d.id = p.batalhao
    WHERE p.id = ?
    LIMIT 1
";

$stmt = $conexao->prepare($sql);
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    echo json_encode([
        "sucesso" => true,
        "produto" => [
            "id" => $row['id'],
            "codigo_produto" => $row['codigo_produto'],
            "nome_produto" => $row['nome_produto'],
            "categoria_produto" => $row['categoria_produto'],
            "data_inclusao" => $row['data_inclusao'],
            "obs_produto" => $row['obs_produto'],
            "estoque_minimo" => $row['estoque_minimo'],
            "unidade" => $row['unidade'],
            "batalhao" => $row['batalhao'],
            "nome_batalhao" => $row['nome_batalhao'],
            "abreviatura_batalhao" => $row['abreviatura_batalhao']
        ]
    ]);
} else {
    echo json_encode(["sucesso" => false, "mensagem" => "Produto não encontrado."]);
}

$stmt->close();
$conexao->close();
?>
