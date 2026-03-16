<?php
header('Content-Type: application/json');
include '../../conexao/config.php';
include '../funcoes/log_os.php';
session_start();

$id_prod = $_POST['id'] ?? null;
if (!$id_prod) {
  echo json_encode(['status' => 'erro', 'mensagem' => 'ID do produto não recebido.']);
  exit;
}

$usuarioLogado = $_SESSION['usuario_id'] ?? 0;

// Buscar dados antigos
$consulta = $conexao->prepare("SELECT * FROM almox_produtos WHERE id = ?");
$consulta->bind_param('i', $id_prod);
$consulta->execute();
$old_os = $consulta->get_result()->fetch_assoc();

if (!$old_os) {
  echo json_encode(['status' => 'erro', 'mensagem' => 'Produto não encontrado.']);
  exit;
}

// Captura dos dados do formulário
$data_inclusao     = $_POST['data_inclusao']     ?? '';
$nome_produto      = $_POST['nome_produto']      ?? '';
$codigo_produto    = $_POST['codigo_produto']    ?? '';
$categoria_produto = $_POST['categoria_produto'] ?? '';
$obs_produto       = $_POST['obs_produto']       ?? '';
$estoque_minimo    = $_POST['estoque_minimo']    ?? '';
$unidade           = $_POST['unidade']           ?? '';

// Preparar atualização
$update = $conexao->prepare("
  UPDATE almox_produtos 
  SET data_inclusao = ?, nome_produto = ?, codigo_produto = ?, categoria_produto = ?, 
      obs_produto = ?, estoque_minimo = ?, unidade = ?
  WHERE id = ?
");

if (!$update) {
  echo json_encode(['status' => 'erro', 'mensagem' => 'Erro no prepare: ' . $conexao->error]);
  exit;
}

$update->bind_param(
  'sssssssi',
  $data_inclusao,
  $nome_produto,
  $codigo_produto,
  $categoria_produto,
  $obs_produto,
  $estoque_minimo,
  $unidade,
  $id_prod
);

if (!$update->execute()) {
  echo json_encode(['status' => 'erro', 'mensagem' => 'Erro ao atualizar: ' . $update->error]);
  exit;
}

// LOG - comparando alterações
$alteracoes = [];
$novos_valores = [
  'data_inclusao'     => $data_inclusao,
  'nome_produto'      => $nome_produto,
  'codigo_produto'    => $codigo_produto,
  'categoria_produto' => $categoria_produto,
  'obs_produto'       => $obs_produto,
  'estoque_minimo'    => $estoque_minimo,
  'unidade'           => $unidade
];

foreach ($novos_valores as $campo => $novo_valor) {
  $valor_antigo = $old_os[$campo] ?? '';
  if ($novo_valor != $valor_antigo) {
    $alteracoes[] = ucfirst($campo) . ": '$valor_antigo' → '$novo_valor'";
  }
}

if (!empty($alteracoes)) {
  $descricao = 'Edição de produto (ID ' . $id_prod . '): ' . implode(', ', $alteracoes);
  registrar_log($conexao, $usuarioLogado, 'almox_produtos', $descricao);
}

echo json_encode(['status' => 'sucesso', 'mensagem' => 'Alterações realizadas com sucesso.']);
?>
