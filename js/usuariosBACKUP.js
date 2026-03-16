// JavaScript Document

window.inicializarUsuarios = function () {
  // FORM: Salvar novo usuário
  const formSalvar = document.getElementById('form-usuario');
  if (formSalvar) {
    formSalvar.addEventListener('submit', function (e) {
      e.preventDefault();
      const formData = new FormData(formSalvar);
      fetch('includes/usuarios/salvar_usuario.php', {
        method: 'POST',
        body: formData
      })
      .then(res => res.text())
      .then(result => {
        if (result.trim() === 'ok') {
          carregarPagina('includes/usuarios/listagem.php');
          fecharModalAberto();
        } else {
          alert('Erro ao salvar: ' + result);
        }
      })
      .catch(err => alert('Erro: ' + err.message));
    });
  }

  // FORM: Cadastrar vários usuários
  const formImportar = document.getElementById('formImportar');
  if (formImportar) {
    formImportar.addEventListener('submit', function (e) {
      e.preventDefault();
      const formData = new FormData(formImportar);
      fetch('includes/usuarios/importar_usuarios.php', {
        method: 'POST',
        body: formData
      })
      .then(res => res.json())
      .then(data => {
        if (data.status === 'ok') {
          let textoFalhas = '';
          if (Array.isArray(data.falhas) && data.falhas.length > 0) {
            textoFalhas = '\n\nFalhas:\n' + data.falhas.map(f => `• Linha ${f.linha}: ${f.erro}`).join('\n');
          }

          swal({
            title: "Importação concluída!",
            text: `Sucessos: ${data.sucessos}${textoFalhas}`,
            icon: "success",
            button: {
              text: "OK",
              className: "btn btn-success"
            }
          }).then(() => {
            carregarPagina('includes/usuarios/listagem.php');
            fecharModalAberto();
          });

        } else {
          swal({
            title: "Erro na importação!",
            text: "Verifique o arquivo e tente novamente.",
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

  // FORM: Editar usuário
  const formEditar = document.getElementById('form-editar-usuario');
  if (formEditar) {
    formEditar.addEventListener('submit', function (e) {
      e.preventDefault();
      const formData = new FormData(formEditar);
      fetch('includes/usuarios/editar_usuario.php', {
        method: 'POST',
        body: formData
      })
      .then(res => res.text())
      .then(retorno => {
        if (retorno.trim() === 'ok') {
          carregarPagina('includes/usuarios/listagem.php');
          fecharModalAberto();
        } else {
          alert('Erro ao editar: ' + retorno);
        }
      })
      .catch(err => alert('Erro ao enviar: ' + err.message));
    });
  }
    
   // Função global para preencher modal de edição dos Usuários
window.editarUsuario = function (id) {
  fetch('includes/usuarios/buscar_usuario.php?id=' + id)
    .then(res => res.json())
    .then(data => {
       console.log(data); // 👈 VER O QUE ESTÁ VINDO AQUI
      if (data.erro) return alert('Erro ao buscar usuário');

      document.getElementById('editar-id').value = data.id;
      document.getElementById('editar-postograd').value = data.postograd;
      document.getElementById('editar-nomeguerra').value = data.nomeguerra;
      document.getElementById('editar-usuario').value = data.usuario;
      document.getElementById('editar-funcao').value = data.funcao;
      document.getElementById('editar-status').value = data.status;
      document.getElementById('editar-foto-preview').src = 'assets/fotoperfil/' + data.foto;

      new bootstrap.Modal(document.getElementById('modalEditarUsuario')).show();
    });
    
}
 
  // FECHAR O MODAL QUANDO EDITAR A FROTA
function fecharModalAberto() {
  const modalElement = document.querySelector('.modal.show');
  if (modalElement) {
    const instance = bootstrap.Modal.getInstance(modalElement);
    if (instance) instance.hide();
  }

  // Aguarda o tempo de animação para garantir que o modal sumiu antes de limpar
  setTimeout(() => {
    // Remove backdrop manualmente, se ainda estiver presente
    const backdrop = document.querySelector('.modal-backdrop');
    if (backdrop) backdrop.remove();

    // Remove a classe 'modal-open' do body
    document.body.classList.remove('modal-open');

    // Remove qualquer padding que o Bootstrap possa ter adicionado ao body
    document.body.style.paddingRight = '';
  }, 300); // tempo semelhante ao tempo de animação padrão do Bootstrap (fade)
}


    // ALTERAR STATUS DO USUÁRIO
    
window.alterarStatusUsuario = function (botao) {
  const id = botao.getAttribute('data-id');
  const acao = botao.getAttribute('data-status'); // ativar ou desativar
  const novoStatus = acao === 'ativar' ? 'sim' : 'não';

  swal({
    title: `Tem certeza que deseja ${acao} este usuário?`,
    icon: "warning",
    buttons: {
      cancel: {
        text: "Cancelar",
        visible: true,
        className: "btn btn-danger",
      },
      confirm: {
        text: "Sim, confirmar",
        className: "btn btn-success",
      },
    },
    dangerMode: true,
  }).then((confirmado) => {
    if (confirmado) {
      fetch('includes/usuarios/alterar_status_usuario.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `id=${encodeURIComponent(id)}&status=${encodeURIComponent(novoStatus)}`
      })
      .then(res => res.text())
      .then(resposta => {
        if (resposta.trim() === 'ok') {
          swal({
            title: `Usuário ${acao === 'ativar' ? 'ativado' : 'desativado'} com sucesso!`,
            icon: "success",
            buttons: false,
            timer: 1500,
          });
          carregarPagina('includes/usuarios/listagem.php');
        } else {
          swal("Erro: " + resposta, { icon: "error" });
        }
      })
      .catch(err => swal("Erro: " + err.message, { icon: "error" }));
    }
  });
}
   
  // Delegação de eventos para botões de deletar
  document.querySelectorAll('.btn-deletar-usuario').forEach(botao => {
    botao.addEventListener('click', () => {
      const id = botao.getAttribute('data-id');

      Swal.fire({
        title: 'Deletar usuário?',
        text: "Essa ação não poderá ser desfeita. Deseja realmente excluir este item?",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sim, deletar',
        cancelButtonText: 'Cancelar'
      }).then((result) => {
        if (result.isConfirmed) {
          const formData = new FormData();
          formData.append('id', id);

          fetch('includes/usuarios/deletar_usuario.php', {
            method: 'POST',
            body: formData
          })
          .then(response => response.json())
          .then(data => {
            if (data.success) {
              Swal.fire({
                icon: 'success',
                title: 'Deletado!',
                text: 'Usuário excluído com sucesso.',
                timer: 1500,
                showConfirmButton: false
              }).then(() => {
                carregarPagina('includes/usuarios/listagem.php');
              });
            } else {
              Swal.fire({
                icon: 'error',
                title: 'Erro',
                text: data.message || 'Erro ao deletar usuário.'
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
  });

  // VER PERFIL DO USUÁRIO NA PÁGINA USUÁRIOS
    
    window.verPerfilUsuario = function (id) {
  const modal = new bootstrap.Modal(document.getElementById('modalPerfilUsuario'));
  const foto = document.getElementById('perfil-foto');
  const posto = document.getElementById('usuario-posto');
  const nomeGuerra = document.getElementById('usuario-nome-guerra');
  const funcao = document.getElementById('usuario-funcao');
  const tabela = document.querySelector('#tabela-logs tbody');
  const paginacao = document.querySelector('.paginacao-logs');
        
         // Atualiza os links dos botões de exportação
  document.getElementById('btnExportarExcel').href = 'includes/usuarios/exportar_excel.php?id=' + id;
  document.getElementById('btnExportarPDF').href = 'includes/usuarios/exportar_pdf.php?id=' + id;
        

  let paginaAtual = 1;

  function carregarPerfil() {
    fetch(`includes/usuarios/buscar_usuario.php?id=${id}`)
      .then(res => res.json())
      .then(data => {
        if (data.erro) return alert('Erro ao buscar usuário.');

        foto.src = `assets/fotoperfil/${data.foto}`;
        posto.textContent = data.postograd;
        nomeGuerra.textContent = data.nomeguerra;
        funcao.textContent = data.funcao;

        carregarLogs();
        modal.show();
      })
      .catch(() => alert('Erro ao carregar perfil.'));
  }

  function carregarLogs() {
    const data = document.getElementById('filtro-data').value;
    const acao = document.getElementById('filtro-acao').value;
    const palavra = document.getElementById('filtro-palavra').value;

    const params = new URLSearchParams({
      id,
      data,
      acao,
      palavra,
      pagina: paginaAtual
    });

    fetch(`includes/usuarios/logs_usuario.php?${params.toString()}`)
      .then(res => res.json())
      .then(data => {
        tabela.innerHTML = '';
        if (data.logs.length === 0) {
          tabela.innerHTML = '<tr><td colspan="5" class="text-center">Nenhum log encontrado</td></tr>';
        } else {
          data.logs.forEach(log => {
            const tr = document.createElement('tr');
            tr.innerHTML = `
              <td>${log.data}</td>
              <td>${log.acao}</td>
              <td>${log.descricao}</td>
              <td>${log.ip}</td>
              <td>${log.navegador}</td>
            `;
            tabela.appendChild(tr);
          });
        }

        paginacao.innerHTML = '';
        const totalPaginas = Math.ceil(data.total / data.porPagina);
        for (let i = 1; i <= totalPaginas; i++) {
          const btn = document.createElement('button');
          btn.className = `btn btn-sm ${i === data.paginaAtual ? 'btn-primary' : 'btn-outline-light'} me-1`;
          btn.textContent = i;
          btn.onclick = () => {
            paginaAtual = i;
            carregarLogs();
          };
          paginacao.appendChild(btn);
        }
      })
      .catch(() => alert('Erro ao carregar logs.'));
  }

  // Filtros
  document.getElementById('filtro-data').onchange =
  document.getElementById('filtro-acao').onchange =
  document.getElementById('filtro-palavra').oninput = () => {
    paginaAtual = 1;
    carregarLogs();
  };

  carregarPerfil();
};

   
    
    
    // FILTRO DA PÁGINA
     const filtroSelect = document.getElementById('filtroFuncao');
  const botaoFiltrar = document.querySelector('button[onclick="filtrarUsuarios()"]');

  function filtrarUsuarios() {
    const funcao = filtroSelect.value;
    const urlParams = new URLSearchParams(window.location.search);

    if (funcao === 'todos') {
      urlParams.delete('funcao');
    } else {
      urlParams.set('funcao', encodeURIComponent(funcao));
    }

    window.location.search = urlParams.toString();
  }

  if (botaoFiltrar) {
    botaoFiltrar.addEventListener('click', filtrarUsuarios);
  }
};
    
    
    
    
}



