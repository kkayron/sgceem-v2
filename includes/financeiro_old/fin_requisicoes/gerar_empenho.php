<?php
session_start();
include_once('../../conexao/config.php');
include_once('../../includes/funcoes/log.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id          = $_POST['id'] ?? null;
    $nmr_empenho = $_POST['nmr_empenho'] ?? '';
    $obra        = $_POST['obra'] ?? '';
    $ano         = $_POST['ano'] ?? '';
    $categoria   = $_POST['categoria'] ?? '';
    $local       = $_POST['local'] ?? '';
    $resto_pagar = $_POST['resto_pagar'] ?? '';
    $data_e      = $_POST['data_empenho'] ?? ''; // NOVO

    if (!$id || empty($nmr_empenho) || empty($data_e)) {
        echo "Dados incompletos";
        exit;
    }

    // 1) Atualiza a requisição principal para marcar que o empenho já foi gerado
    $sql = "
      UPDATE fin_requisicao 
      SET
        nmr_empenho        = ?, 
        empenho_gerado     = 'sim', 
        status_requisicao  = 'Empenho gerado' 
      WHERE id = ?
    ";
    $stmt = $conexao->prepare($sql);
    if (!$stmt) {
        echo "Erro ao preparar statement de atualização da requisição";
        exit;
    }
    $stmt->bind_param("si", $nmr_empenho, $id);
    if (!$stmt->execute()) {
        echo "Erro ao atualizar fin_requisicao";
        $stmt->close();
        exit;
    }
    $stmt->close();

    // 2) Verifica se já existe um registro em fin_empenhos para esta requisição
    $check = $conexao->prepare("SELECT id FROM fin_empenhos WHERE id_requisicao = ?");
    $check->bind_param("i", $id);
    $check->execute();
    $result = $check->get_result();

    if ($result->num_rows > 0) {
        // **Já existe:** faz UPDATE em fin_empenhos
        $update = $conexao->prepare(
            "UPDATE fin_empenhos 
             SET 
               data_empenho = ?, 
               nmr_empenho  = ?, 
               obra         = ?, 
               ano          = ?, 
               categoria    = ?, 
               local        = ?, 
               resto_pagar  = ?
             WHERE id_requisicao = ?"
        );
        $update->bind_param(
            "sssssssi",
            $data_e,
            $nmr_empenho,
            $obra,
            $ano,
            $categoria,
            $local,
            $resto_pagar,
            $id
        );
        if (!$update->execute()) {
            echo "Erro ao atualizar fin_empenhos";
            $update->close();
            exit;
        }
        $update->close();
    } else {
        // **Não existe ainda:** insere um novo registro em fin_empenhos
        $insert = $conexao->prepare(
            "INSERT INTO fin_empenhos 
               (id_requisicao, data_empenho, nmr_empenho, obra, ano, categoria, local, resto_pagar)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $insert->bind_param(
            "isssssss",
            $id,
            $data_e,
            $nmr_empenho,
            $obra,
            $ano,
            $categoria,
            $local,
            $resto_pagar
        );
        if (!$insert->execute()) {
            echo "Erro ao inserir em fin_empenhos";
            $insert->close();
            exit;
        }
        $insert->close();
    }

    $check->close();

    // 3) Log
    $usuarioLogado = $_SESSION['usuario_id'] ?? 0;
    $descricao = "Empenho gerado/alterado para Requisição ID $id: 
      data '$data_e', número '$nmr_empenho', obra '$obra', ano '$ano', categoria '$categoria', local '$local', resto_pagar '$resto_pagar'";
    registrar_log($conexao, $usuarioLogado, 'Gerar Empenho', $descricao, $id);

    echo "ok";
}
?>
