<?php
header('Content-Type: application/json; charset=utf-8');
session_start();
include_once('../../conexao/config.php');

try {
    if (
        !isset($_POST['funcao_id']) ||
        !isset($_POST['pagina_id']) ||
        !isset($_POST['campo']) ||
        !isset($_POST['permitido'])
    ) {
        echo json_encode([
            "sucesso" => false,
            "mensagem" => "Parâmetros incompletos."
        ]);
        exit;
    }

    $funcao_id  = intval($_POST['funcao_id']);
    $pagina_id  = intval($_POST['pagina_id']);
    $campo      = $_POST['campo'];
    $permitido  = intval($_POST['permitido']); // 0 ou 1

    // Validação do campo
    $validos = ['pode_acessar','pode_editar','pode_deletar','pode_cadastrar', 'pode_importar', 'pode_exportar', 'pode_autorizar'];
    if (!in_array($campo, $validos)) {
        echo json_encode(["sucesso" => false, "mensagem" => "Campo inválido."]);
        exit;
    }

    // Verifica se registro existe
    $sqlCheck = $conexao->prepare("
        SELECT id FROM permissoes 
        WHERE funcao_id = ? AND pagina_id = ?
    ");
    $sqlCheck->bind_param("ii", $funcao_id, $pagina_id);
    $sqlCheck->execute();
    $res = $sqlCheck->get_result();

    if ($res->num_rows > 0) {

        // UPDATE
        $sqlUpdate = $conexao->prepare("
            UPDATE permissoes 
            SET $campo = ? 
            WHERE funcao_id = ? AND pagina_id = ?
        ");
        $sqlUpdate->bind_param("iii", $permitido, $funcao_id, $pagina_id);
        $sqlUpdate->execute();

    } else {

        // INSERT
        $sqlInsert = $conexao->prepare("
            INSERT INTO permissoes (funcao_id, pagina_id, $campo)
            VALUES (?, ?, ?)
        ");
        $sqlInsert->bind_param("iii", $funcao_id, $pagina_id, $permitido);
        $sqlInsert->execute();
    }

    echo json_encode([
        "sucesso" => true,
        "mensagem" => "Permissão atualizada!"
    ]);

} catch (Exception $e) {
    echo json_encode([
        "sucesso" => false,
        "mensagem" => $e->getMessage()
    ]);
}
