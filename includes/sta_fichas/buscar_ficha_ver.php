<?php
header('Content-Type: application/json');
include '../../conexao/config.php';

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$retorno = ['sucesso' => false];

if (!$id) {
  echo json_encode($retorno);
  exit;
}

// Consulta da ficha e dados da viatura
$stmt = $conexao->prepare("
  SELECT f.*, 
         v.prefixo_sga, v.chassi, v.marca, v.modelo, v.foto_capa, 
         om.nome AS nome_om
  FROM sta_fichas f
  LEFT JOIN frota v ON f.id_viatura = v.id
  LEFT JOIN organizacoes_militares om ON f.batalhao = om.id
  WHERE f.id = ?
");
$stmt->bind_param("i", $id);
$stmt->execute();
$res = $stmt->get_result();

if ($res && $res->num_rows > 0) {
  $retorno['ficha'] = $res->fetch_assoc();
  $retorno['logs'] = [];

  // Logs
  $logs = $conexao->query("
    SELECT l.*, u.nomeguerra, u.postograd 
    FROM logs l 
    LEFT JOIN usuarios u ON u.id = l.usuario_id 
    WHERE l.frota_id = $id AND l.acao LIKE '%Ficha%' 
    ORDER BY l.data_hora DESC
  ");

  if ($logs) {
    while ($log = $logs->fetch_assoc()) {
      $retorno['logs'][] = $log;
    }
  }

  $retorno['sucesso'] = true;
}

echo json_encode($retorno, JSON_UNESCAPED_UNICODE);
