<?php
header('Content-Type: application/json; charset=utf-8');

// Permite apenas método POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'success' => false,
        'message' => 'Acesso direto não permitido.'
    ]);
    exit;
}

// Verifica se existe dados do formulário
if (!isset($_POST['medicoes'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Requisição inválida.'
    ]);
    exit;
}

$pagina_id = 13;

require_once('../api/seguranca_json_cadastrar.php');
include_once('../../conexao/config.php');

$dataSelecionada = $_POST['data'] ?? date('Y-m-d');
$medicoes = $_POST['medicoes'] ?? [];

foreach ($medicoes as $viatura_id => $valores) {
    // Atualizar odômetro se informado
    if (isset($valores['odometro']) && $valores['odometro'] !== '') {
        $odometro = floatval($valores['odometro']);

        // Verificar se já existe registro
        $sqlVerifica = "SELECT id FROM controle_medicoes WHERE viatura_id = ? AND data = ?";
        $stmtVerifica = $conexao->prepare($sqlVerifica);
        $stmtVerifica->bind_param("is", $viatura_id, $dataSelecionada);
        $stmtVerifica->execute();
        $resultadoVerifica = $stmtVerifica->get_result();

        if ($resultadoVerifica->num_rows > 0) {
            // Atualiza odômetro
            $sqlUpdate = "UPDATE controle_medicoes SET odometro = ? WHERE viatura_id = ? AND data = ?";
            $stmtUpdate = $conexao->prepare($sqlUpdate);
            $stmtUpdate->bind_param("dis", $odometro, $viatura_id, $dataSelecionada);
            $stmtUpdate->execute();
        } else {
            // Insere odômetro
            $sqlInsert = "INSERT INTO controle_medicoes (viatura_id, data, odometro) VALUES (?, ?, ?)";
            $stmtInsert = $conexao->prepare($sqlInsert);
            $stmtInsert->bind_param("isd", $viatura_id, $dataSelecionada, $odometro);
            $stmtInsert->execute();
        }
    }

    // Atualizar status_odometro na tabela frota
    if (isset($valores['status_odometro'])) {
        $statusOdometro = $valores['status_odometro'] ?: 'Funciona';
        $sqlStatus = "UPDATE frota SET status_odometro = ? WHERE id = ?";
        $stmtStatus = $conexao->prepare($sqlStatus);
        $stmtStatus->bind_param("si", $statusOdometro, $viatura_id);
        $stmtStatus->execute();
    }
}

echo json_encode(['success' => true, 'message' => 'Medições salvas com sucesso!']);
?>
