<?php
require_once '../config/protect.php';
require_once '../config/config.php';

// Processar exclusão se solicitada
if (isset($_POST['action']) && $_POST['action'] === 'delete' && isset($_POST['id_venda'])) {
    $id_venda_delete = (int) $_POST['id_venda'];
    
    try {
        // Iniciar transação
        $pdo->beginTransaction();
        
        // Primeiro excluir os boletos relacionados à venda
        $sqlDeleteBoletos = "DELETE FROM boleto WHERE id_venda = ?";
        $stmtDeleteBoletos = $pdo->prepare($sqlDeleteBoletos);
        $stmtDeleteBoletos->execute([$id_venda_delete]);
        
        // Depois excluir a venda
        $sqlDeleteVenda = "DELETE FROM vendas WHERE id_venda = ?";
        $stmtDeleteVenda = $pdo->prepare($sqlDeleteVenda);
        $stmtDeleteVenda->execute([$id_venda_delete]);
        
        // Confirmar transação
        $pdo->commit();
        
        // Redirecionar com mensagem de sucesso
        header('Location: vendas_list.php?sucesso=Venda e boletos excluídos com sucesso');
        exit;
        
    } catch (Exception $e) {
        // Reverter transação em caso de erro
        $pdo->rollBack();
        header('Location: vendas_list.php?erro=Erro ao excluir venda: ' . $e->getMessage());
        exit;
    }
}

// Verificar se o ID foi passado
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: vendas_list.php');
    exit;
}

$id_venda = (int) $_GET['id'];

// Buscar dados da venda com vendedores e comissões
$sql = "SELECT v.*, 
               vd1.nome as nome_vendedor1, vd1.celular as celular1, vd1.tipochave as tipochave1, vd1.chave as chave1,
               vd2.nome as nome_vendedor2, vd2.celular as celular2, vd2.tipochave as tipochave2, vd2.chave as chave2,
               c1.valor as percentual_comissao1,
               c2.valor as percentual_comissao2
        FROM vendas v 
        LEFT JOIN vendedores vd1 ON v.id_vendedor = vd1.id_vendedor
        LEFT JOIN vendedores vd2 ON v.id_vendedor2 = vd2.id_vendedor
        LEFT JOIN comissao c1 ON v.id_comissao = c1.id_comissao
        LEFT JOIN comissao c2 ON v.id_comissao2 = c2.id_comissao
        WHERE v.id_venda = ?";

$stmt = $pdo->prepare($sql);
$stmt->execute([$id_venda]);
$venda = $stmt->fetch();

if (!$venda) {
    header('Location: vendas_list.php?erro=Venda não encontrada');
    exit;
}

// Buscar boletos da venda
$sqlBoletos = "SELECT b.*, v1.nome as nome_vendedor1, v2.nome as nome_vendedor2
               FROM boleto b
               LEFT JOIN vendedores v1 ON b.id_vendedor = v1.id_vendedor
               LEFT JOIN vendedores v2 ON b.id_vendedor2 = v2.id_vendedor
               WHERE b.id_venda = ?
               ORDER BY b.n_parcela";

$stmtBoletos = $pdo->prepare($sqlBoletos);
$stmtBoletos->execute([$id_venda]);
$boletos = $stmtBoletos->fetchAll();

// ATUALIZADA: Verificar se é pagamento à vista/integral com nova lógica
$formas_integrais = ['À Vista', 'PIX', 'Cartão de Débito', 'Dinheiro'];
$eh_pagamento_integral = in_array($venda['forma_pg'], $formas_integrais) || 
                        ($venda['qtd_parcelas'] == 0) ||
                        ($venda['vlr_entrada'] == $venda['vlr_total']);

// CORRIGIDA: Calcular valores baseado no tipo de pagamento
$valor_parcelado = 0;
$valor_entrada_real = 0;
$valor_comissao_total1 = 0;
$valor_comissao_total2 = 0;
$valor_comissao_entrada1 = 0;
$valor_comissao_entrada2 = 0;
$valor_comissao_parcelado1 = 0;
$valor_comissao_parcelado2 = 0;

if ($venda['qtd_parcelas'] == 0 || $eh_pagamento_integral) {
    // Para pagamentos à vista ou integrais, todo valor é considerado "entrada"
    $valor_entrada_real = $venda['vlr_total'];
    $valor_parcelado = 0;
} else {
    // Para pagamentos realmente parcelados
    $valor_entrada_real = $venda['vlr_entrada'];
    $valor_parcelado = $venda['vlr_total'] - $venda['vlr_entrada'];
}

// Calcular comissões para o Vendedor 1
if ($venda['percentual_comissao1']) {
    // Comissão total sobre o valor total da venda
    $valor_comissao_total1 = ($venda['vlr_total'] * $venda['percentual_comissao1']) / 100;
    
    // Comissão sobre a entrada (se houver)
    if ($valor_entrada_real > 0) {
        $valor_comissao_entrada1 = ($valor_entrada_real * $venda['percentual_comissao1']) / 100;
    }
    
    // Comissão sobre o valor parcelado (se houver)
    if ($valor_parcelado > 0) {
        $valor_comissao_parcelado1 = ($valor_parcelado * $venda['percentual_comissao1']) / 100;
    }
}

// Calcular comissões para o Vendedor 2
if ($venda['percentual_comissao2']) {
    // Comissão total sobre o valor total da venda
    $valor_comissao_total2 = ($venda['vlr_total'] * $venda['percentual_comissao2']) / 100;
    
    // Comissão sobre a entrada (se houver)
    if ($valor_entrada_real > 0) {
        $valor_comissao_entrada2 = ($valor_entrada_real * $venda['percentual_comissao2']) / 100;
    }
    
    // Comissão sobre o valor parcelado (se houver)
    if ($valor_parcelado > 0) {
        $valor_comissao_parcelado2 = ($valor_parcelado * $venda['percentual_comissao2']) / 100;
    }
}

// Totais de comissão
$valor_comissao_total_geral = $valor_comissao_total1 + $valor_comissao_total2;

// Determinar tipo de pagamento para exibição
$tipo_pagamento = '';
$tipo_pagamento_class = '';
if ($venda['qtd_parcelas'] == 0) {
    $tipo_pagamento = 'À Vista';
    $tipo_pagamento_class = 'success';
} elseif ($eh_pagamento_integral) {
    $tipo_pagamento = 'Integral';
    $tipo_pagamento_class = 'success';
} else {
    $tipo_pagamento = 'Parcelado';
    $tipo_pagamento_class = 'warning';
}

require_once '../views/header.php';
?>

<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Detalhes da Venda #<?= $venda['id_venda'] ?></h2>
        <a href="vendas_list.php" class="btn btn-secondary">← Voltar para Lista</a>
    </div>

    <!-- Informações da Venda -->
    <div class="row mb-4">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Informações da Venda</h5>
                </div>
                <div class="card-body">
                    <table class="table table-borderless">
                        <tr>
                            <td><strong>ID da Venda:</strong></td>
                            <td><?= $venda['id_venda'] ?></td>
                        </tr>
                        <tr>
                            <td><strong>Data da Venda:</strong></td>
                            <td><?= date('d/m/Y', strtotime($venda['dt_venda'])) ?></td>
                        </tr>
                        <tr>
                            <td><strong>Cliente:</strong></td>
                            <td><?= htmlspecialchars($venda['cliente']) ?></td>
                        </tr>
                        <tr>
                            <td><strong>Forma de Pagamento:</strong></td>
                            <td>
                                <span class="badge bg-<?= $tipo_pagamento_class ?>">
                                    <?= htmlspecialchars($venda['forma_pg']) ?>
                                </span>
                                <?php if ($venda['qtd_parcelas'] == 0): ?>
                                    <small class="text-success">(Sem Parcelas)</small>
                                <?php elseif ($eh_pagamento_integral): ?>
                                    <small class="text-success">(Pagamento Integral)</small>
                                <?php else: ?>
                                    <small class="text-warning">(Pagamento Parcelado)</small>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <td><strong>Quantidade de Parcelas:</strong></td>
                            <td>
                                <?php if ($venda['qtd_parcelas'] == 0): ?>
                                    <span class="badge bg-success">Pagamento à Vista</span>
                                    <small class="text-muted d-block">Sem parcelamento</small>
                                <?php else: ?>
                                    <span class="badge bg-<?= $tipo_pagamento_class ?>"><?= $venda['qtd_parcelas'] ?>x</span>
                                    <?php if ($venda['qtd_parcelas'] == 1): ?>
                                        <small class="text-muted d-block">Parcela única</small>
                                    <?php else: ?>
                                        <small class="text-muted d-block">Múltiplas parcelas</small>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <td><strong>Data dos Boletos:</strong></td>
                            <td>
                                <?php if ($venda['qtd_parcelas'] == 0): ?>
                                    <span class="text-muted">N/A (Pagamento à vista)</span>
                                <?php elseif ($venda['dt_boletos']): ?>
                                    <?= date('d/m/Y', strtotime($venda['dt_boletos'])) ?>
                                <?php else: ?>
                                    <span class="text-muted">N/A</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Informações dos Vendedores</h5>
                </div>
                <div class="card-body">
                    <!-- Vendedor Principal -->
                    <div class="border-bottom pb-3 mb-3">
                        <h6 class="text-primary"><i class="fas fa-user"></i> Vendedor Principal</h6>
                        <table class="table table-borderless table-sm">
                            <tr>
                                <td><strong>Nome:</strong></td>
                                <td><?= htmlspecialchars($venda['nome_vendedor1'] ?? 'N/A') ?></td>
                            </tr>
                            <tr>
                                <td><strong>Celular:</strong></td>
                                <td><?= htmlspecialchars($venda['celular1'] ?? 'N/A') ?></td>
                            </tr>
                            <tr>
                                <td><strong>Tipo de Chave:</strong></td>
                                <td><?= htmlspecialchars($venda['tipochave1'] ?? 'N/A') ?></td>
                            </tr>
                            <tr>
                                <td><strong>Chave PIX:</strong></td>
                                <td><?= htmlspecialchars($venda['chave1'] ?? 'N/A') ?></td>
                            </tr>
                            <tr>
                                <td><strong>Comissão:</strong></td>
                                <td>
                                    <?php if ($venda['percentual_comissao1']): ?>
                                        <span class="badge bg-primary"><?= $venda['percentual_comissao1'] ?>%</span>
                                    <?php else: ?>
                                        <span class="text-muted">N/A</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        </table>
                    </div>

                    <!-- Segundo Vendedor -->
                    <?php if ($venda['nome_vendedor2']): ?>
                        <div>
                            <h6 class="text-secondary"><i class="fas fa-user-plus"></i> Segundo Vendedor</h6>
                            <table class="table table-borderless table-sm">
                                <tr>
                                    <td><strong>Nome:</strong></td>
                                    <td><?= htmlspecialchars($venda['nome_vendedor2']) ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Celular:</strong></td>
                                    <td><?= htmlspecialchars($venda['celular2'] ?? 'N/A') ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Tipo de Chave:</strong></td>
                                    <td><?= htmlspecialchars($venda['tipochave2'] ?? 'N/A') ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Chave PIX:</strong></td>
                                    <td><?= htmlspecialchars($venda['chave2'] ?? 'N/A') ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Comissão:</strong></td>
                                    <td>
                                        <?php if ($venda['percentual_comissao2']): ?>
                                            <span class="badge bg-secondary"><?= $venda['percentual_comissao2'] ?>%</span>
                                        <?php else: ?>
                                            <span class="text-muted">N/A</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center text-muted">
                            <i class="fas fa-user-slash fa-2x mb-2"></i>
                            <p>Apenas um vendedor nesta venda</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Resumo Financeiro -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Resumo Financeiro</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-2 text-center">
                            <h6>Valor Total</h6>
                            <h4 class="text-primary">R$ <?= number_format($venda['vlr_total'], 2, ',', '.') ?></h4>
                        </div>
                        
                        <?php if ($venda['qtd_parcelas'] == 0 || $eh_pagamento_integral): ?>
                            <!-- Pagamento à Vista - Layout Simplificado -->
                            <div class="col-md-5 text-center">
                                <h6>Comissão Total</h6>
                                <div class="row">
                                    <?php if ($valor_comissao_total1 > 0): ?>
                                        <div class="col">
                                            <h5 class="text-primary">R$ <?= number_format($valor_comissao_total1, 2, ',', '.') ?></h5>
                                            <small class="text-primary">Vendedor 1 (<?= $venda['percentual_comissao1'] ?>%)</small>
                                        </div>
                                    <?php endif; ?>
                                    <?php if ($valor_comissao_total2 > 0): ?>
                                        <div class="col">
                                            <h5 class="text-secondary">R$ <?= number_format($valor_comissao_total2, 2, ',', '.') ?></h5>
                                            <small class="text-secondary">Vendedor 2 (<?= $venda['percentual_comissao2'] ?>%)</small>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <small class="text-success">Pagar na data da venda</small>
                            </div>
                            <div class="col-md-2 text-center">
                                <h6>Total Geral</h6>
                                <h4 class="text-info">R$ <?= number_format($valor_comissao_total_geral, 2, ',', '.') ?></h4>
                            </div>
                            <div class="col-md-3 text-center">
                                <h6>Status do Pagamento</h6>
                                <?php if ($venda['qtd_parcelas'] == 0): ?>
                                    <h5><span class="badge bg-success">À Vista</span></h5>
                                    <small class="text-success">Sem parcelas - Comissões liberadas</small>
                                <?php else: ?>
                                    <h5><span class="badge bg-success">Integral</span></h5>
                                    <small class="text-success">Comissões liberadas</small>
                                <?php endif; ?>
                            </div>
                        <?php else: ?>
                            <!-- Pagamento Parcelado - Layout Completo -->
                            <div class="col-md-2 text-center">
                                <h6>Entrada</h6>
                                <h4 class="text-success">R$ <?= number_format($valor_entrada_real, 2, ',', '.') ?></h4>
                                <div class="row">
                                    <?php if ($valor_comissao_entrada1 > 0): ?>
                                        <div class="col-12">
                                            <small class="text-primary">V1: R$ <?= number_format($valor_comissao_entrada1, 2, ',', '.') ?></small>
                                        </div>
                                    <?php endif; ?>
                                    <?php if ($valor_comissao_entrada2 > 0): ?>
                                        <div class="col-12">
                                            <small class="text-secondary">V2: R$ <?= number_format($valor_comissao_entrada2, 2, ',', '.') ?></small>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="col-md-2 text-center">
                                <h6>Valor Parcelado</h6>
                                <h4 class="text-warning">R$ <?= number_format($valor_parcelado, 2, ',', '.') ?></h4>
                                <div class="row">
                                    <?php if ($valor_comissao_parcelado1 > 0): ?>
                                        <div class="col-12">
                                            <small class="text-primary">V1: R$ <?= number_format($valor_comissao_parcelado1, 2, ',', '.') ?></small>
                                        </div>
                                    <?php endif; ?>
                                    <?php if ($valor_comissao_parcelado2 > 0): ?>
                                        <div class="col-12">
                                            <small class="text-secondary">V2: R$ <?= number_format($valor_comissao_parcelado2, 2, ',', '.') ?></small>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="col-md-2 text-center">
                                <h6>Comissão Total</h6>
                                <h4 class="text-info">R$ <?= number_format($valor_comissao_total_geral, 2, ',', '.') ?></h4>
                                <small class="text-warning">Pagar conforme vencimentos</small>
                            </div>
                            <div class="col-md-4 text-center">
                                <h6>Status do Pagamento</h6>
                                <h5><span class="badge bg-warning text-dark">Parcelado</span></h5>
                                <small class="text-warning">Comissões por vencimento</small>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Detalhamento das Comissões -->
    <?php if ($venda['percentual_comissao1'] || $venda['percentual_comissao2']): ?>
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Detalhamento das Comissões</h5>
                </div>
                <div class="card-body">
                    <?php if ($venda['qtd_parcelas'] == 0): ?>
                        <div class="alert alert-success">
                            <h6><i class="fas fa-money-bill-wave"></i> Pagamento à Vista</h6>
                            <div class="row">
                                <?php if ($valor_comissao_total1 > 0): ?>
                                    <div class="col-md-6">
                                        <p><strong><?= htmlspecialchars($venda['nome_vendedor1']) ?> (<?= $venda['percentual_comissao1'] ?>%):</strong></p>
                                        <p>Comissão de <strong>R$ <?= number_format($valor_comissao_total1, 2, ',', '.') ?></strong></p>
                                        <small class="text-muted">Pagar na data da venda: <strong><?= date('d/m/Y', strtotime($venda['dt_venda'])) ?></strong></small>
                                    </div>
                                <?php endif; ?>
                                <?php if ($valor_comissao_total2 > 0): ?>
                                    <div class="col-md-6">
                                        <p><strong><?= htmlspecialchars($venda['nome_vendedor2']) ?> (<?= $venda['percentual_comissao2'] ?>%):</strong></p>
                                        <p>Comissão de <strong>R$ <?= number_format($valor_comissao_total2, 2, ',', '.') ?></strong></p>
                                        <small class="text-muted">Pagar na data da venda: <strong><?= date('d/m/Y', strtotime($venda['dt_venda'])) ?></strong></small>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <small class="text-muted">Esta venda não possui parcelas (qtd_parcelas = 0)</small>
                        </div>
                    <?php elseif ($eh_pagamento_integral): ?>
                        <div class="alert alert-success">
                            <h6><i class="fas fa-money-bill-wave"></i> Pagamento Integral</h6>
                            <div class="row">
                                <?php if ($valor_comissao_total1 > 0): ?>
                                    <div class="col-md-6">
                                        <p><strong><?= htmlspecialchars($venda['nome_vendedor1']) ?> (<?= $venda['percentual_comissao1'] ?>%):</strong></p>
                                        <p>Comissão de <strong>R$ <?= number_format($valor_comissao_total1, 2, ',', '.') ?></strong></p>
                                        <small class="text-muted">Pagar na data da venda: <strong><?= date('d/m/Y', strtotime($venda['dt_venda'])) ?></strong></small>
                                    </div>
                                <?php endif; ?>
                                <?php if ($valor_comissao_total2 > 0): ?>
                                    <div class="col-md-6">
                                        <p><strong><?= htmlspecialchars($venda['nome_vendedor2']) ?> (<?= $venda['percentual_comissao2'] ?>%):</strong></p>
                                        <p>Comissão de <strong>R$ <?= number_format($valor_comissao_total2, 2, ',', '.') ?></strong></p>
                                        <small class="text-muted">Pagar na data da venda: <strong><?= date('d/m/Y', strtotime($venda['dt_venda'])) ?></strong></small>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info">
                            <h6><i class="fas fa-calendar-alt"></i> Pagamento Parcelado</h6>
                            <div class="row">
                                <div class="col-md-6">
                                    <h6>Comissões da Entrada:</h6>
                                    <?php if ($valor_comissao_entrada1 > 0): ?>
                                        <p><strong><?= htmlspecialchars($venda['nome_vendedor1']) ?>:</strong> 
                                        <span class="text-primary">R$ <?= number_format($valor_comissao_entrada1, 2, ',', '.') ?></span></p>
                                    <?php endif; ?>
                                    <?php if ($valor_comissao_entrada2 > 0): ?>
                                        <p><strong><?= htmlspecialchars($venda['nome_vendedor2']) ?>:</strong> 
                                        <span class="text-secondary">R$ <?= number_format($valor_comissao_entrada2, 2, ',', '.') ?></span></p>
                                    <?php endif; ?>
                                    <small class="text-muted">Pagar na data da venda: <?= date('d/m/Y', strtotime($venda['dt_venda'])) ?></small>
                                </div>
                                <div class="col-md-6">
                                    <h6>Comissões das Parcelas:</h6>
                                    <?php if ($valor_comissao_parcelado1 > 0): ?>
                                        <p><strong><?= htmlspecialchars($venda['nome_vendedor1']) ?>:</strong> 
                                        <span class="text-primary">R$ <?= number_format($valor_comissao_parcelado1, 2, ',', '.') ?></span></p>
                                    <?php endif; ?>
                                    <?php if ($valor_comissao_parcelado2 > 0): ?>
                                        <p><strong><?= htmlspecialchars($venda['nome_vendedor2']) ?>:</strong> 
                                        <span class="text-secondary">R$ <?= number_format($valor_comissao_parcelado2, 2, ',', '.') ?></span></p>
                                    <?php endif; ?>
                                    <small class="text-muted">Pagar conforme recebimento dos boletos</small>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Boletos/Parcelas -->
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">
                <?php if ($venda['qtd_parcelas'] == 0): ?>
                    Informações de Pagamento
                <?php else: ?>
                    Boletos/Parcelas
                <?php endif; ?>
            </h5>
            <?php if ($venda['qtd_parcelas'] > 0): ?>
                <a href="boletos_list.php?id_venda=<?= $venda['id_venda'] ?>" class="btn btn-sm btn-primary">Gerenciar Boletos</a>
            <?php endif; ?>
        </div>
        <div class="card-body">
            <?php if ($venda['qtd_parcelas'] == 0): ?>
                <div class="alert alert-success">
                    <h6><i class="fas fa-check-circle"></i> Pagamento à Vista</h6>
                    <p class="mb-2">Esta venda foi configurada para pagamento à vista com as seguintes características:</p>
                    <ul class="mb-2">
                        <li><strong>Forma de Pagamento:</strong> <?= htmlspecialchars($venda['forma_pg']) ?></li>
                        <li><strong>Valor Total:</strong> R$ <?= number_format($venda['vlr_total'], 2, ',', '.') ?></li>
                        <li><strong>Parcelas:</strong> 0 (sem parcelamento)</li>
                        <?php if ($valor_comissao_total_geral > 0): ?>
                            <li><strong>Comissão Total:</strong> R$ <?= number_format($valor_comissao_total_geral, 2, ',', '.') ?></li>
                        <?php endif; ?>
                    </ul>
                    <p class="mb-0 text-success"><i class="fas fa-info-circle"></i> <strong>Não há boletos para gerenciar</strong> - pagamento realizado integralmente.</p>
                </div>
            <?php elseif (count($boletos) === 0): ?>
                <div class="alert alert-warning">
                    <h6><i class="fas fa-exclamation-triangle"></i> Nenhum boleto encontrado</h6>
                    <?php if ($eh_pagamento_integral): ?>
                        <p class="mb-0">Esta venda foi paga integralmente (<?= $venda['forma_pg'] ?>). Não há boletos para gerenciar.</p>
                    <?php else: ?>
                        <p class="mb-0">Nenhum boleto foi gerado para esta venda parcelada. 
                        <a href="boletos_list.php?id_venda=<?= $venda['id_venda'] ?>" class="alert-link">Clique aqui para gerenciar boletos</a>.</p>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="alert alert-info mb-3">
                    <i class="fas fa-info-circle"></i> 
                    Esta venda possui <strong><?= count($boletos) ?> boleto(s)</strong> 
                    <?php if ($venda['qtd_parcelas'] == 1): ?>
                        (parcela única)
                    <?php else: ?>
                        (<?= $venda['qtd_parcelas'] ?> parcelas)
                    <?php endif; ?>
                </div>
                
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Parcela</th>
                                <th>Valor</th>
                                <th>Data Vencimento</th>
                                <th>Status</th>
                                <th>Comissão V1</th>
                                <th>Comissão V2</th>
                                <th>Total Comissão</th>
                                <th>Status Comissão</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($boletos as $boleto): ?>
                                <?php
                                // Comissão baseada no valor da parcela (já calculada nos campos do boleto)
                                $valor_comissao_parcela1 = $boleto['valor_comissao1'] ?? 0;
                                $valor_comissao_parcela2 = $boleto['valor_comissao2'] ?? 0;
                                $valor_comissao_parcela_total = $valor_comissao_parcela1 + $valor_comissao_parcela2;
                                
                                // Se não há valores calculados nos boletos, calcular baseado nos percentuais
                                if ($valor_comissao_parcela1 == 0 && $venda['percentual_comissao1']) {
                                    $valor_comissao_parcela1 = ($boleto['valor'] * $venda['percentual_comissao1']) / 100;
                                }
                                if ($valor_comissao_parcela2 == 0 && $venda['percentual_comissao2']) {
                                    $valor_comissao_parcela2 = ($boleto['valor'] * $venda['percentual_comissao2']) / 100;
                                }
                                $valor_comissao_parcela_total = $valor_comissao_parcela1 + $valor_comissao_parcela2;
                                
                                // Definir classe CSS baseada no status
                                $status_class = '';
                                $status_comissao = '';
                                $status_comissao_class = '';
                                
                                switch($boleto['status']) {
                                    case 'Pago':
                                        $status_class = 'badge bg-success';
                                        $status_comissao = 'Liberar';
                                        $status_comissao_class = 'badge bg-success';
                                        break;
                                    case 'Vencido':
                                        $status_class = 'badge bg-danger';
                                        $status_comissao = 'Aguardar';
                                        $status_comissao_class = 'badge bg-danger';
                                        break;
                                    case 'A Vencer':
                                    default:
                                        $status_class = 'badge bg-warning text-dark';
                                        $status_comissao = 'Aguardar';
                                        $status_comissao_class = 'badge bg-warning text-dark';
                                        break;
                                }
                                ?>
                                <tr>
                                    <td>
                                        <?= $boleto['n_parcela'] ?>/<?= $boleto['qtd_parcelas'] ?>
                                        <?php if ($boleto['qtd_parcelas'] == 1): ?>
                                            <small class="text-muted d-block">Única</small>
                                        <?php endif; ?>
                                    </td>
                                    <td>R$ <?= number_format($boleto['valor'], 2, ',', '.') ?></td>
                                    <td><?= date('d/m/Y', strtotime($boleto['dt_vencimento'])) ?></td>
                                    <td><span class="<?= $status_class ?>"><?= htmlspecialchars($boleto['status']) ?></span></td>
                                    <td>
                                        <?php if ($valor_comissao_parcela1 > 0): ?>
                                            R$ <?= number_format($valor_comissao_parcela1, 2, ',', '.') ?>
                                            <?php if ($venda['percentual_comissao1']): ?>
                                                <small class="text-muted d-block">(<?= $venda['percentual_comissao1'] ?>%)</small>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="text-muted">N/A</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($valor_comissao_parcela2 > 0): ?>
                                            R$ <?= number_format($valor_comissao_parcela2, 2, ',', '.') ?>
                                            <?php if ($venda['percentual_comissao2']): ?>
                                                <small class="text-muted d-block">(<?= $venda['percentual_comissao2'] ?>%)</small>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="text-muted">N/A</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($valor_comissao_parcela_total > 0): ?>
                                            <strong>R$ <?= number_format($valor_comissao_parcela_total, 2, ',', '.') ?></strong>
                                        <?php else: ?>
                                            <span class="text-muted">N/A</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><span class="<?= $status_comissao_class ?>"><?= $status_comissao ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr class="table-secondary">
                                <td><strong>Total:</strong></td>
                                <td><strong>R$ <?= number_format(array_sum(array_column($boletos, 'valor')), 2, ',', '.') ?></strong></td>
                                <td colspan="2"></td>
                                <td>
                                    <?php if ($valor_comissao_parcelado1 > 0): ?>
                                        <strong>R$ <?= number_format($valor_comissao_parcelado1, 2, ',', '.') ?></strong>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($valor_comissao_parcelado2 > 0): ?>
                                        <strong>R$ <?= number_format($valor_comissao_parcelado2, 2, ',', '.') ?></strong>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <strong>R$ <?= number_format($valor_comissao_parcelado1 + $valor_comissao_parcelado2, 2, ',', '.') ?></strong>
                                </td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Ações -->
    <div class="mt-4 text-center">
        <a href="vendas_form.php?id=<?= $venda['id_venda'] ?>" class="btn btn-primary">
            <i class="fas fa-edit"></i> Editar Venda
        </a>
        <?php if ($venda['qtd_parcelas'] > 0 && count($boletos) > 0): ?>
            <a href="boletos_list.php?id_venda=<?= $venda['id_venda'] ?>" class="btn btn-warning">
                <i class="fas fa-file-invoice"></i> Gerenciar Boletos
            </a>
        <?php endif; ?>
        <button type="button" class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#deleteModal">
            <i class="fas fa-trash"></i> Excluir Venda
        </button>
        <a href="vendas_list.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Voltar para Lista
        </a>
    </div>
</div>

<!-- Modal de Confirmação de Exclusão -->
<div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deleteModalLabel">Confirmar Exclusão</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-danger">
                    <strong>⚠️ ATENÇÃO!</strong> Esta ação não pode ser desfeita.
                </div>
                <p>Você tem certeza que deseja excluir esta venda?</p>
                <p><strong>Esta ação irá:</strong></p>
                <ul>
                    <li>Excluir permanentemente a venda #<?= $venda['id_venda'] ?></li>
                    <?php if (count($boletos) > 0): ?>
                        <li>Excluir todos os <?= count($boletos) ?> boleto(s) relacionado(s)</li>
                    <?php endif; ?>
                    <li>Remover todos os dados financeiros associados</li>
                    <?php if ($valor_comissao_total_geral > 0): ?>
                        <li>Cancelar comissões de R$ <?= number_format($valor_comissao_total_geral, 2, ',', '.') ?></li>
                    <?php endif; ?>
                </ul>
                <p><strong>Dados da venda:</strong></p>
                <ul>
                    <li><strong>Cliente:</strong> <?= htmlspecialchars($venda['cliente']) ?></li>
                    <li><strong>Valor Total:</strong> R$ <?= number_format($venda['vlr_total'], 2, ',', '.') ?></li>
                    <li><strong>Data:</strong> <?= date('d/m/Y', strtotime($venda['dt_venda'])) ?></li>
                    <li><strong>Vendedores:</strong> 
                        <?= htmlspecialchars($venda['nome_vendedor1']) ?>
                        <?php if ($venda['nome_vendedor2']): ?>
                            + <?= htmlspecialchars($venda['nome_vendedor2']) ?>
                        <?php endif; ?>
                    </li>
                    <li><strong>Tipo:</strong> <?= $venda['qtd_parcelas'] == 0 ? 'Pagamento à Vista' : ($eh_pagamento_integral ? 'Pagamento Integral' : 'Pagamento Parcelado') ?></li>
                </ul>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <form method="POST" style="display: inline;" onsubmit="return confirm('Tem certeza absoluta? Esta ação é irreversível!');">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id_venda" value="<?= $venda['id_venda'] ?>">
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-trash"></i> Sim, Excluir Definitivamente
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
// Adicionar confirmação extra ao JavaScript
document.addEventListener('DOMContentLoaded', function() {
    const deleteForm = document.querySelector('form[onsubmit*="confirm"]');
    if (deleteForm) {
        deleteForm.addEventListener('submit', function(e) {
            const confirmed = confirm('⚠️ ÚLTIMA CONFIRMAÇÃO: Você tem ABSOLUTA CERTEZA que deseja excluir esta venda e todos os boletos relacionados? Esta ação é IRREVERSÍVEL!');
            if (!confirmed) {
                e.preventDefault();
                return false;
            }
        });
    }
});
</script>

<?php require_once '../views/footer.php'; ?>