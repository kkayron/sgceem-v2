// JavaScript Document
window.inicializarAlmoxDepositos = function () {
    // TOGGLEFILTRO PRODUTOS
window.toggleFiltrosALMOX = function () {
  const container = document.getElementById('filtros-container-almox');
  if (container) {
    container.style.display = container.style.display === 'none' ? 'block' : 'none';
  }
};

// FORMULÁRIO DE FILTRO DOS PRODUTOS
const formALMOX = document.getElementById('filtroAlmoxForm');

function atualizarListaALMOX(extraParams = {}) {
  if (!formALMOX) return;

  const formData = new FormData(formALMOX);
  const params = new URLSearchParams(formData);

  for (const key in extraParams) {
    params.set(key, extraParams[key]);
  }

  const url = `includes/almox_depositos/listagem.php?${params.toString()}`;
  carregarPagina(url); // função AJAX existente no sistema
}

// Submissão do formulário
if (formALMOX) {
  formALMOX.addEventListener('submit', function (e) {
    e.preventDefault();
    atualizarListaALMOX({ pagina: 1 });
  });
}

// Mudança de limite por página
const limiteSelectALMOX = document.getElementById('limiteAlmox');
if (limiteSelectALMOX) {
  limiteSelectALMOX.addEventListener('change', function () {
    atualizarListaALMOX({ pagina: 1, limite: this.value });
  });
}

// Botão "Limpar Filtros"
const btnLimparFiltrosAlmox = document.getElementById('btnLimparFiltrosAlmox');
if (btnLimparFiltrosAlmox && formALMOX) {
  btnLimparFiltrosAlmox.addEventListener('click', function (e) {
    e.preventDefault();
    formALMOX.reset();

    const limiteAtual = document.getElementById('limiteAlmox')?.value || 10;
    const url = `includes/almox_depositos/listagem.php?pagina=1&limite=${limiteAtual}`;
    carregarPagina(url); // AJAX sem filtros
  });
}

// ===============================
// Cadastrar Depósitos
// ===============================
const formCadastrarDepositos = document.getElementById('form-cadastrar-deposito');

if (formCadastrarDepositos) {
  formCadastrarDepositos.addEventListener('submit', function (e) {
    e.preventDefault();

    const formData = new FormData(formCadastrarDepositos);

    fetch('includes/almox_depositos/cadastrar_deposito.php', {
      method: 'POST',
      body: formData
    })
    .then(res => res.json())
    .then(data => {
      if (data.status === 'sucesso') {
        swal("Sucesso!", data.mensagem, "success").then(() => {
          carregarPagina('includes/almox_depositos/listagem.php');
          fecharModalAberto();
        });
      } else {
        swal("Erro!", data.mensagem, "error");
      }
    })
    .catch(err => {
      swal("Erro!", "Erro de rede: " + err.message, "error");
    });
  });
}

window.editarDepositoAlmox = function (id) {
  const form = document.getElementById('form-editar-deposito');
  if (!form) return;

  fetch(`includes/almox_depositos/buscar_deposito_editar.php?id=${id}`)
    .then(res => res.json())
    .then(dados => {

      if (!dados.sucesso) {
        swal('Erro', dados.mensagem || 'Erro ao buscar dados do depósito.', 'error');
        return;
      }

      const deposito = dados.deposito;

      // ===============================
      // Preenche campos
      // ===============================
      form.querySelector('#edit-id-deposito').value = deposito.id;
      form.querySelector('[name="id"]').value = deposito.id;
      form.querySelector('[name="nome_deposito"]').value = deposito.nome_deposito || '';
      form.querySelector('[name="local_deposito"]').value = deposito.local_deposito || '';
      form.querySelector('[name="observacoes_deposito"]').value = deposito.observacoes_deposito || '';
      form.querySelector('[name="batalhao"]').value = deposito.batalhao || '';

      if (form.querySelector('#nome_batalhao_edit')) {
        form.querySelector('#nome_batalhao_edit').value = deposito.nome_batalhao || '';
      }
    })
    .catch(err => {
      console.error(err);
      swal('Erro', 'Erro ao carregar dados do depósito.', 'error');
    });
};


// ===============================
// SUBMIT DO FORMULÁRIO
// ===============================
const formEditarDeposito = document.getElementById('form-editar-deposito');

if (formEditarDeposito) {
  formEditarDeposito.addEventListener('submit', function (e) {
    e.preventDefault();

    const formData = new FormData(formEditarDeposito);

    fetch('includes/almox_depositos/editar_deposito.php', {
      method: 'POST',
      body: formData
    })
      .then(res => res.json())
      .then(res => {
        if (res.status === 'sucesso') {
          swal('Sucesso', res.mensagem, 'success')
            .then(() => {
              carregarPagina('includes/almox_depositos/listagem.php');
              bootstrap.Modal.getInstance(
                document.getElementById('modalEditarDeposito')
              ).hide();
            });
        } else {
          swal('Erro', res.mensagem || 'Falha ao atualizar depósito.', 'error');
        }
      })
      .catch(err => {
        swal('Erro de rede', err.message, 'error');
      });
  });
}

// ===============================
// DELETAR DEPÓSITO
// ===============================
window.deletarDEPOSITO = function (botao) {
  const id = botao.getAttribute('data-id');

  Swal.fire({
    title: 'Deletar depósito?',
    text: 'ATENÇÃO: todas as entradas vinculadas a este depósito serão excluídas. Essa ação não poderá ser desfeita.',
    icon: 'warning',
    showCancelButton: true,
    confirmButtonText: 'Sim, deletar',
    cancelButtonText: 'Cancelar'
  }).then((result) => {
    if (result.isConfirmed) {

      const formData = new FormData();
      formData.append('id', id);

      fetch('includes/almox_depositos/deletar_deposito.php', {
        method: 'POST',
        body: formData
      })
      .then(res => res.json())
      .then(data => {
        if (data.success) {
          Swal.fire({
            icon: 'success',
            title: 'Deletado!',
            text: 'Depósito excluído com sucesso.',
            timer: 1500,
            showConfirmButton: false
          }).then(() => {
            carregarPagina('includes/almox_depositos/listagem.php');
          });
        } else {
          Swal.fire({
            icon: 'error',
            title: 'Erro',
            text: data.message || 'Erro ao deletar depósito.'
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
};

// ===============================
// VINCULA EVENTO AOS BOTÕES
// ===============================
document.querySelectorAll('.btn-deletar-deposito').forEach(botao => {
  botao.addEventListener('click', function () {
    deletarDEPOSITO(this);
  });
});


    
    
};


// SCRIPT DO ESTOQUE
window.inicializarAlmoxEstoque = function () {
    // Alternar exibição dos filtros
// Toggle dos filtros de estoque
window.toggleFiltrosEstoque = function () {
  const container = document.getElementById('filtros-container-estoque');
  if (container) {
    container.style.display = container.style.display === 'none' ? 'block' : 'none';
  }
};

// Formulário de filtros de estoque
const formESTOQUE = document.getElementById('filtroEstoqueForm');

// Função para atualizar a listagem de estoque
function atualizarListaESTOQUE(extraParams = {}) {
  if (!formESTOQUE) return;

  const formData = new FormData(formESTOQUE);
  const params = new URLSearchParams(formData);

  // Mesclar parâmetros extras (página, limite, etc.)
  for (const key in extraParams) {
    params.set(key, extraParams[key]);
  }

  const url = `includes/almox_estoque/listagem.php?${params.toString()}`;
  carregarPagina(url); // mesma função usada nas outras listagens
}

// Evento ao enviar o formulário de filtros
if (formESTOQUE) {
  formESTOQUE.addEventListener('submit', function (e) {
    e.preventDefault();
    atualizarListaESTOQUE({ pagina: 1 }); // volta pra página 1 ao aplicar filtros
  });
}

// Controle do limite de itens por página
const limiteSelectESTOQUE = document.getElementById('limiteEstoqueAlmox');
if (limiteSelectESTOQUE) {
  limiteSelectESTOQUE.addEventListener('change', function () {
    atualizarListaESTOQUE({ pagina: 1, limite: this.value });
  });
}

// Botão de limpar filtros
const btnLimparFiltrosESTOQUE = document.getElementById('btnLimparFiltrosEstoqueAlmox');
if (btnLimparFiltrosESTOQUE && formESTOQUE) {
  btnLimparFiltrosESTOQUE.addEventListener('click', function (e) {
    e.preventDefault();
    formESTOQUE.reset();

    // Mantém o limite atual ou 10 por padrão
    const limiteAtual = document.getElementById('limiteEstoqueAlmox')?.value || 10;
    const url = `includes/almox_estoque/listagem.php?pagina=1&limite=${limiteAtual}`;
    carregarPagina(url);
  });
}


    
};


// SCRIPT DAS ENTRADAS
window.inicializarAlmoxEntradas = function () {
    
// DELETAR ENTRADA
    
    window.deletarEntradaAlmox = function(botao) {
  const id = botao.getAttribute('data-id');

  Swal.fire({
    title: 'Deletar Entrada?',
    text: "Essa ação não poderá ser desfeita. Deseja realmente excluir esta Entrada e todos os produtos relacionados?",
    icon: 'warning',
    showCancelButton: true,
    confirmButtonText: 'Sim, deletar',
    cancelButtonText: 'Cancelar'
  }).then((result) => {
    if (result.isConfirmed) {
      const formData = new FormData();
      formData.append('id', id);

      fetch('includes/almox_entrada/deletar_entrada.php', {
        method: 'POST',
        body: formData
      })
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          Swal.fire({
            icon: 'success',
            title: 'Deletado!',
            text: 'Entrada excluída com sucesso.',
            timer: 1500,
            showConfirmButton: false
          }).then(() => {
            carregarPagina('includes/almox_entrada/listagem.php');
          });
        } else {
          Swal.fire({
            icon: 'error',
            title: 'Erro',
            text: data.message || 'Erro ao deletar Entrada.'
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

// Delegar o evento para todos os botões com a classe deletar-entrada
document.querySelectorAll('.btn-deletar-entrada').forEach(botao => {
  botao.addEventListener('click', function() {
    deletarEntradaAlmox(this);
  });
});

    
// FORMULÁRIO DE EDIÇÃO DA ENTRADA
const formEditarEntrada = document.getElementById('formEditarEntradaAlmox');
if (formEditarEntrada) {
  formEditarEntrada.addEventListener('submit', function (e) {
    e.preventDefault();
    const formData = new FormData(formEditarEntrada);

    fetch('includes/almox_entrada/salvar_editar_entrada.php', {
      method: 'POST',
      body: formData
    })
      .then(res => res.text())
      .then(resp => {
        if (resp === 'ok') {
          swal("Sucesso!", "Entrada atualizada com sucesso!", "success")
            .then(() => {
              carregarPagina('includes/almox_entrada/listagem.php');
              fecharModalAberto();
            });
        } else {
          swal("Erro!", resp, "error");
        }
      });
  });
}

let contadorProdutosEntradaEditar = 0;

// Função para adicionar item no formulário de edição
window.adicionarProdutoEntradaEditar = function (item = {}) {
  contadorProdutosEntradaEditar++;
  const container = document.getElementById('containerProdutosEntradaEdit');

  const itemDiv = document.createElement('div');
  itemDiv.classList.add('border', 'rounded', 'p-3', 'mb-2', 'bg-light');

  itemDiv.innerHTML = `
    <h6 class="fw-bold">Produto ${contadorProdutosEntradaEditar}</h6>
    <div class="row g-2">
      <div class="col-md-6">
        <label class="form-label">Produto</label>
        <select class="form-select produto-select" name="produtos[${contadorProdutosEntradaEditar}][id_produto]" required>
          ${window.selectProdutosTemplate}
        </select>
      </div>
      <div class="col-md-2">
        <label class="form-label">Qtd</label>
        <input type="number" step="any" class="form-control quant-produto" name="produtos[${contadorProdutosEntradaEditar}][quant]" value="${item.quant || ''}" required>
      </div>
      <div class="col-md-2">
        <label class="form-label">Valor Unit.</label>
        <input type="number" step="any" class="form-control valor-unit" name="produtos[${contadorProdutosEntradaEditar}][valor_unt]" value="${item.valor_unt || ''}" required>
      </div>
      <div class="col-md-2">
        <label class="form-label">Valor Total</label>
        <input type="number" step="any" class="form-control valor-total" name="produtos[${contadorProdutosEntradaEditar}][valor_total]" value="${item.valor_total || ''}" readonly>
      </div>
      <div class="col-md-4">
        <label class="form-label">Marca</label>
        <input type="text" class="form-control" name="produtos[${contadorProdutosEntradaEditar}][marca]" value="${item.marca || ''}">
      </div>
      <div class="col-md-4">
        <label class="form-label">Modelo</label>
        <input type="text" class="form-control" name="produtos[${contadorProdutosEntradaEditar}][modelo]" value="${item.modelo || ''}">
      </div>
    </div>
    <button type="button" class="btn btn-danger btn-sm mt-2" onclick="this.parentElement.remove(); atualizarResumoTotalEditar()">Remover produto</button>
  `;

  container.appendChild(itemDiv);

  const select = itemDiv.querySelector('.produto-select');
  const inputQuant = itemDiv.querySelector('.quant-produto');
  const inputUnit = itemDiv.querySelector('.valor-unit');
  const inputTotal = itemDiv.querySelector('.valor-total');

  function atualizarCampos() {
    const quant = parseFloat(inputQuant.value) || 0;
    const valorUnit = parseFloat(inputUnit.value) || 0;
    inputTotal.value = (quant * valorUnit).toFixed(2);
    atualizarResumoTotalEditar();
  }

  inputQuant.addEventListener('input', atualizarCampos);
  inputUnit.addEventListener('input', atualizarCampos);

  if (item.id_produto) {
    select.value = item.id_produto; // Seleciona o produto da entrada
    atualizarCampos();
  }
};

// Atualizar o resumo do valor total
function atualizarResumoTotalEditar() {
  let total = 0;
  document.querySelectorAll('#containerProdutosEntradaEdit .valor-total').forEach(input => {
    total += parseFloat(input.value) || 0;
  });

  const resumoDiv = document.getElementById('resumoTotalEntradaEdit');
  const valorSpan = document.getElementById('valorTotalEntradaEdit');

  if (total > 0) {
    resumoDiv.style.display = 'block';
    valorSpan.textContent = total.toFixed(2).replace('.', ',');
  } else {
    resumoDiv.style.display = 'none';
  }
}

// Função principal para carregar os dados da entrada no modal
window.editarEntradaAlmox = function (id) {
  document.querySelector('#modalEditarEntradaAlmox .modal-title').textContent = `Editando Entrada #${id}`;
  const form = document.getElementById('formEditarEntradaAlmox');
  if (!form) return;

  // Limpa container e contador
  const container = document.getElementById('containerProdutosEntradaEdit');
  container.innerHTML = '';
  contadorProdutosEntradaEditar = 0;

  // 1) Busca dados da entrada
  fetch(`includes/almox_entrada/buscar_entrada.php?id=${id}`)
    .then(res => res.json())
    .then(dados => {
      if (!dados.sucesso) {
        alert('Erro ao buscar dados da entrada.');
        return;
      }

      // Preenche os campos da entrada
      form.querySelector('#idEntradaAlmox').value = id;
      // Converte a data para o formato YYYY-MM-DD
if (dados.entrada.data_entrada) {
  const data = new Date(dados.entrada.data_entrada);
  const ano = data.getFullYear();
  const mes = String(data.getMonth() + 1).padStart(2, '0');
  const dia = String(data.getDate()).padStart(2, '0');
  form.querySelector('#dataEntradaEdit').value = `${ano}-${mes}-${dia}`;
} else {
  form.querySelector('#dataEntradaEdit').value = '';
}

      form.querySelector('#notaEmpenhoEdit').value = dados.entrada.nota_empenho || '';
      form.querySelector('#nome_batalhao_edit').value = dados.entrada.nome_batalhao || '';
      form.querySelector('#notaFiscalEdit').value = dados.entrada.nota_fiscal || '';
    form.querySelector('#depositoEntradaEdit').value = dados.entrada.deposito_id || '';
      form.querySelector('#nomeFornecedorEdit').value = dados.entrada.nome_fornecedor || '';
      form.querySelector('#cnpjFornecedorEdit').value = dados.entrada.cnpj_fornecedor || '';

      // 2) Busca todos os produtos do almoxarifado
      fetch('includes/almox_entrada/buscar_todos_produtos.php')
        .then(res => res.json())
        .then(todos => {
          if (!todos.sucesso) {
            alert('Erro ao carregar produtos do almoxarifado.');
            return;
          }

          // Cria template do select
        window.selectProdutosTemplate = todos.produtos.map(p =>
  `<option value="${p.id}">
     ${p.nome_produto} (${p.categoria_produto}) - ${p.abreviatura_batalhao}
   </option>`
).join('');

          // 3) Agora busca os produtos já cadastrados na entrada
          fetch(`includes/almox_entrada/buscar_produtos_entrada.php?id=${id}`)
            .then(res => res.json())
            .then(produtosEntrada => {
              if (!produtosEntrada.sucesso) {
                alert('Erro ao carregar produtos da entrada.');
                return;
              }

              // Adiciona cada produto da entrada no formulário
              produtosEntrada.produtos.forEach(item => {
                adicionarProdutoEntradaEditar({
                  id_produto: item.id_produto,
                  quant: item.quant,
                  valor_unt: item.valor_unt,
                  valor_total: item.valor_total,
                  marca: item.marca,
                  modelo: item.modelo
                });
              });
            });
        });
    });
};

// Botão adicionar produto no editar
const btnAddProdutoEdit = document.getElementById('btnAddProdutoExistenteEdit');
if (btnAddProdutoEdit) {
  btnAddProdutoEdit.addEventListener('click', function () {
    adicionarProdutoEntradaEditar();
  });
}


    
    
  // Alternar exibição dos filtros
  window.toggleFiltrosEntradas = function () {
    const container = document.getElementById('filtros-container-entradas');
    if (container) {
      container.style.display = container.style.display === 'none' ? 'block' : 'none';
    }
  };

  const formENTRADAS = document.getElementById('filtroEntradasForm');

  // Função para atualizar a listagem
  function atualizarListaENTRADAS(extraParams = {}) {
    if (!formENTRADAS) return;

    const formData = new FormData(formENTRADAS);
    const params = new URLSearchParams(formData);

    for (const key in extraParams) {
      params.set(key, extraParams[key]);
    }

    const url = `includes/almox_entrada/listagem.php?${params.toString()}`;
    carregarPagina(url);
  }

  // Evento ao enviar o formulário
  if (formENTRADAS) {
    formENTRADAS.addEventListener('submit', function (e) {
      e.preventDefault();
      atualizarListaENTRADAS({ pagina: 1 });
    });
  }

  // Mudança de limite (se houver um select de limite implementado futuramente)
  const limiteSelectENTRADAS = document.getElementById('limiteEntradas');
  if (limiteSelectENTRADAS) {
    limiteSelectENTRADAS.addEventListener('change', function () {
      atualizarListaENTRADAS({ pagina: 1, limite: this.value });
    });
  }

  // Botão de limpar filtros
  const btnLimparFiltrosENTRADAS = document.getElementById('btnLimparFiltrosEntradas');
  if (btnLimparFiltrosENTRADAS && formENTRADAS) {
    btnLimparFiltrosENTRADAS.addEventListener('click', function (e) {
      e.preventDefault();
      formENTRADAS.reset();

      const limiteAtual = document.getElementById('limiteEntradas')?.value || 10;
      const url = `includes/almox_entrada/listagem.php?pagina=1&limite=${limiteAtual}`;
      carregarPagina(url);
    });
  }
     
    
};
  



// SCRIPT DA LISTAGEM E CADASTRO DOS PRODUTOS
window.inicializarAlmoxProdutos = function () {
   // TOGGLEFILTRO PRODUTOS
window.toggleFiltrosALMOX = function () {
  const container = document.getElementById('filtros-container-almox');
  if (container) {
    container.style.display = container.style.display === 'none' ? 'block' : 'none';
  }
};

// FORMULÁRIO DE FILTRO DOS PRODUTOS
const formALMOX = document.getElementById('filtroAlmoxForm');

function atualizarListaALMOX(extraParams = {}) {
  if (!formALMOX) return;

  const formData = new FormData(formALMOX);
  const params = new URLSearchParams(formData);

  for (const key in extraParams) {
    params.set(key, extraParams[key]);
  }

  const url = `includes/almox_produtos/listagem.php?${params.toString()}`;
  carregarPagina(url); // função AJAX existente no sistema
}

// Submissão do formulário
if (formALMOX) {
  formALMOX.addEventListener('submit', function (e) {
    e.preventDefault();
    atualizarListaALMOX({ pagina: 1 });
  });
}

// Mudança de limite por página
const limiteSelectALMOX = document.getElementById('limiteAlmox');
if (limiteSelectALMOX) {
  limiteSelectALMOX.addEventListener('change', function () {
    atualizarListaALMOX({ pagina: 1, limite: this.value });
  });
}

// Botão "Limpar Filtros"
const btnLimparFiltrosAlmox = document.getElementById('btnLimparFiltrosAlmox');
if (btnLimparFiltrosAlmox && formALMOX) {
  btnLimparFiltrosAlmox.addEventListener('click', function (e) {
    e.preventDefault();
    formALMOX.reset();

    const limiteAtual = document.getElementById('limiteAlmox')?.value || 10;
    const url = `includes/almox_produtos/listagem.php?pagina=1&limite=${limiteAtual}`;
    carregarPagina(url); // AJAX sem filtros
  });
}

// Cadastrar produtos

const formCadastrarProdutos = document.getElementById('form-cadastrar-produtos');
if (formCadastrarProdutos) {
  formCadastrarProdutos.addEventListener('submit', function (e) {
    e.preventDefault();

    const formData = new FormData(formCadastrarProdutos);

    fetch('includes/almox_produtos/cadastrar_produtos.php', {
      method: 'POST',
      body: formData
    })
      .then(res => res.json())
      .then(data => {
        if (data.status === 'sucesso') {
          swal("Sucesso!", data.mensagem, "success").then(() => {
            carregarPagina('includes/almox_produtos/listagem.php');
            fecharModalAberto();
          });
        } else {
          swal("Erro!", data.mensagem, "error");
        }
      })
      .catch(err => {
        swal("Erro!", "Erro de rede: " + err.message, "error");
      });
  });
}

window.editarProdutoAlmox = function (id) {
  const form = document.getElementById('form-editar-produto');
  if (!form) return;

  fetch(`includes/almox_produtos/buscar_produto_editar.php?id=${id}`)
    .then(res => res.json())
    .then(dados => {
      if (!dados.sucesso) {
        alert(dados.mensagem || 'Erro ao buscar dados do produto.');
        return;
      }

      const produto = dados.produto;

      // ===============================
      // Preenche os campos da entrada
      // ===============================
      form.querySelector('#edit-id-produto').value = id;

      // Converte a data para formato YYYY-MM-DD
      if (produto.data_inclusao) {
        const data = new Date(produto.data_inclusao);
        const ano = data.getFullYear();
        const mes = String(data.getMonth() + 1).padStart(2, '0');
        const dia = String(data.getDate()).padStart(2, '0');
        form.querySelector('#data_inclusao_edit').value = `${ano}-${mes}-${dia}`;
      } else {
        form.querySelector('#data_inclusao_edit').value = '';
      }

      // ===============================
      // Preenche os campos do produto
      // ===============================
      form.querySelector('[name="id"]').value = id;
      form.querySelector('[name="nome_produto"]').value = produto.nome_produto || '';
      form.querySelector('[name="codigo_produto"]').value = produto.codigo_produto || '';
      form.querySelector('[name="categoria_produto"]').value = produto.categoria_produto || '';
      form.querySelector('[name="obs_produto"]').value = produto.obs_produto || '';
      form.querySelector('[name="estoque_minimo"]').value = produto.estoque_minimo || '';
      form.querySelector('[name="unidade"]').value = produto.unidade || '';

      // ===============================
      // Preenche o batalhão (ID, nome e abreviatura)
      // ===============================
      if (form.querySelector('[name="batalhao"]')) {
        form.querySelector('[name="batalhao"]').value = produto.batalhao || '';
      }

      if (form.querySelector('#nome_batalhao_edit')) {
        form.querySelector('#nome_batalhao_edit').value = produto.nome_batalhao || '';
      }

      if (form.querySelector('#abreviatura_batalhao_edit')) {
        form.querySelector('#abreviatura_batalhao_edit').value = produto.abreviatura_batalhao || '';
      }
    })
    .catch(err => {
      console.error('Erro ao buscar dados do produto:', err);
      alert('Erro ao carregar dados do produto.');
    });
};


const formEditarProduto = document.getElementById('form-editar-produto');

if (formEditarProduto) {
  formEditarProduto.addEventListener('submit', function (e) {
    e.preventDefault();

    const formData = new FormData(formEditarProduto);

    fetch('includes/almox_produtos/editar_produto.php', {
      method: 'POST',
      body: formData
    })
    .then(res => res.json())
    .then(res => {
      if (res.status === 'sucesso') {
        swal('Sucesso', res.mensagem, 'success')
          .then(() => {
            carregarPagina('includes/almox_produtos/listagem.php');
            bootstrap.Modal.getInstance(document.getElementById('modalEditarProduto')).hide();
          });
      } else {
        swal('Erro', res.mensagem || 'Falha ao atualizar produto.', 'error');
      }
    })
    .catch(err => {
      swal('Erro de rede', err.message, 'error');
    });
  });
}


// DELETAR PRODUTO
window.deletarPRODUTO = function(botao) {
  const id = botao.getAttribute('data-id');

  Swal.fire({
    title: 'Deletar produto?',
    text: "Essa ação não poderá ser desfeita. Deseja realmente excluir este produto?",
    icon: 'warning',
    showCancelButton: true,
    confirmButtonText: 'Sim, deletar',
    cancelButtonText: 'Cancelar'
  }).then((result) => {
    if (result.isConfirmed) {
      const formData = new FormData();
      formData.append('id', id);

      fetch('includes/almox_produtos/deletar_produto.php', {
        method: 'POST',
        body: formData
      })
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          Swal.fire({
            icon: 'success',
            title: 'Deletado!',
            text: 'Produto excluído com sucesso.',
            timer: 1500,
            showConfirmButton: false
          }).then(() => {
            carregarPagina('includes/almox_produtos/listagem.php');
          });
        } else {
          Swal.fire({
            icon: 'error',
            title: 'Erro',
            text: data.message || 'Erro ao deletar produto.'
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
};

// Adiciona evento aos botões
document.querySelectorAll('.btn-deletar-produto').forEach(botao => {
  botao.addEventListener('click', function () {
    deletarPRODUTO(this);
  });
});



    
};

// SCRIPT PARA CADASTRO DE ENTRADA DO ALMOXARIFADO
window.inicializarCadastroEntradaAlmox = function () {
  let contadorItens = 0;
  const container = document.getElementById("containerProdutosEntrada");
  const btnAddProdutoExistente = document.getElementById("btnAddProdutoExistente");

  btnAddProdutoExistente.addEventListener("click", () => {
    adicionarProdutoExistente();
    atualizarValorTotalEntrada();
  });

  // Carregar produtos no select
  async function carregarProdutos(selectElement) {
    try {
      const res = await fetch('includes/almox_produtos/buscar_produto.php');
      const produtos = await res.json();

      selectElement.innerHTML = '<option value="">Selecione um produto</option>';

      produtos.forEach(p => {
        const option = document.createElement('option');
        option.value = p.id;
        option.textContent = `${p.nome} - ${p.codigo} - ${p.batalhao_nome}`;
        option.dataset.unidade = p.unidade; 
        selectElement.appendChild(option);
      });
    } catch (err) {
      console.error('Erro ao carregar produtos:', err);
    }
  }

  // Adicionar linha de produto existente
  function adicionarProdutoExistente() {
    contadorItens++;
    const linha = document.createElement("div");
    linha.className = "linha-produto border p-3 rounded mb-3 bg-light";

    linha.innerHTML = `
  <h6 class="fw-bold mb-3">Produto ${contadorItens}</h6>
  <div class="row g-3 align-items-end">
    
    <div class="col-lg-4 col-md-6">
      <label class="form-label">Produto</label>
      <select class="form-select form-select-lg select-produto" name="itens[${contadorItens}][id_produto]" required></select>
    </div>

    <div class="col-lg-2 col-md-4">
      <label class="form-label">Marca</label>
      <input type="text" class="form-control form-control-lg" name="itens[${contadorItens}][marca]">
    </div>

    <div class="col-lg-2 col-md-4">
      <label class="form-label">Modelo</label>
      <input type="text" class="form-control form-control-lg" name="itens[${contadorItens}][modelo]">
    </div>

    <div class="col-lg-2 col-md-4">
      <label class="form-label">Qtd</label>
      <input type="number" class="form-control form-control-lg input-quantidade" name="itens[${contadorItens}][quant]" required>
    </div>

    <div class="col-lg-2 col-md-4">
      <label class="form-label">Unid.</label>
      <input type="text" class="form-control form-control-lg input-unidade" name="itens[${contadorItens}][unidade]" readonly>
    </div>

    <div class="col-lg-4 col-md-4">
      <label class="form-label">Valor Unit.</label>
      <div class="input-group input-group-lg">
        <span class="input-group-text">R$</span>
        <input type="number" step="0.01" class="form-control input-valorunit" name="itens[${contadorItens}][valor_unt]" required>
      </div>
    </div>

    <div class="col-lg-4 col-md-4">
      <label class="form-label">Valor Total</label>
      <div class="input-group input-group-lg">
        <span class="input-group-text">R$</span>
        <input type="number" step="0.01" class="form-control input-valortotal" name="itens[${contadorItens}][valor_total]" readonly>
      </div>
    </div>
  </div>

  <div class="text-end mt-3">
    <button type="button" class="btn btn-danger btn-sm" onclick="this.closest('.linha-produto').remove(); atualizarValorTotalEntrada();">
      Remover
    </button>
  </div>
`;


    container.appendChild(linha);
    carregarProdutos(linha.querySelector(".select-produto"));
  }

  // Calcular valor total de cada linha
  container.addEventListener("input", function (e) {
    const linha = e.target.closest(".linha-produto");
    if (!linha) return;

    const quant = parseFloat(linha.querySelector(".input-quantidade")?.value.replace(',', '.') || 0);
    const valorUnt = parseFloat(linha.querySelector(".input-valorunit")?.value.replace(',', '.') || 0);
    const totalInput = linha.querySelector(".input-valortotal");
    if (totalInput) totalInput.value = (quant * valorUnt).toFixed(2);
    atualizarValorTotalEntrada();
  });

  // Preencher unidade ao selecionar produto
  container.addEventListener("change", function (e) {
    if (e.target.classList.contains("select-produto")) {
      const selected = e.target.selectedOptions[0];
      const unidade = selected?.dataset.unidade;
      const linha = e.target.closest(".linha-produto");
      if (unidade && linha) {
        linha.querySelector(".input-unidade").value = unidade;
      }
    }
  });

  // Atualizar valor total geral
  function atualizarValorTotalEntrada() {
    let total = 0;
    container.querySelectorAll(".input-valortotal").forEach(input => {
      const valor = parseFloat(input.value.replace(',', '.') || 0);
      if (!isNaN(valor)) total += valor;
    });

    const totalDisplay = document.getElementById("valorTotalEntrada");
    const boxResumo = document.getElementById("resumoTotalEntrada");

    if (totalDisplay && boxResumo) {
      totalDisplay.textContent = total.toLocaleString("pt-BR", {
        style: "currency",
        currency: "BRL"
      }).replace("R$", "").trim();

      boxResumo.style.display = total > 0 ? "block" : "none";
    }
  }

  // Submit do formulário
  const form = document.getElementById("formCadastroEntradaAlmox");
  if (form) {
    form.addEventListener("submit", function (e) {
      e.preventDefault();
      const formData = new FormData(form);

      fetch("includes/almox_entrada/cadastrar_entrada.php", {
        method: "POST",
        body: formData
      })
        .then(res => res.text())
        .then(resp => {
          if (resp === "ok") {
            swal("Sucesso!", "Entrada cadastrada com sucesso!", "success")
              .then(() => {
                carregarPagina("includes/almox_entrada/listagem.php");
                fecharModalAberto();
              });
          } else {
            swal("Erro!", resp, "error");
          }
        });
    });
  }
};




