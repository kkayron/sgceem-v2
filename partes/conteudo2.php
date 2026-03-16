<?php
session_start();

// ID da página correspondente no banco
$pagina_id = intval(1); // <-- altere para o ID correto desta página

// Verifica se usuário está logado
if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit;
}

// Verifica se o usuário tem permissão de acessar a página
if (empty($_SESSION['permissoes'][$pagina_id]['pode_acessar'])) {
    ?>
    <!DOCTYPE html>
    <html lang="pt-BR">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Acesso Negado</title>
        <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    </head>
    <body>
        <script>
            Swal.fire({
                icon: 'error',
                title: 'Acesso Negado',
                text: 'Você não possui permissão para acessar esta página.',
                showCancelButton: true,
                confirmButtonText: 'Voltar ao Painel',
                cancelButtonText: 'Ir para Login',
                allowOutsideClick: false,
                allowEscapeKey: false
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = 'index.php';
                } else if (result.isDismissed) {
                    window.location.href = 'login.php';
                }
            });
        </script>
    </body>
    </html>
    <?php
    exit;
}

// Variáveis para as outras permissões
$pode_editar    = $_SESSION['permissoes'][$pagina_id]['pode_editar'] ?? false;
$pode_deletar   = $_SESSION['permissoes'][$pagina_id]['pode_deletar'] ?? false;
$pode_cadastrar = $_SESSION['permissoes'][$pagina_id]['pode_cadastrar'] ?? false;
?>



<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
<div class="container">
          <div class="page-inner">
            <div
              class="d-flex align-items-left align-items-md-center flex-column flex-md-row pt-2 pb-4"
            >
              <div>
                <h3 class="fw-bold mb-3">Página Inicial</h3>
                <h6 class="op-7 mb-2">Sistema de Gerenciamento da Cia E Eqp Mnt</h6>
              </div>
            </div>
              
              <div class="row">
              
         
              <!-- Card: CONTROLE ORDENS DE SERVIÇO -->
<div class="col-md-6 mb-4">
  <div class="card card-stats card-round overflow-hidden shadow-sm">
    <div class="d-flex" style="min-height: 150px;">
      <!-- Lateral azul com ícone -->
      <div class="d-flex justify-content-center align-items-center bg-primary" style="width: 90px;">
        <i class="fas fa-tools text-white" style="font-size: 2.5rem;"></i>
      </div>

      <!-- Conteúdo do card -->
      <div class="flex-grow-1 p-3">
        <p class="card-category mb-1 text-muted">Controle de Ordens de Serviço</p>
        <div class="d-flex justify-content-between">
          <span class="fw-bold">Total de Ordens:</span> <span>120</span>
        </div>
        <div class="d-flex justify-content-between">
          <span class="fw-bold text-warning">Em Aberto:</span> <span>28 (23,33%)</span>
        </div>
        <div class="d-flex justify-content-between">
          <span class="fw-bold text-info">Em Execução:</span> <span>40 (33,33%)</span>
        </div>
        <div class="d-flex justify-content-between">
          <span class="fw-bold text-success">Finalizadas:</span> <span>52 (43,34%)</span>
        </div>
      </div>
    </div>
  </div>
</div>
<!-- Card: FICHAS DE VIATURA -->
<div class="col-md-6 mb-4">
  <div class="card card-stats card-round overflow-hidden shadow-sm">
    <div class="d-flex" style="min-height: 150px;">
      <!-- Lateral azul com ícone -->
      <div class="d-flex justify-content-center align-items-center bg-primary" style="width: 90px;">
        <i class="fas fa-route text-white" style="font-size: 2.5rem;"></i>
      </div>

      <!-- Conteúdo do card -->
      <div class="flex-grow-1 p-3">
        <p class="card-category mb-1 text-muted">Controle de Fichas de Viatura</p>
        <div class="d-flex justify-content-between">
          <span class="fw-bold">Total de Fichas:</span> <span>89</span>
        </div>
        <div class="d-flex justify-content-between">
          <span class="fw-bold text-warning">Em Deslocamento:</span> <span>15 (16,85%)</span>
        </div>
        <div class="d-flex justify-content-between">
          <span class="fw-bold text-success">Finalizadas:</span> <span>74 (83,15%)</span>
        </div>
      </div>
    </div>
  </div>
</div>


              
              
              </div> 
              
              
              <!-- Bootstrap e ícones do Bootstrap Icons devem estar incluídos no seu projeto -->
<!-- Exemplo: <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet"> -->
              
              

<div class="card shadow-sm border-0 rounded-3 mb-4">
  <div class="card-header bg-warning text-white rounded-top-3 d-flex align-items-center">
    <i class="bi bi-truck-front-fill fs-4 me-2"></i>
    <h5 class="mb-0 fw-semibold">Controle da Frota</h5>
  </div>
  <div class="card-body bg-light">

    <!-- Ativos Cadastrados -->
    <div class="mb-4">
      <h6 class="text-secondary fw-bold mb-3"><i class="bi bi-clipboard-data me-2 text-dark"></i>Ativos Cadastrados no Sistema</h6>
      
      <div class="row g-3 text-center">
        <div class="col-6 col-md-3">
          <div class="bg-white rounded shadow-sm p-3 h-100">
            <i class="bi bi-truck fs-3 text-warning"></i>
            <div class="fw-bold mt-2">Viaturas</div>
            <div class="fs-5">80</div>
          </div>
        </div>
        <div class="col-6 col-md-3">
          <div class="bg-white rounded shadow-sm p-3 h-100">
            <i class="bi bi-cpu-fill fs-3 text-warning"></i>
            <div class="fw-bold mt-2">Equipamentos</div>
            <div class="fs-5">92</div>
          </div>
        </div>
        <div class="col-6 col-md-3">
          <div class="bg-white rounded shadow-sm p-3 h-100">
            <i class="bi bi-stack fs-3 text-warning"></i>
            <div class="fw-bold mt-2">Total</div>
            <div class="fs-5">172</div>
          </div>
        </div>
      </div>

      <div class="row g-3 text-center mt-3">
        <div class="col-6 col-md-3">
          <div class="bg-white rounded shadow-sm p-3 h-100">
            <i class="bi bi-x-octagon-fill fs-3 text-danger"></i>
            <div class="fw-bold mt-2">Desfazimentos</div>
            <div class="fs-5">0</div>
          </div>
        </div>
        <div class="col-6 col-md-3">
          <div class="bg-white rounded shadow-sm p-3 h-100">
            <i class="bi bi-box-arrow-down fs-3 text-secondary"></i>
            <div class="fw-bold mt-2">Descarregados</div>
            <div class="fs-5">12</div>
          </div>
        </div>
        <div class="col-6 col-md-3">
          <div class="bg-white rounded shadow-sm p-3 h-100">
            <i class="bi bi-hourglass-split fs-3 text-primary"></i>
            <div class="fw-bold mt-2">Em Descarga</div>
            <div class="fs-5">10</div>
          </div>
        </div>
        <div class="col-6 col-md-3">
          <div class="bg-white rounded shadow-sm p-3 h-100">
            <i class="bi bi-arrow-left-right fs-3 text-success"></i>
            <div class="fw-bold mt-2">Emprestados</div>
            <div class="fs-5">33</div>
          </div>
        </div>
      </div>
    </div>

    <!-- Frota Atual -->
    <div>
      <h6 class="text-secondary fw-bold mb-3"><i class="bi bi-speedometer2 me-2 text-dark"></i>Frota Atual</h6>
      
      <div class="row g-3 text-center">
        <div class="col-6 col-md-4">
          <div class="bg-white rounded shadow-sm p-3 h-100">
            <i class="bi bi-check-circle-fill fs-3 text-success"></i>
            <div class="fw-bold mt-2">Confiáveis</div>
            <div class="fs-5">Viaturas: 53</div>
            <div class="fs-6">Equipamentos: 63</div>
            <div class="fw-bold">Total: 116</div>
          </div>
        </div>
        <div class="col-6 col-md-4">
          <div class="bg-white rounded shadow-sm p-3 h-100">
            <i class="bi bi-exclamation-triangle-fill fs-3 text-danger"></i>
            <div class="fw-bold mt-2">Não Confiáveis</div>
            <div class="fs-5">Viaturas: 1</div>
            <div class="fs-6">Equipamentos: 0</div>
            <div class="fw-bold">Total: 1</div>
          </div>
        </div>
        <div class="col-12 col-md-4">
          <div class="bg-white rounded shadow-sm p-3 h-100">
            <i class="bi bi-graph-up-arrow fs-3 text-primary"></i>
            <div class="fw-bold mt-2">Total da Frota Atual</div>
            <div class="fs-5">Viaturas: 54</div>
            <div class="fs-6">Equipamentos: 63</div>
            <div class="fw-bold">Total: 117</div>
          </div>
        </div>
      </div>
    </div>

  </div>
</div>

       
            <!-- Card maior: Disponibilidades -->
<div class="card shadow-sm mb-4" style="background-color: rgba(240, 240, 240, 0.8); border: none;">
  <div class="card-body">

    <!-- Título do agrupador -->
    <h5 class="card-title mb-4 fw-bold text-dark">
      <i class="fas fa-chart-pie me-2 text-primary"></i>Disponibilidade da Frota atual
    </h5>

    <div class="row">

      <!-- Card: DISPONIBILIDADE VIATURAS / FROTA -->
      <div class="col-md-6 mb-4">
        <div class="card card-stats card-round overflow-hidden shadow-sm">
          <div class="d-flex" style="min-height: 150px;">
            <!-- Lateral azul com ícone -->
            <div class="d-flex justify-content-center align-items-center" style="background-color: #007bff; width: 90px;">
              <i class="fas fa-car-side text-white" style="font-size: 2.5rem;"></i>
            </div>

            <!-- Conteúdo do card -->
            <div class="flex-grow-1 p-3">
              <p class="card-category mb-1 text-muted">Disponibilidade Viaturas / Frota</p>
              <div class="d-flex justify-content-between">
                <span class="fw-bold">Total Vtr:</span> <span>54</span>
              </div>
              <div class="d-flex justify-content-between">
                <span class="fw-bold text-success">Disponíveis:</span> <span>49 (90,74%)</span>
              </div>
              <div class="d-flex justify-content-between">
                <span class="fw-bold text-danger">Indisponíveis:</span> <span>5 (9,26%)</span>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Card: DISPONIBILIDADE EQUIPAMENTOS -->
      <div class="col-md-6 mb-4">
        <div class="card card-stats card-round overflow-hidden shadow-sm">
          <div class="d-flex" style="min-height: 150px;">
            <!-- Lateral azul com ícone -->
            <div class="d-flex justify-content-center align-items-center" style="background-color: #007bff; width: 90px;">
              <i class="fas fa-tractor text-white" style="font-size: 2.5rem;"></i>
            </div>

            <!-- Conteúdo do card -->
            <div class="flex-grow-1 p-3">
              <p class="card-category mb-1 text-muted">Disponibilidade Equipamentos</p>
              <div class="d-flex justify-content-between">
                <span class="fw-bold">Total:</span> <span>82</span>
              </div>
              <div class="d-flex justify-content-between">
                <span class="fw-bold text-success">Disponíveis:</span> <span>76 (92,68%)</span>
              </div>
              <div class="d-flex justify-content-between">
                <span class="fw-bold text-danger">Indisponíveis:</span> <span>6 (7,32%)</span>
              </div>
            </div>
          </div>
        </div>
      </div>

    </div> <!-- /row -->
  </div> <!-- /card-body -->
</div> <!-- /card maior -->

              
              <!-- Card maior: Disponibilidades -->
<div class="card shadow-sm mb-4" style="background-color: rgba(240, 240, 240, 0.8); border: none;">
  <div class="card-body">

    <!-- Título do agrupador -->
    <h5 class="card-title mb-4 fw-bold text-dark">
      <i class="fas fa-chart-pie me-2 text-primary"></i>Vtr/Eqp por destino
    </h5>
              
              <div class="row">

  <!-- Card: Quant Viaturas por Destino -->
  <div class="col-md-6 mb-4">
    <div class="card shadow-sm overflow-hidden">
      <div class="d-flex">
        <!-- Lateral azul com ícone -->
        <div class="d-flex justify-content-center align-items-center" style="background-color: #007bff; width: 90px;">
          <i class="fas fa-car-side text-white" style="font-size: 2rem;"></i>
        </div>

        <!-- Conteúdo -->
        <div class="flex-grow-1 p-3">
          <h6 class="fw-bold text-center border-bottom pb-2 mb-3">QUANT VIATURAS POR DESTINO</h6>
          <!-- Cabeçalho -->
          <div class="d-flex fw-bold bg-secondary text-white px-3 py-2">
            <div class="w-50">LOCAL</div>
            <div class="w-50 text-end">TOTAL</div>
          </div>
          <!-- Linhas -->
          <div class="d-flex px-3 py-2 border-bottom">
            <div class="w-50 fw-semibold">Sede</div>
            <div class="w-50 text-end">45</div>
          </div>
          <div class="d-flex px-3 py-2 border-bottom bg-light">
            <div class="w-50">Manaus</div>
            <div class="w-50 text-end">1</div>
          </div>
          <div class="d-flex px-3 py-2 border-bottom">
            <div class="w-50">Jundiaí</div>
            <div class="w-50 text-end">2</div>
          </div>
          <div class="d-flex px-3 py-2 bg-light">
            <div class="w-50">Acolhida</div>
            <div class="w-50 text-end">6</div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Card: Quant Equipamentos por Destino -->
  <div class="col-md-6 mb-4">
    <div class="card shadow-sm overflow-hidden">
      <div class="d-flex">
        <!-- Lateral preta com ícone -->
        <div class="d-flex justify-content-center align-items-center" style="background-color: #000; width: 90px;">
          <i class="fas fa-tractor text-white" style="font-size: 2rem;"></i>
        </div>

        <!-- Conteúdo -->
        <div class="flex-grow-1 p-3">
          <h6 class="fw-bold text-center border-bottom pb-2 mb-3">QUANT EQUIPAMENTOS POR DESTINO</h6>
          <!-- Cabeçalho -->
          <div class="d-flex fw-bold bg-secondary text-white px-3 py-2">
            <div class="w-50">LOCAL</div>
            <div class="w-50 text-end">TOTAL</div>
          </div>
          <!-- Linhas -->
          <div class="d-flex px-3 py-2 border-bottom">
            <div class="w-50 fw-semibold">Surucucu</div>
            <div class="w-50 text-end">5</div>
          </div>
          <div class="d-flex px-3 py-2 border-bottom bg-light">
            <div class="w-50">Sede</div>
            <div class="w-50 text-end">54</div>
          </div>
          <div class="d-flex px-3 py-2 border-bottom">
            <div class="w-50">Manaus</div>
            <div class="w-50 text-end">4</div>
          </div>
          <div class="d-flex px-3 py-2 bg-light">
            <div class="w-50">Acolhida</div>
            <div class="w-50 text-end">1</div>
          </div>
        </div>
      </div>
    </div>
  </div>

</div>
</div>
</div>

            <!-- Card maior: Manutenção Preventiva -->
<div class="card shadow-sm mb-4" style="background-color: rgba(240, 240, 240, 0.8); border: none;">
  <div class="card-body">

    <!-- Título do agrupador -->
    <h5 class="card-title mb-4 fw-bold text-dark">
      <i class="fas fa-tools me-2 text-danger"></i>Manutenção Preventiva
    </h5>

    <div class="row">
      <!-- Card: Viaturas -->
      <div class="col-md-6 mb-4">
        <div class="card shadow-sm overflow-hidden">
          <div class="d-flex">
            <!-- Lateral azul com ícone -->
            <div class="d-flex justify-content-center align-items-center" style="background-color: #007bff; width: 90px;">
              <i class="fas fa-car-side text-white" style="font-size: 2rem;"></i>
            </div>

            <!-- Conteúdo -->
            <div class="flex-grow-1 p-3">
              <h6 class="fw-bold text-center border-bottom pb-2 mb-3">MANUTENÇÃO VIATURAS</h6>
              <!-- Cabeçalho -->
              <div class="d-flex fw-bold bg-secondary text-white px-3 py-2">
                <div class="w-75">Situação</div>
                <div class="w-25 text-end">Qtd</div>
              </div>

              <!-- Linhas -->
              <div class="d-flex px-3 py-2 border-bottom">
                <div class="w-75 fw-semibold text-success">Manutenção em Dia</div>
                <div class="w-25 text-end">24</div>
              </div>
              <div class="d-flex px-3 py-2 border-bottom bg-light">
                <div class="w-75 text-warning">Manutenção Muito Próxima</div>
                <div class="w-25 text-end">6</div>
              </div>
              <div class="d-flex px-3 py-2 border-bottom">
                <div class="w-75 text-dark">Agendada</div>
                <div class="w-25 text-end">1</div>
              </div>
              <div class="d-flex px-3 py-2 bg-light">
                <div class="w-75 text-danger">Manutenção Vencida</div>
                <div class="w-25 text-end">26</div>
              </div>
              <div class="d-flex fw-bold px-3 py-2 border-top mt-2">
                <div class="w-75">Total</div>
                <div class="w-25 text-end">69</div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Card: Equipamentos -->
      <div class="col-md-6 mb-4">
        <div class="card shadow-sm overflow-hidden">
          <div class="d-flex">
            <!-- Lateral preta com ícone -->
            <div class="d-flex justify-content-center align-items-center" style="background-color: #000; width: 90px;">
              <i class="fas fa-tractor text-white" style="font-size: 2rem;"></i>
            </div>

            <!-- Conteúdo -->
            <div class="flex-grow-1 p-3">
              <h6 class="fw-bold text-center border-bottom pb-2 mb-3">MANUTENÇÃO EQUIPAMENTOS</h6>
              <!-- Cabeçalho -->
              <div class="d-flex fw-bold bg-secondary text-white px-3 py-2">
                <div class="w-75">Situação</div>
                <div class="w-25 text-end">Qtd</div>
              </div>

              <!-- Linhas -->
              <div class="d-flex px-3 py-2 border-bottom">
                <div class="w-75 fw-semibold text-success">Manutenção em Dia</div>
                <div class="w-25 text-end">19</div>
              </div>
              <div class="d-flex px-3 py-2 border-bottom bg-light">
                <div class="w-75 text-warning">Manutenção Muito Próxima</div>
                <div class="w-25 text-end">0</div>
              </div>
              <div class="d-flex px-3 py-2 border-bottom">
                <div class="w-75 text-dark">Agendada</div>
                <div class="w-25 text-end">6</div>
              </div>
              <div class="d-flex px-3 py-2 bg-light">
                <div class="w-75 text-danger">Manutenção Vencida</div>
                <div class="w-25 text-end">18</div>
              </div>
              <div class="d-flex fw-bold px-3 py-2 border-top mt-2">
                <div class="w-75">Total</div>
                <div class="w-25 text-end">82</div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Botão de ação -->
    <div class="text-end">
      <a href="#" class="btn btn-info fw-bold">
        <i class="fas fa-list me-1"></i> Listar Controle da Manut Preventiva
      </a>
    </div>

  </div>
</div>

              
         <!-- Card maior: Controle de Empenhos -->
<div class="card shadow-sm mb-4" style="background-color: rgba(240, 240, 240, 0.8); border: none;">
  <div class="card-body">

    <!-- Título do agrupador -->
    <h5 class="card-title mb-4 fw-bold text-dark">
      <i class="fas fa-file-invoice-dollar me-2 text-primary"></i>Controle de Empenhos / Liquidação
    </h5>

    <!-- Filtros -->
    <form class="row g-3 mb-4">
      <div class="col-md-4">
        <label for="filtroAno" class="form-label fw-semibold">Ano</label>
        <select class="form-select" id="filtroAno" name="ano">
          <option value="">Todos</option>
          <option value="2025">2025</option>
          <option value="2024">2024</option>
        </select>
      </div>
      <div class="col-md-4">
        <label for="filtroUnidade" class="form-label fw-semibold">Unidade</label>
        <select class="form-select" id="filtroUnidade" name="unidade">
          <option value="">Todas</option>
          <option value="Unidade A">Unidade A</option>
          <option value="Unidade B">Unidade B</option>
        </select>
      </div>
      <div class="col-md-4">
        <label for="filtroTipo" class="form-label fw-semibold">Tipo</label>
        <select class="form-select" id="filtroTipo" name="tipo">
          <option value="">Todos</option>
          <option value="Empenho">Empenho</option>
          <option value="Liquidação">Liquidação</option>
        </select>
      </div>
    </form>

    <div class="row">
      <!-- Card: Empenhos -->
      <div class="col-md-6 mb-4">
        <div class="card shadow-sm overflow-hidden">
          <div class="d-flex">
            <!-- Lateral azul com ícone -->
            <div class="d-flex justify-content-center align-items-center bg-primary" style="width: 90px;">
              <i class="fas fa-coins text-white" style="font-size: 2rem;"></i>
            </div>

            <!-- Conteúdo -->
            <div class="flex-grow-1 p-3">
              <h6 class="fw-bold text-center border-bottom pb-2 mb-3">EMPENHOS</h6>

              <!-- Cabeçalho -->
              <div class="d-flex fw-bold bg-secondary text-white px-3 py-2">
                <div class="w-75">Indicador</div>
                <div class="w-25 text-end">Valor</div>
              </div>

              <!-- Linhas -->
              <div class="d-flex px-3 py-2 border-bottom">
                <div class="w-75 text-dark fw-semibold">Total Empenhado</div>
                <div class="w-25 text-end">R$ 15.000,00</div>
              </div>
              <div class="d-flex px-3 py-2 border-bottom bg-light">
                <div class="w-75 text-info">Total Liquidado</div>
                <div class="w-25 text-end">R$ 0,00</div>
              </div>
              <div class="d-flex px-3 py-2 border-bottom">
                <div class="w-75 text-primary">Total Req/Cap/Ent</div>
                <div class="w-25 text-end">R$ 0,00</div>
              </div>
              <div class="d-flex px-3 py-2 bg-light">
                <div class="w-75 text-success">Saldo Real</div>
                <div class="w-25 text-end">R$ 15.000,00</div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Card: Saldos -->
      <div class="col-md-6 mb-4">
        <div class="card shadow-sm overflow-hidden">
          <div class="d-flex">
            <!-- Lateral cinza com ícone -->
            <div class="d-flex justify-content-center align-items-center bg-dark" style="width: 90px;">
              <i class="fas fa-balance-scale-left text-white" style="font-size: 2rem;"></i>
            </div>

            <!-- Conteúdo -->
            <div class="flex-grow-1 p-3">
              <h6 class="fw-bold text-center border-bottom pb-2 mb-3">SALDOS</h6>

              <!-- Cabeçalho -->
              <div class="d-flex fw-bold bg-secondary text-white px-3 py-2">
                <div class="w-75">Indicador</div>
                <div class="w-25 text-end">Valor</div>
              </div>

              <!-- Linhas -->
              <div class="d-flex px-3 py-2 border-bottom">
                <div class="w-75 text-dark fw-semibold">Saldo SIAFI</div>
                <div class="w-25 text-end">R$ 0,00</div>
              </div>
              <div class="d-flex px-3 py-2 border-bottom bg-light">
                <div class="w-75 text-danger">Valor em Aberto</div>
                <div class="w-25 text-end">R$ -15.000,00</div>
              </div>
              <div class="d-flex fw-bold px-3 py-2 border-top mt-2">
                <div class="w-75">Total Registros</div>
                <div class="w-25 text-end">1</div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Botão de ação -->
    <div class="text-end">
      <a href="#" class="btn btn-info fw-bold">
        <i class="fas fa-list me-1"></i> Listar Empenhos
      </a>
    </div>

  </div>
</div>

              
              
              
              
              
              
              
              
          </div>
        </div>