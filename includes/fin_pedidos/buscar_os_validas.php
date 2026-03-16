<?php
include '../../conexao/config.php';
header('Content-Type: application/json; charset=utf-8');

$id_os_atual = intval($_GET['id_os_atual'] ?? 0);

$sql = "
  SELECT id, prefixo_sga, problema
  FROM os_principal
  WHERE status IN ('Em andamento','Aguardando Peças','Aguardando Suprimento')
";

if ($id_os_atual > 0) {
  $sql .= " OR id = $id_os_atual";
}
$sql .= " ORDER BY id";

$res = $conexao->query($sql);
$lista = [];

while ($row = $res->fetch_assoc()) {
  $lista[] = $row;
}

echo json_encode($lista, JSON_UNESCAPED_UNICODE);