// Função para carregar páginas dinamicamente
function carregarPagina(url, adicionarAoHistorico = true) {
  const container = document.getElementById('conteudo');
  if (!container) return;

  fetch(url)
    .then(response => {
      if (!response.ok) throw new Error('Erro na resposta');
      return response.text();
    })
    .then(html => {
      container.innerHTML = html;
      container.dataset.page = url;
      container.style.visibility = 'visible';

      // Executa scripts inline e com src contidos no HTML carregado
      const scripts = container.querySelectorAll('script');
      scripts.forEach(script => {
        const novoScript = document.createElement('script');

        if (script.src) {
          novoScript.src = script.src;
          novoScript.async = false;
          document.head.appendChild(novoScript); // Adiciona no head para manter ordem
        } else {
          novoScript.textContent = script.textContent;
          document.body.appendChild(novoScript);
        }

        script.remove(); // Remove o antigo script do container
      });

      // Salva no sessionStorage
      sessionStorage.setItem('paginaAtual', url);

      // Adiciona ao histórico do navegador
      if (adicionarAoHistorico) {
        history.pushState({ url: url }, '', '#' + url);
      }

      // Executa função de inicialização definida na página (se houver)
      setTimeout(() => {
        if (window.funcaoInicializacao && typeof window[window.funcaoInicializacao] === 'function') {
          const fn = window[window.funcaoInicializacao];
          if (typeof fn === 'function') {
            fn();
          } else {
            console.warn('Função de inicialização não encontrada:', window.funcaoInicializacao);
          }
          window.funcaoInicializacao = null; // Limpa para evitar reutilização indevida
        }
      }, 50);
    })
    .catch(() => {
      container.innerHTML = '<h2>Erro ao carregar a página.</h2>';
      container.style.visibility = 'visible';
    });
}

// Ao carregar o DOM, verifica se há uma página anterior no hash ou sessionStorage
document.addEventListener("DOMContentLoaded", function () {
  const conteudo = document.getElementById("conteudo");
  const urlHash = window.location.hash ? window.location.hash.substring(1) : null;
  const paginaSalva = sessionStorage.getItem('paginaAtual');
  const paginaInicial = "partes/home.php";

  const paginaParaCarregar = urlHash || paginaSalva || paginaInicial;
  carregarPagina(paginaParaCarregar, false); // Não adiciona ao histórico pois é carregamento inicial
});

// Ouve cliques em links com data-page
document.addEventListener('click', function (e) {
  const submenuLink = e.target.closest('.has-submenu > a');
  const dataPageLink = e.target.closest('a[data-page]');

  if (submenuLink) {
    e.preventDefault();
    submenuLink.parentElement.classList.toggle('open');
    return;
  }

  if (dataPageLink) {
    e.preventDefault();
    const url = dataPageLink.getAttribute('data-page');
    carregarPagina(url);
  }
});

// Trata botão voltar/avançar do navegador
window.onpopstate = function (event) {
  const url = (event.state && event.state.url) || "partes/conteudo.php";
  carregarPagina(url, false); // false para não empilhar novamente no histórico
};


// Fecha modais Bootstrap manualmente
function fecharModalAberto() {
  const modalAberto = document.querySelector('.modal.show');
  if (modalAberto) {
    const instance = bootstrap.Modal.getInstance(modalAberto);
    if (instance) instance.hide();

    // Remove backdrop e classe do body manualmente
    document.body.classList.remove('modal-open');
    document.querySelector('.modal-backdrop')?.remove();
  }
}
    
    
    