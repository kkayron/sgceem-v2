<?php
session_start();
require_once '../../conexao/config.php';
header('Content-Type: application/json');

// Função para resposta padronizada
function resposta($status, $mensagem) {
    echo json_encode(['status' => $status, 'mensagem' => $mensagem]);
    exit;
}

// ==========================
// VALIDAÇÕES BÁSICAS
// ==========================
if (!isset($_POST['ativo']) || empty($_POST['ativo'])) {
    resposta('erro', 'Campo "Ativo" é obrigatório.');
}

if (!isset($_POST['batalhao']) || empty($_POST['batalhao'])) {
    resposta('erro', 'Campo "Batalhão" é obrigatório.');
}

// ==========================
// PREPARA OS DADOS
// ==========================
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

// ==========================
// UPLOAD DA IMAGEM
// ==========================
$foto_capa_path = 'base.jpg'; // valor padrão

if (isset($_FILES['foto_capa']) && $_FILES['foto_capa']['error'] === UPLOAD_ERR_OK) {
    $ext = pathinfo($_FILES['foto_capa']['name'], PATHINFO_EXTENSION);
    $nome_arquivo = uniqid('frota_', true) . "." . $ext;
    $destino = '../../uploads/frotas/' . $nome_arquivo;

    if (!is_dir('../../uploads/frotas')) {
        mkdir('../../uploads/frotas', 0755, true);
    }

    if (move_uploaded_file($_FILES['foto_capa']['tmp_name'], $destino)) {
        $foto_capa_path = $nome_arquivo;
    } else {
        resposta('erro', 'Falha ao salvar a imagem.');
    }
}

// ==========================
// DADOS ADICIONAIS
// ==========================
$dataHoraAgora = (new DateTime('now', new DateTimeZone('America/Sao_Paulo')))->format('Y-m-d H:i:s');
$cadastrado_por = trim(($_SESSION['postograd'] ?? '') . ' ' . ($_SESSION['nomeguerra'] ?? ''));

// ==========================
// INSERÇÃO NA TABELA FROTA
// ==========================
$sql = "INSERT INTO frota (
    batalhao, foto_capa, disponibilidade, ativo, tipo, prefixo_velho, prefixo_sga, nome_sioc,
    nmr_patrimonio, nmr_eb, chassi, acervo, marca, modelo, ano,
    confiabilidade, obs_encmat, capac_tanque, consumo, missao,
    emprego_atual, ordem_fragmentaria, placa, subunidade, renavam, trem,
    data_inclusao, cadastrado_por, destino
) VALUES (
    ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
)";

$stmt = $conexao->prepare($sql);

$ok = $stmt->execute([
    $batalhao, $foto_capa_path, $disponibilidade, $ativo, $tipo, $prefixo_velho, $prefixo_sga, $nome_sioc,
    $nmr_patrimonio, $nmr_eb, $chassi, $acervo, $marca, $modelo, $ano,
    $confiabilidade, $obs_encmat, $capacidade_tanque, $consumo, $missao,
    $emprego_atual, $ordem_fragmentaria, $placa, $subunidade, $renavam, $trem,
    $dataHoraAgora, $cadastrado_por, $destino
]);

// ==========================
// RESPOSTA E LOG
// ==========================
if ($ok) {
    $usuario_id = $_SESSION['usuario_id'] ?? 0;
    $acao = "Cadastro de frota";
    $descricao = "Frota cadastrada: $prefixo_sga ($tipo)";
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $navegador = $_SERVER['HTTP_USER_AGENT'] ?? '';

    $log_sql = "INSERT INTO logs (usuario_id, acao, descricao, data_hora, ip, navegador)
                VALUES (?, ?, ?, ?, ?, ?)";
    $conexao->prepare($log_sql)->execute([
        $usuario_id, $acao, $descricao, $dataHoraAgora, $ip, $navegador
    ]);

    resposta('ok', $prefixo_sga . ' cadastrado com sucesso pelo usuário ' . $cadastrado_por);
} else {
    resposta('erro', 'Erro ao cadastrar no banco.');
}
