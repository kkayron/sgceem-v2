<?php
session_start();
require_once '../../conexao/config.php';

header('Content-Type: text/html; charset=utf-8');

$id_os = isset($_GET['id_os']) ? (int)$_GET['id_os'] : 0;

if ($id_os <= 0) {
    echo "<div class='alert alert-danger mb-0'>OS inválida.</div>";
    exit;
}

function labelTipoMntPlanoEdit($tipo) {
    return match ($tipo) {
        'odometro' => 'Odômetro',
        'horimetro' => 'Horímetro',
        'tempo' => 'Tempo',
        'odometro_tempo' => 'Odômetro + Tempo',
        'horimetro_tempo' => 'Horímetro + Tempo',
        'conforme_necessidade' => 'Conforme Necessidade',
        default => '—'
    };
}

function fmtMntEdit($v) {
    if ($v === null || $v === '') return '—';
    return number_format((float)$v, 2, ',', '.');
}

function diasEntreHojeEdit($data) {
    if (!$data) return null;

    $hoje = new DateTime(date('Y-m-d'));
    $d = new DateTime($data);

    return (int)$hoje->diff($d)->format('%r%a');
}

function calcularStatusPlanoEdit($plano) {
    $tipo = $plano['tipo_controle'];
	
	if ($tipo === 'conforme_necessidade') {
    return [
        'status' => 'Conforme necessidade',
        'classe' => 'info',
        'prioridade' => 5,
        'mensagem' => 'Executar somente quando houver necessidade operacional',
        'proxima_valor' => null,
        'proxima_data' => null,
        'falta_valor' => null,
        'falta_dias' => null
    ];
}

    $usaValor = in_array($tipo, ['odometro', 'horimetro', 'odometro_tempo', 'horimetro_tempo']);
    $usaTempo = in_array($tipo, ['tempo', 'odometro_tempo', 'horimetro_tempo']);

    $status = 'Em dia';
    $classe = 'success';
    $prioridade = 3;
    $mensagens = [];

    $proximaValor = null;
    $proximaData = null;
    $faltaValor = null;
    $faltaDias = null;

    if ($usaValor) {
        $atual = is_numeric($plano['odometro_atual']) ? (float)$plano['odometro_atual'] : null;
        $base = is_numeric($plano['ultima_execucao_valor']) ? (float)$plano['ultima_execucao_valor'] : null;
        $intervalo = is_numeric($plano['intervalo_valor']) ? (float)$plano['intervalo_valor'] : null;

        if ($base !== null && $intervalo !== null) {
            $proximaValor = $base + $intervalo;
        } elseif (is_numeric($plano['valor_inicial'])) {
            $proximaValor = (float)$plano['valor_inicial'];
        }

        if ($atual === null) {
            $status = 'Sem medição';
            $classe = 'secondary';
            $prioridade = 4;
            $mensagens[] = 'Sem odômetro/horímetro atual';
        } elseif ($proximaValor === null) {
            $status = 'Sem histórico';
            $classe = 'secondary';
            $prioridade = 4;
            $mensagens[] = 'Sem base para cálculo';
        } else {
            $faltaValor = $proximaValor - $atual;
            $alertaValor = is_numeric($plano['alerta_antes_valor']) ? (float)$plano['alerta_antes_valor'] : 0;

            if ($faltaValor < 0) {
                $status = 'Vencida';
                $classe = 'danger';
                $prioridade = 1;
                $mensagens[] = 'Vencida há ' . fmtMntEdit(abs($faltaValor));
            } elseif ($faltaValor <= $alertaValor) {
                $status = 'Próxima';
                $classe = 'warning';
                $prioridade = 2;
                $mensagens[] = 'Faltam ' . fmtMntEdit($faltaValor);
            } else {
                $mensagens[] = 'Faltam ' . fmtMntEdit($faltaValor);
            }
        }
    }

    if ($usaTempo) {
        $ultimaData = $plano['ultima_execucao_data'] ?? null;
        $intervaloDias = is_numeric($plano['intervalo_dias']) ? (int)$plano['intervalo_dias'] : null;

        if (!$ultimaData || !$intervaloDias) {
            if ($status === 'Em dia') {
                $status = 'Sem histórico';
                $classe = 'secondary';
                $prioridade = 4;
            }

            $mensagens[] = 'Sem data de última execução';
        } else {
            $proximaData = date('Y-m-d', strtotime($ultimaData . " +{$intervaloDias} days"));
            $faltaDias = diasEntreHojeEdit($proximaData);
            $alertaDias = is_numeric($plano['alerta_antes_dias']) ? (int)$plano['alerta_antes_dias'] : 0;

            if ($faltaDias < 0) {
                $status = 'Vencida';
                $classe = 'danger';
                $prioridade = 1;
                $mensagens[] = 'Vencida há ' . abs($faltaDias) . ' dias';
            } elseif ($faltaDias <= $alertaDias && $status !== 'Vencida') {
                $status = 'Próxima';
                $classe = 'warning';
                $prioridade = 2;
                $mensagens[] = 'Faltam ' . $faltaDias . ' dias';
            } else {
                $mensagens[] = 'Faltam ' . $faltaDias . ' dias';
            }
        }
    }

    return [
        'status' => $status,
        'classe' => $classe,
        'prioridade' => $prioridade,
        'mensagem' => implode(' | ', array_filter($mensagens)),
        'proxima_valor' => $proximaValor,
        'proxima_data' => $proximaData,
        'falta_valor' => $faltaValor,
        'falta_dias' => $faltaDias
    ];
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
        md.nome_modelo,
        (
            SELECT cmed.odometro
            FROM controle_medicoes cmed
            WHERE cmed.viatura_id = f.id
            ORDER BY cmed.data DESC, cmed.id DESC
            LIMIT 1
        ) AS odometro_atual,
        (
            SELECT cmed.data
            FROM controle_medicoes cmed
            WHERE cmed.viatura_id = f.id
            ORDER BY cmed.data DESC, cmed.id DESC
            LIMIT 1
        ) AS data_medicao
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
    SELECT 
        mp.*,
        (
            SELECT me.odometro_horimetro_execucao
            FROM mnt_execucoes me
            WHERE me.id_plano = mp.id
              AND me.id_frota = ?
            ORDER BY me.data_execucao DESC, me.id DESC
            LIMIT 1
        ) AS ultima_execucao_valor,
        (
            SELECT me.data_execucao
            FROM mnt_execucoes me
            WHERE me.id_plano = mp.id
              AND me.id_frota = ?
            ORDER BY me.data_execucao DESC, me.id DESC
            LIMIT 1
        ) AS ultima_execucao_data,
        (
            SELECT me.id_osprincipal
            FROM mnt_execucoes me
            WHERE me.id_plano = mp.id
              AND me.id_frota = ?
            ORDER BY me.data_execucao DESC, me.id DESC
            LIMIT 1
        ) AS ultima_os
    FROM mnt_planos mp
    WHERE mp.id_marca = ?
      AND mp.id_modelo = ?
      AND mp.ativo = 1
";

$stmtPlanos = $conexao->prepare($sqlPlanos);
$stmtPlanos->bind_param(
    "iiiii",
    $os['id_frota'],
    $os['id_frota'],
    $os['id_frota'],
    $os['marca'],
    $os['modelo']
);
$stmtPlanos->execute();
$resPlanos = $stmtPlanos->get_result();

$planos = [];

while ($plano = $resPlanos->fetch_assoc()) {
    $plano['odometro_atual'] = $os['odometro_atual'];
    $plano['calc'] = calcularStatusPlanoEdit($plano);
    $planos[] = $plano;
}

usort($planos, function($a, $b) {
    $pa = $a['calc']['prioridade'] ?? 9;
    $pb = $b['calc']['prioridade'] ?? 9;

    if ($pa !== $pb) {
        return $pa <=> $pb;
    }

    $fa = $a['calc']['falta_valor'];
    $fb = $b['calc']['falta_valor'];

    if ($fa !== null && $fb !== null) {
        return $fa <=> $fb;
    }

    return strcmp($a['descricao'], $b['descricao']);
});
?>

<div class="mb-2">
  <strong><?= htmlspecialchars($os['prefixo_sga'] ?? '') ?></strong>
  <span class="text-muted">
    <?= htmlspecialchars(($os['nome_marca'] ?? '') . ' / ' . ($os['nome_modelo'] ?? '')) ?>
  </span>
</div>

<div class="alert alert-light border py-2 mb-2">
  <strong>Odômetro/Horímetro atual:</strong>
  <?= fmtMntEdit($os['odometro_atual']) ?>
  <span class="text-muted">
    <?= $os['data_medicao'] ? ' | Medição em ' . date('d/m/Y', strtotime($os['data_medicao'])) : ' | Sem medição registrada' ?>
  </span>
</div>

<?php if (empty($planos)): ?>

  <div class="alert alert-info mb-0">
    Nenhum plano de manutenção programada cadastrado para a marca/modelo deste ativo.
  </div>

<?php else: ?>

  <div class="alert alert-warning py-2">
    Marque as manutenções programadas realizadas nesta OS. As vencidas aparecem em vermelho e as próximas no topo.
  </div>

  <div class="row g-2">
    <?php foreach ($planos as $plano): ?>
      <?php
        $checked = in_array((int)$plano['id'], $marcadas, true) ? 'checked' : '';
        $calc = $plano['calc'];

        $cardClass = match ($calc['status']) {
    'Vencida' => 'mnt-os-card-edit-vencida',
    'Próxima' => 'mnt-os-card-edit-proxima',
    'Em dia' => 'mnt-os-card-edit-ok',
    'Conforme necessidade' => 'mnt-os-card-edit-necessidade',
    default => 'mnt-os-card-edit-neutro'
};

        $badgeClass = match ($calc['status']) {
    'Vencida' => 'danger',
    'Próxima' => 'warning text-dark',
    'Em dia' => 'success',
    'Conforme necessidade' => 'info text-dark',
    default => 'secondary'
};

        $ultimaExecucao = $plano['ultima_execucao_valor'] !== null ? fmtMntEdit($plano['ultima_execucao_valor']) : '—';
        $ultimaData = !empty($plano['ultima_execucao_data']) ? date('d/m/Y', strtotime($plano['ultima_execucao_data'])) : 'Sem histórico';
        $proximaValor = $calc['proxima_valor'] !== null ? fmtMntEdit($calc['proxima_valor']) : '—';
        $proximaData = !empty($calc['proxima_data']) ? date('d/m/Y', strtotime($calc['proxima_data'])) : '—';
      ?>

      <div class="col-md-6">
        <label class="mnt-os-card-edit <?= $cardClass ?>">
          <input 
            type="checkbox" 
            name="manutencoes_programadas[]" 
            value="<?= (int)$plano['id'] ?>"
            class="form-check-input me-2 mt-1"
            <?= $checked ?>
          >

          <div class="w-100">
            <div class="d-flex justify-content-between align-items-start gap-2">
              <strong><?= htmlspecialchars($plano['descricao']) ?></strong>

              <span class="badge bg-<?= $badgeClass ?>">
                <?= htmlspecialchars($calc['status']) ?>
              </span>
            </div>

            <div class="small text-muted mb-1">
              <?= htmlspecialchars(labelTipoMntPlanoEdit($plano['tipo_controle'])) ?>
              <?php if (!empty($plano['ultima_os'])): ?>
                | Última OS #<?= (int)$plano['ultima_os'] ?>
              <?php endif; ?>
            </div>

            <div class="small">
              <strong>Prazo para próxima:</strong>
              <?= htmlspecialchars($calc['mensagem'] ?: 'Em dia') ?>
            </div>

            <div class="small text-muted">
              Última execução: <strong><?= $ultimaExecucao ?></strong>
              em <strong><?= $ultimaData ?></strong>
            </div>

            <div class="small text-muted">
              Próxima valor: <strong><?= $proximaValor ?></strong>
              | Próxima data: <strong><?= $proximaData ?></strong>
            </div>
          </div>
        </label>
      </div>
    <?php endforeach; ?>
  </div>

  <style>
    .mnt-os-card-edit {
      display: flex;
      gap: .5rem;
      align-items: flex-start;
      width: 100%;
      min-height: 120px;
      padding: .85rem;
      border: 1px solid #dee2e6;
      border-radius: .85rem;
      background: #fff;
      cursor: pointer;
      transition: .2s;
    }

    .mnt-os-card-edit:hover {
      transform: translateY(-1px);
      box-shadow: 0 .35rem .85rem rgba(0,0,0,.08);
    }

    .mnt-os-card-edit-vencida {
      background: #fff5f5;
      border-color: #dc3545;
      border-left: 6px solid #dc3545;
    }

    .mnt-os-card-edit-proxima {
      background: #fff8e1;
      border-color: #ffc107;
      border-left: 6px solid #ffc107;
    }

    .mnt-os-card-edit-ok {
      background: #f8fff9;
      border-left: 6px solid #198754;
    }

    .mnt-os-card-edit-neutro {
      background: #f8f9fa;
      border-left: 6px solid #6c757d;
    }
	  .mnt-os-card-edit-necessidade {
  background: #eef8ff;
  border-color: #0dcaf0;
  border-left: 6px solid #0dcaf0;
}
  </style>

<?php endif; ?>