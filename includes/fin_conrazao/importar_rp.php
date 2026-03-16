<?php
include '../../conexao/config.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_FILES['arquivo']) || $_FILES['arquivo']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Arquivo não enviado corretamente.']);
    exit;
}

$arquivo = $_FILES['arquivo']['tmp_name'];
$linhas = file($arquivo);
$enviados = [];
$fails = [];

foreach ($linhas as $i => $linha) {
    $linha = trim($linha);

    // Padrão para capturar empenhos: N 2025NE000001, N 2022NE000099 etc
    if (preg_match('/^N\s+(20[0-9]{2}|21[0-9]{2}|22[0-9]{2}|23[0-9]{2}|24[0-9]{2}|25[0-9]{2})NE\d{6}/', $linha, $match)) {
        $partes = preg_split('/\s+/', $linha);
        $nmr_empenho = $partes[1] ?? null;

        // Linha seguinte deve conter o saldo (último campo)
        $linhaSaldo = $linhas[$i + 1] ?? '';
       $linhaSaldo = trim($linhaSaldo);

// Captura o valor com formato 9.999,99 mesmo que venha antes de um "C" ou qualquer letra
preg_match('/(\d{1,3}(?:\.\d{3})*,\d{2})/', $linhaSaldo, $matchSaldo);
$valorBruto = $matchSaldo[1] ?? null;

$valorLimpo = str_replace(['.', ','], ['', '.'], $valorBruto);


        if ($nmr_empenho && is_numeric($valorLimpo)) {
            // Apagar anterior se existir
            $conexao->query("DELETE FROM fin_siafi_restopagar WHERE nmr_empenho = '$nmr_empenho'");

            $stmt = $conexao->prepare("INSERT INTO fin_siafi_restopagar (nmr_empenho, saldo_empenho) VALUES (?, ?)");
            $stmt->bind_param("sd", $nmr_empenho, $valorLimpo);
            if ($stmt->execute()) {
                $enviados[] = [
                    'nmr_empenho' => $nmr_empenho,
                    'saldo_empenho' => number_format($valorLimpo, 2, ',', '.')
                ];
            } else {
                $fails[] = [
                    'nmr_empenho' => $nmr_empenho,
                    'erro' => 'Erro ao salvar no banco'
                ];
            }
            $stmt->close();
        } else {
            $fails[] = [
                'nmr_empenho' => $nmr_empenho ?? '(não encontrado)',
                'erro' => 'Saldo inválido'
            ];
        }
    }
}

echo json_encode([
    'status' => 'sucesso',
    'enviados' => $enviados,
    'falhas' => $fails
]);
