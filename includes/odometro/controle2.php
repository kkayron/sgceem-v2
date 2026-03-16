
<style>
.btn-group .btn {
  border-radius: 20px;
  transition: all 0.3s ease;
}
</style>

<div class="container">
  <div class="page-inner">
    <div class="d-flex justify-content-between align-items-center py-3">
      <div>
        <h3 class="fw-bold mb-1">Atualizar Odometro/Horímetro</h3>
        <h6 class="text-muted">Controle de odômetros e horímetros</h6>
      </div>
    </div>


    <!-- Lista de usuários -->
    <div class="card">
      <div class="card-body">
          <?php
require_once '../../conexao/config.php';

// Data selecionada (padrão: hoje)
$dataSelecionada = isset($_GET['data']) ? $_GET['data'] : date('Y-m-d');

// Lista de viaturas
$sqlViaturas = "SELECT id, prefixo_sga, modelo, tipo FROM frota ORDER BY prefixo_sga";
$resultViaturas = $conexao->query($sqlViaturas);

// Consulta os dados de odômetro/horímetro da data escolhida
$sqlDados = "SELECT viatura_id, odometro, horimetro FROM controle_medicoes WHERE data = ?";
$stmtDados = $conexao->prepare($sqlDados);
$stmtDados->bind_param("s", $dataSelecionada);
$stmtDados->execute();
$resultDados = $stmtDados->get_result();

$dadosPorViatura = [];
while ($row = $resultDados->fetch_assoc()) {
    $dadosPorViatura[$row['viatura_id']] = $row;
}
?>

<!-- Cabeçalho dos botões -->
<div class="d-flex justify-content-between align-items-end flex-wrap mb-3">
  <!-- Botão Filtros Avançados (lado esquerdo) -->
  <button class="btn btn-outline-primary" type="button" data-bs-toggle="collapse" data-bs-target="#filtrosAvancados">
    Filtros Avançados
  </button>

  <!-- Botão Atualizar Odômetros (lado direito) -->
  <button type="button" id="AcessarEditar" class="btn btn-secondary btn-sm">Atualizar odômetros</button>
</div>

<!-- Painel de Filtros -->
<?php $filtrosAtivos = !empty($_GET); ?>
<div class="collapse <?= $filtrosAtivos ? 'show' : '' ?> mb-3" id="filtrosAvancados">
  <form id="formFiltroData">
    <div class="row g-2">
      <?php
      $camposFiltro = ['ativo', 'tipo', 'prefixo_sga', 'acervo', 'marca', 'modelo', 'ano', 'confiabilidade', 'missao', 'emprego_atual', 'subunidade', 'destino', 'disponibilidade'];
      foreach ($camposFiltro as $campo) {
          $query = "SELECT DISTINCT $campo FROM frota ORDER BY $campo";
          $res = $conexao->query($query);
          echo '<div class="col-md-3">';
          echo '<label class="form-label">' . ucfirst(str_replace('_', ' ', $campo)) . '</label>';
          echo '<select name="' . $campo . '" class="form-select">';
          echo '<option value="">Todos</option>';
          while ($opt = $res->fetch_assoc()) {
              $valor = htmlspecialchars($opt[$campo]);
              echo '<option value="' . $valor . '">' . $valor . '</option>';
          }
          echo '</select></div>';
      }
      ?>
      <div class="col-md-3">
        <label class="form-label">Pesquisar</label>
        <input type="text" name="pesquisa" class="form-control" placeholder="Buscar por nome ou modelo">
      </div>
    </div>
    <div class="mt-3 text-end">
      <button type="submit" class="btn btn-primary">Aplicar Filtros</button>
    </div>
  </form>
</div>


<!-- Tabela estilo Excel -->
<table class="table table-bordered table-hover">
    <thead class="table-dark">
        <tr>
            <th>Viatura / Equipamento</th>
            <th>Tipo</th>
            <th>Maior Medição</th>
            <th>Data da Medição</th>
        </tr>
    </thead>
    <tbody>
        <?php while ($viatura = $resultViaturas->fetch_assoc()):
            $viatura_id = $viatura['id'];
            $tipo = $viatura['tipo'];

            // Consulta todas as medições da viatura
            $sqlMedicoes = "SELECT odometro, horimetro, data FROM controle_medicoes WHERE viatura_id = ?";
            $stmtMedicoes = $conexao->prepare($sqlMedicoes);
            $stmtMedicoes->bind_param("i", $viatura_id);
            $stmtMedicoes->execute();
            $resultMedicoes = $stmtMedicoes->get_result();

            $maiorValor = null;
            $dataMaior = null;

            while ($row = $resultMedicoes->fetch_assoc()) {
                $valorAtual = null;
                if ($tipo === 'Vtr') {
                    $valorAtual = $row['odometro'];
                    $unidade = 'Km/L';
                } elseif ($tipo === 'Eqp') {
                    $valorAtual = $row['horimetro'];
                    $unidade = 'H/L';
                } else {
                    // Para outros tipos, pega o maior entre odômetro e horímetro
                    $valorAtual = max($row['odometro'], $row['horimetro']);
                    $unidade = '(maior)';
                }

                // Armazena se for o maior valor encontrado
                if ($valorAtual !== null && ($maiorValor === null || $valorAtual > $maiorValor)) {
                    $maiorValor = $valorAtual;
                    $dataMaior = $row['data'];
                }
            }

            $valorExibicao = $maiorValor !== null ? $maiorValor . ' ' . $unidade : '-';
            $dataExibicao = $dataMaior ? date('d/m/Y', strtotime($dataMaior)) : '-';
        ?>
            <tr>
                <td><strong><?= htmlspecialchars($viatura['prefixo_sga']) ?></strong> - <?= htmlspecialchars($viatura['modelo']) ?></td>
                <td><?= htmlspecialchars(strtoupper($tipo ?: 'Indefinido')) ?></td>
                <td><?= htmlspecialchars($valorExibicao) ?></td>
                <td><?= $dataExibicao ?></td>
            </tr>
        <?php endwhile; ?>
    </tbody>
</table>

      </div>
    </div>

   
      
  </div>
</div>
