<?php
session_start();
include_once('../../conexao/config.php');
include_once('../funcoes/log.php');

if (!isset($_SESSION['usuario_id'])) {
    exit('Acesso negado');
}

$id = $_SESSION['usuario_id'];

$postograd = $_POST['postograd'] ?? '';
$nomeguerra = $_POST['nomeguerra'] ?? '';
$nomecompleto = $_POST['nomecompleto'] ?? '';
$usuario = $_POST['usuario'] ?? '';
$senha = $_POST['senha'] ?? '';

$foto_nome = null;

if (isset($_FILES['foto']) && $_FILES['foto']['error'] === 0) {

$ext = pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION);

$foto_nome = uniqid().'.'.$ext;

move_uploaded_file(
$_FILES['foto']['tmp_name'],
'../../assets/fotoperfil/'.$foto_nome
);

}

$stmt_antigo = $conexao->prepare("SELECT * FROM usuarios WHERE id=?");
$stmt_antigo->bind_param("i", $id);
$stmt_antigo->execute();
$result_antigo = $stmt_antigo->get_result();
$usuario_antigo = $result_antigo->fetch_assoc();
$stmt_antigo->close();


$sql = "UPDATE usuarios
SET postograd=?,
nomeguerra=?,
nomecompleto=?,
usuario=?";

$params = [$postograd,$nomeguerra,$nomecompleto,$usuario];
$types = "ssss";

if(!empty($senha)){

$senha_hash = password_hash($senha,PASSWORD_DEFAULT);

$sql .= ", senha=?";
$params[] = $senha_hash;
$types .= "s";

}

if($foto_nome){

$sql .= ", foto=?";
$params[] = $foto_nome;
$types .= "s";

}

$sql .= " WHERE id=?";
$params[] = $id;
$types .= "i";

$stmt = $conexao->prepare($sql);
$stmt->bind_param($types,...$params);

if($stmt->execute()){

if($stmt->execute()){

$alteracoes = [];

if ($postograd !== $usuario_antigo['postograd']) {
    $alteracoes[] = "Posto/Grad: '{$usuario_antigo['postograd']}' → '$postograd'";
}

if ($nomeguerra !== $usuario_antigo['nomeguerra']) {
    $alteracoes[] = "Nome de Guerra: '{$usuario_antigo['nomeguerra']}' → '$nomeguerra'";
}

if ($nomecompleto !== $usuario_antigo['nomecompleto']) {
    $alteracoes[] = "Nome Completo alterado";
}

if ($usuario !== $usuario_antigo['usuario']) {
    $alteracoes[] = "Usuário: '{$usuario_antigo['usuario']}' → '$usuario'";
}

if (!empty($senha)) {
    $alteracoes[] = "Senha alterada";
}

if (!empty($foto_nome)) {
    $alteracoes[] = "Foto de perfil atualizada";
}

$descricao = "Usuário editou o próprio perfil: " . implode("; ", $alteracoes);

registrar_log(
    $conexao,
    $_SESSION['usuario_id'],
    "Editar Perfil",
    $descricao
);

echo "ok";

}else{

echo "erro";

}

}else{

echo "erro";

}