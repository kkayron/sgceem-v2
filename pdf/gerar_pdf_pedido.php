<?php
require_once 'vendor/autoload.php'; // Dompdf
require_once '../conexao/config.php';

use Dompdf\Dompdf;

$id = intval($_GET['id'] ?? 0);
if ($id <= 0) {
    die("Pedido inválido.");
}

// ----------------- BUSCAR DADOS DO PEDIDO -----------------
$stmt = $conexao->prepare("SELECT * FROM almox_pedidos_princ WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$pedido = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$pedido) {
    die("Pedido não encontrado.");
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
        pi.id_pedido_principal,
        pi.id_produto,
        pi.id_entrada,
        pi.quant_solicitada,

        p.nome_produto,
        p.codigo_produto,
        p.unidade,

        ei.marca,
        ei.modelo,
        ei.valor_unt,
        ei.quant AS almox_possui
    FROM almox_pedidos_itens pi
    INNER JOIN almox_produtos p
        ON p.id = pi.id_produto
    INNER JOIN almox_entradas_itens ei
        ON ei.id_entrada = pi.id_entrada
       AND ei.id_produto = pi.id_produto
    WHERE pi.id_pedido_principal = ?
    ORDER BY pi.id
");
$stmtItens->bind_param("i", $id);
$stmtItens->execute();
$itens = $stmtItens->get_result()->fetch_all(MYSQLI_ASSOC);
$stmtItens->close();

// ----------------- BUSCAR LOGS DO PEDIDO -----------------
$stmtLogs = $conexao->prepare("
    SELECT 
        l.data_hora,
        l.acao,
        l.descricao,
        u.nomeguerra AS usuario
    FROM logs l
    LEFT JOIN usuarios u ON u.id = l.usuario_id
    WHERE l.pedido_almox_id = ?
    ORDER BY l.data_hora ASC
");
$stmtLogs->bind_param("i", $id);
$stmtLogs->execute();
$logs = $stmtLogs->get_result()->fetch_all(MYSQLI_ASSOC);
$stmtLogs->close();


// ----------------- FORMATA DATAS -----------------
$datapedido   = !empty($pedido['data_pedido']) ? date('d/m/Y', strtotime($pedido['data_pedido'])) : '-';
$dataretirada = !empty($pedido['data_retirada'] ?? null) ? date('d/m/Y', strtotime($pedido['data_retirada'])) : '-';
$dataAtual    = date('d/m/Y H:i');

// ----------------- HTML -----------------
$html = '
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<title>Pedido Nº '.$id.'</title>
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
.page-break {
    page-break-before: always;
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
.signature-box {
    width:32%;
    float:left;
    text-align:center;
    margin-right:1%;
    margin-top:20px;
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
</style>
</head>
<body>
'.($pedido['autorizacao'] !== 'sim'
    ? '<div class="watermark">PEDIDO NÃO AUTORIZADO</div>'
    : ''
).'
<!-- Primeira linha com título do batalhão -->
<table>
<tr class="first-row">
    <td colspan="1">'.htmlspecialchars($nomeBatalhao).'</td>
    <td colspan="8">Pedido Nº '.$id.'</td>
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

    <!-- Dados do pedido -->
    <table>
    <tr><td><b>LOCAL:</b></td><td>'.htmlspecialchars($pedido['local_pedido'] ?? '-').'</td></tr>
    <tr><td><b>DATA DO PEDIDO:</b></td><td>'.$datapedido.'</td></tr>
    <tr><td><b>NÚMERO DA ORDEM DE SERVIÇO:</b></td><td>'.($pedido['id_os'] ?? '-').'</td></tr>
    <tr><td><b>PREFIXO:</b></td><td>'.($pedido['prefixo_sga'] ?? '-').'</td></tr>
    <tr><td><b>CHASSI/SÉRIE:</b></td><td>'.($pedido['odometro'] ?? '-').'</td></tr>
    <tr><td><b>SEÇÃO:</b></td><td>'.($pedido['secao_solicitante'] ?? '-').'</td></tr>
</table>

    <!-- Tabela de itens -->
    <table>
        <tr>
            <th style="width:5%;">ITEM</th>
            <th style="width:12%;">CÓDIGO REF</th>
            <th style="width:35%;">DISCRIMINAÇÃO DETALHADA</th>
            <th style="width:8%;">ALMOX POSSUI (Quant)</th>
            <th style="width:10%;">QUANT SOLICITADA</th>
            <th style="width:7%;">UND</th>
            <th style="width:11%;">VALOR UNITÁRIO</th>
            <th style="width:12%;">VALOR TOTAL</th>
        </tr>';

$totalGeral = 0;
foreach ($itens as $k => $item) {
    $valorUnit  = floatval($item['valor_unt'] ?? 0);
    $qtdSolic   = floatval($item['quant_solicitada'] ?? 0);
    $valorTotal = $valorUnit * $qtdSolic;
    $totalGeral += $valorTotal;

    $html .= '<tr>
        <td>'.str_pad($k + 1, 2, "0", STR_PAD_LEFT).'</td>
        <td>'.htmlspecialchars($item['codigo_produto'] ?? '-').'</td>
        <td>'.htmlspecialchars($item['nome_produto'] ?? '-').'</td>
        <td>'.htmlspecialchars($item['almox_possui'] ?? '-').'</td>
        <td>'.$qtdSolic.'</td>
        <td>'.htmlspecialchars($item['unidade'] ?? '-').'</td>
        <td>R$ '.number_format($valorUnit, 2, ",", ".").'</td>
        <td>R$ '.number_format($valorTotal, 2, ",", ".").'</td>
    </tr>';
}

$html .= '<tr class="total-row">
    <td colspan="7" style="text-align:right;">VALOR TOTAL DO PEDIDO</td>
    <td>R$ '.number_format($totalGeral, 2, ",", ".").'</td>
</tr>';

$html .= '</table>

    <!-- Assinaturas -->
    <div class="signature-box">
        <div>Responsável pela solicitação e retirada do pedido</div>
        <div style="margin-top:25px;">_________________________</div>
        <div>Posto/Grad Nome: '.htmlspecialchars($pedido['militar_solicitante'] ?? '-').'</div>
    </div>
    <div class="signature-box" style="width:34%;">
        <div>Data da retirada do pedido do almox/compras</div>
        <div style="margin-top:25px;">_____/_____/_____</div>
        <div>Retirado em '.$dataretirada.'</div>
    </div>
    <div class="signature-box">
        <div>Responsável pelo fechamento do pedido</div>
        <div style="margin-top:25px;">_________________________</div>
        <div>Posto/Grad Nome: -</div>
    </div>
    <div class="clearfix"></div>

    <!-- Mensagem final -->
    <div class="footer-note">
        Relatório gerado automaticamente em '.$dataAtual.'.<br>
        Dados sujeitos à verificação em sistema. GCEEM 2.0
    </div>
</div>
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
            <td>'.date('d/m/Y H:i', strtotime($log['data_hora'])).'</td>
            <td>'.htmlspecialchars($log['usuario'] ?? '-').'</td>
            <td>'.htmlspecialchars($log['acao']).'</td>
            <td>'.htmlspecialchars($log['descricao']).'</td>
        </tr>';
    }
} else {
    $html .= '
    <tr>
        <td colspan="4" style="text-align:center;">Nenhum registro de log encontrado para este pedido.</td>
    </tr>';
}

$html .= '</table>';
  
$html .'
</body>
</html>';

// ----------------- GERAR PDF -----------------
$dompdf = new Dompdf();
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'landscape'); // paisagem
$dompdf->render();
$dompdf->stream("Pedido_".$id.".pdf", ["Attachment" => false]);
?>
