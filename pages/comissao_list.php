<?php
require_once '../config/protect.php';
require_once '../config/config.php';

// Processar exclusão se solicitada
if (isset($_GET['excluir']) && is_numeric($_GET['excluir'])) {
    $id_comissao = (int) $_GET['excluir'];
    
    try {
        // Verificar se a comissão está sendo usada em vendas
        $sqlVerifica = "SELECT COUNT(*) FROM vendas WHERE id_comissao = ?";
        $stmtVerifica = $pdo->prepare($sqlVerifica);
        $stmtVerifica->execute([$id_comissao]);
        $vendas_usando = $stmtVerifica->fetchColumn();
        
        if ($vendas_usando > 0) {
            header("Location: comissao_list.php?erro=Não é possível excluir esta comissão pois ela está sendo usada em $vendas_usando venda(s)");
            exit;
        }
        
        // Excluir comissão
        $sqlDelete = "DELETE FROM comissao WHERE id_comissao = ?";
        $stmtDelete = $pdo->prepare($sqlDelete);
        $stmtDelete->execute([$id_comissao]);
        
        header('Location: comissao_list.php?sucesso=Comissão excluída com sucesso');
        exit;
        
    } catch (Exception $e) {
        header('Location: comissao_list.php?erro=Erro ao excluir comissão: ' . $e->getMessage());
        exit;
    }
}

$limite = 10;
$pagina = isset($_GET['pagina']) && is_numeric($_GET['pagina']) ? (int) $_GET['pagina'] : 1;
$offset = ($pagina - 1) * $limite;

// Filtros de busca
$filtros = [];
$valores = [];

if (!empty($_GET['valor_min'])) {
    $filtros[] = 'valor >= ?';
    $valores[] = $_GET['valor_min'];
}

if (!empty($_GET['valor_max'])) {
    $filtros[] = 'valor <= ?';
    $valores[] = $_GET['valor_max'];
}

// Consulta para contagem total
$sqlCount = "SELECT COUNT(*) FROM comissao";
if ($filtros) {
    $sqlCount .= " WHERE " . implode(' AND ', $filtros);
}
$stmtCount = $pdo->prepare($sqlCount);
$stmtCount->execute($valores);
$total = $stmtCount->fetchColumn();
$totalPaginas = ceil($total / $limite);

// Consulta principal com informações de uso
$sql = "SELECT c.*, 
               COUNT(v.id_venda) as total_vendas,
               COALESCE(SUM(v.vlr_total - v.vlr_entrada), 0) as valor_total_vendas
        FROM comissao c 
        LEFT JOIN vendas v ON c.id_comissao = v.id_comissao";
if ($filtros) {
    $sql .= " WHERE " . implode(' AND ', $filtros);
}
$sql .= " GROUP BY c.id_comissao ORDER BY c.valor DESC LIMIT $limite OFFSET $offset";

$stmt = $pdo->prepare($sql);
$stmt->execute($valores);
$comissoes = $stmt->fetchAll();

require_once '../views/header.php';
?>

<div class="container">
    <h2>Comissões Cadastradas</h2>

    <form method="get" class="row g-3 mt-3 mb-4">
        <div class="col-md-3">
            <label class="form-label">Valor Mínimo (%)</label>
            <input type="number" name="valor_min" step="0.01" min="0" max="100" class="form-control" value="<?= htmlspecialchars($_GET['valor_min'] ?? '') ?>">
        </div>
        <div class="col-md-3">
            <label class="form-label">Valor Máximo (%)</label>
            <input type="number" name="valor_max" step="0.01" min="0" max="100" class="form-control" value="<?= htmlspecialchars($_GET['valor_max'] ?? '') ?>">
        </div>
        <div class="col-12 mt-2">
            <button type="submit" class="btn btn-primary">Buscar</button>
            <a href="comissao_list.php" class="btn btn-secondary">Limpar</a>
            <a href="comissao_form.php" class="btn btn-success float-end">+ Nova Comissão</a>
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

    <?php if (count($comissoes) === 0): ?>
        <div class="alert alert-warning">Nenhuma comissão encontrada.</div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Percentual</th>
                        <th>Vendas Usando</th>
                        <th>Valor Total Vendas</th>
                        <th>Comissão Estimada</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($comissoes as $comissao): ?>
                        <?php
                        $valor_comissao_estimada = ($comissao['valor_total_vendas'] * $comissao['valor']) / 100;
                        ?>
                        <tr>
                            <td><?= $comissao['id_comissao'] ?></td>
                            <td>
                                <span class="badge bg-primary fs-6"><?= number_format($comissao['valor'], 2, ',', '.') ?>%</span>
                            </td>
                            <td>
                                <?php if ($comissao['total_vendas'] > 0): ?>
                                    <span class="badge bg-info"><?= $comissao['total_vendas'] ?> venda(s)</span>
                                <?php else: ?>
                                    <span class="text-muted">Nenhuma venda</span>
                                <?php endif; ?>
                            </td>
                            <td>R$ <?= number_format($comissao['valor_total_vendas'], 2, ',', '.') ?></td>
                            <td>
                                <span class="text-success fw-bold">R$ <?= number_format($valor_comissao_estimada, 2, ',', '.') ?></span>
                            </td>
                            <td>
                                <a href="comissao_form.php?id=<?= $comissao['id_comissao'] ?>" class="btn btn-sm btn-primary">Editar</a>
                                <?php if ($comissao['total_vendas'] == 0): ?>
                                    <a href="comissao_list.php?excluir=<?= $comissao['id_comissao'] ?>" 
                                       class="btn btn-sm btn-danger" 
                                       onclick="return confirm('Tem certeza que deseja excluir esta comissão?')">Excluir</a>
                                <?php else: ?>
                                    <button class="btn btn-sm btn-secondary" disabled title="Não pode ser excluída pois está sendo usada em vendas">Excluir</button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Estatísticas Gerais -->
        <?php
        // Buscar estatísticas gerais
        $sqlStats = "SELECT 
                        COUNT(*) as total_comissoes,
                        MIN(valor) as menor_comissao,
                        MAX(valor) as maior_comissao,
                        AVG(valor) as media_comissao
                     FROM comissao";
        $stmtStats = $pdo->prepare($sqlStats);
        $stmtStats->execute();
        $stats = $stmtStats->fetch();
        ?>
        
        <div class="row mt-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Estatísticas das Comissões</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-3 text-center">
                                <h6>Total de Comissões</h6>
                                <h4 class="text-primary"><?= $stats['total_comissoes'] ?></h4>
                            </div>
                            <div class="col-md-3 text-center">
                                <h6>Menor Comissão</h6>
                                <h4 class="text-success"><?= number_format($stats['menor_comissao'], 2, ',', '.') ?>%</h4>
                            </div>
                            <div class="col-md-3 text-center">
                                <h6>Maior Comissão</h6>
                                <h4 class="text-warning"><?= number_format($stats['maior_comissao'], 2, ',', '.') ?>%</h4>
                            </div>
                            <div class="col-md-3 text-center">
                                <h6>Comissão Média</h6>
                                <h4 class="text-info"><?= number_format($stats['media_comissao'], 2, ',', '.') ?>%</h4>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Paginação -->
        <?php if ($totalPaginas > 1): ?>
            <nav class="mt-4">
                <ul class="pagination">
                    <?php if ($pagina > 1): ?>
                        <li class="page-item">
                            <a class="page-link" href="?pagina=<?= $pagina - 1 ?><?= http_build_query(array_diff_key($_GET, ['pagina' => ''])) ? '&' . http_build_query(array_diff_key($_GET, ['pagina' => ''])) : '' ?>">Anterior</a>
                        </li>
                    <?php endif; ?>
                    
                    <?php for ($i = 1; $i <= $totalPaginas; $i++): ?>
                        <li class="page-item <?= $i == $pagina ? 'active' : '' ?>">
                            <a class="page-link" href="?pagina=<?= $i ?><?= http_build_query(array_diff_key($_GET, ['pagina' => ''])) ? '&' . http_build_query(array_diff_key($_GET, ['pagina' => ''])) : '' ?>"><?= $i ?></a>
                        </li>
                    <?php endfor; ?>
                    
                    <?php if ($pagina < $totalPaginas): ?>
                        <li class="page-item">
                            <a class="page-link" href="?pagina=<?= $pagina + 1 ?><?= http_build_query(array_diff_key($_GET, ['pagina' => ''])) ? '&' . http_build_query(array_diff_key($_GET, ['pagina' => ''])) : '' ?>">Próxima</a>
                        </li>
                    <?php endif; ?>
                </ul>
            </nav>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php require_once '../views/footer.php'; ?>