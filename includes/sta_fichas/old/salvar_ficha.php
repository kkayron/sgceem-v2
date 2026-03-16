<?php
session_start();
header('Content-Type: application/json');
include_once("../../conexao/config.php");
include_once("../../includes/funcoes/log.php");

function post($key) {
    return isset($_POST[$key]) ? trim($_POST[$key]) : null;
}

$usuario_id = $_SESSION['usuario_id'] ?? null;

// Campos obrigatórios
$id_viatura = post('id_viatura');
$id_om = post('id_om');
$batalhao = $id_om;
$data_abertura = post('data_abertura');
$data_prevista = post('data_prevista');
$solicitante = post('solicitante');
$encerrada_por = $usuario_id;
$motorista = post('motorista');
$subunidade = post('subunidade');
$destino = post('destino');
$chefe_apresentar = post('chefe_apresentar');
$local_apresentar = post('local_apresentar');
$horario_apresentar = post('horario_apresentar');
$natureza = post('natureza');
$status = post('status');
$cidade = post('cidade');
$force = post('force'); // para ignorar o aviso se já confirmado

// Campos opcionais
$data_saida = post('data_saida');
$hora_saida = post('hora_saida');
$odo_saida = post('odo_saida');
$data_retorno = post('data_retorno');
$hora_retorno = post('hora_retorno');
$odo_retorno = post('odo_retorno');
$observacoes_pos_emprego = post('observacoes_pos_emprego');

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
        return $user['nomecompleto'] . " - " . $user['postograd'];
    }
    return "Não definido";
}

// ===========================================
// Busca responsáveis do mesmo batalhão
// ===========================================
$cmt_ceem       = buscarResponsavel($conexao, '8', $batalhao);
$ch_sta       = buscarResponsavel($conexao, '12', $batalhao);

// Validação básica
if (
    !$id_viatura || !$id_om || !$data_abertura || !$solicitante || !$motorista || !$subunidade ||
    !$destino || !$cidade || !$chefe_apresentar || !$local_apresentar || !$horario_apresentar ||
    !$natureza || !$status || !$data_prevista
) {
    echo json_encode([
        "status" => "erro",
        "mensagem" => "Preencha todos os campos obrigatórios."
    ]);
    exit;
}

// Verificação de conflito de ficha existente
if (!$force) {
    $sqlVerifica = "
        SELECT id, data_abertura, data_prevista 
        FROM sta_fichas 
        WHERE id_viatura = ?
          AND status = 'Aberta'
          AND (
              (? BETWEEN data_abertura AND data_prevista)
              OR (? BETWEEN data_abertura AND data_prevista)
              OR (data_abertura BETWEEN ? AND ?)
              OR (data_prevista BETWEEN ? AND ?)
          )
        LIMIT 1
    ";

    $stmtVerifica = $conexao->prepare($sqlVerifica);
    $stmtVerifica->bind_param(
        "issssss",
        $id_viatura,
        $data_abertura,
        $data_prevista,
        $data_abertura,
        $data_prevista,
        $data_abertura,
        $data_prevista
    );
    $stmtVerifica->execute();
    $resVerifica = $stmtVerifica->get_result();

    if ($resVerifica->num_rows > 0) {
        $ficha = $resVerifica->fetch_assoc();
        echo json_encode([
            "status" => "confirmar",
            "mensagem" => "Já existe a ficha #{$ficha['id']} aberta para esta viatura de " .
                         date('d/m/Y', strtotime($ficha['data_abertura'])) . " até " .
                         date('d/m/Y', strtotime($ficha['data_prevista'])) . 
                         ". Deseja cadastrar esta nova ficha mesmo assim?"
        ]);
        exit;
    }
}

// Inserção
$sql = "INSERT INTO sta_fichas (
    id_viatura, batalhao, cmt_ceem, ch_sta, data_abertura, solicitante, motorista, subunidade, destino,
    chefe_apresentar, local_apresentar, horario_apresentar, natureza, status,
    data_retorno, hora_retorno, odo_retorno,
    data_saida, hora_saida, odo_saida,
    encerrada_por, observacoes_pos_emprego, cidade, data_prevista
) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

$stmt = $conexao->prepare($sql);
if (!$stmt) {
    echo json_encode([
        "status" => "erro",
        "mensagem" => "Erro ao preparar inserção: " . $conexao->error
    ]);
    exit;
}

$stmt->bind_param(
    "iissssssssssssssssdsssss",
    $id_viatura,
    $id_om,
    $cmt_ceem,
    $ch_sta,
    $data_abertura,
    $solicitante,
    $motorista,
    $subunidade,
    $destino,
    $chefe_apresentar,
    $local_apresentar,
    $horario_apresentar,
    $natureza,
    $status,
    $data_retorno,
    $hora_retorno,
    $odo_retorno,
    $data_saida,
    $hora_saida,
    $odo_saida,
    $usuario_id,
    $observacoes_pos_emprego,
    $cidade,
    $data_prevista
);

if ($stmt->execute()) {
    $ficha_id = $stmt->insert_id;
    registrar_log($conexao, $usuario_id, "Cadastro de Ficha STA", "Ficha #$ficha_id aberta para viatura $id_viatura");
    echo json_encode([
        "status" => "sucesso",
        "mensagem" => "Ficha cadastrada com sucesso!"
    ]);
} else {
    echo json_encode([
        "status" => "erro",
        "mensagem" => "Erro ao salvar ficha: " . $stmt->error
    ]);
}
?>
