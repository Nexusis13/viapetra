<?php
require_once '../config/config.php';
header('Content-Type: application/json; charset=utf-8');

$q = isset($_GET['q']) ? trim($_GET['q']) : '';
if ($q === '') {
    // Busca vazia: retorna os 25 primeiros clientes ativos
    $sql = "SELECT client_id, nome, documento, telefone, email FROM clientes WHERE status = 1 ORDER BY nome ASC LIMIT 25";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
} else {
    // Substitui espaços por % para busca flexível
    $q_flex = str_replace(' ', '%', $q);
    $sql = "SELECT client_id, nome, documento, telefone, email FROM clientes WHERE status = 1 AND (
        nome LIKE ? OR documento LIKE ? OR telefone LIKE ? OR email LIKE ?
    ) ORDER BY nome ASC LIMIT 25";
    $param = '%' . $q_flex . '%';
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$param, $param, $param, $param]);
}
$clientes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Formatar documento
function formatarDocumento($doc) {
    $doc = preg_replace('/\D/', '', $doc);
    if (strlen($doc) === 11) {
        return preg_replace("/(\d{3})(\d{3})(\d{3})(\d{2})/", "$1.$2.$3-$4", $doc);
    } elseif (strlen($doc) === 14) {
        return preg_replace("/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/", "$1.$2.$3/$4-$5", $doc);
    }
    return $doc;
}

foreach ($clientes as &$c) {
    $c['documento'] = formatarDocumento($c['documento']);
}

echo json_encode($clientes);
