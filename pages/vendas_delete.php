<?php
require_once '../config/protect.php';
require_once '../config/config.php';

// Verificar se é uma requisição POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: vendas_list.php?erro=Método não permitido');
    exit;
}

// Verificar se o ID da venda foi fornecido
if (!isset($_POST['id_venda']) || !is_numeric($_POST['id_venda'])) {
    header('Location: vendas_list.php?erro=ID da venda inválido');
    exit;
}

$id_venda = (int) $_POST['id_venda'];

try {
    // Verificar se a venda existe antes de tentar excluir
    $sqlCheck = "SELECT id_venda, cliente, vlr_total FROM vendas WHERE id_venda = ?";
    $stmtCheck = $pdo->prepare($sqlCheck);
    $stmtCheck->execute([$id_venda]);
    $venda = $stmtCheck->fetch();
    
    if (!$venda) {
        header('Location: vendas_list.php?erro=Venda não encontrada');
        exit;
    }
    
    // Contar quantos boletos serão excluídos
    $sqlCountBoletos = "SELECT COUNT(*) as total FROM boleto WHERE id_venda = ?";
    $stmtCountBoletos = $pdo->prepare($sqlCountBoletos);
    $stmtCountBoletos->execute([$id_venda]);
    $countBoletos = $stmtCountBoletos->fetch()['total'];
    
    // Iniciar transação para garantir integridade dos dados
    $pdo->beginTransaction();
    
    // Primeiro excluir os boletos relacionados à venda
    $sqlDeleteBoletos = "DELETE FROM boleto WHERE id_venda = ?";
    $stmtDeleteBoletos = $pdo->prepare($sqlDeleteBoletos);
    $boletosExcluidos = $stmtDeleteBoletos->execute([$id_venda]);
    
    if (!$boletosExcluidos) {
        throw new Exception("Erro ao excluir boletos da venda");
    }
    
    // Depois excluir a venda
    $sqlDeleteVenda = "DELETE FROM vendas WHERE id_venda = ?";
    $stmtDeleteVenda = $pdo->prepare($sqlDeleteVenda);
    $vendaExcluida = $stmtDeleteVenda->execute([$id_venda]);
    
    if (!$vendaExcluida) {
        throw new Exception("Erro ao excluir a venda");
    }
    
    // Verificar se realmente foi excluída
    if ($stmtDeleteVenda->rowCount() === 0) {
        throw new Exception("Nenhuma venda foi excluída. Verifique se o ID está correto");
    }
    
    // Confirmar transação
    $pdo->commit();
    
    // Log da exclusão (opcional - pode ser implementado se necessário)
    // error_log("Venda excluída - ID: {$id_venda}, Cliente: {$venda['cliente']}, Boletos: {$countBoletos}");
    
    // Redirecionar com mensagem de sucesso
    $mensagem = "Venda #{$id_venda} e {$countBoletos} boleto(s) excluídos com sucesso";
    header('Location: vendas_list.php?sucesso=' . urlencode($mensagem));
    exit;
    
} catch (Exception $e) {
    // Reverter transação em caso de erro
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    // Log do erro
    error_log("Erro ao excluir venda ID {$id_venda}: " . $e->getMessage());
    
    // Redirecionar com mensagem de erro
    header('Location: vendas_list.php?erro=' . urlencode('Erro ao excluir venda: ' . $e->getMessage()));
    exit;
}
?>