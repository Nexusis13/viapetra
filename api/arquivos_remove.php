<?php
// api/arquivos_remove.php
require_once '../config/protect.php';
require_once '../config/config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Método inválido']);
    exit;
}

$id_arquivo = isset($_POST['id_arquivo']) ? (int)$_POST['id_arquivo'] : 0;
if (!$id_arquivo) {
    echo json_encode(['success' => false, 'error' => 'ID do arquivo não informado.']);
    exit;
}

// Buscar o caminho do arquivo
$stmt = $pdo->prepare('SELECT nome FROM arquivos WHERE id_arquivo = ?');
$stmt->execute([$id_arquivo]);
$arq = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$arq) {
    echo json_encode(['success' => false, 'error' => 'Arquivo não encontrado no banco.']);
    exit;
}

$caminho = dirname(__DIR__, 1) . DIRECTORY_SEPARATOR . $arq['nome'];

// Remover do disco
$erro_arquivo = false;
if (is_file($caminho)) {
    if (!@unlink($caminho)) {
        $erro_arquivo = true;
    }
}

// Remover do banco
$stmt = $pdo->prepare('DELETE FROM arquivos WHERE id_arquivo = ?');
$stmt->execute([$id_arquivo]);

if ($erro_arquivo) {
    echo json_encode(['success' => false, 'error' => 'Erro ao remover arquivo do disco, mas removido do banco.']);
} else {
    echo json_encode(['success' => true]);
}
