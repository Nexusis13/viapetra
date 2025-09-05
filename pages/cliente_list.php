<?php


require_once '../config/config.php';
require_once '../views/header.php';

// PROCESSAMENTO DE ATIVAÇÃO/INATIVAÇÃO
if (isset($_GET['id'], $_GET['acao'])) {
    $client_id = (int) $_GET['id'];
    if ($_GET['acao'] === 'inativar') {
        $sql = "UPDATE clientes SET status = 0 WHERE client_id = ?";
        $msg = "Cliente inativado com sucesso!";
    } elseif ($_GET['acao'] === 'ativar') {
        $sql = "UPDATE clientes SET status = 1 WHERE client_id = ?";
        $msg = "Cliente ativado com sucesso!";
    }
    if (isset($sql)) {
        $stmt = $pdo->prepare($sql);
        if ($stmt->execute([$client_id])) {
            $sucesso_msg = $msg;
        } else {
            $erro_msg = "Erro ao atualizar status do cliente.";
        }
        // Redireciona para evitar reenvio do GET ao atualizar/voltar
        header("Location: cliente_list.php");
        exit;
    }
}

// CONFIGURAÇÕES DE FILTRO E PAGINAÇÃO
$registros_por_pagina = isset($_GET['limit']) ? (int) $_GET['limit'] : 25;
$pagina_atual = isset($_GET['page']) ? (int) $_GET['page'] : 1;
$offset = ($pagina_atual - 1) * $registros_por_pagina;

// FILTROS
$filtros = [];
$params = [];

if (!empty($_GET['search'])) {
    $filtros[] = "(c.nome LIKE ? OR c.documento LIKE ? OR c.telefone LIKE ?)";
    $search_term = '%' . $_GET['search'] . '%';
    $params = array_merge($params, [$search_term, $search_term, $search_term]);
}

$where_clause = !empty($filtros) ? ' WHERE ' . implode(' AND ', $filtros) : '';

// CONTAR TOTAL DE REGISTROS
$count_sql = "SELECT COUNT(*) as total FROM clientes c" . $where_clause;
$count_stmt = $pdo->prepare($count_sql);
$count_stmt->execute($params);
$total_registros = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'];
$total_pages = ceil($total_registros / $registros_por_pagina);


// BUSCAR CLIENTES COM FILTROS E PAGINAÇÃO
$clientes_sql = "SELECT 
    c.client_id,
    c.tipo,
    c.documento,
    c.nome,
    c.dt_nascimento,
    c.telefone,
    c.endereco,
    c.end_obra,
    c.status,
    c.email,
    c.cep_endereco,
    c.cep_obra
    FROM clientes c
    " . $where_clause . "
    ORDER BY c.nome ASC
    LIMIT $registros_por_pagina OFFSET $offset";

$stmt = $pdo->prepare($clientes_sql);
$stmt->execute($params);
$clientes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// FORMATAR O DOCUMENTO(CPF/CNPJ)
if (!function_exists('formatarDocumento')) {
    function formatarDocumento($doc) {
        $doc = preg_replace('/\D/', '', $doc);
        if (strlen($doc) === 11) {
            // CPF: 000.000.000-00
            return preg_replace("/(\d{3})(\d{3})(\d{3})(\d{2})/", "$1.$2.$3-$4", $doc);
        } elseif (strlen($doc) === 14) {
            // CNPJ: 00.000.000/0000-00
            return preg_replace("/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/", "$1.$2.$3/$4-$5", $doc);
        }
        return htmlspecialchars($doc);
    }
}

?>

<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Lista de Clientes - Sistema Nexus</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">

    <style>
        .status-badge {
            font-size: 0.75rem;
            padding: 0.25rem 0.5rem;
            border-radius: 0.375rem;
        }

        .card {
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
            border: none;
            border-radius: 0.5rem;
        }

        .table th {
            border-top: none;
            font-weight: 600;
            color: #495057;
        }

        .btn-action {
            font-size: 0.875rem;
            padding: 0.25rem 0.5rem;
        }
    </style>
</head>

<body>

    <?php if (isset($sucesso_msg)): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="bi bi-check-circle me-2"></i>
            <?= $sucesso_msg ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if (isset($erro_msg)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-triangle me-2"></i>
            <?= $erro_msg ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="container-fluid py-4">
        <!-- Cabeçalho da Página -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h2 class="mb-1">
                            <i class="bi bi-people text-primary me-2"></i>
                            Lista de Clientes
                        </h2>
                        <p class="text-muted mb-0">Gerencie os clientes cadastrados no sistema</p>
                    </div>
                    <div>
                        <a href="cliente_form.php" class="btn btn-primary">
                            <i class="bi bi-person-plus me-2"></i>
                            Novo Cliente
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Estatísticas Rápidas -->
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="card bg-primary text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h5 class="card-title mb-1">Total de Clientes</h5>
                                <h3 class="mb-0"><?php
                                $total_clientes = $pdo->query("SELECT COUNT(*) FROM clientes")->fetchColumn();
                                echo number_format($total_clientes);
                                ?></h3>
                            </div>
                            <i class="bi bi-people" style="font-size: 2.5rem; opacity: 0.7;"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filtros -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="bi bi-funnel me-2"></i>
                            Filtros de Busca
                        </h5>
                    </div>
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-6">
                                <label for="search" class="form-label">Buscar por Nome, Documento ou Telefone</label>
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="bi bi-search"></i>
                                    </span>
                                    <input type="text" class="form-control" id="search" name="search"
                                        value="<?= htmlspecialchars($_GET['search'] ?? '') ?>"
                                        placeholder="Digite para buscar...">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <label for="limit" class="form-label">Registros por Página</label>
                                <select class="form-select" name="limit" id="limit">
                                    <option value="15" <?= $registros_por_pagina == 15 ? 'selected' : '' ?>>15</option>
                                    <option value="25" <?= $registros_por_pagina == 25 ? 'selected' : '' ?>>25</option>
                                    <option value="50" <?= $registros_por_pagina == 50 ? 'selected' : '' ?>>50</option>
                                    <option value="100" <?= $registros_por_pagina == 100 ? 'selected' : '' ?>>100</option>
                                </select>
                            </div>
                            <div class="col-md-3 d-flex align-items-end">
                                <div class="btn-group w-100" role="group">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="bi bi-search me-1"></i>Filtrar
                                    </button>
                                    <a href="cliente_list.php" class="btn btn-outline-secondary">
                                        <i class="bi bi-arrow-counterclockwise"></i>
                                    </a>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Lista de Clientes -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">
                            <i class="bi bi-table me-2"></i>
                            Lista de Clientes
                            <span class="badge bg-secondary ms-2"><?= $total_registros ?> registros</span>
                        </h5>
                        <div class="d-flex gap-2">
                            <a href="relatorio_clientes_get.php" target="_blank" class="btn btn-outline-secondary btn-sm">
                                <i class="bi bi-printer"></i> Imprimir/Salvar PDF
                            </a>
                            <a href="cliente_form.php" class="btn btn-primary btn-sm">
                                <i class="bi bi-person-plus me-1"></i>Novo Cliente
                            </a>
                        </div>
                    </div>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($clientes)): ?>
                        <div class="text-center py-5">
                            <i class="bi bi-people text-muted" style="font-size: 4rem; opacity: 0.3;"></i>
                            <h4 class="text-muted mt-3">Nenhum cliente encontrado</h4>
                            <p class="text-muted">Tente ajustar os filtros ou <a href="cliente_form.php">cadastrar um novo
                                    cliente</a></p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>ID</th>
                                        <th>Tipo</th>
                                        <th>Documento</th>
                                        <th>Nome</th>
                                        <th>Data de Nascimento</th>
                                        <th>Telefone</th>
                                        <th>Email</th>
                                        <th>Endereço</th>
                                        <th>Endereço da Obra</th>
                                        <th>Status</th>
                                        <th width="140">Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($clientes as $cliente): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($cliente['client_id']) ?></td>
                                            <td><?= htmlspecialchars($cliente['tipo']) ?></td>
                                            <td><?= formatarDocumento($cliente['documento']) ?></td>
                                            <td><?= htmlspecialchars($cliente['nome']) ?></td>
                                            <td><?= !empty($cliente['dt_nascimento']) ? date('d/m/Y', strtotime($cliente['dt_nascimento'])) : '' ?>
                                            </td>
                                            <td><?= htmlspecialchars($cliente['telefone']) ?></td>
                                            <td><?= htmlspecialchars($cliente['email'] ?? '') ?></td>
                                            <td><?= htmlspecialchars($cliente['endereco']) ?></td>
                                            <td><?= htmlspecialchars($cliente['end_obra']) ?></td>
                                            <td>
                                                <?php if ($cliente['status'] == 1): ?>
                                                    <span class="badge bg-success status-badge">Ativo</span>
                                                <?php else: ?>
                                                    <span class="badge bg-danger status-badge">Inativo</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>

                                                <a href="cliente_form.php?id=<?= $cliente['client_id'] ?>"
                                                    class="btn btn-sm btn-success" title="Editar Cliente">Editar
                                                </a>

                                                <?php if ($cliente['status'] == 1): ?>
                                                    <a href="?id=<?= $cliente['client_id'] ?>&acao=inativar"
                                                        class="btn btn-sm btn-warning">
                                                        <i class="bi btn-sm btn-danger"></i> Inativar
                                                    </a>
                                                <?php else: ?>
                                                    <a href="?id=<?= $cliente['client_id'] ?>&acao=ativar"
                                                        class="btn btn-sm btn-success">
                                                        <i class="bi btn-sm btn-success"></i> Ativar
                                                    </a>
                                                <?php endif; ?>


                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Paginação -->
                <?php if ($total_pages > 1): ?>
                    <div class="card-footer">
                        <div class="d-flex justify-content-between align-items-center">
                            <div class="text-muted">
                                Exibindo <?= ($offset + 1) ?> a
                                <?= min($offset + $registros_por_pagina, $total_registros) ?>
                                de <?= $total_registros ?> registros
                            </div>

                            <nav>
                                <ul class="pagination pagination-sm mb-0">
                                    <?php if ($pagina_atual > 1): ?>
                                        <li class="page-item">
                                            <a class="page-link"
                                                href="?<?= http_build_query(array_merge($_GET, ['page' => 1])) ?>">
                                                <i class="bi bi-chevron-double-left"></i>
                                            </a>
                                        </li>
                                        <li class="page-item">
                                            <a class="page-link"
                                                href="?<?= http_build_query(array_merge($_GET, ['page' => $pagina_atual - 1])) ?>">
                                                <i class="bi bi-chevron-left"></i>
                                            </a>
                                        </li>
                                    <?php endif; ?>

                                    <?php
                                    $inicio = max(1, $pagina_atual - 2);
                                    $fim = min($total_pages, $pagina_atual + 2);

                                    for ($i = $inicio; $i <= $fim; $i++):
                                        ?>
                                        <li class="page-item <?= $i == $pagina_atual ? 'active' : '' ?>">
                                            <a class="page-link"
                                                href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>">
                                                <?= $i ?>
                                            </a>
                                        </li>
                                    <?php endfor; ?>

                                    <?php if ($pagina_atual < $total_pages): ?>
                                        <li class="page-item">
                                            <a class="page-link"
                                                href="?<?= http_build_query(array_merge($_GET, ['page' => $pagina_atual + 1])) ?>">
                                                <i class="bi bi-chevron-right"></i>
                                            </a>
                                        </li>
                                        <li class="page-item">
                                            <a class="page-link"
                                                href="?<?= http_build_query(array_merge($_GET, ['page' => $total_pages])) ?>">
                                                <i class="bi bi-chevron-double-right"></i>
                                            </a>
                                        </li>
                                    <?php endif; ?>
                                </ul>
                            </nav>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    </div>

    <!-- Modal de Confirmação de Exclusão -->
    <div class="modal fade" id="modalExcluir" tabindex="-1" aria-labelledby="modalExcluirLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title" id="modalExcluirLabel">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        Confirmar Exclusão
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-3">Deseja realmente excluir o cliente <strong id="nomeClienteExcluir"></strong>?</p>
                    <div class="alert alert-warning d-flex align-items-center">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        <div>
                            <strong>Atenção:</strong> Esta ação não pode ser desfeita.
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <form method="POST" class="d-inline">
                        <input type="hidden" name="client_id" id="clienteIdExcluir">
                        <input type="hidden" name="excluir_cliente" value="1">
                        <button type="submit" class="btn btn-danger">
                            <i class="bi bi-trash me-1"></i>Excluir Paciente
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <?php require_once '../views/footer.php'; ?>

    <script>
        // Confirmação de exclusão
        function confirmarExclusao(id, nome) {
            document.getElementById('clienteIdExcluir').value = id;
            document.getElementById('nomeClienteExcluir').textContent = nome;

            const modal = new bootstrap.Modal(document.getElementById('modalExcluir'));
            modal.show();
        }



        // Auto-submit no filtro de quantidade por página
        const limitEl = document.getElementById('limit');
        if (limitEl) {
            limitEl.addEventListener('change', function () {
                this.form.submit();
            });
        }

        // Busca em tempo real com debounce
        let searchTimeout;
        const searchEl = document.getElementById('search');
        if (searchEl) {
            searchEl.addEventListener('input', function () {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(() => {
                    this.form.submit();
                }, 800);
            });
        }

        // Mostrar toast de sucesso se houver
        <?php if (isset($sucesso_msg)): ?>
            setTimeout(() => {
                showNotification('<?= addslashes($sucesso_msg) ?>', 'success');
            }, 500);
        <?php endif; ?>

        // Função para mostrar notificação
        function showNotification(message, type = 'info') {
            const alertClass = {
                'success': 'alert-success',
                'error': 'alert-danger',
                'warning': 'alert-warning',
                'info': 'alert-info'
            };

            const alert = document.createElement('div');
            alert.className = `alert ${alertClass[type]} alert-dismissible fade show position-fixed`;
            alert.style.cssText = 'top: 100px; right: 20px; z-index: 9999; min-width: 300px;';
            alert.innerHTML = `
        <i class="bi bi-check-circle me-2"></i>
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;

            document.body.appendChild(alert);

            // Auto remove após 5 segundos
            setTimeout(() => {
                if (alert.parentNode) {
                    alert.remove();
                }
            }, 5000);
        }

        function atualizarStatusCliente(id, acao, btn) {
            if (!confirm('Tem certeza que deseja ' + (acao === 'ativar' ? 'ativar' : 'inativar') + ' este cliente?')) return;
            btn.disabled = true;
            fetch('cliente_status.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'id=' + id + '&acao=' + acao
            })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    } else {
                        alert('Erro ao atualizar status!');
                        btn.disabled = false;
                    }
                })
                .catch(() => {
                    alert('Erro de comunicação!');
                    btn.disabled = false;
                });
        }

    </script>
</body>

</html>