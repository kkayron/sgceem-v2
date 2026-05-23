<?php
require_once '../conexao/config.php';
require_once 'vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

$id = intval($_GET['id'] ?? 0);

if ($id <= 0) {
    die('OS inválida.');
}

function e($valor) {
    return htmlspecialchars((string)$valor, ENT_QUOTES, 'UTF-8');
}

function formatarValor($valor) {
    return 'R$ ' . number_format(floatval($valor), 2, ',', '.');
}

function formatarData($data) {
    if (empty($data) || $data === '0000-00-00') {
        return '-';
    }

    return date('d/m/Y', strtotime($data));
}

function imagemBase64($caminhoRelativo) {
    if (empty($caminhoRelativo)) {
        return '';
    }

    $caminhoRelativo = ltrim($caminhoRelativo, '/');

    $possiveisCaminhos = [
        __DIR__ . '/../' . $caminhoRelativo,
        dirname(__DIR__) . '/' . $caminhoRelativo,
        realpath(__DIR__ . '/../') . '/' . $caminhoRelativo,
    ];

    $caminhoFinal = '';

    foreach ($possiveisCaminhos as $caminho) {
        if ($caminho && file_exists($caminho) && is_file($caminho)) {
            $caminhoFinal = $caminho;
            break;
        }
    }

    if (!$caminhoFinal) {
        return '';
    }

    $mime = mime_content_type($caminhoFinal);

    if (!$mime || strpos($mime, 'image/') !== 0) {
        return '';
    }

    $dados = file_get_contents($caminhoFinal);

    if ($dados === false) {
        return '';
    }

    return 'data:' . $mime . ';base64,' . base64_encode($dados);
}

$stmt = $conexao->prepare("
    SELECT 
        os.*, 
        f.prefixo_sga, 
        f.chassi, 
        f.foto_capa,
        m.marca AS nome_marca,
        mo.nome_modelo AS nome_modelo
    FROM os_principal os 
    JOIN frota f ON os.id_frota = f.id 
    LEFT JOIN config_marcas m ON f.marca = m.id
    LEFT JOIN config_modelos mo ON f.modelo = mo.id
    WHERE os.id = ?
");

$stmt->bind_param("i", $id);
$stmt->execute();
$res = $stmt->get_result();

if (!$res || $res->num_rows == 0) {
    die('OS não encontrada.');
}

$os = $res->fetch_assoc();

$falhas = [];
$pessoal = [];
$servicos = [];
$materiais = [];
$fotos = [];
$mntProgramadas = [];

$stmtFalhas = $conexao->prepare("SELECT * FROM os_falhas WHERE id_osprincipal = ?");
$stmtFalhas->bind_param("i", $id);
$stmtFalhas->execute();
$resFalhas = $stmtFalhas->get_result();

while ($row = $resFalhas->fetch_assoc()) {
    $falhas[] = $row;
}

$stmtPessoal = $conexao->prepare("SELECT * FROM os_pessoal WHERE id_osprincipal = ?");
$stmtPessoal->bind_param("i", $id);
$stmtPessoal->execute();
$resPessoal = $stmtPessoal->get_result();

while ($row = $resPessoal->fetch_assoc()) {
    $pessoal[] = $row;
}

$stmtServicos = $conexao->prepare("SELECT * FROM os_rlzdmnt WHERE id_osprincipal = ?");
$stmtServicos->bind_param("i", $id);
$stmtServicos->execute();
$resServicos = $stmtServicos->get_result();

while ($row = $resServicos->fetch_assoc()) {
    $servicos[] = $row;
}

$stmtMateriais = $conexao->prepare("SELECT * FROM os_itens WHERE id_osprincipal = ?");
$stmtMateriais->bind_param("i", $id);
$stmtMateriais->execute();
$resMateriais = $stmtMateriais->get_result();

while ($row = $resMateriais->fetch_assoc()) {
    $materiais[] = $row;
}

$stmtFotos = $conexao->prepare("
    SELECT 
        id, 
        nome_arquivo, 
        caminho, 
        legenda, 
        data_upload
    FROM os_fotos
    WHERE id_osprincipal = ?
    ORDER BY id ASC
");

$stmtFotos->bind_param("i", $id);
$stmtFotos->execute();
$resFotos = $stmtFotos->get_result();

while ($row = $resFotos->fetch_assoc()) {
    $fotos[] = $row;
}

$stmtMnt = $conexao->prepare("
    SELECT 
        me.id,
        me.id_plano,
        me.odometro_horimetro_execucao,
        me.data_execucao,
        mp.descricao,
        mp.tipo_controle,
        mp.intervalo_valor,
        mp.intervalo_dias
    FROM mnt_execucoes me
    INNER JOIN mnt_planos mp ON mp.id = me.id_plano
    WHERE me.id_osprincipal = ?
    ORDER BY me.data_execucao ASC, me.id ASC
");

$stmtMnt->bind_param("i", $id);
$stmtMnt->execute();
$resMnt = $stmtMnt->get_result();

while ($row = $resMnt->fetch_assoc()) {
    $mntProgramadas[] = $row;
}

$dataGeracao = date('d/m/Y H:i');

$fotoViatura = '';

if (!empty($os['foto_capa'])) {
    $fotoViatura = imagemBase64('uploads/frotas/' . $os['foto_capa']);
}

$html = '
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<style>
@page {
    margin: 18px 22px;
}

body {
    font-family: DejaVu Sans, Arial, sans-serif;
    font-size: 10.5px;
    color: #1f2933;
    margin: 0;
    padding: 0;
    background: #fff;
}

.header-militar {
    border: 2px solid #1f3d2b;
    padding: 8px;
    margin-bottom: 10px;
    text-align: center;
    background: #f4f6f1;
}

.header-militar h3 {
    margin: 0;
    font-size: 15px;
    color: #1f3d2b;
    text-transform: uppercase;
    letter-spacing: .5px;
}

.header-militar p {
    margin: 2px 0;
    font-size: 10px;
    color: #374151;
}

.titulo-os {
    background: #1f3d2b;
    color: #fff;
    text-align: center;
    padding: 8px;
    font-size: 15px;
    font-weight: bold;
    text-transform: uppercase;
    letter-spacing: .8px;
    margin-bottom: 10px;
    border-radius: 3px;
}

.container {
    width: 100%;
}

.section {
    border: 1px solid #9ca3af;
    margin-bottom: 9px;
    page-break-inside: avoid;
}

.section h5 {
    margin: 0;
    padding: 6px 8px;
    background: #2f4f3a;
    color: #fff;
    font-size: 11.5px;
    text-transform: uppercase;
    letter-spacing: .4px;
    border-bottom: 1px solid #1f3d2b;
}

.section-body {
    padding: 8px;
}

.row {
    width: 100%;
}

.col {
    display: inline-block;
    width: 23.5%;
    vertical-align: top;
    margin-bottom: 6px;
}

.col-3 {
    display: inline-block;
    width: 31.5%;
    vertical-align: top;
    margin-bottom: 6px;
}

.campo-label {
    display: block;
    font-size: 8.8px;
    color: #4b5563;
    text-transform: uppercase;
    font-weight: bold;
    border-bottom: 1px solid #d1d5db;
    margin-bottom: 2px;
}

.campo-valor {
    font-size: 10.5px;
    color: #111827;
    font-weight: normal;
}

.border-box {
    border: 1px solid #c7c7c7;
    border-left: 4px solid #2f4f3a;
    padding: 6px;
    margin-bottom: 6px;
    background: #fafafa;
    page-break-inside: avoid;
}

.text-center {
    text-align: center;
}

.img-thumbnail {
    max-width: 260px;
    max-height: 170px;
    border: 1px solid #4b5563;
    padding: 3px;
    margin-bottom: 8px;
}

.foto-os-box {
    display: inline-block;
    width: 30%;
    margin: 1%;
    vertical-align: top;
    border: 1px solid #9ca3af;
    padding: 5px;
    background: #f9fafb;
    page-break-inside: avoid;
}

.foto-os-box img {
    width: 100%;
    max-height: 145px;
    border: 1px solid #6b7280;
}

.badge-success {
    background: #1f7a3a;
    color: #fff;
    padding: 3px 7px;
    font-size: 9px;
    font-weight: bold;
    text-transform: uppercase;
}

.footer {
    border-top: 1px solid #1f3d2b;
    margin-top: 18px;
    padding-top: 6px;
    font-size: 8.5px;
    color: #4b5563;
    text-align: center;
}

.assinaturas {
    margin-top: 35px;
    width: 100%;
}

.assinatura-box {
    display: inline-block;
    width: 45%;
    text-align: center;
    margin: 0 2%;
    font-size: 9.5px;
}

.linha-assinatura {
    border-top: 1px solid #111;
    margin-bottom: 4px;
}

p {
    margin: 2px 0;
}
</style>
</head>

<body>
<div class="container">

    <h4 class="text-center">Ordem de Serviço nº ' . e($os['id']) . '</h4>';

if ($fotoViatura) {
    $html .= '
    <div class="text-center">
        <img class="img-thumbnail" src="' . $fotoViatura . '" alt="Foto Viatura">
    </div>';
} else {
    $html .= '
    <div class="text-center">
        <p>Sem foto da viatura.</p>
    </div>';
}

$html .= '
    <div class="section">
        <h5>Dados da Viatura</h5>
        <div class="row">
            <div class="col"><strong>Prefixo:</strong> ' . e($os['prefixo_sga']) . '</div>
            <div class="col"><strong>Chassi:</strong> ' . e($os['chassi']) . '</div>
            <div class="col"><strong>Marca:</strong> ' . e($os['nome_marca']) . '</div>
            <div class="col"><strong>Modelo:</strong> ' . e($os['nome_modelo']) . '</div>
        </div>
    </div>

    <div class="section">
        <h5>Dados da OS</h5>
        <div class="row">
            <div class="col"><strong>Data Abertura:</strong> ' . formatarData($os['data_abertura']) . '</div>
            <div class="col"><strong>Solicitante:</strong> ' . e($os['solicitante']) . '</div>
            <div class="col"><strong>Status:</strong> ' . e($os['status']) . '</div>
            <div class="col"><strong>ODO/HOR:</strong> ' . e($os['odometro_horimetro']) . '</div>
            <div class="col"><strong>Tipo MNT:</strong> ' . e($os['tipo_mnt']) . '</div>
        </div>

        <p><strong>Falhas/Serviço Solicitado:</strong> ' . e($os['problema']) . '</p>
        <p><strong>Local da Manutenção:</strong> ' . e($os['local_os']) . '</p>
    </div>

    <div class="section">
        <h5>Materiais/Peças Utilizados</h5>';

if (!empty($materiais)) {
    foreach ($materiais as $m) {
        $qtd = floatval($m['quant_itens_utilizados']);
        $valorUnitario = floatval($m['valor_itens']);
        $total = !empty($m['valor_total_item']) ? floatval($m['valor_total_item']) : ($qtd * $valorUnitario);

        $html .= '
        <div class="border-box">
            <strong>Origem:</strong> ' . e($m['origem_item']) . '<br>
            <strong>Descrição:</strong> ' . e($m['itens_utilizados']) . '<br>
            <strong>Quantidade:</strong> ' . e($m['quant_itens_utilizados']) . '<br>
            <strong>Valor Unitário:</strong> ' . formatarValor($valorUnitario) . '<br>
            <strong>Total:</strong> ' . formatarValor($total) . '
        </div>';
    }
} else {
    $html .= '<p>Nenhum material/peça informado.</p>';
}

$html .= '
    </div>

    <div class="section">
        <h5>Falhas Identificadas Durante MNT</h5>';

if (!empty($falhas)) {
    foreach ($falhas as $f) {
        $html .= '
        <div class="border-box">
            <strong>Seção:</strong> ' . e($f['secao_falha']) . '<br>
            <strong>Falha:</strong> ' . e($f['falha_identificada']) . '<br>
            <strong>Identificada por:</strong> ' . e($f['militar_identificou']) . '
        </div>';
    }
} else {
    $html .= '<p>Nenhuma falha identificada informada.</p>';
}

$html .= '
    </div>

    <div class="section">
        <h5>Pessoal Utilizado</h5>';

if (!empty($pessoal)) {
    foreach ($pessoal as $p) {
        $html .= '
        <div class="border-box">
            <strong>' . e($p['postograd_militar']) . ' ' . e($p['nome_militar']) . '</strong><br>
            <strong>Função:</strong> ' . e($p['funcao_militar']) . '<br>
            <strong>Data:</strong> ' . formatarData($p['data_emprego']) . '<br>
            <strong>Serviço:</strong> ' . e($p['servico_executado']) . '
        </div>';
    }
} else {
    $html .= '<p>Nenhum pessoal informado.</p>';
}

$html .= '
    </div>

    <div class="section">
        <h5>Serviços Realizados</h5>';

if (!empty($servicos)) {
    foreach ($servicos as $s) {
        $qtd = floatval($s['qtd_servico']);
        $valorUnitario = floatval($s['valor_unt']);
        $total = $qtd * $valorUnitario;

        $html .= '
        <div class="border-box">
            <strong>Empresa:</strong> ' . e($s['empresa']) . '<br>
            <strong>Serviço:</strong> ' . e($s['rlzd_mnt']) . '<br>
            <strong>Quantidade:</strong> ' . e($s['qtd_servico']) . '<br>
            <strong>Valor Unitário:</strong> ' . formatarValor($valorUnitario) . '<br>
            <strong>Total:</strong> ' . formatarValor($total) . '
        </div>';
    }
} else {
    $html .= '<p>Nenhum serviço informado.</p>';
}

$html .= '
    </div>

    <div class="section">
        <h5>Manutenções Programadas Executadas</h5>';

if (!empty($mntProgramadas)) {
    foreach ($mntProgramadas as $mnt) {
        $html .= '
        <div class="border-box">
            <strong>' . e($mnt['descricao']) . '</strong>
            <span class="badge-success">Executada</span><br>
            <strong>Tipo de controle:</strong> ' . e($mnt['tipo_controle']) . '<br>
            <strong>Data execução:</strong> ' . formatarData($mnt['data_execucao']) . '<br>
            <strong>ODO/HOR execução:</strong> ' . e($mnt['odometro_horimetro_execucao']) . '<br>
            <strong>Intervalo valor:</strong> ' . e($mnt['intervalo_valor']) . '<br>
            <strong>Intervalo dias:</strong> ' . e($mnt['intervalo_dias']) . '
        </div>';
    }
} else {
    $html .= '<p>Nenhuma manutenção programada executada nesta OS.</p>';
}

$html .= '
    </div>

    <div class="section">
        <h5>Fotos da Ordem de Serviço</h5>';

if (!empty($fotos)) {
    $algumaFotoRenderizada = false;

    foreach ($fotos as $foto) {
        $fotoBase64 = imagemBase64($foto['caminho']);

        if (!$fotoBase64) {
            continue;
        }

        $algumaFotoRenderizada = true;

        $html .= '
        <div class="foto-os-box">
            <img src="' . $fotoBase64 . '" alt="Foto da OS">
            <p><strong>' . e($foto['nome_arquivo']) . '</strong></p>
            <p>' . e($foto['legenda']) . '</p>
            <p><small>' . e($foto['data_upload']) . '</small></p>
        </div>';
    }

    if (!$algumaFotoRenderizada) {
        $html .= '<p>As fotos estão cadastradas, mas os arquivos não foram encontrados no servidor.</p>';
    }
} else {
    $html .= '<p>Nenhuma foto enviada para esta OS.</p>';
}

$html .= '
    </div>';

$valorND30 = floatval($os['valornd30']);
$valorND39 = floatval($os['valornd39']);
$totalGasto = !empty($os['valorTOTAL']) ? floatval($os['valorTOTAL']) : ($valorND30 + $valorND39);

$html .= '
    <div class="section">
        <h5>Valores</h5>
        <div class="row">
            <div class="col-3"><strong>ND30 (Materiais):</strong> ' . formatarValor($valorND30) . '</div>
            <div class="col-3"><strong>ND39 (Serviços):</strong> ' . formatarValor($valorND39) . '</div>
            <div class="col-3"><strong>Total:</strong> ' . formatarValor($totalGasto) . '</div>
        </div>
    </div>

    <div class="footer">
        Gerado automaticamente em ' . $dataGeracao . ' | Sistema de Gestão da Cia E Eqp Mnt
    </div>

</div>
</body>
</html>';

$options = new Options();
$options->set('isRemoteEnabled', true);
$options->set('isHtml5ParserEnabled', true);
$options->set('isPhpEnabled', false);
$options->set('defaultFont', 'DejaVu Sans');

$dompdf = new Dompdf($options);
$dompdf->loadHtml($html, 'UTF-8');
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();
$dompdf->stream("OS_{$id}.pdf", ["Attachment" => false]);
exit;