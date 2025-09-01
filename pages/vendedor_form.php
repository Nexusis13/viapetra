<?php
require_once '../config/protect.php';
require_once '../config/config.php';

$id = $_GET['id'] ?? null;
$inativar = $_GET['inativar'] ?? null;
$STATUS = $_GET['STATUS'] ?? null;
$mensagem = '';
$modo_edicao = false;

// Inativar vendedor
if ($id && $inativar) {
    $stmt = $pdo->prepare("UPDATE vendedores SET STATUS = 0 WHERE id_vendedor = ?");
    $stmt->execute([$id]);
    header("Location: vendedor_list.php");
    exit;
}

// Ativar vendedor
if ($id && $STATUS) {
    $stmt = $pdo->prepare("UPDATE vendedores SET STATUS = 1 WHERE id_vendedor = ?");
    $stmt->execute([$id]);
    header("Location: vendedor_list.php");
    exit;
}

// Buscar dados se for edição
if ($id && !$inativar && !$STATUS) {
    $stmt = $pdo->prepare("SELECT * FROM vendedores WHERE id_vendedor = ?");
    $stmt->execute([$id]);
    $vendedor = $stmt->fetch();
    if ($vendedor) {
        $modo_edicao = true;
    }
}

// Salvar vendedor (novo ou edição)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = $_POST['nome'] ?? '';
    $celular = $_POST['celular'] ?? '';
    $tipochave = $_POST['tipochave'] ?? '';
    $chave = $_POST['chave'] ?? '';
    $STATUS = 1;

    if ($modo_edicao) {
        $stmt = $pdo->prepare("UPDATE vendedores SET nome = ?, celular = ?, tipochave = ?, chave = ? WHERE id_vendedor = ?");
        $stmt->execute([$nome, $celular, $tipochave, $chave, $id]);
        $mensagem = 'Vendedor atualizado com sucesso!';
    } else {
        $stmt = $pdo->prepare("INSERT INTO vendedores (nome, celular, tipochave, chave, STATUS) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$nome, $celular, $tipochave, $chave, $STATUS]);
        $mensagem = 'Vendedor cadastrado com sucesso!';
    }
}

require_once '../views/header.php';
?>

<div class="container mt-4">
    <h2><?= $modo_edicao ? 'Editar' : 'Cadastrar' ?> Vendedor</h2>

    <?php if ($mensagem): ?>
        <div class="alert alert-success"><?= $mensagem ?></div>
    <?php endif; ?>

    <form method="post" class="row g-3">
        <div class="col-md-6">
            <label class="form-label">Nome do Vendedor</label>
            <input type="text" name="nome" class="form-control" required value="<?= htmlspecialchars($vendedor['nome'] ?? '') ?>">
        </div>
        <div class="col-md-6">
            <label class="form-label">Celular</label>
            <input type="text" name="celular" class="form-control" required value="<?= htmlspecialchars($vendedor['celular'] ?? '') ?>">
        </div>
        <div class="col-md-6">
            <label class="form-label">Tipo da Chave PIX</label>
            <select name="tipochave" class="form-select" required>
                <option value="">Selecione...</option>
                <option value="CPF" <?= (isset($vendedor['tipochave']) && $vendedor['tipochave'] == 'CPF') ? 'selected' : '' ?>>CPF</option>
                <option value="CNPJ" <?= (isset($vendedor['tipochave']) && $vendedor['tipochave'] == 'CNPJ') ? 'selected' : '' ?>>CNPJ</option>
                <option value="E-mail" <?= (isset($vendedor['tipochave']) && $vendedor['tipochave'] == 'E-mail') ? 'selected' : '' ?>>E-mail</option>
                <option value="CELULAR" <?= (isset($vendedor['tipochave']) && $vendedor['tipochave'] == 'CELULAR') ? 'selected' : '' ?>>Celular</option>
                <option value="Aleatória" <?= (isset($vendedor['tipochave']) && $vendedor['tipochave'] == 'Aleatória') ? 'selected' : '' ?>>Aleatória</option>
            </select>
        </div>
        <div class="col-md-6">
            <label class="form-label">Chave PIX</label>
            <input type="text" name="chave" class="form-control" required value="<?= htmlspecialchars($vendedor['chave'] ?? '') ?>">
        </div>
        <div class="col-12">
            <button class="btn btn-primary" type="submit"><?= $modo_edicao ? 'Atualizar' : 'Cadastrar' ?></button>
            <a href="vendedor_list.php" class="btn btn-secondary">Voltar</a>
        </div>
    </form>
</div>

<?php require_once '../views/footer.php'; ?>
