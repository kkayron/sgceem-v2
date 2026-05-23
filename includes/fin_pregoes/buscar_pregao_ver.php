<?php
$pagina_id = 24;

require_once('../api/seguranca_json.php');

include '../../conexao/config.php';

$id = intval($_GET['id']);
$retorno = ['sucesso' => false];

$sql = "SELECT * FROM fin_pregao WHERE id = ?";
$stmt = $conexao->prepare($sql);
$stmt->bind_param("i", $id);
$stmt->execute();
$pregao = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$pregao) {
  echo json_encode(['sucesso' => false, 'mensagem' => 'Pregão não encontrado.']);
  exit;
}

$sql_itens = "
  SELECT pi.*, 
    COALESCE(SUM(ri.quant_saida_item), 0) as total_saida,
    (pi.saldo_item - COALESCE(SUM(ri.quant_saida_item), 0)) as disponivel
  FROM fin_pregao_itens pi
  LEFT JOIN fin_requisicao_itens ri ON pi.id = ri.id_item
  WHERE pi.id_pregao = ?
  GROUP BY pi.id
";
$stmt = $conexao->prepare($sql_itens);
$stmt->bind_param("i", $id);
$stmt->execute();
$itens = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$retorno = [
  'sucesso' => true,
  'pregao' => $pregao,
  'itens' => $itens
];

echo json_encode($retorno);
?>
