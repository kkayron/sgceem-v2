<?php
session_start();
require_once '../../conexao/config.php';
require_once '../../includes/funcoes/log.php';

header('Content-Type: application/json; charset=utf-8');

$id = $_POST['id'] ?? null;
$autorizado = $_POST['autorizado'] ?? null;
$observacao = trim($_POST['observacao_autorizacao'] ?? '');

$usuarioLogado = $_SESSION['usuario']['id'] ?? $_SESSION['usuario_id'] ?? 0;

if (!$id || !$usuarioLogado) {
    echo json_encode([
        'success' => false,
        'message' => 'ID inválido ou usuário não identificado.'
    ]);
    exit;
}

$permitidos = ['sim', 'não', 'negado'];

if (!in_array($autorizado, $permitidos, true)) {
    echo json_encode([
        'success' => false,
        'message' => 'Situação de autorização inválida.'
    ]);
    exit;
}

$id = (int)$id;
$usuarioLogado = (int)$usuarioLogado;

// Buscar ficha
$stmt = $conexao->prepare("
    SELECT id, motorista, destino, autorizado
    FROM sta_fichas
    WHERE id = ?
    LIMIT 1
");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode([
        'success' => false,
        'message' => 'Ficha não encontrada.'
    ]);
    exit;
}

$ficha = $result->fetch_assoc();
$stmt->close();

// Define o status automaticamente
if ($autorizado === 'negado') {
    $novoStatus = 'Não autorizada';
} elseif ($autorizado === 'sim') {
    $novoStatus = 'Aberta';
} else {
    $novoStatus = 'Aberta';
}

// Atualizar autorização e status
$stmtUpdate = $conexao->prepare("
    UPDATE sta_fichas
    SET autorizado = ?, 
        observacao_autorizacao = ?,
        status = ?
    WHERE id = ?
");

$stmtUpdate->bind_param(
    "sssi",
    $autorizado,
    $observacao,
    $novoStatus,
    $id
);

if ($stmtUpdate->execute()) {

    if ($autorizado === 'sim') {
        $textoAutorizacao = 'Autorizada';
    } elseif ($autorizado === 'negado') {
        $textoAutorizacao = 'Negada';
    } else {
        $textoAutorizacao = 'Pendente';
    }

    $descricao = "Ficha ID {$id} teve autorização alterada para: {$textoAutorizacao}.";
    
    if ($observacao !== '') {
        $descricao .= " Observação: {$observacao}";
    }

    registrar_log(
        $conexao,
        $usuarioLogado,
        'Autorização de Ficha STA',
        $descricao,
        $id
    );

    echo json_encode([
        'success' => true,
        'message' => "Ficha marcada como {$textoAutorizacao}."
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao atualizar autorização: ' . $stmtUpdate->error
    ]);
}

$stmtUpdate->close();