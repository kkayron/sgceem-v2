<?php
session_start();
header('Content-Type: application/json');
$pagina_id = 24;
require_once('../api/seguranca_json_cadastrar.php');

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

include_once("../../conexao/config.php");
include_once("../../includes/funcoes/log.php");

// Função para validar e obter campo
function post($key) {
    return isset($_POST[$key]) ? trim($_POST[$key]) : null;
}

$aberta_por = $_SESSION['usuario_id'] ?? null;

$data_homologacao = post('data_homologacao');
$data_validade = post('data_validade');
$tipo_pregao = post('tipo_pregao');
$nmr_pregao = post('nmr_pregao');
$batalhao = post('batalhao');
$descricao_pregao = post('descricao_pregao');
$ano_pregao = post('ano_pregao');
$ug_licitacao = post('ug_licitacao');
$uasg_licitacao = post('uasg_licitacao');
$nup_licitacao = post('nup_licitacao');
$continuidade_pregao = post('continuidade_pregao');

// 1. Verificar duplicidade por número, ano, UASG E batalhão
$sqlVerificaPregao = "
    SELECT id 
    FROM fin_pregao 
    WHERE nmr_pregao = ? 
      AND ano_pregao = ? 
      AND uasg_licitacao = ?
      AND batalhao = ?
";

$stmtVerifica = $conexao->prepare($sqlVerificaPregao);
$stmtVerifica->bind_param("ssss", $nmr_pregao, $ano_pregao, $uasg_licitacao, $batalhao);
$stmtVerifica->execute();
$stmtVerifica->store_result();

if ($stmtVerifica->num_rows > 0) {
    echo json_encode([
        "status" => "erro",
        "mensagem" => "Já existe um pregão {$nmr_pregao}/{$ano_pregao} para a UASG {$uasg_licitacao} neste mesmo batalhão."
    ]);
    exit;
}
$stmtVerifica->close();

// 2. Verificar duplicidade de NUP
$sqlVerificaNup = "SELECT id FROM fin_pregao WHERE nup_licitacao = ?";
$stmtVerificaNup = $conexao->prepare($sqlVerificaNup);
$stmtVerificaNup->bind_param("s", $nup_licitacao);
$stmtVerificaNup->execute();
$stmtVerificaNup->store_result();

if ($stmtVerificaNup->num_rows > 0) {
    echo json_encode([
        "status" => "erro",
        "mensagem" => "Já existe um pregão cadastrado com o NUP {$nup_licitacao}."
    ]);
    exit;
}
$stmtVerificaNup->close();

// Inserir o pregão
$sql = "INSERT INTO fin_pregao (
    descricao_pregao, batalhao, nmr_pregao, ano_pregao, ug_licitacao, uasg_licitacao, nup_licitacao, 
    tipo_pregao, data_homologacao, data_validade, continuidade_pregao
) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

$stmt = $conexao->prepare($sql);
if (!$stmt) {
    echo json_encode([
        "status" => "erro",
        "mensagem" => "Erro ao preparar a inserção: " . $conexao->error
    ]);
    exit;
}

$stmt->bind_param(
    "sssssssssss",
    $descricao_pregao,
    $batalhao,
    $nmr_pregao,
    $ano_pregao,
    $ug_licitacao,
    $uasg_licitacao,
    $nup_licitacao,
    $tipo_pregao,
    $data_homologacao,
    $data_validade,
    $continuidade_pregao
);

if ($stmt->execute()) {
    $id_pregao = $stmt->insert_id;

    $descricao = "Novo pregão cadastrado: {$nmr_pregao}/{$ano_pregao}";
    registrar_log($conexao, $aberta_por, 'Cadastrar pregão', $descricao);

    // Verifica se há itens JSON enviados
    if (isset($_POST['itens_json'])) {
        $itens = json_decode($_POST['itens_json'], true);

        if (is_array($itens)) {
            $sql_item = "INSERT INTO fin_pregao_itens (
                id_fornecedor, id_pregao, descricao_item, saldo_item, valor_unt, valor_total, nmr_item_pregao
            ) VALUES (?, ?, ?, ?, ?, ?, ?)";
            $stmt_item = $conexao->prepare($sql_item);
            if (!$stmt_item) {
                echo json_encode([
                    "status" => "erro",
                    "mensagem" => "Erro ao preparar inserção de item: " . $conexao->error
                ]);
                exit;
            }

            foreach ($itens as $item) {
                $id_fornecedor = $item['id_fornecedor'] ?? null;
                $descricao_item = $item['descricao_item'] ?? null;
                $saldo_item = $item['saldo_item'] ?? null;
                $valor_unt = $item['valor_unt'] ?? null;
                $valor_total = $item['valor_total'] ?? null;
                $nmr_item_pregao = $item['nmr_item_pregao'] ?? null;

                $stmt_item->bind_param(
                    'iissdds',
                    $id_fornecedor,
                    $id_pregao,
                    $descricao_item,
                    $saldo_item,
                    $valor_unt,
                    $valor_total,
                    $nmr_item_pregao
                );

                $stmt_item->execute();
            }
        }
    }
	
		// 🔒 NOVO TOKEN
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
	
    echo json_encode([
        "status" => "sucesso",
        "mensagem" => "Pregão cadastrado com sucesso!"
    ]);

} else {
    echo json_encode([
        "status" => "erro",
        "mensagem" => "Erro ao salvar pregão: " . $stmt->error
    ]);
}
?>
