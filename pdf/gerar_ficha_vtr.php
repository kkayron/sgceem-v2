<?php
require_once 'vendor/autoload.php';
require_once '../conexao/config.php';

use Dompdf\Dompdf;
use Dompdf\Options;

// Otimização de performance para servidores
ini_set('memory_limit', '256M');

// ===============================
// Validação de ID
// ===============================
$id = intval($_GET['id'] ?? 0);
if ($id <= 0) {
    die('ID inválido.');
}

// ===============================
// Conversão da Imagem para Base64
// ===============================
$path = 'imagens/brasao.png'; // Certifique-se que o caminho está correto em relação a este arquivo PHP
$base64 = '';

if (file_exists($path)) {
    $type = pathinfo($path, PATHINFO_EXTENSION);
    $data = file_get_contents($path);
    $base64 = 'data:image/' . $type . ';base64,' . base64_encode($data);
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
// HTML DO PDF
// ===============================
$html = '
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<style>
    body { font-family: "Times New Roman", Times, serif; font-size: 11px; margin: 20px; }
    h3 { text-align: center; margin: 2px 0; text-transform: uppercase; }
    .table { width: 100%; border-collapse: collapse; margin-top: 8px; }
    .table td { border: 1px solid #000; padding: 4px 6px; vertical-align: top; }
    .table2 { width: 100%; border-collapse: collapse; margin-top: 1px; }
    .table2 td { border: 1px solid #000; padding: 1px 1px; text-align: center; }
    .section-title { font-weight: bold; margin-top: 10px; text-transform: uppercase; background: #f3f3f3; padding: 4px; border: 1px solid #000; }
    .img-container { text-align: center; margin-bottom: 5px; }
</style>
</head>
<body>
<div class="img-container">
    ' . ($base64 ? '<img src="' . $base64 . '" width="50" height="50">' : '[Brasão]') . '
</div>
<h3>MINISTÉRIO DA DEFESA</h3>
<h3>EXÉRCITO BRASILEIRO</h3>
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



<div class="section-title">AUTORIZAÇÕES</div>
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
<table class="table">
<tr>
<td style="text-align: center;">
  <p style="margin: 0; padding: 0;"><b>A viatura/equipamento está em condições de ser utilizada no serviço e itinerário relacionados acima.</b></p>
  <BR>
  <p style="margin: 0; padding: 0;"><b>____________________________________</b></p>
  <p style="margin: 0; padding: 0;"><b>' . htmlspecialchars($ficha['ch_sta']) . '</b></p>
  <p style="margin: 0; padding: 0;">CH DA STA</p>
</td>

</tr>
</table>



<div class="section-title">MOVIMENTO - PREENCHIMENTO PELA GUARDA</div>
<table class="table">
<tr>
<td style="float:top; font-size:10px;">MOTORISTA</B> EXECUTE AS INSPEÇÕES PREVISTAS NO VERSO E PREENCHA OS DADOS ABAIXO PARA O LIVRO REGISTRO DA VIATURA (CMT DA GD FISCALIZAR E COBRAR O CORRETO PREENCHIMENTO, VERIFICAR O ODÔMETRO NO PAINEL DA VIATUTAR AO SAIR E ENTRAR NO AQUARTELAMENTO)</td>
</tr>
</table>

<table class="table">
<tr>
<td><b>Data Saída:</b> </td>
<td><b>Hora Saída:</b> </td>
<td><b>Odômetro Saída:</b> </td>
</tr>
<tr>
<td><b>Data Retorno:</b> </td>
<td><b>Hora Retorno:</b> </td>
<td><b>Odômetro Retorno:</b> </td>
</tr>
<tr>
<td colspan="3" style="font-size:10px; text-align: center;">
<b>Durante a movimentação da viatura, o motorista permanecerá de posse desta ficha. Após a conclusão do trabalho, a ficha deverá ser entregue na STA, juntamente com a chave da viatura/equipamento.</b>
</td>
</tr>
</table>

<!-- Aqui é o talão da primeira página -->

<hr size="1" style="border:1px dashed green;">

<div class="section-title">TALÃO DA GUARDA</div>
<table class="table" style="font-size: 10px;">
<tr>
<td>
<p style="margin: 0; padding: 0;"><b>DATA DE ABERTURA:</b> ' . date('d/m/Y', strtotime($ficha['data_abertura'])) . '</p>
<p style="margin: 0; padding: 0;"><b>NÚMERO DA FICHA:</b>' . $ficha['id'] . '</p>
<p style="margin: 0; padding: 0;"><b>SU:</b> ' . htmlspecialchars($ficha['subunidade']) . '</p>
<p style="margin: 0; padding: 0;"><b>DESTINO:</b> ' . htmlspecialchars($ficha['destino']) . '</p>
</td>
<td>
<p style="margin: 0; padding: 0;"><b>PREFIXO:</b> ' . htmlspecialchars($ficha['prefixo_sga']) . '</p>
<p style="margin: 0; padding: 0;"><b>NATUREZA DO SV:</b> ' . htmlspecialchars($ficha['natureza']) . '</p>
<p style="margin: 0; padding: 0;"><b>MOTORISTA:</b> ' . htmlspecialchars($ficha['motorista']) . '</p>
<p style="margin: 0; padding: 0;"><b>CHEFE DA MISSÃO:</b> ' . htmlspecialchars($ficha['chefe_apresentar']) . '</p>
</td>
<td>
<p style="margin: 0; padding: 0; text-align: center;"><b>AUTORIZADO</b></p>
<br><br>
<p style="margin: 0; padding: 0; text-align: center;">______________</p>
<p style="margin: 0; padding: 0; text-align: center;">FISC ADM</p>

</td>
</tr>
<tr>
<td colspan="3" style="text-align:center;">O talão após a linha pontilhada deverá permanecer na guarda até a passagem de serviço, ocasião em que deverá ser entregue ao Oficial de Dia para ser juntado ao Livro de Serviço.</td>
</tr>
</table>

<!-- Aqui pra baixo é a segunda página -->

<div style="page-break-before: always;"></div>

<table class="table2">
  <tr>
    <td style="width:20%;">' . ($base64 ? '<img src="' . $base64 . '" width="50" height="50">' : '') . '</td>
    <td style="width:60%; font-size:18px;"><b>MANUTENÇÃO PREVENTIVA DE 1° ESCALÃO</b></td>
    <td style="width:20%;"><br><br>________________<br><b>Motorista</b></td>
  </tr>
</table>

<hr style="margin-top:20px;">

<div text-align:center; font-size:15px;"><b>LEGENDA</b></div>
<br>
<table class="table2" border-collapse:collapse; font-size:13px; text-align:center;">
  <tr>
    <td style="width:50%;">A - ANTES DA PARTIDA</td>
    <td style="width:50%;">D - DURANTE O MOVIMENTO</td>
  </tr>
  <tr>
    <td>P - NOS ALTOS E PÓS-OPERAÇÃO</td>
    <td>H/Q - APÓS DETERMINADO NÚMERO</td>
  </tr>
</table>

<br>

<table class="table2" border-collapse:collapse; border:1px solid; text-align:center;">
  <tr style="font-size:15px; font-weight:bold;">
    <th style="width:58%; border:1px solid;">ITEM</th>
    <th style="width:10%; border:1px solid;">A</th>
    <th style="width:10%; border:1px solid;">D</th>
    <th style="width:10%; border:1px solid;">P</th>
    <th style="width:10%; border:1px solid;">H/Q</th>
  </tr>

  <!-- Itens da lista -->
  <tr><td style="border:1px solid;">Visão geral da viatura</td><td style="border:1px solid;"></td><td style="border:1px solid;"></td><td style="border:1px solid;"></td><td style="border:1px solid;"></td></tr>
  <tr><td style="border:1px solid;">Vazamentos</td><td style="border:1px solid;"></td><td style="border:1px solid;"></td><td style="border:1px solid;"></td><td style="border:1px solid;"></td></tr>
  <tr><td style="border:1px solid;">Pneus, lagartas e suspensão</td><td style="border:1px solid;"></td><td style="border:1px solid;"></td><td style="border:1px solid;"></td><td style="border:1px solid;"></td></tr>
  <tr><td style="border:1px solid;">Combustível</td><td style="border:1px solid;"></td><td style="border:1px solid;"></td><td style="border:1px solid;"></td><td style="border:1px solid;"></td></tr>
  <tr><td style="border:1px solid;">Água</td><td style="border:1px solid;"></td><td style="border:1px solid;"></td><td style="border:1px solid;"></td><td style="border:1px solid;"></td></tr>
  <tr><td style="border:1px solid;">Níveis de óleo</td><td style="border:1px solid;"></td><td style="border:1px solid;"></td><td style="border:1px solid;"></td><td style="border:1px solid;"></td></tr>
  <tr><td style="border:1px solid;">Instrumentos do Painel</td><td style="border:1px solid;"></td><td style="border:1px solid;"></td><td style="border:1px solid;"></td><td style="border:1px solid;"></td></tr>
  <tr><td style="border:1px solid;">Motor</td><td style="border:1px solid;"></td><td style="border:1px solid;"></td><td style="border:1px solid;"></td><td style="border:1px solid;"></td></tr>
  <tr><td style="border:1px solid;">Luzes e refletores</td><td style="border:1px solid;"></td><td style="border:1px solid;"></td><td style="border:1px solid;"></td><td style="border:1px solid;"></td></tr>
  <tr><td style="border:1px solid;">Equipamentos de segurança e visão</td><td style="border:1px solid;"></td><td style="border:1px solid;"></td><td style="border:1px solid;"></td><td style="border:1px solid;"></td></tr>
  <tr><td style="border:1px solid;">Ligações para reboque</td><td style="border:1px solid;"></td><td style="border:1px solid;"></td><td style="border:1px solid;"></td><td style="border:1px solid;"></td></tr>
  <tr><td style="border:1px solid;">Portas e escotilhas</td><td style="border:1px solid;"></td><td style="border:1px solid;"></td><td style="border:1px solid;"></td><td style="border:1px solid;"></td></tr>
  <tr><td style="border:1px solid;">Documentação</td><td style="border:1px solid;"></td><td style="border:1px solid;"></td><td style="border:1px solid;"></td><td style="border:1px solid;"></td></tr>
  <tr><td style="border:1px solid;">Sistema hidráulico</td><td style="border:1px solid;"></td><td style="border:1px solid;"></td><td style="border:1px solid;"></td><td style="border:1px solid;"></td></tr>
  <tr><td style="border:1px solid;">Outros equipamentos</td><td style="border:1px solid;"></td><td style="border:1px solid;"></td><td style="border:1px solid;"></td><td style="border:1px solid;"></td></tr>
  <tr><td style="border:1px solid;">Particularidades dos anfíbios</td><td style="border:1px solid;"></td><td style="border:1px solid;"></td><td style="border:1px solid;"></td><td style="border:1px solid;"></td></tr>
  <tr><td style="border:1px solid;">Embreagem</td><td style="border:1px solid;"></td><td style="border:1px solid;"></td><td style="border:1px solid;"></td><td style="border:1px solid;"></td></tr>
  <tr><td style="border:1px solid;">Freios</td><td style="border:1px solid;"></td><td style="border:1px solid;"></td><td style="border:1px solid;"></td><td style="border:1px solid;"></td></tr>
  <tr><td style="border:1px solid;">Direção</td><td style="border:1px solid;"></td><td style="border:1px solid;"></td><td style="border:1px solid;"></td><td style="border:1px solid;"></td></tr>
  <tr><td style="border:1px solid;">Ruídos anormais</td><td style="border:1px solid;"></td><td style="border:1px solid;"></td><td style="border:1px solid;"></td><td style="border:1px solid;"></td></tr>
  <tr><td style="border:1px solid;">Cúpula do comandante</td><td style="border:1px solid;"></td><td style="border:1px solid;"></td><td style="border:1px solid;"></td><td style="border:1px solid;"></td></tr>
  <tr><td style="border:1px solid;">Baterias</td><td style="border:1px solid;"></td><td style="border:1px solid;"></td><td style="border:1px solid;"></td><td style="border:1px solid;"></td></tr>
  <tr><td style="border:1px solid;">Filtro de ar</td><td style="border:1px solid;"></td><td style="border:1px solid;"></td><td style="border:1px solid;"></td><td style="border:1px solid;"></td></tr>
  <tr><td style="border:1px solid;">Filtro de combustível</td><td style="border:1px solid;"></td><td style="border:1px solid;"></td><td style="border:1px solid;"></td><td style="border:1px solid;"></td></tr>
  <tr><td style="border:1px solid;">Respiradores</td><td style="border:1px solid;"></td><td style="border:1px solid;"></td><td style="border:1px solid;"></td><td style="border:1px solid;"></td></tr>
  <tr><td style="border:1px solid;">Radiador de óleo</td><td style="border:1px solid;"></td><td style="border:1px solid;"></td><td style="border:1px solid;"></td><td style="border:1px solid;"></td></tr>
  <tr><td style="border:1px solid;">Ferramentas e acessórios</td><td style="border:1px solid;"></td><td style="border:1px solid;"></td><td style="border:1px solid;"></td><td style="border:1px solid;"></td></tr>
  <tr><td style="border:1px solid;">Conjunto de aquecimento</td><td style="border:1px solid;"></td><td style="border:1px solid;"></td><td style="border:1px solid;"></td><td style="border:1px solid;"></td></tr>
  <tr><td style="border:1px solid;">Gerador auxiliar</td><td style="border:1px solid;"></td><td style="border:1px solid;"></td><td style="border:1px solid;"></td><td style="border:1px solid;"></td></tr>
  <tr><td style="border:1px solid;">Assentos</td><td style="border:1px solid;"></td><td style="border:1px solid;"></td><td style="border:1px solid;"></td><td style="border:1px solid;"></td></tr>
  <tr><td style="border:1px solid;">Reapertos</td><td style="border:1px solid;"></td><td style="border:1px solid;"></td><td style="border:1px solid;"></td><td style="border:1px solid;"></td></tr>
  <tr><td style="border:1px solid;">Exaustores</td><td style="border:1px solid;"></td><td style="border:1px solid;"></td><td style="border:1px solid;"></td><td style="border:1px solid;"></td></tr>
  <tr><td style="border:1px solid; text-align: left;">Outra alteração = </td><td style="border:1px solid;"></td><td style="border:1px solid;"></td><td style="border:1px solid;"></td><td style="border:1px solid;"></td></tr>
</table>

<!-- Aqui é o talão da segunda página, página do verso. -->

<hr size="1" style="border:1px dashed green;">
<table  class="table">
<tr>
<td><b>Data Saída:</b> </td>
<td><b>Hora Saída:</b> </td>
<td><b>Odômetro Saída:</b> </td>
</tr>
<tr>
<td><b>Data Retorno:</b> </td>
<td><b>Hora Retorno:</b> </td>
<td><b>Odômetro Retorno:</b> </td>
</tr>
<tr>
<td colspan="3">O talão deverá ser preenchido corretamente pelo Cmt da Gda/Cb da Gda e motorista.</td>
</tr>
</table>
<table  class="table">
        <tr>
        
        <td style="text-align: center;">
        <p><br></p>
        <p style="margin: 0; padding: 0;">_____________________</p>
       <p style="margin: 0; padding: 0;"> <b>' . htmlspecialchars($ficha['motorista']) . '</b></p>
       <p style="margin: 0; padding: 0;"> Motorista</p>
    </td>
    <td style="text-align: center;">    
        <p><br></p>
        <p style="margin: 0; padding: 0;">_____________________</p>    
        <p style="margin: 0; padding: 0;"> <b>' . htmlspecialchars($ficha['chefe_apresentar']) . '</b></p>
        <p style="margin: 0; padding: 0;"> Chefe de missão</p>
    </td>
</tr>
</table>

</body>
</html>
';

// ===============================
// Configuração e Geração
// ===============================
$options = new Options();
$options->set('isHtml5ParserEnabled', true);
$options->set('isRemoteEnabled', false); // Desativado pois usamos Base64

$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

$dompdf->stream("ficha_vtr_{$id}.pdf", ["Attachment" => false]);
exit;