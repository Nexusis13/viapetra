<?php
require_once '../config/protect.php';
require_once '../config/config.php';

$id = $_GET['id'] ?? null;
$inativar = $_GET['inativar'] ?? null;
$ativar = $_GET['ativar'] ?? null;
$mensagem = '';
$modo_edicao = false;

// Inativar
if ($id && $inativar) {
    $pdo->prepare("UPDATE usuarios SET ativo = 0 WHERE id = ?")->execute([$id]);
    header("Location: listauser.php");
    exit;
}

// Ativar
if ($id && $ativar) {
    $pdo->prepare("UPDATE usuarios SET ativo = 1 WHERE id = ?")->execute([$id]);
    header("Location: listauser.php");
    exit;
}

// Carrega dados para edição
if ($id && !$inativar && !$ativar) {
    $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE id = ?");
    $stmt->execute([$id]);
    $usuario = $stmt->fetch();
    $modo_edicao = true;
}

// Salvar (cadastro ou edição)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = $_POST['nome'] ?? '';
    $login = $_POST['login'] ?? '';
    $senha = $_POST['senha'] ?? '';
    $tipo = $_POST['tipo'] ?? '';
    $ativo = 1;

    if ($modo_edicao) {
        if ($senha) {
            $stmt = $pdo->prepare("UPDATE usuarios SET nome = ?, login = ?, senha = ?, tipo = ? WHERE id = ?");
            $stmt->execute([$nome, $login, password_hash($senha, PASSWORD_DEFAULT), $tipo, $id]);
        } else {
            $stmt = $pdo->prepare("UPDATE usuarios SET nome = ?, login = ?, tipo = ? WHERE id = ?");
            $stmt->execute([$nome, $login, $tipo, $id]);
        }
        $mensagem = 'Usuário atualizado com sucesso!';
    } else {
        $stmt = $pdo->prepare("INSERT INTO usuarios (nome, login, senha, tipo, ativo) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$nome, $login, password_hash($senha, PASSWORD_DEFAULT), $tipo, $ativo]);
        $mensagem = 'Usuário cadastrado com sucesso!';
    }
}
require_once '../views/header.php';
?>

<div class="container mt-4">
    <h2><?= $modo_edicao ? 'Editar' : 'Cadastrar' ?> Usuário</h2>

    <?php if ($mensagem): ?>
        <div class="alert alert-success"><?= $mensagem ?></div>
    <?php endif; ?>

    <form method="post" class="row g-3">
        <div class="col-md-6">
            <label class="form-label">Nome</label>
            <input type="text" name="nome" class="form-control" required value="<?= htmlspecialchars($usuario['nome'] ?? '') ?>">
        </div>
        <div class="col-md-6">
            <label class="form-label">Login</label>
            <input type="text" name="login" class="form-control" required value="<?= htmlspecialchars($usuario['login'] ?? '') ?>">
        </div>
        <div class="col-md-6">
            <label class="form-label">Senha <?= $modo_edicao ? '(deixe em branco para manter)' : '' ?></label>
            <input type="password" name="senha" class="form-control" <?= $modo_edicao ? '' : 'required' ?>>
        </div>
        <div class="col-md-6">
            <label class="form-label">Tipo</label>
            <select name="tipo" class="form-select" required>
                <option value="">Selecione...</option>
                <option value="admin" <?= (isset($usuario['admin']) && $usuario['admin'] == 'admin') ? 'selected' : '' ?>>Administrador</option>
                <option value="user" <?= (isset($usuario['user']) && $usuario['user'] == 'user') ? 'selected' : '' ?>>Usuario</option>
            </select>
        </div>
        <div class="col-12">
            <button class="btn btn-primary" type="submit"><?= $modo_edicao ? 'Atualizar' : 'Cadastrar' ?></button>
            <a href="listauser.php" class="btn btn-secondary">Voltar</a>
        </div>
    </form>
</div>

<?php require_once '../views/footer.php'; ?>
