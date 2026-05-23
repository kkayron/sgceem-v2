<?php
session_start();
require_once '../../conexao/config.php';
require_once '../../includes/funcoes/log.php';

header('Content-Type: application/json; charset=utf-8');

$id = $_POST['id'] ?? null;

$usuarioLogado = $_SESSION['usuario']['id'] ?? $_SESSION['usuario_id'] ?? 0;

if (!$id || !$usuarioLogado) {
    echo json_encode([
        'success' => false,
        'message' => 'ID inválido ou usuário não identificado.'
    ]);
    exit;
}

$id = (int)$id;
$usuarioLogado = (int)$usuarioLogado;

// Buscar dados da solicitação antes de deletar
$stmt_select = $conexao->prepare("
    SELECT id, motorista, destino, criador, autorizado
    FROM sta_fichas
    WHERE id = ?
    LIMIT 1
");
$stmt_select->bind_param("i", $id);
$stmt_select->execute();
$result = $stmt_select->get_result();

if ($result->num_rows === 0) {
    echo json_encode([
        'success' => false,
        'message' => 'Solicitação não encontrada.'
    ]);
    exit;
}

$ficha = $result->fetch_assoc();
$stmt_select->close();

// Segurança: só o criador pode deletar a própria solicitação
if ((int)$ficha['criador'] !== $usuarioLogado) {
    echo json_encode([
        'success' => false,
        'message' => 'Você só pode excluir solicitações cadastradas por você.'
    ]);
    exit;
}

// Segurança: não permite deletar solicitação já autorizada
if (strtolower(trim($ficha['autorizado'])) === 'sim') {
    echo json_encode([
        'success' => false,
        'message' => 'Esta solicitação já foi autorizada e não pode ser excluída.'
    ]);
    exit;
}

// Deletar a solicitação
$stmt_delete = $conexao->prepare("DELETE FROM sta_fichas WHERE id = ? AND criador = ?");
$stmt_delete->bind_param("ii", $id, $usuarioLogado);

if ($stmt_delete->execute()) {
    $descricao = "Solicitação de ficha ID {$ficha['id']} deletada: Motorista: {$ficha['motorista']}, Destino: {$ficha['destino']}.";

    registrar_log(
        $conexao,
        $usuarioLogado,
        'Deletar Solicitação de Ficha STA',
        $descricao,
        $id
    );

    echo json_encode([
        'success' => true,
        'message' => 'Solicitação excluída com sucesso.'
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao deletar: ' . $stmt_delete->error
    ]);
}

$stmt_delete->close();