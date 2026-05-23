<?php
session_start();
$pagina_id = 46;

require_once('../api/seguranca_json_importar.php');

// =============================
// 🔒 CSRF
// =============================
if (
    empty($_POST['csrf_token']) ||
    empty($_SESSION['csrf_token']) ||
    !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
) {
    http_response_code(403);
    resposta_json_and_exit(['status' => 'erro', 'mensagem' => 'Token inválido']);
}

require '../../conexao/config.php';
require '../funcoes/log.php';
require '../../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

header("Content-Type: application/json; charset=utf-8");

$usuarioLogado = $_SESSION['usuario_id'] ?? 0;
$batalhao = intval($_POST['batalhao'] ?? 0);

$resposta = [
    "status"   => "ok",
    "falhas"   => [],
    "mensagem" => ""
];

// ===============================
// VALIDAR ARQUIVO
// ===============================
if (!isset($_FILES['arquivo']) || $_FILES['arquivo']['error'] !== 0) {
    echo json_encode(["status" => "erro", "mensagem" => "Nenhum arquivo enviado"]);
    exit;
}

// ===============================
// CARREGAR PLANILHA (IGUAL XAMPP)
// ===============================
try {
    $spread = IOFactory::load($_FILES['arquivo']['tmp_name']);
    $sheet  = $spread->getActiveSheet();
    $rows   = $sheet->toArray(null, true, true, true);
} catch (Throwable $e) {
    echo json_encode([
        "status" => "erro",
        "mensagem" => "Erro lendo Excel: " . $e->getMessage()
    ]);
    exit;
}

// =======================================================
// Buscar responsáveis — NÃO ALTERADO
// =======================================================
function buscarResp($con, $funcao) {
    $q = $con->prepare("SELECT nomecompleto, postograd FROM usuarios WHERE funcao=? AND status='sim' LIMIT 1");
    $q->bind_param("s", $funcao);
    $q->execute();
    $r = $q->get_result();
    if ($u = $r->fetch_assoc()) {
        return $u['nomecompleto'] . " - " . $u['postograd'];
    }
    return "";
}

// =======================================================
// Buscar ID do empenho — NÃO ALTERADO
// =======================================================
function buscarEmpenhoID($con, $numero) {
    if ($numero === '') return 0;
    $q = $con->prepare("SELECT id FROM fin_empenhos WHERE nmr_empenho=? LIMIT 1");
    $q->bind_param("s", $numero);
    $q->execute();
    $r = $q->get_result();
    if ($e = $r->fetch_assoc()) {
        return (int)$e['id'];
    }
    return 0;
}

$cmt_ceem      = buscarResp($conexao, "8");
$ch_suprimento = buscarResp($conexao, "5");
$ch_controle   = buscarResp($conexao, "9");

$importados = 0;

// =======================================================
// PROCESSAR LINHAS — IGUAL XAMPP
// =======================================================
foreach ($rows as $i => $linha) {

    if ($i == 1) continue;
    if (trim($linha['A']) === '') continue;

    // ================= ORDEM =================
    $nmr_empenho   = trim($linha['A']);
    $empresa_nome  = trim($linha['B']);
    $empresa_cnpj  = trim($linha['C']);
    $data_limite = trim($linha['D']);
    $data_limite = $data_limite === '' ? null : $data_limite;
    $status_of     = trim($linha['E']);
    $local_entrega = trim($linha['F']);
    $nome_resp     = trim($linha['G']);
    $contato_resp  = trim($linha['H']);
    $obs_final     = trim($linha['I']);

    $id_empenho_planilha = buscarEmpenhoID($conexao, $nmr_empenho);

    $stmtOF = $conexao->prepare("
        INSERT INTO fin_ordemforn (
            batalhao, id_empenho, data_entrega_limite, status,
            empresa_nome, empresa_cnpj, local_entrega,
            nome_responsavel, contato_responsavel, observacao_final,
            cmt_ceem, ch_suprimento, ch_controle
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    $stmtOF->bind_param(
        "iisssssssssss",
        $batalhao,
        $id_empenho_planilha,
        $data_limite,
        $status_of,
        $empresa_nome,
        $empresa_cnpj,
        $local_entrega,
        $nome_resp,
        $contato_resp,
        $obs_final,
        $cmt_ceem,
        $ch_suprimento,
        $ch_controle
    );

    if (!$stmtOF->execute()) {
        $resposta["falhas"][] = ["linha" => $i, "erro" => $stmtOF->error];
        continue;
    }

    $id_ordem = $conexao->insert_id;
    $stmtOF->close();

    // ================= PEDIDO =================
    $solicitante     = trim($linha['J']);
    $secao_resp      = trim($linha['K']);
    $situacao_pedido = trim($linha['L']);
    $data_pedido = trim($linha['M']);
    $data_pedido = $data_pedido === '' ? null : $data_pedido;
    $id_vtr          = (int)$linha['N'];
    $desconto_emp    = trim($linha['O']);
    $local_pedido    = trim($linha['P']);

    $id_os = 0;

    $stmtPed = $conexao->prepare("
        INSERT INTO fin_pedidos_forn (
            id_os, batalhao, id_vtr, situacao_pedido,
            local_pedido, data_pedido, solicitante,
            desconto_empenho, secao_rspns,
            cmt_ceem, ch_controle, ch_suprimento
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    $stmtPed->bind_param(
        "iiisssssssss",
        $id_os,
        $batalhao,
        $id_vtr,
        $situacao_pedido,
        $local_pedido,
        $data_pedido,
        $solicitante,
        $desconto_emp,
        $secao_resp,
        $cmt_ceem,
        $ch_controle,
        $ch_suprimento
    );

    if (!$stmtPed->execute()) {
        $resposta["falhas"][] = ["linha" => $i, "erro" => $stmtPed->error];
        continue;
    }

    $id_pedido = $conexao->insert_id;
    $stmtPed->close();

    // ================= ITEM =================
    $codigo_item    = trim($linha['Q']);
    $descricao_item = trim($linha['R']);
    $quant          = (int)$linha['S'];
    $unidade        = trim($linha['T']);
    $almox_possui   = trim($linha['U']);

    $valor_unt   = (float)str_replace(',', '.', $linha['V']);
    $valor_total = (float)str_replace(',', '.', $linha['W']);

    $stmtItem = $conexao->prepare("
        INSERT INTO fin_pedidos_forn_itens (
            id_principal, codigo_item, descricao_item,
            quant_solicitada, und_solicitada, almox_possui,
            valor_unt, valor_total
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");

    $stmtItem->bind_param(
        "ississdd",
        $id_pedido,
        $codigo_item,
        $descricao_item,
        $quant,
        $unidade,
        $almox_possui,
        $valor_unt,
        $valor_total
    );

    if (!$stmtItem->execute()) {
        $resposta["falhas"][] = ["linha" => $i, "erro" => $stmtItem->error];
        continue;
    }
    $stmtItem->close();

    // ================= LINK =================
    $stmtL = $conexao->prepare("
        INSERT INTO fin_ordemforn_pedidos (id_pedido, id_ordemforn)
        VALUES (?, ?)
    ");
    $stmtL->bind_param("ii", $id_pedido, $id_ordem);
    $stmtL->execute();
    $stmtL->close();

    registrar_log(
        $conexao,
        $usuarioLogado,
        "Importar Ordem",
        "Ordem $id_ordem / Pedido $id_pedido importados",
        $id_ordem
    );

    $importados++;
}

// 🔒 NOVO TOKEN
$_SESSION['csrf_token'] = bin2hex(random_bytes(32)); //OBSERVAR SE VAI FUNCIONAR
$resposta["mensagem"] = "Total importado: $importados";
echo json_encode($resposta);
exit;
