<?php
require_once '../config/config.php';

$client_id = 0;
$is_edit = false;
if (isset($_GET['client_id']) && !empty($_GET['client_id'])) {
    $client_id = (int) $_GET['client_id'];
    $is_edit = true;
} elseif (isset($_GET['id']) && !empty($_GET['id'])) {
    $client_id = (int) $_GET['id'];
    $is_edit = true;
}
$cliente = null;

// Se for edição, buscar dados do cliente
if ($is_edit) {
    error_log("Editando cliente com ID: " . $client_id);
    $sql = "SELECT * FROM clientes WHERE client_id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$client_id]);
    $cliente = $stmt->fetch(PDO::FETCH_ASSOC);
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
    $nome = isset($_POST['nome']) ? trim($_POST['nome']) : ($cliente['nome'] ?? '');
    $documento = isset($_POST['cpf']) ? trim($_POST['cpf']) : ($cliente['documento'] ?? '');
    $dt_nascimento = isset($_POST['dt_nascimento']) ? trim($_POST['dt_nascimento']) : ($cliente['dt_nascimento'] ?? '');
    $tipo = isset($_POST['tipo']) ? trim($_POST['tipo']) : ($cliente['tipo'] ?? '');
    $telefone = isset($_POST['telefone']) ? trim($_POST['telefone']) : ($cliente['telefone'] ?? '');
    $email = isset($_POST['email']) ? trim($_POST['email']) : ($cliente['email'] ?? '');
    $cep_endereco = isset($_POST['cep_endereco']) ? trim($_POST['cep_endereco']) : ($cliente['cep_endereco'] ?? '');
    $cep_obra = isset($_POST['cep_obra']) ? trim($_POST['cep_obra']) : ($cliente['cep_obra'] ?? '');
    // Se vazio, define como null para evitar salvar 0
    if ($cep_endereco === '' || $cep_endereco === null) {
        $cep_endereco = null;
    }
    if ($cep_obra === '' || $cep_obra === null) {
        $cep_obra = null;
    }
    $endereco = isset($_POST['endereco']) ? trim($_POST['endereco']) : ($cliente['endereco'] ?? '');
    $end_obra = isset($_POST['end_obra']) ? trim($_POST['end_obra']) : ($cliente['end_obra'] ?? '');
    $status = isset($_POST['status']) ? (int)$_POST['status'] : ($cliente['status'] ?? 1);
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
            $sql = "UPDATE clientes SET nome = ?, documento = ?, dt_nascimento = ?, telefone = ?, email = ?, cep_endereco = ?, endereco = ?, cep_obra = ?, end_obra = ?, tipo = ?, status = ? WHERE client_id = ?";
            $stmt = $pdo->prepare($sql);
            $ok = $stmt->execute([$nome, $documento, $dt_nascimento, $telefone, $email, $cep_endereco, $endereco, $cep_obra, $end_obra, $tipo, $status, $client_id]);
        } else {
            $sql = "INSERT INTO clientes (nome, documento, dt_nascimento, telefone, email, cep_endereco, endereco, cep_obra, end_obra, tipo, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $ok = $stmt->execute([$nome, $documento, $dt_nascimento, $telefone, $email, $cep_endereco, $endereco, $cep_obra, $end_obra, $tipo, $status]);
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
                                    <?php
                                    $dt_nasc_val = $cliente['dt_nascimento'] ?? $_POST['dt_nascimento'] ?? '';
                                    if ($dt_nasc_val === '0000-00-00' || $dt_nasc_val === '' || strpos($dt_nasc_val, '-') === 0 || (strlen($dt_nasc_val) === 10 && (int)substr($dt_nasc_val,0,4) <= 0)) {
                                        $dt_nasc_val = '';
                                    }
                                    ?>
                                    <input type="date" 
                                           class="form-control <?= in_array('Data de nascimento inválida', $erros ?? []) || in_array('Data de nascimento não pode ser futura', $erros ?? []) ? 'is-invalid' : '' ?>" 
                                           id="dt_nascimento" 
                                           name="dt_nascimento" 
                                           value="<?= htmlspecialchars($dt_nasc_val) ?>"
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

                            <!-- CEP Endereço e Endereço -->
                            <div class="col-md-3">
                                <div class="form-floating">
                                    <input type="text" class="form-control" id="cep_endereco" name="cep_endereco" value="<?= htmlspecialchars($cliente['cep_endereco'] ?? $_POST['cep_endereco'] ?? '') ?>" maxlength="9" placeholder="CEP">
                                    <label for="cep_endereco"><i class="bi bi-geo-alt me-1"></i>CEP Endereço</label>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-floating">
                                    <input type="text" class="form-control" id="endereco" name="endereco" value="<?= htmlspecialchars($cliente['endereco'] ?? $_POST['endereco'] ?? '') ?>" required maxlength="100">
                                    <label for="endereco"><i class="bi bi-geo-alt me-1"></i>Endereço <span class="required">*</span></label>
                                </div>
                            </div>
                            <!-- CEP Obra e Endereço da Obra -->
                            <div class="col-md-3">
                                <div class="form-floating">
                                    <input type="text" class="form-control" id="cep_obra" name="cep_obra" value="<?= htmlspecialchars($cliente['cep_obra'] ?? $_POST['cep_obra'] ?? '') ?>" maxlength="9" placeholder="CEP Obra" pattern="\d{5}-\d{3}" inputmode="numeric" autocomplete="postal-code">
                                    <label for="cep_obra"><i class="bi bi-geo me-1"></i>CEP Obra</label>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-floating">
                                    <input type="text" class="form-control" id="end_obra" name="end_obra" value="<?= htmlspecialchars($cliente['end_obra'] ?? $_POST['end_obra'] ?? '') ?>" required maxlength="100">
                                    <label for="end_obra"><i class="bi bi-geo me-1"></i>Endereço da Obra <span class="required">*</span></label>
                                </div>
                            </div>

                            <!-- status -->
                            <div class="col-md-6">
                                <div class="form-floating">
                                    <?php
                                    // DETERMINA O STATUS PARA CLIENTE CADASTRADO
                                    if (isset($_POST['status'])) {
                                        $status_select = (int)$_POST['status'];
                                    } elseif (isset($cliente['status'])) {
                                        $status_select = (int)$cliente['status'];
                                    } else {
                                        $status_select = 1; // PADRAO: Ativo
                                    }
                                    ?>
                                    <select class="form-select" id="status" name="status">
                                        <option value="1" <?= ($status_select === 1) ? 'selected' : '' ?>>Ativo</option>
                                        <option value="0" <?= ($status_select === 0) ? 'selected' : '' ?>>Inativo</option>
                                    </select>
                                    <label for="status"><i class="bi bi-toggle-on me-1"></i>Status</label>
                                </div>
                            </div>
                        </div>

                      

                        <!-- Botões de Ação -->
                        <div class="d-flex justify-content-between mt-4">
                            <div>
                                <a href="cliente_list.php" class="btn btn-outline-secondary">
                                    <i class="bi bi-arrow-left me-2"></i>
                                    Cancelar
                                </a>
                            </div>
                            
                            <div class="btn-group" role="group">
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

    
    
</div>


<?php require_once '../views/footer.php'; ?>

<script>
// Função para buscar endereço pelo CEP usando ViaCEP
function buscarEnderecoPorCep(cep, callback) {
    cep = cep.replace(/\D/g, '');
    if (cep.length !== 8) {
        callback(null);
        return;
    }
    fetch('https://viacep.com.br/ws/' + cep + '/json/')
        .then(response => response.json())
        .then(data => {
            if (!data.erro) {
                callback(data);
            } else {
                callback(null);
            }
        })
        .catch(() => callback(null));
}

// CEP Endereço principal
document.getElementById('cep_endereco').addEventListener('blur', function(e) {
    const cep = e.target.value;
    if (cep.replace(/\D/g, '').length === 8) {
        buscarEnderecoPorCep(cep, function(data) {
            if (data) {
                let enderecoCompleto = '';
                if (data.logradouro) enderecoCompleto += data.logradouro;
                if (data.bairro) enderecoCompleto += (enderecoCompleto ? ', ' : '') + data.bairro;
                if (data.localidade && data.uf) enderecoCompleto += (enderecoCompleto ? ' - ' : '') + data.localidade + '/' + data.uf;
                document.getElementById('endereco').value = enderecoCompleto;
            }
        });
    }
});

// CEP Obra
document.getElementById('cep_obra').addEventListener('blur', function(e) {
    const cep = e.target.value;
    if (cep.replace(/\D/g, '').length === 8) {
        buscarEnderecoPorCep(cep, function(data) {
            if (data) {
                let enderecoCompleto = '';
                if (data.logradouro) enderecoCompleto += data.logradouro;
                if (data.bairro) enderecoCompleto += (enderecoCompleto ? ', ' : '') + data.bairro;
                if (data.localidade && data.uf) enderecoCompleto += (enderecoCompleto ? ' - ' : '') + data.localidade + '/' + data.uf;
                document.getElementById('end_obra').value = enderecoCompleto;
            }
        });
    }
});

// Validação instantânea do CPF/CNPJ ao sair do campo
document.getElementById('cpf').addEventListener('blur', function(e) {
    const valor = e.target.value.replace(/\D/g, '');
    const parent = e.target.parentNode;
    // Remove feedback anterior
    const existingFeedback = parent.querySelector('.invalid-feedback');
    if (existingFeedback) existingFeedback.remove();

    if (!valor) {
        e.target.classList.remove('is-invalid');
        return;
    }

    if (valor.length === 11) {
        // CPF
        if (!validarCPFCliente(valor)) {
            e.target.classList.add('is-invalid');
            const feedback = document.createElement('div');
            feedback.className = 'invalid-feedback';
            feedback.textContent = 'CPF inválido';
            parent.appendChild(feedback);
        } else {
            e.target.classList.remove('is-invalid');
        }
    } else if (valor.length === 14) {
        // CNPJ
        if (!validarCNPJCliente(valor)) {
            e.target.classList.add('is-invalid');
            const feedback = document.createElement('div');
            feedback.className = 'invalid-feedback';
            feedback.textContent = 'CNPJ inválido';
            parent.appendChild(feedback);
        } else {
            e.target.classList.remove('is-invalid');
        }
    } else {
        // Tamanho inválido
        e.target.classList.add('is-invalid');
        const feedback = document.createElement('div');
        feedback.className = 'invalid-feedback';
        feedback.textContent = 'O documento deve ter 11 (CPF) ou 14 (CNPJ) dígitos';
        parent.appendChild(feedback);
    }
});

// Validação de CNPJ (frontend)
function validarCNPJCliente(cnpj) {
    if (cnpj.length !== 14 || /^(\\d)\1{13}$/.test(cnpj)) return false;
    let tamanho = cnpj.length - 2;
    let numeros = cnpj.substring(0, tamanho);
    let digitos = cnpj.substring(tamanho);
    let soma = 0;
    let pos = tamanho - 7;
    for (let i = tamanho; i >= 1; i--) {
        soma += numeros.charAt(tamanho - i) * pos--;
        if (pos < 2) pos = 9;
    }
    let resultado = soma % 11 < 2 ? 0 : 11 - soma % 11;
    if (resultado != digitos.charAt(0)) return false;
    tamanho = tamanho + 1;
    numeros = cnpj.substring(0, tamanho);
    soma = 0;
    pos = tamanho - 7;
    for (let i = tamanho; i >= 1; i--) {
        soma += numeros.charAt(tamanho - i) * pos--;
        if (pos < 2) pos = 9;
    }
    resultado = soma % 11 < 2 ? 0 : 11 - soma % 11;
    if (resultado != digitos.charAt(1)) return false;
    return true;
}
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

// Máscara para CEP (99999-999) nos campos cep_endereco e cep_obra
function aplicarMascaraCep(input) {
    let value = input.value.replace(/\D/g, '');
    if (value.length > 8) value = value.slice(0, 8);
    if (value.length > 5) {
        value = value.replace(/(\d{5})(\d{1,3})/, '$1-$2');
    }
    input.value = value;
}

document.addEventListener('DOMContentLoaded', function() {
    var cepEndereco = document.getElementById('cep_endereco');
    var cepObra = document.getElementById('cep_obra');
    if (cepEndereco) {
        cepEndereco.addEventListener('input', function(e) {
            aplicarMascaraCep(e.target);
        });
        cepEndereco.addEventListener('blur', function(e) {
            aplicarMascaraCep(e.target);
        });
        // Aplica máscara ao carregar valor existente
        aplicarMascaraCep(cepEndereco);
    }
    if (cepObra) {
        cepObra.addEventListener('input', function(e) {
            aplicarMascaraCep(e.target);
        });
        cepObra.addEventListener('blur', function(e) {
            aplicarMascaraCep(e.target);
        });
        // Aplica máscara ao carregar valor existente
        aplicarMascaraCep(cepObra);
    }
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