window.inicializarListagemFrota = function() {
  // PÁGINA FROTA       
  const form = document.getElementById('filtroFrotaForm');

  function atualizarListaComParametros(extraParams = {}) {
    if (!form) return;

    const formData = new FormData(form);
    const params = new URLSearchParams(formData);

    for (const key in extraParams) {
      params.set(key, extraParams[key]);
    }

    const url = `includes/frota/listagem.php?${params.toString()}`;
    carregarPagina(url); // AJAX
  }

  // Submissão do formulário
  if (form) {
    form.addEventListener('submit', function(e) {
      e.preventDefault();
      atualizarListaComParametros({ pagina: 1 });
    });
  }

  // Mudança de limite por página
  const limiteSelect = document.getElementById('limite');
  if (limiteSelect) {
    limiteSelect.addEventListener('change', function() {
      atualizarListaComParametros({ pagina: 1, limite: this.value });
    });
  }

  // Botão "Limpar Filtros"
  const btnLimpar = document.getElementById('btnLimparFiltros');
  if (btnLimpar && form) {
    btnLimpar.addEventListener('click', function(e) {
      e.preventDefault();
      form.reset();

      const limiteAtual = document.getElementById('limite')?.value || 10;
      const url = 'includes/frota/listagem.php?pagina=1&limite=${limiteAtual}';
      carregarPagina(url); // AJAX sem filtros
    });
  }
      

// VISUALIZAR FROTA COM LOGS E VER TUDO - COM DATA-ID
window.visualizarFrota = function(id, paginaOs = 1, paginaPedidos = 1, paginaLogs = 1) {
    fetch(`includes/frota/viatura_dados.php?id=${id}&paginaOs=${paginaOs}&paginaPedidos=${paginaPedidos}&paginaLogs=${paginaLogs}`)
    .then(res => res.json())
    .then(dados => {
        if (!dados.sucesso) {
            alert('Erro: ' + (dados.mensagem || 'Falha ao carregar dados.'));
            return;
        }

        // ---------------- Cabeçalho ----------------
        document.getElementById('foto-viatura').src = 'uploads/frotas/' + (dados.foto_capa || 'sem-foto.png');

        const prefixoEl = document.getElementById('viatura-prefixo');
        prefixoEl.textContent = dados.prefixo_sga || 'N/A';
        prefixoEl.dataset.id = id; // <-- ARMAZENA O ID REAL DA VIATURA

        document.getElementById('viatura-modelo').textContent = dados.modelo || 'N/A';
        document.getElementById('viatura-marca').textContent = dados.marca || 'N/A';
        document.getElementById('viatura-placa').textContent = dados.placa || 'N/A';
        document.getElementById('viatura-tipo').textContent = dados.tipo || 'N/A';
        document.getElementById('viatura-status').textContent = dados.disponibilidade || 'N/A';
        document.getElementById('viatura-localizacao').textContent = dados.destino || 'Não informado';

        // ---------------- Função Auxiliar para Cards ----------------
        const criarCard = (titulo, conteudo) => `
            <div class="card mb-3 shadow-sm">
                <div class="card-header bg-primary text-white">${titulo}</div>
                <div class="card-body">${conteudo}</div>
            </div>
        `;


        // ---------------- ORDENS DE SERVIÇO ----------------
        let osHtml = 'Nenhuma OS registrada';
        if (dados.ordens_servico && dados.ordens_servico.length) {
            osHtml = '';
            dados.ordens_servico.forEach(os => {
                osHtml += `
                    <div class="card mb-2 shadow-sm">
                        <div class="card-body d-flex flex-wrap align-items-start">
                            <div class="me-3 mb-2">
                                <span class="badge bg-info text-dark fs-6 p-2 shadow-sm">OS #${os.id}</span>
                            </div>
                            <div class="flex-fill">
                                <div class="row mb-1">
                                    <div class="col-6"><strong>Status:</strong> ${os.status || 'N/A'}</div>
                                    <div class="col-6"><strong>Tipo Mnt:</strong> ${os.tipo_mnt || 'N/A'}</div>
                                </div>
                                <div class="row mb-1">
                                    <div class="col-6"><strong>Problema:</strong> ${os.problema || 'N/A'}</div>
                                    <div class="col-6"><strong>Solicitante:</strong> ${os.solicitante || 'N/A'}</div>
                                </div>
                                <div class="row mb-1">
                                    <div class="col-6"><strong>Local:</strong> ${os.local_os || 'N/A'}</div>
                                    <div class="col-6"><strong>Data abertura:</strong> ${os.data_abertura || 'N/A'}</div>
                                </div>
                            </div>
                        </div>
                    </div>
                `;
            });

            // Paginação OS
            if (dados.total_paginas_os > 1) {
                osHtml += '<nav><ul class="pagination justify-content-center">';
                for (let i = 1; i <= dados.total_paginas_os; i++) {
                    osHtml += `<li class="page-item ${i === dados.pagina_atual_os ? 'active' : ''}">
                                    <a class="page-link" href="#" onclick="visualizarFrota(${id}, ${i}, ${paginaPedidos}, ${paginaLogs}); return false;">${i}</a>
                               </li>`;
                }
                osHtml += '</ul></nav>';
            }
        }

     // ---------------- ÚLTIMOS 10 ODÔMETROS ----------------
const ods = Array.isArray(dados.ultimos_odometros) ? dados.ultimos_odometros : [];
const ultimo = ods[0] || dados.ultima_medicao || {};

let medHtml = `
  <div class="row mb-2">
    <div class="col-12"><strong>Último odômetro:</strong> ${ultimo.odometro ?? 'N/A'}</div>
    <div class="col-12"><strong>Data:</strong> ${ultimo.data ?? 'N/A'}</div>
  </div>
`;

if (ods.length === 0) {
  medHtml += `<div class="text-muted">Nenhuma medição registrada.</div>`;
} else {
  medHtml += `
    <div class="table-responsive">
      <table class="table table-sm table-bordered mb-0">
        <thead>
          <tr>
            <th style="white-space:nowrap;">Data</th>
            <th style="white-space:nowrap;">Odômetro</th>
          </tr>
        </thead>
        <tbody>
          ${ods.map(m => `
            <tr>
              <td style="white-space:nowrap;">${m.data ?? 'N/A'}</td>
              <td style="white-space:nowrap;">${m.odometro ?? 'N/A'}</td>
            </tr>
          `).join('')}
        </tbody>
      </table>
    </div>
  `;
}

        // ---------------- PEDIDOS ALMOXARIFADO ----------------
        let pedidosAlmoxHtml = 'Nenhum pedido encontrado';
        if (dados.pedidos_almox && dados.pedidos_almox.length) {
            pedidosAlmoxHtml = '';
            dados.pedidos_almox.forEach(p => {
                pedidosAlmoxHtml += `
                    <div class="card mb-2 shadow-sm">
                        <div class="card-body d-flex flex-wrap align-items-start">
                            <div class="me-3 mb-2">
                                <span class="badge bg-info text-dark fs-6 p-2 shadow-sm">Pedido #${p.id}</span>
                            </div>
                            <div class="flex-fill">
                                <div class="row mb-1">
                                    <div class="col-6"><strong>Data:</strong> ${p.data_pedido || 'N/A'}</div>
                                    <div class="col-6"><strong>Local:</strong> ${p.local_pedido || 'N/A'}</div>
                                </div>
                                <div class="row mb-1">
                                    <div class="col-12"><strong>Status:</strong> ${p.status_pedido || 'N/A'}</div>
                                </div>
                            </div>
                        </div>
                    </div>
                `;
            });
        }

        // ---------------- PEDIDOS FINANCEIRO ----------------
        let pedidosFinHtml = 'Nenhum pedido financeiro encontrado';
        if (dados.pedidos_financeiro && dados.pedidos_financeiro.length) {
            pedidosFinHtml = '';
            dados.pedidos_financeiro.forEach(p => {
                pedidosFinHtml += `
                    <div class="card mb-2 shadow-sm">
                        <div class="card-body d-flex flex-wrap align-items-start">
                            <div class="me-3 mb-2">
                                <span class="badge bg-warning text-dark fs-6 p-2 shadow-sm">Pedido Fin #${p.id}</span>
                            </div>
                            <div class="flex-fill">
                                <div class="row mb-1">
                                    <div class="col-6"><strong>Data:</strong> ${p.data_pedido || 'N/A'}</div>
                                    <div class="col-6"><strong>Local:</strong> ${p.local_pedido || 'N/A'}</div>
                                </div>
                                <div class="row mb-1">
                                    <div class="col-6"><strong>Status:</strong> ${p.situacao_pedido || 'N/A'}</div>
                                    <div class="col-6"><strong>Solicitante:</strong> ${p.solicitante || 'N/A'}</div>
                                </div>
                            </div>
                        </div>
                    </div>
                `;
            });
        }

        // ---------------- FORNECIMENTOS ----------------
        let fornecimentosHtml = 'Nenhum fornecimento encontrado';
        if (dados.fornecimentos && dados.fornecimentos.length) {
            fornecimentosHtml = '<div class="list-group">';
            dados.fornecimentos.forEach(f => {
                fornecimentosHtml += `
                    <div class="list-group-item list-group-item-action flex-column align-items-start mb-2 shadow-sm">
                        <div class="d-flex w-100 justify-content-between">
                            <h6 class="mb-1">${f.empresa_nome || 'Fornecedor'}</h6>
                            <small>${f.data_cadastro || 'N/A'}</small>
                        </div>
                        <small>Status: ${f.status || 'N/A'}</small>
                    </div>
                `;
            });
            fornecimentosHtml += '</div>';
        }

      

        // ---------------- FICHAS ----------------
        let fichasHtml = 'Nenhuma ficha encontrada';
        if (dados.fichas && dados.fichas.length) {
            fichasHtml = '';
            dados.fichas.forEach(f => {
                fichasHtml += `
                    <div class="card mb-2 shadow-sm">
                        <div class="card-body">
                            <strong>Ficha #${f.id}</strong><br>
                            Data: ${f.data_abertura || 'N/A'}<br>
                            Destino: ${f.destino || 'N/A'}<br>
                            Motorista: ${f.motorista || 'N/A'}<br>
                            Status: ${f.status || 'N/A'}
                        </div>
                    </div>
                `;
            });
        }

        // ---------------- LOGS ----------------
        let logsHtml = '<tr><td colspan="4">Nenhum log encontrado</td></tr>';
        if (dados.logs && dados.logs.length) {
            logsHtml = '';
            dados.logs.forEach(log => {
                logsHtml += `
                    <tr>
                        <td>${log.data_hora || 'N/A'}</td>
                        <td>${log.acao || 'N/A'}</td>
                        <td>${log.descricao || 'N/A'}</td>
                        <td>${log.responsavel_nome || 'N/A'}</td>
                    </tr>
                `;
            });

            if (dados.total_paginas_logs > 1) {
                logsHtml += `<tr>
                    <td colspan="4" class="text-center">
                        <nav><ul class="pagination justify-content-center mb-0">`;
                for (let i = 1; i <= dados.total_paginas_logs; i++) {
                    logsHtml += `<li class="page-item ${i === dados.pagina_atual_logs ? 'active' : ''}">
                                    <a class="page-link" href="#" onclick="visualizarFrota(${id}, ${paginaOs}, ${paginaPedidos}, ${i}); return false;">${i}</a>
                                 </li>`;
                }
                logsHtml += `</ul></nav></td></tr>`;
            }
        }

        // ---------------- ATUALIZAÇÃO DOS BLOCS ----------------
        document.getElementById('conteudo-os-viatura').innerHTML = criarCard('Ordens de Serviço', osHtml);
        document.getElementById('conteudo-medicoes-viatura').innerHTML = criarCard('Últimos 10 Odômetros', medHtml);
        document.getElementById('conteudo-pedidos-viatura').innerHTML = criarCard('Pedidos Almox', pedidosAlmoxHtml) +
                                                                    criarCard('Pedidos Financeiro', pedidosFinHtml);
        document.getElementById('conteudo-fornecimentos-viatura').innerHTML = criarCard('Ordens de Fornecimento', fornecimentosHtml);
        document.getElementById('conteudo-fichas-viatura').innerHTML = criarCard('Fichas', fichasHtml);
        document.getElementById('tabela-logs-viatura').innerHTML = logsHtml;

        // ---------------- ABA VER TUDO ----------------
        let tudoHtml = `
            ${criarCard('Ordens de Serviço', osHtml)}
            ${criarCard('Últimos 10 Odômetros', medHtml)}
            ${criarCard('Pedidos Almox', pedidosAlmoxHtml)}
            ${criarCard('Pedidos Financeiro', pedidosFinHtml)}
            ${criarCard('Ordens de Fornecimento', fornecimentosHtml)}
            ${criarCard('Fichas', fichasHtml)}
            <div class="card mb-3 shadow-sm">
                <div class="card-header bg-primary text-white">Logs</div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm table-striped table-hover text-white mb-0">
                            <thead class="table-dark">
                                <tr>
                                    <th>Data</th>
                                    <th>Ação</th>
                                    <th>Descrição</th>
                                    <th>Responsável</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${logsHtml}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        `;
        document.getElementById('conteudo-tudo-viatura').innerHTML = tudoHtml;

    })
    .catch(erro => console.error('Erro na requisição:', erro));
};






// EDITAR FROTA - ENVIAR DADOS PRO BANCO
const formEditarFrota = document.getElementById('form-editar-frota');

if (formEditarFrota) {
  formEditarFrota.addEventListener('submit', function(e) {
    e.preventDefault(); // evita atualização da página

    const formData = new FormData(formEditarFrota);

    fetch('includes/frota/editar_frota.php', {
      method: 'POST',
      body: formData
    })
    .then(res => res.text())
    .then(retorno => {
      retorno = retorno.trim();

      if (retorno === 'ok') {
        // Fechar modal
        const modalEl = document.getElementById('modalEditarFrota');
        const modal = bootstrap.Modal.getInstance(modalEl);
        modal.hide();

        // Mensagem de sucesso SweetAlert
        Swal.fire({
          icon: 'success',
          title: 'Alterações salvas!',
          showConfirmButton: false,
          timer: 2000
        });

        // Recarregar listagem via AJAX (se tiver função)
        if (typeof carregarPagina === "function") {
          carregarPagina('includes/frota/listagem.php');
        }

      } else {
        Swal.fire({
          icon: 'error',
          title: 'Erro ao editar',
          text: retorno
        });
      }
    })
    .catch(err => {
      Swal.fire({
        icon: 'error',
        title: 'Erro ao enviar',
        text: err.message
      });
    });
  });
}



window.editarFrota = function(id) {
  const fotoPreview = document.getElementById('editar-foto-preview-frota');
  fotoPreview.src = 'https://cdn-icons-png.flaticon.com/512/3600/3600953.png';

  fetch(`includes/frota/dados_editar.php?id=${id}`)
    .then(res => res.json())
    .then(dados => {
      if (!dados.sucesso) return alert('Erro ao carregar dados: ' + dados.mensagem);

      const frota = dados.frota;

      // Campos básicos
      document.getElementById('editar-id-frota').value = frota.id;
      document.getElementById('editar-ativo').value = frota.ativo;

      // Tipo
      const tipoSelect = document.getElementById('editar-tipo');
      tipoSelect.innerHTML = '';
      dados.tipos.forEach(t => {
        const option = document.createElement('option');
        option.value = t.valor;
        option.text = t.texto;
        if (t.valor === frota.tipo) option.selected = true;
        tipoSelect.appendChild(option);
      });

      // Destino
      const destinoSelect = document.getElementById('editar-destino');
      if (destinoSelect) {
        Array.from(destinoSelect.options).forEach(opt => {
          if (opt.value === frota.destino) opt.selected = true;
        });
      }

      // Campos de texto simples
      const camposMap = {
        prefixo_velho: 'prefixo_velho',
        prefixo_sga: 'prefixo_sga',
        nome_sioc: 'nome_sioc',
        nmr_patrimonio: 'nmr_patrimonio',
        nmr_eb: 'nmr_eb',
        chassi: 'chassi',
        acervo: 'acervo',
        ano: 'ano',
        capac_tanque: 'capacidade_tanque',
        consumo: 'consumo',
        ordem_fragmentaria: 'ordem_fragmentaria',
        placa: 'placa',
        subunidade: 'subunidade',
        renavam: 'renavam',
        trem: 'trem',
        missao: 'missao',
        emprego_atual: 'emprego_atual',
        obs_encmat: 'obs_encmat'
      };
      Object.entries(camposMap).forEach(([banco, inputId]) => {
        const el = document.getElementById('editar-' + inputId);
        if (el) el.value = frota[banco] ?? '';
      });

      // Foto
      if (frota.foto_capa) {
        fotoPreview.src = 'uploads/frotas/' + frota.foto_capa;
      }

      // Confiabilidade
      const confiabilidadeSelect = document.getElementById('editar-confiabilidade');
      confiabilidadeSelect.innerHTML = '';
      dados.confiabilidades.forEach(c => {
        const option = document.createElement('option');
        option.value = c;
        option.text = c;
        if (c === frota.confiabilidade) option.selected = true;
        confiabilidadeSelect.appendChild(option);
      });

      // Disponibilidade
      const disponibilidadeSelect = document.getElementById('editar-disponibilidade');
      disponibilidadeSelect.innerHTML = '';
      dados.disponibilidades.forEach(d => {
        const option = document.createElement('option');
        option.value = d;
        option.text = d;
        if (d === frota.disponibilidade) option.selected = true;
        disponibilidadeSelect.appendChild(option);
      });

      // Marca
      const marcaSelect = document.getElementById('editar-marca');
      marcaSelect.innerHTML = '<option value="">Selecione...</option>';
      dados.marcas.forEach(m => {
        const option = document.createElement('option');
        option.value = m.id;
        option.textContent = m.marca;
        if (m.id == frota.marca) option.selected = true;
        marcaSelect.appendChild(option);
      });

      // Modelo
      const modeloSelect = document.getElementById('editar-modelo');
      modeloSelect.innerHTML = '<option value="">Selecione...</option>';
      dados.modelos.forEach(mo => {
        const option = document.createElement('option');
        option.value = mo.id;
        option.textContent = mo.nome_modelo;
        if (mo.id == frota.modelo) option.selected = true;
        modeloSelect.appendChild(option);
      });

      // Atualizar modelos ao mudar a marca
      marcaSelect.addEventListener('change', () => {
        const marcaId = marcaSelect.value;
        modeloSelect.innerHTML = '<option value="">Carregando...</option>';
        fetch(`includes/frota/buscar_modelos_editar.php?marca_id=${marcaId}`)
          .then(res => res.json())
          .then(data => {
            modeloSelect.innerHTML = '<option value="">Selecione...</option>';
            if (data.sucesso) {
              data.modelos.forEach(mo => {
                const option = document.createElement('option');
                option.value = mo.id;
                option.textContent = mo.nome_modelo;
                modeloSelect.appendChild(option);
              });
            } else {
              modeloSelect.innerHTML = `<option value="">${data.mensagem}</option>`;
            }
          })
          .catch(err => console.error('Erro ao carregar modelos:', err));
      });

    })
    .catch(err => alert('Erro ao buscar dados: ' + err.message));
};

                
    

  // FILTROS - Toggle exibição
  window.toggleFiltros = function() {
    const container = document.getElementById('filtros-container');
    if (container) {
      container.style.display = container.style.display === 'none' ? 'block' : 'none';
    }
  };

// Exportar frota para Excel - apenas uma planilha por clique
document.getElementById('btnExportarExcelFrota').addEventListener('click', function(e) {
    e.preventDefault(); // previne comportamento padrão
    window.location.href = 'includes/frota/exportar_frota_excel.php';
});
      
      
(function(){
    const btn = document.getElementById('btnExportarPDFLivro');
    if (!btn) return;

    btn.addEventListener('click', function(e){
        e.preventDefault();
        e.stopPropagation();

        // Pega o ID da viatura do modal atual
        const idViatura = document.getElementById('viatura-prefixo').dataset.id; // setar data-id no prefixo
        if(!idViatura){
            alert('Viatura inválida.');
            return;
        }

        const url = `pdf/gerar_livro_viatura.php?id=${idViatura}`;

        const win = window.open('', '_blank');
        win.document.write(`
            <html>
            <head>
                <title>Gerando PDF...</title>
                <style>
                    body { font-family: Arial, sans-serif; text-align:center; margin:0; }
                    .loader { margin-top: 20vh; }
                    .spinner { border: 6px solid #f3f3f3; border-top: 6px solid #0d6efd; border-radius: 50%; width: 50px; height: 50px; animation: spin 1s linear infinite; margin: 20px auto; }
                    @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
                    iframe { display:none; width:100%; height:100vh; border:none; }
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





 
};



