<?php
/**
 * SOLUÇÃO 1: Script de Automação (Recomendado)
 * Arquivo: /scripts/atualizar_status_boletos.php
 */

// Script para atualizar automaticamente status dos boletos vencidos
require_once '../config/config.php';

function atualizarStatusBoletos($pdo) {
    try {
        // Log da execução
        $log = date('Y-m-d H:i:s') . " - Iniciando atualização de status dos boletos\n";
        
        // Query para atualizar boletos vencidos
        $sql = "UPDATE boleto 
                SET status = 'Vencido' 
                WHERE dt_vencimento < CURDATE() 
                AND status = 'A Vencer'";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        
        $boletosAtualizados = $stmt->rowCount();
        
        $log .= date('Y-m-d H:i:s') . " - {$boletosAtualizados} boletos atualizados para 'Vencido'\n";
        
        // Salvar log
        file_put_contents('../logs/status_boletos.log', $log, FILE_APPEND | LOCK_EX);
        
        return [
            'sucesso' => true,
            'boletos_atualizados' => $boletosAtualizados,
            'mensagem' => "Status atualizado com sucesso! {$boletosAtualizados} boletos foram marcados como vencidos."
        ];
        
    } catch (Exception $e) {
        $erro = date('Y-m-d H:i:s') . " - ERRO: " . $e->getMessage() . "\n";
        file_put_contents('../logs/status_boletos.log', $erro, FILE_APPEND | LOCK_EX);
        
        return [
            'sucesso' => false,
            'erro' => $e->getMessage()
        ];
    }
}

// Se chamado diretamente (via cron ou URL)
if (basename(__FILE__) == basename($_SERVER['SCRIPT_NAME'])) {
    $resultado = atualizarStatusBoletos($pdo);
    
    if ($resultado['sucesso']) {
        echo "✅ " . $resultado['mensagem'];
    } else {
        echo "❌ Erro: " . $resultado['erro'];
    }
}
?>