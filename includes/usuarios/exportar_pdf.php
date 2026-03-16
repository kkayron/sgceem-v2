<?php
require_once '../../vendor/autoload.php';
require_once '../../conexao/config.php';

use Mpdf\Mpdf;

// Verifica se o ID foi passado
if (!isset($_GET['id'])) {
    die('ID do usuário não fornecido.');
}

$id = intval($_GET['id']);

// Consulta os dados do usuário
$queryUsuario = "SELECT * FROM usuarios WHERE id = $id";
$resultUsuario = mysqli_query($conexao, $queryUsuario);

if (!$resultUsuario || mysqli_num_rows($resultUsuario) == 0) {
    die('Usuário não encontrado.');
}

$usuario = mysqli_fetch_assoc($resultUsuario);

// Consulta os logs desse usuário (usuario_id)
$queryLogs = "SELECT * FROM logs WHERE usuario_id = $id ORDER BY data_hora DESC";
$resultLogs = mysqli_query($conexao, $queryLogs);

// Caminho da foto
$fotoPath = "../../assets/fotoperfil/" . $usuario['foto'];
$fotoBase64 = file_exists($fotoPath) ? base64_encode(file_get_contents($fotoPath)) : null;
$fotoHtml = $fotoBase64 
    ? '<img src="data:image/jpeg;base64,' . $fotoBase64 . '" style="width:120px;height:120px;border-radius:50%;margin-bottom:10px;" />' 
    : '';

// Monta HTML para o PDF
$html = '
<style>
    body { font-family: Arial, sans-serif; }
    table { border-collapse: collapse; width: 100%; margin-top: 20px; }
    th, td { border: 1px solid #000; padding: 8px; }
    th { background-color: #f2f2f2; }
    h2 { text-align: center; }
</style>

<div style="text-align: center;">
    ' . $fotoHtml . '
    <h2>Perfil do Usuário</h2>
</div>

<table>
    <tr><th>Posto/Grad</th><td>' . htmlspecialchars($usuario['postograd']) . '</td></tr>
    <tr><th>Nome de Guerra</th><td>' . htmlspecialchars($usuario['nomeguerra']) . '</td></tr>
    <tr><th>Função</th><td>' . htmlspecialchars($usuario['funcao']) . '</td></tr>
</table>

<h2>Logs de Alterações</h2>
<table>
    <tr>
        <th>Data</th>
        <th>Ação</th>
        <th>Descrição</th>
        <th>IP</th>
        <th>Navegador</th>
    </tr>';

while ($log = mysqli_fetch_assoc($resultLogs)) {
    $html .= '<tr>
        <td>' . date('d/m/Y H:i:s', strtotime($log['data_hora'])) . '</td>
        <td>' . htmlspecialchars($log['acao']) . '</td>
        <td>' . htmlspecialchars($log['descricao']) . '</td>
        <td>' . htmlspecialchars($log['ip']) . '</td>
        <td>' . htmlspecialchars($log['navegador']) . '</td>
    </tr>';
}

$html .= '</table>';

// Gera o PDF
$mpdf = new Mpdf();
$mpdf->WriteHTML($html);
$mpdf->Output("perfil_usuario_$id.pdf", 'I');