<?php
// Rotas do Módulo: Administração (Usuários, Permissões, Cadastros Base)

$router->get('/usuarios', function() {
    try {
        $users = \Illuminate\Database\Capsule\Manager::table('usuarios as u')
            ->select('u.id','u.postograd','u.nomeguerra','u.nomecompleto','u.usuario','u.role_id','u.status', 'r.name as role_name')
            ->leftJoin('roles as r','r.id','=','u.role_id')
            ->orderBy('u.id','desc')->limit(100)->get();
        echo json_encode(['status'=>'sucesso','dados'=>$users]);
    } catch(\Exception $e) {
        http_response_code(500);
        echo json_encode(['status'=>'erro','mensagem'=>$e->getMessage()]);
    }
});

$router->post('/usuarios', function() {
    try {
        $data = json_decode(file_get_contents('php://input'), true);
        
        $postograd = $data['postograd'] ?? '';
        $nomeguerra = $data['nomeguerra'] ?? '';
        $nomecompleto = !empty($data['nomecompleto']) ? $data['nomecompleto'] : trim($postograd . ' ' . $nomeguerra);
        $usuario = !empty($data['usuario']) ? $data['usuario'] : strtolower(str_replace(' ', '', $nomeguerra));
        
        \Illuminate\Database\Capsule\Manager::table('usuarios')->insert([
            'postograd' => $postograd,
            'nomeguerra' => $nomeguerra,
            'nomecompleto' => $nomecompleto,
            'usuario' => $usuario,
            'senha' => password_hash($data['senha'], PASSWORD_DEFAULT),
            'role_id' => $data['role_id'] ?? 3,
            'status' => '1'
        ]);
        echo json_encode(['status'=>'sucesso','mensagem'=>'Usuário cadastrado com sucesso!', 'usuario_gerado' => $usuario]);
    } catch(\Exception $e) {
        http_response_code(500);
        echo json_encode(['status'=>'erro','mensagem'=>$e->getMessage()]);
    }
});

$router->put('/usuarios/(\d+)', function($id) {
    try {
        $data = json_decode(file_get_contents('php://input'), true);
        $update = [
            'postograd' => $data['postograd'],
            'nomeguerra' => $data['nomeguerra'],
            'nomecompleto' => $data['nomecompleto'],
            'usuario' => $data['usuario'],
            'role_id' => $data['role_id'] ?? 3,
            'status' => $data['status']
        ];
        if (!empty($data['senha'])) $update['senha'] = password_hash($data['senha'], PASSWORD_DEFAULT);
        \Illuminate\Database\Capsule\Manager::table('usuarios')->where('id',$id)->update($update);
        notificar_alteracao_bd();
        echo json_encode(['status'=>'sucesso','mensagem'=>'Usuário atualizado.']);
    } catch(\Exception $e) {
        http_response_code(500);
        echo json_encode(['status'=>'erro','mensagem'=>$e->getMessage()]);
    }
});

$router->delete('/usuarios/(\d+)', function($id) {
    try {
        \Illuminate\Database\Capsule\Manager::table('usuarios')->where('id',$id)->delete();
        echo json_encode(['status'=>'sucesso','mensagem'=>'Usuário removido.']);
    } catch(\Exception $e) {
        http_response_code(500);
        echo json_encode(['status'=>'erro','mensagem'=>$e->getMessage()]);
    }
});

$router->get('/oms', function() {
    try {
        $dados = \Illuminate\Database\Capsule\Manager::table('organizacoes_militares')->orderBy('id','asc')->get();
        echo json_encode(['status'=>'sucesso','dados'=>$dados]);
    } catch(\Exception $e) { http_response_code(500); echo json_encode(['status'=>'erro','mensagem'=>$e->getMessage()]); }
});

$router->get('/funcoes', function() {
    try {
        $dados = \Illuminate\Database\Capsule\Manager::table('funcoes')->orderBy('id','asc')->get();
        echo json_encode(['status'=>'sucesso','dados'=>$dados]);
    } catch(\Exception $e) { http_response_code(500); echo json_encode(['status'=>'erro','mensagem'=>$e->getMessage()]); }
});

$router->get('/logs', function() {
    try {
        $dados = \Illuminate\Database\Capsule\Manager::table('logs')->orderBy('id','desc')->limit(200)->get();
        echo json_encode(['status'=>'sucesso','dados'=>$dados]);
    } catch(\Exception $e) { http_response_code(500); echo json_encode(['status'=>'erro','mensagem'=>$e->getMessage()]); }
});

$router->get('/paginas', function() {
    try {
        $dados = \Illuminate\Database\Capsule\Manager::table('paginas as p')
            ->select('p.*','pp.nome as grupo_nome')
            ->leftJoin('paginas_principal as pp','pp.id','=','p.tipo')
            ->orderBy('p.tipo','asc')->orderBy('p.ordem','asc')->get();
        echo json_encode(['status'=>'sucesso','dados'=>$dados]);
    } catch(\Exception $e) { http_response_code(500); echo json_encode(['status'=>'erro','mensagem'=>$e->getMessage()]); }
});
