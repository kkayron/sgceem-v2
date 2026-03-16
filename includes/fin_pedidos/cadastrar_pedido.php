<?php
session_start();
include_once("../../conexao/config.php");
include_once("../../includes/funcoes/log_pedido_financeiro.php");

// ============================
// CONFIGURAÇÕES GERAIS
// ============================
header('Content-Type: application/json; charset=UTF-8');
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
error_reporting(E_ALL);
ini_set('display_errors', 0);

// ============================
// FUNÇÃO DE SANITIZAÇÃO
// ============================
function post($key) {
    return isset($_POST[$key]) ? trim($_POST[$key]) : null;
}

// ============================
// VARIÁVEIS DA SESSÃO E POST
// ============================
$usuario_id = $_SESSION['usuario_id'] ?? null;
$id_om_usuario = $_SESSION['usuario']['batalhao'] ?? null;
$nivel_usuario = $_SESSION['usuario']['nivel'] ?? 3;

$solicitante = post('solicitante');
$secao_rspns = post('secao_rspns');
$autorizacao = 'nao';
$situacao_pedido = post('situacao_pedido');
$data_pedido = post('data_pedido');
$id_vtr = post('id_vtr');
$desconto_empenho = post('desconto_empenho');
$local_pedido = post('local_pedido');
$batalhao = post('batalhao');
$id_os = post('id_os');


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

// ============================
// REGRAS DE BATALHÃO
// ============================
// Se o usuário não for nível 1 (geral), força o batalhão da sessão
if ($nivel_usuario != 1) {
    $batalhao = $id_om_usuario;
}

try {
    // ============================
    // VERIFICA SE OS, LOCAL, VIATURA E BATALHÃO SÃO DA MESMA OM
    // ============================
    $batalhao_os = null;
    $batalhao_local = null;
    $batalhao_vtr = null;

    // Busca batalhão da OS
    if (!empty($id_os) && $id_os !== 'Sem OS') {
        $sql_os = "SELECT batalhao FROM os_principal WHERE id = ?";
        $stmt_os = $conexao->prepare($sql_os);
        $stmt_os->bind_param("s", $id_os);
        $stmt_os->execute();
        $res_os = $stmt_os->get_result();
        if ($res_os->num_rows > 0) {
            $batalhao_os = $res_os->fetch_assoc()['batalhao'];
        }
    }

    // Busca batalhão do local do pedido
    if (!empty($local_pedido)) {
        $sql_local = "SELECT batalhao FROM config_destinos WHERE destino = ? LIMIT 1";
        $stmt_local = $conexao->prepare($sql_local);
        $stmt_local->bind_param("s", $local_pedido);
        $stmt_local->execute();
        $res_local = $stmt_local->get_result();
        if ($res_local->num_rows > 0) {
            $batalhao_local = $res_local->fetch_assoc()['batalhao'];
        }
    }

    // Busca batalhão da viatura
    if (!empty($id_vtr)) {
        $sql_vtr = "SELECT batalhao FROM frota WHERE id = ?";
        $stmt_vtr = $conexao->prepare($sql_vtr);
        $stmt_vtr->bind_param("s", $id_vtr);
        $stmt_vtr->execute();
        $res_vtr = $stmt_vtr->get_result();
        if ($res_vtr->num_rows > 0) {
            $batalhao_vtr = $res_vtr->fetch_assoc()['batalhao'];
        }
    }

    // Verifica se todos pertencem à mesma OM
    $batalhoes = array_filter([$batalhao, $batalhao_os, $batalhao_local, $batalhao_vtr]);
    if (count(array_unique($batalhoes)) > 1) {
        echo json_encode([
            "status" => "erro",
            "mensagem" => "O batalhão da OS, do Local, da Viatura e o selecionado devem pertencer à mesma Organização Militar."
        ]);
        exit;
    }

    // ============================
    // INSERÇÃO DO PEDIDO
    // ============================
    $sql = "INSERT INTO fin_pedidos_forn (
        id_os, autorizacao, batalhao, cmt_ceem, ch_controle, ch_suprimento, local_pedido, solicitante, secao_rspns, situacao_pedido, data_pedido, id_vtr, desconto_empenho
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

    $stmt = $conexao->prepare($sql);
    $stmt->bind_param(
        "ssssssssssssd",
        $id_os,
        $autorizacao,
        $batalhao,
        $cmt_ceem,
        $ch_controle,
        $ch_suprimento,
        $local_pedido,
        $solicitante,
        $secao_rspns,
        $situacao_pedido,
        $data_pedido,
        $id_vtr,
        $desconto_empenho
    );
    $stmt->execute();

    $id_pedido = $stmt->insert_id;

    // ============================
    // LOG
    // ============================
    registrar_log_financeiro(
    $conexao,
    $usuario_id,
    'CADASTRAR PEDIDO',
    "Novo pedido cadastrado. ID: {$id_pedido}",
    $id_vtr ?: null,        // frota_id (opcional)
    $id_pedido,  // pedido_financeiro_id
    $id_os ?: null
);


    // ============================
    // INSERE ITENS DO PEDIDO
    // ============================
    if (!empty($_POST['itens']) && is_array($_POST['itens'])) {
        $sql_item = "INSERT INTO fin_pedidos_forn_itens (
            id_principal, codigo_item, descricao_item, quant_solicitada, und_solicitada, almox_possui, valor_unt, valor_total
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt_item = $conexao->prepare($sql_item);

        foreach ($_POST['itens'] as $item) {
            $codigo_item = $item['codigo_item'] ?? '';
            $descricao_item = $item['descricao_item'] ?? '';
            $quant_solicitada = floatval($item['quant_solicitada'] ?? 0);
            $und_solicitada = $item['und_solicitada'] ?? '';
            $almox_possui = $item['almox_possui'] ?? 0;
            $valor_unt = floatval($item['valor_unt'] ?? 0);
            $valor_total = floatval($item['valor_total'] ?? 0);

            $stmt_item->bind_param(
                "issdssdd",
                $id_pedido,
                $codigo_item,
                $descricao_item,
                $quant_solicitada,
                $und_solicitada,
                $almox_possui,
                $valor_unt,
                $valor_total
            );
            $stmt_item->execute();
        }
    }

    echo json_encode([
        "status" => "sucesso",
        "mensagem" => "Pedido cadastrado com sucesso!",
        "id_pedido" => $id_pedido
    ]);

} catch (mysqli_sql_exception $e) {
    echo json_encode([
        "status" => "erro",
        "mensagem" => "Erro no banco de dados: " . $e->getMessage()
    ]);
    exit;
} catch (Exception $e) {
    echo json_encode([
        "status" => "erro",
        "mensagem" => "Erro inesperado: " . $e->getMessage()
    ]);
    exit;
}
?>
