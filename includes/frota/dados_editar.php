<?php
require '../../conexao/config.php';

$id = $_GET['id'] ?? null;

if (!$id) {
    echo json_encode(['sucesso' => false, 'mensagem' => 'ID não informado.']);
    exit;
}

// Busca os dados da frota junto com nome da marca e modelo
$stmt = $conexao->prepare("
    SELECT f.*, 
           m.marca AS nome_marca,
           mo.nome_modelo AS nome_modelo
    FROM frota f
    LEFT JOIN config_marcas m ON f.marca = m.id
    LEFT JOIN config_modelos mo ON f.modelo = mo.id
    WHERE f.id = ?
");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$frota = $result->fetch_assoc();

if (!$frota) {
    echo json_encode(['sucesso' => false, 'mensagem' => 'Registro não encontrado.']);
    exit;
}

// Buscar todas as marcas
$marcas = [];
$resMarcas = $conexao->query("SELECT id, marca FROM config_marcas ORDER BY marca");
while ($row = $resMarcas->fetch_assoc()) {
    $marcas[] = $row;
}

// Buscar modelos da marca selecionada
$modelos = [];
if (!empty($frota['marca'])) {
    $stmtModelos = $conexao->prepare("
        SELECT id, nome_modelo 
        FROM config_modelos 
        WHERE id_marca = ? 
        ORDER BY nome_modelo
    ");
    $stmtModelos->bind_param("i", $frota['marca']);
    $stmtModelos->execute();
    $resModelos = $stmtModelos->get_result();
    while ($row = $resModelos->fetch_assoc()) {
        $modelos[] = $row;
    }
}

// Opções de tipo, confiabilidade e disponibilidade
$tipos = [
    ['valor' => 'Vtr', 'texto' => 'Viatura'],
    ['valor' => 'Eqp', 'texto' => 'Equipamento']
];

$confiabilidades = [
    'Confiável','Não confiável','Emprestado','Em processo de descarga',
    'Descarregado','Desfeita (Leiloada ou Recolhida)'
];

$disponibilidades = [
    'Disponível','Disponível com restrição','Indisponível'
];

// Retorna tudo
echo json_encode([
    'sucesso' => true,
    'frota' => $frota, // aqui já vem id_marca, id_modelo, nome_marca, nome_modelo
    'marcas' => $marcas,
    'modelos' => $modelos,
    'tipos' => $tipos,
    'confiabilidades' => $confiabilidades,
    'disponibilidades' => $disponibilidades
]);
