<?php
session_start();

header('Content-Type: application/json');

include_once('../../conexao/config.php');
include_once('../../includes/funcoes/log.php');

require_once('../api/batalhoes_permitidos.php');


// ==========================
// VALIDAÇÃO MÉTODO
// ==========================
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

    echo json_encode([
        'status' => 'erro',
        'mensagem' => 'Método inválido'
    ]);

    exit;
}


// ==========================
// CSRF
// ==========================
if (
    empty($_POST['csrf_token']) ||
    empty($_SESSION['csrf_token']) ||
    !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
) {

    http_response_code(403);

    echo json_encode([
        'status' => 'erro',
        'mensagem' => 'Token inválido'
    ]);

    exit;
}


// ==========================
// DADOS
// ==========================
$id                 = intval($_POST['id'] ?? 0);
$acao               = trim($_POST['acao'] ?? '');
$batalhao_destino = intval(
    $_POST['batalhao_destino']
    ?? $_POST['batalhao_origem']
    ?? 0
);
$observacoes        = trim($_POST['observacoes'] ?? '');

$usuario_id         = $_SESSION['usuario_id'] ?? 0;
$batalhao_usuario   = $_SESSION['batalhao'] ?? 0;


if (!$id || !$batalhao_destino) {

    echo json_encode([
        'status' => 'erro',
        'mensagem' => 'Dados inválidos'
    ]);

    exit;
}


// ==========================
// BUSCAR FROTA
// ==========================
$stmt = $conexao->prepare("
    SELECT *
    FROM frota
    WHERE id = ?
");

$stmt->bind_param("i", $id);
$stmt->execute();

$frota = $stmt->get_result()->fetch_assoc();

$stmt->close();

if (!$frota) {

    echo json_encode([
        'status' => 'erro',
        'mensagem' => 'Ativo não encontrado'
    ]);

    exit;
}


// ==========================
// REGRAS
// ==========================
$batalhao_atual   = (int)$frota['batalhao'];
$batalhao_origem  = (int)$frota['batalhao_origem'];


// Primeira vez sem origem cadastrada
if (empty($batalhao_origem)) {
    $batalhao_origem = $batalhao_atual;
}


// ==========================
// EMPRÉSTIMO
// ==========================
if ($acao === 'emprestimo') {

    // Somente proprietário empresta
    if ($batalhao_atual != $batalhao_origem) {

        echo json_encode([
            'status' => 'erro',
            'mensagem' => 'Somente o batalhão proprietário pode emprestar'
        ]);

        exit;
    }

    if ($batalhao_destino == $batalhao_atual) {

        echo json_encode([
            'status' => 'erro',
            'mensagem' => 'Selecione outro batalhão'
        ]);

        exit;
    }

    $novo_batalhao = $batalhao_destino;

    $situacao = 'Empréstimo de ativo';

}


// ==========================
// DEVOLUÇÃO
// ==========================
elseif ($acao === 'devolucao') {

    // devolve para origem
    $novo_batalhao = $batalhao_origem;

    $situacao = 'Devolução de ativo';

} else {

    echo json_encode([
        'status' => 'erro',
        'mensagem' => 'Ação inválida'
    ]);

    exit;
}


// ==========================
// UPDATE FROTA
// ==========================
$stmt = $conexao->prepare("
    UPDATE frota
    SET
        batalhao = ?,
        batalhao_origem = ?
    WHERE id = ?
");

$stmt->bind_param(
    "iii",
    $novo_batalhao,
    $batalhao_origem,
    $id
);

$ok = $stmt->execute();

$stmt->close();

if (!$ok) {

    echo json_encode([
        'status' => 'erro',
        'mensagem' => 'Erro ao atualizar frota'
    ]);

    exit;
}


// ==========================
// HISTÓRICO
// ==========================
$stmtHist = $conexao->prepare("
    INSERT INTO historico_emprestimo
    (
        data_execucao,
        batalhao_origem,
        batalhao_destino,
        situacao,
        observacoes
    )
    VALUES
    (
        NOW(),
        ?,
        ?,
        ?,
        ?
    )
");

$stmtHist->bind_param(
    "iiss",
    $batalhao_atual,
    $novo_batalhao,
    $situacao,
    $observacoes
);

$stmtHist->execute();

$stmtHist->close();


// ==========================
// LOG
// ==========================
registrar_log(
    $conexao,
    $usuario_id,
    $situacao,
    "Ativo ID {$id} transferido de {$batalhao_atual} para {$novo_batalhao}",
    $id
);


// ==========================
// SUCESSO
// ==========================
echo json_encode([
    'status' => 'ok',
    'mensagem' => $situacao . ' realizado com sucesso'
]);
?>