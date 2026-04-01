<?php
function registrar_log($conexao, $usuario_id, $acao, $descricao, $frota_id = null, $os_id = null) {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'Desconhecido';
    $navegador = $_SERVER['HTTP_USER_AGENT'] ?? 'Desconhecido';

    $sql = "INSERT INTO logs (usuario_id, frota_id, acao, descricao, ip, navegador, os_id)
            VALUES (?, ?, ?, ?, ?, ?, ?)";

    $stmt = $conexao->prepare($sql);
    $stmt->bind_param("iissssi", $usuario_id, $frota_id, $acao, $descricao, $ip, $navegador, $os_id);
    $stmt->execute();
    $stmt->close();
}
?>