<?php
header('Content-Type: application/json; charset=utf-8');
if (session_status() === PHP_SESSION_NONE) session_start();
include_once('../../conexao/config.php');

try {
    // --------------------------
    // Dados do usuário
    // --------------------------
    $nivel_usuario    = $_SESSION['usuario']['nivel'] ?? 3;
    $batalhao_usuario = $_SESSION['usuario']['batalhao'] ?? null;

    // Pedido (edição)
    $id_pedido = isset($_GET['id_pedido']) ? intval($_GET['id_pedido']) : 0;

    // --------------------------
    // Filtro de batalhão
    // --------------------------
    $batalhaoFiltro = '';

    if ($nivel_usuario == 1) {

        $batalhaoFiltro = '';

    } elseif ($nivel_usuario == 2) {

        $sqlSub = "SELECT id_om_menor FROM organizacoes_militares_sub WHERE id_om_maior = ?";
        $stmtSub = $conexao->prepare($sqlSub);
        $stmtSub->bind_param("i", $batalhao_usuario);
        $stmtSub->execute();
        $resSub = $stmtSub->get_result();

        $batalhoes = [];
        while ($row = $resSub->fetch_assoc()) {
            $batalhoes[] = $row['id_om_menor'];
        }
        $stmtSub->close();

        if (empty($batalhoes)) {
            throw new Exception('Usuário não tem batalhões autorizados.');
        }

        $batalhaoFiltro = "AND e.batalhao IN (" . implode(',', $batalhoes) . ")";

    } else {
        $batalhaoFiltro = "AND e.batalhao = " . intval($batalhao_usuario);
    }

    // --------------------------
    // SQL principal
    // --------------------------
    $sql = "
        SELECT 
            ei.id_entrada,
            ei.id_produto,
            p.nome_produto,
            ei.marca,
            ei.modelo,
            ei.valor_unt,

            e.batalhao,
            e.deposito_id,
            d.nome_deposito,

            (
                ei.quant 
                - IFNULL((
                    SELECT SUM(pi.quant_solicitada)
                    FROM almox_pedidos_itens pi
                    WHERE pi.id_entrada = ei.id_entrada
                      AND pi.id_produto = ei.id_produto
                ), 0)
                + IFNULL((
                    SELECT SUM(pi2.quant_solicitada)
                    FROM almox_pedidos_itens pi2
                    WHERE pi2.id_pedido_principal = $id_pedido
                      AND pi2.id_entrada = ei.id_entrada
                      AND pi2.id_produto = ei.id_produto
                ), 0)
            ) AS saldo

        FROM almox_entradas_itens ei
        INNER JOIN almox_entradas e 
            ON ei.id_entrada = e.id

        INNER JOIN almox_produtos p 
            ON ei.id_produto = p.id

        LEFT JOIN almox_depositos d 
            ON d.id = e.deposito_id

        WHERE 1=1
        $batalhaoFiltro

        HAVING saldo > 0
        OR EXISTS (
            SELECT 1 
            FROM almox_pedidos_itens pi3
            WHERE pi3.id_pedido_principal = $id_pedido
              AND pi3.id_entrada = ei.id_entrada
              AND pi3.id_produto = ei.id_produto
        )

        ORDER BY 
            d.nome_deposito ASC,
            p.nome_produto ASC
    ";

    $res = $conexao->query($sql);
    if (!$res) {
        throw new Exception("Erro SQL: " . $conexao->error);
    }

    // --------------------------
    // Monta retorno
    // --------------------------
    $produtos = [];

    while ($row = $res->fetch_assoc()) {

        $row['valor_unt'] = (float) $row['valor_unt'];
        $row['saldo']     = (float) $row['saldo'];
        $row['deposito_id'] = (int) $row['deposito_id'];

        // Nome da OM
        $stmtOM = $conexao->prepare(
            "SELECT nome FROM organizacoes_militares WHERE id = ?"
        );
        $stmtOM->bind_param("i", $row['batalhao']);
        $stmtOM->execute();
        $resOM = $stmtOM->get_result();
        $om = $resOM->fetch_assoc();
        $row['om'] = $om['nome'] ?? '';
        $stmtOM->close();

        $produtos[] = $row;
    }

    echo json_encode($produtos);

} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
