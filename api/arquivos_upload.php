<?php
// api/arquivos_upload.php
require_once '../config/protect.php';
require_once '../config/config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Método inválido']);
    exit;
}

$id_venda = isset($_POST['id_venda']) ? (int)$_POST['id_venda'] : 0;
if (!$id_venda) {
    echo json_encode(['success' => false, 'error' => 'ID da venda não informado.']);
    exit;
}

if (!isset($_FILES['pdf_file']) || $_FILES['pdf_file']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'error' => 'Arquivo não enviado ou com erro.']);
    exit;
}

$file = $_FILES['pdf_file'];
$ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
if ($ext !== 'pdf') {
    echo json_encode(['success' => false, 'error' => 'Apenas arquivos PDF são permitidos.']);
    exit;
}

// Criar pasta arquivos_venda/{id_venda} se não existir
$baseDir = dirname(__DIR__, 1) . DIRECTORY_SEPARATOR . 'arquivos_venda' . DIRECTORY_SEPARATOR;
if (!is_dir($baseDir)) {
    $criado = @mkdir($baseDir, 0777, true);
    if (!$criado) {
        $lastError = error_get_last();
        file_put_contents(__DIR__ . '/../logs/upload_debug.log', date('Y-m-d H:i:s') . " ERRO mkdir base: " . print_r($lastError, true) . "\n", FILE_APPEND);
        echo json_encode(['success' => false, 'error' => 'Erro ao criar pasta arquivos_venda.', 'debug' => $lastError]);
        exit;
    }
}

$vendaDir = $baseDir . $id_venda . DIRECTORY_SEPARATOR;
if (!is_dir($vendaDir)) {
    $criado = @mkdir($vendaDir, 0777, true);
    if (!$criado) {
        $lastError = error_get_last();
        file_put_contents(__DIR__ . '/../logs/upload_debug.log', date('Y-m-d H:i:s') . " ERRO mkdir venda: " . print_r($lastError, true) . "\n", FILE_APPEND);
        echo json_encode(['success' => false, 'error' => 'Erro ao criar pasta da venda.', 'debug' => $lastError]);
        exit;
    }
}


$targetName = preg_replace('/[^a-zA-Z0-9_.-]/', '_', basename($file['name']));
$targetPath = $vendaDir . $targetName;
$relativePath = 'arquivos_venda/' . $id_venda . '/' . $targetName;

// Verificar se já existe arquivo com o mesmo nome para esta venda
if (file_exists($targetPath)) {
    echo json_encode(['success' => false, 'error' => 'Já existe um arquivo com este nome para esta venda. Renomeie o arquivo e tente novamente.']);
    exit;
}



if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
    $lastError = error_get_last();
    file_put_contents(__DIR__ . '/../logs/upload_debug.log', date('Y-m-d H:i:s') . " ERRO move_uploaded_file: " . print_r($lastError, true) . "\n", FILE_APPEND);
    echo json_encode([
        'success' => false,
        'error' => 'Erro ao salvar arquivo.',
        'debug' => $lastError,
        'targetPath' => $targetPath,
        'baseDir' => $baseDir,
        'is_dir_base' => is_dir($baseDir),
        'perms_base' => substr(sprintf('%o', fileperms($baseDir)), -4)
    ]);
    exit;
}

try {
    $stmt = $pdo->prepare('INSERT INTO arquivos (id_venda, nome) VALUES (?, ?)');
    $stmt->execute([$id_venda, $relativePath]);
    echo json_encode([
        'success' => true,
        'relativePath' => $relativePath,
        'targetPath' => $targetPath,
        'baseDir' => $baseDir,
        'is_dir_base' => is_dir($baseDir),
        'perms_base' => substr(sprintf('%o', fileperms($baseDir)), -4)
    ]);
} catch (Exception $e) {
    @unlink($targetPath);
    file_put_contents(__DIR__ . '/../logs/upload_debug.log', date('Y-m-d H:i:s') . " ERRO PDO: " . $e->getMessage() . "\n", FILE_APPEND);
    echo json_encode(['success' => false, 'error' => 'Erro ao salvar no banco: ' . $e->getMessage()]);
}
