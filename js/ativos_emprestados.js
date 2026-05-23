// JavaScript Document
function toggleFiltrosEmprestados() {
    const box = document.getElementById('filtros-emprestados-container');

    if (!box) return;

    box.style.display = box.style.display === 'none' ? 'block' : 'none';
}

function limparFiltrosEmprestados() {
    if (typeof carregarPagina === 'function') {
        carregarPagina('includes/frota/ativos_emprestados.php');
    } else {
        window.location.href = 'ativos_emprestados.php';
    }
}

window.inicializarAtivosEmprestados = function() {
	// PÁGINA ATIVOS EMPRESTADOS
const formAtivosEmprestados = document.getElementById('filtroAtivosEmprestadosForm');

function atualizarAtivosEmprestadosComParametros(extraParams = {}) {
    if (!formAtivosEmprestados) return;

    const formData = new FormData(formAtivosEmprestados);
    const params = new URLSearchParams(formData);

    for (const key in extraParams) {
        params.set(key, extraParams[key]);
    }

    const url = `includes/frota/ativos_emprestados.php?${params.toString()}`;

    carregarPagina(url);
}

// Submissão do formulário
if (formAtivosEmprestados) {
    formAtivosEmprestados.addEventListener('submit', function(e) {
        e.preventDefault();

        atualizarAtivosEmprestadosComParametros({
            pagina: 1
        });
    });
}

// Mudança de limite por página
const limiteAtivosEmprestados = document.getElementById('limite');

if (limiteAtivosEmprestados) {
    limiteAtivosEmprestados.addEventListener('change', function() {
        atualizarAtivosEmprestadosComParametros({
            pagina: 1,
            limite: this.value
        });
    });
}

// Botão Limpar Filtros
const btnLimparFiltrosEmprestados = document.getElementById('btnLimparFiltros');

if (btnLimparFiltrosEmprestados && formAtivosEmprestados) {
    btnLimparFiltrosEmprestados.addEventListener('click', function(e) {
        e.preventDefault();

        formAtivosEmprestados.reset();

        const limiteAtual =
            document.getElementById('limite')?.value || 10;

        const url =
            `includes/frota/ativos_emprestados.php?pagina=1&limite=${limiteAtual}`;

        carregarPagina(url);
    });
}

// Paginação AJAX
document.addEventListener('click', function(e) {
    const link = e.target.closest('.paginacao-frota');

    if (!link) return;

    const url = link.getAttribute('data-page');

    if (!url || !url.includes('ativos_emprestados.php')) return;

    e.preventDefault();

    carregarPagina(url);
});
}