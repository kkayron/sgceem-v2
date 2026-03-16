<?php
require_once '../../vendor/autoload.php';
include '../../conexao/config.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

$data = trim($_POST['data'] ?? '');
$usuario = trim($_POST['usuario'] ?? '');
$acao = trim($_POST['acao'] ?? '');
$palavra = trim($_POST['palavra'] ?? '');

$filtros = [];
$params = [];

if (!empty($data) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $data)) {
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

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

$sheet->setTitle('Logs');

// Cabeçalhos
$sheet->fromArray(['Data', 'Usuário', 'Ação', 'Descrição', 'IP', 'Navegador'], null, 'A1');

// Dados
$row = 2;
while ($log = $result->fetch_assoc()) {
  $sheet->setCellValue("A$row", date('d/m/Y H:i:s', strtotime($log['data_hora'])));
  $sheet->setCellValue("B$row", $log['usuario_nome'] ?? 'N/A');
  $sheet->setCellValue("C$row", $log['acao']);
  $sheet->setCellValue("D$row", $log['descricao']);
  $sheet->setCellValue("E$row", $log['ip']);
  $sheet->setCellValue("F$row", $log['navegador']);
  $row++;
}

// Força o download
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="relatorio_logs.xlsx"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;

