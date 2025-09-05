<?php
// api/arquivos_list.php
require_once '../config/protect.php';
require_once '../config/config.php';

header('Content-Type: application/json');

$id_venda = isset($_GET['id_venda']) ? (int)$_GET['id_venda'] : 0;
if (!$id_venda) {
    echo json_encode([]);
    exit;
}


$stmt = $pdo->prepare('SELECT id_arquivo, nome FROM arquivos WHERE id_venda = ? ORDER BY id_arquivo DESC');
$stmt->execute([$id_venda]);
$arquivos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Ajustar caminho para exibir corretamente no front
foreach ($arquivos as &$arq) {
    // Se já começa com arquivos_venda/, mantém
    if (strpos($arq['nome'], 'arquivos_venda/') !== 0) {
        $arq['nome'] = 'arquivos_venda/' . $id_venda . '/' . ltrim($arq['nome'], '/');
    }
}
unset($arq);

echo json_encode($arquivos);
