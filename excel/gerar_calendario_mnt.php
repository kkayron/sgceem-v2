<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once '../conexao/config.php';

if (!isset($_SESSION['usuario_id'])) {
    die("Sessão expirada. Faça login novamente.");
}

header("Content-Type: application/vnd.ms-excel; charset=UTF-8");
header("Content-Disposition: attachment; filename=calendario_plano_mnt_" . date('Ymd_His') . ".xls");
header("Pragma: no-cache");
header("Expires: 0");
echo "\xEF\xBB\xBF";

date_default_timezone_set('America/Sao_Paulo');

// ============================
// OMs PERMITIDAS
// ============================
$id_om_usuario = (int)($_SESSION['usuario']['batalhao'] ?? 0);
$nivel_usuario = (int)($_SESSION['usuario']['nivel'] ?? 3);

$oms_visiveis = [];

if ($nivel_usuario === 1) {
    $sqlOms = "SELECT id, nome, abreviatura FROM organizacoes_militares ORDER BY nome";
    $resOms = $conexao->query($sqlOms);
} elseif ($nivel_usuario === 2) {
    $sqlOms = "
        SELECT id, nome, abreviatura FROM organizacoes_militares
        WHERE id = ?
        OR id IN (
            SELECT id_om_menor 
            FROM organizacoes_militares_sub 
            WHERE id_om_maior = ?
        )
        ORDER BY nome
    ";
    $stmtOms = $conexao->prepare($sqlOms);
    $stmtOms->bind_param("ii", $id_om_usuario, $id_om_usuario);
    $stmtOms->execute();
    $resOms = $stmtOms->get_result();
} else {
    $sqlOms = "SELECT id, nome, abreviatura FROM organizacoes_militares WHERE id = ?";
    $stmtOms = $conexao->prepare($sqlOms);
    $stmtOms->bind_param("i", $id_om_usuario);
    $stmtOms->execute();
    $resOms = $stmtOms->get_result();
}

$batalhoesPermitidos = [];

while ($om = $resOms->fetch_assoc()) {
    $id = (int)$om['id'];
    $batalhoesPermitidos[] = $id;
    $oms_visiveis[$id] = $om['abreviatura'] ?: $om['nome'];
}

if (empty($batalhoesPermitidos)) {
    die("Nenhuma OM disponível.");
}

// ============================
// FILTROS DO CONTROLE
// ============================
$id_marca = $_GET['id_marca'] ?? '';
$id_modelo = $_GET['id_modelo'] ?? '';
$tipo_controle = $_GET['tipo_controle'] ?? '';
$status_alerta = $_GET['status_alerta'] ?? '';
$prefixo = trim($_GET['prefixo'] ?? '');
$descricao = trim($_GET['descricao'] ?? '');
$batalhao = $_GET['batalhao'] ?? '';

$filtros = [];
$params = [];
$tipos = '';

if ($id_marca !== '') {
    $filtros[] = "mp.id_marca = ?";
    $params[] = (int)$id_marca;
    $tipos .= 'i';
}

if ($id_modelo !== '') {
    $filtros[] = "mp.id_modelo = ?";
    $params[] = (int)$id_modelo;
    $tipos .= 'i';
}

if ($tipo_controle !== '') {
    $filtros[] = "mp.tipo_controle = ?";
    $params[] = $tipo_controle;
    $tipos .= 's';
}

if ($descricao !== '') {
    $filtros[] = "mp.descricao LIKE ?";
    $params[] = "%{$descricao}%";
    $tipos .= 's';
}

if ($prefixo !== '') {
    $filtros[] = "(f.prefixo_sga LIKE ? OR f.prefixo_velho LIKE ?)";
    $params[] = "%{$prefixo}%";
    $params[] = "%{$prefixo}%";
    $tipos .= 'ss';
}

if ($batalhao !== '') {
    $filtros[] = "f.batalhao = ?";
    $params[] = (int)$batalhao;
    $tipos .= 'i';
}

$placeholders = implode(',', array_fill(0, count($batalhoesPermitidos), '?'));
$filtros[] = "f.batalhao IN ($placeholders)";
$params = array_merge($params, $batalhoesPermitidos);
$tipos .= str_repeat('i', count($batalhoesPermitidos));

$filtros[] = "mp.ativo = 1";

$condicoes = "WHERE " . implode(" AND ", $filtros);

// ============================
// CONSULTA
// ============================
$sql = "
    SELECT 
        mp.id AS id_plano,
        mp.descricao,
        mp.tipo_controle,
        mp.valor_inicial,
        mp.intervalo_valor,
        mp.intervalo_dias,
        mp.alerta_antes_valor,
        mp.alerta_antes_dias,

        marca.marca AS nome_marca,
        modelo.nome_modelo,

        f.id AS id_frota,
        f.prefixo_sga,
        f.prefixo_velho,
        f.disponibilidade,
        f.confiabilidade,
        f.status AS status_frota,
        f.batalhao,

        om.abreviatura AS om_abreviatura,
        om.nome AS om_nome,

        (
            SELECT cmed.odometro
            FROM controle_medicoes cmed
            WHERE cmed.viatura_id = f.id
            ORDER BY cmed.data DESC, cmed.id DESC
            LIMIT 1
        ) AS odometro_atual,

        (
            SELECT cmed.data
            FROM controle_medicoes cmed
            WHERE cmed.viatura_id = f.id
            ORDER BY cmed.data DESC, cmed.id DESC
            LIMIT 1
        ) AS data_medicao,

        (
            SELECT me.odometro_horimetro_execucao
            FROM mnt_execucoes me
            WHERE me.id_plano = mp.id
              AND me.id_frota = f.id
            ORDER BY me.data_execucao DESC, me.id DESC
            LIMIT 1
        ) AS ultima_execucao_valor,

        (
            SELECT me.data_execucao
            FROM mnt_execucoes me
            WHERE me.id_plano = mp.id
              AND me.id_frota = f.id
            ORDER BY me.data_execucao DESC, me.id DESC
            LIMIT 1
        ) AS ultima_execucao_data,

        (
            SELECT me.id_osprincipal
            FROM mnt_execucoes me
            WHERE me.id_plano = mp.id
              AND me.id_frota = f.id
            ORDER BY me.data_execucao DESC, me.id DESC
            LIMIT 1
        ) AS ultima_os

    FROM mnt_planos mp
    INNER JOIN config_marcas marca ON marca.id = mp.id_marca
    INNER JOIN config_modelos modelo ON modelo.id = mp.id_modelo
    INNER JOIN frota f ON f.marca = mp.id_marca AND f.modelo = mp.id_modelo
    LEFT JOIN organizacoes_militares om ON om.id = f.batalhao
    $condicoes
    ORDER BY f.prefixo_sga ASC, mp.descricao ASC
";

$stmt = $conexao->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($tipos, ...$params);
}
$stmt->execute();
$res = $stmt->get_result();

// ============================
// FUNÇÕES
// ============================
function fmtNumExcel($v) {
    if ($v === null || $v === '') return '--';
    return number_format((float)$v, 2, ',', '.');
}

function dataBrExcel($ymd) {
    if (!$ymd || $ymd === '0000-00-00') return '--';
    return date('d/m/Y', strtotime($ymd));
}

function weekKeyMnt($dateYmd) {
    return date('o-\WW', strtotime($dateYmd));
}

function diasEntreHojeExcel($data) {
    if (!$data) return null;
    $hoje = new DateTime(date('Y-m-d'));
    $d = new DateTime($data);
    return (int)$hoje->diff($d)->format('%r%a');
}

function labelTipoControleExcel($tipo) {
    return match ($tipo) {
        'odometro' => 'Odômetro',
        'horimetro' => 'Horímetro',
        'tempo' => 'Tempo',
        'odometro_tempo' => 'Odômetro + Tempo',
        'horimetro_tempo' => 'Horímetro + Tempo',
        default => '--'
    };
}

function calcularStatusMntExcel($row) {
    $tipo = $row['tipo_controle'];

    $usaValor = in_array($tipo, ['odometro', 'horimetro', 'odometro_tempo', 'horimetro_tempo']);
    $usaTempo = in_array($tipo, ['tempo', 'odometro_tempo', 'horimetro_tempo']);

    $statusFinal = 'Em dia';
    $mensagem = [];

    $proximaValor = null;
    $faltaValor = null;
    $proximaData = null;
    $faltaDias = null;

    if ($usaValor) {
        $atual = is_numeric($row['odometro_atual']) ? (float)$row['odometro_atual'] : null;
        $intervalo = is_numeric($row['intervalo_valor']) ? (float)$row['intervalo_valor'] : null;
        $base = is_numeric($row['ultima_execucao_valor']) ? (float)$row['ultima_execucao_valor'] : null;

        if ($base === null && is_numeric($row['valor_inicial'])) {
            $proximaValor = (float)$row['valor_inicial'];
        } elseif ($base !== null && $intervalo !== null) {
            $proximaValor = $base + $intervalo;
        }

        if ($atual === null) {
            $statusFinal = 'Sem medição';
            $mensagem[] = 'Sem odômetro/horímetro atual';
        } elseif ($proximaValor === null) {
            $statusFinal = 'Sem histórico';
            $mensagem[] = 'Sem base para cálculo';
        } else {
            $faltaValor = $proximaValor - $atual;
            $alertaValor = is_numeric($row['alerta_antes_valor']) ? (float)$row['alerta_antes_valor'] : 0;

            if ($faltaValor < 0) {
                $statusFinal = 'Vencida';
                $mensagem[] = 'Vencida há ' . fmtNumExcel(abs($faltaValor));
            } elseif ($faltaValor <= $alertaValor) {
                $statusFinal = 'Próxima';
                $mensagem[] = 'Faltam ' . fmtNumExcel($faltaValor);
            } else {
                $mensagem[] = 'Faltam ' . fmtNumExcel($faltaValor);
            }
        }
    }

    if ($usaTempo) {
        $intervaloDias = is_numeric($row['intervalo_dias']) ? (int)$row['intervalo_dias'] : null;
        $ultimaData = $row['ultima_execucao_data'] ?? null;

        if (!$ultimaData || !$intervaloDias) {
            if ($statusFinal === 'Em dia') {
                $statusFinal = 'Sem histórico';
            }
            $mensagem[] = 'Sem data de última execução';
        } else {
            $proximaData = date('Y-m-d', strtotime($ultimaData . " +{$intervaloDias} days"));
            $faltaDias = diasEntreHojeExcel($proximaData);
            $alertaDias = is_numeric($row['alerta_antes_dias']) ? (int)$row['alerta_antes_dias'] : 0;

            if ($faltaDias < 0) {
                $statusFinal = 'Vencida';
                $mensagem[] = 'Vencida há ' . abs($faltaDias) . ' dias';
            } elseif ($faltaDias <= $alertaDias && $statusFinal !== 'Vencida') {
                $statusFinal = 'Próxima';
                $mensagem[] = 'Faltam ' . $faltaDias . ' dias';
            } else {
                $mensagem[] = 'Faltam ' . $faltaDias . ' dias';
            }
        }
    }

    return [
        'status' => $statusFinal,
        'mensagem' => implode(' | ', $mensagem),
        'proxima_valor' => $proximaValor,
        'falta_valor' => $faltaValor,
        'proxima_data' => $proximaData,
        'falta_dias' => $faltaDias
    ];
}

function dataPrevistaCalendario($calc) {
    if (!empty($calc['proxima_data'])) {
        return $calc['proxima_data'];
    }

    return null;
}

function omNomeExcel($batalhao, $oms_visiveis) {
    if ($batalhao === '') return 'Todas';
    $id = (int)$batalhao;
    return $oms_visiveis[$id] ?? 'OM filtrada';
}

// ============================
// SEMANAS
// ============================
$hoje = date('Y-m-d');
$inicioSemanaAtual = date('Y-m-d', strtotime('monday this week', strtotime($hoje)));
$semanasExibir = 52;

$weeks = [];
$mesColspan = [];

for ($i = 0; $i < $semanasExibir; $i++) {
    $ini = date('Y-m-d', strtotime("+{$i} week", strtotime($inicioSemanaAtual)));
    $fim = date('Y-m-d', strtotime("+6 day", strtotime($ini)));
    $mes = date('m', strtotime($ini));
    $ano = date('Y', strtotime($ini));

    $weeks[] = [
        'i' => $i,
        'ini' => $ini,
        'fim' => $fim,
        'ano' => $ano,
        'mes' => $mes,
        'key' => weekKeyMnt($ini),
    ];

    $k = $ano . '-' . $mes;
    $mesColspan[$k] = ($mesColspan[$k] ?? 0) + 1;
}

$mesNome = [
    "01"=>"JAN","02"=>"FEV","03"=>"MAR","04"=>"ABR","05"=>"MAI","06"=>"JUN",
    "07"=>"JUL","08"=>"AGO","09"=>"SET","10"=>"OUT","11"=>"NOV","12"=>"DEZ"
];

// ============================
// MONTA ITENS
// ============================
$itens = [];

while ($row = $res->fetch_assoc()) {
    $calc = calcularStatusMntExcel($row);

    if ($status_alerta !== '' && $calc['status'] !== $status_alerta) {
        continue;
    }

    $dataPrev = dataPrevistaCalendario($calc);

    $itens[] = [
        'prefixo' => $row['prefixo_sga'] ?: $row['prefixo_velho'] ?: 'Sem prefixo',
        'om' => $row['om_abreviatura'] ?: $row['om_nome'] ?: '--',
        'plano' => $row['descricao'],
        'marca_modelo' => $row['nome_marca'] . ' / ' . $row['nome_modelo'],
        'tipo_controle' => labelTipoControleExcel($row['tipo_controle']),
        'status' => $calc['status'],
        'mensagem' => $calc['mensagem'],
        'data_prevista' => $dataPrev,
        'atual' => $row['odometro_atual'],
        'ultima_execucao' => $row['ultima_execucao_valor'],
        'ultima_execucao_data' => $row['ultima_execucao_data'],
        'proxima_valor' => $calc['proxima_valor'],
        'ultima_os' => $row['ultima_os'],
    ];
}

usort($itens, function($a, $b) {
    $prio = function($st) {
        return match ($st) {
            'Vencida' => 1,
            'Próxima' => 2,
            'Sem medição' => 3,
            'Sem histórico' => 4,
            'Em dia' => 5,
            default => 9
        };
    };

    $pa = $prio($a['status']);
    $pb = $prio($b['status']);

    if ($pa !== $pb) return $pa <=> $pb;

    $da = $a['data_prevista'] ?: '9999-12-31';
    $db = $b['data_prevista'] ?: '9999-12-31';

    if ($da !== $db) return strcmp($da, $db);

    return strcmp($a['prefixo'], $b['prefixo']);
});

// ============================
// HTML XLS
// ============================
$C_VERDE = "#58D68D";
$C_AMARELO = "#F4D03F";
$C_VERMELHO = "#F1948A";
$C_CINZA = "#F2F3F4";
$C_AZUL = "#85C1E9";

$omLabel = omNomeExcel($batalhao, $oms_visiveis);
$colunasFixas = 9;
$totalCols = count($weeks) + $colunasFixas;

echo "<table border='1' style='border-collapse:collapse; font-family:Arial; font-size:12px;'>";

echo "<tr>
  <th colspan='{$totalCols}' style='background:#0d6efd; color:#fff; font-size:16px; padding:10px; text-align:center;'>
    CALENDÁRIO — PLANOS DE MANUTENÇÃO AGENDADA | OM: " . htmlspecialchars($omLabel) . "
  </th>
</tr>";

echo "<tr>
  <td colspan='{$totalCols}' style='padding:8px; background:#FAFAFA;'>
    <strong>Gerado em:</strong> " . date('d/m/Y H:i') . " &nbsp; | &nbsp;
    <strong>Semana atual:</strong> " . date('d/m/Y', strtotime($inicioSemanaAtual)) . " a " . date('d/m/Y', strtotime("+6 day", strtotime($inicioSemanaAtual))) . "
    <br><br>
    <strong>Legenda:</strong>
    <span style='background:$C_VERDE; border:1px solid #333;'>&nbsp;&nbsp;&nbsp;</span> <strong>M</strong> Semana da manutenção &nbsp;&nbsp;
    <span style='background:$C_AMARELO; border:1px solid #333;'>&nbsp;&nbsp;&nbsp;</span> <strong>P</strong> 3 semanas anteriores &nbsp;&nbsp;
    <span style='background:$C_VERMELHO; border:1px solid #333;'>&nbsp;&nbsp;&nbsp;</span> Vencida &nbsp;&nbsp;
    <span style='background:$C_AZUL; border:1px solid #333;'>&nbsp;&nbsp;&nbsp;</span> Próxima sem data calculada &nbsp;&nbsp;
    <span style='background:$C_CINZA; border:1px solid #333;'>&nbsp;&nbsp;&nbsp;</span> Sem data
  </td>
</tr>";

echo "<tr style='text-align:center; font-weight:bold;'>";
echo "<th rowspan='3' style='background:#D6EAF8; width:120px;'>Prefixo</th>";
echo "<th rowspan='3' style='background:#D6EAF8; width:100px;'>OM</th>";
echo "<th rowspan='3' style='background:#D6EAF8; width:220px;'>Plano</th>";
echo "<th rowspan='3' style='background:#D6EAF8; width:160px;'>Marca/Modelo</th>";
echo "<th rowspan='3' style='background:#D6EAF8; width:110px;'>Tipo</th>";
echo "<th rowspan='3' style='background:#D6EAF8; width:120px;'>Data Prevista</th>";
echo "<th rowspan='3' style='background:#D6EAF8; width:110px;'>Status</th>";
echo "<th rowspan='3' style='background:#D6EAF8; width:160px;'>Situação</th>";
echo "<th rowspan='3' style='background:#D6EAF8; width:90px;'>Próx. Valor</th>";

foreach ($mesColspan as $key => $span) {
    [$anoM, $mesM] = explode("-", $key);
    $label = ($mesNome[$mesM] ?? $mesM) . " / $anoM";
    echo "<th colspan='{$span}' style='background:#AED6F1; padding:6px;'>{$label}</th>";
}
echo "</tr>";

echo "<tr style='background:#EBF5FB; text-align:center; font-weight:bold;'>";
foreach ($weeks as $w) {
    echo "<th style='padding:4px;'>S" . ($w['i'] + 1) . "</th>";
}
echo "</tr>";

echo "<tr style='background:#F8F9F9; text-align:center; font-size:10px;'>";
foreach ($weeks as $w) {
    echo "<th style='padding:4px;'>" . date('d/m', strtotime($w['ini'])) . "–" . date('d/m', strtotime($w['fim'])) . "</th>";
}
echo "</tr>";

if (empty($itens)) {
    echo "<tr><td colspan='{$totalCols}' style='padding:10px; color:#777;'>Nenhum item encontrado com os filtros aplicados.</td></tr>";
    echo "</table>";
    exit;
}

foreach ($itens as $it) {
    $dataPrev = $it['data_prevista'];
    $dueWeekIndex = null;

    if ($dataPrev) {
        $dueWeekKey = weekKeyMnt($dataPrev);
        foreach ($weeks as $w) {
            if ($w['key'] === $dueWeekKey) {
                $dueWeekIndex = (int)$w['i'];
                break;
            }
        }
    }

    $status = $it['status'];
    $isVencida = $status === 'Vencida';
    $isProxima = $status === 'Próxima';

    $statusBg = match ($status) {
        'Vencida' => $C_VERMELHO,
        'Próxima' => $C_AMARELO,
        'Em dia' => $C_VERDE,
        default => $C_CINZA
    };

    echo "<tr>";
    echo "<td style='padding:6px; font-weight:bold; white-space:nowrap;'>" . htmlspecialchars($it['prefixo']) . "</td>";
    echo "<td style='padding:6px;'>" . htmlspecialchars($it['om']) . "</td>";
    echo "<td style='padding:6px;'>" . htmlspecialchars($it['plano']) . "</td>";
    echo "<td style='padding:6px;'>" . htmlspecialchars($it['marca_modelo']) . "</td>";
    echo "<td style='padding:6px;'>" . htmlspecialchars($it['tipo_controle']) . "</td>";
    echo "<td style='padding:6px; text-align:center;'>" . dataBrExcel($dataPrev) . "</td>";
    echo "<td style='padding:6px; background:$statusBg; font-weight:bold;'>" . htmlspecialchars($status) . "</td>";
    echo "<td style='padding:6px;'>" . htmlspecialchars($it['mensagem']) . "</td>";
    echo "<td style='padding:6px; text-align:center;'>" . fmtNumExcel($it['proxima_valor']) . "</td>";

    foreach ($weeks as $w) {
        $bg = "";
        $txt = "";

        if ($w['i'] === 0 && $isVencida) {
            $bg = $C_VERMELHO;
        }

        if ($bg === "" && $dueWeekIndex !== null) {
            if ($w['i'] === $dueWeekIndex) {
                $bg = $C_VERDE;
                $txt = "M";
            } elseif ($w['i'] >= ($dueWeekIndex - 3) && $w['i'] < $dueWeekIndex) {
                $bg = $C_AMARELO;
                $txt = "P";
            }
        }

        if ($bg === "" && $isProxima && $dueWeekIndex === null && $w['i'] === 0) {
            $bg = $C_AZUL;
            $txt = "P";
        }

        if ($bg === "" && !$dataPrev) {
            $bg = $C_CINZA;
        }

        echo "<td style='height:18px; min-width:18px; background:$bg; text-align:center; font-weight:bold;'>$txt</td>";
    }

    echo "</tr>";
}

echo "</table>";
exit;
?>