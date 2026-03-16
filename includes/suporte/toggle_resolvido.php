<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');
session_start();
require_once '../../conexao/config.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Requisição inválida.');
    }

    if (!isset($_SESSION['usuario'])) {
        throw new Exception('Sessão expirada. Faça login novamente.');
    }

    // (Opcional) trava admin
    // $nivel = (int)($_SESSION['usuario']['nivel'] ?? 3);
    // if ($nivel > 2) throw new Exception('Acesso negado.');

    $id = (int)($_POST['id'] ?? 0);
    $resolvido = (int)($_POST['resolvido'] ?? -1);

    if ($id <= 0) {
        throw new Exception('ID inválido.');
    }
    if ($resolvido !== 0 && $resolvido !== 1) {
        throw new Exception('Valor inválido para resolvido.');
    }

    $sql = "UPDATE suporte SET resolvido = ? WHERE id = ? LIMIT 1";
    $stmt = $conexao->prepare($sql);

    if (!$stmt) {
        throw new Exception('Erro ao preparar: ' . $conexao->error);
    }

    $stmt->bind_param('ii', $resolvido, $id);

    if (!$stmt->execute()) {
        throw new Exception('Erro ao atualizar: ' . $stmt->error);
    }

    if ($stmt->affected_rows < 0) {
        throw new Exception('Nenhuma alteração realizada.');
    }

    $stmt->close();

    echo json_encode([
        'status' => 'sucesso',
        'mensagem' => ($resolvido === 1) ? 'Marcado como resolvido.' : 'Marcado como não resolvido.'
    ]);
    exit;

} catch (Exception $e) {
    echo json_encode([
        'status' => 'erro',
        'mensagem' => $e->getMessage()
    ]);
    exit;
}