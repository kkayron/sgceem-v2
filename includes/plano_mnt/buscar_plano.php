<?php

ob_start();
session_start();

header('Content-Type: application/json; charset=utf-8');

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    require_once '../../conexao/config.php';

    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

    if ($id <= 0) {
        ob_clean();
        echo json_encode([
            "status" => "erro",
            "mensagem" => "ID inválido."
        ]);
        exit;
    }

    $sql = "
        SELECT 
            id,
            id_marca,
            id_modelo,
            descricao,
            tipo_controle,
            valor_inicial,
            intervalo_valor,
            intervalo_dias,
            alerta_antes_valor,
            alerta_antes_dias,
            ativo
        FROM mnt_planos
        WHERE id = ?
        LIMIT 1
    ";

    $stmt = $conexao->prepare($sql);
    $stmt->bind_param("i", $id);
    $stmt->execute();

    $res = $stmt->get_result();

    if ($res->num_rows === 0) {
        ob_clean();
        echo json_encode([
            "status" => "erro",
            "mensagem" => "Plano não encontrado."
        ]);
        exit;
    }

    $plano = $res->fetch_assoc();

    ob_clean();
    echo json_encode([
        "status" => "sucesso",
        "plano" => $plano
    ]);

} catch (Throwable $e) {
    ob_clean();
    echo json_encode([
        "status" => "erro",
        "mensagem" => "Erro interno: " . $e->getMessage()
    ]);
}