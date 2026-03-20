<?php

session_start();

include '../../conexao/config.php';
include '../funcoes/log.php';

function buscarResponsavel($conexao, $funcao)
{
    $sql = "SELECT nomecompleto, postograd 
            FROM usuarios 
            WHERE funcao = ? 
            AND status = 'sim' 
            LIMIT 1";

    $stmt = $conexao->prepare($sql);
    $stmt->bind_param("s", $funcao);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($u = $res->fetch_assoc()) {
        return $u['nomecompleto'] . " - " . $u['postograd'];
    }

    return "Não há usuário cadastrado";
}

$cmt_ceem = buscarResponsavel($conexao,'8');
$ch_financeiro = buscarResponsavel($conexao,'10');
$ch_controle = buscarResponsavel($conexao,'9');
$ch_s4 = buscarResponsavel($conexao,'19');
$cmt_batalhao = buscarResponsavel($conexao,'7');

$usuarioLogado = $_SESSION['usuario_id'] ?? 0;

$batalhao = $_POST['batalhao'] ?? '';
$requisitante = $_POST['requisitante'] ?? '';
$destinatario = $_POST['destinatario'] ?? '';
$natureza_despesa = $_POST['natureza_despesa'] ?? '';
$nota_credito = $_POST['nota_credito'] ?? '';
$plano_interno = $_POST['plano_interno'] ?? '';
$finalidade = $_POST['finalidade'] ?? '';
$item_oog = $_POST['item_oog'] ?? '';
$tipo_empenho = $_POST['tipo_empenho'] ?? '';
$necessidade_contrato = $_POST['necessidade_contrato'] ?? '';
$status_requisicao = $_POST['status_requisicao'] ?? '';
$id_pregao = intval($_POST['id_pregao'] ?? 0);

$itens = $_POST['itens'] ?? [];

$data_requisicao = date('Y-m-d H:i:s');

if(empty($itens)){
    echo "Adicione itens à requisição";
    exit;
}

$valor_empenhado = 0;
$fornecedor_principal = null;

foreach($itens as $item){

    $id_item = intval($item['id_item']);
    $quant = floatval($item['quant_saida_item']);

    // ===============================
    // BUSCAR ITEM DO PREGÃO
    // ===============================

    $sql = "SELECT valor_unt, id_fornecedor, saldo_item
            FROM fin_pregao_itens
            WHERE id=?";

    $stmt = $conexao->prepare($sql);
    $stmt->bind_param("i",$id_item);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();

    if(!$res){
        echo "Item inválido";
        exit;
    }

    // ===============================
    // CALCULAR SALDO JÁ REQUISITADO
    // ===============================

    $sqlSaldo = "
        SELECT COALESCE(SUM(quant_saida_item),0) AS total_requisitado
        FROM fin_requisicao_itens
        WHERE id_item = ?
    ";

    $stmtSaldo = $conexao->prepare($sqlSaldo);
    $stmtSaldo->bind_param("i",$id_item);
    $stmtSaldo->execute();
    $resSaldo = $stmtSaldo->get_result()->fetch_assoc();

    $total_requisitado = $resSaldo['total_requisitado'];

    $saldo_disponivel = $res['saldo_item'] - $total_requisitado;

    // ===============================
    // VERIFICAR ESTOQUE
    // ===============================

    if($quant > $saldo_disponivel){

        echo "Saldo insuficiente para o item ID $id_item. 
        Disponível: $saldo_disponivel | Solicitado: $quant";

        exit;

    }

    // ===============================
    // CALCULAR VALOR DO EMPENHO
    // ===============================

    $valor_empenhado += $quant * $res['valor_unt'];

    // ===============================
    // VALIDAR FORNECEDOR
    // ===============================

    if($fornecedor_principal === null){
        $fornecedor_principal = $res['id_fornecedor'];
    }elseif($fornecedor_principal != $res['id_fornecedor']){
        echo "Itens devem ser do mesmo fornecedor";
        exit;
    }

}

$sql = "INSERT INTO fin_requisicao
(
batalhao,
id_pregao,
id_fornecedor,
requisitante,
destinatario,
necessidade_contrato,
valor_empenhado,
finalidade,
item_oog,
tipo_empenho,
data_requisicao,
nota_credito,
plano_interno,
natureza_despesa,
status_requisicao,
cmt_ceem,
ch_financeiro,
ch_controle,
ch_s4,
cmt_batalhao
)
VALUES
(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";

$stmt = $conexao->prepare($sql);

$stmt->bind_param(
"iiisssdsssssssssssss",
$batalhao,
$id_pregao,
$fornecedor_principal,
$requisitante,
$destinatario,
$necessidade_contrato,
$valor_empenhado,
$finalidade,
$item_oog,
$tipo_empenho,
$data_requisicao,
$nota_credito,
$plano_interno,
$natureza_despesa,
$status_requisicao,
$cmt_ceem,
$ch_financeiro,
$ch_controle,
$ch_s4,
$cmt_batalhao
);

$stmt->execute();

$id_requisicao = $conexao->insert_id;

// ===============================
// INSERIR ITENS
// ===============================

foreach($itens as $item){

$id_item = intval($item['id_item']);
$quant = floatval($item['quant_saida_item']);

$stmtI = $conexao->prepare("
INSERT INTO fin_requisicao_itens
(id_requisicao,id_item,quant_saida_item)
VALUES (?,?,?)
");

$stmtI->bind_param("iid",$id_requisicao,$id_item,$quant);
$stmtI->execute();

}

// ===============================
// LOG
// ===============================

$desc = "Requisição $id_requisicao cadastrada. Valor empenhado: $valor_empenhado";

registrar_log($conexao,$usuarioLogado,"Cadastrar Requisição",$desc,$id_requisicao);

echo "ok";