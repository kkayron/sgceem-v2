<?php
include_once('conexao/config.php');

// Consulta única para vários totais
$sql_totais = "
    SELECT
        COUNT(*) AS total_vtr,
        SUM(CASE WHEN confiabilidade = 'Confiável' THEN 1 ELSE 0 END) AS total_confiaveis,
        SUM(CASE WHEN confiabilidade = 'Não confiável' THEN 1 ELSE 0 END) AS total_nao_confiaveis,
        SUM(CASE WHEN disponibilidade = 'Disponível' THEN 1 ELSE 0 END) AS total_disponiveis,
        SUM(CASE WHEN disponibilidade != 'Disponível' THEN 1 ELSE 0 END) AS total_indisponiveis,
        SUM(CASE WHEN confiabilidade = 'Emprestado' THEN 1 ELSE 0 END) AS total_emprestadas,
        SUM(CASE WHEN confiabilidade = 'Em processo de descarga' THEN 1 ELSE 0 END) AS total_em_descarga
    FROM frota
    WHERE tipo = 'Vtr'
";

$result = $conexao->query($sql_totais);
$totais = $result->fetch_assoc();

// Agora você pode acessar facilmente:
echo "Total Viaturas: " . $totais['total_vtr'] . "<br>";
echo "Confiáveis: " . $totais['total_confiaveis'] . "<br>";
echo "Não confiáveis: " . $totais['total_nao_confiaveis'] . "<br>";
echo "Disponíveis: " . $totais['total_disponiveis'] . "<br>";
echo "Indisponíveis: " . $totais['total_indisponiveis'] . "<br>";
echo "Emprestadas: " . $totais['total_emprestadas'] . "<br>";
echo "Em Descarga: " . $totais['total_em_descarga'] . "<br>";
?>
