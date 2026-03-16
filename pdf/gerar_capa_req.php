<?php
ob_start();           // garante que nada será enviado antes
ini_set('display_errors', 0);
error_reporting(E_ALL);

require_once 'vendor/autoload.php';
require_once '../conexao/config.php';

use Dompdf\Dompdf;


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
           om.nome AS nome_batalhao
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

// Formata algumas variáveis vindas do banco
$requisitante = htmlspecialchars($req['requisitante'] ?? '', ENT_QUOTES, 'UTF-8');
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
$nota_credito = htmlspecialchars($req['nota_credito'] ?? '', ENT_QUOTES, 'UTF-8');
$necessidade_contrato = htmlspecialchars($req['necessidade_contrato'] ?? '', ENT_QUOTES, 'UTF-8');
$tipo_empenho = htmlspecialchars($req['tipo_empenho'] ?? '', ENT_QUOTES, 'UTF-8');
$cmt_ceem = htmlspecialchars($req['cmt_ceem'] ?? '', ENT_QUOTES, 'UTF-8');
$ch_financeiro = htmlspecialchars($req['ch_financeiro'] ?? '', ENT_QUOTES, 'UTF-8');

// ===============================
// BUSCA ITENS DA REQUISIÇÃO
// assume fin_requisicao_itens.id_item referencia fin_pregao_itens.id
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
    ORDER BY COALESCE(pi.nmr_item_pregao, ri.id) ASC, ri.id ASC
";
$stmtItens = $conexao->prepare($sqlItens);
$stmtItens->bind_param("i", $id);
$stmtItens->execute();
$resItens = $stmtItens->get_result();

$itens = [];
while ($row = $resItens->fetch_assoc()) {
    // garantir números
    $row['quant_pedida'] = floatval($row['quant_pedida']);
    $row['valor_unt'] = floatval($row['valor_unt']);
    // calcular valor_total por linha se não informado
    if (empty($row['valor_total_item']) || floatval($row['valor_total_item']) == 0) {
        $row['valor_total_item'] = $row['quant_pedida'] * $row['valor_unt'];
    } else {
        $row['valor_total_item'] = floatval($row['valor_total_item']);
    }
    $itens[] = $row;
}
$stmtItens->close();

// ===============================
// Soma total
// ===============================
$totalGeral = 0;
foreach ($itens as $it) {
    $totalGeral += $it['valor_total_item'];
}

// ===============================
// MONTAGEM DO HTML: CAPA + QUEBRA DE PÁGINA + REQUISIÇÃO
// ===============================
$anoAtual = date('Y');
$nomearquivo = "requisicao_nmr{$id}";

// Observação: mantive o visual clássico (Cambria, tabelas com bordas).
$html = '<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<title>Requisição - Nmr '.$id.'</title>
<style>
    body{ font-family: Cambria, "Hoefler Text", "Liberation Serif", Times, "Times New Roman", serif; font-size:12px; color:#000; margin:10px; }
    table{ border:1px solid #000; border-collapse:collapse; width:100%; margin:5px 0; }
    td, th{ border:1px solid #000; padding:4px; vertical-align:top; }
    p{ margin:2px 0; }
    .tabelamenor td{ font-size:9px; }
    .no-border{ border:none !important; }
    .text-left{ text-align:left; }
    .text-right{ text-align:right; }
    .page-break{ page-break-after: always; }
</style>
</head>
<body>';

// -------------------- CAPA (página 1) --------------------
$html .= '
<table>
<tr><td>
<p><b>FLUXO DO PROCESSO</b></p>
<p><b>Protocolo do Proc Adm n° '.$id.'</b></p>
<br>
<p><b>Seção requisitante:</b> Sec Ctr - CEEM  UG: 160353</p>
<p><b>MODALIDADE:</b>
( '.$req['tipo_pregao'].' ) Pregão 6ºBEC &nbsp; ( ) Pregão CARONA &nbsp; ( ) Pregão UG Participante
</p>
<p>
( ) Inexigibilidade &nbsp; ( ) Dispensa de Licitação &nbsp; ( ) Contrato
</p>
<p><b>Descrição do Material ou Serviço:</b> '.htmlspecialchars($req['finalidade'] ?? 'Aquisição de peças de manutenção de viaturas e equipamentos', ENT_QUOTES, 'UTF-8').'</p>
<p><b>Necessidade de contrato:</b> ( '.($req['necessidade_contrato'] ?? '') . ' )</p>
<p><b>CONTATO COM FORNECEDOR</b></p>
<p>Responsável: '.htmlspecialchars($req['contato_nome'] ?? $req['requisitante'], ENT_QUOTES, 'UTF-8').' — Tel: '.htmlspecialchars($req['contato_numero'] ?? '', ENT_QUOTES, 'UTF-8').'</p>
<br>
<p><b>LOCAL DE DESTINO DO MAT/SV:</b> _______________________________________</p>
<br>
<p><b>EMPENHO A SER ANULADO (SFC):</b> ______________________________________________</p>
<br>
<p><b>Nota de Empenho:</b> ________NE___________, de _______/____________/________.</p>
<br>

<p><b>ACOMPANHAMENTO DO PROCESSO</b></p>
<table style="width:100%; text-align:center;">
<tr>
  <td><b>ORIGEM</b></td>
  <td><b>DESTINO</b></td>
  <td><b>DATA</b></td>
  <td><b>RECEBIDO</b></td>
</tr>
<tr><td>Sç Requisitante</td><td>Fisc Adm/S4</td><td>______/______/______</td><td>_____________</td></tr>
<tr><td>Sç Requisitante</td><td>OD</td><td>______/______/______</td><td>_____________</td></tr>
<tr><td>Sç Requisitante</td><td>SALC</td><td>______/______/______</td><td>_____________</td></tr>
<tr><td>SALC</td><td>OD</td><td>______/______/______</td><td>_____________</td></tr>
<tr><td>SALC *</td><td>REQUISITANTE</td><td>______/______/______</td><td>_____________</td></tr>
</table>

<br>
<p><b>DATA DE RECEBIMENTO DA NOTA FISCAL:</b> ______ / _______ / _______</p>
<p><b>OBSERVAÇÕES SOBRE RECEBIMENTO DO MATERIAL:</b> ____________________________________</p>

<br>
<table style="width:100%; text-align:center;">
<tr>
  <td><b>ORIGEM</b></td>
  <td><b>DESTINO</b></td>
  <td><b>DATA</b></td>
  <td><b>RECEBIDO</b></td>
</tr>
<tr><td>Sç Requisitante</td><td>OD</td><td>______/______/______</td><td>_____________</td></tr>
<tr><td>Sç Requisitante</td><td>Set Fin</td><td>______/______/______</td><td>_____________</td></tr>
</table>

<p><b>* SALC entrega a Via da Conformidade de Registro de Gestão</b></p>

</td></tr>
</table>

';

// DOCUMENTAÇÃO A SER ANEXADA - parte da capa
$html .= '
<table>
<tr><td>
<p><b>DOCUMENTAÇÃO A SER ANEXADA</b></p>

<table class="tabelamenor" style="width:100%; text-align:center;">
<tr>
<th>Seção Responsável</th>
<th>Pregão 6ºBEC / Participante</th>
<th>Pregão CARONA</th>
<th>Dispensa Serviço</th>
<th>Dispensa Material (Cotação)</th>
<th>Inexigibilidade</th>
<th>Contrato</th>
</tr>

<tr>
<td>Sç Requisitante</td>
<td>( ) Fluxo Processo<br>( ) Requisição<br>( ) Cópia itens Ata<br>( ) CADIN<br>( ) CNDT<br>( ) SICAF<br>( ) CONS. TCU<br>( ) NC</td>
<td>( ) Fluxo Processo<br>( ) Requisição<br>( ) Cópia itens Ata<br>( ) 3 Orçamentos<br>( ) Solic Fornecedor<br>( ) Autoriz Fornecedor<br>( ) Solic UASG*<br>( ) Autoriz UASG*</td>
<td>( ) Fluxo Processo<br>( ) Requisição<br>( ) Diex requisitório<br>( ) Justif Dispensa<br>( ) 3 Orçamentos</td>
<td>Enviar para SALC:<br>( ) Fluxo Processo<br>( ) Diex Requisitório<br>( ) Justif Dispensa<br>( ) 3 Orçamentos<br><b>* Após homologação:</b><br>( ) Requisição<br>( ) CADIN</td>
<td>( ) Fluxo Processo<br>( ) Requisição<br>( ) CADIN<br>( ) CNDT</td>
<td>( ) Fluxo Processo<br>( ) Último termo aditivo<br>( ) Requisição</td>
</tr>

<tr>
<td>SALC</td>
<td>( ) NE<br>No caso UG PART:<br>( ) Termo Abertura</td>
<td>( ) NE</td>
<td>( ) NE</td>
<td>( ) Publicação BI<br>( ) Divulgação Cotação</td>
<td>( ) NE</td>
<td>( ) NE</td>
</tr>

<tr>
<td>Setor Recebimento Mat/Serv</td>
<td>( ) Nota Fiscal<br>( ) DANFE<br>( ) CNDT<br>( ) Decl Op Simples<br>( ) Boletim Medição<br>( ) Capeador NF</td>
<td>( ) Nota Fiscal<br>( ) DANFE<br>( ) CNDT</td>
<td>( ) Nota Fiscal</td>
<td>( ) Nota Fiscal</td>
<td>( ) Nota Fiscal</td>
<td>( ) Nota Fiscal</td>
</tr>

</table>

</td></tr>
</table>

<div class="page-break"></div>
';

// -------------------- PÁGINA DA REQUISIÇÃO (página 2) --------------------
$html .= '
<table style="width:100%;">
<tr>
<td style="width:10%;">
<p>SALC:</p>
<p>NR NE _________</p>
</td>
<td style="width:80%;">
<p>MINISTÉRIO DA DEFESA</p>
<p>EXÉRCITO BRASILEIRO</p>
<p>'.$nome_batalhao.'</p>
<p>(1ª Cia Esp E Cnst/1967)</p>
<p>BATALHÃO SIMÓN BOLÍVAR</p>
</td>
<td style="width:10%;">
<p>Nr '.$id.'/'.$anoAtual.' - Cia E Eqp Mnt</p>
</td>
</tr>
<tr>
<td colspan="3"><b>REQUISIÇÃO DE MATERIAL (MINUTA DE EMPENHO)</b></td>
</tr>
<tr>
<td colspan="3" class="text-left">
<br>
<p>Do: '.$requisitante.'</p>
<p>Ao: '.$destinatario.'</p>
<p>Data: Boa Vista – RR '.$datareq.'</p>
<p>1- Solicitação de realização de Nota de Empenho</p>
<p>2- Nos termos contidos nos Art 13 das IG 12-02, aprovadas pela Port Min Nº 305, de 22MA95, solicito-vos providências no sentido de aprovar a contratação da empresa para o fornecimento do material</p>
</td>
</tr>
</table>

<table style="width:100%;">
<tr>
<td><b>CNPJ</b></td>
<td><b>RAZÃO SOCIAL DA EMPRESA</b></td>
<td><b>NR PROCESSO</b></td>
<td><b>UG LICITAÇÃO</b></td>
<td><b>UASG</b></td>
<td colspan="5"><b>FINALIDADE</b></td>
</tr>
<tr>
<td>'.$cnpjempresa.'</td>
<td>'.$nomeempresa.'</td>
<td>'.$nmrpregao.'</td>
<td>'.$uglicitacao.'</td>
<td>'.$uasglicitacao.'</td>
<td colspan="5">'.$finalidade.'</td>
</tr>

<tr>
<th>Nr Ordem</th>
<th>DESCRIÇÃO DO ITEM</th>
<th>Item da licitação</th>
<th>Und</th>
<th>Qtd Pedida</th>
<th>Valor Unitário</th>
<th>Valor Total</th>
<th>ITEM OOG</th>
<th>QUANTIDADE DISPONÍVEL</th>
<th>SI</th>
</tr>
';

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
    $qtd = number_format($it['quant_pedida'], 2, ',', '.');
    $valor_unt_fmt = number_format($it['valor_unt'], 2, ',', '.');
    $valor_total_fmt = number_format($it['valor_total_item'], 2, ',', '.');
    $saldo_item = ($it['saldo_item'] !== null && $it['saldo_item'] !== '') ? htmlspecialchars($it['saldo_item'], ENT_QUOTES, 'UTF-8') : '';

    $html .= '
    <tr>
        <td style="text-align:center;">'.$contador.'</td>
        <td class="text-left">'.$descricao.'</td>
        <td style="text-align:center;">'.$nmr_item_pregao.'</td>
        <td style="text-align:center;">'.$und.'</td>
        <td style="text-align:center;">'.$qtd.'</td>
        <td style="text-align:right;">R$ '.$valor_unt_fmt.'</td>
        <td style="text-align:right;">R$ '.$valor_total_fmt.'</td>
        <td style="text-align:center;">'.$item_oog_global.'</td>
        <td style="text-align:center;">'.$saldo_item.'</td>
        <td style="text-align:center;">SI</td>
    </tr>
    ';
}

if ($contador === 0) {
    $html .= '
    <tr>
        <td colspan="10" class="text-left">Nenhum item com quantidade solicitada encontrado para esta requisição.</td>
    </tr>
    ';
}

// Soma total formatada
$somavalortotal = number_format($totalGeral, 2, ',', '.');

$html .= '
<tr>
<td colspan="6" style="text-align:right;"><b>VALOR TOTAL:</b></td>
<td colspan="4" style="text-align:left;"><b>R$ '.$somavalortotal.'</b></td>
</tr>

<tr>
<td colspan="10">
<br>
<p><b>'.$finalidade.'</b></p>
<br>
</td>
</tr>

<tr>
<td colspan="5">
<br><br><br><br><br>
<p>IGOR <b>FELIPE ALVES</b> DE CARVALHO - 1º TEN</p>
<p>Chefe da Seção de Controle</p>
</td>

<td colspan="5">
<br><br><br><br><br>
<p>IGOR <b>FELIPE ALVES</b> DE CARVALHO - 1º TEN</p>
<p>Cmt Cia E Eqp Mnt</p>
</td>
</tr>

<tr>
<td colspan="2">
<p><b><u>INFORMAÇÕES DA DESPESA</u></b></p>
<p>(A CARGO DO COP/FISC ADM E/OU SALC)</p>
<br>
<p style="text-align:left;">NC: </p>
<p style="text-align:left;">PI: </p>
<p style="text-align:left;">ND: </p>
<p style="text-align:left;">NUP: </p>
<p style="text-align:left;">TIPO: </p>
</td>

<td colspan="6">
<p style="text-align:left; margin-left:4px;">Do: '.$destinatario.'</p>
<p style="text-align:left; margin-left:4px;">Ao: Sr OD</p>
<br>
<p style="text-align:left; margin-left:4px;">Solicito autorizar o empenho da despesa</p>
<p style="text-align:left; margin-left:4px;">Em: ____/_____/_____</p>
<br><br><br><br>
<p><b>MARCONE CHAVES DA SILVA JUNIOR - CAP</b></p>
<p>S4 do 6º Batalhão de Engenharia de Construção</p>
</td>

<td colspan="2">
<p style="text-align:left; margin-left:4px;">DO: OD</p>
<p style="text-align:left; margin-left:4px;">AO: Ch SALC</p>
<br>
<p style="text-align:left; margin-left:4px;">Em: ____/_____/_____</p>
<br>
<p>Autorizo (     )                     Não Autorizo (     )</p>
<br><br><br>
<p><b>CADSON DE SOUZA BARBOZA – TC</b></p>
<p>Ordenador de Despesas do 6º BEC</p>
</td>
</tr>

</table>
</body>
</html>
';

$html = ob_get_clean();

// ============= GERA O PDF ===================
$dompdf = new Dompdf(['enable_remote' => true]);

$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

// CORRIGE PROBLEMA DE PDF CORROMPIDO
$pdfOutput = $dompdf->output();

header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="requisicao.pdf"');
header('Content-Length: ' . strlen($pdfOutput));

echo $pdfOutput;
exit;

