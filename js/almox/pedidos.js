// JavaScript Document

// SCRIPT DOS PEDIDOS
window.inicializarAlmoxPedidos = function () {  
// ALTERAR AUTORIZAÇÃO DO PEDIDO

window.toggleAutorizacaoPedidoAlmox = function(botao) {
  const id = botao.getAttribute('data-id');
  const autorizacaoAtual = botao.getAttribute('data-autorizacao');

  const novaAutorizacao = autorizacaoAtual === 'sim' ? 'nao' : 'sim';

  Swal.fire({
    title: 'Alterar autorização?',
    text: novaAutorizacao === 'sim'
      ? 'Deseja AUTORIZAR este pedido?'
      : 'Deseja marcar este pedido como NÃO AUTORIZADO?',
    icon: 'question',
    showCancelButton: true,
    confirmButtonText: 'Sim, confirmar',
    cancelButtonText: 'Cancelar'
  }).then((result) => {
    if (result.isConfirmed) {
      const formData = new FormData();
      formData.append('id', id);
      formData.append('autorizacao', novaAutorizacao);

      fetch('includes/almox_pedidos/alterar_autorizacao.php', {
        method: 'POST',
        body: formData
      })
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          Swal.fire({
            icon: 'success',
            title: 'Atualizado!',
            text: 'Autorização atualizada com sucesso.',
            timer: 1500,
            showConfirmButton: false
          }).then(() => {
            carregarPagina('includes/almox_pedidos/listagem.php');
          });
        } else {
          Swal.fire({
            icon: 'error',
            title: 'Erro',
            text: data.message || 'Erro ao alterar autorização.'
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

// Delegação dos botões
document.querySelectorAll('.btn-toggle-autorizacao-almox').forEach(botao => {
  botao.addEventListener('click', function() {
    toggleAutorizacaoPedidoAlmox(this);
  });
});    
    
// =====================================================
// CONTROLE GLOBAL DA ABA PDF (REALMENTE GLOBAL)
// =====================================================
if (!window.__pedidoAlmoxPDFWindow) {
  window.__pedidoAlmoxPDFWindow = null;
}

// =====================================================
// HANDLER ÚNICO
// =====================================================
function handleExportPDFPedidoAlmox(e) {
  const btn = e.target.closest('.btnExportarPDFpedido');
  if (!btn) return;

  e.preventDefault();
  e.stopPropagation();

  const pedidoId = btn.dataset.id;
  if (!pedidoId) {
    alert('ID do pedido inválido.');
    return;
  }

  // 👉 ABRE A TELA DE LOADING (NÃO O PDF DIRETO)
  const url = `pdf/loading_pedido_almox.php?id=${pedidoId}`;

  // =====================================================
  // SE A ABA JÁ EXISTE → REUTILIZA
  // =====================================================
  if (window.__pedidoAlmoxPDFWindow && !window.__pedidoAlmoxPDFWindow.closed) {
    window.__pedidoAlmoxPDFWindow.focus();
    window.__pedidoAlmoxPDFWindow.location.href = url;
    return;
  }

  // =====================================================
  // ABERTURA DIRETA
  // =====================================================
  window.__pedidoAlmoxPDFWindow = window.open(
    url,
    'pedido_almox_pdf_window' // nome fixo
  );

  if (!window.__pedidoAlmoxPDFWindow) {
    alert('Permita pop-ups para visualizar o PDF.');
    return;
  }

  window.__pedidoAlmoxPDFWindow.focus();
}

// =====================================================
// LISTENER GLOBAL (NÃO DUPLICA)
// =====================================================
document.addEventListener('click', handleExportPDFPedidoAlmox);




// ------------------------
// CONTROLE GLOBAL // CADASTRAR PEDIDO
// ------------------------
let contadorItensPedidoAlmox = 0;
let depositoPedidoAlmox = null;

// ------------------------
// SOMA TOTAL
// ------------------------
function atualizarSomaTotalAlmox() {
  const container = document.getElementById('itensPedidoAlmoxContainer');
  const somaElement = document.getElementById('somaTotalItensAlmox');
  if (!container || !somaElement) return;

  let soma = 0;
  container.querySelectorAll('.valor-total').forEach(input => {
    soma += parseFloat(input.value) || 0;
  });

  if (soma > 0) {
    somaElement.textContent = 'Valor total dos itens: R$ ' + soma.toFixed(2).replace('.', ',');
    somaElement.classList.remove('text-muted');
  } else {
    somaElement.textContent = 'Não há itens adicionados.';
    somaElement.classList.add('text-muted');
  }
}

// ------------------------
// TRAVAR / LIBERAR DEPÓSITOS (GLOBAL)
// ------------------------
function travarDepositosPedido(depositoId) {
  document.querySelectorAll('.select-produto option').forEach(opt => {
    if (!opt.dataset.deposito) return;
    opt.disabled = opt.dataset.deposito !== depositoId;
  });
}

function liberarDepositosPedido() {
  depositoPedidoAlmox = null;
  document.querySelectorAll('.select-produto option').forEach(opt => {
    opt.disabled = false;
  });
}

// ------------------------
// REMOVER ITEM
// ------------------------
window.removerItemPedidoAlmox = function (btn) {

  const item = btn.closest('.item-pedido-almox');
  if (!item) return;

  item.remove();

  const itensRestantes = document.querySelectorAll('.item-pedido-almox').length;

  if (itensRestantes === 0) {
    liberarDepositosPedido();
  }

  atualizarSomaTotalAlmox();
};


// ------------------------
// ADICIONAR ITEM
// ------------------------
window.adicionarItemPedidoAlmox = function () {
  contadorItensPedidoAlmox++;

  const container = document.getElementById('itensPedidoAlmoxContainer');

  const itemDiv = document.createElement('div');
  itemDiv.className = 'item-pedido-almox border rounded p-3 mb-3 bg-light';

  itemDiv.innerHTML = `
    <h6 class="fw-bold">Item ${contadorItensPedidoAlmox}</h6>

    <div class="row g-2">
      <div class="col-md-6">
        <label class="form-label">Produto / Entrada</label>
        <select class="form-select select-produto"
          name="itens[${contadorItensPedidoAlmox}][id_entrada_produto]" required>
          <option value="" disabled selected>Carregando produtos...</option>
        </select>
      </div>

      <div class="col-md-2">
        <label class="form-label">Qtd. Solicitada</label>
        <input type="number" class="form-control qtd-solicitada"
          name="itens[${contadorItensPedidoAlmox}][quant_solicitada]"
          step="any" min="0" required>
      </div>

      <div class="col-md-2">
        <label class="form-label">Valor Unitário</label>
        <input type="number" class="form-control valor-unit"
          readonly>
      </div>

      <div class="col-md-2">
        <label class="form-label">Valor Total</label>
        <input type="number" class="form-control valor-total"
          readonly>
      </div>
    </div>

    <div class="text-end mt-2">
      <button type="button" class="btn btn-danger btn-sm"
        onclick="removerItemPedidoAlmox(this)">
        Remover Item
      </button>
    </div>
  `;

  container.appendChild(itemDiv);

  const selectProduto = itemDiv.querySelector('.select-produto');
  const qtd = itemDiv.querySelector('.qtd-solicitada');
  const unit = itemDiv.querySelector('.valor-unit');
  const total = itemDiv.querySelector('.valor-total');

  // ------------------------
  // BUSCAR PRODUTOS
  // ------------------------
  fetch('includes/almox_pedidos/buscar_produtos_entradas.php')
    .then(res => res.json())
    .then(data => {

      selectProduto.innerHTML =
        '<option value="" disabled selected>Selecione o produto</option>';

      const grupos = {};

      data.forEach(item => {
        if (!grupos[item.deposito_id]) {
          grupos[item.deposito_id] = {
            nome: item.nome_deposito,
            itens: []
          };
        }
        grupos[item.deposito_id].itens.push(item);
      });

      Object.entries(grupos).forEach(([depositoId, grupo]) => {
        const optgroup = document.createElement('optgroup');
        optgroup.label = grupo.nome;

        grupo.itens.forEach(item => {
          const option = document.createElement('option');

          option.value = `${item.id_entrada}|${item.id_produto}`;
          option.dataset.valor = item.valor_unt;
          option.dataset.deposito = item.deposito_id;

          option.textContent =
            `${item.nome_produto} | Saldo: ${item.saldo} | R$ ${parseFloat(item.valor_unt).toFixed(2)}`;

          // 🔒 trava se já houver depósito definido
          if (depositoPedidoAlmox && item.deposito_id != depositoPedidoAlmox) {
            option.disabled = true;
          }

          optgroup.appendChild(option);
        });

        selectProduto.appendChild(optgroup);
      });
    });

  // ------------------------
  // EVENTOS
  // ------------------------
  selectProduto.addEventListener('change', function () {
    const opt = this.selectedOptions[0];
    if (!opt) return;

    const depositoAtual = opt.dataset.deposito;

    // define e trava o depósito global no primeiro item
    if (!depositoPedidoAlmox) {
      depositoPedidoAlmox = depositoAtual;
      travarDepositosPedido(depositoPedidoAlmox);
    }

    unit.value = parseFloat(opt.dataset.valor || 0).toFixed(2);
    calcularTotal();
  });

  function calcularTotal() {
    const q = parseFloat(qtd.value) || 0;
    const u = parseFloat(unit.value) || 0;
    total.value = (q * u).toFixed(2);
    atualizarSomaTotalAlmox();
  }

  qtd.addEventListener('input', calcularTotal);
};

// ------------------------
// SUBMIT DO FORMULÁRIO
// ------------------------
const formCadastrarPedidoAlmox = document.getElementById('form-pedido-almox-cadastrar');

if (formCadastrarPedidoAlmox) {
  formCadastrarPedidoAlmox.addEventListener('submit', function (e) {
    e.preventDefault();

    const formData = new FormData(this);

    fetch('includes/almox_pedidos/cadastrar_pedido.php', {
      method: 'POST',
      body: formData
    })
      .then(res => res.json())
      .then(data => {
        if (data.status === 'sucesso') {
          swal("Sucesso!", data.mensagem || "Pedido cadastrado com sucesso!", "success")
            .then(() => {
              carregarPagina('includes/almox_pedidos/listagem.php');
              fecharModalAberto();

              contadorItensPedidoAlmox = 0;
              depositoPedidoAlmox = null;
              document.getElementById('itensPedidoAlmoxContainer').innerHTML = '';
              atualizarSomaTotalAlmox();
            });
        } else {
          swal("Erro!", data.mensagem || "Erro ao cadastrar pedido.", "error");
        }
      });
  });
}

    // Alternar exibição dos filtros
window.toggleFiltrosPedidos = function () {
  const container = document.getElementById('filtros-container-pedidos');
  if (container) {
    container.style.display = container.style.display === 'none' ? 'block' : 'none';
  }
};

const formPEDIDOS = document.getElementById('filtroPedidosAlmoxForm');

// Função para atualizar a listagem
function atualizarListaPEDIDOS(extraParams = {}) {
  if (!formPEDIDOS) return;

  const formData = new FormData(formPEDIDOS);
  const params = new URLSearchParams(formData);

  for (const key in extraParams) {
    params.set(key, extraParams[key]);
  }

  const url = `includes/almox_pedidos/listagem.php?${params.toString()}`;
  carregarPagina(url);
}

// Evento ao enviar o formulário
if (formPEDIDOS) {
  formPEDIDOS.addEventListener('submit', function (e) {
    e.preventDefault();
    atualizarListaPEDIDOS({ pagina: 1 });
  });
}

// Mudança de limite (se houver um select de limite implementado futuramente)
const limiteSelectPEDIDOS = document.getElementById('limitePedidoAlmox');
if (limiteSelectPEDIDOS) {
  limiteSelectPEDIDOS.addEventListener('change', function () {
    atualizarListaPEDIDOS({ pagina: 1, limite: this.value });
  });
}

// Botão de limpar filtros
const btnLimparFiltrosPEDIDOS = document.getElementById('btnLimparFiltrosPedidosAlmox');
if (btnLimparFiltrosPEDIDOS && formPEDIDOS) {
  btnLimparFiltrosPEDIDOS.addEventListener('click', function (e) {
    e.preventDefault();
    formPEDIDOS.reset();

    const limiteAtual = document.getElementById('limitePedidoAlmox')?.value || 10;
    const url = `includes/almox_pedidos/listagem.php?pagina=1&limite=${limiteAtual}`;
    carregarPagina(url);
  });
}

// ============================
// EDIÇÃO DOS PEDIDOS - ALMOX
// ============================

let contadorItensPedidoAlmoxEditar = 0;
window.itensAlmoxData = [];

// ============================
// Atualizar total geral
// ============================
function atualizarTotaisEditar() {
  const container = document.getElementById('itensPedidoAlmoxContainerEditar');
  let soma = 0;

  container.querySelectorAll('.valor-total').forEach(input => {
    soma += parseFloat(input.value) || 0;
  });

  document.getElementById('somaTotalItensAlmoxEditar').textContent =
    `Valor total dos itens: R$ ${soma.toFixed(2)}`;
}

// ============================
// Montar select com OPTGROUP
// ============================
function montarSelectProdutos(idSelecionado = '') {
  let html = `<option value="" disabled ${idSelecionado ? '' : 'selected'}>
                Selecione o produto
              </option>`;

  const grupos = {};

  window.itensAlmoxData.forEach(item => {
    if (!grupos[item.deposito_id]) {
      grupos[item.deposito_id] = {
        nome: item.nome_deposito,
        itens: []
      };
    }
    grupos[item.deposito_id].itens.push(item);
  });

  Object.values(grupos).forEach(grupo => {
    html += `<optgroup label="${grupo.nome}">`;

    grupo.itens.forEach(item => {
      const value = `${item.id_entrada}|${item.id_produto}`;
      const selected = value === idSelecionado ? 'selected' : '';

      html += `
        <option value="${value}" ${selected}>
          ${item.nome_produto}
          | Saldo: ${item.saldo ?? 0}
          | R$ ${parseFloat(item.valor_unt).toFixed(2)}
          | Marca: ${item.marca ?? '-'}
          | Modelo: ${item.modelo ?? '-'}
          | OM: ${item.om}
        </option>
      `;
    });

    html += `</optgroup>`;
  });

  return html;
}

// ============================
// Adicionar item
// ============================
window.adicionarItemPedidoAlmoxEditar = function (item = {}) {
  contadorItensPedidoAlmoxEditar++;

  const container = document.getElementById('itensPedidoAlmoxContainerEditar');

  const valorUnit = parseFloat(item.valor_unt) || 0;
  const qtd = parseFloat(item.quant_solicitada) || 0;
  const valorTotal = (valorUnit * qtd).toFixed(2);

  const idEntradaProd = item.id_entrada && item.id_produto
    ? `${item.id_entrada}|${item.id_produto}`
    : '';

  const itemDiv = document.createElement('div');
  itemDiv.className = 'border rounded p-3 mb-2 bg-light';

  itemDiv.innerHTML = `
    <h6 class="fw-bold">Item ${contadorItensPedidoAlmoxEditar}</h6>

    <div class="mb-2">
      <label class="form-label">Produto / Entrada</label>
      <select class="form-select item-select" required>
        ${montarSelectProdutos(idEntradaProd)}
      </select>
    </div>

    <div class="mb-2">
      <label class="form-label">Quantidade</label>
      <input type="number" step="any" class="form-control quant-saida"
        value="${qtd}" required>
    </div>

    <div class="mb-2">
      <label class="form-label">Valor Unitário</label>
      <input type="number" step="any" class="form-control valor-unitario"
        value="${valorUnit}" readonly>
    </div>

    <div class="mb-2">
      <label class="form-label">Valor Total</label>
      <input type="number" step="any" class="form-control valor-total"
        value="${valorTotal}" readonly>
    </div>

    <button type="button" class="btn btn-danger btn-sm">
      Remover item
    </button>
  `;

  container.appendChild(itemDiv);

  const select = itemDiv.querySelector('.item-select');
  const inputQuant = itemDiv.querySelector('.quant-saida');
  const inputUnit = itemDiv.querySelector('.valor-unitario');
  const inputTotal = itemDiv.querySelector('.valor-total');
  const btnRemover = itemDiv.querySelector('button');

  function atualizarCampos() {
    if (!select.value) return;

    const [idEntrada, idProduto] = select.value.split('|');
    const prod = window.itensAlmoxData.find(p =>
      p.id_entrada == idEntrada && p.id_produto == idProduto
    );

    if (prod) {
      inputUnit.value = prod.valor_unt;
      const quant = parseFloat(inputQuant.value) || 0;
      inputTotal.value = (quant * parseFloat(prod.valor_unt)).toFixed(2);
      atualizarTotaisEditar();
    }
  }

  select.addEventListener('change', atualizarCampos);
  inputQuant.addEventListener('input', atualizarCampos);

  btnRemover.addEventListener('click', () => {
    itemDiv.remove();
    atualizarTotaisEditar();
  });

  atualizarTotaisEditar();
};

// ============================
// Editar pedido
// ============================
window.editarPedidoAlmox = function (id) {
  const form = document.getElementById('form-pedido-almox-editar');
  if (!form) return;

  fetch(`includes/almox_pedidos/buscar_pedido_editar.php?id=${id}`)
    .then(res => res.json())
    .then(data => {
      if (!data.sucesso) {
        swal('Erro', data.mensagem || 'Erro ao carregar pedido.', 'error');
        return;
      }

      form.reset();
      document.getElementById('itensPedidoAlmoxContainerEditar').innerHTML = '';
      contadorItensPedidoAlmoxEditar = 0;

      // ============================
      // Preenche campos principais
      // ============================
      form.querySelector('#editar-id-pedido').value = data.pedido.id;
      form.querySelector('#editar-data-pedido').value = data.pedido.data_pedido;
      form.querySelector('#editar-militar-solicitante').value = data.pedido.militar_solicitante;
      form.querySelector('#editar-id-os').value = data.pedido.id_os;
      form.querySelector('#editar-batalhao').value = data.pedido.batalhao;
      form.querySelector('#editar-secao-responsavel').value = data.pedido.secao_solicitante;
      form.querySelector('#editar-status-pedido').value = data.pedido.status_pedido;
      form.querySelector('#editar-local-pedido').value = data.pedido.local_pedido;

      // ============================
      // CONTROLE DE AUTORIZAÇÃO
      // ============================
      const autorizado = data.pedido.autorizacao === 'sim';

      const camposBloquear = [
        '#editar-data-pedido',
        '#editar-militar-solicitante',
        '#editar-id-os',
        '#editar-batalhao',
        '#editar-secao-responsavel',
        '#editar-local-pedido'
      ];

      camposBloquear.forEach(sel => {
        const el = form.querySelector(sel);
        if (el) el.disabled = autorizado;
      });

      // Status SEMPRE editável
      form.querySelector('#editar-status-pedido').disabled = false;

      // Botão adicionar item
      const btnAddItem = document.querySelector(
        '#modalEditarPedidoAlmox button[onclick="adicionarItemPedidoAlmoxEditar()"]'
      );
      if (btnAddItem) {
        btnAddItem.style.display = autorizado ? 'none' : 'inline-block';
      }

      // ============================
      // Produtos / Itens
      // ============================
      fetch(`includes/almox_pedidos/buscar_produtos_entradas.php?id_pedido=${id}`)
        .then(res => res.json())
        .then(produtos => {
          window.itensAlmoxData = produtos;

          const agrupados = {};

          data.itens.forEach(it => {
            const chave = `${it.id_produto}|${it.id_entrada}`;
            if (!agrupados[chave]) {
              agrupados[chave] = { ...it };
            } else {
              agrupados[chave].quant_solicitada += parseFloat(it.quant_solicitada);
            }
          });

          Object.values(agrupados).forEach(item =>
            adicionarItemPedidoAlmoxEditar(item)
          );

          // ============================
          // Bloqueia edição dos itens se autorizado
          // ============================
          if (autorizado) {
            document
              .querySelectorAll('#itensPedidoAlmoxContainerEditar .border')
              .forEach(div => {
                div.querySelectorAll('select, input').forEach(inp => {
                  inp.disabled = true;
                });

                const btnRemover = div.querySelector('.btn-danger');
                if (btnRemover) btnRemover.style.display = 'none';
              });
          }

          bootstrap.Modal
            .getOrCreateInstance(document.getElementById('modalEditarPedidoAlmox'))
            .show();
        });
    });
};

// ============================
// Envio do formulário
// ============================
document.getElementById('form-pedido-almox-editar')
  ?.addEventListener('submit', function (e) {
    e.preventDefault();

    const formData = new FormData(this);
    const itens = {};

    document
      .querySelectorAll('#itensPedidoAlmoxContainerEditar .item-select')
      .forEach((select, index) => {
        const [idEntrada, idProduto] = select.value.split('|');
        const quant = parseFloat(
          document.querySelectorAll('.quant-saida')[index].value
        ) || 0;

        const chave = `${idProduto}|${idEntrada}`;
        if (!itens[chave]) itens[chave] = { idProduto, idEntrada, quant };
        else itens[chave].quant += quant;
      });

    let i = 0;
    Object.values(itens).forEach(it => {
      i++;
      formData.append(`itens[${i}][id_entrada_produto]`, `${it.idEntrada}|${it.idProduto}`);
      formData.append(`itens[${i}][quant_solicitada]`, it.quant);
    });

    fetch('includes/almox_pedidos/editar_pedido.php', {
      method: 'POST',
      body: formData
    })
      .then(res => res.json())
      .then(resp => {
        if (resp.status === 'sucesso') {
          swal('Sucesso!', resp.mensagem, 'success').then(() => {
            carregarPagina('includes/almox_pedidos/listagem.php');
            fecharModalAberto();
          });
        } else {
          swal('Erro!', resp.mensagem, 'error');
        }
      })
      .catch(() => {
        swal('Erro!', 'Falha ao processar o pedido.', 'error');
      });
  });

    
    
// DELETAR PEDIDO

window.deletarPedidoAlmox = function(botao) {
  const id = botao.getAttribute('data-id');

  Swal.fire({
    title: 'Deletar Pedido?',
    text: "Essa ação não poderá ser desfeita. Deseja realmente excluir este Pedido e todos os registros relacionados?",
    icon: 'warning',
    showCancelButton: true,
    confirmButtonText: 'Sim, deletar',
    cancelButtonText: 'Cancelar'
  }).then((result) => {
    if (result.isConfirmed) {
      const formData = new FormData();
      formData.append('id', id);

      fetch('includes/almox_pedidos/deletar_pedido.php', {
        method: 'POST',
        body: formData
      })
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          Swal.fire({
            icon: 'success',
            title: 'Deletado!',
            text: 'Pedido excluído com sucesso.',
            timer: 1500,
            showConfirmButton: false
          }).then(() => {
            carregarPagina('includes/almox_pedidos/listagem.php');
          });
        } else {
          Swal.fire({
            icon: 'error',
            title: 'Erro',
            text: data.message || 'Erro ao deletar Pedido.'
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

// Delega o evento para todos os botões com a classe btn-deletar-pedido-almox
document.querySelectorAll('.btn-deletar-pedido-almox').forEach(botao => {
  botao.addEventListener('click', function() {
    deletarPedidoAlmox(this);
  });
});





};




