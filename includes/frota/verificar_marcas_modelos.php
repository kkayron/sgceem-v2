<?php
include_once('../../conexao/config.php'); // já define $conexao
require_once '../api/seguranca_cadastrar.php';

$permissoes = verificarPermissao(2);

// 1️⃣ Sincronizar Marcas
$sqlMarcasFrota = "SELECT DISTINCT marca FROM frota WHERE marca IS NOT NULL AND marca != ''";
$resultMarcas = $conexao->query($sqlMarcasFrota);

while ($row = $resultMarcas->fetch_assoc()) {
    $marca = trim($row['marca']);

    // Se já for numérico (ID), ignora
    if (is_numeric($marca)) {
        continue;
    }

    $marca = $conexao->real_escape_string($marca);

    // Verifica se já existe na config_marcas
    $checkMarca = "SELECT id FROM config_marcas WHERE marca = '$marca'";
    $resCheck = $conexao->query($checkMarca);

    if ($resCheck->num_rows == 0) {
        // Insere a marca na config_marcas
        $insertMarca = "INSERT INTO config_marcas (marca) VALUES ('$marca')";
        $conexao->query($insertMarca);
        echo "Marca cadastrada: $marca<br>";
    }
}

// 2️⃣ Sincronizar Modelos
$sqlModelosFrota = "SELECT DISTINCT marca, modelo FROM frota WHERE modelo IS NOT NULL AND modelo != ''";
$resultModelos = $conexao->query($sqlModelosFrota);

while ($row = $resultModelos->fetch_assoc()) {
    $marca = trim($row['marca']);
    $modelo = trim($row['modelo']);

    // Se modelo já for numérico, ignora
    if (is_numeric($modelo)) {
        continue;
    }

    // Se marca for numérica, usa direto como ID
    if (is_numeric($marca)) {
        $marcaId = (int)$marca;
    } else {
        $marca = $conexao->real_escape_string($marca);

        // Pega o ID da marca na config_marcas
        $getMarcaId = "SELECT id FROM config_marcas WHERE marca = '$marca'";
        $resMarca = $conexao->query($getMarcaId);
        if ($resMarca->num_rows == 0) {
            // Marca não encontrada, ignora
            continue;
        }
        $marcaId = $resMarca->fetch_assoc()['id'];
    }

    $modelo = $conexao->real_escape_string($modelo);

    // Verifica se o modelo já existe para esta marca
    $checkModelo = "SELECT id FROM config_modelos WHERE id_marca = $marcaId AND nome_modelo = '$modelo'";
    $resModelo = $conexao->query($checkModelo);

    if ($resModelo->num_rows == 0) {
        // Insere modelo
        $insertModelo = "INSERT INTO config_modelos (id_marca, nome_modelo) VALUES ($marcaId, '$modelo')";
        $conexao->query($insertModelo);
        echo "Modelo cadastrado: $modelo (Marca ID: $marcaId)<br>";
    }
}

echo "Sincronização concluída.";
