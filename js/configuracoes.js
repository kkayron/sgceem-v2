// ===================== CADASTRO DE TIPOS E VTR/EQP =====================
window.inicializarTipoVtrEqp = function() {
    
    // ===================== EDITAR TIPO VTR/EQP =====================
window.editarTipoRV = function(id) {
  const form = document.getElementById('formEditarTipoRV');
  if (!form) return;

  fetch(`includes/fin_tipos_vtr/buscar_tiporv.php?id=${id}`)
    .then(res => res.json())
    .then(data => {
      if (data.status !== 'sucesso') {
        Swal.fire("Erro", data.mensagem || "Erro ao carregar tipo.", "error");
        return;
      }

      const tipo = data.tipo;

      form.querySelector('#editarTipoRVId').value = tipo.id;
      form.querySelector('#editarAbreviatura').value = tipo.abreviatura;
      form.querySelector('#editarDescricao').value = tipo.descricao;

      // ✅ SELECT (Categoria)
      const selectTipo = form.querySelector('#editarTipo');
      if (selectTipo) {
        selectTipo.value = tipo.tipo; // "Eqp" ou "Vtr"
      }
    })
    .catch(err => Swal.fire("Erro de rede", err.message, "error"));
};
    
// ===================== SUBMISSÃO DO FORM DE EDIÇÃO =====================
const formEditarTipoRV = document.getElementById('formEditarTipoRV');

if (formEditarTipoRV) {
  formEditarTipoRV.addEventListener('submit', function(e) {
    e.preventDefault();

    const formData = new FormData(formEditarTipoRV);

    fetch('includes/fin_tipos_vtr/editar_tiporv.php', {
      method: 'POST',
      body: formData
    })
    .then(res => res.json())
    .then(data => {
      if (data.status === 'sucesso') {
        Swal.fire(
          "Sucesso!",
          data.mensagem || "Tipo de VTR/EQP atualizado com sucesso!",
          "success"
        ).then(() => {
          carregarPagina('includes/fin_tipos_vtr/listagem.php');
          fecharModalAberto();
        });
      } else {
        Swal.fire(
          "Erro!",
          data.mensagem || "Erro ao editar o tipo de VTR/EQP.",
          "error"
        );
      }
    })
    .catch(err => {
      Swal.fire("Erro de rede", err.message, "error");
    });
  });
}
    
// ===================== DELETAR TIPO VTR / EQP =====================
window.deletarTipoVtrEqp = function(botao) {
  const id = botao.getAttribute('data-id');
  const token = botao.dataset.token; // pega o token

  Swal.fire({
    title: 'Deletar Categoria?',
    text: 'Essa ação não poderá ser desfeita. Deseja realmente excluir esta categoria?',
    icon: 'warning',
    showCancelButton: true,
    confirmButtonText: 'Sim, deletar',
    cancelButtonText: 'Cancelar'
  }).then((result) => {
    if (result.isConfirmed) {
      const formData = new FormData();
      formData.append('id', id);
      formData.append('csrf_token', token); // 🔒 envia o token

      fetch('includes/fin_tipos_vtr/deletar_tipo.php', {
        method: 'POST',
        body: formData
      })
      .then(res => res.json())
      .then(data => {
        if (data.success) {
          Swal.fire({
            icon: 'success',
            title: 'Deletado!',
            text: data.message || 'Categoria excluída com sucesso.',
            timer: 1500,
            showConfirmButton: false
          }).then(() => {
            carregarPagina('includes/fin_tipos_vtr/listagem.php');
          });
        } else {
          Swal.fire({
            icon: 'error',
            title: 'Erro',
            text: data.message || 'Erro ao deletar categoria.'
          });
        }
      })
      .catch(err => {
        Swal.fire({
          icon: 'error',
          title: 'Erro de rede',
          text: err.message
        });
      });
    }
  });
};



    
    // ===================== TOGGLE FILTROS =====================
window.toggleFiltrosTipoRV = function() {
  const container = document.getElementById('filtros-container-tiporv');
  if (container) {
    container.style.display = container.style.display === 'none' ? 'block' : 'none';
  }
};

// ===================== FILTROS =====================
const formFiltrosTipoRV = document.getElementById('filtroTipoRVForm');

function atualizarListaTipoRV(extraParams = {}) {
  if (!formFiltrosTipoRV) return;

  const formData = new FormData(formFiltrosTipoRV);
  const params = new URLSearchParams(formData);

  // parâmetros adicionais (como página, limite)
  for (const key in extraParams) {
    params.set(key, extraParams[key]);
  }

  const url = `includes/fin_tipos_vtr/listagem.php?${params.toString()}`;
  carregarPagina(url);
}

// Submit do formulário de filtros
if (formFiltrosTipoRV) {
  formFiltrosTipoRV.addEventListener('submit', function(e) {
    e.preventDefault();
    atualizarListaTipoRV({ pagina: 1 });
  });
}

// Mudança de limite
const limiteSelectTipoRV = document.getElementById('limiteTipoRV');
if (limiteSelectTipoRV) {
  limiteSelectTipoRV.addEventListener('change', function() {
    atualizarListaTipoRV({ pagina: 1, limite: this.value });
  });
}

// Botão limpar filtros
const btnLimparFiltrosTipoRV = document.getElementById('btnLimparFiltrosTipoRV');
if (btnLimparFiltrosTipoRV && formFiltrosTipoRV) {
  btnLimparFiltrosTipoRV.addEventListener('click', function(e) {
    e.preventDefault();
    formFiltrosTipoRV.reset();

    const limiteAtual = document.getElementById('limiteTipoRV')?.value || 10;
    const url = `includes/fin_tipos_vtr/listagem.php?pagina=1&limite=${limiteAtual}`;
    carregarPagina(url);
  });
}

// ===================== PAGINAÇÃO =====================
function inicializarPaginacaoTipoRV() {
  document.querySelectorAll('.pagination a.page-link').forEach(link => {
    link.addEventListener('click', function(e) {
      e.preventDefault();
      const url = this.getAttribute('href');
      if (url && !url.startsWith('#')) {
        carregarPagina(url);
      }
    });
  });
}

inicializarPaginacaoTipoRV();

    
   // ===================== CADASTRAR TIPO =====================
const formCadastroTipo = document.getElementById('formCadastroTipo');

if (formCadastroTipo) {
  formCadastroTipo.addEventListener('submit', function (e) {
    e.preventDefault();

    const formData = new FormData(formCadastroTipo);

    fetch('includes/fin_tipos_vtr/cadastrar_tipo.php', {
      method: 'POST',
      body: formData
    })
      .then(res => res.text())
      .then(text => {
        try {
          return JSON.parse(text);
        } catch (e) {
          console.error("Retorno inválido do PHP:", text);
          throw new Error("A resposta do servidor não está em formato JSON.");
        }
      })
      .then(data => {
        if (data.csrf_token) {
          const tokenInput = formCadastroTipo.querySelector('[name="csrf_token"]');
          if (tokenInput) {
            tokenInput.value = data.csrf_token;
          }
        }

        if (data.status === 'sucesso') {
          Swal.fire({
            icon: 'success',
            title: 'Sucesso!',
            text: data.mensagem || 'Tipo cadastrado com sucesso!'
          }).then(() => {
            formCadastroTipo.reset();
            carregarPagina('includes/fin_tipos_vtr/listagem.php');
            fecharModalAberto();
          });
        } else {
          Swal.fire({
            icon: 'error',
            title: 'Erro!',
            text: data.mensagem || 'Erro ao cadastrar o tipo.'
          });
        }
      })
      .catch(err => {
        Swal.fire({
          icon: 'error',
          title: 'Erro',
          text: err.message || 'Erro inesperado ao cadastrar.'
        });
      });
  });
}


    
}


// ===================== LISTAGEM DE DESTINOS =====================
window.inicializarDestinos = function() {
    // ===================== TOGGLE FILTROS =====================
window.toggleFiltrosDestinos = function() {
  const container = document.getElementById('filtros-container-destinos');
  if (container) {
    container.style.display = container.style.display === 'none' ? 'block' : 'none';
  }
};

// ===================== FILTROS =====================
const formFiltrosDestinos = document.getElementById('filtroDestinosForm');

function atualizarListaDestinos(extraParams = {}) {
  if (!formFiltrosDestinos) return;
  
  const formData = new FormData(formFiltrosDestinos);
  const params = new URLSearchParams(formData);

  // Adiciona parâmetros extras (como paginação, limite, etc.)
  for (const key in extraParams) {
    params.set(key, extraParams[key]);
  }

  // Caminho do PHP de listagem
  const url = `includes/config_destinos/listagem.php?${params.toString()}`;
  carregarPagina(url);
}

// Quando o formulário for enviado
if (formFiltrosDestinos) {
  formFiltrosDestinos.addEventListener('submit', function(e) {
    e.preventDefault();
    atualizarListaDestinos({ pagina: 1 });
  });
}

// Alteração de limite de registros
const limiteSelectDestinos = document.getElementById('limiteDestinos');
if (limiteSelectDestinos) {
  limiteSelectDestinos.addEventListener('change', function() {
    atualizarListaDestinos({ pagina: 1, limite: this.value });
  });
}

// Botão de limpar filtros
const btnLimparFiltrosDestinos = document.getElementById('btnLimparFiltrosDestinos');
if (btnLimparFiltrosDestinos && formFiltrosDestinos) {
  btnLimparFiltrosDestinos.addEventListener('click', function(e) {
    e.preventDefault();
    formFiltrosDestinos.reset();

    // Reseta a listagem com limite padrão
    const limiteAtual = document.getElementById('limiteDestinos')?.value || 10;
    const url = `includes/config_destinos/listagem.php?pagina=1&limite=${limiteAtual}`;
    carregarPagina(url);
  });
}

// ===================== CADASTRAR DESTINO =====================
const formCadastroDestino = document.getElementById('formCadastroDestino');

if (formCadastroDestino) {
  formCadastroDestino.addEventListener('submit', function (e) {
    e.preventDefault();

    const formData = new FormData(formCadastroDestino);

    fetch('includes/config_destinos/cadastrar_destino.php', {
      method: 'POST',
      body: formData
    })
      .then(res => res.text())
      .then(text => {
        try {
          return JSON.parse(text);
        } catch (err) {
          console.error("Retorno inválido do PHP:", text);
          throw new Error("A resposta do servidor não está em formato JSON.");
        }
      })
      .then(data => {
        if (data.status === 'sucesso') {
          Swal.fire({
            icon: 'success',
            title: 'Sucesso!',
            text: data.mensagem || 'Destino cadastrado com sucesso!'
          }).then(() => {
            carregarPagina('includes/config_destinos/listagem.php');
            const modalEl = document.getElementById('modalCadastroDestino');
            const modal = bootstrap.Modal.getInstance(modalEl);
            modal.hide();
          });
        } else {
          Swal.fire({
            icon: 'error',
            title: 'Erro!',
            text: data.mensagem || 'Erro ao cadastrar o destino.'
          });
        }
      })
      .catch(err => {
        console.error(err);
        Swal.fire({
          icon: 'error',
          title: 'Erro de rede',
          text: err.message || 'Falha ao comunicar com o servidor.'
        });
      });
  });
}

// ===================== BUSCAR DESTINO PARA EDIÇÃO =====================
window.editarDestino = function (id) {
  fetch(`includes/config_destinos/buscar_destino.php?id=${id}`)
    .then(res => res.text())
    .then(text => {
      try {
        return JSON.parse(text);
      } catch (err) {
        console.error("Retorno inválido do PHP:", text);
        throw new Error("A resposta do servidor não está em formato JSON.");
      }
    })
    .then(data => {
      if (data.status === 'sucesso') {
        document.getElementById('id_destino_editar').value = data.destino.id;
        document.getElementById('batalhao_destino_editar').value = data.destino.batalhao;
        document.getElementById('nome_destino_editar').value = data.destino.destino;
      } else {
        Swal.fire({
          icon: 'error',
          title: 'Erro!',
          text: data.mensagem || 'Erro ao buscar o destino.'
        });
      }
    })
    .catch(err => {
      Swal.fire({
        icon: 'error',
        title: 'Erro de rede',
        text: err.message || 'Falha ao comunicar com o servidor.'
      });
    });
};

// ===================== SALVAR ALTERAÇÕES =====================
const formEditarDestino = document.getElementById('formEditarDestino');

if (formEditarDestino) {
  formEditarDestino.addEventListener('submit', function (e) {
    e.preventDefault();

    const formData = new FormData(formEditarDestino);

    fetch('includes/config_destinos/editar_destino.php', {
      method: 'POST',
      body: formData
    })
      .then(res => res.text())
      .then(text => {
        try {
          return JSON.parse(text);
        } catch (err) {
          console.error("Retorno inválido do PHP:", text);
          throw new Error("A resposta do servidor não está em formato JSON.");
        }
      })
      .then(data => {
        if (data.status === 'sucesso') {
          Swal.fire({
            icon: 'success',
            title: 'Sucesso!',
            text: data.mensagem || 'Destino atualizado com sucesso!'
          }).then(() => {
            carregarPagina('includes/config_destinos/listagem.php');
            const modalEl = document.getElementById('modalEditarDestino');
            const modal = bootstrap.Modal.getInstance(modalEl);
            modal.hide();
          });
        } else {
          Swal.fire({
            icon: 'error',
            title: 'Erro!',
            text: data.mensagem || 'Erro ao atualizar destino.'
          });
        }
      })
      .catch(err => {
        Swal.fire({
          icon: 'error',
          title: 'Erro de rede',
          text: err.message || 'Falha ao comunicar com o servidor.'
        });
      });
  });
}

// js/config_destinos.js

// ===================== EXCLUIR DESTINO =====================
window.deletarDestino = function (btn) {

  const id = btn.getAttribute("data-id");

  Swal.fire({
    title: "Tem certeza?",
    text: "Você não poderá reverter essa ação!",
    icon: "warning",
    showCancelButton: true,
    confirmButtonColor: "#d33",
    cancelButtonColor: "#3085d6",
    confirmButtonText: "Sim, excluir",
    cancelButtonText: "Cancelar"
  }).then((result) => {

    if (result.isConfirmed) {

      const formData = new FormData();
      formData.append("id", id);

      fetch("includes/config_destinos/excluir_destino.php", {
        method: "POST",
        body: formData
      })
        .then(res => res.text())
        .then(text => {
          try {
            return JSON.parse(text);
          } catch (err) {
            console.error("Retorno inválido do PHP:", text);
            throw new Error("A resposta não está em formato JSON.");
          }
        })
        .then(data => {

          if (data.status === "sucesso") {

            Swal.fire({
              icon: "success",
              title: "Excluído!",
              text: data.mensagem || "Destino excluído com sucesso."
            }).then(() => {
              carregarPagina("includes/config_destinos/listagem.php");
            });

          } else {
            Swal.fire({
              icon: "error",
              title: "Erro!",
              text: data.mensagem || "Não foi possível excluir."
            });
          }

        })
        .catch(err => {
          Swal.fire({
            icon: "error",
            title: "Erro de rede",
            text: err.message || "Falha ao comunicar com o servidor."
          });
        });

    }

  });

};



    };