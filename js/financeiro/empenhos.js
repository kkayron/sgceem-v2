window.inicializarEmpenho = function () {
    
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
                carregarPagina('includes/fin_empenhos/listagem.php');
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

// -------------------------------------------------------------
// Função para formatar número no padrão BR (2.500,43)
// -------------------------------------------------------------
function formatarBR(valor) {
  if (valor === null || valor === "" || isNaN(valor)) return "0,00";

  return parseFloat(valor)
    .toFixed(2)
    .replace(".", ",")
    .replace(/\B(?=(\d{3})+(?!\d))/g, ".");
}

// -------------------------------------------------------------
// Função principal para abrir modal e carregar dados
// -------------------------------------------------------------
window.editarEmpenho = function(id) {

  const modal = new bootstrap.Modal(document.getElementById('modalEditarEmpenho'));
  const form  = document.getElementById('form-editar-empenho');

  document.getElementById('modalLabelEditarEmpenho').textContent =
      `Editar Empenho da Requisição #${id}`;

  form.reset();
  form.querySelector('#editar-id-requisicao').value = id;

  fetch(`includes/fin_requisicoes/buscar_empenho.php?id=${id}`)
    .then(r => r.json())
    .then(d => {

      if (!d.sucesso) {
        alert('Erro ao carregar dados.');
        return;
      }

      const r = d.requisicao;
      const e = d.empenho;

      // -------------------------------
      // Campos da requisição
      // -------------------------------
      form.querySelector('#editar-batalhao').value             = r.batalhao;
      form.querySelector('#editar-fornecedor').value           = r.id_fornecedor;
      form.querySelector('#editar-requisitante').value         = r.requisitante;
      form.querySelector('#editar-naturezadespesa').value      = r.natureza_despesa;
      form.querySelector('#editar-item_oog').value             = r.item_oog;
      form.querySelector('#editar-finalidade').value           = r.finalidade;
      form.querySelector('#editar-destinatario').value         = r.destinatario;
      form.querySelector('#editar-nota_credito').value         = r.nota_credito;
      form.querySelector('#editar-plano_interno').value        = r.plano_interno;
      form.querySelector('#editar-necessidade_contrato').value = r.necessidade_contrato;
      form.querySelector('#editar-tipo_empenho').value         = r.tipo_empenho;
      form.querySelector('#editar-status_requisicao').value    = r.status_requisicao;

      // Valor empenhado → formato BR
      form.querySelector('#editar-valor_empenhado').value      = formatarBR(r.valor_empenhado);

      // -------------------------------
      // Campos do empenho (se já existir)
      // -------------------------------
      if (e) {
        form.querySelector('#editar-data_empenho').value = e.data_empenho;
        form.querySelector('#editar-nmr_empenho').value  = e.nmr_empenho;
        form.querySelector('#editar-obra').value         = e.obra;
        form.querySelector('#editar-ano').value          = e.ano;
        form.querySelector('#editar-categoria').value    = e.categoria;
        form.querySelector('#editar-local').value        = e.local;
        form.querySelector('#editar-resto_pagar').value  = e.resto_pagar;
      } else {
        // Se não tiver empenho ainda, deixa a data atual
        form.querySelector('#editar-data_empenho').value =
          new Date().toISOString().slice(0, 10);
      }

      modal.show();
    })
    .catch(err => {
      alert("Erro ao carregar empenho: " + err.message);
      console.error(err);
    });

};


// ---------------------------------------------------------
// ENVIAR FORMULÁRIO DE EDIÇÃO DE EMPENHO
// ---------------------------------------------------------
const formEditar = document.getElementById('form-editar-empenho');

if (formEditar) {
  formEditar.addEventListener('submit', function (e) {
    e.preventDefault();

    const formData = new FormData(formEditar);

    fetch('includes/fin_requisicoes/salvar_editar_requisicao.php', {
      method: 'POST',
      body: formData
    })
      .then(res => res.text())
      .then(resp => {

        if (resp.trim() === 'ok') {

          swal("Sucesso!", "Empenho atualizado com sucesso!", "success")
            .then(() => {
              carregarPagina('includes/fin_empenhos/listagem.php');
              fecharModalAberto();
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


window.verEmpenho = function(id) {

  fetch(`includes/fin_empenhos/buscar_empenho_ver.php?id=${id}`)
    .then(r => r.json())
    .then(d => {

      if (!d.sucesso) {
        alert("Erro ao buscar dados do empenho.");
        return;
      }

      const e = d.empenho || {};
      const r = d.requisicao || {};
      const f = d.fornecedor || {};

      // ================== DADOS DO EMPENHO ==================
      document.getElementById("ver_emp_nmr_empenho").textContent = e.nmr_empenho || "—";
      document.getElementById("ver_emp_data_empenho").textContent = e.data_empenho || "—";
      document.getElementById("ver_emp_ano").textContent = e.ano || "—";
      document.getElementById("ver_emp_categoria").textContent = e.categoria || "—";
      document.getElementById("ver_emp_local").textContent = e.local || "—";

      // ================== REQUISIÇÃO ==================
      document.getElementById("ver_req_requisitante").textContent = r.requisitante || "—";
      document.getElementById("ver_req_destinatario").textContent = r.destinatario || "—";
      document.getElementById("ver_req_nota_credito").textContent = r.nota_credito || "—";
      document.getElementById("ver_req_id_pregao").textContent = r.id_pregao || "—";

      // ================== FORNECEDOR ==================
      document.getElementById("ver_forn_nome_empresa").textContent = f.nome_empresa || "—";
      document.getElementById("ver_forn_cnpj").textContent = f.cnpj_empresa || "—";
      document.getElementById("ver_forn_contato").textContent = f.contato_nome || "—";

      // ================== SALDOS ==================
      document.getElementById("ver_siafi").textContent = d.saldos.siafi;
      document.getElementById("ver_real").textContent = d.saldos.real;
      document.getElementById("ver_diferenca").textContent = d.saldos.diferenca;

      // ================== TOTAIS DO EMPENHO ==================
      document.getElementById("ver_total_empenhado").textContent = d.totais.empenhado;
      document.getElementById("ver_total_utilizado").textContent = d.totais.utilizado;
      document.getElementById("ver_total_liquidado").textContent = d.totais.liquidado;
      document.getElementById("ver_solicitado_nao_entregue").textContent = d.totais.solicitado_nao_entregue;

      // ================== PEDIDOS ==================
      let pedidosHTML = "";
      (d.pedidos || []).forEach((p, i) => {
        const val = Number(p.valor_total || 0).toLocaleString('pt-BR', {minimumFractionDigits: 2});
        const desconto = Number(p.valor_desconto || 0).toLocaleString('pt-BR',{minimumFractionDigits:2});
        const final = Number(p.valor_final || 0).toLocaleString('pt-BR',{minimumFractionDigits:2});
        pedidosHTML += `
          <tr>
            <td>${i + 1}</td>
            <td>${p.solicitante || "—"}</td>
	        <td>${p.data_pedido ? new Date(p.data_pedido).toLocaleDateString('pt-BR') : "—"}</td>
            <td>${p.situacao_pedido || "—"}</td>
            <td>${p.local_pedido || "—"}</td>
            <td class="text-end">${val}</td>
            <td class="text-end">${desconto}</td>
            <td class="text-end">${final}</td>
          </tr>`;
      });

      document.getElementById("ver_pedidos_container").innerHTML = pedidosHTML;
      document.getElementById("ver_total_pedidos").textContent = d.totais.pedidos;

      // ================== ORDENS DE FORNECIMENTO ==================
      let ofHTML = "";
      (d.ordens || []).forEach((o, i) => {
        const val = Number(o.valor_total || 0).toLocaleString('pt-BR', {minimumFractionDigits: 2});
        ofHTML += `
          <tr>
            <td>${i + 1}</td>
            <td>${o.empresa_nome || "—"}</td>
            <td>${o.data_cadastro ? new Date(o.data_cadastro.replace(' ', 'T')).toLocaleDateString('pt-BR') : "—"}</td>
            <td>${o.data_entrega_limite ? new Date(o.data_entrega_limite.replace(' ', 'T')).toLocaleDateString('pt-BR') : "—"}</td>
            <td>${o.status || "—"}</td>
            <td class="text-end">${val}</td>
          </tr>`;
      });

      document.getElementById("ver_of_container").innerHTML = ofHTML;
      document.getElementById("ver_total_ofs").textContent = d.totais.ofs;


    })
    .catch(err => {
      console.error(err);
      alert("Erro ao carregar dados do servidor.");
    });
};



    
};
