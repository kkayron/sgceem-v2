<?php
session_start();
include_once('../../conexao/config.php');

// 🔒 Só permite POST (beacon)
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    exit;
}

if (isset($_SESSION['usuario_id'])) {

    $usuario_id = $_SESSION['usuario_id'];

    $stmt = $conexao->prepare("
        UPDATE usuarios 
        SET ultima_atividade = NULL
        WHERE id = ?
    ");

    $stmt->bind_param("i", $usuario_id);
    $stmt->execute();
    $stmt->close();
}