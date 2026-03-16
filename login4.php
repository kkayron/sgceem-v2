<?php
session_start();
require_once 'conexao/config.php';

/* MANUTENÇÃO */
$manut = $conexao->query("SELECT valor FROM manutencao WHERE id=1 LIMIT 1")->fetch_assoc();
if (($manut['valor'] ?? 'não') === 'sim'){
    header("Location: manutencao.php");
    exit;
}

/* BATALHÕES */
$batalhoes = $conexao->query("SELECT id,nome,abreviatura FROM organizacoes_militares ORDER BY abreviatura");
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">

<title>SGCEEM - Login</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">

<style>

:root{
--mil-green:#2e4a2f;
--mil-green-soft:#4d6a4e;
--mil-gold:#c7a84a;
}

body{

min-height:100vh;

background:
linear-gradient(rgba(0,0,0,.65),rgba(0,0,0,.65)),
url("imgnovas/tablet_oficina.jpeg") center/cover no-repeat fixed;

font-family:Segoe UI;
display:flex;
align-items:center;
justify-content:center;
padding:20px;
color:#fff;

}

.wrapper{

width:100%;
max-width:1200px;
display:grid;
grid-template-columns:1fr 420px;
gap:40px;
align-items:center;

}

.left{

padding:20px;

}

.title{

font-size:clamp(1.8rem,3vw,3rem);
font-weight:800;
line-height:1.2;

}

.title span{

color:var(--mil-gold);

}

.subtitle{

opacity:.9;
margin-top:15px;
line-height:1.6;

}

.features{

display:grid;
grid-template-columns:repeat(auto-fit,minmax(180px,1fr));
gap:15px;
margin-top:30px;

}

.feature{

background:rgba(255,255,255,.08);
border-radius:14px;
padding:18px;
backdrop-filter:blur(10px);

}

.feature i{

color:var(--mil-gold);
font-size:22px;
margin-bottom:8px;

}

.login-box{

background:#fff;
color:#333;
border-radius:20px;
padding:35px;
box-shadow:0 20px 50px rgba(0,0,0,.35);
animation:fade .6s ease;

}

.logo{

width:80px;
display:block;
margin:auto;

}

.login-title{

text-align:center;
margin-top:10px;
font-weight:800;
color:var(--mil-green);

}

.form-control{

border-radius:12px;
height:50px;

}

.input-group-text{

cursor:pointer;

}

.btn-login{

width:100%;
border-radius:12px;
padding:12px;
background:linear-gradient(135deg,var(--mil-green),var(--mil-green-soft));
border:none;
color:#fff;
font-weight:700;

}

.btn-login:hover{

opacity:.9;

}

.btn-cadastro{

width:100%;
border-radius:12px;

}

.loading{

display:none;

}

.footer{

margin-top:20px;
font-size:.85rem;
text-align:center;
opacity:.7;

}

@keyframes fade{

from{opacity:0;transform:translateY(15px)}
to{opacity:1}

}

@media(max-width:900px){

.wrapper{

grid-template-columns:1fr;

}

.left{

display:none;

}

}

</style>
</head>

<body>

<div class="wrapper">

<div class="left">

<div class="title">
<span>SGCEEM</span><br>
Sistema de Gerenciamento da Cia E Eqp Mnt
</div>

<div class="subtitle">
Plataforma militar para controle de manutenção,
viaturas, equipamentos, almoxarifado e gestão operacional.
</div>

<div class="features">

<div class="feature">
<i class="fa fa-tools"></i>
<br>Ordens de Serviço
</div>

<div class="feature">
<i class="fa fa-truck"></i>
<br>Gestão da Frota
</div>

<div class="feature">
<i class="fa fa-chart-line"></i>
<br>Relatórios
</div>

<div class="feature">
<i class="fa fa-warehouse"></i>
<br>Almoxarifado
</div>

</div>

</div>

<div>

<form action="valida_login.php" method="POST" class="login-box" id="loginForm">

<img src="imagens/icon_png_mnt_2.png" class="logo">

<h3 class="login-title">Acesso ao Sistema</h3>

<?php if(isset($_SESSION['login_erro'])): ?>

<div class="alert alert-danger mt-3">
<?= $_SESSION['login_erro'] ?>
</div>

<?php unset($_SESSION['login_erro']); endif; ?>

<div class="mt-4">

<input type="text" name="usuario" class="form-control" placeholder="Usuário" required>

</div>

<div class="mt-3">

<div class="input-group">

<input type="password" name="senha" id="senha" class="form-control" placeholder="Senha" required>

<span class="input-group-text" onclick="toggleSenha()">
<i class="fa fa-eye"></i>
</span>

</div>

</div>

<button class="btn-login mt-4" id="btnLogin">

<span class="normal">
<i class="fa fa-right-to-bracket"></i> Entrar
</span>

<span class="loading">
<i class="fa fa-spinner fa-spin"></i> Entrando...
</span>

</button>

<hr>

<button type="button" class="btn btn-outline-secondary btn-cadastro"
data-bs-toggle="modal"
data-bs-target="#modalCadastro">

Solicitar Cadastro

</button>

<div class="footer">
SGCEEM © 2024<br>
Desenvolvido pelo <b>1º Ten Felipe Alves</b>
</div>

</form>

</div>

</div>

<script>

function toggleSenha(){

const campo=document.getElementById("senha");

campo.type=campo.type==="password"?"text":"password";

}

document.getElementById("loginForm").addEventListener("submit",function(){

document.querySelector(".normal").style.display="none";
document.querySelector(".loading").style.display="inline";

});

</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>