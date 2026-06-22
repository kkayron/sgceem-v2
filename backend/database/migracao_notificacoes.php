<?php
$dbHost = '127.0.0.1';
$dbUser = 'root';
$dbPass = ''; 
$dbName = 'sgceem_v2'; 

try {
    $pdo = new PDO("mysql:host=$dbHost;dbname=$dbName", $dbUser, $dbPass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Criar tabela de notificacoes
    $sql = "
    CREATE TABLE IF NOT EXISTS notificacoes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        destinatario_tipo ENUM('USER', 'ROLE') NOT NULL DEFAULT 'USER',
        destinatario_id INT NOT NULL,
        titulo VARCHAR(255) NOT NULL,
        mensagem TEXT NOT NULL,
        nivel ENUM('INFO', 'WARNING', 'CRITICAL') NOT NULL DEFAULT 'INFO',
        acao_url VARCHAR(255) NULL,
        lida TINYINT(1) DEFAULT 0,
        data_criacao DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";

    $pdo->exec($sql);
    echo "Tabela 'notificacoes' criada com sucesso.\n";

    // Criar uma notificacao Fake pra gente testar o Bell!
    // Para o Role 2 (Almox) e Role 9 (Financeiro)
    $pdo->exec("INSERT INTO notificacoes (destinatario_tipo, destinatario_id, titulo, mensagem, nivel, acao_url) VALUES 
        ('ROLE', 2, 'Alerta: Pneu 1000x20', 'O estoque deste item atingiu o limite crítico de segurança. Nível atual: 5 unidades.', 'CRITICAL', '/includes/almox_produtos/listagem'),
        ('ROLE', 3, 'Alerta: Empenho Vencendo', 'O Empenho 2026NE000001 (Frota BEC) está a menos de 10 dias do seu vencimento.', 'WARNING', '/includes/fin_empenhos/listagem')
    ");
    echo "Notificações fakes inseridas!\n";

} catch (\Exception $e) {
    echo "Erro: " . $e->getMessage() . "\n";
}
