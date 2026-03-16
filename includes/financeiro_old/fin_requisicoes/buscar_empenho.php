<?php
include '../../conexao/config.php';

$id = intval($_GET['id'] ?? 0);
if (!$id) {
    echo json_encode(['sucesso' => false, 'mensagem' => 'ID inválido']);
    exit;
}

// 1) Verifica se empenho_gerado = 'sim'
$sql_check = "SELECT empenho_gerado FROM fin_requisicao WHERE id = ?";
$stmt_check = $conexao->prepare($sql_check);
$stmt_check->bind_param("i", $id);
$stmt_check->execute();
$res_check = $stmt_check->get_result();
$requisicao = $res_check->fetch_assoc();
$stmt_check->close();

if (!$requisicao) {
    echo json_encode(['sucesso' => false, 'mensagem' => 'Requisição não encontrada']);
    exit;
}

if ($requisicao['empenho_gerado'] !== 'sim') {
    // Nenhum empenho gerado → retornamos 'sucesso' com empenho=null para JS saber que abre modal limpo
    echo json_encode(['sucesso' => true, 'empenho' => null]);
    exit;
}

// 2) Se já foi gerado, busca todos os campos em fin_empenhos
$sql = "
  SELECT data_empenho, nmr_empenho, obra, ano, categoria, local, resto_pagar 
  FROM fin_empenhos 
  WHERE id_requisicao = ?
";
$stmt = $conexao->prepare($sql);
$stmt->bind_param("i", $id);
$stmt->execute();
$res = $stmt->get_result();
$empenho = $res->fetch_assoc();
$stmt->close();

if ($empenho) {
    echo json_encode(['sucesso' => true, 'empenho' => $empenho]);
} else {
    echo json_encode(['sucesso' => false, 'mensagem' => 'Empenho não encontrado']);
}
?>
