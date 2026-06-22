<?php

function obterBatalhoesPermitidos($conexao, $nivel_usuario, $batalhao_usuario)
{
    // Admin → acesso total (retorna null = sem restrição)
    if ($nivel_usuario == 1) {
        return null;
    }

    // Nível 2 → batalhão + subordinados
    if ($nivel_usuario == 2) {

        $sql = "SELECT id_om_menor FROM organizacoes_militares_sub WHERE id_om_maior = ?";
        $stmt = $conexao->prepare($sql);

        if (!$stmt) {
            throw new Exception("Erro ao buscar subordinados");
        }

        $stmt->bind_param("i", $batalhao_usuario);
        $stmt->execute();
        $result = $stmt->get_result();

        $batalhoes = [(int)$batalhao_usuario];

        while ($row = $result->fetch_assoc()) {
            $batalhoes[] = (int)$row['id_om_menor'];
        }

        $stmt->close();

        return $batalhoes;
    }

    // Nível 3 → apenas o próprio
    return [(int)$batalhao_usuario];
}