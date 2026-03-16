<?php
// includes/fin_requisicoes/salvar_editar_requisicao.php
session_start();
include '../../conexao/config.php';
include '../funcoes/log.php';

$id_requisicao = $_POST['id'] ?? null;
if (!$id_requisicao) {
    echo 'ID da Requisição não recebido.';
    exit;
}

$usuarioLogado = $_SESSION['usuario_id'] ?? 0;

// -------------------------------
// Função auxiliar
// -------------------------------
function buscarResponsavel($conexao, $funcao) {
    $sql = "SELECT nomecompleto, postograd 
            FROM usuarios 
            WHERE funcao = ? AND status = 'sim'
            LIMIT 1";

    $stmt = $conexao->prepare($sql);
    $stmt->bind_param("s", $funcao);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($u = $res->fetch_assoc()) {
        return "{$u['nomecompleto']} - {$u['postograd']}";
    }

    return "Não há usuário cadastrado com a função";
}

// Responsáveis (mesmos códigos do cadastrar)
$cmt_ceem       = buscarResponsavel($conexao, '8');
$ch_suprimento  = buscarResponsavel($conexao, '10');
$ch_s4          = buscarResponsavel($conexao, '19');
$cmt_batalhao   = buscarResponsavel($conexao, '7');

// -------------------------------
// Buscar dados antigos
// -------------------------------
$old = $conexao->query("
    SELECT * FROM fin_requisicao WHERE id = $id_requisicao
")->fetch_assoc();

if (!$old) {
    echo 'Requisição não encontrada.';
    exit;
}

// -------------------------------
// Se empenho já gerado, bloquear mudanças nesses campos
// -------------------------------
if ($old['empenho_gerado'] === 'sim') {
    $_POST['status_requisicao'] = $old['status_requisicao'];
    $_POST['nmr_empenho']       = $old['nmr_empenho'];
    $_POST['empenho_gerado']    = 'sim';
}

// -------------------------------
// Validar fornecedor único nos itens
// -------------------------------
$fornecedor_principal = null;

foreach ($_POST['itens'] ?? [] as $it) {

    $iid = intval($it['id_item'] ?? 0);
    if (!$iid) continue;

    $st = $conexao->prepare("SELECT id_fornecedor FROM fin_pregao_itens WHERE id = ?");
    $st->bind_param("i", $iid);
    $st->execute();
    $row = $st->get_result()->fetch_assoc();
    $st->close();

    if (!$row) {
        echo "Erro: item $iid não existe.";
        exit;
    }

    $id_forn_item = $row['id_fornecedor'];

    if ($fornecedor_principal === null) {
        $fornecedor_principal = $id_forn_item;
    } elseif ($id_forn_item != $fornecedor_principal) {
        echo "Erro: Os itens pertencem a fornecedores diferentes.";
        exit;
    }
}

// Se não há itens, erro
if ($fornecedor_principal === null) {
    echo "Erro: A requisição deve conter itens.";
    exit;
}

// Não permitir mudança de fornecedor se empenho gerado
if ($old['empenho_gerado'] === 'sim' && $old['id_fornecedor'] != $fornecedor_principal) {
    echo "Erro: Não é permitido alterar o fornecedor após empenho gerado.";
    exit;
}

// -------------------------------
// Captura dos novos valores
// -------------------------------
$item_oog            = $_POST['item_oog']            ?? '';
$finalidade          = $_POST['finalidade']          ?? '';
$necessidade         = $_POST['necessidade_contrato']?? '';
$requisitante        = $_POST['requisitante']        ?? '';
$destinatario        = $_POST['destinatario']        ?? '';
$nota_credito        = $_POST['nota_credito']        ?? '';
$plano_interno       = $_POST['plano_interno']       ?? '';
$natureza            = $_POST['natureza_despesa']    ?? '';
$status_requisicao   = $_POST['status_requisicao']   ?? '';
$nmr_empenho         = $_POST['nmr_empenho']         ?? '';
$empenho_gerado      = $_POST['empenho_gerado']      ?? 'não';
$tipo_empenho        = $_POST['tipo_empenho']        ?? '';
$id_pregao           = $_POST['id_pregao']           ?? null;
$id_fornecedor       = $fornecedor_principal;

// -------------------------------
// Atualizar requisição
// -------------------------------
$upd = $conexao->prepare("
    UPDATE fin_requisicao
    SET 
      item_oog             = ?,
      finalidade           = ?,
      necessidade_contrato = ?,
      requisitante         = ?,
      destinatario         = ?,
      nota_credito         = ?,
      plano_interno        = ?,
      natureza_despesa     = ?,
      status_requisicao    = ?,
      nmr_empenho          = ?,
      empenho_gerado       = ?,
      cmt_ceem             = ?,
      ch_financeiro        = ?,
      tipo_empenho         = ?,
      id_pregao            = ?,
      id_fornecedor        = ?
    WHERE id = ?
");

$upd->bind_param(
    'ssssssssssssssiii',
    $item_oog,
    $finalidade,
    $necessidade,
    $requisitante,
    $destinatario,
    $nota_credito,
    $plano_interno,
    $natureza,
    $status_requisicao,
    $nmr_empenho,
    $empenho_gerado,
    $cmt_ceem,
    $ch_suprimento,
    $tipo_empenho,
    $id_pregao,
    $id_fornecedor,
    $id_requisicao
);

$upd->execute();
$upd->close();

// -------------------------------
// Log de alterações
// -------------------------------
$novos = [
    'item_oog'            => $item_oog,
    'finalidade'          => $finalidade,
    'necessidade_contrato'=> $necessidade,
    'requisitante'        => $requisitante,
    'destinatario'        => $destinatario,
    'nota_credito'        => $nota_credito,
    'plano_interno'       => $plano_interno,
    'natureza_despesa'    => $natureza,
    'status_requisicao'   => $status_requisicao,
    'nmr_empenho'         => $nmr_empenho,
    'empenho_gerado'      => $empenho_gerado,
    'tipo_empenho'        => $tipo_empenho,
    'id_pregao'           => $id_pregao,
    'id_fornecedor'       => $id_fornecedor
];

$alter = [];
foreach ($novos as $campo => $novo) {
    $oldv = $old[$campo] ?? '';
    if ((string)$novo !== (string)$oldv) {
        $alter[] = ucfirst($campo).": '$oldv' → '$novo'";
    }
}

// -------------------------------
// Atualizar itens
// -------------------------------
$conexao->query("DELETE FROM fin_requisicao_itens WHERE id_requisicao = $id_requisicao");

$itens_log = [];

foreach ($_POST['itens'] ?? [] as $it) {

    $iid = intval($it['id_item'] ?? 0);
    $qtd = floatval($it['quant_saida_item'] ?? 0);

    if (!$iid || $qtd <= 0) continue;

    // Saldo do item (excluindo essa requisição)
    $stmtSaldo = $conexao->prepare("
        SELECT 
            pi.saldo_item - COALESCE(SUM(ri.quant_saida_item), 0) AS saldo_disponivel
        FROM fin_pregao_itens pi
        LEFT JOIN fin_requisicao_itens ri 
               ON ri.id_item = pi.id 
              AND ri.id_requisicao != ?
        WHERE pi.id = ?
        GROUP BY pi.id
    ");
    $stmtSaldo->bind_param("ii", $id_requisicao, $iid);
    $stmtSaldo->execute();
    $rowSaldo = $stmtSaldo->get_result()->fetch_assoc();
    $stmtSaldo->close();

    $saldo_disp = floatval($rowSaldo['saldo_disponivel'] ?? 0);

    if ($qtd > $saldo_disp) {
        echo "Erro: O item ID $iid não possui saldo suficiente. Solicitado: $qtd, Disponível: $saldo_disp.";
        exit;
    }

    // Inserir item atualizado
    $stmtI = $conexao->prepare("
        INSERT INTO fin_requisicao_itens
          (id_requisicao, id_item, quant_saida_item)
        VALUES (?, ?, ?)
    ");
    $stmtI->bind_param('iid', $id_requisicao, $iid, $qtd);
    $stmtI->execute();
    $stmtI->close();

    $itens_log[] = "Item $iid, Qtd $qtd";
}

// -------------------------------
// Registrar log
// -------------------------------
if ($alter || $itens_log) {
    $msg  = "Edição da Requisição #$id_requisicao: ";
    if ($alter) $msg .= implode("; ", $alter).". ";
    if ($itens_log) $msg .= "Itens: ".implode("; ", $itens_log).". ";

    registrar_log($conexao, $usuarioLogado, 'Editar Requisição', $msg, $id_requisicao);
}

echo 'ok';
?>
