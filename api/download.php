<?php
// download.php - Serve arquivos PDF da pasta arquivos_venda de forma segura

// Caminho base da pasta de arquivos
$baseDir = __DIR__ . '/arquivos_venda/';

// Recebe o caminho do arquivo via GET
$file = isset($_GET['file']) ? $_GET['file'] : '';

// Sanitiza o caminho para evitar acesso indevido
$file = str_replace(['..', '\\'], '', $file);
$fullPath = realpath($baseDir . $file);

// Verifica se o arquivo existe e está dentro da pasta permitida
if (!$fullPath || strpos($fullPath, realpath($baseDir)) !== 0 || !is_file($fullPath)) {
    http_response_code(404);
    echo 'Arquivo não encontrado.';
    exit;
}

// Força download como PDF na web
header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="' . basename($fullPath) . '"');
header('Content-Length: ' . filesize($fullPath));
readfile($fullPath);
exit;
