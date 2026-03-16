<?php
include '../../conexao/config.php';

header('Content-Type: application/json');

$id = intval($_GET['id'] ?? 0);
$retorno = ['sucesso' => false];

if ($id <= 0) {
  echo json_encode($retorno);
  exit;
}

$sql = "SELECT * FROM fin_requisicao WHERE id = $id";
$res = $conexao->query($sql);

if ($res && $res->num_rows > 0) {
  $retorno['requisicao'] = $res->fetch_assoc();

  $retorno['itens'] = [];
  $itens = $conexao->query("SELECT * FROM fin_requisicao_itens WHERE id_requisicao = $id");
  while ($i = $itens->fetch_assoc()) {
    $retorno['itens'][] = $i;
  }

  $retorno['sucesso'] = true;
}

echo json_encode($retorno, JSON_UNESCAPED_UNICODE);
?>
