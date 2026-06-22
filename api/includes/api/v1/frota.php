<?php
/**
 * API RESTful (v1) - Módulo Frota
 * Escopo: Headless Architecture (Retorno estrito em JSON)
 * Segurança: Validação de sessão, verificação de hierarquia e sanitização.
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');

session_start();
require_once(__DIR__ . '/../../../../database/conexao/config.php');

// 1. Autenticação e Segurança (Middleware-like)
if (!isset($_SESSION['usuario_id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'erro', 'mensagem' => 'Não autorizado. Token de sessão ausente.']);
    exit;
}

// O ideal é validar a permissão exata para visualização de Frota
// Supondo página ID = 54 (Dashboard Frota) ou ID 10 (Listagem Frota)
// Mas para este MVP, vamos apenas validar se ele tem acesso ao sistema
$usuario_id = (int)$_SESSION['usuario_id'];
$batalhao_id = (int)($_SESSION['usuario']['batalhao'] ?? 0);
$nivel_usuario = (int)($_SESSION['usuario']['nivel'] ?? 3);

$metodo = $_SERVER['REQUEST_METHOD'];
$acao = $_GET['acao'] ?? 'listar';

try {
    if ($metodo === 'GET' && $acao === 'listar') {
        
        // 2. Construção segura da Query (Dependendo do nível hierárquico)
        // Se nível for 3 (Mecânico local), vê só seu batalhão. Se for 1 (Comando Geral), vê tudo.
        $whereClause = "WHERE 1=1";
        $params = [];
        $types = "";
        
        if ($nivel_usuario === 3) {
            $whereClause .= " AND f.batalhao = ?";
            $params[] = $batalhao_id;
            $types .= "i";
        }
        
        // Exemplo: Filtrar por status caso passado via GET
        if (!empty($_GET['status'])) {
            $whereClause .= " AND f.status = ?";
            $params[] = $_GET['status'];
            $types .= "s";
        }

        $sql = "
            SELECT 
                f.id, 
                f.prefixo_sga, 
                f.marca, 
                f.modelo, 
                f.placa, 
                f.status, 
                f.disponibilidade,
                om.abreviatura AS batalhao_nome
            FROM frota f
            LEFT JOIN organizacoes_militares om ON om.id = f.batalhao
            $whereClause
            ORDER BY f.id DESC
            LIMIT 100
        ";

        $stmt = $conexao->prepare($sql);
        
        if ($types !== "") {
            $stmt->bind_param($types, ...$params);
        }
        
        if (!$stmt->execute()) {
            throw new Exception("Falha ao executar consulta no banco de dados.");
        }
        
        $result = $stmt->get_result();
        $viaturas = [];
        
        while ($row = $result->fetch_assoc()) {
            // Transformação e limpeza (Presenter)
            $viaturas[] = [
                'id' => (int)$row['id'],
                'prefixo' => htmlspecialchars($row['prefixo_sga'] ?? 'N/I'),
                'veiculo' => htmlspecialchars(($row['marca'] ?? '') . ' ' . ($row['modelo'] ?? '')),
                'placa' => htmlspecialchars($row['placa'] ?? 'SEM PLACA'),
                'status' => htmlspecialchars($row['status'] ?? 'Indefinido'),
                'disponibilidade' => htmlspecialchars($row['disponibilidade'] ?? 'N/A'),
                'batalhao' => htmlspecialchars($row['batalhao_nome'] ?? 'Desconhecido')
            ];
        }

        http_response_code(200);
        echo json_encode([
            'status' => 'sucesso',
            'meta' => [
                'total_resultados' => count($viaturas),
                'nivel_acesso' => $nivel_usuario
            ],
            'dados' => $viaturas
        ]);
        exit;
    }

    // Se chegar aqui, a ação não existe
    http_response_code(404);
    echo json_encode(['status' => 'erro', 'mensagem' => 'Endpoint não encontrado.']);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'erro', 'mensagem' => 'Erro interno do servidor.']);
    // Em produção, deve-se logar $e->getMessage() em um arquivo de log seguro.
}
