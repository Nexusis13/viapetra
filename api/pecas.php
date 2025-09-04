<?php
require_once '../config/config.php';
header('Content-Type: application/json; charset=utf-8');

$q = isset($_GET['q']) ? trim($_GET['q']) : '';
if ($q === '') {
    // Busca vazia: retorna os 25 primeiros
    $sql = "SELECT id_peca, tipo, formapg FROM pecas ORDER BY tipo ASC LIMIT 25";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
} else {
    // Busca flexível: substitui espaço por %
    $q_flex = str_replace(' ', '%', $q);
    $sql = "SELECT id_peca, tipo, formapg FROM pecas WHERE tipo LIKE ? ORDER BY tipo ASC LIMIT 25";
    $param = '%' . $q_flex . '%';
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$param]);
}
$pecas = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo json_encode($pecas);
