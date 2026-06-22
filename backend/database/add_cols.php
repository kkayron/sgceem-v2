<?php
require '/home/kayrondev/Documentos/SISTEMA GESTAO DE EMPENHO 367/backend/bootstrap.php';
use Illuminate\Database\Capsule\Manager as DB;

try {
    $schema = DB::schema();
    if (!$schema->hasTable('fin_notas_fiscais')) {
        $schema->create('fin_notas_fiscais', function($table) {
            $table->increments('id');
            $table->timestamps();
        });
        echo "Created fin_notas_fiscais table.\n";
    }

    $tables = ['fin_empenhos', 'fin_notas_fiscais'];
    $cols = [
        'cnpj_fornecedor' => 'VARCHAR(45)',
        'nome_empresa' => 'TEXT',
        'valor_total' => 'VARCHAR(45)',
        'valor_produto' => 'VARCHAR(45)',
        'valor_frete' => 'VARCHAR(45)',
        'quantidade_item' => 'VARCHAR(45)',
        'item_descricao' => 'TEXT',
        'numero_nf' => 'VARCHAR(45)',
        'status' => 'VARCHAR(45)',
        'data_emissao' => 'DATE',
        'empenho_id' => 'INT',
        'chave_acesso' => 'VARCHAR(50)',
        'natureza_despesa' => 'VARCHAR(45)',
        'descricao_empenho' => 'TEXT'
    ];

    foreach($tables as $t) {
        foreach($cols as $c => $type) {
            if (!$schema->hasColumn($t, $c)) {
                DB::statement("ALTER TABLE $t ADD COLUMN $c $type NULL");
                echo "Added $c to $t\n";
            }
        }
    }
    echo "Done DB Modification.\n";
} catch(Exception $e) {
    echo $e->getMessage();
}
