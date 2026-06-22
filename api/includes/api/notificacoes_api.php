<?php
session_start();
require_once(__DIR__ . '/../../../database/conexao/config.php');

if (!isset($_SESSION['usuario_id'])) {
    http_response_code(401);
    echo json_encode(['erro' => 'Não autorizado']);
    exit;
}

header('Content-Type: application/json; charset=utf-8');
$acao = $_GET['acao'] ?? '';
$usuario_id = $_SESSION['usuario_id'];

if ($acao === 'listar') {
    // Busca notificações específicas do usuário OU globais (usuario_id IS NULL)
    // Para globais, verificamos se o ID do usuário já está no campo 'lida_por'
    $sql = "SELECT * FROM notificacoes 
            WHERE usuario_id = ? OR usuario_id IS NULL 
            ORDER BY data_criacao DESC LIMIT 10";
            
    $stmt = $conexao->prepare($sql);
    $stmt->bind_param('i', $usuario_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $notificacoes = [];
    $naoLidas = 0;
    
    while ($row = $result->fetch_assoc()) {
        $lida = false;
        
        if ($row['usuario_id'] === null) {
            // É global, verifica JSON de lidos
            $lidos = json_decode($row['lida_por'] ?: '[]', true);
            if (in_array($usuario_id, $lidos)) {
                $lida = true;
            }
        } else {
            // Específica do usuário (se chegou aqui, e não estamos usando campo lida_por pra indivíduo, 
            // podemos usar o mesmo esquema, ou simplesmente assumir que todas são globais para simplificar
            $lidos = json_decode($row['lida_por'] ?: '[]', true);
            if (in_array($usuario_id, $lidos)) {
                $lida = true;
            }
        }
        
        if (!$lida) {
            $naoLidas++;
        }
        
        $notificacoes[] = [
            'id' => $row['id'],
            'titulo' => $row['titulo'],
            'mensagem' => $row['mensagem'],
            'tipo' => $row['tipo'],
            'data' => date('d/m/Y H:i', strtotime($row['data_criacao'])),
            'lida' => $lida
        ];
    }
    
    echo json_encode([
        'nao_lidas' => $naoLidas,
        'notificacoes' => $notificacoes
    ]);
    exit;
}

if ($acao === 'marcar_lidas') {
    $sql = "SELECT id, lida_por FROM notificacoes WHERE usuario_id = ? OR usuario_id IS NULL";
    $stmt = $conexao->prepare($sql);
    $stmt->bind_param('i', $usuario_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $lidos = json_decode($row['lida_por'] ?: '[]', true);
        if (!in_array($usuario_id, $lidos)) {
            $lidos[] = $usuario_id;
            $lidosJson = json_encode($lidos);
            
            $update = $conexao->prepare("UPDATE notificacoes SET lida_por = ? WHERE id = ?");
            $update->bind_param('si', $lidosJson, $row['id']);
            $update->execute();
        }
    }
    
    echo json_encode(['sucesso' => true]);
    exit;
}

echo json_encode(['erro' => 'Ação inválida']);
