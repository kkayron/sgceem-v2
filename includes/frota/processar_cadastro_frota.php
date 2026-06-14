<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

$pagina_id = 2;

include_once('../../conexao/config.php');
include_once('../../includes/funcoes/log.php');

require_once('../api/seguranca_json_cadastrar.php');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'status' => 'erro',
        'mensagem' => 'Método inválido'
    ]);
    exit;
}

// CSRF
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

// Validações básicas
if (empty($_POST['ativo'])) {
    echo json_encode([
        'status' => 'erro',
        'mensagem' => 'Campo "Ativo" é obrigatório.'
    ]);
    exit;
}

if (empty($_POST['batalhao'])) {
    echo json_encode([
        'status' => 'erro',
        'mensagem' => 'Campo "Batalhão" é obrigatório.'
    ]);
    exit;
}

// Dados
$batalhao = intval($_POST['batalhao']);
$ativo = $_POST['ativo'] ?? '';
$tipo = $_POST['tipo'] ?? '';
$prefixo_velho = $_POST['prefixo_velho'] ?? '';
$prefixo_sga = $_POST['prefixo_sga'] ?? '';
$nome_sioc = $_POST['nome_sioc'] ?? '';
$nmr_patrimonio = $_POST['nmr_patrimonio'] ?? '';
$nmr_eb = $_POST['nmr_eb'] ?? '';
$chassi = $_POST['chassi'] ?? '';
$acervo = $_POST['acervo'] ?? '';
$marca = $_POST['marca'] ?? '';
$modelo = $_POST['modelo'] ?? '';
$destino = $_POST['destino'] ?? '';
$ano = $_POST['ano'] ?? '';
$confiabilidade = $_POST['confiabilidade'] ?? '';
$obs_encmat = $_POST['obs_encmat'] ?? '';
$capacidade_tanque = $_POST['capacidade_tanque'] ?? '';
$consumo = $_POST['consumo'] ?? '';
$missao = $_POST['missao'] ?? '';
$emprego_atual = $_POST['emprego_atual'] ?? '';
$ordem_fragmentaria = $_POST['ordem_fragmentaria'] ?? '';
$placa = $_POST['placa'] ?? '';
$subunidade = $_POST['subunidade'] ?? '';
$disponibilidade = $_POST['disponibilidade'] ?? '';
$renavam = $_POST['renavam'] ?? '';
$trem = $_POST['trem'] ?? '';

// Upload da foto
$foto_capa_path = 'base.jpg';

if (isset($_FILES['foto_capa']) && $_FILES['foto_capa']['error'] === UPLOAD_ERR_OK) {

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

    $foto_capa_path = uniqid('ft_') . '.' . $ext;
    $pasta = '../../uploads/frotas/';

    if (!is_dir($pasta)) {
        mkdir($pasta, 0755, true);
    }

    if (!move_uploaded_file($_FILES['foto_capa']['tmp_name'], $pasta . $foto_capa_path)) {
        echo json_encode([
            'status' => 'erro',
            'mensagem' => 'Erro ao salvar imagem'
        ]);
        exit;
    }
}

$dataHoraAgora = (new DateTime('now', new DateTimeZone('America/Sao_Paulo')))->format('Y-m-d H:i:s');

$cadastrado_por = trim(
    ($_SESSION['postograd'] ?? '') . ' ' .
    ($_SESSION['nomeguerra'] ?? '')
);

$sql = "INSERT INTO frota (
    batalhao, foto_capa, disponibilidade, ativo, tipo, prefixo_velho, prefixo_sga, nome_sioc,
    nmr_patrimonio, nmr_eb, chassi, acervo, marca, modelo, ano,
    confiabilidade, obs_encmat, capac_tanque, consumo, missao,
    emprego_atual, ordem_fragmentaria, placa, subunidade, renavam, trem,
    data_inclusao, cadastrado_por, destino
) VALUES (
    ?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?
)";

$stmt = $conexao->prepare($sql);

if (!$stmt) {
    echo json_encode([
        'status' => 'erro',
        'mensagem' => 'Erro ao preparar query: ' . $conexao->error
    ]);
    exit;
}

$stmt->bind_param(
    "issssssssssssssssssssssssssss",
    $batalhao,
    $foto_capa_path,
    $disponibilidade,
    $ativo,
    $tipo,
    $prefixo_velho,
    $prefixo_sga,
    $nome_sioc,
    $nmr_patrimonio,
    $nmr_eb,
    $chassi,
    $acervo,
    $marca,
    $modelo,
    $ano,
    $confiabilidade,
    $obs_encmat,
    $capacidade_tanque,
    $consumo,
    $missao,
    $emprego_atual,
    $ordem_fragmentaria,
    $placa,
    $subunidade,
    $renavam,
    $trem,
    $dataHoraAgora,
    $cadastrado_por,
    $destino
);

if ($stmt->execute()) {

    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

    $usuarioLogado = $_SESSION['usuario_id'] ?? 0;
    $id_frota = $stmt->insert_id;

    $descricao = "Frota cadastrada: $prefixo_sga ($tipo)";

    if (function_exists('registrar_log')) {
        registrar_log($conexao, $usuarioLogado, 'Cadastro de frota', $descricao, $id_frota);
    }

    echo json_encode([
        'status' => 'ok',
        'mensagem' => ($prefixo_sga ?: 'Frota') . ' cadastrada com sucesso'
    ]);
    exit;

} else {

    echo json_encode([
        'status' => 'erro',
        'mensagem' => 'Erro ao cadastrar frota: ' . $stmt->error
    ]);
    exit;
}

$stmt->close();
?>