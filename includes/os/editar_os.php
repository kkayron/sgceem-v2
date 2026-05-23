<?php
include '../../conexao/config.php';
include '../funcoes/log_os.php';

session_start();

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$id_os = $_POST['id_os'] ?? null;

if (!$id_os || !is_numeric($id_os)) {
    echo 'ID da OS não recebido.';
    exit;
}

$id_os = (int)$id_os;
$usuarioLogado = $_SESSION['usuario_id'] ?? 0;

try {
    $conexao->begin_transaction();

    $stmtOld = $conexao->prepare("SELECT * FROM os_principal WHERE id = ? LIMIT 1");
    $stmtOld->bind_param("i", $id_os);
    $stmtOld->execute();
    $old_os = $stmtOld->get_result()->fetch_assoc();

    if (!$old_os) {
        throw new Exception("OS não encontrada.");
    }

    $data_encerramento = $_POST['data_encerramento'] ?? '';
    $observacao = $_POST['observacoes'] ?? '';
    $status = $_POST['situacao_os'] ?? '';
    $tipo_mnt = $_POST['tipo_mnt'] ?? '';
    $secao_rspns = $_POST['secao_rspns'] ?? '';
    $odometro = $_POST['odometro'] ?? '';
    $problema = $_POST['falhas_solicitadas'] ?? '';
    $local = $_POST['local_mnt'] ?? '';

    $mnt_odo = $_POST['proxima_mnt_tempo'] ?? null;
    $mnt_hor = $_POST['proxima_mnt_valor'] ?? null;

    $mnt_odo = ($mnt_odo === '' || !is_numeric($mnt_odo)) ? null : (float)$mnt_odo;
    $mnt_hor = ($mnt_hor === '' || !is_numeric($mnt_hor)) ? null : (float)$mnt_hor;

    $preventiva = $_POST['manutencao_preventiva'] ?? 0;
    $preventiva = ($preventiva == '1') ? 1 : 0;

    $trocas = $_POST['trocas_realizadas'] ?? '';

    $valornd30 = $_POST['valorND30'] ?? null;
    $valornd39 = $_POST['valorND39'] ?? null;
    $valorTotalGasto = $_POST['valorTotalGasto'] ?? null;

    $valornd30 = ($valornd30 === '' || !is_numeric($valornd30)) ? null : (float)$valornd30;
    $valornd39 = ($valornd39 === '' || !is_numeric($valornd39)) ? null : (float)$valornd39;
    $valorTotalGasto = ($valorTotalGasto === '' || !is_numeric($valorTotalGasto)) ? null : (float)$valorTotalGasto;

    $update = $conexao->prepare("
        UPDATE os_principal 
        SET 
            data_encerramento = ?,
            observacao = ?,
            status = ?,
            tipo_mnt = ?,
            secao_rspns = ?,
            odometro_horimetro = ?,
            problema = ?,
            local_os = ?,
            prox_mnt_prev_odo = ?,
            prox_mnt_prev_hor = ?,
            manutencao_preventiva = ?,
            trocas_realizadas = ?,
            valornd30 = ?,
            valornd39 = ?,
            valorTOTAL = ?
        WHERE id = ?
    ");

    $update->bind_param(
        'ssssssssddisdddi',
        $data_encerramento,
        $observacao,
        $status,
        $tipo_mnt,
        $secao_rspns,
        $odometro,
        $problema,
        $local,
        $mnt_odo,
        $mnt_hor,
        $preventiva,
        $trocas,
        $valornd30,
        $valornd39,
        $valorTotalGasto,
        $id_os
    );

    $update->execute();

    $alteracoes = [];
    $novos_valores = [
        'observacao' => $observacao,
        'status' => $status,
        'tipo_mnt' => $tipo_mnt,
        'secao_rspns' => $secao_rspns,
        'odometro_horimetro' => $odometro,
        'problema' => $problema,
        'local_os' => $local,
        'prox_mnt_prev_odo' => $mnt_odo,
        'prox_mnt_prev_hor' => $mnt_hor,
        'manutencao_preventiva' => $preventiva,
        'trocas_realizadas' => $trocas,
        'valornd30' => $valornd30,
        'valornd39' => $valornd39,
        'valorTOTAL' => $valorTotalGasto
    ];

    foreach ($novos_valores as $campo => $novo_valor) {
        $valor_antigo = $old_os[$campo] ?? '';
        if ($novo_valor != $valor_antigo) {
            $alteracoes[] = ucfirst($campo) . ": '$valor_antigo' → '$novo_valor'";
        }
    }

    $stmtDel = $conexao->prepare("DELETE FROM os_falhas WHERE id_osprincipal = ?");
    $stmtDel->bind_param("i", $id_os);
    $stmtDel->execute();

    $stmtDel = $conexao->prepare("DELETE FROM os_pessoal WHERE id_osprincipal = ?");
    $stmtDel->bind_param("i", $id_os);
    $stmtDel->execute();

    $stmtDel = $conexao->prepare("DELETE FROM os_rlzdmnt WHERE id_osprincipal = ?");
    $stmtDel->bind_param("i", $id_os);
    $stmtDel->execute();

    $stmtDel = $conexao->prepare("DELETE FROM os_itens WHERE id_osprincipal = ?");
    $stmtDel->bind_param("i", $id_os);
    $stmtDel->execute();

    // ===============================
    // SINCRONIZA MANUTENÇÕES PROGRAMADAS
    // ===============================
    $stmtDelMnt = $conexao->prepare("DELETE FROM mnt_execucoes WHERE id_osprincipal = ?");
    $stmtDelMnt->bind_param("i", $id_os);
    $stmtDelMnt->execute();

    $manutencoes_programadas = $_POST['manutencoes_programadas'] ?? [];

    if (!is_array($manutencoes_programadas)) {
        $manutencoes_programadas = [];
    }

    $manutencoes_programadas = array_filter($manutencoes_programadas, 'is_numeric');
    $manutencoes_programadas = array_map('intval', $manutencoes_programadas);

    if (!empty($manutencoes_programadas)) {
        $stmtDadosOS = $conexao->prepare("
            SELECT 
                os.id_frota,
                os.odometro_horimetro,
                os.data_abertura,
                f.marca,
                f.modelo
            FROM os_principal os
            INNER JOIN frota f ON f.id = os.id_frota
            WHERE os.id = ?
            LIMIT 1
        ");
        $stmtDadosOS->bind_param("i", $id_os);
        $stmtDadosOS->execute();
        $dadosOS = $stmtDadosOS->get_result()->fetch_assoc();

        if ($dadosOS) {
            $id_frota_execucao = (int)$dadosOS['id_frota'];

            $data_execucao = !empty($data_encerramento)
                ? $data_encerramento
                : date('Y-m-d', strtotime($dadosOS['data_abertura']));

            $odometro_execucao = is_numeric($odometro)
                ? (float)$odometro
                : (is_numeric($dadosOS['odometro_horimetro']) ? (float)$dadosOS['odometro_horimetro'] : 0);

            $stmtValidaPlano = $conexao->prepare("
                SELECT id
                FROM mnt_planos
                WHERE id = ?
                  AND id_marca = ?
                  AND id_modelo = ?
                  AND ativo = 1
                LIMIT 1
            ");

            $stmtInsMnt = $conexao->prepare("
                INSERT INTO mnt_execucoes (
                    id_plano,
                    id_frota,
                    id_osprincipal,
                    odometro_horimetro_execucao,
                    data_execucao
                ) VALUES (?, ?, ?, ?, ?)
            ");

            foreach ($manutencoes_programadas as $id_plano) {
                $stmtValidaPlano->bind_param(
                    "iii",
                    $id_plano,
                    $dadosOS['marca'],
                    $dadosOS['modelo']
                );
                $stmtValidaPlano->execute();
                $resValidaPlano = $stmtValidaPlano->get_result();

                if ($resValidaPlano->num_rows === 0) {
                    continue;
                }

                $stmtInsMnt->bind_param(
                    "iiids",
                    $id_plano,
                    $id_frota_execucao,
                    $id_os,
                    $odometro_execucao,
                    $data_execucao
                );
                $stmtInsMnt->execute();
            }
        }
    }

    // ===============================
    // FALHAS
    // ===============================
    $falhas_log = [];
    $stmtFalha = $conexao->prepare("
        INSERT INTO os_falhas (
            id_osprincipal,
            secao_falha,
            falha_identificada,
            militar_identificou
        ) VALUES (?, ?, ?, ?)
    ");

    foreach ($_POST['secao_falha'] ?? [] as $i => $secao) {
        $falha = $_POST['falha_identificada'][$i] ?? '';
        $militar = $_POST['militar_identificou'][$i] ?? '';

        if ($secao === '' && $falha === '' && $militar === '') {
            continue;
        }

        $stmtFalha->bind_param("isss", $id_os, $secao, $falha, $militar);
        $stmtFalha->execute();

        $falhas_log[] = "Falha: Seção '$secao', Falha '$falha', Militar '$militar'";
    }

    // ===============================
    // PESSOAL
    // ===============================
    $pessoal_log = [];
    $stmtPessoal = $conexao->prepare("
        INSERT INTO os_pessoal (
            id_osprincipal,
            postograd_militar,
            nome_militar,
            funcao_militar,
            data_emprego,
            servico_executado
        ) VALUES (?, ?, ?, ?, ?, ?)
    ");

    foreach ($_POST['postograd'] ?? [] as $i => $grad) {
        $nome = $_POST['nome_guerra'][$i] ?? '';
        $funcao = $_POST['funcao'][$i] ?? '';
        $data = $_POST['data_emprego'][$i] ?? null;
        $servico = $_POST['servico_exec'][$i] ?? '';

        if ($grad === '' && $nome === '' && $funcao === '' && $servico === '') {
            continue;
        }

        $stmtPessoal->bind_param("isssss", $id_os, $grad, $nome, $funcao, $data, $servico);
        $stmtPessoal->execute();

        $pessoal_log[] = "Pessoal: Graduação '$grad', Nome '$nome', Função '$funcao', Data '$data', Serviço '$servico'";
    }

    // ===============================
    // SERVIÇOS
    // ===============================
    $servicos_log = [];
    $stmtServico = $conexao->prepare("
        INSERT INTO os_rlzdmnt (
            id_osprincipal,
            data_execucao,
            rlzd_mnt,
            tipo_rlzdmnt,
            qtd_servico,
            valor_unt,
            empresa
        ) VALUES (?, CURDATE(), ?, '', ?, ?, ?)
    ");

    $empresas = $_POST['empresa'] ?? [];
    $execucoes = $_POST['execucao'] ?? [];
    $qtds = $_POST['qtd_servico'] ?? [];
    $valores_unt = $_POST['valor_unitario_servico'] ?? [];

    for ($i = 0; $i < count($empresas); $i++) {
        $empresa = $empresas[$i] ?? '';
        $exec = $execucoes[$i] ?? '';
        $qtd = is_numeric($qtds[$i] ?? null) ? (float)$qtds[$i] : 0;
        $valor_unt = is_numeric($valores_unt[$i] ?? null) ? (float)$valores_unt[$i] : 0;

        if ($empresa === '' && $exec === '') {
            continue;
        }

        $stmtServico->bind_param("isdds", $id_os, $exec, $qtd, $valor_unt, $empresa);
        $stmtServico->execute();

        $servicos_log[] = "Serviço: Empresa '$empresa', Execução '$exec', Qtd '$qtd', Valor Unit. '$valor_unt'";
    }

    // ===============================
    // MATERIAIS
    // ===============================
    $materiais_log = [];
    $stmtMaterial = $conexao->prepare("
        INSERT INTO os_itens (
            id_osprincipal,
            tipo_item,
            itens_utilizados,
            quant_itens_utilizados,
            valor_itens,
            origem_item,
            valor_total_item
        ) VALUES (?, '', ?, ?, ?, ?, ?)
    ");

    foreach ($_POST['origem_item'] ?? [] as $i => $origem) {
        $desc = $_POST['descricao_item'][$i] ?? '';
        $qtd = is_numeric($_POST['qtd_item'][$i] ?? null) ? (float)$_POST['qtd_item'][$i] : 0;
        $valor_unt = is_numeric($_POST['valor_unitario_item'][$i] ?? null) ? (float)$_POST['valor_unitario_item'][$i] : 0;
        $valor_total = is_numeric($_POST['valor_total_item'][$i] ?? null) ? (float)$_POST['valor_total_item'][$i] : 0;

        if ($origem === '' && $desc === '') {
            continue;
        }

        $stmtMaterial->bind_param("isddsd", $id_os, $desc, $qtd, $valor_unt, $origem, $valor_total);
        $stmtMaterial->execute();

        $materiais_log[] = "Material: Descrição '$desc', Qtd '$qtd', Valor Unit. '$valor_unt', Total '$valor_total', Origem '$origem'";
    }
	
// ===============================
// EXCLUIR FOTOS DA OS
// ===============================
$fotos_excluir_log = [];

$fotosExcluir = $_POST['fotos_excluir'] ?? '';

if (!empty($fotosExcluir)) {
    $idsFotos = array_filter(array_map('intval', explode(',', $fotosExcluir)));

    if (!empty($idsFotos)) {
        $placeholders = implode(',', array_fill(0, count($idsFotos), '?'));
        $types = str_repeat('i', count($idsFotos));

        $stmtBuscaFotos = $conexao->prepare("
            SELECT id, caminho, nome_arquivo
            FROM os_fotos
            WHERE id_osprincipal = ?
              AND id IN ($placeholders)
        ");

        $paramsBusca = array_merge([$id_os], $idsFotos);
        $typesBusca = 'i' . $types;

        $stmtBuscaFotos->bind_param($typesBusca, ...$paramsBusca);
        $stmtBuscaFotos->execute();

        $resFotosExcluir = $stmtBuscaFotos->get_result();

        $fotosParaExcluir = [];

        while ($foto = $resFotosExcluir->fetch_assoc()) {
            $fotosParaExcluir[] = $foto;
        }

        if (!empty($fotosParaExcluir)) {
            $stmtDelFoto = $conexao->prepare("
                DELETE FROM os_fotos
                WHERE id_osprincipal = ?
                  AND id = ?
            ");

            foreach ($fotosParaExcluir as $foto) {
                $idFoto = (int)$foto['id'];

                $stmtDelFoto->bind_param("ii", $id_os, $idFoto);
                $stmtDelFoto->execute();

                $caminhoFisico = '../../' . ltrim($foto['caminho'], '/');

                if (is_file($caminhoFisico)) {
                    @unlink($caminhoFisico);
                }

                $fotos_excluir_log[] = "Foto '{$foto['nome_arquivo']}' excluída";
            }
        }
    }
}
	
// ===============================
// FOTOS DA OS
// ===============================
$fotos_log = [];

if (!empty($_FILES['fotos_os']['name'][0])) {

    $pastaBase = '../../uploads/os_fotos/';
    $pastaRelativa = 'uploads/os_fotos/';

    if (!is_dir($pastaBase)) {
        mkdir($pastaBase, 0775, true);
    }

    $permitidos = ['jpg', 'jpeg', 'png', 'webp'];
    $legendaFoto = trim($_POST['legenda_foto_os'] ?? '');

    $stmtFoto = $conexao->prepare("
        INSERT INTO os_fotos (
            id_osprincipal,
            nome_arquivo,
            caminho,
            legenda,
            usuario_id
        ) VALUES (?, ?, ?, ?, ?)
    ");

    foreach ($_FILES['fotos_os']['name'] as $i => $nomeOriginal) {
        if ($_FILES['fotos_os']['error'][$i] !== UPLOAD_ERR_OK) {
            continue;
        }

        $tmp = $_FILES['fotos_os']['tmp_name'][$i];
        $tamanho = $_FILES['fotos_os']['size'][$i];

        if ($tamanho > 5 * 1024 * 1024) {
            continue;
        }

        $ext = strtolower(pathinfo($nomeOriginal, PATHINFO_EXTENSION));

        if (!in_array($ext, $permitidos)) {
            continue;
        }

        $mime = mime_content_type($tmp);

        if (strpos($mime, 'image/') !== 0) {
            continue;
        }

        $novoNome = 'os_' . $id_os . '_' . date('YmdHis') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;

        $destinoFisico = $pastaBase . $novoNome;
        $destinoBanco = $pastaRelativa . $novoNome;

        if (move_uploaded_file($tmp, $destinoFisico)) {
            $stmtFoto->bind_param(
                "isssi",
                $id_os,
                $nomeOriginal,
                $destinoBanco,
                $legendaFoto,
                $usuarioLogado
            );

            $stmtFoto->execute();

            $fotos_log[] = "Foto '$nomeOriginal' enviada";
        }
    }
}

    // ===============================
    // LOG
    // ===============================
    $descricaoLog = "Alterações na OS ID $id_os: ";

    if (!empty($alteracoes)) {
        $descricaoLog .= implode("; ", $alteracoes) . ". ";
    }
	
	if (!empty($fotos_log)) {
    $descricaoLog .= "Fotos adicionadas: " . implode("; ", $fotos_log) . ". ";
}

    if (!empty($falhas_log)) {
        $descricaoLog .= "Falhas adicionadas: " . implode("; ", $falhas_log) . ". ";
    }

    if (!empty($pessoal_log)) {
        $descricaoLog .= "Pessoal adicionado: " . implode("; ", $pessoal_log) . ". ";
    }
	
	if (!empty($fotos_excluir_log)) {
    $descricaoLog .= "Fotos excluídas: " . implode("; ", $fotos_excluir_log) . ". ";
}

    if (!empty($servicos_log)) {
        $descricaoLog .= "Serviços adicionados: " . implode("; ", $servicos_log) . ". ";
    }

    if (!empty($materiais_log)) {
        $descricaoLog .= "Materiais adicionados: " . implode("; ", $materiais_log) . ". ";
    }

    if (trim($descricaoLog) !== "Alterações na OS ID $id_os:") {
        registrar_log($conexao, $usuarioLogado, 'Editar OS', $descricaoLog, $id_os);
    }

    $conexao->commit();

    echo 'ok';

} catch (Throwable $e) {
    $conexao->rollback();
    echo 'Erro interno: ' . $e->getMessage();
}
?>