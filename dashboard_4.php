<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_start();

$pagina_id = 57; // ajuste se precisar

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

// sessão (usado no JS para exibir botão/ações já vem do bloco)
$id_om_usuario = (int)($_SESSION['usuario']['batalhao'] ?? 0);
$nivel_usuario = (int)($_SESSION['usuario']['nivel'] ?? 3);
?>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">

<div class="container">
  <div class="page-inner">
    <div class="d-flex align-items-left align-items-md-center flex-column flex-md-row pt-2 pb-4">
      <div>
        <h3 class="fw-bold mb-3">Histórico de Disponibilidade</h3>
        <h6 class="op-7 mb-2">Sistema de Gerenciamento da Cia E Eqp Mnt</h6>
      </div>
    </div>

    <!-- PLACEHOLDER -->
    <div id="dashHistoricoDisp" class="mb-4">
      <div class="card shadow-sm">
        <div class="card-body text-muted">
          <i class="fas fa-spinner fa-spin me-2"></i>Carregando histórico de disponibilidade...
        </div>
      </div>
    </div>

  </div>
</div>

<script>
(function(){
  // =========================
  // MAPA DE BLOCOS (padrão dashboard_3)
  // =========================
  const map = {
    historico: { container: 'dashHistoricoDisp', url: 'includes/dashboard/historico_disponibilidade.php' },
  };

  // =========================
  // Chart.js (carrega 1x) + instância global
  // =========================
  window.__dispChartInstance = window.__dispChartInstance || null;

  function loadChartJsOnce(){
    if (window.Chart && window.Chart.version) return Promise.resolve();
    if (window.__loadingChartJs) return window.__loadingChartJs;

    window.__loadingChartJs = new Promise((resolve, reject) => {
      const s = document.createElement('script');
      s.src = 'https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js';
      s.onload = () => resolve();
      s.onerror = () => reject(new Error('Falha ao carregar Chart.js'));
      document.head.appendChild(s);
    });

    return window.__loadingChartJs;
  }

  async function initDispChart(containerEl){
    if (!containerEl) return;

    const canvas = containerEl.querySelector('#dispLineChart');
    const dataEl = containerEl.querySelector('#dispLineChartData');
    if (!canvas || !dataEl) return;

    let payload;
    try {
      payload = JSON.parse((dataEl.textContent || '').trim() || '{}');
    } catch (e) {
      console.warn('JSON do gráfico inválido', e);
      return;
    }

    const labels = Array.isArray(payload.labels) ? payload.labels : [];
    const dss    = Array.isArray(payload.datasets) ? payload.datasets : [];
    if (!labels.length || !dss.length) return;

    await loadChartJsOnce();

    // destrói instância anterior
    if (window.__dispChartInstance) {
      window.__dispChartInstance.destroy();
      window.__dispChartInstance = null;
    }

    const datasets = dss.map(ds => ({
      label: ds.label || 'Série',
      data: Array.isArray(ds.values) ? ds.values : [],
      tension: 0.25,
      pointRadius: 2
    }));

    window.__dispChartInstance = new window.Chart(canvas, {
      type: 'line',
      data: { labels, datasets },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        interaction: { mode: 'index', intersect: false },
        scales: {
          y: {
            min: 0,
            max: 100,
            ticks: { callback: (v) => v + '%' },
            title: { display: true, text: '% de disponibilidade' }
          },
          x: { title: { display: true, text: 'Data' } }
        },
        plugins: {
          legend: { display: true },
          tooltip: { callbacks: { label: (ctx) => ` ${ctx.dataset.label}: ${ctx.parsed.y}%` } }
        }
      }
    });
  }

  // =========================
  // Carregamento de bloco (padrão dashboard_3)
  // =========================
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

      // ✅ após inserir HTML, inicializa o gráfico do bloco
      initDispChart(el);

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
    carregarBloco('historico');
  }

  // expõe para blocos chamarem (se necessário)
  window.dashboardReload = function(chave, params){
    let qs = '';
    if (params instanceof URLSearchParams) qs = params.toString();
    else if (typeof params === 'string') qs = params.replace(/^\?/, '');
    carregarBloco(chave, qs);
  };

  // =========================
  // ✅ SUBMIT delegação (igual dashboard_3)
  // =========================
  document.addEventListener('submit', function(e){
    const form = e.target;
    if (!form || form.tagName !== 'FORM') return;

    if (form.id !== 'filtroHistoricoDispForm') return;

    e.preventDefault();

    const params = new URLSearchParams(new FormData(form));
    const el = document.getElementById('dashHistoricoDisp');

    if (el) {
      el.innerHTML = `
        <div class="card shadow-sm">
          <div class="card-body text-muted">
            <i class="fas fa-spinner fa-spin me-2"></i>Atualizando...
          </div>
        </div>`;
    }

    window.dashboardReload('historico', params);
  });

  // =========================
  // ✅ CLICK delegação (delete + snapshot)
  // =========================
  document.addEventListener('click', async function(e){

    // 1) Deletar snapshot (botão dentro do bloco)
    const btnDel = e.target.closest('.btnDelSnapshot');
    if (btnDel) {
      const dataRef = btnDel.dataset.date;   // YYYY-MM-DD
      const omId    = btnDel.dataset.om;     // '' ou número

      const msg = omId
        ? `Deseja deletar o histórico do dia ${dataRef} (somente OM ${omId})?`
        : `Deseja deletar o histórico do dia ${dataRef} (todas as OMs)?`;

      if(!confirm(msg)) return;

      btnDel.disabled = true;

      try{
        const fd = new FormData();
        fd.append('data_ref', dataRef);
        if (omId) fd.append('om_id', omId);

        const r = await fetch('cron/deletar_snapshot_diario.php', {
          method: 'POST',
          body: fd,
          credentials: 'same-origin'
        });

        const txt = await r.text();
        if(!r.ok) throw new Error(txt);

        alert('Deletado com sucesso!');

        // recarrega o bloco com os filtros atuais (se existirem)
        const f = document.getElementById('filtroHistoricoDispForm');
        const params = f ? new URLSearchParams(new FormData(f)) : '';
        window.dashboardReload('historico', params);

      }catch(err){
        alert('Erro ao deletar: ' + err.message);
        btnDel.disabled = false;
      }
      return;
    }

    // 2) Gerar snapshot (botão dentro do bloco)
    const btnSnap = e.target.closest('#btnGerarSnapshot');
    if (btnSnap) {
      if(!confirm('Deseja registrar o snapshot da frota agora?')) return;

      btnSnap.disabled = true;
      const originalHtml = btnSnap.innerHTML;
      btnSnap.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Gerando...';

      try{
        const url = 'cron/gerar_snapshot_diario.php';

        const r = await fetch(url, {
          method: 'POST',
          credentials: 'same-origin'
        });

        const txt = await r.text();
        if(!r.ok) throw new Error(txt);

        alert('Snapshot gerado com sucesso!');

        // recarrega bloco mantendo filtros
        const f = document.getElementById('filtroHistoricoDispForm');
        const params = f ? new URLSearchParams(new FormData(f)) : '';
        window.dashboardReload('historico', params);

      }catch(err){
        alert('Erro ao gerar snapshot: ' + err.message);
      } finally {
        btnSnap.disabled = false;
        btnSnap.innerHTML = originalHtml;
      }
      return;
    }

  });

  // primeira carga
  carregarTudo();

  // compat SPA
  window.funcaoInicializacao = 'inicializarPaginaHistoricoDisponibilidade';
  window.inicializarPaginaHistoricoDisponibilidade = carregarTudo;

})();
</script>