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

if (!empty($_GET['busca_tipo'])) {
    $filtros[] = 'tipo LIKE ?';
    $valores[] = '%' . $_GET['busca_tipo'] . '%';
}

if (!empty($_GET['formapg'])) {
    $filtros[] = 'formapg = ?';
    $valores[] = $_GET['formapg'];
}

// Consulta para contagem total
$sqlCount = "SELECT COUNT(*) FROM pecas";
if ($filtros) {
    $sqlCount .= " WHERE " . implode(' AND ', $filtros);
}
$stmtCount = $pdo->prepare($sqlCount);
$stmtCount->execute($valores);
$total = $stmtCount->fetchColumn();
$totalPaginas = ceil($total / $limite);

// Consulta principal
$sql = "SELECT * FROM pecas";
if ($filtros) {
    $sql .= " WHERE " . implode(' AND ', $filtros);
}
$sql .= " ORDER BY tipo LIMIT $limite OFFSET $offset";

$stmt = $pdo->prepare($sql);
$stmt->execute($valores);
$pecas = $stmt->fetchAll();

// Buscar formas de pagamento disponíveis para filtro
$stmtFormas = $pdo->prepare("SELECT DISTINCT formapg FROM pecas ORDER BY formapg");
$stmtFormas->execute();
$formasDisponiveis = $stmtFormas->fetchAll(PDO::FETCH_COLUMN);

require_once '../views/header.php';
?>

<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Peças Cadastradas</h2>
        <a href="custos_list.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Voltar para Custos
        </a>
    </div>

    <form method="get" class="row g-3 mb-4">
        <div class="col-md-6">
            <label class="form-label">Buscar por Tipo</label>
            <input type="text" name="busca_tipo" class="form-control" value="<?= htmlspecialchars($_GET['busca_tipo'] ?? '') ?>" placeholder="Digite o tipo da peça">
        </div>
        <div class="col-md-3">
            <label class="form-label">Forma de Pagamento</label>
            <select name="formapg" class="form-control">
                <option value="">Todas</option>
                <?php foreach ($formasDisponiveis as $forma): ?>
                    <option value="<?= htmlspecialchars($forma) ?>" <?= ($_GET['formapg'] ?? '') === $forma ? 'selected' : '' ?>>
                        <?= htmlspecialchars($forma) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3 d-flex align-items-end">
            <button type="submit" class="btn btn-primary me-2">Buscar</button>
            <a href="pecas_list.php" class="btn btn-secondary me-2">Limpar</a>
            <a href="pecas_form.php" class="btn btn-success">+ Nova</a>
        </div>
    </form>

    <?php if (count($pecas) === 0): ?>
        <div class="alert alert-warning">Nenhuma peça encontrada.</div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Tipo</th>
                        <th>Forma de Pagamento</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pecas as $peca): ?>
                        <tr>
                            <td><?= $peca['id_peca'] ?></td>
                            <td><?= htmlspecialchars($peca['tipo']) ?></td>
                            <td>
                                <span class="badge bg-primary"><?= htmlspecialchars($peca['formapg']) ?></span>
                            </td>
                            <td>
                                <a href="pecas_form.php?id=<?= $peca['id_peca'] ?>" class="btn btn-sm btn-primary">Editar</a>
                                <a href="#" class="btn btn-sm btn-danger" onclick="confirmarExclusao(<?= $peca['id_peca'] ?>)">Excluir</a>
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
                            <div class="col-md-4">
                                <strong>Total de Peças:</strong>
                                <span class="text-primary"><?= $total ?></span>
                            </div>
                            <div class="col-md-4">
                                <strong>Formas de Pagamento:</strong>
                                <span class="text-info"><?= count($formasDisponiveis) ?></span>
                            </div>
                            <div class="col-md-4">
                                <strong>Exibindo:</strong>
                                <span><?= count($pecas) ?> de <?= $total ?> registros</span>
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

<script>
function confirmarExclusao(id) {
    if (confirm('Tem certeza que deseja excluir esta peça?')) {
        window.location.href = 'pecas_delete.php?id=' + id;
    }
}
</script>

<?php require_once '../views/footer.php'; ?>