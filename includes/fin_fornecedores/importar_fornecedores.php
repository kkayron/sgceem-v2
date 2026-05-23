<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

$pagina_id = 21;
require_once('../api/seguranca_json_importar.php');

// 🔒 CSRF
if (
    empty($_POST['csrf_token']) ||
    empty($_SESSION['csrf_token']) ||
    !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
) {
    http_response_code(403);
    echo json_encode(['status' => 'erro', 'mensagem' => 'Token inválido']);
    exit;
}

require '../../vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\IOFactory;

require_once '../../conexao/config.php';

// 🔒 DADOS DO USUÁRIO
$usuarioLogado    = $_SESSION['usuario_id'];
$nivel_usuario    = $_SESSION['usuario']['nivel'] ?? 3;
$batalhao_usuario = $_SESSION['usuario']['batalhao'] ?? 0;

// =====================================
// VALIDA BATALHÃO INFORMADO
// =====================================
if (empty($_POST['batalhao'])) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Batalhão não informado.']);
    exit;
}

$batalhao = intval($_POST['batalhao']);

// =====================================
// 🔒 VALIDAÇÃO DE PERMISSÃO POR NÍVEL
// =====================================
if ($nivel_usuario == 1) {
    // Admin → pode qualquer batalhão

} elseif ($nivel_usuario == 2) {

    $sqlSubs = "SELECT id_om_menor FROM organizacoes_militares_sub WHERE id_om_maior = ?";
    $stmtSubs = $conexao->prepare($sqlSubs);
    $stmtSubs->bind_param("i", $batalhao_usuario);
    $stmtSubs->execute();
    $resultSubs = $stmtSubs->get_result();

    $batalhoesPermitidos = [$batalhao_usuario];

    while ($row = $resultSubs->fetch_assoc()) {
        $batalhoesPermitidos[] = (int)$row['id_om_menor'];
    }

    if (!in_array($batalhao, $batalhoesPermitidos)) {
        http_response_code(403);
        echo json_encode([
            'status' => 'erro',
            'mensagem' => 'Sem permissão para importar neste batalhão'
        ]);
        exit;
    }

} else {
    // Nível 3
    if ($batalhao !== $batalhao_usuario) {
        http_response_code(403);
        echo json_encode([
            'status' => 'erro',
            'mensagem' => 'Sem permissão para importar neste batalhão'
        ]);
        exit;
    }
}

// =====================================
// FUNÇÕES AUXILIARES
// =====================================
function limparCNPJ($cnpj) {
    return preg_replace('/[^0-9]/', '', $cnpj);
}

function cnpjValido($cnpj) {
    return strlen($cnpj) === 14;
}

// =====================================
//  VALIDAÇÃO DO ARQUIVO (.xlsx)
// =====================================
if (!isset($_FILES['arquivo']) || $_FILES['arquivo']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode([
        'status' => 'erro_upload',
        'mensagem' => 'Erro no envio do arquivo.'
    ]);
    exit;
}

$arquivo = $_FILES['arquivo'];

// 1. EXTENSÃO
$extensao = strtolower(pathinfo($arquivo['name'], PATHINFO_EXTENSION));
if ($extensao !== 'xlsx') {
    echo json_encode([
        'status' => 'erro_tipo',
        'mensagem' => 'Apenas arquivos .xlsx são permitidos.'
    ]);
    exit;
}

// 2. MIME TYPE (mais seguro)
$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime = $finfo->file($arquivo['tmp_name']);

$mimePermitidos = [
    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
];

if (!in_array($mime, $mimePermitidos)) {
    echo json_encode([
        'status' => 'erro_tipo',
        'mensagem' => 'Arquivo inválido. Envie um .xlsx válido.'
    ]);
    exit;
}

// 3. TAMANHO (opcional, recomendado)
$maxSize = 5 * 1024 * 1024; // 5MB

if ($arquivo['size'] > $maxSize) {
    echo json_encode([
        'status' => 'erro_tamanho',
        'mensagem' => 'Arquivo muito grande. Máximo 5MB.'
    ]);
    exit;
}

// =====================================
// PROCESSAMENTO DO ARQUIVO
// =====================================
$response = [
    'status' => 'ok',
    'sucessos' => 0,
    'falhas' => []
];

if (isset($_FILES['arquivo']) && $_FILES['arquivo']['error'] === UPLOAD_ERR_OK) {

    $arquivoTmp = $_FILES['arquivo']['tmp_name'];

    try {
        $spreadsheet = IOFactory::load($arquivoTmp);
        $sheet = $spreadsheet->getActiveSheet();
        $highestRow = $sheet->getHighestDataRow();
        $highestColumn = $sheet->getHighestDataColumn();

        for ($row = 2; $row <= $highestRow; $row++) {

            $linha = [];
            foreach (range('A', $highestColumn) as $col) {
                $linha[] = trim((string)$sheet->getCell($col . $row)->getValue());
            }

            $linha = array_pad($linha, 6, '');

            [
                $nome_empresa,
                $cnpj_empresa,
                $categoria_empresa,
                $contato_nome,
                $contato_numero,
                $contato_email
            ] = $linha;

            if (
                $nome_empresa === '' &&
                $cnpj_empresa === '' &&
                $categoria_empresa === '' &&
                $contato_nome === '' &&
                $contato_numero === '' &&
                $contato_email === ''
            ) {
                continue;
            }

            // Validações
            if ($nome_empresa === '') {
                $response['falhas'][] = ['linha' => $row, 'erro' => 'Nome da empresa vazio.'];
                continue;
            }

            $cnpj_limpo = limparCNPJ($cnpj_empresa);
            if ($cnpj_limpo === '' || !cnpjValido($cnpj_limpo)) {
                $response['falhas'][] = ['linha' => $row, 'erro' => "CNPJ inválido"];
                continue;
            }

            if ($contato_email !== '' && !filter_var($contato_email, FILTER_VALIDATE_EMAIL)) {
                $response['falhas'][] = ['linha' => $row, 'erro' => "Email inválido"];
                continue;
            }

            // Checar duplicidade
            $stmtCheck = $conexao->prepare("SELECT id FROM fin_fornecedores WHERE cnpj_empresa = ? LIMIT 1");
            $stmtCheck->bind_param("s", $cnpj_limpo);
            $stmtCheck->execute();
            $result = $stmtCheck->get_result();

            if ($result->num_rows > 0) {
                $response['falhas'][] = ['linha' => $row, 'erro' => "CNPJ já existe"];
                $stmtCheck->close();
                continue;
            }
            $stmtCheck->close();

            // Inserir
            $stmt = $conexao->prepare("
                INSERT INTO fin_fornecedores (
                    batalhao, nome_empresa, cnpj_empresa, data_cadastro,
                    categoria_empresa, contato_nome, contato_numero, contato_email
                ) VALUES (?, ?, ?, NOW(), ?, ?, ?, ?)
            ");

            $stmt->bind_param(
                "issssss",
                $batalhao,
                $nome_empresa,
                $cnpj_limpo,
                $categoria_empresa,
                $contato_nome,
                $contato_numero,
                $contato_email
            );

            if ($stmt->execute()) {
                $response['sucessos']++;
            } else {
                $response['falhas'][] = ['linha' => $row, 'erro' => $stmt->error];
            }

            $stmt->close();
        }

        // 🔒 NOVO TOKEN
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

        $response['mensagem'] =
            "{$response['sucessos']} importados" .
            (count($response['falhas']) ? " | " . count($response['falhas']) . " falhas" : "");

        echo json_encode($response);

    } catch (Exception $e) {
        echo json_encode([
            'status' => 'erro_excel',
            'mensagem' => $e->getMessage()
        ]);
    }

} else {
    echo json_encode([
        'status' => 'erro_upload',
        'mensagem' => 'Erro no upload'
    ]);
}
?>