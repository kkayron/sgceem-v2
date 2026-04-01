<?php

// ========================================
// BLOQUEIA ACESSO DIRETO
// ========================================
if (
    !isset($_SERVER['HTTP_X_REQUESTED_WITH']) ||
    strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) !== 'xmlhttprequest'
) {
    exit;
}

// ========================================
// CONFIGURAÇÕES
// ========================================
require_once '../../conexao/config.php';
require_once '../../includes/funcoes/log.php';

require_once '../api/auth.php';
require_once '../api/permissions.php';
require_once '../api/response.php';

header('Content-Type: application/json; charset=utf-8');

// ========================================
// VERIFICA SESSÃO
// ========================================
verificar_sessao();

// ========================================
// VERIFICA PERMISSÃO
// ========================================
$pagina_id = 14;
verificar_permissao($pagina_id, 'pode_editar');

// ========================================
// VALIDAÇÃO DO MÉTODO
// ========================================
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    api_response('erro', 'Método inválido.');
}

// ========================================
// ID DA FROTA
// ========================================
$id = $_POST['id'] ?? null;

if (!$id) {
    api_response('erro', 'ID inválido.');
}

// ========================================
// BUSCA DADOS ANTIGOS
// ========================================
$stmt_antigo = $conexao->prepare("SELECT * FROM frota WHERE id = ?");
$stmt_antigo->bind_param("i", $id);
$stmt_antigo->execute();
$frota_antiga = $stmt_antigo->get_result()->fetch_assoc();
$stmt_antigo->close();

if (!$frota_antiga) {
    api_response('erro', 'Frota não encontrada.');
}

// ========================================
// CAMPOS EDITÁVEIS
// ========================================
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

// ========================================
// UPLOAD DA FOTO
// ========================================
$foto_nome = null;

if (isset($_FILES['foto_capa']) && $_FILES['foto_capa']['error'] === 0) {

    $limite_tamanho = 10 * 1024 * 1024;

    if ($_FILES['foto_capa']['size'] > $limite_tamanho) {
        api_response('erro', 'A imagem excede o limite de 10MB.');
    }

    $ext_permitidas = ['jpg','jpeg','png','gif','webp'];
    $ext = strtolower(pathinfo($_FILES['foto_capa']['name'], PATHINFO_EXTENSION));

    if (!in_array($ext, $ext_permitidas)) {
        api_response('erro', 'Extensão de arquivo não permitida.');
    }

    $mime = mime_content_type($_FILES['foto_capa']['tmp_name']);

    $mime_permitidos = [
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp'
    ];

    if (!in_array($mime, $mime_permitidos)) {
        api_response('erro', 'Arquivo não é uma imagem válida.');
    }

    $foto_nome = uniqid('ft_') . '.' . $ext;

    $pasta = '../../uploads/frotas/';

    if (!is_dir($pasta)) {
        mkdir($pasta, 0755, true);
    }

    move_uploaded_file(
        $_FILES['foto_capa']['tmp_name'],
        $pasta . $foto_nome
    );
}

// ========================================
// MONTA QUERY UPDATE
// ========================================
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

// ========================================
// EXECUTA UPDATE
// ========================================
$stmt = $conexao->prepare($sql);

if (!$stmt) {
    api_response('erro', 'Erro ao preparar consulta.');
}

$stmt->bind_param($tipos, ...$params);

if ($stmt->execute()) {

    // ========================================
    // LOG DE ALTERAÇÕES
    // ========================================
    $usuarioLogado = $_SESSION['usuario_id'] ?? 0;

    $alteracoes = [];

    foreach ($valores as $campo => $valor) {

        if ($valor != $frota_antiga[$campo]) {

            $alteracoes[] =
                ucfirst($campo) .
                ": '{$frota_antiga[$campo]}' → '$valor'";
        }
    }

    if ($foto_nome) {
        $alteracoes[] = "Foto capa atualizada";
    }

    if ($alteracoes) {

        $descricao =
            "Alterações na frota ID $id: " .
            implode("; ", $alteracoes);

        registrar_log(
            $conexao,
            $usuarioLogado,
            'Editar frota',
            $descricao,
            $id
        );
    }

    api_response('ok', 'Frota atualizada com sucesso.');

} else {

    api_response('erro', 'Erro ao atualizar frota.');

}

$stmt->close();