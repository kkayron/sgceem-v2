<?php
session_start();
require_once '../conexao/config.php';

if (!isset($_SESSION['usuario_id'])) {
    die("Sessão expirada. Faça login novamente.");
}

header("Content-Type: application/vnd.ms-excel; charset=UTF-8");
header("Content-Disposition: attachment; filename=planos_manutencao_" . date('Ymd_His') . ".xls");
header("Pragma: no-cache");
header("Expires: 0");
echo "\xEF\xBB\xBF";

$id_marca = $_GET['id_marca'] ?? '';
$id_modelo = $_GET['id_modelo'] ?? '';
$descricao = trim($_GET['descricao'] ?? '');
$tipo_controle = $_GET['tipo_controle'] ?? '';
$ativo = $_GET['ativo'] ?? '';

$filtros = [];
$params = [];
$tipos = '';

if ($id_marca !== '') {
    $filtros[] = "mp.id_marca = ?";
    $params[] = (int)$id_marca;
    $tipos .= 'i';
}

if ($id_modelo !== '') {
    $filtros[] = "mp.id_modelo = ?";
    $params[] = (int)$id_modelo;
    $tipos .= 'i';
}

if ($descricao !== '') {
    $filtros[] = "mp.descricao LIKE ?";
    $params[] = "%{$descricao}%";
    $tipos .= 's';
}

if ($tipo_controle !== '') {
    $filtros[] = "mp.tipo_controle = ?";
    $params[] = $tipo_controle;
    $tipos .= 's';
}

if ($ativo !== '') {
    $filtros[] = "mp.ativo = ?";
    $params[] = (int)$ativo;
    $tipos .= 'i';
}

$condicoes = !empty($filtros) ? "WHERE " . implode(" AND ", $filtros) : "";

$sql = "
    SELECT 
        cm.marca,
        cmo.nome_modelo,
        mp.descricao,
        mp.tipo_controle,
        mp.valor_inicial,
        mp.intervalo_valor,
        mp.intervalo_dias,
        mp.alerta_antes_valor,
        mp.alerta_antes_dias,
        mp.ativo
    FROM mnt_planos mp
    INNER JOIN config_marcas cm ON cm.id = mp.id_marca
    INNER JOIN config_modelos cmo ON cmo.id = mp.id_modelo
    $condicoes
    ORDER BY cm.marca ASC, cmo.nome_modelo ASC, mp.descricao ASC
";

$stmt = $conexao->prepare($sql);

if (!empty($params)) {
    $stmt->bind_param($tipos, ...$params);
}

$stmt->execute();
$res = $stmt->get_result();

function e($v) {
    return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8');
}
?>

<table border="1" style="border-collapse:collapse; font-family:Arial; font-size:12px;">
    <tr>
        <th colspan="10" style="background:#198754; color:#fff; font-size:16px; padding:10px;">
            EXPORTAÇÃO DE PLANOS DE MANUTENÇÃO
        </th>
    </tr>

    <tr>
        <td colspan="10" style="padding:8px;">
            <strong>Gerado em:</strong> <?= date('d/m/Y H:i') ?>
        </td>
    </tr>

    <tr style="background:#D1E7DD; font-weight:bold; text-align:center;">
        <th>Marca</th>
        <th>Modelo</th>
        <th>Descrição</th>
        <th>Tipo Controle</th>
        <th>Valor Inicial</th>
        <th>Intervalo Valor</th>
        <th>Intervalo Dias</th>
        <th>Alerta Antes Valor</th>
        <th>Alerta Antes Dias</th>
        <th>Ativo</th>
    </tr>

    <?php if ($res->num_rows === 0): ?>
        <tr>
            <td colspan="10" style="padding:10px; color:#777;">
                Nenhum plano encontrado com os filtros aplicados.
            </td>
        </tr>
    <?php endif; ?>

    <?php while ($row = $res->fetch_assoc()): ?>
        <tr>
            <td><?= e($row['marca']) ?></td>
            <td><?= e($row['nome_modelo']) ?></td>
            <td><?= e($row['descricao']) ?></td>
            <td><?= e($row['tipo_controle']) ?></td>
            <td><?= e($row['valor_inicial']) ?></td>
            <td><?= e($row['intervalo_valor']) ?></td>
            <td><?= e($row['intervalo_dias']) ?></td>
            <td><?= e($row['alerta_antes_valor']) ?></td>
            <td><?= e($row['alerta_antes_dias']) ?></td>
            <td><?= ((int)$row['ativo'] === 1) ? 'Sim' : 'Não' ?></td>
        </tr>
    <?php endwhile; ?>
</table>
<?php exit; ?>