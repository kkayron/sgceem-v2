// JavaScript Document

window.inicializarControleMnt = function () {
	window.toggleFiltrosControleMnt = function() {
  const container = document.getElementById('filtros-container-controle-mnt');
  if (container) {
    container.style.display = container.style.display === 'none' ? 'block' : 'none';
  }
};

// FILTROS DA PÁGINA DE CONTROLE DE MANUTENÇÃO
const formControleMnt = document.getElementById('filtroControleMntForm');

function atualizarListaControleMnt(extraParams = {}) {
  if (!formControleMnt) return;

  const formData = new FormData(formControleMnt);
  const params = new URLSearchParams(formData);

  for (const key in extraParams) {
    params.set(key, extraParams[key]);
  }

  const url = `includes/plano_mnt/controle.php?${params.toString()}`;
  carregarPagina(url);
}

// Submissão do formulário
if (formControleMnt) {
  formControleMnt.addEventListener('submit', function(e) {
    e.preventDefault();
    atualizarListaControleMnt({ pagina: 1 });
  });
}

// Seleção de limite
const limiteSelectControleMnt = document.getElementById('limiteControleMnt');

if (limiteSelectControleMnt) {
  limiteSelectControleMnt.addEventListener('change', function() {
    atualizarListaControleMnt({
      pagina: 1,
      limite: this.value
    });
  });
}

// Botão limpar filtros
const btnLimparFiltrosControleMnt = document.getElementById('btnLimparFiltrosControleMnt');

if (btnLimparFiltrosControleMnt && formControleMnt) {
  btnLimparFiltrosControleMnt.addEventListener('click', function(e) {
    e.preventDefault();

    formControleMnt.reset();

    const limiteAtual = document.getElementById('limiteControleMnt')?.value || 10;
    const url = `includes/plano_mnt/controle.php?pagina=1&limite=${limiteAtual}`;

    carregarPagina(url);
  });
}

// Paginação
document.addEventListener('click', function(e) {
  const link = e.target.closest('.paginacao-controle-mnt');

  if (!link) return;

  e.preventDefault();

  const url = link.getAttribute('data-page');

  if (url) {
    carregarPagina(url);
  }
});

window.verPlanoMntControle = function(idPlano, idFrota = '') {
  if (!idPlano) {
    Swal.fire("Erro!", "ID do plano não informado.", "error");
    return;
  }

  const container = document.getElementById('conteudo-ver-plano-mnt-controle');

  if (container) {
    container.innerHTML = `
      <div class="text-center py-5">
        <div class="spinner-border text-primary" role="status"></div>
        <p class="mt-3 text-muted">Carregando dados do plano...</p>
      </div>
    `;
  }

  let url = `includes/plano_mnt/ver_plano_controle.php?id=${idPlano}`;

  if (idFrota) {
    url += `&id_frota=${idFrota}`;
  }

  fetch(url)
    .then(res => res.text())
    .then(html => {
      if (container) {
        container.innerHTML = html;
      }
    })
    .catch(error => {
      if (container) {
        container.innerHTML = `
          <div class="alert alert-danger">
            Erro ao carregar os dados do plano: ${error.message}
          </div>
        `;
      }
    });
};
	
};