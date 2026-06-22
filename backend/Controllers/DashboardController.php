<?php
namespace App\Controllers;
use Illuminate\Database\Capsule\Manager as DB;

class DashboardController {
    
    public function frota() {
        try {
            $user = $GLOBALS['usuario_logado'] ?? null;
            $nivel = $user['nivel'] ?? 3;
            
            $query = DB::table('frota');
            
            // 1. Total e Status
            $statusRaw = (clone $query)->select(DB::raw('status, count(*) as total'))->groupBy('status')->get();
            
            // 2. Disponibilidade
            $dispRaw = (clone $query)->select(DB::raw('disponibilidade, count(*) as total'))->groupBy('disponibilidade')->get();
            
            // 3. Distribuição por Batalhão (OM) - Apenas se for nivel 1
            $porOm = [];
            if ($nivel == 1) {
                $porOm = (clone $query)
                    ->join('organizacoes_militares as om', 'om.id', '=', 'frota.batalhao')
                    ->select(DB::raw('om.abreviatura as om_nome, count(frota.id) as total'))
                    ->groupBy('frota.batalhao')
                    ->orderBy('total', 'desc')
                    ->limit(10)
                    ->get();
            }

            echo json_encode([
                'status' => 'sucesso',
                'dados' => [
                    'status' => $statusRaw,
                    'disponibilidade' => $dispRaw,
                    'por_om' => $porOm,
                    'total_geral' => (clone $query)->count()
                ]
            ]);

        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['status' => 'erro', 'mensagem' => $e->getMessage()]);
        }
    }

    public function financeiro() {
        try {
            $user = $GLOBALS['usuario_logado'] ?? null;
            $nivel = $user['nivel'] ?? 3;
            
            $queryForn = DB::table('fin_fornecedores');
            $queryPregao = DB::table('fin_pregao');
            $queryEmpenho = DB::table('fin_empenhos');
            
            $totalFornecedores = $queryForn->count();
            $totalPregoes = $queryPregao->count();
            $totalEmpenhos = $queryEmpenho->count();

            // Pedidos por Fornecedor (Top 5)
            $pedidosPorForn = DB::table('fin_pedidos_forn')
                ->join('fin_fornecedores as f', 'f.id', '=', 'fin_pedidos_forn.id_fornecedor')
                ->select(DB::raw('f.nome_empresa, count(fin_pedidos_forn.id) as total'))
                ->groupBy('fin_pedidos_forn.id_fornecedor')
                ->orderBy('total', 'desc')
                ->limit(5)
                ->get();

            echo json_encode([
                'status' => 'sucesso',
                'dados' => [
                    'total_fornecedores' => $totalFornecedores,
                    'total_pregoes' => $totalPregoes,
                    'total_empenhos' => $totalEmpenhos,
                    'pedidos_por_fornecedor' => $pedidosPorForn
                ]
            ]);

        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['status' => 'erro', 'mensagem' => $e->getMessage()]);
        }
    }

    public function preventiva() {
        try {
            $user = $GLOBALS['usuario_logado'] ?? null;
            $batalhao = isset($_GET['batalhao']) ? (int)$_GET['batalhao'] : ($user['batalhao'] ?? 0);
            $nivel = $user['nivel'] ?? 3;
            
            require_once __DIR__ . '/../includes/preventiva/calculo_manutencao.php';
            // Conexão bruta se o cálculo legado exigir mysqli, mas ele deve aceitar Capsule ou mysqli. 
            // Porém o arquivo de config antigo tem mysqli.
            require __DIR__ . '/../../database/conexao/config.php';

            $query = "SELECT id, tipo, prefixo_sga, nome_sioc, status_odometro, ativo, acervo, confiabilidade, disponibilidade, destino FROM frota WHERE tipo IN ('Vtr','Eqp')";
            
            $res = $conexao->query($query);
            $frota = [];
            while ($r = $res->fetch_assoc()) {
                $r['id'] = (int)$r['id'];
                $frota[] = $r;
            }

            $map = function_exists('calcularManutencaoPreventivaBulk') ? calcularManutencaoPreventivaBulk($conexao, $frota) : [];

            $contagem = [
                'Manutenção em dia' => 0,
                'Muito próxima' => 0,
                'Próxima' => 0,
                'Em manutenção' => 0,
                'Manutenção vencida' => 0,
                'Sem dados' => 0
            ];

            foreach ($frota as $item) {
                $calc = $map[$item['id']] ?? (function_exists('calcularManutencaoPreventiva') ? calcularManutencaoPreventiva($conexao, $item) : ['status' => 'Sem dados']);
                $statusOriginal = (string)($calc['status'] ?? 'Sem dados');
                
                // Mapeamento semântico
                $s = mb_strtolower($statusOriginal);
                $chave = 'Sem dados';
                if (strpos($s, 'vencid') !== false) $chave = 'Manutenção vencida';
                elseif (strpos($s, 'muito próx') !== false || strpos($s, 'muito prox') !== false) $chave = 'Muito próxima';
                elseif (strpos($s, 'próxim') !== false || strpos($s, 'proxim') !== false) $chave = 'Próxima';
                elseif (strpos($s, 'em manuten') !== false || strpos($s, 'agend') !== false || strpos($s, 'aguard') !== false) $chave = 'Em manutenção';
                elseif (strpos($s, 'em dia') !== false) $chave = 'Manutenção em dia';

                $contagem[$chave]++;
            }

            $statusFormatado = [];
            foreach ($contagem as $k => $v) {
                $statusFormatado[] = ['status' => $k, 'total' => $v];
            }

            echo json_encode([
                'status' => 'sucesso',
                'dados' => [
                    'total_preventivas' => count($frota),
                    'status' => $statusFormatado
                ]
            ]);

        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['status' => 'erro', 'mensagem' => $e->getMessage()]);
        }
    }
}
