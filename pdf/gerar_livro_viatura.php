<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once '../conexao/config.php';
require_once 'vendor/autoload.php';

use Dompdf\Dompdf;

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    echo 'Viatura inválida';
    exit;
}

/**
 * Monta "IN (?, ?, ?)" com placeholders e types
 */
function buildInPlaceholders(array $ids): array {
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $types = str_repeat('i', count($ids));
    return [$placeholders, $types];
}

// ------------------- BUSCAR DADOS DA VIATURA -------------------
$stmt = $conexao->prepare("
    SELECT 
      f.*,
      m.marca AS marca_nome,
      mo.nome_modelo AS modelo_nome
    FROM frota f
    LEFT JOIN config_marcas m ON f.marca = m.id
    LEFT JOIN config_modelos mo ON f.modelo = mo.id
    WHERE f.id = ?
    LIMIT 1
");
$stmt->bind_param("i", $id);
$stmt->execute();
$viatura = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$viatura) {
    echo 'Viatura não encontrada';
    exit;
}

// ------------------- ORDENS DE SERVIÇO (pega IDs e dados) -------------------
$stmtOs = $conexao->prepare("
  SELECT 
    id, data_abertura, status, problema
  FROM os_principal
  WHERE id_frota = ?
  ORDER BY data_abertura DESC
  LIMIT 300
");
$stmtOs->bind_param("i", $id);
$stmtOs->execute();
$ordensServico = $stmtOs->get_result()->fetch_all(MYSQLI_ASSOC);
$stmtOs->close();

// IDs das OS (para buscar pedidos do almox sem JOIN/subquery pesada)
$idsOS = [];
foreach ($ordensServico as $os) {
    $idsOS[] = (int)$os['id'];
}

// ------------------- PEDIDOS ALMOXARIFADO (2 PASSOS: SEM JOIN) -------------------
$pedidosAlmox = [];

if (!empty($idsOS)) {
    [$ph, $types] = buildInPlaceholders($idsOS);

    $sqlPedidos = "
        SELECT 
          id,
          data_pedido,
          status_pedido,
          local_pedido,
          militar_solicitante
        FROM almox_pedidos_princ
        WHERE id_os IN ($ph)
        ORDER BY data_pedido DESC
        LIMIT 300
    ";

    $stmtPedidos = $conexao->prepare($sqlPedidos);
    $stmtPedidos->bind_param($types, ...$idsOS);
    $stmtPedidos->execute();
    $pedidosAlmox = $stmtPedidos->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmtPedidos->close();
}

// ------------------- PEDIDOS FORNECEDORES (sem JOIN grande, com LIMIT) -------------------
$pedidosForn = [];
$stmtForn = $conexao->prepare("
    SELECT 
      pf.id,
      pf.data_pedido,
      pf.solicitante,
      pf.local_pedido,
      pf.situacao_pedido
    FROM fin_pedidos_forn pf
    WHERE pf.id_vtr = ?
    ORDER BY pf.data_pedido DESC
    LIMIT 300
");
$stmtForn->bind_param("i", $id);
$stmtForn->execute();
$pedidosForn = $stmtForn->get_result()->fetch_all(MYSQLI_ASSOC);
$stmtForn->close();

// ------------------- (OPCIONAL) Complemento de Ordem/Empresa por pedido (2 passos) -------------------
// Se você precisa MESMO mostrar ordem_id/empresa/status no PDF, faça sem JOIN pesado:
$mapOrdemPorPedido = []; // [id_pedido => ['ordem_id'=>..., 'empresa_nome'=>..., 'ordem_status'=>...]]
if (!empty($pedidosForn)) {

    $idsPedidosForn = array_map(fn($p) => (int)$p['id'], $pedidosForn);
    [$phP, $typesP] = buildInPlaceholders($idsPedidosForn);

    // 1) pega ordemforn ligada a cada pedido
    $sqlOP = "
        SELECT 
          op.id_pedido,
          ofn.id AS ordem_id,
          ofn.empresa_nome,
          ofn.status AS ordem_status
        FROM fin_ordemforn_pedidos op
        INNER JOIN fin_ordemforn ofn ON ofn.id = op.id_ordemforn
        WHERE op.id_pedido IN ($phP)
    ";
    $stmtOP = $conexao->prepare($sqlOP);
    $stmtOP->bind_param($typesP, ...$idsPedidosForn);
    $stmtOP->execute();
    $rsOP = $stmtOP->get_result();
    while ($r = $rsOP->fetch_assoc()) {
        $mapOrdemPorPedido[(int)$r['id_pedido']] = [
            'ordem_id'     => $r['ordem_id'],
            'empresa_nome' => $r['empresa_nome'],
            'ordem_status' => $r['ordem_status'],
        ];
    }
    $stmtOP->close();

    // injeta no array final
    foreach ($pedidosForn as &$p) {
        $extra = $mapOrdemPorPedido[(int)$p['id']] ?? null;
        $p['ordem_id']     = $extra['ordem_id'] ?? '-';
        $p['empresa_nome'] = $extra['empresa_nome'] ?? '-';
        $p['ordem_status'] = $extra['ordem_status'] ?? '-';
    }
    unset($p);
}

// ------------------- FICHAS (reduz colunas + LIMIT) -------------------
$stmtFichas = $conexao->prepare("
    SELECT 
      id, data_abertura, destino, motorista, status
    FROM sta_fichas
    WHERE id_viatura = ?
    ORDER BY data_abertura DESC
    LIMIT 300
");
$stmtFichas->bind_param("i", $id);
$stmtFichas->execute();
$fichas = $stmtFichas->get_result()->fetch_all(MYSQLI_ASSOC);
$stmtFichas->close();

// ------------------- LOGS (LIMIT para não explodir) -------------------
$stmtLogs = $conexao->prepare("
    SELECT 
      l.data_hora,
      l.acao,
      l.descricao,
      CONCAT(u.postograd, ' - ', u.nomeguerra) AS responsavel_nome
    FROM logs l
    LEFT JOIN usuarios u ON l.usuario_id = u.id
    WHERE l.frota_id = ?
    ORDER BY l.data_hora DESC
    LIMIT 500
");
$stmtLogs->bind_param("i", $id);
$stmtLogs->execute();
$logs = $stmtLogs->get_result()->fetch_all(MYSQLI_ASSOC);
$stmtLogs->close();

// ------------------- HTML DO PDF -------------------
$html = '<!DOCTYPE html><html><head><meta charset="UTF-8"><style>
body { font-family:"Segoe UI", Roboto, Arial, sans-serif; color:#333; margin:20px; font-size:12px; }
header { text-align:center; border-bottom:2px solid #0d6efd; padding-bottom:10px; margin-bottom:20px; }
header img { width:70px; margin-bottom:5px; }
header h1 { margin:0; font-size:20px; color:#0d6efd; }
header p { margin:2px 0; font-size:12px; color:#555; }
h2 { color:#0d6efd; margin:10px 0 5px; border-bottom:1px solid #ddd; padding-bottom:3px; }
h4 { color:#0d6efd; margin:12px 0 6px; }
table { width:100%; border-collapse:collapse; margin-top:6px; }
th, td { border:1px solid #dee2e6; padding:6px; text-align:left; vertical-align:top; }
th { background:#0d6efd; color:#fff; font-size:12px; }
td { font-size:11px; }
img.viatura { width:120px; height:120px; border-radius:6px; object-fit:cover; display:block; margin:10px auto; border:1px solid #ccc; }
footer { position:fixed; bottom:10px; left:0; right:0; text-align:center; font-size:10px; color:#666; border-top:1px solid #ddd; padding-top:5px; }
</style></head><body>';

// Header
$html .= '<header>
            <img src="https://gceem.22web.org/uploads/logo_exercito.png" alt="Logo">
            <h1>Relatório da Viatura</h1>
            <p>Controle de Manutenção e Suprimento</p>
          </header>';

// Foto
$foto = (!empty($viatura['foto_capa']) && file_exists('https://gceem.22web.org/uploads/frotas/'.$viatura['foto_capa']))
    ? '../uploads/frotas/'.$viatura['foto_capa']
    : '../uploads/frotas/sem-foto.png';

$html .= '<img src="'.$foto.'" class="viatura" alt="Foto da viatura">';

$html .= '<h2>Viatura '.htmlspecialchars($viatura['prefixo_sga'] ?? '').'</h2>';

$html .= '<p><strong>Marca / Modelo:</strong> '.htmlspecialchars($viatura['marca_nome'] ?? '-').' / '.htmlspecialchars($viatura['modelo_nome'] ?? '-').'<br>
           <strong>Placa:</strong> '.htmlspecialchars($viatura['placa'] ?? '-').' | 
           <strong>Tipo:</strong> '.htmlspecialchars($viatura['tipo'] ?? '-').'<br>
           <strong>Status:</strong> '.htmlspecialchars($viatura['disponibilidade'] ?? '-').' | 
           <strong>Localização:</strong> '.htmlspecialchars($viatura['destino'] ?? '-').'</p>';

// Helper tabela
function criarTabela($titulo, $colunas, $dados){
    $html = '<h4>'.htmlspecialchars($titulo).'</h4>';
    if($dados){
        $html .= '<table><thead><tr>';
        foreach($colunas as $label) $html .= '<th>'.htmlspecialchars($label).'</th>';
        $html .= '</tr></thead><tbody>';

        foreach($dados as $linha){
            $html .= '<tr>';
            foreach($colunas as $key => $label){
                $valor = $linha[$key] ?? '-';
                $html .= '<td>'.nl2br(htmlspecialchars((string)$valor)).'</td>';
            }
            $html .= '</tr>';
        }
        $html .= '</tbody></table>';
    } else {
        $html .= '<p style="color:#999;font-size:11px;">Nenhum registro encontrado.</p>';
    }
    return $html;
}

// Seções
$html .= criarTabela('Ordens de Serviço',
    ['id'=>'ID','data_abertura'=>'Data Abertura','status'=>'Status','problema'=>'Problema'],
    $ordensServico
);

$html .= criarTabela('Pedidos Almoxarifado',
    ['id'=>'ID','data_pedido'=>'Data Pedido','status_pedido'=>'Status','local_pedido'=>'Local','militar_solicitante'=>'Solicitante'],
    $pedidosAlmox
);

$html .= criarTabela('Pedidos Fornecedores',
    ['id'=>'ID','data_pedido'=>'Data Pedido','solicitante'=>'Solicitante','local_pedido'=>'Local','situacao_pedido'=>'Situação','ordem_id'=>'Ordem','empresa_nome'=>'Empresa','ordem_status'=>'Status Ordem'],
    $pedidosForn
);

$html .= criarTabela('Fichas de Serviço',
    ['id'=>'ID','data_abertura'=>'Data Abertura','destino'=>'Destino','motorista'=>'Motorista','status'=>'Status'],
    $fichas
);

$html .= criarTabela('Logs',
    ['data_hora'=>'Data Hora','acao'=>'Ação','descricao'=>'Descrição','responsavel_nome'=>'Responsável'],
    $logs
);

$html .= '<footer>Gerado automaticamente em '.date("d/m/Y H:i").' | Sistema de Gestão da Cia E Eqp Mnt</footer>';
$html .= '</body></html>';

// PDF
$dompdf = new Dompdf(['enable_remote' => true]);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();
$dompdf->stream("Livro_Viatura_".($viatura['prefixo_sga'] ?? $id).".pdf", ['Attachment'=>false]);
exit;