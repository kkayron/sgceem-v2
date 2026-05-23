// JavaScript Document
window.inicializarFinFornecedores = function () {
    // Importar Fornecedores
const formImportarFornecedores = document.getElementById('formImportarFornecedores');

if (formImportarFornecedores) {
  formImportarFornecedores.addEventListener('submit', function(e) {
    e.preventDefault();

    const formData = new FormData(formImportarFornecedores);

    fetch('includes/fin_fornecedores/importar_fornecedores.php', {
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
          carregarPagina('includes/fin_fornecedores/listagem.php');
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

    
    
    // TOGGLEFILTRO
  window.toggleFiltrosFORN = function() {
    const container = document.getElementById('filtros-container-forn');
    if (container) {
      container.style.display = container.style.display === 'none' ? 'block' : 'none';
    }
  };
    
    // FILTRO DOS FORNECEDORES
    const formFORN = document.getElementById('filtroFornForm');

function atualizarListaFORN(extraParams = {}) {
  if (!formFORN) return;

  const formData = new FormData(formFORN);
  const params = new URLSearchParams(formData);

  for (const key in extraParams) {
    params.set(key, extraParams[key]);
  }

  const url = `includes/fin_fornecedores/listagem.php?${params.toString()}`;
  carregarPagina(url); // função AJAX específica para OS
}

// Submissão do formulário
if (formFORN) {
  formFORN.addEventListener('submit', function (e) {
    e.preventDefault();
    atualizarListaFORN({ pagina: 1 });
  });
}

// Mudança de limite por página
const limiteSelectFORN = document.getElementById('limiteFORN');
if (limiteSelectFORN) {
  limiteSelectFORN.addEventListener('change', function () {
    atualizarListaFORN({ pagina: 1, limite: this.value });
  });
}

// Botão "Limpar Filtros"
const btnLimparFiltrosForn = document.getElementById('btnLimparFiltrosForn');
if (btnLimparFiltrosForn && formFORN) {
  btnLimparFiltrosForn.addEventListener('click', function (e) {
    e.preventDefault();
    formFORN.reset();

    const limiteAtual = document.getElementById('limiteOS')?.value || 10;
    const url = `includes/fin_fornecedores/listagem.php?pagina=1&limite=${limiteAtual}`;
    carregarPagina(url); // AJAX sem filtros
  });
}
    
   // CADASTRAR FORNECEDOR
const formCadForn = document.getElementById('form-fornecedor-cadastrar');

if (formCadForn) {
  formCadForn.addEventListener('submit', function (e) {
    e.preventDefault();
    const formData = new FormData(formCadForn);

    fetch('includes/fin_fornecedores/php_cad_fornecedor.php', {
      method: 'POST',
      body: formData
    })
    .then(res => res.text())                   // <<< RECEBE TEXTO BRUTO
    .then(text => {

      // --- DEBUG ---------------------------------------------------------
      // Caso a resposta não seja JSON, vamos mostrar o texto bruto no console
      console.log("📌 Resposta bruta do servidor (debug):", text);
      // -------------------------------------------------------------------

      let data;
      try {
        data = JSON.parse(text);               // <<< TENTA CONVERTER
      } catch (e) {
        // <<< SE A CONVERSÃO FALHAR, EXIBE ERRO CLARO
        swal({
          title: "Erro!",
          text: "Resposta inválida do servidor. Veja o console (F12 → Console).",
          icon: "error",
          button: {
            text: "Fechar",
            className: "btn btn-danger"
          }
        });
        return; // impede continuação
      }

      // ========================================
      // PROCESSA JSON NORMALMENTE SE DEU CERTO
      // ========================================
      if (data.status === 'sucesso') {
        swal({
          title: "Sucesso!",
          text: data.mensagem || "Fornecedor cadastrado com sucesso!",
          icon: "success",
          button: {
            text: "OK",
            className: "btn btn-success"
          }
        }).then(() => {
          carregarPagina('includes/fin_fornecedores/listagem.php');
          fecharModalAberto();
        });

      } else {
        swal({
          title: "Erro!",
          text: data.mensagem || "Erro ao cadastrar fornecedor.",
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

  

// ===================== EDITAR FORNECEDOR =====================
window.editarFORN = function (id) {

  // Atualiza o título do modal
  const modalTitle = document.querySelector('#modalEditarFORN .modal-title');
  if (modalTitle) modalTitle.textContent = `Editar Fornecedor #${id}`;

  const form = document.getElementById('form-editar-forn');
  if (!form) return;

  // Zera campos enquanto carrega
  form.querySelectorAll('input, textarea, select').forEach(el => {
    if (el.type !== 'hidden') el.value = '';
  });

  // ===================== BUSCAR DADOS =====================
  fetch(`includes/fin_fornecedores/buscar_forn.php?id=${id}`)
    .then(res => res.json())
    .then(dados => {

      if (!dados || !dados.sucesso) {
        alert(dados?.mensagem || "Erro ao buscar dados do fornecedor.");
        return;
      }

      const f = dados.forn;

      // ===================== PREENCHER CAMPOS =====================

      // Hidden ID
      const inputId = form.querySelector('#edit-id');
      if (inputId) inputId.value = f.id;

      // Batalhão
      const inpBatalhao = form.querySelector('#nome_batalhao_edit');
      if (inpBatalhao) inpBatalhao.value = f.abreviatura_batalhao ?? f.nome_batalhao ?? "";

      // Categoria
      const selCategoria = form.querySelector('#categoria_empresa');
      if (selCategoria) selCategoria.value = f.categoria_empresa;

      // Nome da empresa
      const inpNome = form.querySelector('#nome_empresa');
      if (inpNome) inpNome.value = f.nome_empresa;

      // CNPJ
      const inpCnpj = form.querySelector('#cnpj_empresa');
      if (inpCnpj) inpCnpj.value = f.cnpj_empresa;

      // Nome do contato
      const inpContatoNome = form.querySelector('#contato_nome');
      if (inpContatoNome) inpContatoNome.value = f.contato_nome;

      // Número do contato
      const inpContatoNum = form.querySelector('#contato_numero');
      if (inpContatoNum) inpContatoNum.value = f.contato_numero;

      // Email
      const inpEmail = form.querySelector('#contato_email');
      if (inpEmail) inpEmail.value = f.contato_email;

      // O modal abre automaticamente pelo botão (data-bs-toggle)

    })
    .catch(err => {
      console.error("Erro ao carregar fornecedor:", err);
      alert("Erro ao carregar dados do fornecedor.");
    });
};


// ENVIAR OS DADOS PARA O BANCO DE DADOS
const form = document.getElementById('form-editar-forn');

form.addEventListener('submit', function (e) {
    e.preventDefault();
    const formData = new FormData(form);

    const id = form.querySelector('#edit-id').value;
    formData.append('id_forn', id);

    fetch('includes/fin_fornecedores/editar_forn.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(retorno => {
        if (retorno.status === 'sucesso') {
            swal('Sucesso', retorno.mensagem, 'success')
              .then(() => {
                carregarPagina('includes/fin_fornecedores/listagem.php');
                fecharModalAberto();
              });
        } else {
            swal('Erro', retorno.mensagem || 'Falha ao editar fornecedor.', 'error');
        }
    })
    .catch(err => swal('Erro de rede', err.message, 'error'));
});


//DELETAR FORNECEDOR

  window.deletarFORN = function(botao) {
  const id = botao.getAttribute('data-id');
  const token = botao.getAttribute('data-token');  

  Swal.fire({
    title: 'Deletar fornecedor?',
    text: "Essa ação não poderá ser desfeita. Deseja realmente excluir este FORNECEDOR e todos os registros relacionados?",
    icon: 'warning',
    showCancelButton: true,
    confirmButtonText: 'Sim, deletar',
    cancelButtonText: 'Cancelar'
  }).then((result) => {
    if (result.isConfirmed) {

      const formData = new FormData();
      formData.append('id', id);
      formData.append('csrf_token', token); 

      fetch('includes/fin_fornecedores/deletar_forn.php', {
        method: 'POST',
        headers: {
          'X-Requested-With': 'XMLHttpRequest' 
        },
        body: formData
      })
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          Swal.fire({
            icon: 'success',
            title: 'Deletado!',
            text: 'Fornecedor excluído com sucesso.',
            timer: 1500,
            showConfirmButton: false
          }).then(() => {
            carregarPagina('includes/fin_fornecedores/listagem.php');
          });
        } else {
          Swal.fire({
            icon: 'error',
            title: 'Erro',
            text: data.message || 'Erro ao deletar fornecedor.'
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
  document.querySelectorAll('.btn-deletar-forn').forEach(botao => {
    botao.addEventListener('click', function() {
      deletarFORN(this);
    });
  });



    
};