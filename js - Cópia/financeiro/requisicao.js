window.inicializarRequisicao = function () {
    let pedidoWindow = null; // variável global para controlar a aba

// Remove event listeners anteriores, se existirem
document.removeEventListener('click', handleExportReq);
    
    function abrirPDFComLoader(url, tituloJanela) {
    if (pedidoWindow && !pedidoWindow.closed) {
        pedidoWindow.location.href = url;
        pedidoWindow.focus();
        return;
    }

    pedidoWindow = window.open('', '_blank');
    pedidoWindow.document.write(`
        <html>
        <head>
            <title>${tituloJanela}</title>
            <style>
                body {
                    font-family: 'Arial', sans-serif;
                    margin:0;
                    padding:0;
                    display:flex;
                    justify-content:center;
                    align-items:center;
                    height:100vh;
                    background: #f8f9fa;
                }
                .loader-container {
                    text-align:center;
                    background: rgba(255,255,255,0.95);
                    padding: 40px 60px;
                    border-radius: 12px;
                    box-shadow: 0 8px 20px rgba(0,0,0,0.2);
                    animation: fadeIn 0.3s ease-in-out;
                }
                h3 {
                    margin-bottom: 20px;
                    color: #0d6efd;
                    font-weight: 600;
                    font-size: 1.5rem;
                }
                .spinner {
                    border: 6px solid #e9ecef;
                    border-top: 6px solid #0d6efd;
                    border-radius: 50%;
                    width: 60px;
                    height: 60px;
                    animation: spin 1s linear infinite;
                    margin: 0 auto;
                }
                @keyframes spin {
                    0% { transform: rotate(0deg); }
                    100% { transform: rotate(360deg); }
                }
                @keyframes fadeIn {
                    from { opacity: 0; transform: scale(0.95); }
                    to { opacity: 1; transform: scale(1); }
                }
                iframe {
                    display:none;
                    width:100%;
                    height:100vh;
                    border:none;
                }
            </style>
        </head>
        <body>
            <div class="loader-container">
                <h3>CARREGANDO PDF...</h3>
                <div class="spinner"></div>
            </div>
            <iframe src="${url}" 
                onload="this.style.display='block';document.querySelector('.loader-container').style.display='none';">
            </iframe>
        </body>
        </html>
    `);
}
// ===============================
// Botão 1: Exportar Requisição
// ===============================
function handleExportReq(e) {
    const btn = e.target.closest('.btnExportarReq');
    if (!btn) return;

    e.preventDefault();

    const id = btn.getAttribute('data-id');
    if (!id) {
        alert("ID da requisição inválido.");
        return;
    }

    const url = `pdf/gerar_req.php?id=${id}`;
    abrirPDFComLoader(url, 'Requisição');
}
    
    // Adiciona ambos os event listeners
document.addEventListener('click', handleExportReq);
    
    // ===============================
// Exportar Excel - Requisição
// ===============================
(function () {
  const btn = document.getElementById('btnExportarExcelRequisicao');
  if (!btn) return;

  // Busca valor por ID ou name, de forma segura
  function getFiltro(id, name) {
    let el = null;

    if (id) el = document.querySelector(id);
    if (!el && name) el = document.querySelector(`[name="${name}"]`);

    if (!el) return "";
    return el.value || "";
  }

  btn.addEventListener('click', function (e) {
    e.preventDefault();
    e.stopPropagation();

    // Filtros exatamente conforme o PHP
    const filtros = {
      id:            getFiltro('#filtroId', 'id'),
      requisitante:  getFiltro('#filtroRequisitante', 'requisitante'),
      destinatario:  getFiltro('#filtroDestinatario', 'destinatario'),
      nota_credito:  getFiltro('#filtroNotaCredito', 'nota_credito'),
      plano_interno: getFiltro('#filtroPlanoInterno', 'plano_interno'),
      data_ini:      getFiltro('#filtroDataIni', 'data_ini'),
      data_fim:      getFiltro('#filtroDataFim', 'data_fim'),
      batalhao:      getFiltro('#filtroBatalhao', 'batalhao'),
      nmr_empenho:   getFiltro('#filtroNmrEmpenho', 'nmr_empenho')
    };

    const params = new URLSearchParams();

    // Só adiciona se tiver valor
    Object.keys(filtros).forEach(k => {
      if (filtros[k] !== "") {
        params.append(k, filtros[k]);
      }
    });

    const url = 'excel/gerar_requisicoes.php?' + params.toString();
    window.open(url, '_blank'); // abre arquivo excel
  });
})();

 
    
    
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

window.verRequisicao = function(id) {
  fetch(`includes/fin_requisicoes/buscar_requisicao_ver.php?id=${id}`)
    .then(res => res.json())
    .then(dados => {
      if (!dados.sucesso) {
        alert('Erro ao buscar dados da requisição.');
        return;
      }

      const r = dados.requisicao;

      document.getElementById('ver_requisitante').textContent = r.requisitante;
      document.getElementById('ver_destinatario').textContent = r.destinatario;
      document.getElementById('ver_nota_credito').textContent = r.nota_credito;
      document.getElementById('ver_plano_interno').textContent = r.plano_interno;
      document.getElementById('ver_id_pregao').textContent = r.id_pregao;

      const container = document.getElementById('itensRequisicaoVerContainer');
      container.innerHTML = '';

      dados.itens.forEach((item, idx) => {
        const tr = document.createElement('tr');
        tr.innerHTML = `
          <td>${idx + 1}</td>
          <td>${item.descricao_item}</td>
          <td>${item.quant_saida_item}</td>
          <td>${item.valor_unt}</td>
          <td>${(item.quant_saida_item * item.valor_unt).toFixed(2)}</td>
        `;
        container.appendChild(tr);
      });
    });
};


window.deletarRequisicao = function(botao) {
  const id = botao.getAttribute('data-id');

  Swal.fire({
    title: 'Deletar Requisição?',
    text: "Essa ação não poderá ser desfeita. Deseja realmente excluir esta Requisição e todos os registros relacionados?",
    icon: 'warning',
    showCancelButton: true,
    confirmButtonText: 'Sim, deletar',
    cancelButtonText: 'Cancelar'
  }).then((result) => {
    if (result.isConfirmed) {
      const formData = new FormData();
      formData.append('id', id);

      fetch('includes/fin_requisicoes/deletar_requisicao.php', {
        method: 'POST',
        body: formData
      })
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          Swal.fire({
            icon: 'success',
            title: 'Deletado!',
            text: 'Requisição excluída com sucesso.',
            timer: 1500,
            showConfirmButton: false
          }).then(() => {
            carregarPagina('includes/fin_requisicoes/listagem.php');
          });
        } else {
          Swal.fire({
            icon: 'error',
            title: 'Erro',
            text: data.message || 'Erro ao deletar Requisição.'
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

// Delega o evento para todos os botões com a classe deletar-requisicao
document.querySelectorAll('.btn-deletar-requisicao').forEach(botao => {
  botao.addEventListener('click', function() {
    deletarRequisicao(this);
  });
});

//EDIÇÃO DA REQUISIÇÃO

  const formEditarRequisicao = document.getElementById('form-requisicao-editar');
  if (formEditarRequisicao) {
    formEditarRequisicao.addEventListener('submit', function (e) {
      e.preventDefault();
      const formData = new FormData(formEditarRequisicao);

      fetch('includes/fin_requisicoes/salvar_editar_requisicao.php', {
        method: 'POST',
        body: formData
      })
        .then(res => res.text())
        .then(resp => {
          if (resp === 'ok') {
            swal("Sucesso!", "Requisição atualizada com sucesso!", "success")
              .then(() => {
                carregarPagina('includes/fin_requisicoes/listagem.php');
                fecharModalAberto();
              });
          } else {
            swal("Erro!", resp, "error");
          }
        });
    });
  }

  let contadorItensRequisicaoEditar = 0;
  window.itensPregaoData = [];

  window.adicionarItemRequisicaoEditar = function (item = {}) {
    contadorItensRequisicaoEditar++;
    const container = document.getElementById('itensRequisicaoLista');

    const itemDiv = document.createElement('div');
    itemDiv.classList.add('border', 'rounded', 'p-3', 'mb-2', 'bg-light');

    itemDiv.innerHTML = `
      <h6 class="fw-bold">Item ${contadorItensRequisicaoEditar}</h6>
      <div class="mb-2">
        <label class="form-label">Item do Pregão</label>
        <select class="form-select item-select" name="itens[${contadorItensRequisicaoEditar}][id_item]" required>
          ${window.selectItensPregaoTemplate}
        </select>
      </div>
      <div class="mb-2">
        <label class="form-label">Quantidade de Saída</label>
        <input type="number" step="any" class="form-control quant-saida" name="itens[${contadorItensRequisicaoEditar}][quant_saida_item]" value="${item.quant_saida_item || ''}" required>
      </div>
      <div class="mb-2">
        <label class="form-label">Valor Unitário</label>
        <input type="number" step="any" class="form-control valor-unitario" readonly>
      </div>
      <div class="mb-2">
        <label class="form-label">Valor Total</label>
        <input type="number" step="any" class="form-control valor-total" readonly>
      </div>
      <button type="button" class="btn btn-danger btn-sm" onclick="this.parentElement.remove()">Remover item</button>
    `;

    container.appendChild(itemDiv);

    const select = itemDiv.querySelector('.item-select');
    const inputQuant = itemDiv.querySelector('.quant-saida');
    const inputUnit = itemDiv.querySelector('.valor-unitario');
    const inputTotal = itemDiv.querySelector('.valor-total');

    function atualizarCampos() {
      const selectedId = select.value;
      const itemData = window.itensPregaoData.find(i => i.id == selectedId);
      if (itemData) {
        inputUnit.value = itemData.valor_unt;
        const quant = parseFloat(inputQuant.value) || 0;
        inputTotal.value = (quant * parseFloat(itemData.valor_unt)).toFixed(2);
      } else {
        inputUnit.value = '';
        inputTotal.value = '';
      }
    }

    select.addEventListener('change', atualizarCampos);
    inputQuant.addEventListener('input', atualizarCampos);

    if (item.id_item) {
      select.value = item.id_item;
      atualizarCampos();
    }
  };

  const selectPregao = document.getElementById('editar-pregao');

  selectPregao.addEventListener('change', function () {
    const idPregao = this.value;

    if (!idPregao) {
      document.getElementById('itensRequisicaoContainerEditar').style.display = 'none';
      document.getElementById('btnAdicionarItemRequisicaoEditar').style.display = 'none';
      return;
    }

    fetch(`includes/fin_requisicoes/buscar_itens_pregao.php?id=${idPregao}`)
      .then(res => res.json())
      .then(data => {
        if (!data.sucesso) {
          alert('Erro ao carregar itens do pregão.');
          return;
        }

        window.itensPregaoData = data.itens;

        window.selectItensPregaoTemplate = data.itens.map(item =>
          `<option value="${item.id}">
     ${item.descricao_item} — Fornecedor: ${item.fornecedor} — Saldo: ${item.saldo_item}
   </option>`
        ).join('');

        document.getElementById('itensRequisicaoContainerEditar').style.display = 'block';
        document.getElementById('btnAdicionarItemRequisicaoEditar').style.display = 'inline-block';

        document.getElementById('itensRequisicaoLista').innerHTML = '';
        contadorItensRequisicaoEditar = 0;
      });
  });

  window.editarRequisicao = function (id) {
    document.querySelector('#modalEditarRequisicao .modal-title').textContent = `Editando Requisição #${id}`;
    const form = document.getElementById('form-requisicao-editar');
    if (!form) return;

    fetch(`includes/fin_requisicoes/buscar_requisicao.php?id=${id}`)
      .then(res => res.json())
      .then(dados => {
        if (!dados.sucesso) {
          alert('Erro ao buscar dados da requisição.');
          return;
        }

        form.setAttribute('data-id', id);
        form.querySelector('#editar-id-requisicao').value = id;

        form.querySelector('#editar-requisitante').value = dados.requisicao.requisitante || '';
        form.querySelector('#editar-natureza_despesa').value = dados.requisicao.natureza_despesa || '';
        form.querySelector('#editar-destinatario').value = dados.requisicao.destinatario || '';
        form.querySelector('#editar-finalidade').value = dados.requisicao.finalidade || '';
        form.querySelector('#editar-item_oog').value = dados.requisicao.item_oog || '';
        form.querySelector('#editar-necessidade_contrato').value = dados.requisicao.necessidade_contrato || '';
        form.querySelector('#editar-nota_credito').value = dados.requisicao.nota_credito || '';
        form.querySelector('#editar-plano_interno').value = dados.requisicao.plano_interno || '';
        form.querySelector('#editar-tipo_empenho').value = dados.requisicao.tipo_empenho || '';
        form.querySelector('#editar-status_requisicao').value = dados.requisicao.status_requisicao || '';

        const selectPregao = form.querySelector('#editar-pregao');
        carregarPregoes(selectPregao, dados.requisicao.id_pregao);

        const container = document.getElementById('itensRequisicaoContainerEditar');
        const lista = document.getElementById('itensRequisicaoLista');

        lista.innerHTML = '';
        contadorItensRequisicaoEditar = 0;

        const idPregao = dados.requisicao.id_pregao;
        if (!idPregao) {
          container.style.display = 'none';
          return;
        }

        fetch(`includes/fin_requisicoes/buscar_itens_pregao.php?id=${idPregao}`)
          .then(res => res.json())
          .then(data => {
            if (!data.sucesso) {
              alert('Erro ao carregar itens do pregão.');
              return;
            }

            window.itensPregaoData = data.itens;

            window.selectItensPregaoTemplate = data.itens.map(item =>
              `<option value="${item.id}">${item.descricao_item} — Fornecedor: ${item.fornecedor} — Saldo: ${item.saldo_item}</option>`
            ).join('');

            container.style.display = 'block';
            document.getElementById('btnAdicionarItemRequisicaoEditar').style.display = 'inline-block';

            if (Array.isArray(dados.itens)) {
              dados.itens.forEach(item => {
                adicionarItemRequisicaoEditar(item);
              });
            }
          });
      });
  };

  function carregarPregoes(selectElement, idSelecionado = '') {
    fetch('includes/fin_pregoes/buscar_pregao.php')
      .then(response => response.json())
      .then(data => {
        selectElement.innerHTML = '<option value="">Selecione um pregão</option>';
        data.forEach(pregao => {
          const option = document.createElement('option');
          option.value = pregao.id;
          option.text = pregao.nome;
          selectElement.add(option);
        });
        selectElement.value = idSelecionado;
      })
      .catch(error => console.error('Erro ao carregar pregões:', error));
  }

  const btnAdicionarItem = document.getElementById('btnAdicionarItemRequisicaoEditar');
  if (btnAdicionarItem) {
    btnAdicionarItem.addEventListener('click', function () {
      adicionarItemRequisicaoEditar();
    });
  }
	
// ===============================
// CADASTRAR REQUISIÇÃO
// ===============================

let contadorItensRequisicaoCadastrar = 0;

window.itensPregaoDataCadastrar = [];

const formCadastrar = document.getElementById('form-requisicao-cadastrar');

if (formCadastrar) {
  formCadastrar.addEventListener('submit', function (e) {

    e.preventDefault();

    const formData = new FormData(formCadastrar);

    fetch('includes/fin_requisicoes/salvar_cadastrar_requisicao.php', {
      method: 'POST',
      body: formData
    })
      .then(res => res.text())
      .then(resp => {

        if (resp === 'ok') {

          Swal.fire({
            icon: 'success',
            title: 'Requisição cadastrada!',
            timer: 1500,
            showConfirmButton: false
          }).then(() => {

            carregarPagina('includes/fin_requisicoes/listagem.php');

            const modal = bootstrap.Modal.getInstance(
              document.getElementById('modalCadastrarRequisicao')
            );
            modal.hide();

          });

        } else {

          Swal.fire({
            icon: 'error',
            title: 'Erro',
            text: resp
          });

        }

      });

  });
}


// ===============================
// ADICIONAR ITEM
// ===============================
window.adicionarItemRequisicaoCadastrar = function () {

  contadorItensRequisicaoCadastrar++;

  const container = document.getElementById('itensRequisicaoListaCadastrar');

  const tr = document.createElement('tr');

  tr.innerHTML = `
<td>${contadorItensRequisicaoCadastrar}</td>

<td>
<select class="form-select item-select"
name="itens[${contadorItensRequisicaoCadastrar}][id_item]" required>
${window.selectItensPregaoTemplateCadastrar}
</select>
</td>

<td>
<input type="number" step="any"
class="form-control quant-saida"
name="itens[${contadorItensRequisicaoCadastrar}][quant_saida_item]" required>
</td>

<td>
<input type="number" class="form-control valor-unitario" readonly>
</td>

<td>
<input type="number" class="form-control valor-total" readonly>
</td>

<td>
<button type="button" class="btn btn-danger btn-sm remover-item">
X
</button>
</td>
`;

  container.appendChild(tr);

  const select = tr.querySelector('.item-select');
  const quant = tr.querySelector('.quant-saida');
  const unit = tr.querySelector('.valor-unitario');
  const total = tr.querySelector('.valor-total');

  function atualizar() {

    const item = window.itensPregaoDataCadastrar.find(
      i => i.id == select.value
    );

    if (!item) return;

    unit.value = item.valor_unt;

    const q = parseFloat(quant.value) || 0;

    total.value = (q * item.valor_unt).toFixed(2);

  }

  select.addEventListener('change', atualizar);
  quant.addEventListener('input', atualizar);

  tr.querySelector('.remover-item').addEventListener('click', () => {
    tr.remove();
  });

};


// ===============================
// BOTÃO ADICIONAR ITEM
// ===============================

document.getElementById('btnAdicionarItemRequisicaoCadastrar')
?.addEventListener('click', () => {

  adicionarItemRequisicaoCadastrar();

});


// ===============================
// CARREGAR ITENS DO PREGÃO
// ===============================

const selectPregaoCadastrar = document.getElementById('cadastrar-pregao');

if (selectPregaoCadastrar) {

  selectPregaoCadastrar.addEventListener('change', function () {

    const idPregao = this.value;

    if (!idPregao) {

      document.getElementById('itensRequisicaoContainerCadastrar').style.display = 'none';
      return;

    }

    fetch(`includes/fin_requisicoes/buscar_itens_pregao.php?id=${idPregao}`)

      .then(res => res.json())

      .then(data => {

        if (!data.sucesso) {

          alert('Erro ao carregar itens do pregão');
          return;

        }

        window.itensPregaoDataCadastrar = data.itens;

        window.selectItensPregaoTemplateCadastrar =
          data.itens.map(item => `
<option value="${item.id}">
${item.descricao_item} — Fornecedor: ${item.fornecedor} — Saldo: ${item.saldo_item}
</option>`).join('');

        document.getElementById('itensRequisicaoContainerCadastrar').style.display = 'block';

        document.getElementById('itensRequisicaoListaCadastrar').innerHTML = '';

        contadorItensRequisicaoCadastrar = 0;

      });

  });

}
    
// ===============================
// CARREGAR PREGÕES NO CADASTRAR
// ===============================

const selectPregaoCadastrarLoad = document.getElementById('cadastrar-pregao');

if (selectPregaoCadastrarLoad) {

  fetch('includes/fin_pregoes/buscar_pregao.php')
    .then(response => response.json())
    .then(data => {

      selectPregaoCadastrarLoad.innerHTML = '<option value="">Selecione um pregão</option>';

      data.forEach(pregao => {

        const option = document.createElement('option');

        option.value = pregao.id;
        option.text = pregao.nome;

        selectPregaoCadastrarLoad.add(option);

      });

    })
    .catch(error => {

      console.error('Erro ao carregar pregões:', error);

    });

}

window.gerarEmpenho = function(id){

    const modal = new bootstrap.Modal(document.getElementById('modalGerarEmpenho'));
    modal.show();

    document.getElementById('gerar-id-requisicao').value = id;

    document.querySelector('#modalLabelGerarEmpenho').innerText =
        "Gerar Empenho da Requisição #" + id;

};

// SUBMIT DO FORM

const formGerarEmpenho = document.getElementById('form-gerar-empenho');

if(formGerarEmpenho){

formGerarEmpenho.addEventListener('submit', function(e){

    e.preventDefault();

    const formData = new FormData(this);

    fetch('includes/fin_requisicoes/gerar_empenho.php',{
        method:'POST',
        body:formData
    })
    .then(res => res.text())
    .then(resp => {

        if(resp === 'ok'){

            swal("Sucesso","Empenho gerado com sucesso","success")
            .then(()=>{

                carregarPagina('includes/fin_requisicoes/listagem.php');

                fecharModalAberto();

            });

        }else{

            swal("Erro",resp,"error");

        }

    });

});

}

window.editarEmpenho = function(id){

fetch('includes/fin_requisicoes/buscar_empenho.php?id=' + id)

.then(res => res.json())

.then(r => {

    if(!r.sucesso){
        swal("Erro", r.mensagem, "error");
        return;
    }

    const emp = r.empenho;

    if(!emp){
        swal("Erro","Empenho não encontrado","error");
        return;
    }

    // ID da requisição
    document.getElementById('gerar-id-requisicao').value = id;

    // CAMPOS DO EMPENHO
    document.querySelector('#form-gerar-empenho [name="data_empenho"]').value = emp.data_empenho;
    document.querySelector('#form-gerar-empenho [name="nmr_empenho"]').value = emp.nmr_empenho;
    document.querySelector('#form-gerar-empenho [name="obra"]').value = emp.obra;
    document.querySelector('#form-gerar-empenho [name="ano"]').value = emp.ano;
    document.querySelector('#form-gerar-empenho [name="categoria"]').value = emp.categoria;
    document.querySelector('#form-gerar-empenho [name="local"]').value = emp.local;
    document.querySelector('#form-gerar-empenho [name="resto_pagar"]').value = emp.resto_pagar;

    // TÍTULO DO MODAL
    document.querySelector('#modalLabelGerarEmpenho').innerText =
        "Editar Empenho da Requisição #" + id;

});

}

    
};
