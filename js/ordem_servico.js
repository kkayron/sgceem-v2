// JavaScript Document
window.inicializarOrdemServico = function () {
    
// Exportar OS com filtros ativos (pegando direto dos inputs)
document.getElementById('btnExportarExcelOS').addEventListener('click', function(e) {
  e.preventDefault();

  const params = new URLSearchParams();

  // Mapeia nomes dos campos HTML → nomes esperados no PHP
  const mapaFiltros = {
    'batalhao': 'batalhao',
    'status': 'status',
    'tipomnt': 'tipo_mnt',      
    'prefixo': 'prefixo_sga',   
    'solicitante': 'solicitante',
    'texto': 'texto',
    'data_ini': 'data_ini',
    'data_fim': 'data_fim'
  };

  // Percorre os campos e adiciona à URL se tiver valor
  for (const [inputName, paramName] of Object.entries(mapaFiltros)) {
    const el = document.querySelector(`[name="${inputName}"]`);
    if (el && el.value.trim() !== '') {
      params.append(paramName, el.value.trim());
    }
  }

  // Monta URL de exportação com filtros
  const urlExportacao = 'includes/os/exportar_os_excel.php' + (params.toString() ? '?' + params.toString() : '');

  // Faz o download
  window.location.href = urlExportacao;
});

    
// Importar Ordens de Serviço
const formImportarOS = document.getElementById('formImportarOS');

if (formImportarOS) {
  formImportarOS.addEventListener('submit', function(e) {
    e.preventDefault();

    const formData = new FormData(formImportarOS);

    fetch('includes/os/importar_os.php', {
      method: 'POST',
      body: formData
    })
    .then(res => res.json())
    .then(data => {
      if (data.status === 'ok') {
        let mensagem = data.mensagem;

        // Se houver falhas, adiciona os detalhes
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
          carregarPagina('includes/os/listagem.php');
          fecharModalAberto();
        });

      } else {
        let erroMsg = data.mensagem;

        // Se houver falhas detalhadas mesmo no status de erro
        if (data.falhas && data.falhas.length > 0) {
          erroMsg += "\n\nFalhas detalhadas:\n" + data.falhas.map(f => 
            `Linha ${f.linha}: ${f.erro}`
          ).join("\n");
        }

        swal({
          title: "Erro na importação!",
          text: erroMsg || "Verifique o arquivo e tente novamente.",
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

    
    
    
// Script do botão Baixar PDF com overlay estilizado
document.getElementById('btnBaixarPDF').addEventListener('click', function() {
    const osId = document.getElementById('os_numero').innerText;
    if (!osId) {
        alert("ID da OS inválido.");
        return;
    }

    const url = `pdf/ver_os_principal.php?id=${osId}`;

    // Abre nova aba
    const win = window.open('', '_blank');
    win.document.write(`
        <html>
        <head>
            <title>Gerando PDF...</title>
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
});

    
// SCRIPT PARA ABRIR "VER OS PREENCHIDA" EM NOVA ABA COM LOADER MODERNO
(function(){
  document.querySelectorAll('.btnExportarPDFveros').forEach(btn => {
    btn.addEventListener('click', function(e){
      e.preventDefault();
      e.stopPropagation();

      const id = this.getAttribute('data-id');
      if (!id) {
        alert("ID da OS inválido.");
        return;
      }

      const url = 'pdf/ver_os.php?id=' + encodeURIComponent(id);

      const win = window.open('', '_blank');
      win.document.write(`
        <html>
        <head>
          <title>Ver OS Preenchida</title>
          <style>
            body {
              font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
              display: flex;
              justify-content: center;
              align-items: center;
              height: 100vh;
              margin: 0;
              background: #f5f7fa;
              color: #333;
            }
            .loader-box {
              text-align: center;
            }
            .spinner {
              width: 60px;
              height: 60px;
              border: 6px solid #e0e0e0;
              border-top: 6px solid #0d6efd;
              border-radius: 50%;
              animation: spin 1s linear infinite;
              margin: 20px auto;
            }
            @keyframes spin {
              0% { transform: rotate(0deg); }
              100% { transform: rotate(360deg); }
            }
            iframe {
              display: none;
              width: 100%;
              height: 100vh;
              border: none;
            }
            h3 {
              font-weight: 400;
              font-size: 1.2rem;
              margin: 0;
            }
          </style>
        </head>
        <body>
          <div class="loader-box">
            <h3>Carregando PDF...</h3>
            <div class="spinner"></div>
          </div>
          <iframe src="${url}" 
                  onload="this.style.display='block';document.querySelector('.loader-box').style.display='none';">
          </iframe>
        </body>
        </html>
      `);
    });
  });
})();

// GERAR OS PDF PARA PREENCHIMENTO COM ESTILO MODERNO
document.querySelectorAll('.btnExportarPDFos').forEach(btn => {
  btn.addEventListener('click', function (e) {
    e.preventDefault();
    e.stopPropagation();

    const id = btn.getAttribute("data-id");
    if (!id) {
      alert("ID da OS não encontrado!");
      return;
    }

    const url = 'pdf/gerar_os.php?id=' + encodeURIComponent(id);

    const win = window.open('', '_blank');
    win.document.write(`
      <html>
      <head>
        <title>Carregando PDF...</title>
        <style>
          body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
            background: #f5f7fa;
            color: #333;
          }
          .loader-box {
            text-align: center;
          }
          .spinner {
            width: 60px;
            height: 60px;
            border: 6px solid #e0e0e0;
            border-top: 6px solid #dc3545;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin: 20px auto;
          }
          @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
          }
          iframe {
            display: none;
            width: 100%;
            height: 100vh;
            border: none;
          }
          h3 {
            font-weight: 400;
            font-size: 1.2rem;
            margin: 0;
          }
        </style>
      </head>
      <body>
        <div class="loader-box">
          <h3>Carregando PDF...</h3>
          <div class="spinner"></div>
        </div>
        <iframe src="${url}" 
                onload="this.style.display='block';document.querySelector('.loader-box').style.display='none';">
        </iframe>
      </body>
      </html>
    `);
  });
});

    
   // TOGGLEFILTRO
  window.toggleFiltrosOS = function() {
    const container = document.getElementById('filtros-container-os');
    if (container) {
      container.style.display = container.style.display === 'none' ? 'block' : 'none';
    }
  };

    // FILTROS DA PÁGINA
const formOS = document.getElementById('filtroOSForm');

function atualizarListaOS(extraParams = {}) {
  if (!formOS) return;

  const formData = new FormData(formOS);
  const params = new URLSearchParams(formData);

  for (const key in extraParams) {
    params.set(key, extraParams[key]);
  }

  const url = `includes/os/listagem.php?${params.toString()}`;
  carregarPagina(url); // função AJAX específica para OS
}

// Submissão do formulário
if (formOS) {
  formOS.addEventListener('submit', function (e) {
    e.preventDefault();
    atualizarListaOS({ pagina: 1 });
  });
}

// Mudança de limite por página
const limiteSelectOS = document.getElementById('limiteOS');
if (limiteSelectOS) {
  limiteSelectOS.addEventListener('change', function () {
    atualizarListaOS({ pagina: 1, limite: this.value });
  });
}

// Botão "Limpar Filtros"
const btnLimparOS = document.getElementById('btnLimparFiltrosOS');
if (btnLimparOS && formOS) {
  btnLimparOS.addEventListener('click', function (e) {
    e.preventDefault();
    formOS.reset();

    const limiteAtual = document.getElementById('limiteOS')?.value || 10;
    const url = 'includes/os/listagem.php?pagina=1&limite=${limiteAtual}';
    carregarPagina(url); // AJAX sem filtros
  });
}

 // ABRIR ORDEM DE SERVIÇO
const formAbrirOS = document.getElementById('form-os-abrir');
if (formAbrirOS) {
  formAbrirOS.addEventListener('submit', function (e) {
    e.preventDefault();
    const formData = new FormData(formAbrirOS);

    fetch('includes/os/abrir_os.php', {
      method: 'POST',
      body: formData
    })
    .then(res => res.json())
    .then(data => {
      if (data.status === 'sucesso') {
        swal({
          title: "Sucesso!",
          text: data.mensagem || "Ordem de serviço registrada com sucesso!",
          icon: "success",
          button: {
            text: "OK",
            className: "btn btn-success"
          }
        }).then(() => {
          carregarPagina('includes/os/listagem.php');
          fecharModalAberto();
        });
      } else {
        swal({
          title: "Erro!",
          text: data.mensagem || "Erro ao registrar ordem de serviço.",
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
  }); // <-- ESTA CHAVE FECHA O formAbrirOS.addEventListener
} // <-- ESTA CHAVE FECHA O if (formAbrirOS)

// CARREGAR PLANOS DE MANUTENÇÃO DA VIATURA NA OS
const selectFrotaOS = document.getElementById('id_frota');
const blocoMntProgramada = document.getElementById('bloco-mnt-programada');
const areaPlanosMntOS = document.getElementById('area-planos-mnt-os');

if (selectFrotaOS) {
  selectFrotaOS.addEventListener('change', function () {
    const idFrota = this.value;

    if (!idFrota || !areaPlanosMntOS) return;

    blocoMntProgramada.style.display = 'block';

    areaPlanosMntOS.innerHTML = `
      <div class="text-center py-3">
        <div class="spinner-border text-warning" role="status"></div>
        <div class="mt-2 text-muted">Carregando manutenções programadas...</div>
      </div>
    `;

    fetch(`includes/os/buscar_planos_mnt_frota.php?id_frota=${idFrota}`)
      .then(res => res.text())
      .then(html => {
        areaPlanosMntOS.innerHTML = html;
      })
      .catch(err => {
        areaPlanosMntOS.innerHTML = `
          <div class="alert alert-danger mb-0">
            Erro ao carregar planos de manutenção: ${err.message}
          </div>
        `;
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

// Funções para adicionar campos dinâmicos com labels
window.adicionarFalha = function () {
  document.getElementById('falhasContainer').insertAdjacentHTML('beforeend', `
    <div class="row g-2 mb-2">
      <div class="col">
        <label class="form-label small mb-0">Seção</label>
        <input type="text" name="sec[]" class="form-control" placeholder="Seção">
      </div>
      <div class="col">
        <label class="form-label small mb-0">Falha identificada</label>
        <input type="text" name="falha[]" class="form-control" placeholder="Falha identificada">
      </div>
      <div class="col">
        <label class="form-label small mb-0">Militar que identificou</label>
        <input type="text" name="militar[]" class="form-control" placeholder="Militar que identificou">
      </div>
      <div class="col-auto d-flex align-items-end">
        <button type="button" class="btn btn-danger" onclick="this.closest('.row').remove()">🗑️</button>
      </div>
    </div>
  `);
};

window.adicionarPessoal = function () {
  document.getElementById('pessoalContainer').insertAdjacentHTML('beforeend', `
    <div class="row g-2 mb-2">
      <div class="col">
        <label class="form-label small mb-0">Graduação</label>
        <input type="text" name="grad[]" class="form-control" placeholder="Graduação">
      </div>
      <div class="col">
        <label class="form-label small mb-0">Nome de Guerra</label>
        <input type="text" name="nome[]" class="form-control" placeholder="Nome de Guerra">
      </div>
      <div class="col">
        <label class="form-label small mb-0">Função</label>
        <input type="text" name="funcao[]" class="form-control" placeholder="Função">
      </div>
      <div class="col">
        <label class="form-label small mb-0">Data</label>
        <input type="date" name="data[]" class="form-control">
      </div>
      <div class="col">
        <label class="form-label small mb-0">Serviço</label>
        <input type="text" name="servico[]" class="form-control" placeholder="Serviço">
      </div>
      <div class="col-auto d-flex align-items-end">
        <button type="button" class="btn btn-danger" onclick="this.closest('.row').remove()">🗑️</button>
      </div>
    </div>
  `);
};

// Função para adicionar novo serviço
window.adicionarServico = function () {
  const container = document.getElementById('servicosContainer');
  container.insertAdjacentHTML('beforeend', `
    <div class="row g-2 mb-2 align-items-end">
      <div class="col">
        <label class="form-label small mb-0">Empresa</label>
        <input type="text" name="empresa[]" class="form-control" placeholder="Empresa">
      </div>
      <div class="col">
        <label class="form-label small mb-0">Serviço Executado</label>
        <input type="text" name="execucao[]" class="form-control" placeholder="Serviço Executado">
      </div>
      <div class="col">
        <label class="form-label small mb-0">Qtd</label>
        <input type="number" name="qtd[]" class="form-control qtd-servico" placeholder="Qtd" step="any">
      </div>
      <div class="col">
        <label class="form-label small mb-0">Valor Unitário</label>
        <input type="number" name="valor_unt[]" class="form-control valor-unitario-servico" placeholder="Valor Unitário" step="any">
      </div>
      <div class="col">
        <label class="form-label small mb-0">Valor Total</label>
        <input type="number" name="valor_total[]" class="form-control valor-total-servico" placeholder="Valor Total" readonly>
      </div>
      <div class="col-auto">
        <div class="form-check">
          <input class="form-check-input modo-invertido-servico" type="checkbox">
          <label class="form-check-label small">Invertido</label>
        </div>
        <button type="button" class="btn btn-danger btn-sm mt-1 btn-remover">🗑️</button>
      </div>
    </div>
  `);

  // Pega o último serviço adicionado para configurar eventos
  const ultimaLinha = container.lastElementChild;

  const qtdInput = ultimaLinha.querySelector('.qtd-servico');
  const valorUnitarioInput = ultimaLinha.querySelector('.valor-unitario-servico');
  const valorTotalInput = ultimaLinha.querySelector('.valor-total-servico');
  const modoInvertidoCheck = ultimaLinha.querySelector('.modo-invertido-servico');

  function calcular() {
    const valorUnitario = parseFloat(valorUnitarioInput.value) || 0;
    if (modoInvertidoCheck.checked) {
      const valorTotal = parseFloat(valorTotalInput.value) || 0;
      qtdInput.value = valorUnitario !== 0 ? (valorTotal / valorUnitario).toFixed(2) : '';
    } else {
      const qtd = parseFloat(qtdInput.value) || 0;
      valorTotalInput.value = (qtd * valorUnitario).toFixed(2);
    }
    atualizarValoresTotais();
  }

  qtdInput.addEventListener('input', calcular);
  valorUnitarioInput.addEventListener('input', calcular);
  valorTotalInput.addEventListener('input', calcular);

  modoInvertidoCheck.addEventListener('change', () => {
    if (modoInvertidoCheck.checked) {
      qtdInput.setAttribute('readonly', true);
      valorTotalInput.removeAttribute('readonly');
    } else {
      qtdInput.removeAttribute('readonly');
      valorTotalInput.setAttribute('readonly', true);
      calcular();
    }
  });
};

// Função para adicionar novo material
window.adicionarMaterial = function () {
  const container = document.getElementById('materiaisContainer');
  container.insertAdjacentHTML('beforeend', `
    <div class="row g-2 mb-2 align-items-end">
      <div class="col">
        <label class="form-label small mb-0">Origem</label>
        <input type="text" name="origem[]" class="form-control" placeholder="Origem">
      </div>
      <div class="col">
        <label class="form-label small mb-0">Descrição</label>
        <input type="text" name="descricao[]" class="form-control" placeholder="Descrição">
      </div>
      <div class="col">
        <label class="form-label small mb-0">Qtd</label>
        <input type="number" name="qtd_mat[]" class="form-control qtd" placeholder="Qtd" step="any">
      </div>
      <div class="col">
        <label class="form-label small mb-0">Valor Unitário</label>
        <input type="number" name="valor_unt_mat[]" class="form-control valor-unitario" placeholder="Valor Unitário" step="any">
      </div>
      <div class="col">
        <label class="form-label small mb-0">Valor Total</label>
        <input type="number" name="valor_total_mat[]" class="form-control valor-total" placeholder="Valor Total" readonly>
      </div>
      <div class="col-auto">
        <div class="form-check">
          <input class="form-check-input modo-invertido-material" type="checkbox">
          <label class="form-check-label small">Invertido</label>
        </div>
        <button type="button" class="btn btn-danger btn-sm mt-1 btn-remover">🗑️</button>
      </div>
    </div>
  `);

  const ultimaLinha = container.lastElementChild;

  const qtdInput = ultimaLinha.querySelector('.qtd');
  const valorUnitarioInput = ultimaLinha.querySelector('.valor-unitario');
  const valorTotalInput = ultimaLinha.querySelector('.valor-total');
  const modoInvertidoCheck = ultimaLinha.querySelector('.modo-invertido-material');

  function calcular() {
    const valorUnitario = parseFloat(valorUnitarioInput.value) || 0;
    if (modoInvertidoCheck.checked) {
      const valorTotal = parseFloat(valorTotalInput.value) || 0;
      qtdInput.value = valorUnitario !== 0 ? (valorTotal / valorUnitario).toFixed(2) : '';
    } else {
      const qtd = parseFloat(qtdInput.value) || 0;
      valorTotalInput.value = (qtd * valorUnitario).toFixed(2);
    }
    atualizarValoresTotais();
  }

  qtdInput.addEventListener('input', calcular);
  valorUnitarioInput.addEventListener('input', calcular);
  valorTotalInput.addEventListener('input', calcular);

  modoInvertidoCheck.addEventListener('change', () => {
    if (modoInvertidoCheck.checked) {
      qtdInput.setAttribute('readonly', true);
      valorTotalInput.removeAttribute('readonly');
    } else {
      qtdInput.removeAttribute('readonly');
      valorTotalInput.setAttribute('readonly', true);
      calcular();
    }
  });
}

// Função para atualizar os totais dos valores
function atualizarValoresTotais() {
  let totalMateriais = 0;
  let totalServicos = 0;

  document.querySelectorAll('#materiaisContainer .valor-total').forEach(input => {
    totalMateriais += parseFloat(input.value) || 0;
  });

  document.querySelectorAll('#servicosContainer .valor-total-servico').forEach(input => {
    totalServicos += parseFloat(input.value) || 0;
  });

  document.getElementById('valorND30').value = totalMateriais.toFixed(2);
  document.getElementById('valorND39').value = totalServicos.toFixed(2);
  document.getElementById('valorTotalGasto').value = (totalMateriais + totalServicos).toFixed(2);
}

// Delegar evento de clique para remover linhas de serviços e materiais
document.addEventListener('click', function (e) {
  if (e.target.classList.contains('btn-remover')) {
    const row = e.target.closest('.row');
    if (row) {
      row.remove();
      // Espera um pouco para garantir que o DOM foi atualizado antes de recalcular
      setTimeout(atualizarValoresTotais, 10);
    }
  }
});

// Atualiza valores totais ao abrir o modal (se você estiver usando bootstrap modal)
const modalEditarOS = document.getElementById('modalEditarOS');
if (modalEditarOS) {
  modalEditarOS.addEventListener('shown.bs.modal', function () {
    atualizarValoresTotais();
  });
}

// Função para editar OS
window.editarOS = function (id) {
fotosSelecionadasOS = [];
fotosExcluirOS = [];

if (inputFotosOS) inputFotosOS.value = '';
if (previewFotosOS) previewFotosOS.innerHTML = '';

const inputFotosExcluir = document.getElementById('fotos_excluir');
if (inputFotosExcluir) inputFotosExcluir.value = '';
  const modalTitle = document.querySelector('#modalEditarOS .modal-title');
  const form = document.getElementById('form-editar-os');
  const nomeBatalhao = document.getElementById('nomeBatalhaoOS');

  if (!form || !nomeBatalhao) return;

  modalTitle.textContent = `Editando Ordem de Serviço Nmr #${id}`;

  fetch(`includes/os/buscar_os.php?id=${id}`)
    .then(res => res.json())
    .then(dados => {
      if (!dados.sucesso) {
        alert('Erro ao buscar dados da OS.');
        return;
      }

      form.setAttribute('data-id', id);

      form.querySelector('[name="os_numero"]').value = dados.os.id || '';
      form.querySelector('[name="data_abertura"]').value = dados.os.data_abertura || '';
      form.querySelector('[name="data_encerramento"]').value = dados.os.data_encerramento || '';
      form.querySelector('[name="solicitante"]').value = dados.os.solicitante || '';
      form.querySelector('[name="situacao_os"]').value = dados.os.status || '';
      form.querySelector('[name="prefixo"]').value = dados.os.prefixo_sga || '';
      form.querySelector('[name="odometro"]').value = dados.os.odometro_horimetro || '';
      form.querySelector('[name="tipo_mnt"]').value = dados.os.tipo_mnt || '';
      form.querySelector('[name="observacoes"]').value = dados.os.observacao || '';
      form.querySelector('[name="falhas_solicitadas"]').value = dados.os.problema || '';
      form.querySelector('[name="valorND30"]').value = dados.os.valornd30 || '';
      form.querySelector('[name="valorND39"]').value = dados.os.valornd39 || '';
      form.querySelector('[name="valorTotalGasto"]').value = dados.os.valorTOTAL || '';
      form.querySelector('[name="secao_rspns"]').value = dados.os.secao_rspns || '';

      nomeBatalhao.textContent = dados.os.nome_batalhao || 'Batalhão não informado';

      const localSelect = form.querySelector('[name="local_mnt"]');

      if (localSelect) {
        localSelect.innerHTML = '<option value="" disabled selected>Selecione o local da manutenção</option>';

        if (Array.isArray(dados.locais) && dados.locais.length > 0) {
          dados.locais.forEach(l => {
            const opt = document.createElement('option');
            opt.value = l.destino;
            opt.textContent = `${l.destino} - ${l.nome_batalhao}`;

            if (l.destino === dados.os.local_os) {
              opt.selected = true;
            }

            localSelect.appendChild(opt);
          });
        }
      }

      const preventiva = dados.os.manutencao_preventiva == 1;
      const chkPreventiva = document.getElementById('manutencaoPreventiva');

      if (chkPreventiva) {
        chkPreventiva.checked = preventiva;
      }

      const dadosPreventiva = document.getElementById('dadosPreventiva');

      if (dadosPreventiva) {
        dadosPreventiva.style.display = preventiva ? 'block' : 'none';
      }

      form.querySelector('[name="proxima_mnt_tempo"]').value = dados.os.prox_mnt_prev_odo || '';
      form.querySelector('[name="proxima_mnt_valor"]').value = dados.os.prox_mnt_prev_hor || '';
      form.querySelector('[name="trocas_realizadas"]').value = dados.os.trocas_realizadas || '';

      const blocoEditMnt = document.getElementById('bloco-edit-mnt-programada');
      const areaEditMnt = document.getElementById('area-edit-planos-mnt-os');

      if (blocoEditMnt && areaEditMnt) {
        blocoEditMnt.style.display = 'block';

        areaEditMnt.innerHTML = `
          <div class="text-center py-3">
            <div class="spinner-border text-warning" role="status"></div>
            <div class="mt-2 text-muted">Carregando manutenções programadas...</div>
          </div>
        `;

        fetch(`includes/os/buscar_planos_mnt_os_editar.php?id_os=${id}`)
          .then(res => res.text())
          .then(html => {
            areaEditMnt.innerHTML = html;
          })
          .catch(err => {
            areaEditMnt.innerHTML = `
              <div class="alert alert-danger mb-0">
                Erro ao carregar manutenções programadas: ${err.message}
              </div>
            `;
          });
      }

      ['falhasContainer', 'pessoalContainer', 'servicosContainer', 'materiaisContainer'].forEach(containerId => {
        const container = document.getElementById(containerId);
        if (container) container.innerHTML = '';
      });

      if (Array.isArray(dados.falhas)) {
        dados.falhas.forEach(f => {
          adicionarFalha();

          const last = document.getElementById('falhasContainer').lastElementChild;

          if (last) {
            last.querySelector('[name="sec[]"]').value = f.secao_falha || '';
            last.querySelector('[name="falha[]"]').value = f.falha_identificada || '';
            last.querySelector('[name="militar[]"]').value = f.militar_identificou || '';
          }
        });
      }

      if (Array.isArray(dados.pessoal)) {
        dados.pessoal.forEach(p => {
          adicionarPessoal();

          const last = document.getElementById('pessoalContainer').lastElementChild;

          if (last) {
            last.querySelector('[name="grad[]"]').value = p.postograd_militar || '';
            last.querySelector('[name="nome[]"]').value = p.nome_militar || '';
            last.querySelector('[name="funcao[]"]').value = p.funcao_militar || '';
            last.querySelector('[name="data[]"]').value = p.data_emprego || '';
            last.querySelector('[name="servico[]"]').value = p.servico_executado || '';
          }
        });
      }

      if (Array.isArray(dados.servicos)) {
        dados.servicos.forEach(s => {
          adicionarServico();

          const last = document.getElementById('servicosContainer').lastElementChild;

          if (last) {
            const qtd = parseFloat(s.qtd_servico) || 0;
            const valorUnt = parseFloat(s.valor_unt) || 0;

            last.querySelector('[name="empresa[]"]').value = s.empresa || '';
            last.querySelector('[name="execucao[]"]').value = s.rlzd_mnt || '';
            last.querySelector('[name="qtd[]"]').value = qtd;
            last.querySelector('[name="valor_unt[]"]').value = valorUnt;
            last.querySelector('[name="valor_total[]"]').value = (qtd * valorUnt).toFixed(2);
          }
        });
      }

      if (Array.isArray(dados.materiais)) {
        dados.materiais.forEach(m => {
          adicionarMaterial();

          const last = document.getElementById('materiaisContainer').lastElementChild;

          if (last) {
            const qtd = parseFloat(m.quant_itens_utilizados) || 0;
            const valorUnt = parseFloat(m.valor_itens) || 0;
            const valorTotal = m.valor_total_item !== null && m.valor_total_item !== undefined && m.valor_total_item !== ''
              ? parseFloat(m.valor_total_item)
              : qtd * valorUnt;

            last.querySelector('[name="origem[]"]').value = m.origem_item || '';
            last.querySelector('[name="descricao[]"]').value = m.itens_utilizados || '';
            last.querySelector('[name="qtd_mat[]"]').value = qtd;
            last.querySelector('[name="valor_unt_mat[]"]').value = valorUnt;
            last.querySelector('[name="valor_total_mat[]"]').value = valorTotal.toFixed(2);
          }
        });
      }

      if (typeof atualizarValoresTotais === 'function') {
        atualizarValoresTotais();
      }
const fotosExistentesOS = document.getElementById('fotosExistentesOS');

if (fotosExistentesOS) {
  fotosExistentesOS.innerHTML = '';

  if (Array.isArray(dados.fotos) && dados.fotos.length > 0) {
    dados.fotos.forEach(foto => {
      fotosExistentesOS.insertAdjacentHTML('beforeend', `
        <div class="col-md-3 col-sm-6" id="foto-os-existente-${foto.id}">
          <div class="card shadow-sm h-100">
            <a href="${foto.caminho}" target="_blank">
              <img src="${foto.caminho}" class="card-img-top" style="height:160px; object-fit:cover;">
            </a>
            <div class="card-body p-2">
              <div class="small text-muted">${foto.data_upload || ''}</div>
              <div class="small">${foto.legenda || ''}</div>

              <button type="button" class="btn btn-danger btn-sm w-100 mt-2" onclick="marcarFotoExistenteParaExcluir(${foto.id})">
                <i class="fas fa-trash me-1"></i> Excluir foto
              </button>
            </div>
          </div>
        </div>
      `);
    });
  } else {
    fotosExistentesOS.innerHTML = `
      <div class="col-12">
        <div class="alert alert-secondary py-2 mb-0">
          Nenhuma foto enviada para esta OS.
        </div>
      </div>
    `;
  }
}

window.marcarFotoExistenteParaExcluir = function(idFoto) {
  if (!fotosExcluirOS.includes(idFoto)) {
    fotosExcluirOS.push(idFoto);
  }

  const inputFotosExcluir = document.getElementById('fotos_excluir');
  if (inputFotosExcluir) {
    inputFotosExcluir.value = fotosExcluirOS.join(',');
  }

  const card = document.getElementById(`foto-os-existente-${idFoto}`);

  if (card) {
    card.innerHTML = `
      <div class="alert alert-danger h-100 d-flex flex-column justify-content-center align-items-center text-center">
        <div class="fw-bold mb-2">Foto marcada para exclusão</div>
        <button type="button" class="btn btn-secondary btn-sm" onclick="desmarcarFotoExistenteOS(${idFoto})">
          Desfazer
        </button>
      </div>
    `;
  }
};

window.desmarcarFotoExistenteOS = function(idFoto) {
  fotosExcluirOS = fotosExcluirOS.filter(id => id !== idFoto);

  const inputFotosExcluir = document.getElementById('fotos_excluir');
  if (inputFotosExcluir) {
    inputFotosExcluir.value = fotosExcluirOS.join(',');
  }

  editarOS(formEditarOS.getAttribute('data-id'));
};
	  
    })
    .catch(err => {
      console.error('Erro ao buscar dados da OS:', err);
      alert('Erro ao carregar OS.');
    });
};

const formEditarOS = document.getElementById('form-editar-os');

if (formEditarOS) {
  formEditarOS.addEventListener('submit', function (e) {
    e.preventDefault();

    const formData = new FormData(formEditarOS);

    const id = formEditarOS.getAttribute('data-id');
    formData.append('id_os', id);

    document.querySelectorAll('#falhasContainer .row').forEach(row => {
      formData.append('secao_falha[]', row.querySelector('[name="sec[]"]')?.value || '');
      formData.append('falha_identificada[]', row.querySelector('[name="falha[]"]')?.value || '');
      formData.append('militar_identificou[]', row.querySelector('[name="militar[]"]')?.value || '');
    });

    document.querySelectorAll('#pessoalContainer .row').forEach(row => {
      formData.append('postograd[]', row.querySelector('[name="grad[]"]')?.value || '');
      formData.append('nome_guerra[]', row.querySelector('[name="nome[]"]')?.value || '');
      formData.append('funcao[]', row.querySelector('[name="funcao[]"]')?.value || '');
      formData.append('data_emprego[]', row.querySelector('[name="data[]"]')?.value || '');
      formData.append('servico_exec[]', row.querySelector('[name="servico[]"]')?.value || '');
    });

    document.querySelectorAll('#servicosContainer .row').forEach(row => {
      formData.append('empresa[]', row.querySelector('[name="empresa[]"]')?.value || '');
      formData.append('execucao[]', row.querySelector('[name="execucao[]"]')?.value || '');
      formData.append('qtd_servico[]', row.querySelector('[name="qtd[]"]')?.value || '');
      formData.append('valor_unitario_servico[]', row.querySelector('[name="valor_unt[]"]')?.value || '');
      formData.append('valor_total_servico[]', row.querySelector('[name="valor_total[]"]')?.value || '');
    });

    document.querySelectorAll('#materiaisContainer .row').forEach(row => {
      formData.append('origem_item[]', row.querySelector('[name="origem[]"]')?.value || '');
      formData.append('descricao_item[]', row.querySelector('[name="descricao[]"]')?.value || '');
      formData.append('qtd_item[]', row.querySelector('[name="qtd_mat[]"]')?.value || '');
      formData.append('valor_unitario_item[]', row.querySelector('[name="valor_unt_mat[]"]')?.value || '');
      formData.append('valor_total_item[]', row.querySelector('[name="valor_total_mat[]"]')?.value || '');
    });

    const preventiva = document.getElementById('manutencaoPreventiva')?.checked ? '1' : '0';

    formData.set('manutencao_preventiva', preventiva);
    formData.set('proxima_mnt_tempo', formEditarOS.querySelector('[name="proxima_mnt_tempo"]')?.value || '');
    formData.set('proxima_mnt_valor', formEditarOS.querySelector('[name="proxima_mnt_valor"]')?.value || '');
    formData.set('trocas_realizadas', formEditarOS.querySelector('[name="trocas_realizadas"]')?.value || '');
				 
	formData.delete('fotos_os[]');

    fotosSelecionadasOS.forEach(file => {
    formData.append('fotos_os[]', file);
    });

    formData.set('fotos_excluir', fotosExcluirOS.join(','));

    fetch('includes/os/editar_os.php', {
      method: 'POST',
      body: formData
    })
      .then(res => res.text())
      .then(retorno => {
        if (retorno.trim() === 'ok') {
          fecharModalAberto();

          Swal.fire({
            title: 'Sucesso!',
            text: 'Dados salvos com sucesso!',
            icon: 'success',
            confirmButtonText: 'OK'
          }).then(() => {
            carregarPagina('includes/os/listagem.php');
          });
        } else {
          Swal.fire({
            title: 'Erro!',
            text: 'Erro ao editar OS: ' + retorno,
            icon: 'error',
            confirmButtonText: 'OK'
          });
        }
      })
      .catch(err => {
        Swal.fire({
          title: 'Erro!',
          text: 'Erro no envio: ' + err.message,
          icon: 'error',
          confirmButtonText: 'OK'
        });
      });
  });
}
								
let fotosSelecionadasOS = [];
let fotosExcluirOS = [];

const inputFotosOS = document.getElementById('fotos_os');
const previewFotosOS = document.getElementById('previewFotosOS');

function atualizarInputFotosOS() {
  const dataTransfer = new DataTransfer();

  fotosSelecionadasOS.forEach(file => {
    dataTransfer.items.add(file);
  });

  inputFotosOS.files = dataTransfer.files;
}

function renderPreviewFotosOS() {
  previewFotosOS.innerHTML = '';

  fotosSelecionadasOS.forEach((file, index) => {
    const reader = new FileReader();

    reader.onload = function (e) {
      previewFotosOS.insertAdjacentHTML('beforeend', `
        <div class="col-md-3 col-sm-6">
          <div class="card shadow-sm">
            <img src="${e.target.result}" class="card-img-top" style="height:160px; object-fit:cover;">
            <div class="card-body p-2">
              <div class="small text-muted text-truncate">${file.name}</div>
              <button type="button" class="btn btn-window.editarOS = function (id) {danger btn-sm w-100 mt-2" onclick="removerFotoSelecionadaOS(${index})">
                <i class="fas fa-trash me-1"></i> Remover
              </button>
            </div>
          </div>
        </div>
      `);
    };

    reader.readAsDataURL(file);
  });
}

window.removerFotoSelecionadaOS = function(index) {
  fotosSelecionadasOS.splice(index, 1);
  atualizarInputFotosOS();
  renderPreviewFotosOS();
};

if (inputFotosOS && previewFotosOS) {
  inputFotosOS.addEventListener('change', function () {
    Array.from(this.files).forEach(file => {
      if (file.type.startsWith('image/')) {
        fotosSelecionadasOS.push(file);
      }
    });

    atualizarInputFotosOS();
    renderPreviewFotosOS();
  });
}		



// Função para exibir mensagem temporária
function exibirMensagemSucesso(mensagem) {
  const msgDiv = document.createElement('div');
  msgDiv.textContent = mensagem;
  msgDiv.style.position = 'fixed';
  msgDiv.style.top = '20px';
  msgDiv.style.right = '20px';
  msgDiv.style.backgroundColor = '#28a745';
  msgDiv.style.color = '#fff';
  msgDiv.style.padding = '10px 20px';
  msgDiv.style.borderRadius = '5px';
  msgDiv.style.boxShadow = '0 2px 6px rgba(0,0,0,0.2)';
  msgDiv.style.zIndex = 9999;
  msgDiv.style.fontFamily = 'Arial, sans-serif';
  msgDiv.style.fontSize = '14px';
  document.body.appendChild(msgDiv);

  setTimeout(() => {
    msgDiv.remove();
  }, 3000); // desaparece após 3 segundos
}


// VER OS
window.verOS = function (id) {
  fetch(`includes/os/buscar_os_veros.php?id=${id}`)
    .then(res => res.json())
    .then(dados => {
      if (!dados.sucesso) {
        alert('Erro ao carregar dados da OS.');
        return;
      }

      const os = dados.os || {};

      // Foto da viatura
      const img = document.querySelector('#modalVerOS #fotoViatura');
      if (img && os.foto_capa) {
        img.src = `uploads/frotas/${os.foto_capa}`;
      }

      // Campos principais
      document.getElementById('os_numero').innerText = os.id || '';
      document.getElementById('viatura_chassi').innerText = os.chassi || '';
      document.getElementById('viatura_marca').innerText = os.nome_marca || '';   // Ajustado
      document.getElementById('viatura_modelo').innerText = os.nome_modelo || ''; // Ajustado
      document.getElementById('viatura_prefixo').innerText = os.prefixo_sga || '';

      if (os.data_abertura) {
        const data = new Date(os.data_abertura);
        const dia = String(data.getDate()).padStart(2, '0');
        const mes = String(data.getMonth() + 1).padStart(2, '0');
        const ano = data.getFullYear();
        document.getElementById('data_abertura').innerText = `${dia}/${mes}/${ano}`;
      } else {
        document.getElementById('data_abertura').innerText = '';
      }
      
      document.getElementById('solicitante2').innerText = os.solicitante || '';
      document.getElementById('situacao_os').innerText = os.status || '';
      document.getElementById('odometro2').innerText = os.odometro_horimetro || '';
      document.getElementById('tipo_mnt2').innerText = os.tipo_mnt || '';
      document.getElementById('falhas_solicitadas').innerText = os.problema || '';
      document.getElementById('local_mnt').innerText = os.local_os || '';

      // Valores
      const nd30 = parseFloat(os.valornd30 || 0);
      const nd39 = parseFloat(os.valornd39 || 0);
      const total = nd30 + nd39;

      document.getElementById('valorND302').innerText = `R$ ${nd30.toFixed(2).replace('.', ',')}`;
      document.getElementById('valorND392').innerText = `R$ ${nd39.toFixed(2).replace('.', ',')}`;
      document.getElementById('valorTotalGasto2').innerText = `R$ ${total.toFixed(2).replace('.', ',')}`;

      // Falhas
      const falhasContainer2 = document.getElementById('falhasContainer2');
      falhasContainer2.innerHTML = '';
      (dados.falhas || []).forEach(f => {
        falhasContainer2.innerHTML += `
          <div class="border rounded p-2 mb-2">
            <strong>Seção:</strong> ${f.secao_falha || ''}<br>
            <strong>Falha:</strong> ${f.falha_identificada || ''}<br>
            <strong>Identificada por:</strong> ${f.militar_identificou || ''}
          </div>`;
      });

      // Pessoal
      const pessoalContainer2 = document.getElementById('pessoalContainer2');
      pessoalContainer2.innerHTML = '';
      (dados.pessoal || []).forEach(p => {
        pessoalContainer2.innerHTML += `
          <div class="border rounded p-2 mb-2">
            <strong>${p.postograd_militar || ''} ${p.nome_militar || ''}</strong><br>
            <strong>Função:</strong> ${p.funcao_militar || ''}<br>
            <strong>Data:</strong> ${p.data_emprego || ''}<br>
            <strong>Serviço:</strong> ${p.servico_executado || ''}
          </div>`;
      });

      // Serviços
      const servicosContainer2 = document.getElementById('servicosContainer2');
      servicosContainer2.innerHTML = '';
      (dados.servicos || []).forEach(s => {
        const qtd = parseFloat(s.qtd_servico || 0);
        const valor = parseFloat(s.valor_unt || 0);
        const total = qtd * valor;
        servicosContainer2.innerHTML += `
          <div class="border rounded p-2 mb-2">
            <strong>Empresa:</strong> ${s.empresa || ''}<br>
            <strong>Serviço:</strong> ${s.rlzd_mnt || ''}<br>
            <strong>Quantidade:</strong> ${qtd}<br>
            <strong>Valor Unitário:</strong> R$ ${valor.toFixed(2).replace('.', ',')}<br>
            <strong>Total:</strong> R$ ${total.toFixed(2).replace('.', ',')}
          </div>`;
      });

      // Materiais
      const materiaisContainer2 = document.getElementById('materiaisContainer2');
      materiaisContainer2.innerHTML = '';
      (dados.materiais || []).forEach(m => {
        const qtd = parseFloat(m.quant_itens_utilizados || 0);
        const valor = parseFloat(m.valor_itens || 0);
        const total = qtd * valor;
        materiaisContainer2.innerHTML += `
          <div class="border rounded p-2 mb-2">
            <strong>Origem:</strong> ${m.origem_item || ''}<br>
            <strong>Descrição:</strong> ${m.itens_utilizados || ''}<br>
            <strong>Quantidade:</strong> ${qtd}<br>
            <strong>Valor Unitário:</strong> R$ ${valor.toFixed(2).replace('.', ',')}<br>
            <strong>Total:</strong> R$ ${total.toFixed(2).replace('.', ',')}
          </div>`;
      });
	  
	  // Manutenções programadas executadas
const mntProgramadasContainer = document.getElementById('mntProgramadasExecutadasContainer');

if (mntProgramadasContainer) {
  mntProgramadasContainer.innerHTML = '';

  const mnts = dados.mnt_programadas || [];

  if (mnts.length > 0) {
    mnts.forEach(mnt => {
      let dataExecucao = '';

      if (mnt.data_execucao) {
        const data = new Date(mnt.data_execucao + 'T00:00:00');
        dataExecucao = data.toLocaleDateString('pt-BR');
      }

      mntProgramadasContainer.innerHTML += `
        <div class="border rounded p-2 mb-2 bg-light">
          <div class="d-flex justify-content-between flex-wrap gap-2">
            <div>
              <strong>${mnt.descricao || 'Plano de manutenção'}</strong><br>
              <span class="text-muted small">Tipo de controle: ${mnt.tipo_controle || '-'}</span>
            </div>
            <span class="badge bg-success align-self-start">
              Executada
            </span>
          </div>

          <hr class="my-2">

          <div class="row g-2 small">
            <div class="col-md-3">
              <strong>Data execução:</strong><br>
              ${dataExecucao || '-'}
            </div>
            <div class="col-md-3">
              <strong>ODO/HOR execução:</strong><br>
              ${mnt.odometro_horimetro_execucao || '-'}
            </div>
            <div class="col-md-3">
              <strong>Intervalo valor:</strong><br>
              ${mnt.intervalo_valor || '-'}
            </div>
            <div class="col-md-3">
              <strong>Intervalo dias:</strong><br>
              ${mnt.intervalo_dias || '-'}
            </div>
          </div>
        </div>
      `;
    });
  } else {
    mntProgramadasContainer.innerHTML = `
      <p class="text-muted mb-0">Nenhuma manutenção programada executada nesta OS.</p>
    `;
  }
}
	  
	  // Fotos da OS
const fotosOSVerContainer = document.getElementById('fotosOSVerContainer');

if (fotosOSVerContainer) {
  fotosOSVerContainer.innerHTML = '';

  const fotos = dados.fotos || [];

  if (fotos.length > 0) {
    fotos.forEach(foto => {
      fotosOSVerContainer.innerHTML += `
        <div class="col-md-3 col-sm-6">
          <div class="card shadow-sm h-100">
            <a href="${foto.caminho}" target="_blank">
              <img 
                src="${foto.caminho}" 
                class="card-img-top" 
                style="height:170px; object-fit:cover;"
                alt="${foto.nome_arquivo || 'Foto da OS'}"
              >
            </a>

            <div class="card-body p-2">
              <div class="small fw-bold text-truncate">
                ${foto.nome_arquivo || 'Foto da OS'}
              </div>
              <div class="small text-muted">
                ${foto.data_upload || ''}
              </div>
              <div class="small mt-1">
                ${foto.legenda || ''}
              </div>
            </div>
          </div>
        </div>
      `;
    });
  } else {
    fotosOSVerContainer.innerHTML = `
      <div class="col-12">
        <p class="text-muted mb-0">Nenhuma foto enviada para esta OS.</p>
      </div>
    `;
  }
}

      // Logs
      const logsContainer = document.getElementById('logsContainer');
      logsContainer.innerHTML = '';
      const logs = dados.logs || [];
      if (logs.length > 0) {
        logs.forEach(log => {
          logsContainer.innerHTML += `
            <div class="border rounded p-2 mb-2 small text-muted">
              <strong>${log.postograd || ''} - ${log.nomeguerra || 'Usuário'}</strong> realizou <strong>${log.acao || ''}</strong> em ${log.data_hora || ''}<br>
              <em>${log.descricao || ''}</em><br>
              <small>IP: ${log.ip || ''} | Navegador: ${log.navegador || ''}</small>
            </div>`;
        });
      } else {
        logsContainer.innerHTML = '<p class="text-muted">Nenhum log registrado.</p>';
      }

    })
    .catch(err => {
      console.error('Erro ao carregar dados da OS:', err);
      alert('Erro ao carregar OS.');
    });
};


window.deletarOS = function(botao) {
    const id = botao.getAttribute('data-id');

    Swal.fire({
      title: 'Deletar OS?',
      text: "Essa ação não poderá ser desfeita. Deseja realmente excluir esta OS e todos os registros relacionados?",
      icon: 'warning',
      showCancelButton: true,
      confirmButtonText: 'Sim, deletar',
      cancelButtonText: 'Cancelar'
    }).then((result) => {
      if (result.isConfirmed) {
        const formData = new FormData();
        formData.append('id', id);

        fetch('includes/os/deletar_os.php', {  // <- ajuste o caminho conforme seu projeto
          method: 'POST',
          body: formData
        })
        .then(response => response.json())
        .then(data => {
          if (data.success) {
            Swal.fire({
              icon: 'success',
              title: 'Deletado!',
              text: 'OS excluída com sucesso.',
              timer: 1500,
              showConfirmButton: false
            }).then(() => {
              // Atualiza a página ou remove o item da lista
              carregarPagina('includes/os/listagem.php');  // <- ajuste conforme necessário
            });
          } else {
            Swal.fire({
              icon: 'error',
              title: 'Erro',
              text: data.message || 'Erro ao deletar OS.'
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

  // Delega o evento para todos os botões com a classe deletar-os
  document.querySelectorAll('.btn-deletar-os').forEach(botao => {
    botao.addEventListener('click', function() {
      deletarOS(this);
    });
  });
  
}