<?php
session_start();
header('Content-Type: application/json');
$pagina_id = 14;
include_once('../../conexao/config.php');
include_once('../../includes/funcoes/log.php');

require_once('../api/seguranca_json_editar.php');
require_once('../api/batalhoes_permitidos.php');


$nivel_usuario    = $_SESSION['nivel_usuario'] ?? 3;
$batalhao_usuario = $_SESSION['batalhao'] ?? 0;

try {
    $batalhoesPermitidos = obterBatalhoesPermitidos($conexao, $nivel_usuario, $batalhao_usuario);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro ao validar permissões']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'status' => 'erro',
        'mensagem' => 'Método inválido'
    ]);
    exit;
}

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

$id = $_POST['id'] ?? null;

if (!$id) {
    echo json_encode([
        'status' => 'erro',
        'mensagem' => 'ID inválido'
    ]);
    exit;
}

// Buscar dados antigos
$stmt_antigo = $conexao->prepare("SELECT * FROM frota WHERE id = ?");
$stmt_antigo->bind_param("i", $id);
$stmt_antigo->execute();
$frota_antiga = $stmt_antigo->get_result()->fetch_assoc();
$stmt_antigo->close();

// Campos da frota
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

// Upload de foto
$foto_nome = null;

if (isset($_FILES['foto_capa']) && $_FILES['foto_capa']['error'] === 0) {

    $limite_tamanho = 10 * 1024 * 1024;
    if ($_FILES['foto_capa']['size'] > $limite_tamanho) {
        echo json_encode([
            'status' => 'erro',
            'mensagem' => 'Imagem maior que 10MB'
        ]);
        exit;
    }

    $ext_permitidas = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    $ext = strtolower(pathinfo($_FILES['foto_capa']['name'], PATHINFO_EXTENSION));

    if (!in_array($ext, $ext_permitidas)) {
        echo json_encode([
            'status' => 'erro',
            'mensagem' => 'Extensão não permitida'
        ]);
        exit;
    }

    $mime = mime_content_type($_FILES['foto_capa']['tmp_name']);
    $mime_permitidos = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

    if (!in_array($mime, $mime_permitidos)) {
        echo json_encode([
            'status' => 'erro',
            'mensagem' => 'Arquivo inválido'
        ]);
        exit;
    }

    $foto_nome = uniqid('ft_') . '.' . $ext;

    if (!move_uploaded_file($_FILES['foto_capa']['tmp_name'], '../../uploads/frotas/' . $foto_nome)) {
        echo json_encode([
            'status' => 'erro',
            'mensagem' => 'Erro ao salvar imagem'
        ]);
        exit;
    }
}

// Monta query
$sql = "UPDATE frota SET ";
$updates = [];
$tipos = '';
$params = [];

foreach ($valores as $campo => $valor) {
    $updates[] = "$campo=?";
    $tipos .= 's';
    $params[] = $valor;
}

if ($foto_nome) {
    $updates[] = "foto_capa=?";
    $tipos .= 's';
    $params[] = $foto_nome;
}

$sql .= implode(', ', $updates) . " WHERE id=?";
$tipos .= 'i';
$params[] = $id;

$stmt = $conexao->prepare($sql);

if (!$stmt) {
    echo json_encode([
        'status' => 'erro',
        'mensagem' => 'Erro ao preparar query'
    ]);
    exit;
}

$stmt->bind_param($tipos, ...$params);

if ($stmt->execute()) {

    // Log
    $usuarioLogado = $_SESSION['usuario_id'] ?? 0;
    $alteracoes = [];

    foreach ($valores as $campo => $valor) {
        if (isset($frota_antiga[$campo]) && $valor != $frota_antiga[$campo]) {
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

    echo json_encode([
        'status' => 'ok',
        'mensagem' => 'Frota atualizada com sucesso'
    ]);

} else {

    echo json_encode([
        'status' => 'erro',
        'mensagem' => 'Erro ao atualizar frota'
    ]);

}

$stmt->close();
?>