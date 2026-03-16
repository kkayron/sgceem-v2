<?php
header('Content-Type: text/html; charset=utf-8');
session_start();
include_once('../../conexao/config.php');

// Obtem o filtro da URL e decodifica
$filtro_funcao = isset($_GET['funcao']) ? urldecode($_GET['funcao']) : 'todos';

// Aplica o filtro na consulta
if ($filtro_funcao !== 'todos') {
    $stmt = $conexao->prepare("SELECT * FROM usuarios WHERE funcao = ? ORDER BY id DESC");
    $stmt->bind_param("s", $filtro_funcao);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    $sql = "SELECT * FROM usuarios ORDER BY id DESC";
    $result = $conexao->query($sql);
}
?>

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
        <h3 class="fw-bold mb-1">Enviar CONRAZAO do ano corrente</h3>
        <h6 class="text-muted">Página de upload</h6>
      </div>
    </div>
<?php
include '../../conexao/config.php';

$successos = [];
$falhas = [];

if (isset($_FILES['arquivo']) && $_FILES['arquivo']['error'] === UPLOAD_ERR_OK) {
    $arquivo = $_FILES['arquivo']['tmp_name'];
    $linhas = file($arquivo, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    foreach ($linhas as $linha) {
        // Match linhas do tipo: N 2025NE000001 10
        if (preg_match('/^N\s+(20\d{2}NE\d{6})\s+\d+/', $linha, $matches)) {
            $nmr_empenho = $matches[1];
            $index = array_search($linha, $linhas);
            $linha_saldo = trim($linhas[$index + 1] ?? '');

            // Extrair saldo (último número com vírgula)
            if (preg_match('/([\d.]+,\d{2})\s*[CD]?$/i', $linha_saldo, $matchSaldo)) {
    // Captura valor como "5.245,00" e remove "C" ou "D" (crédito/débito) ao final
    $valor_br = $matchSaldo[1]; // exemplo: 5.245,00
    $saldo = floatval(str_replace(['.', ','], ['', '.'], $valor_br)); // resultado: 5245.00

                // Remove anteriores duplicados
                $conexao->query("DELETE FROM fin_siafi_corrente WHERE nmr_empenho = '$nmr_empenho'");

                // Inserir
                $stmt = $conexao->prepare("INSERT INTO fin_siafi_corrente (nmr_empenho, saldo_empenho) VALUES (?, ?)");
                $stmt->bind_param("sd", $nmr_empenho, $saldo);
                if ($stmt->execute()) {
                    $successos[] = [$nmr_empenho, number_format($saldo, 2, ',', '.')];
                } else {
                    $falhas[] = [$nmr_empenho, 'Erro ao inserir'];
                }
                $stmt->close();
            } else {
                $falhas[] = [$nmr_empenho, 'Saldo não encontrado'];
            }
        }
    }
}
?>

<!-- Exibição dos resultados -->
<div class="card shadow-sm border-0 mt-4">
  <div class="card-body">
    <h5 class="card-title">Resultado da Importação</h5>

    <?php if ($successos): ?>
      <h6>Importados com Sucesso:</h6>
      <table class="table table-bordered table-sm">
        <thead><tr><th>Empenho</th><th>Saldo</th></tr></thead>
        <tbody>
          <?php foreach ($successos as [$emp, $saldo]): ?>
            <tr><td><?= htmlspecialchars($emp) ?></td><td>R$ <?= $saldo ?></td></tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>

    <?php if ($falhas): ?>
      <h6 class="mt-3">Falhas:</h6>
      <table class="table table-bordered table-sm table-warning">
        <thead><tr><th>Empenho</th><th>Motivo</th></tr></thead>
        <tbody>
          <?php foreach ($falhas as [$emp, $motivo]): ?>
            <tr><td><?= htmlspecialchars($emp) ?></td><td><?= htmlspecialchars($motivo) ?></td></tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>
</div>

</div>
</div>
