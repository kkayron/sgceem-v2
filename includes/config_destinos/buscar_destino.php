<?php
header('Content-Type: application/json; charset=utf-8');


include_once('../../conexao/config.php');

$pagina_id = 35;

require_once('../api/seguranca_json.php');

if (empty($_SERVER['HTTP_REFERER'])) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Acesso direto não permitido']);
    exit;
}

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        echo json_encode(['status' => 'erro', 'mensagem' => 'Método inválido.']);
        exit;
    }

    $id = $_GET['id'] ?? null;

    if (empty($id) || !is_numeric($id)) {
        echo json_encode(['status' => 'erro', 'mensagem' => 'ID inválido.']);
        exit;
    }

    $stmt = $conexao->prepare("SELECT id, batalhao, destino FROM config_destinos WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($res->num_rows === 0) {
        echo json_encode(['status' => 'erro', 'mensagem' => 'Destino não encontrado.']);
        exit;
    }

    $destino = $res->fetch_assoc();

    echo json_encode([
        'status' => 'sucesso',
        'destino' => $destino
    ]);

} catch (Exception $e) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Erro: ' . $e->getMessage()]);
}
