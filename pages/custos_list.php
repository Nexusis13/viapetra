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

if (!empty($_GET['busca_geral'])) {
    $filtros[] = '(f.nome LIKE ? OR p.nome LIKE ? OR m.nome LIKE ?)';
    $termo = '%' . $_GET['busca_geral'] . '%';
    $valores[] = $termo;
    $valores[] = $termo;
    $valores[] = $termo;
}

if (!empty($_GET['data_inicio'])) {
    $filtros[] = 'c.data >= ?';
    $valores[] = $_GET['data_inicio'];
}

if (!empty($_GET['data_fim'])) {
    $filtros[] = 'c.data <= ?';
    $valores[] = $_GET['data_fim'];
}

if (!empty($_GET['funcionario'])) {
    $filtros[] = 'c.id_funcionario = ?';
    $valores[] = $_GET['funcionario'];
}

if (!empty($_GET['materia'])) {
    $filtros[] = 'c.id_materia = ?';
    $valores[] = $_GET['materia'];
}

// Consulta para contagem total
$sqlCount = "SELECT COUNT(*) FROM custos c 
             LEFT JOIN funcionarios f ON c.id_funcionario = f.id_funcionario
             LEFT JOIN pecas p ON c.id_peca = p.id_peca
             LEFT JOIN materia_prima m ON c.id_materia = m.id_materia";
if ($filtros) {
    $sqlCount .= " WHERE " . implode(' AND ', $filtros);
}
$stmtCount = $pdo->prepare($sqlCount);
$stmtCount->execute($valores);
$total = $stmtCount->fetchColumn();
$totalPaginas = ceil($total / $limite);

// Consulta principal
$sql = "SELECT c.*, f.nome as funcionario, p.tipo as peca, m.nome as materia 
        FROM custos c
        LEFT JOIN funcionarios f ON c.id_funcionario = f.id_funcionario
        LEFT JOIN pecas p ON c.id_peca = p.id_peca
        LEFT JOIN materia_prima m ON c.id_materia = m.id_materia";
if ($filtros) {
    $sql .= " WHERE " . implode(' AND ', $filtros);
}
$sql .= " ORDER BY c.data DESC LIMIT $limite OFFSET $offset";

$stmt = $pdo->prepare($sql);
$stmt->execute($valores);
$custos = $stmt->fetchAll();

// Buscar funcionários para o filtro
$stmtFuncionarios = $pdo->prepare("SELECT id_funcionario, nome FROM funcionarios WHERE status = 1 ORDER BY nome");
$stmtFuncionarios->execute();
$funcionariosDisponiveis = $stmtFuncionarios->fetchAll();

// Buscar matérias-primas para o filtro
$stmtMaterias = $pdo->prepare("SELECT id_materia, nome FROM materia_prima ORDER BY nome");
$stmtMaterias->execute();
$materiasDisponiveis = $stmtMaterias->fetchAll();

require_once '../views/header.php';
?>

<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Custos Cadastrados</h2>
        
        <!-- Botões de navegação para cadastros -->
        <div class="btn-group" role="group">
            <a href="funcionarios_list.php" class="btn btn-outline-primary">
                <i class="fas fa-users"></i> Funcionários
            </a>
            <a href="pecas_list.php" class="btn btn-outline-info">
                <i class="fas fa-puzzle-piece"></i> Peças
            </a>
            <a href="materiaprima_list.php" class="btn btn-outline-warning">
                <i class="fas fa-cubes"></i> Matéria Prima
            </a>
        </div>
    </div>

    <form method="get" class="row g-3 mt-3 mb-4">
        <div class="col-md-4">
            <label class="form-label">Buscar por Funcionário, Peça ou Matéria Prima</label>
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
        <div class="col-md-2">
            <label class="form-label">Funcionário</label>
            <select name="funcionario" class="form-control">
                <option value="">Todos os funcionários</option>
                <?php foreach ($funcionariosDisponiveis as $func): ?>
                    <option value="<?= $func['id_funcionario'] ?>" <?= ($_GET['funcionario'] ?? '') == $func['id_funcionario'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($func['nome']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label">Matéria Prima</label>
            <select name="materia" class="form-control">
                <option value="">Todas as matérias</option>
                <?php foreach ($materiasDisponiveis as $mat): ?>
                    <option value="<?= $mat['id_materia'] ?>" <?= ($_GET['materia'] ?? '') == $mat['id_materia'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($mat['nome']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-12 mt-2">
            <button type="submit" class="btn btn-primary">Buscar</button>
            <a href="custos_list.php" class="btn btn-secondary">Limpar</a>
            <a href="custos_form.php" class="btn btn-success float-end">+ Novo Custo</a>
        </div>
    </form>

    <?php if (count($custos) === 0): ?>
        <div class="alert alert-warning">Nenhum custo encontrado.</div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>Data</th>
                        <th>Funcionário</th>
                        <th>Peça</th>
                        <th>Ambiente</th>
                        <th>Matéria Prima</th>
                        <th>Comp. (m)</th>
                        <th>Larg. (m)</th>
                        <th>Qtd. (Peças)</th>
						<th>Total(mts)</th>
                        <th>Vlr. Venda</th>
                        <th>Vlr. Compra</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($custos as $custo): ?>
                        <tr>
                            <td><?= date('d/m/Y', strtotime($custo['data'])) ?></td>
                            <td><?= htmlspecialchars($custo['funcionario'] ?? 'N/A') ?></td>
                            <td><?= htmlspecialchars($custo['peca'] ?? 'N/A') ?></td>
                            <td><?= htmlspecialchars($custo['ambiente'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($custo['materia'] ?? 'N/A') ?></td>
                            <td><?= number_format($custo['comp'], 2, ',', '.') ?></td>
                            <td><?= number_format($custo['larg'], 2, ',', '.') ?></td>
                            <td><?= $custo['qntd'] ?></td>
							<td><strong><?= number_format($custo['total'], 2, ',', '.') ?></strong></td>
                            <td>R$ <?= number_format($custo['vlrvenda'], 2, ',', '.') ?></td>
                            <td>R$ <?= number_format($custo['vlrcompra'], 2, ',', '.') ?></td>
                            <td>
                                <a href="custos_form.php?id=<?= $custo['id_custo'] ?>" class="btn btn-sm btn-primary">Editar</a>
                                <a href="custos_view.php?id=<?= $custo['id_custo'] ?>" class="btn btn-sm btn-info">Ver</a>
                                <a href="#" class="btn btn-sm btn-danger" onclick="confirmarExclusao(<?= $custo['id_custo'] ?>)">Excluir</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Resumo dos custos -->
        <?php
        $totalGeral = array_sum(array_column($custos, 'total'));
        $totalVenda = array_sum(array_column($custos, 'vlrvenda'));
        $totalCompra = array_sum(array_column($custos, 'vlrcompra'));
        ?>
        <div class="row mt-3">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Resumo da Página</h5>
                        <div class="row">
                            <div class="col-md-3">
                                <strong>Total Geral:</strong><br>
                                <span class="text-primary fs-5">R$ <?= number_format($totalGeral, 2, ',', '.') ?></span>
                            </div>
                            <div class="col-md-3">
                                <strong>Total Venda:</strong><br>
                                <span class="text-success">R$ <?= number_format($totalVenda, 2, ',', '.') ?></span>
                            </div>
                            <div class="col-md-3">
                                <strong>Total Compra:</strong><br>
                                <span class="text-danger">R$ <?= number_format($totalCompra, 2, ',', '.') ?></span>
                            </div>
                            <div class="col-md-3">
                                <strong>Registros:</strong><br>
                                <span><?= count($custos) ?> de <?= $total ?></span>
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
    if (confirm('Tem certeza que deseja excluir este custo?')) {
        window.location.href = 'custos_delete.php?id=' + id;
    }
}
</script>

<?php require_once '../views/footer.php'; ?>