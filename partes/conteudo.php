<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_start();

// ID da página correspondente no banco
$pagina_id = intval(1); // <-- altere para o ID correto desta página

// Verifica se usuário está logado
if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit;
}

// Verifica se o usuário tem permissão de acessar a página
if (empty($_SESSION['permissoes'][$pagina_id]['pode_acessar'])) {
    ?>




    <!DOCTYPE html>
    <html lang="pt-BR">
        
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Acesso Negado</title>
        <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
        <style>
          #filtroDashboardForm .form-control,
  #filtroDashboardForm .form-select {
    transition: all 0.2s ease;
  }
  #filtroDashboardForm .form-control:focus,
  #filtroDashboardForm .form-select:focus {
    box-shadow: 0 0 0 0.15rem rgba(25, 135, 84, 0.25);
    border-color: #198754;
  }
        </style>
    </head>
    <body>
        <script>
            Swal.fire({
                icon: 'error',
                title: 'Acesso Negado',
                text: 'Você não possui permissão para acessar esta página.',
                showCancelButton: true,
                confirmButtonText: 'Voltar ao Painel',
                cancelButtonText: 'Ir para Login',
                allowOutsideClick: false,
                allowEscapeKey: false
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = 'index.php';
                } else if (result.isDismissed) {
                    window.location.href = 'login.php';
                }
            });
        </script>
    </body>
    </html>
    <?php
    exit;
}

// Variáveis para as outras permissões
$pode_editar    = $_SESSION['permissoes'][$pagina_id]['pode_editar'] ?? false;
$pode_deletar   = $_SESSION['permissoes'][$pagina_id]['pode_deletar'] ?? false;
$pode_cadastrar = $_SESSION['permissoes'][$pagina_id]['pode_cadastrar'] ?? false;
?>

<!-- PHP DO PRIMEIRO CARD -->
<?php
include_once('../conexao/config.php');

// ============================
// Verifica login ativo
// ============================
if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit;
}

// ============================
// Captura informações da sessão
// ============================
$id_om_usuario = $_SESSION['usuario']['batalhao'];
$nivel_usuario = $_SESSION['usuario']['nivel'];

// ============================
// Monta lista de OMs acessíveis
// ============================
$oms_visiveis = [];

if ($nivel_usuario == 1) {
    $sql_oms = "SELECT id, nome FROM organizacoes_militares ORDER BY nome";
} elseif ($nivel_usuario == 2) {
    $sql_oms = "
        SELECT om.id, om.nome
        FROM organizacoes_militares om
        JOIN organizacoes_militares_sub sub ON om.id = sub.id_om_menor
        WHERE sub.id_om_maior = $id_om_usuario
        UNION
        SELECT id, nome FROM organizacoes_militares WHERE id = $id_om_usuario
        ORDER BY nome
    ";
} else {
    $sql_oms = "SELECT id, nome FROM organizacoes_militares WHERE id = $id_om_usuario";
}

$result_oms = $conexao->query($sql_oms);
while ($row = $result_oms->fetch_assoc()) {
    $oms_visiveis[$row['id']] = $row['nome'];
}

// ============================
// Filtro de batalhão selecionado
// ============================
$om_selecionada = $_GET['batalhao'] ?? 'todos';
$filtro_om = ($om_selecionada === 'todos') ? 'todos' : intval($om_selecionada);

// ============================
// CONSULTA ORDENS DE SERVIÇO
// ============================
if ($filtro_om === 'todos') {
    $ids = implode(',', array_keys($oms_visiveis));
    $sql_os = "SELECT status FROM os_principal WHERE batalhao IN ($ids)";
} else {
    $sql_os = "SELECT status FROM os_principal WHERE batalhao = $filtro_om";
}

$result_os = $conexao->query($sql_os);

$total_os = $result_os->num_rows;
$em_andamento = $concluidas = $aguardando = 0;

if ($total_os > 0) {
    while ($os = $result_os->fetch_assoc()) {
        $status = $os['status'];
        if (in_array($status, ['Em andamento', 'Aguardando Peças', 'Aguardando Suprimento', 'Aguardando Descarga'])) {
            $em_andamento++;
        } elseif (in_array($status, ['Concluída', 'Eqp/Vtr descarregado'])) {
            $concluidas++;
        } else {
            $aguardando++;
        }
    }
}

// ============================
// CONSULTA FICHAS DE DESLOCAMENTO
// ============================
if ($filtro_om === 'todos') {
    $ids = implode(',', array_keys($oms_visiveis));
    $sql_fichas = "SELECT status FROM sta_fichas WHERE batalhao IN ($ids)";
} else {
    $sql_fichas = "SELECT status FROM sta_fichas WHERE batalhao = $filtro_om";
}

$result_fichas = $conexao->query($sql_fichas);

$total_fichas = $result_fichas->num_rows;
$abertas = $encerradas = 0;

if ($total_fichas > 0) {
    while ($ficha = $result_fichas->fetch_assoc()) {
        if ($ficha['status'] === 'Aberta') $abertas++;
        if ($ficha['status'] === 'Encerrada') $encerradas++;
    }
}

// ============================
// Função de cálculo percentual
// ============================
function pct($parte, $total) {
    return $total > 0 ? number_format(($parte / $total) * 100, 2, ',', '.') : '0,00';
}


// ============================
// Monta filtro de OM
// ============================
if ($filtro_om === 'todos') {
    $ids_array = array_keys($oms_visiveis);
    $where_om = !empty($ids_array) ? "batalhao IN (" . implode(',', $ids_array) . ")" : "1=0";
} else {
    $where_om = "batalhao = $filtro_om";
}

// ============================
// Consulta única para totais de frota
// ============================
$sql_totais = "
    SELECT
        -- Viaturas
        COUNT(CASE WHEN tipo = 'Vtr' THEN 1 END) AS total_vtr,
        SUM(CASE WHEN tipo = 'Vtr' AND confiabilidade = 'Confiável' THEN 1 ELSE 0 END) AS confiaveis_vtr,
        SUM(CASE WHEN tipo = 'Vtr' AND confiabilidade = 'Não confiável' THEN 1 ELSE 0 END) AS nao_confiaveis_vtr,
        SUM(CASE WHEN tipo = 'Vtr' AND disponibilidade = 'Disponível' THEN 1 ELSE 0 END) AS disponiveis_vtr,
        SUM(CASE WHEN tipo = 'Vtr' AND disponibilidade != 'Disponível' THEN 1 ELSE 0 END) AS indisp_vtr,
        SUM(CASE WHEN tipo = 'Vtr' AND confiabilidade = 'Emprestado' THEN 1 ELSE 0 END) AS emprestadas_vtr,
        SUM(CASE WHEN tipo = 'Vtr' AND confiabilidade = 'Em processo de descarga' THEN 1 ELSE 0 END) AS em_descarga_vtr,
        SUM(CASE WHEN tipo = 'Vtr' AND confiabilidade = 'Desfeita (Leiloada ou recolhida)' THEN 1 ELSE 0 END) AS desfeitas_vtr,
        SUM(CASE WHEN tipo = 'Vtr' AND confiabilidade = 'Descarregado' THEN 1 ELSE 0 END) AS descarregadas_vtr,

        -- Equipamentos
        COUNT(CASE WHEN tipo = 'Eqp' THEN 1 END) AS total_eqp,
        SUM(CASE WHEN tipo = 'Eqp' AND confiabilidade = 'Confiável' THEN 1 ELSE 0 END) AS confiaveis_eqp,
        SUM(CASE WHEN tipo = 'Eqp' AND confiabilidade = 'Não confiável' THEN 1 ELSE 0 END) AS nao_confiaveis_eqp,
        SUM(CASE WHEN tipo = 'Eqp' AND disponibilidade = 'Disponível' THEN 1 ELSE 0 END) AS disponiveis_eqp,
        SUM(CASE WHEN tipo = 'Eqp' AND disponibilidade != 'Disponível' THEN 1 ELSE 0 END) AS indisp_eqp,
        SUM(CASE WHEN tipo = 'Eqp' AND confiabilidade = 'Emprestado' THEN 1 ELSE 0 END) AS emprestadas_eqp,
        SUM(CASE WHEN tipo = 'Eqp' AND confiabilidade = 'Em processo de descarga' THEN 1 ELSE 0 END) AS em_descarga_eqp,
        SUM(CASE WHEN tipo = 'Eqp' AND confiabilidade = 'Desfeita (Leiloada ou recolhida)' THEN 1 ELSE 0 END) AS desfeitas_eqp,
        SUM(CASE WHEN tipo = 'Eqp' AND confiabilidade = 'Descarregado' THEN 1 ELSE 0 END) AS descarregadas_eqp
    FROM frota
    WHERE $where_om
";

$result_totais = $conexao->query($sql_totais);
$totais = $result_totais->fetch_assoc();

// ============================
// Consulta para destinos (viaturas e equipamentos)
// ============================
$destinos_viaturas = [];
$destinos_equipamentos = [];

// Query SQL com agrupamento por tipo e destino
$sql_destinos = "
    SELECT 
        UPPER(TRIM(tipo)) AS tipo_normalizado,
        destino,
        COUNT(*) AS total
    FROM frota
    WHERE $where_om
    GROUP BY tipo_normalizado, destino
";
$result_destinos = $conexao->query($sql_destinos);

if ($result_destinos && $result_destinos->num_rows > 0) {
    while ($row = $result_destinos->fetch_assoc()) {
        $dest = trim($row['destino']) ?: 'Sem destino';
        $tipo = $row['tipo_normalizado']; // já normalizado para maiúsculas e sem espaços

        // Contabiliza viaturas e equipamentos
        if ($tipo === 'VTR') {
            $destinos_viaturas[$dest] = $row['total'];
        } elseif ($tipo === 'EQP' || $tipo === 'EQUIPAMENTO') {
            $destinos_equipamentos[$dest] = $row['total'];
        }
    }
}


// ============================
// Inicializa variáveis derivadas
// ============================
$total_viaturas = $totais['total_vtr'];
$total_equipamentos = $totais['total_eqp'];
$total_ativos = $total_viaturas + $total_equipamentos;

$desfeitas = $totais['desfeitas_vtr'] + $totais['desfeitas_eqp'];
$descarregadas = $totais['descarregadas_vtr'] + $totais['descarregadas_eqp'];
$em_descarga = $totais['em_descarga_vtr'] + $totais['em_descarga_eqp'];
$emprestadas = $totais['emprestadas_vtr'] + $totais['emprestadas_eqp'];

$confiaveis_vtr = $totais['confiaveis_vtr'];
$confiaveis_eqp = $totais['confiaveis_eqp'];
$nao_confiaveis_vtr = $totais['nao_confiaveis_vtr'];
$nao_confiaveis_eqp = $totais['nao_confiaveis_eqp'];

$disponiveis_vtr = $totais['disponiveis_vtr'];
$indisp_vtr = $totais['indisp_vtr'];
$disponiveis_eqp = $totais['disponiveis_eqp'];
$indisp_eqp = $totais['indisp_eqp'];

$total_vtr_considered = $confiaveis_vtr + $nao_confiaveis_vtr + $emprestadas + $em_descarga;
$total_eqp_considered = $confiaveis_eqp + $nao_confiaveis_eqp + $emprestadas + $em_descarga;

// ============================
// Função de porcentagem
// ============================
function pct_val($parte, $total) {
    return $total > 0 ? number_format(($parte / $total) * 100, 2, ',', '.') : '0,00';
}

// ============================
// Inicializa índices de loop para destinos
// ============================
$loop_index = 0;
$loop_index2 = 0;



include_once('../includes/preventiva/calculo_manutencao.php');

// ============================
// 1. MONTAGEM DO FILTRO DE OM
// ============================
if ($filtro_om === 'todos') {
    $ids_array = array_keys($oms_visiveis);
    $where_om = !empty($ids_array) ? "batalhao IN (" . implode(',', $ids_array) . ")" : "1=0";
} else {
    $where_om = "batalhao = $filtro_om";
}

// ============================
// 2. BUSCA TODAS AS VIATURAS E EQUIPAMENTOS
// ============================
$sql_frota = "
    SELECT id, tipo, prefixo_sga, nome_sioc, status_odometro
    FROM frota
    WHERE $where_om AND (tipo = 'Vtr' OR tipo = 'Eqp')
";
$result_frota = $conexao->query($sql_frota);

// ============================
// 3. CONTADORES DE STATUS
// ============================
$contagem = [
    'Vtr' => [
        'Manutenção em dia' => 0,
        'Muito próxima' => 0,
        'Próxima' => 0,
        'Manutenção vencida' => 0,
        'Em manutenção' => 0,
        'Sem dados' => 0,
        'Total' => 0
    ],
    'Eqp' => [
        'Manutenção em dia' => 0,
        'Muito próxima' => 0,
        'Próxima' => 0,
        'Manutenção vencida' => 0,
        'Em manutenção' => 0,
        'Sem dados' => 0,
        'Total' => 0
    ]
];

// ============================
// 4. PROCESSA CADA ATIVO
// ============================
if ($result_frota && $result_frota->num_rows > 0) {
    while ($vtr = $result_frota->fetch_assoc()) {
        $tipoBruto = strtolower(trim($vtr['tipo']));

if (in_array($tipoBruto, ['vtr', 'viatura', 'veiculo'])) {
    $tipo = 'Vtr';
} else {
    $tipo = 'Eqp';
}

        $resultado = calcularManutencaoPreventiva($conexao, $vtr);
        $status = $resultado['status'];

        // Normaliza status
        if (!isset($contagem[$tipo][$status])) {
            $status = 'Sem dados';
        }

        $contagem[$tipo][$status]++;
        $contagem[$tipo]['Total']++;
    }
}

// ============================
// 5. FUNÇÃO DE IMPRESSÃO PADRÃO
// ============================
function linha_status($nome, $valor, $classe = '') {
    return "
        <div class='d-flex px-3 py-2 border-bottom $classe'>
            <div class='w-75 fw-semibold'>$nome</div>
            <div class='w-25 text-end'>$valor</div>
        </div>";
}


// ============================
// 1. FILTROS
// ============================
$filtro_om        = $_GET['batalhao'] ?? 'todos';
$filtro_ano       = $_GET['ano'] ?? '';
$filtro_categoria = $_GET['categoria'] ?? '';
$filtro_resto     = $_GET['resto'] ?? '';

$filtros = [];
$params  = [];
$tipos   = '';

// OM
if ($filtro_om !== 'todos') {
    $filtros[] = "r.batalhao = ?";
    $params[]  = (int)$filtro_om;
    $tipos    .= 'i';
} else {
    $ids_array = array_keys($oms_visiveis ?? []);
    if (!empty($ids_array)) {
        $filtros[] = "r.batalhao IN (" . implode(',', array_map('intval', $ids_array)) . ")";
    }
}

// Ano
if ($filtro_ano !== '') {
    $filtros[] = "e.ano = ?";
    $params[]  = (int)$filtro_ano;
    $tipos    .= 'i';
}

// Categoria
if ($filtro_categoria !== '') {
    $filtros[] = "e.categoria = ?";
    $params[]  = $filtro_categoria;
    $tipos    .= 's';
}

// Restos a pagar
if ($filtro_resto === 'Sim') {
    $filtros[] = "e.resto_pagar = 'Sim'";
} elseif ($filtro_resto === 'Não') {
    $filtros[] = "e.resto_pagar = 'Não'";
}

$where_final = $filtros ? 'WHERE ' . implode(' AND ', $filtros) : '';


// ============================
// 2. BUSCAR EMPENHOS FILTRADOS
// ============================
$sql = "
    SELECT 
        e.id,
        e.id_requisicao,
        e.nmr_empenho
    FROM fin_empenhos e
    JOIN fin_requisicao r ON r.id = e.id_requisicao
    $where_final
";

$stmt = $conexao->prepare($sql);
if ($params) {
    $stmt->bind_param($tipos, ...$params);
}
$stmt->execute();
$empenhos = $stmt->get_result();


// ============================
// 2.1 PRÉ-CARREGAR VALORES POR STATUS (SEM JOIN PESADO)
// ============================
$mapaStatus = [];

/* Coleta IDs dos empenhos */
$idsEmpenhos = [];
$empenhos->data_seek(0);
while ($row = $empenhos->fetch_assoc()) {
    $idsEmpenhos[] = (int)$row['id'];
}
$empenhos->data_seek(0);

if (empty($idsEmpenhos)) {
    return;
}

/* 1️⃣ Buscar ORDENS por empenho (query LEVE) */
$listaIds = implode(',', $idsEmpenhos);

$sqlOrdens = "
    SELECT id, id_empenho, LOWER(status) AS status
    FROM fin_ordemforn
    WHERE id_empenho IN ($listaIds)
";

$resOrdens = $conexao->query($sqlOrdens);

while ($ordem = $resOrdens->fetch_assoc()) {

    $idOrdem   = (int)$ordem['id'];
    $idEmp    = (int)$ordem['id_empenho'];
    $status   = $ordem['status'];

    /* Inicializa estrutura */
    if (!isset($mapaStatus[$idEmp])) {
        $mapaStatus[$idEmp] = [];
    }
    if (!isset($mapaStatus[$idEmp][$status])) {
        $mapaStatus[$idEmp][$status] = 0;
    }

    /* 2️⃣ Buscar pedidos da ordem (LEVE) */
    $stmtPedidos = $conexao->prepare("
        SELECT id_pedido
        FROM fin_ordemforn_pedidos
        WHERE id_ordemforn = ?
    ");
    $stmtPedidos->bind_param("i", $idOrdem);
    $stmtPedidos->execute();
    $resPedidos = $stmtPedidos->get_result();

    while ($pedido = $resPedidos->fetch_assoc()) {

        /* 3️⃣ Somar itens do pedido (LEVE, SEM JOIN) */
        $stmtItens = $conexao->prepare("
            SELECT SUM(valor_total) AS total
            FROM fin_pedidos_forn_itens
            WHERE id_principal = ?
        ");
        $stmtItens->bind_param("i", $pedido['id_pedido']);
        $stmtItens->execute();
        $rowTotal = $stmtItens->get_result()->fetch_assoc();
        $stmtItens->close();

        $mapaStatus[$idEmp][$status] += (float)($rowTotal['total'] ?? 0);
    }

    $stmtPedidos->close();
}


// ============================
// 3. INICIALIZA SOMATÓRIOS
// ============================
$totalRegistros = 0;

$soma_total_empenhado = 0;
$soma_total_utilizado = 0;

$soma_nao_entregue = 0;
$soma_entregue     = 0;
$soma_capeador     = 0;
$soma_liquidado    = 0;

$soma_saldo_real   = 0;
$soma_saldo_siafi  = 0;
$soma_diferenca    = 0;


// ============================
// 4. LOOP – LÓGICA VALIDADA
// ============================
while ($empenho = $empenhos->fetch_assoc()) {

    $totalRegistros++;

    /* ===== TOTAL EMPENHADO ===== */
    $stmtValor = $conexao->prepare("
        SELECT valor_empenhado 
        FROM fin_requisicao 
        WHERE id = ?
        LIMIT 1
    ");
    $stmtValor->bind_param("i", $empenho['id_requisicao']);
    $stmtValor->execute();
    $dadosValor = $stmtValor->get_result()->fetch_assoc();
    $stmtValor->close();

    $valorEmpenhado = floatval($dadosValor['valor_empenhado'] ?? 0);
    $soma_total_empenhado += $valorEmpenhado;


    /* ===== VALORES POR STATUS (CACHE) ===== */
    $valoresStatus = [
        'Não entregue' => 0,
        'Entregue'     => 0,
        'Capeador'     => 0,
        'Liquidado'    => 0
    ];

    if (isset($mapaStatus[$empenho['id']])) {
        foreach ($mapaStatus[$empenho['id']] as $status => $valor) {
            if ($status === 'não entregue') {
                $valoresStatus['Não entregue'] += $valor;
            } elseif ($status === 'entregue') {
                $valoresStatus['Entregue'] += $valor;
            } elseif ($status === 'capeador') {
                $valoresStatus['Capeador'] += $valor;
            } elseif ($status === 'liquidado' || $status === 'pago') {
                $valoresStatus['Liquidado'] += $valor;
            }
        }
    }

    $soma_nao_entregue += $valoresStatus['Não entregue'];
    $soma_entregue     += $valoresStatus['Entregue'];
    $soma_capeador     += $valoresStatus['Capeador'];
    $soma_liquidado    += $valoresStatus['Liquidado'];

    $utilizado = array_sum($valoresStatus);
    $soma_total_utilizado += $utilizado;


    /* ===== SALDO REAL ===== */
    $saldoReal = $valorEmpenhado - $utilizado;
    $soma_saldo_real += $saldoReal;


    /* ===== SIAFI ===== */
    $saldoSiafi = null;

    $stmtSaldo = $conexao->prepare("
        SELECT saldo_empenho 
        FROM fin_siafi_corrente 
        WHERE nmr_empenho = ?
    ");
    $stmtSaldo->bind_param("s", $empenho['nmr_empenho']);
    $stmtSaldo->execute();
    $linhaSaldo = $stmtSaldo->get_result()->fetch_assoc();
    $stmtSaldo->close();

    if (!$linhaSaldo) {
        $stmtSaldo = $conexao->prepare("
            SELECT saldo_empenho 
            FROM fin_siafi_restopagar 
            WHERE nmr_empenho = ?
        ");
        $stmtSaldo->bind_param("s", $empenho['nmr_empenho']);
        $stmtSaldo->execute();
        $linhaSaldo = $stmtSaldo->get_result()->fetch_assoc();
        $stmtSaldo->close();
    }

    if ($linhaSaldo) {
        $saldoSiafi = floatval(str_replace(',', '.', str_replace('.', '', $linhaSaldo['saldo_empenho'])));
        $soma_saldo_siafi += $saldoSiafi;
    }

    /* ===== DIFERENÇA ===== */
    $soma_diferenca += ($saldoSiafi !== null) ? ($saldoSiafi - $saldoReal) : 0;
}


// ============================
// 5. RESULTADOS FINAIS
// ============================
function fmt($v) {
    return 'R$ ' . number_format($v ?? 0, 2, ',', '.');
}

$totalEmpenhado_fmt = fmt($soma_total_empenhado);
$saldoReal_fmt      = fmt($soma_saldo_real);
$siafiTotal_fmt     = fmt($soma_saldo_siafi);
$diferenca_fmt      = fmt($soma_diferenca);

// Percentuais
if ($soma_total_empenhado > 0) {
    $porc_liquidado = ($soma_liquidado / $soma_total_empenhado) * 100;
    $porc_req       = ($soma_nao_entregue / $soma_total_empenhado) * 100;
    $porc_entregue  = ($soma_entregue / $soma_total_empenhado) * 100;
    $porc_capeador  = ($soma_capeador / $soma_total_empenhado) * 100;
} else {
    $porc_liquidado = $porc_req = $porc_entregue = $porc_capeador = 0;
}

?>







<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
<div class="container">
          <div class="page-inner">
            <div
              class="d-flex align-items-left align-items-md-center flex-column flex-md-row pt-2 pb-4"
            >
              <div>
                <h3 class="fw-bold mb-3">Página Inicial</h3>
                <h6 class="op-7 mb-2">Sistema de Gerenciamento da Cia E Eqp Mnt</h6>
              </div>
            </div>
              
              <div class="row">
              
         
<!-- ============================ -->
<!-- FILTRO DE OM + Ano + Categoria -->
<!-- ============================ -->
<div class="mb-4">
  <form id="filtroDashboardForm" method="GET" class="d-flex align-items-center gap-2 flex-wrap">

    <!-- Filtro Batalhão -->
    <label for="batalhao" class="fw-bold me-2">Batalhão:</label>
    <select name="batalhao" id="batalhao" class="form-select w-auto">
      <option value="todos" <?= ($filtro_om === 'todos') ? 'selected' : '' ?>>Todos</option>
      <?php foreach ($oms_visiveis as $id => $nome): ?>
        <option value="<?= $id ?>" <?= ($id == $filtro_om) ? 'selected' : '' ?>>
          <?= htmlspecialchars($nome) ?>
        </option>
      <?php endforeach; ?>
    </select>

    <!-- Filtro Ano -->
    <label for="ano" class="fw-bold me-2">Ano:</label>
    <select name="ano" id="ano" class="form-select w-auto">
      <option value="">Todos anos</option>
      <?php
      $ano_atual = (int)date('Y');
      for ($i = 0; $i < 10; $i++):
          $ano = $ano_atual - $i;
      ?>
        <option value="<?= $ano ?>" <?= ($filtro_ano == $ano) ? 'selected' : '' ?>>
          <?= $ano ?>
        </option>
      <?php endfor; ?>
    </select>

    
  </form>
</div>


<!-- ============================ -->
<!-- CARDS PRINCIPAIS -->
<!-- ============================ -->
<div class="row">
  <!-- Card: CONTROLE ORDENS DE SERVIÇO -->
  <div class="col-md-6 mb-4">
    <div class="card card-stats card-round overflow-hidden shadow-sm">
      <div class="d-flex" style="min-height: 150px;">
        <div class="d-flex justify-content-center align-items-center bg-primary" style="width: 90px;">
          <i class="fas fa-tools text-white" style="font-size: 2.5rem;"></i>
        </div>
        <div class="flex-grow-1 p-3">
          <p class="card-category mb-1 text-muted">Controle de Ordens de Serviço</p>
          <div class="d-flex justify-content-between">
            <span class="fw-bold">Total de Ordens:</span> <span><?= $total_os ?></span>
          </div>
          <div class="d-flex justify-content-between">
            <span class="fw-bold text-warning">Em Andamento / Aguardando:</span> 
            <span><?= $em_andamento ?> (<?= pct($em_andamento, $total_os) ?>%)</span>
          </div>
          <div class="d-flex justify-content-between">
            <span class="fw-bold text-success">Concluídas:</span> 
            <span><?= $concluidas ?> (<?= pct($concluidas, $total_os) ?>%)</span>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Card: FICHAS DE VIATURA -->
  <div class="col-md-6 mb-4">
    <div class="card card-stats card-round overflow-hidden shadow-sm">
      <div class="d-flex" style="min-height: 150px;">
        <div class="d-flex justify-content-center align-items-center bg-primary" style="width: 90px;">
          <i class="fas fa-route text-white" style="font-size: 2.5rem;"></i>
        </div>
        <div class="flex-grow-1 p-3">
          <p class="card-category mb-1 text-muted">Controle de Fichas de Viatura</p>
          <div class="d-flex justify-content-between">
            <span class="fw-bold">Total de Fichas:</span> <span><?= $total_fichas ?></span>
          </div>
          <div class="d-flex justify-content-between">
            <span class="fw-bold text-warning">Em Deslocamento:</span> 
            <span><?= $abertas ?> (<?= pct($abertas, $total_fichas) ?>%)</span>
          </div>
          <div class="d-flex justify-content-between">
            <span class="fw-bold text-success">Encerradas:</span> 
            <span><?= $encerradas ?> (<?= pct($encerradas, $total_fichas) ?>%)</span>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
                  
                  
         <?php
// Inicializa índices para alternar cores das linhas
$loop_index = 0;
$loop_index2 = 0;
?>

<div class="card shadow-sm border-0 rounded-3 mb-4">
  <div class="card-header bg-warning text-white rounded-top-3 d-flex align-items-center">
    <i class="bi bi-truck-front-fill fs-4 me-2"></i>
    <h5 class="mb-0 fw-semibold">Controle da Frota</h5>
  </div>
  <div class="card-body bg-light">

    <!-- Ativos Cadastrados -->
    <div class="mb-4">
      <h6 class="text-secondary fw-bold mb-3">
        <i class="bi bi-clipboard-data me-2 text-dark"></i>Ativos Cadastrados no Sistema
      </h6>

      <div class="row g-3 text-center">
        <div class="col-6 col-md-3">
          <div class="bg-white rounded shadow-sm p-3 h-100">
            <i class="bi bi-truck fs-3 text-warning"></i>
            <div class="fw-bold mt-2">Viaturas</div>
            <div class="fs-5"><?= $total_viaturas ?></div>
          </div>
        </div>
        <div class="col-6 col-md-3">
          <div class="bg-white rounded shadow-sm p-3 h-100">
            <i class="bi bi-cpu-fill fs-3 text-warning"></i>
            <div class="fw-bold mt-2">Equipamentos</div>
            <div class="fs-5"><?= $total_equipamentos ?></div>
          </div>
        </div>
        <div class="col-6 col-md-3">
          <div class="bg-white rounded shadow-sm p-3 h-100">
            <i class="bi bi-stack fs-3 text-warning"></i>
            <div class="fw-bold mt-2">Total</div>
            <div class="fs-5"><?= $total_ativos ?></div>
          </div>
        </div>
      </div>

      <div class="row g-3 text-center mt-3">
        <div class="col-6 col-md-3">
          <div class="bg-white rounded shadow-sm p-3 h-100">
            <i class="bi bi-x-octagon-fill fs-3 text-danger"></i>
            <div class="fw-bold mt-2">Desfazimentos</div>
            <div class="fs-5"><?= $desfeitas ?></div>
          </div>
        </div>
        <div class="col-6 col-md-3">
          <div class="bg-white rounded shadow-sm p-3 h-100">
            <i class="bi bi-box-arrow-down fs-3 text-secondary"></i>
            <div class="fw-bold mt-2">Descarregados</div>
            <div class="fs-5"><?= $descarregadas ?></div>
          </div>
        </div>
        <div class="col-6 col-md-3">
          <div class="bg-white rounded shadow-sm p-3 h-100">
            <i class="bi bi-hourglass-split fs-3 text-primary"></i>
            <div class="fw-bold mt-2">Em Descarga</div>
            <div class="fs-5"><?= $em_descarga ?></div>
          </div>
        </div>
        <div class="col-6 col-md-3">
          <div class="bg-white rounded shadow-sm p-3 h-100">
            <i class="bi bi-arrow-left-right fs-3 text-success"></i>
            <div class="fw-bold mt-2">Emprestados</div>
            <div class="fs-5"><?= $emprestadas ?></div>
          </div>
        </div>
      </div>
    </div>

    <!-- Frota Atual -->
    <div>
      <h6 class="text-secondary fw-bold mb-3">
        <i class="bi bi-speedometer2 me-2 text-dark"></i>Frota Atual
      </h6>
      <div class="row g-3 text-center">
        <div class="col-6 col-md-4">
          <div class="bg-white rounded shadow-sm p-3 h-100">
            <i class="bi bi-check-circle-fill fs-3 text-success"></i>
            <div class="fw-bold mt-2">Confiáveis</div>
            <div class="fs-5">Viaturas: <?= $confiaveis_vtr ?></div>
            <div class="fs-6">Equipamentos: <?= $confiaveis_eqp ?></div>
            <div class="fw-bold">Total: <?= $confiaveis_vtr + $confiaveis_eqp ?></div>
          </div>
        </div>
        <div class="col-6 col-md-4">
          <div class="bg-white rounded shadow-sm p-3 h-100">
            <i class="bi bi-exclamation-triangle-fill fs-3 text-danger"></i>
            <div class="fw-bold mt-2">Não Confiáveis</div>
            <div class="fs-5">Viaturas: <?= $nao_confiaveis_vtr ?></div>
            <div class="fs-6">Equipamentos: <?= $nao_confiaveis_eqp ?></div>
            <div class="fw-bold">Total: <?= $nao_confiaveis_vtr + $nao_confiaveis_eqp ?></div>
          </div>
        </div>
        <div class="col-12 col-md-4">
          <div class="bg-white rounded shadow-sm p-3 h-100">
            <i class="bi bi-graph-up-arrow fs-3 text-primary"></i>
            <div class="fw-bold mt-2">Total da Frota Atual</div>
            <div class="fs-5">Viaturas: <?= $confiaveis_vtr + $nao_confiaveis_vtr ?></div>
            <div class="fs-6">Equipamentos: <?= $confiaveis_eqp + $nao_confiaveis_eqp ?></div>
            <div class="fw-bold">Total: <?= ($confiaveis_vtr + $nao_confiaveis_vtr) + ($confiaveis_eqp + $nao_confiaveis_eqp) ?></div>
          </div>
        </div>
      </div>
    </div>

  </div>
</div>

<!-- DISPONIBILIDADE -->
<div class="card shadow-sm mb-4" style="background-color: rgba(240, 240, 240, 0.8); border: none;">
  <div class="card-body">
    <h5 class="card-title mb-4 fw-bold text-dark">
      <i class="fas fa-chart-pie me-2 text-primary"></i>Disponibilidade da Frota atual
    </h5>
    <div class="row">
      <div class="col-md-6 mb-4">
        <div class="card card-stats card-round overflow-hidden shadow-sm">
          <div class="d-flex" style="min-height: 150px;">
            <div class="d-flex justify-content-center align-items-center" style="background-color: #007bff; width: 90px;">
              <i class="fas fa-car-side text-white" style="font-size: 2.5rem;"></i>
            </div>
            <div class="flex-grow-1 p-3">
              <p class="card-category mb-1 text-muted">Disponibilidade Viaturas / Frota</p>
              <div class="d-flex justify-content-between">
                <span class="fw-bold">Total Vtr:</span> <span><?= $total_viaturas ?></span>
              </div>
              <div class="d-flex justify-content-between">
                <span class="fw-bold text-success">Disponíveis:</span> 
                <span><?= $disponiveis_vtr ?> (<?= pct_val($disponiveis_vtr, $total_vtr_considered) ?>%)</span>
              </div>
              <div class="d-flex justify-content-between">
                <span class="fw-bold text-danger">Indisponíveis:</span> 
                <span><?= $indisp_vtr ?> (<?= pct_val($indisp_vtr, $total_vtr_considered) ?>%)</span>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="col-md-6 mb-4">
        <div class="card card-stats card-round overflow-hidden shadow-sm">
          <div class="d-flex" style="min-height: 150px;">
            <div class="d-flex justify-content-center align-items-center" style="background-color: #007bff; width: 90px;">
              <i class="fas fa-tractor text-white" style="font-size: 2.5rem;"></i>
            </div>
            <div class="flex-grow-1 p-3">
              <p class="card-category mb-1 text-muted">Disponibilidade Equipamentos</p>
              <div class="d-flex justify-content-between">
                <span class="fw-bold">Total:</span> <span><?= $total_equipamentos ?></span>
              </div>
              <div class="d-flex justify-content-between">
                <span class="fw-bold text-success">Disponíveis:</span> 
                <span><?= $disponiveis_eqp ?> (<?= pct_val($disponiveis_eqp, $total_eqp_considered) ?>%)</span>
              </div>
              <div class="d-flex justify-content-between">
                <span class="fw-bold text-danger">Indisponíveis:</span> 
                <span><?= $indisp_eqp ?> (<?= pct_val($indisp_eqp, $total_eqp_considered) ?>%)</span>
              </div>
            </div>
          </div>
        </div>
      </div>

    </div>
  </div>
</div>

<!-- DESTINOS -->
<div class="card shadow-sm mb-4" style="background-color: rgba(240, 240, 240, 0.8); border: none;">
  <div class="card-body">
    <h5 class="card-title mb-4 fw-bold text-dark">
      <i class="fas fa-chart-pie me-2 text-primary"></i>Vtr/Eqp por destino
    </h5>
    <div class="row">
      <div class="col-md-6 mb-4">
        <div class="card shadow-sm overflow-hidden">
          <div class="d-flex">
            <div class="d-flex justify-content-center align-items-center" style="background-color: #007bff; width: 90px;">
              <i class="fas fa-car-side text-white" style="font-size: 2rem;"></i>
            </div>
            <div class="flex-grow-1 p-3">
              <h6 class="fw-bold text-center border-bottom pb-2 mb-3">QUANT VIATURAS POR DESTINO</h6>
              <div class="d-flex fw-bold bg-secondary text-white px-3 py-2">
                <div class="w-50">LOCAL</div>
                <div class="w-50 text-end">TOTAL</div>
              </div>
              <?php foreach ($destinos_viaturas as $dest => $qtd): ?>
                <div class="d-flex px-3 py-2 border-bottom <?= ($loop_index++ % 2 == 0) ? '' : 'bg-light' ?>">
                  <div class="w-50 fw-semibold"><?= htmlspecialchars($dest) ?></div>
                  <div class="w-50 text-end"><?= $qtd ?></div>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
        </div>
      </div>

      <div class="col-md-6 mb-4">
        <div class="card shadow-sm overflow-hidden">
          <div class="d-flex">
            <div class="d-flex justify-content-center align-items-center" style="background-color: #000; width: 90px;">
              <i class="fas fa-tractor text-white" style="font-size: 2rem;"></i>
            </div>
            <div class="flex-grow-1 p-3">
              <h6 class="fw-bold text-center border-bottom pb-2 mb-3">QUANT EQUIPAMENTOS POR DESTINO</h6>
              <div class="d-flex fw-bold bg-secondary text-white px-3 py-2">
                <div class="w-50">LOCAL</div>
                <div class="w-50 text-end">TOTAL</div>
              </div>
              <?php foreach ($destinos_equipamentos as $dest => $qtd): ?>
                <div class="d-flex px-3 py-2 border-bottom <?= ($loop_index2++ % 2 == 0) ? '' : 'bg-light' ?>">
                  <div class="w-50 fw-semibold"><?= htmlspecialchars($dest) ?></div>
                  <div class="w-50 text-end"><?= $qtd ?></div>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
        </div>
      </div>

    </div>
  </div>
</div>
                  
  <!-- ============================
     6. HTML DA LISTAGEM
============================ -->
<div class="card shadow-sm mb-4" style="background-color: rgba(240, 240, 240, 0.8); border: none;">
    <div class="card-body">

        <h5 class="card-title mb-4 fw-bold text-dark">
            <i class="fas fa-tools me-2 text-danger"></i>Manutenção Preventiva
        </h5>

        <div class="row">

            <!-- ============================
                 VIATURAS
            ============================= -->
            <div class="col-md-6 mb-4">
                <div class="card shadow-sm overflow-hidden">
                    <div class="d-flex">
                        <div class="d-flex justify-content-center align-items-center"
                             style="background-color: #007bff; width: 90px;">
                            <i class="fas fa-car-side text-white" style="font-size: 2rem;"></i>
                        </div>
                        <div class="flex-grow-1 p-3">
                            <h6 class="fw-bold text-center border-bottom pb-2 mb-3">MANUTENÇÃO VIATURAS</h6>
                            <div class="d-flex fw-bold bg-secondary text-white px-3 py-2">
                                <div class="w-75">Situação</div>
                                <div class="w-25 text-end">Qtd</div>
                            </div>

                            <?= linha_status('<span class="text-success">Manutenção em Dia</span>', $contagem['Vtr']['Manutenção em dia']); ?>
                            <?= linha_status('<span class="text-warning">Manutenção Muito Próxima</span>', $contagem['Vtr']['Muito próxima'], 'bg-light'); ?>
                            <?= linha_status('<span class="text-primary">Agendada/Em manutenção</span>', $contagem['Vtr']['Em manutenção']); ?>
                            <?= linha_status('<span class="text-dark">Sem dados</span>', $contagem['Vtr']['Sem dados']); ?>
                            <?= linha_status('<span class="text-danger">Manutenção Vencida</span>', $contagem['Vtr']['Manutenção vencida'], 'bg-light'); ?>
                            <?= linha_status('<b>Total</b>', '<b>'.$contagem['Vtr']['Total'].'</b>', 'border-top mt-2'); ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ============================
                 EQUIPAMENTOS
            ============================= -->
            <div class="col-md-6 mb-4">
                <div class="card shadow-sm overflow-hidden">
                    <div class="d-flex">
                        <div class="d-flex justify-content-center align-items-center"
                             style="background-color: #000; width: 90px;">
                            <i class="fas fa-tractor text-white" style="font-size: 2rem;"></i>
                        </div>
                        <div class="flex-grow-1 p-3">
                            <h6 class="fw-bold text-center border-bottom pb-2 mb-3">MANUTENÇÃO EQUIPAMENTOS</h6>
                            <div class="d-flex fw-bold bg-secondary text-white px-3 py-2">
                                <div class="w-75">Situação</div>
                                <div class="w-25 text-end">Qtd</div>
                            </div>

                            <?= linha_status('<span class="text-success">Manutenção em Dia</span>', $contagem['Eqp']['Manutenção em dia']); ?>
                            <?= linha_status('<span class="text-warning">Manutenção Muito Próxima</span>', $contagem['Eqp']['Muito próxima'], 'bg-light'); ?>
                            <?= linha_status('<span class="text-primary">Agendada/Em manutenção</span>', $contagem['Eqp']['Em manutenção']); ?>
                            <?= linha_status('<span class="text-dark">Sem dados</span>', $contagem['Eqp']['Sem dados']); ?>
                            <?= linha_status('<span class="text-danger">Manutenção Vencida</span>', $contagem['Eqp']['Manutenção vencida'], 'bg-light'); ?>
                            <?= linha_status('<b>Total</b>', '<b>'.$contagem['Eqp']['Total'].'</b>', 'border-top mt-2'); ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>      
            <!-- ============================
     4. FILTROS E CARD MODERNO
============================ -->
<div class="card shadow-sm border-0 mb-4">
  <div class="card-body">

<!-- ============================ -->
<!-- FILTROS MODERNOS & SOFISTICADOS -->
<!-- ============================ -->
<form id="filtroEmpenhosForm" method="GET" class="row g-3 align-items-end mb-4">

  
  <!-- Categoria -->
  <div class="col-lg-4 col-md-6 col-sm-12">
    <label for="categoria" class="form-label fw-bold text-secondary small">
      <i class="fas fa-tags me-1"></i> Categoria
    </label>
    <select name="categoria" id="categoria" class="form-select shadow-sm border-0">
      <option value="">Todos</option>
      <option value="Peças" <?= ($filtro_categoria == 'Peças') ? 'selected' : '' ?>>Peças</option>
      <option value="Serviços" <?= ($filtro_categoria == 'Serviços') ? 'selected' : '' ?>>Serviços</option>
    </select>
  </div>

  <!-- Restos a Pagar -->
  <div class="col-lg-3 col-md-6 col-sm-12">
    <label for="resto" class="form-label fw-bold text-secondary small">
      <i class="fas fa-file-invoice-dollar me-1"></i> Restos a Pagar
    </label>
    <select name="resto" id="resto" class="form-select shadow-sm border-0">
      <option value="">Todos</option>
      <option value="Sim" <?= $filtro_resto === 'Sim' ? 'selected' : '' ?>>Sim</option>
      <option value="Não" <?= $filtro_resto === 'Não' ? 'selected' : '' ?>>Não</option>
    </select>
  </div>

</form>



    <!-- CARD PRINCIPAL -->
    <div class="d-flex bg-light rounded overflow-hidden shadow-sm">
  <!-- Lateral -->
  <div class="bg-success d-flex align-items-center justify-content-center" style="width: 100px;">
    <i class="fas fa-file-invoice-dollar text-white" style="font-size: 2.3rem;"></i>
  </div>

  <!-- Conteúdo -->
  <div class="flex-grow-1 p-4">
    <h5 class="fw-bold text-center border-bottom pb-2 mb-3 text-success">
      RESUMO FINANCEIRO / EMPENHOS
    </h5>

    <!-- Cabeçalho -->
    <div class="d-flex fw-bold bg-secondary text-white px-3 py-2 rounded-top">
      <div class="w-75">Indicador</div>
      <div class="w-25 text-end">Valor</div>
    </div>

    <!-- Total Empenhado -->
    <div class="d-flex px-3 py-2 border-bottom bg-white">
      <div class="w-75 fw-semibold">Total Empenhado</div>
      <div class="w-25 text-end"><?= fmt($soma_total_empenhado) ?></div>
    </div>

    <!-- Valor em Aberto -->
    <div class="d-flex px-3 py-2 border-bottom bg-light">
      <div class="w-75 text-danger">
        Valor em Aberto (Req / Entregue / Capeador)
      </div>
      <div class="w-25 text-end">
        <?= fmt($soma_nao_entregue + $soma_entregue + $soma_capeador) ?>
      </div>
    </div>

    <!-- Liquidado -->
    <div class="d-flex px-3 py-2 border-bottom bg-white">
      <div class="w-75 text-success">Total Liquidado</div>
      <div class="w-25 text-end"><?= fmt($soma_liquidado) ?></div>
    </div>

    <!-- Saldo Real -->
    <div class="d-flex px-3 py-2 border-bottom bg-light">
      <div class="w-75 text-primary">Saldo Real (Controle)</div>
      <div class="w-25 text-end"><?= fmt($soma_saldo_real) ?></div>
    </div>

    <!-- Saldo SIAFI -->
    <div class="d-flex px-3 py-2 border-bottom bg-white">
      <div class="w-75 text-muted">Saldo SIAFI (Corrente + Restos)</div>
      <div class="w-25 text-end"><?= fmt($soma_saldo_siafi) ?></div>
    </div>

    <!-- Diferença -->
    <div class="d-flex px-3 py-2 bg-light">
      <div class="w-75 text-warning">Diferença (SIAFI − Saldo Real)</div>
      <div class="w-25 text-end"><?= fmt($soma_diferenca) ?></div>
    </div>

    <!-- Rodapé -->
    <div class="d-flex fw-bold px-3 py-3 border-top mt-2">
      <div class="w-75">Total de Empenhos</div>
      <div class="w-25 text-end"><?= number_format($totalRegistros) ?></div>
    </div>

    <!-- Percentuais -->
    <div class="mt-3 small text-muted">
      <div class="d-flex justify-content-between">
        <div>Liquidado</div>
        <div><?= number_format($porc_liquidado, 2, ',', '.') ?>%</div>
      </div>

      <div class="d-flex justify-content-between">
        <div>Req / Entregue</div>
        <div><?= number_format($porc_req + $porc_entregue, 2, ',', '.') ?>%</div>
      </div>

      <div class="d-flex justify-content-between">
        <div>Capeador</div>
        <div><?= number_format($porc_capeador, 2, ',', '.') ?>%</div>
      </div>
    </div>
  </div>
</div>

  </div>
</div> 

                  
<!-- Script da página Principal -->
<script>
    window.funcaoInicializacao = 'inicializarPaginaPrincipal';
</script>