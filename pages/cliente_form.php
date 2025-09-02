<?php
require_once '../config/config.php';

// Verificar se é edição
$is_edit = isset($_GET['client_id']) && !empty($_GET['client_id']);
$client_id = $is_edit ? (int) $_GET['client_id'] : 0;
$cliente = null;

// Se for edição, buscar dados do cliente
if ($is_edit) {
    $sql = "SELECT * FROM clientes WHERE client_id = ?";
    $stmt = $mysqli->prepare($sql);
    $stmt->bind_param('i', $client_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $cliente = $result->fetch_assoc();
    
    if (!$cliente) {
        header("Location: cliente_list.php");
        exit;
    }
}

// Função para validar CPF
function validarCPF($cpf) {
    if (strlen($cpf) != 11 || preg_match('/(\d)\1{10}/', $cpf)) {
        return false;
    }
    
    for ($t = 9; $t < 11; $t++) {
        for ($d = 0, $c = 0; $c < $t; $c++) {
            $d += $cpf[$c] * (($t + 1) - $c);
        }
        $d = ((10 * $d) % 11) % 10;
        if ($cpf[$c] != $d) {
            return false;
        }
    }
    return true;
}

// PROCESSAMENTO DO FORMULÁRIO
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = trim($_POST['nome']);
    $documento = trim($_POST['cpf']);
    $dt_nascimento = trim($_POST['dt_nascimento'] ?? '');
    $tipo = trim($_POST['tipo'] ?? '');
    $telefone = trim($_POST['telefone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $endereco = trim($_POST['endereco'] ?? '');
    $end_obra = trim($_POST['end_obra'] ?? '');
    $erros = [];
    
    // Validações
    if (empty($nome)) {
        $erros[] = "Nome é obrigatório";
    } elseif (strlen($nome) < 3) {
        $erros[] = "Nome deve ter pelo menos 3 caracteres";
    }
    
    if (!empty($documento)) {
        // Remove caracteres não numéricos do documento
        $documento_limpo = preg_replace('/\D/', '', $documento);
        if (strlen($documento_limpo) != 11 && strlen($documento_limpo) != 14) {
            $erros[] = "Documento deve ter 11 (CPF) ou 14 (CNPJ) dígitos";
        } else if (strlen($documento_limpo) == 11) {
            // Validação básica de CPF
            if (!validarCPF($documento_limpo)) {
                $erros[] = "CPF inválido";
            }
        }
        $documento = $documento_limpo;
    }
    
    if (!empty($dt_nascimento)) {
        $data_obj = DateTime::createFromFormat('Y-m-d', $dt_nascimento);
        if (!$data_obj || $data_obj->format('Y-m-d') !== $dt_nascimento) {
            $erros[] = "Data de nascimento inválida";
        } elseif ($data_obj > new DateTime()) {
            $erros[] = "Data de nascimento não pode ser futura";
        }
    }
    
    // Validação telefone
    if (!empty($telefone) && !preg_match('/^\(\d{2}\) \d{4,5}-\d{4}$/', $telefone)) {
        $erros[] = "Telefone inválido";
    }
    // Validação email
    if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $erros[] = "Email inválido";
    }
    
    // Verificar se já existe cliente com mesmo CPF (se CPF foi informado)
    if (!empty($documento)) {
        $check_doc_sql = "SELECT client_id FROM clientes WHERE documento = ?";
        $params = [$documento];
        if ($is_edit) {
            $check_doc_sql .= " AND client_id != ?";
            $params[] = $client_id;
        }
        $stmt_check_doc = $pdo->prepare($check_doc_sql);
        $stmt_check_doc->execute($params);
        if ($stmt_check_doc->fetch()) {
            $erros[] = "Já existe um cliente com este documento";
        }
    }
    
    // Verificar se já existe cliente com mesmo nome (exceto se for edição do mesmo)
    $check_sql = "SELECT client_id FROM clientes WHERE LOWER(nome) = LOWER(?)";
    $params = [$nome];
    if ($is_edit) {
        $check_sql .= " AND client_id != ?";
        $params[] = $client_id;
    }
    $stmt_check = $pdo->prepare($check_sql);
    $stmt_check->execute($params);
    if ($stmt_check->fetch()) {
        $erros[] = "Já existe um cliente com este nome";
    }
    
    // Se não há erros, salvar no banco
    if (empty($erros)) {
        if ($is_edit) {
            $sql = "UPDATE clientes SET nome = ?, documento = ?, dt_nascimento = ?, telefone = ?, email = ?, endereco = ?, end_obra = ?, tipo = ? WHERE client_id = ?";
            $stmt = $pdo->prepare($sql);
            $ok = $stmt->execute([$nome, $documento, $dt_nascimento, $telefone, $email, $endereco, $end_obra, $tipo, $client_id]);
        } else {
            $sql = "INSERT INTO clientes (nome, documento, dt_nascimento, telefone, email, endereco, end_obra, tipo) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $ok = $stmt->execute([$nome, $documento, $dt_nascimento, $telefone, $email, $endereco, $end_obra, $tipo]);
        }
        if ($ok) {
            $redirect_url = "cliente_list.php";
            if ($is_edit) {
                $redirect_url .= "?updated=1";
            } else {
                $redirect_url .= "?created=1";
            }
            header("Location: $redirect_url");
            exit;
        } else {
            $erros[] = "Erro ao salvar no banco de dados.";
        }
    }
}

// Agora incluir o header após o processamento
require_once '../views/header.php';
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $is_edit ? 'Editar' : 'Novo' ?> cliente - Sistema Nexus</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    
    <style>
        .form-floating > .form-control {
            padding-top: 1.625rem;
            padding-bottom: 0.625rem;
        }
        .form-floating > label {
            padding: 1rem 0.75rem;
        }
        .card {
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
            border: none;
            border-radius: 0.5rem;
        }
        .btn-group .btn {
            min-width: 120px;
        }
        .form-text {
            font-size: 0.875rem;
        }
    </style>
</head>
<body>

<div class="container-fluid py-4">
    <!-- Cabeçalho da Página -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h2 class="mb-1">
                        <i class="bi bi-person-<?= $is_edit ? 'gear' : 'plus' ?> text-primary me-2"></i>
                        <?= $is_edit ? 'Editar' : 'Novo' ?> cliente
                    </h2>
                    <p class="text-muted mb-0">
                        <?= $is_edit ? 'Atualize os dados do cliente' : 'Cadastre um novo cliente no sistema' ?>
                    </p>
                </div>
                <div>
                    <a href="cliente_list.php" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-left me-2"></i>
                        Voltar para Lista
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Exibir Erros -->
    <?php if (!empty($erros)): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="bi bi-exclamation-triangle me-2"></i>
        <strong>Corrija os seguintes erros:</strong>
        <ul class="mb-0 mt-2">
            <?php foreach ($erros as $erro): ?>
                <li><?= $erro ?></li>
            <?php endforeach; ?>
        </ul>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <!-- Formulário -->
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">
                        <i class="bi bi-person-lines-fill me-2"></i>
                        Dados do cliente
                    </h5>
                </div>
                <div class="card-body">
                    <form method="POST" novalidate>
                        <div class="row g-4">
                            <!-- Nome Completo -->
                            <div class="col-12">
                                <div class="form-floating">
                                    <input type="text" 
                                           class="form-control <?= in_array('Nome é obrigatório', $erros ?? []) || in_array('Nome deve ter pelo menos 3 caracteres', $erros ?? []) ? 'is-invalid' : '' ?> required = *" 
                                           id="nome" 
                                           name="nome" 
                                           value="<?= htmlspecialchars($cliente['nome'] ?? $_POST['nome'] ?? '') ?>"
                                           placeholder="Nome completo"
                                           required
                                           maxlength="100">
                                    <label for="nome">
                                        <i class="bi bi-person me-1"></i>Nome Completo <span class="required">*
                                    </label>
                                    <div class="form-text">
                                        Nome completo do cliente (mínimo 3 caracteres)
                                    </div>
                                </div>
                            </div>

           
                            <input type="hidden" id="tipo" name="tipo" value="<?= htmlspecialchars($cliente['tipo'] ?? $_POST['tipo'] ?? 'CPF') ?>">
                            <!-- CPF/CNPJ -->
                            <div class="col-md-6">
                                <div class="form-floating">
                                    <input type="text" 
                                           class="form-control <?= in_array('CPF deve ter 11 dígitos', $erros ?? []) || in_array('CPF inválido', $erros ?? []) || in_array('Já existe um cliente com este CPF', $erros ?? []) ? 'is-invalid' : '' ?>" 
                                           id="cpf" 
                                           name="cpf" 
                                           value="<?= !empty($cliente['documento']) ? (strlen($cliente['documento']) == 11 ? preg_replace('/(\d{3})(\d{3})(\d{3})(\d{2})/', '$1.$2.$3-$4', $cliente['documento']) : preg_replace('/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/', '$1.$2.$3/$4-$5', $cliente['documento'])) : ($_POST['cpf'] ?? '') ?>"
                                           placeholder="Digite CPF ou CNPJ"
                                           maxlength="18">
                                    <label for="cpf">
                                        <i class="bi bi-person-vcard me-1"></i>CPF ou CNPJ  <span class="required">*
                                    </label>
                                    <div class="form-text">
                                        Digite o CPF (11 dígitos) ou CNPJ (14 dígitos)
                                    </div>
                                </div>
                            </div>

                            <!-- Data de Nascimento -->
                            <div class="col-md-6">
                                <div class="form-floating">
                                    <input type="date" 
                                           class="form-control <?= in_array('Data de nascimento inválida', $erros ?? []) || in_array('Data de nascimento não pode ser futura', $erros ?? []) ? 'is-invalid' : '' ?>" 
                                           id="dt_nascimento" 
                                           name="dt_nascimento" 
                                           value="<?= htmlspecialchars($cliente['dt_nascimento'] ?? $_POST['dt_nascimento'] ?? '') ?>"
                                           max="<?= date('Y-m-d') ?>">
                                    <label for="dt_nascimento">
                                        <i class="bi bi-calendar-event me-1"></i>Data de Nascimento   <span class="required">*
                                    </label>
                                    <div class="form-text">
                                        Data de nascimento do cliente
                                    </div>
                                </div>
                            </div>

                            <!-- Telefone -->
                            <div class="col-md-6">
                                <div class="form-floating">
                                    <input type="text" 
                                           class="form-control <?= in_array('Telefone inválido', $erros ?? []) ? 'is-invalid' : '' ?>" 
                                           id="telefone" 
                                           name="telefone" 
                                           value="<?= htmlspecialchars($cliente['telefone'] ?? $_POST['telefone'] ?? '') ?>"
                                           placeholder="(99) 99999-9999" 
                                           maxlength="20">
                                    <label for="telefone">
                                        <i class="bi bi-telephone me-1"></i>Telefone <span class="required">*</span>
                                    </label>
                                    <div class="form-text">
                                        Informe o telefone no formato (99) 99999-9999
                                    </div>
                                </div>
                            </div>

                            <!-- Email -->
                            <div class="col-md-6">
                                <div class="form-floating">
                                    <input type="email" 
                                           class="form-control <?= in_array('Email inválido', $erros ?? []) ? 'is-invalid' : '' ?>" 
                                           id="email" 
                                           name="email" 
                                           value="<?= htmlspecialchars($cliente['email'] ?? $_POST['email'] ?? '') ?>"
                                           placeholder="Digite o email" 
                                           maxlength="100">
                                    <label for="email">
                                        <i class="bi bi-envelope me-1"></i>Email <span class="required">*</span>
                                    </label>
                                    <div class="form-text">
                                        Informe um email válido
                                    </div>
                                </div>
                            </div>

                            <!-- Endereço -->
                            <div class="col-md-6">
                                <div class="form-floating">
                                    <input type="text" class="form-control" id="endereco" name="endereco" value="<?= htmlspecialchars($cliente['endereco'] ?? $_POST['endereco'] ?? '') ?>" required maxlength="100">
                                    <label for="endereco"><i class="bi bi-geo-alt me-1"></i>Endereço  <span class="required">*</label>
                                </div>
                            </div>
                            <!-- Endereço da Obra -->
                            <div class="col-md-6">
                                <div class="form-floating">
                                    <input type="text" class="form-control" id="end_obra" name="end_obra" value="<?= htmlspecialchars($cliente['end_obra'] ?? $_POST['end_obra'] ?? '') ?>" required maxlength="100">
                                    <label for="end_obra"><i class="bi bi-geo me-1"></i>Endereço da Obra  <span class="required">*</label>
                                </div>
                            </div>
                        </div>

                        <!-- Informações Adicionais (se for edição) -->
                        <?php if ($is_edit): ?>
                        <hr class="my-4">
                        <div class="row">
                            <div class="col-12">
                                <h6 class="text-muted mb-3">
                                    <i class="bi bi-info-circle me-1"></i>
                                    Informações do Sistema
                                </h6>
                            </div>
                            <div class="col-md-4">
                                <div class="form-floating">
                                    <input type="text" 
                                           class="form-control" 
                                           value="<?= $cliente['client_id'] ?>"
                                           readonly>
                                    <label>ID do cliente</label>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-floating">
                                    <input type="text" 
                                           class="form-control" 
                                           value="<?= !empty($cliente['dt_nascimento']) ? date('d/m/Y', strtotime($cliente['dt_nascimento'])) : 'Não informado' ?>"
                                           readonly>
                                    <label>Data de Nascimento</label>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-floating">
                                    <?php
                                    // Contar consultas do cliente
                                    $consultas_sql = "SELECT COUNT(*) as total FROM consultas WHERE id_cliente = ?";
                                    $stmt_consultas = $mysqli->prepare($consultas_sql);
                                    $stmt_consultas->bind_param('i', $client_id);
                                    $stmt_consultas->execute();
                                    $total_consultas = $stmt_consultas->get_result()->fetch_assoc()['total'];
                                    ?>
                                    <input type="text" 
                                           class="form-control" 
                                           value="<?= $total_consultas ?> consulta(s)"
                                           readonly>
                                    <label>Total de Consultas</label>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- Botões de Ação -->
                        <div class="d-flex justify-content-between mt-4">
                            <div>
                                <a href="cliente_list.php" class="btn btn-outline-secondary">
                                    <i class="bi bi-arrow-left me-2"></i>
                                    Cancelar
                                </a>
                            </div>
                            
                            <div class="btn-group" role="group">
                                <?php if ($is_edit): ?>
                                <a href="agenda.php?search=<?= urlencode($cliente['nome']) ?>" 
                                   class="btn btn-outline-info">
                                    <i class="bi bi-calendar-check me-1"></i>
                                    Ver Consultas
                                </a>
                                <?php endif; ?>
                                
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-<?= $is_edit ? 'pencil' : 'plus' ?>-circle me-2"></i>
                                    <?= $is_edit ? 'Atualizar cliente' : 'Cadastrar cliente' ?>
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Histórico de Consultas (se for edição) -->
    <?php if ($is_edit): ?>
    <?php
    // Buscar últimas consultas do cliente
    $historico_sql = "SELECT 
    c.client_id,
        c.data_horario,
        c.status,
        u.nome as medico
        FROM consultas c
        JOIN usuarios u ON c.id_medico = u.id
        WHERE c.id_cliente = ?
        ORDER BY c.data_horario DESC
        LIMIT 10";
    
    $stmt_historico = $mysqli->prepare($historico_sql);
    $stmt_historico->bind_param('i', $client_id);
    $stmt_historico->execute();
    $consultas_historico = $stmt_historico->get_result()->fetch_all(MYSQLI_ASSOC);
    ?>
    
    <div class="row mt-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header bg-info text-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="bi bi-clock-history me-2"></i>
                        Histórico de Consultas
                    </h5>
                    <a href="agenda.php?search=<?= urlencode($cliente['nome']) ?>" class="btn btn-light btn-sm">
                        <i class="bi bi-eye me-1"></i>Ver Todas
                    </a>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($consultas_historico)): ?>
                    <div class="text-center py-4">
                        <i class="bi bi-calendar-x text-muted" style="font-size: 3rem; opacity: 0.3;"></i>
                        <p class="text-muted mt-2 mb-0">Nenhuma consulta registrada</p>
                        <a href="nova_consulta.php?cliente_id=<?= $client_id ?>" class="btn btn-primary btn-sm mt-2">
                            <i class="bi bi-plus me-1"></i>Agendar Primeira Consulta
                        </a>
                    </div>
                    <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Data/Hora</th>
                                    <th>Status</th>
                                    <th>Psicólogo</th>
                                    <th>Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($consultas_historico as $consulta): ?>
                                <tr>
                                    <td>
                                        <i class="bi bi-calendar me-1"></i>
                                        <?= date('d/m/Y', strtotime($consulta['data_horario'])) ?>
                                        <br>
                                        <small class="text-muted">
                                            <i class="bi bi-clock me-1"></i>
                                            <?= date('H:i', strtotime($consulta['data_horario'])) ?>
                                        </small>
                                    </td>
                                    <td>
                                        <?php
                                        $status_config = [
                                            'agendada' => ['badge' => 'warning', 'icon' => 'calendar-check', 'text' => 'Agendada'],
                                            'atendimento' => ['badge' => 'primary', 'icon' => 'person-check', 'text' => 'Em Atendimento'],
                                            'finalizada' => ['badge' => 'success', 'icon' => 'check-circle', 'text' => 'Finalizada'],
                                            'negado' => ['badge' => 'danger', 'icon' => 'x-circle', 'text' => 'Não Compareceu'],
                                            'cancelada' => ['badge' => 'secondary', 'icon' => 'dash-circle', 'text' => 'Cancelada']
                                        ];
                                        $config = $status_config[$consulta['status']] ?? $status_config['agendada'];
                                        ?>
                                        <span class="badge bg-<?= $config['badge'] ?>">
                                            <i class="bi bi-<?= $config['icon'] ?> me-1"></i>
                                            <?= $config['text'] ?>
                                        </span>
                                    </td>
                                    <td>
                                        <i class="bi bi-person-badge me-1"></i>
                                        <?= htmlspecialchars($consulta['medico']) ?>
                                    </td>
                                    <td>
                                        <a href="info_consulta.php?id=<?= $consulta['client_id'] ?>" 
                                           class="btn btn-outline-info btn-sm">
                                            <i class="bi bi-eye me-1"></i>Detalhes
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php require_once '../views/footer.php'; ?>

<script>
// Máscara dinâmica para CPF/CNPJ e detecção automática do tipo
document.getElementById('cpf').addEventListener('input', function(e) {
    let value = e.target.value.replace(/\D/g, '');
    let tipo = 'CPF';
    if (value.length > 11) {
        // CNPJ
        tipo = 'CNPJ';
        value = value.slice(0, 14);
        value = value.replace(/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/, '$1.$2.$3/$4-$5');
    } else {
        // CPF
        value = value.slice(0, 11);
        value = value.replace(/(\d{3})(\d{3})(\d{3})(\d{2})/, '$1.$2.$3-$4');
    }
    e.target.value = value;
    document.getElementById('tipo').value = tipo;
});

// Máscara para telefone
document.getElementById('telefone').addEventListener('input', function(e) {
    let value = e.target.value.replace(/\D/g, '');
    if (value.length > 11) value = value.slice(0, 11);
    if (value.length > 10) {
        value = value.replace(/(\d{2})(\d{5})(\d{4})/, '($1) $2-$3');
    } else if (value.length > 2) {
        value = value.replace(/(\d{2})(\d{4})(\d{0,4})/, '($1) $2-$3');
    }
    e.target.value = value;
});

// Máscara para contato de emergência
document.getElementById('contato_emergencia').addEventListener('input', function(e) {
    let value = e.target.value.replace(/\D/g, '');
    
    if (value.length <= 11) {
        if (value.length <= 10) {
            // Telefone fixo: (11) 1234-5678
            value = value.replace(/(\d{2})(\d{4})(\d{4})/, '($1) $2-$3');
        } else {
            // Celular: (11) 91234-5678
            value = value.replace(/(\d{2})(\d{5})(\d{4})/, '($1) $2-$3');
        }
    }
    
    e.target.value = value;
});

// Validação em tempo real do email
document.getElementById('email').addEventListener('blur', function(e) {
    const email = e.target.value;
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    
    if (email && !emailRegex.test(email)) {
        e.target.classList.add('is-invalid');
        
        // Remover feedback anterior
        const existingFeedback = e.target.parentNode.querySelector('.invalid-feedback');
        if (existingFeedback) existingFeedback.remove();
        
        // Adicionar novo feedback
        const feedback = document.createElement('div');
        feedback.className = 'invalid-feedback';
        feedback.textContent = 'Por favor, insira um email válido';
        e.target.parentNode.appendChild(feedback);
    } else {
        e.target.classList.remove('is-invalid');
        const existingFeedback = e.target.parentNode.querySelector('.invalid-feedback');
        if (existingFeedback) existingFeedback.remove();
    }
});

// Capitalizar primeira letra de cada palavra no nome
document.getElementById('nome').addEventListener('blur', function(e) {
    const words = e.target.value.toLowerCase().split(' ');
    const capitalizedWords = words.map(word => {
        if (word.length > 0) {
            // Não capitalizar conectores
            const conectores = ['de', 'da', 'do', 'das', 'dos', 'e'];
            if (conectores.includes(word)) {
                return word;
            }
            return word.charAt(0).toUpperCase() + word.slice(1);
        }
        return word;
    });
    e.target.value = capitalizedWords.join(' ');
});

// Validação do formulário antes do envio
document.querySelector('form').addEventListener('submit', function(e) {
    const nome = document.getElementById('nome').value.trim();
    const cpf = document.getElementById('cpf').value.replace(/\D/g, '');
    const dataNascimento = document.getElementById('dt_nascimento').value;
    const tipo = document.getElementById('tipo').value;
    const endereco = document.getElementById('endereco').value.trim();
    const endObra = document.getElementById('end_obra').value.trim();

    let hasError = false;

    // Validar nome
    if (nome.length < 3) {
        document.getElementById('nome').classList.add('is-invalid');
        hasError = true;
    } else {
        document.getElementById('nome').classList.remove('is-invalid');
    }

    // Validar CPF (se preenchido)
    if (cpf && !validarCPFCliente(cpf) && cpf.length === 11) {
        document.getElementById('cpf').classList.add('is-invalid');
        hasError = true;
    } else {
        document.getElementById('cpf').classList.remove('is-invalid');
    }

    // Validar data de nascimento (se preenchida)
    if (dataNascimento) {
        const hoje = new Date();
        const nascimento = new Date(dataNascimento);
        if (nascimento > hoje) {
            document.getElementById('dt_nascimento').classList.add('is-invalid');
            hasError = true;
        } else {
            document.getElementById('dt_nascimento').classList.remove('is-invalid');
        }
    }

    // Validar endereço
    if (endereco.length < 3) {
        document.getElementById('endereco').classList.add('is-invalid');
        hasError = true;
    } else {
        document.getElementById('endereco').classList.remove('is-invalid');
    }

    // Validar endereço da obra
    if (endObra.length < 3) {
        document.getElementById('end_obra').classList.add('is-invalid');
        hasError = true;
    } else {
        document.getElementById('end_obra').classList.remove('is-invalid');
    }

    if (hasError) {
        e.preventDefault();
        showNotification('Por favor, corrija os campos destacados em vermelho.', 'error');
    }
});

// Função para validar CPF no frontend
function validarCPFCliente(cpf) {
    if (cpf.length !== 11 || /^(\d)\1{10}$/.test(cpf)) {
        return false;
    }
    
    let soma = 0;
    let resto;
    
    for (let i = 1; i <= 9; i++) {
        soma = soma + parseInt(cpf.substring(i-1, i)) * (11 - i);
    }
    
    resto = (soma * 10) % 11;
    if ((resto === 10) || (resto === 11)) resto = 0;
    if (resto !== parseInt(cpf.substring(9, 10))) return false;
    
    soma = 0;
    for (let i = 1; i <= 10; i++) {
        soma = soma + parseInt(cpf.substring(i-1, i)) * (12 - i);
    }
    
    resto = (soma * 10) % 11;
    if ((resto === 10) || (resto === 11)) resto = 0;
    if (resto !== parseInt(cpf.substring(10, 11))) return false;
    
    return true;
}

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
        <i class="bi bi-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-triangle' : 'info-circle'} me-2"></i>
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

// Focus no primeiro campo
document.getElementById('nome').focus();
</script>

</body>
</html>