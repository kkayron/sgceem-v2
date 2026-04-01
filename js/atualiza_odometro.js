window.inicializarAtualizarOdometro = function() {
    
     // FORM: Importar Odômetros/Horímetros via Planilha
const formImportarOdometro = document.getElementById('formImportarOdometro');
if (formImportarOdometro) {
  formImportarOdometro.addEventListener('submit', function (e) {
    e.preventDefault();
    const formData = new FormData(formImportarOdometro);

    fetch('includes/odometro/importar_odometro.php', {
      method: 'POST',
      body: formData
    })
    .then(res => res.json())
    .then(data => {
      if (data.status === 'ok') {
        // Montar detalhes adicionais de casamentos e falhas
        let detalhes = "";
        if (data.casamentos && data.casamentos.length > 0) {
          detalhes += "\n\n✔️ Casamentos:\n" + data.casamentos.join("\n");
        }
        if (data.falhas && data.falhas.length > 0) {
          detalhes += "\n\n❌ Falhas:\n" + data.falhas.join("\n");
        }

        swal({
          title: "Importação concluída!",
          text: data.mensagem + detalhes,
          icon: "success",
          button: {
            text: "OK",
            className: "btn btn-success"
          }
        }).then(() => {
          carregarPagina('includes/odometro/controle.php'); // ajuste conforme sua página
          fecharModalAberto();
        });

      } else {
        swal({
          title: "Erro na importação!",
          text: data.mensagem || "Verifique o arquivo e tente novamente.",
          icon: "error",
          button: {
            text: "Fechar",
            className: "btn btn-danger"
          }
        });
      }
    })
    .catch(err => {
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
  });
}

  // FILTROS DO CONTROLE DOS ODÔMETROS
  const formFiltroData = document.getElementById('formFiltroData');
  if (formFiltroData) {
    formFiltroData.addEventListener('submit', function(e) {
      e.preventDefault();
      const formData = new FormData(formFiltroData);
      const params = new URLSearchParams(formData);
      const url = `includes/odometro/controle.php?${params.toString()}`;
      carregarPagina(url);
    });
  }

  // IR PARA PÁGINA DE ATUALIZAR ODÔMETROS
  const btnEditar = document.getElementById('AcessarEditar');
  if (btnEditar && formFiltroData) {
    btnEditar.addEventListener('click', function() {
      const formData = new FormData(formFiltroData);
      const params = new URLSearchParams(formData);
      const url = `includes/odometro/editar.php?${params.toString()}`;
      carregarPagina(url);
    });
  }

  // FILTRO DE DATA NA PÁGINA DE EDIÇÃO DO ODOMETRO
  const formFiltroDataEditar = document.getElementById('formFiltroDataEditar');
  if (formFiltroDataEditar) {
    formFiltroDataEditar.addEventListener('submit', function(e) {
      e.preventDefault();
      const formData = new FormData(formFiltroDataEditar);
      const params = new URLSearchParams(formData);
      const url = `includes/odometro/editar.php?${params.toString()}`;
      carregarPagina(url);
    });
  }

  // LIMPAR FILTROS DA PÁGINA DE CONTROLE
  const btnLimparODO = document.getElementById('btnLimparFiltrosdoCtrlODO');
  if (btnLimparODO) {
    btnLimparODO.addEventListener('click', function() {
      carregarPagina('includes/odometro/controle.php');
    });
  }
    
 // Paginação AJAX para todos os accordions
document.querySelectorAll('.accordion-page').forEach(link => {
    link.addEventListener('click', function(e){
        e.preventDefault();
        const container = document.getElementById('accordionBody' + this.dataset.viatura);
        if (!container) return;

        // Aqui usamos o data-page-url já completo
        const url = this.dataset.pageUrl;

        fetch(url)
            .then(res => res.text())
            .then(html => {
                container.innerHTML = html;

                // Reaplicar evento de paginação dentro do novo HTML carregado
                container.querySelectorAll('.accordion-page').forEach(newLink => {
                    newLink.addEventListener('click', arguments.callee);
                });
            });
    });
});
  // BOTÃO "EDITAR" NA PÁGINA DE EDIÇÃO (VOLTA PARA CONTROLE)
  const btnControle = document.getElementById('AcessarControle');
  if (btnControle && formFiltroDataEditar) {
    btnControle.addEventListener('click', function() {
      const formData = new FormData(formFiltroDataEditar);
      const params = new URLSearchParams(formData);
      const url = `includes/odometro/controle.php?${params.toString()}`;
      carregarPagina(url);
    });
  }

  // SALVAR ODOMETROS E HORIMETROS
  const formEditarMedicoes = document.querySelector('#formMedicoes');
  if (formEditarMedicoes) {
    formEditarMedicoes.addEventListener('submit', function(e) {
      e.preventDefault();

      Swal.fire({
        title: 'Salvar medições?',
        text: "Deseja realmente salvar os dados informados?",
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Sim, salvar',
        cancelButtonText: 'Cancelar'
      }).then((result) => {
        if (result.isConfirmed) {
          const formData = new FormData(formEditarMedicoes);
          const dataSelecionada = formData.get('data');

          fetch('includes/odometro/salvar_medicoes.php', {
            method: 'POST',
            body: formData
          })
          .then(response => response.json())
          .then(data => {
            if (data.success) {
              Swal.fire({
                icon: 'success',
                title: 'Sucesso!',
                text: 'Medições salvas com sucesso.',
                timer: 1500,
                showConfirmButton: false
              }).then(() => {
                const url = `includes/odometro/controle.php?data=${encodeURIComponent(dataSelecionada)}`;
                carregarPagina(url);
              });
            } else {
              Swal.fire({
                icon: 'error',
                title: 'Erro',
                text: data.message || 'Erro ao salvar medições.'
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
    });
  }
    
 // DELETAR MEDIÇÃO DE ODÔMETRO/HORÍMETRO
window.deletarMedicao = function(botao) {
    const viaturaId = botao.getAttribute('data-viatura');
    const data = botao.getAttribute('data-data');

    Swal.fire({
        title: 'Excluir Medição?',
        text: "Essa ação não poderá ser desfeita. Deseja realmente excluir esta medição?",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sim, excluir',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            fetch('includes/odometro/excluir_medicao.php', {
                method: 'POST', 
                headers: { 'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest' },
                body: JSON.stringify({ viatura_id: viaturaId, data: data })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Excluída!',
                        text: 'Medição excluída com sucesso.',
                        timer: 1500,
                        showConfirmButton: false
                    }).then(() => {
                        // Atualiza o histórico sem recarregar a página
                        atualizarHistoricoViatura(viaturaId);
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Erro',
                        text: data.message || 'Erro ao excluir medição.'
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

// Função para recarregar o histórico da viatura
function atualizarHistoricoViatura(viaturaId) {
    const container = document.getElementById(`accordionBody${viaturaId}`);
    if (!container) return;

    // Mantém os filtros atuais da página
    const params = new URLSearchParams(window.location.search);
    params.set('viatura_id', viaturaId);

    fetch(`includes/odometro/controle_accordion.php?${params.toString()}`)
        .then(response => response.text())
        .then(html => {
            container.innerHTML = html;

            // Reaplica os eventos de deletar nos novos botões carregados
            container.querySelectorAll('.btn-deletar-medicao').forEach(botao => {
                botao.addEventListener('click', function() {
                    deletarMedicao(this);
                });
            });
        })
        .catch(error => {
            console.error('Erro ao atualizar histórico:', error);
        });
}

// Delegar evento para todos os botões existentes
document.querySelectorAll('.btn-deletar-medicao').forEach(botao => {
    botao.addEventListener('click', function() {
        deletarMedicao(this);
    });
});

   
   
};

window.inicializarPreventiva = function() {
    
    
    // ------------------------------------------
// TOGGLE DOS FILTROS
// ------------------------------------------
 window.toggleFiltrosFichas = function() {
    const container = document.getElementById('filtros-container-fichas');
    if (container) {
      container.style.display = container.style.display === 'none' ? 'block' : 'none';
    }
  };

// ------------------------------------------
// FILTROS DA PÁGINA PREVENTIVA
// ------------------------------------------
const formFichas = document.getElementById('filtroFichaForm');

function atualizarListaFichas(extraParams = {}) {
  if (!formFichas) return;

  const formData = new FormData(formFichas);
  const params = new URLSearchParams();

  // Adiciona somente os campos preenchidos
  for (const [key, value] of formData.entries()) {
    if (value !== "" && value !== null) {
      params.append(key, value);
    }
  }

  // Aplica parâmetros extras (força substituição)
  for (const key in extraParams) {
    params.set(key, extraParams[key]);
  }

  const url = `includes/preventiva/listagem.php?${params.toString()}`;

  // Evita recarregar a mesma coisa duas vezes
  if (window.__ultimaURLPreventiva !== url) {
    window.__ultimaURLPreventiva = url;
    carregarPagina(url);
  }
}

// Submissão do formulário de filtros
if (formFichas) {
  formFichas.addEventListener('submit', function (e) {
    e.preventDefault();
    atualizarListaFichas({ pagina: 1 });
  });
}


// Mudança de limite por página (caso tenha select de limite)
const limiteSelectFichas = document.getElementById('limiteFichas');
if (limiteSelectFichas) {
  limiteSelectFichas.addEventListener('change', function () {
    atualizarListaFichas({ pagina: 1, limite: this.value });
  });
}

// Botão "Limpar Filtros"
const btnLimparFichas = document.getElementById('btnLimparFiltrosFichas');
if (btnLimparFichas && formFichas) {
  btnLimparFichas.addEventListener('click', function (e) {
    e.preventDefault();
    formFichas.reset();

    // Redefine para primeira página e limite padrão
    const limiteAtual = document.getElementById('limiteFichas')?.value || 10;
    const url = `includes/preventiva/listagem.php?pagina=1&limite=${limiteAtual}`;
    carregarPagina(url); // AJAX sem filtros
  });
}

    

// Exportar PDF do controle da preventiva
(function(){
  const btn = document.getElementById('btnExportarPDFpreventiva');
  if (!btn) return;

  function getVal(id, name) {
    const elById = document.querySelector(id);
    if (elById) return elById.value || '';
    const elByName = document.querySelector('[name="'+name+'"]');
    return elByName ? elByName.value || '' : '';
  }

  btn.addEventListener('click', function (e) {
    e.preventDefault();
    e.stopPropagation();

    const filtros = {
      id: getVal('#filtroId','id'),
      tipo: getVal('#filtroTipo','tipo'),
      batalhao: getVal('#filtrobatalhao','batalhao'),
      modelo: getVal('#filtroModelo','modelo'),
      marca: getVal('#filtroMarca','marca'),
      ativo: getVal('#filtroAtivo','ativo'),
      prefixo_sga: getVal('#filtroPrefixo','prefixo_sga'),
      nmr_patrimonio: getVal('#filtroPatrimonio','nmr_patrimonio'),
      chassi: getVal('#filtroChassi','chassi'),
      acervo: getVal('#filtroAcervo','acervo'),
      emprego_atual: getVal('#filtroEmpregoAtual','emprego_atual'),
      subunidade: getVal('#filtroSubunidade','subunidade'),
      disponibilidade: getVal('#filtroDisponibilidade','disponibilidade'),
      confiabilidade: getVal('#filtroConfiabilidade','confiabilidade'),
      status_manut: getVal('#filtroStatusManut','status_manut')
    };

    const params = new URLSearchParams();
    Object.keys(filtros).forEach(k => {
      if (filtros[k] !== '' && filtros[k] !== null) params.set(k, filtros[k]);
    });

    const url = 'pdf/gerar_preventiva.php?' + params.toString();

    // Abre nova janela com mensagem + iframe do PDF
    const win = window.open('', '_blank');
    win.document.write(`
      <html>
      <head>
        <title>Gerando PDF...</title>
        <style>
          body { font-family: Arial, sans-serif; text-align:center; margin:0; }
          .loader {
            margin-top: 20vh;
          }
          .spinner {
            border: 6px solid #f3f3f3;
            border-top: 6px solid #0d6efd;
            border-radius: 50%;
            width: 50px;
            height: 50px;
            animation: spin 1s linear infinite;
            margin: 20px auto;
          }
          @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
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
        <div class="loader">
          <h3>PDF carregando...</h3>
          <div class="spinner"></div>
        </div>
        <iframe src="${url}" onload="this.style.display='block';document.querySelector('.loader').style.display='none';"></iframe>
      </body>
      </html>
    `);
  });
})();

// Exportar excel

(function(){
  const btn = document.getElementById('btnExportarExcelPreventiva');
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
      tipo: getVal('#filtroTipo','tipo'),
      batalhao: getVal('#filtrobatalhao','batalhao'),
      modelo: getVal('#filtroModelo','modelo'),
      marca: getVal('#filtroMarca','marca'),
      ativo: getVal('#filtroAtivo','ativo'),
      prefixo_sga: getVal('#filtroPrefixo','prefixo_sga'),
      nmr_patrimonio: getVal('#filtroPatrimonio','nmr_patrimonio'),
      chassi: getVal('#filtroChassi','chassi'),
      acervo: getVal('#filtroAcervo','acervo'),
      emprego_atual: getVal('#filtroEmpregoAtual','emprego_atual'),
      subunidade: getVal('#filtroSubunidade','subunidade'),
      disponibilidade: getVal('#filtroDisponibilidade','disponibilidade'),
      confiabilidade: getVal('#filtroConfiabilidade','confiabilidade'),
      status_manut: getVal('#filtroStatusManut','status_manut')
    };

    const params = new URLSearchParams();
    Object.keys(filtros).forEach(k => {
      if (filtros[k] !== '' && filtros[k] !== null) params.set(k, filtros[k]);
    });

    const url = 'excel/exportar_preventiva.php?' + params.toString();
    window.open(url, '_blank'); // abre nova aba com Excel
  });
})();

    };