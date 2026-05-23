<?php
session_start();
require_once '../../conexao/config.php';

header('Content-Type: text/html; charset=utf-8');

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id <= 0) {
    echo "<div class='alert alert-danger'>ID do plano inválido.</div>";
    exit;
}

// ===============================
// BUSCA PLANO
// ===============================
$sqlPlano = "
    SELECT 
        mp.*,
        cm.marca AS nome_marca,
        cmo.nome_modelo
    FROM mnt_planos mp
    INNER JOIN config_marcas cm ON cm.id = mp.id_marca
    INNER JOIN config_modelos cmo ON cmo.id = mp.id_modelo
    WHERE mp.id = ?
    LIMIT 1
";

$stmtPlano = $conexao->prepare($sqlPlano);
$stmtPlano->bind_param("i", $id);
$stmtPlano->execute();
$resPlano = $stmtPlano->get_result();

if ($resPlano->num_rows === 0) {
    echo "<div class='alert alert-warning'>Plano não encontrado.</div>";
    exit;
}

$plano = $resPlano->fetch_assoc();

$tipoLabel = match ($plano['tipo_controle']) {
    'odometro' => 'Odômetro',
    'horimetro' => 'Horímetro',
    'tempo' => 'Tempo',
    'odometro_tempo' => 'Odômetro + Tempo',
    'horimetro_tempo' => 'Horímetro + Tempo',
    default => '—'
};

$ativoLabel = ((int)$plano['ativo'] === 1) ? 'Ativo' : 'Inativo';
$ativoClass = ((int)$plano['ativo'] === 1) ? 'success' : 'secondary';

// ===============================
// BUSCA FROTA DA MARCA/MODELO
// COM ÚLTIMA MEDIÇÃO
// ===============================
$sqlFrota = "
    SELECT 
        f.id,
        f.prefixo_sga,
        f.prefixo_velho,
        f.nome_sioc,
        f.placa,
        f.ano,
        f.disponibilidade,
        f.confiabilidade,
        f.status,
        f.status_odometro,
        om.abreviatura AS om_abreviatura,
        om.nome AS om_nome,
        med.odometro AS odometro_atual,
        med.data AS data_medicao
    FROM frota f
    LEFT JOIN organizacoes_militares om ON om.id = f.batalhao
    LEFT JOIN (
        SELECT cm1.viatura_id, cm1.odometro, cm1.data
        FROM controle_medicoes cm1
        INNER JOIN (
            SELECT viatura_id, MAX(data) AS ultima_data
            FROM controle_medicoes
            GROUP BY viatura_id
        ) ult ON ult.viatura_id = cm1.viatura_id
             AND ult.ultima_data = cm1.data
    ) med ON med.viatura_id = f.id
    WHERE f.marca = ?
      AND f.modelo = ?
    ORDER BY f.prefixo_sga ASC
";

$stmtFrota = $conexao->prepare($sqlFrota);
$stmtFrota->bind_param("ii", $plano['id_marca'], $plano['id_modelo']);
$stmtFrota->execute();
$resFrota = $stmtFrota->get_result();

$frotas = [];
while ($f = $resFrota->fetch_assoc()) {
    $frotas[] = $f;
}

function badgeDisponibilidade($disp) {
    $d = strtolower(trim((string)$disp));

    if (in_array($d, ['disponível', 'disponivel', 'sim', 'operacional'])) {
        return ['success', $disp ?: 'Disponível'];
    }

    if (in_array($d, ['indisponível', 'indisponivel', 'não', 'nao', 'baixada'])) {
        return ['danger', $disp ?: 'Indisponível'];
    }

    if (in_array($d, ['restrição', 'restricao', 'restrita'])) {
        return ['warning', $disp ?: 'Restrição'];
    }

    return ['secondary', $disp ?: 'Não informado'];
}

function badgeConfiabilidade($conf) {
    $c = strtolower(trim((string)$conf));

    if (in_array($c, ['alta', 'boa'])) {
        return ['success', $conf];
    }

    if (in_array($c, ['média', 'media', 'regular'])) {
        return ['warning', $conf];
    }

    if (in_array($c, ['baixa', 'ruim'])) {
        return ['danger', $conf];
    }

    return ['secondary', $conf ?: 'Não informado'];
}

function formatarNumero($valor) {
    if ($valor === null || $valor === '') return '—';
    return number_format((float)$valor, 2, ',', '.');
}
?>

<div class="plano-ver-wrapper">

  <div class="card border-0 shadow-sm mb-3">
    <div class="card-body">
      <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
        <div>
          <h5 class="fw-bold text-primary mb-1">
            Plano #<?= (int)$plano['id'] ?> - <?= htmlspecialchars($plano['descricao']) ?>
          </h5>

          <div class="text-muted">
            <?= htmlspecialchars($plano['nome_marca']) ?> /
            <?= htmlspecialchars($plano['nome_modelo']) ?>
          </div>
        </div>

        <div class="d-flex gap-2 flex-wrap">
          <span class="badge bg-dark"><?= htmlspecialchars($tipoLabel) ?></span>
          <span class="badge bg-<?= $ativoClass ?>"><?= $ativoLabel ?></span>
        </div>
      </div>

      <hr>

      <div class="row g-3 plano-resumo-dados">
        <div class="col-md-2">
          <small>Valor inicial</small>
          <strong><?= formatarNumero($plano['valor_inicial']) ?></strong>
        </div>

        <div class="col-md-2">
          <small>Intervalo valor</small>
          <strong><?= formatarNumero($plano['intervalo_valor']) ?></strong>
        </div>

        <div class="col-md-2">
          <small>Intervalo dias</small>
          <strong><?= $plano['intervalo_dias'] ? (int)$plano['intervalo_dias'] . ' dias' : '—' ?></strong>
        </div>

        <div class="col-md-2">
          <small>Alerta valor</small>
          <strong><?= formatarNumero($plano['alerta_antes_valor']) ?></strong>
        </div>

        <div class="col-md-2">
          <small>Alerta dias</small>
          <strong><?= $plano['alerta_antes_dias'] ? (int)$plano['alerta_antes_dias'] . ' dias' : '—' ?></strong>
        </div>

        <div class="col-md-2">
          <small>Ativos atingidos</small>
          <strong><?= count($frotas) ?></strong>
        </div>
      </div>
    </div>
  </div>

  <?php if (empty($frotas)): ?>
    <div class="alert alert-info">
      Nenhum ativo da frota encontrado para esta marca/modelo.
    </div>
  <?php else: ?>

    <div class="accordion" id="accordionFrotaPlanoMnt">

      <?php foreach ($frotas as $index => $frota): ?>
        <?php
          [$dispClass, $dispTexto] = badgeDisponibilidade($frota['disponibilidade']);
          [$confClass, $confTexto] = badgeConfiabilidade($frota['confiabilidade']);

          $collapseId = 'collapsePlanoMntFrota' . (int)$frota['id'];

          // ===============================
          // OS ASSOCIADAS AO PLANO E ATIVO
          // ===============================
          $sqlOS = "
              SELECT 
                  me.id AS id_execucao,
                  me.data_execucao,
                  me.odometro_horimetro_execucao,
                  os.id AS id_os,
                  os.data_abertura,
                  os.data_encerramento,
                  os.problema,
                  os.tipo_mnt,
                  os.status,
                  os.odometro_horimetro,
                  os.solicitante,
                  os.local_os,
                  os.valorTOTAL
              FROM mnt_execucoes me
              INNER JOIN os_principal os ON os.id = me.id_osprincipal
              WHERE me.id_plano = ?
                AND me.id_frota = ?
              ORDER BY me.data_execucao DESC, os.id DESC
          ";

          $stmtOS = $conexao->prepare($sqlOS);
          $stmtOS->bind_param("ii", $plano['id'], $frota['id']);
          $stmtOS->execute();
          $resOS = $stmtOS->get_result();

          $ordens = [];
          while ($os = $resOS->fetch_assoc()) {
              $ordens[] = $os;
          }
        ?>

        <div class="accordion-item mb-2 border rounded shadow-sm">
          <h2 class="accordion-header" id="heading<?= $collapseId ?>">
            <button class="accordion-button <?= $index > 0 ? 'collapsed' : '' ?>" 
                    type="button"
                    data-bs-toggle="collapse"
                    data-bs-target="#<?= $collapseId ?>"
                    aria-expanded="<?= $index === 0 ? 'true' : 'false' ?>"
                    aria-controls="<?= $collapseId ?>">

              <div class="w-100 d-flex justify-content-between align-items-center flex-wrap gap-2 pe-3">
                <div>
                  <strong class="text-primary">
                    <?= htmlspecialchars($frota['prefixo_sga'] ?: 'Sem prefixo') ?>
                  </strong>

                  <span class="text-muted ms-2">
                    <?= htmlspecialchars($frota['nome_sioc'] ?: $frota['prefixo_velho'] ?: '') ?>
                  </span>
                </div>

                <div class="d-flex gap-2 flex-wrap">
                  <span class="badge bg-<?= $dispClass ?>">
                    <?= htmlspecialchars($dispTexto) ?>
                  </span>

                  <span class="badge bg-<?= $confClass ?>">
                    Conf.: <?= htmlspecialchars($confTexto) ?>
                  </span>

                  <span class="badge bg-secondary">
                    OS: <?= count($ordens) ?>
                  </span>
                </div>
              </div>

            </button>
          </h2>

          <div id="<?= $collapseId ?>" 
               class="accordion-collapse collapse <?= $index === 0 ? 'show' : '' ?>"
               aria-labelledby="heading<?= $collapseId ?>"
               data-bs-parent="#accordionFrotaPlanoMnt">

            <div class="accordion-body">

              <div class="row g-3 mb-3 frota-dados-plano">
                <div class="col-md-3">
                  <small>OM</small>
                  <strong>
                    <?= htmlspecialchars($frota['om_abreviatura'] ?: $frota['om_nome'] ?: '—') ?>
                  </strong>
                </div>

                <div class="col-md-2">
                  <small>Placa</small>
                  <strong><?= htmlspecialchars($frota['placa'] ?: '—') ?></strong>
                </div>

                <div class="col-md-2">
                  <small>Ano</small>
                  <strong><?= htmlspecialchars($frota['ano'] ?: '—') ?></strong>
                </div>

                <div class="col-md-2">
                  <small>Status</small>
                  <strong><?= htmlspecialchars($frota['status'] ?: '—') ?></strong>
                </div>

                <div class="col-md-3">
                  <small>Odômetro/Horímetro atual</small>
                  <strong class="text-primary">
                    <?= formatarNumero($frota['odometro_atual']) ?>
                  </strong>
                  <div class="text-muted small">
                    <?= $frota['data_medicao'] ? 'Medição em ' . date('d/m/Y', strtotime($frota['data_medicao'])) : 'Sem medição registrada' ?>
                  </div>
                </div>
              </div>

              <?php if (empty($ordens)): ?>
                <div class="alert alert-light border mb-0">
                  Nenhuma ordem de serviço vinculada a este plano para este ativo.
                </div>
              <?php else: ?>

                <div class="table-responsive">
                  <table class="table table-sm table-hover align-middle mb-0">
                    <thead class="table-light">
                      <tr>
                        <th>OS</th>
                        <th>Execução</th>
                        <th>Status</th>
                        <th>Tipo</th>
                        <th>Odômetro/Horímetro</th>
                        <th>Solicitante</th>
                        <th>Problema/Serviço</th>
                        <th>Valor Total</th>
                      </tr>
                    </thead>

                    <tbody>
                      <?php foreach ($ordens as $os): ?>
                        <?php
                          $statusOsClass = match ($os['status']) {
                              'Aberta' => 'warning',
                              'Encerrada' => 'success',
                              'Cancelada' => 'danger',
                              default => 'secondary'
                          };
                        ?>

                        <tr>
                          <td>
                            <strong>#<?= (int)$os['id_os'] ?></strong>
                          </td>

                          <td>
                            <?= !empty($os['data_execucao']) ? date('d/m/Y', strtotime($os['data_execucao'])) : '—' ?>
                          </td>

                          <td>
                            <span class="badge bg-<?= $statusOsClass ?>">
                              <?= htmlspecialchars($os['status'] ?: '—') ?>
                            </span>
                          </td>

                          <td>
                            <?= htmlspecialchars($os['tipo_mnt'] ?: '—') ?>
                          </td>

                          <td>
                            <?= formatarNumero($os['odometro_horimetro_execucao'] ?: $os['odometro_horimetro']) ?>
                          </td>

                          <td>
                            <?= htmlspecialchars($os['solicitante'] ?: '—') ?>
                          </td>

                          <td style="min-width: 220px;">
                            <?= htmlspecialchars($os['problema'] ?: '—') ?>
                          </td>

                          <td>
                            R$ <?= formatarNumero($os['valorTOTAL']) ?>
                          </td>
                        </tr>
                      <?php endforeach; ?>
                    </tbody>
                  </table>
                </div>

              <?php endif; ?>

            </div>
          </div>
        </div>

      <?php endforeach; ?>

    </div>
  <?php endif; ?>
</div>

<style>
  .plano-resumo-dados small,
  .frota-dados-plano small {
    display: block;
    color: #6c757d;
    font-size: .72rem;
    text-transform: uppercase;
    letter-spacing: .03em;
    margin-bottom: .15rem;
  }

  .plano-resumo-dados strong,
  .frota-dados-plano strong {
    display: block;
    font-size: .9rem;
    color: #212529;
  }

  .accordion-button:not(.collapsed) {
    background: #f8fbff;
  }

  .accordion-button:focus {
    box-shadow: none;
  }
</style>