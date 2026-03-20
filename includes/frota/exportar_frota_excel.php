<?php

session_start();

require_once '../../conexao/config.php';

// ========================================
// VERIFICA LOGIN
// ========================================
if (!isset($_SESSION['usuario_id']) || empty($_SESSION['usuario'])) {
    die('Sessão inválida.');
}

// ========================================
// VERIFICA PERMISSÃO
// ========================================
$pagina_id = 2;

if (empty($_SESSION['permissoes'][$pagina_id]['pode_exportar'])) {
    die('Você não possui permissão para exportar.');
}

// ========================================
// HEADERS EXCEL
// ========================================
header("Content-Type: application/vnd.ms-excel; charset=UTF-8");
header("Content-Disposition: attachment; filename=frota_" . date('Y-m-d_H-i') . ".xls");
header("Pragma: no-cache");
header("Expires: 0");

echo "\xEF\xBB\xBF"; // UTF8 BOM

// ========================================
// DADOS DO USUÁRIO
// ========================================
$id_om_usuario = $_SESSION['usuario']['batalhao'] ?? null;
$nivel_usuario = $_SESSION['usuario']['nivel'] ?? null;

// ========================================
// FILTROS
// ========================================
$filtros = [];
$params = [];
$tipos = '';

$campos = [
    'ativo','tipo','marca','modelo','ano',
    'confiabilidade','subunidade','acervo',
    'destino','disponibilidade'
];

foreach ($campos as $campo) {

    if (!empty($_GET[$campo])) {

        $filtros[] = "f.$campo = ?";

        $params[] = $_GET[$campo];

        $tipos .= 's';
    }
}

// ========================================
// BUSCA TEXTUAL
// ========================================
if (!empty($_GET['texto'])) {

    $texto = '%' . $_GET['texto'] . '%';

    $filtros[] = "(f.prefixo_sga LIKE ? OR f.placa LIKE ? OR f.tipo LIKE ?)";

    $params = array_merge($params, [$texto, $texto, $texto]);

    $tipos .= 'sss';
}

// ========================================
// CONTROLE POR OM
// ========================================
if ($nivel_usuario != 1 && $id_om_usuario) {

    $sqlSubs = "SELECT id_om_menor FROM organizacoes_militares_sub WHERE id_om_maior = ?";

    $stmtSubs = $conexao->prepare($sqlSubs);

    $stmtSubs->bind_param("i", $id_om_usuario);

    $stmtSubs->execute();

    $resSubs = $stmtSubs->get_result();

    $idsVisiveis = [$id_om_usuario];

    while ($row = $resSubs->fetch_assoc()) {

        $idsVisiveis[] = $row['id_om_menor'];
    }

    $stmtSubs->close();

    $placeholders = implode(',', array_fill(0, count($idsVisiveis), '?'));

    $filtros[] = "f.batalhao IN ($placeholders)";

    $params = array_merge($params, $idsVisiveis);

    $tipos .= str_repeat('i', count($idsVisiveis));
}

// ========================================
// SQL PRINCIPAL
// ========================================
$sql = "SELECT 
            f.ativo,
            f.tipo,
            f.prefixo_velho,
            f.prefixo_sga,
            f.nome_sioc,
            f.nmr_patrimonio,
            f.nmr_eb,
            f.chassi,
            f.acervo,
            cm.marca AS nome_marca,
            md.nome_modelo AS nome_modelo,
            f.ano,
            f.confiabilidade,
            f.obs_encmat,
            f.capac_tanque,
            f.consumo,
            f.destino,
            f.disponibilidade,
            f.missao,
            f.emprego_atual,
            f.ordem_fragmentaria,
            f.placa,
            f.subunidade,
            f.renavam,
            f.trem,
            om.nome AS nome_om,
            om.abreviatura AS sigla_om
        FROM frota f
        LEFT JOIN config_marcas cm ON f.marca = cm.id
        LEFT JOIN config_modelos md ON f.modelo = md.id
        LEFT JOIN organizacoes_militares om ON f.batalhao = om.id";

if (!empty($filtros)) {

    $sql .= " WHERE " . implode(" AND ", $filtros);
}

$sql .= " ORDER BY f.prefixo_sga ASC";

// ========================================
// EXECUTA CONSULTA
// ========================================
$stmt = $conexao->prepare($sql);

if (!empty($params)) {

    $stmt->bind_param($tipos, ...$params);
}

$stmt->execute();

$result = $stmt->get_result();

// ========================================
// GERA TABELA EXCEL
// ========================================
echo "<table border='1'>";

    echo "<tr style='background-color:#4CAF50; color:white; text-align:center;'>
            <th>Ativo</th>
            <th>Tipo</th>
            <th>Prefixo Velho</th>
            <th>Prefixo SGA</th>
            <th>Nome SIOC</th>
            <th>Nº Patrimônio</th>
            <th>Nº EB</th>
            <th>Chassi</th>
            <th>Acervo</th>
            <th>Marca</th>
            <th>Modelo</th>
            <th>Ano</th>
            <th>Confiabilidade</th>
            <th>Obs. EncMat</th>
            <th>Capac. Tanque</th>
            <th>Consumo</th>
            <th>Destino</th>
            <th>Disponibilidade</th>
            <th>Missão</th>
            <th>Emprego Atual</th>
            <th>Ordem Fragmentária</th>
            <th>Placa</th>
            <th>Subunidade</th>
            <th>Renavam</th>
            <th>Trem</th>
            <th>OM</th>
          </tr>";

while ($frota = $result->fetch_assoc()) {
        echo "<tr style='text-align:center;'>";
        echo "<td>".htmlspecialchars($frota['ativo'])."</td>";
        echo "<td>".htmlspecialchars($frota['tipo'])."</td>";
        echo "<td>".htmlspecialchars($frota['prefixo_velho'])."</td>";
        echo "<td>".htmlspecialchars($frota['prefixo_sga'])."</td>";
        echo "<td>".htmlspecialchars($frota['nome_sioc'])."</td>";
        echo "<td>".htmlspecialchars($frota['nmr_patrimonio'])."</td>";
        echo "<td>".htmlspecialchars($frota['nmr_eb'])."</td>";
        echo "<td>".htmlspecialchars($frota['chassi'])."</td>";
        echo "<td>".htmlspecialchars($frota['acervo'])."</td>";
        echo "<td>".htmlspecialchars($frota['nome_marca'])."</td>";
        echo "<td>".htmlspecialchars($frota['nome_modelo'])."</td>";
        echo "<td>".htmlspecialchars($frota['ano'])."</td>";
        echo "<td>".htmlspecialchars($frota['confiabilidade'])."</td>";
        echo "<td>".htmlspecialchars($frota['obs_encmat'])."</td>";
        echo "<td>".htmlspecialchars($frota['capac_tanque'])."</td>";
        echo "<td>".htmlspecialchars($frota['consumo'])."</td>";
        echo "<td>".htmlspecialchars($frota['destino'])."</td>";
        echo "<td>".htmlspecialchars($frota['disponibilidade'])."</td>";
        echo "<td>".htmlspecialchars($frota['missao'])."</td>";
        echo "<td>".htmlspecialchars($frota['emprego_atual'])."</td>";
        echo "<td>".htmlspecialchars($frota['ordem_fragmentaria'])."</td>";
        echo "<td>".htmlspecialchars($frota['placa'])."</td>";
        echo "<td>".htmlspecialchars($frota['subunidade'])."</td>";
        echo "<td>".htmlspecialchars($frota['renavam'])."</td>";
        echo "<td>".htmlspecialchars($frota['trem'])."</td>";
        echo "<td>".htmlspecialchars($frota['sigla_om'] ?? $frota['nome_om'])."</td>";
        echo "</tr>";
    }
    echo "</table>";

