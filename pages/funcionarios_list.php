<?php
require_once '../config/protect.php';
require_once '../config/config.php';

// Paginação
$limite = 10;
$pagina = isset($_GET['pagina']) && is_numeric($_GET['pagina']) ? (int) $_GET['pagina'] : 1;
$offset = ($pagina - 1) * $limite;

// Filtros de busca
$filtros = [];
$valores = [];

if (!empty($_GET['busca_nome'])) {
    $filtros[] = 'nome LIKE ?';
    $valores[] = '%' . $_GET['busca_nome'] . '%';
}

if (isset($_GET['status']) && $_GET['status'] !== '') {
    $filtros[] = 'status = ?';
    $valores[] = $_GET['status'];
}

// Consulta para contagem total
$sqlCount = "SELECT COUNT(*) FROM funcionarios";
if ($filtros) {
    $sqlCount .= " WHERE " . implode(' AND ', $filtros);
}
$stmtCount = $pdo->prepare($sqlCount);
$stmtCount->execute($valores);
$total = $stmtCount->fetchColumn();
$totalPaginas = ceil($total / $limite);

// Consulta principal
$sql = "SELECT * FROM funcionarios";
if ($filtros) {
    $sql .= " WHERE " . implode(' AND ', $filtros);
}
$sql .= " ORDER BY nome LIMIT $limite OFFSET $offset";

$stmt = $pdo->prepare($sql);
$stmt->execute($valores);
$funcionarios = $stmt->fetchAll();

require_once '../views/header.php';
?>

<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Funcionários Cadastrados</h2>
        <a href="custos_list.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Voltar para Custos
        </a>
    </div>

    <form method="get" class="row g-3 mb-4">
        <div class="col-md-6">
            <label class="form-label">Buscar por Nome</label>
            <input type="text" name="busca_nome" class="form-control" value="<?= htmlspecialchars($_GET['busca_nome'] ?? '') ?>" placeholder="Digite o nome do funcionário">
        </div>
        <div class="col-md-3">
            <label class="form-label">Status</label>
            <select name="status" class="form-control">
                <option value="">Todos</option>
                <option value="1" <?= ($_GET['status'] ?? '') === '1' ? 'selected' : '' ?>>Ativo</option>
                <option value="0" <?= ($_GET['status'] ?? '') === '0' ? 'selected' : '' ?>>Inativo</option>
            </select>
        </div>
        <div class="col-md-3 d-flex align-items-end">
            <button type="submit" class="btn btn-primary me-2">Buscar</button>
            <a href="funcionarios_list.php" class="btn btn-secondary me-2">Limpar</a>
            <a href="funcionarios_form.php" class="btn btn-success">+ Novo</a>
        </div>
    </form>

    <?php if (count($funcionarios) === 0): ?>
        <div class="alert alert-warning">Nenhum funcionário encontrado.</div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nome</th>
                        <th>Status</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($funcionarios as $funcionario): ?>
                        <tr>
                            <td><?= $funcionario['id_funcionario'] ?></td>
                            <td><?= htmlspecialchars($funcionario['nome']) ?></td>
                            <td>
                                <?php if ($funcionario['status'] == 1): ?>
                                    <span class="badge bg-success">Ativo</span>
                                <?php else: ?>
                                    <span class="badge bg-danger">Inativo</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="funcionarios_form.php?id=<?= $funcionario['id_funcionario'] ?>" class="btn btn-sm btn-primary">Editar</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Resumo -->
        <div class="row mt-3">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Resumo</h5>
                        <div class="row">
                            <div class="col-md-6">
                                <strong>Total de Funcionários:</strong>
                                <span class="text-primary"><?= $total ?></span>
                            </div>
                            <div class="col-md-6">
                                <strong>Exibindo:</strong>
                                <span><?= count($funcionarios) ?> de <?= $total ?> registros</span>
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