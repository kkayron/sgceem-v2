<?php

session_start();
include '../../conexao/config.php';
include '../funcoes/log.php';

if($_SERVER['REQUEST_METHOD'] !== 'POST'){
    echo "Requisição inválida";
    exit;
}

$id = intval($_POST['id'] ?? 0);

$data_empenho = $_POST['data_empenho'] ?? '';
$nmr_empenho = $_POST['nmr_empenho'] ?? '';
$obra = $_POST['obra'] ?? '';
$ano = $_POST['ano'] ?? '';
$categoria = $_POST['categoria'] ?? '';
$local = $_POST['local'] ?? '';
$resto_pagar = $_POST['resto_pagar'] ?? '';

if($id <= 0 || empty($data_empenho) || empty($nmr_empenho)){
    echo "Dados incompletos";
    exit;
}


// VERIFICAR SE EMPENHO JÁ EXISTE

$sqlCheck = "SELECT id FROM fin_empenhos WHERE id_requisicao=? LIMIT 1";

$stmt = $conexao->prepare($sqlCheck);
$stmt->bind_param("i",$id);
$stmt->execute();
$res = $stmt->get_result();
$existe = $res->fetch_assoc();
$stmt->close();


// UPDATE OU INSERT

if($existe){

$sql = "UPDATE fin_empenhos SET
data_empenho=?,
nmr_empenho=?,
obra=?,
ano=?,
categoria=?,
local=?,
resto_pagar=?
WHERE id_requisicao=?";

$stmt = $conexao->prepare($sql);

$stmt->bind_param(
"sssssssi",
$data_empenho,
$nmr_empenho,
$obra,
$ano,
$categoria,
$local,
$resto_pagar,
$id
);

}else{

$sql = "INSERT INTO fin_empenhos
(id_requisicao,data_empenho,nmr_empenho,obra,ano,categoria,local,resto_pagar)
VALUES (?,?,?,?,?,?,?,?)";

$stmt = $conexao->prepare($sql);

$stmt->bind_param(
"isssssss",
$id,
$data_empenho,
$nmr_empenho,
$obra,
$ano,
$categoria,
$local,
$resto_pagar
);

}

if(!$stmt->execute()){
    echo "Erro ao salvar empenho";
    exit;
}

$stmt->close();


// ATUALIZAR STATUS DA REQUISIÇÃO

$sql2 = "UPDATE fin_requisicao
SET
status_requisicao='Empenho gerado',
empenho_gerado='sim',
nmr_empenho=?
WHERE id=?";

$stmt2 = $conexao->prepare($sql2);
$stmt2->bind_param("si",$nmr_empenho,$id);
$stmt2->execute();
$stmt2->close();


// LOG

$usuarioLogado = $_SESSION['usuario_id'] ?? 0;

$descricao = "Empenho gerado para requisição $id | Nº $nmr_empenho";

registrar_log(
$conexao,
$usuarioLogado,
"Gerar Empenho",
$descricao,
$id
);


echo "ok";
exit;

?>