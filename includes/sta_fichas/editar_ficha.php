<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

require_once '../../conexao/config.php';
require_once '../funcoes/log.php';

function post($key) {
  return $_POST[$key] ?? null;
}

$id_ficha = intval(post('id'));
if ($id_ficha <= 0) {
  echo json_encode(['status' => 'erro', 'mensagem' => 'ID da ficha é inválido.']);
  exit;
}

$usuarioLogado = $_SESSION['usuario_id'] ?? 0;

$camposObrigatorios = [
  'id_viatura', 'id_om', 'data_abertura', 'solicitante', 'motorista',
  'subunidade', 'destino', 'cidade', 'chefe_apresentar', 'local_apresentar',
  'horario_apresentar', 'natureza', 'status', 'data_prevista'
];

foreach ($camposObrigatorios as $campo) {
  if (!post($campo)) {
    echo json_encode(['status' => 'erro', 'mensagem' => "Campo obrigatório '$campo' não preenchido."]);
    exit;
  }
}

// Dados recebidos
$dados = [
  'id_viatura'             => intval(post('id_viatura')),
  'id_om'                  => intval(post('id_om')),
  'data_abertura'          => post('data_abertura'),
  'data_prevista'          => post('data_prevista'),
  'solicitante'            => post('solicitante'),
  'motorista'              => post('motorista'),
  'subunidade'             => post('subunidade'),
  'destino'                => post('destino'),
  'cidade'                 => post('cidade'),
  'chefe_apresentar'       => post('chefe_apresentar'),
  'local_apresentar'       => post('local_apresentar'),
  'horario_apresentar'     => post('horario_apresentar'),
  'natureza'               => post('natureza'),
  'status'                 => post('status'),
  'data_saida'             => post('data_saida'),
  'hora_saida'             => post('hora_saida'),
  'odo_saida'              => post('odo_saida'),
  'data_retorno'           => post('data_retorno'),
  'hora_retorno'           => post('hora_retorno'),
  'odo_retorno'            => post('odo_retorno'),
  'observacoes_pos_emprego'=> post('observacoes_pos_emprego')
];

$force = isset($_POST['force']) && $_POST['force'] == '1';

// Verificar conflito de fichas abertas no mesmo período
if (!$force) {
  $stmtCheck = $conexao->prepare("
    SELECT id, data_abertura, data_prevista 
    FROM sta_fichas 
    WHERE id_viatura = ? 
      AND status = 'Aberta'
      AND id != ?
      AND (
        (data_abertura <= ? AND data_prevista >= ?) OR
        (data_abertura <= ? AND data_prevista >= ?) OR
        (data_abertura >= ? AND data_prevista <= ?)
      )
    LIMIT 1
  ");
  $stmtCheck->bind_param(
    "iissssss",
    $dados['id_viatura'],
    $id_ficha,
    $dados['data_abertura'], $dados['data_abertura'],
    $dados['data_prevista'], $dados['data_prevista'],
    $dados['data_abertura'], $dados['data_prevista']
  );
  $stmtCheck->execute();
  $resCheck = $stmtCheck->get_result();
  if ($resCheck->num_rows > 0) {
    $conflito = $resCheck->fetch_assoc();
    echo json_encode([
      'status' => 'confirmar',
      'mensagem' => "Já existe uma ficha (#{$conflito['id']}) aberta nesse intervalo " .
                         date('d/m/Y', strtotime($conflito['data_abertura'])) . " até " .
                         date('d/m/Y', strtotime($conflito['data_prevista'])) . " . Deseja continuar mesmo assim?"
    ]);
    exit;
  }
  $stmtCheck->close();
}

// Recuperar dados antigos
$dados_anteriores = $conexao->query("SELECT * FROM sta_fichas WHERE id = $id_ficha")->fetch_assoc();

// Atualizar
$stmt = $conexao->prepare("UPDATE sta_fichas SET
  id_viatura = ?, batalhao = ?, data_abertura = ?, data_prevista = ?, solicitante = ?, motorista = ?,
  subunidade = ?, destino = ?, cidade = ?, chefe_apresentar = ?, local_apresentar = ?,
  horario_apresentar = ?, natureza = ?, status = ?, data_saida = ?, hora_saida = ?,
  odo_saida = ?, data_retorno = ?, hora_retorno = ?, odo_retorno = ?, observacoes_pos_emprego = ?
  WHERE id = ?");

$stmt->bind_param(
  "iissssssssssssssdssdsi",
  $dados['id_viatura'],
  $dados['id_om'],
  $dados['data_abertura'],
  $dados['data_prevista'],
  $dados['solicitante'],
  $dados['motorista'],
  $dados['subunidade'],
  $dados['destino'],
  $dados['cidade'],
  $dados['chefe_apresentar'],
  $dados['local_apresentar'],
  $dados['horario_apresentar'],
  $dados['natureza'],
  $dados['status'],
  $dados['data_saida'],
  $dados['hora_saida'],
  $dados['odo_saida'],
  $dados['data_retorno'],
  $dados['hora_retorno'],
  $dados['odo_retorno'],
  $dados['observacoes_pos_emprego'],
  $id_ficha
);

if (!$stmt->execute()) {
  echo json_encode(['status' => 'erro', 'mensagem' => 'Erro ao atualizar ficha: ' . $stmt->error]);
  exit;
}
$stmt->close();

// Log
$alteracoes = [];
foreach ($dados as $campo => $valorNovo) {
  $valorAntigo = $dados_anteriores[$campo] ?? '';
  if ((string)$valorNovo !== (string)$valorAntigo) {
    $alteracoes[] = ucfirst(str_replace('_', ' ', $campo)) . ": '$valorAntigo' → '$valorNovo'";
  }
}
if (!empty($alteracoes)) {
  registrar_log(
    $conexao,
    $usuarioLogado,
    'Editar Ficha STA',
    "Ficha #$id_ficha atualizada: " . implode('; ', $alteracoes),
    $id_ficha
  );
}

echo json_encode(['status' => 'sucesso', 'mensagem' => "Ficha #$id_ficha atualizada com sucesso."]);
