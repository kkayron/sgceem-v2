<?php
// includes/fin_pedidos/salvar_editar_pedido.php
session_start();
include_once("../../conexao/config.php");
include_once("../funcoes/log.php");

header('Content-Type: application/json; charset=utf-8');

$id_pedido = $_POST['id'] ?? null;
if (!$id_pedido || !is_numeric($id_pedido)) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'ID do Pedido não recebido ou inválido.']);
    exit;
}

$usuarioLogado = $_SESSION['usuario_id'] ?? 0;

// 1) Buscar valores antigos
$old = $conexao->query("SELECT * FROM fin_pedidos_forn WHERE id = $id_pedido")->fetch_assoc();

// 2) Atualizar pedido principal
$update = $conexao->prepare("
    UPDATE fin_pedidos_forn
    SET 
      data_pedido      = ?, 
      solicitante      = ?, 
      id_vtr           = ?, 
      id_os            = ?, 
      secao_rspns      = ?, 
      situacao_pedido  = ?, 
      local_pedido     = ?, 
      desconto_empenho = ?
    WHERE id = ?
");
$data_pedido      = $_POST['data_pedido']      ?? '';
$solicitante      = $_POST['solicitante']      ?? '';
$id_vtr           = (int)($_POST['id_vtr']      ?? 0);
$id_os            = (int)($_POST['id_os']       ?? 0);
$secao_rspns      = $_POST['secao_rspns']      ?? '';
$situacao_pedido  = $_POST['situacao_pedido']  ?? '';
$local_pedido     = $_POST['local_pedido']     ?? '';
$desconto_empenho = (float)($_POST['desconto_empenho'] ?? 0);

$update->bind_param(
    'ssiisssdi',
    $data_pedido,
    $solicitante,
    $id_vtr,
    $id_os,
    $secao_rspns,
    $situacao_pedido,
    $local_pedido,
    $desconto_empenho,
    $id_pedido
);

if (!$update->execute()) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Erro ao atualizar o pedido: '.$update->error]);
    exit;
}
$update->close();

// 3) Remover itens antigos
$conexao->query("DELETE FROM fin_pedidos_forn_itens WHERE id_principal = $id_pedido");

// 4) Inserir os novos itens
$itens_log = [];
if (isset($_POST['itens']) && is_array($_POST['itens'])) {
    $sqlItem = "
      INSERT INTO fin_pedidos_forn_itens
        (id_principal, codigo_item, descricao_item, quant_solicitada, und_solicitada, valor_unt, valor_total)
      VALUES (?, ?, ?, ?, ?, ?, ?)
    ";
    $stmtItem = $conexao->prepare($sqlItem);

    foreach ($_POST['itens'] as $it) {
        $cod   = $it['codigo_item']      ?? '';
        $desc  = $it['descricao_item']   ?? '';
        $qtd   = (float)($it['quant_solicitada'] ?? 0);
        $und   = $it['und_solicitada']   ?? '';
        $unit  = (float)($it['valor_unt'] ?? 0);
        $tot   = (float)($it['valor_total'] ?? 0);

        $stmtItem->bind_param(
            'issdsdd',
            $id_pedido,
            $cod,
            $desc,
            $qtd,
            $und,
            $unit,
            $tot
        );
        $stmtItem->execute();
        $itens_log[] = "Item '{$cod}': '{$desc}', qtd {$qtd}, unit {$unit}, total {$tot}";
    }
    $stmtItem->close();
}

// 5) Registrar log
$descricao = "Pedido ID {$id_pedido} editado.";
if (!empty($itens_log)) {
    $descricao .= " Itens: " . implode("; ", $itens_log) . ".";
}
registrar_log($conexao, $usuarioLogado, 'Editar Pedido', $descricao, $id_pedido);

// 6) Retornar JSON de sucesso
echo json_encode(['status' => 'sucesso', 'mensagem' => 'Pedido atualizado com sucesso!']);
exit;
