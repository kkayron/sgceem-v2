<?php
// Rotas do Módulo: Ordens de Serviço e Manutenção

$router->get('/os', function() {
    try {
        $dados = \Illuminate\Database\Capsule\Manager::table('os_principal as o')->select('o.*')->orderBy('o.id','desc')->limit(100)->get();
        echo json_encode(['status'=>'sucesso','dados'=>$dados]);
    } catch(\Exception $e) { http_response_code(500); echo json_encode(['status'=>'erro','mensagem'=>$e->getMessage()]); }
});
$router->delete('/os/(\d+)', function($id) {
    try {
        \Illuminate\Database\Capsule\Manager::table('os_principal')->where('id',$id)->delete();
        echo json_encode(['status'=>'sucesso','mensagem'=>'OS removida.']);
    } catch(\Exception $e) { http_response_code(500); echo json_encode(['status'=>'erro','mensagem'=>$e->getMessage()]); }
});

$router->get('/preventiva', function() {
    try {
        $dados = \Illuminate\Database\Capsule\Manager::table('manutencao')->orderBy('id','desc')->limit(100)->get();
        echo json_encode(['status'=>'sucesso','dados'=>$dados]);
    } catch(\Exception $e) { http_response_code(500); echo json_encode(['status'=>'erro','mensagem'=>$e->getMessage()]); }
});

$router->get('/plano-mnt', function() {
    try {
        $dados = \Illuminate\Database\Capsule\Manager::table('mnt_planos')->orderBy('id','desc')->limit(100)->get();
        echo json_encode(['status'=>'sucesso','dados'=>$dados]);
    } catch(\Exception $e) { http_response_code(500); echo json_encode(['status'=>'erro','mensagem'=>$e->getMessage()]); }
});

$router->get('/sta-fichas', function() {
    try {
        $dados = \Illuminate\Database\Capsule\Manager::table('sta_fichas')->orderBy('id','desc')->limit(100)->get();
        echo json_encode(['status'=>'sucesso','dados'=>$dados]);
    } catch(\Exception $e) { http_response_code(500); echo json_encode(['status'=>'erro','mensagem'=>$e->getMessage()]); }
});
