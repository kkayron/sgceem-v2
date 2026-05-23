<?php

ob_start();
session_start();

header('Content-Type: application/json; charset=utf-8');

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {

    include_once("../../conexao/config.php");
    include_once("../../includes/funcoes/log.php");

    function post($key) {
        return isset($_POST[$key]) && $_POST[$key] !== ''
            ? trim($_POST[$key])
            : null;
    }

    $usuario_id = $_SESSION['usuario']['id'] ?? $_SESSION['usuario_id'] ?? null;

    if (!$usuario_id) {
        ob_clean();
        echo json_encode([
            "status" => "erro",
            "mensagem" => "Usuário não identificado na sessão."
        ]);
        exit;
    }

    // ===============================
    // CAMPOS OBRIGATÓRIOS
    // ===============================
    $id_viatura         = post('id_viatura');
    $id_om              = post('id_om');
    $batalhao           = $id_om;
    $data_abertura      = post('data_abertura');
    $data_prevista      = post('data_prevista');
    $solicitante        = post('solicitante');
    $motorista          = post('motorista');
    $subunidade         = post('subunidade');
    $destino            = post('destino');
    $chefe_apresentar   = post('chefe_apresentar');
    $local_apresentar   = post('local_apresentar');
    $horario_apresentar = post('horario_apresentar');
    $natureza           = post('natureza');
    $cidade             = post('cidade');
    $force              = post('force');

    // Solicitação sempre inicia aberta e não autorizada
    $status = 'Aberta';
    $autorizado = 'não';
    $criador = (int)$usuario_id;

    // ===============================
    // CAMPOS OPCIONAIS
    // ===============================
    $data_saida   = post('data_saida') ?: '0001-01-01';
    $hora_saida   = post('hora_saida') ?: '00:00';
    $data_retorno = post('data_retorno') ?: '0001-01-01';
    $hora_retorno = post('hora_retorno') ?: '00:00';

    $odo_saida   = post('odo_saida');
    $odo_retorno = post('odo_retorno');

    $odo_saida   = is_numeric($odo_saida)   ? (int)$odo_saida   : 0;
    $odo_retorno = is_numeric($odo_retorno) ? (int)$odo_retorno : 0;

    $observacoes_pos_emprego = post('observacoes_pos_emprego') ?: '';

    // ===============================
    // VALIDAÇÃO DOS OBRIGATÓRIOS
    // ===============================
    $camposObrigatorios = [
        $id_viatura, $id_om, $data_abertura, $data_prevista,
        $solicitante, $motorista, $subunidade, $destino,
        $chefe_apresentar, $local_apresentar, $horario_apresentar,
        $natureza, $cidade
    ];

    if (in_array(null, $camposObrigatorios, true)) {
        ob_clean();
        echo json_encode([
            "status" => "erro",
            "mensagem" => "Preencha todos os campos obrigatórios."
        ]);
        exit;
    }

    // ===============================
    // RESPONSÁVEIS
    // ===============================
    function buscarResponsavel($conexao, $funcao, $batalhao) {
        $sql = "SELECT nomecompleto, postograd
                FROM usuarios
                WHERE funcao = ?
                  AND batalhao = ?
                  AND status = 'sim'
                LIMIT 1";

        $stmt = $conexao->prepare($sql);
        $stmt->bind_param("si", $funcao, $batalhao);
        $stmt->execute();

        $res = $stmt->get_result();

        if ($u = $res->fetch_assoc()) {
            return $u['nomecompleto'] . " - " . $u['postograd'];
        }

        return "Não definido";
    }

    $cmt_ceem = buscarResponsavel($conexao, '8',  $batalhao);
    $ch_sta   = buscarResponsavel($conexao, '12', $batalhao);

    // ===============================
    // VERIFICA CONFLITO
    // ===============================
    if (!$force) {
        $sql = "
            SELECT id, data_abertura, data_prevista
            FROM sta_fichas
            WHERE id_viatura = ?
              AND status = 'Aberta'
              AND (
                (? BETWEEN data_abertura AND data_prevista)
                OR (? BETWEEN data_abertura AND data_prevista)
                OR (data_abertura BETWEEN ? AND ?)
                OR (data_prevista BETWEEN ? AND ?)
              )
            LIMIT 1
        ";

        $st = $conexao->prepare($sql);
        $st->bind_param(
            "issssss",
            $id_viatura,
            $data_abertura,
            $data_prevista,
            $data_abertura,
            $data_prevista,
            $data_abertura,
            $data_prevista
        );
        $st->execute();

        $r = $st->get_result();

        if ($r->num_rows > 0) {
            $f = $r->fetch_assoc();

            ob_clean();
            echo json_encode([
                "status" => "confirmar",
                "mensagem" =>
                    "Já existe a ficha #{$f['id']} aberta de " .
                    date('d/m/Y', strtotime($f['data_abertura'])) .
                    " até " .
                    date('d/m/Y', strtotime($f['data_prevista'])) .
                    ". Deseja continuar com a solicitação?"
            ]);
            exit;
        }
    }

    // ===============================
    // INSERÇÃO DA SOLICITAÇÃO
    // ===============================
    $sql = "INSERT INTO sta_fichas (
        id_viatura, batalhao, cmt_ceem, ch_sta, data_abertura, solicitante,
        motorista, subunidade, destino, chefe_apresentar, local_apresentar,
        horario_apresentar, natureza, status,
        data_retorno, hora_retorno, odo_retorno,
        data_saida, hora_saida, odo_saida,
        encerrada_por, observacoes_pos_emprego, cidade, data_prevista,
        criador, autorizado
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

    $stmt = $conexao->prepare($sql);

    $stmt->bind_param(
        "iissssssssssssssississssis",
        $id_viatura,
        $id_om,
        $cmt_ceem,
        $ch_sta,
        $data_abertura,
        $solicitante,
        $motorista,
        $subunidade,
        $destino,
        $chefe_apresentar,
        $local_apresentar,
        $horario_apresentar,
        $natureza,
        $status,
        $data_retorno,
        $hora_retorno,
        $odo_retorno,
        $data_saida,
        $hora_saida,
        $odo_saida,
        $usuario_id,
        $observacoes_pos_emprego,
        $cidade,
        $data_prevista,
        $criador,
        $autorizado
    );

    $stmt->execute();

    $id_ficha = $stmt->insert_id;

    registrar_log(
        $conexao,
        $usuario_id,
        "Solicitação de Ficha STA",
        "Solicitação de ficha #{$id_ficha} cadastrada aguardando autorização"
    );

    ob_clean();
    echo json_encode([
        "status" => "sucesso",
        "mensagem" => "Solicitação cadastrada com sucesso! Aguarde autorização da seção."
    ]);

} catch (Throwable $e) {
    ob_clean();
    echo json_encode([
        "status" => "erro",
        "mensagem" => "Erro interno: " . $e->getMessage()
    ]);
}