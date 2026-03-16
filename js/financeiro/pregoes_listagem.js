window.inicializarCadastrarPregao = function () {
  console.log('Inicializador do Pregão carregado.');

  window.toggleFiltrosPregao = function() {
    const container = document.getElementById('filtros-container-pregao');
    if (container) {
      container.style.display = container.style.display === 'none' ? 'block' : 'none';
    }
  };

  // FILTROS DA PÁGINA
  const formPREGAO = document.getElementById('filtroPregaoForm');

  function atualizarListaPREGAO(extraParams = {}) {
    if (!formPREGAO) return;

    const formData = new FormData(formPREGAO);
    const params = new URLSearchParams(formData);

    for (const key in extraParams) {
      params.set(key, extraParams[key]);
    }

    const url = `includes/fin_pregoes/listagem.php?${params.toString()}`;
    carregarPagina(url);
  }

  if (formPREGAO) {
    formPREGAO.addEventListener('submit', function (e) {
      e.preventDefault();
      atualizarListaPREGAO({ pagina: 1 });
    });
  }

  const limiteSelectPREGAO = document.getElementById('limitePREGAO');
  if (limiteSelectPREGAO) {
    limiteSelectPREGAO.addEventListener('change', function () {
      atualizarListaPREGAO({ pagina: 1, limite: this.value });
    });
  }

  const btnLimparFiltrosPregao = document.getElementById('btnLimparFiltrosPregao');
  if (btnLimparFiltrosPregao && formPREGAO) {
    btnLimparFiltrosPregao.addEventListener('click', function (e) {
      e.preventDefault();
      formPREGAO.reset();

      const limiteAtual = document.getElementById('limiteOS')?.value || 10;
      const url = `includes/fin_pregoes/listagem.php?pagina=1&limite=${limiteAtual}`;
      carregarPagina(url);
    });
  }

// ===================== ADICIONAR ITEM PREGÃO =====================
let contadorItensPregao = 0;

window.adicionarItemPregao = function () {
  contadorItensPregao++;

  const container = document.getElementById('itensPregaoContainer');

  const itemDiv = document.createElement('div');
  itemDiv.classList.add('border', 'rounded', 'p-3', 'mb-2', 'bg-light');

  itemDiv.innerHTML = `
    <h6 class="fw-bold">Ordem - ${contadorItensPregao}º</h6>
    
    <div class="mb-2">
      <label class="form-label">Nº Item Pregão</label>
      <input 
        type="text" 
        class="form-control" 
        name="itens[${contadorItensPregao}][nmr_item_pregao]" 
        required
      >
    </div>
    
    <div class="mb-2">
      <label class="form-label">Fornecedor</label>
      <select class="form-select" name="itens[${contadorItensPregao}][id_fornecedor]" required>
        <option value="" selected disabled>Selecione o fornecedor</option>
        ${window.selectFornecedorTemplate}
      </select>
    </div>

    <div class="mb-2">
      <label class="form-label">Descrição do Item</label>
      <textarea class="form-control" rows="2" name="itens[${contadorItensPregao}][descricao_item]" required></textarea>
    </div>

    <div class="mb-2">
      <label class="form-label">Saldo do Item</label>
      <input type="number" step="any" class="form-control saldo-input" name="itens[${contadorItensPregao}][saldo_item]" required>
    </div>

    <div class="mb-2">
      <label class="form-label">Valor Unitário</label>
      <input type="number" step="any" class="form-control valor-unit-input" name="itens[${contadorItensPregao}][valor_unt]" required>
    </div>

    <div class="mb-2">
      <label class="form-label">Valor Total</label>
      <input type="number" step="any" class="form-control valor-total-input" name="itens[${contadorItensPregao}][valor_total]" readonly>
    </div>

    <button type="button" class="btn btn-danger btn-sm" onclick="this.parentElement.remove()">Remover item</button>
  `;

  container.appendChild(itemDiv);

  const saldoInput = itemDiv.querySelector('.saldo-input');
  const valorUnitInput = itemDiv.querySelector('.valor-unit-input');
  const valorTotalInput = itemDiv.querySelector('.valor-total-input');

  function calcularValorTotal() {
    const saldo = parseFloat(saldoInput.value) || 0;
    const valorUnit = parseFloat(valorUnitInput.value) || 0;
    valorTotalInput.value = (saldo * valorUnit).toFixed(2);
  }

  saldoInput.addEventListener('input', calcularValorTotal);
  valorUnitInput.addEventListener('input', calcularValorTotal);
};

// ===================== SUBMIT DO FORM =====================
const formCadastrarPregao = document.getElementById('form-pregao-cadastrar');

if (formCadastrarPregao) {
  formCadastrarPregao.addEventListener('submit', function (e) {
    e.preventDefault();

    const formData = new FormData(formCadastrarPregao);

    // ==========================================================
    // CAPTURAR OS ITENS DO FORM E MONTAR JSON PARA O PHP
    // ==========================================================
    const itens = [];

    document.querySelectorAll('#itensPregaoContainer > div').forEach(div => {
      itens.push({
        nmr_item_pregao: div.querySelector(`[name$="[nmr_item_pregao]"]`).value,
        id_fornecedor: div.querySelector(`[name$="[id_fornecedor]"]`).value,
        descricao_item: div.querySelector(`[name$="[descricao_item]"]`).value,
        saldo_item: div.querySelector(`[name$="[saldo_item]"]`).value,
        valor_unt: div.querySelector(`[name$="[valor_unt]"]`).value,
        valor_total: div.querySelector(`[name$="[valor_total]"]`).value
      });
    });

    // 👉 Adicionar JSON ao FormData para o backend receber
    formData.append("itens_json", JSON.stringify(itens));

    // ==========================================================
    // ENVIAR PARA O PHP
    // ==========================================================
    fetch('includes/fin_pregoes/cadastrar_pregao.php', {
      method: 'POST',
      body: formData
    })
    .then(res => res.json())
    .then(data => {
      if (data.status === 'sucesso') {
        swal("Sucesso!", data.mensagem || "Pregão cadastrado com sucesso!", "success")
          .then(() => {
            carregarPagina('includes/fin_pregoes/listagem.php');
            fecharModalAberto();
          });
      } else {
        swal("Erro!", data.mensagem || "Erro ao cadastrar pregão.", "error");
      }
    })
    .catch(err => {
      swal("Erro!", "Erro de rede: " + err.message, "error");
    });
  });
}


  const formEditarPregao = document.getElementById('form-pregao-editar');

if (formEditarPregao) {
  formEditarPregao.addEventListener('submit', function(e) {
    e.preventDefault();
    const formData = new FormData(formEditarPregao);

    fetch('includes/fin_pregoes/salvar_editar_pregao.php', {
      method: 'POST',
      body: formData
    })
    .then(res => res.text())
    .then(resp => {
      try {
        const json = JSON.parse(resp);

        if (json.status === 'parcial') {
          let mensagem = "Pregão atualizado com ressalvas:\n\n";
          mensagem += json.avisos.map(msg => `• ${msg}`).join("\n");

          swal({
            title: "Atenção!",
            text: mensagem,
            icon: "warning"
          }).then(() => {
            carregarPagina('includes/fin_pregoes/listagem.php');
            fecharModalAberto();
          });

        } else if (json.status === 'sucesso') {
          swal("Sucesso!", json.mensagem || "Pregão atualizado com sucesso!", "success")
            .then(() => {
              carregarPagina('includes/fin_pregoes/listagem.php');
              fecharModalAberto();
            });
        } else {
          swal("Erro!", json.mensagem || "Erro ao salvar.", "error");
        }

      } catch {
        // Se não for JSON, trata como erro simples
        if (resp === 'ok') {
          swal("Sucesso!", "Pregão atualizado com sucesso!", "success")
            .then(() => {
              carregarPagina('includes/fin_pregoes/listagem.php');
              fecharModalAberto();
            });
        } else {
          swal("Erro!", resp, "error");
        }
      }
    });
  });
}

  let contadorItensPregaoEditar = 0;

window.adicionarItemPregaoEditar = function (item = {}) {
  contadorItensPregaoEditar++;

  const container = document.getElementById('itensPregaoContainerEditar');
  const idItem = item.id || ''; // ← Definido antes de usar!

  const itemDiv = document.createElement('div');
  itemDiv.classList.add('border', 'rounded', 'p-3', 'mb-2', 'bg-light');

  const valorNmrItem = item.nmr_item_pregao || '';

  itemDiv.innerHTML = `
    <input type="hidden" name="itens[${contadorItensPregaoEditar}][id]" value="${idItem}">
    <h6 class="fw-bold">Ordem - ${contadorItensPregaoEditar}º</h6>

    <div class="mb-2">
      <label class="form-label">Nº Item Pregão</label>
      <input type="text" class="form-control" name="itens[${contadorItensPregaoEditar}][nmr_item_pregao]" value="${valorNmrItem}" required>
    </div>

    <div class="mb-2">
      <label class="form-label">Fornecedor</label>
      <select class="form-select fornecedor-select" name="itens[${contadorItensPregaoEditar}][id_fornecedor]" required>
        <option value="" selected disabled>Selecione o fornecedor</option>
        ${window.selectFornecedorTemplate}
      </select>
    </div>

    <div class="mb-2">
      <label class="form-label">Descrição do Item</label>
      <textarea class="form-control" rows="2" name="itens[${contadorItensPregaoEditar}][descricao_item]" required>${item.descricao_item || ''}</textarea>
    </div>

    <div class="mb-2">
      <label class="form-label">Saldo do Item</label>
      <input type="number" step="any" class="form-control saldo-input" name="itens[${contadorItensPregaoEditar}][saldo_item]" value="${item.saldo_item || ''}" required>
    </div>

    <div class="mb-2">
      <label class="form-label">Valor Unitário</label>
      <input type="number" step="any" class="form-control valor-unit-input" name="itens[${contadorItensPregaoEditar}][valor_unt]" value="${item.valor_unt || ''}" required>
    </div>

    <div class="mb-2">
      <label class="form-label">Valor Total</label>
      <input type="number" step="any" class="form-control valor-total-input" name="itens[${contadorItensPregaoEditar}][valor_total]" value="${item.valor_total || ''}" readonly>
    </div>

    <button type="button" class="btn btn-danger btn-sm" onclick="this.parentElement.remove()">Remover item</button>
  `;

  container.appendChild(itemDiv);

  const select = itemDiv.querySelector('.fornecedor-select');
  if (item.id_fornecedor) {
    select.value = item.id_fornecedor;
  }

  const saldoInput = itemDiv.querySelector('.saldo-input');
  const valorUnitInput = itemDiv.querySelector('.valor-unit-input');
  const valorTotalInput = itemDiv.querySelector('.valor-total-input');

  function calcularValorTotal() {
    const saldo = parseFloat(saldoInput.value) || 0;
    const valorUnit = parseFloat(valorUnitInput.value) || 0;
    valorTotalInput.value = (saldo * valorUnit).toFixed(2);
  }

  saldoInput.addEventListener('input', calcularValorTotal);
  valorUnitInput.addEventListener('input', calcularValorTotal);
};
  window.editarPregao = function(id) {
  document.querySelector('#modalEditarPregao .modal-title')
          .textContent = `Editando Pregão #${id}`;

  const form = document.getElementById('form-pregao-editar');
  if (!form) return;

  fetch(`includes/fin_pregoes/buscar_pregao.php?id=${id}`)
    .then(res => res.json())
    .then(dados => {
      if (!dados.sucesso) {
        alert('Erro ao buscar dados do pregão.');
        return;
      }

      // Preenche os campos do formulário com os valores retornados
      const p = dados.pregao;
      form.setAttribute('data-id', id);
      form.querySelector('#editar-id').value = id;
      form.querySelector('#editar-nome_batalhao').value = p.nome_batalhao || '';
      form.querySelector('#editar-data_homologacao').value = p.data_homologacao || '';
      form.querySelector('#editar-descricao_pregao').value = p.descricao_pregao || '';
      form.querySelector('#editar-data_validade').value   = p.data_validade   || '';
      form.querySelector('#editar-tipo_pregao').value     = p.tipo_pregao      || '';
      form.querySelector('#editar-nmr_pregao').value      = p.nmr_pregao       || '';
      form.querySelector('#editar-ano_pregao').value      = p.ano_pregao       || '';
      form.querySelector('#editar-ug_licitacao').value    = p.ug_licitacao     || '';
      form.querySelector('#editar-uasg_licitacao').value  = p.uasg_licitacao   || '';
      form.querySelector('#editar-nup_licitacao').value   = p.nup_licitacao    || '';
      form.querySelector('#editar-continuidade_pregao').value = p.continuidade_pregao || '';

      // Limpa container de itens e reseta contador
      const container = document.getElementById('itensPregaoContainerEditar');
      container.innerHTML = '';
      contadorItensPregaoEditar = 0;

      // Sevier para reusar função de adicionar item
      if (Array.isArray(dados.itens)) {
        dados.itens.forEach(item => {
          adicionarItemPregaoEditar(item);
        });
      }

      // Atenção: NÃO abra o modal manualmente aqui. O atributo data-bs-target no próprio botão já cuida disso.
    })
    .catch(err => {
      console.error(err);
      alert('Erro de rede ao buscar pregão.');
    });
};

// ===========================
// Exportar Excel - Pregão
// ===========================
(function(){
  const btn = document.getElementById('btnExportarExcelPregao');
  if (!btn) return;

  function getVal(id, name) {
    const elById = document.querySelector(id);
    if (elById) return elById.value || '';
    const elByName = document.querySelector('[name="'+name+'"]');
    return elByName ? elByName.value || '' : '';
  }

  btn.addEventListener('click', function(e){
    e.preventDefault();
    e.stopPropagation();

    const filtros = {
      id: getVal('#filtroId','id'),
      nmr_pregao: getVal('#filtroNmrPregao','nmr_pregao'),
      ano_pregao: getVal('#filtroAnoPregao','ano_pregao'),
      ug_licitacao: getVal('#filtroUgLicitacao','ug_licitacao'),
      uasg_licitacao: getVal('#filtroUasgLicitacao','uasg_licitacao'),
      tipo_pregao: getVal('#filtroTipoPregao','tipo_pregao'),
      data_ini: getVal('#filtroDataIni','data_ini'),
      data_fim: getVal('#filtroDataFim','data_fim'),
      batalhao: getVal('#filtroBatalhao','batalhao')
    };

    const params = new URLSearchParams();
    Object.keys(filtros).forEach(k => {
      if (filtros[k] !== '' && filtros[k] !== null) params.set(k, filtros[k]);
    });

    const url = 'excel/pregoes_calendario.php?' + params.toString();
    window.open(url, '_blank'); // abre nova aba com Excel
  });
})();


window.deletarPregao = function(botao) {
  const id = botao.getAttribute('data-id');

  Swal.fire({
    title: 'Deletar Pregão?',
    text: "Essa ação não poderá ser desfeita. Deseja realmente excluir este Pregão e todos os registros relacionados?",
    icon: 'warning',
    showCancelButton: true,
    confirmButtonText: 'Sim, deletar',
    cancelButtonText: 'Cancelar'
  }).then((result) => {
    if (result.isConfirmed) {
      const formData = new FormData();
      formData.append('id', id);

      fetch('includes/fin_pregoes/deletar_pregoes.php', {
        method: 'POST',
        body: formData
      })
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          Swal.fire({
            icon: 'success',
            title: 'Deletado!',
            text: 'Pregão excluído com sucesso.',
            timer: 1500,
            showConfirmButton: false
          }).then(() => {
            carregarPagina('includes/fin_pregoes/listagem.php');
          });
        } else {
          Swal.fire({
            icon: 'error',
            title: 'Erro',
            text: data.message || 'Erro ao deletar Pregão.'
          });
        }
      })
      .catch(error => {
        Swal.fire({
          icon: 'error',
          title: 'Erro de rede',
          text: error.message
        });
      });
    }
  });
}

// Delega o evento para todos os botões com a classe deletar-pregao
document.querySelectorAll('.btn-deletar-pregao').forEach(botao => {
  botao.addEventListener('click', function() {
    deletarPregao(this);
  });
});

window.verPregao = function(id) {
  fetch(`includes/fin_pregoes/buscar_pregao_ver.php?id=${id}`)
    .then(res => res.json())
    .then(dados => {
      if (!dados.sucesso) {
        alert('Erro ao buscar dados do pregão.');
        return;
      }

      const p = dados.pregao;

      document.getElementById('ver_nmr_pregao').textContent = p.nmr_pregao;
      document.getElementById('ver_ano_pregao').textContent = p.ano_pregao;
      document.getElementById('ver_tipo_pregao').textContent = p.tipo_pregao;
      document.getElementById('ver_ug_licitacao').textContent = p.ug_licitacao;
      document.getElementById('ver_uasg_licitacao').textContent = p.uasg_licitacao;
      document.getElementById('ver_nup_licitacao').textContent = p.nup_licitacao;
      document.getElementById('ver_data_homologacao').textContent = p.data_homologacao || '—';
      document.getElementById('ver_data_validade').textContent = p.data_validade || '—';
      document.getElementById('ver_continuidade_pregao').textContent = p.continuidade_pregao;

      const container = document.getElementById('itensPregaoVerContainer');
      container.innerHTML = '';

      dados.itens.forEach((item, idx) => {
        const tr = document.createElement('tr');
        tr.innerHTML = `
          <td>${idx + 1}</td>
          <td>${item.descricao_item}</td>
          <td>${item.saldo_item}</td>
          <td>${item.total_saida}</td>
          <td>${item.disponivel}</td>
        `;
        container.appendChild(tr);
      });

    });
};

};
