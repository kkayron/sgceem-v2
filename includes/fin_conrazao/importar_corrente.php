<?php
// includes/siafi/importar_conrazao.php

declare(strict_types=1);

include '../../conexao/config.php';

$pagina_id = 26;

require_once('../api/seguranca_json_importar.php');

header('Content-Type: application/json; charset=utf-8');

// 🔒 VALIDAR CSRF TOKEN
if (
    empty($_POST['csrf_token']) ||
    empty($_SESSION['csrf_token']) ||
    !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
) {
    http_response_code(403);
    echo json_encode([
        'status' => 'erro',
        'mensagem' => 'Token inválido.'
    ]);
    exit;
}

// 🔒 Configurações
$MAX_FILE_SIZE = 2 * 1024 * 1024; // 2MB
$ALLOWED_EXT = ['txt'];

$response = ['enviados' => [], 'falhas' => []];

try {

    // 🔒 Validação de upload
    if (!isset($_FILES['arquivo']) || $_FILES['arquivo']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('Erro no upload do arquivo.');
    }

    $file = $_FILES['arquivo'];

    // 🔒 Tamanho
    if ($file['size'] > $MAX_FILE_SIZE) {
        throw new Exception('Arquivo muito grande. Máx 2MB.');
    }

    // 🔒 Extensão
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $ALLOWED_EXT)) {
        throw new Exception('Apenas arquivos .txt são permitidos.');
    }

    // 🔒 MIME (flexível — não quebrar funcionamento)
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);

    if (strpos($mime, 'text') === false && $mime !== 'application/octet-stream') {
        throw new Exception('Tipo de arquivo inválido.');
    }

    // 🔒 Abrir arquivo (stream)
    $handle = fopen($file['tmp_name'], 'r');
    if (!$handle) {
        throw new Exception('Erro ao abrir o arquivo.');
    }

    $nmr_empenho = null;

    // 🔒 Transação
    $conexao->begin_transaction();

    while (($linha = fgets($handle)) !== false) {

        $linha = trim($linha);

        // 🔒 Ignorar lixo
        if ($linha === '' || strlen($linha) > 500) {
            continue;
        }

        // ✅ PADRÃO REAL DO SIAFI (mantido compatível)
        if (preg_match('/^N\s+(\d{4}NE\d{6})/i', $linha, $match)) {
            $nmr_empenho = $match[1];
            continue;
        }

        // ✅ Linha com descrição + valor
        if ($nmr_empenho && preg_match('/(\d{1,3}(?:\.\d{3})*,\d{2})\s*[CD]?$/', $linha, $matchSaldo)) {

            $valor_br = $matchSaldo[1];

            // 🔒 Sanitização
            $valor_br = preg_replace('/[^\d.,]/', '', $valor_br);

            $saldo = floatval(str_replace(['.', ','], ['', '.'], $valor_br));

            if ($saldo >= 0) {

                // 🔒 DELETE seguro
                $stmtDel = $conexao->prepare("DELETE FROM fin_siafi_corrente WHERE nmr_empenho = ?");
                $stmtDel->bind_param("s", $nmr_empenho);
                $stmtDel->execute();
                $stmtDel->close();

                // 🔒 INSERT seguro
                $stmt = $conexao->prepare("
                    INSERT INTO fin_siafi_corrente (nmr_empenho, saldo_empenho)
                    VALUES (?, ?)
                ");

                $stmt->bind_param("sd", $nmr_empenho, $saldo);

                if ($stmt->execute()) {
                    $response['enviados'][] = [
                        'nmr_empenho' => $nmr_empenho,
                        'saldo_empenho' => number_format($saldo, 2, ',', '.')
                    ];
                } else {
                    $response['falhas'][] = [
                        'nmr_empenho' => $nmr_empenho,
                        'erro' => 'Erro ao inserir'
                    ];
                }

                $stmt->close();
                $nmr_empenho = null;
            }
        }
    }

    fclose($handle);

    // 🔒 Commit
    $conexao->commit();

    echo json_encode(['status' => 'sucesso'] + $response);

} catch (Exception $e) {

    $conexao->rollback();

    echo json_encode([
        'status' => 'erro',
        'mensagem' => $e->getMessage()
    ]);
}