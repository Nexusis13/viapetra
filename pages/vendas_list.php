<?php
require_once '../config/protect.php';
require_once '../config/config.php';

$limite = 10;
$pagina = isset($_GET['pagina']) && is_numeric($_GET['pagina']) ? (int) $_GET['pagina'] : 1;
$offset = ($pagina - 1) * $limite;

// Filtros de busca
$filtros = [];
$valores = [];

if (!empty($_GET['busca_geral'])) {
    $filtros[] = '(v.cliente LIKE ? OR vd.nome LIKE ?)';
    $termo = '%' . $_GET['busca_geral'] . '%';
    $valores[] = $termo;
    $valores[] = $termo;
}

if (!empty($_GET['data_inicio'])) {
    $filtros[] = 'v.dt_venda >= ?';
    $valores[] = $_GET['data_inicio'];
}

if (!empty($_GET['data_fim'])) {
    $filtros[] = 'v.dt_venda <= ?';
    $valores[] = $_GET['data_fim'];
}

if (!empty($_GET['vendedor'])) {
    $filtros[] = 'v.id_vendedor = ?';
    $valores[] = $_GET['vendedor'];
}

// Consulta para contagem total
$sqlCount = "SELECT COUNT(*) FROM vendas v 
             LEFT JOIN vendedores vd ON v.id_vendedor = vd.id_vendedor";
if ($filtros) {
    $sqlCount .= " WHERE " . implode(' AND ', $filtros);
}
$stmtCount = $pdo->prepare($sqlCount);
$stmtCount->execute($valores);
$total = $stmtCount->fetchColumn();
$totalPaginas = ceil($total / $limite);

// Consulta principal com JOIN para pegar o nome do vendedor
$sql = "SELECT v.*, vd.nome as nome_vendedor, c.valor as percentual_comissao 
        FROM vendas v 
        LEFT JOIN vendedores vd ON v.id_vendedor = vd.id_vendedor
        LEFT JOIN comissao c ON v.id_comissao = c.id_comissao";
if ($filtros) {
    $sql .= " WHERE " . implode(' AND ', $filtros);
}
$sql .= " ORDER BY v.id_venda DESC LIMIT $limite OFFSET $offset";

$stmt = $pdo->prepare($sql);
$stmt->execute($valores);
$vendas = $stmt->fetchAll();

// Buscar vendedores para o filtro
$stmtVendedores = $pdo->prepare("SELECT id_vendedor, nome FROM vendedores WHERE STATUS = 1 ORDER BY nome");
$stmtVendedores->execute();
$vendedoresDisponiveis = $stmtVendedores->fetchAll();

require_once '../views/header.php';
?>

<div class="container-fluid">
    <h2>Vendas Cadastradas</h2>

    <form method="get" class="row g-3 mt-3 mb-4">
        <div class="col-md-4">
            <label class="form-label">Buscar por Cliente ou Vendedor</label>
            <input type="text" name="busca_geral" class="form-control" value="<?= htmlspecialchars($_GET['busca_geral'] ?? '') ?>">
        </div>
        <div class="col-md-2">
            <label class="form-label">Data Início</label>
            <input type="date" name="data_inicio" class="form-control" value="<?= htmlspecialchars($_GET['data_inicio'] ?? '') ?>">
        </div>
        <div class="col-md-2">
            <label class="form-label">Data Fim</label>
            <input type="date" name="data_fim" class="form-control" value="<?= htmlspecialchars($_GET['data_fim'] ?? '') ?>">
        </div>
        <div class="col-md-4">
            <label class="form-label">Vendedor</label>
            <select name="vendedor" class="form-control">
                <option value="">Todos os vendedores</option>
                <?php foreach ($vendedoresDisponiveis as $vend): ?>
                    <option value="<?= $vend['id_vendedor'] ?>" <?= ($_GET['vendedor'] ?? '') == $vend['id_vendedor'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($vend['nome']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <!-- <div class="col-12 mt-2">
            <button type="submit" class="btn btn-primary">Buscar</button>
            <a href="vendas_list.php" class="btn btn-secondary">Limpar</a>
            <a href="vendas_form.php" class="btn btn-success float-end">+ Nova Venda</a>
        </div> -->

         <div class="col-12 mt-2">
            <button type="submit" class="btn btn-primary">Buscar</button>
            <a href="vendas_list.php" class="btn btn-secondary">Limpar</a>
            <a href="vendas_form.php" class="btn btn-success float-end">+ Nova Venda</a>
        </div>
    </form>

    <?php if (count($vendas) === 0): ?>
        <div class="alert alert-warning">Nenhuma Venda encontrada.</div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Data</th>
                        <th>Cliente</th>
                        <th>Vendedor</th>
                        <th>Valor Total</th>
                        <th>Entrada</th>
                        <th>Forma Pgto</th>
                        <th>Parcelas</th>
                        <th>Comissão</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($vendas as $venda): ?>
                        <tr>
                            <td><?= $venda['id_venda'] ?></td>
                            <td><?= date('d/m/Y', strtotime($venda['dt_venda'])) ?></td>
                            <td><?= htmlspecialchars($venda['cliente']) ?></td>
                            <td><?= htmlspecialchars($venda['nome_vendedor'] ?? 'N/A') ?></td>
                            <td>R$ <?= number_format($venda['vlr_total'], 2, ',', '.') ?></td>
                            <td>R$ <?= number_format($venda['vlr_entrada'], 2, ',', '.') ?></td>
                            <td><?= htmlspecialchars($venda['forma_pg']) ?></td>
                            <td><?= $venda['qtd_parcelas'] ?>x</td>
                            <td><?= $venda['percentual_comissao'] ? $venda['percentual_comissao'] . '%' : 'N/A' ?></td>
                            <td>
                                <a href="vendas_form.php?id=<?= $venda['id_venda'] ?>" class="btn btn-sm btn-primary">Editar</a>
                                <a href="vendas_view.php?id=<?= $venda['id_venda'] ?>" class="btn btn-sm btn-info">Ver</a>
                                <a href="boletos_list.php?id_venda=<?= $venda['id_venda'] ?>" class="btn btn-sm btn-warning">Boletos</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
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