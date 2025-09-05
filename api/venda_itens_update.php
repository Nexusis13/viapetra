<?php
require_once '../config/protect.php';
require_once '../config/config.php';
header('Content-Type: application/json');


$id_item = isset($_POST['id_item']) ? (int)$_POST['id_item'] : 0;
$id_materia = $_POST['id_materia'] ?? null;
$id_peca = $_POST['id_peca'] ?? null;
$id_ambiente = $_POST['id_ambiente'] ?? null;
$nome_materia_prima = $_POST['nome_materia_prima'] ?? null;
$nome_peca = $_POST['nome_peca'] ?? null;
$nome_ambiente = $_POST['nome_ambiente'] ?? null;
$qtd_itens = $_POST['qtd_itens'] ?? 1;
$valor_item = $_POST['valor_item'] ?? null;


if (!$id_item) {
    echo json_encode(['success' => false, 'error' => 'ID do item inválido.']);
    exit;
}

try {
    $stmt = $pdo->prepare('UPDATE vendas_itens SET id_materia = ?, id_peca = ?, id_ambiente = ?, nome_materia_prima = ?, nome_peca = ?, nome_ambiente = ?, qtd_itens = ?, valor_item = ? WHERE id_item = ?');
    $stmt->execute([$id_materia, $id_peca, $id_ambiente, $nome_materia_prima, $nome_peca, $nome_ambiente, $qtd_itens, $valor_item, $id_item]);
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
