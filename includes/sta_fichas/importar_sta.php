<?php
session_start();

require '../../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

require_once '../../conexao/config.php';

header('Content-Type: application/json; charset=utf-8');
ob_start();

$response = [
    'status' => 'ok',
    'sucessos' => 0,
    'falhas' => []
];

function normalize_text($s) {
    $s = mb_strtolower(trim((string)$s), 'UTF-8');
    $map = [
        'á'=>'a','à'=>'a','ã'=>'a','â'=>'a','ä'=>'a',
        'é'=>'e','è'=>'e','ê'=>'e','ë'=>'e',
        'í'=>'i','ì'=>'i','î'=>'i','ï'=>'i',
        'ó'=>'o','ò'=>'o','õ'=>'o','ô'=>'o','ö'=>'o',
        'ú'=>'u','ù'=>'u','û'=>'u','ü'=>'u',
        'ç'=>'c'
    ];
    return strtr($s, $map);
}

function converterDataExcel($valor) {
    if ($valor === null || $valor === '') {
        return null;
    }

    if (is_numeric($valor)) {
        try {
            $dt = ExcelDate::excelToDateTimeObject($valor);
            return $dt->format('Y-m-d');
        } catch (Exception $e) {}
    }

    $valor = trim((string)$valor);

    $formatos = ['d/m/Y', 'd-m-Y', 'Y-m-d', 'Y/m/d', 'd.m.Y'];

    foreach ($formatos as $formato) {
        $dt = DateTime::createFromFormat($formato, $valor);
        if ($dt && $dt->format($formato) === $valor) {
            return $dt->format('Y-m-d');
        }
    }

    $ts = strtotime(str_replace('/', '-', $valor));
    return $ts ? date('Y-m-d', $ts) : null;
}

function converterHoraExcel($valor) {
    if ($valor === null || $valor === '') {
        return null;
    }

    if (is_numeric($valor)) {
        try {
            $dt = ExcelDate::excelToDateTimeObject($valor);
            return $dt->format('H:i:s');
        } catch (Exception $e) {}
    }

    $valor = trim((string)$valor);

    $formatos = ['H:i:s', 'H:i', 'G:i'];

    foreach ($formatos as $formato) {
        $dt = DateTime::createFromFormat($formato, $valor);
        if ($dt) {
            return $dt->format('H:i:s');
        }
    }

    return null;
}

if (empty($_POST['batalhao'])) {
    echo json_encode([
        'status' => 'erro',
        'mensagem' => 'Batalhão não informado.'
    ]);
    exit;
}

$batalhao = intval($_POST['batalhao']);
$criador = $_SESSION['usuario']['id'] ?? $_SESSION['usuario_id'] ?? 0;

if (!$criador) {
    echo json_encode([
        'status' => 'erro',
        'mensagem' => 'Usuário criador não identificado na sessão.'
    ]);
    exit;
}

if (!isset($_FILES['arquivo']) || $_FILES['arquivo']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode([
        'status' => 'erro_upload',
        'mensagem' => 'Falha ao carregar o arquivo. Erro: ' . ($_FILES['arquivo']['error'] ?? 'nenhum arquivo')
    ]);
    exit;
}

$arquivoTmp = $_FILES['arquivo']['tmp_name'];

try {
    $spreadsheet = IOFactory::load($arquivoTmp);
    $sheet = $spreadsheet->getActiveSheet();

    $highestRow = $sheet->getHighestDataRow();
    $highestColumn = $sheet->getHighestDataColumn();

    for ($row = 2; $row <= $highestRow; $row++) {
        $linha = [];

        foreach (range('A', $highestColumn) as $col) {
            $linha[] = $sheet->getCell($col . $row)->getValue();
        }

        $linhaExcel = $row;
        $linha = array_pad($linha, 26, '');

        [
            $prefixo_sga,
            $autorizado,
            $data_abertura,
            $data_prevista,
            $solicitante,
            $motorista,
            $subunidade,
            $destino,
            $cidade,
            $chefe_apresentar,
            $local_apresentar,
            $horario_apresentar,
            $status,
            $natureza,
            $data_retorno,
            $hora_retorno,
            $odo_retorno,
            $data_saida,
            $hora_saida,
            $odo_saida,
            $encerrada_por,
            $observacoes_pos_emprego,
            $cmt_ceem,
            $ch_sta,
            $observacao_autorizacao,
			$id_antigo
        ] = array_map(fn($v) => trim((string)$v), $linha);

        if ($prefixo_sga === '') {
            $response['falhas'][] = [
                'linha' => $linhaExcel,
                'erro' => 'Prefixo SGA vazio.'
            ];
            continue;
        }

        $stmtFrota = $conexao->prepare("
            SELECT f.id, f.batalhao, om.nome AS nome_om
            FROM frota f
            LEFT JOIN organizacoes_militares om ON om.id = f.batalhao
            WHERE f.prefixo_sga = ?
            LIMIT 1
        ");

        $stmtFrota->bind_param("s", $prefixo_sga);
        $stmtFrota->execute();
        $resultFrota = $stmtFrota->get_result();

        if (!$rowFrota = $resultFrota->fetch_assoc()) {
            $response['falhas'][] = [
                'linha' => $linhaExcel,
                'erro' => "Prefixo SGA '{$prefixo_sga}' não encontrado na tabela frota."
            ];
            $stmtFrota->close();
            continue;
        }

        $id_viatura = intval($rowFrota['id']);
        $batalhao_frota = intval($rowFrota['batalhao']);
        $nome_om_frota = $rowFrota['nome_om'] ?? 'Desconhecido';

        if ($batalhao_frota !== $batalhao) {
            $response['falhas'][] = [
                'linha' => $linhaExcel,
                'erro' => "A viatura/equipamento '{$prefixo_sga}' pertence à OM '{$nome_om_frota}', diferente do batalhão selecionado."
            ];
            $stmtFrota->close();
            continue;
        }

        $stmtFrota->close();

        $data_abertura = converterDataExcel($data_abertura);
        $data_prevista = converterDataExcel($data_prevista);
        $data_retorno = converterDataExcel($data_retorno);
        $data_saida = converterDataExcel($data_saida);

        $horario_apresentar = converterHoraExcel($horario_apresentar);
        $hora_retorno = converterHoraExcel($hora_retorno);
        $hora_saida = converterHoraExcel($hora_saida);

        if ($autorizado === '') {
            $autorizado = 'pendente';
        }

        if ($status === '') {
            $status = 'Aberta';
        }

        $sql = "
            INSERT INTO sta_fichas (
                batalhao,
                autorizado,
                criador,
                id_viatura,
                data_abertura,
                data_prevista,
                solicitante,
                motorista,
                subunidade,
                destino,
                cidade,
                chefe_apresentar,
                local_apresentar,
                horario_apresentar,
                status,
                natureza,
                data_retorno,
                hora_retorno,
                odo_retorno,
                data_saida,
                hora_saida,
                odo_saida,
                encerrada_por,
                observacoes_pos_emprego,
                cmt_ceem,
                ch_sta,
                created_at,
                observacao_autorizacao,
				id_antigo
            ) VALUES (
                ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?
            )
        ";

        $stmt = $conexao->prepare($sql);

        if (!$stmt) {
            $response['falhas'][] = [
                'linha' => $linhaExcel,
                'erro' => 'Erro ao preparar inserção da ficha: ' . $conexao->error
            ];
            continue;
        }

        $types = "isii" . str_repeat("s", 24);

        $stmt->bind_param(
            $types,
            $batalhao,
            $autorizado,
            $criador,
            $id_viatura,
            $data_abertura,
            $data_prevista,
            $solicitante,
            $motorista,
            $subunidade,
            $destino,
            $cidade,
            $chefe_apresentar,
            $local_apresentar,
            $horario_apresentar,
            $status,
            $natureza,
            $data_retorno,
            $hora_retorno,
            $odo_retorno,
            $data_saida,
            $hora_saida,
            $odo_saida,
            $encerrada_por,
            $observacoes_pos_emprego,
            $cmt_ceem,
            $ch_sta,
            $observacao_autorizacao,
			$id_antigo
        );

        if ($stmt->execute()) {
            $response['sucessos']++;
        } else {
            $response['falhas'][] = [
                'linha' => $linhaExcel,
                'erro' => 'Erro ao inserir ficha STA: ' . $stmt->error
            ];
        }

        $stmt->close();
    }

    $response['mensagem'] = "{$response['sucessos']} fichas STA importadas com sucesso." .
        (count($response['falhas']) > 0 ? " " . count($response['falhas']) . " falhas encontradas." : "");

    ob_end_clean();
    echo json_encode($response);

} catch (Exception $e) {
    ob_end_clean();
    echo json_encode([
        'status' => 'erro_leitura_excel',
        'mensagem' => $e->getMessage()
    ]);
}