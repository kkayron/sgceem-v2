<?php
require_once 'vendor/autoload.php'; // Dompdf
require_once '../conexao/config.php';

use Dompdf\Dompdf;

$id = intval($_GET['id'] ?? 0);
if ($id <= 0) {
    die("Pedido inválido.");
}

// ----------------- BUSCAR DADOS DO PEDIDO -----------------
$stmt = $conexao->prepare("SELECT * FROM fin_pedidos_forn WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$pedido = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$pedido) {
    die("Pedido não encontrado.");
}

// ----------------- BUSCAR DADOS DA VIATURA -----------------
if (!empty($pedido['id_vtr'])) {
    $stmtFrota = $conexao->prepare("SELECT * FROM frota WHERE id = ?");
    $stmtFrota->bind_param("i", $pedido['id_vtr']);
    $stmtFrota->execute();
    $viatura = $stmtFrota->get_result()->fetch_assoc();
    $stmtFrota->close();
}

// ----------------- BUSCAR MARCA E MODELO -----------------
if (!empty($viatura['marca'])) {
    $stmtMarca = $conexao->prepare("SELECT * FROM config_marcas WHERE id = ?");
    $stmtMarca->bind_param("i", $viatura['marca']);
    $stmtMarca->execute();
    $marca = $stmtMarca->get_result()->fetch_assoc();
    $stmtMarca->close();
}

if (!empty($viatura['modelo'])) {
    $stmtModelo = $conexao->prepare("SELECT * FROM config_modelos WHERE id = ?");
    $stmtModelo->bind_param("i", $viatura['modelo']);
    $stmtModelo->execute();
    $modelo = $stmtModelo->get_result()->fetch_assoc();
    $stmtModelo->close();
}
// ----------------- BUSCAR NOME DO BATALHÃO -----------------
$nomeBatalhao = '-';
if (!empty($pedido['batalhao'])) {
    $stmtBat = $conexao->prepare("SELECT nome FROM organizacoes_militares WHERE id = ?");
    $stmtBat->bind_param("i", $pedido['batalhao']);
    $stmtBat->execute();
    $resBat = $stmtBat->get_result()->fetch_assoc();
    $stmtBat->close();
    if ($resBat) $nomeBatalhao = $resBat['nome'];
}

// ----------------- BUSCAR ITENS DO PEDIDO -----------------
$stmtItens = $conexao->prepare("
    SELECT 
        codigo_item,
        descricao_item,
        quant_solicitada,
        und_solicitada,
        almox_possui,
        valor_unt,
        valor_total
    FROM fin_pedidos_forn_itens
    WHERE id_principal = ?
");
$stmtItens->bind_param("i", $id);
$stmtItens->execute();
$itens = $stmtItens->get_result()->fetch_all(MYSQLI_ASSOC);
$stmtItens->close();

// ----------------- BUSCAR LOGS DO PEDIDO (FORNECEDOR) -----------------
$stmtLogs = $conexao->prepare("
    SELECT 
        l.data_hora,
        l.acao,
        l.descricao,
        u.nomeguerra AS usuario
    FROM logs l
    LEFT JOIN usuarios u ON u.id = l.usuario_id
    WHERE l.pedido_financeiro_id = ?
    ORDER BY l.data_hora ASC
");

$stmtLogs->bind_param("i", $id);
$stmtLogs->execute();
$logs = $stmtLogs->get_result()->fetch_all(MYSQLI_ASSOC);
$stmtLogs->close();


// ----------------- FORMATA DATAS -----------------
$datapedido = !empty($pedido['data_pedido']) ? date('d/m/Y', strtotime($pedido['data_pedido'])) : '-';
$dataAtual  = date('d/m/Y H:i');

// ----------------- CALCULAR VALORES -----------------
$totalPedido = 0;
foreach ($itens as $i) {
    $totalPedido += floatval($i['valor_total'] ?? 0);
}
$percentualDesconto = floatval($pedido['desconto_empenho'] ?? 0);
$desconto = $totalPedido * ($percentualDesconto / 100);
$totalFinal = max($totalPedido - $desconto, 0);

// ----------------- HTML -----------------
$html = '
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<title>Pedido Fornecedor Nº '.$id.'</title>
<style>
body {
    font-family: "Calibri", "Arial", sans-serif;
    font-size:11px;
    margin:0;
    padding:0;
    color:#333;
    background:#fff;
}
h2 { margin:0; font-size:14px; }
table {
    width:100%;
    border-collapse: collapse;
    margin-bottom:10px;
    font-size:11px;
}
th, td {
    border:1px solid #555;
    padding:5px;
    text-align:left;
}
th {
    background:#f0f0f0;
}
.header-box {
    width:31.5%;
    text-align: center;
    height:80px;
    float:left;
    border:1px solid #555;
    border-radius:5px;
    padding:5px;
    margin-right:1%;
    margin-bottom:5px;
    box-sizing:border-box;
    background:#f9f9f9;
}
.clearfix { clear:both; }
.first-row td {
    background:#cce0ff;
    font-weight:bold;
    text-align:center;
    border:1px solid #555;
}
.total-row td {
    font-weight:bold;
    background:#e6f2ff;
}
.footer-note {
    margin-top:30px;
    font-size:10px;
    text-align:center;
    color:#555;
}
.signature-box {
    width:32%;
    float:left;
    text-align:center;
    margin-right:1%;
    margin-top:20px;
}
.watermark {
    position: fixed;
    top: 45%;
    left: 5%;
    width: 90%;
    text-align: center;
    font-size: 60px;
    color: #FF0000;
    opacity: 0.08;
    transform: rotate(-30deg);
    z-index: -1;
    font-weight: bold;
    letter-spacing: 5px;
}
.page-break {
    page-break-before: always;
}
</style>
</head>
<body>
'.($pedido['autorizacao'] !== 'sim'
    ? '<div class="watermark">PEDIDO NÃO AUTORIZADO</div>'
    : ''
).'
<!-- Cabeçalho -->
<table>
<tr class="first-row">
    <td colspan="9">'.htmlspecialchars($nomeBatalhao).'</td>
</tr>
<tr>
    <td colspan="9" style="text-align:center; font-weight:bold;">PEDIDO AO FORNECEDOR Nº '.$id.'</td>
</tr>
</table>

<div id="site">
    <!-- Cabeçalho -->
    <div class="header-box">
        <div>Responsável pelo controle de pedidos:</div>
        <div style="margin-top:25px;">___________________________________</div>
        <div>'.htmlspecialchars($pedido['cmt_ceem'] ?? '-').'</div>
        <div>Cmt Cia E Eqp Mnt</div>
    </div>
    <div class="header-box">
        <div>Responsável pelo controle de pedidos:</div>
        <div style="margin-top:25px;">___________________________________</div>
        <div>'.htmlspecialchars($pedido['ch_controle'] ?? '-').'</div>
        <div>Ch Seção Controle</div>
    </div>
    <div class="header-box">
        <div>Responsável pelo controle de pedidos:</div>
        <div style="margin-top:25px;">___________________________________</div>
        <div>'.htmlspecialchars($pedido['ch_suprimento'] ?? '-').'</div>
        <div>Ch Seção Suprimento</div>
    </div>
    <div class="clearfix"></div>

<!-- Dados principais -->
<table>
<tr><td><b>Solicitante:</b></td><td>'.htmlspecialchars($pedido['solicitante'] ?? '-').'</td></tr>
<tr><td><b>Seção Responsável:</b></td><td>'.htmlspecialchars($pedido['secao_rspns'] ?? '-').'</td></tr>
<tr><td><b>Data do Pedido:</b></td><td>'.$datapedido.'</td></tr>
<tr><td><b>Prefixo da Viatura:</b></td><td>'.htmlspecialchars($viatura['prefixo_sga'] ?? '-').'</td></tr>
<tr><td><b>Chassi da Viatura:</b></td><td>'.htmlspecialchars($viatura['chassi'] ?? '-').'</td></tr>
<tr><td><b>Marca - Modelo:</b></td><td>'.htmlspecialchars($marca['marca'] ?? '-').' - '.htmlspecialchars($modelo['nome_modelo'] ?? '-').'</td></tr>
<tr><td><b>Local do Pedido:</b></td><td>'.htmlspecialchars($pedido['local_pedido'] ?? '-').'</td></tr>
<tr><td><b>Ordem de Serviço:</b></td><td>'.htmlspecialchars($pedido['id_os'] ?? '-').'</td></tr>
</table>

<!-- Itens -->
<table>
<tr>
    <th style="width:5%;">ITEM</th>
    <th style="width:15%;">CÓDIGO</th>
    <th style="width:35%;">DESCRIÇÃO</th>
    <th style="width:8%;">ALMOX POSSUI</th>
    <th style="width:8%;">QTD</th>
    <th style="width:8%;">UND</th>
    <th style="width:10%;">VALOR UNIT.</th>
    <th style="width:11%;">VALOR TOTAL</th>
</tr>';

$contador = 1;
foreach ($itens as $item) {
    $html .= '<tr>
        <td>'.$contador++.'</td>
        <td>'.htmlspecialchars($item['codigo_item'] ?? '-').'</td>
        <td>'.htmlspecialchars($item['descricao_item'] ?? '-').'</td>
        <td>'.htmlspecialchars($item['almox_possui'] ?? '-').'</td>
        <td>'.number_format(floatval($item['quant_solicitada'] ?? 0), 2, ",", ".").'</td>
        <td>'.htmlspecialchars($item['und_solicitada'] ?? '-').'</td>
        <td>R$ '.number_format(floatval($item['valor_unt'] ?? 0), 2, ",", ".").'</td>
        <td>R$ '.number_format(floatval($item['valor_total'] ?? 0), 2, ",", ".").'</td>
    </tr>';
}

$html .= '
<tr class="total-row">
    <td colspan="7" style="text-align:right;">VALOR TOTAL DO PEDIDO</td>
    <td>R$ '.number_format($totalPedido, 2, ",", ".").'</td>
</tr>
<tr class="total-row">
    <td colspan="7" style="text-align:right;">DESCONTO EMPENHO</td>
    <td>R$ '.number_format($desconto, 2, ",", ".").'</td>
</tr>
<tr class="total-row">
    <td colspan="7" style="text-align:right;">VALOR FINAL COM DESCONTO</td>
    <td>R$ '.number_format($totalFinal, 2, ",", ".").'</td>
</tr>
</table>

<!-- Assinaturas -->
<div class="signature-box">
    <div>Responsável pela solicitação</div>
    <div style="margin-top:25px;">_________________________</div>
    <div>'.htmlspecialchars($pedido['solicitante'] ?? '-').'</div>
</div>
<div class="signature-box">
    <div>Responsável pela conferência</div>
    <div style="margin-top:25px;">_________________________</div>
    <div>Posto/Grad Nome:</div>
</div>
<div class="signature-box">
    <div>Responsável pela aprovação</div>
    <div style="margin-top:25px;">_________________________</div>
    <div>Posto/Grad Nome:</div>
</div>
<div class="clearfix"></div>

<div class="footer-note">
    Relatório gerado automaticamente em '.$dataAtual.'.<br>
    Dados sujeitos à verificação em sistema. GCEEM 2.0
</div>
</html>';

$html .= '
<div class="page-break"></div>

<h2>Histórico de Movimentações do Pedido</h2>

<table>
    <tr>
        <th style="width:15%;">Data / Hora</th>
        <th style="width:20%;">Usuário</th>
        <th style="width:20%;">Ação</th>
        <th style="width:45%;">Descrição</th>
    </tr>
';

if (count($logs) > 0) {
    foreach ($logs as $log) {
        $html .= '
        <tr>
            <td>' . date('d/m/Y H:i', strtotime($log['data_hora'])) . '</td>
            <td>' . htmlspecialchars($log['usuario'] ?? '-') . '</td>
            <td>' . htmlspecialchars($log['acao']) . '</td>
            <td>' . htmlspecialchars($log['descricao']) . '</td>
        </tr>';
    }
} else {
    $html .= '
    <tr>
        <td colspan="4" style="text-align:center;">
            Nenhum registro de log encontrado para este pedido.
        </td>
    </tr>';
}

$html .= '</table>

</body>
</html>

';


// ----------------- GERAR PDF -----------------
$dompdf = new Dompdf();
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'landscape');
$dompdf->render();
$dompdf->stream("Pedido_Fornecedor_".$id.".pdf", ["Attachment" => false]);
?>
