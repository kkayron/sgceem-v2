// JavaScript Document

window.inicializarPlanoMnt = function () {
	window.toggleFiltrosPlanosMnt = function() {
  const container = document.getElementById('filtros-container-planos-mnt');
  if (container) {
    container.style.display = container.style.display === 'none' ? 'block' : 'none';
  }
};
	
// FILTROS DA PÁGINA DE PLANOS DE MANUTENÇÃO
const formPlanoMnt = document.getElementById('filtroPlanoMntForm');

function atualizarListaPlanosMnt(extraParams = {}) {
  if (!formPlanoMnt) return;

  const formData = new FormData(formPlanoMnt);
  const params = new URLSearchParams(formData);

  for (const key in extraParams) {
    params.set(key, extraParams[key]);
  }

  const url = `includes/plano_mnt/listagem.php?${params.toString()}`;
  carregarPagina(url);
}

// Submissão do formulário
if (formPlanoMnt) {
  formPlanoMnt.addEventListener('submit', function (e) {
    e.preventDefault();
    atualizarListaPlanosMnt({ pagina: 1 });
  });
}

// Seleção de limite
const limiteSelectPlanosMnt = document.getElementById('limitePlanosMnt');

if (limiteSelectPlanosMnt) {
  limiteSelectPlanosMnt.addEventListener('change', function () {
    atualizarListaPlanosMnt({
      pagina: 1,
      limite: this.value
    });
  });
}

// Botão limpar filtros
const btnLimparFiltrosPlanosMnt = document.getElementById('btnLimparFiltrosPlanosMnt');

if (btnLimparFiltrosPlanosMnt && formPlanoMnt) {
  btnLimparFiltrosPlanosMnt.addEventListener('click', function (e) {
    e.preventDefault();

    formPlanoMnt.reset();

    const limiteAtual = document.getElementById('limitePlanosMnt')?.value || 10;
    const url = `includes/plano_mnt/listagem.php?pagina=1&limite=${limiteAtual}`;

    carregarPagina(url);
  });
}

// Paginação
document.addEventListener('click', function (e) {
  const link = e.target.closest('.paginacao-planos-mnt');

  if (!link) return;

  e.preventDefault();

  const url = link.getAttribute('data-page');

  if (url) {
    carregarPagina(url);
  }
});	


// CADASTRAR PLANO DE MANUTENÇÃO
const formPlanoMntCadastro = document.getElementById('form-plano-mnt');

function filtrarModelosPlanoMnt() {
  const selectMarca = document.getElementById('mnt_id_marca');
  const selectModelo = document.getElementById('mnt_id_modelo');

  if (!selectMarca || !selectModelo) return;

  const marcaSelecionada = selectMarca.value;

  Array.from(selectModelo.options).forEach(option => {
    if (!option.value) {
      option.hidden = false;
      return;
    }

    option.hidden = option.dataset.marca !== marcaSelecionada;
  });

  selectModelo.value = '';
}

const selectMarcaPlanoMnt = document.getElementById('mnt_id_marca');
if (selectMarcaPlanoMnt) {
  selectMarcaPlanoMnt.addEventListener('change', filtrarModelosPlanoMnt);
}

function ajustarCamposTipoPlanoMnt() {
  const tipo = document.getElementById('mnt_tipo_controle')?.value;

  const camposValor = document.querySelectorAll('.campo-valor-mnt');
  const camposTempo = document.querySelectorAll('.campo-tempo-mnt');

  camposValor.forEach(campo => {
    campo.disabled = tipo === 'tempo' || tipo === 'conforme_necessidade';
    if (tipo === 'tempo' || tipo === 'conforme_necessidade') campo.value = '';
  });

  camposTempo.forEach(campo => {
    campo.disabled = tipo === 'odometro' || tipo === 'horimetro' || tipo === 'conforme_necessidade';
    if (tipo === 'odometro' || tipo === 'horimetro' || tipo === 'conforme_necessidade') campo.value = '';
  });
}

const selectTipoPlanoMnt = document.getElementById('mnt_tipo_controle');
if (selectTipoPlanoMnt) {
  selectTipoPlanoMnt.addEventListener('change', ajustarCamposTipoPlanoMnt);
}

if (formPlanoMntCadastro) {
  formPlanoMntCadastro.addEventListener('submit', function (e) {
    e.preventDefault();

    const formData = new FormData(formPlanoMntCadastro);

    fetch('includes/plano_mnt/salvar_plano.php', {
      method: 'POST',
      body: formData
    })
    .then(res => res.json())
    .then(data => {
      if (data.status === 'sucesso') {
        swal("Sucesso!", data.mensagem || "Plano cadastrado com sucesso!", "success")
          .then(() => {
            formPlanoMntCadastro.reset();
            carregarPagina('includes/plano_mnt/listagem.php');
            fecharModalAberto();
          });
      } else {
        swal("Erro!", data.mensagem || "Erro ao cadastrar o plano.", "error");
      }
    })
    .catch(err => {
      swal("Erro!", "Erro de rede ou JSON inválido: " + err.message, "error");
    });
  });
}

// MODELOS DO PLANO DE MANUTENÇÃO
// Se você já declarou no cadastro, não precisa repetir.
const modelosPlanoMntEdit = typeof modelosPlanoMnt !== 'undefined'
  ? modelosPlanoMnt
  : [];

// FILTRAR MODELOS NO MODAL DE EDIÇÃO
function filtrarModelosPlanoMntEdit(modeloSelecionado = '') {
  const selectMarca = document.getElementById('edit_mnt_id_marca');
  const selectModelo = document.getElementById('edit_mnt_id_modelo');

  if (!selectMarca || !selectModelo) return;

  const marcaSelecionada = selectMarca.value;

  selectModelo.innerHTML = '';

  if (!marcaSelecionada) {
    selectModelo.disabled = true;
    selectModelo.innerHTML = '<option value="">Selecione a marca primeiro...</option>';
    return;
  }

  selectModelo.disabled = false;
  selectModelo.innerHTML = '<option value="">Selecione...</option>';

  modelosPlanoMntEdit
    .filter(modelo => modelo.id_marca == marcaSelecionada)
    .forEach(modelo => {
      const option = document.createElement('option');
      option.value = modelo.id;
      option.textContent = modelo.nome_modelo;

      if (String(modelo.id) === String(modeloSelecionado)) {
        option.selected = true;
      }

      selectModelo.appendChild(option);
    });
}

const selectMarcaPlanoMntEdit = document.getElementById('edit_mnt_id_marca');

if (selectMarcaPlanoMntEdit) {
  selectMarcaPlanoMntEdit.addEventListener('change', function () {
    filtrarModelosPlanoMntEdit('');
  });
}

// AJUSTAR CAMPOS POR TIPO NA EDIÇÃO
function ajustarCamposTipoPlanoMntEdit() {
  const tipo = document.getElementById('edit_mnt_tipo_controle')?.value;

  const camposValor = document.querySelectorAll('.campo-edit-valor-mnt');
  const camposTempo = document.querySelectorAll('.campo-edit-tempo-mnt');

  camposValor.forEach(campo => {
    campo.disabled = tipo === 'tempo' || tipo === 'conforme_necessidade';
    if (tipo === 'tempo' || tipo === 'conforme_necessidade') campo.value = '';
  });

  camposTempo.forEach(campo => {
    campo.disabled = tipo === 'odometro' || tipo === 'horimetro' || tipo === 'conforme_necessidade';
    if (tipo === 'odometro' || tipo === 'horimetro' || tipo === 'conforme_necessidade') campo.value = '';
  });
}

const selectTipoPlanoMntEdit = document.getElementById('edit_mnt_tipo_controle');

if (selectTipoPlanoMntEdit) {
  selectTipoPlanoMntEdit.addEventListener('change', ajustarCamposTipoPlanoMntEdit);
}

// ABRIR MODAL DE EDIÇÃO E CARREGAR DADOS
window.editarPlanoMnt = function(id) {
  if (!id) {
    swal("Erro!", "ID do plano não informado.", "error");
    return;
  }

  fetch(`includes/plano_mnt/buscar_plano.php?id=${id}`)
    .then(res => res.json())
    .then(data => {
      if (data.status !== 'sucesso') {
        swal("Erro!", data.mensagem || "Não foi possível carregar o plano.", "error");
        return;
      }

      const plano = data.plano;

      document.getElementById('edit_mnt_id').value = plano.id;
      document.getElementById('edit_mnt_id_marca').value = plano.id_marca;
      document.getElementById('edit_mnt_descricao').value = plano.descricao || '';
      document.getElementById('edit_mnt_tipo_controle').value = plano.tipo_controle || '';
      document.getElementById('edit_mnt_ativo').value = plano.ativo;

      document.getElementById('edit_mnt_valor_inicial').value = plano.valor_inicial ?? '';
      document.getElementById('edit_mnt_intervalo_valor').value = plano.intervalo_valor ?? '';
      document.getElementById('edit_mnt_intervalo_dias').value = plano.intervalo_dias ?? '';
      document.getElementById('edit_mnt_alerta_antes_valor').value = plano.alerta_antes_valor ?? '';
      document.getElementById('edit_mnt_alerta_antes_dias').value = plano.alerta_antes_dias ?? '';

      filtrarModelosPlanoMntEdit(plano.id_modelo);
      ajustarCamposTipoPlanoMntEdit();
    })
    .catch(err => {
      swal("Erro!", "Erro ao buscar plano: " + err.message, "error");
    });
}

// SALVAR EDIÇÃO
const formEditarPlanoMnt = document.getElementById('form-editar-plano-mnt');

if (formEditarPlanoMnt) {
  formEditarPlanoMnt.addEventListener('submit', function (e) {
    e.preventDefault();

    const formData = new FormData(formEditarPlanoMnt);

    fetch('includes/plano_mnt/editar_plano.php', {
      method: 'POST',
      body: formData
    })
    .then(res => res.json())
    .then(data => {
      if (data.status === 'sucesso') {
        swal("Sucesso!", data.mensagem || "Plano atualizado com sucesso!", "success")
          .then(() => {
            carregarPagina('includes/plano_mnt/listagem.php');
            fecharModalAberto();
          });
      } else {
        swal("Erro!", data.mensagem || "Erro ao atualizar o plano.", "error");
      }
    })
    .catch(err => {
      swal("Erro!", "Erro de rede ou JSON inválido: " + err.message, "error");
    });
  });
}

window.deletarPlanoMnt = function(botao) {
  const id = botao.getAttribute('data-id');

  Swal.fire({
    title: 'Deletar Plano?',
    text: "Essa ação não poderá ser desfeita. Deseja realmente excluir este plano de manutenção?",
    icon: 'warning',
    showCancelButton: true,
    confirmButtonText: 'Sim, deletar',
    cancelButtonText: 'Cancelar'
  }).then((result) => {
    if (result.isConfirmed) {
      const formData = new FormData();
      formData.append('id', id);

      fetch('includes/plano_mnt/deletar_plano.php', {
        method: 'POST',
        body: formData
      })
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          Swal.fire({
            icon: 'success',
            title: 'Deletado!',
            text: data.message || 'Plano excluído com sucesso.',
            timer: 1500,
            showConfirmButton: false
          }).then(() => {
            carregarPagina('includes/plano_mnt/listagem.php');
          });
        } else {
          Swal.fire({
            icon: 'error',
            title: 'Erro',
            text: data.message || 'Erro ao deletar plano.'
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

// Delegação opcional
document.querySelectorAll('.btn-deletar-plano-mnt').forEach(botao => {
  botao.addEventListener('click', function() {
    deletarPlanoMnt(this);
  });
});

window.verPlanoMnt = function(id) {
  if (!id) {
    Swal.fire("Erro!", "ID do plano não informado.", "error");
    return;
  }

  const container = document.getElementById('conteudo-ver-plano-mnt');

  if (container) {
    container.innerHTML = `
      <div class="text-center py-5">
        <div class="spinner-border text-primary" role="status"></div>
        <p class="mt-3 text-muted">Carregando dados do plano...</p>
      </div>
    `;
  }

  fetch(`includes/plano_mnt/ver_plano.php?id=${id}`)
    .then(res => res.text())
    .then(html => {
      if (container) {
        container.innerHTML = html;
      }
    })
    .catch(error => {
      if (container) {
        container.innerHTML = `
          <div class="alert alert-danger">
            Erro ao carregar os dados do plano: ${error.message}
          </div>
        `;
      }
    });
};

const formImportarPlanoMnt = document.getElementById('formImportarPlanoMnt');

if (formImportarPlanoMnt) {
  formImportarPlanoMnt.addEventListener('submit', function(e) {
    e.preventDefault();

    const formData = new FormData(formImportarPlanoMnt);

    fetch('includes/plano_mnt/importar_planos.php', {
      method: 'POST',
      body: formData
    })
    .then(res => res.json())
    .then(data => {
      if (data.status === 'ok') {
        let mensagem = data.mensagem || 'Importação concluída.';

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
          carregarPagina('includes/plano_mnt/listagem.php');
          fecharModalAberto();
        });

      } else {
        let erroMsg = data.mensagem || 'Verifique o arquivo e tente novamente.';

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
	
};
