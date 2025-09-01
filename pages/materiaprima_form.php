<?php
require_once '../config/protect.php';
require_once '../config/config.php';

$materia = null;
$isEdit = false;

// Se está editando
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $id = (int)$_GET['id'];
    $stmt = $pdo->prepare("SELECT * FROM materia_prima WHERE id_materia = ?");
    $stmt->execute([$id]);
    $materia = $stmt->fetch();
    
    if (!$materia) {
        header("Location: materiaprima_list.php");
        exit;
    }
    $isEdit = true;
}

// Processamento do formulário
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = trim($_POST['nome']);
    
    $errors = [];
    
    // Validações
    if (empty($nome)) {
        $errors[] = "Nome é obrigatório.";
    }
    
    // Verificar se nome já existe (exceto se for edição do próprio registro)
    $sqlCheck = "SELECT id_materia FROM materia_prima WHERE nome = ? AND id_materia != ?";
    $stmtCheck = $pdo->prepare($sqlCheck);
    $stmtCheck->execute([$nome, $isEdit ? $materia['id_materia'] : 0]);
    if ($stmtCheck->fetch()) {
        $errors[] = "Já existe uma matéria-prima com este nome.";
    }
    
    if (empty($errors)) {
        if ($isEdit) {
            // Atualizar
            $sql = "UPDATE materia_prima SET nome = ? WHERE id_materia = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$nome, $materia['id_materia']]);
            
            $mensagem = "Matéria-prima atualizada com sucesso!";
        } else {
            // Inserir
            $sql = "INSERT INTO materia_prima (nome) VALUES (?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$nome]);
            
            $mensagem = "Matéria-prima cadastrada com sucesso!";
        }
        
        header("Location: materiaprima_list.php?sucesso=" . urlencode($mensagem));
        exit;
    }
}

require_once '../views/header.php';
?>

<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><?= $isEdit ? 'Editar Matéria-Prima' : 'Nova Matéria-Prima' ?></h2>
        <a href="materiaprima_list.php" class="btn btn-secondary">
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
                    <div class="col-md-12">
                        <div class="mb-3">
                            <label for="nome" class="form-label">Nome da Matéria-Prima <span class="text-danger">*</span></label>
                            <textarea class="form-control" 
                                      id="nome" 
                                      name="nome" 
                                      rows="3"
                                      required
                                      placeholder="Ex: ARDÓSIA BIPOLIDA, ARDÓSIA POLIDA, GRANITO BRANCO..."><?= htmlspecialchars($materia['nome'] ?? $_POST['nome'] ?? '') ?></textarea>
                            <div class="form-text">
                                Digite o nome completo da matéria-prima. Pode incluir detalhes sobre acabamento, cor, etc.
                            </div>
                        </div>
                    </div>
                </div>

                <div class="d-flex justify-content-between">