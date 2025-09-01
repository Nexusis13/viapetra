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

// Consulta para contagem total
$sqlCount = "SELECT COUNT(*) FROM materia_prima";
if ($filtros) {
    $sqlCount .= " WHERE " . implode(' AND ', $filtros);
}
$stmtCount = $pdo->prepare($sqlCount);
$stmtCount->execute($valores);
$total = $stmtCount->fetchColumn();
$totalPaginas = ceil($total / $limite);

// Consulta principal
$sql = "SELECT * FROM materia_prima";
if ($filtros) {
    $sql .= " WHERE " . implode(' AND ', $filtros);
}
$sql .= " ORDER BY nome LIMIT $limite OFFSET $offset";

$stmt = $pdo->prepare($sql);
$stmt->execute($valores);
$materias = $stmt->fetchAll();

require_once '../views/header.php';
?>

<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Matérias-Primas Cadastradas</h2>
        <a href="custos_list.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Voltar para Custos
        </a>
    </div>

    <form method="get" class="row g-3 mb-4">
        <div class="col-md-8">
            <label class="form-label">Buscar por Nome</label>
            <input type="text" name="busca_nome" class="form-control" value="<?= htmlspecialchars($_GET['busca_nome'] ?? '') ?>" placeholder="Digite o nome da matéria-prima">
        </div>
        <div class="col-md-4 d-flex align-items-end">
            <button type="submit" class="btn btn-primary me-2">Buscar</button>
            <a href="materiaprima_list.php" class="btn btn-secondary me-2">Limpar</a>
            <a href="materiaprima_form.php" class="btn btn-success">+ Nova</a>
        </div>
    </form>

    <?php if (count($materias) === 0): ?>
        <div class="alert alert-warning">Nenhuma matéria-prima encontrada.</div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nome</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($materias as $materia): ?>
                        <tr>
                            <td><?= $materia['id_materia'] ?></td>
                            <td><?= htmlspecialchars($materia['nome']) ?></td>
                            <td>
                                <a href="materiaprima_form.php?id=<?= $materia['id_materia'] ?>" class="btn btn-sm btn-primary">Editar</a>
                                <a href="#" class="btn btn-sm btn-danger" onclick="confirmarExclusao(<?= $materia['id_materia'] ?>)">Excluir</a>
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
                                <strong>Total de Matérias-Primas:</strong>
                                <span class="text-primary"><?= $total ?></span>
                            </div>
                            <div class="col-md-6">
                                <strong>Exibindo:</strong>
                                <span><?= count($materias) ?> de <?= $total ?> registros</span>
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
    if (confirm('Tem certeza que deseja excluir esta matéria-prima?')) {
        window.location.href = 'materiaprima_delete.php?id=' + id;
    }
}
</script>

<?php require_once '../views/footer.php'; ?>