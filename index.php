<?php
session_start();
if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit;
}

?>


<style>
 #conteudo {
    visibility: hidden;
       padding-top: 50px;
     width: 100%;
  }
</style>	

<!-- cabeça da página -->
	<?php
			include_once 'partes/head.php'
				?>

  <body>
    <div class="wrapper">
        
        	<!-- menu da página -->
	<?php
			include_once 'partes/sidebar.php'
				?>
        

      <div class="main-panel">
          
<!-- barra de cima de conteúdo da página -->
	<?php
			include_once 'partes/mainhead.php'
				?>
<div id="conteudo">
          <!--  conteúdo da página -->
	
          </div>
              <!--  footer da página -->
	<?php
			include_once 'partes/footer.php'
				?>
          
      </div>
  <!--  final da lateral direita do conteudo -->
      
    </div>
    <!--   Core JS Files   -->
      	<?php
			include_once 'partes/scripts.php'
				?>
       