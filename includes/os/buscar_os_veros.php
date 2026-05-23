<?php
// Ativar erros para depuração
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Retornar como JSON
header('Content-Type: application/json');

include '../../conexao/config.php';

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?? 0;
$retorno = ['sucesso' => false];

if ($id <= 0) {
    echo json_encode($retorno);
    exit;
}

// Consulta principal (OS + dados da frota com marca e modelo)
$stmt = $conexao->prepare("
    SELECT 
        os.*, 
        f.prefixo_sga, 
        f.chassi, 
        f.foto_capa,
        m.marca AS nome_marca,
        mo.nome_modelo AS nome_modelo
    FROM os_principal os 
    JOIN frota f ON os.id_frota = f.id 
    LEFT JOIN config_marcas m ON f.marca = m.id
    LEFT JOIN config_modelos mo ON f.modelo = mo.id
    WHERE os.id = ?
");
$stmt->bind_param("i", $id);
$stmt->execute();
$res = $stmt->get_result();

if ($res && $res->num_rows > 0) {
    $retorno['os'] = $res->fetch_assoc();

    $retorno['falhas'] = [];
    $retorno['pessoal'] = [];
    $retorno['servicos'] = [];
    $retorno['materiais'] = [];
    $retorno['logs'] = [];
	$retorno['fotos'] = [];
    $retorno['mnt_programadas'] = [];

    // Consulta segura com real_escape_string (apenas precaução adicional)
    $id_sql = $conexao->real_escape_string($id);

    // Falhas
    $falhas = $conexao->query("SELECT * FROM os_falhas WHERE id_osprincipal = $id_sql");
    if ($falhas) {
        while ($f = $falhas->fetch_assoc()) {
            $retorno['falhas'][] = $f;
        }
    }

    // Pessoal
    $pessoal = $conexao->query("SELECT * FROM os_pessoal WHERE id_osprincipal = $id_sql");
    if ($pessoal) {
        while ($p = $pessoal->fetch_assoc()) {
            $retorno['pessoal'][] = $p;
        }
    }

    // Serviços realizados
    $servicos = $conexao->query("SELECT * FROM os_rlzdmnt WHERE id_osprincipal = $id_sql");
    if ($servicos) {
        while ($s = $servicos->fetch_assoc()) {
            $retorno['servicos'][] = $s;
        }
    }

    // Materiais/Peças
    $materiais = $conexao->query("SELECT * FROM os_itens WHERE id_osprincipal = $id_sql");
    if ($materiais) {
        while ($m = $materiais->fetch_assoc()) {
            $retorno['materiais'][] = $m;
        }
    }
	
	// Fotos da OS
$stmtFotos = $conexao->prepare("
    SELECT 
        id,
        nome_arquivo,
        caminho,
        legenda,
        data_upload
    FROM os_fotos
    WHERE id_osprincipal = ?
    ORDER BY id DESC
");

$stmtFotos->bind_param("i", $id);
$stmtFotos->execute();

$resFotos = $stmtFotos->get_result();

while ($foto = $resFotos->fetch_assoc()) {
    $retorno['fotos'][] = $foto;
}

	// Manutenções programadas executadas nesta OS
$stmtMnt = $conexao->prepare("
    SELECT 
        me.id,
        me.id_plano,
        me.odometro_horimetro_execucao,
        me.data_execucao,
        mp.descricao,
        mp.tipo_controle,
        mp.valor_inicial,
        mp.intervalo_valor,
        mp.intervalo_dias
    FROM mnt_execucoes me
    INNER JOIN mnt_planos mp ON mp.id = me.id_plano
    WHERE me.id_osprincipal = ?
    ORDER BY me.data_execucao DESC, me.id DESC
");

$stmtMnt->bind_param("i", $id);
$stmtMnt->execute();

$resMnt = $stmtMnt->get_result();

while ($mnt = $resMnt->fetch_assoc()) {
    $retorno['mnt_programadas'][] = $mnt;
}
	
    // Logs
    $logs = $conexao->query("
        SELECT l.*, u.nomeguerra, u.postograd 
        FROM logs l 
        LEFT JOIN usuarios u ON u.id = l.usuario_id 
        WHERE l.os_id = $id_sql 
        ORDER BY l.data_hora DESC
    ");
    if ($logs) {
        while ($log = $logs->fetch_assoc()) {
            $retorno['logs'][] = $log;
        }
    }

    $retorno['sucesso'] = true;
}

echo json_encode($retorno, JSON_UNESCAPED_UNICODE);
