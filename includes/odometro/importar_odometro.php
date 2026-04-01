<?php
session_start();
require '../../vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
require_once '../../conexao/config.php';

header('Content-Type: application/json');


$pagina_id = 13;

require_once('../api/seguranca_json_importar.php');
require_once '../../conexao/config.php';

$response = [
    'status' => 'ok',
    'sucessos' => 0,
    'falhas' => [],
    'casamentos' => []
];

// Função para normalizar prefixos
function normalizar_prefixo($s) {
    $s = mb_strtoupper((string)$s, 'UTF-8');
    $s = str_replace(["–", "—", "−"], '-', $s);
    $s = str_replace("\xC2\xA0", '', $s);
    $s = preg_replace('/[\x{200B}-\x{200D}\x{FEFF}]/u', '', $s);
    $s = preg_replace('/\s+/u', '', $s);
    $s = preg_replace('/[^A-Z0-9-]/u', '', $s);
    return trim($s);
}

// Função para gerar "chave de similaridade" (2 primeiras letras + últimos 5 números)
function chave_similar($s) {
    $s = preg_replace('/[^A-Z0-9]/', '', strtoupper($s));
    if (preg_match('/^([A-Z]{2}).*?(\d{5})$/', $s, $m)) {
        return $m[1] . $m[2];
    }
    return $s;
}

// Função para converter datas vindas do Excel
function converterDataExcel($valor) {
    // Se for número → serial do Excel
    if (is_numeric($valor)) {
        try {
            $dt = ExcelDate::excelToDateTimeObject($valor);
            return $dt->format('Y-m-d');
        } catch (Exception $e) {
            return false;
        }
    }

    $valor = trim((string)$valor);

    // dd/mm/yyyy
    if (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/', $valor, $m)) {
        $d = (int)$m[1];
        $mth = (int)$m[2];
        $y = (int)$m[3];
        if (checkdate($mth, $d, $y)) {
            return sprintf('%04d-%02d-%02d', $y, $mth, $d);
        }
    }

    // yyyy-mm-dd
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $valor)) {
        return $valor;
    }

    // fallback genérico
    $ts = strtotime(str_replace('/', '-', $valor));
    if ($ts !== false) {
        return date('Y-m-d', $ts);
    }

    return false;
}

if (isset($_FILES['arquivo']) && $_FILES['arquivo']['error'] === UPLOAD_ERR_OK) {
    $arquivoTmp = $_FILES['arquivo']['tmp_name'];

    try {
        $spreadsheet = IOFactory::load($arquivoTmp);
        $sheet = $spreadsheet->getActiveSheet();
        $dados = $sheet->toArray();

        if (count($dados) < 2) {
            echo json_encode(['status' => 'erro', 'mensagem' => 'Planilha sem dados suficientes.']);
            exit;
        }

        $cabecalho = $dados[0];

        // Pré-carregar todos prefixos do banco
        $todosPrefixos = [];
        $resAll = $conexao->query("SELECT id, prefixo_sga, prefixo_velho FROM frota");
        while ($row = $resAll->fetch_assoc()) {
            foreach (['prefixo_sga','prefixo_velho'] as $col) {
                if (!empty($row[$col])) {
                    $norm = normalizar_prefixo($row[$col]);
                    $chave = chave_similar($row[$col]);
                    $todosPrefixos[$row['id']]['norm'][] = $norm;
                    $todosPrefixos[$row['id']]['chave'][] = $chave;
                    $todosPrefixos[$row['id']]['originais'][] = $row[$col];
                }
            }
        }

        // Processar cada linha
        foreach (array_slice($dados, 1) as $index => $linha) {
            $linhaExcel = $index + 2;
            $prefixoOriginal = trim((string)$linha[0]);
            $prefixo = normalizar_prefixo($prefixoOriginal);
            $chavePrefixo = chave_similar($prefixoOriginal);
            $patrimonio = trim((string)$linha[1]);

            if ($prefixo === '' && $patrimonio === '') {
                $response['falhas'][] = "Linha $linhaExcel - ambos prefixo e patrimônio vazios";
                continue;
            }

            $viatura_id = null;
            $casado_com = null;

            // 1. Match exato normalizado
            foreach ($todosPrefixos as $id => $arr) {
                if (in_array($prefixo, $arr['norm'])) {
                    $viatura_id = $id;
                    $casado_com = $arr['originais'][0];
                    break;
                }
            }

            // 2. Match por chave similar
            if ($viatura_id === null) {
                foreach ($todosPrefixos as $id => $arr) {
                    if (in_array($chavePrefixo, $arr['chave'])) {
                        $viatura_id = $id;
                        $casado_com = $arr['originais'][0];
                        break;
                    }
                }
            }

            // 3. Match pelo patrimônio
            if ($viatura_id === null && $patrimonio !== '') {
                $sql = "SELECT id, prefixo_sga, prefixo_velho FROM frota WHERE TRIM(nmr_patrimonio) = ? LIMIT 1";
                $stmt = $conexao->prepare($sql);
                $stmt->bind_param("s", $patrimonio);
                $stmt->execute();
                $res = $stmt->get_result();
                if ($res->num_rows > 0) {
                    $row = $res->fetch_assoc();
                    $viatura_id = $row['id'];
                    $casado_com = $row['prefixo_sga'] ?: $row['prefixo_velho'];
                }
                $stmt->close();
            }

            if ($viatura_id === null) {
                $response['falhas'][] = "Linha $linhaExcel - $prefixoOriginal - Não enviada pois não foi localizada no banco de dados";
                continue;
            } else {
                $response['casamentos'][] = "Linha $linhaExcel - $prefixoOriginal → Banco: $casado_com";
            }

            // Processar colunas de valores
            for ($col = 2; $col < count($linha); $col++) {
                $valor = trim((string)$linha[$col]);
                $dataCol = $cabecalho[$col];

                if ($dataCol === '') {
                    $response['falhas'][] = "Linha 1 Coluna " . ($col+1) . " - Cabeçalho vazio (sem data)";
                    continue;
                }

                $dataMedicao = converterDataExcel($dataCol);
                if ($dataMedicao === false) {
                    $response['falhas'][] = "Linha 1 Coluna " . ($col+1) . " - Data inválida: $dataCol";
                    continue;
                }

                if ($valor === '') {
                    $response['falhas'][] = "Linha $linhaExcel Coluna " . ($col+1) . " - Valor vazio";
                    continue;
                }

                if (!is_numeric(str_replace([',', '.'], '', $valor))) {
                    $response['falhas'][] = "Linha $linhaExcel Coluna " . ($col+1) . " - Valor não numérico: '$valor'";
                    continue;
                }

                $odometro = floatval(str_replace(',', '.', $valor));
                if ($odometro <= 0) {
                    $response['falhas'][] = "Linha $linhaExcel Coluna " . ($col+1) . " - Valor zero ou negativo";
                    continue;
                }

                // Verificar se já existe registro
                $sqlVerifica = "SELECT id FROM controle_medicoes WHERE viatura_id = ? AND data = ?";
                $stmtVerifica = $conexao->prepare($sqlVerifica);
                $stmtVerifica->bind_param("is", $viatura_id, $dataMedicao);
                $stmtVerifica->execute();
                $resultadoVerifica = $stmtVerifica->get_result();

                if ($resultadoVerifica->num_rows > 0) {
                    $sqlUpdate = "UPDATE controle_medicoes SET odometro = ? WHERE viatura_id = ? AND data = ?";
                    $stmtUpdate = $conexao->prepare($sqlUpdate);
                    $stmtUpdate->bind_param("dis", $odometro, $viatura_id, $dataMedicao);
                    $stmtUpdate->execute();
                    $stmtUpdate->close();
                } else {
                    $sqlInsert = "INSERT INTO controle_medicoes (viatura_id, data, odometro) VALUES (?, ?, ?)";
                    $stmtInsert = $conexao->prepare($sqlInsert);
                    $stmtInsert->bind_param("isd", $viatura_id, $dataMedicao, $odometro);
                    $stmtInsert->execute();
                    $stmtInsert->close();
                }

                $response['sucessos']++;
            }
        }

        $response['mensagem'] = "{$response['sucessos']} registros importados com sucesso." .
            (count($response['falhas']) > 0 ? " " . count($response['falhas']) . " falhas encontradas." : "");

        echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

    } catch (Exception $e) {
        echo json_encode([
            'status' => 'erro_leitura_excel',
            'mensagem' => $e->getMessage()
        ]);
    }

} else {
    echo json_encode([
        'status' => 'erro_upload',
        'mensagem' => 'Falha ao carregar o arquivo. Erro: ' . ($_FILES['arquivo']['error'] ?? 'nenhum arquivo')
    ]);
    exit;
}
