<?php
require_once '../config/config.php';
redirect_if_not_logged_in();

// Verificar se é edição
$is_edit = isset($_GET['id']) && !empty($_GET['id']);
$paciente_id = $is_edit ? (int) $_GET['id'] : 0;
$paciente = null;

// Se for edição, buscar dados do paciente
if ($is_edit) {
    $sql = "SELECT * FROM pacientes WHERE id = ?";
    $stmt = $mysqli->prepare($sql);
    $stmt->bind_param('i', $paciente_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $paciente = $result->fetch_assoc();
    
    if (!$paciente) {
        header("Location: pacientes_list.php");
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
    $cpf = trim($_POST['cpf']);
    $data_nascimento = trim($_POST['data_nascimento']);
    $plano = trim($_POST['plano']);
    $telefone = trim($_POST['telefone']);
    $email = trim($_POST['email']);
    $contato_emergencia = trim($_POST['contato_emergencia']);
    $empresa = trim($_POST['empresa']);
    
    $erros = [];
    
    // Validações
    if (empty($nome)) {
        $erros[] = "Nome é obrigatório";
    } elseif (strlen($nome) < 3) {
        $erros[] = "Nome deve ter pelo menos 3 caracteres";
    }
    
    if (!empty($cpf)) {
        // Remove caracteres não numéricos do CPF
        $cpf_limpo = preg_replace('/\D/', '', $cpf);
        if (strlen($cpf_limpo) != 11) {
            $erros[] = "CPF deve ter 11 dígitos";
        } else {
            // Validação básica de CPF
            if (!validarCPF($cpf_limpo)) {
                $erros[] = "CPF inválido";
            }
        }
        $cpf = $cpf_limpo;
    }
    
    if (!empty($data_nascimento)) {
        $data_obj = DateTime::createFromFormat('Y-m-d', $data_nascimento);
        if (!$data_obj || $data_obj->format('Y-m-d') !== $data_nascimento) {
            $erros[] = "Data de nascimento inválida";
        } elseif ($data_obj > new DateTime()) {
            $erros[] = "Data de nascimento não pode ser futura";
        }
    }
    
    if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $erros[] = "Email inválido";
    }
    
    if (!empty($telefone)) {
        // Remove caracteres não numéricos
        $telefone_limpo = preg_replace('/\D/', '', $telefone);
        if (strlen($telefone_limpo) < 10 || strlen($telefone_limpo) > 11) {
            $erros[] = "Telefone deve ter 10 ou 11 dígitos";
        }
        $telefone = $telefone_limpo;
    }
    
    if (!empty($contato_emergencia)) {
        // Remove caracteres não numéricos
        $contato_limpo = preg_replace('/\D/', '', $contato_emergencia);
        if (strlen($contato_limpo) < 10 || strlen($contato_limpo) > 11) {
            $erros[] = "Contato de emergência deve ter 10 ou 11 dígitos";
        }
        $contato_emergencia = $contato_limpo;
    }
    
    // Verificar se já existe paciente com mesmo CPF (se CPF foi informado)
    if (!empty($cpf)) {
        $check_cpf_sql = "SELECT id FROM pacientes WHERE cpf = ?";
        if ($is_edit) {
            $check_cpf_sql .= " AND id != ?";
        }
        
        $stmt_check_cpf = $mysqli->prepare($check_cpf_sql);
        if ($is_edit) {
            $stmt_check_cpf->bind_param('si', $cpf, $paciente_id);
        } else {
            $stmt_check_cpf->bind_param('s', $cpf);
        }
        $stmt_check_cpf->execute();
        $resultado_check_cpf = $stmt_check_cpf->get_result();
        
        if ($resultado_check_cpf->num_rows > 0) {
            $erros[] = "Já existe um paciente com este CPF";
        }
    }
    
    // Verificar se já existe paciente com mesmo nome (exceto se for edição do mesmo)
    $check_sql = "SELECT id FROM pacientes WHERE LOWER(nome) = LOWER(?)";
    if ($is_edit) {
        $check_sql .= " AND id != ?";
    }
    
    $stmt_check = $mysqli->prepare($check_sql);
    if ($is_edit) {
        $stmt_check->bind_param('si', $nome, $paciente_id);
    } else {
        $stmt_check->bind_param('s', $nome);
    }
    $stmt_check->execute();
    $resultado_check = $stmt_check->get_result();
    
    if ($resultado_check->num_rows > 0) {
        $erros[] = "Já existe um paciente com este nome";
    }
    
    // Se não há erros, salvar no banco
    if (empty($erros)) {
        if ($is_edit) {
            // Atualizar paciente existente
            $sql = "UPDATE pacientes SET nome = ?, cpf = ?, data_nascimento = ?, plano = ?, telefone = ?, email = ?, contato_emergencia = ?, empresa = ? WHERE id = ?";
            $stmt = $mysqli->prepare($sql);
            $stmt->bind_param('ssssssssi', $nome, $cpf, $data_nascimento, $plano, $telefone, $email, $contato_emergencia, $empresa, $paciente_id);
        } else {
            // Inserir novo paciente
            $sql = "INSERT INTO pacientes (nome, cpf, data_nascimento, plano, telefone, email, contato_emergencia, empresa) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $mysqli->prepare($sql);
            $stmt->bind_param('ssssssss', $nome, $cpf, $data_nascimento, $plano, $telefone, $email, $contato_emergencia, $empresa);
        }
        
        if ($stmt->execute()) {
            // Redirecionar para lista após sucesso
            $redirect_url = "pacientes_list.php";
            if ($is_edit) {
                $redirect_url .= "?updated=1";
            } else {
                $redirect_url .= "?created=1";
            }
            
            header("Location: $redirect_url");
            exit;
        } else {
            $erros[] = "Erro ao salvar no banco de dados: " . $mysqli->error;
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
    <title><?= $is_edit ? 'Editar' : 'Novo' ?> Paciente - Sistema Nexus</title>
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
                        <?= $is_edit ? 'Editar' : 'Novo' ?> Paciente
                    </h2>
                    <p class="text-muted mb-0">
                        <?= $is_edit ? 'Atualize os dados do paciente' : 'Cadastre um novo paciente no sistema' ?>
                    </p>
                </div>
                <div>
                    <a href="pacientes_list.php" class="btn btn-outline-secondary">
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
                        Dados do Paciente
                    </h5>
                </div>
                <div class="card-body">
                    <form method="POST" novalidate>
                        <div class="row g-4">
                            <!-- Nome Completo -->
                            <div class="col-12">
                                <div class="form-floating">
                                    <input type="text" 
                                           class="form-control <?= in_array('Nome é obrigatório', $erros ?? []) || in_array('Nome deve ter pelo menos 3 caracteres', $erros ?? []) ? 'is-invalid' : '' ?>" 
                                           id="nome" 
                                           name="nome" 
                                           value="<?= htmlspecialchars($paciente['nome'] ?? $_POST['nome'] ?? '') ?>"
                                           placeholder="Nome completo"
                                           required
                                           maxlength="100">
                                    <label for="nome">
                                        <i class="bi bi-person me-1"></i>Nome Completo *
                                    </label>
                                    <div class="form-text">
                                        Nome completo do paciente (mínimo 3 caracteres)
                                    </div>
                                </div>
                            </div>

                            <!-- CPF -->
                            <div class="col-md-6">
                                <div class="form-floating">
                                    <input type="text" 
                                           class="form-control <?= in_array('CPF deve ter 11 dígitos', $erros ?? []) || in_array('CPF inválido', $erros ?? []) || in_array('Já existe um paciente com este CPF', $erros ?? []) ? 'is-invalid' : '' ?>" 
                                           id="cpf" 
                                           name="cpf" 
                                           value="<?= !empty($paciente['cpf']) ? preg_replace('/(\d{3})(\d{3})(\d{3})(\d{2})/', '$1.$2.$3-$4', $paciente['cpf']) : ($_POST['cpf'] ?? '') ?>"
                                           placeholder="000.000.000-00"
                                           maxlength="14">
                                    <label for="cpf">
                                        <i class="bi bi-person-vcard me-1"></i>CPF
                                    </label>
                                    <div class="form-text">
                                        Documento de identificação (opcional)
                                    </div>
                                </div>
                            </div>

                            <!-- Data de Nascimento -->
                            <div class="col-md-6">
                                <div class="form-floating">
                                    <input type="date" 
                                           class="form-control <?= in_array('Data de nascimento inválida', $erros ?? []) || in_array('Data de nascimento não pode ser futura', $erros ?? []) ? 'is-invalid' : '' ?>" 
                                           id="data_nascimento" 
                                           name="data_nascimento" 
                                           value="<?= htmlspecialchars($paciente['data_nascimento'] ?? $_POST['data_nascimento'] ?? '') ?>"
                                           max="<?= date('Y-m-d') ?>">
                                    <label for="data_nascimento">
                                        <i class="bi bi-calendar-event me-1"></i>Data de Nascimento
                                    </label>
                                    <div class="form-text">
                                        Data de nascimento do paciente
                                    </div>
                                </div>
                            </div>

                            <!-- Plano de Saúde -->
                            <div class="col-md-6">
    <div class="form-floating">
        <select class="form-select" 
                id="plano" 
                name="plano">
            <option value="" disabled <?= empty($paciente['plano'] ?? $_POST['plano'] ?? '') ? 'selected' : '' ?>>Selecione...</option>
            <option value="Particular" <?= (($paciente['plano'] ?? $_POST['plano'] ?? '') == 'Particular') ? 'selected' : '' ?>>Particular</option>
            <option value="Unimed" <?= (($paciente['plano'] ?? $_POST['plano'] ?? '') == 'Unimed') ? 'selected' : '' ?>>Unimed</option>
            <option value="Amil" <?= (($paciente['plano'] ?? $_POST['plano'] ?? '') == 'Amil') ? 'selected' : '' ?>>Amil</option>
            <option value="Bradesco Saúde" <?= (($paciente['plano'] ?? $_POST['plano'] ?? '') == 'Bradesco Saúde') ? 'selected' : '' ?>>Bradesco Saúde</option>
            <option value="Outro" <?= (($paciente['plano'] ?? $_POST['plano'] ?? '') == 'Outro') ? 'selected' : '' ?>>Outro</option>
        </select>
        <label for="plano">
            <i class="bi bi-card-text me-1"></i>Plano de Saúde
        </label>
        <div class="form-text">
            Convênio médico ou particular
        </div>
    </div>
</div>


                            <!-- Telefone -->
                            <div class="col-md-6">
                                <div class="form-floating">
                                    <input type="tel" 
                                           class="form-control <?= in_array('Telefone deve ter 10 ou 11 dígitos', $erros ?? []) ? 'is-invalid' : '' ?>" 
                                           id="telefone" 
                                           name="telefone" 
                                           value="<?= !empty($paciente['telefone']) ? preg_replace('/(\d{2})(\d{4,5})(\d{4})/', '($1) $2-$3', $paciente['telefone']) : ($_POST['telefone'] ?? '') ?>"
                                           placeholder="Telefone"
                                           maxlength="15">
                                    <label for="telefone">
                                        <i class="bi bi-telephone me-1"></i>Telefone
                                    </label>
                                    <div class="form-text">
                                        Formato: (11) 99999-9999
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
                                           value="<?= htmlspecialchars($paciente['email'] ?? $_POST['email'] ?? '') ?>"
                                           placeholder="Email"
                                           maxlength="100">
                                    <label for="email">
                                        <i class="bi bi-envelope me-1"></i>Email
                                    </label>
                                    <div class="form-text">
                                        Endereço de email para contato
                                    </div>
                                </div>
                            </div>

                            <!-- Contato de Emergência -->
                            <div class="col-md-6">
                                <div class="form-floating">
                                    <input type="tel" 
                                           class="form-control <?= in_array('Contato de emergência deve ter 10 ou 11 dígitos', $erros ?? []) ? 'is-invalid' : '' ?>" 
                                           id="contato_emergencia" 
                                           name="contato_emergencia" 
                                           value="<?= !empty($paciente['contato_emergencia']) ? preg_replace('/(\d{2})(\d{4,5})(\d{4})/', '($1) $2-$3', $paciente['contato_emergencia']) : ($_POST['contato_emergencia'] ?? '') ?>"
                                           placeholder="Contato de emergência"
                                           maxlength="15">
                                    <label for="contato_emergencia">
                                        <i class="bi bi-telephone-plus me-1"></i>Contato de Emergência
                                    </label>
                                    <div class="form-text">
                                        Telefone de contato para emergências
                                    </div>
                                </div>
                            </div>

                            <!-- Empresa/Recomendação -->
                            <div class="col-md-6">
                                <div class="form-floating">
                                    <input type="text" 
                                           class="form-control" 
                                           id="empresa" 
                                           name="empresa" 
                                           value="<?= htmlspecialchars($paciente['empresa'] ?? $_POST['empresa'] ?? '') ?>"
                                           placeholder="Empresa ou recomendação"
                                           maxlength="100">
                                    <label for="empresa">
                                        <i class="bi bi-building me-1"></i>Empresa/Recomendação
                                    </label>
                                    <div class="form-text">
                                        Empresa que trabalha ou quem recomendou
                                    </div>
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
                                           value="<?= $paciente['id'] ?>"
                                           readonly>
                                    <label>ID do Paciente</label>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-floating">
                                    <input type="text" 
                                           class="form-control" 
                                           value="<?= !empty($paciente['data_nascimento']) ? date('d/m/Y', strtotime($paciente['data_nascimento'])) : 'Não informado' ?>"
                                           readonly>
                                    <label>Data de Nascimento</label>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-floating">
                                    <?php
                                    // Contar consultas do paciente
                                    $consultas_sql = "SELECT COUNT(*) as total FROM consultas WHERE id_paciente = ?";
                                    $stmt_consultas = $mysqli->prepare($consultas_sql);
                                    $stmt_consultas->bind_param('i', $paciente_id);
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
                                <a href="pacientes_list.php" class="btn btn-outline-secondary">
                                    <i class="bi bi-arrow-left me-2"></i>
                                    Cancelar
                                </a>
                            </div>
                            
                            <div class="btn-group" role="group">
                                <?php if ($is_edit): ?>
                                <a href="agenda.php?search=<?= urlencode($paciente['nome']) ?>" 
                                   class="btn btn-outline-info">
                                    <i class="bi bi-calendar-check me-1"></i>
                                    Ver Consultas
                                </a>
                                <?php endif; ?>
                                
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-<?= $is_edit ? 'pencil' : 'plus' ?>-circle me-2"></i>
                                    <?= $is_edit ? 'Atualizar Paciente' : 'Cadastrar Paciente' ?>
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
    // Buscar últimas consultas do paciente
    $historico_sql = "SELECT 
        c.id,
        c.data_horario,
        c.status,
        u.nome as medico
        FROM consultas c
        JOIN usuarios u ON c.id_medico = u.id
        WHERE c.id_paciente = ?
        ORDER BY c.data_horario DESC
        LIMIT 10";
    
    $stmt_historico = $mysqli->prepare($historico_sql);
    $stmt_historico->bind_param('i', $paciente_id);
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
                    <a href="agenda.php?search=<?= urlencode($paciente['nome']) ?>" class="btn btn-light btn-sm">
                        <i class="bi bi-eye me-1"></i>Ver Todas
                    </a>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($consultas_historico)): ?>
                    <div class="text-center py-4">
                        <i class="bi bi-calendar-x text-muted" style="font-size: 3rem; opacity: 0.3;"></i>
                        <p class="text-muted mt-2 mb-0">Nenhuma consulta registrada</p>
                        <a href="nova_consulta.php?paciente_id=<?= $paciente_id ?>" class="btn btn-primary btn-sm mt-2">
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
                                        <a href="info_consulta.php?id=<?= $consulta['id'] ?>" 
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
// Máscara para CPF
document.getElementById('cpf').addEventListener('input', function(e) {
    let value = e.target.value.replace(/\D/g, '');
    
    if (value.length <= 11) {
        value = value.replace(/(\d{3})(\d{3})(\d{3})(\d{2})/, '$1.$2.$3-$4');
    }
    
    e.target.value = value;
});

// Máscara para telefone
document.getElementById('telefone').addEventListener('input', function(e) {
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
    const dataNascimento = document.getElementById('data_nascimento').value;
    const telefone = document.getElementById('telefone').value.replace(/\D/g, '');
    const email = document.getElementById('email').value.trim();
    const contatoEmergencia = document.getElementById('contato_emergencia').value.replace(/\D/g, '');
    
    let hasError = false;
    
    // Validar nome
    if (nome.length < 3) {
        document.getElementById('nome').classList.add('is-invalid');
        hasError = true;
    } else {
        document.getElementById('nome').classList.remove('is-invalid');
    }
    
    // Validar CPF (se preenchido)
    if (cpf && !validarCPFCliente(cpf)) {
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
            document.getElementById('data_nascimento').classList.add('is-invalid');
            hasError = true;
        } else {
            document.getElementById('data_nascimento').classList.remove('is-invalid');
        }
    }
    
    // Validar telefone (se preenchido)
    if (telefone && (telefone.length < 10 || telefone.length > 11)) {
        document.getElementById('telefone').classList.add('is-invalid');
        hasError = true;
    } else {
        document.getElementById('telefone').classList.remove('is-invalid');
    }
    
    // Validar email (se preenchido)
    if (email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
        document.getElementById('email').classList.add('is-invalid');
        hasError = true;
    } else {
        document.getElementById('email').classList.remove('is-invalid');
    }
    
    // Validar contato de emergência (se preenchido)
    if (contatoEmergencia && (contatoEmergencia.length < 10 || contatoEmergencia.length > 11)) {
        document.getElementById('contato_emergencia').classList.add('is-invalid');
        hasError = true;
    } else {
        document.getElementById('contato_emergencia').classList.remove('is-invalid');
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