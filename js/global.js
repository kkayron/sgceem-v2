// JavaScript Document

// SALVAR PERFIL

document.addEventListener("submit", function(e){

if(e.target.id === "form-editar-perfil"){

e.preventDefault()

const formData = new FormData(e.target)

fetch("includes/perfil/salvar_perfil.php",{
method:"POST",
body:formData
})

.then(res=>res.text())

.then(ret=>{

if(ret.trim() === "ok"){

Swal.fire({
icon:"success",
title:"Perfil atualizado com sucesso",
confirmButtonColor:"#28a745"
}).then(() => {
location.reload(); // 🔄 atualiza a página
})

}else{

Swal.fire({
icon:"error",
title:"Erro ao salvar perfil"
})

}

})

}

})

// Deletar Frota - Página Listagem da Frota
function deletarFrota(botao) {
  const id = botao.getAttribute('data-id');

  Swal.fire({
    title: 'Deletar frota?',
    text: "Essa ação não poderá ser desfeita. Deseja realmente excluir este item?",
    icon: 'warning',
    showCancelButton: true,
    confirmButtonText: 'Sim, deletar',
    cancelButtonText: 'Cancelar'
  }).then((result) => {
    if (!result.isConfirmed) return;

    const formData = new FormData();
    formData.append('id', id);

    fetch('includes/frota/deletar_frota.php', {
      method: 'POST',
      body: formData
    })
    .then(async (response) => {
      const text = await response.text();

      let data;
      try {
        data = JSON.parse(text);
      } catch (e) {
        console.error('Resposta NÃO-JSON do servidor:', text);
        throw new Error('Servidor retornou resposta inválida (não JSON). Veja o console.');
      }

      if (!response.ok && !data.success) {
        const msg = data.message || 'Erro no servidor.';
        const dbg = data.debug ? `\n\n${data.debug}` : '';
        throw new Error(msg + dbg);
      }

      return data;
    })
    .then((data) => {
      if (data.success) {
        Swal.fire({
          icon: 'success',
          title: 'Deletado!',
          text: 'Frota excluída com sucesso.',
          timer: 1500,
          showConfirmButton: false
        }).then(() => {
          carregarPagina('includes/frota/listagem.php');
        });
      } else {
        Swal.fire({
          icon: 'error',
          title: 'Erro',
          text: data.message || 'Erro ao deletar frota.',
          footer: data.debug ? `<small style="color:#666">${String(data.debug).replace(/</g,'&lt;')}</small>` : ''
        });
      }
    })
    .catch((error) => {
      Swal.fire({
        icon: 'error',
        title: 'Falha ao deletar',
        text: error.message
      });
    });
  });
}



function filtrarUsuarios() {
  const funcao = document.getElementById('filtroFuncao').value;
  window.location.href = '?funcao=' + encodeURIComponent(funcao);
}


window.inicializarPaginaPrincipal = function () {
 // ===================== FILTROS DO DASHBOARD =====================
// ===================== FORMULÁRIOS =====================
const forms = [
  {
    form: document.getElementById('filtroDashboardForm'), // topo
    selects: {
      batalhao: document.getElementById('batalhao'),
      ano: document.getElementById('ano'),
      categoria: document.getElementById('categoria'),
      resto: document.getElementById('resto')
    }
  },
  {
    form: document.getElementById('filtroEmpenhosForm'), // dentro do card de empenhos
    selects: {
      ano: document.getElementById('ano_empenho'),
      categoria: document.getElementById('categoria_empenho'),
      resto: document.getElementById('resto_empenho')
    }
  }
];

// ===================== FUNÇÃO PRINCIPAL =====================
function atualizarDashboard(formObj, extraParams = {}) {
  if (!formObj.form) return;

  const formData = new FormData(formObj.form);
  const params = new URLSearchParams();

  // Adiciona todos os valores do formulário
  formData.forEach((value, key) => {
    if (value !== '') params.set(key, value);
  });

  // Substitui/insere parâmetros extras se houver
  for (const key in extraParams) {
    params.set(key, extraParams[key]);
  }

  // Atualiza conteúdo via AJAX
  const url = `partes/conteudo.php?${params.toString()}`;
  carregarPagina(url); // sua função existente
}

// ===================== REGISTRO DE EVENTOS =====================
function registrarFiltros(formObj) {
  if (!formObj.form || !formObj.selects) return;

  for (const [nomeFiltro, elemento] of Object.entries(formObj.selects)) {
    if (!elemento) continue;

    const delay = nomeFiltro === 'categoria' ? 700 : 0; // categoria pode ter atraso
    const evento = delay > 0 ? 'input' : 'change';

    let timer;
    elemento.addEventListener(evento, () => {
      clearTimeout(timer);
      timer = setTimeout(() => {
        atualizarDashboard(formObj, { [nomeFiltro]: elemento.value });
      }, delay);
    });
  }
}

// ===================== INICIALIZAÇÃO =====================
forms.forEach(f => registrarFiltros(f));


};

// ===================== USUÁRIOS ONLINE =====================

// 🔄 Carregar usuários online
function carregarUsuariosOnline(){

fetch("includes/api/usuarios_online.php", {
    headers: {
        "X-Requested-With": "XMLHttpRequest"
    }
})
.then(response => response.json())
.then(data => {

let html = "<b>Usuários online:</b><br>";

if(!data || data.length === 0){
    html += "Nenhum usuário online";
}else{
    data.forEach(function(usuario){
        html += "🟢 " + usuario + "<br>";
    });
}

document.getElementById("usuarios-online").innerHTML = html;

})
.catch(() => {
    document.getElementById("usuarios-online").innerHTML = "Erro ao carregar";
});

}

// 🔄 Atualizar atividade (heartbeat)
function atualizarAtividade(){

fetch("includes/api/atualiza_online.php", {
    method: "POST",
    headers: {
        "X-Requested-With": "XMLHttpRequest"
    }
}).catch(() => {});

}

// 🚀 Inicialização
carregarUsuariosOnline();
atualizarAtividade();

// ⏱️ Intervalos
setInterval(carregarUsuariosOnline, 10000); // lista
setInterval(atualizarAtividade, 5000); // heartbeat

// 🔴 Offline ao sair
window.addEventListener("beforeunload", function () {

    navigator.sendBeacon(
        "includes/api/usuario_offline.php",
        new Blob([], { type: 'application/x-www-form-urlencoded' })
    );

});


