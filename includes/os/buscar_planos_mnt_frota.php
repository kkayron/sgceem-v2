<?php
session_start();
require_once '../../conexao/config.php';

header('Content-Type: text/html; charset=utf-8');

$id_frota = isset($_GET['id_frota']) ? (int)$_GET['id_frota'] : 0;

if ($id_frota <= 0) {
    echo "<div class='alert alert-danger mb-0'>Viatura/equipamento inválido.</div>";
    exit;
}

$sqlFrota = "
    SELECT 
        f.id,
        f.marca,
        f.modelo,
        f.prefixo_sga,
        cm.marca AS nome_marca,
        md.nome_modelo
    FROM frota f
    LEFT JOIN config_marcas cm ON cm.id = f.marca
    LEFT JOIN config_modelos md ON md.id = f.modelo
    WHERE f.id = ?
    LIMIT 1
";

$stmtFrota = $conexao->prepare($sqlFrota);
$stmtFrota->bind_param("i", $id_frota);
$stmtFrota->execute();
$resFrota = $stmtFrota->get_result();

if ($resFrota->num_rows === 0) {
    echo "<div class='alert alert-warning mb-0'>Viatura/equipamento não encontrado.</div>";
    exit;
}

$frota = $resFrota->fetch_assoc();

$sqlPlanos = "
    SELECT 
        mp.*
    FROM mnt_planos mp
    WHERE mp.id_marca = ?
      AND mp.id_modelo = ?
      AND mp.ativo = 1
    ORDER BY mp.descricao ASC
";

$stmtPlanos = $conexao->prepare($sqlPlanos);
$stmtPlanos->bind_param("ii", $frota['marca'], $frota['modelo']);
$stmtPlanos->execute();
$resPlanos = $stmtPlanos->get_result();

function labelTipoMntPlano($tipo) {
    return match ($tipo) {
        'odometro' => 'Odômetro',
        'horimetro' => 'Horímetro',
        'tempo' => 'Tempo',
        'odometro_tempo' => 'Odômetro + Tempo',
        'horimetro_tempo' => 'Horímetro + Tempo',
        default => '—'
    };
}

function fmtMnt($v) {
    if ($v === null || $v === '') return '—';
    return number_format((float)$v, 2, ',', '.');
}
?>

<div class="mb-2">
  <strong><?= htmlspecialchars($frota['prefixo_sga'] ?? '') ?></strong>
  <span class="text-muted">
    <?= htmlspecialchars(($frota['nome_marca'] ?? '') . ' / ' . ($frota['nome_modelo'] ?? '')) ?>
  </span>
</div>

<?php if ($resPlanos->num_rows === 0): ?>

  <div class="alert alert-info mb-0">
    Nenhum plano de manutenção programada cadastrado para a marca/modelo deste ativo.
  </div>

<?php else: ?>

  <div class="alert alert-warning py-2">
    Marque abaixo as manutenções programadas que serão realizadas nesta OS.
  </div>

  <div class="row g-2">
    <?php while ($plano = $resPlanos->fetch_assoc()): ?>
      <div class="col-md-6">
        <label class="mnt-os-card">
          <input 
            type="checkbox" 
            name="manutencoes_programadas[]" 
            value="<?= (int)$plano['id'] ?>"
            class="form-check-input me-2"
          >

          <div>
            <strong><?= htmlspecialchars($plano['descricao']) ?></strong>

            <div class="small text-muted">
              <?= htmlspecialchars(labelTipoMntPlano($plano['tipo_controle'])) ?>
            </div>

            <div class="small">
              Valor inicial: <strong><?= fmtMnt($plano['valor_inicial']) ?></strong> |
              Intervalo: <strong><?= fmtMnt($plano['intervalo_valor']) ?></strong> |
              Dias: <strong><?= $plano['intervalo_dias'] ? (int)$plano['intervalo_dias'] : '—' ?></strong>
            </div>
          </div>
        </label>
      </div>
    <?php endwhile; ?>
  </div>

  <style>
    .mnt-os-card {
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

    .mnt-os-card:hover {
      background: #fff8e1;
      border-color: #ffda6a;
    }
  </style>

<?php endif; ?>