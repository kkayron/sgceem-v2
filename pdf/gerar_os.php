<?php
require_once '../conexao/config.php';

// ================================
// VALIDAR ID
// ================================
$id = intval($_GET['id'] ?? 0);
if ($id <= 0) {
    die('ID inválido.');
}

// ================================
// BUSCAR OS PRINCIPAL
// ================================
$sqlOS = "SELECT * FROM os_principal WHERE id = $id";
$resultOS = $conexao->query($sqlOS);

if (!$resultOS || $resultOS->num_rows === 0) {
    die('Ordem de serviço não encontrada.');
}

$os = $resultOS->fetch_assoc();

// ================================
// BUSCAR BATALHÃO (abreviatura)
// ================================
$batalhaoId = intval($os['batalhao']);
$sqlBatalhao = "SELECT abreviatura FROM organizacoes_militares WHERE id = $batalhaoId LIMIT 1";
$resultBatalhao = $conexao->query($sqlBatalhao);
$nomebatalhaoabrev = ($resultBatalhao && $row = $resultBatalhao->fetch_assoc()) ? $row['abreviatura'] : '';

// ================================
// VARIÁVEIS
// ================================
$dataAbertura = !empty($os['data_abertura']) ? date('d/m/Y', strtotime($os['data_abertura'])) : '';
$dataEncerramento = !empty($os['data_encerramento']) ? date('d/m/Y', strtotime($os['data_encerramento'])) : '';

$prefixo     = $os['prefixo_sga'] ?? '';
$solicitante = $os['solicitante'] ?? '';
$problema    = $os['problema'] ?? '';
$odometro    = $os['odometro_horimetro'] ?? '';
$tipomnt     = $os['tipo_mnt'] ?? '';
$localdaos   = $os['local_os'] ?? '';

// ================================
// HTML DO DOCUMENTO
// ================================
$dados = "
<!DOCTYPE html>
<html lang='pt-br'>
<head>
<meta charset='UTF-8'>
<title>Ordem de Serviço Nº $id</title>
<style>
    body {
        font-family: 'Cambria', 'Times New Roman', serif;
        font-size: 12px;
        color: #000;
        margin: 20px;
    }
    h5 {
        margin: 8px 0;
        text-align: center;
        font-size: 13px;
        background-color: #f2f2f2;
        padding: 4px;
        border-radius: 4px;
    }
    table {
        border: 1px solid #000;
        border-collapse: collapse;
        width: 100%;
        margin-bottom: 10px;
        font-size: 12px;
    }
    th, td {
        border: 1px solid #000;
        padding: 4px;
        text-align: center;
    }
    .header-table td {
        font-weight: bold;
        background-color: #f8f8f8;
    }
    .fontemaior {
        font-size: 16px;
        font-weight: bold;
    }
    .linha {
        border-bottom: 1px solid #000;
        height: 18px;
    }
    .assinatura {
        text-align: center;
        padding-top: 10px;
        font-size: 11px;
    }
</style>
</head>
<body>

<!-- CABEÇALHO -->
<table class='header-table'>
<tr>
<td class='fontemaior'>$nomebatalhaoabrev</td>
<td>OS Nº <b>$id</b></td>
<td>Data de Abertura: <b>$dataAbertura</b></td>
<td>Solicitante: <b>$solicitante</b></td>
<td>Autorização do Serviço</td>
</tr>
<tr>
<td colspan='4'>$localdaos</td>
<td>
<div class='assinatura'>______________________<br>Chefe da Sec Ctrl Mnt</div>
</td>
</tr>
</table>

<!-- DADOS PRINCIPAIS -->
<table>
<tr>
<th>Prefixo EQP/VTR</th>
<th>Odom/Hor</th>
<th>Tipo Mnt</th>
<th>Falhas Apresentadas / Serviço Solicitado</th>
</tr>
<tr>
<td>$prefixo</td>
<td></td>
<td>$tipomnt</td>
<td style='text-align:left;'>$problema</td>
</tr>
</table>

<!-- FALHAS IDENTIFICADAS -->
<h5>FALHAS IDENTIFICADAS DURANTE A MANUTENÇÃO</h5>
<table>
<tr>
<th>Seção</th>
<th>Falha Identificada</th>
<th>Militar que Identificou</th>
</tr>";
for ($i=0; $i<6; $i++) {
    $dados .= "<tr><td class='linha'></td><td class='linha'></td><td class='linha'></td></tr>";
}
$dados .= "
</table>

<!-- PESSOAL UTILIZADO -->
<h5>PESSOAL UTILIZADO NA MANUTENÇÃO / SERVIÇO EXECUTADO NO BATALHÃO</h5>
<table>
<tr>
<th>Grad</th>
<th>Nome de Guerra</th>
<th>Função</th>
<th>Data Emprego</th>
<th>Serviço Executado</th>
</tr>";
for ($i=0; $i<4; $i++) {
    $dados .= "<tr><td class='linha'></td><td class='linha'></td><td class='linha'></td><td class='linha'></td><td class='linha'></td></tr>";
}
$dados .= "</table>

<!-- SERVIÇOS REALIZADOS -->
<h5>SERVIÇOS REALIZADOS NA MANUTENÇÃO</h5>
<table>
<tr>
<th>Data</th>
<th>Serviço Executado</th>
</tr>";
for ($i=0; $i<4; $i++) {
    $dados .= "<tr><td class='linha'></td><td class='linha'></td></tr>";
}
$dados .= "</table>

<!-- MATERIAIS UTILIZADOS -->
<h5>MATERIAL / PEÇAS UTILIZADOS NA MANUTENÇÃO</h5>
<table>
<tr>
<th>Origem (Almox / Empresa)</th>
<th>Descrição</th>
<th>Qtd</th>
<th>Valor Unitário</th>
<th>Valor Total</th>
</tr>";
for ($i=0; $i<7; $i++) {
    $dados .= "<tr><td class='linha'></td><td class='linha'></td><td class='linha'></td><td class='linha'></td><td class='linha'></td></tr>";
}
$dados .= "</table>

<!-- CHECKLIST -->
<h5>CHECKLIST DE VERIFICAÇÃO</h5>
<div style='margin-bottom:5px; font-size:12px;'>
<b>Legenda:</b> A - Antes | D - Durante | P - Pós Manutenção
</div>
<table>
<tr><th>Item</th><th>A</th><th>D</th><th>P</th></tr>";

$itens = [
    'Visão geral da viatura','Vazamentos','Pneus, lagartas e suspensão','Combustível',
    'Água','Níveis de óleo','Instrumentos do painel','Motor','Luzes e refletores',
    'Equipamentos de segurança e visão','Ligações para reboque','Portas e escotilhas',
    'Documentação','Sistema hidráulico','Outros equipamentos','Particularidades dos anfíbios',
    'Embreagem','Freios','Direção','Ruídos anormais','Cúpula do comandante','Baterias','Filtro de ar'
];
foreach ($itens as $item) {
    $dados .= "<tr><td>$item</td><td class='linha'></td><td class='linha'></td><td class='linha'></td></tr>";
}
$dados .= "</table>

<!-- OBSERVAÇÕES -->
<h5>OBSERVAÇÕES</h5>
<table>";
for ($i=0; $i<4; $i++) {
    $dados .= "<tr><td class='linha'></td></tr>";
}
$dados .= "</table>

<!-- ENCERRAMENTO -->
<h5>ENCERRAMENTO DA ORDEM DE SERVIÇO</h5>
<table>
<tr>
<th>Viatura Disponibilizada</th>
<th>Data/Hora Encerramento</th>
<th>O.S. Encerrada por</th>
<th>O.S. Apropriada por</th>
</tr>
<tr>
<td>( )SIM ( )NÃO ( )Com Restrição</td>
<td>$dataEncerramento</td>
<td class='linha'></td>
<td class='linha'></td>
</tr>
</table>

</body>
</html>
";

// ================================
// GERAR PDF
// ================================
require 'vendor/autoload.php';
use Dompdf\Dompdf;

$dompdf = new Dompdf(['enable_remote' => true]);
$dompdf->loadHtml($dados);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();
$dompdf->stream("ordem_de_servico_$id.pdf", ["Attachment" => false]);
