<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
include_once("../../conexao/config.php");
include_once("../../includes/funcoes/log.php");

// helper
function post(string $key) {
    return isset($_POST[$key]) ? trim($_POST[$key]) : null;
}

$usuarioLogado = $_SESSION['usuario_id'] ?? 0;

// campos principais
$id_empenho          = post('id_empenho');
$data_entrega_limite = post('data_entrega_limite');
$status              = post('status');
$batalhao              = post('batalhao');
$empresa_nome        = post('empresa_nome');
$empresa_cnpj        = post('empresa_cnpj');
$empresa_email       = post('empresa_email');
$local_entrega       = post('local_entrega');
$nome_responsavel    = post('nome_responsavel');
$contato_responsavel = post('contato_responsavel');
$observacao_final    = post('observacao_final');
$data_cadastro       = post('data_cadastro');

// pedidos vinculados (array de ids)
$pedidos = $_POST['pedidos'] ?? [];

if (
    !$data_entrega_limite ||
    !$status ||
    !$empresa_nome ||
    !$empresa_cnpj ||
    !$local_entrega ||
    !$nome_responsavel ||
    !is_array($pedidos) ||
    count($pedidos) === 0
) {
    echo json_encode([
        'status'  => 'erro',
        'mensagem'=> 'Preencha todos os campos obrigatórios e selecione ao menos um pedido.'
    ]);
    exit;
}

// 🔒 Verificar duplicidade no envio do formulário
if (count($pedidos) !== count(array_unique($pedidos))) {
    echo json_encode([
        'status'  => 'erro',
        'mensagem'=> 'Não é permitido repetir o mesmo pedido na mesma ordem.'
    ]);
    exit;
}

// 🔒 Verificar se algum pedido já está vinculado em outra ordem
$placeholders = implode(',', array_fill(0, count($pedidos), '?'));
$tipos = str_repeat('i', count($pedidos));

$sqlVerifica = "SELECT id_pedido FROM fin_ordemforn_pedidos WHERE id_pedido IN ($placeholders)";
$stmtVerifica = $conexao->prepare($sqlVerifica);
$stmtVerifica->bind_param($tipos, ...$pedidos);
$stmtVerifica->execute();
$resVerifica = $stmtVerifica->get_result();

$jaVinculados = [];
while ($row = $resVerifica->fetch_assoc()) {
    $jaVinculados[] = $row['id_pedido'];
}
$stmtVerifica->close();

if (!empty($jaVinculados)) {
    echo json_encode([
        'status'  => 'erro',
        'mensagem'=> 'Os seguintes pedidos já estão vinculados a uma ordem: ' . implode(', ', $jaVinculados)
    ]);
    exit;
}

// --------------------------
    // Função para buscar responsável do batalhão selecionado
    // --------------------------
    function buscarResponsavel($conexao, $funcao, $batalhao) {
        $sql = "SELECT nomecompleto, postograd 
                FROM usuarios 
                WHERE funcao = ? AND batalhao = ? AND status = 'sim' 
                LIMIT 1";
        $stmt = $conexao->prepare($sql);
        $stmt->bind_param("si", $funcao, $batalhao);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($user = $result->fetch_assoc()) {
            return $user['nomecompleto'] . " - " . $user['postograd'];
        }
        return "Não há usuário cadastrado com a função neste batalhão";
    }

    // --------------------------
    // Buscar responsáveis do batalhão
    // --------------------------
    // Atenção: aqui você passou códigos ('8','9','10') em chamadas anteriores — mantenho como estava.
    $cmt_ceem      = buscarResponsavel($conexao, '8', $batalhao);
    $ch_suprimento = buscarResponsavel($conexao, '10', $batalhao);
    $ch_controle   = buscarResponsavel($conexao, '9', $batalhao);

// 1) inserir ordem principal
$stmt = $conexao->prepare("
    INSERT INTO fin_ordemforn (
      id_empenho,
      data_cadastro,
      data_entrega_limite,
      batalhao,
      status,
      empresa_nome,
      empresa_cnpj,
      empresa_email,
      local_entrega,
      nome_responsavel,
      contato_responsavel,
      observacao_final,
      cmt_ceem,
      ch_controle,
      ch_suprimento
    ) VALUES (
      ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
    )
");
$stmt->bind_param(
    "issssssssssssss",
    $id_empenho,
    $data_cadastro,
    $data_entrega_limite,
    $batalhao,
    $status,
    $empresa_nome,
    $empresa_cnpj,
    $empresa_email,
    $local_entrega,
    $nome_responsavel,
    $contato_responsavel,
    $observacao_final,
    $cmt_ceem,
    $ch_controle,
    $ch_suprimento
);

if (! $stmt->execute()) {
    echo json_encode([
        'status'  => 'erro',
        'mensagem'=> 'Falha ao cadastrar ordem: ' . $stmt->error
    ]);
    exit;
}
$id_ordem = $stmt->insert_id;
$stmt->close();

// 2) vincular pedidos
$stmtLink = $conexao->prepare("
    INSERT INTO fin_ordemforn_pedidos (id_pedido, id_ordemforn)
    VALUES (?, ?)
");
foreach ($pedidos as $pid) {
    $pid = (int)$pid;
    if ($pid > 0) {
        $stmtLink->bind_param("ii", $pid, $id_ordem);
        $stmtLink->execute();
    }
}
$stmtLink->close();

// 3) registrar log
$descricao = "Ordem #{$id_ordem} criada vinculando pedidos: [" . implode(", ", $pedidos) . "]";
registrar_log($conexao, $usuarioLogado, 'Cadastrar Ordem de Fornecimento', $descricao, $id_ordem);

// sucesso
echo json_encode([
    'status'   => 'sucesso',
    'mensagem' => "Ordem de fornecimento #{$id_ordem} cadastrada com sucesso!"
]);
