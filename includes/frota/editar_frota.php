<?php
session_start();
include_once('../../conexao/config.php');
include_once('../../includes/funcoes/log.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'] ?? null;
    if (!$id) { echo "ID inválido"; exit; }

    // Buscar dados antigos
    $stmt_antigo = $conexao->prepare("SELECT * FROM frota WHERE id = ?");
    $stmt_antigo->bind_param("i", $id);
    $stmt_antigo->execute();
    $frota_antiga = $stmt_antigo->get_result()->fetch_assoc();
    $stmt_antigo->close();

    // Campos da frota
    $campos = [
        'ativo','tipo','prefixo_velho','prefixo_sga','nome_sioc',
        'nmr_patrimonio','nmr_eb','chassi','acervo','marca',
        'modelo','ano','confiabilidade','obs_encmat',
        'capac_tanque','consumo','missao','emprego_atual',
        'ordem_fragmentaria','placa','subunidade','renavam','trem',
        'destino','disponibilidade' // <--- adicionado
    ];

    $valores = [];
    foreach ($campos as $campo) {
        $valores[$campo] = $_POST[$campo] ?? null;
    }

// Upload de foto
$foto_nome = null;

if (isset($_FILES['foto_capa']) && $_FILES['foto_capa']['error'] === 0) {

    // Limite de tamanho (10 MB)
    $limite_tamanho = 10 * 1024 * 1024; // 10 MB
    if ($_FILES['foto_capa']['size'] > $limite_tamanho) {
        die('A imagem excede o tamanho máximo permitido de 10 MB.');
    }

    // Extensões permitidas
    $ext_permitidas = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

    // Captura a extensão em minúsculo
    $ext = strtolower(pathinfo($_FILES['foto_capa']['name'], PATHINFO_EXTENSION));

    // Verifica a extensão
    if (!in_array($ext, $ext_permitidas)) {
        die('Extensão de arquivo não permitida. Envie apenas imagens.');
    }

    // Verifica o MIME real (segurança extra)
    $mime = mime_content_type($_FILES['foto_capa']['tmp_name']);
    $mime_permitidos = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

    if (!in_array($mime, $mime_permitidos)) {
        die('Arquivo não é uma imagem válida.');
    }

    // Gera nome único e faz upload
    $foto_nome = uniqid('ft_') . '.' . $ext;
    move_uploaded_file($_FILES['foto_capa']['tmp_name'], '../../uploads/frotas/' . $foto_nome);
}



    // Monta query
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

    $stmt = $conexao->prepare($sql);
    if (!$stmt) { echo "Erro ao preparar query"; exit; }
    $stmt->bind_param($tipos, ...$params);

    if ($stmt->execute()) {
        // Log
        $usuarioLogado = $_SESSION['usuario_id'] ?? 0;
        $alteracoes = [];
        foreach ($valores as $campo => $valor) {
            if ($valor != $frota_antiga[$campo]) {
                $alteracoes[] = ucfirst($campo) . ": '{$frota_antiga[$campo]}' → '$valor'";
            }
        }
        if ($foto_nome) $alteracoes[] = "Foto capa: (atualizada)";
        if ($alteracoes) {
            $descricao = "Alterações na frota ID $id: " . implode("; ", $alteracoes);
            registrar_log($conexao, $usuarioLogado, 'Editar frota', $descricao, $id);
        }
        echo "ok";
    } else {
        echo "Erro ao atualizar frota";
    }

    $stmt->close();
}

?>
