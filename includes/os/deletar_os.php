<?php
session_start();
include_once("../../conexao/config.php");
include_once("../../includes/funcoes/log.php");

header('Content-Type: application/json; charset=utf-8');

$id = $_POST['id'] ?? null;

if (!$id) {
    echo json_encode(['success' => false, 'message' => 'ID inválido']);
    exit;
}

// Buscar dados da OS antes de deletar
$stmt_select = $conexao->prepare("SELECT id, problema, status, data_abertura FROM os_principal WHERE id = ?");
$stmt_select->bind_param("i", $id);
$stmt_select->execute();
$result = $stmt_select->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'OS não encontrada']);
    exit;
}

$os = $result->fetch_assoc();
$stmt_select->close();

$usuarioLogado = $_SESSION['usuario_id'] ?? 0;

// Função auxiliar para buscar e logar registros relacionados
function logar_relacionados($conexao, $tabela, $campo_relacionamento, $os_principal, $usuarioLogado) {
    $stmt = $conexao->prepare("SELECT * FROM $tabela WHERE $campo_relacionamento = ?");
    $stmt->bind_param("s", $os_principal);
    $stmt->execute();
    $result = $stmt->get_result();

    $descricao = "";
    while ($row = $result->fetch_assoc()) {
        $descricao .= json_encode($row) . "\n";
    }
    $stmt->close();

    if (!empty($descricao)) {
        $descricao_logs = "Registros da tabela $tabela relacionados à OS ID $os_principal serão excluídos:\n" . $descricao;
        registrar_log($conexao, $usuarioLogado, "Excluir $tabela", $descricao_logs, $os_principal);
    }
}

// Tabelas relacionadas e campo de relacionamento
$tabelas_relacionadas = [
    'os_falhas' => 'id_osprincipal',
    'os_itens' => 'id_osprincipal',
    'os_rlzdmnt' => 'id_osprincipal',
    'os_pessoal' => 'id_osprincipal'
];

// Logar e deletar registros relacionados
foreach ($tabelas_relacionadas as $tabela => $campo_relacionamento) {
    logar_relacionados($conexao, $tabela, $campo_relacionamento, $id, $usuarioLogado);

    $stmt_delete = $conexao->prepare("DELETE FROM $tabela WHERE $campo_relacionamento = ?");
    $stmt_delete->bind_param("s", $id);
    $stmt_delete->execute();
    $stmt_delete->close();
}

// Deletar OS principal
$stmt_delete_os = $conexao->prepare("DELETE FROM os_principal WHERE id = ?");
$stmt_delete_os->bind_param("i", $id);

if ($stmt_delete_os->execute()) {
    $descricao = "OS ID $id deletada: Problema: {$os['problema']}, Status: {$os['status']}, Data de Abertura: {$os['data_abertura']}";
    registrar_log($conexao, $usuarioLogado, 'Deletar OS', $descricao, $id);

    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Erro ao deletar: ' . $stmt_delete_os->error]);
}

$stmt_delete_os->close();
?>
