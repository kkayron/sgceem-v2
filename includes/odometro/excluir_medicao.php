<?php
header('Content-Type: application/json; charset=utf-8');

// Impede acesso direto pelo navegador
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'success' => false,
        'message' => 'Acesso direto não permitido.'
    ]);
    exit;
}

// Verifica se a requisição é AJAX
if (
    !isset($_SERVER['HTTP_X_REQUESTED_WITH']) ||
    strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) !== 'xmlhttprequest'
) {
    echo json_encode([
        'success' => false,
        'message' => 'Requisição inválida.'
    ]);
    exit;
}


$pagina_id = 13;

require_once('../api/seguranca_json_deletar.php');
require_once '../../conexao/config.php';

// Recebe os dados via JSON
$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['viatura_id'], $input['data'])) {
    echo json_encode(['success' => false, 'message' => 'Parâmetros inválidos.']);
    exit;
}

$viatura_id = intval($input['viatura_id']);
$data = $input['data'];

// Valida data
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $data)) {
    echo json_encode(['success' => false, 'message' => 'Data inválida.']);
    exit;
}

// Verifica se existe o registro
$sqlCheck = "SELECT id FROM controle_medicoes WHERE viatura_id = ? AND data = ?";
$stmtCheck = $conexao->prepare($sqlCheck);
$stmtCheck->bind_param("is", $viatura_id, $data);
$stmtCheck->execute();
$resCheck = $stmtCheck->get_result();

if ($resCheck->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Medição não encontrada.']);
    exit;
}

// Exclui a medição
$sqlDelete = "DELETE FROM controle_medicoes WHERE viatura_id = ? AND data = ?";
$stmtDelete = $conexao->prepare($sqlDelete);
$stmtDelete->bind_param("is", $viatura_id, $data);

if ($stmtDelete->execute()) {
    echo json_encode(['success' => true, 'message' => 'Medição excluída com sucesso.']);
} else {
    echo json_encode(['success' => false, 'message' => 'Erro ao excluir a medição.']);
}
?>
