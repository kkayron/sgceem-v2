<?php

header('Content-Type: text/html; charset=utf-8');
session_start();
include_once('../../conexao/config.php');
require_once '../api/seguranca_editar.php';

$permissoes = verificarPermissao(13);

$pode_cadastrar = $permissoes['cadastrar'];
$pode_editar    = $permissoes['editar'];
$pode_deletar   = $permissoes['deletar'];
$pode_importar  = $permissoes['importar'];

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
        <h3 class="fw-bold mb-1">Atualizar Odômetro</h3>
        <h6 class="text-muted">Controle de odômetros</h6>
      </div>
        <div>
         <!-- Botão para abrir modal -->
            
            <?php if ($pode_importar): ?>
<button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#modalImportarOdometros">
  Importar Planilha de Odômetros/Horímetros
</button>
            <?php endif; ?>
      </div>
    </div>

   <div class="card">
  <div class="card-body">
    <?php
require_once '../../conexao/config.php';

// ====================================
// 🔹 Captura dados da sessão
// ====================================
$id_om_usuario = $_SESSION['usuario']['batalhao'] ?? 0;
$nivel_usuario = $_SESSION['usuario']['nivel'] ?? 3;

// ====================================
// 🔹 Monta lista de OMs acessíveis
// ====================================
$oms_visiveis = [];

if ($nivel_usuario == 1) {
    // Nível 1 → todas as OMs
    $sql_oms = "SELECT id, nome, abreviatura FROM organizacoes_militares ORDER BY nome";
} elseif ($nivel_usuario == 2) {
    // Nível 2 → sua OM + subordinadas
    $sql_oms = "
        SELECT om.id, om.nome, om.abreviatura
        FROM organizacoes_militares om
        JOIN organizacoes_militares_sub sub ON om.id = sub.id_om_menor
        WHERE sub.id_om_maior = ?
        UNION
        SELECT id, nome, abreviatura FROM organizacoes_militares WHERE id = ?
        ORDER BY nome
    ";
} else {
    // Nível 3 → apenas sua OM
    $sql_oms = "SELECT id, nome, abreviatura FROM organizacoes_militares WHERE id = ?";
}

$stmt_oms = $conexao->prepare($sql_oms);

if ($nivel_usuario == 2) {
    $stmt_oms->bind_param("ii", $id_om_usuario, $id_om_usuario);
} elseif ($nivel_usuario == 3) {
    $stmt_oms->bind_param("i", $id_om_usuario);
}

$stmt_oms->execute();
$res_oms = $stmt_oms->get_result();
while ($r = $res_oms->fetch_assoc()) {
    $oms_visiveis[$r['id']] = $r['abreviatura'] ?: $r['nome'];
}
$stmt_oms->close();

// ====================================
// 🔹 Define filtro de OMs permitidas
// ====================================
if (!empty($oms_visiveis)) {
    $ids_oms = implode(',', array_keys($oms_visiveis));
    $whereBatalhao = "f.batalhao IN ($ids_oms)";
} else {
    $whereBatalhao = "1=0"; // sem acesso
}

// ====================================
// 🔹 Data selecionada
// ====================================
$dataSelecionada = $_GET['data'] ?? date('Y-m-d');

// ====================================
// 🔹 Processar POST (salvar medições)
// ====================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['medicoes'])) {
    foreach ($_POST['medicoes'] as $viatura_id => $valores) {
        $odometro = floatval($valores['odometro'] ?? 0);
        $statusOdometro = $valores['status_odometro'] ?? 'Funciona';

        // Verificar se já existe registro
        $sqlVerifica = "SELECT id FROM controle_medicoes WHERE viatura_id = ? AND data = ?";
        $stmtVerifica = $conexao->prepare($sqlVerifica);
        $stmtVerifica->bind_param("is", $viatura_id, $dataSelecionada);
        $stmtVerifica->execute();
        $resultadoVerifica = $stmtVerifica->get_result();

        if ($resultadoVerifica->num_rows > 0) {
            // Atualiza odômetro
            $sqlUpdate = "UPDATE controle_medicoes SET odometro = ? WHERE viatura_id = ? AND data = ?";
            $stmtUpdate = $conexao->prepare($sqlUpdate);
            $stmtUpdate->bind_param("dis", $odometro, $viatura_id, $dataSelecionada);
            $stmtUpdate->execute();
        } else {
            // Insere odômetro
            $sqlInsert = "INSERT INTO controle_medicoes (viatura_id, data, odometro) VALUES (?, ?, ?)";
            $stmtInsert = $conexao->prepare($sqlInsert);
            $stmtInsert->bind_param("isd", $viatura_id, $dataSelecionada, $odometro);
            $stmtInsert->execute();
        }

        // Atualizar status_odometro na tabela frota
        $sqlStatus = "UPDATE frota SET status_odometro = ? WHERE id = ?";
        $stmtStatus = $conexao->prepare($sqlStatus);
        $stmtStatus->bind_param("si", $statusOdometro, $viatura_id);
        $stmtStatus->execute();
    }

    echo "<div class='alert alert-success'>Medições salvas com sucesso!</div>";
}

// ====================================
// 🔹 Filtros
// ====================================
$filtros = ['batalhao','ativo','tipo','prefixo_sga','marca','modelo','ano'];
foreach ($filtros as $filtro) {
    $$filtro = $_GET[$filtro] ?? '';
}
$filtroPesquisa = $_GET['pesquisa'] ?? '';

// ====================================
// 🔹 Query principal com filtros
// ====================================
$sqlViaturas = "
    SELECT 
        f.id, 
        f.prefixo_sga, 
        f.status_odometro,
        f.batalhao,
        m.marca AS nome_marca, 
        mo.nome_modelo AS nome_modelo,
        om.nome AS nome_batalhao, 
        om.abreviatura AS abreviatura_batalhao
    FROM frota f
    LEFT JOIN config_marcas m ON f.marca = m.id
    LEFT JOIN config_modelos mo ON f.modelo = mo.id
    LEFT JOIN organizacoes_militares om ON f.batalhao = om.id
    WHERE $whereBatalhao
";

// 🔸 Aplicar filtros
foreach ($filtros as $filtro) {
    if (!empty($$filtro)) {
        $valor = $conexao->real_escape_string($$filtro);
        $sqlViaturas .= " AND f.$filtro = '$valor'";
    }
}

// 🔸 Pesquisa livre
if (!empty($filtroPesquisa)) {
    $termo = $conexao->real_escape_string($filtroPesquisa);
    $sqlViaturas .= " AND (
        f.prefixo_sga LIKE '%$termo%' OR
        mo.nome_modelo LIKE '%$termo%' OR
        m.marca LIKE '%$termo%' OR
        om.nome LIKE '%$termo%' OR
        om.abreviatura LIKE '%$termo%'
    )";
}

$sqlViaturas .= " ORDER BY f.prefixo_sga";
$resultViaturas = $conexao->query($sqlViaturas);

// ====================================
// 🔹 Buscar medições da data
// ====================================
$sqlDados = "SELECT viatura_id, odometro FROM controle_medicoes WHERE data = ?";
$stmtDados = $conexao->prepare($sqlDados);
$stmtDados->bind_param("s", $dataSelecionada);
$stmtDados->execute();
$resultDados = $stmtDados->get_result();

$dadosPorViatura = [];
while ($row = $resultDados->fetch_assoc()) {
    $dadosPorViatura[$row['viatura_id']] = $row;
}
?>

<!-- 🔹 Formulário de filtros -->
<form id="formFiltroDataEditar" class="mb-3" method="get">
  <div class="d-flex justify-content-between align-items-end flex-wrap" style="gap: 10px;">
    <div class="d-flex align-items-end gap-2 flex-wrap">

      <!-- Data -->
      <div>
        <label for="data" class="form-label">Selecionar Data:</label>
        <input type="date" name="data" id="data" value="<?= htmlspecialchars($dataSelecionada) ?>" class="form-control">
      </div>

      <!-- OM -->
      <div>
        <label for="batalhao" class="form-label">Organização Militar:</label>
        <select name="batalhao" id="batalhao" class="form-select">
          <option value="">Todas</option>
          <?php foreach ($oms_visiveis as $id => $nomeOM): ?>
            <option value="<?= $id ?>" <?= ($batalhao == $id) ? 'selected' : '' ?>>
              <?= htmlspecialchars($nomeOM) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <!-- Tipo -->
      <div>
        <label for="tipo" class="form-label">Tipo:</label>
        <select name="tipo" id="tipo" class="form-select">
          <option value="">Todos</option>
          <?php
          $tipos = $conexao->query("SELECT DISTINCT tipo FROM frota WHERE batalhao IN ($ids_oms) ORDER BY tipo");
          while ($row = $tipos->fetch_assoc()): ?>
            <option value="<?= htmlspecialchars($row['tipo']) ?>" <?= $row['tipo'] === $tipo ? 'selected' : '' ?>>
              <?= htmlspecialchars($row['tipo']) ?>
            </option>
          <?php endwhile; ?>
        </select>
      </div>

      <!-- Prefixo -->
      <div>
        <label for="prefixo_sga" class="form-label">Prefixo SGA:</label>
        <input type="text" name="prefixo_sga" id="prefixo_sga" class="form-control" value="<?= htmlspecialchars($prefixo_sga) ?>" placeholder="Ex: VTR-001">
      </div>

      <!-- Pesquisa -->
      <div>
        <label for="pesquisa" class="form-label">Pesquisar:</label>
        <input type="text" name="pesquisa" id="pesquisa" value="<?= htmlspecialchars($filtroPesquisa) ?>" class="form-control" placeholder="Buscar por modelo, OM, etc...">
      </div>

      <button type="submit" class="btn btn-primary btn-sm">Filtrar</button>
    </div>

    <button type="button" id="AcessarControle" class="btn btn-secondary btn-sm">Retornar ao controle</button>
  </div>
</form>

    <!-- Formulário de edição -->
    <form id="formMedicoes">
        <input type="hidden" name="data" value="<?= htmlspecialchars($dataSelecionada) ?>">

        <div class="text-end mt-3 mb-2">
            <?php if ($pode_cadastrar): ?>
          <button type="submit" class="btn btn-success">Salvar Medições</button>
             <?php endif; ?>
        </div>

        <table class="table table-bordered table-hover">
            <thead class="table-dark">
                <tr>
                    <th>Viatura</th>
                    <th>Status Odômetro</th>
                    <th>Odômetro (<?= htmlspecialchars(date('d/m/Y', strtotime($dataSelecionada))) ?>)</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($viatura = $resultViaturas->fetch_assoc()):
                    $id = $viatura['id'];
                    $odometro = $dadosPorViatura[$id]['odometro'] ?? '';
                    $statusAtual = $viatura['status_odometro'] ?: 'Funciona';
                    $nomeMarca = $viatura['nome_marca'] ?? '-';
                    $nomeModelo = $viatura['nome_modelo'] ?? '-';
                ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($viatura['prefixo_sga']) ?></strong> - <?= htmlspecialchars($nomeMarca.' '.$nomeModelo) ?></td>
                        <td>
                            <select name="medicoes[<?= $id ?>][status_odometro]" class="form-select">
                                <option value="Funciona" <?= $statusAtual == 'Funciona' ? 'selected' : '' ?>>Funciona</option>
                                <option value="Não Funciona" <?= $statusAtual == 'Não Funciona' ? 'selected' : '' ?>>Não Funciona</option>
                                <option value="Não Possui" <?= $statusAtual == 'Não Possui' ? 'selected' : '' ?>>Não Possui</option>
                            </select>
                        </td>
                        <td>
                            <input type="number" step="0.1" name="medicoes[<?= $id ?>][odometro]" class="form-control" value="<?= htmlspecialchars($odometro) ?>">
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>

        <button type="submit" class="btn btn-success">Salvar Medições</button>
    </form>
  </div>
</div>

  </div>
</div>

<!-- Modal de Importação de Odômetros/Horímetros -->
<div class="modal fade" id="modalImportarOdometros" tabindex="-1" aria-labelledby="modalImportarOdometrosLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
        <form id="formImportarOdometro" action="#" method="POST" enctype="multipart/form-data">
        <div class="modal-header">
          <h5 class="modal-title" id="modalImportarOdometrosLabel">Importar Odômetros/Horímetros via Planilha</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label for="arquivo" class="form-label">Selecione a planilha (.xlsx)</label>
            <input type="file" name="arquivo" id="arquivo" accept=".xlsx" class="form-control" required>
          </div>
          <div class="mt-3">
            <a href="includes/odometro/planilha_modelo.xlsx" class="btn btn-link">
              📥 Baixar modelo de planilha
            </a>
          </div>
        </div>
        <div class="modal-footer">
          <button type="submit" class="btn btn-primary">Importar</button>
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
        </div>
      </form>
    </div>
  </div>
</div>


<script>
  window.funcaoInicializacao = 'inicializarAtualizarOdometro';
</script>
