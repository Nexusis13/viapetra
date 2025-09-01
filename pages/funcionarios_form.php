<?php
require_once '../config/protect.php';
require_once '../config/config.php';

$funcionario = null;
$isEdit = false;

// Se está editando
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $id = (int)$_GET['id'];
    $stmt = $pdo->prepare("SELECT * FROM funcionarios WHERE id_funcionario = ?");
    $stmt->execute([$id]);
    $funcionario = $stmt->fetch();
    
    if (!$funcionario) {
        header("Location: funcionarios_list.php");
        exit;
    }
    $isEdit = true;
}

// Processamento do formulário
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = trim($_POST['nome']);
    $status = isset($_POST['status']) ? 1 : 0;
    
    $errors = [];
    
    // Validações
    if (empty($nome)) {
        $errors[] = "Nome é obrigatório.";
    } elseif (strlen($nome) > 255) {
        $errors[] = "Nome deve ter no máximo 255 caracteres.";
    }
    
    // Verificar se nome já existe (exceto se for edição do próprio registro)
    $sqlCheck = "SELECT id_funcionario FROM funcionarios WHERE nome = ? AND id_funcionario != ?";
    $stmtCheck = $pdo->prepare($sqlCheck);
    $stmtCheck->execute([$nome, $isEdit ? $funcionario['id_funcionario'] : 0]);
    if ($stmtCheck->fetch()) {
        $errors[] = "Já existe um funcionário com este nome.";
    }
    
    if (empty($errors)) {
        if ($isEdit) {
            // Atualizar
            $sql = "UPDATE funcionarios SET nome = ?, status = ? WHERE id_funcionario = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$nome, $status, $funcionario['id_funcionario']]);
            
            $mensagem = "Funcionário atualizado com sucesso!";
        } else {
            // Inserir
            $sql = "INSERT INTO funcionarios (nome, status) VALUES (?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$nome, $status]);
            
            $mensagem = "Funcionário cadastrado com sucesso!";
        }
        
        header("Location: funcionarios_list.php?sucesso=" . urlencode($mensagem));
        exit;
    }
}

require_once '../views/header.php';
?>

<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><?= $isEdit ? 'Editar Funcionário' : 'Novo Funcionário' ?></h2>
        <a href="funcionarios_list.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Voltar
        </a>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
            <ul class="mb-0">
                <?php foreach ($errors as $error): ?>
                    <li><?= htmlspecialchars($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <?php if (isset($_GET['sucesso'])): ?>
        <div class="alert alert-success">
            <?= htmlspecialchars($_GET['sucesso']) ?>
        </div>
    <?php endif; ?>

    <div class="card">
        <div class="card-body">
            <form method="post">
                <div class="row">
                    <div class="col-md-8">
                        <div class="mb-3">
                            <label for="nome" class="form-label">Nome do Funcionário <span class="text-danger">*</span></label>
                            <input type="text" 
                                   class="form-control" 
                                   id="nome" 
                                   name="nome" 
                                   value="<?= htmlspecialchars($funcionario['nome'] ?? $_POST['nome'] ?? '') ?>" 
                                   maxlength="255"
                                   required>
                        </div>
                    </div>
                    
                    <div class="col-md-4">
                        <div class="mb-3">
                            <label for="status" class="form-label">Status</label>
                            <div class="form-check form-switch">
                                <input class="form-check-input" 
                                       type="checkbox" 
                                       id="status" 
                                       name="status" 
                                       <?= ($funcionario['status'] ?? $_POST['status'] ?? 1) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="status">
                                    Ativo
                                </label>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="d-flex justify-content-between">
                    <div>
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-save"></i> <?= $isEdit ? 'Atualizar' : 'Cadastrar' ?>
                        </button>
                        <button type="reset" class="btn btn-secondary">
                            <i class="fas fa-undo"></i> Limpar
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <?php if ($isEdit): ?>
        <div class="card mt-4">
            <div class="card-header">
                <h5 class="mb-0">Informações Adicionais</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <strong>ID do Funcionário:</strong> <?= $funcionario['id_funcionario'] ?>
                    </div>
                    <div class="col-md-6">
                        <strong>Status Atual:</strong> 
                        <?php if ($funcionario['status'] == 1): ?>
                            <span class="badge bg-success">Ativo</span>
                        <?php else: ?>
                            <span class="badge bg-danger">Inativo</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
// Prevenir envio duplo do formulário
document.querySelector('form').addEventListener('submit', function(e) {
    const submitBtn = this.querySelector('button[type="submit"]');
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processando...';
});
</script>

<?php require_once '../views/footer.php'; ?>