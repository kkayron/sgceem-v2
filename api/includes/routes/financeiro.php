<?php
// Rotas do Módulo: Financeiro

$router->get('/financeiro/empenhos', function() {
    try {
        $user_jwt = $GLOBALS['usuario_logado'];
        $batalhao_id = (int)$user_jwt['batalhao'];
        $nivel = (int)$user_jwt['nivel'];

        $query = \Illuminate\Database\Capsule\Manager::table('fin_empenhos as e')
            ->select('e.id', 'e.nmr_empenho as nmr', 'f.nome_empresa as fornecedor', 'r.valor_empenhado as valor', 'r.status_requisicao as status')
            ->leftJoin('fin_requisicao as r', 'r.id', '=', 'e.id_requisicao')
            ->leftJoin('fin_fornecedores as f', 'f.id', '=', 'r.id_fornecedor');

        if ($nivel === 3) {
            $query->where('e.batalhao', '=', $batalhao_id);
        }

        $empenhos = $query->orderBy('e.id', 'desc')->limit(50)->get();

        $dadosFormatados = $empenhos->map(function($emp) {
            $valorFmt = 'R$ ' . number_format((float)$emp->valor, 2, ',', '.');
            $statusMap = ['0' => 'Pendente', '1' => 'Aprovado', '2' => 'Empenhado', '3' => 'Negado'];
            $statusFinal = $statusMap[$emp->status] ?? 'Desconhecido';

            return [
                'id' => $emp->id,
                'fornecedor' => $emp->fornecedor ?: 'Sem Fornecedor Vinculado',
                'valor' => $valorFmt,
                'status' => $statusFinal
            ];
        });

        echo json_encode(['status' => 'sucesso', 'dados' => $dadosFormatados]);
    } catch (\Exception $e) {
        http_response_code(500);
        echo json_encode(['status' => 'erro', 'mensagem' => 'Falha ao buscar empenhos.', 'detalhe' => $e->getMessage()]);
    }
});

$router->get('/fornecedores', function() {
    try {
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 100;
        if ($limit > 500) $limit = 500;
        if ($page < 1) $page = 1;
        $offset = ($page - 1) * $limit;
        
        $query = \Illuminate\Database\Capsule\Manager::table('fin_fornecedores')->orderBy('id','desc');
        $total = $query->count();
        $dados = $query->limit($limit)->offset($offset)->get();
        
        echo json_encode(['status' => 'sucesso', 'dados' => $dados, 'paginacao' => ['total' => $total, 'pagina_atual' => $page, 'limite' => $limit, 'total_paginas' => ceil($total / $limit)]]);
    } catch(\Exception $e) { http_response_code(500); echo json_encode(['status'=>'erro','mensagem'=>$e->getMessage()]); }
});
$router->post('/fornecedores', function() {
    try {
        $d = json_decode(file_get_contents('php://input'), true);
        \Illuminate\Database\Capsule\Manager::table('fin_fornecedores')->insert([
            'nome_empresa'=>$d['nome_empresa'],'cnpj_empresa'=>$d['cnpj_empresa'],'categoria_empresa'=>$d['categoria_empresa']??'',
            'contato_nome'=>$d['contato_nome']??'','contato_numero'=>$d['contato_numero']??'','contato_email'=>$d['contato_email']??'',
            'data_cadastro'=>date('Y-m-d'),'batalhao'=>$GLOBALS['usuario_logado']['batalhao']??0
        ]);
        echo json_encode(['status'=>'sucesso','mensagem'=>'Fornecedor cadastrado.']);
    } catch(\Exception $e) { http_response_code(500); echo json_encode(['status'=>'erro','mensagem'=>$e->getMessage()]); }
});
$router->put('/fornecedores/(\d+)', function($id) {
    try {
        $d = json_decode(file_get_contents('php://input'), true);
        unset($d['id']);
        unset($d['batalhao']);
        \Illuminate\Database\Capsule\Manager::table('fin_fornecedores')->where('id',$id)->update($d);
        echo json_encode(['status'=>'sucesso','mensagem'=>'Fornecedor atualizado.']);
    } catch(\Exception $e) { http_response_code(500); echo json_encode(['status'=>'erro','mensagem'=>$e->getMessage()]); }
});
$router->delete('/fornecedores/(\d+)', function($id) {
    try {
        \Illuminate\Database\Capsule\Manager::table('fin_fornecedores')->where('id',$id)->delete();
        echo json_encode(['status'=>'sucesso','mensagem'=>'Fornecedor removido.']);
    } catch(\Exception $e) { http_response_code(500); echo json_encode(['status'=>'erro','mensagem'=>$e->getMessage()]); }
});

$router->get('/pregoes', function() {
    try {
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 100;
        if ($limit > 500) $limit = 500;
        if ($page < 1) $page = 1;
        $offset = ($page - 1) * $limit;
        
        $query = \Illuminate\Database\Capsule\Manager::table('fin_pregao')->orderBy('id','desc');
        $total = $query->count();
        $dados = $query->limit($limit)->offset($offset)->get();
        
        echo json_encode(['status' => 'sucesso', 'dados' => $dados, 'paginacao' => ['total' => $total, 'pagina_atual' => $page, 'limite' => $limit, 'total_paginas' => ceil($total / $limit)]]);
    } catch(\Exception $e) { http_response_code(500); echo json_encode(['status'=>'erro','mensagem'=>$e->getMessage()]); }
});
$router->post('/pregoes', function() {
    try {
        $d = json_decode(file_get_contents('php://input'), true);
        $colunas = \Illuminate\Database\Capsule\Manager::schema()->getColumnListing('fin_pregao');
        $d = array_intersect_key($d, array_flip($colunas));
        \Illuminate\Database\Capsule\Manager::table('fin_pregao')->insert($d);
        echo json_encode(['status'=>'sucesso','mensagem'=>'Pregão cadastrado.']);
    } catch(\Exception $e) { http_response_code(500); echo json_encode(['status'=>'erro','mensagem'=>$e->getMessage()]); }
});
$router->delete('/pregoes/(\d+)', function($id) {
    try {
        \Illuminate\Database\Capsule\Manager::table('fin_pregao')->where('id',$id)->delete();
        echo json_encode(['status'=>'sucesso','mensagem'=>'Pregão removido.']);
    } catch(\Exception $e) { http_response_code(500); echo json_encode(['status'=>'erro','mensagem'=>$e->getMessage()]); }
});

$router->get('/requisicoes', function() {
    try {
        $dados = \Illuminate\Database\Capsule\Manager::table('fin_requisicao as r')
            ->select('r.*','f.nome_empresa as fornecedor','p.nmr_pregao')
            ->leftJoin('fin_fornecedores as f','f.id','=','r.id_fornecedor')
            ->leftJoin('fin_pregao as p','p.id','=','r.id_pregao')
            ->orderBy('r.id','desc')->limit(100)->get();
        echo json_encode(['status'=>'sucesso','dados'=>$dados]);
    } catch(\Exception $e) { http_response_code(500); echo json_encode(['status'=>'erro','mensagem'=>$e->getMessage()]); }
});

$router->get('/ordens-fornecimento', function() {
    try {
        $dados = \Illuminate\Database\Capsule\Manager::table('fin_ordemforn as of2')->select('of2.*')->orderBy('of2.id','desc')->limit(100)->get();
        echo json_encode(['status'=>'sucesso','dados'=>$dados]);
    } catch(\Exception $e) { http_response_code(500); echo json_encode(['status'=>'erro','mensagem'=>$e->getMessage()]); }
});

$router->get('/pedidos-fornecedor', function() {
    try {
        $dados = \Illuminate\Database\Capsule\Manager::table('fin_pedidos_forn as pf')->select('pf.*')->orderBy('pf.id','desc')->limit(100)->get();
        echo json_encode(['status'=>'sucesso','dados'=>$dados]);
    } catch(\Exception $e) { http_response_code(500); echo json_encode(['status'=>'erro','mensagem'=>$e->getMessage()]); }
});

$router->get('/conrazao/corrente', function() {
    try {
        $dados = \Illuminate\Database\Capsule\Manager::table('fin_siafi_corrente')->orderBy('id','desc')->limit(100)->get();
        echo json_encode(['status'=>'sucesso','dados'=>$dados]);
    } catch(\Exception $e) { http_response_code(500); echo json_encode(['status'=>'erro','mensagem'=>$e->getMessage()]); }
});
$router->get('/conrazao/rp', function() {
    try {
        $dados = \Illuminate\Database\Capsule\Manager::table('fin_siafi_restopagar')->orderBy('id','desc')->limit(100)->get();
        echo json_encode(['status'=>'sucesso','dados'=>$dados]);
    } catch(\Exception $e) { http_response_code(500); echo json_encode(['status'=>'erro','mensagem'=>$e->getMessage()]); }
});
