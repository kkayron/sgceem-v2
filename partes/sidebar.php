
<!-- Sidebar -->
<div class="sidebar military-sidebar" data-background-color="dark">

  <!-- LOGO -->
  <div class="sidebar-logo">
    <div class="logo-header" data-background-color="dark">
      <a href="index.php" class="logo full-logo">
        <img src="assets/img/kaiadmin/logo_light.png" alt="navbar brand" height="38" />
      </a>

      <!-- Logo compacta -->
      <span class="logo-mini text-warning fw-bold">SGC</span>

      <div class="nav-toggle">
        <button class="btn btn-toggle sidenav-toggler">
          <i class="gg-menu-right"></i>
        </button>
      </div>

      <button class="topbar-toggler more">
        <i class="gg-more-vertical-alt"></i>
      </button>
    </div>
  </div>

  <!-- SIDEBAR BODY -->
  <div class="sidebar-wrapper scrollbar scrollbar-inner">
    <div class="sidebar-content">
      <ul class="nav nav-secondary">


<?php
// ======================================================================
// CONEXÃO
// ======================================================================
include_once('conexao/config.php'); // deve fornecer a variável $conexao
	


// ======================================================================
// 1) BUSCAR TÓPICOS PRINCIPAIS
// ======================================================================
$sqlMenu = "SELECT * FROM paginas_principal ORDER BY id ASC";
$menuPrincipal = $conexao->query($sqlMenu);

// ======================================================================
// 2) BUSCAR TODAS AS PÁGINAS
// ======================================================================
$sqlPaginas = "
SELECT p.*, pp.nome AS principal_nome, pp.icone 
FROM paginas p
JOIN paginas_principal pp ON pp.id = p.tipo
ORDER BY pp.ordem ASC, p.ordem ASC
";
$paginasBD = $conexao->query($sqlPaginas);

// ======================================================================
// AGRUPAR POR TIPO + VALIDAR PERMISSÕES
// ======================================================================
$paginasAgrupadas = [];
$permissoes = $_SESSION["permissoes"];

while ($p = $paginasBD->fetch_assoc()) {

    $pid = $p["id"];

    // SE NÃO TEM PERMISSÃO PARA ACESSAR → pula
    if (empty($permissoes[$pid]["pode_acessar"])) {
        continue;
    }

    // AGRUPA
    $paginasAgrupadas[$p["tipo"]][] = $p;
}
?>

<?php
// ======================================================================
// 3) GERAR MENU LATERAL DINÂMICO
// ======================================================================
while ($top = $menuPrincipal->fetch_assoc()):
    $tipo = $top["id"];

    // Se usuário não tem nenhuma página deste grupo → não exibe
    if (empty($paginasAgrupadas[$tipo])) continue;

    // ID único do collapse
    $collapseId = "menu" . $tipo;
?>

<!-- ITEM PRINCIPAL -->
<li class="nav-item">
  <a data-bs-toggle="collapse" href="#<?= $collapseId ?>">
    <i class="<?= $top['icone'] ?>"></i>
    <p><?= $top['nome'] ?></p>
    <span class="caret"></span>
  </a>

  <!-- SUBMENU DINÂMICO -->
  <div class="collapse" id="<?= $collapseId ?>">
    <ul class="nav nav-collapse">
      <?php foreach ($paginasAgrupadas[$tipo] as $p): ?>
        <li>
          <a href="#" data-page="<?= $p['arquivo'] ?>">
            <span class="dot"></span>
            <span class="sub-item"><?= $p['nome'] ?></span>
          </a>
        </li>
      <?php endforeach; ?>
    </ul>
  </div>
</li>

<?php endwhile; ?>

<li class="nav-section mt-3">
  <span class="sidebar-mini-icon"><i class="fa fa-ellipsis-h"></i></span>
  <h4 class="text-section text-uppercase">Atalhos</h4>
</li>
		   <li class="nav-item military-nav-item"><a href="#" data-page="includes/perfil/perfil.php"><span class="dot"></span> 
    <i class="fas fa-user-edit"></i><span class="sub-item">Editar Perfil</span></a></li>


<li class="nav-item military-nav-item">
  <a href="logout.php" id="btnLogout">
    <i class="fas fa-sign-out-alt"></i>
    <p>Sair</p>
  </a>
</li>

      </ul>
    </div>
  </div>
</div>
<!-- End Sidebar -->
