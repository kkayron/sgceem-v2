<?php
/*******************************************************************************
 * GERAR CALENDÁRIO DE PREGÕES – EXCEL (organizado e corrigido)
 * - Usa filtros idênticos à listagem (id, nmr_pregao, ano_pregao, ug, uasg, tipo)
 * - Filtra por data_homologacao (data_ini / data_fim)
 * - Respeita permissão por batalhão (níveis 1,2,3)
 * - Geração do calendário: ano atual + ano seguinte
 * - Saída XLS (compatible Excel)
 *******************************************************************************/

session_start();
require_once '../conexao/config.php';

// ----------------------------
// Header para Excel
// ----------------------------
header("Content-Type: application/vnd.ms-excel; charset=UTF-8");
header("Content-Disposition: attachment; filename=calendario_pregoes_" . date('Ymd_His') . ".xls");
header("Pragma: no-cache");
header("Expires: 0");
echo "\xEF\xBB\xBF"; // BOM para UTF-8/Excel

// ----------------------------
// Dados do usuário / permissão
// ----------------------------
$usuario          = $_SESSION['usuario'] ?? [];
$nivel_usuario    = $usuario['nivel'] ?? 3;
$batalhao_usuario = $usuario['batalhao'] ?? null;

if (!$batalhao_usuario) {
    die("Erro: Não foi possível identificar o batalhão do usuário.");
}

// ----------------------------
// Recebimento dos filtros (mesma lógica da listagem)
// ----------------------------
$camposFiltro = [
    "id",
    "nmr_pregao",
    "ano_pregao",
    "ug_licitacao",
    "uasg_licitacao",
    "tipo_pregao"
];

$filters = [];
$params  = [];
$types   = "";

// Campos padrão (LIKE)
foreach ($camposFiltro as $campo) {
    if (!empty($_GET[$campo])) {
        $filters[] = "{$campo} LIKE ?";
        $params[]  = "%{$_GET[$campo]}%";
        $types    .= "s";
    }
}

// Datas (data_homologacao)
if (!empty($_GET['data_ini'])) {
    $filters[] = "data_homologacao >= ?";
    $params[]  = $_GET['data_ini'];
    $types    .= "s";
}
if (!empty($_GET['data_fim'])) {
    $filters[] = "data_homologacao <= ?";
    $params[]  = $_GET['data_fim'];
    $types    .= "s";
}

$batalhao_filtro = $_GET['batalhao'] ?? "";

// ----------------------------
// Controle de acesso por nível (mesma lógica da listagem)
// ----------------------------
if ($nivel_usuario == 1) {
    // Admin vê todos; se filtrou por batalhão, aplica
    if (!empty($batalhao_filtro)) {
        $filters[] = "batalhao = ?";
        $params[]  = (int)$batalhao_filtro;
        $types    .= "i";
    }

} elseif ($nivel_usuario == 2) {
    // N2: próprio batalhão + subordinados
    $sqlSubs = "SELECT id_om_menor FROM organizacoes_militares_sub WHERE id_om_maior = ?";
    $stmtSubs = $conexao->prepare($sqlSubs);
    $stmtSubs->bind_param("i", $batalhao_usuario);
    $stmtSubs->execute();
    $resultSubs = $stmtSubs->get_result();

    $batalhoesPermitidos = [$batalhao_usuario];
    while ($sub = $resultSubs->fetch_assoc()) {
        $batalhoesPermitidos[] = $sub['id_om_menor'];
    }

    if (!empty($batalhao_filtro)) {
        if (!in_array((int)$batalhao_filtro, $batalhoesPermitidos)) {
            die("Acesso negado ao batalhão selecionado.");
        }
        $filters[] = "batalhao = ?";
        $params[]  = (int)$batalhao_filtro;
        $types    .= "i";
    } else {
        $placeholders = implode(",", array_fill(0, count($batalhoesPermitidos), "?"));
        $filters[] = "batalhao IN ($placeholders)";
        // merge mantendo ordem
        $params = array_merge($params, $batalhoesPermitidos);
        $types .= str_repeat("i", count($batalhoesPermitidos));
    }

} else {
    // N3: só o próprio batalhão
    $filters[] = "batalhao = ?";
    $params[]  = $batalhao_usuario;
    $types    .= "i";
}

// WHERE final
$where = "";
if (!empty($filters)) {
    $where = "WHERE " . implode(" AND ", $filters);
}

// ----------------------------
// Buscar pregões (mesma ordenação da listagem)
// ----------------------------
$sql = "
    SELECT 
        nmr_pregao,
        ano_pregao,
        descricao_pregao,
        data_homologacao,
        data_validade
    FROM fin_pregao
    $where
    ORDER BY ano_pregao ASC, nmr_pregao ASC
";

$stmt = $conexao->prepare($sql);
if ($stmt === false) {
    die("Erro ao preparar consulta: " . $conexao->error);
}
if (!empty($params)) {
    // bind dinâmico
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$pregoes = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// ----------------------------
// Configuração do calendário: ano atual + ano seguinte
// (Usamos a mesma lógica do código antigo que funcionava)
// ----------------------------
$anoAtual = date("Y");
$anoSeguinte = $anoAtual + 1;

$inicioCalendario = strtotime("$anoAtual-01-01");
$fimCalendario    = strtotime("$anoSeguinte-12-31");

$semanas = [];
$dt = $inicioCalendario;

// Gera semanas acumulando dias (cada entrada $semanas[$ano][$sem]['dias'][] é um dia)
while ($dt <= $fimCalendario) {
    $ano = date("Y", $dt);
    $sem = (int)date("W", $dt); // ISO week number
    $mes = date("m", $dt);

    if (!isset($semanas[$ano][$sem])) {
        $semanas[$ano][$sem] = [
            'mes' => $mes,
            'dias' => []
        ];
    }

    $semanas[$ano][$sem]['dias'][] = [
        'data' => date("Y-m-d", $dt),
        'dow'  => (int)date("w", $dt) // 0 (domingo) .. 6 (sábado)
    ];

    $dt = strtotime("+1 day", $dt);
}

// Flatten – mantém ordem por ano então por semana (preserva comportamento do antigo)
$semanasLin = [];
$mesColspan = [];

foreach ([$anoAtual, $anoSeguinte] as $ano) {
    if (!isset($semanas[$ano])) continue;

    foreach ($semanas[$ano] as $numSemana => $info) {
        $semanasLin[] = [
            'ano' => $ano,
            'semana' => $numSemana,
            'mes' => $info['mes'],
            'dias' => $info['dias']
        ];

        $key = "$ano-" . $info['mes'];
        $mesColspan[$key] = ($mesColspan[$key] ?? 0) + 1;
    }
}

$totalSemanas = count($semanasLin);

// Nomes dos meses
$mesNome = [
    "01"=>"JAN","02"=>"FEV","03"=>"MAR","04"=>"ABR","05"=>"MAI","06"=>"JUN",
    "07"=>"JUL","08"=>"AGO","09"=>"SET","10"=>"OUT","11"=>"NOV","12"=>"DEZ"
];

// ----------------------------
// Montagem da tabela Excel (HTML table que o Excel abre)
// ----------------------------
echo "<table border='1' style='border-collapse:collapse; font-family:Arial; font-size:12px;'>";

// Título
echo "<tr>
        <th colspan='".($totalSemanas + 4)."' style='background:#2471A3; color:#fff; font-size:16px; padding:8px; text-align:center;'>
            CALENDÁRIO DE VIGÊNCIA – $anoAtual / $anoSeguinte
        </th>
      </tr>";

// Linha dos meses (colspan por quantidade de semanas dentro do mês)
echo "<tr style='background:#D6EAF8; font-weight:bold; text-align:center;'>";
echo "<th rowspan='4' style='width:320px;'>Pregão</th>";
echo "<th rowspan='4' style='width:110px;'>Homologação</th>";
echo "<th rowspan='4' style='width:110px;'>Validade</th>";
echo "<th rowspan='4' style='width:25px;'>Dia<br>Sem.</th>";

foreach ($mesColspan as $key => $span) {
    [$anoM, $mesM] = explode("-", $key);
    $mesM_padded = str_pad($mesM, 2, "0", STR_PAD_LEFT);
    $label = ($mesNome[$mesM_padded] ?? $mesM_padded) . " / $anoM";
    echo "<th colspan='{$span}' style='background:#AED6F1;'>{$label}</th>";
}
echo "</tr>";

// Segunda linha — Semanas S1, S2...
echo "<tr style='background:#EBF5FB; text-align:center; font-weight:bold;'>";
foreach ($semanasLin as $i => $sem) {
    echo "<th>S".($i+1)."</th>";
}
echo "</tr>";

// Terceira linha — dias numéricos (cada semana mostra os dias que pertencem a ela)
echo "<tr style='background:#F2F4F4; text-align:center; font-size:10px;'>";
foreach ($semanasLin as $sem) {
    echo "<th>";
    foreach ($sem['dias'] as $d) {
        echo date("d", strtotime($d['data'])) . "<br>";
    }
    echo "</th>";
}
echo "</tr>";

// Quarta linha — dias da semana (D,S,T,Q,Q,S,S)
$diasNomes = ["D","S","T","Q","Q","S","S"];
echo "<tr style='background:#F8F9F9; text-align:center; font-size:10px;'>";
foreach ($semanasLin as $sem) {
    echo "<th>";
    foreach ($sem['dias'] as $d) {
        echo $diasNomes[$d['dow']] . "<br>";
    }
    echo "</th>";
}
echo "</tr>";

// ----------------------------
// Linhas dos pregões com coloração das células conforme período
// - Verde claro => vigente e fora do intervalo de 90 dias finais
// - Amarelo    => dentro de 90 dias antes do fim
// - Vermelho   => fora do período (sem validade ou fora do range)
// ----------------------------
foreach ($pregoes as $p) {

    $homolog = $p['data_homologacao'];
    $valid   = $p['data_validade'];

    // normaliza datas para comparação
    $inicio = $homolog ? $homolog : date("Y-m-d", $inicioCalendario);
    $fim    = $valid  ? $valid  : date("Y-m-d", $fimCalendario);

    $tresMesesAntes = $valid ? date("Y-m-d", strtotime("-90 days", strtotime($valid))) : null;

    $titulo = "{$p['nmr_pregao']}/{$p['ano_pregao']}";
    if (!empty($p['descricao_pregao'])) {
        $titulo .= " – " . htmlspecialchars($p['descricao_pregao']);
    }

    echo "<tr>";
    echo "<td style='font-weight:bold; padding-left:6px;'>$titulo</td>";
    echo "<td>".($homolog && $homolog !== "0000-00-00" ? date("d/m/Y", strtotime($homolog)) : "--")."</td>";
    echo "<td>".($valid && $valid !== "0000-00-00" ? date("d/m/Y", strtotime($valid)) : "--")."</td>";
    echo "<td style='text-align:center; background:#FCF3CF; font-weight:bold;'>S</td>";

    foreach ($semanasLin as $sem) {

        $diaMin = $sem['dias'][0]['data'];
        $diaMax = end($sem['dias'])['data'];

        // Se não há validade definida, pinta vermelho (fora)
        if (!$valid || $valid === "0000-00-00") {
            echo "<td style='background:#F1948A;'></td>";
            continue;
        }

        // Se a semana está inteiramente fora do intervalo [inicio..fim]
        if ($diaMax < $inicio || $diaMin > $fim) {
            echo "<td style='background:#F1948A;'></td>";
            continue;
        }

        // Se a semana cruza o período de 90 dias finais (tresMesesAntes)
        if ($tresMesesAntes && $diaMax >= $tresMesesAntes) {
            echo "<td style='background:#F9E79F;'></td>";
        } else {
            echo "<td style='background:#ABEBC6;'></td>";
        }
    }

    echo "</tr>";
}

echo "</table>";
exit;
?>
