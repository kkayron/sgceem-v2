<?php

header('Content-Type: application/json; charset=utf-8');

session_start();

$pagina_id = 2;

require_once('../api/seguranca_json_importar.php');

require '../../vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\IOFactory;
require_once '../../conexao/config.php';

ob_start();
header('Content-Type: application/json; charset=utf-8');

$response = [
    'status' => 'ok',
    'sucessos' => 0,
    'falhas' => []
];

// ====================================================
// Função de normalização de texto
// ====================================================
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

// ====================================================
// Verifica batalhão
// ====================================================
if (empty($_POST['batalhao'])) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Batalhão não informado.']);
    exit;
}
$batalhao = intval($_POST['batalhao']);

// ====================================================
// Processa arquivo Excel
// ====================================================
if (isset($_FILES['arquivo']) && $_FILES['arquivo']['error'] === UPLOAD_ERR_OK) {
    $arquivoTmp = $_FILES['arquivo']['tmp_name'];

    try {
        $spreadsheet = IOFactory::load($arquivoTmp);
        $sheet = $spreadsheet->getActiveSheet();
        $dados = $sheet->toArray();
        array_shift($dados); // remove cabeçalho

        foreach ($dados as $index => $linha) {
            $linhaExcel = $index + 2;

            // Garante que sempre haverá 25 colunas
            $linha = array_pad($linha, 25, '');

            [
                $ativo, $tipo, $prefixo_velho, $prefixo_sga, $nome_sioc, $nmr_patrimonio,
                $nmr_eb, $chassi, $acervo, $marca, $modelo, $ano, $confiabilidade,
                $obs_encmat, $capac_tanque, $consumo, $destino, $disponibilidade,
                $missao, $emprego_atual, $ordem_fragmentaria, $placa, $subunidade,
                $renavam, $trem
            ] = array_map(fn($v) => trim((string)$v), $linha);

            // ====================================================
            // Valida prefixo
            // ====================================================
            if ($prefixo_sga === '') {
                $response['falhas'][] = ['linha' => $linhaExcel, 'erro' => 'Prefixo SGA vazio.'];
                continue;
            }

            // ====================================================
            // Impede duplicidade
            // ====================================================
            $check = $conexao->prepare("SELECT id FROM frota WHERE prefixo_sga = ?");
            $check->bind_param("s", $prefixo_sga);
            $check->execute();
            $check->store_result();
            if ($check->num_rows > 0) {
                $response['falhas'][] = ['linha' => $linhaExcel, 'erro' => 'Prefixo SGA já cadastrado.'];
                $check->close();
                continue;
            }
            $check->close();

            // ====================================================
            // Marca e modelo
            // ====================================================
            if ($marca !== '' && !is_numeric($marca)) {
                $marcaEsc = $conexao->real_escape_string($marca);
                $resMarca = $conexao->query("SELECT id FROM config_marcas WHERE marca = '$marcaEsc' LIMIT 1");
                if ($resMarca && $resMarca->num_rows > 0) {
                    $marca = $resMarca->fetch_assoc()['id'];
                } else {
                    $conexao->query("INSERT INTO config_marcas (marca) VALUES ('$marcaEsc')");
                    $marca = $conexao->insert_id;
                }
            } else {
                $marca = (int)$marca;
            }

            if ($modelo !== '' && !is_numeric($modelo)) {
                $modeloEsc = $conexao->real_escape_string($modelo);
                $resModelo = $conexao->query("SELECT id FROM config_modelos WHERE id_marca = {$marca} AND nome_modelo = '$modeloEsc' LIMIT 1");
                if ($resModelo && $resModelo->num_rows > 0) {
                    $modelo = $resModelo->fetch_assoc()['id'];
                } else {
                    $conexao->query("INSERT INTO config_modelos (id_marca, nome_modelo) VALUES ({$marca}, '$modeloEsc')");
                    $modelo = $conexao->insert_id;
                }
            } else {
                $modelo = (int)$modelo;
            }

            // ====================================================
            // Disponibilidade
            // ====================================================
            $normDisp = normalize_text($disponibilidade);
            if ($normDisp === 'd') $disponibilidade = 'Disponível';
            elseif ($normDisp === 'i') $disponibilidade = 'Indisponível';
            elseif (strpos($normDisp, 'dispon') !== false && strpos($normDisp, 'indispon') === false) $disponibilidade = 'Disponível';
            elseif (strpos($normDisp, 'indispon') !== false) $disponibilidade = 'Indisponível';

            $foto_capa = 'base.jpg';
            $cadastrado_por = isset($_SESSION['postograd'], $_SESSION['nomeguerra'])
                ? $_SESSION['postograd'] . ' - ' . $_SESSION['nomeguerra']
                : 'Desconhecido';

            // ====================================================
            // Inserção — 29 colunas e 29 placeholders + NOW()
            // ====================================================
            $sql = "INSERT INTO frota (
                ativo, tipo, prefixo_velho, prefixo_sga, nome_sioc, nmr_patrimonio,
                nmr_eb, chassi, acervo, marca, modelo, ano, confiabilidade,
                obs_encmat, capac_tanque, consumo, destino, disponibilidade,
                missao, emprego_atual, ordem_fragmentaria, placa, subunidade,
                renavam, trem, foto_capa, cadastrado_por, batalhao, data_inclusao
            ) VALUES (
                ?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW()
            )";

            $stmt = $conexao->prepare($sql);
            if (!$stmt) {
                $response['falhas'][] = ['linha' => $linhaExcel, 'erro' => 'Erro ao preparar statement: ' . $conexao->error];
                continue;
            }

            // 28 parâmetros: 25 da planilha + 3 adicionais
            $stmt->bind_param(
                str_repeat('s', 27) . 'i',
                $ativo, $tipo, $prefixo_velho, $prefixo_sga, $nome_sioc, $nmr_patrimonio,
                $nmr_eb, $chassi, $acervo, $marca, $modelo, $ano, $confiabilidade,
                $obs_encmat, $capac_tanque, $consumo, $destino, $disponibilidade,
                $missao, $emprego_atual, $ordem_fragmentaria, $placa, $subunidade,
                $renavam, $trem, $foto_capa, $cadastrado_por, $batalhao
            );

            if ($stmt->execute()) {
                $response['sucessos']++;
            } else {
                $response['falhas'][] = ['linha' => $linhaExcel, 'erro' => 'Erro na inserção: ' . $stmt->error];
            }

            $stmt->close();
        }

        // ====================================================
        // Retorno final
        // ====================================================
        $response['mensagem'] = "{$response['sucessos']} registros importados com sucesso." .
            (count($response['falhas']) > 0 ? " " . count($response['falhas']) . " falhas encontradas." : "");

        ob_end_clean();
        echo json_encode($response);

    } catch (Exception $e) {
        ob_end_clean();
        echo json_encode(['status' => 'erro_leitura_excel', 'mensagem' => $e->getMessage()]);
    }

} else {
    echo json_encode([
        'status' => 'erro_upload',
        'mensagem' => 'Falha ao carregar o arquivo. Erro: ' . ($_FILES['arquivo']['error'] ?? 'nenhum arquivo')
    ]);
    exit;
}
