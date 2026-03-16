<?php
session_start();
include_once('../conexao/config.php');
require 'vendor/autoload.php';

use Dompdf\Dompdf;

// =============================
// USUÁRIO
// =============================
$usuario = $_SESSION['usuario'] ?? [];
$nivel_usuario = $usuario['nivel'] ?? 3;
$batalhao_usuario = $usuario['batalhao'] ?? null;

if (!$batalhao_usuario) {
    die("Batalhão não identificado.");
}

// =============================
// NOME DA OM
// =============================
$nomebatalhaoabrev = '';
$r = $conexao->query("SELECT informacao FROM dados WHERE id = 1");
if ($r && $r->num_rows) {
    $nomebatalhaoabrev = $r->fetch_assoc()['informacao'];
}

// =============================
// FILTROS
// =============================
$filtros = [];
$params  = [];
$tipos   = '';

$deposito_filtro = $_GET['deposito'] ?? '';
$batalhao_filtro = $_GET['batalhao'] ?? '';

$map = [
  'p.id' => 'id',
  'p.nome_produto' => 'nome_produto',
  'p.codigo_produto' => 'codigo_produto',
  'p.categoria_produto' => 'categoria_produto',
  'p.data_inclusao >=' => 'data_ini',
  'p.data_inclusao <=' => 'data_fim'
];

foreach ($map as $coluna => $param) {
    if (!empty($_GET[$param])) {
        if ($coluna === 'p.id') {
            $filtros[] = "$coluna = ?";
            $params[] = (int)$_GET[$param];
            $tipos .= 'i';
        } elseif (str_contains($coluna, '>=')) {
            $filtros[] = "$coluna ?";
            $params[] = $_GET[$param];
            $tipos .= 's';
        } elseif (str_contains($coluna, '<=')) {
            $filtros[] = "$coluna ?";
            $params[] = $_GET[$param];
            $tipos .= 's';
        } else {
            $filtros[] = "$coluna LIKE ?";
            $params[] = '%' . $_GET[$param] . '%';
            $tipos .= 's';
        }
    }
}

// =============================
// CONTROLE DE ACESSO
// =============================
if ($nivel_usuario == 1) {

    if ($batalhao_filtro) {
        $filtros[] = "p.batalhao = ?";
        $params[] = (int)$batalhao_filtro;
        $tipos .= 'i';
    }

} elseif ($nivel_usuario == 2) {

    $permitidos = [$batalhao_usuario];
    $stmt = $conexao->prepare("
        SELECT id_om_menor 
        FROM organizacoes_militares_sub 
        WHERE id_om_maior = ?
    ");
    $stmt->bind_param('i', $batalhao_usuario);
    $stmt->execute();
    $res = $stmt->get_result();

    while ($r = $res->fetch_assoc()) {
        $permitidos[] = $r['id_om_menor'];
    }

    if ($batalhao_filtro) {
        if (!in_array($batalhao_filtro, $permitidos)) {
            die("Sem permissão.");
        }
        $filtros[] = "p.batalhao = ?";
        $params[] = (int)$batalhao_filtro;
        $tipos .= 'i';
    } else {
        $place = implode(',', array_fill(0, count($permitidos), '?'));
        $filtros[] = "p.batalhao IN ($place)";
        $params = array_merge($params, $permitidos);
        $tipos .= str_repeat('i', count($permitidos));
    }

} else {
    $filtros[] = "p.batalhao = ?";
    $params[] = $batalhao_usuario;
    $tipos .= 'i';
}

$where = $filtros ? 'WHERE ' . implode(' AND ', $filtros) : '';

// =============================
// CONSULTA – IGUAL À LISTAGEM
// =============================
$sql = "
SELECT
  p.id,
  p.nome_produto,
  p.codigo_produto,
  p.categoria_produto,
  p.unidade,
  p.estoque_minimo,

  om.abreviatura AS om,
  d.nome_deposito,

  IFNULL(ent.total_entradas,0) AS entradas,
  IFNULL(sai.total_saidas,0) AS saidas,
  (IFNULL(ent.total_entradas,0) - IFNULL(sai.total_saidas,0)) AS estoque

FROM almox_produtos p

JOIN organizacoes_militares om
  ON om.id = p.batalhao

JOIN (
  SELECT
    ei.id_produto,
    e.deposito_id,
    SUM(ei.quant) total_entradas
  FROM almox_entradas_itens ei
  JOIN almox_entradas e ON e.id = ei.id_entrada
  " . ($deposito_filtro ? "WHERE e.deposito_id = ?" : "") . "
  GROUP BY ei.id_produto, e.deposito_id
) ent ON ent.id_produto = p.id

JOIN almox_depositos d
  ON d.id = ent.deposito_id

LEFT JOIN (
  SELECT
    pi.id_produto,
    e.deposito_id,
    SUM(pi.quant_solicitada) total_saidas
  FROM almox_pedidos_itens pi
  JOIN almox_entradas e ON e.id = pi.id_entrada
  " . ($deposito_filtro ? "WHERE e.deposito_id = ?" : "") . "
  GROUP BY pi.id_produto, e.deposito_id
) sai
  ON sai.id_produto = ent.id_produto
 AND sai.deposito_id = ent.deposito_id

$where
ORDER BY d.nome_deposito, p.nome_produto
";

// =============================
// PARAMS
// =============================
$paramsFinal = [];
$tiposFinal  = '';

if ($deposito_filtro) {
    $paramsFinal[] = (int)$deposito_filtro;
    $paramsFinal[] = (int)$deposito_filtro;
    $tiposFinal .= 'ii';
}

$paramsFinal = array_merge($paramsFinal, $params);
$tiposFinal  .= $tipos;

$stmt = $conexao->prepare($sql);

if (!empty($tiposFinal)) {
    $stmt->bind_param($tiposFinal, ...$paramsFinal);
}

$stmt->execute();

$res = $stmt->get_result();

// =============================
// HTML
// =============================
$html = "
<style>
body { font-family: Arial; font-size: 11px; }
h2,h4 { text-align:center; margin:4px; }
h3 { background:#ddd; padding:4px; }
table { width:100%; border-collapse:collapse; margin-bottom:10px; }
th,td { border:1px solid #000; padding:4px; text-align:center; }
th { background:#eee; }
</style>

<h2>$nomebatalhaoabrev</h2>
<h4>Relatório de Estoque por Depósito</h4>
";

$depAtual = '';

while ($r = $res->fetch_assoc()) {

    if ($depAtual !== $r['nome_deposito']) {
        if ($depAtual !== '') $html .= "</table>";
        $depAtual = $r['nome_deposito'];

        $html .= "
        <h3>Depósito: {$depAtual}</h3>
        <table>
        <tr>
            <th>OM</th>
            <th>ID</th>
            <th>Produto</th>
            <th>Código</th>
            <th>Categoria</th>
            <th>Un.</th>
            <th>Est. Min</th>
            <th>Entradas</th>
            <th>Saídas</th>
            <th>Estoque</th>
        </tr>";
    }

    $html .= "
    <tr>
        <td>{$r['om']}</td>
        <td>{$r['id']}</td>
        <td>{$r['nome_produto']}</td>
        <td>{$r['codigo_produto']}</td>
        <td>{$r['categoria_produto']}</td>
        <td>{$r['unidade']}</td>
        <td>{$r['estoque_minimo']}</td>
        <td>{$r['entradas']}</td>
        <td>{$r['saidas']}</td>
        <td>{$r['estoque']}</td>
    </tr>";
}

$html .= "</table>
<p style='text-align:right'>Gerado em: " . date('d/m/Y H:i') . "</p>";

// =============================
// PDF
// =============================
$dompdf = new Dompdf();
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'landscape');
$dompdf->render();
$dompdf->stream("estoque_por_deposito_" . date('Ymd_His'), ["Attachment" => false]);
