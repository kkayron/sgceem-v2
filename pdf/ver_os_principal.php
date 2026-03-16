<?php
require_once '../conexao/config.php';
require_once 'vendor/autoload.php';
use Dompdf\Dompdf;

// Receber ID da OS
$id = intval($_GET['id'] ?? 0);
if ($id <= 0) {
    die('OS inválida.');
}

// Buscar OS e dados da frota com nomes de marca e modelo
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

// Função para formatar valor
function formatarValor($valor) {
    return 'R$ ' . number_format(floatval($valor), 2, ',', '.');
}

// Buscar dados relacionados
$falhas = []; $pessoal = []; $servicos = []; $materiais = [];

$result = $conexao->query("SELECT * FROM os_falhas WHERE id_osprincipal = $id");
while ($row = $result->fetch_assoc()) { $falhas[] = $row; }

$result = $conexao->query("SELECT * FROM os_pessoal WHERE id_osprincipal = $id");
while ($row = $result->fetch_assoc()) { $pessoal[] = $row; }

$result = $conexao->query("SELECT * FROM os_rlzdmnt WHERE id_osprincipal = $id");
while ($row = $result->fetch_assoc()) { $servicos[] = $row; }

$result = $conexao->query("SELECT * FROM os_itens WHERE id_osprincipal = $id");
while ($row = $result->fetch_assoc()) { $materiais[] = $row; }

// Data de geração
$dataGeracao = date('d/m/Y H:i');

// Criar HTML do PDF
$html = '
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<style>
body { font-family: "Helvetica Neue", Helvetica, Arial, sans-serif; font-size: 12px; color: #333; line-height: 1.4; margin:0; padding:0; background:#f9f9f9; }
h4,h5 { margin:0 0 10px 0; color:#222; }
p { margin:2px 0; }
.container { width: 95%; margin: 10px auto; padding:10px; background:#fff; border-radius:8px; box-shadow:0 4px 12px rgba(0,0,0,0.05); }
.section { border: 1px solid #e0e0e0; padding:10px; margin-bottom:10px; border-radius:6px; }
.section h5 { color: #0d6efd; background: #f1f5f9; padding:6px; border-radius:4px; font-size:14px; }
.border-box { border: 1px solid #ddd; padding:6px; margin-bottom:5px; border-radius:4px; background:#fafafa; }
.text-center { text-align: center; }
.img-thumbnail { max-width: 280px; max-height: 180px; object-fit: cover; border-radius:6px; border:1px solid #ddd; margin-bottom:10px; }
.row { display:flex; flex-wrap:wrap; margin:0 -5px; }
.col { flex:1; min-width:150px; padding:0 5px; margin-bottom:5px; }
.footer { margin-top:20px; font-size:10px; color:#666; text-align:center; }
</style>
</head>
<body>
<div class="container">
    <h4 class="text-center">Ordem de Serviço nº '.$os['id'].'</h4>
    <div class="text-center">
        <img class="img-thumbnail" src="../uploads/frotas/'.$os['foto_capa'].'" alt="Foto Viatura">
    </div>

    <div class="section">
        <h5>Dados da Viatura</h5>
        <div class="row">
            <div class="col"><strong>Prefixo:</strong> '.$os['prefixo_sga'].'</div>
            <div class="col"><strong>Chassi:</strong> '.$os['chassi'].'</div>
            <div class="col"><strong>Marca:</strong> '.$os['nome_marca'].'</div>
            <div class="col"><strong>Modelo:</strong> '.$os['nome_modelo'].'</div>
        </div>
    </div>

    <div class="section">
        <h5>Dados da OS</h5>
        <div class="row">
            <div class="col"><strong>Data Abertura:</strong> '.date('d/m/Y', strtotime($os['data_abertura'])).'</div>
            <div class="col"><strong>Solicitante:</strong> '.$os['solicitante'].'</div>
            <div class="col"><strong>Status:</strong> '.$os['status'].'</div>
            <div class="col"><strong>ODO/HOR:</strong> '.$os['odometro_horimetro'].'</div>
            <div class="col"><strong>Tipo MNT:</strong> '.$os['tipo_mnt'].'</div>
        </div>
        <p><strong>Falhas/Serviço Solicitado:</strong> '.$os['problema'].'</p>
        <p><strong>Local da Manutenção:</strong> '.$os['local_os'].'</p>
    </div>

    <div class="section">
        <h5>Materiais/Peças Utilizados</h5>';
foreach ($materiais as $m) {
    $total = floatval($m['quant_itens_utilizados'])*floatval($m['valor_itens']);
    $html .= '<div class="border-box">
        <strong>Origem:</strong> '.$m['origem_item'].'<br>
        <strong>Descrição:</strong> '.$m['itens_utilizados'].'<br>
        <strong>Quantidade:</strong> '.$m['quant_itens_utilizados'].'<br>
        <strong>Valor Unitário:</strong> '.formatarValor($m['valor_itens']).'<br>
        <strong>Total:</strong> '.formatarValor($total).'
    </div>';
}
$html .= '</div>';

$html .= '<div class="section">
        <h5>Falhas Identificadas Durante MNT</h5>';
foreach ($falhas as $f) {
    $html .= '<div class="border-box">
        <strong>Seção:</strong> '.$f['secao_falha'].'<br>
        <strong>Falha:</strong> '.$f['falha_identificada'].'<br>
        <strong>Identificada por:</strong> '.$f['militar_identificou'].'
    </div>';
}
$html .= '</div>';

$html .= '<div class="section">
        <h5>Pessoal Utilizado</h5>';
foreach ($pessoal as $p) {
    $html .= '<div class="border-box">
        <strong>'.$p['postograd_militar'].' '.$p['nome_militar'].'</strong><br>
        <strong>Função:</strong> '.$p['funcao_militar'].'<br>
        <strong>Data:</strong> '.$p['data_emprego'].'<br>
        <strong>Serviço:</strong> '.$p['servico_executado'].'
    </div>';
}
$html .= '</div>';

$html .= '<div class="section">
        <h5>Serviços Realizados</h5>';
foreach ($servicos as $s) {
    $total = floatval($s['qtd_servico'])*floatval($s['valor_unt']);
    $html .= '<div class="border-box">
        <strong>Empresa:</strong> '.$s['empresa'].'<br>
        <strong>Serviço:</strong> '.$s['rlzd_mnt'].'<br>
        <strong>Quantidade:</strong> '.$s['qtd_servico'].'<br>
        <strong>Valor Unitário:</strong> '.formatarValor($s['valor_unt']).'<br>
        <strong>Total:</strong> '.formatarValor($total).'
    </div>';
}
$html .= '</div>';

// Valores totais
$valorND30 = floatval($os['valornd30']);
$valorND39 = floatval($os['valornd39']);
$totalGasto = $valorND30 + $valorND39;

$html .= '<div class="section">
        <h5>Valores</h5>
        <div class="row">
            <div class="col"><strong>ND30 (Materiais):</strong> '.formatarValor($valorND30).'</div>
            <div class="col"><strong>ND39 (Serviços):</strong> '.formatarValor($valorND39).'</div>
            <div class="col"><strong>Total:</strong> '.formatarValor($totalGasto).'</div>
        </div>
    </div>

    <div class="footer">
        Gerado automaticamente em '.$dataGeracao.' | Sistema de Gestão da Cia E Eqp Mnt
    </div>

</div>
</body>
</html>';

// Gerar PDF
$dompdf = new Dompdf();
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();
$dompdf->stream("OS_{$id}.pdf", ["Attachment" => false]);
