<?php
require_once '../config/protect.php';
require_once '../config/config.php';

// Processar alteração de status se enviado via POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id_boleto']) && isset($_POST['novo_status'])) {
    $id_boleto = (int) $_POST['id_boleto'];
    $novo_status = $_POST['novo_status'];
    
    // Buscar o status atual do boleto
    $sqlStatusAtual = "SELECT status FROM boleto WHERE id_boleto = ?";
    $stmtStatusAtual = $pdo->prepare($sqlStatusAtual);
    $stmtStatusAtual->execute([$id_boleto]);
    $statusAtual = $stmtStatusAtual->fetchColumn();
    
    // Validar se pode alterar o status (não permitir alterar se já estiver pago)
    if ($statusAtual === 'Pago') {
        header("Location: gerenciar_boletos.php?erro=Não é possível alterar o status de um boleto já pago");
        exit;
    }
    
    // Validar status
    $status_validos = ['A Vencer', 'Pago', 'Vencido'];
    if (in_array($novo_status, $status_validos)) {
        $sqlUpdate = "UPDATE boleto SET status = ? WHERE id_boleto = ?";
        $stmtUpdate = $pdo->prepare($sqlUpdate);
        $stmtUpdate->execute([$novo_status, $id_boleto]);
        
        // Redirecionar para evitar reenvio do formulário
        header("Location: gerenciar_boletos.php?sucesso=Status atualizado com sucesso");
        exit;
    }
}

// Filtros de busca
$filtros = [];
$valores = [];

if (!empty($_GET['cliente'])) {
    $filtros[] = 'v.cliente LIKE ?';
    $valores[] = '%' . $_GET['cliente'] . '%';
}

if (!empty($_GET['vendedor'])) {
    $filtros[] = 'vd.nome LIKE ?';
    $valores[] = '%' . $_GET['vendedor'] . '%';
}

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

if (!empty($_GET['valor_min'])) {
    $filtros[] = 'b.valor >= ?';
    $valores[] = $_GET['valor_min'];
}

if (!empty($_GET['valor_max'])) {
    $filtros[] = 'b.valor <= ?';
    $valores[] = $_GET['valor_max'];
}

// Paginação
$registros_por_pagina = 50;
$pagina_atual = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
$offset = ($pagina_atual - 1) * $registros_por_pagina;

// Contar total de registros
$sqlCount = "SELECT COUNT(*) as total 
             FROM boleto b
             LEFT JOIN vendas v ON b.id_venda = v.id_venda
             LEFT JOIN vendedores vd ON b.id_vendedor = vd.id_vendedor
             LEFT JOIN comissao c ON b.id_comissao = c.id_comissao";

if ($filtros) {
    $sqlCount .= " WHERE " . implode(' AND ', $filtros);
}

$stmtCount = $pdo->prepare($sqlCount);
$stmtCount->execute($valores);
$total_registros = $stmtCount->fetch()['total'];
$total_paginas = ceil($total_registros / $registros_por_pagina);

// Buscar boletos com filtros e paginação
$sql = "SELECT b.*, 
               v.cliente, 
               v.dt_venda,
               v.vlr_total as valor_total_venda,
               vd.nome as nome_vendedor,
               c.valor as percentual_comissao
        FROM boleto b
        LEFT JOIN vendas v ON b.id_venda = v.id_venda
        LEFT JOIN vendedores vd ON b.id_vendedor = vd.id_vendedor
        LEFT JOIN comissao c ON b.id_comissao = c.id_comissao";

if ($filtros) {
    $sql .= " WHERE " . implode(' AND ', $filtros);
}

$sql .= " ORDER BY b.dt_vencimento ASC, b.id_venda ASC, b.n_parcela ASC";
$sql .= " LIMIT $registros_por_pagina OFFSET $offset";

$stmt = $pdo->prepare($sql);
$stmt->execute($valores);
$boletos = $stmt->fetchAll();

// Calcular totais (apenas dos registros filtrados, não paginados)
$sqlTotais = "SELECT COUNT(*) as total_boletos,
                     SUM(b.valor) as valor_total,
                     SUM(CASE WHEN b.status = 'Pago' THEN 1 ELSE 0 END) as boletos_pagos,
                     SUM(CASE WHEN b.status = 'Vencido' THEN 1 ELSE 0 END) as boletos_vencidos,
                     SUM(CASE WHEN b.status = 'A Vencer' THEN 1 ELSE 0 END) as boletos_a_vencer,
                     SUM(CASE WHEN b.status = 'Pago' THEN b.valor ELSE 0 END) as valor_pago
              FROM boleto b
              LEFT JOIN vendas v ON b.id_venda = v.id_venda
              LEFT JOIN vendedores vd ON b.id_vendedor = vd.id_vendedor
              LEFT JOIN comissao c ON b.id_comissao = c.id_comissao";

if ($filtros) {
    $sqlTotais .= " WHERE " . implode(' AND ', $filtros);
}

$stmtTotais = $pdo->prepare($sqlTotais);
$stmtTotais->execute($valores);
$totais = $stmtTotais->fetch();

$valor_pendente = $totais['valor_total'] - $totais['valor_pago'];

require_once '../views/header.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="fas fa-file-invoice-dollar"></i> Gerenciar Boletos</h2>
        <div>
            <button class="btn btn-success" onclick="exportarDados()">
                <i class="fas fa-download"></i> Exportar
            </button>
            <a href="vendas_list.php" class="btn btn-secondary">
                <i class="fas fa-shopping-cart"></i> Ver Vendas
            </a>
        </div>
    </div>

    <!-- Alertas de Boletos Críticos -->
    <?php
    // Verificar boletos críticos
    $sqlCriticos = "SELECT COUNT(*) as vence_hoje,
                           (SELECT COUNT(*) FROM boleto WHERE dt_vencimento < CURDATE() AND status != 'Pago') as vencidos,
                           (SELECT COUNT(*) FROM boleto WHERE dt_vencimento BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY) AND status = 'A Vencer') as vence_semana
                    FROM boleto 
                    WHERE dt_vencimento = CURDATE() AND status != 'Pago'";
    
    $stmtCriticos = $pdo->prepare($sqlCriticos);
    $stmtCriticos->execute();
    $criticos = $stmtCriticos->fetch();
    ?>

    <?php if ($criticos['vence_hoje'] > 0): ?>
        <div class="alert alert-warning alert-dismissible fade show">
            <i class="fas fa-exclamation-triangle"></i>
            <strong>Atenção!</strong> Existem <strong><?= $criticos['vence_hoje'] ?></strong> boleto(s) vencendo hoje.
            <a href="?status=A Vencer&data_vencimento_inicio=<?= date('Y-m-d') ?>&data_vencimento_fim=<?= date('Y-m-d') ?>" class="btn btn-sm btn-warning ms-2">Ver Boletos</a>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if ($criticos['vencidos'] > 0): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <i class="fas fa-times-circle"></i>
            <strong>Urgente!</strong> Existem <strong><?= $criticos['vencidos'] ?></strong> boleto(s) vencidos.
            <a href="?status=Vencido" class="btn btn-sm btn-danger ms-2">Ver Vencidos</a>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if ($criticos['vence_semana'] > 0): ?>
        <div class="alert alert-info alert-dismissible fade show">
            <i class="fas fa-info-circle"></i>
            <strong>Lembrete:</strong> <?= $criticos['vence_semana'] ?> boleto(s) vencem nos próximos 7 dias.
            <a href="?status=A Vencer&data_vencimento_inicio=<?= date('Y-m-d') ?>&data_vencimento_fim=<?= date('Y-m-d', strtotime('+7 days')) ?>" class="btn btn-sm btn-info ms-2">Ver Próximos</a>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    <div class="row mb-4">
        <div class="col-md-2">
            <div class="card text-center border-primary">
                <div class="card-body">
                    <h5 class="card-title text-primary"><?= number_format($totais['total_boletos']) ?></h5>
                    <p class="card-text">Total Boletos</p>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card text-center border-success">
                <div class="card-body">
                    <h5 class="card-title text-success"><?= number_format($totais['boletos_pagos']) ?></h5>
                    <p class="card-text">Pagos</p>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card text-center border-warning">
                <div class="card-body">
                    <h5 class="card-title text-warning"><?= number_format($totais['boletos_a_vencer']) ?></h5>
                    <p class="card-text">A Vencer</p>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card text-center border-danger">
                <div class="card-body">
                    <h5 class="card-title text-danger"><?= number_format($totais['boletos_vencidos']) ?></h5>
                    <p class="card-text">Vencidos</p>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card text-center border-success">
                <div class="card-body">
                    <h5 class="card-title text-success">R$ <?= number_format($totais['valor_pago'], 2, ',', '.') ?></h5>
                    <p class="card-text">Valor Pago</p>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card text-center border-info">
                <div class="card-body">
                    <h5 class="card-title text-info">R$ <?= number_format($valor_pendente, 2, ',', '.') ?></h5>
                    <p class="card-text">Valor Pendente</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Filtros Avançados -->
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0">
                <button class="btn btn-link" type="button" data-bs-toggle="collapse" data-bs-target="#filtrosAvancados">
                    <i class="fas fa-filter"></i> Filtros Avançados
                </button>
            </h5>
        </div>
        <div class="collapse show" id="filtrosAvancados">
            <div class="card-body">
                <form method="get" class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">Cliente</label>
                        <input type="text" name="cliente" class="form-control" placeholder="Nome do cliente" value="<?= htmlspecialchars($_GET['cliente'] ?? '') ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Vendedor</label>
                        <input type="text" name="vendedor" class="form-control" placeholder="Nome do vendedor" value="<?= htmlspecialchars($_GET['vendedor'] ?? '') ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-control">
                            <option value="">Todos</option>
                            <option value="A Vencer" <?= ($_GET['status'] ?? '') === 'A Vencer' ? 'selected' : '' ?>>A Vencer</option>
                            <option value="Pago" <?= ($_GET['status'] ?? '') === 'Pago' ? 'selected' : '' ?>>Pago</option>
                            <option value="Vencido" <?= ($_GET['status'] ?? '') === 'Vencido' ? 'selected' : '' ?>>Vencido</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Vencimento Início</label>
                        <input type="date" name="data_vencimento_inicio" class="form-control" value="<?= htmlspecialchars($_GET['data_vencimento_inicio'] ?? '') ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Vencimento Fim</label>
                        <input type="date" name="data_vencimento_fim" class="form-control" value="<?= htmlspecialchars($_GET['data_vencimento_fim'] ?? '') ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Valor Mínimo</label>
                        <input type="number" name="valor_min" class="form-control" step="0.01" placeholder="0.00" value="<?= htmlspecialchars($_GET['valor_min'] ?? '') ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Valor Máximo</label>
                        <input type="number" name="valor_max" class="form-control" step="0.01" placeholder="0.00" value="<?= htmlspecialchars($_GET['valor_max'] ?? '') ?>">
                    </div>
                    <div class="col-md-8 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary me-2">
                            <i class="fas fa-search"></i> Filtrar
                        </button>
                        <a href="gerenciar_boletos.php" class="btn btn-secondary me-2">
                            <i class="fas fa-eraser"></i> Limpar
                        </a>
                        <span class="text-muted">
                            Exibindo <?= count($boletos) ?> de <?= number_format($total_registros) ?> boletos
                        </span>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Mensagens -->
    <?php if (isset($_GET['sucesso'])): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <i class="fas fa-check-circle"></i> <?= htmlspecialchars($_GET['sucesso']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if (isset($_GET['erro'])): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($_GET['erro']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Tabela de Boletos -->
    <?php if (count($boletos) === 0): ?>
        <div class="alert alert-warning">
            <i class="fas fa-info-circle"></i> Nenhum boleto encontrado com os filtros aplicados.
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-striped table-hover">
                <thead class="table-dark">
                    <tr>
                        <th>ID</th>
                        <th>Venda</th>
                        <th>Cliente</th>
                        <th>Vendedor</th>
                        <th>Parcela</th>
                        <th>Valor</th>
                        <th>Vencimento</th>
                        <th>Status</th>
                        <th>Situação</th>
                        <th>Comissão</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($boletos as $boleto): ?>
                        <?php
                        // Calcular dias para vencimento
                        $dias_para_vencimento_texto = '-';
                        $dias_class = '';
                        $situacao_class = '';
                        
                        if ($boleto['status'] !== 'Pago') {
                            $data_vencimento = new DateTime($boleto['dt_vencimento']);
                            $data_atual = new DateTime();
                            $dias_para_vencimento = $data_atual->diff($data_vencimento)->format('%r%a');
                            
                            if ($dias_para_vencimento < 0) {
                                $dias_para_vencimento_texto = abs($dias_para_vencimento) . ' dias vencido';
                                $dias_class = 'text-danger fw-bold';
                                $situacao_class = 'bg-danger bg-opacity-10';
                            } elseif ($dias_para_vencimento == 0) {
                                $dias_para_vencimento_texto = 'Vence hoje';
                                $dias_class = 'text-warning fw-bold';
                                $situacao_class = 'bg-warning bg-opacity-10';
                            } else {
                                $dias_para_vencimento_texto = $dias_para_vencimento . ' dias';
                                if ($dias_para_vencimento <= 7) {
                                    $dias_class = 'text-warning fw-bold';
                                    $situacao_class = 'bg-warning bg-opacity-10';
                                }
                            }
                        }
                        
                        // Calcular comissão
                        $valor_comissao = 0;
                        if ($boleto['percentual_comissao']) {
                            $valor_comissao = ($boleto['valor'] * $boleto['percentual_comissao']) / 100;
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
                        <tr class="<?= $situacao_class ?>">
                            <td>
                                <small class="text-muted">#<?= $boleto['id_boleto'] ?></small>
                            </td>
                            <td>
                                <a href="boletos_list.php?id_venda=<?= $boleto['id_venda'] ?>" class="text-decoration-none">
                                    <strong>#<?= $boleto['id_venda'] ?></strong>
                                </a>
                                <br>
                                <small class="text-muted"><?= date('d/m/Y', strtotime($boleto['dt_venda'])) ?></small>
                            </td>
                            <td>
                                <div style="max-width: 150px;">
                                    <span class="d-inline-block text-truncate" style="max-width: 100%;" title="<?= htmlspecialchars($boleto['cliente']) ?>">
                                        <?= htmlspecialchars($boleto['cliente']) ?>
                                    </span>
                                </div>
                            </td>
                            <td>
                                <div style="max-width: 120px;">
                                    <span class="d-inline-block text-truncate" style="max-width: 100%;" title="<?= htmlspecialchars($boleto['nome_vendedor']) ?>">
                                        <?= htmlspecialchars($boleto['nome_vendedor']) ?>
                                    </span>
                                </div>
                            </td>
                            <td>
                                <strong><?= $boleto['n_parcela'] ?>/<?= $boleto['qtd_parcelas'] ?></strong>
                            </td>
                            <td>
                                <strong>R$ <?= number_format($boleto['valor'], 2, ',', '.') ?></strong>
                            </td>
                            <td>
                                <?= date('d/m/Y', strtotime($boleto['dt_vencimento'])) ?>
                            </td>
                            <td>
                                <span class="<?= $status_class ?>"><?= htmlspecialchars($boleto['status']) ?></span>
                            </td>
                            <td class="<?= $dias_class ?>">
                                <?= $dias_para_vencimento_texto ?>
                            </td>
                            <td>
                                <?php if ($boleto['percentual_comissao']): ?>
                                    <?= $boleto['percentual_comissao'] ?>% 
                                    <br>
                                    <small class="text-muted">R$ <?= number_format($valor_comissao, 2, ',', '.') ?></small>
                                <?php else: ?>
                                    <span class="text-muted">N/A</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($boleto['status'] !== 'Pago'): ?>
                                    <div class="dropdown">
                                        <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <ul class="dropdown-menu">
                                            <?php if ($boleto['status'] !== 'A Vencer'): ?>
                                                <li>
                                                    <form method="post" class="d-inline">
                                                        <input type="hidden" name="id_boleto" value="<?= $boleto['id_boleto'] ?>">
                                                        <input type="hidden" name="novo_status" value="A Vencer">
                                                        <button type="submit" class="dropdown-item" onclick="return confirm('Alterar status para A Vencer?')">
                                                            <i class="fas fa-clock text-warning"></i> A Vencer
                                                        </button>
                                                    </form>
                                                </li>
                                            <?php endif; ?>
                                            <li>
                                                <form method="post" class="d-inline">
                                                    <input type="hidden" name="id_boleto" value="<?= $boleto['id_boleto'] ?>">
                                                    <input type="hidden" name="novo_status" value="Pago">
                                                    <button type="submit" class="dropdown-item text-success" onclick="return confirm('Marcar como Pago?')">
                                                        <i class="fas fa-check-circle text-success"></i> Pago
                                                    </button>
                                                </form>
                                            </li>
                                            <?php if ($boleto['status'] !== 'Vencido'): ?>
                                                <li>
                                                    <form method="post" class="d-inline">
                                                        <input type="hidden" name="id_boleto" value="<?= $boleto['id_boleto'] ?>">
                                                        <input type="hidden" name="novo_status" value="Vencido">
                                                        <button type="submit" class="dropdown-item text-danger" onclick="return confirm('Marcar como Vencido?')">
                                                            <i class="fas fa-times-circle text-danger"></i> Vencido
                                                        </button>
                                                    </form>
                                                </li>
                                            <?php endif; ?>
                                        </ul>
                                    </div>
                                <?php else: ?>
                                    <span class="text-muted small">
                                        <i class="fas fa-lock"></i> Bloqueado
                                    </span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot class="table-secondary">
                    <tr>
                        <td colspan="5"><strong>Totais da Página:</strong></td>
                        <td><strong>R$ <?= number_format(array_sum(array_column($boletos, 'valor')), 2, ',', '.') ?></strong></td>
                        <td colspan="5"></td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <!-- Paginação -->
        <?php if ($total_paginas > 1): ?>
            <nav aria-label="Paginação">
                <ul class="pagination justify-content-center">
                    <!-- Primeira página -->
                    <?php if ($pagina_atual > 1): ?>
                        <li class="page-item">
                            <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['pagina' => 1])) ?>">
                                <i class="fas fa-angle-double-left"></i>
                            </a>
                        </li>
                        <li class="page-item">
                            <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['pagina' => $pagina_atual - 1])) ?>">
                                <i class="fas fa-angle-left"></i>
                            </a>
                        </li>
                    <?php endif; ?>

                    <!-- Páginas numeradas -->
                    <?php
                    $inicio = max(1, $pagina_atual - 5);
                    $fim = min($total_paginas, $pagina_atual + 5);
                    
                    for ($i = $inicio; $i <= $fim; $i++):
                    ?>
                        <li class="page-item <?= ($i == $pagina_atual) ? 'active' : '' ?>">
                            <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['pagina' => $i])) ?>">
                                <?= $i ?>
                            </a>
                        </li>
                    <?php endfor; ?>

                    <!-- Última página -->
                    <?php if ($pagina_atual < $total_paginas): ?>
                        <li class="page-item">
                            <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['pagina' => $pagina_atual + 1])) ?>">
                                <i class="fas fa-angle-right"></i>
                            </a>
                        </li>
                        <li class="page-item">
                            <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['pagina' => $total_paginas])) ?>">
                                <i class="fas fa-angle-double-right"></i>
                            </a>
                        </li>
                    <?php endif; ?>
                </ul>
            </nav>

            <div class="text-center text-muted mb-3">
                Página <?= $pagina_atual ?> de <?= $total_paginas ?> 
                (<?= number_format($total_registros) ?> boletos no total)
            </div>
        <?php endif; ?>
    <?php endif; ?>

    <!-- Rodapé com Informações -->
    <div class="row mt-4">
        <div class="col-md-6">
            <div class="card">
                <div class="card-body">
                    <h6 class="card-title"><i class="fas fa-keyboard"></i> Atalhos de Teclado</h6>
                    <small class="text-muted">
                        <strong>Ctrl + F:</strong> Filtrar por cliente<br>
                        <strong>Ctrl + E:</strong> Exportar dados<br>
                        <strong>ESC:</strong> Limpar filtros
                    </small>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card">
                <div class="card-body">
                    <h6 class="card-title"><i class="fas fa-info-circle"></i> Informações</h6>
                    <small class="text-muted">
                        • Boletos pagos não podem ter status alterado<br>
                        • Clique nos cards de resumo para filtrar<br>
                        • Use os filtros rápidos para navegação ágil
                    </small>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Processar exportação
if (isset($_GET['exportar'])) {
    // Buscar todos os dados sem paginação para exportação
    $sqlExport = "SELECT b.id_boleto,
                         b.id_venda,
                         b.n_parcela,
                         b.qtd_parcelas,
                         b.valor,
                         b.dt_vencimento,
                         b.status,
                         v.cliente,
                         v.dt_venda,
                         v.vlr_total as valor_total_venda,
                         vd.nome as nome_vendedor,
                         c.valor as percentual_comissao
                  FROM boleto b
                  LEFT JOIN vendas v ON b.id_venda = v.id_venda
                  LEFT JOIN vendedores vd ON b.id_vendedor = vd.id_vendedor
                  LEFT JOIN comissao c ON b.id_comissao = c.id_comissao";
    
    if ($filtros) {
        $sqlExport .= " WHERE " . implode(' AND ', $filtros);
    }
    
    $sqlExport .= " ORDER BY b.dt_vencimento ASC, b.id_venda ASC, b.n_parcela ASC";
    
    $stmtExport = $pdo->prepare($sqlExport);
    $stmtExport->execute($valores);
    $dadosExport = $stmtExport->fetchAll();
    
    // Headers para download CSV
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=boletos_' . date('Y-m-d_H-i-s') . '.csv');
    
    $output = fopen('php://output', 'w');
    
    // BOM para UTF-8
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // Cabeçalhos
    fputcsv($output, [
        'ID Boleto',
        'ID Venda',
        'Cliente',
        'Vendedor',
        'Parcela',
        'Qtd Parcelas',
        'Valor',
        'Data Vencimento',
        'Status',
        'Data Venda',
        'Valor Total Venda',
        'Percentual Comissão',
        'Valor Comissão',
        'Dias para Vencimento'
    ], ';');
    
    // Dados
    foreach ($dadosExport as $row) {
        // Calcular comissão
        $valorComissao = 0;
        if ($row['percentual_comissao']) {
            $valorComissao = ($row['valor'] * $row['percentual_comissao']) / 100;
        }
        
        // Calcular dias para vencimento
        $diasVencimento = '';
        if ($row['status'] !== 'Pago') {
            $dataVencimento = new DateTime($row['dt_vencimento']);
            $dataAtual = new DateTime();
            $diff = $dataAtual->diff($dataVencimento)->format('%r%a');
            $diasVencimento = $diff;
        }
        
        fputcsv($output, [
            $row['id_boleto'],
            $row['id_venda'],
            $row['cliente'],
            $row['nome_vendedor'],
            $row['n_parcela'],
            $row['qtd_parcelas'],
            number_format($row['valor'], 2, ',', '.'),
            date('d/m/Y', strtotime($row['dt_vencimento'])),
            $row['status'],
            date('d/m/Y', strtotime($row['dt_venda'])),
            number_format($row['valor_total_venda'], 2, ',', '.'),
            $row['percentual_comissao'] ? $row['percentual_comissao'] . '%' : '',
            number_format($valorComissao, 2, ',', '.'),
            $diasVencimento
        ], ';');
    }

    
    fclose($output);
    exit;
}

// Função para exportar dados
function exportarDados() {
    const params = new URLSearchParams(window.location.search);
    params.append('exportar', '1');
    window.open('?' + params.toString(), '_blank');
}

// Funcionalidades avançadas
document.addEventListener('DOMContentLoaded', function() {
    // Destacar boletos que vencem hoje
    const hoje = new Date().toLocaleDateString('pt-BR');
    const linhas = document.querySelectorAll('tbody tr');
    
    linhas.forEach(linha => {
        const dataVencimento = linha.querySelector('td:nth-child(7)').textContent.trim();
        if (dataVencimento === hoje) {
            linha.classList.add('table-warning');
        }
    });

    // Confirmar alterações de status
    const forms = document.querySelectorAll('form[method="post"]');
    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            const status = this.querySelector('input[name="novo_status"]').value;
            const mensagem = status === 'Pago' ? 
                'Tem certeza que deseja marcar este boleto como PAGO?\n\n⚠️ Esta ação não poderá ser desfeita!' :
                `Tem certeza que deseja alterar o status para ${status}?`;
            
            if (!confirm(mensagem)) {
                e.preventDefault();
            }
        });
    });

    // Filtro rápido por status nos cards
    document.querySelectorAll('.card').forEach(card => {
        card.addEventListener('click', function() {
            const texto = this.querySelector('.card-text').textContent.trim();
            const filtroStatus = document.querySelector('select[name="status"]');
            
            switch(texto) {
                case 'Pagos':
                    filtroStatus.value = 'Pago';
                    break;
                case 'A Vencer':
                    filtroStatus.value = 'A Vencer';
                    break;
                case 'Vencidos':
                    filtroStatus.value = 'Vencido';
                    break;
                default:
                    filtroStatus.value = '';
            }
            
            if (texto !== 'Total Boletos' && texto !== 'Valor Pago' && texto !== 'Valor Pendente') {
                filtroStatus.closest('form').submit();
            }
        });
    });

    // Atalhos de teclado
    document.addEventListener('keydown', function(e) {
        // Ctrl + F para focar no filtro de cliente
        if (e.ctrlKey && e.key === 'f') {
            e.preventDefault();
            document.querySelector('input[name="cliente"]').focus();
        }
        
        // Ctrl + E para exportar
        if (e.ctrlKey && e.key === 'e') {
            e.preventDefault();
            exportarDados();
        }
        
        // ESC para limpar filtros
        if (e.key === 'Escape') {
            window.location.href = 'gerenciar_boletos.php';
        }
    });

    // Tooltip para botões
    const tooltips = {
        'Ctrl + F': 'Filtrar por cliente',
        'Ctrl + E': 'Exportar dados',
        'ESC': 'Limpar filtros'
    };

    // Auto-completar cliente
    const inputCliente = document.querySelector('input[name="cliente"]');
    if (inputCliente) {
        let timeoutId;
        inputCliente.addEventListener('input', function() {
            clearTimeout(timeoutId);
            timeoutId = setTimeout(() => {
                // Implementar auto-complete se necessário
            }, 300);
        });
    }
});

// Atualizar contador a cada minuto
setInterval(function() {
    const agora = new Date();
    const elemento = document.querySelector('.container-fluid h2');
    if (elemento) {
        elemento.innerHTML = '<i class="fas fa-file-invoice-dollar"></i> Gerenciar Boletos <small class="text-muted">(' + 
                           agora.toLocaleTimeString('pt-BR') + ')</small>';
    }
}, 60000);
</script>

<?php require_once '../views/footer.php'; ?>