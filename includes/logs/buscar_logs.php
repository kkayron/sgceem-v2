<?php
file_put_contents('debug_log.txt', print_r($_POST, true));
header('Content-Type: application/json; charset=utf-8');
include '../../conexao/config.php';

$data = trim($_POST['data'] ?? '');
$acao = trim($_POST['acao'] ?? '');
$usuario = trim($_POST['usuario'] ?? '');
$palavra = trim($_POST['palavra'] ?? '');
$pagina = intval($_POST['pagina'] ?? 1);
$limite = 10;
$offset = ($pagina - 1) * $limite;

$filtros = [];
$params = [];

// Filtro por data
if (!empty($data)) {
 $filtros[] = "DATE(l.data_hora) = ?";
  $params[] = $data;
}

// Filtro por ação
if (!empty($acao)) {
  $filtros[] = "l.acao = ?";
  $params[] = $acao;
}

// Filtro por usuário
if (!empty($usuario)) {
  $filtros[] = "CONCAT(u.postograd, ' ', u.nomeguerra) = ?";
  $params[] = $usuario;
}

// Filtro por palavra-chave
if (!empty($palavra)) {
  $filtros[] = "(l.descricao LIKE ? OR l.ip LIKE ? OR l.navegador LIKE ?)";
  $params[] = "%$palavra%";
  $params[] = "%$palavra%";
  $params[] = "%$palavra%";
}

$where = count($filtros) ? 'WHERE ' . implode(' AND ', $filtros) : '';

// Consulta principal com JOIN e paginação
$query = "
  SELECT l.*, u.postograd, u.nomeguerra
  FROM logs l
  LEFT JOIN usuarios u ON l.usuario_id = u.id
  $where
  ORDER BY l.data_hora DESC
  LIMIT ? OFFSET ?
";

$stmt = $conexao->prepare($query);
$tipos = str_repeat('s', count($params)) . 'ii';
$bindParams = [...$params, $limite, $offset];
$stmt->bind_param($tipos, ...$bindParams);
$stmt->execute();
$result = $stmt->get_result();
$logs = $result->fetch_all(MYSQLI_ASSOC);

// Consulta total de registros
$queryTotal = "
  SELECT COUNT(*) FROM logs l
  LEFT JOIN usuarios u ON l.usuario_id = u.id
  $where
";

$stmtTotal = $conexao->prepare($queryTotal);
if (!empty($params)) {
  $tiposTotal = str_repeat('s', count($params));
  $stmtTotal->bind_param($tiposTotal, ...$params);
}
$stmtTotal->execute();
$resultTotal = $stmtTotal->get_result();
$total = $resultTotal->fetch_row()[0];
$totalPaginas = ceil($total / $limite);

// Monta HTML da tabela com nova coluna de usuário
$tabela = '';
foreach ($logs as $log) {
  $dataFormatada = date('d/m/Y H:i:s', strtotime($log['data_hora']));
  $usuarioNome = $log['postograd'] && $log['nomeguerra'] ? "{$log['postograd']} {$log['nomeguerra']}" : '-';
  $tabela .= "<tr>
    <td>{$dataFormatada}</td>
    <td>{$usuarioNome}</td>
    <td>{$log['acao']}</td>
    <td>{$log['descricao']}</td>
    <td>{$log['ip']}</td>
    <td>{$log['navegador']}</td>
  </tr>";
}

// Monta paginação com limite de 5 páginas visíveis
$paginacao = '';
$maxLinksVisiveis = 5;

// Garante que a página atual esteja no meio da paginação, se possível
$inicio = max(1, $pagina - floor($maxLinksVisiveis / 2));
$fim = min($totalPaginas, $inicio + $maxLinksVisiveis - 1);

// Ajusta o início se estiver no final
$inicio = max(1, $fim - $maxLinksVisiveis + 1);

// Link para a primeira página (se necessário)
if ($inicio > 1) {
  $paginacao .= "<a href='#' class='pagina-link mx-1' data-pagina='1'>&laquo; 1</a>";
  if ($inicio > 2) $paginacao .= "<span class='mx-1'>...</span>";
}

// Links visíveis
for ($i = $inicio; $i <= $fim; $i++) {
  $ativo = $i == $pagina ? 'fw-bold text-warning' : '';
  $paginacao .= "<a href='#' class='pagina-link mx-1 $ativo' data-pagina='$i'>$i</a>";
}

// Link para a última página (se necessário)
if ($fim < $totalPaginas) {
  if ($fim < $totalPaginas - 1) $paginacao .= "<span class='mx-1'>...</span>";
  $paginacao .= "<a href='#' class='pagina-link mx-1' data-pagina='$totalPaginas'>$totalPaginas &raquo;</a>";
}

// Retorna JSON
echo json_encode([
  'tabela' => $tabela,
  'paginacao' => $paginacao
], JSON_UNESCAPED_UNICODE);