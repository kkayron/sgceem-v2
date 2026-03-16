// ===================== LISTAGEM DE FUNÇÕES =====================
window.inicializarOMS = function() {
// ===================== TOGGLE FILTROS =====================
window.toggleFiltrosOM = function() {
  const container = document.getElementById('filtros-container-oms');
  if (container) {
    container.style.display = container.style.display === 'none' ? 'block' : 'none';
  }
};

// ===================== DELETAR OM =====================
window.deletarOM = function(botao) {
  const id = botao.getAttribute('data-id');

  Swal.fire({
    title: 'Deletar Organização Militar?',
    text: "Essa ação não poderá ser desfeita. Deseja realmente excluir esta OM?",
    icon: 'warning',
    showCancelButton: true,
    confirmButtonText: 'Sim, deletar',
    cancelButtonText: 'Cancelar'
  }).then((result) => {
    if (result.isConfirmed) {
      const formData = new FormData();
      formData.append('id', id);

      fetch('includes/oms/deletar_om.php', {
        method: 'POST',
        body: formData
      })
      .then(res => res.json())
      .then(data => {
        if (data.success) {
          Swal.fire({
            icon: 'success',
            title: 'Deletado!',
            text: 'Organização Militar excluída com sucesso.',
            timer: 1500,
            showConfirmButton: false
          }).then(() => {
            carregarPagina('includes/oms/listagem.php');
          });
        } else {
          Swal.fire({
            icon: 'error',
            title: 'Erro',
            text: data.message || 'Erro ao deletar Organização Militar.'
          });
        }
      })
      .catch(err => {
        Swal.fire({ icon: 'error', title: 'Erro de rede', text: err.message });
      });
    }
  });
};

// Delega evento para todos os botões de deletar OM
document.querySelectorAll('.btn-deletar-om').forEach(botao => {
  botao.addEventListener('click', function() {
    deletarOM(this);
  });
});

// ===================== CADASTRAR OM =====================
const formCadastroOM = document.getElementById('formCadastroOM');
if (formCadastroOM) {
  formCadastroOM.addEventListener('submit', function(e) {
    e.preventDefault();
    const formData = new FormData(formCadastroOM);

    fetch('includes/oms/cadastrar_om.php', {
      method: 'POST',
      body: formData
    })
    .then(res => res.json())
    .then(data => {
      if (data.status === 'sucesso') {
        Swal.fire("Sucesso!", data.mensagem || "Organização Militar cadastrada com sucesso!", "success")
          .then(() => {
            carregarPagina('includes/oms/listagem.php');
            fecharModalAberto();
          });
      } else {
        Swal.fire("Erro!", data.mensagem || "Erro ao cadastrar OM.", "error");
      }
    })
    .catch(err => Swal.fire("Erro de rede", err.message, "error"));
  });
}

// ===================== EDITAR OM =====================
window.editarOM = function(id) {
  const form = document.getElementById('formEditarOM');
  if (!form) return;

  fetch(`includes/oms/buscar_om.php?id=${id}`)
    .then(res => res.json())
    .then(data => {
      if (data.status !== 'sucesso') {
        Swal.fire("Erro", data.mensagem || "Erro ao carregar Organização Militar.", "error");
        return;
      }

      const om = data.om;
      form.querySelector('#editarOMId').value = om.id;
      form.querySelector('#editarNomeOM').value = om.nome;
      form.querySelector('#editarAbreviaturaOM').value = om.abreviatura;
      form.querySelector('#editarNivelOM').value = om.nivel;
    })
    .catch(err => Swal.fire("Erro de rede", err.message, "error"));
};

// Submissão do form de edição
const formEditarOM = document.getElementById('formEditarOM');
if (formEditarOM) {
  formEditarOM.addEventListener('submit', function(e) {
    e.preventDefault();
    const formData = new FormData(formEditarOM);

    fetch('includes/oms/editar_om.php', {
      method: 'POST',
      body: formData
    })
    .then(res => res.json())
    .then(data => {
      if (data.status === 'sucesso') {
        Swal.fire("Sucesso!", data.mensagem || "Organização Militar editada com sucesso!", "success")
          .then(() => {
            carregarPagina('includes/oms/listagem.php');
            fecharModalAberto();
          });
      } else {
        Swal.fire("Erro!", data.mensagem || "Erro ao editar Organização Militar.", "error");
      }
    })
    .catch(err => Swal.fire("Erro de rede", err.message, "error"));
  });
}



// ===================== FILTROS =====================
const formFiltrosOM = document.getElementById('filtroOMForm');

function atualizarListaOM(extraParams = {}) {
  if (!formFiltrosOM) return;
  const formData = new FormData(formFiltrosOM);
  const params = new URLSearchParams(formData);

  for (const key in extraParams) {
    params.set(key, extraParams[key]);
  }

  const url = `includes/oms/listagem.php?${params.toString()}`;
  carregarPagina(url);
}

if (formFiltrosOM) {
  formFiltrosOM.addEventListener('submit', function(e) {
    e.preventDefault();
    atualizarListaOM({ pagina: 1 });
  });
}

const limiteSelectOM = document.getElementById('limiteOM');
if (limiteSelectOM) {
  limiteSelectOM.addEventListener('change', function() {
    atualizarListaOM({ pagina: 1, limite: this.value });
  });
}

const btnLimparFiltrosOM = document.getElementById('btnLimparFiltrosOM');
if (btnLimparFiltrosOM && formFiltrosOM) {
  btnLimparFiltrosOM.addEventListener('click', function(e) {
    e.preventDefault();
    formFiltrosOM.reset();

    const limiteAtual = document.getElementById('limiteOM')?.value || 10;
    const url = `includes/oms/listagem.php?pagina=1&limite=${limiteAtual}`;
    carregarPagina(url);
  });
}

};


window.inicializarMARCASMODELOS = function() {
  // ===================== TOGGLE FILTROS =====================
  window.toggleFiltrosMarcas = function() {
    const container = document.getElementById('filtros-container-marcas');
    if (container) {
      container.style.display = container.style.display === 'none' ? 'block' : 'none';
    }
  };

  // ===================== FILTROS =====================
  const formFiltrosMarcas = document.getElementById('filtroMarcasForm');

  function atualizarListaMarcas(extraParams = {}) {
    if (!formFiltrosMarcas) return;
    const formData = new FormData(formFiltrosMarcas);
    const params = new URLSearchParams(formData);

    for (const key in extraParams) {
      params.set(key, extraParams[key]);
    }

    const url = `includes/marcas_modelos/listagem.php?${params.toString()}`;
    carregarPagina(url);
  }

  if (formFiltrosMarcas) {
    formFiltrosMarcas.addEventListener('submit', function(e) {
      e.preventDefault();
      atualizarListaMarcas({ pagina: 1 });
    });
  }

  const limiteSelectMarcas = document.getElementById('limiteMarcas');
  if (limiteSelectMarcas) {
    limiteSelectMarcas.addEventListener('change', function() {
      atualizarListaMarcas({ pagina: 1, limite: this.value });
    });
  }

  const btnLimparFiltrosMarcas = document.getElementById('btnLimparFiltrosMarcas');
  if (btnLimparFiltrosMarcas && formFiltrosMarcas) {
    btnLimparFiltrosMarcas.addEventListener('click', function(e) {
      e.preventDefault();
      formFiltrosMarcas.reset();

      const limiteAtual = document.getElementById('limiteMarcas')?.value || 10;
      const url = `includes/marcas_modelos/listagem.php?pagina=1&limite=${limiteAtual}`;
      carregarPagina(url);
    });
  }

  // ===================== PAGINAÇÃO =====================
  function inicializarPaginacao() {
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

  inicializarPaginacao();

  // ===================== CADASTRAR MARCA =====================
  const formCadastroMarca = document.getElementById('formCadastroMarca');

  if (formCadastroMarca) {
    formCadastroMarca.addEventListener('submit', function (e) {
      e.preventDefault();

      const formData = new FormData(formCadastroMarca);

      fetch('includes/marcas_modelos/cadastrar_marca.php', {
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
          if (data.status === 'sucesso') {
            Swal.fire({
              icon: 'success',
              title: 'Sucesso!',
              text: data.mensagem || 'Marca cadastrada com sucesso!'
            }).then(() => {
              carregarPagina('includes/marcas_modelos/listagem.php');
              fecharModalAberto();
            });
          } else {
            Swal.fire({
              icon: 'error',
              title: 'Erro!',
              text: data.mensagem || 'Erro ao cadastrar a marca.'
            });
          }
        })
        .catch(err => {
          console.error(err);
          Swal.fire({
            icon: 'error',
            title: 'Erro',
            text: err.message || 'Erro de rede ou resposta inválida.'
          });
        });
    });
  }

// ===================== EDITAR MARCA =====================
window.editarMarca = function(id) {
  const form = document.getElementById('formEditarMarca');
  if (!form) return;

  fetch(`includes/marcas_modelos/buscar_marca.php?id=${id}`)
    .then(res => res.json())
    .then(data => {
      if (data.status !== 'sucesso') {
        Swal.fire("Erro", data.mensagem || "Erro ao carregar marca.", "error");
        return;
      }

      const marca = data.marca;
      form.querySelector('#editarMarcaId').value = marca.id;
      form.querySelector('#editarNomeMarca').value = marca.marca;
    })
    .catch(err => Swal.fire("Erro de rede", err.message, "error"));
};

// ===================== SUBMISSÃO DO FORM DE EDIÇÃO =====================
const formEditarMarca = document.getElementById('formEditarMarca');
if (formEditarMarca) {
  formEditarMarca.addEventListener('submit', function(e) {
    e.preventDefault();
    const formData = new FormData(formEditarMarca);

    fetch('includes/marcas_modelos/editar_marca.php', {
      method: 'POST',
      body: formData
    })
    .then(res => res.json())
    .then(data => {
      if (data.status === 'sucesso') {
        Swal.fire("Sucesso!", data.mensagem || "Marca editada com sucesso!", "success")
          .then(() => {
            carregarPagina('includes/marcas_modelos/listagem.php');
            fecharModalAberto();
          });
      } else {
        Swal.fire("Erro!", data.mensagem || "Erro ao editar a marca.", "error");
      }
    })
    .catch(err => Swal.fire("Erro de rede", err.message, "error"));
  });
}

// ===================== DELETAR MARCA =====================
window.deletarMarca = function(botao) {
  const id = botao.getAttribute('data-id');

  Swal.fire({
    title: 'Deletar Marca?',
    text: "Essa ação não poderá ser desfeita. Deseja realmente excluir esta marca?",
    icon: 'warning',
    showCancelButton: true,
    confirmButtonText: 'Sim, deletar',
    cancelButtonText: 'Cancelar'
  }).then((result) => {
    if (result.isConfirmed) {
      const formData = new FormData();
      formData.append('id', id);

      fetch('includes/marcas_modelos/deletar_marca.php', {
        method: 'POST',
        body: formData
      })
      .then(res => res.json())
      .then(data => {
        if (data.success) {
          Swal.fire({
            icon: 'success',
            title: 'Deletado!',
            text: 'Marca excluída com sucesso.',
            timer: 1500,
            showConfirmButton: false
          }).then(() => {
            carregarPagina('includes/marcas_modelos/listagem.php');
          });
        } else {
          Swal.fire({
            icon: 'error',
            title: 'Erro',
            text: data.message || 'Erro ao deletar marca.'
          });
        }
      })
      .catch(err => {
        Swal.fire({ icon: 'error', title: 'Erro de rede', text: err.message });
      });
    }
  });
};

// ===================== DELEGAÇÃO PARA BOTÕES DELETAR =====================
document.querySelectorAll('.btn-deletar-marca').forEach(botao => {
  botao.addEventListener('click', function() {
    deletarMarca(this);
  });
});



  // ===================== CADASTRAR MODELO =====================
  const formCadastroModelo = document.getElementById('formCadastroModelo');

  if (formCadastroModelo) {
    formCadastroModelo.addEventListener('submit', function (e) {
      e.preventDefault();

      const formData = new FormData(formCadastroModelo);

      fetch('includes/marcas_modelos/cadastrar_modelos.php', {
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
              text: data.mensagem || 'Modelo cadastrado com sucesso!'
            }).then(() => {
              carregarPagina('includes/marcas_modelos/listagem.php');
              const modalEl = document.getElementById('modalCadastroModelo');
              const modal = bootstrap.Modal.getInstance(modalEl);
              modal.hide();
            });
          } else {
            Swal.fire({
              icon: 'error',
              title: 'Erro!',
              text: data.mensagem || 'Erro ao cadastrar o modelo.'
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

  // Exponha função de reinicialização para quando carregarPagina for chamado
  window.reinicializarPaginacaoMarcasModelos = inicializarPaginacao;

// ===================== EDITAR MODELO =====================
window.editarModelo = function(id) {
  const form = document.getElementById('formEditarModelo');
  if (!form) return;

  fetch(`includes/marcas_modelos/buscar_modelo.php?id=${id}`)
    .then(res => res.json())
    .then(data => {
      if (data.status !== 'sucesso') {
        Swal.fire("Erro", data.mensagem || "Erro ao carregar dados do modelo.", "error");
        return;
      }

      const modelo = data.modelo;
      form.querySelector('#editarModeloId').value = modelo.id;
      form.querySelector('#editarNomeModelo').value = modelo.modelo;
      form.querySelector('#editarMarcaModelo').value = modelo.id_marca;
    })
    .catch(err => Swal.fire("Erro de rede", err.message, "error"));
};

// ===================== SALVAR ALTERAÇÕES DO MODELO =====================
const formEditarModelo = document.getElementById('formEditarModelo');
if (formEditarModelo) {
  formEditarModelo.addEventListener('submit', function(e) {
    e.preventDefault();

    const formData = new FormData(formEditarModelo);

    fetch('includes/marcas_modelos/editar_modelo.php', {
      method: 'POST',
      body: formData
    })
    .then(res => res.json())
    .then(data => {
      if (data.status === 'sucesso') {
        Swal.fire({
          icon: 'success',
          title: 'Sucesso!',
          text: data.mensagem || 'Modelo atualizado com sucesso!'
        }).then(() => {
          carregarPagina('includes/marcas_modelos/listagem.php');
          const modalEl = document.getElementById('modalEditarModelo');
          const modal = bootstrap.Modal.getInstance(modalEl);
          modal.hide();
        });
      } else {
        Swal.fire({
          icon: 'error',
          title: 'Erro!',
          text: data.mensagem || 'Erro ao editar o modelo.'
        });
      }
    })
    .catch(err => Swal.fire("Erro de rede", err.message, "error"));
  });
}
// ===================== DELETAR MODELO =====================
window.deletarModelo = function(botao) {
  const id = botao.getAttribute('data-id');

  Swal.fire({
    title: 'Excluir Modelo?',
    text: "Essa ação não poderá ser desfeita. Deseja realmente excluir este modelo?",
    icon: 'warning',
    showCancelButton: true,
    confirmButtonText: 'Sim, excluir',
    cancelButtonText: 'Cancelar'
  }).then((result) => {
    if (result.isConfirmed) {
      const formData = new FormData();
      formData.append('id', id);

      fetch('includes/marcas_modelos/deletar_modelo.php', {
        method: 'POST',
        body: formData
      })
      .then(res => res.json())
      .then(data => {
        if (data.success) {
          Swal.fire({
            icon: 'success',
            title: 'Excluído!',
            text: data.message || 'Modelo excluído com sucesso.',
            timer: 1500,
            showConfirmButton: false
          }).then(() => {
            carregarPagina('includes/marcas_modelos/listagem.php');
          });
        } else {
          Swal.fire({
            icon: 'error',
            title: 'Erro!',
            text: data.message || 'Erro ao excluir o modelo.'
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

// Delega o evento para todos os botões de deletar modelo
document.querySelectorAll('.btn-deletar-modelo').forEach(botao => {
  botao.addEventListener('click', function() {
    deletarModelo(this);
  });
});



};

