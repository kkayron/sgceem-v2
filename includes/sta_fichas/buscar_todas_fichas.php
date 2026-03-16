<?php
header('Content-Type: application/json');
include '../../conexao/config.php';

$id_viatura = filter_input(INPUT_GET, 'id_viatura', FILTER_VALIDATE_INT);
$retorno = ['sucesso' => false];

if (!$id_viatura) {
  echo json_encode($retorno);
  exit;
}

// Consulta dados da viatura
$stmtViatura = $conexao->prepare("
  SELECT id, prefixo_sga, chassi, marca, modelo, foto_capa
  FROM frota
  WHERE id = ?
");
$stmtViatura->bind_param("i", $id_viatura);
$stmtViatura->execute();
$resViatura = $stmtViatura->get_result();

if ($resViatura && $resViatura->num_rows > 0) {
  $retorno['viatura'] = $resViatura->fetch_assoc();
} else {
  echo json_encode($retorno);
  exit;
}
$stmtViatura->close();

// Consulta todas as fichas da viatura
$stmtFichas = $conexao->prepare("
  SELECT id, data_abertura, data_prevista, status
  FROM sta_fichas
  WHERE id_viatura = ?
  ORDER BY data_abertura DESC, id DESC
");
$stmtFichas->bind_param("i", $id_viatura);
$stmtFichas->execute();
$resFichas = $stmtFichas->get_result();

$retorno['fichas'] = [];
while ($ficha = $resFichas->fetch_assoc()) {
  $retorno['fichas'][] = $ficha;
}
$stmtFichas->close();

$retorno['sucesso'] = true;
echo json_encode($retorno, JSON_UNESCAPED_UNICODE);
