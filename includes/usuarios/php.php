<?php

    session_start();
    include_once('../../conexao/config.php');


    $filtro_funcao = $_GET['funcao'] ?? 'todos';
if ($filtro_funcao != 'todos') {
    $stmt = $conexao->prepare("SELECT * FROM usuarios WHERE funcao = ? ORDER BY id DESC");
    $stmt->bind_param("s", $filtro_funcao);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    $sql = "SELECT * FROM usuarios ORDER BY id DESC";
    $result = $conexao->query($sql);
}

?>