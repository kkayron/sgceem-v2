<!-- Sidebar -->
<div class="sidebar" data-background-color="dark">
<div class="sidebar-logo">
          <!-- Logo Header -->
          <div class="logo-header" data-background-color="dark">
            <a href="index.html" class="logo">
              <img
                src="assets/img/kaiadmin/logo_light.png"
                alt="navbar brand"
                class="navbar-brand"
                height="40"
              />
            </a>
            <div class="nav-toggle">
              <button class="btn btn-toggle toggle-sidebar">
                <i class="gg-menu-right"></i>
              </button>
              <button class="btn btn-toggle sidenav-toggler">
                <i class="gg-menu-left"></i>
              </button>
            </div>
            <button class="topbar-toggler more">
              <i class="gg-more-vertical-alt"></i>
            </button>
          </div>
          <!-- End Logo Header -->
        </div>

  <div class="sidebar-wrapper scrollbar scrollbar-inner">
    <div class="sidebar-content">
      <ul class="nav nav-secondary">

        <!-- Página Inicial -->
        <li class="nav-item active">
          <a data-bs-toggle="collapse" href="#dashboard" class="collapsed" aria-expanded="false">
            <i class="fas fa-home"></i>
            <p>Página Inicial</p>
            <span class="caret"></span>
          </a>
          <div class="collapse" id="dashboard">
            <ul class="nav nav-collapse">
              <li>
                <a href="#" data-page="partes/conteudo.php"><span class="dot"></span>
                  <span class="sub-item">Dashboard 1</span>
                </a>
              </li>
              <li>
                <a href="#" data-page="partes/conteudo2.php"><span class="dot"></span>
                  <span class="sub-item">Conteúdo sem PHP</span>
                </a>
              </li>
            </ul>
          </div>
        </li>

        <!-- Seção Navegação -->
        <li class="nav-section mt-3">
          <span class="sidebar-mini-icon"><i class="fa fa-ellipsis-h"></i></span>
          <h4 class="text-section text-uppercase">Navegação</h4>
        </li>

        <!-- Frota -->
        <li class="nav-item">
          <a data-bs-toggle="collapse" href="#base">
            <i class="fas fa-car-alt"></i>
            <p>Frota</p>
            <span class="caret"></span>
          </a>
          <div class="collapse" id="base">
            <ul class="nav nav-collapse">
              <li><a href="#" data-page="includes/frota/cadastro.php"><span class="dot"></span> <span class="sub-item">Cadastrar Vtr/Eqp</span></a></li>
              <li><a href="#" data-page="includes/odometro/controle.php"><span class="dot"></span> <span class="sub-item">Atualizar Odo/Hor</span></a></li>
              <li><a href="#" data-page="includes/frota/listagem.php"><span class="dot"></span> <span class="sub-item">Listar Eqp/Vtr</span></a></li>
            </ul>
          </div>
        </li>

        <!-- Seção de Controle -->
        <li class="nav-item">
          <a data-bs-toggle="collapse" href="#sidebarLayouts">
            <i class="fas fa-desktop"></i>
            <p>Seção de Controle</p>
            <span class="caret"></span>
          </a>
          <div class="collapse" id="sidebarLayouts">
            <ul class="nav nav-collapse">
              <li><a href="#" data-page="includes/os/listagem.php"><span class="dot"></span> <span class="sub-item">Ordens de Serviço</span></a></li>
              <li><a href="#" data-page="includes/almox_pedidos/listagem.php"><span class="dot"></span> <span class="sub-item">Pedidos (Almox)</span></a></li>
              <li><a href="#" data-page="includes/fin_pedidos/listagem.php"><span class="dot"></span> <span class="sub-item">Pedidos (Fornecedor)</span></a></li>
              <li><a href="#" data-page="includes/fin_ordensdefornecimento/listagem.php"><span class="dot"></span> <span class="sub-item">Ordens de Fornecimento</span></a></li>
            </ul>
          </div>
        </li>

        <!-- Manutenção Preventiva -->
        <li class="nav-item">
          <a data-bs-toggle="collapse" href="#preventiva">
            <i class="fas fa-cogs"></i>
            <p>Manutenção Preventiva</p>
            <span class="caret"></span>
          </a>
          <div class="collapse" id="preventiva">
            <ul class="nav nav-collapse">
              <li><a href="#" data-page="includes/preventiva/listagem.php"><span class="dot"></span> <span class="sub-item">Controle da Preventiva</span></a></li>
              <li><a href="#" data-page="includes/odometro/controle.php"><span class="dot"></span> <span class="sub-item">Atualizar Odo/Hor</span></a></li>
            </ul>
          </div>
        </li>

        <!-- Financeiro -->
        <li class="nav-item">
          <a data-bs-toggle="collapse" href="#forms">
            <i class="fas fa-donate"></i>
            <p>Financeiro</p>
            <span class="caret"></span>
          </a>
          <div class="collapse" id="forms">
            <ul class="nav nav-collapse">
              <li><a href="#" data-page="includes/fin_fornecedores/listagem.php"><span class="dot"></span> <span class="sub-item">Fornecedores</span></a></li>
              <li><a href="#" data-page="includes/fin_pregoes/listagem.php"><span class="dot"></span> <span class="sub-item">Pregões</span></a></li>
              <li><a href="#" data-page="includes/fin_requisicoes/listagem.php"><span class="dot"></span> <span class="sub-item">Requisição p/ SALC</span></a></li>
              <li><a href="#" data-page="includes/fin_empenhos/listagem.php"><span class="dot"></span> <span class="sub-item">Todos Empenhos</span></a></li>
              <li><a href="#" data-page="includes/fin_pedidos/listagem.php"><span class="dot"></span> <span class="sub-item">Pedidos Fornecedor</span></a></li>
              <li><a href="#" data-page="includes/fin_ordensdefornecimento/listagem.php"><span class="dot"></span> <span class="sub-item">Ordens de Fornecimento</span></a></li>
              <li><a href="#" data-page="includes/fin_conrazao/conrazao_corrente.php"><span class="dot"></span> <span class="sub-item">CONRAZAO Corrente</span></a></li>
              <li><a href="#" data-page="includes/fin_conrazao/conrazao_rp.php"><span class="dot"></span> <span class="sub-item">CONRAZAO RP</span></a></li>
            </ul>
          </div>
        </li>

        <!-- STA -->
        <li class="nav-item">
          <a data-bs-toggle="collapse" href="#tables">
            <i class="fas fa-car-side"></i>
            <p>STA</p>
            <span class="caret"></span>
          </a>
          <div class="collapse" id="tables">
            <ul class="nav nav-collapse">
              <li><a href="#" data-page="includes/sta_fichas/listagem.php"><span class="dot"></span> <span class="sub-item">Fichas Vtr/Eqp</span></a></li>
              <li><a href="#" data-page="includes/sta_fichas/emprego.php"><span class="dot"></span> <span class="sub-item">Emprego dos Meios</span></a></li>
            </ul>
          </div>
        </li>

        <!-- Almox Peças -->
        <li class="nav-item">
          <a data-bs-toggle="collapse" href="#maps">
            <i class="fas fa-dolly"></i>
            <p>Almox Peças</p>
            <span class="caret"></span>
          </a>
          <div class="collapse" id="maps">
            <ul class="nav nav-collapse">
              <li><a href="#" data-page="includes/almox_produtos/listagem.php"><span class="dot"></span> <span class="sub-item">Produtos</span></a></li>
              <li><a href="#" data-page="includes/almox_entrada/listagem.php"><span class="dot"></span> <span class="sub-item">Entradas</span></a></li>
              <li><a href="#" data-page="includes/almox_pedidos/listagem.php"><span class="dot"></span> <span class="sub-item">Pedidos (Saídas)</span></a></li>
              <li><a href="#" data-page="includes/almox_estoque/listagem.php"><span class="dot"></span> <span class="sub-item">Estoque</span></a></li>
            </ul>
          </div>
        </li>

        <!-- Cadastros -->
        <li class="nav-item">
          <a data-bs-toggle="collapse" href="#cadastros">
            <i class="fas fa-cog"></i>
            <p>Cadastros</p>
            <span class="caret"></span>
          </a>
          <div class="collapse" id="cadastros">
            <ul class="nav nav-collapse">
              <li><a href="#" data-page="includes/marcas_modelos/listagem.php"><span class="sub-item">Marcas e Modelos</span></a></li>
              <li><a href="#" data-page="includes/config_destinos/listagem.php"><span class="sub-item">Destinos de Vtr/Eqp</span></a></li>
            </ul>
          </div>
        </li>

        <!-- Configurações -->
        <li class="nav-item">
          <a data-bs-toggle="collapse" href="#charts">
            <i class="fas fa-sliders-h"></i>
            <p>Configurações</p>
            <span class="caret"></span>
          </a>
          <div class="collapse" id="charts">
            <ul class="nav nav-collapse">
              <li><a href="#" data-page="includes/usuarios/listagem.php"><span class="dot"></span> <span class="sub-item">Usuários</span></a></li>
              <li><a href="#" data-page="includes/logs/listagem.php"><span class="dot"></span> <span class="sub-item">Registro de Atividades</span></a></li>
              <li><a href="#" data-page="includes/funcoes_militares/listagem.php"><span class="sub-item">Funções dos Usuários</span></a></li>
              <li><a href="#" data-page="includes/paginas/listagem.php"><span class="sub-item">Páginas e Permissões</span></a></li>
              <li><a href="#" data-page="includes/oms/listagem.php"><span class="sub-item">Organizações Militares</span></a></li>
            </ul>
          </div>
        </li>

        <!-- Manuais -->
        <li class="nav-item">
          <a href="../../documentation/index.html">
            <i class="fas fa-book"></i>
            <p>Manuais</p>
            <span class="badge badge-secondary">1</span>
          </a>
        </li>

        <!-- Atalhos -->
        <li class="nav-section mt-3">
          <span class="sidebar-mini-icon"><i class="fa fa-ellipsis-h"></i></span>
          <h4 class="text-section text-uppercase">Atalhos</h4>
        </li>

        <li class="nav-item"><a href="widgets.html"><i class="fas fa-user-edit"></i><p>Editar Perfil</p></a></li>
        <li class="nav-item"><a href="widgets.html"><i class="fas fa-camera"></i><p>Trocar Foto</p></a></li>
        <li class="nav-item"><a href="logout.php" id="btnLogout"><i class="fas fa-sign-out-alt"></i><p>Sair</p></a></li>

      </ul>
    </div>
  </div>
</div>
<!-- End Sidebar -->
