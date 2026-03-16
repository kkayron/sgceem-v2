<?php
include_once("../../conexao/config.php");
include_once("../../includes/funcoes/log.php");
session_start();

$id = $_POST['id'] ?? null;
$status = $_POST['status'] ?? null;

if (!$id || ($status !== 'sim' && $status !== 'não')) {
    echo "Parâmetros inválidos";
    exit;
}

// Buscar dados do usuário (função, OM, nomeguerra, posto/grad)
$stmtUsuario = $conexao->prepare("SELECT postograd, nomeguerra, funcao, batalhao FROM usuarios WHERE id = ?");
$stmtUsuario->bind_param("i", $id);
$stmtUsuario->execute();
$stmtUsuario->bind_result($postograd, $usuario, $funcao, $batalhao);
$stmtUsuario->fetch();
$stmtUsuario->close();

// Definir funções exclusivas
$funcoes_exclusivas = ['Cmt Cia E Eqp Mnt', 'Ch Financeiro', 'Ch Seç Ctrl'];

// Se estiver ativando e for função exclusiva, verifica se já existe outro ativo na mesma OM
if ($status === 'sim' && in_array($funcao, $funcoes_exclusivas)) {
    $stmtVerifica = $conexao->prepare("
        SELECT COUNT(*) 
        FROM usuarios 
        WHERE funcao = ? 
          AND status = 'sim' 
          AND id != ? 
          AND batalhao = ?
    ");
    $stmtVerifica->bind_param("sii", $funcao, $id, $batalhao);
    $stmtVerifica->execute();
    $stmtVerifica->bind_result($total);
    $stmtVerifica->fetch();
    $stmtVerifica->close();

    if ($total > 0) {
        echo "Já existe outro usuário ativo com a função exclusiva '$funcao' nesta Organização Militar.";
        exit;
    }
}

// Atualiza status e limpa solicitação
$stmt = $conexao->prepare("UPDATE usuarios SET status = ?, solicitacao = 'não' WHERE id = ?");
$stmt->bind_param("si", $status, $id);

if ($stmt->execute()) {
    $usuarioLogado = $_SESSION['usuario_id'] ?? 0;
    $acao = $status === 'sim' ? 'Ativou' : 'Desativou';
    $descricao = "$acao o usuário ID $id ($postograd $usuario)";
    registrar_log($conexao, $usuarioLogado, 'Alterar status de usuário', $descricao);
    
    echo "ok";
} else {
    echo "Erro ao alterar status: " . $stmt->error;
}

$stmt->close();
?>
