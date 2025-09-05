<?php
require_once '../config/protect.php';
require_once '../config/config.php';

if (!isset($_GET['id_venda']) || !is_numeric($_GET['id_venda'])) {
    http_response_code(400);
    echo json_encode(['error' => 'ID da venda inválido']);
    exit;
}

$id_venda = (int)$_GET['id_venda'];



try {
    $stmt = $pdo->prepare('SELECT vi.id_item, vi.id_materia, vi.id_peca, vi.id_ambiente, vi.qtd_itens, vi.valor_item,
        vi.nome_ambiente, vi.nome_peca, vi.nome_materia_prima
        FROM vendas_itens vi
        WHERE vi.id_venda = ?
        ORDER BY vi.id_item DESC');
    $stmt->execute([$id_venda]);
    $itens = $stmt->fetchAll(PDO::FETCH_ASSOC);
    header('Content-Type: application/json');
    echo json_encode($itens);
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Erro SQL: ' . $e->getMessage()]);
}
