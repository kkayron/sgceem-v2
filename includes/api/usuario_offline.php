<?php
session_start();
include_once('../conexao/config.php');

if(isset($_SESSION['usuario_id'])){

    $usuario_id = $_SESSION['usuario_id'];

    $stmt = $conexao->prepare("
        UPDATE usuarios 
        SET ultima_atividade = NULL
        WHERE id = ?
    ");

    $stmt->bind_param("i", $usuario_id);
    $stmt->execute();
}