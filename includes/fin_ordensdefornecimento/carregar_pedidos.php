<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
include_once('../../conexao/config.php');

$id_ordem = (int) ($_GET['id_ordem'] ?? 0);
if (!$id_ordem) exit;

$sql = "
  SELECT 
    p.id,
    p.local_pedido,
    p.situacao_pedido
  FROM fin_ordemforn_pedidos op
  JOIN fin_pedidos_forn p ON p.id = op.id_pedido
  WHERE op.id_ordemforn = ?
";

$stmt = $conexao->prepare($sql);
$stmt->bind_param("i", $id_ordem);
$stmt->execute();
$res = $stmt->get_result();

if ($res->num_rows == 0) {
    echo '<div class="alert alert-info">Nenhum pedido vinculado.</div>';
    exit;
}

while ($pedido = $res->fetch_assoc()) {

    // itens do pedido
    $stmtItens = $conexao->prepare("
        SELECT descricao_item, quant_solicitada, valor_unt, valor_total
        FROM fin_pedidos_forn_itens
        WHERE id_principal = ?
    ");
    $stmtItens->bind_param("i", $pedido['id']);
    $stmtItens->execute();
    $resItens = $stmtItens->get_result();

    $total = 0;
    $itens = [];
    while ($i = $resItens->fetch_assoc()) {
        $total += $i['valor_total'];
        $itens[] = $i;
    }
    $stmtItens->close();
    ?>

    <div class="border rounded p-3 mb-3 bg-light">
      <h6 class="fw-semibold text-primary mb-2">
        Pedido #<?= $pedido['id'] ?> —
        Valor total: R$ <?= number_format($total, 2, ',', '.') ?>
      </h6>

      <div class="text-muted small mb-2">
        <div><strong>Local:</strong> <?= htmlspecialchars($pedido['local_pedido']) ?></div>
        <div><strong>Situação:</strong> <?= htmlspecialchars($pedido['situacao_pedido']) ?></div>
      </div>

      <ul class="list-group list-group-flush">
        <?php foreach ($itens as $item): ?>
        <li class="list-group-item">
          <strong><?= htmlspecialchars($item['descricao_item']) ?></strong>
          — Qtd: <?= $item['quant_solicitada'] ?>
          — Valor: R$ <?= number_format($item['valor_unt'], 2, ',', '.') ?>
        </li>
        <?php endforeach; ?>
      </ul>
    </div>

<?php }