<?php
header('Content-Type: application/json');
session_start();
include_once('../../conexao/config.php');

$funcao_id = intval($_POST['funcao_id'] ?? 0);
$pagina_id = intval($_POST['pagina_id'] ?? 0);
$campo = $_POST['campo'] ?? '';
$valor = intval($_POST['valor'] ?? 0);

$campos_validos = ['pode_acessar', 'pode_editar', 'pode_deletar', 'pode_cadastrar', 'pode_importar', 'pode_exportar'];
if ($funcao_id <= 0 || $pagina_id <= 0 || !in_array($campo, $campos_validos)) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Parâmetros inválidos.']);
    exit;
}

// Verifica se já existe permissão
$stmtCheck = $conexao->prepare("SELECT id FROM permissoes WHERE funcao_id = ? AND pagina_id = ?");
$stmtCheck->bind_param("ii", $funcao_id, $pagina_id);
$stmtCheck->execute();
$stmtCheck->store_result();

if ($stmtCheck->num_rows > 0) {
    // Atualiza campo específico
    $sql = "UPDATE permissoes SET $campo = ? WHERE funcao_id = ? AND pagina_id = ?";
    $stmt = $conexao->prepare($sql);
    $stmt->bind_param("iii", $valor, $funcao_id, $pagina_id);
} else {
    // Insere nova permissão
    $sql = "INSERT INTO permissoes (funcao_id, pagina_id, $campo) VALUES (?, ?, ?)";
    $stmt = $conexao->prepare($sql);
    $stmt->bind_param("iii", $funcao_id, $pagina_id, $valor);
}

if ($stmt->execute()) {
    echo json_encode(['status' => 'sucesso']);
} else {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Erro ao atualizar permissão.']);
}

$stmt->close();
?>
