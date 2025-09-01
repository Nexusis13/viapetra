<?php
require_once '../config/protect.php';
require_once '../config/config.php';

$limite = 10;
$pagina = isset($_GET['pagina']) && is_numeric($_GET['pagina']) ? (int) $_GET['pagina'] : 1;
$offset = ($pagina - 1) * $limite;

// Atualização de status se necessário
if (isset($_GET['id']) && isset($_GET['acao']) && in_array($_GET['acao'], ['ativar', 'inativar'])) {
    $id = (int) $_GET['id'];
    $novoStatus = $_GET['acao'] === 'ativar' ? 1 : 0;

    $stmt = $pdo->prepare("UPDATE vendedores SET ativo = ? WHERE id_vendedor = ?");
    $stmt->execute([$novoStatus, $id]);

    header("Location: vendedor_list.php");
    exit;
}

// Filtros de busca
$filtros = [];
$valores = [];

if (!empty($_GET['busca_geral'])) {
    $filtros[] = '(nome LIKE ? OR celular LIKE ?)';
    $termo = '%' . $_GET['busca_geral'] . '%';
    $valores[] = $termo;
    $valores[] = $termo;
}

$sqlCount = "SELECT COUNT(*) FROM vendedores";
if ($filtros) {
    $sqlCount .= " WHERE " . implode(' AND ', $filtros);
}
$stmtCount = $pdo->prepare($sqlCount);
$stmtCount->execute($valores);
$total = $stmtCount->fetchColumn();
$totalPaginas = ceil($total / $limite);

// Consulta principal
$sql = "SELECT * FROM vendedores";
if ($filtros) {
    $sql .= " WHERE " . implode(' AND ', $filtros);
}
$sql .= " ORDER BY id_vendedor DESC LIMIT $limite OFFSET $offset";

$stmt = $pdo->prepare($sql);
$stmt->execute($valores);
$vendedores = $stmt->fetchAll();

require_once '../views/header.php';
?>

<div class="container">
    <h2>Vendedores Cadastrados</h2>

    <form method="get" class="row g-3 mt-3 mb-4">
        <div class="col-md-6">
            <label class="form-label">Buscar por Nome ou Celular</label>
            <input type="text" name="busca_geral" class="form-control" value="<?= htmlspecialchars($_GET['busca_geral'] ?? '') ?>">
        </div>
        <div class="col-12 mt-2">
            <button type="submit" class="btn btn-primary">Buscar</button>
            <a href="vendedor_list.php" class="btn btn-secondary">Limpar</a>
            <a href="vendedor_form.php" class="btn btn-success float-end">+ Novo Vendedor</a>
        </div>
    </form>

    <?php if (count($vendedores) === 0): ?>
        <div class="alert alert-warning">Nenhum Vendedor encontrado.</div>
    <?php else: ?>
        <table class="table table-striped">
            <thead>
                <tr>
                    <th>Nome</th>
                    <th>Celular</th>
                    <th>Tipo Chave</th>
                    <th>Chave</th>
                    <th>Status</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($vendedores as $vendedor): ?>
                    <tr>
                        <td><?= htmlspecialchars($vendedor['nome']) ?></td>
                        <td><?= htmlspecialchars($vendedor['celular']) ?></td>
                        <td><?= htmlspecialchars($vendedor['tipochave']) ?></td>
                        <td><?= htmlspecialchars($vendedor['chave']) ?></td>
                        <td>
                            <?php if ($vendedor['STATUS']): ?>
                                <span class="badge bg-success">Ativo</span>
                            <?php else: ?>
                                <span class="badge bg-danger">Inativo</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <a href="vendedor_form.php?id=<?= $vendedor['id_vendedor'] ?>" class="btn btn-sm btn-primary">Editar</a>
                            <?php if ($vendedor['STATUS']): ?>
                                <a href="?id=<?= $vendedor['id_vendedor'] ?>&acao=inativar" class="btn btn-sm btn-danger">Inativar</a>
                            <?php else: ?>
                                <a href="?id=<?= $vendedor['id_vendedor'] ?>&acao=ativar" class="btn btn-sm btn-success">Ativar</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <!-- Paginação -->
        <nav>
            <ul class="pagination">
                <?php for ($i = 1; $i <= $totalPaginas; $i++): ?>
                    <li class="page-item <?= $i == $pagina ? 'active' : '' ?>">
                        <a class="page-link" href="?pagina=<?= $i ?>"><?= $i ?></a>
                    </li>
                <?php endfor; ?>
            </ul>
        </nav>
    <?php endif; ?>
</div>

<?php require_once '../views/footer.php'; ?>
