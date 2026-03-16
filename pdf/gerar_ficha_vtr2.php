<?php
require_once 'vendor/autoload.php';
require_once '../conexao/config.php';

use Dompdf\Dompdf;

// ===============================
// Validação de ID
// ===============================
$id = intval($_GET['id'] ?? 0);
if ($id <= 0) {
    die('ID inválido.');
}

// ===============================
// Consulta principal - Ficha
// ===============================
$sql = "
    SELECT 
        f.*,
        om.nome AS nome_batalhao,
        om.abreviatura AS sigla_batalhao,
        v.prefixo_sga,
        v.prefixo_velho,
        v.ano,
        m.marca AS nome_marca,
        mo.nome_modelo
    FROM sta_fichas f
    LEFT JOIN organizacoes_militares om ON om.id = f.batalhao
    LEFT JOIN frota v ON v.id = f.id_viatura
    LEFT JOIN config_marcas m ON v.marca = m.id
    LEFT JOIN config_modelos mo ON v.modelo = mo.id
    WHERE f.id = ?
";
$stmt = $conexao->prepare($sql);
$stmt->bind_param("i", $id);
$stmt->execute();
$ficha = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$ficha) {
    die('Ficha não encontrada.');
}

// ===============================
// Busca Cmt Cia E Eqp Mnt
// ===============================
$sqlCmt = "SELECT nomecompleto FROM usuarios WHERE funcao LIKE '%Cmt Cia E Eqp Mnt%' LIMIT 1";
$resCmt = $conexao->query($sqlCmt);
$cmt_ceem = $resCmt->fetch_assoc()['nomecompleto'] ?? '________________________________';

// ===============================
// HTML DO PDF (Layout modelo)
// ===============================
$html = '
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<style>
body {
    font-family: "Times New Roman", Times, serif;
    font-size: 11px;
    margin: 25px;
}
h2, h3 {
    text-align: center;
    margin: 2px 0;
}
h3 { text-transform: uppercase; }
.table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 8px;
}
.table td {
    border: 1px solid #000;
    padding: 4px 6px;
    vertical-align: top;
}
.section-title {
    font-weight: bold;
    margin-top: 10px;
    text-transform: uppercase;
    background: #f3f3f3;
    padding: 4px;
    border: 1px solid #000;
}
.assinaturas {
    margin-top: 40px;
    width: 100%;
    text-align: center;
}
.assinaturas div {
    display: inline-block;
    width: 30%;
    margin: 0 1%;
}
.assinaturas hr {
    margin-top: 40px;
    border: none;
    border-top: 1px solid #000;
}
.footer {
    margin-top: 30px;
    text-align: center;
    font-size: 9px;
    color: #555;
}
</style>
</head>
<body>
<p style="text-align: center;">
  <img src="https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcT-d2hgBOBbojBokAIYajmOPio84hI2O_6M_Q&s" width="60" height="60" alt=""/>
</p>
<h2>MINISTÉRIO DA DEFESA</h2>
<h2>EXÉRCITO BRASILEIRO</h2>
<h3>' . mb_strtoupper(htmlspecialchars($ficha["nome_batalhao"]), "UTF-8") . '</h3>
<br>
<h3><b>FICHA DE SERVIÇO DE DESLOCAMENTO DE VIATURA/EQUIPAMENTO Nº ' . $ficha['id'] . '</b></h3>

<div class="section-title">IDENTIFICAÇÃO DA VIATURA</div>
<table class="table">
<tr>
<td><b>Prefixo SGA:</b> ' . htmlspecialchars($ficha['prefixo_sga']) . '</td>
<td><b>Prefixo Antigo:</b> ' . htmlspecialchars($ficha['prefixo_velho']) . '</td>
<td><b>Ano:</b> ' . htmlspecialchars($ficha['ano']) . '</td>
</tr>
<tr>
<td colspan="3"><b>Marca / Modelo:</b> ' . htmlspecialchars($ficha['nome_marca'] . " / " . $ficha['nome_modelo']) . '</td>
</tr>
</table>

<div class="section-title">DADOS GERAIS</div>
<table class="table">
<tr>
<td><b>Data de Abertura:</b> ' . date('d/m/Y', strtotime($ficha['data_abertura'])) . '</td>
<td><b>Data Prevista:</b> ' . date('d/m/Y', strtotime($ficha['data_prevista'])) . '</td>
<td><b>Status:</b> ' . htmlspecialchars($ficha['status']) . '</td>
</tr>
<tr>
<td><b>Solicitante:</b> ' . htmlspecialchars($ficha['solicitante']) . '</td>
<td><b>Motorista:</b> ' . htmlspecialchars($ficha['motorista']) . '</td>
<td><b>Subunidade:</b> ' . htmlspecialchars($ficha['subunidade']) . '</td>
</tr>
<tr>
<td colspan="3"><b>Destino:</b> ' . htmlspecialchars($ficha['destino']) . ' — <b>Cidade:</b> ' . htmlspecialchars($ficha['cidade']) . '</td>
</tr>
</table>

<div class="section-title">APRESENTAÇÃO</div>
<table class="table">
<tr>
<td><b>Chefe a se apresentar:</b> ' . htmlspecialchars($ficha['chefe_apresentar']) . '</td>
<td><b>Local:</b> ' . htmlspecialchars($ficha['local_apresentar']) . '</td>
<td><b>Horário:</b> ' . htmlspecialchars($ficha['horario_apresentar']) . '</td>
</tr>
</table>



<div class="section-title">AUTORIZAÇÃO - CMT CEEM</div>
<table class="table">
<tr>
<td style="text-align: center;">
  <p style="margin: 0; padding: 10;"><b></b></p>
  <p style="margin: 0; padding: 0;"><b>____________________________________</b></p>
  <p style="margin: 0; padding: 0;"><b>' . htmlspecialchars($ficha['cmt_ceem']) . '</b></p>
  <p style="margin: 0; padding: 0;">CMT CIA E EQP MNT</p>
</td>

</tr>
</table>

<div class="section-title">AUTORIZAÇÃO - STA</div>
<table class="table">
<tr>
<td style="text-align: center;">
  <p style="margin: 0; padding: 10;"><b>A viatura/equipamento está em condições de ser utilizada no serviço e itinerário relacionados acima.</b></p>
  <BR>
  <p style="margin: 0; padding: 0;"><b>____________________________________</b></p>
  <p style="margin: 0; padding: 0;"><b>' . htmlspecialchars($ficha['ch_sta']) . '</b></p>
  <p style="margin: 0; padding: 0;">Ch da Seção de Transporte e Apoio</p>
</td>

</tr>
</table>

<div class="section-title">MOVIMENTO</div>
<table class="table">
<tr>
<td style="float:top; font-size:10px;">MOTORISTA</B> EXECUTE AS INSPEÇÕES PREVISTAS NO VERSO E PREENCHA OS DADOS ABAIXO PARA O LIVRO REGISTRO DA VIATURA (CMT DA GD FISCALIZAR E COBRAR O CORRETO PREENCHIMENTO, VERIFICAR O ODÔMETRO NO PAINEL DA VIATUTAR AO SAIR E ENTRAR NO AQUARTELAMENTO)</td>
</tr>
</table>

<div class="section-title">MOVIMENTO - PREENCHIMENTO PELA GUARDA</div>
<table class="table">
<tr>
<td><b>Data Saída:</b> ' . htmlspecialchars($ficha['data_saida']) . '</td>
<td><b>Hora Saída:</b> ' . htmlspecialchars($ficha['hora_saida']) . '</td>
<td><b>Odômetro Saída:</b> ' . htmlspecialchars($ficha['odo_saida']) . '</td>
</tr>
<tr>
<td><b>Data Retorno:</b> ' . htmlspecialchars($ficha['data_retorno']) . '</td>
<td><b>Hora Retorno:</b> ' . htmlspecialchars($ficha['hora_retorno']) . '</td>
<td><b>Odômetro Retorno:</b> ' . htmlspecialchars($ficha['odo_retorno']) . '</td>
</tr>
<tr>
<td colspan="3"><b>Encerrada por:</b> ' . htmlspecialchars($ficha['encerrada_por']) . '</td>
</tr>
</table>

<div class="section-title">OBSERVAÇÕES PÓS-EMPREGO</div>
<table class="table">
<tr><td>' . nl2br(htmlspecialchars($ficha['observacoes_pos_emprego'])) . '</td></tr>
</table>

<div class="assinaturas">
    <div>
        <hr>
        <b>' . htmlspecialchars($cmt_ceem) . '</b><br>
        Cmt Cia E Eqp Mnt
    </div>
    <div>
        <hr>
        <b>' . htmlspecialchars($ficha['motorista']) . '</b><br>
        Motorista
    </div>
    <div>
        <hr>
        <b>' . htmlspecialchars($ficha['solicitante']) . '</b><br>
        Solicitante
    </div>
</div>

<div class="footer">
    Sistema de Gerenciamento - © 2025<br>
    Documento emitido automaticamente — Requer assinatura manual.
</div>

</body>
</html>
';

// ===============================
// Geração do PDF
// ===============================
$dompdf = new Dompdf();
$dompdf->set_option('isRemoteEnabled', true); // permite carregar imagens externas
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();
$dompdf->stream("ficha_viatura_{$id}.pdf", ["Attachment" => false]);
exit;

?>
