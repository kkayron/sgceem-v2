<style>
.logo-header {
  display: flex;
  align-items: center;
  gap: 10px;
}

.logo-header .logo img {
  max-height: 40px;
}

.nav-toggle {
  display: flex;
  align-items: center;
  gap: 5px;
}

.topbar-toggler {
  margin-left: 5px;
}

</style>
<div class="main-header">
  <div class="main-header-logo">

    <!-- Logo Header -->
    <div class="logo-header" data-background-color="dark">

      <!-- LOGO -->
      <a href="index.html" class="logo">
        <img
          src="assets/img/kaiadmin/logo_light.svg"
          alt="navbar brand"
          class="navbar-brand"
        />
      </a>

      <!-- NAV TOGGLE — MOVIDO PARA A DIREITA -->
      <div class="nav-toggle ms-auto">
        <button class="btn btn-toggle toggle-sidebar">
          <i class="gg-menu-right"></i>
        </button>
        <button class="btn btn-toggle sidenav-toggler">
          <i class="gg-menu-left"></i>
        </button>
      </div>

      <!-- MORE BUTTON -->
      <button class="topbar-toggler more">
        <i class="gg-more-vertical-alt"></i>
      </button>

    </div>
    <!-- End Logo Header -->
  </div>

  <!-- Navbar Header -->
  <nav class="navbar navbar-header navbar-header-transparent navbar-expand-lg border-bottom">
    <div class="container-fluid">

      <ul class="navbar-nav topbar-nav ms-md-auto align-items-center">

        <li class="nav-item topbar-user dropdown hidden-caret">
          <a class="dropdown-toggle profile-pic" data-bs-toggle="dropdown" href="#" aria-expanded="false">
            <div class="avatar-sm">
              <img
                src="assets/fotoperfil/<?= htmlspecialchars($_SESSION['foto'] ?? '') ?>"
                alt="Foto de perfil"
                class="avatar-img rounded-circle"
              />
            </div>
            <span class="profile-username">
              <span class="op-7">Olá,</span>
              <span class="fw-bold">
                <?= htmlspecialchars($_SESSION['postograd'] ?? '') ?>
                <?= htmlspecialchars($_SESSION['nomeguerra'] ?? '') ?>
              </span>
            </span>
          </a>

          <ul class="dropdown-menu dropdown-user animated fadeIn">
            <div class="dropdown-user-scroll scrollbar-outer">

              <li>
                <div class="user-box">
                  <div class="avatar-lg">
                    <img
                      src="assets/fotoperfil/<?= htmlspecialchars($_SESSION['foto'] ?? '') ?>"
                      alt="image profile"
                      class="avatar-img rounded"
                    />
                  </div>
                  <div class="u-text">
                    <h4>
                      <?= htmlspecialchars($_SESSION['postograd'] ?? '') ?>
                      <?= htmlspecialchars($_SESSION['nomeguerra'] ?? '') ?>
                    </h4>
                    <p class="text-muted"><?= htmlspecialchars($_SESSION['email'] ?? '') ?></p>

                    
                  </div>
                </div>
              </li>

              <li>
                <div class="dropdown-divider"></div>
                <a href="#" class="dropdown-item" data-page="includes/perfil/perfil.php">Editar conta</a>
                <div class="dropdown-divider"></div>
                <a class="dropdown-item" href="logout.php">Logout</a>
              </li>

            </div>
          </ul>

        </li>
      </ul>

    </div>
  </nav>
  <!-- End Navbar -->

</div>
