<?php
function calcularManutencaoPreventiva($conexao, $vtr) {

    $idFrota = $vtr['id'];
    $hoje = new DateTime();

    /* ============================================================
       1) Status do odômetro
    ============================================================ */
    $stmtStatus = $conexao->prepare(
        "SELECT status_odometro FROM frota WHERE id = ? LIMIT 1"
    );
    $stmtStatus->bind_param("i", $idFrota);
    $stmtStatus->execute();
    $resStatus = $stmtStatus->get_result()->fetch_assoc();
    $stmtStatus->close();

    $statusOdometro = strtolower(trim($resStatus['status_odometro'] ?? ''));
    $ignorarOdo = in_array(
        $statusOdometro,
        ['não funciona','nao funciona','não possui','nao possui'],
        true
    );

    /* ============================================================
       2) Última OS preventiva
    ============================================================ */
    $stmtOS = $conexao->prepare("
        SELECT *
        FROM os_principal
        WHERE id_frota = ?
          AND tipo_mnt = 'Manutenção Preventiva'
        ORDER BY data_abertura DESC
        LIMIT 1
    ");
    $stmtOS->bind_param("i", $idFrota);
    $stmtOS->execute();
    $os = $stmtOS->get_result()->fetch_assoc();
    $stmtOS->close();

    /* ============================================================
       3) Resumo de serviços realizados
    ============================================================ */
    $resumoRlzd = '--';

    if ($os && !empty($os['id'])) {

        $stmtRlzd = $conexao->prepare("
            SELECT GROUP_CONCAT(DISTINCT rlzd_mnt SEPARATOR '; ') AS resumo
            FROM os_rlzdmnt
            WHERE id_osprincipal = ?
        ");
        $stmtRlzd->bind_param("i", $os['id']);
        $stmtRlzd->execute();
        $r = $stmtRlzd->get_result()->fetch_assoc();
        $stmtRlzd->close();

        if (!empty($r['resumo'])) {
            $resumoRlzd = mb_strlen($r['resumo']) > 120
                ? mb_substr($r['resumo'], 0, 117) . '...'
                : $r['resumo'];
        }
    }

    /* ============================================================
       4) Histórico de medições / média diária
    ============================================================ */
    $odoAtual = null;
    $odoData  = null;
    $kmPorDia = null;

    if (!$ignorarOdo) {

        $stmtMed = $conexao->prepare("
            SELECT odometro, data
            FROM controle_medicoes
            WHERE viatura_id = ?
            ORDER BY data ASC
        ");
        $stmtMed->bind_param("i", $idFrota);
        $stmtMed->execute();
        $med = $stmtMed->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmtMed->close();

        $stmtUlt = $conexao->prepare("
            SELECT odometro, data
            FROM controle_medicoes
            WHERE viatura_id = ?
            ORDER BY data DESC
            LIMIT 1
        ");
        $stmtUlt->bind_param("i", $idFrota);
        $stmtUlt->execute();
        $u = $stmtUlt->get_result()->fetch_assoc();
        $stmtUlt->close();

        if ($u) {
            $odoAtual = is_numeric($u['odometro']) ? (float)$u['odometro'] : null;
            $odoData  = $u['data'];
        }

        if (count($med) >= 2) {
            $p = $med[0];
            $lt = end($med);

            $dias = (strtotime($lt['data']) - strtotime($p['data'])) / 86400;

            if ($dias > 0 && $lt['odometro'] >= $p['odometro']) {
                $kmPorDia = ($lt['odometro'] - $p['odometro']) / $dias;
            }
        }
    }

    /* ============================================================
       5) Inicialização
    ============================================================ */
    $status = '--';
    $diasRestantes = '--';
    $dataPrevTempo = '--';
    $dataPrevOdo   = '--';
    $proxOdo       = '--';
    $kmRestante    = '--';

    /* ============================================================
       6) Retornos imediatos
    ============================================================ */
    if (!$os && ($odoAtual === null || $ignorarOdo)) {
        return [
            'os' => $os,
            'status' => 'Sem dados',
            'dias_restantes' => '--',
            'data_limite_tempo' => '--',
            'data_limite_odo' => '--',
            'data_prev_odometro' => '--',
            'km_restante' => '--',
            'odometro_atual' => $odoAtual,
            'odometro_data' => $odoData ? date('d/m/Y', strtotime($odoData)) : '--',
            'km_por_dia' => $kmPorDia,
            'os_em_andamento' => false,
            'nmr_os' => '--',
            'data_abertura' => '--',
            'os_odometro_momento' => '--',
            'os_prox_tempo_max' => '--',
            'os_prox_odo_max' => '--',
            'resumo_realizado' => $resumoRlzd
        ];
    }

    if ($os && ($os['status'] ?? '') !== 'Concluída') {
        return [
            'os' => $os,
            'status' => 'Em manutenção',
            'dias_restantes' => '--',
            'data_limite_tempo' => '--',
            'data_limite_odo' => '--',
            'data_prev_odometro' => '--',
            'km_restante' => '--',
            'odometro_atual' => $odoAtual,
            'odometro_data' => $odoData ? date('d/m/Y', strtotime($odoData)) : '--',
            'km_por_dia' => $kmPorDia,
            'os_em_andamento' => true,
            'nmr_os' => $os['id'],
            'data_abertura' => $os['data_abertura'],
            'os_odometro_momento' => $os['odometro_horimetro'],
            'os_prox_tempo_max' => $os['prox_mnt_prev_odo'],
            'os_prox_odo_max' => $os['prox_mnt_prev_hor'],
            'resumo_realizado' => $resumoRlzd
        ];
    }

    /* ============================================================
       7) Cálculo por TEMPO (seguro)
    ============================================================ */
    $diasTempo = null;

    if ($os && is_numeric($os['prox_mnt_prev_odo']) && !empty($os['data_abertura'])) {
        try {
            $dataAbertura = new DateTime($os['data_abertura']);
            $meses = (float)$os['prox_mnt_prev_odo'];

            $dataPrev = clone $dataAbertura;

            if ($meses >= 1) {
                $dataPrev->add(new DateInterval('P' . intval($meses) . 'M'));
            } else {
                $dias = intval(round($meses * 30));
                $dataPrev->add(new DateInterval('P' . $dias . 'D'));
            }

            $dataPrevTempo = $dataPrev->format('d/m/Y');
            $diasTempo = (int)$hoje->diff($dataPrev)->format('%r%a');

        } catch (Exception $e) {
            $diasTempo = null;
            $dataPrevTempo = '--';
        }
    }

    /* ============================================================
       8) Cálculo por ODOMETRO
    ============================================================ */
    $diasOdo = null;

    if (
        !$ignorarOdo &&
        is_numeric($os['odometro_horimetro'] ?? null) &&
        is_numeric($os['prox_mnt_prev_hor'] ?? null) &&
        is_numeric($odoAtual) &&
        $kmPorDia > 0
    ) {

        $proxOdo = (float)$os['odometro_horimetro']
                 + (float)$os['prox_mnt_prev_hor'];

        $kmRestante = max(0, $proxOdo - $odoAtual);
        $diasOdo = ceil($kmRestante / $kmPorDia);
        $dataPrevOdo = date('d/m/Y', strtotime("+{$diasOdo} days"));
    }

    /* ============================================================
       9) Dias restantes
    ============================================================ */
    if (is_numeric($diasTempo) && is_numeric($diasOdo)) {
        $diasRestantes = min($diasTempo, $diasOdo);
    } elseif (is_numeric($diasTempo)) {
        $diasRestantes = $diasTempo;
    } elseif (is_numeric($diasOdo)) {
        $diasRestantes = $diasOdo;
    }

    /* ============================================================
       10) Status final
    ============================================================ */
    if (is_numeric($diasRestantes)) {
        if ($diasRestantes <= 0) {
            $status = 'Manutenção vencida';
        } elseif ($diasRestantes <= 40) {
            $status = 'Muito próxima';
        } elseif ($diasRestantes <= 90) {
            $status = 'Próxima';
        } else {
            $status = 'Manutenção em dia';
        }
    } else {
        $status = 'Sem dados';
    }

    /* ============================================================
       11) Retorno final
    ============================================================ */
    return [
        'os' => $os,
        'status' => $status,
        'dias_restantes' => is_numeric($diasRestantes) ? round($diasRestantes) : '--',
        'data_limite_tempo' => $dataPrevTempo,
        'data_limite_odo' => $proxOdo,
        'data_prev_odometro' => $dataPrevOdo,
        'km_restante' => $kmRestante,
        'odometro_atual' => $odoAtual,
        'odometro_data' => $odoData ? date('d/m/Y', strtotime($odoData)) : '--',
        'km_por_dia' => $kmPorDia,
        'os_em_andamento' => false,
        'nmr_os' => $os['id'] ?? '--',
        'data_abertura' => $os['data_abertura'] ?? '--',
        'os_odometro_momento' => $os['odometro_horimetro'] ?? '--',
        'os_prox_tempo_max' => $os['prox_mnt_prev_odo'] ?? '--',
        'os_prox_odo_max' => $os['prox_mnt_prev_hor'] ?? '--',
        'resumo_realizado' => $resumoRlzd
    ];
}
?>
