<?php
require_once '../../conexao/config.php';

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id <= 0) {
  echo json_encode([]);
  exit;
}

$sql = "SELECT 
          DATE_FORMAT(data_hora, '%d/%m/%Y %H:%i:%s') AS data, 
          acao, 
          descricao, 
          CONCAT(u.posto_grad, ' ', u.nome_guerra) AS responsavel, 
          l.local 
        FROM logs l
        LEFT JOIN usuarios u ON l.usuario_id = u.id
        WHERE l.frota_id = ?
        ORDER BY l.data_hora DESC";

$stmt = $conexao->prepare($sql);
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();

$logs = [];
while ($row = $result->fetch_assoc()) {
  $logs[] = $row;
}

echo json_encode($logs);