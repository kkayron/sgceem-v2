<?php
header('Content-Type: application/json; charset=utf-8');
include_once('../../conexao/config.php');

$pagina_id = 35;

require_once('../api/seguranca_json_deletar.php');

if (empty($_SERVER['HTTP_REFERER'])) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Acesso direto não permitido']);
    exit;
}

try {

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(['status' => 'erro', 'mensagem' => 'Método inválido.']);
        exit;
    }

    $id = $_POST['id'] ?? null;

    if (empty($id) || !is_numeric($id)) {
        echo json_encode(['status' => 'erro', 'mensagem' => 'ID inválido.']);
        exit;
    }

    // ============================
    // BUSCA O NOME DO DESTINO PELO ID
    // ============================
    $stmt = $conexao->prepare("SELECT destino FROM config_destinos WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->bind_result($destinoNome);
    $stmt->fetch();
    $stmt->close();

    if (empty($destinoNome)) {
        echo json_encode(['status' => 'erro', 'mensagem' => 'Destino não encontrado.']);
        exit;
    }

    // ============================
    // VERIFICA SE O DESTINO ESTÁ SENDO USADO NA TABELA FROTA
    // ============================
    $stmt = $conexao->prepare("
        SELECT id 
        FROM frota
        WHERE destino = ?
        LIMIT 1
    ");
    $stmt->bind_param("s", $destinoNome);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        echo json_encode([
            'status' => 'erro',
            'mensagem' => 'Este destino não pode ser excluído pois está associado a uma frota.'
        ]);
        exit;
    }
    $stmt->close();

    // ============================
    // EXCLUI O DESTINO
    // ============================
    $stmt = $conexao->prepare("DELETE FROM config_destinos WHERE id = ?");
    $stmt->bind_param("i", $id);

    if ($stmt->execute()) {
        echo json_encode(['status' => 'sucesso', 'mensagem' => 'Destino excluído com sucesso!']);
    } else {
        echo json_encode(['status' => 'erro', 'mensagem' => 'Erro ao excluir destino.']);
    }

    $stmt->close();
    $conexao->close();

} catch (Exception $e) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Erro inesperado: ' . $e->getMessage()]);
}
