<?php
header('Content-Type: application/json; charset=utf-8');
session_start();
require_once '../../conexao/config.php';

// ===============================
// 1) Validação de ID
// ===============================
$id = intval($_GET['id'] ?? 0);
if ($id <= 0) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'ID inválido']);
    exit;
}

// ===============================
// 2) Captura permissões do usuário
// ===============================
$id_om_usuario = $_SESSION['usuario']['batalhao'] ?? 0;

// Descobre o nível do usuário (1=global, 2=subordinados, 3=apenas próprio)
$sqlNivel = "SELECT nivel FROM organizacoes_militares WHERE id = ?";
$stmtNivel = $conexao->prepare($sqlNivel);
$stmtNivel->bind_param("i", $id_om_usuario);
$stmtNivel->execute();
$resNivel = $stmtNivel->get_result();
$nivelUsuario = $resNivel->fetch_assoc()['nivel'] ?? 3;
$stmtNivel->close();

// ===============================
// 3) Monta lista de batalhões visíveis
// ===============================
if ($nivelUsuario == 1) {
    $sqlBatalhoes = "SELECT id FROM organizacoes_militares";
    $resBatalhoes = $conexao->query($sqlBatalhoes);
} else {
    $sqlBatalhoes = "
        SELECT om.id
        FROM organizacoes_militares om
        WHERE om.id = $id_om_usuario
        OR om.id IN (
            SELECT id_om_menor FROM organizacoes_militares_sub WHERE id_om_maior = $id_om_usuario
        )
    ";
    $resBatalhoes = $conexao->query($sqlBatalhoes);
}

$batalhoesPermitidos = [];
while ($bat = $resBatalhoes->fetch_assoc()) {
    $batalhoesPermitidos[] = (int)$bat['id'];
}

// Se o usuário não tiver batalhões permitidos, sai
if (empty($batalhoesPermitidos)) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Sem permissão para visualizar fichas']);
    exit;
}

// ===============================
// 4) Buscar dados da ficha
// ===============================
$stmtFicha = $conexao->prepare("
    SELECT f.*, om.nome AS nome_om, om.abreviatura AS abreviatura_om
    FROM sta_fichas f
    LEFT JOIN organizacoes_militares om ON om.id = f.batalhao
    WHERE f.id = ?
");
$stmtFicha->bind_param("i", $id);
$stmtFicha->execute();
$resFicha = $stmtFicha->get_result();

if (!$resFicha || $resFicha->num_rows === 0) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Ficha não encontrada']);
    exit;
}

$ficha = $resFicha->fetch_assoc();
$stmtFicha->close();

// Verifica se a ficha pertence a um batalhão permitido
if (!in_array((int)$ficha['batalhao'], $batalhoesPermitidos)) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Você não tem permissão para visualizar esta ficha']);
    exit;
}

// ===============================
// 5) Buscar viaturas permitidas
// ===============================
$viaturas = [];
if (!empty($batalhoesPermitidos)) {
    $placeholders = implode(',', array_fill(0, count($batalhoesPermitidos), '?'));
    $sqlViaturas = "
        SELECT f.id, f.prefixo_sga, f.prefixo_velho,
               cm.marca AS nome_marca, md.nome_modelo AS nome_modelo,
               f.ano, om.nome AS nome_om
        FROM frota f
        LEFT JOIN config_marcas cm ON f.marca = cm.id
        LEFT JOIN config_modelos md ON f.modelo = md.id
        LEFT JOIN organizacoes_militares om ON f.batalhao = om.id
        WHERE f.batalhao IN ($placeholders)
        ORDER BY f.prefixo_sga ASC
    ";
    $stmtViaturas = $conexao->prepare($sqlViaturas);
    $tipos = str_repeat('i', count($batalhoesPermitidos));
    $stmtViaturas->bind_param($tipos, ...$batalhoesPermitidos);
    $stmtViaturas->execute();
    $resViaturas = $stmtViaturas->get_result();
    while ($row = $resViaturas->fetch_assoc()) {
        $viaturas[] = $row;
    }
    $stmtViaturas->close();
}

// ===============================
// 6) Buscar OMs permitidas
// ===============================
$oms = [];
if (!empty($batalhoesPermitidos)) {
    $placeholders = implode(',', array_fill(0, count($batalhoesPermitidos), '?'));
    $sqlOMs = "
        SELECT id, nome, abreviatura
        FROM organizacoes_militares
        WHERE id IN ($placeholders)
        ORDER BY nome ASC
    ";
    $stmtOMs = $conexao->prepare($sqlOMs);
    $stmtOMs->bind_param($tipos, ...$batalhoesPermitidos);
    $stmtOMs->execute();
    $resOMs = $stmtOMs->get_result();
    while ($row = $resOMs->fetch_assoc()) {
        $oms[] = $row;
    }
    $stmtOMs->close();
}

// ===============================
// 7) Retorna tudo em JSON
// ===============================
echo json_encode([
    'status' => 'sucesso',
    'ficha' => $ficha,
    'viaturas' => $viaturas,
    'oms' => $oms
], JSON_UNESCAPED_UNICODE);
