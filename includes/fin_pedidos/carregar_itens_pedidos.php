<?php
header('Content-Type: text/html; charset=utf-8');
if (session_status() === PHP_SESSION_NONE) session_start();

$pagina_ids = [17, 25];
require_once('../api/seguranca_json.php');

require_once('../../conexao/config.php');

$idPedido = (int)($_GET['id'] ?? 0);
if ($idPedido <= 0) {
    http_response_code(400);
    echo '<div class="alert alert-danger">ID inválido.</div>';
    exit;
}

// ==================================================
// Permissão: replica a regra de batalhões visíveis
// ==================================================
$id_om_usuario = (int)($_SESSION['usuario']['batalhao'] ?? 0);

$sqlNivel = "SELECT nivel FROM organizacoes_militares WHERE id = ?";
$stmtNivel = $conexao->prepare($sqlNivel);
$stmtNivel->bind_param("i", $id_om_usuario);
$stmtNivel->execute();
$resNivel = $stmtNivel->get_result();
$nivel_usuario = (int)($resNivel->fetch_assoc()['nivel'] ?? 0);
$stmtNivel->close();

$batalhoesPermitidos = [];

if ($nivel_usuario == 1) {
    $res = $conexao->query("SELECT id FROM organizacoes_militares");
    while ($r = $res->fetch_assoc()) $batalhoesPermitidos[] = (int)$r['id'];
} elseif ($nivel_usuario == 2) {
    $batalhoesPermitidos[] = $id_om_usuario;
    $stmt = $conexao->prepare("SELECT id_om_menor FROM organizacoes_militares_sub WHERE id_om_maior = ?");
    $stmt->bind_param("i", $id_om_usuario);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()) $batalhoesPermitidos[] = (int)$r['id_om_menor'];
    $stmt->close();
} else {
    $batalhoesPermitidos[] = $id_om_usuario;
}

// ==================================================
// Confere se o pedido pertence a batalhão permitido
// ==================================================
$stmtChk = $conexao->prepare("SELECT id, batalhao FROM fin_pedidos_forn WHERE id = ?");
$stmtChk->bind_param("i", $idPedido);
$stmtChk->execute();
$pedidoRow = $stmtChk->get_result()->fetch_assoc();
$stmtChk->close();

if (!$pedidoRow) {
    http_response_code(404);
    echo '<div class="alert alert-warning">Pedido não encontrado.</div>';
    exit;
}

$idBatalhaoPedido = (int)$pedidoRow['batalhao'];
if (!in_array($idBatalhaoPedido, $batalhoesPermitidos, true)) {
    http_response_code(403);
    echo '<div class="alert alert-danger">Acesso negado.</div>';
    exit;
}

// ==================================================
// Busca itens (somente o necessário)
// ==================================================
$stmtItens = $conexao->prepare("
    SELECT descricao_item, quant_solicitada, valor_unt
    FROM fin_pedidos_forn_itens
    WHERE id_principal = ?
    ORDER BY id ASC
");
$stmtItens->bind_param("i", $idPedido);
$stmtItens->execute();
$resItens = $stmtItens->get_result();

if ($resItens->num_rows > 0) {
    echo '<ul class="list-group list-group-flush">';
    while ($item = $resItens->fetch_assoc()) {
        echo '<li class="list-group-item bg-transparent">';
        echo '<strong>' . htmlspecialchars($item['descricao_item']) . '</strong><br>';
        echo '<small class="text-muted">';
        echo 'Qtd: ' . (int)$item['quant_solicitada'] . ' — ';
        echo 'Valor unit.: R$ ' . number_format((float)$item['valor_unt'], 2, ',', '.');
        echo '</small>';
        echo '</li>';
    }
    echo '</ul>';
} else {
    echo '<p class="text-muted mb-0">Nenhum item cadastrado.</p>';
}

$stmtItens->close();