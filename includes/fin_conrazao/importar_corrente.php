<?php
// includes/siafi/importar_conrazao.php
include '../../conexao/config.php';

$response = ['enviados' => [], 'falhas' => []];

if (!isset($_FILES['arquivo']) || $_FILES['arquivo']['error'] !== UPLOAD_ERR_OK) {
  echo json_encode(['status' => 'erro', 'mensagem' => 'Erro no upload do arquivo.']);
  exit;
}

$conteudo = file_get_contents($_FILES['arquivo']['tmp_name']);
$linhas = explode("\n", $conteudo);

$nmr_empenho = null;

foreach ($linhas as $i => $linha) {
  $linha = trim($linha);

  // Se for uma linha com número de empenho
  if (preg_match('/^N\s+(\d{4}NE\d{6})/i', $linha, $match)) {
    $nmr_empenho = $match[1];
    continue;
  }

  // Se for linha de saldo
  if ($nmr_empenho && preg_match('/([\d.]+,\d{2})\s*[CD]?$/i', $linha, $matchSaldo)) {
    $valor_br = $matchSaldo[1];
    $saldo = floatval(str_replace(['.', ','], ['', '.'], $valor_br));

    // Apaga se já existe
    $stmtDel = $conexao->prepare("DELETE FROM fin_siafi_corrente WHERE nmr_empenho = ?");
    $stmtDel->bind_param("s", $nmr_empenho);
    $stmtDel->execute();
    $stmtDel->close();

    // Insere
    $stmt = $conexao->prepare("INSERT INTO fin_siafi_corrente (nmr_empenho, saldo_empenho) VALUES (?, ?)");
    $stmt->bind_param("sd", $nmr_empenho, $saldo);
    if ($stmt->execute()) {
      $response['enviados'][] = ['nmr_empenho' => $nmr_empenho, 'saldo_empenho' => number_format($saldo, 2, ',', '.')];
    } else {
      $response['falhas'][] = ['nmr_empenho' => $nmr_empenho, 'erro' => $stmt->error];
    }
    $stmt->close();

    $nmr_empenho = null; // reseta para a próxima entrada
  }
}

echo json_encode(['status' => 'sucesso'] + $response);
