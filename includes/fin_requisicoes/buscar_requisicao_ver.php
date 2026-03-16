<?php
include '../../conexao/config.php';

$id = intval($_GET['id']);
$retorno = ['sucesso' => false];

$sql = "SELECT * FROM fin_requisicao WHERE id = ?";
$stmt = $conexao->prepare($sql);
$stmt->bind_param("i", $id);
$stmt->execute();
$requisicao = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$requisicao) {
  echo json_encode(['sucesso' => false, 'mensagem' => 'Requisição não encontrada.']);
  exit;
}

$sql_itens = "
  SELECT fri.id_item, fri.quant_saida_item, fpi.descricao_item, fpi.valor_unt
  FROM fin_requisicao_itens fri
  INNER JOIN fin_pregao_itens fpi ON fri.id_item = fpi.id
  WHERE fri.id_requisicao = ?
";

$stmt = $conexao->prepare($sql_itens);
$stmt->bind_param("i", $id);
$stmt->execute();
$itens = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$retorno = [
  'sucesso' => true,
  'requisicao' => $requisicao,
  'itens' => $itens
];

echo json_encode($retorno);
?>
