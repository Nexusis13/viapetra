<?php
require_once '../config/protect.php';
require_once '../config/config.php';
header('Content-Type: application/json');

$id_item = isset($_GET['id_item']) ? (int)$_GET['id_item'] : 0;
if (!$id_item) {
    echo json_encode(['success' => false, 'error' => 'ID do item inválido.']);
    exit;
}

try {
    $stmt = $pdo->prepare('SELECT * FROM vendas_itens WHERE id_item = ?');
    $stmt->execute([$id_item]);
    $item = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($item) {
        echo json_encode($item);
    } else {
        echo json_encode(['error' => 'Item não encontrado.']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
