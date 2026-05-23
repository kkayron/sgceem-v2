<?php
session_start();

header('Content-Type: application/json; charset=utf-8');

$pagina_id = 14;

require_once '../../conexao/config.php';
require_once "../../includes/funcoes/log.php";
require_once '../api/seguranca_json_editar.php';
require_once '../api/batalhoes_permitidos.php';

// 🔒 AMBIENTE
define('ENVIRONMENT', 'prod'); // 'dev' ou 'prod'

// 🔒 MÉTODO
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    exit;
}

// 🔒 CSRF
if (
    empty($_POST['csrf_token']) ||
    empty($_SESSION['csrf_token']) ||
    !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Token inválido']);
    exit;
}

// 🔒 DADOS DO USUÁRIO
$nivel_usuario    = $_SESSION['usuario']['nivel'] ?? 3;
$batalhao_usuario = $_SESSION['usuario']['batalhao'] ?? 0;
$usuarioLogado    = (int)($_SESSION['usuario_id'] ?? 0);

// 🔒 BATALHÕES PERMITIDOS
try {
    $batalhoesPermitidos = obterBatalhoesPermitidos($conexao, $nivel_usuario, $batalhao_usuario);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro ao validar permissões']);
    exit;
}

ob_start();

// 🔒 TRATAMENTO GLOBAL
register_shutdown_function(function () {
    $err = error_get_last();

    if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {

        if (ob_get_length()) ob_clean();

        $debug = (ENVIRONMENT === 'dev')
            ? $err['message'] . ' em ' . $err['file'] . ':' . $err['line']
            : null;

        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Erro fatal no servidor.',
            'debug'   => $debug
        ]);
    }
});

// 🔒 ID
$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;

if ($id <= 0) {
    if (ob_get_length()) ob_clean();
    echo json_encode(['success' => false, 'message' => 'ID inválido']);
    exit;
}

try {
    $conexao->begin_transaction();

    // ==========================
    // 🔍 BUSCAR FROTA COM PERMISSÃO
    // ==========================
    if ($batalhoesPermitidos === null) {

        $stmt = $conexao->prepare("SELECT * FROM frota WHERE id = ?");

        $stmt->bind_param("i", $id);

    } else {

        $placeholders = implode(',', array_fill(0, count($batalhoesPermitidos), '?'));
        $types = str_repeat('i', count($batalhoesPermitidos) + 1);
        $params = array_merge([$id], $batalhoesPermitidos);

        $sql = "SELECT * FROM frota WHERE id = ? AND batalhao IN ($placeholders)";

        $stmt = $conexao->prepare($sql);
        $stmt->bind_param($types, ...$params);
    }

    if (!$stmt) throw new Exception("Erro ao preparar SELECT");

    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        $stmt->close();
        $conexao->rollback();
        if (ob_get_length()) ob_clean();
        echo json_encode(['success' => false, 'message' => 'Frota não encontrada ou sem permissão']);
        exit;
    }

    $frota_antiga = $result->fetch_assoc();
    $stmt->close();

    // ==========================
    // CAMPOS
    // ==========================
    $campos = [
        'ativo','tipo','prefixo_velho','prefixo_sga','nome_sioc',
        'nmr_patrimonio','nmr_eb','chassi','acervo','marca',
        'modelo','ano','confiabilidade','obs_encmat',
        'capac_tanque','consumo','missao','emprego_atual',
        'ordem_fragmentaria','placa','subunidade','renavam','trem',
        'destino','disponibilidade'
    ];

    $valores = [];

    foreach ($campos as $campo) {
        $valores[$campo] = $_POST[$campo] ?? null;
    }

    // ==========================
    // UPLOAD
    // ==========================
    $foto_nome = null;

    if (isset($_FILES['foto_capa']) && $_FILES['foto_capa']['error'] === 0) {

        if ($_FILES['foto_capa']['size'] > 10 * 1024 * 1024) {
            throw new Exception("Imagem excede 10MB");
        }

        $ext = strtolower(pathinfo($_FILES['foto_capa']['name'], PATHINFO_EXTENSION));
        $permitidas = ['jpg','jpeg','png','gif','webp'];

        if (!in_array($ext, $permitidas)) {
            throw new Exception("Extensão inválida");
        }

        $mime = mime_content_type($_FILES['foto_capa']['tmp_name']);
        $mimes = ['image/jpeg','image/png','image/gif','image/webp'];

        if (!in_array($mime, $mimes)) {
            throw new Exception("Arquivo inválido");
        }

        $foto_nome = uniqid('ft_') . '.' . $ext;

        $pasta = '../../uploads/frotas/';
        if (!is_dir($pasta)) mkdir($pasta, 0755, true);

        move_uploaded_file($_FILES['foto_capa']['tmp_name'], $pasta . $foto_nome);
    }

    // ==========================
    // UPDATE
    // ==========================
    $updates = [];
    $types = '';
    $params = [];

    foreach ($valores as $campo => $valor) {
        $updates[] = "$campo=?";
        $types .= 's';
        $params[] = $valor;
    }

    if ($foto_nome) {
        $updates[] = "foto_capa=?";
        $types .= 's';
        $params[] = $foto_nome;
    }

    $sql = "UPDATE frota SET " . implode(', ', $updates) . " WHERE id=?";

    $types .= 'i';
    $params[] = $id;

    $stmt = $conexao->prepare($sql);

    if (!$stmt) throw new Exception("Erro ao preparar UPDATE");

    $stmt->bind_param($types, ...$params);

    if (!$stmt->execute()) {
        throw new Exception("Erro ao atualizar");
    }

    $stmt->close();

    // ==========================
    // LOG
    // ==========================
    $alteracoes = [];

    foreach ($valores as $campo => $valor) {
        if ($valor != $frota_antiga[$campo]) {
            $alteracoes[] = ucfirst($campo) . ": '{$frota_antiga[$campo]}' → '$valor'";
        }
    }

    if ($foto_nome) {
        $alteracoes[] = "Foto capa atualizada";
    }

    if ($alteracoes) {
        $descricao = "Alterações na frota ID $id: " . implode("; ", $alteracoes);
        registrar_log($conexao, $usuarioLogado, 'Editar frota', $descricao, $id);
    }

    // 🔒 NOVO TOKEN
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

    $conexao->commit();

    if (ob_get_length()) ob_clean();
    echo json_encode(['success' => true, 'message' => 'Frota atualizada com sucesso']);
    exit;

} catch (Throwable $e) {

    $conexao->rollback();

    if (ob_get_length()) ob_clean();

    $debug = (ENVIRONMENT === 'dev') ? $e->getMessage() : null;

    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao atualizar frota.',
        'debug'   => $debug
    ]);
    exit;
}