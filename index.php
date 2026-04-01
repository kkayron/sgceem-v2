<?php
session_start();
if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit;
}
?>	
	<?php
			include_once 'partes/head.php'
				?>
  <body>
    <div class="wrapper">
	<?php
			include_once 'partes/sidebar.php'
				?>
      <div class="main-panel">   
	<?php
			include_once 'partes/mainhead.php'
				?>
<div id="conteudo">
          </div>
	<?php
			include_once 'partes/footer.php'
				?>
          
      </div>
    </div>
      	<?php
			include_once 'partes/scripts.php'
				?>
       