<?php
require_once '../../vendor/autoload.php'; // ajuste o caminho se necessário
include '../../conexao/config.php';

use Mpdf\Mpdf;

$data = trim($_POST['data'] ?? '');
$usuario = trim($_POST['usuario'] ?? '');
$acao = trim($_POST['acao'] ?? '');
$palavra = trim($_POST['palavra'] ?? '');

$filtros = [];
$params = [];

if (!empty($data)) {
  $filtros[] = "DATE(l.data_hora) = ?";
  $params[] = $data;
}

if (!empty($usuario)) {
  $filtros[] = "CONCAT(u.postograd, ' ', u.nomeguerra) = ?";
  $params[] = $usuario;
}

if (!empty($acao)) {
  $filtros[] = "l.acao = ?";
  $params[] = $acao;
}

if (!empty($palavra)) {
  $filtros[] = "(l.descricao LIKE ? OR l.ip LIKE ? OR l.navegador LIKE ?)";
  $params[] = "%$palavra%";
  $params[] = "%$palavra%";
  $params[] = "%$palavra%";
}

$where = count($filtros) ? 'WHERE ' . implode(' AND ', $filtros) : '';

// Consulta os logs com JOIN na tabela de usuários
$query = "SELECT l.*, CONCAT(u.postograd, ' ', u.nomeguerra) AS usuario_nome
          FROM logs l
          LEFT JOIN usuarios u ON l.usuario_id = u.id
          $where
          ORDER BY l.data_hora DESC";

$stmt = $conexao->prepare($query);
if (!empty($params)) {
  $tipos = str_repeat('s', count($params));
  $stmt->bind_param($tipos, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

$html = '<h2 style="text-align:center;">Relatório de Logs</h2>';
$html .= '<table border="1" cellspacing="0" cellpadding="5" width="100%">';
$html .= '<thead>
  <tr>
    <th>Data</th>
    <th>Usuário</th>
    <th>Ação</th>
    <th>Descrição</th>
    <th>IP</th>
    <th>Navegador</th>
  </tr>
</thead><tbody>';

while ($log = $result->fetch_assoc()) {
  $dataFormatada = date('d/m/Y H:i:s', strtotime($log['data_hora']));
  $usuarioNome = $log['usuario_nome'] ?? 'N/A';

  $html .= "<tr>
    <td>{$dataFormatada}</td>
    <td>{$usuarioNome}</td>
    <td>{$log['acao']}</td>
    <td>{$log['descricao']}</td>
    <td>{$log['ip']}</td>
    <td>{$log['navegador']}</td>
  </tr>";
}

$html .= '</tbody></table>';

// Gera PDF com mPDF
$mpdf = new Mpdf();
$mpdf->WriteHTML($html);
$mpdf->Output('relatorio_logs.pdf', 'D'); // D = download
exit;