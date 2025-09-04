<?php
require_once '../config/config.php';
header('Content-Type: application/json; charset=utf-8');

$q = isset($_GET['q']) ? trim($_GET['q']) : '';
if ($q === '') {
    // Busca vazia: retorna os 25 primeiros
    $sql = "SELECT id_ambiente, nome FROM ambiente ORDER BY nome ASC LIMIT 25";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
} else {
    // Busca flexível: substitui espaço por %
    $q_flex = str_replace(' ', '%', $q);
    $sql = "SELECT id_ambiente, nome FROM ambiente WHERE nome LIKE ? ORDER BY nome ASC LIMIT 25";
    $param = '%' . $q_flex . '%';
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$param]);
}
$ambientes = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo json_encode($ambientes);
