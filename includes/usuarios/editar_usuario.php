<?php
session_start();
include_once('../../conexao/config.php');
include_once('../../includes/funcoes/log.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'] ?? null;
    $postograd     = $_POST['postograd'] ?? '';
    $nomeguerra    = $_POST['nomeguerra'] ?? '';
    $nomecompleto  = $_POST['nomecompleto'] ?? '';
    $usuario       = $_POST['usuario'] ?? '';
    $funcao        = $_POST['funcao'] ?? '';
    $batalhao      = $_POST['batalhao'] ?? null;
    $status        = $_POST['status'] ?? '';
    $senha         = $_POST['senha'] ?? null;

    if (!$id) {
        echo "ID inválido";
        exit;
    }

    // Buscar dados antigos
    $stmt_antigo = $conexao->prepare("SELECT * FROM usuarios WHERE id = ?");
    $stmt_antigo->bind_param("i", $id);
    $stmt_antigo->execute();
    $resultado = $stmt_antigo->get_result();
    $usuario_antigo = $resultado->fetch_assoc();
    $stmt_antigo->close();

    if (!$usuario_antigo) {
        echo "Usuário não encontrado";
        exit;
    }

    // Funções exclusivas
    $funcoesRestritas = ['Cmt Cia E Eqp Mnt', 'Ch Financeiro', 'Ch Seç Ctrl'];

    // Verifica duplicidade apenas se for função restrita e status ativo
    if ($status === 'sim' && in_array($funcao, $funcoesRestritas)) {
        $stmtVerifica = $conexao->prepare("
            SELECT COUNT(*) 
            FROM usuarios 
            WHERE funcao = ? 
              AND status = 'sim' 
              AND id != ? 
              AND batalhao = ?
        ");
        $stmtVerifica->bind_param("sii", $funcao, $id, $batalhao);
        $stmtVerifica->execute();
        $stmtVerifica->bind_result($quantidade);
        $stmtVerifica->fetch();
        $stmtVerifica->close();

        if ($quantidade > 0) {
            echo "Já existe outro usuário ativo com a função exclusiva \"$funcao\" nesta Organização Militar.";
            exit;
        }
    }

    // Upload da foto, se enviada
    $foto_nome = null;
    if (isset($_FILES['foto']) && $_FILES['foto']['error'] === 0) {
        $ext = pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION);
        $foto_nome = uniqid() . '.' . $ext;
        $destino = '../../assets/fotoperfil/' . $foto_nome;
        move_uploaded_file($_FILES['foto']['tmp_name'], $destino);
    }

    // Monta SQL dinâmico
    $sql = "UPDATE usuarios SET postograd=?, nomeguerra=?, usuario=?, funcao=?, status=?, nomecompleto=?, batalhao=?";
    $param_types = "sssssss";
    $params = [$postograd, $nomeguerra, $usuario, $funcao, $status, $nomecompleto, $batalhao];

    if (!empty($senha)) {
        $senha_hash = password_hash($senha, PASSWORD_DEFAULT);
        $sql .= ", senha=?";
        $param_types .= "s";
        $params[] = $senha_hash;
    }

    if (!empty($foto_nome)) {
        $sql .= ", foto=?";
        $param_types .= "s";
        $params[] = $foto_nome;
    }

    $sql .= " WHERE id=?";
    $param_types .= "i";
    $params[] = $id;

    $stmt = $conexao->prepare($sql);
    if (!$stmt) {
        echo "Erro ao preparar statement";
        exit;
    }

    $stmt->bind_param($param_types, ...$params);

    if ($stmt->execute()) {
        // Registrar log das alterações
        $usuarioLogado = $_SESSION['usuario_id'] ?? 0;
        $alteracoes = [];

        if ($postograd !== $usuario_antigo['postograd']) {
            $alteracoes[] = "Posto/Grad: '{$usuario_antigo['postograd']}' → '$postograd'";
        }
        if ($nomeguerra !== $usuario_antigo['nomeguerra']) {
            $alteracoes[] = "Nome de Guerra: '{$usuario_antigo['nomeguerra']}' → '$nomeguerra'";
        }
        if ($usuario !== $usuario_antigo['usuario']) {
            $alteracoes[] = "Usuário: '{$usuario_antigo['usuario']}' → '$usuario'";
        }
        if ($funcao !== $usuario_antigo['funcao']) {
            $alteracoes[] = "Função: '{$usuario_antigo['funcao']}' → '$funcao'";
        }
        if ($status !== $usuario_antigo['status']) {
            $alteracoes[] = "Status: '{$usuario_antigo['status']}' → '$status'";
        }
        if ($nomecompleto !== $usuario_antigo['nomecompleto']) {
            $alteracoes[] = "Nome completo: '{$usuario_antigo['nomecompleto']}' → '$nomecompleto'";
        }
        if ($batalhao !== $usuario_antigo['batalhao']) {
            $alteracoes[] = "Batalhão: '{$usuario_antigo['batalhao']}' → '$batalhao'";
        }
        if (!empty($senha)) {
            $alteracoes[] = "Senha: (alterada)";
        }
        if (!empty($foto_nome)) {
            $alteracoes[] = "Foto: (atualizada)";
        }

        $descricao = "Alterações no usuário ID $id: " . implode("; ", $alteracoes);
        registrar_log($conexao, $usuarioLogado, 'Editar usuário', $descricao);

        echo "ok";
    } else {
        echo "Erro ao atualizar usuário";
    }

    $stmt->close();
}
?>
