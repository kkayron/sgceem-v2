<?php
session_start();
include_once("../../conexao/config.php");
include_once("../../includes/funcoes/log.php");

$postograd     = $_POST['postograd'];
$nomeguerra    = $_POST['nomeguerra'];
$nomecompleto  = $_POST['nomecompleto'];
$usuario       = $_POST['usuario'];
$funcao        = $_POST['funcao'];
$batalhao      = $_POST['batalhao']; // campo obrigatório
$status        = 'sim';
$senha         = password_hash($_POST['senha'], PASSWORD_DEFAULT);

// ------------------------------
// Verifica se o nome de usuário já existe
// ------------------------------
$verifica = $conexao->prepare("SELECT id FROM usuarios WHERE usuario = ?");
$verifica->bind_param("s", $usuario);
$verifica->execute();
$verifica->store_result();

if ($verifica->num_rows > 0) {
    echo "Usuário já existe";
    exit;
}
$verifica->close();

// ------------------------------
// Verifica funções exclusivas dentro do mesmo batalhão
// ------------------------------
$funcoes_exclusivas = ["Cmt Cia E Eqp Mnt", "Ch Seç Ctrl", "Ch Financeiro"];

if ($status === 'sim' && in_array($funcao, $funcoes_exclusivas)) {
    $stmtFuncao = $conexao->prepare("
        SELECT id 
        FROM usuarios 
        WHERE funcao = ? AND status = 'sim' AND batalhao = ?
    ");
    $stmtFuncao->bind_param("ss", $funcao, $batalhao);
    $stmtFuncao->execute();
    $stmtFuncao->store_result();

    if ($stmtFuncao->num_rows > 0) {
        echo "Já existe um usuário ativo com a função '$funcao' neste batalhão ($batalhao). 
              Apenas um usuário ativo por função exclusiva é permitido na mesma OM.";
        $stmtFuncao->close();
        exit;
    }
    $stmtFuncao->close();
}

// ------------------------------
// Upload de foto de perfil
// ------------------------------
$diretorio = "../../assets/fotoperfil/";
$fotoNomeFinal = "66758a7f047ac.png"; // imagem padrão

if (isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
    $arquivoTmp = $_FILES['foto']['tmp_name'];
    $nomeOriginal = $_FILES['foto']['name'];
    $tamanho = $_FILES['foto']['size'];

    if ($tamanho <= 20 * 1024 * 1024) { // limite 20 MB
        $extensao = pathinfo($nomeOriginal, PATHINFO_EXTENSION);
        $codigoUnico = uniqid();
        $fotoNomeFinal = $codigoUnico . "." . $extensao;
        move_uploaded_file($arquivoTmp, $diretorio . $fotoNomeFinal);
    }
}

// ------------------------------
// Inserção no banco
// ------------------------------
$sql = "
    INSERT INTO usuarios 
    (postograd, nomeguerra, usuario, funcao, senha, foto, status, nomecompleto, batalhao) 
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
";
$stmt = $conexao->prepare($sql);
$stmt->bind_param("sssssssss", $postograd, $nomeguerra, $usuario, $funcao, $senha, $fotoNomeFinal, $status, $nomecompleto, $batalhao);

if ($stmt->execute()) {
    // LOG
    $usuarioLogado = $_SESSION['usuario_id'] ?? 0;
    $descricao = "Novo usuário cadastrado: {$postograd} {$nomeguerra} ({$usuario}) - Função: {$funcao}, Batalhão: {$batalhao}";
    registrar_log($conexao, $usuarioLogado, 'Cadastrar usuário', $descricao);

    echo "ok";
} else {
    echo "Erro ao salvar no banco: " . $stmt->error;
}

$stmt->close();
?>
