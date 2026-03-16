<?php
include '../../conexao/config.php';
include '../funcoes/log_os.php'; // Se sua função estiver num arquivo

session_start();

$id_os = $_POST['id_os'] ?? null;
if (!$id_os) {
  echo 'ID da OS não recebido.';
  exit;
}

$usuarioLogado = $_SESSION['usuario_id'] ?? 0;

// Buscar valores antigos
$old_os = $conexao->query("SELECT * FROM os_principal WHERE id = $id_os")->fetch_assoc();

// Atualizar principal
$update = $conexao->prepare("
  UPDATE os_principal 
  SET data_encerramento = ?, observacao = ?, status = ?, tipo_mnt = ?, secao_rspns = ?, odometro_horimetro = ?, problema = ?, local_os = ?, 
      prox_mnt_prev_odo = ?, prox_mnt_prev_hor = ?, 
      manutencao_preventiva = ?, trocas_realizadas = ?, valornd30 = ?, valornd39 = ?, valorTOTAL = ?
  WHERE id = ?
");


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

$mnt_odo = ($mnt_odo === '' || !is_numeric($mnt_odo)) ? null : $mnt_odo;
$mnt_hor = ($mnt_hor === '' || !is_numeric($mnt_hor)) ? null : $mnt_hor;

$valornd30 = $_POST['valorND30'] ?? null;
$valornd39 = $_POST['valorND39'] ?? null;
$valorTotalGasto = $_POST['valorTotalGasto'] ?? null;

$valornd30 = ($valornd30 === '' || !is_numeric($valornd30)) ? null : $valornd30;
$valornd39 = ($valornd39 === '' || !is_numeric($valornd39)) ? null : $valornd39;
$valorTotalGasto = ($valorTotalGasto === '' || !is_numeric($valorTotalGasto)) ? null : $valorTotalGasto;


$update->bind_param(
  'ssssssssdssdddsi',
  $data_encerramento,
  $observacao,
  $status,
  $tipo_mnt,
  $secao_rspns,
  $odometro,
  $problema,
  $local,
  $mnt_odo,          // d
  $mnt_hor,          // s ou d (depende do tipo no banco)
  $preventiva,
  $trocas,
  $valornd30,        // d
  $valornd39,        // d
  $valorTotalGasto,  // d
  $id_os             // i
);
$update->execute();

// LOG - comparando alterações
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
    $valor_antigo = $old_os[$campo];
    if ($novo_valor != $valor_antigo) {
        $alteracoes[] = ucfirst($campo) . ": '$valor_antigo' → '$novo_valor'";
    }
}

// Limpar antigos
$conexao->query("DELETE FROM os_falhas WHERE id_osprincipal = $id_os");
$conexao->query("DELETE FROM os_pessoal WHERE id_osprincipal = $id_os");
$conexao->query("DELETE FROM os_rlzdmnt WHERE id_osprincipal = $id_os");
$conexao->query("DELETE FROM os_itens WHERE id_osprincipal = $id_os");

// Falhas
$falhas_log = [];
foreach ($_POST['secao_falha'] ?? [] as $i => $secao) {
  $falha = $_POST['falha_identificada'][$i];
  $militar = $_POST['militar_identificou'][$i];
  $conexao->query("INSERT INTO os_falhas (id_osprincipal, secao_falha, falha_identificada, militar_identificou) VALUES ('$id_os', '$secao', '$falha', '$militar')");
  $falhas_log[] = "Falha: Seção '$secao', Falha '$falha', Militar '$militar'";
}

// Pessoal
$pessoal_log = [];
foreach ($_POST['postograd'] ?? [] as $i => $grad) {
  $nome = $_POST['nome_guerra'][$i];
  $funcao = $_POST['funcao'][$i];
  $data = $_POST['data_emprego'][$i];
  $servico = $_POST['servico_exec'][$i];
  $conexao->query("INSERT INTO os_pessoal (id_osprincipal, postograd_militar, nome_militar, funcao_militar, data_emprego, servico_executado) VALUES ('$id_os', '$grad', '$nome', '$funcao', '$data', '$servico')");
  $pessoal_log[] = "Pessoal: Graduação '$grad', Nome '$nome', Função '$funcao', Data '$data', Serviço '$servico'";
}

// Serviços
$servicos_log = [];
$empresas = $_POST['empresa'] ?? [];
$execucoes = $_POST['execucao'] ?? [];
$qtds = $_POST['qtd_servico'] ?? [];
$valores_unt = $_POST['valor_unitario_servico'] ?? [];

$quantidadeServicos = count($empresas);

for ($i = 0; $i < $quantidadeServicos; $i++) {
  if (
    isset($empresas[$i], $execucoes[$i], $qtds[$i], $valores_unt[$i])
    && $empresas[$i] !== '' && $execucoes[$i] !== ''
  ) {
    $empresa = $empresas[$i];
    $exec = $execucoes[$i];
    $qtd = is_numeric($qtds[$i]) ? $qtds[$i] : 0;
    $valor_unt = is_numeric($valores_unt[$i]) ? $valores_unt[$i] : 0;

    $conexao->query("INSERT INTO os_rlzdmnt 
      (id_osprincipal, data_execucao, rlzd_mnt, tipo_rlzdmnt, qtd_servico, valor_unt, empresa)
      VALUES ('$id_os', CURDATE(), '$exec', '', '$qtd', '$valor_unt', '$empresa')");
    
    $servicos_log[] = "Serviço: Empresa '$empresa', Execução '$exec', Qtd '$qtd', Valor Unit. '$valor_unt'";
  }
}

// Materiais
$materiais_log = [];
foreach ($_POST['origem_item'] ?? [] as $i => $origem) {
  $desc = $_POST['descricao_item'][$i];
  $qtd = $_POST['qtd_item'][$i];
  $valor_unt = $_POST['valor_unitario_item'][$i];
  $valor_total = $_POST['valor_total_item'][$i];
  
  $conexao->query("INSERT INTO os_itens (id_osprincipal, tipo_item, itens_utilizados, quant_itens_utilizados, valor_itens, origem_item, valor_total_item) VALUES ('$id_os', '', '$desc', '$qtd', '$valor_unt', '$origem', '$valor_total')");

  $materiais_log[] = "Material: Descrição '$desc', Qtd '$qtd', Valor Unit. '$valor_unt', Total '$valor_total', Origem '$origem'";
}

// Registrar log geral
$descricao = "Alterações na OS ID $id_os: ";
if (!empty($alteracoes)) {
    $descricao .= implode("; ", $alteracoes) . ". ";
}

if (!empty($falhas_log)) {
    $descricao .= "Falhas adicionadas: " . implode("; ", $falhas_log) . ". ";
}
if (!empty($pessoal_log)) {
    $descricao .= "Pessoal adicionado: " . implode("; ", $pessoal_log) . ". ";
}
if (!empty($servicos_log)) {
    $descricao .= "Serviços adicionados: " . implode("; ", $servicos_log) . ". ";
}
if (!empty($materiais_log)) {
    $descricao .= "Materiais adicionados: " . implode("; ", $materiais_log) . ". ";
}

if (trim($descricao) != "Alterações na OS ID $id_os:") {
    registrar_log($conexao, $usuarioLogado, 'Editar OS', $descricao, $id_os);
}

echo 'ok';
?>
