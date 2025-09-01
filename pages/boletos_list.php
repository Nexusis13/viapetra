<?php
require_once '../config/protect.php';
require_once '../config/config.php';

// Verificar se o ID da venda foi passado
if (!isset($_GET['id_venda']) || !is_numeric($_GET['id_venda'])) {
    header('Location: vendas_list.php');
    exit;
}

$id_venda = (int) $_GET['id_venda'];

// Buscar informações da venda
$sqlVenda = "SELECT v.*, vd.nome as nome_vendedor, c.valor as percentual_comissao 
             FROM vendas v 
             LEFT JOIN vendedores vd ON v.id_vendedor = vd.id_vendedor
             LEFT JOIN comissao c ON v.id_comissao = c.id_comissao
             WHERE v.id_venda = ?";

$stmtVenda = $pdo->prepare($sqlVenda);
$stmtVenda->execute([$id_venda]);
$venda = $stmtVenda->fetch();

if (!$venda) {
    header('Location: vendas_list.php?erro=Venda não encontrada');
    exit;
}

// Processar alteração de status se enviado via POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id_boleto']) && isset($_POST['novo_status'])) {
    $id_boleto = (int) $_POST['id_boleto'];
    $novo_status = $_POST['novo_status'];
    
    // Buscar o status atual do boleto
    $sqlStatusAtual = "SELECT status FROM boleto WHERE id_boleto = ? AND id_venda = ?";
    $stmtStatusAtual = $pdo->prepare($sqlStatusAtual);
    $stmtStatusAtual->execute([$id_boleto, $id_venda]);
    $statusAtual = $stmtStatusAtual->fetchColumn();
    
    // Validar se pode alterar o status (não permitir alterar se já estiver pago)
    if ($statusAtual === 'Pago') {
        header("Location: boletos_list.php?id_venda=$id_venda&erro=Não é possível alterar o status de um boleto já pago");
        exit;
    }
    
    // Validar status
    $status_validos = ['A Vencer', 'Pago', 'Vencido'];
    if (in_array($novo_status, $status_validos)) {
        $sqlUpdate = "UPDATE boleto SET status = ? WHERE id_boleto = ? AND id_venda = ?";
        $stmtUpdate = $pdo->prepare($sqlUpdate);
        $stmtUpdate->execute([$novo_status, $id_boleto, $id_venda]);
        
        // Redirecionar para evitar reenvio do formulário
        header("Location: boletos_list.php?id_venda=$id_venda&sucesso=Status atualizado com sucesso");
        exit;
    }
}

// Filtros de busca
$filtros = ["b.id_venda = ?"];
$valores = [$id_venda];

if (!empty($_GET['status'])) {
    $filtros[] = 'b.status = ?';
    $valores[] = $_GET['status'];
}

if (!empty($_GET['data_vencimento_inicio'])) {
    $filtros[] = 'b.dt_vencimento >= ?';
    $valores[] = $_GET['data_vencimento_inicio'];
}

if (!empty($_GET['data_vencimento_fim'])) {
    $filtros[] = 'b.dt_vencimento <= ?';
    $valores[] = $_GET['data_vencimento_fim'];
}

// Buscar boletos com filtros
$sql = "SELECT b.*, vd.nome as nome_vendedor, v.cliente, v.percentual_comissao
        FROM boleto b
        LEFT JOIN vendedores vd ON b.id_vendedor = vd.id_vendedor
        LEFT JOIN (
            SELECT vd2.*, c.valor as percentual_comissao 
            FROM vendas vd2 
            LEFT JOIN comissao c ON vd2.id_comissao = c.id_comissao
        ) v ON b.id_venda = v.id_venda";

if ($filtros) {
    $sql .= " WHERE " . implode(' AND ', $filtros);
}
$sql .= " ORDER BY b.n_parcela ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($valores);
$boletos = $stmt->fetchAll();

// Calcular totais
$total_boletos = count($boletos);
$total_valor = array_sum(array_column($boletos, 'valor'));
$boletos_pagos = count(array_filter($boletos, function($b) { return $b['status'] === 'Pago'; }));
$boletos_vencidos = count(array_filter($boletos, function($b) { return $b['status'] === 'Vencido'; }));
$boletos_a_vencer = count(array_filter($boletos, function($b) { return $b['status'] === 'A Vencer'; }));

$valor_pago = array_sum(array_map(function($b) { return $b['status'] === 'Pago' ? $b['valor'] : 0; }, $boletos));
$valor_pendente = $total_valor - $valor_pago;

// Calcular comissão total
$valor_comissao_total = 0;
if ($venda['percentual_comissao']) {
    $valor_comissao_total = ($total_valor * $venda['percentual_comissao']) / 100;
}

require_once '../views/header.php';
?>

<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Boletos - Venda #<?= $venda['id_venda'] ?></h2>
        <div>
            <a href="vendas_view.php?id=<?= $venda['id_venda'] ?>" class="btn btn-info">Ver Venda</a>
            <a href="vendas_list.php" class="btn btn-secondary">← Voltar para Vendas</a>
        </div>
    </div>

    <!-- Informações da Venda -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3">
                            <strong>Cliente:</strong><br>
                            <?= htmlspecialchars($venda['cliente']) ?>
                        </div>
                        <div class="col-md-3">
                            <strong>Vendedor:</strong><br>
                            <?= htmlspecialchars($venda['nome_vendedor']) ?>
                        </div>
                        <div class="col-md-2">
                            <strong>Data Venda:</strong><br>
                            <?= date('d/m/Y', strtotime($venda['dt_venda'])) ?>
                        </div>
                        <div class="col-md-2">
                            <strong>Valor Total:</strong><br>
                            R$ <?= number_format($venda['vlr_total'], 2, ',', '.') ?>
                        </div>
                        <div class="col-md-2">
                            <strong>Comissão:</strong><br>
                            <?= $venda['percentual_comissao'] ? $venda['percentual_comissao'] . '%' : 'N/A' ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Resumo dos Boletos -->
    <div class="row mb-4">
        <div class="col-md-2">
            <div class="card text-center">
                <div class="card-body">
                    <h5 class="card-title text-primary"><?= $total_boletos ?></h5>
                    <p class="card-text">Total Boletos</p>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card text-center">
                <div class="card-body">
                    <h5 class="card-title text-success"><?= $boletos_pagos ?></h5>
                    <p class="card-text">Pagos</p>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card text-center">
                <div class="card-body">
                    <h5 class="card-title text-warning"><?= $boletos_a_vencer ?></h5>
                    <p class="card-text">A Vencer</p>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card text-center">
                <div class="card-body">
                    <h5 class="card-title text-danger"><?= $boletos_vencidos ?></h5>
                    <p class="card-text">Vencidos</p>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card text-center">
                <div class="card-body">
                    <h5 class="card-title text-success">R$ <?= number_format($valor_pago, 2, ',', '.') ?></h5>
                    <p class="card-text">Valor Pago</p>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card text-center">
                <div class="card-body">
                    <h5 class="card-title text-info">R$ <?= number_format($valor_comissao_total, 2, ',', '.') ?></h5>
                    <p class="card-text">Comissão Total</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Filtros -->
    <form method="get" class="row g-3 mb-4">
        <input type="hidden" name="id_venda" value="<?= $id_venda ?>">
        <div class="col-md-3">
            <label class="form-label">Status</label>
            <select name="status" class="form-control">
                <option value="">Todos os status</option>
                <option value="A Vencer" <?= ($_GET['status'] ?? '') === 'A Vencer' ? 'selected' : '' ?>>A Vencer</option>
                <option value="Pago" <?= ($_GET['status'] ?? '') === 'Pago' ? 'selected' : '' ?>>Pago</option>
                <option value="Vencido" <?= ($_GET['status'] ?? '') === 'Vencido' ? 'selected' : '' ?>>Vencido</option>
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label">Vencimento Início</label>
            <input type="date" name="data_vencimento_inicio" class="form-control" value="<?= htmlspecialchars($_GET['data_vencimento_inicio'] ?? '') ?>">
        </div>
        <div class="col-md-3">
            <label class="form-label">Vencimento Fim</label>
            <input type="date" name="data_vencimento_fim" class="form-control" value="<?= htmlspecialchars($_GET['data_vencimento_fim'] ?? '') ?>">
        </div>
        <div class="col-md-3 d-flex align-items-end">
            <button type="submit" class="btn btn-primary me-2">Filtrar</button>
            <a href="boletos_list.php?id_venda=<?= $id_venda ?>" class="btn btn-secondary">Limpar</a>
        </div>
    </form>

    <!-- Mensagens -->
    <?php if (isset($_GET['sucesso'])): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <?= htmlspecialchars($_GET['sucesso']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if (isset($_GET['erro'])): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <?= htmlspecialchars($_GET['erro']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Tabela de Boletos -->
    <?php if (count($boletos) === 0): ?>
        <div class="alert alert-warning">Nenhum boleto encontrado para esta venda.</div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>Parcela</th>
                        <th>Valor</th>
                        <th>Vencimento</th>
                        <th>Status</th>
                        <th>Dias para Vencimento</th>
                        <th>Comissão</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($boletos as $boleto): ?>
                        <?php
                        // Calcular dias para vencimento apenas se não estiver pago
                        $dias_para_vencimento_texto = '-';
                        $dias_class = '';
                        
                        if ($boleto['status'] !== 'Pago') {
                            $data_vencimento = new DateTime($boleto['dt_vencimento']);
                            $data_atual = new DateTime();
                            $dias_para_vencimento = $data_atual->diff($data_vencimento)->format('%r%a');
                            
                            if ($dias_para_vencimento < 0) {
                                $dias_para_vencimento_texto = abs($dias_para_vencimento) . ' dias vencido';
                                $dias_class = 'text-danger fw-bold';
                            } elseif ($dias_para_vencimento == 0) {
                                $dias_para_vencimento_texto = 'Vence hoje';
                                $dias_class = 'text-warning fw-bold';
                            } else {
                                $dias_para_vencimento_texto = $dias_para_vencimento . ' dias';
                                if ($dias_para_vencimento <= 7) {
                                    $dias_class = 'text-warning fw-bold';
                                }
                            }
                        }
                        
                        // Calcular comissão da parcela
                        $valor_comissao_parcela = 0;
                        if ($venda['percentual_comissao']) {
                            $valor_comissao_parcela = ($boleto['valor'] * $venda['percentual_comissao']) / 100;
                        }
                        
                        // Definir classes CSS baseadas no status
                        $status_class = '';
                        switch($boleto['status']) {
                            case 'Pago':
                                $status_class = 'badge bg-success';
                                break;
                            case 'Vencido':
                                $status_class = 'badge bg-danger';
                                break;
                            case 'A Vencer':
                            default:
                                $status_class = 'badge bg-warning text-dark';
                                break;
                        }
                        ?>
                        <tr>
                            <td><?= $boleto['n_parcela'] ?>/<?= $boleto['qtd_parcelas'] ?></td>
                            <td>R$ <?= number_format($boleto['valor'], 2, ',', '.') ?></td>
                            <td><?= date('d/m/Y', strtotime($boleto['dt_vencimento'])) ?></td>
                            <td><span class="<?= $status_class ?>"><?= htmlspecialchars($boleto['status']) ?></span></td>
                            <td class="<?= $dias_class ?>">
                                <?= $dias_para_vencimento_texto ?>
                            </td>
                            <td>R$ <?= number_format($valor_comissao_parcela, 2, ',', '.') ?></td>
                            <td>
                                <?php if ($boleto['status'] !== 'Pago'): ?>
                                    <div class="dropdown">
                                        <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                            Alterar Status
                                        </button>
                                        <ul class="dropdown-menu">
                                            <?php if ($boleto['status'] !== 'A Vencer'): ?>
                                                <li>
                                                    <form method="post" class="d-inline">
                                                        <input type="hidden" name="id_boleto" value="<?= $boleto['id_boleto'] ?>">
                                                        <input type="hidden" name="novo_status" value="A Vencer">
                                                        <button type="submit" class="dropdown-item" onclick="return confirm('Alterar status para A Vencer?')">A Vencer</button>
                                                    </form>
                                                </li>
                                            <?php endif; ?>
                                            <li>
                                                <form method="post" class="d-inline">
                                                    <input type="hidden" name="id_boleto" value="<?= $boleto['id_boleto'] ?>">
                                                    <input type="hidden" name="novo_status" value="Pago">
                                                    <button type="submit" class="dropdown-item text-success" onclick="return confirm('Marcar como Pago?')">Pago</button>
                                                </form>
                                            </li>
                                            <?php if ($boleto['status'] !== 'Vencido'): ?>
                                                <li>
                                                    <form method="post" class="d-inline">
                                                        <input type="hidden" name="id_boleto" value="<?= $boleto['id_boleto'] ?>">
                                                        <input type="hidden" name="novo_status" value="Vencido">
                                                        <button type="submit" class="dropdown-item text-danger" onclick="return confirm('Marcar como Vencido?')">Vencido</button>
                                                    </form>
                                                </li>
                                            <?php endif; ?>
                                        </ul>
                                    </div>
                                <?php else: ?>
                                    <span class="text-muted small">Pago - Status bloqueado</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr class="table-secondary">
                        <td><strong>Total:</strong></td>
                        <td><strong>R$ <?= number_format($total_valor, 2, ',', '.') ?></strong></td>
                        <td colspan="3"></td>
                        <td><strong>R$ <?= number_format($valor_comissao_total, 2, ',', '.') ?></strong></td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    <?php endif; ?>

    <!-- Botões de Ação -->
    <div class="mt-4 text-center">
        <a href="vendas_view.php?id=<?= $venda['id_venda'] ?>" class="btn btn-info">Ver Detalhes da Venda</a>
        <a href="vendas_form.php?id=<?= $venda['id_venda'] ?>" class="btn btn-primary">Editar Venda</a>
        <a href="vendas_list.php" class="btn btn-secondary">Voltar para Vendas</a>
    </div>
</div>

<script>
// Auto-atualizar status vencidos (opcional)
document.addEventListener('DOMContentLoaded', function() {
    // Aqui você pode adicionar JavaScript para funcionalidades extras
    // como confirmações, modais, etc.
});
</script>

<?php require_once '../views/footer.php'; ?>