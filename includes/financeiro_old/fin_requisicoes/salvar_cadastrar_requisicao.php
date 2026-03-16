<?php
// includes/fin_requisicoes/cadastrar_requisicao.php
session_start();
include '../../conexao/config.php';
include '../funcoes/log.php';

// Função auxiliar para buscar usuário por função
function buscarResponsavel($conexao, $funcao) {
    $sql = "SELECT nomecompleto, postograd FROM usuarios 
            WHERE funcao = ? AND status = 'sim' LIMIT 1";
    $stmt = $conexao->prepare($sql);
    $stmt->bind_param("s", $funcao);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($user = $result->fetch_assoc()) {
        return $user['nomecompleto'] . " - " . $user['postograd'];
    }
    return "Não há usuário cadastrado com a função";
}

// Busca responsáveis
$cmt_ceem       = buscarResponsavel($conexao, '8');
$ch_suprimento  = buscarResponsavel($conexao, '10');
$ch_controle    = buscarResponsavel($conexao, '9');
$ch_s4          = buscarResponsavel($conexao, '19');
$cmt_batalhao   = buscarResponsavel($conexao, '7');

$usuarioLogado = $_SESSION['usuario_id'] ?? 0;

// Campos principais
$requisitante        = $_POST['requisitante']        ?? '';
$naturezadespesa     = $_POST['naturezadespesa']     ?? '';
$batalhao            = $_POST['batalhao']            ?? '';
$destinatario        = $_POST['destinatario']        ?? '';
$nota_credito        = $_POST['nota_credito']        ?? '';
$tipo_empenho        = $_POST['tipo_empenho']        ?? '';
$necessidade_contrato= $_POST['necessidade_contrato']?? '';
$finalidade          = $_POST['finalidade']          ?? '';
$item_oog            = $_POST['item_oog']            ?? '';
$plano_interno       = $_POST['plano_interno']       ?? '';
$status_requisicao   = $_POST['status_requisicao']   ?? '';
$id_pregao           = $_POST['id_pregao']           ?? null;

$itens = $_POST['itens'] ?? [];
$data_requisicao = date('Y-m-d H:i:s');

// ====================================
// 1) Descobrir fornecedor real dos itens
// ====================================
$fornecedor_principal = null;

if (empty($itens)) {
    echo "Erro: A requisição deve possuir itens.";
    exit;
}

foreach ($itens as $item) {

    $id_item = intval($item['id_item'] ?? 0);
    if (!$id_item) continue;

    // Busca fornecedor do item
    $stmtF = $conexao->prepare("SELECT id_fornecedor FROM fin_pregao_itens WHERE id = ?");
    $stmtF->bind_param("i", $id_item);
    $stmtF->execute();
    $resultF = $stmtF->get_result()->fetch_assoc();
    $stmtF->close();

    if (!$resultF) {
        echo "Erro: Item ID $id_item não encontrado no pregão.";
        exit;
    }

    $id_fornecedor_item = $resultF['id_fornecedor'];

    if ($fornecedor_principal === null) {
        // Primeiro fornecedor encontrado
        $fornecedor_principal = $id_fornecedor_item;
    } elseif ($fornecedor_principal != $id_fornecedor_item) {
        echo "Erro: Todos os itens devem ser do mesmo fornecedor";
        exit;
    }
}

// ====================================
// 2) Inserir a requisição principal
// ====================================

$insert = $conexao->prepare("
    INSERT INTO fin_requisicao (
      batalhao,
      necessidade_contrato,
      finalidade,
      item_oog,
      requisitante,
      destinatario,
      nota_credito,
      plano_interno,
      natureza_despesa,
      status_requisicao,
      tipo_empenho,
      cmt_ceem,
      ch_financeiro,
      cmt_batalhao,
      ch_s4,
      data_requisicao,
      id_pregao,
      id_fornecedor
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
");

$insert->bind_param(
    'ssssssssssssssssii',
    $batalhao,
    $necessidade_contrato,
    $finalidade,
    $item_oog,
    $requisitante,
    $destinatario,
    $nota_credito,
    $plano_interno,
    $naturezadespesa,
    $status_requisicao,
    $tipo_empenho,
    $cmt_ceem,
    $ch_suprimento,
    $cmt_batalhao,
    $ch_s4,
    $data_requisicao,
    $id_pregao,
    $fornecedor_principal
);

$insert->execute();
$id_requisicao = $conexao->insert_id;
$insert->close();

$itens_log = [];

// ====================================
// 3) Inserir itens da requisição
// ====================================

foreach ($itens as $item) {

    $id_item = intval($item['id_item'] ?? 0);
    $quant_saida_item = floatval($item['quant_saida_item'] ?? 0);

    if (!$id_item) continue;

    // Busca saldo atual
    $stmtS = $conexao->prepare("
        SELECT
            pi.saldo_item - COALESCE(SUM(ri.quant_saida_item), 0) AS saldo_disponivel
        FROM fin_pregao_itens pi
        LEFT JOIN fin_requisicao_itens ri ON pi.id = ri.id_item
        WHERE pi.id = ?
        GROUP BY pi.id
    ");
    $stmtS->bind_param("i", $id_item);
    $stmtS->execute();
    $resS = $stmtS->get_result()->fetch_assoc();
    $stmtS->close();

    $saldo_disponivel = floatval($resS['saldo_disponivel'] ?? 0);

    if ($quant_saida_item > $saldo_disponivel) {
        $conexao->query("DELETE FROM fin_requisicao WHERE id = $id_requisicao");
        echo "Erro: Saldo insuficiente no item $id_item.";
        exit;
    }

    // Insere item
    $stmtI = $conexao->prepare("
        INSERT INTO fin_requisicao_itens (id_requisicao, id_item, quant_saida_item)
        VALUES (?, ?, ?)
    ");
    $stmtI->bind_param("iid", $id_requisicao, $id_item, $quant_saida_item);
    $stmtI->execute();
    $stmtI->close();

    $itens_log[] = "Item $id_item, Quantidade $quant_saida_item";
}

// ====================================
// 4) Log
// ====================================
$desc = "Cadastro da Requisição ID $id_requisicao. Fornecedor: $fornecedor_principal. ";
$desc .= "Itens: " . implode("; ", $itens_log);

registrar_log($conexao, $usuarioLogado, "Cadastrar Requisição", $desc, $id_requisicao);

echo "ok";
?>
