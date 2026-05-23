window.inicializarEmpenho = function () {
  window.toggleFiltrosEmpenho = function () {
    const container = document.getElementById('filtros-container-empenho');
    if (container) {
      container.style.display = container.style.display === 'none' ? 'block' : 'none';
    }
  };   
    // FILTROS DA PÁGINA DE EMPENHOS
const formEMPENHO = document.getElementById('filtroEmpenhoForm');

function atualizarListaEMPENHO(extraParams = {}) {
  if (!formEMPENHO) return;

  const formData = new FormData(formEMPENHO);
  const params = new URLSearchParams(formData);

  for (const key in extraParams) {
    params.set(key, extraParams[key]);
  }

  const url = `includes/fin_empenhos/listagem.php?${params.toString()}`;
  carregarPagina(url);
}

if (formEMPENHO) {
  formEMPENHO.addEventListener('submit', function (e) {
    e.preventDefault();
    atualizarListaEMPENHO({ pagina: 1 });
  });
}

const limiteSelectEMPENHO = document.getElementById('limiteEmpenho');
if (limiteSelectEMPENHO) {
  limiteSelectEMPENHO.addEventListener('change', function () {
    atualizarListaEMPENHO({ pagina: 1, limite: this.value });
  });
}

const btnLimparFiltrosEMPENHO = document.getElementById('btnLimparFiltrosEmpenho');
if (btnLimparFiltrosEMPENHO && formEMPENHO) {
  btnLimparFiltrosEMPENHO.addEventListener('click', function (e) {
    e.preventDefault();
    formEMPENHO.reset();

    const limiteAtual = document.getElementById('limiteEmpenho')?.value || 10;
    const url = `includes/fin_empenhos/listagem.php?pagina=1&limite=${limiteAtual}`;
    carregarPagina(url);
  });
}

/**
 * Abre o modal “Gerar/Editar Empenho” e preenche (se já existir) ou deixa em branco (se ainda não gerado).
 */
window.editarEmpenho = function (id) {
  // Atualiza o título do modal
  document.querySelector('#modalGerarEmpenho .modal-title')
          .textContent = `Editar Empenho da Requisição #${id}`;

  const form = document.getElementById('form-gerar-empenho');
  if (!form) return;

  // Coloca o ID escondido no form
  form.setAttribute('data-id', id);
  form.querySelector('#gerar-id-requisicao').value = id;

  // Chama o PHP buscar_empenho.php
  fetch(`includes/fin_requisicoes/buscar_empenho.php?id=${id}`)
    .then(res => res.json())
    .then(dados => {
      if (!dados.sucesso) {
        alert('Erro ao buscar dados do empenho.');
        return;
      }

      if (!dados.empenho) {
        // Ainda não existe empenho → limpa todos os campos, mantêm data_empenho como hoje
        form.querySelector('#gerar-nmr-empenho').value = '';
        form.querySelector('#gerar-data_empenho').value = new Date().toISOString().slice(0, 10);
        form.querySelector('#gerar-obra').value = '';
        form.querySelector('#gerar-ano').value = '';
        form.querySelector('#gerar-categoria').value = '';
        form.querySelector('#gerar-local').value = 'Sede';
        form.querySelector('#gerar-resto_pagar').value = 'Não';
        return;
      }

      // Se já existe, preenche todos
      const e = dados.empenho;
      form.querySelector('#gerar-nmr-empenho').value = e.nmr_empenho || '';
      form.querySelector('#gerar-data_empenho').value = e.data_empenho || new Date().toISOString().slice(0, 10);
      form.querySelector('#gerar-obra').value       = e.obra       || '';
      form.querySelector('#gerar-ano').value        = e.ano        || '';
      form.querySelector('#gerar-categoria').value  = e.categoria  || '';
      form.querySelector('#gerar-local').value      = e.local      || 'Sede';
      form.querySelector('#gerar-resto_pagar').value= e.resto_pagar|| 'Não';
    });
};


/**
 * Abertura rápida do modal para “Gerar Empenho” sem dados (cadastrar novo).
 * Basta definir o ID e limpar os campos (data em hoje).
 */
window.gerarEmpenho = function (id) {
  const form = document.getElementById('form-gerar-empenho');
  if (!form) return;

  form.setAttribute('data-id', id);
  form.querySelector('#gerar-id-requisicao').value = id;
  form.querySelector('#gerar-nmr-empenho').value = '';
  form.querySelector('#gerar-data_empenho').value = new Date().toISOString().slice(0, 10);
  form.querySelector('#gerar-obra').value       = '';
  form.querySelector('#gerar-ano').value        = '';
  form.querySelector('#gerar-categoria').value  = '';
  form.querySelector('#gerar-local').value      = 'Sede';
  form.querySelector('#gerar-resto_pagar').value= 'Não';
};


/**
 * Envia o formulário de “Gerar/Editar Empenho” ao servidor
 */
const formGerarEmpenho = document.getElementById('form-gerar-empenho');
if (formGerarEmpenho) {
  formGerarEmpenho.addEventListener('submit', function (e) {
    e.preventDefault();
    const formData = new FormData(formGerarEmpenho);

    fetch('includes/fin_requisicoes/gerar_empenho.php', {
      method: 'POST',
      body: formData
    })
      .then(res => res.text())
      .then(retorno => {
        if (retorno.trim() === 'ok') {
          carregarPagina('includes/fin_empenhos/listagem.php');
          fecharModalAberto();
        } else {
          alert('Erro ao gerar empenho: ' + retorno);
        }
      })
      .catch(err => alert('Erro ao enviar: ' + err.message));
  });
}

window.verEmpenho = function(id) {

  fetch(`includes/fin_empenhos/buscar_empenho_ver.php?id=${id}`)
    .then(r => r.json())
    .then(d => {

      if (!d.sucesso) {
        alert("Erro ao buscar dados do empenho.");
        return;
      }

      const e = d.empenho;
      const r = d.requisicao;
      const f = d.fornecedor;

      // ================== DADOS DO EMPENHO ==================
      document.getElementById("ver_emp_nmr_empenho").textContent = e.nmr_empenho;
      document.getElementById("ver_emp_data_empenho").textContent = e.data_empenho;
      document.getElementById("ver_emp_ano").textContent = e.ano;
      document.getElementById("ver_emp_categoria").textContent = e.categoria;
      document.getElementById("ver_emp_local").textContent = e.local;

      // ================== REQUISIÇÃO ==================
      document.getElementById("ver_req_requisitante").textContent = r.requisitante;
      document.getElementById("ver_req_destinatario").textContent = r.destinatario;
      document.getElementById("ver_req_nota_credito").textContent = r.nota_credito;
      document.getElementById("ver_req_id_pregao").textContent = r.id_pregao;

      // ================== FORNECEDOR ==================
      document.getElementById("ver_forn_nome_empresa").textContent = f.nome_empresa ?? "—";
      document.getElementById("ver_forn_cnpj").textContent = f.cnpj_empresa ?? "—";
      document.getElementById("ver_forn_contato").textContent = f.contato_nome ?? "—";

      // ================== SALDOS ==================
      document.getElementById("ver_siafi").textContent = d.saldos.siafi;
      document.getElementById("ver_real").textContent = d.saldos.real;
      document.getElementById("ver_diferenca").textContent = d.saldos.diferenca;

      // ================== PEDIDOS ==================
      let pedidosHTML = "";
      let totalPedidos = 0;

      d.pedidos.forEach((p, i) => {
        const valor = parseFloat(p.valor_total ?? 0);
        totalPedidos += valor;

        pedidosHTML += `
          <tr>
            <td>${i+1}</td>
            <td>${p.solicitante}</td>
            <td>${p.data_pedido}</td>
            <td>${p.situacao_pedido}</td>
            <td>${p.local_pedido}</td>
            <td class="text-end">${valor.toFixed(2).replace(".", ",")}</td>
          </tr>`;
      });

      document.getElementById("ver_pedidos_container").innerHTML = pedidosHTML;
      document.getElementById("ver_total_pedidos").textContent =
        totalPedidos.toFixed(2).replace(".", ",");

      // ================== ORDENS DE FORNECIMENTO ==================
      let ofHTML = "";
      let totalOF = 0;

      d.ordens.forEach((o, i) => {
        const valor = parseFloat(o.valor_total ?? 0);
        totalOF += valor;

        ofHTML += `
          <tr>
            <td>${i+1}</td>
            <td>${o.empresa_nome}</td>
            <td>${o.data_cadastro}</td>
            <td>${o.data_entrega_limite}</td>
            <td>${o.status}</td>
            <td class="text-end">${valor.toFixed(2).replace(".", ",")}</td>
          </tr>`;
      });

      document.getElementById("ver_of_container").innerHTML = ofHTML;
      document.getElementById("ver_total_ofs").textContent =
        totalOF.toFixed(2).replace(".", ",");

    });
};
	
	// Importar Empenhos
const formImportarEmpenho = document.getElementById('formImportarEmpenho');

if (formImportarEmpenho) {
  formImportarEmpenho.addEventListener('submit', function (e) {
    e.preventDefault();

    const formData = new FormData(formImportarEmpenho);

    fetch('includes/fin_empenhos/importar_empenhos.php', {
      method: 'POST',
      body: formData
    })
      .then(async res => {

        // 🔥 DEBUG – captura a resposta crua ANTES do JSON
        const raw = await res.text();
        console.log("RAW RESPONSE IMPORTAÇÃO:", raw);

        try {
          return JSON.parse(raw);
        } catch (e) {
          swal({
            title: "Erro de Resposta!",
            text: "A resposta do servidor não é JSON válido.\n\nVeja o console do navegador (F12).",
            icon: "error",
            button: { text: "Fechar", className: "btn btn-danger" }
          });
          throw e;
        }

      })
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
            carregarPagina('includes/fin_empenhos/listagem.php');
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

 // ===============================
// Exportar Excel - Empenhos (ALINHADO COM A LISTAGEM)
// ===============================
(function () {

    const btn = document.getElementById('btnExportarExcelEmpenhos');
    if (!btn) return;

    function getValByIdOrName(id, name) {

        if (id) {
            const el = document.getElementById(id);
            if (el) return el.value || '';
        }

        if (name) {
            const el = document.querySelector('[name="' + name + '"]');
            if (el) return el.value || '';
        }

        return '';
    }

    btn.addEventListener('click', function (e) {
        e.preventDefault();

        // 🔹 NOMES EXATOS IGUAIS AO PHP DA LISTAGEM
        const filtros = {
            batalhao:      getValByIdOrName('filtroBatalhao', 'batalhao'),
            ano:           getValByIdOrName('filtroAno', 'ano'),
            nmr_empenho:   getValByIdOrName('filtroNumeroEmpenho', 'nmr_empenho'),
            fornecedor:    getValByIdOrName('filtroFornecedor', 'fornecedor'),
            requisitante:  getValByIdOrName('filtroRequisitante', 'requisitante'),
            marca:         getValByIdOrName('filtroMarca', 'marca'),
            destinatario:  getValByIdOrName('filtroDestinatario', 'destinatario'),
            obra:          getValByIdOrName('filtroObra', 'obra'),
            categoria:     getValByIdOrName('filtroCategoria', 'categoria'),
            local:         getValByIdOrName('filtroLocal', 'local'),
            saldo_real:    getValByIdOrName('filtroSaldoReal', 'saldo_real')
        };

        const params = new URLSearchParams();

        Object.entries(filtros).forEach(([key, value]) => {
            if (value !== null && value !== '') {
                params.append(key, value);
            }
        });

        const url = 'excel/exportar_empenhos.php?' + params.toString();
        window.open(url, '_blank');
    });

})();

    
};
