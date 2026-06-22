<?php
function handleNotificacoesRoutes($method, $path, $pdo, $user) {
    // Pegar o role_id e o id do usuario atual
    $role_id = $user['role_id'];
    $user_id = $user['id'];

    if ($method === 'GET' && $path === 'unread') {
        // Busca notificacoes destinadas a ROLE dele ou especificamente ao USER dele
        $stmt = $pdo->prepare("
            SELECT * FROM notificacoes 
            WHERE lida = 0 
            AND (
                (destinatario_tipo = 'ROLE' AND destinatario_id = ?) 
                OR 
                (destinatario_tipo = 'USER' AND destinatario_id = ?)
            )
            ORDER BY nivel DESC, data_criacao DESC
        ");
        $stmt->execute([$role_id, $user_id]);
        $notificacoes = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(['status' => 'sucesso', 'dados' => $notificacoes]);
        exit;
    }

    if ($method === 'POST' && $path === 'marcar_lida') {
        $data = json_decode(file_get_contents("php://input"), true);
        $notificacao_id = $data['id'] ?? null;
        
        if ($notificacao_id) {
            $stmt = $pdo->prepare("UPDATE notificacoes SET lida = 1 WHERE id = ?");
            $stmt->execute([$notificacao_id]);
        }
        echo json_encode(['status' => 'sucesso']);
        exit;
    }

    http_response_code(404);
    echo json_encode(['status' => 'erro', 'mensagem' => 'Rota de notificacoes nao encontrada']);
    exit;
}
