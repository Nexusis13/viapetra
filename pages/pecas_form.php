<?php
require_once '../config/protect.php';
require_once '../config/config.php';

$peca = null;
$isEdit = false;

// Se está editando
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $id = (int)$_GET['id'];
    $stmt = $pdo->prepare("SELECT * FROM pecas WHERE id_peca = ?");
    $stmt->execute([$id]);
    $peca = $stmt->fetch();
    
    if (!$peca) {
        header("Location: pecas_list.php");
        exit;
    }
    $isEdit = true;
}

// Processamento do formulário
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tipo = trim($_POST['tipo']);
    $formapg = trim($_POST['formapg']);
    
    $errors = [];
    
    // Validações
    if (empty($tipo)) {
        $errors[] = "Tipo é obrigatório.";
    } elseif (strlen($tipo) > 255) {
        $errors[] = "Tipo deve ter no máximo 255 caracteres.";
    }
    
    if (empty($formapg)) {
        $errors[] = "Forma de pagamento é obrigatória.";
    } elseif (strlen($formapg) > 50) {
        $errors[] = "Forma de pagamento deve ter no máximo 50 caracteres.";
    }
    
    // Verificar se tipo já existe (exceto se for edição do próprio registro)
    $sqlCheck = "SELECT id_peca FROM pecas WHERE tipo = ? AND id_peca != ?";
    $stmtCheck = $pdo->prepare($sqlCheck);
    $stmtCheck->execute([$tipo, $isEdit ? $peca['id_peca'] : 0]);
    if ($stmtCheck->fetch()) {
        $errors[] = "Já existe uma peça com este tipo.";
    }
    
    if (empty($errors)) {
        if ($isEdit) {
            // Atualizar
            $sql = "UPDATE pecas SET tipo = ?, formapg = ? WHERE id_peca = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$tipo, $formapg, $peca['id_peca']]);
            
            $mensagem = "Peça atualizada com sucesso!";
        } else {
            // Inserir
            $sql = "INSERT INTO pecas (tipo, formapg) VALUES (?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$tipo, $formapg]);
            
            $mensagem = "Peça cadastrada com sucesso!";
        }
        
        header("Location: pecas_list.php?sucesso=" . urlencode($mensagem));
        exit;
    }
}

// Buscar formas de pagamento existentes para sugestão
$stmtFormas = $pdo->prepare("SELECT DISTINCT formapg FROM pecas ORDER BY formapg");
$stmtFormas->execute();
$formasExistentes = $stmtFormas->fetchAll(PDO::FETCH_COLUMN);

require_once '../views/header.php';
?>

<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><?= $isEdit ? 'Editar Peça' : 'Nova Peça' ?></h2>
        <a href="pecas_list.php" class="btn btn-secondary">
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
                            <label for="tipo" class="form-label">Tipo da Peça <span class="text-danger">*</span></label>
                            <input type="text" 
                                   class="form-control" 
                                   id="tipo" 
                                   name="tipo" 
                                   value="<?= htmlspecialchars($peca['tipo'] ?? $_POST['tipo'] ?? '') ?>" 
                                   maxlength="255"
                                   required
                                   placeholder="Ex: Bancada, Escada, Patamares...">
                        </div>
                    </div>
                    
                    <div class="col-md-4">
                        <div class="mb-3">
                            <label for="formapg" class="form-label">Forma de Pagamento <span class="text-danger">*</span></label>
                            <input type="text" 
                                   class="form-control" 
                                   id="formapg" 
                                   name="formapg" 
                                   value="<?= htmlspecialchars($peca['formapg'] ?? $_POST['formapg'] ?? '') ?>" 
                                   maxlength="50"
                                   required
                                   placeholder="Ex: m², ML, Peça"
                                   list="formas-existentes">
                            
                            <datalist id="formas-existentes">
                                <?php foreach ($formasExistentes as $forma): ?>
                                    <option value="<?= htmlspecialchars($forma) ?>">
                                <?php endforeach; ?>
                            </datalist>
                            
                            <div class="form-text">
                                Formas comuns: m² (metro quadrado), ML (metro linear), Peça (unidade)
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
                    <div class="col-md-4">
                        <strong>ID da Peça:</strong> <?= $peca['id_peca'] ?>
                    </div>
                    <div class="col-md-4">
                        <strong>Tipo Atual:</strong> <?= htmlspecialchars($peca['tipo']) ?>
                    </div>
                    <div class="col-md-4">
                        <strong>Forma de Pagamento:</strong> 
                        <span class="badge bg-primary"><?= htmlspecialchars($peca['formapg']) ?></span>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Card com exemplos de formas de pagamento -->
    <div class="card mt-4">
        <div class="card-header">
            <h5 class="mb-0">Exemplos de Formas de Pagamento</h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-4">
                    <strong>m²</strong> - Metro quadrado<br>
                    <small class="text-muted">Para superfícies como bancadas, fachadas</small>
                </div>
                <div class="col-md-4">
                    <strong>ML</strong> - Metro linear<br>
                    <small class="text-muted">Para cortes retos, bordas</small>
                </div>
                <div class="col-md-4">
                    <strong>Peça</strong> - Unidade<br>
                    <small class="text-muted">Para itens únicos como cubas, nichos</small>
                </div>
            </div>
        </div>
    </div>
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