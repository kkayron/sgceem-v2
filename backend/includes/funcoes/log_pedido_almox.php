<?php
function registrar_log(
    $conexao,
    $usuario_id,
    $acao,
    $descricao,
    $frota_id = null,
    $pedido_almox_id = null,
    $os_id = null
) {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'Desconhecido';
    $navegador = $_SERVER['HTTP_USER_AGENT'] ?? 'Desconhecido';

    $sql = "
        INSERT INTO logs 
            (usuario_id, frota_id, pedido_almox_id, os_id, acao, descricao, ip, navegador)
        VALUES 
            (?, ?, ?, ?, ?, ?, ?, ?)
    ";

    $stmt = $conexao->prepare($sql);
    $stmt->bind_param(
        "iiiissss",
        $usuario_id,
        $frota_id,
        $pedido_almox_id,
        $os_id,
        $acao,
        $descricao,
        $ip,
        $navegador
    );

    $stmt->execute();
    $stmt->close();
}
?>
