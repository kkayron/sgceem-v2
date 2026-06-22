<?php
// Rotas do Módulo: Frota e Veículos

$router->get('/frota', function() {
    $user = $GLOBALS['usuario_logado'] ?? [];
    $batalhao_id = (int)($user['batalhao'] ?? 0);
    $nivel = (int)($user['nivel'] ?? 3);
    $status_filtro = $_GET['status'] ?? null;
    
    try {
        $query = \Illuminate\Database\Capsule\Manager::table('frota as f')
            ->select('f.id', 'f.prefixo_sga', 'f.marca', 'f.modelo', 'f.placa', 'f.status', 'f.disponibilidade', 'om.abreviatura as batalhao_nome')
            ->leftJoin('organizacoes_militares as om', 'om.id', '=', 'f.batalhao');
        
        if ($nivel === 3) {
            $query->where('f.batalhao', '=', $batalhao_id);
        }
        
        if ($status_filtro) {
            $query->where('f.status', '=', $status_filtro);
        }
        
        $viaturas = $query->orderBy('f.id', 'desc')->limit(100)->get();
        
        echo json_encode(['status' => 'sucesso', 'meta' => ['total' => $viaturas->count()], 'dados' => $viaturas]);
    } catch (\Exception $e) {
        http_response_code(500);
        echo json_encode(['status' => 'erro', 'mensagem' => 'Erro interno ao consultar viaturas.']);
    }
});

$router->post('/frota', function() {
    try {
        $d = json_decode(file_get_contents('php://input'), true);
        $d['batalhao'] = $GLOBALS['usuario_logado']['batalhao'] ?? $d['batalhao'] ?? 0;
        $d['destino'] = $d['destino'] ?? '';
        
        $colunas = \Illuminate\Database\Capsule\Manager::schema()->getColumnListing('frota');
        $d = array_intersect_key($d, array_flip($colunas));

        \Illuminate\Database\Capsule\Manager::table('frota')->insert($d);
        echo json_encode(['status'=>'sucesso','mensagem'=>'Viatura cadastrada.']);
    } catch(\Exception $e) { http_response_code(500); echo json_encode(['status'=>'erro','mensagem'=>$e->getMessage()]); }
});

$router->delete('/frota/(\d+)', function($id) {
    try {
        \Illuminate\Database\Capsule\Manager::table('frota')->where('id',$id)->delete();
        echo json_encode(['status'=>'sucesso','mensagem'=>'Viatura removida.']);
    } catch(\Exception $e) { http_response_code(500); echo json_encode(['status'=>'erro','mensagem'=>$e->getMessage()]); }
});

$router->get('/odometro', function() {
    try {
        $dados = \Illuminate\Database\Capsule\Manager::table('controle_medicoes as cm')->select('cm.*')->orderBy('cm.id','desc')->limit(100)->get();
        echo json_encode(['status'=>'sucesso','dados'=>$dados]);
    } catch(\Exception $e) { http_response_code(500); echo json_encode(['status'=>'erro','mensagem'=>$e->getMessage()]); }
});

$router->get('/destinos', function() {
    try {
        $dados = \Illuminate\Database\Capsule\Manager::table('config_destinos')->orderBy('id','asc')->get();
        echo json_encode(['status'=>'sucesso','dados'=>$dados]);
    } catch(\Exception $e) { http_response_code(500); echo json_encode(['status'=>'erro','mensagem'=>$e->getMessage()]); }
});

$router->get('/marcas', function() {
    try {
        $dados = \Illuminate\Database\Capsule\Manager::table('config_marcas')->orderBy('id','asc')->get();
        echo json_encode(['status'=>'sucesso','dados'=>$dados]);
    } catch(\Exception $e) { http_response_code(500); echo json_encode(['status'=>'erro','mensagem'=>$e->getMessage()]); }
});
