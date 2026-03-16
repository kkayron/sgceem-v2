<?php
require_once '../../conexao/config.php';

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$paginaOs = isset($_GET['paginaOs']) ? intval($_GET['paginaOs']) : 1;
$paginaPedidos = isset($_GET['paginaPedidos']) ? intval($_GET['paginaPedidos']) : 1;
$paginaLogs = isset($_GET['paginaLogs']) ? intval($_GET['paginaLogs']) : 1;

$limite = 5;
$offsetOs = ($paginaOs - 1) * $limite;
$offsetPedidos = ($paginaPedidos - 1) * $limite;
$offsetLogs = ($paginaLogs - 1) * $limite;

if($id <= 0){
    echo json_encode(['sucesso' => false, 'mensagem' => 'ID inválido']);
    exit;
}

// Buscar dados da viatura
$sqlVtr = "
    SELECT f.id, f.prefixo_sga, f.placa, f.modelo AS modelo_id, f.marca AS marca_id, f.tipo, f.foto_capa, f.disponibilidade, f.destino,
           m.marca AS marca_nome,
           mo.nome_modelo AS modelo_nome
    FROM frota f
    LEFT JOIN config_marcas m ON f.marca = m.id
    LEFT JOIN config_modelos mo ON f.modelo = mo.id
    WHERE f.id = ?
";
$stmtVtr = $conexao->prepare($sqlVtr);
$stmtVtr->bind_param("i", $id);
$stmtVtr->execute();
$resultVtr = $stmtVtr->get_result();

if($resultVtr->num_rows === 0){
    echo json_encode(['sucesso' => false, 'mensagem' => 'Viatura não encontrada.']);
    exit;
}

$viatura = $resultVtr->fetch_assoc();

// Total de OS para paginação
$sqlCount = "SELECT COUNT(*) AS total FROM os_principal WHERE id_frota = ?";
$stmtCount = $conexao->prepare($sqlCount);
$stmtCount->bind_param("i", $id);
$stmtCount->execute();
$totalOs = $stmtCount->get_result()->fetch_assoc()['total'];
$totalPaginasOs = ceil($totalOs / $limite);

// OS da página atual
$sqlOs = "SELECT id, DATE_FORMAT(data_abertura, '%d/%m/%Y') AS data_abertura, status, problema, local_os, solicitante, tipo_mnt, aberta_por
          FROM os_principal
          WHERE id_frota = ?
          ORDER BY data_abertura DESC
          LIMIT ? OFFSET ?";
$stmtOs = $conexao->prepare($sqlOs);
$stmtOs->bind_param("iii", $id, $limite, $offsetOs);
$stmtOs->execute();
$ordensServico = $stmtOs->get_result()->fetch_all(MYSQLI_ASSOC);

// Últimos 10 odômetros (apenas odômetro)
$sqlMed = "
    SELECT odometro, DATE_FORMAT(data, '%d/%m/%Y') AS data
    FROM controle_medicoes
    WHERE viatura_id = ?
    ORDER BY data DESC
    LIMIT 10
";
$stmtMed = $conexao->prepare($sqlMed);
$stmtMed->bind_param("i", $id);
$stmtMed->execute();
$ultimosOdometros = $stmtMed->get_result()->fetch_all(MYSQLI_ASSOC);
$stmtMed->close();

// (Opcional) mantém compatibilidade se você ainda usa "ultima_medicao" em algum lugar
$ultimaMed = $ultimosOdometros[0] ?? [];

// Últimos 5 pedidos almoxarifado
$sqlPed = "SELECT p.id, DATE_FORMAT(p.data_pedido, '%d/%m/%Y') AS data_pedido, p.local_pedido, p.status_pedido, p.militar_solicitante
           FROM almox_pedidos_princ p
           WHERE p.id_os IN (SELECT id FROM os_principal WHERE id_frota = ?)
           ORDER BY p.data_pedido DESC LIMIT 5";
$stmtPed = $conexao->prepare($sqlPed);
$stmtPed->bind_param("i", $id);
$stmtPed->execute();
$pedidosAlmox = $stmtPed->get_result()->fetch_all(MYSQLI_ASSOC);

// Últimos 5 pedidos financeiro
$sqlPedFin = "SELECT f.id, DATE_FORMAT(f.data_pedido, '%d/%m/%Y') AS data_pedido, f.local_pedido, f.situacao_pedido, f.solicitante
              FROM fin_pedidos_forn f
              WHERE f.id_vtr = ?
              ORDER BY f.data_pedido DESC LIMIT 5";
$stmtPedFin = $conexao->prepare($sqlPedFin);
$stmtPedFin->bind_param("i", $id);
$stmtPedFin->execute();
$pedidosFin = $stmtPedFin->get_result()->fetch_all(MYSQLI_ASSOC);

// Últimos 5 fornecimentos da viatura
$sqlForn = "
    SELECT of.id, of.data_cadastro, of.status, of.empresa_nome
    FROM fin_ordemforn of
    INNER JOIN fin_ordemforn_pedidos op ON of.id = op.id_ordemforn
    INNER JOIN fin_pedidos_forn pf ON op.id_pedido = pf.id
    WHERE pf.id_vtr = ?
    ORDER BY of.data_cadastro DESC
    LIMIT 5
";
$stmtForn = $conexao->prepare($sqlForn);
$stmtForn->bind_param("i", $id);
$stmtForn->execute();
$fornecimentos = $stmtForn->get_result()->fetch_all(MYSQLI_ASSOC);

// Total de fichas para paginação
$sqlCountFichas = "SELECT COUNT(*) AS total FROM sta_fichas WHERE id_viatura = ?";
$stmtCountFichas = $conexao->prepare($sqlCountFichas);
$stmtCountFichas->bind_param("i", $id);
$stmtCountFichas->execute();
$totalFichas = $stmtCountFichas->get_result()->fetch_assoc()['total'];
$totalPaginasFichas = ceil($totalFichas / $limite);

// Fichas da página atual
$sqlFichas = "SELECT id, DATE_FORMAT(data_abertura, '%d/%m/%Y') AS data_abertura, destino, motorista, status
              FROM sta_fichas
              WHERE id_viatura = ?
              ORDER BY data_abertura DESC
              LIMIT ? OFFSET ?";
$stmtFichas = $conexao->prepare($sqlFichas);
$stmtFichas->bind_param("iii", $id, $limite, $offsetOs); // Aqui você pode criar $offsetFichas separado se quiser paginação própria
$stmtFichas->execute();
$fichas = $stmtFichas->get_result()->fetch_all(MYSQLI_ASSOC);

// Logs
$sqlCountLOGS = "SELECT COUNT(*) AS total FROM logs WHERE frota_id = ?";
$stmtCountLOGS = $conexao->prepare($sqlCountLOGS);
$stmtCountLOGS->bind_param("i", $id);
$stmtCountLOGS->execute();
$totalLogs = $stmtCountLOGS->get_result()->fetch_assoc()['total'];
$totalPaginasLogs = ceil($totalLogs / $limite);

$sqlLogs = "SELECT l.*, CONCAT(u.postograd, ' - ', u.nomeguerra) AS responsavel_nome
            FROM logs l
            LEFT JOIN usuarios u ON l.usuario_id = u.id
            WHERE l.frota_id = ?
            ORDER BY l.id DESC LIMIT ? OFFSET ?";
$stmtLogs = $conexao->prepare($sqlLogs);
$stmtLogs->bind_param("iii", $id, $limite, $offsetLogs);
$stmtLogs->execute();
$logs = $stmtLogs->get_result()->fetch_all(MYSQLI_ASSOC);

echo json_encode([
    'sucesso' => true,
    'foto_capa' => $viatura['foto_capa'],
    'prefixo_sga' => $viatura['prefixo_sga'],
    'modelo' => $viatura['modelo_nome'],
    'marca' => $viatura['marca_nome'],
    'placa' => $viatura['placa'],
    'tipo' => $viatura['tipo'],
    'disponibilidade' => $viatura['disponibilidade'],
    'destino' => $viatura['destino'],
    'ultima_medicao' => $ultimaMed,
    'ultimos_odometros' => $ultimosOdometros,
    'ordens_servico' => $ordensServico,
    'total_paginas_os' => $totalPaginasOs,
    'pagina_atual_os' => $paginaOs,
    'pedidos_almox' => $pedidosAlmox,
    'pedidos_financeiro' => $pedidosFin,
    'fornecimentos' => $fornecimentos,
    'fichas' => $fichas,
    'total_paginas_fichas' => $totalPaginasFichas,
    'logs' => $logs,
    'total_paginas_logs' => $totalPaginasLogs,
    'pagina_atual_logs' => $paginaLogs
]);
