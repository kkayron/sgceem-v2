<?php
// includes/fin_ordemforn/buscar_empenho.php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../conexao/config.php';

$id_empenho = intval($_GET['id'] ?? 0);
if ($id_empenho <= 0) {
    echo json_encode(['sucesso'=>false,'mensagem'=>'ID de empenho inválido']);
    exit;
}

// 1) Pega o id_requisicao e o id_fornecedor diretamente via JOIN
$stmt = $conexao->prepare("
  SELECT r.id_fornecedor
    FROM fin_empenhos e
    JOIN fin_requisicao r ON r.id = e.id_requisicao
   WHERE e.id = ?
");
$stmt->bind_param("i", $id_empenho);
$stmt->execute();
$res = $stmt->get_result();
if (!$res || $res->num_rows === 0) {
    echo json_encode(['sucesso' => false, 'mensagem' => 'Empenho ou requisição não encontrado']);
    exit;
}

$id_fornecedor = $res->fetch_assoc()['id_fornecedor'];
$stmt->close();

if (!$id_fornecedor) {
    echo json_encode(['sucesso' => false, 'mensagem' => 'Fornecedor não vinculado à requisição']);
    exit;
}

// 2) Buscar dados do fornecedor
$stmt = $conexao->prepare("
  SELECT id, nome_empresa, cnpj_empresa, contato_email
    FROM fin_fornecedores
   WHERE id = ?
");
$stmt->bind_param("i", $id_fornecedor);
$stmt->execute();
$res = $stmt->get_result();
$fornecedor = $res->fetch_assoc();
$stmt->close();

if (!$fornecedor) {
    echo json_encode(['sucesso' => false, 'mensagem' => 'Fornecedor não encontrado']);
    exit;
}

// 3) Retornar dados com sucesso
echo json_encode([
  'sucesso'    => true,
  'fornecedor' => $fornecedor
], JSON_UNESCAPED_UNICODE);
