<?php
require_once '../conexao/config.php';
session_start();

/* ============================================================
   🔹 DADOS DO USUÁRIO
   ============================================================ */
$usuario          = $_SESSION['usuario'] ?? [];
$nivel_usuario    = $usuario['nivel'] ?? 3;
$batalhao_usuario = $usuario['batalhao'] ?? null;

if (!$batalhao_usuario) {
    die("Erro: Não foi possível identificar o batalhão.");
}

/* ============================================================
   🔹 LISTA DE BATALHÕES VISÍVEIS AO USUÁRIO
   ============================================================ */
$oms_visiveis = [];

if ($nivel_usuario == 1) {
    // Admin — todos
    $sql = "SELECT id, abreviatura FROM organizacoes_militares ORDER BY abreviatura";
    $stmt = $conexao->prepare($sql);

} elseif ($nivel_usuario == 2) {
    // N2 — ele + subordinados
    $sql = "
        SELECT om.id, om.abreviatura 
        FROM organizacoes_militares om
        JOIN organizacoes_militares_sub sub ON om.id = sub.id_om_menor
        WHERE sub.id_om_maior = ?
        UNION
        SELECT id, abreviatura FROM organizacoes_militares WHERE id = ?
        ORDER BY abreviatura
    ";
    $stmt = $conexao->prepare($sql);
    $stmt->bind_param("ii", $batalhao_usuario, $batalhao_usuario);

} else {
    // N3 — somente seu batalhão
    $sql = "
        SELECT id, abreviatura 
        FROM organizacoes_militares 
        WHERE id = ?
    ";
    $stmt = $conexao->prepare($sql);
    $stmt->bind_param("i", $batalhao_usuario);
}

$stmt->execute();
$res = $stmt->get_result();
while ($r = $res->fetch_assoc()) {
    $oms_visiveis[$r['id']] = $r['abreviatura'];
}
$stmt->close();

/* ============================================================
   🔹 HEADER EXCEL
   ============================================================ */
header("Content-Type: application/vnd.ms-excel; charset=UTF-8");
header("Content-Disposition: attachment; filename=requisicoes_" . date('Ymd_His') . ".xls");
header("Pragma: no-cache");
header("Expires: 0");
echo "\xEF\xBB\xBF"; // UTF-8 BOM

/* ============================================================
   🔹 COLETA DE FILTROS GET
   ============================================================ */
$filtrosGET = [
    'id'            => $_GET['id'] ?? '',
    'requisitante'  => $_GET['requisitante'] ?? '',
    'destinatario'  => $_GET['destinatario'] ?? '',
    'nota_credito'  => $_GET['nota_credito'] ?? '',
    'plano_interno' => $_GET['plano_interno'] ?? '',
    'data_ini'      => $_GET['data_ini'] ?? '',
    'data_fim'      => $_GET['data_fim'] ?? '',
    'batalhao'      => $_GET['batalhao'] ?? '',
    'nmr_empenho'   => $_GET['nmr_empenho'] ?? ''
];

$filtrosSQL = [];
$params = [];
$types = "";

/* ============================================================
   🔹 RESTRIÇÃO POR BATALHÃO
   ============================================================ */
$batalhaoFiltro = (int)($filtrosGET['batalhao'] ?? 0);

if ($nivel_usuario == 1) {
    if ($batalhaoFiltro > 0) {
        $filtrosSQL[] = "fr.batalhao = ?";
        $params[] = $batalhaoFiltro;
        $types .= "i";
    }
} elseif ($nivel_usuario == 2) {
    if ($batalhaoFiltro > 0) {
        if (!array_key_exists($batalhaoFiltro, $oms_visiveis)) {
            die("Acesso negado ao batalhão selecionado.");
        }
        $filtrosSQL[] = "fr.batalhao = ?";
        $params[] = $batalhaoFiltro;
        $types .= "i";
    } else {
        $ids = implode(",", array_keys($oms_visiveis));
        $filtrosSQL[] = "fr.batalhao IN ($ids)";
    }
} else { 
    // N3
    $filtrosSQL[] = "fr.batalhao = ?";
    $params[] = $batalhao_usuario;
    $types .= "i";
}

/* ============================================================
   🔹 OUTROS FILTROS (igual listagem)
   ============================================================ */
$camposLike = [
    'id', 'requisitante', 'destinatario',
    'nota_credito', 'plano_interno', 'nmr_empenho'
];

foreach ($camposLike as $campo) {
    if (!empty($filtrosGET[$campo])) {
        $filtrosSQL[] = "fr.$campo LIKE ?";
        $params[] = "%{$filtrosGET[$campo]}%";
        $types .= "s";
    }
}

if (!empty($filtrosGET['data_ini'])) {
    $filtrosSQL[] = "fr.data_requisicao >= ?";
    $params[] = $filtrosGET['data_ini'];
    $types .= "s";
}
if (!empty($filtrosGET['data_fim'])) {
    $filtrosSQL[] = "fr.data_requisicao <= ?";
    $params[] = $filtrosGET['data_fim'];
    $types .= "s";
}

$whereSQL = !empty($filtrosSQL) ? "WHERE " . implode(" AND ", $filtrosSQL) : "";

/* ============================================================
   🔹 CONSULTA DAS REQUISIÇÕES
   ============================================================ */
$sql = "
    SELECT fr.*, om.abreviatura AS batalhao_nome
    FROM fin_requisicao fr
    JOIN organizacoes_militares om ON om.id = fr.batalhao
    $whereSQL
    ORDER BY fr.id DESC
";

$stmt = $conexao->prepare($sql);
if ($params) $stmt->bind_param($types, ...$params);
$stmt->execute();
$reqs = $stmt->get_result();

/* ============================================================
   🔹 TABELA EXCEL
   ============================================================ */
echo "<table border='1' style='border-collapse:collapse; font-family:Arial;'>";

echo "<tr><th colspan='9' style='background:#2E86C1; color:white; padding:6px;'>
        LISTAGEM DE REQUISIÇÕES (" . date("d/m/Y H:i") . ")
      </th></tr>";

echo "
<tr style='background:#D6EAF8; font-weight:bold;'>
    <th>ID</th>
    <th>Batalhão</th>
    <th>Data</th>
    <th>Requisitante</th>
    <th>Destinatário</th>
    <th>Nota de Crédito</th>
    <th>Plano Interno</th>
    <th>Status</th>
    <th>Empenho</th>
</tr>
";

/* ============================================================
   🔹 PREENCHIMENTO DAS LINHAS
   ============================================================ */
while ($r = $reqs->fetch_assoc()) {

    // Conta itens
    $stmtI = $conexao->prepare("SELECT COUNT(*) AS t FROM fin_requisicao_itens WHERE id_requisicao = ?");
    $stmtI->bind_param("i", $r['id']);
    $stmtI->execute();
    $tItens = $stmtI->get_result()->fetch_assoc()['t'] ?? 0;
    $stmtI->close();

    echo "<tr>";
    echo "<td>{$r['id']}</td>";
    echo "<td>{$r['batalhao_nome']}</td>";
    echo "<td>" . date("d/m/Y", strtotime($r['data_requisicao'])) . "</td>";
    echo "<td>{$r['requisitante']}</td>";
    echo "<td>{$r['destinatario']}</td>";
    echo "<td>{$r['nota_credito']}</td>";
    echo "<td>{$r['plano_interno']}</td>";
    echo "<td>{$r['status_requisicao']}</td>";
    echo "<td>" . ($r['empenho_gerado'] === 'sim' ? $r['nmr_empenho'] : '—') . "</td>";
    echo "</tr>";

    /* ---------------------------------------------
       🔹 ITENS DA REQUISIÇÃO (cada item em nova linha)
       --------------------------------------------- */
    $stmtItens = $conexao->prepare("
        SELECT fri.*, fpi.descricao_item
        FROM fin_requisicao_itens fri
        LEFT JOIN fin_pregao_itens fpi ON fpi.id = fri.id_item
        WHERE fri.id_requisicao = ?
    ");
    $stmtItens->bind_param("i", $r['id']);
    $stmtItens->execute();
    $resItens = $stmtItens->get_result();

    if ($resItens->num_rows > 0) {

        echo "<tr style='background:#F2F4F4; font-weight:bold;'>
                <td colspan='9'>Itens da Requisição #{$r['id']}</td>
              </tr>";

        echo "<tr style='background:#FBFCFC; font-weight:bold;'>
                <td colspan='7'>Descrição do Item</td>
                <td colspan='2'>Quantidade</td>
              </tr>";

        while ($it = $resItens->fetch_assoc()) {
            echo "<tr>";
            echo "<td colspan='7'>" . htmlspecialchars($it['descricao_item'] ?? '—') . "</td>";
            echo "<td colspan='2' style='text-align:center;'>" . ($it['quant_saida_item'] ?? 0) . "</td>";
            echo "</tr>";
        }
    }

    $stmtItens->close();
}

echo "</table>";
exit;
?>
