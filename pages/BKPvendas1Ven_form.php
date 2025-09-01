<?php
require_once '../config/protect.php';
require_once '../config/config.php';

$erro = '';
$sucesso = '';
$novaVenda = false;
$vendaId = null;
$venda = [
    'id_venda' => '',
    'id_vendedor' => '',
    'id_comissao' => '',
    'dt_venda' => date('Y-m-d'),
    'cliente' => '',
    'vlr_total' => '',
    'vlr_entrada' => '',
    'forma_pg' => '',
    'qtd_parcelas' => '1',
    'dt_boletos' => ''
];

// Buscar vendedores ativos
$stmtVendedores = $pdo->prepare("SELECT id_vendedor, nome FROM vendedores WHERE STATUS = 1 ORDER BY nome");
$stmtVendedores->execute();
$vendedores = $stmtVendedores->fetchAll();

// Buscar comissões disponíveis
$stmtComissoes = $pdo->prepare("SELECT id_comissao, valor FROM comissao ORDER BY valor");
$stmtComissoes->execute();
$comissoes = $stmtComissoes->fetchAll();

// Se é edição, carregar dados
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $id = (int) $_GET['id'];
    $stmt = $pdo->prepare("SELECT * FROM vendas WHERE id_venda = ?");
    $stmt->execute([$id]);
    $vendaExistente = $stmt->fetch();
    
    if ($vendaExistente) {
        $venda = $vendaExistente;
        $venda['dt_venda'] = date('Y-m-d', strtotime($venda['dt_venda']));
        if ($venda['dt_boletos']) {
            $venda['dt_boletos'] = date('Y-m-d', strtotime($venda['dt_boletos']));
        }
    } else {
        $erro = 'Venda não encontrada.';
    }
}

// Processar formulário
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_vendedor = $_POST['id_vendedor'] ?? '';
    $id_comissao = $_POST['id_comissao'] ?? '';
    $dt_venda = $_POST['dt_venda'] ?? '';
    $cliente = trim($_POST['cliente'] ?? '');
    $vlr_total = str_replace(['.', ','], ['', '.'], $_POST['vlr_total'] ?? '');
    $vlr_entrada = str_replace(['.', ','], ['', '.'], $_POST['vlr_entrada'] ?? '');
    $forma_pg = $_POST['forma_pg'] ?? '';
    $qtd_parcelas = $_POST['qtd_parcelas'] ?? '1';
    $dt_boletos = $_POST['dt_boletos'] ?? '';

    // NOVA LÓGICA: Ajustar qtd_parcelas baseado na forma de pagamento
    $formas_avista = ['À Vista', 'PIX', 'Dinheiro', 'Cartão de Débito'];
    if (in_array($forma_pg, $formas_avista)) {
        $qtd_parcelas = '0';
    }

    // Validações
    if (empty($id_vendedor)) {
        $erro = 'Selecione um vendedor.';
    } elseif (empty($cliente)) {
        $erro = 'Informe o nome do cliente.';
    } elseif (empty($vlr_total) || !is_numeric($vlr_total) || $vlr_total <= 0) {
        $erro = 'Informe um valor total válido.';
    } elseif (!is_numeric($vlr_entrada) || $vlr_entrada < 0) {
        $erro = 'Informe um valor de entrada válido.';
    } elseif ($vlr_entrada > $vlr_total) {
        $erro = 'O valor de entrada não pode ser maior que o valor total.';
    } elseif (empty($venda['id_venda']) && $vlr_entrada == $vlr_total && $vlr_total > 0) {
        $erro = 'Para uma nova venda, o valor de entrada não pode ser igual ao valor total. Use forma de pagamento "À Vista", "PIX" ou "Dinheiro" para vendas sem parcelamento.';
    } elseif (empty($forma_pg)) {
        $erro = 'Selecione uma forma de pagamento.';
    } elseif (!is_numeric($qtd_parcelas) || $qtd_parcelas < 0) {
        $erro = 'Informe uma quantidade de parcelas válida.';
    } elseif (($forma_pg === 'Parcelado' || $forma_pg === 'Cartão de Crédito' || $forma_pg === 'Permuta Parcelado') && $qtd_parcelas < 1) {
        $erro = 'Para pagamento parcelado, informe pelo menos 1 parcela.';
    } elseif (($forma_pg === 'Parcelado' || $forma_pg === 'Cartão de Crédito' || $forma_pg === 'Permuta Parcelado') && empty($dt_boletos)) {
        $erro = 'Para pagamento parcelado, informe a data dos boletos.';
    }

    // Validar se vendedor existe e está ativo
    if (empty($erro)) {
        $stmtVendedor = $pdo->prepare("SELECT id_vendedor FROM vendedores WHERE id_vendedor = ? AND STATUS = 1");
        $stmtVendedor->execute([$id_vendedor]);
        if (!$stmtVendedor->fetch()) {
            $erro = 'Vendedor selecionado não é válido ou está inativo.';
        }
    }

    // Validar comissão se informada
    if (empty($erro) && !empty($id_comissao)) {
        $stmtComissao = $pdo->prepare("SELECT id_comissao FROM comissao WHERE id_comissao = ?");
        $stmtComissao->execute([$id_comissao]);
        if (!$stmtComissao->fetch()) {
            $erro = 'Comissão selecionada não é válida.';
        }
    }

    if (empty($erro)) {
        try {
            $pdo->beginTransaction();
            
            // Preparar dados para inserção/atualização
            $dt_boletos_final = (($forma_pg === 'Parcelado' || $forma_pg === 'Cartão de Crédito' || $forma_pg === 'Permuta Parcelado') && !empty($dt_boletos)) ? $dt_boletos : null;
            $id_comissao_final = !empty($id_comissao) ? $id_comissao : null;
            
            if (empty($venda['id_venda'])) {
                // Inserir nova venda
                $stmt = $pdo->prepare("
                    INSERT INTO vendas (id_vendedor, id_comissao, dt_venda, cliente, vlr_total, vlr_entrada, forma_pg, qtd_parcelas, dt_boletos) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $id_vendedor, 
                    $id_comissao_final, 
                    $dt_venda, 
                    $cliente, 
                    $vlr_total, 
                    $vlr_entrada, 
                    $forma_pg, 
                    $qtd_parcelas, 
                    $dt_boletos_final
                ]);
                
                $id_venda = $pdo->lastInsertId();
                $vendaId = $id_venda;
                
                // Se for parcelado, criar boletos (apenas se qtd_parcelas >= 1)
                if (($forma_pg === 'Parcelado' || $forma_pg === 'Cartão de Crédito' || $forma_pg === 'Permuta Parcelado') && $qtd_parcelas >= 1) {
                    $vlr_parcela = ($vlr_total - $vlr_entrada) / $qtd_parcelas;
                    
                    for ($i = 1; $i <= $qtd_parcelas; $i++) {
                        // CORREÇÃO: Para 1 parcela, vence 30 dias após a data base
                        if ($qtd_parcelas == 1) {
                            $dt_vencimento = date('Y-m-d', strtotime($dt_boletos . " +30 days"));
                        } else {
                            // Para múltiplas parcelas, vence mensalmente a partir da data informada
                            $dt_vencimento = date('Y-m-d', strtotime($dt_boletos . " +{$i} month"));
                        }
                        
                        $stmtBoleto = $pdo->prepare("
                            INSERT INTO boleto (qtd_parcelas, valor, id_vendedor, id_venda, n_parcela, dt_vencimento, id_comissao, status) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, 'A Vencer')
                        ");
                        $stmtBoleto->execute([
                            $qtd_parcelas,
                            $vlr_parcela,
                            $id_vendedor,
                            $id_venda,
                            $i,
                            $dt_vencimento,
                            $id_comissao_final
                        ]);
                    }
                }
                
                $sucesso = 'Venda cadastrada com sucesso!';
                $novaVenda = true;
                
            } else {
                // Atualizar venda existente
                $stmt = $pdo->prepare("
                    UPDATE vendas SET 
                        id_vendedor = ?, id_comissao = ?, dt_venda = ?, cliente = ?, 
                        vlr_total = ?, vlr_entrada = ?, forma_pg = ?, qtd_parcelas = ?, dt_boletos = ?
                    WHERE id_venda = ?
                ");
                $stmt->execute([
                    $id_vendedor, 
                    $id_comissao_final, 
                    $dt_venda, 
                    $cliente, 
                    $vlr_total, 
                    $vlr_entrada, 
                    $forma_pg, 
                    $qtd_parcelas, 
                    $dt_boletos_final,
                    $venda['id_venda']
                ]);
                
                // Atualizar boletos existentes se a data base foi alterada
                if (($forma_pg === 'Parcelado' || $forma_pg === 'Cartão de Crédito' || $forma_pg === 'Permuta Parcelado') && !empty($dt_boletos_final)) {
                    // Verificar se a data base dos boletos foi alterada
                    $dt_boletos_anterior = $venda['dt_boletos'] ? date('Y-m-d', strtotime($venda['dt_boletos'])) : null;
                    
                    if ($dt_boletos_anterior !== $dt_boletos_final) {
                        // Buscar boletos existentes desta venda
                        $stmtBoletos = $pdo->prepare("
                            SELECT id_boleto, n_parcela, status 
                            FROM boleto 
                            WHERE id_venda = ? 
                            ORDER BY n_parcela
                        ");
                        $stmtBoletos->execute([$venda['id_venda']]);
                        $boletosExistentes = $stmtBoletos->fetchAll();
                        
                        // Atualizar as datas de vencimento dos boletos
                        foreach ($boletosExistentes as $boleto) {
                            // Só atualizar boletos que ainda não foram pagos
                            if ($boleto['status'] !== 'Pago' && $boleto['status'] !== 'Cancelado') {
                                // CORREÇÃO: Para 1 parcela, vence 30 dias após a data base
                                if ($qtd_parcelas == 1) {
                                    $nova_dt_vencimento = date('Y-m-d', strtotime($dt_boletos_final . " +30 days"));
                                } else {
                                    // Para múltiplas parcelas, vence mensalmente
                                    $nova_dt_vencimento = date('Y-m-d', strtotime($dt_boletos_final . " +{$boleto['n_parcela']} month"));
                                }
                                
                                // Atualizar o boleto
                                $stmtUpdateBoleto = $pdo->prepare("
                                    UPDATE boleto 
                                    SET dt_vencimento = ?, 
                                        qtd_parcelas = ?,
                                        id_comissao = ?,
                                        id_vendedor = ?
                                    WHERE id_boleto = ?
                                ");
                                $stmtUpdateBoleto->execute([
                                    $nova_dt_vencimento,
                                    $qtd_parcelas,
                                    $id_comissao_final,
                                    $id_vendedor,
                                    $boleto['id_boleto']
                                ]);
                            }
                        }
                        
                        $sucesso = 'Venda atualizada com sucesso! As datas dos boletos foram recalculadas.';
                    } else {
                        // Atualizar apenas outros dados dos boletos se necessário
                        $stmtUpdateBoletos = $pdo->prepare("
                            UPDATE boleto 
                            SET qtd_parcelas = ?, 
                                id_comissao = ?,
                                id_vendedor = ?
                            WHERE id_venda = ?
                        ");
                        $stmtUpdateBoletos->execute([
                            $qtd_parcelas,
                            $id_comissao_final,
                            $id_vendedor,
                            $venda['id_venda']
                        ]);
                        
                        $sucesso = 'Venda atualizada com sucesso!';
                    }
                } else {
                    $sucesso = 'Venda atualizada com sucesso!';
                }
                
                // Se mudou de parcelado para outro tipo de pagamento, pode querer deletar boletos
                if (!in_array($forma_pg, ['Parcelado', 'Cartão de Crédito', 'Permuta Parcelado']) && 
                    in_array($venda['forma_pg'], ['Parcelado', 'Cartão de Crédito', 'Permuta Parcelado'])) {
                    // Verificar se existem boletos não pagos para deletar
                    $stmtBoletosPendentes = $pdo->prepare("
                        SELECT COUNT(*) as total 
                        FROM boleto 
                        WHERE id_venda = ? AND status NOT IN ('Pago', 'Cancelado')
                    ");
                    $stmtBoletosPendentes->execute([$venda['id_venda']]);
                    $boletosPendentes = $stmtBoletosPendentes->fetch()['total'];
                    
                    if ($boletosPendentes > 0) {
                        $sucesso .= ' Atenção: Existem ' . $boletosPendentes . ' boleto(s) pendente(s) que podem precisar ser cancelados manualmente.';
                    }
                }
                
                $novaVenda = false;
            }
            
            $pdo->commit();
            
            // Atualizar array para exibir valores atualizados
            $venda['id_vendedor'] = $id_vendedor;
            $venda['id_comissao'] = $id_comissao_final;
            $venda['dt_venda'] = $dt_venda;
            $venda['cliente'] = $cliente;
            $venda['vlr_total'] = $vlr_total;
            $venda['vlr_entrada'] = $vlr_entrada;
            $venda['forma_pg'] = $forma_pg;
            $venda['qtd_parcelas'] = $qtd_parcelas;
            $venda['dt_boletos'] = $dt_boletos_final;
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $erro = 'Erro ao salvar venda: ' . $e->getMessage();
        }
    }
}

require_once '../views/header.php';
?>

<div class="container">
    <h2><?= empty($venda['id_venda']) && !$novaVenda ? 'Nova Venda' : ($novaVenda ? 'Nova Venda' : 'Editar Venda') ?></h2>

    <?php if ($erro): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($erro) ?></div>
    <?php endif; ?>

    <?php if ($sucesso && !$novaVenda): ?>
        <div class="alert alert-success"><?= htmlspecialchars($sucesso) ?></div>
    <?php endif; ?>

    <form method="post" class="row g-3" id="vendaForm">
        <div class="col-md-6">
            <label for="id_vendedor" class="form-label">Vendedor *</label>
            <select name="id_vendedor" id="id_vendedor" class="form-control" required>
                <option value="">Selecione um vendedor</option>
                <?php foreach ($vendedores as $vendedor): ?>
                    <option value="<?= $vendedor['id_vendedor'] ?>" 
                            <?= $venda['id_vendedor'] == $vendedor['id_vendedor'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($vendedor['nome']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="col-md-6">
            <label for="id_comissao" class="form-label">Comissão</label>
            <select name="id_comissao" id="id_comissao" class="form-control">
                <option value="">Sem comissão</option>
                <?php foreach ($comissoes as $comissao): ?>
                    <option value="<?= $comissao['id_comissao'] ?>" 
                            <?= $venda['id_comissao'] == $comissao['id_comissao'] ? 'selected' : '' ?>>
                        <?= $comissao['valor'] ?>%
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="col-md-6">
            <label for="dt_venda" class="form-label">Data da Venda *</label>
            <input type="date" name="dt_venda" id="dt_venda" class="form-control" 
                   value="<?= htmlspecialchars($venda['dt_venda']) ?>" required>
        </div>

        <div class="col-md-6">
            <label for="cliente" class="form-label">Nome do Cliente *</label>
            <input type="text" name="cliente" id="cliente" class="form-control" 
                   value="<?= htmlspecialchars($venda['cliente']) ?>" required maxlength="100"
                   oninput="this.value = this.value.toUpperCase();">

        </div>

        <div class="col-md-6">
            <label for="vlr_total" class="form-label">Valor Total *</label>
            <input type="text" name="vlr_total" id="vlr_total" class="form-control money" 
                   value="<?= $venda['vlr_total'] ? number_format($venda['vlr_total'], 2, ',', '.') : '' ?>" required>
        </div>

        <div class="col-md-6">
            <label for="vlr_entrada" class="form-label">Valor de Entrada</label>
            <input type="text" name="vlr_entrada" id="vlr_entrada" class="form-control money" 
                   value="<?= $venda['vlr_entrada'] ? number_format($venda['vlr_entrada'], 2, ',', '.') : '0,00' ?>">
        </div>

        <div class="col-md-6">
            <label for="forma_pg" class="form-label">Forma de Pagamento *</label>
            <select name="forma_pg" id="forma_pg" class="form-control" required>
                <option value="">Selecione</option>
                <option value="À Vista" <?= $venda['forma_pg'] === 'À Vista' ? 'selected' : '' ?>>À Vista</option>
                <option value="Parcelado" <?= $venda['forma_pg'] === 'Parcelado' ? 'selected' : '' ?>>Boleto Parcelado</option>
                <option value="PIX" <?= $venda['forma_pg'] === 'PIX' ? 'selected' : '' ?>>PIX</option>
                <option value="Cartão de Crédito" <?= $venda['forma_pg'] === 'Cartão de Crédito' ? 'selected' : '' ?>>Cartão de Crédito</option>
                <option value="Cartão de Débito" <?= $venda['forma_pg'] === 'Cartão de Débito' ? 'selected' : '' ?>>Cartão de Débito</option>
                <option value="Dinheiro" <?= $venda['forma_pg'] === 'Dinheiro' ? 'selected' : '' ?>>Dinheiro</option>
                <option value="Permuta Parcelado" <?= $venda['forma_pg'] === 'Permuta Parcelado' ? 'selected' : '' ?>>Permuta Parcelado</option>
            </select>
        </div>

        <div class="col-md-6">
            <label for="qtd_parcelas" class="form-label">Quantidade de Parcelas</label>
            <input type="number" name="qtd_parcelas" id="qtd_parcelas" class="form-control" 
                   value="<?= htmlspecialchars($venda['qtd_parcelas']) ?>" min="0" max="36">
            <small class="form-text text-muted" id="parcelasHelp">
                Pagamentos à vista: 0 parcelas | Parcelados: 1 ou mais
            </small>
        </div>

        <div class="col-md-6" id="dt_boletos_div" style="display: none;">
            <label for="dt_boletos" class="form-label">Data Base dos Boletos</label>
            <input type="date" name="dt_boletos" id="dt_boletos" class="form-control" 
                   value="<?= htmlspecialchars($venda['dt_boletos']) ?>">
            <small class="form-text text-muted">Primeira parcela vence 1 mês após esta data</small>
            <?php if (!empty($venda['id_venda']) && in_array($venda['forma_pg'], ['Parcelado', 'Cartão de Crédito', 'Permuta Parcelado'])): ?>
                <small class="form-text text-warning"><strong>Atenção:</strong> Alterar esta data recalculará as datas de vencimento dos boletos não pagos.</small>
            <?php endif; ?>
        </div>

        <div class="col-12">
            <button type="submit" class="btn btn-primary">Salvar</button>
            <a href="vendas_list.php" class="btn btn-secondary">Cancelar</a>
        </div>
    </form>
</div>

<?php if ($sucesso && $novaVenda): ?>
    <!-- Modal de Sucesso -->
    <div class="modal fade" id="successModal" tabindex="-1" aria-labelledby="successModalLabel" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title" id="successModalLabel">
                        <i class="fas fa-check-circle me-2"></i>Sucesso!
                    </h5>
                </div>
                <div class="modal-body text-center">
                    <div class="mb-3">
                        <i class="fas fa-check-circle text-success" style="font-size: 3rem;"></i>
                    </div>
                    <h5>Venda cadastrada com sucesso!</h5>
                    <p class="text-muted">
                        Cliente: <strong><?= htmlspecialchars($venda['cliente']) ?></strong><br>
                        Valor: <strong>R$ <?= number_format($venda['vlr_total'], 2, ',', '.') ?></strong>
                        <?php if (isset($vendaId)): ?>
                            <br><small class="text-info">ID da Venda: #<?= $vendaId ?></small>
                        <?php endif; ?>
                    </p>
                </div>
                <div class="modal-footer justify-content-center">
                    <button type="button" class="btn btn-success btn-lg" id="btnNovaVenda">
                        <i class="fas fa-plus me-2"></i>OK - Nova Venda
                    </button>
                    <button type="button" class="btn btn-outline-secondary" onclick="window.location.href='vendas_list.php'">
                        <i class="fas fa-list me-2"></i>Ver Vendas
                    </button>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<style>
.modal-content {
    border: none;
    border-radius: 15px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.3);
}

.modal-header.bg-success {
    border-radius: 15px 15px 0 0;
}

.btn-lg {
    padding: 12px 30px;
    font-size: 1.1rem;
}

.fade-out {
    opacity: 0;
    transition: opacity 0.5s ease-out;
}

#qtd_parcelas:disabled {
    background-color: #f8f9fa;
    cursor: not-allowed;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Mostrar modal de sucesso se venda foi cadastrada
    <?php if ($sucesso && $novaVenda): ?>
        const successModal = new bootstrap.Modal(document.getElementById('successModal'));
        successModal.show();
        
        // Função para limpar formulário e fechar modal
        document.getElementById('btnNovaVenda').addEventListener('click', function() {
            // Limpar todos os campos do formulário
            document.getElementById('vendaForm').reset();
            
            // Resetar valores específicos
            document.getElementById('dt_venda').value = '<?= date('Y-m-d') ?>';
            document.getElementById('vlr_entrada').value = '0,00';
            document.getElementById('qtd_parcelas').value = '1';
            
            // Resetar selects para primeira opção
            document.getElementById('id_vendedor').selectedIndex = 0;
            document.getElementById('id_comissao').selectedIndex = 0;
            document.getElementById('forma_pg').selectedIndex = 0;
            
            // Esconder div de boletos
            document.getElementById('dt_boletos_div').style.display = 'none';
            document.getElementById('dt_boletos').required = false;
            document.getElementById('dt_boletos').value = '';
            
            // Habilitar campo de parcelas
            document.getElementById('qtd_parcelas').disabled = false;
            
            // Remover alertas de sucesso/erro
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => alert.remove());
            
            // Remover mensagens de validação personalizadas
            const warningMessages = document.querySelectorAll('.entrada-warning');
            warningMessages.forEach(warning => warning.remove());
            
            // Resetar bordas dos campos
            const inputs = document.querySelectorAll('input, select');
            inputs.forEach(input => {
                input.style.borderColor = '';
                input.setCustomValidity('');
            });
            
            // Focar no primeiro campo
            document.getElementById('id_vendedor').focus();
            
            // Fechar modal
            successModal.hide();
            
            // Remover parâmetros da URL (se houver)
            if (window.location.search) {
                window.history.replaceState({}, document.title, window.location.pathname);
            }
        });
    <?php endif; ?>

    // Máscara para valores monetários
    const moneyInputs = document.querySelectorAll('.money');
    moneyInputs.forEach(input => {
        input.addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            value = (value / 100).toFixed(2);
            value = value.replace('.', ',');
            value = value.replace(/(\d)(?=(\d{3})+(?!\d))/g, '$1.');
            e.target.value = value;
        });
    });

    // Validação de entrada vs total
    const vlrTotal = document.getElementById('vlr_total');
    const vlrEntrada = document.getElementById('vlr_entrada');
    const isEdicao = <?= !empty($venda['id_venda']) && !$novaVenda ? 'true' : 'false' ?>;
    
    function validarEntradaVsTotal() {
        if (!isEdicao) {
            const totalValue = parseFloat(vlrTotal.value.replace(/\./g, '').replace(',', '.')) || 0;
            const entradaValue = parseFloat(vlrEntrada.value.replace(/\./g, '').replace(',', '.')) || 0;
            
            if (totalValue > 0 && entradaValue === totalValue) {
                vlrEntrada.setCustomValidity('Para uma nova venda, o valor de entrada não pode ser igual ao valor total.');
                vlrEntrada.style.borderColor = '#dc3545';
                
                if (!document.querySelector('.entrada-warning')) {
                    const warning = document.createElement('small');
                    warning.className = 'form-text text-danger entrada-warning';
                    warning.textContent = 'Use "À Vista", "PIX" ou "Dinheiro" para pagamento integral.';
                    vlrEntrada.parentNode.appendChild(warning);
                }
            } else {
                vlrEntrada.setCustomValidity('');
                vlrEntrada.style.borderColor = '';
                
                const warning = document.querySelector('.entrada-warning');
                if (warning) {
                    warning.remove();
                }
            }
        }
    }
    
    vlrTotal.addEventListener('input', validarEntradaVsTotal);
    vlrEntrada.addEventListener('input', validarEntradaVsTotal);

    // NOVA LÓGICA: Controle automático de parcelas baseado na forma de pagamento
    const formaPg = document.getElementById('forma_pg');
    const qtdParcelas = document.getElementById('qtd_parcelas');
    const dtBoletosDiv = document.getElementById('dt_boletos_div');
    const dtBoletos = document.getElementById('dt_boletos');
    const parcelasHelp = document.getElementById('parcelasHelp');

    function adjustParcelasBasedOnFormaPg() {
        const formasAvista = ['À Vista', 'PIX', 'Dinheiro', 'Cartão de Débito'];
        const formasParceladas = ['Parcelado', 'Cartão de Crédito', 'Permuta Parcelado'];
        
        if (formasAvista.includes(formaPg.value)) {
            // Para formas à vista, definir 0 parcelas e desabilitar campo
            qtdParcelas.value = '0';
            qtdParcelas.disabled = true;
            qtdParcelas.style.backgroundColor = '#f8f9fa';
            parcelasHelp.textContent = 'Pagamento à vista - 0 parcelas';
            parcelasHelp.className = 'form-text text-info';
            
            // Esconder campos de boletos
            dtBoletosDiv.style.display = 'none';
            dtBoletos.required = false;
            dtBoletos.value = '';
            
        } else if (formasParceladas.includes(formaPg.value)) {
            // Para formas parceladas, habilitar campo e definir mínimo 1
            qtdParcelas.disabled = false;
            qtdParcelas.style.backgroundColor = '';
            if (qtdParcelas.value === '0' || qtdParcelas.value === '') {
                qtdParcelas.value = '1';
            }
            qtdParcelas.min = '1';
            parcelasHelp.textContent = 'Pagamentos parcelados: 1 ou mais parcelas';
            parcelasHelp.className = 'form-text text-muted';
            
            // Mostrar campos de boletos
            dtBoletosDiv.style.display = 'block';
            dtBoletos.required = true;
            
            updateBoletosHelpText();
            
        } else {
            // Forma de pagamento não selecionada, resetar para estado padrão
            qtdParcelas.disabled = false;
            qtdParcelas.style.backgroundColor = '';
            qtdParcelas.value = '1';
            qtdParcelas.min = '0';
            parcelasHelp.textContent = 'Pagamentos à vista: 0 parcelas | Parcelados: 1 ou mais';
            parcelasHelp.className = 'form-text text-muted';
            
            dtBoletosDiv.style.display = 'none';
            dtBoletos.required = false;
            dtBoletos.value = '';
        }
    }

    function updateBoletosHelpText() {
        const formasParceladas = ['Parcelado', 'Cartão de Crédito', 'Permuta Parcelado'];
        
        if (formasParceladas.includes(formaPg.value)) {
            const helpText = document.querySelector('#dt_boletos_div small:not(.text-warning)');
            if (helpText && qtdParcelas.value == 1) {
                helpText.textContent = 'Parcela única vence 30 dias após esta data';
            } else if (helpText) {
                helpText.textContent = 'Primeira parcela vence 1 mês após esta data';
            }
        }
    }

    // Event listeners
    formaPg.addEventListener('change', adjustParcelasBasedOnFormaPg);
    qtdParcelas.addEventListener('change', updateBoletosHelpText);
    qtdParcelas.addEventListener('input', updateBoletosHelpText);
    
    // Aplicar lógica inicial ao carregar a página
    adjustParcelasBasedOnFormaPg();
});
</script>

<?php require_once '../views/footer.php'; ?>