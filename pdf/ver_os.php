<?php
require 'vendor/autoload.php';
include_once '../conexao/config.php';
use Dompdf\Dompdf;

// ============================
// Verifica se o ID foi passado
// ============================
$id = $_GET['id'] ?? null;
if (!$id) {
    die("ID da OS não informado.");
}

// ============================
// Buscar dados principais da OS + nome do batalhão
// ============================
$sql = "SELECT p.*, f.prefixo_sga, om.nome AS nome_batalhao
        FROM os_principal p
        JOIN frota f ON p.id_frota = f.id
        LEFT JOIN organizacoes_militares om ON om.id = p.batalhao
        WHERE p.id = $id";

$result = $conexao->query($sql);
if (!$result || $result->num_rows == 0) {
    die("OS não encontrada.");
}
$os = $result->fetch_assoc();

// ============================
// Formatando datas
// ============================
$dataAbertura = $os['data_abertura'] ? date('d/m/Y', strtotime($os['data_abertura'])) : '—';
$dataEncerramento = $os['data_encerramento'] ? date('d/m/Y', strtotime($os['data_encerramento'])) : '—';

// ============================
// Falhas identificadas
// ============================
$falhasHTML = '';
$res = $conexao->query("SELECT * FROM os_falhas WHERE id_osprincipal = $id");
while ($row = $res->fetch_assoc()) {
    $falhasHTML .= "<tr>
        <td>{$row['secao_falha']}</td>
        <td>{$row['falha_identificada']}</td>
        <td>{$row['militar_identificou']}</td>
    </tr>";
}
if ($falhasHTML == '') {
    $falhasHTML = "<tr><td colspan='3' style='text-align:center;'>Nenhuma falha registrada.</td></tr>";
}

// ============================
// Pessoal utilizado
// ============================
$pessoalHTML = '';
$res = $conexao->query("SELECT * FROM os_pessoal WHERE id_osprincipal = $id");
while ($row = $res->fetch_assoc()) {
    $dataEmprego = $row['data_emprego'] ? date('d/m/Y', strtotime($row['data_emprego'])) : '—';
    $pessoalHTML .= "<tr>
        <td>{$row['postograd_militar']}</td>
        <td>{$row['nome_militar']}</td>
        <td>{$row['funcao_militar']}</td>
        <td>$dataEmprego</td>
        <td>{$row['servico_executado']}</td>
    </tr>";
}
if ($pessoalHTML == '') {
    $pessoalHTML = "<tr><td colspan='5' style='text-align:center;'>Nenhum registro de pessoal.</td></tr>";
}

// ============================
// Serviços realizados
// ============================
$servicosHTML = '';
$res = $conexao->query("SELECT * FROM os_rlzdmnt WHERE id_osprincipal = $id");
while ($row = $res->fetch_assoc()) {
    $total = number_format($row['valor_unt'] * $row['qtd_servico'], 2, ',', '.');
    $valorUnt = number_format($row['valor_unt'], 2, ',', '.');
    $servicosHTML .= "<tr>
        <td>{$row['empresa']}</td>
        <td>{$row['rlzd_mnt']}</td>
        <td>{$row['qtd_servico']}</td>
        <td>R$ $valorUnt</td>
        <td>R$ $total</td>
    </tr>";
}
if ($servicosHTML == '') {
    $servicosHTML = "<tr><td colspan='5' style='text-align:center;'>Nenhum serviço registrado.</td></tr>";
}

// ============================
// Materiais utilizados
// ============================
$materiaisHTML = '';
$res = $conexao->query("SELECT * FROM os_itens WHERE id_osprincipal = $id");
while ($row = $res->fetch_assoc()) {
    $total = number_format($row['valor_itens'] * $row['quant_itens_utilizados'], 2, ',', '.');
    $valorUnt = number_format($row['valor_itens'], 2, ',', '.');
    $materiaisHTML .= "<tr>
        <td>{$row['origem_item']}</td>
        <td>{$row['itens_utilizados']}</td>
        <td>{$row['quant_itens_utilizados']}</td>
        <td>R$ $valorUnt</td>
        <td>R$ $total</td>
    </tr>";
}
if ($materiaisHTML == '') {
    $materiaisHTML = "<tr><td colspan='5' style='text-align:center;'>Nenhum material registrado.</td></tr>";
}

// ============================
// HTML do relatório moderno
// ============================
$html = "
<style>
body {
    font-family: 'Segoe UI', Arial, sans-serif;
    font-size: 11.5px;
    color: #333;
    margin: 25px;
}
h1 {
    text-align: center;
    font-size: 20px;
    background: #0d6efd;
    color: white;
    padding: 8px;
    border-radius: 6px;
}
h2 {
    text-align: center;
    font-size: 14px;
    color: #555;
    margin-top: -5px;
    margin-bottom: 20px;
}
h3 {
    margin-top: 25px;
    margin-bottom: 8px;
    color: #0d6efd;
    border-bottom: 2px solid #0d6efd;
    padding-bottom: 3px;
}
table {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 15px;
}
th, td {
    border: 1px solid #ccc;
    padding: 6px;
    text-align: left;
}
th {
    background: #f2f2f2;
    color: #000;
}
tr:nth-child(even) {
    background: #fafafa;
}
.footer {
    margin-top: 25px;
    font-size: 10px;
    text-align: center;
    color: #555;
    border-top: 1px solid #ccc;
    padding-top: 8px;
}
.assinaturas {
    margin-top: 40px;
    text-align: center;
}
.assinaturas table {
    width: 100%;
    border: none;
}
.assinaturas td {
    width: 33%;
    vertical-align: top;
    text-align: center;
    border: none;
    padding-top: 40px;
}
.assinaturas .linha {
    border-top: 1px solid #333;
    display: inline-block;
    width: 80%;
    margin-bottom: 3px;
}
</style>

<h1>{$os['nome_batalhao']}</h1>
<h2>Relatório da Ordem de Serviço Nº {$os['id']}</h2>

<h3>Dados Principais</h3>
<table>
<tr>
    <th>Data Abertura</th>
    <th>Data Encerramento</th>
    <th>Prefixo</th>
    <th>Solicitante</th>
    <th>Status</th>
</tr>
<tr>
    <td>$dataAbertura</td>
    <td>$dataEncerramento</td>
    <td>{$os['prefixo_sga']}</td>
    <td>{$os['solicitante']}</td>
    <td>{$os['status']}</td>
</tr>
</table>

<h3>Informações Gerais</h3>
<table>
<tr>
    <th>Tipo Manutenção</th>
    <th>Falhas/Serviço Solicitado</th>
    <th>Odômetro/Horímetro</th>
    <th>Seção Responsável</th>
</tr>
<tr>
    <td>{$os['tipo_mnt']}</td>
    <td>{$os['problema']}</td>
    <td>{$os['odometro_horimetro']}</td>
    <td>{$os['secao_rspns']}</td>
</tr>
</table>

<h3>Falhas Identificadas</h3>
<table>
<tr><th>Seção</th><th>Falha</th><th>Militar</th></tr>
$falhasHTML
</table>

<h3>Pessoal Utilizado</h3>
<table>
<tr><th>Graduação</th><th>Nome</th><th>Função</th><th>Data</th><th>Serviço</th></tr>
$pessoalHTML
</table>

<h3>Serviços Realizados (Terceirizados)</h3>
<table>
<tr><th>Empresa</th><th>Serviço</th><th>Qtd</th><th>Valor Unitário</th><th>Valor Total</th></tr>
$servicosHTML
</table>

<h3>Materiais/Peças Utilizadas</h3>
<table>
<tr><th>Origem</th><th>Descrição</th><th>Qtd</th><th>Valor Unitário</th><th>Valor Total</th></tr>
$materiaisHTML
</table>

<h3>Encerramento</h3>
<table>
<tr><th>Data Encerramento</th><th>Chefe Controle</th></tr>
<tr><td>$dataEncerramento</td><td>{$os['ch_controle']}</td></tr>
</table>

<div class='assinaturas'>
    <table style='width: 100%; border: none; text-align: center; margin-top: 50px;'>
        <tr>
            <!-- Cmt CEEM à esquerda -->
            <td style='width: 33%; text-align: left; padding-left: 30px;'>
                <div class='linha' style='width: 80%; border-top: 1px solid #000; margin-bottom: 3px;'></div>
                <div style='font-weight: bold;'>{$os['cmt_ceem']}</div>
                <div style='font-size: 11px;'>Cmt CEEM</div>
            </td>

            <!-- Chefe do Controle no meio -->
            <td style='width: 33%; text-align: center;'>
                <div class='linha' style='width: 80%; border-top: 1px solid #000; margin-bottom: 3px;'></div>
                <div style='font-weight: bold;'>{$os['ch_controle']}</div>
                <div style='font-size: 11px;'>Chefe do Controle</div>
            </td>

            <!-- Chefe do Suprimento à direita -->
            <td style='width: 33%; text-align: right; padding-right: 30px;'>
                <div class='linha' style='width: 80%; border-top: 1px solid #000; margin-bottom: 3px;'></div>
                <div style='font-weight: bold;'>{$os['ch_suprimento']}</div>
                <div style='font-size: 11px;'>Chefe do Suprimento</div>
            </td>
        </tr>
    </table>
</div>


<div class='footer'>
Gerado automaticamente em ".date('d/m/Y H:i')." — Sistema de Gestão da Cia E Eqp Mnt
</div>
";

// ============================
// Gerar PDF
// ============================
$dompdf = new Dompdf(['enable_remote' => true]);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();
$dompdf->stream("ordem_servico_nmr_{$os['id']}.pdf", ['Attachment' => false]);
