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

// ================================
// Função de normalização de texto
// ================================
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

// ================================
// Função para converter datas vindas do Excel
// ================================
function converterDataExcel($valor) {
    if ($valor === null || $valor === '') {
        return null;
    }

    // Se for número serial do Excel
    if (is_numeric($valor)) {
        try {
            $dt = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($valor);
            return $dt->format('Y-m-d');
        } catch (Exception $e) {
            // se falhar, continua nos formatos abaixo
        }
    }

    $valor = trim((string)$valor);

    // Tenta formatos comuns de data (dd/mm/yyyy, yyyy-mm-dd, etc)
    $formatos = [
        'd/m/Y',
        'd-m-Y',
        'Y-m-d',
        'Y/m/d',
        'd.m.Y'
    ];

    foreach ($formatos as $formato) {
        $dt = DateTime::createFromFormat($formato, $valor);
        if ($dt && $dt->format($formato) === $valor) {
            return $dt->format('Y-m-d');
        }
    }

    // Última tentativa genérica com strtotime
    $ts = strtotime(str_replace('/', '-', $valor));
    if ($ts !== false && $ts > 0) {
        return date('Y-m-d', $ts);
    }

    // Se nada funcionou
    return null;
}

// ================================
// Verifica batalhão
// ================================
if (empty($_POST['batalhao'])) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Batalhão não informado.']);
    exit;
}
$batalhao = intval($_POST['batalhao']);

// ================================
// Processa arquivo Excel
// ================================
if (isset($_FILES['arquivo']) && $_FILES['arquivo']['error'] === UPLOAD_ERR_OK) {
    $arquivoTmp = $_FILES['arquivo']['tmp_name'];

    try {
        $spreadsheet = IOFactory::load($arquivoTmp);
        $sheet = $spreadsheet->getActiveSheet();
        $highestRow = $sheet->getHighestDataRow();
        $highestColumn = $sheet->getHighestDataColumn();
        $headerSkipped = false;

        for ($row = 1; $row <= $highestRow; $row++) {
            $linha = [];
            foreach (range('A', $highestColumn) as $col) {
                $linha[] = $sheet->getCell($col . $row)->getValue();
            }

            if (!$headerSkipped) { // pula cabeçalho
                $headerSkipped = true;
                continue;
            }

            $linhaExcel = $row;
            $linha = array_pad($linha, 23, '');

            [
                $prefixo_sga,
                $data_abertura,
                $odometro_horimetro,
                $solicitante,
                $local_os,
                $problema,
                $secao_rspns,
                $causa_indisponibilidade,
                $tipo_mnt,
                $status,
                $valornd30,
                $valornd39,
                $valorTOTAL,
                $manutencao_preventiva,
                $prox_mnt_prev_hor,
                $prox_mnt_prev_odo,
                $cmt_ceem,
                $ch_controle,
                $ch_suprimento,
                $falhas_identificadas,
                $pessoal_utilizado,
                $itens_utilizados
            ] = array_map(fn($v) => trim((string)$v), $linha);

            // ================================
            // Verifica prefixo
            // ================================
            if ($prefixo_sga === '') {
                $response['falhas'][] = ['linha' => $linhaExcel, 'erro' => 'Prefixo SGA vazio.'];
                continue;
            }

            // ================================
            // Busca ID e batalhão da frota
            // ================================
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

            if ($rowFrota = $resultFrota->fetch_assoc()) {
                $id_frota = $rowFrota['id'];
                $batalhao_frota = intval($rowFrota['batalhao']);
                $nome_om_frota = $rowFrota['nome_om'] ?? 'Desconhecido';

                // 🚫 Verifica se pertence ao batalhão selecionado
                if ($batalhao_frota !== $batalhao) {
                    $response['falhas'][] = [
                        'linha' => $linhaExcel,
                        'erro' => "A viatura/equipamento '{$prefixo_sga}' pertence à OM '{$nome_om_frota}', diferente do batalhão selecionado."
                    ];
                    $stmtFrota->close();
                    continue;
                }

            } else {
                $response['falhas'][] = [
                    'linha' => $linhaExcel,
                    'erro' => "Prefixo SGA '{$prefixo_sga}' não encontrado no sistema."
                ];
                $stmtFrota->close();
                continue;
            }
            $stmtFrota->close();

            // ================================
            // Converte data corretamente
            // ================================
            $data_abertura = converterDataExcel($data_abertura);

            // ================================
            // Converte manutenção preventiva
            // ================================
            $manutencao_preventiva = normalize_text($manutencao_preventiva);
            $manutencao_preventiva = ($manutencao_preventiva === 'sim' || $manutencao_preventiva === '1') ? 1 : 0;

            // ================================
            // Inserção principal
            // ================================
            $sql = "INSERT INTO os_principal (
                batalhao, id_frota, prefixo_sga, data_abertura, odometro_horimetro,
                solicitante, local_os, problema, secao_rspns, causa_indisponibilidade,
                tipo_mnt, status, valornd30, valornd39, valorTOTAL, manutencao_preventiva,
                prox_mnt_prev_hor, prox_mnt_prev_odo, cmt_ceem, ch_controle, ch_suprimento
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $stmt = $conexao->prepare($sql);
            if (!$stmt) {
                $response['falhas'][] = ['linha' => $linhaExcel, 'erro' => 'Erro ao preparar statement principal.'];
                continue;
            }

            $stmt->bind_param(
                "iisssssssssssdiiissss",
                $batalhao,
                $id_frota,
                $prefixo_sga,
                $data_abertura,
                $odometro_horimetro,
                $solicitante,
                $local_os,
                $problema,
                $secao_rspns,
                $causa_indisponibilidade,
                $tipo_mnt,
                $status,
                $valornd30,
                $valornd39,
                $valorTOTAL,
                $manutencao_preventiva,
                $prox_mnt_prev_hor,
                $prox_mnt_prev_odo,
                $cmt_ceem,
                $ch_controle,
                $ch_suprimento
            );

            if ($stmt->execute()) {
                $id_os = $stmt->insert_id;

                // ================================
                // Falhas identificadas
                // ================================
                if (!empty($falhas_identificadas)) {
                    $falhas = array_map('trim', explode(';', $falhas_identificadas));
                    foreach ($falhas as $falha) {
                        if ($falha === '') continue;
                        $stmtFalha = $conexao->prepare("INSERT INTO os_falhas (id_osprincipal, falha_identificada) VALUES (?, ?)");
                        $stmtFalha->bind_param("is", $id_os, $falha);
                        $stmtFalha->execute();
                        $stmtFalha->close();
                    }
                }

                // ================================
                // Pessoal utilizado
                // ================================
                if (!empty($pessoal_utilizado)) {
                    $pessoas = array_map('trim', explode(';', $pessoal_utilizado));
                    foreach ($pessoas as $pessoa) {
                        if ($pessoa === '') continue;
                        $stmtPessoal = $conexao->prepare("INSERT INTO os_pessoal (id_osprincipal, nome_militar) VALUES (?, ?)");
                        $stmtPessoal->bind_param("is", $id_os, $pessoa);
                        $stmtPessoal->execute();
                        $stmtPessoal->close();
                    }
                }

                // ================================
                // Itens utilizados
                // ================================
                if (!empty($itens_utilizados)) {
                    $itens = array_map('trim', explode(';', $itens_utilizados));
                    foreach ($itens as $item) {
                        if ($item === '') continue;
                        $stmtItem = $conexao->prepare("INSERT INTO os_itens (id_osprincipal, itens_utilizados) VALUES (?, ?)");
                        $stmtItem->bind_param("is", $id_os, $item);
                        $stmtItem->execute();
                        $stmtItem->close();
                    }
                }

                $response['sucessos']++;
            } else {
                $response['falhas'][] = [
                    'linha' => $linhaExcel,
                    'erro' => 'Erro ao inserir OS principal: ' . $stmt->error
                ];
            }
            $stmt->close();
        }

        $response['mensagem'] = "{$response['sucessos']} OS importadas com sucesso." . 
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
?>
