<?php
session_start();
require '../../vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\IOFactory;
require_once '../../conexao/config.php';

header('Content-Type: application/json; charset=utf-8');
ob_start();

$response = [
    'status' => 'ok',
    'sucessos' => 0,
    'falhas' => []
];

// =====================================
// Funções auxiliares
// =====================================
function limparCNPJ($cnpj) {
    return preg_replace('/[^0-9]/', '', $cnpj);
}

function cnpjValido($cnpj) {
    return strlen($cnpj) === 14;
}

// =====================================
// Verifica batalhão
// =====================================
if (empty($_POST['batalhao'])) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Batalhão não informado.']);
    exit;
}
$batalhao = intval($_POST['batalhao']);

// =====================================
// Processa arquivo
// =====================================
if (isset($_FILES['arquivo']) && $_FILES['arquivo']['error'] === UPLOAD_ERR_OK) {

    $arquivoTmp = $_FILES['arquivo']['tmp_name'];

    try {
        $spreadsheet = IOFactory::load($arquivoTmp);
        $sheet = $spreadsheet->getActiveSheet();
        $highestRow = $sheet->getHighestDataRow();
        $highestColumn = $sheet->getHighestDataColumn();

        // =====================================
        // INICIA NA LINHA 2 (cabeçalho é a linha 1)
        // =====================================
        for ($row = 2; $row <= $highestRow; $row++) {

            $linha = [];
            foreach (range('A', $highestColumn) as $col) {
                $linha[] = trim((string)$sheet->getCell($col . $row)->getValue());
            }

            // Garante pelo menos 6 posições
            $linha = array_pad($linha, 6, '');

            // Mapeamento simples e direto
            [
                $nome_empresa,
                $cnpj_empresa,
                $categoria_empresa,
                $contato_nome,
                $contato_numero,
                $contato_email
            ] = $linha;

            // Se a linha estiver totalmente vazia, pula
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

            // =====================================
            // Validações
            // =====================================
            if ($nome_empresa === '') {
                $response['falhas'][] = ['linha' => $row, 'erro' => 'Nome da empresa vazio.'];
                continue;
            }

            $cnpj_limpo = limparCNPJ($cnpj_empresa);
            if ($cnpj_limpo === '' || !cnpjValido($cnpj_limpo)) {
                $response['falhas'][] = ['linha' => $row, 'erro' => "CNPJ inválido: '{$cnpj_empresa}'"];
                continue;
            }

            if ($contato_email !== '' && !filter_var($contato_email, FILTER_VALIDATE_EMAIL)) {
                $response['falhas'][] = ['linha' => $row, 'erro' => "Email de contato inválido: '{$contato_email}'"];
                continue;
            }

            // =====================================
            // Checa duplicidade
            // =====================================
            $stmtCheck = $conexao->prepare(
                "SELECT id FROM fin_fornecedores WHERE cnpj_empresa = ? LIMIT 1"
            );
            $stmtCheck->bind_param("s", $cnpj_limpo);
            $stmtCheck->execute();
            $result = $stmtCheck->get_result();

            if ($result->num_rows > 0) {
                $response['falhas'][] = [
                    'linha' => $row,
                    'erro' => "CNPJ '{$cnpj_empresa}' já existe no sistema."
                ];
                $stmtCheck->close();
                continue;
            }
            $stmtCheck->close();

            // =====================================
            // Inserção
            // =====================================
            $sql = "INSERT INTO fin_fornecedores (
                batalhao,
                nome_empresa,
                cnpj_empresa,
                data_cadastro,
                categoria_empresa,
                contato_nome,
                contato_numero,
                contato_email
            ) VALUES (?, ?, ?, NOW(), ?, ?, ?, ?)";

            $stmt = $conexao->prepare($sql);

            if (!$stmt) {
                $response['falhas'][] = [
                    'linha' => $row,
                    'erro' => 'Erro ao preparar statement.'
                ];
                continue;
            }

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
                $response['falhas'][] = [
                    'linha' => $row,
                    'erro' => 'Erro ao inserir: ' . $stmt->error
                ];
            }

            $stmt->close();
        }

        // =====================================
        // Resposta final
        // =====================================
        $response['mensagem'] =
            "{$response['sucessos']} fornecedores importados com sucesso." .
            (count($response['falhas']) > 0 ? " " . count($response['falhas']) . " falhas encontradas." : "");

        ob_end_clean();
        echo json_encode($response);

    } catch (Exception $e) {
        ob_end_clean();
        echo json_encode([
            'status' => 'erro_excel',
            'mensagem' => $e->getMessage()
        ]);
    }

} else {
    echo json_encode([
        'status' => 'erro_upload',
        'mensagem' => 'Erro ao enviar o arquivo.'
    ]);
}
?>
