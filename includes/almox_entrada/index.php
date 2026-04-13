<?php
// Retorna código HTTP de acesso proibido
http_response_code(403);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<title>Área Restrita</title>

<style>

*{
    box-sizing:border-box;
}

body{
    margin:0;
    font-family:Segoe UI, Tahoma, sans-serif;
    background:linear-gradient(135deg,#0f2027,#203a43,#2c5364);
    color:white;
    display:flex;
    align-items:center;
    justify-content:center;
    height:100vh;
    text-align:center;
}

.container{
    max-width:600px;
    padding:40px;
    background:rgba(0,0,0,0.45);
    border-radius:12px;
    box-shadow:0 10px 35px rgba(0,0,0,0.5);
}

h1{
    font-size:48px;
    margin-bottom:10px;
}

h2{
    margin-top:0;
    font-weight:400;
    color:#f1c40f;
}

p{
    line-height:1.6;
    opacity:0.9;
}

.icon{
    font-size:70px;
    margin-bottom:20px;
}

.btn{
    display:inline-block;
    margin-top:25px;
    padding:12px 26px;
    background:#3498db;
    color:white;
    text-decoration:none;
    border-radius:6px;
    font-weight:bold;
    transition:0.3s;
}

.btn:hover{
    background:#2980b9;
    transform:scale(1.05);
}

.footer{
    margin-top:25px;
    font-size:13px;
    opacity:0.6;
}

</style>

</head>

<body>

<div class="container">

<div class="icon">🔒</div>

<h1>Acesso Bloqueado</h1>

<h2>Área reservada do sistema</h2>

<p>
Hmm... parece que você tentou entrar por uma porta que não é pública.
</p>

<p>
Essa pasta faz parte do sistema interno e não está aberta para visitas curiosas 👀
</p>

<p>
Mas não se preocupe, isso acontece até com os melhores exploradores da internet.
</p>

<a class="btn" href="/index.php">Voltar ao sistema</a>

<div class="footer">
Sistema protegido • Acesso controlado
</div>

</div>

</body>
</html>