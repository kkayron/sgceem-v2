window.inicializarPedidosFornecedores = function () {
 // =========================================================
  // LAZY LOAD (não pode dar return aqui, senão quebra o resto)
  // =========================================================
  if (!window.__bindLazyItensPedidoFornecedor) {
    window.__bindLazyItensPedidoFornecedor = true;

    document.addEventListener('click', function (e) {
      const btn = e.target.closest('.btn-ver-itens-pedido');
      if (!btn) return;

      const pedidoId = btn.dataset.id;
      const collapseEl = document.getElementById('itensPedido' + pedidoId);
      if (!collapseEl) return;

      if (collapseEl.dataset.loaded === "1") return;

      const body = collapseEl.querySelector('.itens-body');
      if (!body) return;

      body.innerHTML = `
        <div class="d-flex align-items-center gap-2 text-muted small">
          <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>
          Carregando os itens...
        </div>
      `;

      const url = '/includes/fin_pedidos/carregar_itens_pedidos.php?id=' + encodeURIComponent(pedidoId);

      fetch(url, { method: 'GET', credentials: 'same-origin' })
        .then(async (r) => {
          const txt = await r.text();
          if (!r.ok) throw new Error(txt || ('HTTP ' + r.status));
          if (!txt || txt.trim().length === 0) throw new Error('Resposta vazia do servidor.');
          return txt;
        })
        .then(html => {
          body.innerHTML = html;
          collapseEl.dataset.loaded = "1";
        })
        .catch(err => {
          body.innerHTML = `
            <div class="alert alert-danger mb-0">
              Erro ao carregar itens.<br>
              <small>${String(err.message || err)}</small>
            </div>
          `;
        });
    });
  }

window.toggleAutorizacaoPedidoFornecedor = function(botao) {

  const id = botao.dataset.id;
  const token = botao.dataset.token;
  const statusAtual = botao.dataset.status;

  // 🔄 alterna status
  const novaAutorizacao = statusAtual === 'sim' ? 'nao' : 'sim';

  Swal.fire({
    title: 'Alterar autorização?',
    text: novaAutorizacao === '1'
      ? 'Deseja AUTORIZAR este pedido do fornecedor?'
      : 'Deseja marcar este pedido como NÃO AUTORIZADO?',
    icon: 'question',
    showCancelButton: true,
    confirmButtonText: 'Sim, confirmar',
    cancelButtonText: 'Cancelar'
  }).then((result) => {

    if (result.isConfirmed) {

      // 🔒 evita múltiplos cliques
      botao.disabled = true;

      const formData = new FormData();
      formData.append('id', id);
      formData.append('autorizacao', novaAutorizacao);
      formData.append('csrf_token', token);

      fetch('includes/fin_pedidos/alterar_autorizacao.php', {
        method: 'POST',
        headers: {
          'X-Requested-With': 'XMLHttpRequest'
        },
        body: formData
      })
      .then(response => response.json())
      .then(data => {

        botao.disabled = false;

        if (data.success) {

          // 🔄 atualiza botão sem recarregar
          botao.dataset.status = novaAutorizacao;

          if (novaAutorizacao === 'sim') {
            botao.classList.remove('btn-danger');
            botao.classList.add('btn-success');
            botao.innerHTML = '<i class="fas fa-check-circle me-1"></i> Autorizado';
          } else {
            botao.classList.remove('btn-success');
            botao.classList.add('btn-danger');
            botao.innerHTML = '<i class="fas fa-ban me-1"></i> Não autorizado';
          }

          Swal.fire({
            icon: 'success',
            title: 'Atualizado!',
            timer: 1200,
            showConfirmButton: false
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

        botao.disabled = false;

        Swal.fire({
          icon: 'error',
          title: 'Erro de rede',
          text: error.message
        });
      });
    }
  });
};

// =============================
// EVENTO (delegação segura)
// =============================
document.addEventListener('click', function(e) {
  const botao = e.target.closest('.btn-autorizar');
  if (botao) {
    toggleAutorizacaoPedidoFornecedor(botao);
  }
});
    
    
    
// =====================================================
// CONTROLE GLOBAL DA ABA PDF (ÚNICA)
// =====================================================
if (!window.__pedidoFornecedorPDFWindow) {
  window.__pedidoFornecedorPDFWindow = null;
}

// =====================================================
// HANDLER ÚNICO
// =====================================================
function handleExportPDFpedidoFornecedor(e) {
  const btn = e.target.closest('.btnExportarPDFpedidoFornecedor');
  if (!btn) return;

  e.preventDefault();
  e.stopPropagation();

  const pedidoId = btn.dataset.id;
  if (!pedidoId) {
    alert('ID do pedido inválido.');
    return;
  }

  // 👇 AQUI entra a página de loading (mantida)
  const url = `pdf/loading_pedido.php?id=${pedidoId}`;

  // =====================================================
  // SE A ABA JÁ EXISTE → REUTILIZA
  // =====================================================
  if (
    window.__pedidoFornecedorPDFWindow &&
    !window.__pedidoFornecedorPDFWindow.closed
  ) {
    window.__pedidoFornecedorPDFWindow.focus();
    window.__pedidoFornecedorPDFWindow.location.href = url;
    return;
  }

  // =====================================================
  // ABERTURA ÚNICA (nome fixo)
  // =====================================================
  window.__pedidoFornecedorPDFWindow = window.open(
    url,
    'pedido_fornecedor_pdf_window' // ⚠️ nome fixo
  );

  if (!window.__pedidoFornecedorPDFWindow) {
    alert('Permita pop-ups para visualizar o PDF.');
    return;
  }

  window.__pedidoFornecedorPDFWindow.focus();
}

// =====================================================
// LISTENER GLOBAL (NÃO DUPLICA)
// =====================================================
document.addEventListener('click', handleExportPDFpedidoFornecedor);

 
    
    
  window.toggleFiltrosPedidos = function () {
    const container = document.getElementById('filtros-container-pedidos');
    if (container) {
      container.style.display = container.style.display === 'none' ? 'block' : 'none';
    }
  };
    
   // FILTROS DA PÁGINA DE PEDIDOS
const formPEDIDOS = document.getElementById('filtroPedidosForm');

function atualizarListaPEDIDOS(extraParams = {}) {
  if (!formPEDIDOS) return;

  const formData = new FormData(formPEDIDOS);
  const params = new URLSearchParams(formData);

  for (const key in extraParams) {
    params.set(key, extraParams[key]);
  }

  const url = `includes/fin_pedidos/listagem.php?${params.toString()}`;
  carregarPagina(url);
}

if (formPEDIDOS) {
  formPEDIDOS.addEventListener('submit', function (e) {
    e.preventDefault();
    atualizarListaPEDIDOS({ pagina: 1 });
  });
}

const limiteSelectPEDIDOS = document.getElementById('limiteRequisicao');
if (limiteSelectPEDIDOS) {
  limiteSelectPEDIDOS.addEventListener('change', function () {
    atualizarListaPEDIDOS({ pagina: 1, limite: this.value });
  });
}

const btnLimparFiltrosPEDIDOS = document.getElementById('btnLimparFiltrosPedidos');
if (btnLimparFiltrosPEDIDOS && formPEDIDOS) {
  btnLimparFiltrosPEDIDOS.addEventListener('click', function (e) {
    e.preventDefault();
    formPEDIDOS.reset();

    const limiteAtual = document.getElementById('limitePedidoForn')?.value || 10;
    const url = `includes/fin_pedidos/listagem.php?pagina=1&limite=${limiteAtual}`;
    carregarPagina(url);
  });
}

let contadorItensPedido = 0;
let editarObserver = null;

// ============================
// SINCRONIZA O CONTADOR GLOBAL COM OS ITENS ATUAIS NO DOM
// ============================
function syncContadorComDOM() {
  const seletorTitulos = '#itensPedidoContainer .fw-bold, #itensPedidoContainerEditar .fw-bold';
  const titulos = document.querySelectorAll(seletorTitulos);
  let maxN = contadorItensPedido || 0;
  titulos.forEach(t => {
    const txt = t.textContent || '';
    const m = txt.match(/Item\s+(\d+)/i);
    if (m && m[1]) {
      const n = parseInt(m[1], 10);
      if (!isNaN(n) && n > maxN) maxN = n;
    }
  });
  const totalItensCadastro = document.querySelectorAll('#itensPedidoContainer .valor-total').length;
  const totalItensEditar = document.querySelectorAll('#itensPedidoContainerEditar .total-edit').length;
  const totalItens = Math.max(totalItensCadastro, totalItensEditar);
  if (totalItens > maxN) maxN = totalItens;
  contadorItensPedido = maxN;
}

// ============================
// FUNÇÃO GERAL DE ATUALIZAÇÃO DE SOMA TOTAL
// ============================
function atualizarSomaTotal(contexto = 'cadastro') {
  let containerId, totalId, descontoInput;
  if (contexto === 'editar') {
    containerId = 'itensPedidoContainerEditar';
    totalId = 'editar-somaTotalItens';
    descontoInput = document.getElementById('editar-desconto_empenho');
  } else {
    containerId = 'itensPedidoContainer';
    totalId = 'somaTotalItens';
    descontoInput = document.getElementById('desconto_empenho');
  }

  const container = document.getElementById(containerId);
  const somaElement = document.getElementById(totalId);
  if (!container || !somaElement) return;

  const totais = container.querySelectorAll('.valor-total, .total-edit');
  let soma = 0;
  totais.forEach(input => soma += parseFloat(input.value) || 0);

  let descontoPercent = parseFloat(descontoInput?.value) || 0;
  if (descontoPercent > 100) {
    descontoPercent = 100;
    if (descontoInput) descontoInput.value = 100;
  } else if (descontoPercent < 0) {
    descontoPercent = 0;
    if (descontoInput) descontoInput.value = 0;
  }

  const valorDesconto = soma * (descontoPercent / 100);
  const valorFinal = soma - valorDesconto;

  if (soma > 0) {
    somaElement.innerHTML = `
      Valor total dos itens: <strong>R$ ${soma.toFixed(2).replace('.', ',')}</strong> /
      Valor total do desconto: <strong>R$ ${valorDesconto.toFixed(2).replace('.', ',')}</strong> /
      Valor final com desconto: <strong>R$ ${valorFinal.toFixed(2).replace('.', ',')}</strong>
    `;
    somaElement.classList.remove('text-muted');
  } else {
    somaElement.textContent = 'Não há itens adicionados.';
    somaElement.classList.add('text-muted');
  }
}

// ============================
// ATUALIZA AO MUDAR O DESCONTO (CADASTRO E EDIÇÃO)
// ============================
document.addEventListener('input', function (e) {
  if (e.target.id === 'desconto_empenho') atualizarSomaTotal('cadastro');
  if (e.target.id === 'editar-desconto_empenho') atualizarSomaTotal('editar');
});

// ============================
// FUNÇÃO PARA ADICIONAR ITEM NO CADASTRO
// ============================
window.adicionarItemPedido = function () {
  contadorItensPedido++;
  syncContadorComDOM();
  const container = document.getElementById('itensPedidoContainer');
  if (!container) return;

  const itemDiv = document.createElement('div');
  itemDiv.classList.add('border', 'rounded', 'p-3', 'mb-3', 'bg-light');
  itemDiv.innerHTML = `
    <h6 class="fw-bold">Item ${contadorItensPedido}</h6>
    <div class="row g-2">
      <div class="col-md-4">
        <label class="form-label">Código do Item</label>
        <input type="text" class="form-control" name="itens[${contadorItensPedido}][codigo_item]" required>
      </div>
      <div class="col-md-8">
        <label class="form-label">Descrição do Item</label>
        <input type="text" class="form-control" name="itens[${contadorItensPedido}][descricao_item]" required>
      </div>
      <div class="col-md-2">
        <label class="form-label">Qtd. Solicitada</label>
        <input type="number" class="form-control qtd-solicitada" name="itens[${contadorItensPedido}][quant_solicitada]" step="any" required>
      </div>
      <div class="col-md-2">
        <label class="form-label">Unidade</label>
        <input type="text" class="form-control" name="itens[${contadorItensPedido}][und_solicitada]" required>
      </div>
      <div class="col-md-3">
        <label class="form-label">Valor Unitário</label>
        <input type="number" class="form-control valor-unit" name="itens[${contadorItensPedido}][valor_unt]" step="any" required>
      </div>
      <div class="col-md-3">
        <label class="form-label">Valor Total</label>
        <input type="number" class="form-control valor-total" name="itens[${contadorItensPedido}][valor_total]" step="any" readonly>
      </div>
    </div>
    <div class="text-end mt-2">
      <button type="button" class="btn btn-danger btn-sm" onclick="this.closest('.bg-light').remove(); atualizarSomaTotal('cadastro')">Remover Item</button>
    </div>
  `;
  container.appendChild(itemDiv);

  const unit = itemDiv.querySelector('.valor-unit');
  const qtd = itemDiv.querySelector('.qtd-solicitada');
  const total = itemDiv.querySelector('.valor-total');
  function calcularTotal() {
    total.value = ((parseFloat(qtd.value) || 0) * (parseFloat(unit.value) || 0)).toFixed(2);
    atualizarSomaTotal('cadastro');
  }
  unit.addEventListener('input', calcularTotal);
  qtd.addEventListener('input', calcularTotal);
  atualizarSomaTotal('cadastro');
};

function controlarCamposEdicaoPedido(autorizado) {
  const form = document.getElementById('form-pedido-editar');
  if (!form) return;

  const liberarSomente = ['editar-situacao_pedido'];

  // Inputs e selects
  form.querySelectorAll('input, select, textarea').forEach(el => {
    if (liberarSomente.includes(el.id)) {
      el.disabled = false;
      el.readOnly = false;
      return;
    }

    if (autorizado) {
      if (el.tagName === 'SELECT') {
        el.disabled = true;
      } else {
        el.readOnly = true;
      }
    } else {
      el.disabled = false;
      el.readOnly = false;
    }
  });

  // Botão adicionar item
  const btnAdd = form.querySelector('button[onclick="adicionarItemPedidoEditar()"]');
  if (btnAdd) btnAdd.disabled = autorizado;

  // Remover botões de item
  form.querySelectorAll('.btn-danger').forEach(btn => {
    if (btn.textContent.includes('Remover')) {
      btn.disabled = autorizado;
      if (autorizado) btn.classList.add('d-none');
      else btn.classList.remove('d-none');
    }
  });
}


// ============================
// MODAL DE EDIÇÃO
// ============================
document.addEventListener('DOMContentLoaded', function () {
  const modalEditar = document.getElementById('modalEditarPedido');
  if (!modalEditar) return;

  modalEditar.addEventListener('shown.bs.modal', function () {
    syncContadorComDOM();
    ativarObserverEditar();
    atualizarSomaTotal('editar');
  });

  modalEditar.addEventListener('hidden.bs.modal', function () {
    desconectarObserverEditar();
  });
});

function ativarObserverEditar() {
  const container = document.getElementById('itensPedidoContainerEditar');
  if (!container) return;
  if (editarObserver) editarObserver.disconnect();

  editarObserver = new MutationObserver(() => {
    syncContadorComDOM();
    atualizarSomaTotal('editar');
  });
  editarObserver.observe(container, { childList: true, subtree: true });
}
function desconectarObserverEditar() {
  if (editarObserver) editarObserver.disconnect();
}

// ============================
// ABRIR MODAL E CARREGAR DADOS DO PEDIDO
// ============================
window.editarPedido = function (id) {
  document.getElementById('modalEditarPedidoLabel').textContent = `Editando Pedido #${id}`;
  const form = document.getElementById('form-pedido-editar');
  const container = document.getElementById('itensPedidoContainerEditar');
  if (!form || !container) return;

  container.innerHTML = '';
  atualizarSomaTotal('editar');

  fetch(`includes/fin_pedidos/buscar_pedido.php?id=${id}`)
    .then(r => r.json())
.then(data => {
  if (!data.sucesso) throw new Error(data.mensagem || 'Erro ao carregar');
  const p = data.pedido;

  const autorizado = (p.autorizacao || '').toLowerCase() === 'sim';

      form.querySelector('#editar-id-pedido').value = p.id;
      form.querySelector('#editar-data_pedido').value = p.data_pedido;
      form.querySelector('#editar-batalhao').value = p.batalhao;
      form.querySelector('#editar-solicitante').value = p.solicitante;
      form.querySelector('#editar-id_vtr').value = p.id_vtr;
      form.querySelector('#editar-id_os').value = p.id_os;
      form.querySelector('#editar-secao_rspns').value = p.secao_rspns;
      form.querySelector('#editar-situacao_pedido').value = p.situacao_pedido;
      form.querySelector('#editar-local_pedido').value = p.local_pedido;
      form.querySelector('#editar-desconto_empenho').value = p.desconto_empenho;

  
      
      window.contadorItensPedidoEditar = 0;
      data.itens.forEach(item => adicionarItemPedidoEditar(item));
      atualizarSomaTotal('editar');
      controlarCamposEdicaoPedido(autorizado);
    })
    .catch(err => swal('Erro', err.message, 'error'));
};

// ============================
// ADICIONAR ITEM NO MODO EDIÇÃO
// ============================
window.adicionarItemPedidoEditar = function (item = {}) {
  window.contadorItensPedidoEditar++;
  const idx = window.contadorItensPedidoEditar;
  const container = document.getElementById('itensPedidoContainerEditar');
  const div = document.createElement('div');
  div.className = 'border rounded p-3 mb-3 bg-light';
  div.innerHTML = `
    <h6 class="fw-bold">Item ${idx}</h6>
    <div class="row g-2">
      <div class="col-md-4">
        <label class="form-label">Código do Item</label>
        <input type="text" class="form-control" name="itens[${idx}][codigo_item]" value="${item.codigo_item||''}" required>
      </div>
      <div class="col-md-8">
        <label class="form-label">Descrição do Item</label>
        <input type="text" class="form-control" name="itens[${idx}][descricao_item]" value="${item.descricao_item||''}" required>
      </div>
      <div class="col-md-2">
        <label class="form-label">Qtd. Solicitada</label>
        <input type="number" class="form-control qtd-edit" name="itens[${idx}][quant_solicitada]" value="${item.quant_solicitada||''}" step="any" required>
      </div>
      <div class="col-md-2">
        <label class="form-label">Unidade</label>
        <input type="text" class="form-control" name="itens[${idx}][und_solicitada]" value="${item.und_solicitada||''}" required>
      </div>
      <div class="col-md-3">
        <label class="form-label">Valor Unitário</label>
        <input type="number" class="form-control unit-edit" name="itens[${idx}][valor_unt]" value="${item.valor_unt||''}" step="any" required>
      </div>
      <div class="col-md-3">
        <label class="form-label">Valor Total</label>
        <input type="number" class="form-control total-edit" name="itens[${idx}][valor_total]" value="${item.valor_total||''}" step="any" readonly>
      </div>
    </div>
    <div class="text-end mt-2">
      <button type="button" class="btn btn-danger btn-sm" onclick="this.closest('.bg-light').remove(); atualizarSomaTotal('editar')">Remover Item</button>
    </div>
  `;
  container.appendChild(div);

  const qtd = div.querySelector('.qtd-edit');
  const unit = div.querySelector('.unit-edit');
  const tot = div.querySelector('.total-edit');
  function calc() {
    const v = (parseFloat(qtd.value)||0)*(parseFloat(unit.value)||0);
    tot.value = v.toFixed(2);
    atualizarSomaTotal('editar');
  }
  qtd.addEventListener('input', calc);
  unit.addEventListener('input', calc);
  calc();
};

// ============================
// SUBMETER EDIÇÃO
// ============================
const formEditar = document.getElementById('form-pedido-editar');
if (formEditar) {
  formEditar.addEventListener('submit', e => {
    e.preventDefault();
    const fd = new FormData(formEditar);
    fetch('includes/fin_pedidos/salvar_editar_pedido.php', {
      method: 'POST',
      body: fd
    })
      .then(r => r.json())
      .then(res => {
        if (res.status === 'sucesso') {
          swal('Sucesso', res.mensagem, 'success')
            .then(() => {
              carregarPagina('includes/fin_pedidos/listagem.php');
              bootstrap.Modal.getInstance(document.getElementById('modalEditarPedido')).hide();
            });
        } else {
          swal('Erro', res.mensagem || 'Falha ao atualizar pedido.', 'error');
        }
      })
      .catch(err => swal('Erro de rede', err.message, 'error'));
  });
}
    
    // ============================
// SUBMETER CADASTRO DE NOVO PEDIDO
// ============================
const formCadastrar = document.getElementById('form-pedido-cadastrar');
if (formCadastrar) {
  formCadastrar.addEventListener('submit', function (e) {
    e.preventDefault();

    const formData = new FormData(formCadastrar);

    fetch('includes/fin_pedidos/cadastrar_pedido.php', {
      method: 'POST',
      body: formData
    })
      .then(response => response.json())
      .then(result => {
        if (result.status === 'sucesso') {
          swal('Sucesso', result.mensagem, 'success')
            .then(() => {
              // Atualiza a listagem e fecha o modal
              carregarPagina('includes/fin_pedidos/listagem.php');
              const modal = bootstrap.Modal.getInstance(document.getElementById('modalCadastrarPedido'));
              if (modal) modal.hide();
              formCadastrar.reset();
              document.getElementById('itensPedidoContainer').innerHTML = '';
              document.getElementById('somaTotalItens').innerHTML = 'Não há itens adicionados.';
            });
        } else {
          swal('Erro', result.mensagem || 'Falha ao cadastrar pedido.', 'error');
        }
      })
      .catch(error => {
        swal('Erro de rede', 'Ocorreu um erro ao enviar o formulário: ' + error.message, 'error');
      });
  });
}
    
window.deletarPedido = function(botao) {

  const id = botao.dataset.id;
  const token = botao.dataset.token; //  pega o token

  Swal.fire({
    title: 'Deletar Pedido?',
    text: "Essa ação não poderá ser desfeita. Deseja realmente excluir este Pedido e todos os seus itens?",
    icon: 'warning',
    showCancelButton: true,
    confirmButtonText: 'Sim, deletar',
    cancelButtonText: 'Cancelar'
  }).then((result) => {

    if (result.isConfirmed) {

      // 🔒 evita múltiplos cliques
      botao.disabled = true;

      const formData = new FormData();
      formData.append('id', id);
      formData.append('csrf_token', token); // 🔒 envia o token

      fetch('includes/fin_pedidos/deletar_pedido.php', {
        method: 'POST',
        headers: {
          'X-Requested-With': 'XMLHttpRequest' // 🔒 reforço
        },
        body: formData
      })
      .then(response => response.json())
      .then(data => {

        botao.disabled = false;

        if (data.success) {
          Swal.fire({
            icon: 'success',
            title: 'Deletado!',
            text: 'Pedido excluído com sucesso.',
            timer: 1500,
            showConfirmButton: false
          }).then(() => {
            carregarPagina('includes/fin_pedidos/listagem.php');
          });
        } else {
          Swal.fire({
            icon: 'error',
            title: 'Erro',
            text: data.message || 'Erro ao deletar o Pedido.'
          });
        }
      })
      .catch(error => {

        botao.disabled = false;

        Swal.fire({
          icon: 'error',
          title: 'Erro de rede',
          text: error.message
        });
      });
    }
  });
};

// =============================
// EVENTO (melhor que querySelectorAll)
// =============================
document.addEventListener('click', function(e) {
  const botao = e.target.closest('.btn-deletar-pedido');
  if (botao) {
    deletarPedido(botao);
  }
});

    
};
