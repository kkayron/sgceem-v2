<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once '../conexao/config.php';
session_start();

header("Content-Type: application/vnd.ms-excel; charset=UTF-8");
header("Content-Disposition: attachment; filename=empenhos_" . date('Ymd_His') . ".xls");
header("Pragma: no-cache");
header("Expires: 0");

echo "\xEF\xBB\xBF";

$usuario = $_SESSION['usuario'] ?? [];

$nivel_usuario    = (int)($usuario['nivel'] ?? 3);
$batalhao_usuario = (int)($usuario['batalhao'] ?? 0);

if (!$batalhao_usuario) {
    die("Erro: não foi possível identificar o batalhão do usuário.");
}


/* ======================================================
   FILTROS (IGUAL À LISTAGEM)
====================================================== */

$id               = $_GET['id'] ?? '';
$id_requisicao    = $_GET['id_requisicao'] ?? '';
$requisitante     = $_GET['requisitante'] ?? '';
$marca            = $_GET['marca'] ?? '';
$destinatario     = $_GET['destinatario'] ?? '';
$obra             = $_GET['obra'] ?? '';
$nmr_empenho      = $_GET['nmr_empenho'] ?? '';
$ano              = $_GET['ano'] ?? '';
$categoria        = $_GET['categoria'] ?? '';
$local            = $_GET['local'] ?? '';
$fornecedor       = $_GET['fornecedor'] ?? '';
$filtro_batalhao  = $_GET['batalhao'] ?? '';
$saldo_real       = $_GET['saldo_real'] ?? '';

$filtros = [];
$params  = [];
$tipos   = "";


/* ===============================
   FILTROS EXISTENTES
=============================== */

if (!empty($id)) {
    $filtros[] = "e.id = ?";
    $params[]  = (int)$id;
    $tipos    .= "i";
}

if (!empty($id_requisicao)) {
    $filtros[] = "r.id = ?";
    $params[]  = (int)$id_requisicao;
    $tipos    .= "i";
}

if (!empty($requisitante)) {
    $filtros[] = "r.requisitante LIKE ?";
    $params[]  = "%{$requisitante}%";
    $tipos    .= "s";
}

if (!empty($nmr_empenho)) {
    $filtros[] = "e.nmr_empenho LIKE ?";
    $params[]  = "%{$nmr_empenho}%";
    $tipos    .= "s";
}

if (!empty($marca)) {
    $filtros[] = "r.marca LIKE ?";
    $params[]  = "%{$marca}%";
    $tipos    .= "s";
}

if (!empty($destinatario)) {
    $filtros[] = "r.destinatario LIKE ?";
    $params[]  = "%{$destinatario}%";
    $tipos    .= "s";
}

if (!empty($obra)) {
    $filtros[] = "e.obra = ?";
    $params[]  = $obra;
    $tipos    .= "s";
}

if (!empty($categoria)) {
    $filtros[] = "e.categoria = ?";
    $params[]  = $categoria;
    $tipos    .= "s";
}

if (!empty($local)) {
    $filtros[] = "e.local = ?";
    $params[]  = $local;
    $tipos    .= "s";
}

if (!empty($ano)) {
    $filtros[] = "e.ano = ?";
    $params[]  = $ano;
    $tipos    .= "s";
}

if (!empty($fornecedor)) {
    $filtros[] = "r.id_fornecedor = ?";
    $params[]  = (int)$fornecedor;
    $tipos    .= "i";
}


/* ===============================
   BATALHÃO
=============================== */

if ($nivel_usuario != 1) {

    $filtros[] = "r.batalhao = ?";
    $params[]  = $batalhao_usuario;
    $tipos    .= "i";

} elseif (!empty($filtro_batalhao)) {

    $filtros[] = "r.batalhao = ?";
    $params[]  = $filtro_batalhao;
    $tipos    .= "i";

}


$condicoes = $filtros ? "WHERE " . implode(" AND ", $filtros) : "";


/* ===============================
   CONSULTA EMPENHOS
=============================== */

$sql = "

SELECT 
    e.id,
    e.ano,
    e.nmr_empenho,
    r.valor_empenhado,
    r.natureza_despesa,
    r.tipo_empenho,
    r.status_requisicao,
    r.batalhao,
    f.nome_empresa AS fornecedor,
    om.abreviatura AS nome_om

FROM fin_empenhos e
INNER JOIN fin_requisicao r ON r.id = e.id_requisicao
LEFT JOIN fin_fornecedores f ON f.id = r.id_fornecedor
LEFT JOIN organizacoes_militares om ON om.id = r.batalhao

$condicoes

ORDER BY e.ano DESC, e.nmr_empenho DESC

";

$stmt = $conexao->prepare($sql);

if (!empty($params)) {
    $stmt->bind_param($tipos, ...$params);
}

$stmt->execute();
$res = $stmt->get_result();

$empenhos = [];
$idsEmp = [];

while ($row = $res->fetch_assoc()) {

    $empenhos[] = $row;
    $idsEmp[] = (int)$row['id'];

}

$stmt->close();


/* ===============================
   CALCULAR VALORES POR STATUS
=============================== */

$valoresStatus = [];

if (!empty($idsEmp)) {

$inEmp = implode(',', array_fill(0, count($idsEmp), '?'));

$sqlPedStatus = "

SELECT 
    o.id_empenho,
    op.id_pedido,
    o.status

FROM fin_ordemforn o

JOIN fin_ordemforn_pedidos op
ON op.id_ordemforn = o.id

WHERE o.id_empenho IN ($inEmp)

";

$stmtPS = $conexao->prepare($sqlPedStatus);

$tiposPS = str_repeat('i', count($idsEmp));
$stmtPS->bind_param($tiposPS, ...$idsEmp);

$stmtPS->execute();
$resPS = $stmtPS->get_result();

$pedidosInfo = [];
$idsPedidos = [];

while ($row = $resPS->fetch_assoc()) {

$idEmp = (int)$row['id_empenho'];
$idPed = (int)$row['id_pedido'];

$pedidosInfo[$idPed] = [

'id_empenho'=>$idEmp,
'status'=>trim($row['status'])

];

$idsPedidos[$idPed] = true;

}

$stmtPS->close();



/* ===============================
   SOMAR ITENS POR PEDIDO
=============================== */

$totalPorPedido = [];

if (!empty($idsPedidos)) {

$listaPedidos = array_keys($idsPedidos);

$inPed = implode(',', array_fill(0, count($listaPedidos), '?'));

$sqlPedidos = "

SELECT 
p.id,
COALESCE(SUM(i.valor_total),0) AS total_itens,
COALESCE(p.desconto_empenho,0) AS desconto

FROM fin_pedidos_forn p

LEFT JOIN fin_pedidos_forn_itens i
ON i.id_principal = p.id

WHERE p.id IN ($inPed)

GROUP BY p.id

";

$stmtSP = $conexao->prepare($sqlPedidos);

$tiposSP = str_repeat('i', count($listaPedidos));
$stmtSP->bind_param($tiposSP, ...$listaPedidos);

$stmtSP->execute();
$resSP = $stmtSP->get_result();

while ($r = $resSP->fetch_assoc()) {

$totalItens = (float)$r['total_itens'];
$desconto = (float)$r['desconto'];

$totalFinal = $totalItens * (1 - $desconto / 100);

$totalPorPedido[(int)$r['id']] = $totalFinal;

}

$stmtSP->close();

}


/* ===============================
   AGRUPAR POR STATUS
=============================== */

foreach ($idsEmp as $idEmp) {

$valoresStatus[$idEmp] = [

'nao_entregue'=>0,
'entregue'=>0,
'capeador'=>0,
'liquidado'=>0

];

}

foreach ($pedidosInfo as $idPed=>$info) {

$idEmp = $info['id_empenho'];

$status = mb_strtolower(trim($info['status']));

$valor = $totalPorPedido[$idPed] ?? 0;

if ($status == 'não entregue' || $status == 'nao entregue') {

$valoresStatus[$idEmp]['nao_entregue'] += $valor;

}

elseif ($status == 'entregue') {

$valoresStatus[$idEmp]['entregue'] += $valor;

}

elseif ($status == 'capeador') {

$valoresStatus[$idEmp]['capeador'] += $valor;

}

elseif ($status == 'liquidado' || $status == 'pago') {

$valoresStatus[$idEmp]['liquidado'] += $valor;

}

}

}


/* ===============================
   TABELA EXCEL
=============================== */

echo "<table border='1' style='border-collapse:collapse;font-family:Arial;font-size:12px;'>";

echo "<tr>
<th colspan='13' style='background:#1E8449;color:#fff;font-size:14px;padding:8px;text-align:center;'>
Relatório de Empenhos (" . date('d/m/Y H:i') . ")
</th>
</tr>";

echo "<tr style='background:#D5F5E3;font-weight:bold;text-align:center;'>

<th>OM</th>
<th>Ano</th>
<th>Nº Empenho</th>
<th>Fornecedor</th>
<th>Natureza</th>
<th>Modalidade</th>

<th>Valor Empenhado</th>
<th>Não Entregue</th>
<th>Entregue</th>
<th>Capeador</th>
<th>Liquidado</th>
<th>Saldo Real</th>

</tr>";

foreach ($empenhos as $e) {

$id = (int)$e['id'];

$valorEmp = (float)$e['valor_empenhado'];

$nao = $valoresStatus[$id]['nao_entregue'] ?? 0;
$ent = $valoresStatus[$id]['entregue'] ?? 0;
$cap = $valoresStatus[$id]['capeador'] ?? 0;
$liq = $valoresStatus[$id]['liquidado'] ?? 0;

$totalUtilizado = $nao + $ent + $cap + $liq;

$saldo = $valorEmp - $totalUtilizado;


/* ===============================
   FILTRO SALDO REAL
=============================== */

if ($saldo_real === 'positivo' && $saldo <= 0) continue;
if ($saldo_real === 'zerado' && $saldo != 0) continue;
if ($saldo_real === 'negativo' && $saldo >= 0) continue;

echo "<tr>";

echo "<td>{$e['nome_om']}</td>";
echo "<td align='center'>{$e['ano']}</td>";
echo "<td>{$e['nmr_empenho']}</td>";
echo "<td>{$e['fornecedor']}</td>";
echo "<td>{$e['natureza_despesa']}</td>";
echo "<td>{$e['tipo_empenho']}</td>";

echo "<td align='right'>" . number_format($valorEmp,2,',','.') . "</td>";
echo "<td align='right'>" . number_format($nao,2,',','.') . "</td>";
echo "<td align='right'>" . number_format($ent,2,',','.') . "</td>";
echo "<td align='right'>" . number_format($cap,2,',','.') . "</td>";
echo "<td align='right'>" . number_format($liq,2,',','.') . "</td>";
echo "<td align='right'>" . number_format($saldo,2,',','.') . "</td>";

echo "</tr>";

}

echo "</table>";

exit;