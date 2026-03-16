<?php
require_once 'vendor/autoload.php';
require_once '../conexao/config.php';

use Dompdf\Dompdf;

// ===============================
$id = intval($_GET['id'] ?? 0);
if ($id <= 0) die('ID inválido.');

// ===============================
// ORDEM DE FORNECIMENTO
// ===============================
$sql = "
SELECT 
o.*,
om.nome AS nome_batalhao,
e.nmr_empenho
FROM fin_ordemforn o
LEFT JOIN organizacoes_militares om ON om.id=o.batalhao
LEFT JOIN fin_empenhos e ON e.id=o.id_empenho
WHERE o.id=?";

$stmt=$conexao->prepare($sql);
$stmt->bind_param("i",$id);
$stmt->execute();
$ordem=$stmt->get_result()->fetch_assoc();
$stmt->close();

if(!$ordem) die('Ordem não encontrada');


// ===============================
// PEDIDOS
// ===============================
$sqlPedidos="
SELECT 
p.id id_pedido,
p.solicitante,
p.desconto_empenho,
COALESCE(
(SELECT SUM(valor_total)
FROM fin_pedidos_forn_itens
WHERE id_principal=p.id),0) total_pedido
FROM fin_ordemforn_pedidos op
JOIN fin_pedidos_forn p ON p.id=op.id_pedido
WHERE op.id_ordemforn=?";

$stmtPedidos=$conexao->prepare($sqlPedidos);
$stmtPedidos->bind_param("i",$id);
$stmtPedidos->execute();
$resPedidos=$stmtPedidos->get_result();

$pedidos=[];

while($row=$resPedidos->fetch_assoc()){

$percentual=floatval($row['desconto_empenho']);
$totalPedido=floatval($row['total_pedido']);

$valorDesconto=$totalPedido*($percentual/100);
$totalFinal=$totalPedido-$valorDesconto;

$row['valor_desconto']=$valorDesconto;
$row['total_final']=$totalFinal;

$pedidos[]=$row;
}
$stmtPedidos->close();


// ===============================
// ITENS
// ===============================
$itens=[];
$totalGeral=0;
$totalDescontoGeral=0;
$totalFinalGeral=0;

foreach($pedidos as $pedido){

$sqlItens="
SELECT
descricao_item,
quant_solicitada,
und_solicitada,
valor_unt,
valor_total
FROM fin_pedidos_forn_itens
WHERE id_principal=?";

$stmtItens=$conexao->prepare($sqlItens);
$stmtItens->bind_param("i",$pedido['id_pedido']);
$stmtItens->execute();
$resItens=$stmtItens->get_result();

while($i=$resItens->fetch_assoc()){

$percentual=$pedido['desconto_empenho'];

$valorDesconto=$i['valor_total']*($percentual/100);
$valorFinal=$i['valor_total']-$valorDesconto;

$i['id_pedido']=$pedido['id_pedido'];
$i['valor_desconto']=$valorDesconto;
$i['valor_final']=$valorFinal;

$itens[]=$i;

$totalGeral+=$i['valor_total'];
}

$totalDescontoGeral+=$pedido['valor_desconto'];
$totalFinalGeral+=$pedido['total_final'];

$stmtItens->close();
}

// ===============================
ob_start();
?>

<html>
<head>
<meta charset="UTF-8">

<style>

body{
font-family:Arial;
font-size:11px;
margin:25px 35px;
}

h2,h3{
text-align:center;
margin:0;
}

.section-title{
background:#d8e1f5;
border:1px solid #a6b5cc;
padding:5px;
margin-top:12px;
font-weight:bold;
}

.box-info{
border:1px solid #b0b0b0;
padding:8px;
margin-top:5px;
}

.table{
width:100%;
border-collapse:collapse;
margin-top:10px;
}

.table th,.table td{
border:1px solid #bbb;
padding:6px;
}

.table th{
background:#eaf0fa;
}

.total{
text-align:right;
margin-top:8px;
font-weight:bold;
}

.assinaturas{
margin-top:40px;
text-align:center;
}

.assinaturas div{
display:inline-block;
width:30%;
}

.assinaturas hr{
margin:50px 0 5px;
}

.page-break{
page-break-after:always;
}

.footer{
text-align:center;
font-size:10px;
margin-top:20px;
}

</style>

</head>

<body>

<!-- CABEÇALHO -->

<h2>MINISTÉRIO DA DEFESA</h2>
<h2>EXÉRCITO BRASILEIRO</h2>
<h3><?=mb_strtoupper($ordem['nome_batalhao'],'UTF-8')?></h3>

<br>

<h3><b>ORDEM DE FORNECIMENTO Nº <?=$ordem['id']?></b></h3>


<hr>


<div class="box-info">

<strong>Empenho:</strong> <?=$ordem['nmr_empenho']?> <br>

<strong>Data de Cadastro:</strong> <?=date('d/m/Y',strtotime($ordem['data_cadastro']))?>

— 

<strong>Data Limite:</strong> <?=date('d/m/Y',strtotime($ordem['data_entrega_limite']))?>

<br>

<strong>Status:</strong> <?=$ordem['status']?>

</div>


<div class="section-title">DADOS DA EMPRESA</div>

<div class="box-info">

<strong><?=$ordem['empresa_nome']?></strong><br>

CNPJ: <?=$ordem['empresa_cnpj']?> <br>

Email: <?=$ordem['empresa_email']?>

</div>


<div class="section-title">LOCAL DE ENTREGA</div>

<div class="box-info">

<strong>Local:</strong> <?=$ordem['local_entrega']?> <br>

<strong>Responsável:</strong> <?=$ordem['nome_responsavel']?> <br>

<strong>Contato:</strong> <?=$ordem['contato_responsavel']?>

</div>



<div class="section-title">PEDIDOS RELACIONADOS</div>

<div class="box-info">

<?php foreach($pedidos as $p){ ?>

Pedido <b>#<?=$p['id_pedido']?></b>

— Solicitante: <?=htmlspecialchars($p['solicitante'])?>

— Total: R$ <?=number_format($p['total_pedido'],2,',','.')?>

— Desconto (<?=$p['desconto_empenho']?>%): R$ <?=number_format($p['valor_desconto'],2,',','.')?>

— Final: R$ <?=number_format($p['total_final'],2,',','.')?>

<br>

<?php } ?>

</div>



<div class="section-title">ITENS DA ORDEM</div>

<table class="table">

<thead>

<tr>
<th>Pedido</th>
<th>Descrição</th>
<th>Qtd</th>
<th>Unid</th>
<th>Vlr Unit</th>
<th>Vlr Total</th>
<th>Desconto</th>
<th>Vlr Final</th>
</tr>

</thead>

<tbody>

<?php foreach($itens as $item){ ?>

<tr>

<td><?=$item['id_pedido']?></td>

<td><?=htmlspecialchars($item['descricao_item'])?></td>

<td align="center"><?=$item['quant_solicitada']?></td>

<td align="center"><?=$item['und_solicitada']?></td>

<td align="right"><?=number_format($item['valor_unt'],2,',','.')?></td>

<td align="right"><?=number_format($item['valor_total'],2,',','.')?></td>

<td align="right"><?=number_format($item['valor_desconto'],2,',','.')?></td>

<td align="right"><?=number_format($item['valor_final'],2,',','.')?></td>

</tr>

<?php } ?>

</tbody>

</table>


<div class="total">

Total Bruto: R$ <?=number_format($totalGeral,2,',','.')?><br>

Total Descontos: R$ <?=number_format($totalDescontoGeral,2,',','.')?><br>

Total Final: R$ <?=number_format($totalFinalGeral,2,',','.')?>

</div>


<div class="section-title">OBSERVAÇÕES</div>

<div class="box-info">

<?=nl2br(htmlspecialchars($ordem['observacao_final']))?>

</div>


<div class="assinaturas">

<div>
<hr>
<strong><?=$ordem['cmt_ceem']?></strong><br>
Cmt Cia E Eqp Mnt
</div>

<div>
<hr>
<strong><?=$ordem['ch_suprimento']?></strong><br>
Ch Suprimento
</div>

<div>
<hr>
<strong><?=$ordem['ch_controle']?></strong><br>
Ch Controle
</div>

</div>


<div class="footer">

Sistema de Gerenciamento<br>

Documento emitido automaticamente

</div>



<?php
foreach($pedidos as $p){

$idPedido=$p['id_pedido'];

$stmt=$conexao->prepare("
SELECT codigo_item,descricao_item,quant_solicitada,und_solicitada,valor_unt,valor_total
FROM fin_pedidos_forn_itens
WHERE id_principal=?");

$stmt->bind_param("i",$idPedido);
$stmt->execute();

$itensPed=$stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$stmt->close();

$totalPedido=0;

foreach($itensPed as $it){
$totalPedido+=$it['valor_total'];
}

$percentual=$p['desconto_empenho'];
$valorDesconto=$totalPedido*($percentual/100);
$totalFinal=$totalPedido-$valorDesconto;

?>

<div class="page-break"></div>

<h3>PEDIDO AO FORNECEDOR Nº <?=$idPedido?></h3>

<table class="table">

<thead>
<tr>
<th>Item</th>
<th>Descrição</th>
<th>Qtd</th>
<th>Un</th>
<th>Unit</th>
<th>Total</th>
</tr>
</thead>

<tbody>

<?php $c=1; foreach($itensPed as $it){ ?>

<tr>

<td><?=$c++?></td>

<td><?=htmlspecialchars($it['descricao_item'])?></td>

<td><?=$it['quant_solicitada']?></td>

<td><?=$it['und_solicitada']?></td>

<td align="right"><?=number_format($it['valor_unt'],2,',','.')?></td>

<td align="right"><?=number_format($it['valor_total'],2,',','.')?></td>

</tr>

<?php } ?>

<tr>
<td colspan="5" align="right"><b>Total Pedido</b></td>
<td align="right"><b>R$ <?=number_format($totalPedido,2,',','.')?></b></td>
</tr>

<tr>
<td colspan="5" align="right"><b>Desconto (<?=$percentual?>%)</b></td>
<td align="right"><b>R$ <?=number_format($valorDesconto,2,',','.')?></b></td>
</tr>

<tr>
<td colspan="5" align="right"><b>Total Final</b></td>
<td align="right"><b>R$ <?=number_format($totalFinal,2,',','.')?></b></td>
</tr>

</tbody>

</table>

<?php } ?>

</body>
</html>

<?php

$html=ob_get_clean();

$dompdf=new Dompdf();
$dompdf->loadHtml($html);
$dompdf->setPaper('A4','portrait');
$dompdf->render();

$dompdf->stream("ordem_fornecimento_{$id}.pdf",["Attachment"=>false]);

exit;

?>