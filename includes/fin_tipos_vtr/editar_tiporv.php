<?php
header('Content-Type: application/json; charset=utf-8');

$pagina_id = 49;
require_once('../api/seguranca_json_editar.php');

//CSRF
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

include_once('../../conexao/config.php');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode([
            'status' => 'erro',
            'mensagem' => 'Método inválido.'
        ]);
        exit;
    }

    $id          = $_POST['id'] ?? '';
    $abreviatura = trim($_POST['abreviatura'] ?? '');
    $descricao   = trim($_POST['descricao'] ?? '');
    $tipo        = trim($_POST['tipo'] ?? '');

    // ===================== VALIDAÇÕES =====================
    if (empty($id) || !is_numeric($id)) {
        echo json_encode([
            'status' => 'erro',
            'mensagem' => 'ID inválido.'
        ]);
        exit;
    }

    if ($abreviatura === '' || $descricao === '' || $tipo === '') {
        echo json_encode([
            'status' => 'erro',
            'mensagem' => 'Todos os campos são obrigatórios.'
        ]);
        exit;
    }

    // ===================== DUPLICIDADE =====================
    // Evita duplicar abreviatura + tipo (exceto o próprio registro)
    $stmtVerifica = $conexao->prepare("
        SELECT id
        FROM config_tiposvtreqp
        WHERE abreviatura = ?
          AND tipo = ?
          AND id != ?
    ");
    $stmtVerifica->bind_param("ssi", $abreviatura, $tipo, $id);
    $stmtVerifica->execute();
    $resultado = $stmtVerifica->get_result();

    if ($resultado->num_rows > 0) {
        echo json_encode([
            'status' => 'erro',
            'mensagem' => 'Já existe um tipo com esta abreviatura e categoria.'
        ]);
        exit;
    }

    // ===================== UPDATE =====================
    $stmt = $conexao->prepare("
        UPDATE config_tiposvtreqp
        SET abreviatura = ?, descricao = ?, tipo = ?
        WHERE id = ?
    ");
    $stmt->bind_param("sssi", $abreviatura, $descricao, $tipo, $id);

    if ($stmt->execute()) {
				// 🔒 NOVO TOKEN
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
		
        echo json_encode([
            'status' => 'sucesso',
            'mensagem' => 'Tipo de VTR/EQP atualizado com sucesso!'
        ]);
    } else {
        echo json_encode([
            'status' => 'erro',
            'mensagem' => 'Erro ao atualizar o tipo.'
        ]);
    }

} catch (Exception $e) {
    echo json_encode([
        'status' => 'erro',
        'mensagem' => 'Erro inesperado: ' . $e->getMessage()
    ]);
}
