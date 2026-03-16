<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

require_once '../../conexao/config.php';
require_once '../funcoes/log.php';

function post($key) {
  return $_POST[$key] ?? null;
}

$id = intval(post('id'));
if ($id <= 0) {
  echo json_encode(['status' => 'erro', 'mensagem' => 'ID da ordem inválido.']);
  exit;
}

$usuarioLogado = $_SESSION['usuario_id'] ?? 0;

// Campos principais
$id_empenho          = post('id_empenho');
$data_entrega_limite = post('data_entrega_limite');
$data_cadastro       = post('data_cadastro');
$status              = post('status');
$empresa_nome        = post('empresa_nome');
$empresa_cnpj        = post('empresa_cnpj');
$empresa_email       = post('empresa_email');
$local_entrega       = post('local_entrega');
$batalhao       = post('batalhao');
$nome_responsavel    = post('nome_responsavel');
$contato_responsavel = post('contato_responsavel');
$observacao_final    = post('observacao_final');

$pedidos = $_POST['pedidos'] ?? [];

if (
  !$data_entrega_limite ||
  !$status ||
  !$empresa_nome ||
  !$empresa_cnpj ||
  !$local_entrega ||
  !$nome_responsavel ||
  !is_array($pedidos) || count($pedidos) === 0
) {
  echo json_encode(['status' => 'erro', 'mensagem' => 'Preencha todos os campos obrigatórios e selecione ao menos um pedido.']);
  exit;
}

// 1) Verificar se todos os pedidos ainda podem ser vinculados
$pedidosFiltrados = [];
$pedidosDuplicados = [];

foreach ($pedidos as $pid) {
  $pid = (int)$pid;
  if ($pid <= 0) continue;

  if (in_array($pid, $pedidosFiltrados)) {
    $pedidosDuplicados[] = $pid;
    continue;
  }

  $pedidosFiltrados[] = $pid;

  $stmt = $conexao->prepare("SELECT id_ordemforn FROM fin_ordemforn_pedidos WHERE id_pedido = ? AND id_ordemforn != ?");
  $stmt->bind_param("ii", $pid, $id);
  $stmt->execute();
  $res = $stmt->get_result();

  if ($res->num_rows > 0) {
    echo json_encode([
      'status' => 'erro',
      'mensagem' => "Pedido #$pid já está vinculado a outra ordem e não pode ser selecionado."
    ]);
    exit;
  }
  $stmt->close();
}

if (!empty($pedidosDuplicados)) {
  echo json_encode([
    'status' => 'erro',
    'mensagem' => 'Há pedidos repetidos: ' . implode(', ', $pedidosDuplicados)
  ]);
  exit;
}

// 2) Atualizar a ordem principal
$id_empenho = is_numeric($id_empenho) && $id_empenho > 0 ? (int)$id_empenho : null;

$stmtUpdate = $conexao->prepare("
  UPDATE fin_ordemforn SET
    id_empenho = ?,
    data_cadastro = ?,
    data_entrega_limite = ?,
    status = ?,
    empresa_nome = ?,
    empresa_cnpj = ?,
    empresa_email = ?,
    local_entrega = ?,
    nome_responsavel = ?,
    contato_responsavel = ?,
    observacao_final = ?,
    batalhao = ?
  WHERE id = ?
");

$stmtUpdate->bind_param(
  "isssssssssssi",
  $id_empenho,
  $data_cadastro,
  $data_entrega_limite,
  $status,
  $empresa_nome,
  $empresa_cnpj,
  $empresa_email,
  $local_entrega,
  $nome_responsavel,
  $contato_responsavel,
  $observacao_final,
  $batalhao,
  $id
);

if (!$stmtUpdate->execute()) {
  echo json_encode(['status' => 'erro', 'mensagem' => 'Erro ao atualizar ordem: ' . $stmtUpdate->error]);
  exit;
}
$stmtUpdate->close();

// 3) Apagar os vínculos antigos e inserir os novos
$conexao->query("DELETE FROM fin_ordemforn_pedidos WHERE id_ordemforn = $id");

$stmtLink = $conexao->prepare("
  INSERT INTO fin_ordemforn_pedidos (id_pedido, id_ordemforn)
  VALUES (?, ?)
");

foreach ($pedidosFiltrados as $pid) {
  $stmtLink->bind_param("ii", $pid, $id);
  $stmtLink->execute();
}
$stmtLink->close();

// 4) Log
registrar_log(
  $conexao,
  $usuarioLogado,
  'Editar Ordem de Fornecimento',
  "Ordem #$id atualizada com pedidos: [" . implode(", ", $pedidosFiltrados) . "]",
  $id
);

// 5) Sucesso
echo json_encode(['status' => 'sucesso', 'mensagem' => "Ordem #$id atualizada com sucesso."]);
