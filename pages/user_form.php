<?php

require_once '../config/protect.php';
require_once '../config/config.php';
require_once '../api/vendedores_get.php';

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
    $id_vendedor = !empty($_POST['id_vendedor']) ? (int)$_POST['id_vendedor'] : null;
    $ativo = 1;

    if ($modo_edicao) {
        if ($senha) {
            $stmt = $pdo->prepare("UPDATE usuarios SET nome = ?, login = ?, senha = ?, tipo = ?, id_vendedor = ? WHERE id = ?");
            $stmt->execute([$nome, $login, password_hash($senha, PASSWORD_DEFAULT), $tipo, $id_vendedor, $id]);
        } else {
            $stmt = $pdo->prepare("UPDATE usuarios SET nome = ?, login = ?, tipo = ?, id_vendedor = ? WHERE id = ?");
            $stmt->execute([$nome, $login, $tipo, $id_vendedor, $id]);
        }
        // Atualizar $usuario para manter seleção após update
        $usuario['nome'] = $nome;
        $usuario['login'] = $login;
        $usuario['tipo'] = $tipo;
        $usuario['id_vendedor'] = $id_vendedor;
        $mensagem = 'Usuário atualizado com sucesso!';
    } else {
        $stmt = $pdo->prepare("INSERT INTO usuarios (nome, login, senha, tipo, ativo, id_vendedor) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$nome, $login, password_hash($senha, PASSWORD_DEFAULT), $tipo, $ativo, $id_vendedor]);
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
                <option value="admin" <?= (isset($usuario['tipo']) && $usuario['tipo'] == 'admin') ? 'selected' : '' ?>>Administrador</option>
                <option value="user" <?= (isset($usuario['tipo']) && $usuario['tipo'] == 'user') ? 'selected' : '' ?>>Usuario</option>
            </select>
        </div>
        <div class="col-md-6">
            <label class="form-label">Vincular a Vendedor</label>
            <select name="id_vendedor" class="form-select">
                <option value="">Nenhum</option>
                <?php foreach ($vendedores as $vend): ?>
                    <option value="<?= $vend['id_vendedor'] ?>" <?= (isset($usuario['id_vendedor']) && $usuario['id_vendedor'] == $vend['id_vendedor']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($vend['nome']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-12">
            <button class="btn btn-primary" type="submit"><?= $modo_edicao ? 'Atualizar' : 'Cadastrar' ?></button>
            <a href="listauser.php" class="btn btn-secondary">Voltar</a>
        </div>
    </form>
</div>

<?php require_once '../views/footer.php'; ?>
