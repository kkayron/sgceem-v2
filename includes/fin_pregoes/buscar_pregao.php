<?php
// includes/fin_pregoes/buscar_pregao.php
session_start();
include '../../conexao/config.php';
$pagina_id = 24;

require_once('../api/seguranca_json.php');

header('Content-Type: application/json; charset=utf-8');

// ===============================================================
// DADOS DO USUÁRIO E BATALHÕES PERMITIDOS
// ===============================================================
$usuario          = $_SESSION['usuario'] ?? [];
$nivel_usuario    = $usuario['nivel'] ?? 3;
$batalhao_usuario = $usuario['batalhao'] ?? null;

if (!$batalhao_usuario) {
    echo json_encode(["erro" => "Batalhão do usuário não localizado."]);
    exit;
}

$batalhoesPermitidos = [];

if ($nivel_usuario == 1) {
    // ADMIN: vê tudo (sem filtro)
} 
else if ($nivel_usuario == 2) {
    // Nível 2: próprio batalhão + subordinados
    $batalhoesPermitidos[] = $batalhao_usuario;

    $sqlSubs = "SELECT id_om_menor FROM organizacoes_militares_sub WHERE id_om_maior = ?";
    $stmtSubs = $conexao->prepare($sqlSubs);
    $stmtSubs->bind_param("i", $batalhao_usuario);
    $stmtSubs->execute();
    $resultSubs = $stmtSubs->get_result();

    while ($row = $resultSubs->fetch_assoc()) {
        $batalhoesPermitidos[] = $row['id_om_menor'];
    }
} 
else {
    // Nível 3: vê apenas o próprio batalhão
    $batalhoesPermitidos[] = $batalhao_usuario;
}


// ===============================================================
// SE HÁ ID → BUSCA DETALHES DO PREGÃO + VALIDA PERMISSÃO
// ===============================================================
$id = intval($_GET['id'] ?? 0);

if ($id > 0) {

    // --------------------------------------------
    // Monta filtro por batalhão se NÃO for admin
    // --------------------------------------------
    $filtro = "";
    $params = [];
    $tipos  = "";

    if ($nivel_usuario != 1) {
        $placeholders = implode(",", array_fill(0, count($batalhoesPermitidos), "?"));
        $filtro = " AND fp.batalhao IN ($placeholders) ";
        $params = $batalhoesPermitidos;
        $tipos  = str_repeat("i", count($batalhoesPermitidos));
    }

    // --------------------------------------------
    // Buscar o pregão validando acesso
    // --------------------------------------------
    $sqlPregao = "
        SELECT 
            fp.*,
            om.nome AS nome_batalhao,
            om.abreviatura AS abreviatura_batalhao
        FROM fin_pregao fp
        LEFT JOIN organizacoes_militares om ON om.id = fp.batalhao
        WHERE fp.id = ?
        $filtro
    ";

    $stmtPregao = $conexao->prepare($sqlPregao);

    // Monta bind_param dinâmico
    if ($nivel_usuario == 1) {
        $stmtPregao->bind_param("i", $id);
    } else {
        $tipos = "i" . $tipos;
        $params = array_merge([$id], $params);
        $stmtPregao->bind_param($tipos, ...$params);
    }

    $stmtPregao->execute();
    $resPregao = $stmtPregao->get_result();

    if (!$resPregao || $resPregao->num_rows === 0) {
        echo json_encode([
            'sucesso'  => false,
            'mensagem' => 'Pregão não encontrado ou você não tem permissão para acessá-lo.'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $pregao = $resPregao->fetch_assoc();

    // -------------------------------------------------------------------------
    // Buscar itens do pregão (não precisa validar batalhão aqui, já validado acima)
    // -------------------------------------------------------------------------
    $stmtItens = $conexao->prepare("
        SELECT 
            pi.id,
            pi.nmr_item_pregao,
            pi.id_fornecedor,
            f.nome_empresa AS fornecedor_nome,
            pi.descricao_item,
            pi.saldo_item,
            pi.valor_unt,
            pi.valor_total
        FROM fin_pregao_itens pi
        JOIN fin_fornecedores f ON f.id = pi.id_fornecedor
        WHERE pi.id_pregao = ?
        ORDER BY pi.nmr_item_pregao ASC
    ");
    $stmtItens->bind_param("i", $id);
    $stmtItens->execute();
    $resItens = $stmtItens->get_result();

    $listaItens = [];
    while ($row = $resItens->fetch_assoc()) {
        $listaItens[] = $row;
    }

    echo json_encode([
        'sucesso' => true,
        'pregao'  => $pregao,
        'itens'   => $listaItens
    ], JSON_UNESCAPED_UNICODE);
    exit;
}



// ===============================================================
// SEM ID → LISTAR PREGÕES APENAS DOS BATALHÕES PERMITIDOS
// ===============================================================
if ($nivel_usuario == 1) {

    // Admin → lista tudo
    $sql = "
        SELECT id, nmr_pregao, ano_pregao, tipo_pregao 
        FROM fin_pregao 
        ORDER BY id DESC
    ";
    $stmt = $conexao->prepare($sql);

} else {

    $placeholders = implode(",", array_fill(0, count($batalhoesPermitidos), "?"));
    $tipos        = str_repeat("i", count($batalhoesPermitidos));

    $sql = "
        SELECT id, nmr_pregao, ano_pregao, tipo_pregao 
        FROM fin_pregao
        WHERE batalhao IN ($placeholders)
        ORDER BY id DESC
    ";

    $stmt = $conexao->prepare($sql);
    $stmt->bind_param($tipos, ...$batalhoesPermitidos);
}

$stmt->execute();
$res = $stmt->get_result();

$retorno = [];
while ($row = $res->fetch_assoc()) {
    $retorno[] = [
        'id'   => $row['id'],
        'nome' => "Pregão {$row['nmr_pregao']}/{$row['ano_pregao']} — {$row['tipo_pregao']}"
    ];
}

echo json_encode($retorno, JSON_UNESCAPED_UNICODE);
