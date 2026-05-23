<?php
session_start();
include_once('conexao/config.php');

if (isset($_SESSION['usuario_id'])) {

    $usuario_id = $_SESSION['usuario_id'];

    $stmt = $conexao->prepare("
        UPDATE usuarios 
        SET ultima_atividade = NULL
        WHERE id = ?
    ");

    if ($stmt) {
        $stmt->bind_param("i", $usuario_id);
        $stmt->execute();
        $stmt->close();
    }
}

// 🔒 Limpa todas as variáveis da sessão
$_SESSION = [];

// 🔒 Destroi o cookie da sessão também
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();

    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

// 🔒 Destroi a sessão
session_destroy();

// 🔄 Redireciona
header("Location: login.php");
exit;
?>