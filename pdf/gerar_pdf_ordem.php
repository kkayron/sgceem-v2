<?php
require_once 'vendor/autoload.php';
require_once '../conexao/config.php';

use Dompdf\Dompdf;

$id = intval($_GET['id'] ?? 0);
if ($id <= 0) {
    die('ID inválido.');
}

// ===============================
// Consulta principal
// ===============================
$sql = "
    SELECT 
        o.id,
        o.batalhao,
        om.nome AS nome_batalhao,
        o.id_empenho,
        e.nmr_empenho,
        o.data_cadastro,
        o.data_entrega_limite,
        o.status,
        o.empresa_nome,
        o.empresa_cnpj,
        o.empresa_email,
        o.local_entrega,
        o.nome_responsavel,
        o.contato_responsavel,
        o.cmt_ceem,
        o.ch_suprimento,
        o.ch_controle,
        o.observacao_final
    FROM fin_ordemforn o
    LEFT JOIN organizacoes_militares om ON om.id = o.batalhao
    LEFT JOIN fin_empenhos e ON e.id = o.id_empenho
    WHERE o.id = ?
";
$stmt = $conexao->prepare($sql);
$stmt->bind_param("i", $id);
$stmt->execute();
$ordem = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$ordem) {
    die('Ordem de fornecimento não encontrada.');
}

// =============================== //
// Pedidos relacionados            //
// =============================== //
$sqlPedidos = "
    SELECT 
        p.id AS id_pedido,
        p.solicitante,
        p.desconto_empenho,
        COALESCE((
            SELECT SUM(valor_total)
            FROM fin_pedidos_forn_itens
            WHERE id_principal = p.id
        ), 0) AS total_pedido
    FROM fin_ordemforn_pedidos op
    INNER JOIN fin_pedidos_forn p ON p.id = op.id_pedido
    WHERE op.id_ordemforn = ?
";

$stmtPedidos = $conexao->prepare($sqlPedidos);
$stmtPedidos->bind_param("i", $id);
$stmtPedidos->execute();
$resPedidos = $stmtPedidos->get_result();

$pedidos = [];

while ($row = $resPedidos->fetch_assoc()) {

    $percentual = floatval($row['desconto_empenho'] ?? 0);
    $totalPedido = floatval($row['total_pedido']);

    $valorDesconto = $totalPedido * ($percentual / 100);
    $totalFinal = $totalPedido - $valorDesconto;

    $row['valor_desconto'] = $valorDesconto;
    $row['total_final'] = $totalFinal;

    $pedidos[] = $row;
}

$stmtPedidos->close();


// ===============================
// Itens dos pedidos
// ===============================
$itens = [];
$totalGeral = 0;
$totalDescontoGeral = 0;
$totalFinalGeral = 0;

foreach ($pedidos as $pedido) {

    $sqlItens = "
        SELECT 
            descricao_item,
            quant_solicitada,
            und_solicitada,
            valor_unt,
            valor_total
        FROM fin_pedidos_forn_itens
        WHERE id_principal = ?
    ";

    $stmtItens = $conexao->prepare($sqlItens);
    $stmtItens->bind_param("i", $pedido['id_pedido']);
    $stmtItens->execute();
    $resItens = $stmtItens->get_result();

    while ($i = $resItens->fetch_assoc()) {

        $i['id_pedido'] = $pedido['id_pedido'];

        // trazer o desconto do pedido para o item
        $i['desconto_empenho'] = $pedido['desconto_empenho'];

        // calcular desconto e valor final do item
        $valorTotal = floatval($i['valor_total']);
        $percentual = floatval($pedido['desconto_empenho'] ?? 0);

        $i['valor_desconto'] = $valorTotal * ($percentual / 100);
        $i['valor_final'] = $valorTotal - $i['valor_desconto'];

        $itens[] = $i;

        $totalGeral += $valorTotal;
    }

    $totalDescontoGeral += $pedido['valor_desconto'];
    $totalFinalGeral += $pedido['total_final'];

    $stmtItens->close();
}
// ===============================
// HTML - VISUAL APRIMORADO
// ===============================
$html = '
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<style>
body {
    font-family: "Segoe UI", Arial, sans-serif;
    font-size: 11.5px;
    color: #222;
    margin: 25px 35px;
}
h2, h3 {
    text-align: center;
    margin: 0;
}
h2 {
    font-size: 14px;
    font-weight: bold;
    color: #0d1b3f;
}
h3 {
    font-size: 12px;
    font-weight: normal;
    color: #333;
}
.header {
    position: relative;
    margin-bottom: 10px;
    text-align: center;
}
.header img {
    position: absolute;
    left: 15px;
    top: 10px;
    width: 65px;
}
hr {
    border: none;
    border-top: 1px solid #555;
    margin: 10px 0 15px 0;
}
.box-info {
    border: 1px solid #b0b0b0;
    border-radius: 6px;
    padding: 8px 12px;
    background: #fdfdfd;
    box-shadow: 0 1px 2px rgba(0,0,0,0.05);
    margin-top: 10px;
}
.box-info strong {
    color: #000;
}
.section-title {
    background: #d8e1f5;
    border: 1px solid #a6b5cc;
    border-radius: 4px;
    padding: 5px 10px;
    font-weight: bold;
    margin-top: 15px;
    color: #0d1b3f;
}
.table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 10px;
}
.table th, .table td {
    border: 1px solid #bbb;
    padding: 6px;
}
.table th {
    background: #eaf0fa;
    text-align: center;
    font-weight: 600;
}
.table td {
    vertical-align: top;
}
.total {
    text-align: right;
    font-weight: bold;
    margin-top: 8px;
    padding-right: 4px;
}
.assinaturas {
    margin-top: 50px;
    text-align: center;
}
.assinaturas div {
    display: inline-block;
    width: 30%;
    margin: 0 5px;
}
.assinaturas hr {
    border-top: 1px solid #000;
    margin: 50px 0 5px 0;
}
.footer {
    margin-top: 25px;
    font-size: 10px;
    color: #444;
    text-align: center;
}
</style>
</head>
<body>

<div class="header">
    <h2>MINISTÉRIO DA DEFESA</h2>
    <h2>EXÉRCITO BRASILEIRO</h2>
    <h3>' . mb_strtoupper(htmlspecialchars($ordem['nome_batalhao']), 'UTF-8') . '</h3>
<br><br>
    <h3><b>ORDEM DE FORNECIMENTO Nº ' . $ordem['id'] . '</b></h3>
</div>
<hr>

<div class="box-info">
    <strong>Empenho:</strong> ' . htmlspecialchars($ordem['nmr_empenho']) . '<br>
    <strong>Data de Cadastro:</strong> ' . date('d/m/Y', strtotime($ordem['data_cadastro'])) . ' — 
    <strong>Data Limite de Entrega:</strong> ' . date('d/m/Y', strtotime($ordem['data_entrega_limite'])) . '<br>
    <strong>Status:</strong> ' . htmlspecialchars($ordem['status']) . '
</div>

<div class="section-title">DADOS DA EMPRESA FORNECEDORA</div>
<div class="box-info">
    <strong>' . htmlspecialchars($ordem['empresa_nome']) . '</strong><br>
    CNPJ: ' . htmlspecialchars($ordem['empresa_cnpj']) . '<br>
    E-mail: ' . htmlspecialchars($ordem['empresa_email']) . '
</div>

<div class="section-title">LOCAL E CONTATO PARA ENTREGA</div>
<div class="box-info">
    <strong>Local de Entrega:</strong> ' . htmlspecialchars($ordem['local_entrega']) . '<br>
    <strong>Responsável:</strong> ' . htmlspecialchars($ordem['nome_responsavel']) . '<br>
    <strong>Contato:</strong> ' . htmlspecialchars($ordem['contato_responsavel']) . '
</div>

<div class="section-title">PEDIDOS RELACIONADOS</div>
<div class="box-info">';

foreach ($pedidos as $p) {
    $html .= 'Pedido <strong>#' . $p['id_pedido'] . '</strong> — ' .
         'Solicitante: ' . htmlspecialchars($p['solicitante']) .
         ' — Valor Total: R$ ' . number_format($p['total_pedido'], 2, ',', '.') . '<br>' .
         ' — Desconto (' . number_format($p['desconto_empenho'], 2, ',', '.') . '%): R$ ' . number_format($p['valor_desconto'], 2, ',', '.') . '<br>' .
         ' — Valor Final: R$ ' . number_format($p['total_final'], 2, ',', '.') . '<br>';
}

$html .= '</div>

<div class="section-title">ITENS DA ORDEM DE FORNECIMENTO</div>
<table class="table">
    <thead>
        <tr>
            <th>Pedido</th>
            <th>Descrição</th>
            <th>Qtd</th>
            <th>Unid.</th>
            <th>Vlr Unit. (R$)</th>
            <th>Vlr Total (R$)</th>
            <th>Desconto (R$)</th>
            <th>Vlr Final (R$)</th>
        </tr>
    </thead>
    <tbody>';

foreach ($itens as $item) {

    $html .= '
        <tr>
            <td style="text-align:center;">' . $item['id_pedido'] . '</td>
            <td>' . htmlspecialchars($item['descricao_item']) . '</td>
            <td style="text-align:center;">' . $item['quant_solicitada'] . '</td>
            <td style="text-align:center;">' . htmlspecialchars($item['und_solicitada']) . '</td>
            <td style="text-align:right;">' . number_format($item['valor_unt'], 2, ',', '.') . '</td>
            <td style="text-align:right;">' . number_format($item['valor_total'], 2, ',', '.') . '</td>
            <td style="text-align:right;">' . number_format($item['valor_desconto'], 2, ',', '.') . '</td>
            <td style="text-align:right;">' . number_format($item['valor_final'], 2, ',', '.') . '</td>
        </tr>';
}

$html .= '
    </tbody>
</table>

<div class="total">
    Total Bruto: R$ ' . number_format($totalGeral, 2, ',', '.') . '<br>
    Total de Descontos: R$ ' . number_format($totalDescontoGeral, 2, ',', '.') . '<br>
    <strong>Total Final da Ordem: R$ ' . number_format($totalFinalGeral, 2, ',', '.') . '</strong>
</div>
<div class="section-title">OBSERVAÇÕES</div>
<div class="box-info">
    ' . nl2br(htmlspecialchars($ordem['observacao_final'])) . '
</div>

<div class="assinaturas">
    <div>
        <hr>
        <strong>' . htmlspecialchars($ordem['cmt_ceem']) . '</strong><br>
        Cmt Cia E Eqp Mnt
    </div>
    <div>
        <hr>
        <strong>' . htmlspecialchars($ordem['ch_suprimento']) . '</strong><br>
        Ch Suprimento
    </div>
    <div>
        <hr>
        <strong>' . htmlspecialchars($ordem['ch_controle']) . '</strong><br>
        Ch Controle
    </div>
</div>

<div class="footer">
    Sistema de Gerenciamento - © 2025<br>
    Documento emitido automaticamente — Requer assinatura manual.
</div>

</body>
</html>
';

$dompdf = new Dompdf();
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();
$dompdf->stream("ordem_fornecimento_{$id}.pdf", ["Attachment" => false]);
exit;
?>
