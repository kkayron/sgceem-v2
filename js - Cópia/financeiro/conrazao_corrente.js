// JavaScript Document
window.inicializarConrazaoCorrente = function () {
  const form = document.getElementById('form-conrazao-corrente');
  if (!form) return;

  form.addEventListener('submit', function (e) {
    e.preventDefault();
    const formData = new FormData(form);
    const output = document.getElementById('resultado-envio-conrazao');
    output.innerHTML = 'Enviando...';

    fetch('includes/fin_conrazao/importar_corrente.php', {
      method: 'POST',
      body: formData
    })
    .then(r => r.json())
    .then(data => {
      if (data.status !== 'sucesso') {
        output.innerHTML = `<div class="alert alert-danger">${data.mensagem || 'Erro no envio.'}</div>`;
        return;
      }

      const enviados = data.enviados || [];
      const falhas = data.falhas || [];

      let html = '';
      if (enviados.length > 0) {
        html += '<div class="alert alert-success">Dados enviados com sucesso:</div>';
        html += '<div class="table-responsive"><table class="table table-bordered table-sm">';
        html += '<thead><tr><th>Empenho</th><th>Saldo</th></tr></thead><tbody>';
        enviados.forEach(e => {
          html += `<tr><td>${e.nmr_empenho}</td><td>R$ ${e.saldo_empenho}</td></tr>`;
        });
        html += '</tbody></table></div>';
      }

      if (falhas.length > 0) {
        html += '<div class="alert alert-warning">Falhas ao enviar:</div>';
        html += '<ul class="list-group">';
        falhas.forEach(f => {
          html += `<li class="list-group-item">${f.nmr_empenho}: ${f.erro}</li>`;
        });
        html += '</ul>';
      }

      output.innerHTML = html;
    })
    .catch(err => {
      output.innerHTML = `<div class="alert alert-danger">Erro: ${err.message}</div>`;
    });
  });
}

window.inicializarConrazaoRP = function () {
  const form = document.getElementById('form-conrazao-rp');
  if (!form) return;

  form.addEventListener('submit', function (e) {
    e.preventDefault();
    const formData = new FormData(form);
    const output = document.getElementById('resultado-envio-conrazao-rp');
    output.innerHTML = 'Enviando...';

    fetch('includes/fin_conrazao/importar_rp.php', {
      method: 'POST',
      body: formData
    })
    .then(r => r.json())
    .then(data => {
      if (data.status !== 'sucesso') {
        output.innerHTML = `<div class="alert alert-danger">${data.mensagem || 'Erro no envio.'}</div>`;
        return;
      }

      const enviados = data.enviados || [];
      const falhas = data.falhas || [];

      let html = '';
      if (enviados.length > 0) {
        html += '<div class="alert alert-success">Dados enviados com sucesso:</div>';
        html += '<div class="table-responsive"><table class="table table-bordered table-sm">';
        html += '<thead><tr><th>Empenho</th><th>Saldo</th></tr></thead><tbody>';
        enviados.forEach(e => {
          html += `<tr><td>${e.nmr_empenho}</td><td>R$ ${e.saldo_empenho}</td></tr>`;
        });
        html += '</tbody></table></div>';
      }

      if (falhas.length > 0) {
        html += '<div class="alert alert-warning">Falhas ao enviar:</div>';
        html += '<ul class="list-group">';
        falhas.forEach(f => {
          html += `<li class="list-group-item">${f.nmr_empenho}: ${f.erro}</li>`;
        });
        html += '</ul>';
      }

      output.innerHTML = html;
    })
    .catch(err => {
      output.innerHTML = `<div class="alert alert-danger">Erro: ${err.message}</div>`;
    });
  });
}


