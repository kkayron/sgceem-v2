window.inicializarRequisicao = function () {

window.toggleFiltrosRequisicao = function () {
    const container = document.getElementById('filtros-container-requisicao');
    if (container) {
      container.style.display = container.style.display === 'none' ? 'block' : 'none';
    }
  };
    
    // FILTROS DA PÁGINA DE REQUISIÇÕES
const formREQUISICAO = document.getElementById('filtroRequisicaoForm');

function atualizarListaREQUISICAO(extraParams = {}) {
  if (!formREQUISICAO) return;

  const formData = new FormData(formREQUISICAO);
  const params = new URLSearchParams(formData);

  for (const key in extraParams) {
    params.set(key, extraParams[key]);
  }

  const url = `includes/fin_requisicoes/listagem.php?${params.toString()}`;
  carregarPagina(url);
}

if (formREQUISICAO) {
  formREQUISICAO.addEventListener('submit', function (e) {
    e.preventDefault();
    atualizarListaREQUISICAO({ pagina: 1 });
  });
}

const limiteSelectREQUISICAO = document.getElementById('limiteRequisicao');
if (limiteSelectREQUISICAO) {
  limiteSelectREQUISICAO.addEventListener('change', function () {
    atualizarListaREQUISICAO({ pagina: 1, limite: this.value });
  });
}

const btnLimparFiltrosREQUISICAO = document.getElementById('btnLimparFiltrosRequisicao');
if (btnLimparFiltrosREQUISICAO && formREQUISICAO) {
  btnLimparFiltrosREQUISICAO.addEventListener('click', function (e) {
    e.preventDefault();
    formREQUISICAO.reset();

    const limiteAtual = document.getElementById('limiteRequisicao')?.value || 10;
    const url = `includes/fin_requisicoes/listagem.php?pagina=1&limite=${limiteAtual}`;
    carregarPagina(url);
  });
}    
    
  // ---------------------------------------------------------
  // CARREGAR FORNECEDORES
  // ---------------------------------------------------------
  function carregarFornecedores() {
    const selectFornecedor = document.getElementById('empenho-fornecedor');
    if (!selectFornecedor) return;

    fetch('includes/fin_fornecedores/buscar_fornecedores.php')
      .then(res => res.json())
      .then(data => {
        selectFornecedor.innerHTML = '<option value="">Selecione um fornecedor</option>';

        data.forEach(f => {
          const option = document.createElement('option');
          option.value = f.id;
          option.textContent = `${f.nome_empresa} — ${f.cnpj_empresa}`;
          selectFornecedor.appendChild(option);
        });
      })
      .catch(err => console.error("Erro ao carregar fornecedores:", err));
  }


  // ---------------------------------------------------------
  // ENVIAR FORMULÁRIO DE CADASTRO DE EMPENHO
  // ---------------------------------------------------------
  const form = document.getElementById('form-cadastrar-empenho');

  if (form) {
    form.addEventListener('submit', function (e) {
      e.preventDefault();

      const formData = new FormData(form);

      fetch('includes/fin_requisicoes/salvar_cadastrar_requisicao.php', {
        method: 'POST',
        body: formData
      })
        .then(res => res.text())
        .then(resp => {

          if (resp.trim() === 'ok') {

            swal("Sucesso!", "Empenho cadastrado com sucesso!", "success")
              .then(() => {
                carregarPagina('includes/fin_requisicoes/listagem.php');
                fecharModalAberto();
                form.reset();
              });

          } else {
            swal("Erro!", resp, "error");
          }

        })
        .catch(err => {
          swal("Erro!", "Falha na comunicação com o servidor.", "error");
          console.error(err);
        });
    });
  }


  // ---------------------------------------------------------
  // LIMPAR O FORMULÁRIO AO FECHAR O MODAL
  // ---------------------------------------------------------
  const modal = document.getElementById('modalCadastrarEmpenho');
  if (modal) {
    modal.addEventListener('hidden.bs.modal', () => {
      if (form) form.reset();
    });
  }


  // Carregar fornecedores assim que abrir o modal
  carregarFornecedores();
};
