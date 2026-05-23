// JavaScript Document

// LISTAGEM DAS FICHAS DE VIATURAS
window.inicializarSolicitacaoFichas = function () {
 window.toggleFiltrosFichas = function() {
  const container = document.getElementById('filtros-container-fichas');
  if (container) {
    container.style.display = container.style.display === 'none' ? 'block' : 'none';
  }
};
	
 // FILTROS DA PÁGINA DE FICHAS STA
const formFICHA = document.getElementById('filtroFichaForm');

function atualizarListaFICHAS(extraParams = {}) {
  if (!formFICHA) return;

  const formData = new FormData(formFICHA);
  const params = new URLSearchParams(formData);

  for (const key in extraParams) {
    params.set(key, extraParams[key]);
  }

  const url = `includes/sta_fichas/solicitacao_vtr.php?${params.toString()}`;
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
    atualizarListaFICHAS({ pagina: 1, limite: this.value });
  });
}

const btnLimparFiltrosFichas = document.getElementById('btnLimparFiltrosFichas');
if (btnLimparFiltrosFichas && formFICHA) {
  btnLimparFiltrosFichas.addEventListener('click', function (e) {
    e.preventDefault();
    formFICHA.reset();

    const limiteAtual = document.getElementById('limiteFICHAS')?.value || 10;
    const url = `includes/sta_fichas/solicitacao_vtr.php?pagina=1&limite=${limiteAtual}`;
    carregarPagina(url);
  });
}

 
// Solicitar ficha
const formSolicitarFicha = document.getElementById('form-ficha-sta');

if (formSolicitarFicha) {
  formSolicitarFicha.addEventListener('submit', function (e) {
    e.preventDefault();

    const formData = new FormData(formSolicitarFicha);

    // Solicitação sempre começa como aberta
    formData.set('status', 'Aberta');

    // Solicitação sempre começa não autorizada
    formData.set('autorizado', 'não');

    // Campos opcionais com valores padrão
    const camposOpcionais = [
      'data_saida', 'hora_saida', 'odo_saida',
      'data_retorno', 'hora_retorno', 'odo_retorno',
      'observacoes_pos_emprego'
    ];

    camposOpcionais.forEach(campo => {
      let valor = formData.get(campo);

      if (!valor || valor === "") {
        if (campo.includes('data')) valor = '0001-01-01';
        else if (campo.includes('hora')) valor = '00:00';
        else if (campo.includes('odo')) valor = '0';
        else valor = '';

        formData.set(campo, valor);
      }
    });

    function enviarSolicitacaoFicha(force = false) {
      if (force) formData.set('force', '1');

      fetch('includes/sta_fichas/salvar_solicitacao_ficha.php', {
        method: 'POST',
        body: formData
      })
      .then(res => res.json())
      .then(data => {
        if (data.status === 'sucesso') {
          swal("Sucesso!", data.mensagem || "Solicitação cadastrada com sucesso!", "success")
            .then(() => {
              carregarPagina('includes/sta_fichas/solicitacao_vtr.php');
              fecharModalAberto();
            });

        } else if (data.status === 'confirmar') {
          swal({
            title: "Conflito de Ficha!",
            text: data.mensagem || "Já existe uma ficha aberta neste intervalo. Deseja continuar mesmo assim?",
            icon: "warning",
            buttons: ["Cancelar", "Sim, solicitar"],
            dangerMode: true,
          }).then((confirmado) => {
            if (confirmado) {
              enviarSolicitacaoFicha(true);
            }
          });

        } else {
          swal("Erro!", data.mensagem || "Erro ao cadastrar solicitação.", "error");
        }
      })
      .catch(err => {
        swal("Erro!", "Erro de rede ou JSON inválido: " + err.message, "error");
      });
    }

    enviarSolicitacaoFicha();
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
          carregarPagina('includes/sta_fichas/solicitacao_vtr.php');
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
                  carregarPagina('includes/sta_fichas/solicitacao_vtr.php');
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

window.deletarSolicitacaoFicha = function(botao) {
  const id = botao.getAttribute('data-id');

  Swal.fire({
    title: 'Deletar Solicitação?',
    text: "Essa ação não poderá ser desfeita. Deseja realmente excluir esta solicitação de viatura?",
    icon: 'warning',
    showCancelButton: true,
    confirmButtonText: 'Sim, deletar',
    cancelButtonText: 'Cancelar'
  }).then((result) => {
    if (result.isConfirmed) {
      const formData = new FormData();
      formData.append('id', id);

      fetch('includes/sta_fichas/deletar_solicitacao_ficha.php', {
        method: 'POST',
        body: formData
      })
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          Swal.fire({
            icon: 'success',
            title: 'Deletado!',
            text: data.message || 'Solicitação excluída com sucesso.',
            timer: 1500,
            showConfirmButton: false
          }).then(() => {
            carregarPagina('includes/sta_fichas/solicitacao_vtr.php');
          });

        } else {
          Swal.fire({
            icon: 'error',
            title: 'Erro',
            text: data.message || 'Erro ao deletar solicitação.'
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

document.querySelectorAll('.btn-deletar-solicitacao-ficha').forEach(botao => {
  botao.addEventListener('click', function() {
    deletarSolicitacaoFicha(this);
  });
});

    
};

