<?php
header('Content-Type: application/json');
include_once('../../conexao/config.php');

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$dataFiltro = trim($_GET['data'] ?? '');
$acaoFiltro = trim($_GET['acao'] ?? '');
$palavraFiltro = trim($_GET['palavra'] ?? '');
$pagina = max(1, intval($_GET['pagina'] ?? 1));
$porPagina = 10;
$offset = ($pagina - 1) * $porPagina;

$response = [
    'total' => 0,
    'porPagina' => $porPagina,
    'paginaAtual' => $pagina,
    'logs' => []
];

if ($id <= 0) {
    echo json_encode($response);
    exit;
}

// Filtros dinâmicos
$where = "WHERE usuario_id = ?";
$tipos = "i";
$valores = [$id];

if ($dataFiltro !== '') {
    if (preg_match('/\d{2}\/\d{2}\/\d{4}/', $dataFiltro)) {
        $partes = explode('/', $dataFiltro);
        $dataFiltro = "{$partes[2]}-{$partes[1]}-{$partes[0]}";
    }
    $where .= " AND DATE(data_hora) = ?";
    $tipos .= "s";
    $valores[] = $dataFiltro;
}

if ($acaoFiltro !== '') {
    if (is_numeric($acaoFiltro)) {
        $where .= " AND acao = ?";
        $tipos .= "i";
        $valores[] = intval($acaoFiltro);
    } else {
        $where .= " AND acao LIKE ?";
        $tipos .= "s";
        $valores[] = "%$acaoFiltro%";
    }
}

if ($palavraFiltro !== '') {
    $where .= " AND (descricao LIKE ? OR ip LIKE ? OR navegador LIKE ?)";
    $tipos .= "sss";
    $valores[] = "%$palavraFiltro%";
    $valores[] = "%$palavraFiltro%";
    $valores[] = "%$palavraFiltro%";
}

// Consulta total
$sqlTotal = "SELECT COUNT(*) as total FROM logs $where";
$stmt = $conexao->prepare($sqlTotal);
if ($stmt === false) {
    error_log("Erro no prepare total: " . $conexao->error);
    echo json_encode($response);
    exit;
}
$stmt->bind_param($tipos, ...$valores);
$stmt->execute();
$result = $stmt->get_result();
$response['total'] = $result->fetch_assoc()['total'] ?? 0;
$stmt->close();

// Consulta paginada
$sql = "SELECT data_hora, acao, descricao, ip, navegador FROM logs $where ORDER BY data_hora DESC LIMIT ? OFFSET ?";
$stmt = $conexao->prepare($sql);
if ($stmt === false) {
    error_log("Erro no prepare paginado: " . $conexao->error);
    echo json_encode($response);
    exit;
}

$tiposComLimite = $tipos . "ii";
$valoresComLimite = array_merge($valores, [$porPagina, $offset]);
$stmt->bind_param($tiposComLimite, ...$valoresComLimite);
$stmt->execute();
$result = $stmt->get_result();

while ($log = $result->fetch_assoc()) {
    $response['logs'][] = [
        'data' => date('d/m/Y H:i:s', strtotime($log['data_hora'])),
        'acao' => $log['acao'],
        'descricao' => $log['descricao'],
        'ip' => $log['ip'],
        'navegador' => $log['navegador']
    ];
}
$stmt->close();

echo json_encode($response);
