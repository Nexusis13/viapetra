<?php
require_once '../config/protect.php';
require_once '../config/config.php';
header('Content-Type: application/json');

$erro = '';
$id_venda = null;

// Receber dados via POST
$id_vendedor = $_POST['id_vendedor'] ?? '';
$id_vendedor2 = $_POST['id_vendedor2'] ?? '';
$id_comissao = $_POST['id_comissao'] ?? '';
$id_comissao2 = $_POST['id_comissao2'] ?? '';
$dt_venda = $_POST['dt_venda'] ?? '';
$cliente = trim($_POST['cliente'] ?? '');
$vlr_total = str_replace(['.', ','], ['', '.'], $_POST['vlr_total'] ?? '');
$vlr_entrada = str_replace(['.', ','], ['', '.'], $_POST['vlr_entrada'] ?? '');
$forma_pg = $_POST['forma_pg'] ?? '';
$qtd_parcelas = $_POST['qtd_parcelas'] ?? '1';
$dt_boletos = $_POST['dt_boletos'] ?? '';

// Validações mínimas
if (empty($dt_venda)) {
    echo json_encode(['success' => false, 'error' => 'Informe a data da venda.']);
    exit;
}
if (empty($cliente)) {
    echo json_encode(['success' => false, 'error' => 'Informe o nome do cliente.']);
    exit;
}
if (empty($id_vendedor)) {
    echo json_encode(['success' => false, 'error' => 'Selecione o vendedor principal.']);
    exit;
}

try {
    $pdo->beginTransaction();
    $stmt = $pdo->prepare("INSERT INTO vendas (id_vendedor, id_vendedor2, id_comissao, id_comissao2, dt_venda, cliente, vlr_total, vlr_entrada, forma_pg, qtd_parcelas, dt_boletos) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        $id_vendedor,
        !empty($id_vendedor2) ? $id_vendedor2 : null,
        !empty($id_comissao) ? $id_comissao : null,
        !empty($id_comissao2) ? $id_comissao2 : null,
        $dt_venda,
        $cliente,
        $vlr_total,
        $vlr_entrada,
        $forma_pg,
        $qtd_parcelas,
        !empty($dt_boletos) ? $dt_boletos : null
    ]);
    $id_venda = $pdo->lastInsertId();
    $pdo->commit();
    echo json_encode(['success' => true, 'id_venda' => $id_venda]);
} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'error' => 'Erro ao salvar venda: ' . $e->getMessage()]);
}
