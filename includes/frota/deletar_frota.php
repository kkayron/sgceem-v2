<?php
declare(strict_types=1);

session_start();
require_once("../../conexao/config.php");
require_once("../../includes/funcoes/log.php");

header('Content-Type: application/json; charset=utf-8');

ob_start();

register_shutdown_function(function () {
    $err = error_get_last();
    if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        if (ob_get_length()) ob_clean();
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Erro fatal no servidor ao deletar.',
            'debug'   => $err['message'] . ' em ' . $err['file'] . ':' . $err['line']
        ]);
    }
});

// Validação do ID
$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
if ($id <= 0) {
    if (ob_get_length()) ob_clean();
    echo json_encode(['success' => false, 'message' => 'ID inválido']);
    exit;
}

$usuarioLogado = (int)($_SESSION['usuario_id'] ?? 0);

try {
    $conexao->begin_transaction();

    // 1) Buscar dados da frota
    $stmt_select = $conexao->prepare("
        SELECT id, prefixo_sga, tipo, marca, modelo, placa, chassi
        FROM frota
        WHERE id = ?
        LIMIT 1
    ");
    if (!$stmt_select) throw new Exception("Prepare SELECT frota falhou: " . $conexao->error);

    $stmt_select->bind_param("i", $id);
    $stmt_select->execute();
    $result = $stmt_select->get_result();

    if ($result->num_rows === 0) {
        $stmt_select->close();
        $conexao->rollback();
        if (ob_get_length()) ob_clean();
        echo json_encode(['success' => false, 'message' => 'Frota não encontrada']);
        exit;
    }

    $frota = $result->fetch_assoc();
    $prefixo = $frota['prefixo_sga'] ?? '';
    $stmt_select->close();

    // 2) Buscar medições (para log) - SEM horímetro
    $stmt_medicoes_log = $conexao->prepare("
        SELECT DATE_FORMAT(data, '%d/%m/%Y') AS data, odometro
        FROM controle_medicoes
        WHERE viatura_id = ?
        ORDER BY data ASC
    ");
    if (!$stmt_medicoes_log) throw new Exception("Prepare SELECT medições falhou: " . $conexao->error);

    $stmt_medicoes_log->bind_param("i", $id);
    $stmt_medicoes_log->execute();
    $result_medicoes = $stmt_medicoes_log->get_result();

    $descricao_medicoes = "";
    while ($medicao = $result_medicoes->fetch_assoc()) {
        $data = $medicao['data'] ?? 'N/A';
        $odo  = $medicao['odometro'] ?? 'N/A';
        $descricao_medicoes .= "Data: {$data} - Odômetro: {$odo}\n";
    }
    $stmt_medicoes_log->close();

    // 3) Registrar log das medições (não derruba o processo)
    if (!empty($descricao_medicoes)) {
        $descricao_logs = "Medições da viatura {$prefixo} serão excluídas:\n" . $descricao_medicoes;
        try {
            registrar_log($conexao, $usuarioLogado, 'Excluir medições', $descricao_logs, $id);
        } catch (Throwable $e) {}
    }

    // ==========================
    // 4) DELETAR DEPENDÊNCIAS
    // ==========================

    // 4.1) Medições
    $stmt_medicoes = $conexao->prepare("DELETE FROM controle_medicoes WHERE viatura_id = ?");
    if (!$stmt_medicoes) throw new Exception("Prepare DELETE medições falhou: " . $conexao->error);
    $stmt_medicoes->bind_param("i", $id);
    $stmt_medicoes->execute();
    $stmt_medicoes->close();

    // 4.2) Fichas
    $stmt_fichas = $conexao->prepare("DELETE FROM sta_fichas WHERE id_viatura = ?");
    if (!$stmt_fichas) throw new Exception("Prepare DELETE fichas falhou: " . $conexao->error);
    $stmt_fichas->bind_param("i", $id);
    $stmt_fichas->execute();
    $stmt_fichas->close();

    // 4.3) Logs da frota
    $stmt_logs = $conexao->prepare("DELETE FROM logs WHERE frota_id = ?");
    if (!$stmt_logs) throw new Exception("Prepare DELETE logs falhou: " . $conexao->error);
    $stmt_logs->bind_param("i", $id);
    $stmt_logs->execute();
    $stmt_logs->close();

    // 4.4) OS da frota
    // ⚠️ Se existirem tabelas filhas de os_principal, delete-as antes aqui.
    // Exemplo (ajuste nomes se existirem):
    // $stmt_os_itens = $conexao->prepare("DELETE FROM os_itens WHERE id_os IN (SELECT id FROM os_principal WHERE id_frota = ?)");
    // $stmt_os_itens->bind_param("i", $id);
    // $stmt_os_itens->execute();
    // $stmt_os_itens->close();

    $stmt_os = $conexao->prepare("DELETE FROM os_principal WHERE id_frota = ?");
    if (!$stmt_os) throw new Exception("Prepare DELETE OS falhou: " . $conexao->error);
    $stmt_os->bind_param("i", $id);
    $stmt_os->execute();
    $stmt_os->close();

    // ==========================
    // 5) DELETAR FROTA
    // ==========================
    $stmt_delete = $conexao->prepare("DELETE FROM frota WHERE id = ?");
    if (!$stmt_delete) throw new Exception("Prepare DELETE frota falhou: " . $conexao->error);
    $stmt_delete->bind_param("i", $id);

    if (!$stmt_delete->execute()) {
        $erro = $stmt_delete->error;
        $stmt_delete->close();
        throw new Exception("Erro ao deletar frota: " . $erro);
    }
    $stmt_delete->close();

    // 6) Log do delete (não derruba)
    $descricao = "Frota ID {$id} deletada: Prefixo {$frota['prefixo_sga']}, Tipo: {$frota['tipo']}, Marca: {$frota['marca']}, Modelo: {$frota['modelo']}, Placa: {$frota['placa']}, Chassi: {$frota['chassi']}";
    try {
        registrar_log($conexao, $usuarioLogado, 'Deletar frota', $descricao, $id);
    } catch (Throwable $e) {}

    $conexao->commit();

    if (ob_get_length()) ob_clean();
    echo json_encode(['success' => true]);
    exit;

} catch (Throwable $e) {
    $conexao->rollback();

    if (ob_get_length()) ob_clean();
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Falha ao deletar.',
        'debug'   => $e->getMessage()
    ]);
    exit;
}