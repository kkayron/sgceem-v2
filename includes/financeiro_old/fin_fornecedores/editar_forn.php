<?php
header('Content-Type: application/json');

include '../../conexao/config.php';
include '../funcoes/log_os.php';
session_start();

$id_forn = $_POST['id_forn'] ?? null;
if (!$id_forn) {
  echo json_encode(['status' => 'erro', 'mensagem' => 'ID do fornecedor não recebido.']);
  exit;
}

$usuarioLogado = $_SESSION['usuario_id'] ?? 0;

// Obter dados antigos para log
$old_os = $conexao->query("SELECT * FROM fin_fornecedores WHERE id = $id_forn")->fetch_assoc();

$update = $conexao->prepare("
  UPDATE fin_fornecedores 
  SET categoria_empresa = ?, nome_empresa = ?, cnpj_empresa = ?, contato_nome = ?, contato_numero = ?, 
      contato_email = ?
  WHERE id = ?
");

if (!$update) {
  echo json_encode(['status' => 'erro', 'mensagem' => 'Erro no prepare: ' . $conexao->error]);
  exit;
}

$categoria_empresa = $_POST['categoria_empresa'] ?? '';
$nome_empresa = $_POST['nome_empresa'] ?? '';
$cnpj_empresa = $_POST['cnpj_empresa'] ?? '';
$contato_nome = $_POST['contato_nome'] ?? '';
$contato_numero = $_POST['contato_numero'] ?? '';
$contato_email = $_POST['contato_email'] ?? '';

$update->bind_param('ssssssi', $categoria_empresa, $nome_empresa, $cnpj_empresa, $contato_nome, $contato_numero, $contato_email, $id_forn);

if (!$update->execute()) {
  echo json_encode(['status' => 'erro', 'mensagem' => 'Erro ao atualizar: ' . $update->error]);
  exit;
}
$usuarioLogado = $_SESSION['usuario_id'] ?? 0;
// Comparar alterações para log
$alteracoes = [];
$novos_valores = [
  'categoria_empresa' => $categoria_empresa,
  'nome_empresa' => $nome_empresa,
  'cnpj_empresa' => $cnpj_empresa,
  'contato_nome' => $contato_nome,
  'contato_numero' => $contato_numero,
  'contato_email' => $contato_email
];

foreach ($novos_valores as $campo => $novo_valor) {
  $valor_antigo = $old_os[$campo] ?? '';
  if ($novo_valor != $valor_antigo) {
    $alteracoes[] = ucfirst($campo) . ": '$valor_antigo' → '$novo_valor'";
  }
}

// Salvar log se houver alterações
if (!empty($alteracoes)) {
  $descricao = 'Alterações no fornecedor ID ' . $id_forn . ': ' . implode('; ', $alteracoes);
   registrar_log($conexao, $usuarioLogado, 'Editar Fornecedor', $descricao);
}

echo json_encode(['status' => 'sucesso', 'mensagem' => 'Alterações realizadas com sucesso.']);
?>
