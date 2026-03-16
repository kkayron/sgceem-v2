<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');
session_start();
require_once '../../conexao/config.php'; // ajuste se seu caminho for diferente

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Requisição inválida.');
    }

    if (!isset($_SESSION['usuario'])) {
        throw new Exception('Sessão expirada. Faça login novamente.');
    }

    $nome     = trim($_POST['nome'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $assunto  = trim($_POST['assunto'] ?? '');
    $mensagem = trim($_POST['mensagem'] ?? '');
    $resolvido = '0';

    // Validações
    if ($nome === '') {
        $nome = 'Não identificado';
    }

    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Informe um e-mail válido para retorno.');
    }

    if ($assunto === '') {
        throw new Exception('Selecione um assunto.');
    }

    if ($mensagem === '' || mb_strlen($mensagem) < 10) {
        throw new Exception('A mensagem deve ter pelo menos 10 caracteres.');
    }

    // INSERT no banco
    $sql = "INSERT INTO suporte (nome, email, assunto, mensagem, resolvido) VALUES (?, ?, ?, ?, ?)";
    $stmt = $conexao->prepare($sql);

    if (!$stmt) {
        throw new Exception('Erro ao preparar envio: ' . $conexao->error);
    }

    $stmt->bind_param('sssss', $nome, $email, $assunto, $mensagem, $resolvido);

    if (!$stmt->execute()) {
        throw new Exception('Erro ao salvar no banco: ' . $stmt->error);
    }

    $stmt->close();

    echo json_encode([
        'status' => 'sucesso',
        'mensagem' => 'Mensagem registrada com sucesso! Nossa equipe irá analisar.'
    ]);
    exit;

} catch (Exception $e) {
    echo json_encode([
        'status' => 'erro',
        'mensagem' => $e->getMessage()
    ]);
    exit;
}