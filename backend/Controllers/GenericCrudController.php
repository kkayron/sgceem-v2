<?php

namespace App\Controllers;

use Illuminate\Database\Capsule\Manager as DB;

class GenericCrudController {

    private $table;
    private $allowedColumns;

    public function __construct($table) {
        $this->table = $table;
        // Obter colunas válidas do schema para evitar SQL injection via nomes de colunas
        $this->allowedColumns = DB::schema()->getColumnListing($table);
    }

    /**
     * Valida de forma genérica as permissões (pode ser customizado depois)
     */
    private function checkPermission($action) {
        $user = $GLOBALS['usuario_logado'] ?? null;
        if (!$user) {
            http_response_code(401);
            echo json_encode(['status' => 'erro', 'mensagem' => 'Acesso negado: Sessão não encontrada.']);
            exit;
        }

        // Exemplo: Mecânicos (nível 3) não podem DELETAR
        if ($action === 'DELETE' && isset($user['nivel']) && $user['nivel'] >= 3) {
            http_response_code(403);
            echo json_encode(['status' => 'erro', 'mensagem' => 'Acesso negado: Nível de permissão insuficiente para excluir registros.']);
            exit;
        }

        return $user;
    }

    public function index() {
        try {
            // Permissão básica de leitura já verificada pelo Middleware, mas podemos refinar aqui
            $user = $GLOBALS['usuario_logado'] ?? null;

            $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 100; // Limite padrão seguro

            // Limitamos a max 500 por requisição por segurança
            if ($limit > 500) $limit = 500;
            if ($page < 1) $page = 1;

            $offset = ($page - 1) * $limit;

            $query = DB::table($this->table)->orderBy('id', 'desc');

            // Exemplo de IDOR Prevention: Se a tabela tem batalhao, filtra pelo batalhao do usuario!
            if (in_array('batalhao', $this->allowedColumns) && isset($user['batalhao']) && isset($user['nivel']) && $user['nivel'] >= 2) {
                // Se não for admin central (ex: nível 1), só pode ver do seu batalhão
                $query->where('batalhao', $user['batalhao']);
            }

            $total = $query->count();
            $dados = $query->limit($limit)->offset($offset)->get();

            echo json_encode([
                'status' => 'sucesso',
                'dados' => $dados,
                'paginacao' => [
                    'total' => $total,
                    'pagina_atual' => $page,
                    'limite' => $limit,
                    'total_paginas' => ceil($total / $limit)
                ]
            ]);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['status' => 'erro', 'mensagem' => 'Erro interno ao listar: ' . $e->getMessage()]);
        }
    }

    public function store() {
        try {
            $user = $this->checkPermission('POST');

            $data = json_decode(file_get_contents('php://input'), true);
            if (empty($data)) throw new \Exception("Nenhum dado enviado");

            $clean_data = [];
            foreach ($data as $key => $val) {
                if (in_array($key, $this->allowedColumns) && $key !== 'id') {
                    $clean_data[$key] = $val;
                }
            }

            // Forçar IDOR Prevention: O usuário não pode enviar `batalhao` de outra OM.
            if (in_array('batalhao', $this->allowedColumns) && isset($user['batalhao']) && isset($user['nivel']) && $user['nivel'] >= 2) {
                $clean_data['batalhao'] = $user['batalhao'];
            }

            // Preenchimento Automático de Datas Obrigatórias (Phantom Shield Complement)
            $dateNow = date('Y-m-d H:i:s');
            $timestampFields = ['data_inclusao', 'data_cadastro', 'created_at', 'data'];
            foreach ($timestampFields as $field) {
                if (in_array($field, $this->allowedColumns) && !isset($clean_data[$field])) {
                    $clean_data[$field] = $dateNow;
                }
            }

            // Fallback para campos NOT NULL legados que não possuem default no schema
            if ($this->table === 'frota') {
                if (in_array('destino', $this->allowedColumns) && !isset($clean_data['destino'])) {
                    $clean_data['destino'] = 'Não Informado';
                }
                if (in_array('disponibilidade', $this->allowedColumns) && !isset($clean_data['disponibilidade'])) {
                    $clean_data['disponibilidade'] = 'Disponível';
                }
            }

            $id = DB::table($this->table)->insertGetId($clean_data);

            echo json_encode(['status' => 'sucesso', 'mensagem' => 'Registro cadastrado com sucesso.', 'id' => $id]);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['status' => 'erro', 'mensagem' => 'Erro ao cadastrar: ' . $e->getMessage()]);
        }
    }

    public function update($id) {
        try {
            $user = $this->checkPermission('PUT');

            $data = json_decode(file_get_contents('php://input'), true);
            if (empty($data)) throw new \Exception("Nenhum dado enviado");

            // Verifica posse do registro (IDOR Check)
            $registro = DB::table($this->table)->where('id', $id)->first();
            if (!$registro) throw new \Exception("Registro não encontrado");

            if (isset($registro->batalhao) && isset($user['batalhao']) && isset($user['nivel']) && $user['nivel'] >= 2) {
                if ($registro->batalhao != $user['batalhao']) {
                    http_response_code(403);
                    echo json_encode(['status' => 'erro', 'mensagem' => 'Acesso negado: Este registro pertence a outra OM.']);
                    exit;
                }
            }

            $clean_data = [];
            foreach ($data as $key => $val) {
                if (in_array($key, $this->allowedColumns) && $key !== 'id' && $key !== 'batalhao') {
                    $clean_data[$key] = $val;
                }
            }

            DB::table($this->table)->where('id', $id)->update($clean_data);

            echo json_encode(['status' => 'sucesso', 'mensagem' => 'Registro atualizado com sucesso.']);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['status' => 'erro', 'mensagem' => 'Erro ao atualizar: ' . $e->getMessage()]);
        }
    }

    public function destroy($id) {
        try {
            $user = $this->checkPermission('DELETE');

            // Verifica posse do registro (IDOR Check)
            $registro = DB::table($this->table)->where('id', $id)->first();
            if (!$registro) throw new \Exception("Registro não encontrado");

            if (isset($registro->batalhao) && isset($user['batalhao']) && isset($user['nivel']) && $user['nivel'] >= 2) {
                if ($registro->batalhao != $user['batalhao']) {
                    http_response_code(403);
                    echo json_encode(['status' => 'erro', 'mensagem' => 'Acesso negado: Este registro pertence a outra OM.']);
                    exit;
                }
            }

            // Em um mundo ideal isso seria um update('deleted_at', now()), mas para não quebrar o PHP legado:
            DB::table($this->table)->where('id', $id)->delete();

            echo json_encode(['status' => 'sucesso', 'mensagem' => 'Registro deletado com sucesso.']);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['status' => 'erro', 'mensagem' => 'Erro ao deletar: ' . $e->getMessage()]);
        }
    }
}
