<?php
session_start();
require_once 'conexao/config.php';

$postograd    = $_POST['postograd'] ?? '';
$funcao       = $_POST['funcao'] ?? '';
$nomeguerra   = $_POST['nomeguerra'] ?? '';
$nomecompleto = $_POST['nomecompleto'] ?? '';
$usuario      = $_POST['usuario'] ?? '';
$batalhao     = $_POST['batalhao'] ?? '';
$senha_raw    = $_POST['senha'] ?? '';
$status       = 'não';
$solicitacao  = 'sim';
$fotoNomeFinal = "66758a7f047ac.png"; // nome da foto padrão

// Validação básica
if (!$postograd || !$funcao || !$nomeguerra || !$nomecompleto || !$usuario || !$batalhao || !$senha_raw) {
    $_SESSION['cadastro_erro'] = "Todos os campos são obrigatórios!";
    header("Location: index.php");
    exit;
}

// Hash da senha
$senha = password_hash($senha_raw, PASSWORD_DEFAULT);

// Verificar usuário existente
$stmtVerifica = $conexao->prepare("SELECT id FROM usuarios WHERE usuario = ?");
$stmtVerifica->bind_param("s", $usuario);
$stmtVerifica->execute();
$stmtVerifica->store_result();

if ($stmtVerifica->num_rows > 0) {
    $_SESSION['cadastro_erro'] = "Já existe um usuário com esse nome!";
    header("Location: index.php");
    exit;
}
$stmtVerifica->close();

// Upload da foto
$foto = $fotoNomeFinal; // define padrão
if (!empty($_FILES['foto']['name'])) {
    $ext = pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION);
    $nomeFoto = uniqid('foto_', true) . '.' . $ext;
    $destino = 'assets/fotoperfil/' . $nomeFoto;
    if (move_uploaded_file($_FILES['foto']['tmp_name'], $destino)) {
        $foto = $nomeFoto; // sobrescreve com a foto enviada
    }
}

// Inserir usuário
$stmt = $conexao->prepare("
    INSERT INTO usuarios (usuario, senha, postograd, funcao, foto, nomeguerra, nomecompleto, status, solicitacao, batalhao)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
");
$stmt->bind_param("ssssssssss", $usuario, $senha, $postograd, $funcao, $foto, $nomeguerra, $nomecompleto, $status, $solicitacao, $batalhao);

if ($stmt->execute()) {
    $_SESSION['cadastro_sucesso'] = "Solicitação enviada com sucesso!";
} else {
    $_SESSION['cadastro_erro'] = "Falha ao enviar a solicitação: " . $stmt->error;
}

$stmt->close();
$conexao->close();

header("Location: index.php");
exit;
?>
