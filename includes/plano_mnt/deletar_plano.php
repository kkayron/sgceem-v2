<?php
session_start();

header('Content-Type: application/json; charset=utf-8');

require_once '../../conexao/config.php';
require_once '../../includes/funcoes/log.php';

$id = $_POST['id'] ?? null;
$usuario_id = $_SESSION['usuario_id'] ?? ($_SESSION['usuario']['id'] ?? null);

if (!$id || !is_numeric($id)) {
    echo json_encode([
        'success' => false,
        'message' => 'ID inválido.'
    ]);
    exit;
}

$id = (int)$id;

try {
    // Verifica se existe
    $stmtBusca = $conexao->prepare("
        SELECT id, descricao
        FROM mnt_planos
        WHERE id = ?
        LIMIT 1
    ");
    $stmtBusca->bind_param("i", $id);
    $stmtBusca->execute();
    $resBusca = $stmtBusca->get_result();

    if ($resBusca->num_rows === 0) {
        echo json_encode([
            'success' => false,
            'message' => 'Plano não encontrado.'
        ]);
        exit;
    }

    $plano = $resBusca->fetch_assoc();

    // Impede deletar caso já tenha execução vinculada
    $stmtUso = $conexao->prepare("
        SELECT id
        FROM mnt_execucoes
        WHERE id_plano = ?
        LIMIT 1
    ");
    $stmtUso->bind_param("i", $id);
    $stmtUso->execute();
    $resUso = $stmtUso->get_result();

    if ($resUso->num_rows > 0) {
        echo json_encode([
            'success' => false,
            'message' => 'Este plano já possui manutenções executadas vinculadas. Para preservar o histórico, inative o plano em vez de deletar.'
        ]);
        exit;
    }

    $stmtDel = $conexao->prepare("
        DELETE FROM mnt_planos
        WHERE id = ?
    ");
    $stmtDel->bind_param("i", $id);
    $stmtDel->execute();

    if (function_exists('registrar_log')) {
        registrar_log(
            $conexao,
            $usuario_id,
            "Exclusão de Plano de Manutenção",
            "Plano #{$id} - {$plano['descricao']} excluído"
        );
    }

    echo json_encode([
        'success' => true,
        'message' => 'Plano excluído com sucesso.'
    ]);

} catch (Throwable $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Erro interno: ' . $e->getMessage()
    ]);
}