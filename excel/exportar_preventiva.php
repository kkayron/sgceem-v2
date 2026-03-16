<?php
require_once '../conexao/config.php';
require_once '../includes/preventiva/calculo_manutencao.php';
session_start();

// ===============================
// 🔹 Captura dados da sessão
// ===============================
$id_om_usuario = $_SESSION['usuario']['batalhao'] ?? 0;
$nivel_usuario = $_SESSION['usuario']['nivel'] ?? 3; // padrão restrito

// ===============================
// 🔹 Monta lista de OMs acessíveis
// ===============================
$oms_visiveis = [];

if ($nivel_usuario == 1) {
    // Nível 1 → todas as OMs
    $sql_oms = "SELECT id, nome, abreviatura FROM organizacoes_militares ORDER BY nome";
    $stmt_oms = $conexao->prepare($sql_oms);
} elseif ($nivel_usuario == 2) {
    // Nível 2 → sua OM + subordinadas
    $sql_oms = "
        SELECT om.id, om.nome, om.abreviatura
        FROM organizacoes_militares om
        JOIN organizacoes_militares_sub sub ON om.id = sub.id_om_menor
        WHERE sub.id_om_maior = ?
        UNION
        SELECT id, nome, abreviatura FROM organizacoes_militares WHERE id = ?
        ORDER BY nome
    ";
    $stmt_oms = $conexao->prepare($sql_oms);
    $stmt_oms->bind_param("ii", $id_om_usuario, $id_om_usuario);
} else {
    // Nível 3 → apenas sua OM
    $sql_oms = "SELECT id, nome, abreviatura FROM organizacoes_militares WHERE id = ?";
    $stmt_oms = $conexao->prepare($sql_oms);
    $stmt_oms->bind_param("i", $id_om_usuario);
}

$stmt_oms->execute();
$res_oms = $stmt_oms->get_result();
while ($r = $res_oms->fetch_assoc()) {
    $oms_visiveis[$r['id']] = $r['abreviatura'] ?: $r['nome'];
}
$stmt_oms->close();

// ===============================
// 🔹 Força download como Excel
// ===============================
header("Content-Type: application/vnd.ms-excel; charset=UTF-8");
header("Content-Disposition: attachment; filename=preventiva_" . date('Ymd_His') . ".xls");
header("Pragma: no-cache");
header("Expires: 0");
echo "\xEF\xBB\xBF"; // UTF-8 BOM

// ===============================
// 🔹 Filtros recebidos
// ===============================
$filtrosGET = [
    'batalhao'       => $_GET['batalhao'] ?? '',
    'id'             => $_GET['id'] ?? '',
    'tipo'           => $_GET['tipo'] ?? '',
    'modelo'         => $_GET['modelo'] ?? '',
    'marca'          => $_GET['marca'] ?? '',
    'ativo'          => $_GET['ativo'] ?? '',
    'prefixo_sga'    => $_GET['prefixo_sga'] ?? '',
    'nmr_patrimonio' => $_GET['nmr_patrimonio'] ?? '',
    'chassi'         => $_GET['chassi'] ?? '',
    'acervo'         => $_GET['acervo'] ?? '',
    'emprego_atual'  => $_GET['emprego_atual'] ?? '',
    'subunidade'     => $_GET['subunidade'] ?? '',
    'disponibilidade'=> $_GET['disponibilidade'] ?? '',
    'confiabilidade' => $_GET['confiabilidade'] ?? '',
    'status_manut'   => $_GET['status_manut'] ?? ''
];

$filtrosSQL = [];
$params = [];
$types = '';

// ===============================
// 🔹 Filtro por batalhão permitido
// ===============================
$batalhaoFiltro = (int)($filtrosGET['batalhao'] ?? 0);
if ($batalhaoFiltro > 0) {
    // Verifica se o batalhão filtrado está na lista de OMs visíveis
    if (!array_key_exists($batalhaoFiltro, $oms_visiveis)) {
        die('Acesso negado ao batalhão selecionado.');
    }
    $filtrosSQL[] = "f.batalhao = ?";
    $params[] = $batalhaoFiltro;
    $types .= 'i';
} else {
    // Nenhum filtro GET → restringe às OMs visíveis
    if (!empty($oms_visiveis)) {
        $ids_oms = implode(',', array_map('intval', array_keys($oms_visiveis)));
        $filtrosSQL[] = "f.batalhao IN ($ids_oms)";
    }
}

// ===============================
// 🔹 Demais filtros
// ===============================
foreach ($filtrosGET as $campo => $valor) {
    if ($valor === '' || $campo === 'status_manut' || $campo === 'batalhao') continue;

    switch ($campo) {
        case 'id':
            $filtrosSQL[] = "f.id = ?";
            $params[] = (int)$valor;
            $types .= 'i';
            break;
        case 'marca':
            $filtrosSQL[] = "f.marca = ?";
            $params[] = (int)$valor;
            $types .= 'i';
            break;
        case 'modelo':
            $filtrosSQL[] = "f.modelo = ?";
            $params[] = (int)$valor;
            $types .= 'i';
            break;
        case 'tipo':
            $filtrosSQL[] = "f.tipo = ?";
            $params[] = $valor;
            $types .= 's';
            break;
        case 'nmr_patrimonio':
        case 'chassi':
        case 'prefixo_sga':
            $filtrosSQL[] = "f.$campo LIKE ?";
            $params[] = "%$valor%";
            $types .= 's';
            break;
        default:
            $filtrosSQL[] = "f.$campo LIKE ?";
            $params[] = "%$valor%";
            $types .= 's';
            break;
    }
}

$whereSQL = !empty($filtrosSQL) ? "WHERE " . implode(" AND ", $filtrosSQL) : "";

// ===============================
// 🔹 Consulta principal
// ===============================
$sql = "SELECT f.*, m.marca AS marca_nome, mo.nome_modelo AS modelo_nome, om.abreviatura AS nome_om
        FROM frota f
        LEFT JOIN config_marcas m ON f.marca = m.id
        LEFT JOIN config_modelos mo ON f.modelo = mo.id
        LEFT JOIN organizacoes_militares om ON f.batalhao = om.id
        $whereSQL
        ORDER BY f.prefixo_sga";

$stmt = $conexao->prepare($sql);
if ($params) $stmt->bind_param($types, ...$params);
$stmt->execute();
$res = $stmt->get_result();

$viaturas = [];
while ($r = $res->fetch_assoc()) $viaturas[] = $r;
$stmt->close();

// ===============================
// 🔹 Calcula manutenção e aplica filtro de status
// ===============================
function normalizeStr(?string $s): string {
    if ($s === null) return '';
    $s = trim($s);
    $trans = @iconv('UTF-8', 'ASCII//TRANSLIT', $s);
    if ($trans !== false) $s = $trans;
    $s = preg_replace('/[^\p{L}\p{N}\s]+/u', '', $s);
    $s = mb_strtolower($s);
    $s = preg_replace('/\s+/', ' ', $s);
    return trim($s);
}

$viaturasFiltradas = [];
$statusFilterNorm = normalizeStr($filtrosGET['status_manut'] ?? '');

foreach ($viaturas as $v) {
    $manut = calcularManutencaoPreventiva($conexao, $v);
    $manut = is_array($manut) ? $manut : [];

    if ($statusFilterNorm !== '') {
        $manutStatusNorm = normalizeStr($manut['status'] ?? '');
        if ($manutStatusNorm === '' || strpos($manutStatusNorm, $statusFilterNorm) === false) {
            continue;
        }
    }

    $v['manut'] = $manut;
    $viaturasFiltradas[] = $v;
}

// ===============================
// 🔹 Gera tabela Excel
// ===============================
echo "<table border='1' style='border-collapse:collapse; font-family:Arial, sans-serif; font-size:12px;'>";
echo "<tr><th colspan='11' style='background:#2E86C1; color:#fff; font-size:14px; padding:8px; text-align:center;'>
        Relatório de Manutenção Preventiva (" . date('d/m/Y H:i') . ")
      </th></tr>";
echo "<tr style='background:#D6EAF8; font-weight:bold; text-align:center;'>
        <th>OM</th>
        <th>Prefixo</th>
        <th>Marca / Modelo</th>
        <th>Status</th>
        <th>Dias Restantes</th>
        <th>Odo Atual</th>
        <th>Km Restante</th>
        <th>Média/dia</th>
        <th>Limite Tempo</th>
        <th>Limite Odo/Hor</th>
        <th>Previsão Odo</th>
      </tr>";

foreach ($viaturasFiltradas as $v) {
    $m = $v['manut'];
    $bg = "#fff";
    $statusNorm = strtolower($m['status'] ?? '');

    if (strpos($statusNorm, 'vencida') !== false) $bg = "#F5B7B1";
    elseif (strpos($statusNorm, 'proxima') !== false) $bg = "#F9E79F";
    elseif (strpos($statusNorm, 'manutencao') !== false) $bg = "#D6EAF8";
    elseif (strpos($statusNorm, 'ok') !== false || strpos($statusNorm, 'em dia') !== false) $bg = "#ABEBC6";

    echo "<tr style='background:$bg'>";
    echo "<td>" . htmlspecialchars($v['nome_om'] ?? '-') . "</td>";
    echo "<td>" . htmlspecialchars($v['prefixo_sga'] ?? '') . "</td>";
    echo "<td>" . htmlspecialchars(($v['marca_nome'] ?? '-') . ' ' . ($v['modelo_nome'] ?? '')) . "</td>";
    echo "<td>" . htmlspecialchars($m['status'] ?? '--') . "</td>";
    echo "<td style='text-align:center;'>" . htmlspecialchars($m['dias_restantes'] ?? '--') . "</td>";
    echo "<td style='text-align:center;'>" . htmlspecialchars($m['odometro_atual'] ?? '--') . "</td>";
    echo "<td style='text-align:center;'>" . htmlspecialchars($m['km_restante'] ?? '--') . "</td>";
    echo "<td style='text-align:center;'>" . (isset($m['km_por_dia']) && is_numeric($m['km_por_dia']) ? round($m['km_por_dia'],2) : '--') . "</td>";
    echo "<td style='text-align:center;'>" . htmlspecialchars($m['data_limite_tempo'] ?? '--') . "</td>";
    echo "<td style='text-align:center;'>" . htmlspecialchars($m['data_limite_odo'] ?? '--') . "</td>";
    echo "<td style='text-align:center;'>" . htmlspecialchars($m['data_prev_odometro'] ?? '--') . "</td>";
    echo "</tr>";
}

echo "</table>";
exit;
