<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$tipo_usuario = $_SESSION['usuario_tipo'] ?? '';
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Painel - Comissões</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
    <div class="container-fluid">
        <a class="navbar-brand" href="dashboard.php">Painel Comissões</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto">
                <li class="nav-item">
                    <a class="nav-link" href="dashboard.php"><i class="bi bi-house-door"></i> Início</a>
                </li>
                <li class="nav-item">
                        <a class="nav-link" href="vendedor_list.php"><i class="bi bi-people"></i> Gerenciar Vendedores</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="vendas_list.php"><i class="bi bi-people"></i> Gerenciar Vendas</a>
                    </li>
				<li class="nav-item">
                        <a class="nav-link" href="custos_list.php"><i class="bi bi-bar-chart-steps"></i> Gerenciar Custos</a>
                    </li>
                <?php if ($tipo_usuario === 'admin'): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="comissao_list.php"><i class="bi bi-people"></i> Gerenciar Comissão</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="listauser.php"><i class="bi bi-person-bounding-box"></i> Gerenciar User</a>
                    </li>
				<li class="nav-item">
                        <a class="nav-link" href="gerenciar_boletos.php"><i class="bi bi-person-bounding-box"></i> Gerenciar Boletos</a>
                    </li>
				<li class="nav-item">
                    <a class="nav-link" href="relatorio_comissao.php"><i class="bi bi-clipboard2-data"></i> Comissão a Pagar</a>
                 </li>
<!-- 
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="comissaoDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="bi bi-kanban"></i> Esteira de <br>Vendas
                    </a>
                    <ul class="dropdown-menu" aria-labelledby="comissaoDropdown">
                        <li><a class="dropdown-item" href="cliente_obra.php">Cadastro de Obra</a></li>
                        
                </ul> -->
                </li>
                <?php endif; ?>
            </ul>

            <span class="navbar-text me-3 text-white">
                <?= htmlspecialchars($_SESSION['usuario_nome']) ?> (<?= htmlspecialchars($tipo_usuario) ?>)
            </span>
            <a href="../auth/logout.php" class="btn btn-outline-light btn-sm">Sair</a>
        </div>
    </div>
</nav>
