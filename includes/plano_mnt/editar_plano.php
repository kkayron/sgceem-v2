<?php

ob_start();
session_start();

header('Content-Type: application/json; charset=utf-8');

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {

    require_once '../../conexao/config.php';
    require_once '../../includes/funcoes/log.php';

    function post($key) {
        return isset($_POST[$key]) && $_POST[$key] !== ''
            ? trim($_POST[$key])
            : null;
    }

    $usuario_id = $_SESSION['usuario_id'] ?? ($_SESSION['usuario']['id'] ?? null);

    $id = post('id');
    $id_marca = post('id_marca');
    $id_modelo = post('id_modelo');
    $descricao = post('descricao');
    $tipo_controle = post('tipo_controle');
    $ativo = post('ativo');

    $valor_inicial = post('valor_inicial');
    $intervalo_valor = post('intervalo_valor');
    $intervalo_dias = post('intervalo_dias');
    $alerta_antes_valor = post('alerta_antes_valor');
    $alerta_antes_dias = post('alerta_antes_dias');

    if (!$id || !$id_marca || !$id_modelo || !$descricao || !$tipo_controle || $ativo === null) {
        ob_clean();
        echo json_encode([
            "status" => "erro",
            "mensagem" => "Preencha todos os campos obrigatórios."
        ]);
        exit;
    }

    $id = (int)$id;
    $id_marca = (int)$id_marca;
    $id_modelo = (int)$id_modelo;
    $ativo = (int)$ativo;

    if ($id <= 0) {
        ob_clean();
        echo json_encode([
            "status" => "erro",
            "mensagem" => "ID inválido."
        ]);
        exit;
    }

    $tiposPermitidos = [
        'odometro',
        'horimetro',
        'tempo',
        'odometro_tempo',
        'horimetro_tempo',
        'conforme_necessidade'
    ];

    if (!in_array($tipo_controle, $tiposPermitidos, true)) {
        ob_clean();
        echo json_encode([
            "status" => "erro",
            "mensagem" => "Tipo de controle inválido."
        ]);
        exit;
    }

    $valor_inicial = is_numeric($valor_inicial) ? (float)$valor_inicial : null;
    $intervalo_valor = is_numeric($intervalo_valor) ? (float)$intervalo_valor : null;
    $intervalo_dias = is_numeric($intervalo_dias) ? (int)$intervalo_dias : null;
    $alerta_antes_valor = is_numeric($alerta_antes_valor) ? (float)$alerta_antes_valor : null;
    $alerta_antes_dias = is_numeric($alerta_antes_dias) ? (int)$alerta_antes_dias : null;

    if ($tipo_controle === 'conforme_necessidade') {

        $valor_inicial = null;
        $intervalo_valor = null;
        $intervalo_dias = null;
        $alerta_antes_valor = null;
        $alerta_antes_dias = null;

    } elseif (in_array($tipo_controle, ['odometro', 'horimetro'], true)) {

        if ($intervalo_valor === null || $intervalo_valor <= 0) {
            ob_clean();
            echo json_encode([
                "status" => "erro",
                "mensagem" => "Informe o intervalo de odômetro/horímetro."
            ]);
            exit;
        }

        $intervalo_dias = null;
        $alerta_antes_dias = null;

    } elseif ($tipo_controle === 'tempo') {

        if ($intervalo_dias === null || $intervalo_dias <= 0) {
            ob_clean();
            echo json_encode([
                "status" => "erro",
                "mensagem" => "Informe o intervalo em dias."
            ]);
            exit;
        }

        $valor_inicial = null;
        $intervalo_valor = null;
        $alerta_antes_valor = null;

    } elseif (in_array($tipo_controle, ['odometro_tempo', 'horimetro_tempo'], true)) {

        if ($intervalo_valor === null || $intervalo_valor <= 0 || $intervalo_dias === null || $intervalo_dias <= 0) {
            ob_clean();
            echo json_encode([
                "status" => "erro",
                "mensagem" => "Informe o intervalo de valor e o intervalo em dias."
            ]);
            exit;
        }
    }

    $stmtExiste = $conexao->prepare("
        SELECT id
        FROM mnt_planos
        WHERE id = ?
        LIMIT 1
    ");
    $stmtExiste->bind_param("i", $id);
    $stmtExiste->execute();
    $resExiste = $stmtExiste->get_result();

    if ($resExiste->num_rows === 0) {
        ob_clean();
        echo json_encode([
            "status" => "erro",
            "mensagem" => "Plano não encontrado."
        ]);
        exit;
    }

    $stmtModelo = $conexao->prepare("
        SELECT id
        FROM config_modelos
        WHERE id = ?
          AND id_marca = ?
        LIMIT 1
    ");
    $stmtModelo->bind_param("ii", $id_modelo, $id_marca);
    $stmtModelo->execute();
    $resModelo = $stmtModelo->get_result();

    if ($resModelo->num_rows === 0) {
        ob_clean();
        echo json_encode([
            "status" => "erro",
            "mensagem" => "O modelo selecionado não pertence à marca informada."
        ]);
        exit;
    }

    $stmtDup = $conexao->prepare("
        SELECT id
        FROM mnt_planos
        WHERE id_marca = ?
          AND id_modelo = ?
          AND descricao = ?
          AND tipo_controle = ?
          AND id <> ?
        LIMIT 1
    ");
    $stmtDup->bind_param("iissi", $id_marca, $id_modelo, $descricao, $tipo_controle, $id);
    $stmtDup->execute();
    $resDup = $stmtDup->get_result();

    if ($resDup->num_rows > 0) {
        ob_clean();
        echo json_encode([
            "status" => "erro",
            "mensagem" => "Já existe outro plano semelhante cadastrado para essa marca/modelo."
        ]);
        exit;
    }

    $sql = "
        UPDATE mnt_planos SET
            id_marca = ?,
            id_modelo = ?,
            descricao = ?,
            tipo_controle = ?,
            valor_inicial = ?,
            intervalo_valor = ?,
            intervalo_dias = ?,
            alerta_antes_valor = ?,
            alerta_antes_dias = ?,
            ativo = ?
        WHERE id = ?
    ";

    $stmt = $conexao->prepare($sql);
    $stmt->bind_param(
        "iissddidiii",
        $id_marca,
        $id_modelo,
        $descricao,
        $tipo_controle,
        $valor_inicial,
        $intervalo_valor,
        $intervalo_dias,
        $alerta_antes_valor,
        $alerta_antes_dias,
        $ativo,
        $id
    );

    $stmt->execute();

    if (function_exists('registrar_log')) {
        registrar_log(
            $conexao,
            $usuario_id,
            "Edição de Plano de Manutenção",
            "Plano de manutenção #{$id} atualizado"
        );
    }

    ob_clean();
    echo json_encode([
        "status" => "sucesso",
        "mensagem" => "Plano de manutenção atualizado com sucesso!"
    ]);

} catch (Throwable $e) {
    ob_clean();
    echo json_encode([
        "status" => "erro",
        "mensagem" => "Erro interno: " . $e->getMessage()
    ]);
}