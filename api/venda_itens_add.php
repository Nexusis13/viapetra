<?php
require_once '../config/protect.php';
require_once '../config/config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método não permitido']);
    exit;
}



$id_venda = isset($_POST['id_venda']) ? (int)$_POST['id_venda'] : 0;
$id_ambiente = isset($_POST['id_ambiente']) && $_POST['id_ambiente'] !== '' ? (int)$_POST['id_ambiente'] : null;
$id_materia = isset($_POST['id_materia']) && $_POST['id_materia'] !== '' ? (int)$_POST['id_materia'] : null;
$id_peca = isset($_POST['id_peca']) && $_POST['id_peca'] !== '' ? (int)$_POST['id_peca'] : null;
$nome_ambiente = isset($_POST['nome_ambiente']) ? trim($_POST['nome_ambiente']) : null;
$nome_materia_prima = isset($_POST['nome_materia_prima']) ? trim($_POST['nome_materia_prima']) : null;
$nome_peca = isset($_POST['nome_peca']) ? trim($_POST['nome_peca']) : null;
$qtd_itens = isset($_POST['qtd_itens']) ? (int)$_POST['qtd_itens'] : 1;

// Exigir pelo menos um dos campos preenchidos
if (
    empty($nome_materia_prima)
    && empty($nome_peca)
    && empty($nome_ambiente)
) {
    echo json_encode(['success' => false, 'error' => 'Preencha pelo menos Matéria-prima, Peça ou Ambiente.']);
    exit;
}

$valor_item = isset($_POST['valor_item']) && $_POST['valor_item'] !== '' ? (float)$_POST['valor_item'] : null;

if (!$id_venda || !$qtd_itens) {
    http_response_code(400);
    echo json_encode(['error' => 'Dados obrigatórios ausentes']);
    exit;
}

$stmt = $pdo->prepare('INSERT INTO vendas_itens (id_venda, id_ambiente, id_materia, id_peca, nome_ambiente, nome_materia_prima, nome_peca, qtd_itens, valor_item) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
$stmt->execute([$id_venda, $id_ambiente, $id_materia, $id_peca, $nome_ambiente, $nome_materia_prima, $nome_peca, $qtd_itens, $valor_item]);

$id_item = $pdo->lastInsertId();

$stmt = $pdo->prepare('SELECT id_item, id_venda, id_ambiente, id_materia, id_peca, nome_ambiente, nome_materia_prima, nome_peca, qtd_itens, valor_item FROM vendas_itens WHERE id_item = ?');
$stmt->execute([$id_item]);
$item = $stmt->fetch(PDO::FETCH_ASSOC);

echo json_encode(['success' => true, 'item' => $item]);
