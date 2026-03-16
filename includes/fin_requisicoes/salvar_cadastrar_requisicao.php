<?php
// includes/fin_requisicoes/salvar_cadastrar_requisicao.php
session_start();
include '../../conexao/config.php';
include '../funcoes/log.php';

// -----------------------------
// Função auxiliar buscar usuário
// -----------------------------
function buscarResponsavel($conexao, $funcao) {
    $sql = "SELECT nomecompleto, postograd 
            FROM usuarios 
            WHERE funcao = ? AND status = 'sim'
            LIMIT 1";
    $stmt = $conexao->prepare($sql);
    if (!$stmt) {
        return "Erro ao preparar busca responsavel: " . $conexao->error;
    }
    $stmt->bind_param("s", $funcao);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($u = $res->fetch_assoc()) {
        return $u['nomecompleto'] . " - " . $u['postograd'];
    }
    return "Não há usuário cadastrado com a função";
}

// -----------------------------
// Responsáveis automáticos
// -----------------------------
$cmt_ceem       = buscarResponsavel($conexao, '8');
$ch_financeiro  = buscarResponsavel($conexao, '10');
$ch_controle    = buscarResponsavel($conexao, '9');
$ch_s4          = buscarResponsavel($conexao, '19');
$cmt_batalhao   = buscarResponsavel($conexao, '7');

$usuarioLogado  = $_SESSION['usuario_id'] ?? 0;

// -----------------------------
// Ler dados do POST (formulário)
// -----------------------------
$batalhao             = !empty($_POST['batalhao']) ? intval($_POST['batalhao']) : 0;
$id_fornecedor        = !empty($_POST['id_fornecedor']) ? intval($_POST['id_fornecedor']) : 0;
$requisitante         = $_POST['requisitante'] ?? '';
$destinatario         = $_POST['destinatario'] ?? '';
$nota_credito         = $_POST['nota_credito'] ?? '';
$plano_interno        = $_POST['plano_interno'] ?? '';
$naturezadespesa      = $_POST['naturezadespesa'] ?? '';
$item_oog             = $_POST['item_oog'] ?? '';
$finalidade           = $_POST['finalidade'] ?? '';
$tipo_empenho         = $_POST['tipo_empenho'] ?? '';
$necessidade_contrato = $_POST['necessidade_contrato'] ?? '';
$status_requisicao    = $_POST['status_requisicao'] ?? 'Em confecção';

// Campos do modal "Gerar Empenho"
$data_empenho         = $_POST['data_empenho'] ?? null; // formato YYYY-MM-DD
$nmr_empenho          = $_POST['nmr_empenho'] ?? '';
$obra                 = $_POST['obra'] ?? '';
$ano                  = $_POST['ano'] ?? '';
$categoria            = $_POST['categoria'] ?? '';
$local                = $_POST['local'] ?? '';
$resto_pagar          = $_POST['resto_pagar'] ?? 'Não';

// valor empenhado (formato brasileiro possível "12.345,67" -> convert)
$valor_empenhado_raw  = $_POST['valor_empenhado'] ?? '0';
$valor_empenhado      = floatval(str_replace(['.', ','], ['', '.'], $valor_empenhado_raw));

// data_requisicao (agora)
$data_requisicao = date('Y-m-d H:i:s');

// ==================================================
// 1) Inserir em fin_requisicao (ficha do empenho)
// ==================================================
// Observação: não incluí id_pregao (mantemos NULL/default).
// Campos: batalhao, id_fornecedor, requisitante, destinatario,
// necessidade_contrato, finalidade, item_oog, tipo_empenho,
// data_requisicao, nota_credito, plano_interno, natureza_despesa,
// status_requisicao, nmr_empenho, empenho_gerado, cmt_ceem,
// ch_financeiro, ch_controle, ch_s4, cmt_batalhao, valor_empenhado

$sqlReq = "
INSERT INTO fin_requisicao (
    batalhao,
    id_fornecedor,
    requisitante,
    destinatario,
    necessidade_contrato,
    finalidade,
    item_oog,
    tipo_empenho,
    data_requisicao,
    nota_credito,
    plano_interno,
    natureza_despesa,
    status_requisicao,
    nmr_empenho,
    empenho_gerado,
    cmt_ceem,
    ch_financeiro,
    ch_controle,
    ch_s4,
    cmt_batalhao,
    valor_empenhado
) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
";

$stmtReq = $conexao->prepare($sqlReq);
if (!$stmtReq) {
    echo "Erro ao preparar INSERT fin_requisicao: " . $conexao->error;
    exit;
}

// montar tipos: 2 ints (batalhao, id_fornecedor), 12 strings, 1 int (empenho_gerado), 5 strings, 1 double
// mas montamos de forma explícita:
// tipos = "ii" + 12*s + "i" + 5*s + "d"  => total 21
$types = "ii" . str_repeat("s", 12) . "i" . str_repeat("s", 5) . "d";

// preparar variáveis
$empenho_gerado = 1; // já estamos gerando o empenho

$params = [
    &$types,
    &$batalhao,
    &$id_fornecedor,
    &$requisitante,
    &$destinatario,
    &$necessidade_contrato,
    &$finalidade,
    &$item_oog,
    &$tipo_empenho,
    &$data_requisicao,
    &$nota_credito,
    &$plano_interno,
    &$naturezadespesa,
    &$status_requisicao,
    &$nmr_empenho,
    &$empenho_gerado,
    &$cmt_ceem,
    &$ch_financeiro,
    &$ch_controle,
    &$ch_s4,
    &$cmt_batalhao,
    &$valor_empenhado
];

// Vincular parâmetros (bind_param exige referências)
call_user_func_array([$stmtReq, 'bind_param'], $params);

// executar
if (!$stmtReq->execute()) {
    echo "Erro ao executar INSERT fin_requisicao: " . $stmtReq->error;
    $stmtReq->close();
    exit;
}

$id_requisicao = $conexao->insert_id;
$stmtReq->close();

// ==================================================
// 2) Inserir em fin_empenhos (registro do empenho)
// ==================================================
// Campos: id_requisicao, data_empenho, nmr_empenho, obra, ano, categoria, local, resto_pagar

$sqlEmp = "
INSERT INTO fin_empenhos (
    id_requisicao,
    data_empenho,
    nmr_empenho,
    obra,
    ano,
    categoria,
    local,
    resto_pagar
) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
";

$stmtEmp = $conexao->prepare($sqlEmp);
if (!$stmtEmp) {
    // Se falhar, remover a requisição criada para consistência
    $conexao->query("DELETE FROM fin_requisicao WHERE id = " . intval($id_requisicao));
    echo "Erro ao preparar INSERT fin_empenhos: " . $conexao->error;
    exit;
}

$stmtEmp->bind_param(
    "isssssss",
    $id_requisicao,
    $data_empenho,
    $nmr_empenho,
    $obra,
    $ano,
    $categoria,
    $local,
    $resto_pagar
);

if (!$stmtEmp->execute()) {
    // rollback simples: deletar requisição já criada
    $stmtEmp->close();
    $conexao->query("DELETE FROM fin_requisicao WHERE id = " . intval($id_requisicao));
    echo "Erro ao executar INSERT fin_empenhos: " . $stmtEmp->error;
    exit;
}

$id_empenho = $conexao->insert_id;
$stmtEmp->close();

// ==================================================
// 3) Registrar Log
// ==================================================
$descricaoLog = "Empenho cadastrado. ID_REQUISICAO: $id_requisicao. ID_EMPENHO: $id_empenho. Fornecedor: $id_fornecedor. Valor: $valor_empenhado";

registrar_log($conexao, $usuarioLogado, "Cadastrar Empenho", $descricaoLog, $id_requisicao);

// ==================================================
// 4) Retorno OK
// ==================================================
echo "ok";
exit;
