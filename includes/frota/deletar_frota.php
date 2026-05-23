<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

$pagina_id = 14;

require_once('../../conexao/config.php');
require_once("../../includes/funcoes/log.php");
require_once('../api/seguranca_json_deletar.php');
require_once('../api/batalhoes_permitidos.php');

$nivel_usuario    = $_SESSION['usuario']['nivel'] ?? 3;
$batalhao_usuario = $_SESSION['usuario']['batalhao'] ?? 0;
$usuarioLogado    = (int)($_SESSION['usuario_id'] ?? 0);


// ==============================
// 🔒 BATALHÕES PERMITIDOS
// ==============================
try {
    $batalhoesPermitidos = obterBatalhoesPermitidos($conexao, $nivel_usuario, $batalhao_usuario);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro ao validar permissões']);
    exit;
}


// ==============================
// 🔒 CSRF
// ==============================
if (
    empty($_POST['csrf_token']) ||
    empty($_SESSION['csrf_token']) ||
    !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'Token inválido'
    ]);
    exit;
}


// ==============================
// 🔒 VALIDAR ID
// ==============================
$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;

if ($id <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID inválido']);
    exit;
}


// ==============================
// 🔍 1) BUSCAR FROTA (COM BATALHÃO)
// ==============================
$stmt_select = $conexao->prepare("
    SELECT id, prefixo_sga, tipo, marca, modelo, placa, chassi, batalhao
    FROM frota
    WHERE id = ?
    LIMIT 1
");

if (!$stmt_select) {
    echo json_encode(['success' => false, 'message' => 'Erro ao buscar frota']);
    exit;
}

$stmt_select->bind_param("i", $id);
$stmt_select->execute();
$result = $stmt_select->get_result();

if ($result->num_rows === 0) {
    $stmt_select->close();
    echo json_encode(['success' => false, 'message' => 'Frota não encontrada']);
    exit;
}

$frota = $result->fetch_assoc();
$stmt_select->close();


// ==============================
// 🔒 2) VALIDAR PERMISSÃO
// ==============================
$batalhaoFrota = (int)$frota['batalhao'];

// Se NÃO for admin (null = acesso total)
if ($batalhoesPermitidos !== null) {

    if (!in_array($batalhaoFrota, $batalhoesPermitidos)) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'message' => 'Sem permissão para deletar esta frota'
        ]);
        exit;
    }
}

$prefixo = $frota['prefixo_sga'] ?? '';


// ==============================
// 🚀 3) TRANSAÇÃO
// ==============================
try {
    $conexao->begin_transaction();


    // ==========================
    // 📋 LOG DAS MEDIÇÕES
    // ==========================
    $stmt_medicoes_log = $conexao->prepare("
        SELECT DATE_FORMAT(data, '%d/%m/%Y') AS data, odometro
        FROM controle_medicoes
        WHERE viatura_id = ?
        ORDER BY data ASC
    ");

    if ($stmt_medicoes_log) {
        $stmt_medicoes_log->bind_param("i", $id);
        $stmt_medicoes_log->execute();
        $result_medicoes = $stmt_medicoes_log->get_result();

        $descricao_medicoes = "";

        while ($medicao = $result_medicoes->fetch_assoc()) {
            $descricao_medicoes .= "Data: {$medicao['data']} - Odômetro: {$medicao['odometro']}\n";
        }

        $stmt_medicoes_log->close();

        if (!empty($descricao_medicoes)) {
            try {
                registrar_log($conexao, $usuarioLogado, 'Excluir medições',
                    "Medições da viatura {$prefixo} serão excluídas:\n" . $descricao_medicoes,
                    $id
                );
            } catch (Throwable $e) {}
        }
    }


    // ==========================
    // 🧨 DELETES
    // ==========================

    $stmt = $conexao->prepare("DELETE FROM controle_medicoes WHERE viatura_id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();

    $stmt = $conexao->prepare("DELETE FROM sta_fichas WHERE id_viatura = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();

    $stmt = $conexao->prepare("DELETE FROM logs WHERE frota_id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();

    $stmt = $conexao->prepare("DELETE FROM os_principal WHERE id_frota = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();


    // ==========================
    // 🗑️ DELETAR FROTA
    // ==========================
    $stmt_delete = $conexao->prepare("DELETE FROM frota WHERE id = ?");
    $stmt_delete->bind_param("i", $id);

    if (!$stmt_delete->execute()) {
        throw new Exception("Erro ao deletar frota");
    }

    $stmt_delete->close();


    // ==========================
    // 🧾 LOG FINAL
    // ==========================
    try {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

        registrar_log(
            $conexao,
            $usuarioLogado,
            'Deletar frota',
            "Frota ID {$id} deletada: Prefixo {$frota['prefixo_sga']}, Tipo: {$frota['tipo']}, Marca: {$frota['marca']}, Modelo: {$frota['modelo']}, Placa: {$frota['placa']}, Chassi: {$frota['chassi']}",
            $id
        );

    } catch (Throwable $e) {}


    $conexao->commit();

    echo json_encode(['success' => true]);
    exit;

} catch (Throwable $e) {

    $conexao->rollback();

    echo json_encode([
        'success' => false,
        'message' => 'Falha ao deletar',
        'debug' => $e->getMessage()
    ]);
    exit;
}