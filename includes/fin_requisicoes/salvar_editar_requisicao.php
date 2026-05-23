<?php
session_start();

$pagina_id = 60;
require_once('../api/seguranca_json_editar.php');

//CSRF
if (
    empty($_POST['csrf_token']) ||
    empty($_SESSION['csrf_token']) ||
    !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'Token inválido'
    ]);
    exit;
}

include '../../conexao/config.php';
include '../funcoes/log.php';

$usuarioLogado = $_SESSION['usuario_id'] ?? 0;

// ===============================
// Receber dados
// ===============================

$id = intval($_POST['id'] ?? 0);

if ($id <= 0) {
    echo "Requisição inválida";
    exit;
}

$requisitante = $_POST['requisitante'] ?? '';
$natureza_despesa = $_POST['natureza_despesa'] ?? '';
$item_oog = $_POST['item_oog'] ?? '';
$finalidade = $_POST['finalidade'] ?? '';
$destinatario = $_POST['destinatario'] ?? '';
$nota_credito = $_POST['nota_credito'] ?? '';
$plano_interno = $_POST['plano_interno'] ?? '';
$necessidade_contrato = $_POST['necessidade_contrato'] ?? '';
$tipo_empenho = $_POST['tipo_empenho'] ?? '';
$status_requisicao = $_POST['status_requisicao'] ?? '';
$id_pregao = intval($_POST['id_pregao'] ?? 0);

$itens = $_POST['itens'] ?? [];

if(empty($itens)){
    echo "Adicione itens à requisição";
    exit;
}


// ===============================
// VALIDAR SALDO DOS ITENS
// ===============================

foreach($itens as $item){

    $id_item = intval($item['id_item']);
    $quant = floatval($item['quant_saida_item']);

    // Dados do item
    $sqlItem = "SELECT saldo_item
                FROM fin_pregao_itens
                WHERE id=?";

    $stmtItem = $conexao->prepare($sqlItem);
    $stmtItem->bind_param("i",$id_item);
    $stmtItem->execute();
    $resItem = $stmtItem->get_result()->fetch_assoc();
    $stmtItem->close();

    if(!$resItem){
        echo "Item inválido";
        exit;
    }

    // Soma das outras requisições
    $sqlSaldo = "
        SELECT COALESCE(SUM(quant_saida_item),0) AS total_requisitado
        FROM fin_requisicao_itens
        WHERE id_item = ?
        AND id_requisicao <> ?
    ";

    $stmtSaldo = $conexao->prepare($sqlSaldo);
    $stmtSaldo->bind_param("ii",$id_item,$id);
    $stmtSaldo->execute();
    $resSaldo = $stmtSaldo->get_result()->fetch_assoc();
    $stmtSaldo->close();

    $total_requisitado = $resSaldo['total_requisitado'];

    $saldo_disponivel = $resItem['saldo_item'] - $total_requisitado;

    if($quant > $saldo_disponivel){

        echo "Saldo insuficiente para o item ID $id_item.
        Disponível: $saldo_disponivel | Solicitado: $quant";

        exit;

    }

}


// ===============================
// Atualizar requisição
// ===============================

$sql = "UPDATE fin_requisicao SET
requisitante=?,
natureza_despesa=?,
item_oog=?,
finalidade=?,
destinatario=?,
nota_credito=?,
plano_interno=?,
necessidade_contrato=?,
tipo_empenho=?,
status_requisicao=?,
id_pregao=?
WHERE id=?";

$stmt = $conexao->prepare($sql);

$stmt->bind_param(
"ssssssssssii",
$requisitante,
$natureza_despesa,
$item_oog,
$finalidade,
$destinatario,
$nota_credito,
$plano_interno,
$necessidade_contrato,
$tipo_empenho,
$status_requisicao,
$id_pregao,
$id
);

if (!$stmt->execute()) {
    echo "Erro ao atualizar requisição";
    exit;
}

$stmt->close();


// ===============================
// Apagar itens antigos
// ===============================

$sqlDelete = "DELETE FROM fin_requisicao_itens WHERE id_requisicao=?";
$stmtDelete = $conexao->prepare($sqlDelete);
$stmtDelete->bind_param("i", $id);
$stmtDelete->execute();
$stmtDelete->close();


// ===============================
// Inserir itens novamente
// ===============================

$sqlInsert = "INSERT INTO fin_requisicao_itens
(id_requisicao, id_item, quant_saida_item)
VALUES (?,?,?)";

$stmtInsert = $conexao->prepare($sqlInsert);

foreach ($itens as $item) {

    $id_item = intval($item['id_item']);
    $quant = floatval($item['quant_saida_item']);

    $stmtInsert->bind_param(
        "iid",
        $id,
        $id_item,
        $quant
    );

    $stmtInsert->execute();
}

$stmtInsert->close();


// ===============================
// Recalcular valor empenhado
// ===============================

$valor_empenhado = 0;
$fornecedor_principal = null;

foreach ($itens as $item) {

    $id_item = intval($item['id_item']);
    $quant = floatval($item['quant_saida_item']);

    $sqlItem = "SELECT valor_unt, id_fornecedor
                FROM fin_pregao_itens
                WHERE id=?";

    $stmtItem = $conexao->prepare($sqlItem);
    $stmtItem->bind_param("i", $id_item);
    $stmtItem->execute();

    $res = $stmtItem->get_result()->fetch_assoc();
    $stmtItem->close();

    if (!$res) {
        echo "Item inválido";
        exit;
    }

    $valor_empenhado += $quant * $res['valor_unt'];

    if ($fornecedor_principal === null) {
        $fornecedor_principal = $res['id_fornecedor'];
    } elseif ($fornecedor_principal != $res['id_fornecedor']) {
        echo "Itens devem ser do mesmo fornecedor";
        exit;
    }

}


// ===============================
// Atualizar valor_empenhado
// ===============================

$sqlValor = "UPDATE fin_requisicao
SET valor_empenhado=?, id_fornecedor=?
WHERE id=?";

$stmtValor = $conexao->prepare($sqlValor);

$stmtValor->bind_param(
"dii",
$valor_empenhado,
$fornecedor_principal,
$id
);

$stmtValor->execute();
$stmtValor->close();


// ===============================
// LOG
// ===============================

$descricao = "Requisição editada. ID: $id | Novo valor empenhado: $valor_empenhado";

		// 🔒 NOVO TOKEN
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));

registrar_log($conexao, $usuarioLogado, "Editar Requisição", $descricao, $id);


// ===============================
// RETORNO
// ===============================

echo "ok";
exit;

?>