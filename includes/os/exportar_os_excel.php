<?php
header("Content-Type: application/vnd.ms-excel; charset=UTF-8");
header("Content-Disposition: attachment; filename=ordens_servico.xls");
header("Pragma: no-cache");
header("Expires: 0");
echo "\xEF\xBB\xBF"; // BOM UTF-8

session_start();
include_once('../../conexao/config.php');

// ==============================
// Dados do usuário logado
// ==============================
$id_om_usuario = $_SESSION['usuario']['batalhao'] ?? null;
$nivel_usuario = $_SESSION['usuario']['nivel'] ?? null;

// ==============================
// Inicializa filtros
// ==============================
$filtros = [];
$params = [];
$tipos = '';

// Campos de filtro disponíveis via GET
$campos = ['batalhao', 'status', 'tipo_mnt', 'prefixo_sga', 'solicitante'];

// ==============================
// Filtros dinâmicos via GET
// ==============================
foreach ($campos as $campo) {
    if (!empty($_GET[$campo])) {
        $filtros[] = "os.$campo = ?";
        $params[] = $_GET[$campo];
        $tipos .= 's';
    }
}

// ==============================
// Filtro textual
// ==============================
if (!empty($_GET['texto'])) {
    $texto = '%' . $_GET['texto'] . '%';
    $filtros[] = "(os.problema LIKE ? OR os.solicitante LIKE ? OR os.prefixo_sga LIKE ?)";
    $params = array_merge($params, [$texto, $texto, $texto]);
    $tipos .= 'sss';
}

// ==============================
// Controle de visualização por OM
// ==============================
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
    $filtros[] = "os.batalhao IN ($placeholders)";
    $params = array_merge($params, $idsVisiveis);
    $tipos .= str_repeat('i', count($idsVisiveis));
}

// ==============================
// Consulta SQL principal
// ==============================
$sql = "SELECT 
            os.id,
            os.prefixo_sga,
            os.data_abertura,
            os.odometro_horimetro,
            os.solicitante,
            os.local_os,
            os.problema,
            os.secao_rspns,
            os.causa_indisponibilidade,
            os.tipo_mnt,
            os.status,
            os.valornd30,
            os.valornd39,
            os.valorTOTAL,
            os.manutencao_preventiva,
            os.prox_mnt_prev_hor,
            os.prox_mnt_prev_odo,
            os.cmt_ceem,
            os.ch_controle,
            os.ch_suprimento,
            (SELECT GROUP_CONCAT(falha_identificada SEPARATOR '; ') FROM os_falhas WHERE id_osprincipal = os.id) AS falhas_identificadas,
            (SELECT GROUP_CONCAT(nome_militar SEPARATOR '; ') FROM os_pessoal WHERE id_osprincipal = os.id) AS pessoal_utilizado,
            (SELECT GROUP_CONCAT(itens_utilizados SEPARATOR '; ') FROM os_itens WHERE id_osprincipal = os.id) AS itens_utilizados,
            om.nome AS nome_om,
            om.abreviatura AS sigla_om
        FROM os_principal os
        LEFT JOIN organizacoes_militares om ON os.batalhao = om.id";

if (!empty($filtros)) {
    $sql .= " WHERE " . implode(" AND ", $filtros);
}

$sql .= " ORDER BY os.data_abertura ASC";

// ==============================
// Executa query
// ==============================
$stmt = $conexao->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($tipos, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

// ==============================
// Geração da planilha
// ==============================
if ($result->num_rows > 0) {
    echo "<table border='1' cellpadding='5' cellspacing='0' style='border-collapse:collapse; width:100%;'>";
    echo "<tr style='background-color:#4CAF50; color:white; text-align:center;'>
            <th>Prefixo SGA</th>
            <th>Data Abertura</th>
            <th>Odômetro/Horímetro Entrada</th>
            <th>Solicitante</th>
            <th>Local OS</th>
            <th>Problema</th>
            <th>Seção</th>
            <th>Causa Indisponibilidade</th>
            <th>Tipo Manutenção</th>
            <th>Status</th>
            <th>Valor ND30</th>
            <th>Valor ND39</th>
            <th>Valor Total</th>
            <th>Manutenção Preventiva</th>
            <th>Próx. Manutenção Horímetro</th>
            <th>Próx. Manutenção Odômetro</th>
            <th>CMT CEEM</th>
            <th>CH Controle</th>
            <th>CH Suprimento</th>
            <th>Falhas Identificadas</th>
            <th>Pessoal Utilizado</th>
            <th>Itens Utilizados</th>
            <th>OM</th>
          </tr>";

    while ($os = $result->fetch_assoc()) {
        echo "<tr style='text-align:center;'>";
        echo "<td>".htmlspecialchars($os['prefixo_sga'])."</td>";
        echo "<td>".htmlspecialchars(date('d/m/Y', strtotime($os['data_abertura'])))."</td>";
        echo "<td>".htmlspecialchars($os['odometro_horimetro'])."</td>";
        echo "<td>".htmlspecialchars($os['solicitante'])."</td>";
        echo "<td>".htmlspecialchars($os['local_os'])."</td>";
        echo "<td>".htmlspecialchars($os['problema'])."</td>";
        echo "<td>".htmlspecialchars($os['secao_rspns'])."</td>";
        echo "<td>".htmlspecialchars($os['causa_indisponibilidade'])."</td>";
        echo "<td>".htmlspecialchars($os['tipo_mnt'])."</td>";
        echo "<td>".htmlspecialchars($os['status'])."</td>";
        echo "<td>".htmlspecialchars($os['valornd30'])."</td>";
        echo "<td>".htmlspecialchars($os['valornd39'])."</td>";
        echo "<td>".htmlspecialchars($os['valorTOTAL'])."</td>";
        echo "<td>".($os['manutencao_preventiva'] ? 'Sim' : 'Não')."</td>";
        echo "<td>".htmlspecialchars($os['prox_mnt_prev_hor'])."</td>";
        echo "<td>".htmlspecialchars($os['prox_mnt_prev_odo'])."</td>";
        echo "<td>".htmlspecialchars($os['cmt_ceem'])."</td>";
        echo "<td>".htmlspecialchars($os['ch_controle'])."</td>";
        echo "<td>".htmlspecialchars($os['ch_suprimento'])."</td>";
        echo "<td>".htmlspecialchars($os['falhas_identificadas'])."</td>";
        echo "<td>".htmlspecialchars($os['pessoal_utilizado'])."</td>";
        echo "<td>".htmlspecialchars($os['itens_utilizados'])."</td>";
        echo "<td>".htmlspecialchars($os['sigla_om'] ?? $os['nome_om'])."</td>";
        echo "</tr>";
    }

    echo "</table>";
} else {
    echo "<p>Nenhum registro encontrado.</p>";
}
?>
