<?php
session_start();
require_once '../../conexao/config.php';

header('Content-Type: text/html; charset=utf-8');

$id_os = isset($_GET['id_os']) ? (int)$_GET['id_os'] : 0;

if ($id_os <= 0) {
    echo "<div class='alert alert-danger mb-0'>OS inválida.</div>";
    exit;
}

$sqlOS = "
    SELECT 
        os.id,
        os.id_frota,
        os.odometro_horimetro,
        f.marca,
        f.modelo,
        f.prefixo_sga,
        cm.marca AS nome_marca,
        md.nome_modelo
    FROM os_principal os
    INNER JOIN frota f ON f.id = os.id_frota
    LEFT JOIN config_marcas cm ON cm.id = f.marca
    LEFT JOIN config_modelos md ON md.id = f.modelo
    WHERE os.id = ?
    LIMIT 1
";

$stmtOS = $conexao->prepare($sqlOS);
$stmtOS->bind_param("i", $id_os);
$stmtOS->execute();
$resOS = $stmtOS->get_result();

if ($resOS->num_rows === 0) {
    echo "<div class='alert alert-warning mb-0'>OS ou viatura não encontrada.</div>";
    exit;
}

$os = $resOS->fetch_assoc();

$sqlMarcadas = "
    SELECT id_plano
    FROM mnt_execucoes
    WHERE id_osprincipal = ?
";

$stmtMarcadas = $conexao->prepare($sqlMarcadas);
$stmtMarcadas->bind_param("i", $id_os);
$stmtMarcadas->execute();
$resMarcadas = $stmtMarcadas->get_result();

$marcadas = [];
while ($m = $resMarcadas->fetch_assoc()) {
    $marcadas[] = (int)$m['id_plano'];
}

$sqlPlanos = "
    SELECT *
    FROM mnt_planos
    WHERE id_marca = ?
      AND id_modelo = ?
      AND ativo = 1
    ORDER BY descricao ASC
";

$stmtPlanos = $conexao->prepare($sqlPlanos);
$stmtPlanos->bind_param("ii", $os['marca'], $os['modelo']);
$stmtPlanos->execute();
$resPlanos = $stmtPlanos->get_result();

function labelTipoMntPlanoEdit($tipo) {
    return match ($tipo) {
        'odometro' => 'Odômetro',
        'horimetro' => 'Horímetro',
        'tempo' => 'Tempo',
        'odometro_tempo' => 'Odômetro + Tempo',
        'horimetro_tempo' => 'Horímetro + Tempo',
        default => '—'
    };
}

function fmtMntEdit($v) {
    if ($v === null || $v === '') return '—';
    return number_format((float)$v, 2, ',', '.');
}
?>

<div class="mb-2">
  <strong><?= htmlspecialchars($os['prefixo_sga'] ?? '') ?></strong>
  <span class="text-muted">
    <?= htmlspecialchars(($os['nome_marca'] ?? '') . ' / ' . ($os['nome_modelo'] ?? '')) ?>
  </span>
</div>

<?php if ($resPlanos->num_rows === 0): ?>

  <div class="alert alert-info mb-0">
    Nenhum plano de manutenção programada cadastrado para a marca/modelo deste ativo.
  </div>

<?php else: ?>

  <div class="alert alert-warning py-2">
    Marque as manutenções programadas realizadas nesta OS. As alterações serão sincronizadas ao salvar.
  </div>

  <div class="row g-2">
    <?php while ($plano = $resPlanos->fetch_assoc()): ?>
      <?php $checked = in_array((int)$plano['id'], $marcadas, true) ? 'checked' : ''; ?>

      <div class="col-md-6">
        <label class="mnt-os-card-edit">
          <input 
            type="checkbox" 
            name="manutencoes_programadas[]" 
            value="<?= (int)$plano['id'] ?>"
            class="form-check-input me-2"
            <?= $checked ?>
          >

          <div>
            <strong><?= htmlspecialchars($plano['descricao']) ?></strong>

            <div class="small text-muted">
              <?= htmlspecialchars(labelTipoMntPlanoEdit($plano['tipo_controle'])) ?>
            </div>

            <div class="small">
              Valor inicial: <strong><?= fmtMntEdit($plano['valor_inicial']) ?></strong> |
              Intervalo: <strong><?= fmtMntEdit($plano['intervalo_valor']) ?></strong> |
              Dias: <strong><?= $plano['intervalo_dias'] ? (int)$plano['intervalo_dias'] : '—' ?></strong>
            </div>
          </div>
        </label>
      </div>
    <?php endwhile; ?>
  </div>

  <style>
    .mnt-os-card-edit {
      display: flex;
      gap: .5rem;
      align-items: flex-start;
      width: 100%;
      padding: .75rem;
      border: 1px solid #dee2e6;
      border-radius: .75rem;
      background: #fff;
      cursor: pointer;
      transition: .2s;
    }

    .mnt-os-card-edit:hover {
      background: #fff8e1;
      border-color: #ffda6a;
    }
  </style>

<?php endif; ?>