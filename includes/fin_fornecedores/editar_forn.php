<?php
header('Content-Type: application/json');
session_start();

$pagina_id = 21;

require_once('../api/seguranca_json_editar.php');

include '../../conexao/config.php';
include '../funcoes/log_os.php';

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

// 🔒 DADOS USUÁRIO
$usuarioLogado    = $_SESSION['usuario_id'];
$nivel_usuario    = $_SESSION['usuario']['nivel'] ?? 3;
$batalhao_usuario = $_SESSION['usuario']['batalhao'] ?? 0;

// 🔒 ID SEGURO
$id_forn = intval($_POST['id_forn'] ?? 0);

if ($id_forn <= 0) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'ID inválido']);
    exit;
}

// ================================
// BUSCAR FORNECEDOR (SEGURO)
// ================================
$stmt_select = $conexao->prepare("SELECT * FROM fin_fornecedores WHERE id = ?");
$stmt_select->bind_param("i", $id_forn);
$stmt_select->execute();
$result = $stmt_select->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Fornecedor não encontrado']);
    exit;
}

$old_os = $result->fetch_assoc();
$stmt_select->close();

$batalhao_forn = (int)$old_os['batalhao'];

// ================================
// 🔒 VALIDAÇÃO POR NÍVEL
// ================================
if ($nivel_usuario == 1) {
    // Admin → acesso total

} elseif ($nivel_usuario == 2) {

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
            'status' => 'erro',
            'mensagem' => 'Sem permissão para editar este fornecedor'
        ]);
        exit;
    }

} else {
    // Nível 3
    if ($batalhao_forn !== $batalhao_usuario) {
        http_response_code(403);
        echo json_encode([
            'status' => 'erro',
            'mensagem' => 'Sem permissão para editar este fornecedor'
        ]);
        exit;
    }
}

// ================================
// UPDATE
// ================================
$update = $conexao->prepare("
  UPDATE fin_fornecedores 
  SET categoria_empresa = ?, nome_empresa = ?, cnpj_empresa = ?, 
      contato_nome = ?, contato_numero = ?, contato_email = ?
  WHERE id = ? AND batalhao = ?
");

if (!$update) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Erro no prepare: ' . $conexao->error]);
    exit;
}

// Dados novos
$categoria_empresa = $_POST['categoria_empresa'] ?? '';
$nome_empresa      = $_POST['nome_empresa'] ?? '';
$cnpj_empresa      = $_POST['cnpj_empresa'] ?? '';
$contato_nome      = $_POST['contato_nome'] ?? '';
$contato_numero    = $_POST['contato_numero'] ?? '';
$contato_email     = $_POST['contato_email'] ?? '';

// 🔒 bind com proteção por batalhão
$update->bind_param(
    'ssssssii',
    $categoria_empresa,
    $nome_empresa,
    $cnpj_empresa,
    $contato_nome,
    $contato_numero,
    $contato_email,
    $id_forn,
    $batalhao_forn
);

if (!$update->execute()) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Erro ao atualizar: ' . $update->error]);
    exit;
}

// ================================
// LOG DE ALTERAÇÕES
// ================================
$alteracoes = [];

$novos_valores = [
    'categoria_empresa' => $categoria_empresa,
    'nome_empresa'      => $nome_empresa,
    'cnpj_empresa'      => $cnpj_empresa,
    'contato_nome'      => $contato_nome,
    'contato_numero'    => $contato_numero,
    'contato_email'     => $contato_email
];

foreach ($novos_valores as $campo => $novo_valor) {
    $valor_antigo = $old_os[$campo] ?? '';
    if ($novo_valor != $valor_antigo) {
        $alteracoes[] = ucfirst($campo) . ": '$valor_antigo' → '$novo_valor'";
    }
}

if (!empty($alteracoes)) {
    $descricao = 'Alterações no fornecedor ID ' . $id_forn . ': ' . implode('; ', $alteracoes);
    registrar_log($conexao, $usuarioLogado, 'Editar Fornecedor', $descricao);
}

// 🔒 NOVO TOKEN
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));

echo json_encode([
    'status' => 'sucesso',
    'mensagem' => 'Alterações realizadas com sucesso.'
]);
?>