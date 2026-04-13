<?php
session_start();
require_once '../api/seguranca_editar.php';

$permissoes = verificarPermissao(31);

$pode_cadastrar = $permissoes['cadastrar'];
$pode_editar    = $permissoes['editar'];
$pode_deletar   = $permissoes['deletar'];
$pode_importar  = $permissoes['importar'];
include_once("../../conexao/config.php");


$usuarioLogado = $_SESSION['usuario_id'] ?? 0;

// ==========================
// DADOS DO FORMULÁRIO
// ==========================
$id_entrada      = intval($_POST['id_entrada'] ?? 0);
$data_entrada    = $_POST['data_entrada'] ?? '';
$nota_empenho    = $_POST['nota_empenho'] ?? '';
$nota_fiscal     = $_POST['nota_fiscal'] ?? '';
$nome_fornecedor = $_POST['nome_fornecedor'] ?? '';
$cnpj_fornecedor = $_POST['cnpj_fornecedor'] ?? '';
$deposito_id     = intval($_POST['deposito_id'] ?? 0);
$produtos        = $_POST['produtos'] ?? [];

if (!$id_entrada) {
    echo "ID da entrada não informado";
    exit;
}

try {
    // =======================================================
    // 1) Buscar batalhão da entrada
    // =======================================================
    $stmt = $conexao->prepare("
        SELECT batalhao 
        FROM almox_entradas 
        WHERE id = ?
    ");
    $stmt->bind_param("i", $id_entrada);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($res->num_rows === 0) {
        throw new Exception("Entrada não encontrada.");
    }

    $batalhaoEntrada = (int)$res->fetch_assoc()['batalhao'];
    $stmt->close();

    // =======================================================
    // 2) Validar depósito
    // =======================================================
    if (!$deposito_id) {
        throw new Exception("Depósito não informado.");
    }

    $stmt = $conexao->prepare("
        SELECT batalhao 
        FROM almox_depositos 
        WHERE id = ?
    ");
    $stmt->bind_param("i", $deposito_id);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($res->num_rows === 0) {
        throw new Exception("Depósito inválido.");
    }

    $batalhaoDeposito = (int)$res->fetch_assoc()['batalhao'];
    $stmt->close();

    if ($batalhaoDeposito !== $batalhaoEntrada) {
        throw new Exception("O depósito selecionado não pertence ao batalhão da entrada.");
    }

    // =======================================================
    // 3) Validar produtos x batalhão
    // =======================================================
    if (!empty($produtos)) {
        $idsProdutos = array_column($produtos, 'id_produto');
        $idsProdutos = array_filter(array_map('intval', $idsProdutos));

        if (!empty($idsProdutos)) {
            $idsStr = implode(',', $idsProdutos);

            $sql = "
                SELECT DISTINCT batalhao 
                FROM almox_produtos 
                WHERE id IN ($idsStr)
            ";
            $res = $conexao->query($sql);

            $batalhoesProdutos = [];
            while ($r = $res->fetch_assoc()) {
                $batalhoesProdutos[] = (int)$r['batalhao'];
            }

            if (
                count($batalhoesProdutos) > 1 ||
                (count($batalhoesProdutos) === 1 && $batalhoesProdutos[0] !== $batalhaoEntrada)
            ) {
                throw new Exception("Existem produtos que não pertencem ao batalhão da entrada.");
            }
        }
    }

    // =======================================================
    // 4) Atualizar dados da entrada (AGORA COM DEPÓSITO)
    // =======================================================
    $stmt = $conexao->prepare("
        UPDATE almox_entradas 
        SET 
            data_entrada = ?, 
            nota_empenho = ?, 
            nota_fiscal = ?, 
            nome_fornecedor = ?, 
            cnpj_fornecedor = ?, 
            deposito_id = ?
        WHERE id = ?
    ");
    $stmt->bind_param(
        "sssssii",
        $data_entrada,
        $nota_empenho,
        $nota_fiscal,
        $nome_fornecedor,
        $cnpj_fornecedor,
        $deposito_id,
        $id_entrada
    );

    if (!$stmt->execute()) {
        throw new Exception("Erro ao atualizar entrada: " . $stmt->error);
    }
    $stmt->close();

    // =======================================================
    // 5) Remover itens antigos
    // =======================================================
    $stmt = $conexao->prepare("
        DELETE FROM almox_entradas_itens 
        WHERE id_entrada = ?
    ");
    $stmt->bind_param("i", $id_entrada);
    if (!$stmt->execute()) {
        throw new Exception("Erro ao remover itens antigos.");
    }
    $stmt->close();

    // =======================================================
    // 6) Inserir itens atualizados
    // =======================================================
    if (!empty($produtos)) {
        $stmt = $conexao->prepare("
            INSERT INTO almox_entradas_itens 
            (id_entrada, id_produto, quant, valor_unt, valor_total, marca, modelo)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");

        foreach ($produtos as $item) {
            $id_produto  = intval($item['id_produto']);
            $quant       = floatval($item['quant']);
            $valor_unt   = floatval($item['valor_unt']);
            $valor_total = floatval($item['valor_total']);
            $marca       = trim($item['marca'] ?? '');
            $modelo      = trim($item['modelo'] ?? '');

            if (!$id_produto || $quant <= 0) {
                continue;
            }

            $stmt->bind_param(
                "iidddss",
                $id_entrada,
                $id_produto,
                $quant,
                $valor_unt,
                $valor_total,
                $marca,
                $modelo
            );

            if (!$stmt->execute()) {
                throw new Exception("Erro ao inserir item: " . $stmt->error);
            }
        }
        $stmt->close();
    }

    echo "ok";

} catch (Exception $e) {
    echo "Erro: " . $e->getMessage();
}
?>
