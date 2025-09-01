<?php
require_once '../config/protect.php';
require_once '../config/config.php';

$id_comissao = isset($_GET['id']) && is_numeric($_GET['id']) ? (int) $_GET['id'] : null;
$isEdicao = $id_comissao !== null;

// Buscar dados da comissão se for edição
$comissao = null;
if ($isEdicao) {
    $sql = "SELECT c.*, 
                   COUNT(v.id_venda) as total_vendas,
                   COALESCE(SUM(v.vlr_total - v.vlr_entrada), 0) as valor_total_vendas
            FROM comissao c 
            LEFT JOIN vendas v ON c.id_comissao = v.id_comissao 
            WHERE c.id_comissao = ? 
            GROUP BY c.id_comissao";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$id_comissao]);
    $comissao = $stmt->fetch();
    
    if (!$comissao) {
        header('Location: comissao_list.php?erro=Comissão não encontrada');
        exit;
    }
}

// Processar formulário
$erro = '';
$sucesso = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $valor = trim($_POST['valor'] ?? '');
    
    // Validações
    if (empty($valor)) {
        $erro = 'O valor da comissão é obrigatório.';
    } elseif (!is_numeric($valor)) {
        $erro = 'O valor da comissão deve ser um número válido.';
    } elseif ($valor < 0 || $valor > 100) {
        $erro = 'O valor da comissão deve estar entre 0% e 100%.';
    } else {
        try {
            // Verificar se já existe uma comissão com o mesmo valor (exceto a atual se for edição)
            $sqlVerifica = "SELECT id_comissao FROM comissao WHERE valor = ?";
            $params = [$valor];
            
            if ($isEdicao) {
                $sqlVerifica .= " AND id_comissao != ?";
                $params[] = $id_comissao;
            }
            
            $stmtVerifica = $pdo->prepare($sqlVerifica);
            $stmtVerifica->execute($params);
            
            if ($stmtVerifica->fetch()) {
                $erro = 'Já existe uma comissão cadastrada com este valor.';
            } else {
                if ($isEdicao) {
                    // Atualizar comissão existente
                    $sql = "UPDATE comissao SET valor = ? WHERE id_comissao = ?";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([$valor, $id_comissao]);
                    
                    $sucesso = 'Comissão atualizada com sucesso!';
                } else {
                    // Inserir nova comissão
                    $sql = "INSERT INTO comissao (valor) VALUES (?)";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([$valor]);
                    
                    header('Location: comissao_list.php?sucesso=Comissão cadastrada com sucesso');
                    exit;
                }
            }
        } catch (Exception $e) {
            $erro = 'Erro ao salvar comissão: ' . $e->getMessage();
        }
    }
}

// Buscar últimas vendas que usam esta comissão (se for edição)
$vendas_usando = [];
if ($isEdicao && $comissao['total_vendas'] > 0) {
    $sqlVendas = "SELECT v.id_venda, v.cliente, v.dt_venda, v.vlr_total, vd.nome as nome_vendedor
                  FROM vendas v
                  LEFT JOIN vendedores vd ON v.id_vendedor = vd.id_vendedor
                  WHERE v.id_comissao = ?
                  ORDER BY v.dt_venda DESC
                  LIMIT 5";
    $stmtVendas = $pdo->prepare($sqlVendas);
    $stmtVendas->execute([$id_comissao]);
    $vendas_usando = $stmtVendas->fetchAll();
}

require_once '../views/header.php';
?>

<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><?= $isEdicao ? 'Editar Comissão' : 'Nova Comissão' ?></h2>
        <a href="comissao_list.php" class="btn btn-secondary">← Voltar para Lista</a>
    </div>

    <div class="row">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><?= $isEdicao ? 'Editar Dados da Comissão' : 'Cadastrar Nova Comissão' ?></h5>
                </div>
                <div class="card-body">
                    <!-- Mensagens -->
                    <?php if (!empty($erro)): ?>
                        <div class="alert alert-danger"><?= htmlspecialchars($erro) ?></div>
                    <?php endif; ?>

                    <?php if (!empty($sucesso)): ?>
                        <div class="alert alert-success"><?= htmlspecialchars($sucesso) ?></div>
                    <?php endif; ?>

                    <form method="post">
                        <div class="mb-3">
                            <label for="valor" class="form-label">Percentual de Comissão (%)</label>
                            <div class="input-group">
                                <input type="number" 
                                       class="form-control" 
                                       id="valor" 
                                       name="valor" 
                                       step="0.01" 
                                       min="0" 
                                       max="100" 
                                       required 
                                       value="<?= htmlspecialchars($comissao['valor'] ?? $_POST['valor'] ?? '') ?>"
                                       placeholder="Ex: 5.00">
                                <span class="input-group-text">%</span>
                            </div>
                            <div class="form-text">Informe o percentual de comissão (de 0% a 100%)</div>
                        </div>

                        <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                            <a href="comissao_list.php" class="btn btn-secondary">Cancelar</a>
                            <button type="submit" class="btn btn-primary">
                                <?= $isEdicao ? 'Atualizar' : 'Cadastrar' ?> Comissão
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <?php if ($isEdicao): ?>
            <div class="col-md-6">
                <!-- Informações de Uso -->
                <div class="card mb-3">
                    <div class="card-header">
                        <h5 class="mb-0">Informações de Uso</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-6">
                                <h6>Vendas Usando</h6>
                                <h4 class="text-primary"><?= $comissao['total_vendas'] ?></h4>
                            </div>
                            <div class="col-6">
                                <h6>Valor Total</h6>
                                <h4 class="text-success">R$ <?= number_format($comissao['valor_total_vendas'], 2, ',', '.') ?></h4>
                            </div>
                        </div>
                        <hr>
                        <div class="text-center">
                            <h6>Comissão Estimada Total</h6>
                            <h3 class="text-info">R$ <?= number_format(($comissao['valor_total_vendas'] * $comissao['valor']) / 100, 2, ',', '.') ?></h3>
                        </div>
                    </div>
                </div>

                <!-- Últimas Vendas -->
                <?php if (count($vendas_usando) > 0): ?>
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Últimas Vendas (<?= $comissao['total_vendas'] > 5 ? '5 de ' . $comissao['total_vendas'] : $comissao['total_vendas'] ?>)</h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Cliente</th>
                                            <th>Data</th>
                                            <th>Valor</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($vendas_usando as $venda): ?>
                                            <tr>
                                                <td>
                                                    <a href="vendas_view.php?id=<?= $venda['id_venda'] ?>" class="text-decoration-none">
                                                        #<?= $venda['id_venda'] ?>
                                                    </a>
                                                </td>
                                                <td><?= htmlspecialchars($venda['cliente']) ?></td>
                                                <td><?= date('d/m/Y', strtotime($venda['dt_venda'])) ?></td>
                                                <td>R$ <?= number_format($venda['vlr_total'], 2, ',', '.') ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <?php if ($comissao['total_vendas'] > 5): ?>
                                <div class="text-center mt-2">
                                    <small class="text-muted">Mostrando apenas as 5 mais recentes</small>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="col-md-6">
                <!-- Informações sobre Comissões -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Sobre as Comissões</h5>
                    </div>
                    <div class="card-body">
                        <h6>Como funciona?</h6>
                        <ul>
                            <li>As comissões são calculadas sobre o valor parcelado (valor total - entrada)</li>
                            <li>Cada venda pode ter uma comissão diferente</li>
                            <li>O percentual é aplicado em cada parcela individualmente</li>
                            <li>Você pode cadastrar diferentes níveis de comissão</li>
                        </ul>

                        <h6 class="mt-3">Dicas:</h6>
                        <ul>
                            <li>Comissões comuns: 2%, 3%, 4%, 5%</li>
                            <li>Para vendedores mais experientes, use percentuais maiores</li>
                            <li>Considere o tipo de produto na definição da comissão</li>
                        </ul>

                        <?php
                        // Buscar comissões existentes
                        $sqlExistentes = "SELECT valor FROM comissao ORDER BY valor";
                        $stmtExistentes = $pdo->prepare($sqlExistentes);
                        $stmtExistentes->execute();
                        $comissoes_existentes = $stmtExistentes->fetchAll();
                        ?>

                        <?php if (count($comissoes_existentes) > 0): ?>
                            <h6 class="mt-3">Comissões já cadastradas:</h6>
                            <div class="d-flex flex-wrap gap-2">
                                <?php foreach ($comissoes_existentes as $ce): ?>
                                    <span class="badge bg-secondary"><?= number_format($ce['valor'], 2, ',', '.') ?>%</span>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once '../views/footer.php'; ?>