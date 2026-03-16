<?php
session_start();
require_once 'vendor/autoload.php';
require_once '../conexao/config.php';

use Dompdf\Dompdf;
use Dompdf\Options;

ini_set('memory_limit', '512M');

// ===============================
// Usuário / Batalhões permitidos
// ===============================
$id_om_usuario = $_SESSION['usuario']['batalhao'] ?? 0;
if (!$id_om_usuario) {
    die('Sessão expirada. Faça login novamente.');
}

$sqlNivel = "SELECT nivel FROM organizacoes_militares WHERE id = ?";
$stmtNivel = $conexao->prepare($sqlNivel);
$stmtNivel->bind_param("i", $id_om_usuario);
$stmtNivel->execute();
$nivelUsuario = (int)($stmtNivel->get_result()->fetch_assoc()['nivel'] ?? 0);
$stmtNivel->close();

if ($nivelUsuario == 1) {
    $sqlBatalhoes = "SELECT id FROM organizacoes_militares";
    $resBatalhoes = $conexao->query($sqlBatalhoes);
} else {
    $sqlBatalhoes = "
        SELECT om.id
        FROM organizacoes_militares om
        WHERE om.id = ?
        OR om.id IN (
            SELECT id_om_menor FROM organizacoes_militares_sub WHERE id_om_maior = ?
        )
    ";
    $stmtB = $conexao->prepare($sqlBatalhoes);
    $stmtB->bind_param("i", $id_om_usuario, $id_om_usuario);
    $stmtB->execute();
    $resBatalhoes = $stmtB->get_result();
}

$batalhoesPermitidos = [];
while ($bat = $resBatalhoes->fetch_assoc()) {
    $batalhoesPermitidos[] = (int)$bat['id'];
}
if (isset($stmtB)) $stmtB->close();

if (empty($batalhoesPermitidos)) {
    die('Nenhum batalhão disponível para este usuário.');
}

// ===============================
// IDs recebidos
// ===============================
$idsRaw = $_GET['ids'] ?? '';
$ids = array_values(array_filter(array_map('intval', explode(',', $idsRaw))));
$ids = array_values(array_unique(array_filter($ids, fn($v) => $v > 0)));

if (empty($ids)) {
    die('Nenhuma ficha selecionada.');
}

// limite (proteção)
$MAX = 50;
if (count($ids) > $MAX) {
    die("Muitas fichas selecionadas. Máximo permitido: {$MAX}.");
}

// ===============================
// Brasão Base64
// ===============================
$path = 'imagens/brasao.png';
$base64 = '';
if (file_exists($path)) {
    $type = pathinfo($path, PATHINFO_EXTENSION);
    $data = file_get_contents($path);
    $base64 = 'data:image/' . $type . ';base64,' . base64_encode($data);
}

// ===============================
// Busca Cmt Cia E Eqp Mnt (1x)
// ===============================
$sqlCmt = "SELECT nomecompleto FROM usuarios WHERE funcao LIKE '%Cmt Cia E Eqp Mnt%' LIMIT 1";
$resCmt = $conexao->query($sqlCmt);
$cmt_ceem = $resCmt->fetch_assoc()['nomecompleto'] ?? '________________________________';

// ===============================
// Query das fichas (IN) + permissão por batalhão
// ===============================
$phIds = implode(',', array_fill(0, count($ids), '?'));
$phBat = implode(',', array_fill(0, count($batalhoesPermitidos), '?'));

$sql = "
    SELECT 
        f.*,
        om.nome AS nome_batalhao,
        om.abreviatura AS sigla_batalhao,
        v.prefixo_sga,
        v.prefixo_velho,
        v.ano,
        m.marca AS nome_marca,
        mo.nome_modelo
    FROM sta_fichas f
    LEFT JOIN organizacoes_militares om ON om.id = f.batalhao
    LEFT JOIN frota v ON v.id = f.id_viatura
    LEFT JOIN config_marcas m ON v.marca = m.id
    LEFT JOIN config_modelos mo ON v.modelo = mo.id
    WHERE f.id IN ($phIds)
      AND f.batalhao IN ($phBat)
    ORDER BY FIELD(f.id, $phIds)
";

$tipos = str_repeat('i', count($ids)) . str_repeat('i', count($batalhoesPermitidos)) . str_repeat('i', count($ids));
$params = array_merge($ids, $batalhoesPermitidos, $ids);

$stmt = $conexao->prepare($sql);
$stmt->bind_param($tipos, ...$params);
$stmt->execute();
$res = $stmt->get_result();

$fichas = [];
while ($row = $res->fetch_assoc()) {
    $fichas[] = $row;
}
$stmt->close();

if (empty($fichas)) {
    die('Nenhuma ficha encontrada (ou sem permissão).');
}

// ===============================
// Monta HTML (todas as fichas)
// ===============================
function montarHtmlFicha($ficha, $base64, $cmt_ceem) {
    // garante campos usados
    $ficha['cmt_ceem'] = $ficha['cmt_ceem'] ?? $cmt_ceem;
    $ficha['ch_sta']   = $ficha['ch_sta'] ?? '________________________________';

    $data_abertura = !empty($ficha['data_abertura']) ? date('d/m/Y', strtotime($ficha['data_abertura'])) : '';
    $data_prevista = !empty($ficha['data_prevista']) ? date('d/m/Y', strtotime($ficha['data_prevista'])) : '';

    $html = '
    <div class="ficha">
      <div class="img-container">
        ' . ($base64 ? '<img src="' . $base64 . '" width="50" height="50">' : '[Brasão]') . '
      </div>

      <h3>MINISTÉRIO DA DEFESA</h3>
      <h3>EXÉRCITO BRASILEIRO</h3>
      <h3>' . mb_strtoupper(htmlspecialchars($ficha["nome_batalhao"] ?? ''), "UTF-8") . '</h3>
      <br>
      <h3><b>FICHA DE SERVIÇO DE DESLOCAMENTO DE VIATURA/EQUIPAMENTO Nº ' . (int)$ficha['id'] . '</b></h3>

      <div class="section-title">IDENTIFICAÇÃO DA VIATURA</div>
      <table class="table">
        <tr>
          <td><b>Prefixo SGA:</b> ' . htmlspecialchars($ficha['prefixo_sga'] ?? '') . '</td>
          <td><b>Prefixo Antigo:</b> ' . htmlspecialchars($ficha['prefixo_velho'] ?? '') . '</td>
          <td><b>Ano:</b> ' . htmlspecialchars($ficha['ano'] ?? '') . '</td>
        </tr>
        <tr>
          <td colspan="3"><b>Marca / Modelo:</b> ' . htmlspecialchars(($ficha['nome_marca'] ?? '') . " / " . ($ficha['nome_modelo'] ?? '')) . '</td>
        </tr>
      </table>

      <div class="section-title">DADOS GERAIS</div>
      <table class="table">
        <tr>
          <td><b>Data de Abertura:</b> ' . $data_abertura . '</td>
          <td><b>Data Prevista:</b> ' . $data_prevista . '</td>
          <td><b>Status:</b> ' . htmlspecialchars($ficha['status'] ?? '') . '</td>
        </tr>
        <tr>
          <td><b>Solicitante:</b> ' . htmlspecialchars($ficha['solicitante'] ?? '') . '</td>
          <td><b>Motorista:</b> ' . htmlspecialchars($ficha['motorista'] ?? '') . '</td>
          <td><b>Subunidade:</b> ' . htmlspecialchars($ficha['subunidade'] ?? '') . '</td>
        </tr>
        <tr>
          <td colspan="3"><b>Destino:</b> ' . htmlspecialchars($ficha['destino'] ?? '') . ' — <b>Cidade:</b> ' . htmlspecialchars($ficha['cidade'] ?? '') . '</td>
        </tr>
      </table>

      <div class="section-title">APRESENTAÇÃO</div>
      <table class="table">
        <tr>
          <td><b>Chefe a se apresentar:</b> ' . htmlspecialchars($ficha['chefe_apresentar'] ?? '') . '</td>
          <td><b>Local:</b> ' . htmlspecialchars($ficha['local_apresentar'] ?? '') . '</td>
          <td><b>Horário:</b> ' . htmlspecialchars($ficha['horario_apresentar'] ?? '') . '</td>
        </tr>
      </table>

      <div class="section-title">AUTORIZAÇÕES</div>
      <table class="table">
        <tr>
          <td style="text-align:center;">
            <p style="margin:0; padding:0;"><b>____________________________________</b></p>
            <p style="margin:0; padding:0;"><b>' . htmlspecialchars($ficha['cmt_ceem']) . '</b></p>
            <p style="margin:0; padding:0;">CMT CIA E EQP MNT</p>
          </td>
        </tr>
      </table>

      <table class="table">
        <tr>
          <td style="text-align:center;">
            <p style="margin:0; padding:0;"><b>A viatura/equipamento está em condições de ser utilizada no serviço e itinerário relacionados acima.</b></p>
            <br>
            <p style="margin:0; padding:0;"><b>____________________________________</b></p>
            <p style="margin:0; padding:0;"><b>' . htmlspecialchars($ficha['ch_sta']) . '</b></p>
            <p style="margin:0; padding:0;">CH DA STA</p>
          </td>
        </tr>
      </table>

      <div class="section-title">MOVIMENTO - PREENCHIMENTO PELA GUARDA</div>
      <table class="table">
        <tr>
          <td style="font-size:10px;">
            <b>MOTORISTA</b> EXECUTE AS INSPEÇÕES PREVISTAS NO VERSO E PREENCHA OS DADOS ABAIXO PARA O LIVRO REGISTRO DA VIATURA
            (CMT DA GD FISCALIZAR E COBRAR O CORRETO PREENCHIMENTO, VERIFICAR O ODÔMETRO NO PAINEL DA VIATURA AO SAIR E ENTRAR NO AQUARTELAMENTO)
          </td>
        </tr>
      </table>

      <table class="table">
        <tr>
          <td><b>Data Saída:</b> </td>
          <td><b>Hora Saída:</b> </td>
          <td><b>Odômetro Saída:</b> </td>
        </tr>
        <tr>
          <td><b>Data Retorno:</b> </td>
          <td><b>Hora Retorno:</b> </td>
          <td><b>Odômetro Retorno:</b> </td>
        </tr>
        <tr>
          <td colspan="3" style="font-size:10px; text-align:center;">
            <b>Durante a movimentação da viatura, o motorista permanecerá de posse desta ficha. Após a conclusão do trabalho, a ficha deverá ser entregue na STA, juntamente com a chave da viatura/equipamento.</b>
          </td>
        </tr>
      </table>

      <hr size="1" style="border:1px dashed green;">
      <div class="section-title">TALÃO DA GUARDA</div>
      <table class="table" style="font-size:10px;">
        <tr>
          <td>
            <p style="margin:0;"><b>DATA DE ABERTURA:</b> ' . $data_abertura . '</p>
            <p style="margin:0;"><b>NÚMERO DA FICHA:</b> ' . (int)$ficha['id'] . '</p>
            <p style="margin:0;"><b>SU:</b> ' . htmlspecialchars($ficha['subunidade'] ?? '') . '</p>
            <p style="margin:0;"><b>DESTINO:</b> ' . htmlspecialchars($ficha['destino'] ?? '') . '</p>
          </td>
          <td>
            <p style="margin:0;"><b>PREFIXO:</b> ' . htmlspecialchars($ficha['prefixo_sga'] ?? '') . '</p>
            <p style="margin:0;"><b>NATUREZA DO SV:</b> ' . htmlspecialchars($ficha['natureza'] ?? '') . '</p>
            <p style="margin:0;"><b>MOTORISTA:</b> ' . htmlspecialchars($ficha['motorista'] ?? '') . '</p>
            <p style="margin:0;"><b>CHEFE DA MISSÃO:</b> ' . htmlspecialchars($ficha['chefe_apresentar'] ?? '') . '</p>
          </td>
          <td>
            <p style="margin:0; text-align:center;"><b>AUTORIZADO</b></p>
            <br><br>
            <p style="margin:0; text-align:center;">______________</p>
            <p style="margin:0; text-align:center;">FISC ADM</p>
          </td>
        </tr>
        <tr>
          <td colspan="3" style="text-align:center;">
            O talão após a linha pontilhada deverá permanecer na guarda até a passagem de serviço, ocasião em que deverá ser entregue ao Oficial de Dia para ser juntado ao Livro de Serviço.
          </td>
        </tr>
      </table>

      <div style="page-break-before:always;"></div>

      <table class="table2">
        <tr>
          <td style="width:20%;">' . ($base64 ? '<img src="' . $base64 . '" width="50" height="50">' : '') . '</td>
          <td style="width:60%; font-size:18px;"><b>MANUTENÇÃO PREVENTIVA DE 1° ESCALÃO</b></td>
          <td style="width:20%;"><br><br>________________<br><b>Motorista</b></td>
        </tr>
      </table>

      <hr style="margin-top:20px;">
      <div style="text-align:center; font-size:15px;"><b>LEGENDA</b></div>
      <br>
      <table class="table2" style="font-size:13px;">
        <tr><td style="width:50%;">A - ANTES DA PARTIDA</td><td style="width:50%;">D - DURANTE O MOVIMENTO</td></tr>
        <tr><td>P - NOS ALTOS E PÓS-OPERAÇÃO</td><td>H/Q - APÓS DETERMINADO NÚMERO</td></tr>
      </table>

      <br>
      <table class="table2">
        <tr style="font-size:15px; font-weight:bold;">
          <th style="width:58%; border:1px solid;">ITEM</th>
          <th style="width:10%; border:1px solid;">A</th>
          <th style="width:10%; border:1px solid;">D</th>
          <th style="width:10%; border:1px solid;">P</th>
          <th style="width:10%; border:1px solid;">H/Q</th>
        </tr>
        <tr><td style="border:1px solid;">Visão geral da viatura</td><td style="border:1px solid;"></td><td style="border:1px solid;"></td><td style="border:1px solid;"></td><td style="border:1px solid;"></td></tr>
        <tr><td style="border:1px solid;">Vazamentos</td><td style="border:1px solid;"></td><td style="border:1px solid;"></td><td style="border:1px solid;"></td><td style="border:1px solid;"></td></tr>
        <tr><td style="border:1px solid;">Pneus, lagartas e suspensão</td><td style="border:1px solid;"></td><td style="border:1px solid;"></td><td style="border:1px solid;"></td><td style="border:1px solid;"></td></tr>
        <tr><td style="border:1px solid;">Combustível</td><td style="border:1px solid;"></td><td style="border:1px solid;"></td><td style="border:1px solid;"></td><td style="border:1px solid;"></td></tr>
        <tr><td style="border:1px solid;">Água</td><td style="border:1px solid;"></td><td style="border:1px solid;"></td><td style="border:1px solid;"></td><td style="border:1px solid;"></td></tr>
        <tr><td style="border:1px solid;">Níveis de óleo</td><td style="border:1px solid;"></td><td style="border:1px solid;"></td><td style="border:1px solid;"></td><td style="border:1px solid;"></td></tr>
        <tr><td style="border:1px solid;">Instrumentos do Painel</td><td style="border:1px solid;"></td><td style="border:1px solid;"></td><td style="border:1px solid;"></td><td style="border:1px solid;"></td></tr>
        <tr><td style="border:1px solid;">Motor</td><td style="border:1px solid;"></td><td style="border:1px solid;"></td><td style="border:1px solid;"></td><td style="border:1px solid;"></td></tr>
        <tr><td style="border:1px solid;">Luzes e refletores</td><td style="border:1px solid;"></td><td style="border:1px solid;"></td><td style="border:1px solid;"></td><td style="border:1px solid;"></td></tr>
        <tr><td style="border:1px solid;">Equipamentos de segurança e visão</td><td style="border:1px solid;"></td><td style="border:1px solid;"></td><td style="border:1px solid;"></td><td style="border:1px solid;"></td></tr>
        <tr><td style="border:1px solid;">Ligações para reboque</td><td style="border:1px solid;"></td><td style="border:1px solid;"></td><td style="border:1px solid;"></td><td style="border:1px solid;"></td></tr>
        <tr><td style="border:1px solid;">Portas e escotilhas</td><td style="border:1px solid;"></td><td style="border:1px solid;"></td><td style="border:1px solid;"></td><td style="border:1px solid;"></td></tr>
        <tr><td style="border:1px solid;">Documentação</td><td style="border:1px solid;"></td><td style="border:1px solid;"></td><td style="border:1px solid;"></td><td style="border:1px solid;"></td></tr>
        <tr><td style="border:1px solid;">Sistema hidráulico</td><td style="border:1px solid;"></td><td style="border:1px solid;"></td><td style="border:1px solid;"></td><td style="border:1px solid;"></td></tr>
        <tr><td style="border:1px solid;">Outros equipamentos</td><td style="border:1px solid;"></td><td style="border:1px solid;"></td><td style="border:1px solid;"></td><td style="border:1px solid;"></td></tr>
      </table>

      <hr size="1" style="border:1px dashed green;">

      <table class="table">
        <tr>
          <td><b>Data Saída:</b> </td>
          <td><b>Hora Saída:</b> </td>
          <td><b>Odômetro Saída:</b> </td>
        </tr>
        <tr>
          <td><b>Data Retorno:</b> </td>
          <td><b>Hora Retorno:</b> </td>
          <td><b>Odômetro Retorno:</b> </td>
        </tr>
        <tr><td colspan="3">O talão deverá ser preenchido corretamente pelo Cmt da Gda/Cb da Gda e motorista.</td></tr>
      </table>

      <table class="table">
        <tr>
          <td style="text-align:center;">
            <p><br></p>
            <p style="margin:0;">_____________________</p>
            <p style="margin:0;"><b>' . htmlspecialchars($ficha['motorista'] ?? '') . '</b></p>
            <p style="margin:0;">Motorista</p>
          </td>
          <td style="text-align:center;">
            <p><br></p>
            <p style="margin:0;">_____________________</p>
            <p style="margin:0;"><b>' . htmlspecialchars($ficha['chefe_apresentar'] ?? '') . '</b></p>
            <p style="margin:0;">Chefe de missão</p>
          </td>
        </tr>
      </table>
    </div>
    ';

    return $html;
}

// CSS global do PDF (uma vez)
$css = '
<style>
  body { font-family: "Times New Roman", Times, serif; font-size: 11px; margin: 20px; }
  h3 { text-align: center; margin: 2px 0; text-transform: uppercase; }
  .table { width: 100%; border-collapse: collapse; margin-top: 8px; }
  .table td { border: 1px solid #000; padding: 4px 6px; vertical-align: top; }
  .table2 { width: 100%; border-collapse: collapse; margin-top: 1px; }
  .table2 td, .table2 th { border: 1px solid #000; padding: 1px 1px; text-align: center; }
  .section-title { font-weight: bold; margin-top: 10px; text-transform: uppercase; background: #f3f3f3; padding: 4px; border: 1px solid #000; }
  .img-container { text-align: center; margin-bottom: 5px; }
  .quebra { page-break-after: always; }
</style>
';

// HTML final
$html = '<!DOCTYPE html><html lang="pt-BR"><head><meta charset="UTF-8">' . $css . '</head><body>';

$ultimoIndex = count($fichas) - 1;
foreach ($fichas as $i => $ficha) {
    $html .= montarHtmlFicha($ficha, $base64, $cmt_ceem);
    if ($i < $ultimoIndex) {
        $html .= '<div class="quebra"></div>'; // quebra ENTRE fichas
    }
}

$html .= '</body></html>';

// ===============================
// Dompdf
// ===============================
$options = new Options();
$options->set('isHtml5ParserEnabled', true);
$options->set('isRemoteEnabled', false);

$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

$dompdf->stream("fichas_vtr_" . date('Ymd_His') . ".pdf", ["Attachment" => false]);
exit;