<?php
function registrar_log($conexao, $usuario_id, $acao, $descricao, $os_id = null) {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'Desconhecido';
    $navegador = $_SERVER['HTTP_USER_AGENT'] ?? 'Desconhecido';

    $sql = "INSERT INTO logs (usuario_id, os_id, acao, descricao, ip, navegador)
            VALUES (?, ?, ?, ?, ?, ?)";
    $stmt = $conexao->prepare($sql);
    $stmt->bind_param("iissss", $usuario_id, $os_id, $acao, $descricao, $ip, $navegador);
    $stmt->execute();
    $stmt->close();
}



?>