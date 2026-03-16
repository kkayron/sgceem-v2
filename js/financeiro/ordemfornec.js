window.inicializarOF = function () {
    
// Importar Ordens de Fornecimento
const formImportarOrdem = document.getElementById('formImportarOrdem');

if (formImportarOrdem) {
  formImportarOrdem.addEventListener('submit', function (e) {
    e.preventDefault();

    const formData = new FormData(formImportarOrdem);

    fetch('includes/fin_ordensdefornecimento/importar_ordens.php', {
      method: 'POST',
      body: formData
    })
      .then(res => res.json())
      .then(data => {
        if (data.status === 'ok') {

          let mensagem = data.mensagem;

          if (data.falhas && data.falhas.length > 0) {
            mensagem += "\n\nFalhas encontradas:\n" +
              data.falhas.map(f => `Linha ${f.linha}: ${f.erro}`).join("\n");
          }

          swal({
            title: "Importação concluída!",
            text: mensagem,
            icon: "success",
            button: { text: "OK", className: "btn btn-success" }
          }).then(() => {
            carregarPagina('includes/fin_ordensdefornecimento/listagem.php');
            fecharModalAberto();
          });

        } else {

          let erroMsg = data.mensagem;

          if (data.falhas && data.falhas.length > 0) {
            erroMsg += "\n\nFalhas:\n" +
              data.falhas.map(f => `Linha ${f.linha}: ${f.erro}`).join("\n");
          }

          swal({
            title: "Erro na importação!",
            text: erroMsg,
            icon: "error",
            button: { text: "Fechar", className: "btn btn-danger" }
          });
        }
      })
      .catch(err => {
        swal({
          title: "Erro!",
          text: "Erro de rede: " + err.message,
          icon: "error",
          button: { text: "Fechar", className: "btn btn-danger" }
        });
      });
  });
}
    
    
let pedidoWindow = null; // variável global para controlar a aba

// Remove event listeners anteriores, se existirem
document.removeEventListener('click', handleExportPDFordem);
document.removeEventListener('click', handleExportPDFofpedidos);

// Função genérica para abrir o PDF com loader
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
// Botão 1: Exportar Ordem de Fornecimento
// ===============================
function handleExportPDFordem(e) {
    const btn = e.target.closest('.btnExportarPDFordem');
    if (!btn) return;

    e.preventDefault();

    const id = btn.getAttribute('data-id');
    if (!id) {
        alert("ID da ordem inválido.");
        return;
    }

    const url = `pdf/gerar_pdf_ordem.php?id=${id}`;
    abrirPDFComLoader(url, 'Ordem de Fornecimento');
}

// ===============================
// Botão 2: Exportar Ordem + Pedidos Vinculados
// ===============================
function handleExportPDFofpedidos(e) {
    const btn = e.target.closest('.btnExportarPDFofpedidos');
    if (!btn) return;

    e.preventDefault();

    const id = btn.getAttribute('data-id');
    if (!id) {
        alert("ID da ordem inválido.");
        return;
    }

    const url = `pdf/gerar_of_e_pedido.php?id=${id}`;
    abrirPDFComLoader(url, 'Ordem de Fornecimento + Pedidos');
}

// Adiciona ambos os event listeners
document.addEventListener('click', handleExportPDFordem);
document.addEventListener('click', handleExportPDFofpedidos);


    
    // Puxar os dados do fornecedor pelo empenho
document.getElementById('id_empenho').addEventListener('change', function() {
  const id = this.value;
  if (!id) return;

  fetch(`includes/fin_ordensdefornecimento/buscar_empenho.php?id=${id}`)
    .then(r => r.json())
    .then(json => {
      if (!json.sucesso) {
        swal('Erro', json.mensagem || 'Falha ao buscar dados.', 'error');
        return;
      }

      // Ajuste aqui
      const f = json.fornecedor || { nome_empresa:'', cnpj_empresa:'', contato_email:'' };
      document.getElementById('empresa_nome').value   = f.nome_empresa;
      document.getElementById('empresa_cnpj').value   = f.cnpj_empresa;
      document.getElementById('empresa_email').value  = f.contato_email;
    })
    .catch(err => {
      console.error(err);
      swal('Erro de rede', err.message, 'error');
    });
});
    
    //Abrir a área de filtros

// Alternar exibição dos filtros
window.toggleFiltrosOF = function () {
  const container = document.getElementById('filtros-container-of');
  if (container) {
    const isVisible = container.style.display === 'block';
    container.style.display = isVisible ? 'none' : 'block';
  }
};

// Formulário e elementos de filtro
const formOF = document.getElementById('filtroPregaoForm');
const btnLimparFiltrosOF = document.getElementById('btnLimparFiltrosOF');
const limiteSelectOF = document.getElementById('limiteOF');

// Função de atualização da listagem
function atualizarListaOF(extraParams = {}) {
  if (!formOF) return;

  const formData = new FormData(formOF);
  const params = new URLSearchParams(formData);

  for (const key in extraParams) {
    params.set(key, extraParams[key]);
  }

  const url = `includes/fin_ordensdefornecimento/listagem.php?${params.toString()}`;
  carregarPagina(url);
}

// Submissão do formulário de filtros
if (formOF) {
  formOF.addEventListener('submit', function (e) {
    e.preventDefault();
    atualizarListaOF({ pagina: 1 });
  });
}

// Atualiza ao mudar o limite por página
if (limiteSelectOF) {
  limiteSelectOF.addEventListener('change', function () {
    atualizarListaOF({ pagina: 1, limite: this.value });
  });
}

// Botão de limpar filtros
if (btnLimparFiltrosOF && formOF) {
  btnLimparFiltrosOF.addEventListener('click', function (e) {
    e.preventDefault();
    formOF.reset();

    const limiteAtual = limiteSelectOF?.value || 10;
    const url = `includes/fin_ordensdefornecimento/listagem.php?pagina=1&limite=${limiteAtual}`;
    carregarPagina(url);
  });
}





  // —————— ADICIONAR/REMOVER SELECTS DE PEDIDOS NA ORDEM ——————
  let contadorPedidos = 0;

  // Guarda o HTML das opções de pedido (primeiro select já existente)
  const pedidosOptionsHTML = (() => {
    const sel = document.querySelector('#pedidosContainer select[name="pedidos[]"]');
    return sel ? sel.innerHTML : '';
  })();

 window.adicionarPedidoSelect = function() {
    contadorPedidos++;
    const container = document.getElementById('pedidosContainer');
    const linha = document.createElement('div');
    linha.className = 'row g-2 align-items-end pedido-linha mb-2';
    linha.innerHTML = `
      <div class="col-md-10">
        <select class="form-select" name="pedidos[]" required>
          <option value="" disabled selected>Selecione um pedido</option>
          ${pedidosOptionsHTML}
        </select>
      </div>
      <div class="col-md-2">
        <button type="button" class="btn btn-danger w-100"
                onclick="this.closest('.pedido-linha').remove()">Remover</button>
      </div>
    `;
    container.appendChild(linha);
  }

  // Ao abrir o modal de cadastro de ordem, limpa selects extras
  const modalCadOF = document.getElementById('modalCadastrarOrdem');
  modalCadOF?.addEventListener('show.bs.modal', () => {
    document.querySelectorAll('#pedidosContainer .pedido-linha:not(:first-child)')
      .forEach(el => el.remove());
    contadorPedidos = 0;
  });


  // —————— ENVIO DO FORMULÁRIO VIA AJAX ——————
  const formCadOF = document.getElementById('form-ordem-cadastrar');
  if (formCadOF) {
    formCadOF.addEventListener('submit', e => {
      e.preventDefault();
      const formData = new FormData(formCadOF);
      fetch('includes/fin_ordensdefornecimento/cadastrar_ordem.php', {
        method: 'POST',
        body: formData
      })
      .then(r => r.json())
      .then(data => {
        if (data.status === 'sucesso') {
          swal("Sucesso!", data.mensagem || "Ordem cadastrada com sucesso!", "success")
            .then(() => {
              carregarPagina('includes/fin_ordensdefornecimento/listagem.php');
              bootstrap.Modal.getInstance(modalCadOF).hide();
            });
        } else {
          swal("Erro!", data.mensagem || "Falha ao cadastrar ordem.", "error");
        }
      })
      .catch(err => swal("Erro de rede", err.message, "error"));
    });
  }



const formEditarOrdem = document.getElementById('form-ordem-editar');
if (formEditarOrdem) {
  formEditarOrdem.addEventListener('submit', function (e) {
    e.preventDefault(); // impede reload da página

    const formData = new FormData(formEditarOrdem);

    fetch('includes/fin_ordensdefornecimento/salvar_editar.php', {
      method: 'POST',
      body: formData
    })
    .then(res => res.json()) // ← ESSENCIAL! usar .json()
    .then(data => {
      if (data.status === 'sucesso') {
        swal("Sucesso!", data.mensagem, "success").then(() => {
          carregarPagina('includes/fin_ordensdefornecimento/listagem.php');
          fecharModalAberto();
        });
      } else {
        swal("Erro!", data.mensagem || "Erro ao editar ordem.", "error");
      }
    })
    .catch(err => {
      console.error(err);
      swal("Erro de rede!", err.message, "error");
    });
  });
}

let contadorPedidosEditar = 0;

window.adicionarPedidoEdit = function (pedidoId = null) {
  contadorPedidosEditar++;

  const container = document.getElementById('editarPedidosContainer');
  const row = document.createElement('div');
  row.classList.add('row', 'g-2', 'align-items-end', 'pedido-linha');

  row.innerHTML = `
    <div class="col-md-10">
      <label class="form-label">Pedido</label>
      <select class="form-select" name="pedidos[]">
        <option value="" selected disabled>Selecione um pedido</option>
        ${window.selectPedidosDisponiveisTemplate}
      </select>
    </div>
    <div class="col-md-2">
      <button type="button" class="btn btn-outline-danger w-100" onclick="this.closest('.pedido-linha').remove()">Remover</button>
    </div>
  `;

  container.appendChild(row);

  if (pedidoId) {
    const select = row.querySelector('select');
    select.value = pedidoId;
  }
};

window.editarOrdem = function (id) {
  const modal = document.getElementById('modalEditarOrdem');
  const form = document.getElementById('form-ordem-editar');
  if (!form || !modal) return;

  fetch(`includes/fin_ordensdefornecimento/buscar_ordem.php?id=${id}`)
    .then(res => res.json())
    .then(json => {
      if (!json.sucesso) {
        swal("Erro", json.mensagem || "Erro ao buscar dados da ordem.", "error");
        return;
      }

      const o = json.ordem;

      form.querySelector('#editar-id_ordem').value = id;
      form.querySelector('#editar-id_empenho').value = o.id_empenho;
      form.querySelector('#editar-batalhao').value = o.batalhao;
      form.querySelector('#editar-data_cadastro').value = o.data_cadastro;
      form.querySelector('#editar-data_entrega_limite').value = o.data_entrega_limite;
      form.querySelector('#editar-status').value = o.status;
      form.querySelector('#editar-empresa_nome').value = o.empresa_nome;
      form.querySelector('#editar-empresa_cnpj').value = o.empresa_cnpj;
      form.querySelector('#editar-empresa_email').value = o.empresa_email;
      form.querySelector('#editar-local_entrega').value = o.local_entrega;
      form.querySelector('#editar-nome_responsavel').value = o.nome_responsavel;
      form.querySelector('#editar-contato_responsavel').value = o.contato_responsavel;
      form.querySelector('#editar-observacao_final').value = o.observacao_final || '';

      // pedidos vinculados
      const pedidos = json.pedidos || [];
      const container = document.getElementById('editarPedidosContainer');
      container.innerHTML = '';
      contadorPedidosEditar = 0;

      pedidos.forEach(p => adicionarPedidoEdit(p.id));
    })
    .catch(err => {
      console.error(err);
      swal("Erro de rede", err.message, "error");
    });
};
    
window.deletarOF = function(botao) {
  const id = botao.getAttribute('data-id');

  Swal.fire({
    title: 'Deletar Ordem de Fornecimento?',
    text: "Essa ação não poderá ser desfeita. Deseja realmente excluir esta Ordem de Fornecimento e todos os registros relacionados?",
    icon: 'warning',
    showCancelButton: true,
    confirmButtonText: 'Sim, deletar',
    cancelButtonText: 'Cancelar'
  }).then((result) => {
    if (result.isConfirmed) {
      const formData = new FormData();
      formData.append('id', id);

      fetch('includes/fin_ordensdefornecimento/deletar_of.php', {
        method: 'POST',
        body: formData
      })
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          Swal.fire({
            icon: 'success',
            title: 'Deletado!',
            text: 'Ordem de fornecimento excluído com sucesso.',
            timer: 1500,
            showConfirmButton: false
          }).then(() => {
            carregarPagina('includes/fin_ordensdefornecimento/listagem.php');
          });
        } else {
          Swal.fire({
            icon: 'error',
            title: 'Erro',
            text: data.message || 'Erro ao deletar ordem de fornecimento.'
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



    };