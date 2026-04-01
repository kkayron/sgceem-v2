<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="assets/js/core/jquery-3.7.1.min.js"></script>
<script src="assets/js/plugin/datatables/datatables.min.js"></script>
<script>
  $(document).ready(function () {
    $('#multi-filter-select').DataTable({
      orderCellsTop: true,
      fixedHeader: true,
      initComplete: function () {
        var api = this.api();
        api.columns().every(function (colIdx) {
          var column = this;
          var select = $('thead tr:eq(1) th').eq(colIdx).find('select');
          select.empty().append('<option value="">Todos</option>');

          column.data().unique().sort().each(function (d) {
            select.append('<option value="' + d + '">' + d + '</option>');
          });

          select.on('change', function () {
            var val = $.fn.dataTable.util.escapeRegex($(this).val());
            column.search(val ? '^' + val + '$' : '', true, false).draw();
          });
        });
      }
    });
  });
</script>
    <script src="assets/js/core/popper.min.js"></script>
    <script src="assets/js/core/bootstrap.min.js"></script>

    <!-- jQuery Scrollbar -->
    <script src="assets/js/plugin/jquery-scrollbar/jquery.scrollbar.min.js"></script>

    <!-- Chart JS -->
    <script src="assets/js/plugin/chart.js/chart.min.js"></script>

    <!-- jQuery Sparkline -->
    <script src="assets/js/plugin/jquery.sparkline/jquery.sparkline.min.js"></script>

    <!-- Chart Circle -->
    <script src="assets/js/plugin/chart-circle/circles.min.js"></script>

   

    <!-- Bootstrap Notify -->
    <script src="assets/js/plugin/bootstrap-notify/bootstrap-notify.min.js"></script>

    <!-- jQuery Vector Maps -->
    <script src="assets/js/plugin/jsvectormap/jsvectormap.min.js"></script>
    <script src="assets/js/plugin/jsvectormap/world.js"></script>

    <!-- Sweet Alert -->
    <script src="assets/js/plugin/sweetalert/sweetalert.min.js"></script>

    <!-- Kaiadmin JS -->
    <script src="assets/js/kaiadmin.min.js"></script>

      
      
      
      
    <!-- Kaiadmin DEMO methods, don't include it in your project! -->
    <script src="assets/js/setting-demo.js"></script>
    <script src="assets/js/demo.js"></script>
    <script>
      $("#lineChart").sparkline([102, 109, 120, 99, 110, 105, 115], {
        type: "line",
        height: "70",
        width: "100%",
        lineWidth: "2",
        lineColor: "#177dff",
        fillColor: "rgba(23, 125, 255, 0.14)",
      });

      $("#lineChart2").sparkline([99, 125, 122, 105, 110, 124, 115], {
        type: "line",
        height: "70",
        width: "100%",
        lineWidth: "2",
        lineColor: "#f3545d",
        fillColor: "rgba(243, 84, 93, .14)",
      });

      $("#lineChart3").sparkline([105, 103, 123, 100, 95, 105, 115], {
        type: "line",
        height: "70",
        width: "100%",
        lineWidth: "2",
        lineColor: "#ffa534",
        fillColor: "rgba(255, 165, 52, .14)",
      });
    </script>
      <script>
      $(document).ready(function () {
        $("#basic-datatables").DataTable({});

        $("#multi-filter-select").DataTable({
          pageLength: 5,
          initComplete: function () {
            this.api()
              .columns()
              .every(function () {
                var column = this;
                var select = $(
                  '<select class="form-select"><option value=""></option></select>'
                )
                  .appendTo($(column.footer()).empty())
                  .on("change", function () {
                    var val = $.fn.dataTable.util.escapeRegex($(this).val());

                    column
                      .search(val ? "^" + val + "$" : "", true, false)
                      .draw();
                  });

                column
                  .data()
                  .unique()
                  .sort()
                  .each(function (d, j) {
                    select.append(
                      '<option value="' + d + '">' + d + "</option>"
                    );
                  });
              });
          },
        });

        // Add Row
        $("#add-row").DataTable({
          pageLength: 5,
        });

        var action =
          '<td> <div class="form-button-action"> <button type="button" data-bs-toggle="tooltip" title="" class="btn btn-link btn-primary btn-lg" data-original-title="Edit Task"> <i class="fa fa-edit"></i> </button> <button type="button" data-bs-toggle="tooltip" title="" class="btn btn-link btn-danger" data-original-title="Remove"> <i class="fa fa-times"></i> </button> </div> </td>';

        $("#addRowButton").click(function () {
          $("#add-row")
            .dataTable()
            .fnAddData([
              $("#addName").val(),
              $("#addPosition").val(),
              $("#addOffice").val(),
              action,
            ]);
          $("#addRowModal").modal("hide");
        });
      });
    </script>

<!-- Script GERAL -->
<script src="js/main.js"></script>
<script src="js/global.js"></script>

<!-- Script USUÁRIOS -->
<script src="js/usuarios.js"></script> 

<!-- Script FROTA -->
<script src="js/atualiza_odometro.js"></script>
<script src="js/cadastro_vtr.js"></script>
<script src="js/listagem_frota.js"></script>

<!-- Script SEC CTRL -->
<script src="js/ordem_servico.js?v=<?= time(); ?>"></script>

<!-- Script FINANCEIRO -->
<script src="js/fin_fornecedores.js?v=<?= time(); ?>"></script>
<script src="js/financeiro/pregoes_listagem.js?v=<?= time(); ?>"></script>
<script src="js/financeiro/requisicao.js?v=<?= time(); ?>"></script>
<script src="js/financeiro/pedidos_forn.js?v=<?= time(); ?>"></script>
<script src="js/financeiro/conrazao_corrente.js?v=<?= time(); ?>"></script>
<script src="js/financeiro/ordemfornec.js?v=<?= time(); ?>"></script>
<script src="js/financeiro/empenhos.js?v=<?= time(); ?>"></script>

<!-- Script STA -->
<script src="js/sta/fichas.js?v=<?= time(); ?>" defer></script>

<!-- Script ALMOX -->
<script src="js/almox/pedidos.js?v=<?= time(); ?>"></script>
<script src="js/almox/produtos.js?v=<?= time(); ?>"></script>

<!-- Script CONFIGURAÇÕES -->
<script src="js/funcoes_permissoes.js?v=<?= time(); ?>"></script>
<script src="js/configuracoes.js?v=<?= time(); ?>"></script>
<script src="js/oms.js?v=<?= time(); ?>"></script>

      <script>
// VER PERFIL NA PÁGINA USUÁRIOS
let idUsuarioAtual = null;
let debounceTimer = null;

function carregarLogsUsuario(pagina = 1) {
  const data = document.getElementById('filtro-data')?.value || '';
  const acao = document.getElementById('filtro-acao')?.value || '';
  const palavra = document.getElementById('filtro-palavra')?.value || '';

  if (!idUsuarioAtual) return;

 const params = new URLSearchParams();
  params.set('id', idUsuarioAtual);
  params.set('pagina', pagina);

  if (data) params.set('data', data);
  if (acao) params.set('acao', acao);
  if (palavra) params.set('palavra', palavra);

  fetch('includes/usuarios/logs_usuario.php?' + params.toString())
    .then(res => res.json())
    .then(resultado => {
      const tbody = document.querySelector('#tabela-logs tbody');
      const paginacaoDivs = document.querySelectorAll('.paginacao-logs');

      tbody.innerHTML = '';
      paginacaoDivs.forEach(div => div.innerHTML = '');

      if (!resultado.logs.length) {
        tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted">Nenhum log encontrado.</td></tr>';
        return;
      }

      resultado.logs.forEach(log => {
        const tr = document.createElement('tr');
        tr.innerHTML = 
          <td>${log.data}</td>
          <td>${log.acao}</td>
          <td>${log.descricao}</td>
          <td>${log.ip}</td>
          <td>${log.navegador}</td>;
        tbody.appendChild(tr);
      });

      const totalPaginas = Math.ceil(resultado.total / resultado.porPagina);
      if (totalPaginas > 1) {
        let html = '';
        for (let i = 1; i <= totalPaginas; i++) {
          html += <button class="btn btn-sm ${i === resultado.paginaAtual ? 'btn-primary' : 'btn-outline-light'} me-1 paginador-logs" data-pagina="${i}">${i}</button>;
        }
        paginacaoDivs.forEach(div => {
          div.innerHTML = html;

          // Adiciona eventos dinâmicos nos botões de paginação (preservando os filtros)
          div.querySelectorAll('.paginador-logs').forEach(btn => {
            btn.addEventListener('click', () => {
              const paginaSelecionada = parseInt(btn.dataset.pagina);
              carregarLogsUsuario(paginaSelecionada);
            });
          });
        });
      }
    });
}

function verPerfilUsuario(id) {
  idUsuarioAtual = id;

  fetch('includes/usuarios/buscar_usuario.php?id=' + id)
    .then(res => res.json())
    .then(usuario => {
      if (usuario.erro) return alert('Erro ao buscar usuário.');

      document.getElementById('perfil-foto').src = 'assets/fotoperfil/' + usuario.foto;
      document.getElementById('perfil-postograd').textContent = usuario.postograd;
      document.getElementById('perfil-nomeguerra').textContent = usuario.nomeguerra;
      document.getElementById('perfil-funcao').textContent = usuario.funcao;

      document.getElementById('btnExportarExcel').href = 'includes/usuarios/exportar_excel.php?id=' + id;
      document.getElementById('btnExportarPDF').href = 'includes/usuarios/exportar_pdf.php?id=' + id;

      // Limpa filtros
      document.getElementById('filtro-data').value = '';
      document.getElementById('filtro-acao').value = '';
      document.getElementById('filtro-palavra').value = '';

      // Adiciona eventos dos filtros (com debounce) ao abrir o modal
      ['filtro-data', 'filtro-acao'].forEach(inputId => {
        const el = document.getElementById(inputId);
        if (el && !el.dataset.listenerAdded) {
          el.addEventListener('input', () => {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(() => {
              carregarLogsUsuario(1);
            }, 100);
          });
          el.dataset.listenerAdded = "true";
        }
      });

      const palavraEl = document.getElementById('filtro-palavra');
      if (palavraEl && !palavraEl.dataset.listenerAdded) {
        palavraEl.addEventListener('input', () => {
          clearTimeout(debounceTimer);
          debounceTimer = setTimeout(() => {
            carregarLogsUsuario(1);
          }, 300);
        });
        palavraEl.dataset.listenerAdded = "true";
      }

      carregarLogsUsuario(1); // Carrega primeira página dos logs
      new bootstrap.Modal(document.getElementById('modalPerfilUsuario')).show();
    });
}
      
</script>
<script>
         // LOGS GERAL
window.inicializarLogs = function() {
  function carregarLogs(pagina = 1) {
    const data = $('#filtro-data-geral').val();
    const usuario = $('#filtro-usuario-geral').val();
    const acao = $('#filtro-acao-geral').val();
    const palavra = $('#filtro-palavra-geral').val();

    $.ajax({
      url: 'includes/logs/buscar_logs.php',
      method: 'POST',
      data: { pagina, data, usuario, acao, palavra },
      dataType: 'json',
      success: function (resposta) {
        $('#tabela-logs tbody').html(resposta.tabela);
        $('.paginacao-logs').html(resposta.paginacao);
      },
      error: function () {
        console.error('Erro ao buscar logs');
      }
    });
  }

  $(document).on('change', '#filtro-data-geral', function () {
    carregarLogs(1);
  });

  $(document).on('change', '#filtro-usuario-geral', function () {
    carregarLogs(1);
  });

  $(document).on('change', '#filtro-acao-geral', function () {
    carregarLogs(1);
  });

  $(document).on('keyup', '#filtro-palavra-geral', function () {
    carregarLogs(1);
  });

  $(document).on('click', '.pagina-link', function (e) {
    e.preventDefault();
    const pagina = $(this).data('pagina');
    carregarLogs(pagina);
  });

  $(document).on('submit', '#form-exportar-pdf', function (e) {
    e.preventDefault();
    $('#export-data').val($('#filtro-data-geral').val());
    $('#export-usuario').val($('#filtro-usuario-geral').val());
    $('#export-acao').val($('#filtro-acao-geral').val());
    $('#export-palavra').val($('#filtro-palavra-geral').val());
    this.submit();
  });

  $(document).on('submit', '#form-exportar-excel', function () {
    $('#export-data-excel').val($('#filtro-data-geral').val());
    $('#export-usuario-excel').val($('#filtro-usuario-geral').val());
    $('#export-acao-excel').val($('#filtro-acao-geral').val());
    $('#export-palavra-excel').val($('#filtro-palavra-geral').val());
  });

$(document).ready(function() {
  carregarLogs();
});
}
</script>

  </body>
</html>