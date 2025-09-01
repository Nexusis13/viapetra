<?php
require_once '../config/config.php';
require_once '../views/header.php';
redirect_if_not_logged_in();

// PROCESSAMENTO DE EXCLUSÃO
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['excluir_paciente'])) {
    $paciente_id = (int) $_POST['paciente_id'];
    
    // Verificar se o paciente tem consultas vinculadas
    $consultas_check = "SELECT COUNT(*) as total FROM consultas WHERE id_paciente = ?";
    $stmt_check = $mysqli->prepare($consultas_check);
    $stmt_check->bind_param('i', $paciente_id);
    $stmt_check->execute();
    $result_check = $stmt_check->get_result()->fetch_assoc();
    
    if ($result_check['total'] > 0) {
        $erro_msg = "Não é possível excluir este paciente pois ele possui consultas vinculadas.";
    } else {
        $delete_sql = "DELETE FROM pacientes WHERE id = ?";
        $stmt_delete = $mysqli->prepare($delete_sql);
        $stmt_delete->bind_param('i', $paciente_id);
        
        if ($stmt_delete->execute()) {
            $sucesso_msg = "Paciente excluído com sucesso!";
        } else {
            $erro_msg = "Erro ao excluir paciente: " . $mysqli->error;
        }
    }
}

// CONFIGURAÇÕES DE FILTRO E PAGINAÇÃO
$registros_por_pagina = isset($_GET['limit']) ? (int)$_GET['limit'] : 25;
$pagina_atual = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($pagina_atual - 1) * $registros_por_pagina;

// FILTROS
$filtros = [];
$params = [];
$param_types = '';

if (!empty($_GET['search'])) {
    $filtros[] = "(p.nome LIKE ? OR p.plano LIKE ? OR p.telefone LIKE ? OR p.email LIKE ?)";
    $search_term = '%' . $_GET['search'] . '%';
    $params = array_merge($params, [$search_term, $search_term, $search_term, $search_term]);
    $param_types .= 'ssss';
}

if (!empty($_GET['plano'])) {
    $filtros[] = "p.plano LIKE ?";
    $params[] = '%' . $_GET['plano'] . '%';
    $param_types .= 's';
}

$where_clause = !empty($filtros) ? ' WHERE ' . implode(' AND ', $filtros) : '';

// CONTAR TOTAL DE REGISTROS
$count_sql = "SELECT COUNT(*) as total FROM pacientes p" . $where_clause;
$count_stmt = $mysqli->prepare($count_sql);
if (!empty($params)) {
    $count_stmt->bind_param($param_types, ...$params);
}
$count_stmt->execute();
$total_registros = $count_stmt->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total_registros / $registros_por_pagina);

// BUSCAR PACIENTES COM FILTROS E PAGINAÇÃO
$pacientes_sql = "SELECT 
    p.id,
    p.nome,
    p.plano,
    p.telefone,
    p.email,
    COUNT(c.id) as total_consultas,
    SUM(CASE WHEN c.status = 'finalizada' THEN 1 ELSE 0 END) as consultas_finalizadas,
    MAX(c.data_horario) as ultima_consulta
    FROM pacientes p
    LEFT JOIN consultas c ON p.id = c.id_paciente
    " . $where_clause . "
    GROUP BY p.id, p.nome, p.plano, p.telefone, p.email
    ORDER BY p.nome ASC
    LIMIT ? OFFSET ?";

$params_final = $params;
$params_final[] = $registros_por_pagina;
$params_final[] = $offset;
$param_types_final = $param_types . 'ii';

$stmt = $mysqli->prepare($pacientes_sql);
if (!empty($params_final)) {
    $stmt->bind_param($param_types_final, ...$params_final);
} else {
    $stmt->bind_param('ii', $registros_por_pagina, $offset);
}

$stmt->execute();
$resultado = $stmt->get_result();
$pacientes = $resultado->fetch_all(MYSQLI_ASSOC);

// ESTATÍSTICAS RÁPIDAS
$stats_sql = "SELECT 
    (SELECT COUNT(*) FROM pacientes) as total_pacientes,
    (SELECT COUNT(DISTINCT plano) FROM pacientes WHERE plano IS NOT NULL AND plano != '') as total_planos,
    (SELECT COUNT(*) FROM consultas WHERE data_horario >= DATE_SUB(NOW(), INTERVAL 30 DAY)) as consultas_30_dias";

$stats_result = $mysqli->query($stats_sql);
$stats = $stats_result->fetch_assoc();

// BUSCAR PLANOS PARA FILTRO
$planos_sql = "SELECT DISTINCT plano FROM pacientes WHERE plano IS NOT NULL AND plano != '' ORDER BY plano";
$planos_result = $mysqli->query($planos_sql);
$planos = $planos_result->fetch_all(MYSQLI_ASSOC);


?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Lista de Pacientes - Sistema Nexus</title>
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
                        Lista de Pacientes
                    </h2>
                    <p class="text-muted mb-0">Gerencie os pacientes cadastrados no sistema</p>
                </div>
                <div>
                    <a href="paciente_form.php" class="btn btn-primary">
                        <i class="bi bi-person-plus me-2"></i>
                        Novo Paciente
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
                            <h5 class="card-title mb-1">Total de Pacientes</h5>
                            <h3 class="mb-0"><?= number_format($stats['total_pacientes']) ?></h3>
                        </div>
                        <i class="bi bi-people" style="font-size: 2.5rem; opacity: 0.7;"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card bg-info text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="card-title mb-1">Planos Diferentes</h5>
                            <h3 class="mb-0"><?= number_format($stats['total_planos']) ?></h3>
                        </div>
                        <i class="bi bi-card-list" style="font-size: 2.5rem; opacity: 0.7;"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="card-title mb-1">Consultas (30 dias)</h5>
                            <h3 class="mb-0"><?= number_format($stats['consultas_30_dias']) ?></h3>
                        </div>
                        <i class="bi bi-calendar-check" style="font-size: 2.5rem; opacity: 0.7;"></i>
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
                        <div class="col-md-4">
                            <label for="search" class="form-label">Buscar por Nome, Telefone ou Email</label>
                            <div class="input-group">
                                <span class="input-group-text">
                                    <i class="bi bi-search"></i>
                                </span>
                                <input type="text" 
                                       class="form-control" 
                                       id="search" 
                                       name="search" 
                                       value="<?= htmlspecialchars($_GET['search'] ?? '') ?>"
                                       placeholder="Digite para buscar...">
                            </div>
                        </div>

                        <div class="col-md-3">
                            <label for="plano" class="form-label">Filtrar por Plano</label>
                            <select class="form-select" name="plano" id="plano">
                                <option value="">Todos os Planos</option>
                                <?php foreach ($planos as $plano): ?>
                                    <option value="<?= htmlspecialchars($plano['plano']) ?>" 
                                            <?= ($_GET['plano'] ?? '') == $plano['plano'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($plano['plano']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
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

                        <div class="col-md-2 d-flex align-items-end">
                            <div class="btn-group w-100" role="group">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-search me-1"></i>Filtrar
                                </button>
                                <a href="pacientes_list.php" class="btn btn-outline-secondary">
                                    <i class="bi bi-arrow-counterclockwise"></i>
                                </a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Lista de Pacientes -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="bi bi-table me-2"></i>
                        Lista de Pacientes
                        <span class="badge bg-secondary ms-2"><?= $total_registros ?> registros</span>
                    </h5>
                    <div class="d-flex gap-2">
                        <button class="btn btn-outline-success btn-sm" onclick="exportarDados()">
                            <i class="bi bi-download me-1"></i>Exportar
                        </button>
                        <a href="paciente_form.php" class="btn btn-primary btn-sm">
                            <i class="bi bi-person-plus me-1"></i>Novo Paciente
                        </a>
                    </div>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($pacientes)): ?>
                    <div class="text-center py-5">
                        <i class="bi bi-people text-muted" style="font-size: 4rem; opacity: 0.3;"></i>
                        <h4 class="text-muted mt-3">Nenhum paciente encontrado</h4>
                        <p class="text-muted">Tente ajustar os filtros ou <a href="paciente_form.php">cadastrar um novo paciente</a></p>
                    </div>
                    <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th><i class="bi bi-person me-1"></i>Nome</th>
                                    <th><i class="bi bi-card-text me-1"></i>Plano de Saúde</th>
                                    <th><i class="bi bi-telephone me-1"></i>Telefone</th>
                                    <th><i class="bi bi-envelope me-1"></i>Email</th>
                                    <th><i class="bi bi-calendar-check me-1"></i>Consultas</th>
                                    <th><i class="bi bi-clock me-1"></i>Última Consulta</th>
                                    <th width="120"><i class="bi bi-gear me-1"></i>Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($pacientes as $paciente): ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="me-3">
                                                <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center" 
                                                     style="width: 35px; height: 35px; font-size: 0.8rem; font-weight: bold;">
                                                    <?php 
                                                    $nomes = explode(' ', $paciente['nome']);
                                                    echo strtoupper(substr($nomes[0], 0, 1) . (isset($nomes[1]) ? substr($nomes[1], 0, 1) : ''));
                                                    ?>
                                                </div>
                                            </div>
                                            <div>
                                                <strong><?= htmlspecialchars($paciente['nome']) ?></strong>
                                                <small class="d-block text-muted">ID: <?= $paciente['id'] ?></small>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if (!empty($paciente['plano'])): ?>
                                            <span class="badge bg-info"><?= htmlspecialchars($paciente['plano']) ?></span>
                                        <?php else: ?>
                                            <span class="text-muted">Não informado</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (!empty($paciente['telefone'])): ?>
                                            <a href="tel:<?= $paciente['telefone'] ?>" class="text-decoration-none">
                                                <i class="bi bi-telephone me-1"></i><?= $paciente['telefone'] ?>
                                            </a>
                                        <?php else: ?>
                                            <span class="text-muted">Não informado</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (!empty($paciente['email'])): ?>
                                            <a href="mailto:<?= $paciente['email'] ?>" class="text-decoration-none">
                                                <i class="bi bi-envelope me-1"></i><?= htmlspecialchars($paciente['email']) ?>
                                            </a>
                                        <?php else: ?>
                                            <span class="text-muted">Não informado</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <span class="badge bg-primary"><?= $paciente['total_consultas'] ?></span>
                                            <?php if ($paciente['consultas_finalizadas'] > 0): ?>
                                                <span class="badge bg-success"><?= $paciente['consultas_finalizadas'] ?> finalizadas</span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if (!empty($paciente['ultima_consulta'])): ?>
                                            <?= date('d/m/Y H:i', strtotime($paciente['ultima_consulta'])) ?>
                                        <?php else: ?>
                                            <span class="text-muted">Nenhuma consulta</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm" role="group">
                                            <a href="paciente_form.php?id=<?= $paciente['id'] ?>" 
                                               class="btn btn-outline-primary btn-action"
                                               title="Editar Paciente">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                            <a href="agenda.php?search=<?= urlencode($paciente['nome']) ?>" 
                                               class="btn btn-outline-info btn-action"
                                               title="Ver Consultas">
                                                <i class="bi bi-calendar-check"></i>
                                            </a>
                                            <button type="button" 
                                                    class="btn btn-outline-danger btn-action"
                                                    title="Excluir Paciente"
                                                    onclick="confirmarExclusao(<?= $paciente['id'] ?>, '<?= htmlspecialchars($paciente['nome'], ENT_QUOTES) ?>')">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </div>
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
                            Exibindo <?= ($offset + 1) ?> a <?= min($offset + $registros_por_pagina, $total_registros) ?> 
                            de <?= $total_registros ?> registros
                        </div>
                        
                        <nav>
                            <ul class="pagination pagination-sm mb-0">
                                <?php if ($pagina_atual > 1): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => 1])) ?>">
                                            <i class="bi bi-chevron-double-left"></i>
                                        </a>
                                    </li>
                                    <li class="page-item">
                                        <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $pagina_atual - 1])) ?>">
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
                                        <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>">
                                            <?= $i ?>
                                        </a>
                                    </li>
                                <?php endfor; ?>

                                <?php if ($pagina_atual < $total_pages): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $pagina_atual + 1])) ?>">
                                            <i class="bi bi-chevron-right"></i>
                                        </a>
                                    </li>
                                    <li class="page-item">
                                        <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $total_pages])) ?>">
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
                <p class="mb-3">Deseja realmente excluir o paciente <strong id="nomePacienteExcluir"></strong>?</p>
                <div class="alert alert-warning d-flex align-items-center">
                    <i class="bi bi-exclamation-triangle me-2"></i>
                    <div>
                        <strong>Atenção:</strong> Esta ação não pode ser desfeita. 
                        Pacientes com consultas vinculadas não podem ser excluídos.
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <form method="POST" class="d-inline">
                    <input type="hidden" name="paciente_id" id="pacienteIdExcluir">
                    <input type="hidden" name="excluir_paciente" value="1">
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
    document.getElementById('pacienteIdExcluir').value = id;
    document.getElementById('nomePacienteExcluir').textContent = nome;
    
    const modal = new bootstrap.Modal(document.getElementById('modalExcluir'));
    modal.show();
}

// Exportar dados
function exportarDados() {
    const params = new URLSearchParams(window.location.search);
    params.set('export', '1');
    window.location.href = 'export_pacientes.php?' + params.toString();
}

// Auto-submit no filtro de plano
document.getElementById('plano').addEventListener('change', function() {
    this.form.submit();
});

document.getElementById('limit').addEventListener('change', function() {
    this.form.submit();
});

// Busca em tempo real com debounce
let searchTimeout;
document.getElementById('search').addEventListener('input', function() {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        this.form.submit();
    }, 800);
});

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
</script>

</body>
</html>