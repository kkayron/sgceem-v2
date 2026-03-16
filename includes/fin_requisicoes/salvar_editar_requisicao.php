<?php
// includes/fin_requisicoes/salvar_editar_empenho.php
session_start();
include '../../conexao/config.php';
include '../funcoes/log.php';

// -----------------------------
// Função auxiliar buscar usuário
// -----------------------------
function buscarResponsavel($conexao, $funcao) {
    $sql = "SELECT nomecompleto, postograd FROM usuarios WHERE funcao = ? AND status = 'sim' LIMIT 1";
    $stmt = $conexao->prepare($sql);
    if (!$stmt) return "Erro ao preparar: " . $conexao->error;

    $stmt->bind_param("s", $funcao);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($u = $res->fetch_assoc()) return $u['nomecompleto'] . " - " . $u['postograd'];

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
// Ler dados enviados pelo formulário
// -----------------------------
$id_requisicao        = intval($_POST['id_requisicao'] ?? 0);
$batalhao             = intval($_POST['batalhao'] ?? 0);
$id_fornecedor        = intval($_POST['id_fornecedor'] ?? 0);
$requisitante         = $_POST['requisitante'] ?? '';
$destinatario         = $_POST['destinatario'] ?? '';
$nota_credito         = $_POST['nota_credito'] ?? '';
$plano_interno        = $_POST['plano_interno'] ?? '';
$naturezadespesa       = $_POST['naturezadespesa'] ?? '';
$item_oog             = $_POST['item_oog'] ?? '';
$finalidade           = $_POST['finalidade'] ?? '';
$tipo_empenho         = $_POST['tipo_empenho'] ?? '';
$necessidade_contrato = $_POST['necessidade_contrato'] ?? '';
$status_requisicao    = $_POST['status_requisicao'] ?? '';

// Dados do empenho (tabela fin_empenhos)
$data_empenho   = $_POST['data_empenho'] ?? null;
$nmr_empenho     = $_POST['nmr_empenho'] ?? '';
$obra           = $_POST['obra'] ?? '';
$ano            = $_POST['ano'] ?? '';
$categoria       = $_POST['categoria'] ?? '';
$local          = $_POST['local'] ?? '';
$resto_pagar    = $_POST['resto_pagar'] ?? 'Não';

// Valor empenhado (formato BR → float)
$valor_raw       = $_POST['valor_empenhado'] ?? '0';
$valor_empenhado = floatval(str_replace(['.', ','], ['', '.'], $valor_raw));

if ($id_requisicao <= 0) {
    echo "Erro: Requisição inválida";
    exit;
}

// ==================================================
// 1) Atualizar fin_requisicao
// ==================================================
$sql1 = "UPDATE fin_requisicao SET
    batalhao = ?,
    id_fornecedor = ?,
    requisitante = ?,
    destinatario = ?,
    necessidade_contrato = ?,
    finalidade = ?,
    item_oog = ?,
    tipo_empenho = ?,
    nota_credito = ?,
    plano_interno = ?,
    natureza_despesa = ?,
    status_requisicao = ?,
    nmr_empenho = ?,
    cmt_ceem = ?,
    ch_financeiro = ?,
    ch_controle = ?,
    ch_s4 = ?,
    cmt_batalhao = ?,
    valor_empenhado = ?
WHERE id = ?";

$stmt1 = $conexao->prepare($sql1);
if (!$stmt1) {
    echo "Erro ao preparar UPDATE fin_requisicao: " . $conexao->error;
    exit;
}

$stmt1->bind_param(
    "iissssssssssssssssdi",
    $batalhao,
    $id_fornecedor,
    $requisitante,
    $destinatario,
    $necessidade_contrato,
    $finalidade,
    $item_oog,
    $tipo_empenho,
    $nota_credito,
    $plano_interno,
    $naturezadespesa,
    $status_requisicao,
    $nmr_empenho,
    $cmt_ceem,
    $ch_financeiro,
    $ch_controle,
    $ch_s4,
    $cmt_batalhao,
    $valor_empenhado,
    $id_requisicao
);

if (!$stmt1->execute()) {
    echo "Erro ao executar UPDATE fin_requisicao: " . $stmt1->error;
    exit;
}
$stmt1->close();

// ==================================================
// 2) Atualizar fin_empenhos (ou inserir caso não exista)
// ==================================================

$sqlCheck = "SELECT id FROM fin_empenhos WHERE id_requisicao = ? LIMIT 1";
$stmtC = $conexao->prepare($sqlCheck);
$stmtC->bind_param("i", $id_requisicao);
$stmtC->execute();
$resC = $stmtC->get_result();
$temEmp = $resC->fetch_assoc();
$stmtC->close();

if ($temEmp) {
    // UPDATE
    $sql2 = "UPDATE fin_empenhos SET
        data_empenho = ?,
        nmr_empenho = ?,
        obra = ?,
        ano = ?,
        categoria = ?,
        local = ?,
        resto_pagar = ?
    WHERE id_requisicao = ?";

    $stmt2 = $conexao->prepare($sql2);
    if (!$stmt2) {
        echo "Erro ao preparar UPDATE fin_empenhos: " . $conexao->error;
        exit;
    }

    $stmt2->bind_param(
        "sssssssi",
        $data_empenho,
        $nmr_empenho,
        $obra,
        $ano,
        $categoria,
        $local,
        $resto_pagar,
        $id_requisicao
    );

    if (!$stmt2->execute()) {
        echo "Erro ao atualizar fin_empenhos: " . $stmt2->error;
        exit;
    }
    $stmt2->close();

} else {
    // INSERT
    $sql2 = "INSERT INTO fin_empenhos (
        id_requisicao, data_empenho, nmr_empenho, obra, ano, categoria, local, resto_pagar
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";

    $stmt2 = $conexao->prepare($sql2);
    if (!$stmt2) {
        echo "Erro ao preparar INSERT fin_empenhos: " . $conexao->error;
        exit;
    }

    $stmt2->bind_param(
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

    if (!$stmt2->execute()) {
        echo "Erro ao inserir fin_empenhos: " . $stmt2->error;
        exit;
    }
    $stmt2->close();
}

// ==================================================
// 3) Registrar LOG
// ==================================================
$descricaoLog = "Empenho atualizado. ID_REQUISICAO: $id_requisicao | Fornecedor: $id_fornecedor | Valor: $valor_empenhado";
registrar_log($conexao, $usuarioLogado, "Editar Empenho", $descricaoLog, $id_requisicao);

// ==================================================
// 4) Retorno
// ==================================================
echo "ok";
exit;
?>