<?php
require_once '../config/protect.php';
require_once '../config/config.php';

$busca = $_GET['busca'] ?? '';
$sql = "SELECT * FROM usuarios WHERE nome LIKE ? OR login LIKE ? ORDER BY nome ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute(["%$busca%", "%$busca%"]);
$usuarios = $stmt->fetchAll();

require_once '../views/header.php';
?>

<div class="container mt-4">
    <h2>Usuários do Sistema</h2>

    <form method="get" class="row g-3 mb-3">
        <div class="col-md-6">
            <input type="text" name="busca" class="form-control" placeholder="Buscar por nome ou login" value="<?= htmlspecialchars($busca) ?>">
        </div>
        <div class="col-auto">
            <button class="btn btn-primary" type="submit">Buscar</button>
            <a href="user_form.php" class="btn btn-success">Novo Usuário</a>
        </div>
    </form>

    <table class="table table-bordered table-hover">
        <thead class="table-light">
            <tr>
                <th>Nome</th>
                <th>Login</th>
                <th>Tipo</th>
                <th>Ativo</th>
                <th>Ações</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($usuarios as $user): ?>
                <tr>
                    <td><?= htmlspecialchars($user['nome']) ?></td>
                    <td><?= htmlspecialchars($user['login']) ?></td>
                    <td><?= ucfirst($user['tipo']) ?></td>
                    <td><?= $user['ativo'] ? 'Sim' : 'Não' ?></td>
                    <td>
                        <a href="user_form.php?id=<?= $user['id'] ?>" class="btn btn-sm btn-warning">Editar</a>
                        <?php if ($user['ativo']): ?>
                            <a href="user_form.php?id=<?= $user['id'] ?>&inativar=1" class="btn btn-sm btn-danger" onclick="return confirm('Deseja inativar este usuário?')">Inativar</a>
                        <?php else: ?>
                            <a href="user_form.php?id=<?= $user['id'] ?>&ativar=1" class="btn btn-sm btn-success" onclick="return confirm('Deseja reativar este usuário?')">Ativar</a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php require_once '../views/footer.php'; ?>
