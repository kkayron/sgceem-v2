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
$pagina_id = 2;
verificar_permissao($pagina_id, 'pode_cadastrar');

// ========================================
// VALIDAÇÕES BÁSICAS
// ========================================
if (empty($_POST['ativo'])) {
    api_response('erro', 'Campo "Ativo" é obrigatório.');
}

if (empty($_POST['batalhao'])) {
    api_response('erro', 'Campo "Batalhão" é obrigatório.');
}

// ========================================
// PREPARA OS DADOS
// ========================================
$batalhao = intval($_POST['batalhao']);
$ativo = $_POST['ativo'];
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

// ========================================
// UPLOAD DA IMAGEM
// ========================================
$foto_capa_path = 'base.jpg';

if (isset($_FILES['foto_capa']) && $_FILES['foto_capa']['error'] === UPLOAD_ERR_OK) {

    $ext = strtolower(pathinfo($_FILES['foto_capa']['name'], PATHINFO_EXTENSION));

    $nome_arquivo = uniqid('frota_', true) . "." . $ext;

    $pasta = '../../uploads/frotas/';
    $destinoArquivo = $pasta . $nome_arquivo;

    if (!is_dir($pasta)) {
        mkdir($pasta, 0755, true);
    }

    if (move_uploaded_file($_FILES['foto_capa']['tmp_name'], $destinoArquivo)) {
        $foto_capa_path = $nome_arquivo;
    } else {
        api_response('erro', 'Falha ao salvar a imagem.');
    }
}

// ========================================
// DADOS ADICIONAIS
// ========================================
$dataHoraAgora = (new DateTime('now', new DateTimeZone('America/Sao_Paulo')))
    ->format('Y-m-d H:i:s');

$cadastrado_por = trim(
    ($_SESSION['postograd'] ?? '') . ' ' .
    ($_SESSION['nomeguerra'] ?? '')
);

// ========================================
// INSERÇÃO NA TABELA FROTA
// ========================================
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
    api_response('erro', 'Erro ao preparar consulta.');
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

$ok = $stmt->execute();

// ========================================
// RESPOSTA E LOG
// ========================================
if ($ok) {

    $usuario_id = $_SESSION['usuario_id'] ?? 0;
    $acao = "Cadastro de frota";
    $descricao = "Frota cadastrada: $prefixo_sga ($tipo)";
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $navegador = $_SERVER['HTTP_USER_AGENT'] ?? '';

    $log_sql = "INSERT INTO logs (usuario_id, acao, descricao, data_hora, ip, navegador)
                VALUES (?, ?, ?, ?, ?, ?)";

    $stmtLog = $conexao->prepare($log_sql);

    $stmtLog->bind_param(
        "isssss",
        $usuario_id,
        $acao,
        $descricao,
        $dataHoraAgora,
        $ip,
        $navegador
    );

    $stmtLog->execute();

    api_response(
        'ok',
        $prefixo_sga . ' cadastrado com sucesso pelo usuário ' . $cadastrado_por
    );

} else {

    api_response('erro', 'Erro ao cadastrar no banco.');

}