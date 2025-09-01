<?php
require_once '../config/protect.php';
require_once '../config/config.php';

$erro = '';
$sucesso = '';
$editando = false;
$custo = [];

// Verificar se estamos editando
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $editando = true;
    $id_custo = (int) $_GET['id'];
    
    // Buscar dados do custo para edição
    try {
        $stmt = $pdo->prepare("SELECT * FROM custos WHERE id_custo = ?");
        $stmt->execute([$id_custo]);
        $custo = $stmt->fetch();
        
        if (!$custo) {
            $erro = "Custo não encontrado!";
            $editando = false;
        }
    } catch (PDOException $e) {
        $erro = "Erro ao buscar custo: " . $e->getMessage();
        $editando = false;
    }
}

// Processar formulário quando enviado
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    try {
        $data = $_POST['data'];
        $id_funcionario = $_POST['id_funcionario'];
        $id_peca = $_POST['id_peca'];
        $ambiente = $_POST['ambiente'];
        $id_materia = $_POST['id_materia'];
        $comp = $_POST['comp'] ?: 0;
        $larg = $_POST['larg'] ?: 0;
        $qntd = $_POST['qntd'] ?: 0;
        $total = $_POST['total'] ?: 0;
        $vlrvenda = str_replace(['R$', '.', ','], ['', '', '.'], $_POST['vlrvenda']);
        $vlrcompra = str_replace(['R$', '.', ','], ['', '', '.'], $_POST['vlrcompra']);
        
        if ($editando && isset($_POST['id_custo'])) {
            // Atualizar registro existente
            $sql = "UPDATE custos SET data = ?, id_funcionario = ?, id_peca = ?, ambiente = ?, id_materia = ?, 
                    comp = ?, larg = ?, qntd = ?, total = ?, vlrvenda = ?, vlrcompra = ? WHERE id_custo = ?";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$data, $id_funcionario, $id_peca, $ambiente, $id_materia, $comp, $larg, $qntd, $total, $vlrvenda, $vlrcompra, $_POST['id_custo']]);
            
            $sucesso = "Custo atualizado com sucesso!";
        } else {
            // Inserir novo registro
            $sql = "INSERT INTO custos (data, id_funcionario, id_peca, ambiente, id_materia, comp, larg, qntd, total, vlrvenda, vlrcompra)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$data, $id_funcionario, $id_peca, $ambiente, $id_materia, $comp, $larg, $qntd, $total, $vlrvenda, $vlrcompra]);
            
            $sucesso = "Custo adicionado com sucesso!";
            
            // Limpar o formulário após sucesso (apenas para novos registros)
            $_POST = [];
        }
        
    } catch (PDOException $e) {
        $erro = ($editando ? "Erro ao atualizar custo: " : "Erro ao adicionar custo: ") . $e->getMessage();
    }
}

// Buscar funcionários para o dropdown
try {
    $stmtFuncionarios = $pdo->prepare("SELECT id_funcionario, nome FROM funcionarios WHERE status = 1 ORDER BY nome");
    $stmtFuncionarios->execute();
    $funcionarios = $stmtFuncionarios->fetchAll();
} catch (PDOException $e) {
    $funcionarios = [];
}

// Buscar peças para o dropdown
try {
    $stmtPecas = $pdo->prepare("SELECT id_peca, tipo FROM pecas ORDER BY tipo");
    $stmtPecas->execute();
    $pecas = $stmtPecas->fetchAll();
} catch (PDOException $e) {
    $pecas = [];
}

// Buscar matérias-primas para o dropdown
try {
    $stmtMaterias = $pdo->prepare("SELECT id_materia, nome FROM materia_prima ORDER BY nome");
    $stmtMaterias->execute();
    $materias = $stmtMaterias->fetchAll();
} catch (PDOException $e) {
    $materias = [];
}

// Função para obter valor do campo (POST ou dados do banco)
function getFieldValue($fieldName, $custo = [], $default = '') {
    if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST[$fieldName])) {
        return $_POST[$fieldName];
    } elseif (!empty($custo) && isset($custo[$fieldName])) {
        return $custo[$fieldName];
    }
    return $default;
}

require_once '../views/header.php';
?>

<div class="container">
    <h2><?= $editando ? 'Editar Custo' : 'Cadastro de Custos' ?></h2>
    
    <?php if ($sucesso): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($sucesso) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    
    <?php if ($erro): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($erro) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <form method="POST" action="" class="mt-3">
        <?php if ($editando): ?>
            <input type="hidden" name="id_custo" value="<?= $custo['id_custo'] ?>">
        <?php endif; ?>
        
        <!-- Primeira linha: Data, Funcionário, Peça -->
        <div class="row g-3 mb-3">
            <div class="col-md-2">
                <label for="data" class="form-label">Data <span class="text-danger">*</span></label>
                <input type="date" class="form-control" id="data" name="data" required 
                       value="<?= htmlspecialchars(getFieldValue('data', $custo, date('Y-m-d'))) ?>">
            </div>
            
            <div class="col-md-4">
                <label for="id_funcionario" class="form-label">Funcionário <span class="text-danger">*</span></label>
                <div class="search-select-wrapper">
                    <input type="text" class="form-control search-input" placeholder="Digite para buscar funcionário..." autocomplete="off">
                    <select class="form-control select-hidden" id="id_funcionario" name="id_funcionario" required>
                        <option value="">Selecione um funcionário</option>
                        <?php foreach ($funcionarios as $funcionario): ?>
                            <option value="<?= $funcionario['id_funcionario'] ?>" 
                                    <?= getFieldValue('id_funcionario', $custo) == $funcionario['id_funcionario'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($funcionario['nome']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="search-results"></div>
                </div>
            </div>
            
            <div class="col-md-3">
                <label for="id_peca" class="form-label">Peça <span class="text-danger">*</span></label>
                <div class="search-select-wrapper">
                    <input type="text" class="form-control search-input" placeholder="Digite para buscar peça..." autocomplete="off">
                    <select class="form-control select-hidden" id="id_peca" name="id_peca" required>
                        <option value="">Selecione uma peça</option>
                        <?php foreach ($pecas as $peca): ?>
                            <option value="<?= $peca['id_peca'] ?>" 
                                    <?= getFieldValue('id_peca', $custo) == $peca['id_peca'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($peca['tipo']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="search-results"></div>
                </div>
            </div>
            
            <div class="col-md-3">
                <label for="ambiente" class="form-label">Ambiente</label>
                <input type="text" class="form-control" id="ambiente" name="ambiente" 
                       placeholder="Ex: Casa"
                       value="<?= htmlspecialchars(getFieldValue('ambiente', $custo)) ?>">
            </div>
        </div>
        
        <!-- Segunda linha: Matéria Prima, Comprimento, Largura, Quantidade -->
        <div class="row g-3 mb-3">
            <div class="col-md-3">
                <label for="id_materia" class="form-label">Matéria Prima <span class="text-danger">*</span></label>
                <div class="search-select-wrapper">
                    <input type="text" class="form-control search-input" placeholder="Digite para buscar matéria prima..." autocomplete="off">
                    <select class="form-control select-hidden" id="id_materia" name="id_materia" required>
                        <option value="">Selecione uma matéria prima</option>
                        <?php foreach ($materias as $materia): ?>
                            <option value="<?= $materia['id_materia'] ?>" 
                                    <?= getFieldValue('id_materia', $custo) == $materia['id_materia'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($materia['nome']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="search-results"></div>
                </div>
            </div>
            
            <div class="col-md-2">
                <label for="comp" class="form-label">Comprimento (m)</label>
                <input type="number" class="form-control" id="comp" name="comp" step="0.01" min="0" 
                       value="<?= htmlspecialchars(getFieldValue('comp', $custo)) ?>" placeholder="0.00">
            </div>
            
            <div class="col-md-2">
                <label for="larg" class="form-label">Largura (m)</label>
                <input type="number" class="form-control" id="larg" name="larg" step="0.01" min="0" 
                       value="<?= htmlspecialchars(getFieldValue('larg', $custo)) ?>" placeholder="0.00">
            </div>
            
            <div class="col-md-2">
                <label for="qntd" class="form-label">Quantidade</label>
                <input type="number" class="form-control" id="qntd" name="qntd" min="0" 
                       value="<?= htmlspecialchars(getFieldValue('qntd', $custo)) ?>" placeholder="0">
            </div>
            
            <div class="col-md-3">
                <label for="total" class="form-label">Total</label>
                <input type="number" class="form-control" id="total" name="total" step="0.01" min="0"
                       value="<?= htmlspecialchars(getFieldValue('total', $custo)) ?>" placeholder="0,00">
            </div>
        </div>
        
        <!-- Terceira linha: Valor Venda e Valor Compra -->
        <div class="row g-3 mb-4">
            <div class="col-md-6">
                <label for="vlrvenda" class="form-label">Valor Venda (R$) <span class="text-danger">*</span></label>
                <input type="text" class="form-control money-mask" id="vlrvenda" name="vlrvenda" required
                       value="<?= htmlspecialchars(getFieldValue('vlrvenda', $custo)) ?>" placeholder="R$ 0,00">
            </div>
            
            <div class="col-md-6">
                <label for="vlrcompra" class="form-label">Valor Compra (R$) <span class="text-danger">*</span></label>
                <input type="text" class="form-control money-mask" id="vlrcompra" name="vlrcompra" required
                       value="<?= htmlspecialchars(getFieldValue('vlrcompra', $custo)) ?>" placeholder="R$ 0,00">
            </div>
        </div>
        
        <div class="row">
            <div class="col-12">
                <button type="submit" class="btn btn-primary">
                    <?= $editando ? 'Atualizar Custo' : 'Cadastrar Custo' ?>
                </button>
                <a href="custos_list.php" class="btn btn-secondary">Voltar para Lista</a>
            </div>
        </div>
    </form>
</div>

<style>
.search-select-wrapper {
    position: relative;
    width: 100%;
}

.search-input {
    width: 100%;
    padding: 0.375rem 0.75rem;
    font-size: 1rem;
    font-weight: 400;
    line-height: 1.5;
    color: #212529;
    background-color: #fff;
    background-image: none;
    border: 1px solid #ced4da;
    border-radius: 0.375rem;
    transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
}

.search-input:focus {
    color: #212529;
    background-color: #fff;
    border-color: #86b7fe;
    outline: 0;
    box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
}

.select-hidden {
    display: none !important;
}

.search-results {
    position: absolute;
    top: 100%;
    left: 0;
    right: 0;
    background: white;
    border: 1px solid #ddd;
    border-top: none;
    border-radius: 0 0 0.375rem 0.375rem;
    max-height: 200px;
    overflow-y: auto;
    z-index: 1000;
    display: none;
    box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
}

.search-result-item {
    padding: 8px 12px;
    cursor: pointer;
    border-bottom: 1px solid #eee;
    font-size: 0.9rem;
    color: #212529;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.search-result-item:hover {
    background-color: #f8f9fa;
    color: #495057;
}

.search-result-item:last-child {
    border-bottom: none;
}

.search-result-item.text-muted {
    color: #6c757d !important;
    font-style: italic;
}
</style>

<script>
// Função para criar busca em selects
function createSearchableSelect(wrapper) {
    const input = wrapper.querySelector('.search-input');
    const select = wrapper.querySelector('select');
    const resultsDiv = wrapper.querySelector('.search-results');
    const options = Array.from(select.options).slice(1); // Remove a primeira opção vazia
    
    // Função para filtrar e mostrar resultados
    function showResults(filter = '') {
        resultsDiv.innerHTML = '';
        
        const filteredOptions = options.filter(option => 
            option.textContent.toLowerCase().trim().includes(filter.toLowerCase().trim())
        );
        
        if (filteredOptions.length === 0 && filter !== '') {
            resultsDiv.innerHTML = '<div class="search-result-item text-muted">Nenhum resultado encontrado</div>';
        } else {
            filteredOptions.forEach(option => {
                const div = document.createElement('div');
                div.className = 'search-result-item';
                div.textContent = option.textContent.trim(); // Remove espaços extras
                div.addEventListener('click', () => selectOption(option));
                resultsDiv.appendChild(div);
            });
        }
        
        resultsDiv.style.display = filteredOptions.length > 0 || filter !== '' ? 'block' : 'none';
    }
    
    // Função para selecionar uma opção
    function selectOption(option) {
        select.value = option.value;
        input.value = option.textContent.trim(); // Remove espaços extras
        resultsDiv.style.display = 'none';
    }
    
    // Eventos do input
    input.addEventListener('input', (e) => {
        showResults(e.target.value);
    });
    
    input.addEventListener('focus', () => {
        showResults(input.value);
    });
    
    // Fechar resultados ao clicar fora
    document.addEventListener('click', (e) => {
        if (!wrapper.contains(e.target)) {
            resultsDiv.style.display = 'none';
        }
    });
    
    // Carregar valor inicial se existir
    const selectedOption = select.options[select.selectedIndex];
    if (selectedOption && selectedOption.value) {
        input.value = selectedOption.textContent.trim(); // Remove espaços extras
    }
    
    // Limpar espaços ao perder o foco
    input.addEventListener('blur', () => {
        input.value = input.value.trim();
    });
}

// Máscara para valores monetários
function formatMoney(value) {
    value = value.replace(/\D/g, '');
    value = (value / 100).toFixed(2) + '';
    value = value.replace(".", ",");
    value = value.replace(/(\d)(?=(\d{3})+(?!\d))/g, "$1.");
    return 'R$ ' + value;
}

// Função para formatar valor monetário vindos do banco
function formatMoneyFromDB(value) {
    if (!value || value === '') return 'R$ 0,00';
    if (typeof value === 'string' && value.includes('R$')) return value;
    
    const numValue = parseFloat(value);
    return 'R$ ' + numValue.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

// Inicializar quando a página carregar
document.addEventListener('DOMContentLoaded', function() {
    // Criar busca nos selects
    const searchWrappers = document.querySelectorAll('.search-select-wrapper');
    searchWrappers.forEach(createSearchableSelect);
    
    // Aplicar máscara monetária
    const moneyFields = document.querySelectorAll('.money-mask');
    moneyFields.forEach(function(field) {
        // Formatar valores vindos do banco
        if (field.value && !field.value.includes('R$')) {
            field.value = formatMoneyFromDB(field.value);
        }
        
        field.addEventListener('input', function(e) {
            e.target.value = formatMoney(e.target.value);
        });
        
        field.addEventListener('focus', function(e) {
            if (e.target.value === 'R$ 0,00' || e.target.value === '') {
                e.target.value = 'R$ ';
            }
        });
        
        field.addEventListener('blur', function(e) {
            if (e.target.value === 'R$ ' || e.target.value === '') {
                e.target.value = 'R$ 0,00';
            }
        });
    });
});
</script>

<?php require_once '../views/footer.php'; ?>