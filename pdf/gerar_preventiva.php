<?php
// gerar_preventiva.php
require_once '../conexao/config.php';
require_once 'vendor/autoload.php'; // Dompdf via composer
require_once '../includes/preventiva/calculo_manutencao.php'; // Função calcularManutencaoPreventiva

use Dompdf\Dompdf;

session_start();

// -----------------------------
// 🔹 Captura dados da sessão
// -----------------------------
$id_om_usuario = (int) ($_SESSION['usuario']['batalhao'] ?? 0);
$nivel_usuario = (int) ($_SESSION['usuario']['nivel'] ?? 3); // padrão restrito

// -----------------------------
// 🔹 Monta lista de OMs acessíveis
// -----------------------------
$oms_visiveis = []; // id => abreviatura|nome

if ($nivel_usuario === 1) {
    // Nível 1 → todas as OMs
    $resAll = $conexao->query("SELECT id, nome, abreviatura FROM organizacoes_militares ORDER BY nome");
    while ($r = $resAll->fetch_assoc()) {
        $oms_visiveis[(int)$r['id']] = $r['abreviatura'] ?: $r['nome'];
    }
} elseif ($nivel_usuario === 2) {
    // Nível 2 → sua OM + subordinadas (usa prepared)
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
    $stmt_oms->execute();
    $res_oms = $stmt_oms->get_result();
    while ($r = $res_oms->fetch_assoc()) {
        $oms_visiveis[(int)$r['id']] = $r['abreviatura'] ?: $r['nome'];
    }
    $stmt_oms->close();
} else {
    // Nível 3 → apenas sua OM
    if ($id_om_usuario > 0) {
        $stmt_oms = $conexao->prepare("SELECT id, nome, abreviatura FROM organizacoes_militares WHERE id = ?");
        $stmt_oms->bind_param("i", $id_om_usuario);
        $stmt_oms->execute();
        $res_oms = $stmt_oms->get_result();
        while ($r = $res_oms->fetch_assoc()) {
            $oms_visiveis[(int)$r['id']] = $r['abreviatura'] ?: $r['nome'];
        }
        $stmt_oms->close();
    }
}

// Se por algum motivo não há OMs visíveis (usuário completamente sem OM), não deixa consultar tudo
if (empty($oms_visiveis)) {
    // evita vazamento: resultado vazio
    $oms_visiveis = [];
}

// -------------------------------------------------
// Helper: normaliza string (remove acentos, lower)
// -------------------------------------------------
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

// ================= FILTROS RECEBIDOS ===================
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
    'status_manut'   => $_GET['status_manut'] ?? '' // aplicado APÓS cálculo
];

// --- montar WHERE preparado (exceto status_manut) ---
$filtrosSQL = [];
$params = [];
$types = '';

// --------------------
// 🔹 BATALHÃO (GET + verificação)
// --------------------
if (!empty($filtrosGET['batalhao'])) {
    $b_sel = (int)$filtrosGET['batalhao'];
    if (!array_key_exists($b_sel, $oms_visiveis)) {
        // Batalhão solicitado não está entre os visíveis -> negar
        die('Acesso negado: batalhão não autorizado.');
    }
    $filtrosSQL[] = 'f.batalhao = ?';
    $params[] = $b_sel;
    $types .= 'i';
} else {
    // sem filtro manual: restringe à OMs visíveis (se houver)
    if (!empty($oms_visiveis)) {
        $ids = array_map('intval', array_keys($oms_visiveis));
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $filtrosSQL[] = "f.batalhao IN ($placeholders)";
        foreach ($ids as $idv) { $params[] = $idv; $types .= 'i'; }
    } else {
        // nenhum OM visível => força resultado vazio com condição impossível
        $filtrosSQL[] = "1=0";
    }
}

// --------------------
// 🔹 Demais filtros
// --------------------
foreach ($filtrosGET as $campo => $valor) {
    if ($valor === '' || $campo === 'status_manut' || $campo === 'batalhao') continue;

    switch ($campo) {
        case 'id':
        case 'marca':
        case 'modelo':
            $filtrosSQL[] = "f.$campo = ?";
            $params[] = (int)$valor;
            $types .= 'i';
            break;

        case 'tipo':
            $filtrosSQL[] = "f.tipo = ?";
            $params[] = $valor;
            $types .= 's';
            break;

        case 'prefixo_sga':
        case 'nmr_patrimonio':
        case 'chassi':
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

$whereSQL = !empty($filtrosSQL) ? 'WHERE ' . implode(' AND ', $filtrosSQL) : '';

// ================= QUERY VIATURAS ===================
$sql = "SELECT f.*, m.marca AS marca_nome, mo.nome_modelo AS modelo_nome, om.nome AS nome_om, om.abreviatura AS sigla_om
        FROM frota f
        LEFT JOIN config_marcas m ON f.marca = m.id
        LEFT JOIN config_modelos mo ON f.modelo = mo.id
        LEFT JOIN organizacoes_militares om ON om.id = f.batalhao
        $whereSQL
        ORDER BY om.nome, f.prefixo_sga";

$stmt = $conexao->prepare($sql);
if ($params) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$res = $stmt->get_result();
$viaturas = [];
while ($r = $res->fetch_assoc()) $viaturas[] = $r;
$stmt->close();

// ================= CALCULA MANUTENÇÃO E APLICA FILTRO DE STATUS ===================
$viaturasFiltradas = [];
$statusFilterRaw = $filtrosGET['status_manut'] ?? '';
$statusFilterNorm = normalizeStr($statusFilterRaw);

foreach ($viaturas as $v) {
    $manut = calcularManutencaoPreventiva($conexao, $v);
    $manut = is_array($manut) ? $manut : [];
    $manut += [
        'status' => '--',
        'dias_restantes' => '--',
        'odometro_atual' => '--',
        'km_restante' => '--',
        'km_por_dia' => '--',
        'data_limite_tempo' => '--',
        'data_limite_odo' => '--',
        'data_prev_odometro' => '--',
        'os_em_andamento' => false,
        'os_realizado_resumo' => ''
    ];

    if ($statusFilterNorm !== '') {
        $manutStatusNorm = normalizeStr($manut['status'] ?? '');
        if ($manutStatusNorm === '' || strpos($manutStatusNorm, $statusFilterNorm) === false) {
            continue;
        }
    }

    $v['manut'] = $manut;
    $viaturasFiltradas[] = $v;
}

// ================= Monta string com filtros ativos (para exibir no PDF) ===================
$filtrosAtivos = [];
foreach ($filtrosGET as $ch => $val) {
    if ($val === '' || $ch === 'status_manut') continue;
    $label = $ch;
    $display = $val;
    if ($ch === 'marca' && is_numeric($val)) {
        $qr = $conexao->prepare("SELECT marca FROM config_marcas WHERE id = ? LIMIT 1");
        $qr->bind_param('i', $val);
        $qr->execute();
        $resm = $qr->get_result()->fetch_assoc();
        $qr->close();
        if (!empty($resm['marca'])) $display = $resm['marca'];
    }
    if ($ch === 'modelo' && is_numeric($val)) {
        $qr = $conexao->prepare("SELECT nome_modelo FROM config_modelos WHERE id = ? LIMIT 1");
        $qr->bind_param('i', $val);
        $qr->execute();
        $resm = $qr->get_result()->fetch_assoc();
        $qr->close();
        if (!empty($resm['nome_modelo'])) $display = $resm['nome_modelo'];
    }
    if ($ch === 'tipo') {
        $display = $val === 'Vtr' ? 'Viatura' : ($val === 'Eqp' ? 'Equipamento' : $val);
    }
    if ($ch === 'batalhao' && is_numeric($val)) {
        $display = $oms_visiveis[(int)$val] ?? $val;
    }
    $filtrosAtivos[] = ucfirst(str_replace('_',' ',$label)) . ': ' . $display;
}
if ($statusFilterRaw !== '') $filtrosAtivos[] = 'Status: ' . $statusFilterRaw;
$filtrosStr = !empty($filtrosAtivos) ? implode(' | ', $filtrosAtivos) : 'Sem filtros';

// ================= HTML DO RELATÓRIO ===================
$nomeRelatorio = "Relatorio_Manutencao_Preventiva_" . date('Ymd_His');
$html = '<!doctype html><html><head><meta charset="utf-8">';
$html .= '<style>
body { font-family:"Segoe UI",Roboto,Arial,sans-serif; font-size:12px; color:#222; margin:20px; background:#f8f9fa; }
h2 { color:#0d6efd; margin-bottom:5px; }
.small { font-size:11px; color:#6c757d; }
table { width:100%; border-collapse:collapse; margin-top:10px; }
th, td { border:1px solid #dee2e6; padding:6px; text-align:left; vertical-align:middle; }
th { background:#0d6efd; color:#fff; font-weight:600; font-size:12px; }
td img { max-width:80px; max-height:60px; object-fit:cover; border-radius:4px; }
.badge { padding:3px 8px; border-radius:999px; font-weight:500; font-size:11px; display:inline-block; text-align:center; min-width:60px; }
.badge.success { background:#198754; color:#fff; }
.badge.warning { background:#ffc107; color:#000; }
.badge.danger { background:#dc3545; color:#fff; }
.badge.dark { background:#6c757d; color:#fff; }
.footer-note { text-align:center; font-size:11px; color:#6c757d; margin-top:15px; }
</style></head><body>';

$html .= '<h2>Relatório de Manutenção Preventiva</h2>';
$html .= '<p class="small">Gerado em '.date('d/m/Y H:i').' | Total de viaturas: '.count($viaturasFiltradas).'</p>';
$html .= '<p class="small"><strong>Filtros:</strong> '.htmlspecialchars($filtrosStr).'</p>';

// Tabela
$html .= '<table>';
$html .= '<thead>
<tr>
<th>Batalhão</th>
<th>Prefixo</th>
<th>Marca / Modelo</th>
<th>Status</th>
<th>Dias Restantes</th>
<th>Odo Atual</th>
<th>Faltam (km)</th>
<th>Média/dia</th>
<th>Próximos Limites</th>
<th>OS Resumo</th>
</tr>
</thead><tbody>';

if (empty($viaturasFiltradas)) {
    $html .= '<tr><td colspan="10" style="text-align:center;">Nenhuma viatura/equipamento para este filtro.</td></tr>';
} else {
    foreach ($viaturasFiltradas as $v) {
        $m = $v['manut'];
        $statusLower = normalizeStr($m['status'] ?? '');
        if (strpos($statusLower, 'vencid') !== false) $badgeClass='danger';
        elseif (strpos($statusLower, 'muito') !== false) $badgeClass='warning';
        elseif ($m['os_em_andamento']) $badgeClass='dark';
        elseif (strpos($statusLower, 'dia') !== false) $badgeClass='success';
        else $badgeClass='dark';

        $diasRest = is_numeric($m['dias_restantes']) ? $m['dias_restantes'].' dias' : ($m['dias_restantes'] ?? '--');
        $odometroAtual = $m['odometro_atual'] ?? '--';
        $kmRestante = $m['km_restante'] ?? '--';
        $kmPorDia = isset($m['km_por_dia']) && is_numeric($m['km_por_dia']) ? round($m['km_por_dia'],2) : '--';

        $dataLimiteTempo = $m['data_limite_tempo'] ?? '--';
        $dataLimiteOdo = $m['data_limite_odo'] ?? '--';
        $dataPrevOdo = $m['data_prev_odometro'] ?? '--';

        $marcaNome = htmlspecialchars($v['marca_nome'] ?? '-');
        $modeloNome = htmlspecialchars($v['modelo_nome'] ?? '');
        $prefixo = htmlspecialchars($v['prefixo_sga'] ?? '-');
        $nomeOm = htmlspecialchars($v['sigla_om'] ?? $v['nome_om'] ?? '-');
        $osResumo = htmlspecialchars($m['os_realizado_resumo'] ?? '');

        $img = !empty($v['foto_capa']) 
            ? htmlspecialchars((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . dirname($_SERVER['SCRIPT_NAME']) . '/../uploads/frotas/' . rawurlencode($v['foto_capa']))
            : '';

        $html .= '<tr>';
        $html .= '<td>'.$nomeOm.'</td>';
        $html .= '<td>'.$prefixo.'</td>';
        $html .= '<td>'.$marcaNome.' '.$modeloNome.'</td>';
        $html .= '<td><span class="badge '.$badgeClass.'">'.htmlspecialchars($m['status']).'</span></td>';
        $html .= '<td>'.$diasRest.'</td>';
        $html .= '<td>'.$odometroAtual.'</td>';
        $html .= '<td>'.$kmRestante.'</td>';
        $html .= '<td>'.$kmPorDia.'</td>';
        $html .= '<td>Tempo: '.$dataLimiteTempo.'<br>Odo/Hor: '.$dataLimiteOdo.'<br>Prev. Odo: '.$dataPrevOdo.'</td>';
        $html .= '<td>'.$osResumo.'</td>';
        $html .= '</tr>';
    }
}

$html .= '</tbody></table>';
$html .= '<div class="footer-note">Relatório gerado automaticamente. Dados sujeitos à verificação em sistema. GCEEM 2.0</div>';
$html .= '</body></html>';

// ================= GERAR PDF ===================
$dompdf = new Dompdf(['enable_remote'=>true]);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4','landscape');
$dompdf->render();
$filename = $nomeRelatorio ?? 'Relatorio_Preventiva';
$dompdf->stream($filename.'.pdf', ['Attachment'=>false]);
exit;
