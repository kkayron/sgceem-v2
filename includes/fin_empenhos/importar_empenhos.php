<?php
session_start();
require '../../conexao/config.php';
require '../funcoes/log.php';
require '../../vendor/autoload.php'; // PhpSpreadsheet

use PhpOffice\PhpSpreadsheet\IOFactory;

header("Content-Type: application/json; charset=utf-8");

$usuarioLogado = $_SESSION['usuario_id'] ?? 0;

// batalhão vem do modal
$batalhao = intval($_POST['batalhao'] ?? 0);

$resposta = [
    "status" => "ok",
    "mensagem" => "",
    "falhas" => []
];

// validar arquivo
if (!isset($_FILES['arquivo']) || $_FILES['arquivo']['error'] !== 0) {
    echo json_encode(["status" => "erro", "mensagem" => "Nenhum arquivo enviado"]);
    exit;
}

// ler Excel
$arquivoTmp = $_FILES['arquivo']['tmp_name'];

try {
    $spread = IOFactory::load($arquivoTmp);
    $sheet = $spread->getActiveSheet();
    $rows = $sheet->toArray(null, true, true, true); // ← garante leitura crua sem conversões
} catch (Exception $e) {
    echo json_encode(["status" => "erro", "mensagem" => "Erro ao ler o Excel: " . $e->getMessage()]);
    exit;
}

/**
 * Função segura para converter valores monetários do Excel
 * Aceita formatos:
 *  - 2.500,00
 *  - 2500,00
 *  - 2500.00
 *  - 250000  (Excel moeda *100)
 *  - R$ 2.500,00
 *  - etc
 */
function converterValorSeguro($valorRaw)
{
    if ($valorRaw === null || $valorRaw === "") return 0;

    // Remove símbolos de moeda e espaços
    $valor = preg_replace('/[^\d.,\-]/', '', $valorRaw);

    // Caso o valor esteja em formato numérico puro (excel armazenado como número)
    if (is_numeric($valorRaw)) {

        // Excel às vezes multiplica moeda por 100 → detecta usando quantidade de dígitos
        if ($valorRaw > 100000000) {
            return $valorRaw / 100;
        }

        return floatval($valorRaw);
    }

    // Converter 2.500,00 → 2500.00
    if (strpos($valor, ',') !== false) {
        $valor = str_replace('.', '', $valor);
        $valor = str_replace(',', '.', $valor);
    }

    if (!is_numeric($valor)) return 0;

    // Se vier algo como 250000 mas deveria ser 2500 — heurística:
    if ($valor > 100000000) {
        return $valor / 100;
    }

    return floatval($valor);
}


/**
 * Buscar fornecedor pelo CNPJ + batalhão
 */
function buscarFornecedorPorCNPJ($conexao, $batalhao, $cnpj)
{
    if (empty($cnpj)) {
        return ["erro" => "CNPJ vazio"];
    }

    $cnpj_limpo = preg_replace('/[^0-9]/', '', $cnpj);

    if (strlen($cnpj_limpo) !== 14) {
        return ["erro" => "CNPJ inválido: {$cnpj}"];
    }

    $sql = "SELECT id, nome_empresa 
            FROM fin_fornecedores 
            WHERE batalhao = ? AND cnpj_empresa = ?
            LIMIT 1";

    $stmt = $conexao->prepare($sql);

    if (!$stmt) {
        return ["erro" => "Erro ao preparar consulta de fornecedor"];
    }

    $stmt->bind_param("is", $batalhao, $cnpj_limpo);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($row = $res->fetch_assoc()) {
        return ["id" => $row['id'], "nome" => $row['nome_empresa']];
    }

    return ["erro" => "Fornecedor com CNPJ {$cnpj} não encontrado para esse batalhão"];
}


// Função auxiliar igual ao seu script
function buscarResponsavel($conexao, $funcao)
{
    $sql = "SELECT nomecompleto, postograd 
            FROM usuarios 
            WHERE funcao = ? AND status = 'sim'
            LIMIT 1";
    $stmt = $conexao->prepare($sql);
    $stmt->bind_param("s", $funcao);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($u = $res->fetch_assoc()) {
        return $u['nomecompleto'] . " - " . $u['postograd'];
    }
    return "Não há usuário cadastrado com a função";
}

// buscar responsáveis
$cmt_ceem       = buscarResponsavel($conexao, '8');
$ch_financeiro  = buscarResponsavel($conexao, '10');
$ch_controle    = buscarResponsavel($conexao, '9');
$ch_s4          = buscarResponsavel($conexao, '19');
$cmt_batalhao   = buscarResponsavel($conexao, '7');

$importados = 0;

// COMEÇAR NA SEGUNDA LINHA
foreach ($rows as $i => $linha) {

    if ($i == 1) continue; // pular cabeçalho Excel

    // Excel usando colunas A, B, C...
    $cnpj_fornecedor = trim($linha['A'] ?? '');

    if ($cnpj_fornecedor === '') continue;

    // Buscar fornecedor
    $fornecedor = buscarFornecedorPorCNPJ($conexao, $batalhao, $cnpj_fornecedor);

    if (!empty($fornecedor["erro"])) {
        $resposta["falhas"][] = [
            "linha" => $i,
            "erro" => $fornecedor["erro"]
        ];
        continue;
    }

    $id_fornecedor = intval($fornecedor["id"]);

    // Capturar campos do Excel
    $requisitante        = $linha['B'] ?? "";
    $destinatario        = $linha['C'] ?? "";
    $nota_credito        = $linha['D'] ?? "";
    $plano_interno       = $linha['E'] ?? "";
    $natureza_despesa    = $linha['F'] ?? "";
    $item_oog            = $linha['G'] ?? "";
    $finalidade          = $linha['H'] ?? "";
    $tipo_empenho        = $linha['I'] ?? "";
    $necessidade_contrato= $linha['J'] ?? "";
    $data_empenho        = $linha['K'] ?? null;
    $nmr_empenho         = $linha['L'] ?? "";
    $obra                = $linha['M'] ?? "";
    $ano                 = $linha['N'] ?? "";
    $categoria           = $linha['O'] ?? "";
    $local               = $linha['P'] ?? "";
    $resto_pagar         = $linha['Q'] ?? "Não";
    $valor_empenhado_raw = $linha['R'] ?? "0";
    $marca               = $linha['S'] ?? "Sem marca";

    // ---- NOVA CONVERSÃO SEGURA ----
    $valor_empenhado = converterValorSeguro($valor_empenhado_raw);

    // data atual da requisição
    $data_requisicao = date("Y-m-d H:i:s");

    // ==========================================
    // 2) INSERIR NA fin_requisicao
    // ==========================================
    $sqlReq = "
        INSERT INTO fin_requisicao (
            batalhao, id_fornecedor, requisitante, destinatario,
            necessidade_contrato, finalidade, item_oog, tipo_empenho,
            data_requisicao, nota_credito, plano_interno, natureza_despesa,
            status_requisicao, nmr_empenho, empenho_gerado,
            cmt_ceem, ch_financeiro, ch_controle, ch_s4, cmt_batalhao,
            valor_empenhado, marca
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ";

    $stmtReq = $conexao->prepare($sqlReq);

    if (!$stmtReq) {
        $resposta["falhas"][] = [
            "linha" => $i,
            "erro" => "Erro preparar fin_requisicao: " . $conexao->error
        ];
        continue;
    }

    $status_requisicao = "Em confecção";
    $empenho_gerado = "sim";

    $stmtReq->bind_param(
        "iissssssssssissssssdss",
        $batalhao,
        $id_fornecedor,
        $requisitante,
        $destinatario,
        $necessidade_contrato,
        $finalidade,
        $item_oog,
        $tipo_empenho,
        $data_requisicao,
        $nota_credito,
        $plano_interno,
        $natureza_despesa,
        $status_requisicao,
        $nmr_empenho,
        $empenho_gerado,
        $cmt_ceem,
        $ch_financeiro,
        $ch_controle,
        $ch_s4,
        $cmt_batalhao,
        $valor_empenhado,
        $marca
    );

    if (!$stmtReq->execute()) {
        $resposta["falhas"][] = [
            "linha" => $i,
            "erro" => $stmtReq->error
        ];
        $stmtReq->close();
        continue;
    }

    $id_requisicao = $conexao->insert_id;
    $stmtReq->close();

    // ==========================================
    // 3) INSERIR NA fin_empenhos
    // ==========================================
    $sqlEmp = "
        INSERT INTO fin_empenhos (
            id_requisicao, data_empenho, nmr_empenho,
            obra, ano, categoria, local, resto_pagar
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ";

    $stmtEmp = $conexao->prepare($sqlEmp);

    if (!$stmtEmp) {
        $conexao->query("DELETE FROM fin_requisicao WHERE id = $id_requisicao");
        $resposta["falhas"][] = [
            "linha" => $i,
            "erro" => "Erro preparar fin_empenhos"
        ];
        continue;
    }

    $stmtEmp->bind_param(
        "isssssss",
        $id_requisicao,
        $data_empenho,
        $nmr_empenho,
        $obra,
        $ano,
        $categoria,
        $local,
        $resto_pagar
    );

    if (!$stmtEmp->execute()) {
        $stmtEmp->close();
        $conexao->query("DELETE FROM fin_requisicao WHERE id = $id_requisicao");
        $resposta["falhas"][] = ["linha" => $i, "erro" => $stmtEmp->error];
        continue;
    }

    $id_empenho = $conexao->insert_id;
    $stmtEmp->close();

    // registrar log
    $descricaoLog = "Empenho importado. Req: $id_requisicao - Emp: $id_empenho";
    registrar_log($conexao, $usuarioLogado, "Importar Empenho", $descricaoLog, $id_requisicao);

    $importados++;
}

$resposta["mensagem"] = "Total importado: $importados";

echo json_encode($resposta);
exit;
