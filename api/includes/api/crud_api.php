<?php
// ==========================================
// API UNIVERSAL DE CRUD (CREATE, READ, UPDATE, DELETE)
// ==========================================
// Esta API substitui todos os antigos scripts da pasta /includes/
// Ela permite listar, criar, editar e excluir dados de qualquer tabela 
// desde que o usuário tenha permissão no RBAC.

use Illuminate\Database\Capsule\Manager as DB;

// ==========================================
// WHITELIST DE TABELAS (Proteção contra acesso indevido)
// ==========================================
// Apenas tabelas listadas aqui podem ser manipuladas pelo CRUD Universal.
// Tabelas sensíveis como usuarios, config_sistema, role_permissions
// possuem rotas dedicadas com proteção específica.
$CRUD_TABELAS_PERMITIDAS = [
    'frota', 'fin_empenhos', 'fin_notas_fiscais', 'fin_requisicao', 'fin_fornecedores',
    'fin_pregao', 'fin_pedidos_forn', 'fin_ordemforn', 'fin_siafi_corrente',
    'fin_siafi_restopagar', 'almox_produtos', 'almox_entradas',
    'almox_pedidos_princ', 'almox_depositos', 'os_principal',
    'manutencao', 'mnt_planos', 'controle_medicoes', 'sta_fichas',
    'organizacoes_militares', 'funcoes', 'config_marcas', 'config_destinos',
    'paginas', 'paginas_principal', 'logs', 'notificacoes', 'roles'
];

// HELPER: Verifica Permissão Genérica
function verificar_permissao_crud($modulo_path, $acao) {
    $user_jwt = $GLOBALS['usuario_logado'];
    $role_id = $user_jwt['role_id'];
    
    // Super Admin e GodMode passam direto
    if ($role_id == 1 || $role_id == 16) return true;
    
    // Tabelas administrativas protegidas
    $tabelas_admin = ['roles', 'role_permissions', 'config_marcas', 'config_destinos', 'paginas', 'paginas_principal', 'logs', 'notificacoes'];
    
    // Acha o ID do módulo pelo path
    $modulo = DB::table('modules')->where('path', $modulo_path)->first();
    
    if (!$modulo) {
        // Se a tabela for administrativa, SÓ o master pode acessar (já verificou acima)
        if (in_array($modulo_path, $tabelas_admin)) {
            return false;
        }
        return true; // Se não for admin e não tiver módulo, passa direto (tabelas auxiliares comuns)
    }
    
    // Verifica na tabela role_permissions
    $perm = DB::table('role_permissions')
        ->where('role_id', $role_id)
        ->where('module_id', $modulo->id)
        ->first();
        
    if (!$perm) return false;
    
    switch ($acao) {
        case 'view': return $perm->can_view == 1;
        case 'create': return $perm->can_create == 1;
        case 'edit': return $perm->can_edit == 1;
        case 'delete': return $perm->can_delete == 1;
        default: return false;
    }
}

// HELPER: Schema Cache em Memória (Auditoria M6)
$SCHEMA_CACHE = [];
function getCachedColumns($tabela) {
    global $SCHEMA_CACHE;
    if (!isset($SCHEMA_CACHE[$tabela])) {
        $SCHEMA_CACHE[$tabela] = DB::schema()->getColumnListing($tabela);
    }
    return $SCHEMA_CACHE[$tabela];
}

// =========================================================================
// VALIDAÇÃO DE RESPONSABILIDADE FISCAL (TRAVA RÍGIDA)
// =========================================================================
function validar_nota_fiscal($data, $nf_id_edicao = null) {
    if (empty($data['empenho_id'])) {
        throw new \Exception("Bloqueio Fiscal: A Nota Fiscal não pode ser salva sem estar vinculada a um Empenho válido (Lei de Responsabilidade Fiscal).");
    }
    
    $empenho = DB::table('fin_empenhos')->where('nmr_empenho', $data['empenho_id'])->first();
    if (!$empenho) {
        throw new \Exception("Bloqueio Fiscal: O Empenho informado (" . $data['empenho_id'] . ") não existe no sistema. Cadastro de NF rejeitado.");
    }
    
    $valorNF = (float) ($data['valor_total'] ?? 0);
    
    // Calcula o saldo já consumido por outras NFs (excluindo a atual se for edição)
    $queryOutrasNFs = DB::table('fin_notas_fiscais')->where('empenho_id', $data['empenho_id']);
    if ($nf_id_edicao) {
        $queryOutrasNFs->where('id', '!=', $nf_id_edicao);
    }
    $outrasNFs = $queryOutrasNFs->get();
    
    $somaOutrasNFs = 0;
    foreach ($outrasNFs as $nf) {
        $somaOutrasNFs += (float) $nf->valor_total;
    }
    
    $valorTotalEmpenho = (float) ($empenho->valor_total ?? $empenho->valor_empenhado ?? 0);
    $saldoDisponivel = $valorTotalEmpenho - $somaOutrasNFs;
    
    // Tolerância de 1 centavo para problemas de arredondamento de JS
    if ($valorNF > ($saldoDisponivel + 0.01)) {
        $saldoFormatado = number_format($saldoDisponivel, 2, ',', '.');
        $valorNFF = number_format($valorNF, 2, ',', '.');
        throw new \Exception("Bloqueio Fiscal (Over-burn Protection): O valor desta Nota Fiscal (R$ $valorNFF) ultrapassa o teto do saldo disponível do Empenho (Saldo Restante: R$ $saldoFormatado). A operação foi bloqueada.");
    }
}
// MOTOR FINANCEIRO: AUTOMAÇÃO DE RETENÇÃO E LIQUIDAÇÃO DE EMPENHOS
// =========================================================================
function atualizarSaldosEmpenho($empenhoId) {
    if (empty($empenhoId)) return;
    
    try {
        $nfs = DB::table('fin_notas_fiscais')->where('empenho_id', $empenhoId)->get();
        
        $saldoConsumido = 0;
        $saldoRetido = 0;
        $qtdConsumida = 0;
        $qtdRetida = 0;
        
        foreach ($nfs as $nf) {
            $valorNF = (float) $nf->valor_total;
            $qtdNF = (float) $nf->quantidade_item;
            
            if ($nf->status === 'Enviada pra S4' || $nf->status === 'Liquidada' || $nf->status === 'Paga') {
                $saldoConsumido += $valorNF;
                $qtdConsumida += $qtdNF;
            } else {
                $saldoRetido += $valorNF;
                $qtdRetida += $qtdNF;
            }
        }
        
        $empenho = DB::table('fin_empenhos')->where('nmr_empenho', $empenhoId)->first();
        if ($empenho) {
            $valorTotal = (float) ($empenho->valor_total ?? $empenho->valor_empenhado ?? 0);
            $novoStatus = $empenho->status; 
            
            if (($saldoConsumido + $saldoRetido) >= $valorTotal && $valorTotal > 0) {
                if ($saldoConsumido >= $valorTotal) {
                    $novoStatus = 'Liquidado Total';
                } else {
                    $novoStatus = 'Liquidado Parcial';
                }
            } elseif (($saldoConsumido + $saldoRetido) > 0) {
                $novoStatus = 'Liquidado Parcial';
            } else {
                $novoStatus = 'Ativo';
            }
            
            DB::table('fin_empenhos')->where('nmr_empenho', $empenhoId)->update([
                'saldo_consumido' => $saldoConsumido,
                'saldo_retido' => $saldoRetido,
                'qtd_consumida' => $qtdConsumida,
                'qtd_retida' => $qtdRetida,
                'status' => $novoStatus
            ]);
        }
    } catch (\Exception $e) {
        $GLOBALS['logger']->error("Erro ao atualizar saldos: " . $e->getMessage());
    }
}

// 1. READ (Listagem / Ver 1)
$router->get('/crud/(.*)', function($tabela) use ($CRUD_TABELAS_PERMITIDAS) {
    if (!in_array($tabela, $CRUD_TABELAS_PERMITIDAS)) {
        http_response_code(403); echo json_encode(['status' => 'erro', 'mensagem' => 'Tabela não autorizada.']); exit;
    }
    if (!verificar_permissao_crud($tabela, 'view')) {
        http_response_code(403); echo json_encode(['status' => 'erro', 'mensagem' => 'Sem permissão de leitura.']); exit;
    }
    
    try {
        $id = $_GET['id'] ?? null;
        
        if ($id) {
            $dados = DB::table($tabela)->where('id', $id)->first();
        } else {
            // Paginação simples
            $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
            $offset = ($page - 1) * $limit;
            
            $busca = $_GET['busca'] ?? '';
            $statusFilter = $_GET['status'] ?? '';
            
            if ($tabela === 'frota') {
                $query = DB::table('frota')
                    ->select('frota.*', 'om.abreviatura as om_abreviatura')
                    ->leftJoin('organizacoes_militares as om', 'om.id', '=', 'frota.batalhao')
                    ->where('frota.status', '!=', 'CANCELADA');
            } else {
                $query = DB::table($tabela);
                // Ocultar soft-deleted records nas tabelas aplicáveis
                if (in_array($tabela, ['almox_produtos', 'os_principal'])) {
                    if (in_array('status', getCachedColumns($tabela))) {
                        $query->where('status', '!=', 'CANCELADA');
                    }
                }
            }
            
            // --- INJEÇÃO DA BLINDAGEM DE RLS (TENANCY) ---
            $batalhao_usuario = $GLOBALS['usuario_logado']['om_id'] ?? null;
            if ($GLOBALS['usuario_logado']['role_id'] != 1 && $GLOBALS['usuario_logado']['role_id'] != 16 && $batalhao_usuario) {
                $colunas = getCachedColumns($tabela);
                if (in_array('batalhao_id', $colunas)) {
                    $query->where($tabela === 'frota' ? 'frota.batalhao_id' : 'batalhao_id', $batalhao_usuario);
                } elseif (in_array('om_id', $colunas)) {
                    $query->where($tabela === 'frota' ? 'frota.om_id' : 'om_id', $batalhao_usuario);
                } elseif (in_array('batalhao', $colunas)) {
                    $query->where($tabela === 'frota' ? 'frota.batalhao' : 'batalhao', $batalhao_usuario);
                }
            }
            // ---------------------------------------------
            
            // Filtro de Status Exato com suporte a registros legados (nulos/vazios)
            if (!empty($statusFilter)) {
                $colStatus = $tabela === 'frota' ? 'frota.status' : 'status';
                
                $query->where(function($q) use ($colStatus, $statusFilter) {
                    $q->where($colStatus, $statusFilter);
                    
                    // Se o status buscado for o "Padrão/Inicial", assumimos que registros sem status também entram nele
                    $statusPadrao = ['Ativo', 'No Destacamento', 'Pendente'];
                    if (in_array($statusFilter, $statusPadrao)) {
                        $q->orWhereNull($colStatus)
                          ->orWhere($colStatus, '');
                    }
                });
            }
            
            // Busca Global Otimizada (Auditoria A2)
            if (!empty($busca)) {
                $colunas_tabela = getCachedColumns($tabela);
                
                // Mapeamento de colunas buscáveis (evita Full Table Scan inútil)
                $colunasBuscaveis = [
                    'frota' => ['prefixo_sga', 'placa', 'modelo', 'marca'],
                    'fin_empenhos' => ['nmr_empenho', 'descricao'],
                    'fin_notas_fiscais' => ['numero_nf', 'nome_empresa', 'cnpj_fornecedor', 'empenho_id'],
                    'os_principal' => ['placa_vtr', 'defeito_relatado', 'oficina_destino'],
                    'usuarios' => ['nomecompleto', 'nomeguerra', 'usuario', 'postograd']
                ];
                
                $colsBusca = $colunasBuscaveis[$tabela] ?? array_slice($colunas_tabela, 0, 5);
                $colsReais = array_intersect($colsBusca, $colunas_tabela);
                if (empty($colsReais)) $colsReais = $colunas_tabela; // fallback
                
                $query->where(function($q) use ($colsReais, $busca, $tabela) {
                    foreach ($colsReais as $col) {
                        $q->orWhere($tabela === 'frota' ? "frota.$col" : $col, 'LIKE', "%{$busca}%");
                    }
                });
            }
            
            $total = $query->count();
            $dados = $query->orderBy($tabela === 'frota' ? 'frota.id' : 'id', 'desc')->limit($limit)->offset($offset)->get();
            
            echo json_encode([
                'status' => 'sucesso', 
                'dados' => $dados, 
                'paginacao' => ['total' => $total, 'pagina_atual' => $page, 'limite' => $limit, 'total_paginas' => (int)ceil($total / $limit)]
            ]);
            exit;
        }
        
        echo json_encode(['status' => 'sucesso', 'dados' => $dados]);
    } catch (\Exception $e) {
        http_response_code(500); echo json_encode(['status' => 'erro', 'mensagem' => $e->getMessage()]);
    }
});

// HELPER: Dispara gatilho de alteração no banco (Para WebSockets/Polling)
function notificar_alteracao_bd() {
    $file = __DIR__ . '/../../../logs/db_version.txt';
    if (!file_exists(dirname($file))) mkdir(dirname($file), 0777, true);
    file_put_contents($file, time());
}

// HELPER: Auditoria de Segurança
function registrar_auditoria($acao, $tabela, $registro_id, $detalhes = '') {
    $uid = $GLOBALS['usuario_logado']['id'] ?? 0;
    $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    DB::table('logs_auditoria')->insert([
        'usuario_id' => $uid,
        'acao' => $acao,
        'tabela' => $tabela,
        'registro_id' => $registro_id,
        'detalhes' => is_array($detalhes) ? json_encode($detalhes) : $detalhes,
        'ip_address' => $ip
    ]);
}

// HELPER: Notificações
function criar_notificacao($usuario_id, $titulo, $mensagem, $nivel = 'INFO', $url = null) {
    try {
        $tipo = $usuario_id === 0 ? 'ROLE' : 'USER';
        $id_destino = $usuario_id === 0 ? 1 : $usuario_id; // Se 0, manda para a ROLE 1 (Admin)

        DB::table('notificacoes')->insert([
            'destinatario_tipo' => $tipo,
            'destinatario_id' => $id_destino,
            'titulo' => $titulo,
            'mensagem' => $mensagem,
            'nivel' => $nivel,
            'acao_url' => $url
        ]);
    } catch (\Exception $e) {
        $GLOBALS['logger']->error("Falha ao criar notificação: " . $e->getMessage());
    }
}

// 2. CREATE
$router->post('/crud/(.*)', function($tabela) use ($CRUD_TABELAS_PERMITIDAS) {
    if (!in_array($tabela, $CRUD_TABELAS_PERMITIDAS)) {
        http_response_code(403); echo json_encode(['status' => 'erro', 'mensagem' => 'Tabela não autorizada.']); exit;
    }
    if (!verificar_permissao_crud($tabela, 'create')) {
        http_response_code(403); echo json_encode(['status' => 'erro', 'mensagem' => 'Sem permissão para criar.']); exit;
    }
    
    // Verifica Fechamento de Mês
    $fechado = DB::table('config_sistema')->where('chave', 'mes_fechado')->value('valor');
    if ($fechado == '1' && $GLOBALS['usuario_logado']['role_id'] != 1 && $GLOBALS['usuario_logado']['role_id'] != 16) {
        http_response_code(403); echo json_encode(['status' => 'erro', 'mensagem' => 'O sistema está fechado para balanço do mês.']); exit;
    }

    try {
        $json = file_get_contents('php://input');
        $data = json_decode($json, true);
        
        // --- Cruzamento de Módulos: OS x Almoxarifado ---
        $pecas_utilizadas = null;
        if ($tabela === 'os_principal' && isset($data['pecas_utilizadas'])) {
            $pecas_utilizadas = is_string($data['pecas_utilizadas']) ? json_decode($data['pecas_utilizadas'], true) : $data['pecas_utilizadas'];
        }
        
        // Blindagem contra Colunas Fantasmas
        $colunas = getCachedColumns($tabela);
        $data = array_intersect_key($data, array_flip($colunas));
        
        // Conversor Universal BRL -> Decimal MySQL
        foreach ($data as $k => $v) {
            if (is_string($v)) {
                $vTrim = trim($v);
                if (preg_match('/^-?\d{1,3}(?:\.\d{3})*,\d{1,4}$/', $vTrim) || preg_match('/^-?\d+,\d{1,4}$/', $vTrim)) {
                    $vClean = str_replace('.', '', $vTrim);
                    $vClean = str_replace(',', '.', $vClean);
                    $data[$k] = $vClean;
                }
            }
        }
        
        // Preenchimento Automático de Datas e Campos Obrigatórios Legados (Autofill Shield)
        $dateNow = date('Y-m-d H:i:s');
        $timestampFields = ['data_inclusao', 'data_cadastro', 'created_at', 'data', 'data_emissao', 'data_empenho'];
        $requiredFields = ['valor_total', 'status'];
        
        foreach (array_merge($timestampFields, $requiredFields) as $field) {
            if (in_array($field, $colunas) && !isset($data[$field])) {
                if ($field === 'valor_total') {
                    $data[$field] = 0.00;
                } elseif ($field === 'status') {
                    // Mantem nulo se for um status dinamico, o banco assume o DEFAULT do Schema (ex: 'Ativo' ou 'No Destacamento')
                } else {
                    $data[$field] = $dateNow;
                }
            }
        }
        
        // Fallbacks específicos para tabelas legadas com NOT NULL sem DEFAULT
        if ($tabela === 'frota') {
            if (in_array('destino', $colunas) && !isset($data['destino'])) $data['destino'] = 'Não Informado';
            if (in_array('disponibilidade', $colunas) && !isset($data['disponibilidade'])) $data['disponibilidade'] = 'Disponível';
        }
        
        
        // Auditoria A1: Início da Transação
        DB::beginTransaction();
        
        // Lock Pessimista no Empenho
        if ($tabela === 'fin_notas_fiscais' && !empty($data['empenho_id'])) {
            DB::table('fin_empenhos')->where('nmr_empenho', $data['empenho_id'])->lockForUpdate()->first();
        }

        // --- TRAVA FISCAL (ROTA A) ---
        if ($tabela === 'fin_notas_fiscais') {
            validar_nota_fiscal($data, null);
        }
        // -----------------------------
        
        $id = DB::table($tabela)->insertGetId($data);
        
        // --- AUTOMAÇÃO: Recálculo de Empenho ---
        if ($tabela === 'fin_notas_fiscais' && !empty($data['empenho_id'])) {
            atualizarSaldosEmpenho($data['empenho_id']);
        }
        // ---------------------------------------

        // --- AUTOMAÇÃO: Baixa de Estoque OS x Almoxarifado ---
        if ($tabela === 'os_principal' && is_array($pecas_utilizadas)) {
            $dateNow = date('Y-m-d H:i:s');
            foreach ($pecas_utilizadas as $peca) {
                if (empty($peca['produto_id']) || empty($peca['quantidade'])) continue;
                $produtoId = (int)$peca['produto_id'];
                $qtdUsada = (int)$peca['quantidade'];
                
                // Insere na tabela ponte
                DB::table('os_pecas')->insert([
                    'os_id' => $id,
                    'produto_id' => $produtoId,
                    'quantidade' => $qtdUsada,
                    'data_uso' => $dateNow
                ]);
                
                // Atualiza o saldo real da peça no Almoxarifado
                DB::table('almox_produtos')
                    ->where('id', $produtoId)
                    ->decrement('estoque_atual', $qtdUsada);
                    
                // Regista a Saída Oficial no Log de Movimentação do Almoxarifado
                DB::table('almox_saidas')->insert([
                    'id_produto' => $produtoId,
                    'quantidade' => $qtdUsada,
                    'data_saida' => $dateNow,
                    'destino' => "O.S. #" . $id . " (Viatura: " . ($data['placa_vtr'] ?? 'N/A') . ")",
                    'id_solicitante' => $GLOBALS['usuario_logado']['id'] ?? 1,
                    'status' => 'Concluída'
                ]);
            }
        }
        // -----------------------------------------------------

        DB::commit();

        notificar_alteracao_bd();
        $GLOBALS['logger']->info("CREATE em $tabela", ['usuario_id' => $GLOBALS['usuario_logado']['id'], 'novo_id' => $id]);
        
        // Log de Auditoria
        registrar_auditoria('CREATE', $tabela, $id, $data);
        
        // Gatilhos Sociais (Notificações)
        if ($tabela === 'almox_pedidos_princ' || $tabela === 'almox_pedidos_itens') {
            criar_notificacao(0, "Novo Pedido de Peça", "Um novo registro foi inserido no Almoxarifado (ID: $id).");
        }
        if ($tabela === 'os_principal') {
            criar_notificacao(0, "Nova OS Aberta", "Ordem de Serviço (ID: $id) registrada no sistema.");
        }
        
        echo json_encode(['status' => 'sucesso', 'mensagem' => 'Registro criado.', 'id' => $id]);
    } catch (\Exception $e) {
        if (DB::transactionLevel() > 0) DB::rollBack();
        http_response_code(500); echo json_encode(['status' => 'erro', 'mensagem' => $e->getMessage()]);
    }
});

// 3. UPDATE
$router->put('/crud/(.*)', function($path) use ($CRUD_TABELAS_PERMITIDAS) {
    $parts = explode('/', $path);
    $tabela = $parts[0];
    $idFromUrl = $parts[1] ?? null;

    if (!in_array($tabela, $CRUD_TABELAS_PERMITIDAS)) {
        http_response_code(403); echo json_encode(['status' => 'erro', 'mensagem' => "Tabela não autorizada: $tabela"]); exit;
    }
    if (!verificar_permissao_crud($tabela, 'edit')) {
        http_response_code(403); echo json_encode(['status' => 'erro', 'mensagem' => 'Sem permissão para editar.']); exit;
    }
    
    try {
        $json = file_get_contents('php://input');
        $data = json_decode($json, true);
        $id = $data['id'] ?? $idFromUrl;
        
        if (!$id) {
            http_response_code(400); echo json_encode(['status' => 'erro', 'mensagem' => 'ID obrigatório.']); exit;
        }
        
        unset($data['id']); // Nao atualiza o PK
        
        // Blindagem contra Colunas Fantasmas
        $colunas = getCachedColumns($tabela);
        $data = array_intersect_key($data, array_flip($colunas));
        
        // Conversor Universal BRL -> Decimal MySQL
        foreach ($data as $k => $v) {
            if (is_string($v)) {
                $vTrim = trim($v);
                if (preg_match('/^-?\d{1,3}(?:\.\d{3})*,\d{1,4}$/', $vTrim) || preg_match('/^-?\d+,\d{1,4}$/', $vTrim)) {
                    $vClean = str_replace('.', '', $vTrim);
                    $vClean = str_replace(',', '.', $vClean);
                    $data[$k] = $vClean;
                }
            }
        }
        
        // Captura o Empenho Antigo caso seja uma NF mudando de Empenho
        $empenhoAntigo = null;
        if ($tabela === 'fin_notas_fiscais') {
            $nfAntiga = DB::table('fin_notas_fiscais')->where('id', $id)->first();
            $empenhoAntigo = $nfAntiga ? $nfAntiga->empenho_id : null;
        }
        // Auditoria A1: Início da Transação
        DB::beginTransaction();

        // Lock Pessimista
        if ($tabela === 'fin_notas_fiscais') {
            if ($empenhoAntigo) DB::table('fin_empenhos')->where('nmr_empenho', $empenhoAntigo)->lockForUpdate()->first();
            if (!empty($data['empenho_id']) && $data['empenho_id'] !== $empenhoAntigo) {
                DB::table('fin_empenhos')->where('nmr_empenho', $data['empenho_id'])->lockForUpdate()->first();
            }
        }

        // --- TRAVA FISCAL (ROTA A) ---
        if ($tabela === 'fin_notas_fiscais') {
            validar_nota_fiscal($data, $id);
        }
        // -----------------------------

        DB::table($tabela)->where('id', $id)->update($data);
        
        // --- AUTOMAÇÃO: Recálculo de Empenho ---
        if ($tabela === 'fin_notas_fiscais') {
            atualizarSaldosEmpenho($empenhoAntigo); // Recalcula o empenho antigo
            if (!empty($data['empenho_id']) && $data['empenho_id'] !== $empenhoAntigo) {
                atualizarSaldosEmpenho($data['empenho_id']); // Recalcula o novo
            }
        }
        // ---------------------------------------

        DB::commit();

        notificar_alteracao_bd();
        
        $GLOBALS['logger']->info("UPDATE em $tabela", ['usuario_id' => $GLOBALS['usuario_logado']['id'], 'id_editado' => $id]);
        
        // Log de Auditoria
        registrar_auditoria('UPDATE', $tabela, $id, $data);
        
        echo json_encode(['status' => 'sucesso', 'mensagem' => 'Registro atualizado.']);
    } catch (\Exception $e) {
        if (DB::transactionLevel() > 0) DB::rollBack();
        http_response_code(500); echo json_encode(['status' => 'erro', 'mensagem' => $e->getMessage()]);
    }
});

// 4. DELETE
$router->delete('/crud/(.*)', function($path) use ($CRUD_TABELAS_PERMITIDAS) {
    $parts = explode('/', $path);
    $tabela = $parts[0];
    $idFromUrl = $parts[1] ?? null;

    if (!in_array($tabela, $CRUD_TABELAS_PERMITIDAS)) {
        http_response_code(403); echo json_encode(['status' => 'erro', 'mensagem' => 'Tabela não autorizada.']); exit;
    }
    if (!verificar_permissao_crud($tabela, 'delete')) {
        http_response_code(403); echo json_encode(['status' => 'erro', 'mensagem' => 'Sem permissão para excluir.']); exit;
    }
    
    try {
        $json = file_get_contents('php://input');
        $data = $json ? json_decode($json, true) : [];
        $id = $idFromUrl ?? ($data['id'] ?? null);
        
        if (!$id) {
            http_response_code(400); echo json_encode(['status' => 'erro', 'mensagem' => 'ID obrigatório.']); exit;
        }
        
        $tabelas_soft_delete = ['frota', 'almox_produtos', 'os_principal'];
        
        // Captura o Empenho antes de deletar
        $empenhoParaRecalculo = null;
        if ($tabela === 'fin_notas_fiscais') {
            $nfDeletada = DB::table('fin_notas_fiscais')->where('id', $id)->first();
            $empenhoParaRecalculo = $nfDeletada ? $nfDeletada->empenho_id : null;
        }
        
        if (in_array($tabela, $tabelas_soft_delete)) {
            $colunas = getCachedColumns($tabela);
            if (in_array('status', $colunas)) {
                DB::table($tabela)->where('id', $id)->update(['status' => 'CANCELADA']);
            } else {
                DB::table($tabela)->where('id', $id)->delete(); // Fallback se a tabela não tiver a coluna status
            }
        } else {
            DB::table($tabela)->where('id', $id)->delete();
        }
        
        // --- AUTOMAÇÃO: Recálculo de Empenho ---
        if ($tabela === 'fin_notas_fiscais') {
            atualizarSaldosEmpenho($empenhoParaRecalculo);
        }
        // ---------------------------------------

        notificar_alteracao_bd();
        $GLOBALS['logger']->warning("DELETE em $tabela", ['usuario_id' => $GLOBALS['usuario_logado']['id'], 'id_deletado' => $id]);
        
        // Log de Auditoria
        registrar_auditoria('DELETE', $tabela, $id, "Registro Excluído.");
        
        echo json_encode(['status' => 'sucesso', 'mensagem' => 'Registro apagado.']);
    } catch (\Exception $e) {
        if (DB::transactionLevel() > 0) DB::rollBack();
        http_response_code(500); echo json_encode(['status' => 'erro', 'mensagem' => $e->getMessage()]);
    }
});

// 5. IMPORTAÇÃO (Bulk Insert do Excel)
$router->post('/crud_import/(.*)', function($tabela) use ($CRUD_TABELAS_PERMITIDAS) {
    if (!in_array($tabela, $CRUD_TABELAS_PERMITIDAS)) {
        http_response_code(403); echo json_encode(['status' => 'erro', 'mensagem' => 'Tabela não autorizada.']); exit;
    }
    if (!verificar_permissao_crud($tabela, 'create')) {
        http_response_code(403); echo json_encode(['status' => 'erro', 'mensagem' => 'Sem permissão para criar.']); exit;
    }
    
    try {
        $json = file_get_contents('php://input');
        $payload = json_decode($json, true);
        $rows = $payload['rows'] ?? [];
        
        if (empty($rows)) {
            http_response_code(400); echo json_encode(['status' => 'erro', 'mensagem' => 'Nenhum dado enviado.']); exit;
        }
        
        $colunas = getCachedColumns($tabela);
        $inseridos = 0;
        
        DB::beginTransaction();

        foreach ($rows as $row) {
            $dataLimpa = array_intersect_key($row, array_flip($colunas));
            if (!empty($dataLimpa)) {
                // Auditoria M1: Trava fiscal em Import
                if ($tabela === 'fin_notas_fiscais') {
                    if (!empty($dataLimpa['empenho_id'])) {
                        DB::table('fin_empenhos')->where('nmr_empenho', $dataLimpa['empenho_id'])->lockForUpdate()->first();
                    }
                    validar_nota_fiscal($dataLimpa, null);
                }
                
                DB::table($tabela)->insert($dataLimpa);
                
                if ($tabela === 'fin_notas_fiscais' && !empty($dataLimpa['empenho_id'])) {
                    atualizarSaldosEmpenho($dataLimpa['empenho_id']);
                }
                $inseridos++;
            }
        }
        
        DB::commit();
        
        notificar_alteracao_bd();
        $GLOBALS['logger']->info("IMPORT em $tabela", ['usuario_id' => $GLOBALS['usuario_logado']['id'], 'qtd' => $inseridos]);
        
        echo json_encode(['status' => 'sucesso', 'inseridos' => $inseridos, 'mensagem' => 'Importação concluída.']);
    } catch (\Exception $e) {
        if (DB::transactionLevel() > 0) DB::rollBack();
        http_response_code(500); echo json_encode(['status' => 'erro', 'mensagem' => $e->getMessage()]);
    }
});
