<?php
session_start();

require '../../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

require_once '../../conexao/config.php';

header('Content-Type: application/json; charset=utf-8');
ob_start();

$response = [
    'status' => 'ok',
    'sucessos' => 0,
    'marcas_criadas' => 0,
    'modelos_criados' => 0,
    'falhas' => []
];

function normalize_text_mnt($s) {
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

function normalizarTipoControlePlano($tipo) {
    $tipo = normalize_text_mnt($tipo);

    return match ($tipo) {
        'odometro', 'odômetro' => 'odometro',
        'horimetro', 'horímetro' => 'horimetro',
        'tempo' => 'tempo',

        'odometro + tempo',
        'odometro_tempo',
        'odometro tempo' => 'odometro_tempo',

        'horimetro + tempo',
        'horimetro_tempo',
        'horimetro tempo' => 'horimetro_tempo',

        'conforme necessidade',
        'conforme_necessidade',
        'necessidade' => 'conforme_necessidade',

        default => null
    };
}

function normalizarAtivoPlano($valor) {
    $v = normalize_text_mnt($valor);

    if ($v === '' || $v === 'sim' || $v === 'ativo' || $v === '1') {
        return 1;
    }

    if ($v === 'nao' || $v === 'não' || $v === 'inativo' || $v === '0') {
        return 0;
    }

    return 1;
}

function numPlano($v) {
    $v = trim((string)$v);

    if ($v === '') return null;

    $v = str_replace('.', '', $v);
    $v = str_replace(',', '.', $v);

    return is_numeric($v) ? (float)$v : null;
}

function buscarOuCriarMarca($conexao, $marcaNome, &$response) {
    $stmtMarca = $conexao->prepare("
        SELECT id 
        FROM config_marcas 
        WHERE LOWER(TRIM(marca)) = LOWER(TRIM(?))
        LIMIT 1
    ");
    $stmtMarca->bind_param("s", $marcaNome);
    $stmtMarca->execute();
    $resMarca = $stmtMarca->get_result();

    if ($resMarca->num_rows > 0) {
        return (int)$resMarca->fetch_assoc()['id'];
    }

    $stmtNovaMarca = $conexao->prepare("
        INSERT INTO config_marcas (marca)
        VALUES (?)
    ");
    $stmtNovaMarca->bind_param("s", $marcaNome);
    $stmtNovaMarca->execute();

    $response['marcas_criadas']++;

    return (int)$stmtNovaMarca->insert_id;
}

function buscarOuCriarModelo($conexao, $id_marca, $modeloNome, &$response) {
    $stmtModelo = $conexao->prepare("
        SELECT id 
        FROM config_modelos 
        WHERE id_marca = ?
          AND LOWER(TRIM(nome_modelo)) = LOWER(TRIM(?))
        LIMIT 1
    ");
    $stmtModelo->bind_param("is", $id_marca, $modeloNome);
    $stmtModelo->execute();
    $resModelo = $stmtModelo->get_result();

    if ($resModelo->num_rows > 0) {
        return (int)$resModelo->fetch_assoc()['id'];
    }

    $stmtNovoModelo = $conexao->prepare("
        INSERT INTO config_modelos (
            id_marca,
            nome_modelo
        ) VALUES (?, ?)
    ");
    $stmtNovoModelo->bind_param("is", $id_marca, $modeloNome);
    $stmtNovoModelo->execute();

    $response['modelos_criados']++;

    return (int)$stmtNovoModelo->insert_id;
}

if (!isset($_FILES['arquivo']) || $_FILES['arquivo']['error'] !== UPLOAD_ERR_OK) {
    ob_end_clean();
    echo json_encode([
        'status' => 'erro_upload',
        'mensagem' => 'Falha ao carregar o arquivo.'
    ]);
    exit;
}

try {
    $arquivoTmp = $_FILES['arquivo']['tmp_name'];

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
        $linha = array_pad($linha, 10, '');

        [
            $marcaNome,
            $modeloNome,
            $descricao,
            $tipoControle,
            $valorInicial,
            $intervaloValor,
            $intervaloDias,
            $alertaValor,
            $alertaDias,
            $ativo
        ] = array_map(fn($v) => trim((string)$v), $linha);

        if ($marcaNome === '' && $modeloNome === '' && $descricao === '') {
            continue;
        }

        if ($marcaNome === '') {
            $response['falhas'][] = ['linha' => $linhaExcel, 'erro' => 'Marca não informada.'];
            continue;
        }

        if ($modeloNome === '') {
            $response['falhas'][] = ['linha' => $linhaExcel, 'erro' => 'Modelo não informado.'];
            continue;
        }

        if ($descricao === '') {
            $response['falhas'][] = ['linha' => $linhaExcel, 'erro' => 'Descrição da manutenção não informada.'];
            continue;
        }

        $tipoControle = normalizarTipoControlePlano($tipoControle);

        if (!$tipoControle) {
            $response['falhas'][] = [
                'linha' => $linhaExcel,
                'erro' => 'Tipo Controle inválido. Use: odometro, horimetro, tempo, odometro_tempo, horimetro_tempo ou conforme_necessidade.'
            ];
            continue;
        }

        $id_marca = buscarOuCriarMarca($conexao, $marcaNome, $response);
        $id_modelo = buscarOuCriarModelo($conexao, $id_marca, $modeloNome, $response);

        $valorInicial = numPlano($valorInicial);
        $intervaloValor = numPlano($intervaloValor);
        $intervaloDias = is_numeric($intervaloDias) ? (int)$intervaloDias : null;
        $alertaValor = numPlano($alertaValor);
        $alertaDias = is_numeric($alertaDias) ? (int)$alertaDias : null;
        $ativo = normalizarAtivoPlano($ativo);

        if ($tipoControle === 'conforme_necessidade') {
            $valorInicial = null;
            $intervaloValor = null;
            $intervaloDias = null;
            $alertaValor = null;
            $alertaDias = null;
        } elseif (in_array($tipoControle, ['odometro', 'horimetro'], true)) {
            if ($intervaloValor === null || $intervaloValor <= 0) {
                $response['falhas'][] = [
                    'linha' => $linhaExcel,
                    'erro' => 'Informe Intervalo Valor para planos por odômetro/horímetro.'
                ];
                continue;
            }

            $intervaloDias = null;
            $alertaDias = null;

        } elseif ($tipoControle === 'tempo') {
            if ($intervaloDias === null || $intervaloDias <= 0) {
                $response['falhas'][] = [
                    'linha' => $linhaExcel,
                    'erro' => 'Informe Intervalo Dias para planos por tempo.'
                ];
                continue;
            }

            $valorInicial = null;
            $intervaloValor = null;
            $alertaValor = null;

        } elseif (in_array($tipoControle, ['odometro_tempo', 'horimetro_tempo'], true)) {
            if ($intervaloValor === null || $intervaloValor <= 0 || $intervaloDias === null || $intervaloDias <= 0) {
                $response['falhas'][] = [
                    'linha' => $linhaExcel,
                    'erro' => 'Informe Intervalo Valor e Intervalo Dias para planos combinados.'
                ];
                continue;
            }
        }

        $stmtDup = $conexao->prepare("
            SELECT id
            FROM mnt_planos
            WHERE id_marca = ?
              AND id_modelo = ?
              AND descricao = ?
              AND tipo_controle = ?
            LIMIT 1
        ");
        $stmtDup->bind_param("iiss", $id_marca, $id_modelo, $descricao, $tipoControle);
        $stmtDup->execute();
        $resDup = $stmtDup->get_result();

        if ($resDup->num_rows > 0) {
            $response['falhas'][] = [
                'linha' => $linhaExcel,
                'erro' => 'Plano já cadastrado para esta marca/modelo.'
            ];
            continue;
        }

        $stmtIns = $conexao->prepare("
            INSERT INTO mnt_planos (
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
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmtIns->bind_param(
            "iissddidii",
            $id_marca,
            $id_modelo,
            $descricao,
            $tipoControle,
            $valorInicial,
            $intervaloValor,
            $intervaloDias,
            $alertaValor,
            $alertaDias,
            $ativo
        );

        $stmtIns->execute();
        $response['sucessos']++;
    }

    $response['mensagem'] =
        "{$response['sucessos']} planos importados com sucesso. " .
        "{$response['marcas_criadas']} marcas criadas. " .
        "{$response['modelos_criados']} modelos criados." .
        (count($response['falhas']) > 0 ? " " . count($response['falhas']) . " falhas encontradas." : "");

    ob_end_clean();
    echo json_encode($response);

} catch (Throwable $e) {
    ob_end_clean();
    echo json_encode([
        'status' => 'erro_leitura_excel',
        'mensagem' => $e->getMessage()
    ]);
}