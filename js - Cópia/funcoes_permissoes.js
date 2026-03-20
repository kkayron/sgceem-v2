// ===================== LISTAGEM DE FUNÇÕES =====================
window.inicializarFuncoesUsuarios = function() {
    
    // Delegação dinâmica (funciona sempre)
  document.addEventListener("change", async function (e) {

    const chk = e.target;

    if (!chk.classList.contains("chk-permissao-pagina")) return;

    const funcaoId = chk.dataset.funcao;
    const paginaId = chk.dataset.pagina;
    const campo = chk.dataset.campo;
    const permitido = chk.checked ? 1 : 0;

    chk.disabled = true;

    try {
      const res = await fetch("includes/funcoes_militares/atualizar_permissao.php", {
        method: "POST",
        body: new URLSearchParams({
          funcao_id: funcaoId,
          pagina_id: paginaId,
          campo: campo,
          permitido: permitido
        })
      });

      const json = await res.json();

      if (!json.sucesso) {
        Swal.fire("Erro!", json.mensagem, "error");
        chk.checked = !chk.checked;
      } else {
        Swal.fire({
          icon: "success",
          title: "Atualizado!",
          text: json.mensagem,
          timer: 1200,
          showConfirmButton: false
        });
      }

    } catch (err) {
      Swal.fire("Erro!", "Falha na comunicação.", "error");
      chk.checked = !chk.checked;
    }

    chk.disabled = false;
  });

  // Animação das setas
  document.querySelectorAll("[data-bs-toggle='collapse']").forEach(btn => {
    btn.addEventListener("click", function () {
      const icon = this.querySelector("i");
      if (!icon) return;

      icon.classList.toggle("fa-chevron-down");
      icon.classList.toggle("fa-chevron-up");
    });
  });


  // ========== COLAPSAR SETAS ==========
  document.querySelectorAll("[data-bs-toggle='collapse']").forEach(btn => {
    btn.addEventListener("click", function () {
      const icon = this.querySelector("i");

      if (!icon) return;

      if (icon.classList.contains("fa-chevron-down")) {
        icon.classList.remove("fa-chevron-down");
        icon.classList.add("fa-chevron-up");
      } else {
        icon.classList.remove("fa-chevron-up");
        icon.classList.add("fa-chevron-down");
      }
    });
  });

  // Toggle filtros
  window.toggleFiltrosFuncoes = function() {
    const container = document.getElementById('filtros-container-funcoes');
    if (container) {
      container.style.display = container.style.display === 'none' ? 'block' : 'none';
    }
  };

  // ===================== DELETAR FUNÇÃO =====================
  window.deletarFuncao = function(botao) {
    const id = botao.getAttribute('data-id');

    Swal.fire({
      title: 'Deletar Função?',
      text: "Essa ação não poderá ser desfeita. Deseja realmente excluir esta função?",
      icon: 'warning',
      showCancelButton: true,
      confirmButtonText: 'Sim, deletar',
      cancelButtonText: 'Cancelar'
    }).then((result) => {
      if (result.isConfirmed) {
        const formData = new FormData();
        formData.append('id', id);

        fetch('includes/funcoes_militares/deletar_funcao.php', {
          method: 'POST',
          body: formData
        })
        .then(res => res.json())
        .then(data => {
          if (data.success) {
            Swal.fire({
              icon: 'success',
              title: 'Deletado!',
              text: 'Função excluída com sucesso.',
              timer: 1500,
              showConfirmButton: false
            }).then(() => {
              carregarPagina('includes/funcoes_militares/listagem.php');
            });
          } else {
            Swal.fire({
              icon: 'error',
              title: 'Erro',
              text: data.message || 'Erro ao deletar função.'
            });
          }
        })
        .catch(err => {
          Swal.fire({ icon: 'error', title: 'Erro de rede', text: err.message });
        });
      }
    });
  };

  // Delega evento para todos os botões de deletar
  document.querySelectorAll('.btn-deletar-funcao').forEach(botao => {
    botao.addEventListener('click', function() {
      deletarFuncao(this);
    });
  });

  // ===================== CADASTRAR FUNÇÃO =====================
  const formCadastroFuncao = document.getElementById('formCadastroFuncao');
  if (formCadastroFuncao) {
    formCadastroFuncao.addEventListener('submit', function(e) {
      e.preventDefault();
      const formData = new FormData(formCadastroFuncao);

      fetch('includes/funcoes_militares/cadastrar_funcao.php', {
        method: 'POST',
        body: formData
      })
      .then(res => res.json())
      .then(data => {
        if (data.status === 'sucesso') {
          Swal.fire("Sucesso!", data.mensagem || "Função cadastrada com sucesso!", "success")
            .then(() => {
              carregarPagina('includes/funcoes_militares/listagem.php');
              fecharModalAberto();
            });
        } else {
          Swal.fire("Erro!", data.mensagem || "Erro ao cadastrar função.", "error");
        }
      })
      .catch(err => Swal.fire("Erro de rede", err.message, "error"));
    });
  }

  // ===================== EDITAR FUNÇÃO =====================
  window.editarFuncao = function(id) {
    const form = document.getElementById('formEditarFuncao');
    if (!form) return;

    fetch(`includes/funcoes_militares/buscar_funcao.php?id=${id}`)
      .then(res => res.json())
      .then(data => {
        if (data.status !== 'sucesso') {
          Swal.fire("Erro", data.mensagem || "Erro ao carregar função.", "error");
          return;
        }

        const f = data.funcao;
        form.querySelector('#editarFuncaoId').value = f.id;
        form.querySelector('#editarNomeFuncao').value = f.nome;
        form.querySelector('#editarDescricaoFuncao').value = f.descricao || '';
      })
      .catch(err => Swal.fire("Erro de rede", err.message, "error"));
  };

  // Submissão do form de edição
  const formEditarFuncao = document.getElementById('formEditarFuncao');
  if (formEditarFuncao) {
    formEditarFuncao.addEventListener('submit', function(e) {
      e.preventDefault();
      const formData = new FormData(formEditarFuncao);

      fetch('includes/funcoes_militares/editar_funcao.php', {
        method: 'POST',
        body: formData
      })
      .then(res => res.json())
      .then(data => {
        if (data.status === 'sucesso') {
          Swal.fire("Sucesso!", data.mensagem || "Função editada com sucesso!", "success")
            .then(() => {
              carregarPagina('includes/funcoes_militares/listagem.php');
              fecharModalAberto();
            });
        } else {
          Swal.fire("Erro!", data.mensagem || "Erro ao editar função.", "error");
        }
      })
      .catch(err => Swal.fire("Erro de rede", err.message, "error"));
    });
  }

  // ===================== FILTROS =====================
  const formFiltrosFuncoes = document.getElementById('filtroFuncoesForm');

  function atualizarListaFuncoes(extraParams = {}) {
    if (!formFiltrosFuncoes) return;
    const formData = new FormData(formFiltrosFuncoes);
    const params = new URLSearchParams(formData);

    for (const key in extraParams) {
      params.set(key, extraParams[key]);
    }

    const url = `includes/funcoes_militares/listagem.php?${params.toString()}`;
    carregarPagina(url);
  }

  if (formFiltrosFuncoes) {
    formFiltrosFuncoes.addEventListener('submit', function(e) {
      e.preventDefault();
      atualizarListaFuncoes({ pagina: 1 });
    });
  }

  const limiteSelectFuncoes = document.getElementById('limiteFuncoes');
  if (limiteSelectFuncoes) {
    limiteSelectFuncoes.addEventListener('change', function() {
      atualizarListaFuncoes({ pagina: 1, limite: this.value });
    });
  }

  const btnLimparFiltrosFuncoes = document.getElementById('btnLimparFiltrosFuncoes');
  if (btnLimparFiltrosFuncoes && formFiltrosFuncoes) {
    btnLimparFiltrosFuncoes.addEventListener('click', function(e) {
      e.preventDefault();
      formFiltrosFuncoes.reset();

      const limiteAtual = document.getElementById('limiteFuncoes')?.value || 10;
      const url = `includes/funcoes_militares/listagem.php?pagina=1&limite=${limiteAtual}`;
      carregarPagina(url);
    });
  }
};

// ===================== LISTAGEM DE PERMISSÕES E PÁGINAS =====================
window.inicializarPaginasPermissoes = function() {
// Se não existir carregarPagina, cria fallback
  if (typeof window.carregarPagina !== "function") {
    window.carregarPagina = function(url) {
      window.location.href = url;
    };
  }

  // --- Helpers ---
  function tratarRespostaJson(res) {
    return res.text().then(text => {
      try {
        return JSON.parse(text);
      } catch (err) {
        throw new Error("Resposta inválida: " + text);
      }
    });
  }

  function mostrarErro(titulo = "Erro", texto = "Ocorreu um erro.") {
    Swal.fire({ icon: "error", title: titulo, text: texto });
  }

  // --- Toggle filtros ---
  window.toggleFiltrosPaginas = function() {
    const box = document.getElementById("filtros-container-paginas");
    if (!box) return;
    box.style.display = box.style.display === "none" ? "block" : "none";
  };

  // --- Atualizar listagem ---
  function atualizarListaPaginas(extraParams = {}) {
    const form = document.getElementById("filtroPaginasForm");
    const params = new URLSearchParams();

    if (form) {
      const fd = new FormData(form);
      for (const [k, v] of fd.entries()) params.append(k, v);
    }

    for (const k in extraParams) {
      params.set(k, extraParams[k]);
    }

    const url = `includes/paginas/listagem.php?${params.toString()}`;
    window.carregarPagina(url);
  }

  // ============================================================
  // 🔥 DELEGAÇÃO DE EVENTOS (Funciona mesmo após recarregar AJAX)
  // ============================================================

  document.addEventListener("click", function(e) {
    // Botão LIMPAR
    if (e.target.closest("#btnLimparFiltrosPaginas")) {
      e.preventDefault();

      const form = document.getElementById("filtroPaginasForm");
      if (form) form.reset();

      const limite = document.getElementById("limitePaginas")?.value || 10;
      window.carregarPagina(`includes/paginas/listagem.php?pagina=1&limite=${limite}`);
    }

    // Paginação
    if (e.target.closest(".paginacao-paginas")) {
      e.preventDefault();
      let url = e.target.getAttribute("data-page");
      if (url) window.carregarPagina(url);
    }
  });

  // Aplicar filtros (submit)
  document.addEventListener("submit", function(e) {
    if (e.target.id === "filtroPaginasForm") {
      e.preventDefault();
      atualizarListaPaginas({ pagina: 1 });
    }
  });

  // Select de limite
  document.addEventListener("change", function(e) {
    if (e.target.id === "limitePaginas") {
      atualizarListaPaginas({ pagina: 1, limite: e.target.value });
    }
  });

// --- CADASTRAR PÁGINA PRINCIPAL ---
(function ligarCadastroPaginaPrincipal() {
  const form = document.getElementById('formCadastroPaginaPrincipal');
  if (!form) return;

  form.addEventListener('submit', function(e) {
    e.preventDefault();
    const fd = new FormData(form);

    fetch('includes/paginas/cadastrar_principal.php', {
      method: 'POST',
      body: fd
    })
    .then(res => res.json())
    .then(data => {
      if (data.status === 'sucesso') {
        Swal.fire({
          icon: 'success',
          title: 'Tópico cadastrado',
          text: data.mensagem || '',
          timer: 1400,
          showConfirmButton: false
        }).then(() => {
          fecharModalAberto();
          carregarPagina('includes/paginas/listagem.php');
        });
      } else {
        mostrarErro('Erro ao cadastrar', data.mensagem || 'Erro desconhecido.');
      }
    })
    .catch(err => mostrarErro('Erro de rede', err.message));
  });
})();

// --- BUSCAR TÓPICO PRINCIPAL (preenche modal editar) ---
window.editarPaginaPrincipal = function(id) {
  const form = document.getElementById('formEditarPaginaPrincipal');
  if (!form) return;

  fetch(`includes/paginas/buscar_principal.php?id=${encodeURIComponent(id)}`)
    .then(res => res.json())
    .then(data => {
      if (data.status !== 'sucesso') {
        mostrarErro('Erro', data.mensagem || 'Não foi possível carregar o tópico.');
        return;
      }
      const p = data.topico;
      form.querySelector('#editarPaginaPrincipalId').value = p.id;
      form.querySelector('#editarNomePaginaPrincipal').value = p.nome || '';
      form.querySelector('#editarIconePaginaPrincipal').value = p.icone || '';
      form.querySelector('#editarOrdemPaginaPrincipal').value = p.ordem || '';

      // abre modal
      const modalEl = document.getElementById('modalEditarPaginaPrincipal');
      if (modalEl && !modalEl.classList.contains('show')) {
        const bs = new bootstrap.Modal(modalEl);
        bs.show();
      }
    })
    .catch(err => mostrarErro('Erro de rede', err.message));
};

// --- EDITAR TÓPICO PRINCIPAL (submit) ---
(function ligarEditarPaginaPrincipal() {
  const form = document.getElementById('formEditarPaginaPrincipal');
  if (!form) return;

  form.addEventListener('submit', function(e) {
    e.preventDefault();
    const fd = new FormData(form);

    fetch('includes/paginas/editar_principal.php', {
      method: 'POST',
      body: fd
    })
    .then(res => res.json())
    .then(data => {
      if (data.status === 'sucesso') {
        Swal.fire({
          icon: 'success',
          title: 'Alterado',
          text: data.mensagem || '',
          timer: 1200,
          showConfirmButton: false
        }).then(() => {
          carregarPagina('includes/paginas/listagem.php');
        });
      } else {
        mostrarErro('Erro', data.mensagem || 'Erro ao editar tópico.');
      }
    })
    .catch(err => mostrarErro('Erro de rede', err.message));
  });
})();



// --- CADASTRAR PÁGINA ---
(function ligarCadastroPagina() {
  const form = document.getElementById('formCadastroPagina');
  if (!form) return;

  form.addEventListener('submit', function(e) {
    e.preventDefault();
    const fd = new FormData(form);

    fetch('includes/paginas/cadastrar_pagina.php', {
      method: 'POST',
      body: fd
    })
    .then(tratarRespostaJson)
    .then(data => {
      if (data.status === 'sucesso') {
        Swal.fire({ icon: 'success', title: 'Página cadastrada', text: data.mensagem || '', timer: 1400, showConfirmButton: false })
          .then(() => {
            fecharModalAberto();
            carregarPagina('includes/paginas/listagem.php');
          });
      } else {
        mostrarErro('Erro ao cadastrar', data.mensagem || 'Erro desconhecido.');
      }
    })
    .catch(err => mostrarErro('Erro de rede', err.message));
  });
})();

// --- BUSCAR PÁGINA (preenche modal editar) ---
window.editarPagina = function(id) {
  const form = document.getElementById('formEditarPagina');
  if (!form) return;

  fetch(`includes/paginas/buscar_pagina.php?id=${encodeURIComponent(id)}`)
    .then(tratarRespostaJson)
    .then(data => {
      if (data.status !== 'sucesso') {
        mostrarErro('Erro', data.mensagem || 'Não foi possível carregar a página.');
        return;
      }
      const p = data.pagina;
      form.querySelector('#editarPaginaId').value = p.id;
      form.querySelector('#editarNomePagina').value = p.nome || '';
      form.querySelector('#editarDescricaoPagina').value = p.descricao || '';
      form.querySelector('#editarTipoPagina').value = p.tipo || '';
      form.querySelector('#editarOrdemPagina').value = p.ordem || '';
      form.querySelector('#editarArquivo').value = p.arquivo || '';
      // abre modal (caso não tenha sido aberto via data-bs-target)
      const modalEl = document.getElementById('modalEditarPagina');
      if (modalEl && !modalEl.classList.contains('show')) {
        const bs = new bootstrap.Modal(modalEl);
        bs.show();
      }
    })
    .catch(err => mostrarErro('Erro de rede', err.message));
};

// --- EDITAR PÁGINA (submit) ---
(function ligarEditarPagina() {
  const form = document.getElementById('formEditarPagina');
  if (!form) return;

  form.addEventListener('submit', function(e) {
    e.preventDefault();
    const fd = new FormData(form);

    fetch('includes/paginas/editar_pagina.php', {
      method: 'POST',
      body: fd
    })
    .then(tratarRespostaJson)
    .then(data => {
      if (data.status === 'sucesso') {
        Swal.fire({ icon: 'success', title: 'Alterado', text: data.mensagem || '', timer: 1200, showConfirmButton: false })
          .then(() => {
            carregarPagina('includes/paginas/listagem.php');
          });
      } else {
        mostrarErro('Erro', data.mensagem || 'Erro ao editar página.');
      }
    })
    .catch(err => mostrarErro('Erro de rede', err.message));
  });
})();

// --- DELETAR PÁGINA ---
window.deletarPagina = function(id) {
  if (!id) return;
  Swal.fire({
    title: 'Excluir página?',
    text: 'Esta ação removerá a página e suas permissões. Continuar?',
    icon: 'warning',
    showCancelButton: true,
    confirmButtonText: 'Sim, excluir',
    cancelButtonText: 'Cancelar'
  }).then(result => {
    if (!result.isConfirmed) return;

    const fd = new FormData();
    fd.append('id', id);

    fetch('includes/paginas/deletar_pagina.php', {
      method: 'POST',
      body: fd
    })
    .then(tratarRespostaJson)
    .then(data => {
      if (data.status === 'sucesso') {
        Swal.fire({ icon: 'success', title: 'Excluído', text: data.mensagem || '', timer: 1200, showConfirmButton: false })
          .then(() => carregarPagina('includes/paginas/listagem.php'));
      } else {
        mostrarErro('Erro', data.mensagem || 'Não foi possível excluir.');
      }
    })
    .catch(err => mostrarErro('Erro de rede', err.message));
  });
};

// Delegação para botões de exclusão (se existirem no DOM no carregamento)
document.addEventListener('click', function(e) {
  const btn = e.target.closest('.btn-deletar-pagina');
  if (!btn) return;
  const id = btn.getAttribute('data-id');
  // Abre modal de confirmação caso tenha modalExcluirPagina - preencher input se desejar
  const excluirModal = document.getElementById('modalExcluirPagina');
  if (excluirModal) {
    const inputId = excluirModal.querySelector('#excluirPaginaId');
    if (inputId) inputId.value = id;
    // mostra modal
    const bs = new bootstrap.Modal(excluirModal);
    bs.show();
  } else {
    // caso não use modal, chama a função direta
    deletarPagina(id);
  }
});

// Submissão do modal de exclusão (se estiver usando #formExcluirPagina)
(function ligarFormExcluirPagina() {
  const form = document.getElementById('formExcluirPagina');
  if (!form) return;
  form.addEventListener('submit', function(e) {
    e.preventDefault();
    const id = form.querySelector('#excluirPaginaId')?.value;
    if (!id) return;
    deletarPagina(id);
  });
})();

// --- ATUALIZAR PERMISSÃO (ao marcar/desmarcar) ---
// Função chamada nos onchange de cada checkbox da listagem
window.atualizarPermissao = function(funcao_id, pagina_id, campo, valorBooleano) {
  // normaliza
  funcao_id = parseInt(funcao_id, 10);
  pagina_id = parseInt(pagina_id, 10);
  const valor = valorBooleano ? 1 : 0;

  // monta FormData
  const fd = new FormData();
  fd.append('funcao_id', funcao_id);
  fd.append('pagina_id', pagina_id);
  fd.append('campo', campo);
  fd.append('valor', valor);

  // Chamada AJAX
  fetch('includes/paginas/atualizar_permissao.php', {
    method: 'POST',
    body: fd
  })
  .then(tratarRespostaJson)
  .then(data => {
    if (data.status === 'sucesso') {
      // feedback sutil: mostra toast pequeno
      Swal.fire({
        toast: true,
        position: 'top-end',
        icon: 'success',
        title: data.mensagem || 'Permissão atualizada',
        showConfirmButton: false,
        timer: 1200
      });
    } else {
      // reverter visual do checkbox caso erro (procura o checkbox)
      const selector = `input[type="checkbox"][data-funcao="${funcao_id}"][data-pagina="${pagina_id}"][data-campo="${campo}"]`;
      const cb = document.querySelector(selector);
      if (cb) cb.checked = !cb.checked;
      mostrarErro('Erro', data.mensagem || 'Erro ao atualizar permissão.');
    }
  })
  .catch(err => {
    // reverter visual se erro de rede
    const selector = `input[type="checkbox"][data-funcao="${funcao_id}"][data-pagina="${pagina_id}"][data-campo="${campo}"]`;
    const cb = document.querySelector(selector);
    if (cb) cb.checked = !cb.checked;
    mostrarErro('Erro de rede', err.message);
  });
};

// Delegação global para checkboxes (caso você não queira inline onchange)
// Espera que os checkboxes tenham atributos: data-funcao, data-pagina, data-campo
document.addEventListener('change', function(e) {
  const el = e.target;
  if (!el.matches('input[type="checkbox"][data-funcao][data-pagina][data-campo]')) return;
  const funcaoId = el.getAttribute('data-funcao');
  const paginaId = el.getAttribute('data-pagina');
  const campo = el.getAttribute('data-campo');
  const valor = el.checked;
  // chama a função de atualização
  window.atualizarPermissao(funcaoId, paginaId, campo, valor);
});

// --- Inicialização: se desejar registrar handlers adicionais ao carregar a view ---
window.inicializarPaginasPermissoes = function() {
  // qualquer inicialização extra pode ficar aqui
  // ex: tooltips bootstrap
  const tt = document.querySelectorAll('[data-bs-toggle="tooltip"]');
  tt.forEach(el => new bootstrap.Tooltip(el));
};

// auto-inicializa ao carregar
document.addEventListener('DOMContentLoaded', function() {
  if (typeof window.inicializarPaginasPermissoes === 'function') window.inicializarPaginasPermissoes();
});


};