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
$manutencoes_programadas = $_POST['manutencoes_programadas'] ?? [];

if (!is_array($manutencoes_programadas)) {
    $manutencoes_programadas = [];
}

$manutencoes_programadas = array_filter($manutencoes_programadas, 'is_numeric');
$manutencoes_programadas = array_map('intval', $manutencoes_programadas);

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
$conexao->begin_transaction();

try {
    $stmt->execute();

    $id_osprincipal = $stmt->insert_id;

    // Registra manutenções programadas realizadas
    if (!empty($manutencoes_programadas) && is_numeric($id_frota)) {

        // Confere marca/modelo da frota
        $sqlFrotaPlano = "
            SELECT marca, modelo 
            FROM frota 
            WHERE id = ? 
            LIMIT 1
        ";
        $stmtFrotaPlano = $conexao->prepare($sqlFrotaPlano);
        $stmtFrotaPlano->bind_param("i", $id_frota);
        $stmtFrotaPlano->execute();
        $resFrotaPlano = $stmtFrotaPlano->get_result();
        $dadosFrotaPlano = $resFrotaPlano->fetch_assoc();

        if ($dadosFrotaPlano) {
            $data_execucao = date('Y-m-d', strtotime($data_abertura));
            $odometro_execucao = is_numeric($odometro_horimetro) ? (float)$odometro_horimetro : 0;

            $stmtInsMnt = $conexao->prepare("
                INSERT INTO mnt_execucoes (
                    id_plano,
                    id_frota,
                    id_osprincipal,
                    odometro_horimetro_execucao,
                    data_execucao
                ) VALUES (?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    odometro_horimetro_execucao = VALUES(odometro_horimetro_execucao),
                    data_execucao = VALUES(data_execucao),
                    atualizado_em = CURRENT_TIMESTAMP
            ");

            foreach ($manutencoes_programadas as $id_plano) {

                // Segurança: só aceita plano compatível com marca/modelo da frota
                $stmtValidaPlano = $conexao->prepare("
                    SELECT id 
                    FROM mnt_planos
                    WHERE id = ?
                      AND id_marca = ?
                      AND id_modelo = ?
                      AND ativo = 1
                    LIMIT 1
                ");
                $stmtValidaPlano->bind_param(
                    "iii",
                    $id_plano,
                    $dadosFrotaPlano['marca'],
                    $dadosFrotaPlano['modelo']
                );
                $stmtValidaPlano->execute();
                $resValidaPlano = $stmtValidaPlano->get_result();

                if ($resValidaPlano->num_rows === 0) {
                    continue;
                }

                $stmtInsMnt->bind_param(
                    "iiids",
                    $id_plano,
                    $id_frota,
                    $id_osprincipal,
                    $odometro_execucao,
                    $data_execucao
                );

                $stmtInsMnt->execute();
            }
        }
    }

    $descricao = "Nova OS aberta: {$prefixo_sga} - {$problema} ({$status})";
    registrar_log($conexao, $aberta_por, 'Abrir OS', $descricao);

    $conexao->commit();

    echo json_encode([
        "status" => "sucesso",
        "mensagem" => "Ordem de serviço aberta com sucesso!",
        "id_os" => $id_osprincipal,
        "data_abertura" => $data_abertura
    ]);

} catch (Throwable $e) {
    $conexao->rollback();

    echo json_encode([
        "status" => "erro",
        "mensagem" => "Erro ao salvar OS: " . $e->getMessage()
    ]);
}

ob_end_flush();
exit;
?>
