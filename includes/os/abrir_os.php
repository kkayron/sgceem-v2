<?php
$pagina_id = 13;

require_once('../../includes/api/seguranca_json_cadastrar.php');

ob_start();
date_default_timezone_set('America/Fortaleza');

include_once("../../conexao/config.php");
include_once("../../includes/funcoes/log.php");

// ===========================================
// Funções auxiliares
// ===========================================
function post($key) {
    return isset($_POST[$key]) ? trim($_POST[$key]) : null;
}

function buscarResponsavel($conexao, $funcao, $batalhao) {
    $sql = "SELECT nomecompleto, postograd 
            FROM usuarios 
            WHERE funcao = ? 
              AND batalhao = ? 
              AND status = 'sim' 
            LIMIT 1";
    $stmt = $conexao->prepare($sql);
    $stmt->bind_param("si", $funcao, $batalhao);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($user = $result->fetch_assoc()) {
        return $user['postograd'] . " " . $user['nomecompleto'];
    }
    return "Não definido";
}

// ===========================================
// Dados do formulário
// ===========================================
$aberta_por = $_SESSION['usuario_id'] ?? null;
$batalhao = post('batalhao'); // <- agora vem do formulário
$local_os = post('local_os');
$id_frota = post('id_frota');
$odometro_horimetro = post('odometro_horimetro');
$solicitante = post('solicitante');
$problema = post('problema');
$secao_rspns = post('secao_rspns');
$tipo_mnt = post('tipo_mnt');
$status = post('status');
$causa_indisponibilidade = post('causa_indisponibilidade');
$data_abertura = date('Y-m-d H:i:s');

// ===========================================
// 🔒 Validação: Local, Viatura e Batalhão da OS devem ser do mesmo batalhão
// ===========================================

// 1️⃣ Busca batalhão do local
$sqlLocal = "SELECT batalhao FROM config_destinos WHERE destino = ? LIMIT 1";
$stmtLocal = $conexao->prepare($sqlLocal);
$stmtLocal->bind_param("s", $local_os);
$stmtLocal->execute();
$resLocal = $stmtLocal->get_result();
$local_batalhao = $resLocal->fetch_assoc()['batalhao'] ?? null;
$stmtLocal->close();

// 2️⃣ Busca batalhão da viatura (se houver)
$viatura_batalhao = null;
if (is_numeric($id_frota)) {
    $sqlVtr = "SELECT batalhao FROM frota WHERE id = ? LIMIT 1";
    $stmtVtr = $conexao->prepare($sqlVtr);
    $stmtVtr->bind_param("i", $id_frota);
    $stmtVtr->execute();
    $resVtr = $stmtVtr->get_result();
    $viatura_batalhao = $resVtr->fetch_assoc()['batalhao'] ?? null;
    $stmtVtr->close();
}

// 3️⃣ Valida coerência entre os três batalhões
if (!$local_batalhao || !$viatura_batalhao || !$batalhao) {
    echo json_encode([
        "status" => "erro",
        "mensagem" => "Erro: não foi possível identificar o batalhão do local, da viatura ou da OS."
    ]);
    exit;
}

if (!($local_batalhao == $viatura_batalhao && $viatura_batalhao == $batalhao)) {
    echo json_encode([
        "status" => "erro",
        "mensagem" => "Erro: o local da OS, a viatura e o batalhão selecionado devem pertencer ao mesmo batalhão."
    ]);
    exit;
}

// ===========================================
// Busca responsáveis do mesmo batalhão
// ===========================================
$cmt_ceem       = buscarResponsavel($conexao, 'Cmt Cia E Eqp Mnt', $batalhao);
$ch_suprimento  = buscarResponsavel($conexao, 'Ch Financeiro', $batalhao);
$ch_controle    = buscarResponsavel($conexao, 'Ch Seç Ctrl', $batalhao);

// ===========================================
// Busca prefixo ou define "Outra Vtr"
// ===========================================
$prefixo_sga = null;
if (is_numeric($id_frota)) {
    $query = "SELECT prefixo_sga FROM frota WHERE id = ?";
    $stmtFrota = $conexao->prepare($query);
    $stmtFrota->bind_param("i", $id_frota);
    $stmtFrota->execute();
    $result = $stmtFrota->get_result();
    if ($row = $result->fetch_assoc()) {
        $prefixo_sga = $row['prefixo_sga'];
    }
    $stmtFrota->close();
} else {
    $prefixo_sga = $id_frota;
    $id_frota = null;
}

// ===========================================
// Inserção no banco
// ===========================================
$sql = "INSERT INTO os_principal (
    batalhao, local_os, id_frota, odometro_horimetro, solicitante,
    problema, secao_rspns, tipo_mnt, status, causa_indisponibilidade,
    prefixo_sga, data_abertura, aberta_por, cmt_ceem, ch_suprimento, ch_controle
) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
$stmt = $conexao->prepare($sql);
$stmt->bind_param(
    "isisssssssssisss",
    $batalhao,
    $local_os,
    $id_frota,
    $odometro_horimetro,
    $solicitante,
    $problema,
    $secao_rspns,
    $tipo_mnt,
    $status,
    $causa_indisponibilidade,
    $prefixo_sga,
    $data_abertura,
    $aberta_por,
    $cmt_ceem,
    $ch_suprimento,
    $ch_controle
);

// ===========================================
// Execução e resposta
// ===========================================
if ($stmt->execute()) {
    $descricao = "Nova OS aberta: {$prefixo_sga} - {$problema} ({$status})";
    registrar_log($conexao, $aberta_por, 'Abrir OS', $descricao);

    echo json_encode([
        "status" => "sucesso",
        "mensagem" => "Ordem de serviço aberta com sucesso!",
        "data_abertura" => $data_abertura
    ]);
} else {
    echo json_encode([
        "status" => "erro",
        "mensagem" => "Erro ao salvar no banco: " . $stmt->error
    ]);
}

ob_end_flush();
?>
