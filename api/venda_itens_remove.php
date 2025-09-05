<?php
require_once '../config/protect.php';
require_once '../config/config.php';
header('Content-Type: application/json');

$id_item = isset($_POST['id_item']) ? (int)$_POST['id_item'] : 0;
if (!$id_item) {
    echo json_encode(['success' => false, 'error' => 'ID do item inválido.']);
    exit;
}

try {
    $stmt = $pdo->prepare('DELETE FROM vendas_itens WHERE id_item = ?');
    $stmt->execute([$id_item]);
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
