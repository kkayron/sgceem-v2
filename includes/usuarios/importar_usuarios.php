<?php
session_start();
require '../../vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\IOFactory;
require_once '../../conexao/config.php';

header('Content-Type: application/json');

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
        $dados = $sheet->toArray();
        array_shift($dados); // Remove cabeçalho

        // Funções que só podem ter 1 usuário
        $funcoesUnicas = ['Cmt Cia E Eqp Mnt', 'Ch Seç Ctrl', 'Ch Financeiro', 'Ch STA'];

        // Verifica quais dessas funções já existem no banco
        $placeholders = implode(',', array_fill(0, count($funcoesUnicas), '?'));
        $stmtFuncoes = $conexao->prepare("SELECT funcao FROM usuarios WHERE funcao IN ($placeholders)");
        $stmtFuncoes->bind_param(str_repeat('s', count($funcoesUnicas)), ...$funcoesUnicas);
        $stmtFuncoes->execute();
        $resultadoFuncoes = $stmtFuncoes->get_result();

        $funcoesJaExistentes = [];
        while ($row = $resultadoFuncoes->fetch_assoc()) {
            $funcoesJaExistentes[] = $row['funcao'];
        }
        $stmtFuncoes->close();

        foreach ($dados as $index => $linha) {
            $linhaExcel = $index + 2;

            $postograd   = trim($linha[0] ?? '');
            $usuario     = trim($linha[1] ?? '');
            $senhaBruta  = trim($linha[2] ?? '');
            $funcao      = trim($linha[3] ?? '');
            $nomeguerra  = trim($linha[4] ?? '');
            $foto        = '66758a7f047ac.png';

            // Validações básicas
            if ($usuario === '') {
                $response['falhas'][] = ['linha' => $linhaExcel, 'erro' => 'Campo usuário vazio'];
                continue;
            }
            if ($senhaBruta === '') {
                $response['falhas'][] = ['linha' => $linhaExcel, 'erro' => 'Campo senha vazio'];
                continue;
            }

            // Verifica se o usuário já existe
            $verificaUsuario = $conexao->prepare("SELECT id FROM usuarios WHERE usuario = ?");
            $verificaUsuario->bind_param("s", $usuario);
            $verificaUsuario->execute();
            $verificaUsuario->store_result();
            if ($verificaUsuario->num_rows > 0) {
                $response['falhas'][] = ['linha' => $linhaExcel, 'erro' => 'Usuário já cadastrado no sistema'];
                $verificaUsuario->close();
                continue;
            }
            $verificaUsuario->close();

            // Verifica se a função é única e já foi usada
            if (in_array($funcao, $funcoesUnicas) && in_array($funcao, $funcoesJaExistentes)) {
                $response['falhas'][] = ['linha' => $linhaExcel, 'erro' => "Função '{$funcao}' já cadastrada para outro usuário"];
                continue;
            }

            $senha = password_hash($senhaBruta, PASSWORD_DEFAULT);

            $stmt = $conexao->prepare("INSERT INTO usuarios (postograd, usuario, senha, funcao, nomeguerra, foto, status) VALUES (?, ?, ?, ?, ?, ?, 'sim')");
            if (!$stmt) {
                $response['falhas'][] = ['linha' => $linhaExcel, 'erro' => 'Erro ao preparar statement'];
                continue;
            }

            $stmt->bind_param("ssssss", $postograd, $usuario, $senha, $funcao, $nomeguerra, $foto);

            if ($stmt->execute()) {
                $response['sucessos']++;

                // Log do cadastro
                $novoUsuarioId = $stmt->insert_id;
                $usuarioResponsavelId = $_SESSION['usuario_id'] ?? 0;
                $ip = $_SERVER['REMOTE_ADDR'];
                $navegador = $_SERVER['HTTP_USER_AGENT'];
                $acao = "Cadastrar usuário";
                $descricao = "Novo usuário cadastrado: $nomeguerra ($usuario)";

                $stmtLog = $conexao->prepare("INSERT INTO logs (usuario_id, acao, descricao, data_hora, ip, navegador) VALUES (?, ?, ?, NOW(), ?, ?)");
                $stmtLog->bind_param("issss", $usuarioResponsavelId, $acao, $descricao, $ip, $navegador);
                $stmtLog->execute();
                $stmtLog->close();

                // Marcar que essa função única já foi usada
                if (in_array($funcao, $funcoesUnicas)) {
                    $funcoesJaExistentes[] = $funcao;
                }

            } else {
                $erro = $stmt->error ?: 'Erro desconhecido';
                $response['falhas'][] = ['linha' => $linhaExcel, 'erro' => $erro];
            }

            $stmt->close();
        }

        echo json_encode($response);
    } catch (Exception $e) {
        echo json_encode([
            'status' => 'erro_leitura_excel',
            'mensagem' => $e->getMessage()
        ]);
    }

} else {
    echo json_encode([
        'status' => 'erro_upload',
        'mensagem' => 'Falha ao carregar o arquivo'
    ]);
}