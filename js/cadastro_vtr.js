// JavaScript Document
function inicializarCadastroVtrEqp() {// FORM: Cadastrar frota
const formCadastrarFrota = document.getElementById('formCadastrarFrota');

function alertaSweet(tipo, titulo, texto) {
  if (typeof Swal !== 'undefined') {
    return Swal.fire({
      icon: tipo,
      title: titulo,
      text: texto,
      confirmButtonText: 'OK'
    });
  }

  if (typeof swal !== 'undefined') {
    return swal({
      title: titulo,
      text: texto,
      icon: tipo,
      button: 'OK'
    });
  }

  alert(titulo + '\n' + texto);
  return Promise.resolve();
}

if (formCadastrarFrota) {
  formCadastrarFrota.addEventListener('submit', function (e) {
    e.preventDefault();

    const formData = new FormData(formCadastrarFrota);

    fetch('includes/frota/processar_cadastro_frota.php', {
      method: 'POST',
      body: formData,
      headers: {
        'X-Requested-With': 'XMLHttpRequest'
      }
    })
    .then(res => res.text())
    .then(text => {
      try {
        return JSON.parse(text);
      } catch (e) {
        console.error('Retorno inválido do PHP:', text);
        throw new Error('O PHP não retornou JSON válido.');
      }
    })
    .then(data => {
      const status = data.status || data.success;
      const mensagem = data.mensagem || data.message || '';

      if (status === 'ok' || status === true || status === 'sucesso') {
        alertaSweet(
          'success',
          'Sucesso!',
          mensagem || 'Frota cadastrada com sucesso!'
        ).then(() => {
          fecharModalAberto();
          carregarPagina('includes/frota/listagem.php');
        });
      } else {
        alertaSweet(
          'error',
          'Erro!',
          mensagem || 'Erro ao cadastrar frota.'
        );
      }
    })
    .catch(err => {
      console.error(err);

      alertaSweet(
        'error',
        'Erro!',
        'Erro ao processar cadastro: ' + err.message
      );
    });
  });
}
    
    // Importar frota
   const formImportarFrota = document.getElementById('formImportarFrota');
if (formImportarFrota) {
  formImportarFrota.addEventListener('submit', function(e) {
    e.preventDefault();

    const formData = new FormData(formImportarFrota);

    // DEBUG: verificar se batalhao está chegando
    console.log('Batalhao enviado:', formData.get('batalhao'));

    fetch('includes/frota/importar_frota.php', {
      method: 'POST',
      body: formData
    })
    .then(res => res.json())
    .then(data => {
      if (data.status === 'ok') {
        swal({
          title: "Importação concluída!",
          text: data.mensagem,
          icon: "success",
          button: { text: "OK", className: "btn btn-success" }
        }).then(() => {
          carregarPagina('includes/frota/listagem.php');
          fecharModalAberto();
        });
      } else {
        swal({
          title: "Erro na importação!",
          text: data.mensagem || "Verifique o arquivo e tente novamente.",
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


} // Final dos eventos dinâmicos

// Preview da imagem da capa
function previewImagemCapa(event) {
  const imagem = document.getElementById('previewFotoCapa');
  const arquivo = event.target.files[0];
  if (arquivo) {
    imagem.src = URL.createObjectURL(arquivo);
  }
}
