<?php
include_once('../../conexao/config.php');

$id_om_maior = $_POST['id_om_maior'] ?? null;
$id_om_menor = $_POST['id_om_menor'] ?? null;
$ativo = $_POST['ativo'] ?? 0;

if (!$id_om_maior || !$id_om_menor) {
    echo json_encode(['sucesso' => false, 'mensagem' => 'Parâmetros inválidos.']);
    exit;
}

if ($ativo) {
    // Inserir relação se ainda não existir
    $stmt = $conexao->prepare("SELECT id FROM organizacoes_militares_sub WHERE id_om_maior = ? AND id_om_menor = ?");
    $stmt->bind_param("ii", $id_om_maior, $id_om_menor);
    $stmt->execute();
    if ($stmt->get_result()->num_rows == 0) {
        $stmtIns = $conexao->prepare("INSERT INTO organizacoes_militares_sub (id_om_maior, id_om_menor) VALUES (?, ?)");
        $stmtIns->bind_param("ii", $id_om_maior, $id_om_menor);
        $stmtIns->execute();
    }
    echo json_encode(['sucesso' => true]);
} else {
    // Remover relação
    $stmtDel = $conexao->prepare("DELETE FROM organizacoes_militares_sub WHERE id_om_maior = ? AND id_om_menor = ?");
    $stmtDel->bind_param("ii", $id_om_maior, $id_om_menor);
    $stmtDel->execute();
    echo json_encode(['sucesso' => true]);
}
?>
