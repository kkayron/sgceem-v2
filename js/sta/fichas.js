// JavaScript Document

// LISTAGEM DAS FICHAS DE VIATURAS
window.inicializarFichas = function () {
	
	// Importar Fichas STA
const formImportarSTA = document.getElementById('formImportarSTA');

if (formImportarSTA) {
  formImportarSTA.addEventListener('submit', function(e) {
    e.preventDefault();

    const formData = new FormData(formImportarSTA);

    fetch('includes/sta_fichas/importar_sta.php', {
      method: 'POST',
      body: formData
    })
    .then(res => res.json())
    .then(data => {
      if (data.status === 'ok') {
        let mensagem = data.mensagem;

        if (data.falhas && data.falhas.length > 0) {
          mensagem += "\n\nFalhas detalhadas:\n" + data.falhas.map(f =>
            `Linha ${f.linha}: ${f.erro}`
          ).join("\n");
        }

        swal({
          title: "Importação concluída!",
          text: mensagem,
          icon: "success",
          button: { text: "OK", className: "btn btn-success" }
        }).then(() => {
          carregarPagina('includes/sta_fichas/listagem.php');
          fecharModalAberto();
        });

      } else {
        let erroMsg = data.mensagem || "Verifique o arquivo e tente novamente.";

        if (data.falhas && data.falhas.length > 0) {
          erroMsg += "\n\nFalhas detalhadas:\n" + data.falhas.map(f =>
            `Linha ${f.linha}: ${f.erro}`
          ).join("\n");
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
	
	window.abrirModalAutorizacaoFicha = function(botao) {
  const id = botao.getAttribute('data-id');
  const autorizado = botao.getAttribute('data-autorizado') || 'não';
  const observacao = botao.getAttribute('data-observacao') || '';

  document.getElementById('autorizar-ficha-id').value = id;
  document.getElementById('autorizar-ficha-status').value = autorizado;
  document.getElementById('observacao-autorizacao').value = observacao;
};

const formAutorizarFicha = document.getElementById('form-autorizar-ficha');

if (formAutorizarFicha) {
  formAutorizarFicha.addEventListener('submit', function(e) {
    e.preventDefault();

    const formData = new FormData(formAutorizarFicha);

    fetch('includes/sta_fichas/autorizar_ficha.php', {
      method: 'POST',
      body: formData
    })
    .then(res => res.json())
    .then(data => {
      if (data.success) {
        Swal.fire({
          icon: 'success',
          title: 'Atualizado!',
          text: data.message || 'Autorização atualizada com sucesso.',
          timer: 1500,
          showConfirmButton: false
        }).then(() => {
          carregarPagina('includes/sta_fichas/listagem.php');
          fecharModalAberto();
        });
      } else {
        Swal.fire({
          icon: 'error',
          title: 'Erro',
          text: data.message || 'Erro ao atualizar autorização.'
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
  });
}
    
     // Mostrar/ocultar dados da manutenção preventiva
const chkPreventiva = document.getElementById('manutencaoPreventiva');
if (chkPreventiva) {
  chkPreventiva.addEventListener('change', function () {
    const dadosPreventiva = document.getElementById('dadosPreventiva');
    if (dadosPreventiva) {
      dadosPreventiva.style.display = this.checked ? 'block' : 'none';
    }
  });
}
    
    // ===============================
// Seleção em lote (fichas)
// ===============================
let fichasWindow = null;

function getIdsSelecionados() {
  return Array.from(document.querySelectorAll('.ficha-check:checked'))
    .map(el => el.value)
    .filter(Boolean);
}

function atualizarUISelecao() {
  const ids = getIdsSelecionados();
  const btnPrint = document.getElementById('btnImprimirSelecionadas');
  const btnLimpar = document.getElementById('btnLimparSelecao');
  const badge = document.getElementById('qtdSelecionadas');

  if (badge) badge.textContent = ids.length;

  const temSelecao = ids.length > 0;
  if (btnPrint) btnPrint.disabled = !temSelecao;
  if (btnLimpar) btnLimpar.disabled = !temSelecao;
}

document.addEventListener('change', function(e) {
  if (e.target && e.target.classList.contains('ficha-check')) {
    atualizarUISelecao();
  }

  if (e.target && e.target.id === 'checkAllFichas') {
    const marcar = e.target.checked;
    document.querySelectorAll('.ficha-check').forEach(chk => chk.checked = marcar);
    atualizarUISelecao();
  }
});

document.addEventListener('click', function(e) {
  // Limpar seleção
  if (e.target && e.target.id === 'btnLimparSelecao') {
    document.getElementById('checkAllFichas').checked = false;
    document.querySelectorAll('.ficha-check').forEach(chk => chk.checked = false);
    atualizarUISelecao();
  }

  // Imprimir selecionadas
  const btn = e.target.closest('#btnImprimirSelecionadas');
  if (!btn) return;

  const ids = getIdsSelecionados();
  if (ids.length === 0) {
    alert('Selecione pelo menos uma ficha.');
    return;
  }

  // (opcional) limite para não gerar PDFs gigantes sem querer
  const LIMITE = 50;
  if (ids.length > LIMITE) {
    alert(`Você selecionou ${ids.length}. Selecione no máximo ${LIMITE} por vez.`);
    return;
  }

  const url = `pdf/gerar_fichas_vtr.php?ids=${encodeURIComponent(ids.join(','))}`;

  // Reaproveita aba (igual seu padrão)
  if (fichasWindow && !fichasWindow.closed) {
    fichasWindow.location.href = url;
    fichasWindow.focus();
    return;
  }

  fichasWindow = window.open('', '_blank');
  fichasWindow.document.write(`
    <html>
    <head>
      <title>Fichas Selecionadas</title>
      <style>
        body{font-family:Arial;margin:0;padding:0;display:flex;justify-content:center;align-items:center;height:100vh;background:#f8f9fa}
        .loader{ text-align:center;background:rgba(255,255,255,0.95);padding:40px 60px;border-radius:12px;box-shadow:0 8px 20px rgba(0,0,0,.2)}
        h3{margin:0 0 20px 0;color:#0d6efd;font-weight:600}
        .spinner{border:6px solid #e9ecef;border-top:6px solid #0d6efd;border-radius:50%;width:60px;height:60px;animation:spin 1s linear infinite;margin:0 auto}
        @keyframes spin{0%{transform:rotate(0)}100%{transform:rotate(360deg)}}
        iframe{display:none;width:100%;height:100vh;border:none}
      </style>
    </head>
    <body>
      <div class="loader">
        <h3>CARREGANDO PDF...</h3>
        <div class="spinner"></div>
      </div>
      <iframe src="${url}" onload="this.style.display='block';document.querySelector('.loader').style.display='none';"></iframe>
    </body>
    </html>
  `);
});
    
    
     let pedidoWindow = null; // variável global para controlar a aba

// Remove event listener anterior, se existir
document.removeEventListener('click', handleExportPDFpedido);

function handleExportPDFpedido(e) {
    const btn = e.target.closest('.btnExportarPDFsta');
    if (!btn) return;

    e.preventDefault();

    const pedidoId = btn.getAttribute('data-id');
    if (!pedidoId) {
        alert("ID do pedido inválido.");
        return;
    }

    const url = `pdf/gerar_ficha_vtr.php?id=${pedidoId}`;

    // Se já existe uma aba aberta, apenas muda o URL
    if (pedidoWindow && !pedidoWindow.closed) {
        pedidoWindow.location.href = url;
        pedidoWindow.focus();
        return;
    }

    // Caso não exista, cria uma nova aba
    pedidoWindow = window.open('', '_blank');
    pedidoWindow.document.write(`
        <html>
        <head>
            <title>Ficha de Viatura/Equipamento</title>
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

// Adiciona o event listener apenas uma vez
document.addEventListener('click', handleExportPDFpedido);
    
    
    window.toggleFiltrosFichas = function() {
  const container = document.getElementById('filtros-container-fichas');
  if (container) {
    container.style.display = container.style.display === 'none' ? 'block' : 'none';
  }
};
    
   // ===============================
// DELETAR FICHA (IGUAL AO FORN)
// ===============================

window.deletarFicha = function(botao) {
  const id = botao.getAttribute('data-id');

  Swal.fire({
    title: 'Deletar Ficha?',
    text: "Essa ação não poderá ser desfeita. Deseja realmente excluir esta Ficha?",
    icon: 'warning',
    showCancelButton: true,
    confirmButtonText: 'Sim, deletar',
    cancelButtonText: 'Cancelar'
  }).then((result) => {
    if (result.isConfirmed) {
      const formData = new FormData();
      formData.append('id', id);

      fetch('includes/sta_fichas/deletar_ficha.php', {
        method: 'POST',
        body: formData
      })
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          Swal.fire({
            icon: 'success',
            title: 'Deletado!',
            text: 'Ficha excluída com sucesso.',
            timer: 1500,
            showConfirmButton: false
          }).then(() => {
            carregarPagina('includes/sta_fichas/listagem.php');
          });

        } else {
          Swal.fire({
            icon: 'error',
            title: 'Erro',
            text: data.message || 'Erro ao deletar Ficha.'
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

// Delegação opcional (mesmo estilo do fornecedor)
document.querySelectorAll('.btn-deletar-ficha').forEach(botao => {
  botao.addEventListener('click', function() {
    deletarFicha(this);
  });
});

    
// ========================================
// CADASTRAR FICHA STA
// ========================================

const formFicha = document.getElementById('form-ficha-sta');

if (formFicha) {

    formFicha.addEventListener('submit', function (e) {

        e.preventDefault();

        const btnSubmit = formFicha.querySelector('button[type="submit"]');

        // Evita duplo clique
        if (btnSubmit.disabled) {
            return;
        }

        btnSubmit.disabled = true;

        const textoOriginal = btnSubmit.innerHTML;

        btnSubmit.innerHTML = `
            <span class="spinner-border spinner-border-sm me-2"></span>
            Cadastrando...
        `;

        const formData = new FormData(formFicha);

        // ========================================
        // CAMPOS OPCIONAIS
        // ========================================

        const camposOpcionais = [
            'data_saida',
            'hora_saida',
            'odo_saida',
            'data_retorno',
            'hora_retorno',
            'odo_retorno',
            'observacoes_pos_emprego'
        ];

        camposOpcionais.forEach(campo => {

            let valor = formData.get(campo);

            if (!valor || valor === '') {

                if (campo.includes('data')) {
                    valor = '0001-01-01';
                }
                else if (campo.includes('hora')) {
                    valor = '00:00';
                }
                else if (campo.includes('odo')) {
                    valor = '0';
                }
                else {
                    valor = '';
                }

                formData.set(campo, valor);
            }
        });

        function restaurarBotao() {
            btnSubmit.disabled = false;
            btnSubmit.innerHTML = textoOriginal;
        }

        function enviarFicha(force = false) {

            if (force) {
                formData.set('force', '1');
            }

            fetch('includes/sta_fichas/salvar_ficha.php', {
                method: 'POST',
                body: formData
            })

            .then(res => res.text())

            .then(text => {

                console.log('📌 Resposta bruta salvar_ficha.php:', text);

                let data;

                try {

                    data = JSON.parse(text);

                } catch (err) {

                    restaurarBotao();

                    swal({
                        title: "Erro!",
                        text: "Resposta inválida do servidor. Verifique o Console (F12).",
                        icon: "error",
                        button: {
                            text: "Fechar",
                            className: "btn btn-danger"
                        }
                    });

                    return;
                }

                // ========================================
                // SUCESSO
                // ========================================

                if (data.status === 'sucesso') {

                    swal({
                        title: "Sucesso!",
                        text: data.mensagem || "Ficha cadastrada com sucesso!",
                        icon: "success",
                        button: {
                            text: "OK",
                            className: "btn btn-success"
                        }
                    })

                    .then(() => {

                        carregarPagina('includes/sta_fichas/listagem.php');

                        fecharModalAberto();
                    });

                    return;
                }

                // ========================================
                // CONFLITO
                // ========================================

                if (data.status === 'confirmar') {

                    restaurarBotao();

                    swal({
                        title: "Conflito de Ficha",
                        text: data.mensagem || "Já existe uma ficha aberta neste período. Deseja continuar?",
                        icon: "warning",
                        buttons: ["Cancelar", "Continuar"],
                        dangerMode: true
                    })

                    .then((confirmado) => {

                        if (confirmado) {

                            btnSubmit.disabled = true;

                            btnSubmit.innerHTML = `
                                <span class="spinner-border spinner-border-sm me-2"></span>
                                Cadastrando...
                            `;

                            enviarFicha(true);
                        }
                    });

                    return;
                }

                // ========================================
                // ERRO
                // ========================================

                restaurarBotao();

                swal({
                    title: "Erro!",
                    text: data.mensagem || "Erro ao cadastrar ficha.",
                    icon: "error",
                    button: {
                        text: "Fechar",
                        className: "btn btn-danger"
                    }
                });

            })

            .catch(err => {

                restaurarBotao();

                swal({
                    title: "Erro!",
                    text: "Erro de rede: " + err.message,
                    icon: "error",
                    button: {
                        text: "Fechar",
                        className: "btn btn-danger"
                    }
                });

            });
        }

        enviarFicha();

    });
}
    
    
     // FILTROS DA PÁGINA DE FICHAS STA
  const formFICHA = document.getElementById('filtroFichaForm');

  function atualizarListaFICHAS(extraParams = {}) {
    const formAtual = document.getElementById('filtroFichaForm');
    if (!formAtual) return;

    const formData = new FormData(formAtual);
    const params = new URLSearchParams(formData);

    for (const key in extraParams) {
      params.set(key, extraParams[key]);
    }

    const url = `includes/sta_fichas/listagem.php?${params.toString()}`;
    carregarPagina(url);
  }

  if (formFICHA) {
    formFICHA.addEventListener('submit', function (e) {
      e.preventDefault();
      atualizarListaFICHAS({ pagina: 1 });
    });
  }

  const limiteSelectFICHAS = document.getElementById('limiteFICHAS');

  if (limiteSelectFICHAS) {
    limiteSelectFICHAS.addEventListener('change', function () {
      atualizarListaFICHAS({
        pagina: 1,
        limite: this.value
      });
    });
  }

  const btnLimparFiltrosFichas = document.getElementById('btnLimparFiltrosFichas');

  if (btnLimparFiltrosFichas) {
    btnLimparFiltrosFichas.addEventListener('click', function (e) {
      e.preventDefault();

      const formAtual = document.getElementById('filtroFichaForm');
      if (formAtual) {
        formAtual.reset();
      }

      const limiteAtual = document.getElementById('limiteFICHAS')?.value || 10;

      carregarPagina(`includes/sta_fichas/listagem.php?pagina=1&limite=${limiteAtual}`);
    });
  }
window.editarFICHA = function (id) {
  const form = document.getElementById('form-editar-ficha');
  if (!form) return;

  fetch(`includes/sta_fichas/buscar_ficha.php?id=${id}`)
    .then(res => res.json())
    .then(data => {
      if (data.status !== 'sucesso') {
        swal("Erro", data.mensagem || "Erro ao carregar ficha.", "error");
        return;
      }

      const f = data.ficha;

      // Atualiza o ID oculto
      form.querySelector('#editar-ficha-id').value = id;

// ==============================
// Preenche select de viaturas
// ==============================
const selectViatura = form.querySelector('#editar-id_viatura');
selectViatura.innerHTML = '<option value="" disabled>Selecione a viatura/equipamento</option>';

data.viaturas.forEach(v => {
  const opt = document.createElement('option');
  opt.value = v.id;

  // Monta a descrição completa igual ao PHP
  opt.textContent = `${v.prefixo_sga}  - ${v.nome_marca} - ${v.nome_modelo} - ${v.ano} - ${v.nome_om}`;

  if (parseInt(v.id) === parseInt(f.id_viatura)) {
    opt.selected = true;
  }

  selectViatura.appendChild(opt);
});

// ==============================
// Preenche select de OM (Batalhão)
// ==============================
const selectOM = form.querySelector('#editar-id_om');
selectOM.innerHTML = '<option value="" disabled>Selecione o batalhão</option>';

data.oms.forEach(o => {
  const opt = document.createElement('option');
  opt.value = o.id;

  // Exibe abreviatura + nome (igual ao PHP)
  opt.textContent = `${o.abreviatura} - ${o.nome}`;

  if (parseInt(o.id) === parseInt(f.batalhao)) {
    opt.selected = true;
  }

  selectOM.appendChild(opt);
});


      // Demais campos
      form.querySelector('#editar-data_abertura').value = f.data_abertura || '';
      form.querySelector('#editar-data_prevista').value = f.data_prevista || '';
      form.querySelector('#editar-solicitante').value = f.solicitante || '';
      form.querySelector('#editar-motorista').value = f.motorista || '';
      form.querySelector('#editar-subunidade').value = f.subunidade || '';
      form.querySelector('#editar-destino').value = f.destino || '';
      form.querySelector('#editar-cidade').value = f.cidade || '';
      form.querySelector('#editar-chefe_apresentar').value = f.chefe_apresentar || '';
      form.querySelector('#editar-local_apresentar').value = f.local_apresentar || '';
      form.querySelector('#editar-horario_apresentar').value = f.horario_apresentar || '';
      form.querySelector('#editar-natureza').value = f.natureza || '';
      form.querySelector('#editar-status').value = f.status || '';

      form.querySelector('#editar-data_saida').value = f.data_saida || '';
      form.querySelector('#editar-hora_saida').value = f.hora_saida || '';
      form.querySelector('#editar-odo_saida').value = f.odo_saida || '';
      form.querySelector('#editar-data_retorno').value = f.data_retorno || '';
      form.querySelector('#editar-hora_retorno').value = f.hora_retorno || '';
      form.querySelector('#editar-odo_retorno').value = f.odo_retorno || '';
      form.querySelector('#editar-observacoes_pos_emprego').value = f.observacoes_pos_emprego || '';
    })
    .catch(err => swal("Erro de rede", err.message, "error"));
};

// Submissão
const formEditarFicha = document.getElementById('form-editar-ficha');
if (formEditarFicha) {
  formEditarFicha.addEventListener('submit', function (e) {
    e.preventDefault();
    const formData = new FormData(formEditarFicha);

    fetch('includes/sta_fichas/editar_ficha.php', {
      method: 'POST',
      body: formData
    })
    .then(res => res.json())
    .then(data => {
      if (data.status === 'sucesso') {
        swal("Sucesso!", data.mensagem, "success").then(() => {
          carregarPagina('includes/sta_fichas/listagem.php');
          fecharModalAberto();
        });

      } else if (data.status === 'confirmar') {
        swal({
          title: "Confirmação necessária",
          text: data.mensagem || "Existe ficha aberta nesse intervalo. Deseja continuar?",
          icon: "warning",
          buttons: ["Cancelar", "Sim, continuar"],
          dangerMode: true
        }).then((confirmado) => {
          if (confirmado) {
            formData.append('force', '1'); // Força envio
            fetch('includes/sta_fichas/editar_ficha.php', {
              method: 'POST',
              body: formData
            })
            .then(res => res.json())
            .then(data2 => {
              if (data2.status === 'sucesso') {
                swal("Sucesso!", data2.mensagem, "success").then(() => {
                  carregarPagina('includes/sta_fichas/listagem.php');
                  fecharModalAberto();
                });
              } else {
                swal("Erro!", data2.mensagem || "Erro ao editar ficha.", "error");
              }
            })
            .catch(err => swal("Erro de rede", err.message, "error"));
          }
        });

      } else {
        swal("Erro!", data.mensagem || "Erro ao editar ficha.", "error");
      }
    })
    .catch(err => swal("Erro de rede", err.message, "error"));
  });
}

window.verFicha = function (id) {
  fetch(`includes/sta_fichas/buscar_ficha_ver.php?id=${id}`)
    .then(res => res.json())
    .then(dados => {
      if (!dados.sucesso) {
        swal("Erro", "Erro ao carregar dados da ficha.", "error");
        return;
      }

      const ficha = dados.ficha || {};

      // Foto
      const img = document.querySelector('#modalVerFICHA #fotoViaturaFicha');
      if (img && ficha.foto_capa) {
        img.src = `uploads/frotas/${ficha.foto_capa}`;
      }

      // Dados da Viatura
      document.getElementById('verf_prefixo').innerText = ficha.prefixo_sga || '';
      document.getElementById('verf_chassi').innerText = ficha.chassi || '';
      document.getElementById('verf_marca').innerText = ficha.marca || '';
      document.getElementById('verf_modelo').innerText = ficha.modelo || '';

      // Dados principais da ficha
      document.getElementById('verf_data_abertura').innerText = formatarData(ficha.data_abertura);
      document.getElementById('verf_data_prevista').innerText = formatarData(ficha.data_prevista);
      document.getElementById('verf_solicitante').innerText = ficha.solicitante || '';
      document.getElementById('verf_motorista').innerText = ficha.motorista || '';
      document.getElementById('verf_subunidade').innerText = ficha.subunidade || '';
      document.getElementById('verf_destino').innerText = ficha.destino || '';
      document.getElementById('verf_cidade').innerText = ficha.cidade || '';
      document.getElementById('verf_om').innerText = ficha.nome_om || '';
      document.getElementById('verf_status').innerText = ficha.status || '';
      document.getElementById('verf_natureza').innerText = ficha.natureza || '';

      document.getElementById('verf_chefe_apresentar').innerText = ficha.chefe_apresentar || '';
      document.getElementById('verf_local_apresentar').innerText = ficha.local_apresentar || '';
      document.getElementById('verf_horario_apresentar').innerText = ficha.horario_apresentar || '';

      // Dados de retorno
      document.getElementById('verf_data_saida').innerText = formatarData(ficha.data_saida);
      document.getElementById('verf_hora_saida').innerText = ficha.hora_saida || '';
      document.getElementById('verf_odo_saida').innerText = ficha.odo_saida || '';
      document.getElementById('verf_data_retorno').innerText = formatarData(ficha.data_retorno);
      document.getElementById('verf_hora_retorno').innerText = ficha.hora_retorno || '';
      document.getElementById('verf_odo_retorno').innerText = ficha.odo_retorno || '';
      document.getElementById('verf_observacoes').innerText = ficha.observacoes_pos_emprego || '';

      // Logs
      const logsContainer = document.getElementById('verf_logsContainer');
      logsContainer.innerHTML = '';
      const logs = dados.logs || [];
      if (logs.length > 0) {
        logs.forEach(log => {
          logsContainer.innerHTML += `
            <div class="border rounded p-2 mb-2 small text-muted">
              <strong>${log.postograd || ''} - ${log.nomeguerra || 'Usuário'}</strong> realizou <strong>${log.acao || ''}</strong> em ${log.data_hora || ''}<br>
              <em>${log.descricao || ''}</em><br>
              <small>IP: ${log.ip || ''} | Navegador: ${log.navegador || ''}</small>
            </div>
          `;
        });
      } else {
        logsContainer.innerHTML = '<p class="text-muted">Nenhum log registrado.</p>';
      }

    })
    .catch(err => {
      console.error('Erro ao carregar dados da ficha:', err);
      swal("Erro", "Erro ao carregar ficha.", "error");
    });
};

// Utilitário para formatar data YYYY-MM-DD para DD/MM/AAAA
function formatarData(dataISO) {
  if (!dataISO) return '';
  const [ano, mes, dia] = dataISO.split('-');
  return `${dia}/${mes}/${ano}`;
}

    
};





// LISTAGEM DO EMPREGO DE VIATURAS
window.inicializarEmpregoFichas = function () {
    window.toggleFiltrosFichas = function() {
  const container = document.getElementById('filtros-container-fichas');
  if (container) {
    container.style.display = container.style.display === 'none' ? 'block' : 'none';
  }
};

    
    // Cadastrar ficha
  // Cadastrar ficha
const formFicha = document.getElementById('form-ficha-sta');

if (formFicha) {
  formFicha.addEventListener('submit', function (e) {
    e.preventDefault();
    const formData = new FormData(formFicha);

    function enviarFicha(force = false) {
      if (force) {
        formData.set('force', '1');
      }

      fetch('includes/sta_fichas/salvar_ficha.php', {
        method: 'POST',
        body: formData
      })
      .then(res => res.json())
      .then(data => {
        if (data.status === 'sucesso') {
          swal("Sucesso!", data.mensagem || "Ficha cadastrada com sucesso!", "success")
            .then(() => {
              carregarPagina('includes/sta_fichas/emprego.php');
              fecharModalAberto(); // fecha o modal
            });
        } else if (data.status === 'confirmar') {
          swal({
            title: "Conflito de Ficha!",
            text: data.mensagem || "Já existe uma ficha aberta neste intervalo. Deseja continuar mesmo assim?",
            icon: "warning",
            buttons: ["Cancelar", "Sim, cadastrar"],
            dangerMode: true,
          }).then((confirmado) => {
            if (confirmado) {
              enviarFicha(true); // reenviar com force
            }
          });
        } else {
          swal("Erro!", data.mensagem || "Erro ao cadastrar a ficha.", "error");
        }
      })
      .catch(err => {
        swal("Erro!", "Erro de rede: " + err.message, "error");
      });
    }

    enviarFicha(); // chamada inicial sem force
  });
}

    
    
   // FILTROS DA PÁGINA DE EMPREGO DAS FICHAS STA
const formFICHA = document.getElementById('filtroFichaForm');

function atualizarListaFICHAS(extraParams = {}) {
  if (!formFICHA) return;

  const formData = new FormData(formFICHA);
  const params = new URLSearchParams(formData);

  for (const key in extraParams) {
    params.set(key, extraParams[key]);
  }

  const url = `includes/sta_fichas/emprego.php?${params.toString()}`;
  carregarPagina(url);
}

// Submissão do formulário
if (formFICHA) {
  formFICHA.addEventListener('submit', function (e) {
    e.preventDefault();
    atualizarListaFICHAS({ pagina: 1 });
  });
}

// Seleção de limite
const limiteSelectFICHAS = document.getElementById('limiteFICHAS');
if (limiteSelectFICHAS) {
  limiteSelectFICHAS.addEventListener('change', function () {
    atualizarListaFICHAS({ pagina: 1, limite: this.value });
  });
}

// Botão limpar filtros
const btnLimparFiltrosFichas = document.getElementById('btnLimparFiltrosFichas');
if (btnLimparFiltrosFichas && formFICHA) {
  btnLimparFiltrosFichas.addEventListener('click', function (e) {
    e.preventDefault();
    formFICHA.reset();

    const limiteAtual = document.getElementById('limiteFICHAS')?.value || 10;
    const url = `includes/sta_fichas/emprego.php?pagina=1&limite=${limiteAtual}`;
    carregarPagina(url);
  });
}


window.editarFICHA = function (id) {
  const form = document.getElementById('form-editar-ficha');
  if (!form) return;

  fetch(`includes/sta_fichas/buscar_ficha.php?id=${id}`)
    .then(res => res.json())
    .then(data => {
      if (data.status !== 'sucesso') {
        swal("Erro", data.mensagem || "Erro ao carregar ficha.", "error");
        return;
      }

      const f = data.ficha;

      // Atualiza o ID oculto
      form.querySelector('#editar-ficha-id').value = id;

      // Preenche select de viaturas
      const selectViatura = form.querySelector('#editar-id_viatura');
      selectViatura.innerHTML = '<option value="" disabled>Selecione</option>';
      data.viaturas.forEach(v => {
        const opt = document.createElement('option');
        opt.value = v.id;
        opt.textContent = `${v.prefixo_sga} - ${v.modelo}`;
        if (v.id == f.id_viatura) opt.selected = true;
        selectViatura.appendChild(opt);
      });

      // Preenche select de OM
      const selectOM = form.querySelector('#editar-id_om');
      selectOM.innerHTML = '<option value="" disabled>Selecione</option>';
      data.oms.forEach(o => {
        const opt = document.createElement('option');
        opt.value = o.id;
        opt.textContent = o.nome;
        if (o.id == f.id_om) opt.selected = true;
        selectOM.appendChild(opt);
      });

      // Demais campos
      form.querySelector('#editar-data_abertura').value = f.data_abertura || '';
      form.querySelector('#editar-data_prevista').value = f.data_prevista || '';
      form.querySelector('#editar-solicitante').value = f.solicitante || '';
      form.querySelector('#editar-motorista').value = f.motorista || '';
      form.querySelector('#editar-subunidade').value = f.subunidade || '';
      form.querySelector('#editar-destino').value = f.destino || '';
      form.querySelector('#editar-cidade').value = f.cidade || '';
      form.querySelector('#editar-chefe_apresentar').value = f.chefe_apresentar || '';
      form.querySelector('#editar-local_apresentar').value = f.local_apresentar || '';
      form.querySelector('#editar-horario_apresentar').value = f.horario_apresentar || '';
      form.querySelector('#editar-natureza').value = f.natureza || '';
      form.querySelector('#editar-status').value = f.status || '';

      form.querySelector('#editar-data_saida').value = f.data_saida || '';
      form.querySelector('#editar-hora_saida').value = f.hora_saida || '';
      form.querySelector('#editar-odo_saida').value = f.odo_saida || '';
      form.querySelector('#editar-data_retorno').value = f.data_retorno || '';
      form.querySelector('#editar-hora_retorno').value = f.hora_retorno || '';
      form.querySelector('#editar-odo_retorno').value = f.odo_retorno || '';
      form.querySelector('#editar-observacoes_pos_emprego').value = f.observacoes_pos_emprego || '';
    })
    .catch(err => swal("Erro de rede", err.message, "error"));
};

// Submissão
const formEditarFicha = document.getElementById('form-editar-ficha');
if (formEditarFicha) {
  formEditarFicha.addEventListener('submit', function (e) {
    e.preventDefault();
    const formData = new FormData(formEditarFicha);

    fetch('includes/sta_fichas/editar_ficha.php', {
      method: 'POST',
      body: formData
    })
    .then(res => res.json())
    .then(data => {
      if (data.status === 'sucesso') {
        swal("Sucesso!", data.mensagem, "success").then(() => {
          carregarPagina('includes/sta_fichas/emprego.php');
          fecharModalAberto();
        });

      } else if (data.status === 'confirmar') {
        swal({
          title: "Confirmação necessária",
          text: data.mensagem || "Existe ficha aberta nesse intervalo. Deseja continuar?",
          icon: "warning",
          buttons: ["Cancelar", "Sim, continuar"],
          dangerMode: true
        }).then((confirmado) => {
          if (confirmado) {
            formData.append('force', '1'); // Força envio
            fetch('includes/sta_fichas/editar_ficha.php', {
              method: 'POST',
              body: formData
            })
            .then(res => res.json())
            .then(data2 => {
              if (data2.status === 'sucesso') {
                swal("Sucesso!", data2.mensagem, "success").then(() => {
                  carregarPagina('includes/sta_fichas/emprego.php');
                  fecharModalAberto();
                });
              } else {
                swal("Erro!", data2.mensagem || "Erro ao editar ficha.", "error");
              }
            })
            .catch(err => swal("Erro de rede", err.message, "error"));
          }
        });

      } else {
        swal("Erro!", data.mensagem || "Erro ao editar ficha.", "error");
      }
    })
    .catch(err => swal("Erro de rede", err.message, "error"));
  });
}


window.verFicha = function (id) {
  fetch(`includes/sta_fichas/buscar_ficha_ver.php?id=${id}`)
    .then(res => res.json())
    .then(dados => {
      if (!dados.sucesso) {
        swal("Erro", "Erro ao carregar dados da ficha.", "error");
        return;
      }

      const ficha = dados.ficha || {};

      // Foto
      const img = document.querySelector('#modalVerFICHA #fotoViaturaFicha');
      if (img && ficha.foto_capa) {
        img.src = `uploads/frotas/${ficha.foto_capa}`;
      }

      // Dados da Viatura
      document.getElementById('verf_prefixo').innerText = ficha.prefixo_sga || '';
      document.getElementById('verf_chassi').innerText = ficha.chassi || '';
      document.getElementById('verf_marca').innerText = ficha.marca || '';
      document.getElementById('verf_modelo').innerText = ficha.modelo || '';

      // Dados principais da ficha
      document.getElementById('verf_data_abertura').innerText = formatarData(ficha.data_abertura);
      document.getElementById('verf_data_prevista').innerText = formatarData(ficha.data_prevista);
      document.getElementById('verf_solicitante').innerText = ficha.solicitante || '';
      document.getElementById('verf_motorista').innerText = ficha.motorista || '';
      document.getElementById('verf_subunidade').innerText = ficha.subunidade || '';
      document.getElementById('verf_destino').innerText = ficha.destino || '';
      document.getElementById('verf_cidade').innerText = ficha.cidade || '';
      document.getElementById('verf_om').innerText = ficha.nome_om || '';
      document.getElementById('verf_status').innerText = ficha.status || '';
      document.getElementById('verf_natureza').innerText = ficha.natureza || '';

      document.getElementById('verf_chefe_apresentar').innerText = ficha.chefe_apresentar || '';
      document.getElementById('verf_local_apresentar').innerText = ficha.local_apresentar || '';
      document.getElementById('verf_horario_apresentar').innerText = ficha.horario_apresentar || '';

      // Dados de retorno
      document.getElementById('verf_data_saida').innerText = formatarData(ficha.data_saida);
      document.getElementById('verf_hora_saida').innerText = ficha.hora_saida || '';
      document.getElementById('verf_odo_saida').innerText = ficha.odo_saida || '';
      document.getElementById('verf_data_retorno').innerText = formatarData(ficha.data_retorno);
      document.getElementById('verf_hora_retorno').innerText = ficha.hora_retorno || '';
      document.getElementById('verf_odo_retorno').innerText = ficha.odo_retorno || '';
      document.getElementById('verf_observacoes').innerText = ficha.observacoes_pos_emprego || '';

      // Logs
      const logsContainer = document.getElementById('verf_logsContainer');
      logsContainer.innerHTML = '';
      const logs = dados.logs || [];
      if (logs.length > 0) {
        logs.forEach(log => {
          logsContainer.innerHTML += `
            <div class="border rounded p-2 mb-2 small text-muted">
              <strong>${log.postograd || ''} - ${log.nomeguerra || 'Usuário'}</strong> realizou <strong>${log.acao || ''}</strong> em ${log.data_hora || ''}<br>
              <em>${log.descricao || ''}</em><br>
              <small>IP: ${log.ip || ''} | Navegador: ${log.navegador || ''}</small>
            </div>
          `;
        });
      } else {
        logsContainer.innerHTML = '<p class="text-muted">Nenhum log registrado.</p>';
      }

    })
    .catch(err => {
      console.error('Erro ao carregar dados da ficha:', err);
      swal("Erro", "Erro ao carregar ficha.", "error");
    });
};

window.verTodasFichas = function (id_viatura) {
  fetch(`includes/sta_fichas/buscar_todas_fichas.php?id_viatura=${id_viatura}`)
    .then(res => res.json())
    .then(dados => {
      if (!dados.sucesso) {
        swal("Erro", "Erro ao carregar dados da viatura e fichas.", "error");
        return;
      }

      const viatura = dados.viatura;
      const fichas = dados.fichas;

      // Preenche dados da viatura
      document.getElementById('todasf_prefixo').innerText = viatura.prefixo_sga || '';
      document.getElementById('todasf_chassi').innerText = viatura.chassi || '';
      document.getElementById('todasf_marca').innerText = viatura.marca || '';
      document.getElementById('todasf_modelo').innerText = viatura.modelo || '';

      const img = document.querySelector('#modalTodasFichas #fotoViaturaTodasFichas');
      if (img) {
        img.src = viatura.foto_capa ? `uploads/frotas/${viatura.foto_capa}` : '';
      }

      // Preenche fichas
      const container = document.getElementById('todasFichasContainer');
      container.innerHTML = '';

      if (!fichas || fichas.length === 0) {
        container.innerHTML = '<p class="text-muted">Nenhuma ficha registrada para esta viatura.</p>';
        return;
      }

      fichas.forEach(f => {
        const statusClass = f.status === 'Aberta' ? 'warning'
                          : f.status === 'Encerrada' ? 'success'
                          : 'secondary';
        container.innerHTML += `
          <div class="border rounded p-3 mb-3 bg-white shadow-sm">
            <div class="row align-items-center gy-2">
              <div class="col-12 col-md-3">
                <strong>Ficha #${f.id}</strong>
              </div>
              <div class="col-6 col-md-3">
                <span class="text-muted small">Abertura</span><br>
                ${formatarData(f.data_abertura)}
              </div>
              <div class="col-6 col-md-3">
                <span class="text-muted small">Prevista</span><br>
                ${formatarData(f.data_prevista)}
              </div>
              <div class="col-12 col-md-3 text-end">
                <span class="badge bg-${statusClass}">${f.status}</span>
              </div>
            </div>
          </div>
        `;
      });

    })
    .catch(err => {
      console.error("Erro ao buscar fichas:", err);
      swal("Erro", "Erro ao carregar as fichas.", "error");
    });
}

   //DELETAR FICHA
    window.deletarFicha = function(botao) {
  const id = botao.getAttribute('data-id');

  Swal.fire({
    title: 'Deletar Ficha?',
    text: "Essa ação não poderá ser desfeita. Deseja realmente excluir esta Ficha?",
    icon: 'warning',
    showCancelButton: true,
    confirmButtonText: 'Sim, deletar',
    cancelButtonText: 'Cancelar'
  }).then((result) => {
    if (result.isConfirmed) {
      const formData = new FormData();
      formData.append('id', id);

      fetch('includes/sta_fichas/deletar_ficha.php', {
        method: 'POST',
        body: formData
      })
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          Swal.fire({
            icon: 'success',
            title: 'Deletado!',
            text: 'Ficha excluída com sucesso.',
            timer: 1500,
            showConfirmButton: false
          }).then(() => {
            carregarPagina('includes/sta_fichas/emprego.php');
          });
        } else {
          Swal.fire({
            icon: 'error',
            title: 'Erro',
            text: data.message || 'Erro ao deletar Ficha.'
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

// Delega evento para todos os botões (opcional se usar `onclick`)
document.querySelectorAll('.btn-deletar-ficha').forEach(botao => {
  botao.addEventListener('click', function () {
    deletarFicha(this);
  });
});

// Utilitário para formatar data YYYY-MM-DD para DD/MM/AAAA
function formatarData(dataISO) {
  if (!dataISO) return '';
  const [ano, mes, dia] = dataISO.split('-');
  return `${dia}/${mes}/${ano}`;
}

    
};

