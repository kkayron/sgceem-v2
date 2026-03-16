<?php
include_once('../../conexao/config.php');

// Verifica se o ID foi informado e é válido
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($id <= 0) {
    die("Usuário não encontrado");
}

// Busca dados do usuário
$usuarioQuery = $conexao->prepare("SELECT nomeguerra, postograd, funcao, foto FROM usuarios WHERE id = ?");
$usuarioQuery->bind_param("i", $id);
$usuarioQuery->execute();
$resultUsuario = $usuarioQuery->get_result();

if ($resultUsuario->num_rows === 0) {
    die("Usuário não encontrado");
}

$usuario = $resultUsuario->fetch_assoc();

// Busca logs
$logsQuery = $conexao->prepare("SELECT data_hora, acao, descricao, ip, navegador FROM logs WHERE usuario_id = ? ORDER BY data_hora DESC");
$logsQuery->bind_param("i", $id);
$logsQuery->execute();
$resultLogs = $logsQuery->get_result();

// Cabeçalhos para Excel
header("Content-Type: application/vnd.ms-excel; charset=UTF-8");
header("Content-Disposition: attachment; filename=perfil_usuario_$id.xls");
header("Pragma: no-cache");
header("Expires: 0");

// Força o BOM para UTF-8
echo "\xEF\xBB\xBF";

// Tabela do Perfil
echo "<table border='1'>";
echo "<tr><th colspan='2'>Perfil do Usuário</th></tr>";
echo "<tr><td><b>Posto/Grad:</b></td><td>" . htmlspecialchars($usuario['postograd']) . "</td></tr>";
echo "<tr><td><b>Nome de Guerra:</b></td><td>" . htmlspecialchars($usuario['nomeguerra']) . "</td></tr>";
echo "<tr><td><b>Função:</b></td><td>" . htmlspecialchars($usuario['funcao']) . "</td></tr>";
echo "</table><br>";

// Tabela de Logs
echo "<table border='1'>";
echo "<tr><th colspan='5'>Logs de Alterações</th></tr>";
echo "<tr>
        <th>Data</th>
        <th>Ação</th>
        <th>Descrição</th>
        <th>IP</th>
        <th>Navegador</th>
      </tr>";

while ($log = $resultLogs->fetch_assoc()) {
    $data = date('d/m/Y H:i:s', strtotime($log['data_hora']));
    $acao = htmlspecialchars($log['acao']);
    $descricao = htmlspecialchars($log['descricao']);
    $ip = htmlspecialchars($log['ip']);
    $navegador = htmlspecialchars($log['navegador']);

    echo "<tr>
            <td>{$data}</td>
            <td>{$acao}</td>
            <td>{$descricao}</td>
            <td>{$ip}</td>
            <td>{$navegador}</td>
          </tr>";
}
echo "</table>";
?>