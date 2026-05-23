<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

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

$pagina_id = 21;

require_once('../api/seguranca_json_deletar.php');
include_once("../../conexao/config.php");
include_once("../../includes/funcoes/log.php");

//DADOS DO USUÁRIO
$usuarioLogado    = $_SESSION['usuario_id'];
$nivel_usuario    = $_SESSION['usuario']['nivel'] ?? 3;
$batalhao_usuario = $_SESSION['usuario']['batalhao'] ?? 0;


//ID SEGURO
$id = intval($_POST['id'] ?? 0);

if ($id <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID inválido']);
    exit;
}

// ================================
// BUSCAR FORNECEDOR
// ================================
$stmt_select = $conexao->prepare("
    SELECT id, nome_empresa, cnpj_empresa, data_cadastro, categoria_empresa, 
           contato_nome, contato_numero, contato_email, batalhao
    FROM fin_fornecedores 
    WHERE id = ?
");

$stmt_select->bind_param("i", $id);
$stmt_select->execute();
$result = $stmt_select->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Fornecedor não encontrado']);
    exit;
}

$forn = $result->fetch_assoc();
$stmt_select->close();

$batalhao_forn = (int)$forn['batalhao'];

// ================================
//VALIDAÇÃO POR NÍVEL
// ================================

if ($nivel_usuario == 1) {
    // Admin → acesso total

} elseif ($nivel_usuario == 2) {

    // Buscar subordinados
    $sqlSubs = "SELECT id_om_menor FROM organizacoes_militares_sub WHERE id_om_maior = ?";
    $stmtSubs = $conexao->prepare($sqlSubs);
    $stmtSubs->bind_param("i", $batalhao_usuario);
    $stmtSubs->execute();
    $resultSubs = $stmtSubs->get_result();

    $batalhoesPermitidos = [$batalhao_usuario];

    while ($row = $resultSubs->fetch_assoc()) {
        $batalhoesPermitidos[] = (int)$row['id_om_menor'];
    }

    if (!in_array($batalhao_forn, $batalhoesPermitidos)) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'message' => 'Sem permissão para deletar este fornecedor'
        ]);
        exit;
    }

} else {
    // Nível 3 → somente o próprio batalhão
    if ($batalhao_forn !== $batalhao_usuario) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'message' => 'Sem permissão para deletar este fornecedor'
        ]);
        exit;
    }
}

// ================================
// DELETE PROTEGIDO
// ================================
$stmt_delete = $conexao->prepare("
    DELETE FROM fin_fornecedores 
    WHERE id = ? AND batalhao = ?
");

$stmt_delete->bind_param("ii", $id, $batalhao_forn);

if ($stmt_delete->execute()) {

    // NOVO TOKEN (após sucesso)
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

    $descricao = "Fornecedor ID $id deletado: Empresa: {$forn['nome_empresa']}, CNPJ: {$forn['cnpj_empresa']}, Data de Cadastro: {$forn['data_cadastro']}, Categoria: {$forn['categoria_empresa']}, Contato: {$forn['contato_nome']}, Número: {$forn['contato_numero']}, Email: {$forn['contato_email']}";

    registrar_log($conexao, $usuarioLogado, 'Deletar Fornecedor', $descricao, $id);

    echo json_encode(['success' => true]);

} else {
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao deletar: ' . $stmt_delete->error
    ]);
}

$stmt_delete->close();
?>