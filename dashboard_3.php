
<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_start();

$pagina_id = 56;

if (!isset($_SESSION['usuario_id'])) {
  header("Location: login.php");
  exit;
}

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
  if (result.isConfirmed) window.location.href = 'index.php';
  else window.location.href = 'login.php';
});
</script>
</body>
</html>
<?php
  exit;
}

include_once('conexao/config.php');

// sessão (pode manter, mesmo sem usar)
$id_om_usuario = (int)($_SESSION['usuario']['batalhao'] ?? 0);
$nivel_usuario = (int)($_SESSION['usuario']['nivel'] ?? 3);

// OMs visíveis (pode manter, mesmo sem usar aqui)
$oms_visiveis = [];
if ($nivel_usuario === 1) {
  $sql_oms = "SELECT id, nome FROM organizacoes_militares ORDER BY nome";
} elseif ($nivel_usuario === 2) {
  $sql_oms = "
    SELECT om.id, om.nome
    FROM organizacoes_militares om
    JOIN organizacoes_militares_sub sub ON om.id = sub.id_om_menor
    WHERE sub.id_om_maior = $id_om_usuario
    UNION
    SELECT id, nome FROM organizacoes_militares WHERE id = $id_om_usuario
    ORDER BY nome
  ";
} else {
  $sql_oms = "SELECT id, nome FROM organizacoes_militares WHERE id = $id_om_usuario";
}
$res = $conexao->query($sql_oms);
while ($r = $res->fetch_assoc()) $oms_visiveis[(int)$r['id']] = $r['nome'];
?>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">

<div class="container">
  <div class="page-inner">
    <div class="d-flex align-items-left align-items-md-center flex-column flex-md-row pt-2 pb-4">
      <div>
        <h3 class="fw-bold mb-3">Relatório Financeiro</h3>
        <h6 class="op-7 mb-2">Sistema de Gerenciamento da Cia E Eqp Mnt</h6>
      </div>
    </div>

    <!-- PLACEHOLDERS -->
  <div id="dashFinanceiro" class="mb-4">
      <div class="card shadow-sm">
        <div class="card-body text-muted">
          <i class="fas fa-spinner fa-spin me-2"></i>Carregando resumo financeiro...
        </div>
      </div>
    </div>

  </div>
</div>

<script>
(function(){
  const map = {
     financeiro: { container: 'dashFinanceiro', url: 'includes/dashboard/bloco_financeiro.php' },
  };

  async function carregarBloco(chave, queryString){
    const info = map[chave];
    if(!info) return;

    const el = document.getElementById(info.container);
    if(!el) return;

    const url = queryString ? `${info.url}?${queryString}` : info.url;

    try{
      const r = await fetch(url, { credentials: 'same-origin' });
      if(!r.ok) throw new Error('HTTP '+r.status);
      el.innerHTML = await r.text();
    }catch(e){
      el.innerHTML = `
        <div class="card shadow-sm">
          <div class="card-body text-danger">
            <i class="fas fa-exclamation-triangle me-2"></i>
            Falha ao carregar (${info.container}): ${e.message}
          </div>
        </div>`;
    }
  }

  function carregarTudo(){
    carregarBloco('financeiro');
  }

  // expõe para os blocos chamarem
  window.dashboardReload = function(chave, params){
    let qs = '';
    if (params instanceof URLSearchParams) qs = params.toString();
    else if (typeof params === 'string') qs = params.replace(/^\?/, '');
    carregarBloco(chave, qs);
  };

  // ✅ DELEGAÇÃO: intercepta submits mesmo após innerHTML (sem recarregar página)
  document.addEventListener('submit', function(e){
    const form = e.target;
    if (!form || form.tagName !== 'FORM') return;

    const id = form.id;
    let bloco = null;
    let containerId = null;

    if (id === 'filtroResumoForm') {
      bloco = 'resumo';
      containerId = 'dashResumo';
    } else if (id === 'filtroPreventivaForm') {
      bloco = 'preventiva';
      containerId = 'dashPreventiva';
    } else if (id === 'filtroFinanceiroForm') {
      bloco = 'financeiro';
      containerId = 'dashFinanceiro';
    } else {
      return; // não é um filtro do dashboard
    }

    e.preventDefault();

    const params = new URLSearchParams(new FormData(form));

    const el = document.getElementById(containerId);
    if (el) {
      el.innerHTML = `
        <div class="card shadow-sm">
          <div class="card-body text-muted">
            <i class="fas fa-spinner fa-spin me-2"></i>Atualizando...
          </div>
        </div>`;
    }

    window.dashboardReload(bloco, params);
  });

  // primeira carga
  carregarTudo();

  // compat SPA
  window.funcaoInicializacao = 'inicializarPaginaPrincipal';
  window.inicializarPaginaPrincipal = carregarTudo;
})();
</script>