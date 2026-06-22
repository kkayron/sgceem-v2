<?php
// Rotas do Módulo: Almoxarifado

$router->get('/almoxarifado/produtos', function() {
    try {
        $produtos = \Illuminate\Database\Capsule\Manager::table('almox_produtos')
            ->select('id', 'nome_produto', 'codigo_produto', 'estoque_minimo')
            ->orderBy('nome_produto', 'asc')
            ->limit(50)
            ->get();
            
        $dadosFormatados = $produtos->map(function($prod) {
            $qtdReal = (int) ($prod->estoque_atual ?? $prod->quantidade ?? 0);
            $status = ($qtdReal > $prod->estoque_minimo) ? 'Estoque Bom' : ($qtdReal == 0 ? 'Falta' : 'Estoque Baixo');
            
            return [
                'id' => $prod->id,
                'nome' => $prod->nome_produto . ' (' . $prod->codigo_produto . ')',
                'qtd' => $qtdReal,
                'status' => $status
            ];
        });

        echo json_encode(['status' => 'sucesso', 'dados' => $dadosFormatados]);
    } catch (\Exception $e) {
        http_response_code(500);
        echo json_encode(['status' => 'erro', 'mensagem' => 'Falha ao carregar produtos.']);
    }
});

$router->get('/almox/entradas', function() {
    try {
        $dados = \Illuminate\Database\Capsule\Manager::table('almox_entradas')->orderBy('id','desc')->limit(100)->get();
        echo json_encode(['status'=>'sucesso','dados'=>$dados]);
    } catch(\Exception $e) { http_response_code(500); echo json_encode(['status'=>'erro','mensagem'=>$e->getMessage()]); }
});

$router->get('/almox/pedidos', function() {
    try {
        $dados = \Illuminate\Database\Capsule\Manager::table('almox_pedidos_princ')->orderBy('id','desc')->limit(100)->get();
        echo json_encode(['status'=>'sucesso','dados'=>$dados]);
    } catch(\Exception $e) { http_response_code(500); echo json_encode(['status'=>'erro','mensagem'=>$e->getMessage()]); }
});

$router->get('/almox/depositos', function() {
    try {
        $dados = \Illuminate\Database\Capsule\Manager::table('almox_depositos')->orderBy('id','desc')->limit(100)->get();
        echo json_encode(['status'=>'sucesso','dados'=>$dados]);
    } catch(\Exception $e) { http_response_code(500); echo json_encode(['status'=>'erro','mensagem'=>$e->getMessage()]); }
});
