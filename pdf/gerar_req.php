<?php
// requisicao_pdf.php
require_once 'vendor/autoload.php';
require_once '../conexao/config.php'; // ajuste o caminho se necessário

use Dompdf\Dompdf;

// ------------------------------------------------------------------
// Helper: normaliza strings numéricas (ex.: "1.234,56" -> 1234.56)
// ------------------------------------------------------------------
function parse_decimal($valor) {
    if ($valor === null || $valor === '') return 0.0;
    // Se já for numérico
    if (is_numeric($valor)) return (float)$valor;
    // Remove espaços e quebras
    $v = trim($valor);
    $v = str_replace(chr(160), '', $v); // non-break space
    $v = str_replace(' ', '', $v);
    // Remove milhares: remove pontos que aparecem antes de 3 dígitos seguidos por vírgula ou fim
    // Simples e robusto: remover todos os pontos e trocar vírgula por ponto
    $v = str_replace('.', '', $v);
    $v = str_replace(',', '.', $v);
    // Remove qualquer caractere que não seja dígito, ponto ou sinal menos
    $v = preg_replace('/[^\d\.\-]/', '', $v);
    if ($v === '' || $v === '.' || $v === '-.' ) return 0.0;
    return (float)$v;
}

// Recebe ID da requisição via GET
$id = intval($_GET['id'] ?? 0);
if ($id <= 0) {
    die('ID inválido.');
}

// ===============================
// BUSCA DADOS DA REQUISIÇÃO (principal)
// ===============================
$sqlReq = "
    SELECT r.*,
           f.nome_empresa AS fornecedor_nome,
           f.cnpj_empresa AS fornecedor_cnpj,
           p.nmr_pregao,
           p.ug_licitacao,
           p.uasg_licitacao,
           om.nome AS nome_batalhao,
           om.abreviatura AS abreviatura_batalhao
    FROM fin_requisicao r
    LEFT JOIN fin_fornecedores f ON f.id = r.id_fornecedor
    LEFT JOIN fin_pregao p ON p.id = r.id_pregao
    LEFT JOIN organizacoes_militares om ON om.id = r.batalhao
    WHERE r.id = ?
";
$stmtReq = $conexao->prepare($sqlReq);
$stmtReq->bind_param("i", $id);
$stmtReq->execute();
$resReq = $stmtReq->get_result();
$req = $resReq->fetch_assoc();
$stmtReq->close();

if (!$req) {
    die('Requisição não encontrada.');
}

// Formata algumas variáveis
$requisitante = htmlspecialchars($req['requisitante'] ?? '', ENT_QUOTES, 'UTF-8');
$cmt_ceem = htmlspecialchars($req['cmt_ceem'] ?? '', ENT_QUOTES, 'UTF-8');
$ch_financeiro = htmlspecialchars($req['ch_financeiro'] ?? '', ENT_QUOTES, 'UTF-8');
$ch_controle = htmlspecialchars($req['ch_controle'] ?? '', ENT_QUOTES, 'UTF-8');
$ch_s4 = htmlspecialchars($req['ch_s4'] ?? '', ENT_QUOTES, 'UTF-8');
$cmt_batalhao = htmlspecialchars($req['cmt_batalhao'] ?? '', ENT_QUOTES, 'UTF-8');
$destinatario = htmlspecialchars($req['destinatario'] ?? '', ENT_QUOTES, 'UTF-8');
$datareq = !empty($req['data_requisicao']) ? date('d/m/Y', strtotime($req['data_requisicao'])) : '';
$cnpjempresa = htmlspecialchars($req['fornecedor_cnpj'] ?? '', ENT_QUOTES, 'UTF-8');
$nomeempresa = htmlspecialchars($req['fornecedor_nome'] ?? '', ENT_QUOTES, 'UTF-8');
$nmrpregao = htmlspecialchars($req['nmr_pregao'] ?? '', ENT_QUOTES, 'UTF-8');
$uglicitacao = htmlspecialchars($req['ug_licitacao'] ?? '', ENT_QUOTES, 'UTF-8');
$uasglicitacao = htmlspecialchars($req['uasg_licitacao'] ?? '', ENT_QUOTES, 'UTF-8');
$finalidade = htmlspecialchars($req['finalidade'] ?? '', ENT_QUOTES, 'UTF-8');
$item_oog_global = htmlspecialchars($req['item_oog'] ?? '', ENT_QUOTES, 'UTF-8'); // caso seja global
$nome_batalhao = htmlspecialchars($req['nome_batalhao'] ?? '', ENT_QUOTES, 'UTF-8');
$abreviatura_batalhao = htmlspecialchars($req['abreviatura_batalhao'] ?? '', ENT_QUOTES, 'UTF-8');
$nota_credito = htmlspecialchars($req['nota_credito'] ?? '', ENT_QUOTES, 'UTF-8');
$plano_interno = htmlspecialchars($req['plano_interno'] ?? '', ENT_QUOTES, 'UTF-8');
$natureza_despesa = htmlspecialchars($req['natureza_despesa'] ?? '', ENT_QUOTES, 'UTF-8');
$tipo_empenho = htmlspecialchars($req['tipo_empenho'] ?? '', ENT_QUOTES, 'UTF-8');

// ===============================
// BUSCA ITENS DA REQUISIÇÃO
// Assume que fin_requisicao_itens.id_item referencia fin_pregao_itens.id
// ===============================
$sqlItens = "
    SELECT ri.id AS id_requisicao_item,
           ri.quant_saida_item AS quant_pedida,
           pi.nmr_item_pregao,
           COALESCE(pi.descricao_item, '') AS descricao_item,
           COALESCE(pi.und_solicitada, '') AS und,
           COALESCE(pi.valor_unt, 0) AS valor_unt,
           COALESCE(pi.valor_total, 0) AS valor_total_item,
           COALESCE(pi.saldo_item, '') AS saldo_item
    FROM fin_requisicao_itens ri
    LEFT JOIN fin_pregao_itens pi ON pi.id = ri.id_item
    WHERE ri.id_requisicao = ?
    ORDER BY pi.nmr_item_pregao ASC, ri.id ASC
";
$stmtItens = $conexao->prepare($sqlItens);
$stmtItens->bind_param("i", $id);
$stmtItens->execute();
$resItens = $stmtItens->get_result();

$itens = [];
while ($row = $resItens->fetch_assoc()) {
    // garantir números e normalizar formatos brasileiros
    $quant = parse_decimal($row['quant_pedida'] ?? $row['quant_saida_item'] ?? 0);
    $valorUnt = parse_decimal($row['valor_unt'] ?? 0);

    // calcular valor_total por linha: quantidade * valor unitário
    $valorTotalCalc = round($quant * $valorUnt, 2);

    // Preencher o array com valores corretos (numéricos)
    $row['quant_pedida'] = $quant;
    $row['valor_unt'] = $valorUnt;
    $row['valor_total_item'] = $valorTotalCalc;
    $itens[] = $row;
}
$stmtItens->close();

// ===============================
// Soma total
// ===============================
$totalGeral = 0.0;
foreach ($itens as $it) {
    $totalGeral += (float)$it['valor_total_item'];
}
$totalGeral = round($totalGeral, 2);

// ===============================
// Monta o HTML (visual clássico)
// ===============================
$nomearquivo = "requisicao_nmr{$id}";

$html = "<!DOCTYPE html>
<html lang='pt-br'>
<head>
<meta charset='UTF-8'>
<title>Requisição - Nmr {$id}</title>
<style>
    body{
        font-family: Cambria, 'Hoefler Text', 'Liberation Serif', Times, 'Times New Roman', 'serif';
        font-size: 12px;
        color: #000;
        margin: 8px 10px;
    }
    table{
        border: 1px black solid;
        border-collapse: collapse;
        margin: 5px 5px 5px 5px;
        text-align: center;
        padding: 3px;
        font-size: 12px;
    }
    p { margin-bottom: 0; margin-top: 1.5px; }
    td{ border: 1px black solid; border-collapse: collapse; padding: 4px; vertical-align: top; }
    .fontemaior{ font-size: 18px; }
    .tabelamenor{ font-size: 9px; }
    .no-border { border: none !important; }
    .text-left { text-align: left; }
    .text-right { text-align: right; }
    .small { font-size: 10px; }
</style>
</head>
<body>
<table style='width: 100%;'>
<tr>
<td style='width: 10%;'>
<p>SALC:</p>
<p>NR NE _________</p>
</td>
<td style='width: 80%;'>
<p>MINISTÉRIO DA DEFESA</p>
<p>EXÉRCITO BRASILEIRO</p>
<p>{$nome_batalhao}</p>
</td>
<td style='width: 10%;'>
<p>Nr {$id}/" . date('Y') . " - Cia E Eqp Mnt</p>
</td>
</tr>
<tr>
<td colspan='3'>
<b>REQUISIÇÃO DE MATERIAL (MINUTA DE EMPENHO)</b>
</td>
</tr>
<tr>
<td colspan='3' style='text-align: left;'>
<br>
<p>Do: {$requisitante}</p>
<p>Ao: {$destinatario}</p>
<p>Data: Boa Vista – RR {$datareq}</p>
<p>
1- Solicitação de realização de Nota de Empenho</p>
<p>2- Nos termos contidos nos Art 13 das IG 12-02, aprovadas pela Port Min Nº 305, de 22MA95, solicito-vos providências no sentido de aprovar a contratação da empresa para o fornecimento do material</p>
</td>
</tr>
</table>

<table style='width: 100%;'>
<tr>
<td><b>CNPJ</b></td>
<td><b>RAZÃO SOCIAL DA EMPRESA</b></td>
<td><b>NR PROCESSO</b></td>
<td><b>UG LICITAÇÃO</b></td>
<td><b>UASG</b></td>
<td colspan='5'><b>FINALIDADE</b></td>
</tr>
<tr>
<td>{$cnpjempresa}</td>
<td>{$nomeempresa}</td>
<td>{$nmrpregao}</td>
<td>{$uglicitacao}</td>
<td>{$uasglicitacao}</td>
<td colspan='5'>{$finalidade}</td>
</tr>

<tr>
<td><b>Nr Ordem</b></td>
<td><b>DESCRIÇÃO DO ITEM</b></td>
<td><b>Item da licitação</b></td>
<td><b>Und</b></td>
<td><b>Qtd Pedida</b></td>
<td><b>Valor Unitário</b></td>
<td><b>Valor Total</b></td>
<td><b>ITEM OOG</b></td>
<td><b>QUANTIDADE DISPONÍVEL</b></td>
<td><b>SI</b></td>
</tr>
";

// Preenche linhas de itens dinamicamente (somente itens com quantidade > 0)
$contador = 0;
foreach ($itens as $it) {
    if ($it['quant_pedida'] <= 0) {
        continue;
    }
    $contador++;
    $descricao = htmlspecialchars($it['descricao_item'], ENT_QUOTES, 'UTF-8');
    $nmr_item_pregao = htmlspecialchars($it['nmr_item_pregao'], ENT_QUOTES, 'UTF-8');
    $und = htmlspecialchars($it['und'], ENT_QUOTES, 'UTF-8');

    // FORMATAÇÃO CORRETA: usa os valores numéricos já calculados
    $qtd = number_format($it['quant_pedida'], 2, ',', '.');
    $valor_unt_fmt = number_format($it['valor_unt'], 2, ',', '.');
    $valor_total_fmt = number_format($it['valor_total_item'], 2, ',', '.');

    $saldo_item = ($it['saldo_item'] !== null && $it['saldo_item'] !== '') ? htmlspecialchars($it['saldo_item'], ENT_QUOTES, 'UTF-8') : '';

    $html .= "
    <tr>
        <td>{$contador}</td>
        <td class='text-left'>{$descricao}</td>
        <td>{$nmr_item_pregao}</td>
        <td>{$und}</td>
        <td>{$qtd}</td>
        <td>R$ {$valor_unt_fmt}</td>
        <td>R$ {$valor_total_fmt}</td>
        <td>{$item_oog_global}</td>
        <td>{$saldo_item}</td>
        <td>SI</td>
    </tr>
    ";
}

if ($contador === 0) {
    $html .= "
    <tr>
        <td colspan='10' class='text-left small'>Nenhum item com quantidade solicitada encontrado para esta requisição.</td>
    </tr>
    ";
}

// Soma total formatada
$somavalortotal = number_format($totalGeral, 2, ',', '.');

$html .= "
<tr>
<td colspan='6' style='text-align: right;'><b>VALOR TOTAL:</b></td>
<td colspan='4' style='text-align: left;'><b>R$ {$somavalortotal}</b></td>
<tr>
<tr>
<td colspan='10'>
<br>
<p><b>{$finalidade}</b></p>
<br>
</td>
</tr>
<tr>
<td colspan='5'>
<br>
<br>
<br>
<br>
<br>
<p>$ch_financeiro</p>
<p>Chefe da SAM</p>
</td>
<td colspan='5'>
<br>
<br>
<br>
<br>
<br>
<p>$cmt_ceem</p>
<p>Cmt Cia E Eqp Mnt</p>
</td>
</tr>
<tr>
<td colspan='2'>
<p><b><u>INFORMAÇÕES DA DESPESA</u></b></p>
<p>(A CARGO DO COP/FISC ADM E/OU SALC)</p>
<br>
<p style='text-align: left;'>NC: $nota_credito</p>
<p style='text-align: left;'>PI: $plano_interno </p>
<p style='text-align: left;'>ND: $natureza_despesa </p>
<p style='text-align: left;'>NUP: </p>
<p style='text-align: left;'>TIPO: $tipo_empenho</p>
</td>
<td colspan='6'>
<p style='text-align: left; margin-left: 4px;'>Do: {$destinatario}</p>
<p style='text-align: left; margin-left: 4px;'>Ao: Sr OD</p>
<br>
<p style='text-align: left; margin-left: 4px;'>Solicito autorizar o empenho da despesa</p>
<p style='text-align: left; margin-left: 4px;'>Em: ____/_____/_____</p>
<br><br><br><br>
<p><b>$ch_s4</b></p>
<p>Fisc Adm/S4 do $abreviatura_batalhao</p>
</td>
<td colspan='2'>
<p style='text-align: left; margin-left: 4px;'>DO: OD</p>
<p style='text-align: left; margin-left: 4px;'>AO: Ch SALC</p>
<br>
<p style='text-align: left; margin-left: 4px;'>Em: ____/_____/_____</p>
<br>
<p>Autorizo (     )                     Não Autorizo (     )</p>
<br><br><br>
<p><b>$cmt_batalhao</b></p>
<p>Ordenador de Despesas do $abreviatura_batalhao</p>
</td>
</tr>

</table>

</body>
</html>
";

// ===============================
// GERA O PDF VIA DOMPDF
// ===============================
$dompdf = new Dompdf(['enable_remote' => true]);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

// Saída segura para prevenir PDF corrompido
$pdfOutput = $dompdf->output();
header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="'.$nomearquivo.'.pdf"');
header('Content-Length: ' . strlen($pdfOutput));
echo $pdfOutput;
exit;
?>
