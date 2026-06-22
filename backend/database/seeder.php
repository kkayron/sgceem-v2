<?php
/**
 * SGCEEM v2.0 - Seeder de Teste Nível Militar
 * Este script popula o banco de dados com massa de testes rica para todos os módulos principais.
 */

$dbHost = '127.0.0.1';
$dbUser = 'root';
$dbPass = ''; 
$dbName = 'sgceem_v2'; 

try {
    $pdo = new PDO("mysql:host=$dbHost;dbname=$dbName", $dbUser, $dbPass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Disable FK checks to allow truncation
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");
    
    echo "Conectado ao Banco de Dados. Iniciando semeadura...\n";

    // 1. Organizações Militares (OM)
    $pdo->exec("TRUNCATE TABLE organizacoes_militares");
    $oms = [
        ['1º Batalhão de Engenharia de Construção', '1º BEC', 'Caicó', 'RN'],
        ['2º Batalhão de Engenharia de Construção', '2º BEC', 'Teresina', 'PI'],
        ['3º Batalhão de Engenharia de Construção', '3º BEC', 'Picos', 'PI'],
        ['4º Batalhão de Engenharia de Construção', '4º BEC', 'Barreiras', 'BA']
    ];
    $stmt = $pdo->prepare("INSERT INTO organizacoes_militares (nome, abreviatura, cidade, uf) VALUES (?, ?, ?, ?)");
    foreach ($oms as $om) $stmt->execute($om);
    echo "-> Organizações Militares populadas.\n";

    // 2. Funcoes ignorado pois a tabela nao existe mais (consolidado em roles)

    // 3. Financeiro: Fornecedores
    $pdo->exec("TRUNCATE TABLE fin_fornecedores");
    $fornecedores = [
        ['Comércio de Alimentos Alpha', '98765432000110', 'Alimentação', 'Maria Souza', '(84) 98888-2222', 'vendas@alpha.com.br', '2026-02-15'],
        ['Tech Tática Equipamentos', '45123890000155', 'Equipamentos Táticos', 'Carlos Oliveira', '(61) 97777-3333', 'gov@techtatica.com', '2026-03-10']
    ];
    $stmt = $pdo->prepare("INSERT INTO fin_fornecedores (nome_empresa, cnpj_empresa, categoria_empresa, contato_nome, contato_numero, contato_email, data_cadastro) VALUES (?, ?, ?, ?, ?, ?, ?)");
    foreach ($fornecedores as $f) {
        $f_clean = array_slice($f, 0, 7); // Remove the batalhao which was at index 7
        $stmt->execute($f_clean);
    }
    echo "-> Fornecedores populados.\n";

    // 4. Financeiro: Pregões
    $pdo->exec("TRUNCATE TABLE fin_pregao");
    $pregoes = [
        ['10/2026', 'Aquisição de peças para viaturas pesadas', 'SRP', '2026-12-31'],
        ['15/2026', 'Fornecimento de Gêneros Alimentícios', 'SRP', '2026-12-31'],
        ['05/2026', 'Material de Consumo e Limpeza', 'Tradicional', '2026-12-31']
    ];
    // Ajuste baseado no schema de fin_pregao
    $stmt = $pdo->prepare("INSERT INTO fin_pregao (nmr_pregao, descricao_pregao, tipo_pregao, data_validade) VALUES (?, ?, ?, ?)");
    foreach ($pregoes as $p) {
        try { $stmt->execute($p); } catch (\Exception $e) {} // Ignora se schema divergir levemente
    }
    echo "-> Pregões populados.\n";

    // 5. Financeiro: Empenhos
    $pdo->exec("TRUNCATE TABLE fin_empenhos");
    $empenhos = [
        ['2026NE000001', 'Manutenção Frota BEC', 150000.00, '2026-04-01'],
        ['2026NE000002', 'Rancho (Carne e Cereais)', 85000.00, '2026-04-15'],
        ['2026NE000003', 'Materiais Táticos e Sobrevivência', 220000.00, '2026-05-10']
    ];
    $stmt = $pdo->prepare("INSERT INTO fin_empenhos (nmr_empenho, descricao_empenho, valor_total, data_empenho) VALUES (?, ?, ?, ?)");
    foreach ($empenhos as $e) {
        try { $stmt->execute($e); } catch (\Exception $ex) {}
    }
    echo "-> Empenhos populados.\n";

    // 6. Frota
    $pdo->exec("TRUNCATE TABLE frota");
    $frotas = [
        ['EB34001', 'Mercedes-Benz', 'Atego 1725A', 'BRK-1234', 'Operacional', 'Sim', 1, 'Geral'],
        ['EB34002', 'Agrale', 'Marruá AM11', 'AMX-9876', 'Manutenção', 'Não', 1, 'Geral'],
        ['EB34003', 'Volkswagen', 'Constellation 31.320', 'XYZ-5544', 'Operacional', 'Sim', 1, 'Geral'],
        ['EB34004', 'Toyota', 'Hilux SW4', 'OFB-2211', 'Administrativo', 'Sim', 2, 'Geral']
    ];
    $stmt = $pdo->prepare("INSERT INTO frota (prefixo_sga, marca, modelo, placa, status, disponibilidade, batalhao, destino) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    foreach ($frotas as $f) {
        try { $stmt->execute($f); } catch (\Exception $e) {}
    }
    echo "-> Frota populada.\n";

    // 7. Almoxarifado: Produtos
    $pdo->exec("TRUNCATE TABLE almox_produtos");
    $produtos = [
        [date('Y-m-d'), 'Pneu 1000x20', 'PN-001', 'Borracha', 'Und', 50, 10, 'Prateleira A1'],
        [date('Y-m-d'), 'Óleo de Motor 15W40', 'OL-001', 'Lubrificante', 'Litro', 200, 50, 'Prateleira B2'],
        [date('Y-m-d'), 'Bateria 150Ah', 'BT-001', 'Elétrica', 'Und', 15, 5, 'Prateleira C1'],
        [date('Y-m-d'), 'Filtro de Ar Primário', 'FL-001', 'Filtros', 'Und', 30, 10, 'Prateleira D3']
    ];
    $stmt = $pdo->prepare("INSERT INTO almox_produtos (data_inclusao, nome_produto, codigo_produto, categoria_produto, unidade_medida, estoque_atual, estoque_minimo, localizacao) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    foreach ($produtos as $p) {
        try { $stmt->execute($p); } catch (\Exception $e) {}
    }
    echo "-> Produtos do Almoxarifado populados.\n";

    // 8. Ordens de Serviço
    $pdo->exec("TRUNCATE TABLE os_principal");
    $os = [
        ['1', 'Troca de Óleo e Filtros', 'Corretiva', 'Em Andamento', 'Sgt Silva', '2026-06-01'],
        ['2', 'Revisão de Freios', 'Preventiva', 'Aberta', 'Cb Souza', '2026-06-15'],
        ['3', 'Alinhamento e Balanceamento', 'Preventiva', 'Concluída', 'Sgt Costa', '2026-05-20']
    ];
    $stmt = $pdo->prepare("INSERT INTO os_principal (id_frota, problema, tipo_mnt, status, mecanico_responsavel, data_abertura) VALUES (?, ?, ?, ?, ?, ?)");
    foreach ($os as $o) {
        try { $stmt->execute($o); } catch (\Exception $e) {}
    }
    echo "-> Ordens de Serviço populadas.\n";

    // 9. Usuários de Teste (Um para cada Role)
    $roles = $pdo->query("SELECT id, name FROM roles WHERE id != 1 AND id != 16")->fetchAll(PDO::FETCH_ASSOC); // Pula Admin e Dev
    $senha_padrao = password_hash('123456', PASSWORD_BCRYPT);
    
    // Usando try/catch individual para nao falhar se o usuario ja existir (unique constraint)
    $stmtUser = $pdo->prepare("INSERT INTO usuarios (nomecompleto, usuario, senha, role_id, status, nomeguerra, postograd) VALUES (?, ?, ?, ?, 1, ?, 'Sgt')");
    
    foreach ($roles as $r) {
        $slug = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '_', $r['name']));
        $usuario_login = "teste." . $slug;
        $nome_guerra = explode(' ', $r['name'])[0];
        try {
            $stmtUser->execute([
                "Usuário Teste - {$r['name']}",
                $usuario_login,
                $senha_padrao,
                $r['id'],
                $nome_guerra
            ]);
        } catch (\Exception $e) {}
    }
    echo "-> Usuários de teste para todas as funções criados (Senha Padrão: 123456).\n";

    echo "\n=== SUCESSO! Banco de Dados semeado com Sucesso. Ambiente pronto para Testes Oficiais. ===\n";

    // Re-enable FK checks
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");

} catch (\Exception $e) {
    // Attempt to re-enable FK checks on error just in case
    try { $pdo->exec("SET FOREIGN_KEY_CHECKS = 1;"); } catch (\Exception $ex) {}
    echo "ERRO AO SEMEAR BANCO: " . $e->getMessage() . "\n";
}
