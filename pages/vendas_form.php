<?php
require_once '../config/protect.php';
require_once '../config/config.php';

// Função para definir opções de status e permissão de edição por tipo de usuário
function getStatusOptionsByTipo($tipo)
{
    // Adapte conforme adicionar novos tipos
    switch ($tipo) {
        case 'admin':
            return [
                'options' => ['PENDENTE', 'PRODUCAO'],
                'editavel' => true
            ];
        case 'user':
        default:
            return [
                'options' => ['PENDENTE'],
                'editavel' => false
            ];
    }
}

// Descobrir tipo do usuário logado
$usuario_tipo = $_SESSION['usuario_tipo'] ?? 'user';
$statusPerm = getStatusOptionsByTipo($usuario_tipo);
$status_options = $statusPerm['options'];
$status_editavel = $statusPerm['editavel'];



$erro = '';
$sucesso = '';
$novaVenda = false;
$vendaId = null;
$venda = [
    'id_venda' => '',
    'id_vendedor' => '',
    'id_vendedor2' => '',
    'id_comissao' => '',
    'id_comissao2' => '',
    'dt_venda' => date('Y-m-d'),
    'cliente' => '',
    'vlr_total' => '',
    'vlr_entrada' => '',
    'forma_pg' => '',
    'qtd_parcelas' => '1',
    'dt_boletos' => '',
    'status' => 'PENDENTE'
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
    $id_vendedor2 = $_POST['id_vendedor2'] ?? '';
    $id_comissao = $_POST['id_comissao'] ?? '';
    $id_comissao2 = $_POST['id_comissao2'] ?? '';
    $dt_venda = $_POST['dt_venda'] ?? '';
    $cliente = trim($_POST['cliente'] ?? '');
    $vlr_total = str_replace(['.', ','], ['', '.'], $_POST['vlr_total'] ?? '');
    $vlr_entrada = str_replace(['.', ','], ['', '.'], $_POST['vlr_entrada'] ?? '');
    $forma_pg = $_POST['forma_pg'] ?? '';
    $qtd_parcelas = $_POST['qtd_parcelas'] ?? '1';

    $dt_boletos = $_POST['dt_boletos'] ?? '';
    $status = $_POST['status'] ?? $venda['status'] ?? 'PENDENTE';
    // Validação do status
    $status_enum = ['PENDENTE', 'PRODUCAO'];
    if (!in_array($status, $status_enum)) {
        $status = 'PENDENTE';
    }


    // NOVA LÓGICA: Ajustar qtd_parcelas baseado na forma de pagamento
    $formas_avista = ['À Vista', 'PIX', 'Dinheiro', 'Cartão de Débito'];
    if (in_array($forma_pg, $formas_avista)) {
        $qtd_parcelas = '0';
    }

    // Validações
    if (empty($dt_venda)) {
        $erro = 'Informe a data da venda.';
    } elseif (empty($cliente)) {
        $erro = 'Informe o nome do cliente.';
    }

    // Validar se vendedores existem e estão ativos
    if (empty($erro)) {
        $stmtVendedor = $pdo->prepare("SELECT id_vendedor FROM vendedores WHERE id_vendedor = ? AND STATUS = 1");
        $stmtVendedor->execute([$id_vendedor]);
        if (!$stmtVendedor->fetch()) {
            $erro = 'Vendedor principal selecionado não é válido ou está inativo.';
        }

        if (!empty($id_vendedor2)) {
            $stmtVendedor2 = $pdo->prepare("SELECT id_vendedor FROM vendedores WHERE id_vendedor = ? AND STATUS = 1");
            $stmtVendedor2->execute([$id_vendedor2]);
            if (!$stmtVendedor2->fetch()) {
                $erro = 'Segundo vendedor selecionado não é válido ou está inativo.';
            }
        }
    }

    // Validar comissões se informadas
    if (empty($erro) && !empty($id_comissao)) {
        $stmtComissao = $pdo->prepare("SELECT id_comissao FROM comissao WHERE id_comissao = ?");
        $stmtComissao->execute([$id_comissao]);
        if (!$stmtComissao->fetch()) {
            $erro = 'Comissão do vendedor principal não é válida.';
        }
    }

    if (empty($erro) && !empty($id_comissao2)) {
        $stmtComissao2 = $pdo->prepare("SELECT id_comissao FROM comissao WHERE id_comissao = ?");
        $stmtComissao2->execute([$id_comissao2]);
        if (!$stmtComissao2->fetch()) {
            $erro = 'Comissão do segundo vendedor não é válida.';
        }
    }

    if (empty($erro)) {
        try {
            $pdo->beginTransaction();

            // Preparar dados para inserção/atualização
            $dt_boletos_final = (($forma_pg === 'Parcelado' || $forma_pg === 'Cartão de Crédito' || $forma_pg === 'Permuta Parcelado') && !empty($dt_boletos)) ? $dt_boletos : null;
            $id_comissao_final = !empty($id_comissao) ? $id_comissao : null;
            $id_comissao2_final = !empty($id_comissao2) ? $id_comissao2 : null;
            $id_vendedor2_final = !empty($id_vendedor2) ? $id_vendedor2 : null;

            if (empty($venda['id_venda'])) {
                // Inserir nova venda
                $stmt = $pdo->prepare("
                    INSERT INTO vendas (id_vendedor, id_vendedor2, id_comissao, id_comissao2, dt_venda, cliente, vlr_total, vlr_entrada, forma_pg, qtd_parcelas, dt_boletos, status) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $id_vendedor,
                    $id_vendedor2_final,
                    $id_comissao_final,
                    $id_comissao2_final,
                    $dt_venda,
                    $cliente,
                    $vlr_total,
                    $vlr_entrada,
                    $forma_pg,
                    $qtd_parcelas,
                    $dt_boletos_final,
                    $status
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

                        // Boletos são associados ao vendedor principal por padrão
                        $stmtBoleto = $pdo->prepare("
                            INSERT INTO boleto (qtd_parcelas, valor, id_vendedor, id_venda, n_parcela, dt_vencimento, id_comissao, status) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, 'A Vencer')
                        ");
                        $stmtBoleto->execute([
                            $qtd_parcelas,
                            $vlr_parcela,
                            $id_vendedor, // Vendedor principal
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
                        id_vendedor = ?, id_vendedor2 = ?, id_comissao = ?, id_comissao2 = ?, dt_venda = ?, cliente = ?, 
                        vlr_total = ?, vlr_entrada = ?, forma_pg = ?, qtd_parcelas = ?, dt_boletos = ?, status = ?
                    WHERE id_venda = ?
                ");
                $stmt->execute([
                    $id_vendedor,
                    $id_vendedor2_final,
                    $id_comissao_final,
                    $id_comissao2_final,
                    $dt_venda,
                    $cliente,
                    $vlr_total,
                    $vlr_entrada,
                    $forma_pg,
                    $qtd_parcelas,
                    $dt_boletos_final,
                    $status,
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
                                    $id_vendedor, // Vendedor principal
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
                            $id_vendedor, // Vendedor principal
                            $venda['id_venda']
                        ]);

                        $sucesso = 'Venda atualizada com sucesso!';
                    }
                } else {
                    $sucesso = 'Venda atualizada com sucesso!';
                }

                $novaVenda = false;
            }

            $pdo->commit();

            // Atualizar array para exibir valores atualizados
            $venda['id_vendedor'] = $id_vendedor;
            $venda['id_vendedor2'] = $id_vendedor2_final;
            $venda['id_comissao'] = $id_comissao_final;
            $venda['id_comissao2'] = $id_comissao2_final;
            $venda['dt_venda'] = $dt_venda;
            $venda['cliente'] = $cliente;
            $venda['vlr_total'] = $vlr_total;
            $venda['vlr_entrada'] = $vlr_entrada;
            $venda['forma_pg'] = $forma_pg;
            $venda['qtd_parcelas'] = $qtd_parcelas;
            $venda['dt_boletos'] = $dt_boletos_final;
            $venda['status'] = $status;


        } catch (Exception $e) {
            $pdo->rollBack();
            $erro = 'Erro ao salvar venda: ' . $e->getMessage();
        }
    }
}

require_once '../views/header.php';
?>

<div class="container-fluid">
    <form id="formVenda" method="post" autocomplete="off">
        <h2><?= empty($venda['id_venda']) && !$novaVenda ? 'Nova Venda' : ($novaVenda ? 'Nova Venda' : 'Editar Venda') ?>
        </h2>

        <?php if ($erro): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($erro) ?></div>
        <?php endif; ?>

        <?php if ($sucesso && !$novaVenda): ?>
            <div class="alert alert-success"><?= htmlspecialchars($sucesso) ?></div>
        <?php endif; ?>


        <!-- Nav tabs -->
        <ul class="nav nav-tabs mb-3" id="vendaTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="dados-tab" data-bs-toggle="tab" data-bs-target="#dados"
                    type="button" role="tab" aria-controls="dados" aria-selected="true">
                    Dados da Venda e Vendedores
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="info-tab" data-bs-toggle="tab" data-bs-target="#info" type="button"
                    role="tab" aria-controls="info" aria-selected="false">
                    Informações da Venda
                </button>
            </li>

            <li class="nav-item" role="presentation">
                <button class="nav-link" id="arquivos-tab" data-bs-toggle="tab" data-bs-target="#arquivos" type="button"
                    role="tab" aria-controls="arquivos" aria-selected="false">
                    Arquivos PDF
                </button>
            </li>
        </ul>

        <div class="tab-content" id="vendaTabsContent">
            <!-- Aba 1: Dados da Venda e Vendedores -->
            <div class="tab-pane fade show active" id="dados" role="tabpanel" aria-labelledby="dados-tab">
                <div class="row g-3">

                    <!-- DADOS DA VENDA -->
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">📄 Dados da Venda</h5>
                            </div>
                            <div class="card-body">
                                <!--Linha 1-->
                                <div class="row">


                                    <div class="col-md-4">
                                        <label for="cliente" class="form-label">Nome do Cliente *</label>
                                        <input type="text" name="cliente" id="cliente" class="form-control"
                                            value="<?= htmlspecialchars($venda['cliente']) ?>" required maxlength="100"
                                            autocomplete="off" oninput="buscanome()">
                                        <div id="cliente-sugestoes" class="list-group position-absolute w-100"
                                            style="z-index: 1000;"></div>
                                    </div>
                                    <div class="col-md-4">
                                        <label for="status" class="form-label">Status da Venda</label>
                                        <select name="status" id="status" class="form-control" <?= !$status_editavel ? 'disabled' : '' ?>>
                                            <?php foreach ($status_options as $opt): ?>
                                                <option value="<?= $opt ?>" <?= $venda['status'] === $opt ? 'selected' : '' ?>>
                                                    <?= $opt ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <?php if (!$status_editavel): ?>
                                            <input type="hidden" name="status"
                                                value="<?= htmlspecialchars($venda['status']) ?>">
                                        <?php endif; ?>

                                    </div>



                                    <div class="col-md-4">
                                        <label for="dt_venda" class="form-label">Data da Venda *</label>
                                        <input type="date" name="dt_venda" id="dt_venda" class="form-control"
                                            value="<?= htmlspecialchars($venda['dt_venda']) ?>" required>
                                    </div>
                                </div>
                                <!--Linha 2-->
                                <div class="row">

                                    <div class="col-md-4">
                                        <label for="numero_pedido" class="form-label">Nº Pedido</label>
                                        <input type="text" name="numero_pedido" id="numero_pedido" class="form-control"
                                            value="<?= isset($venda['numero_pedido']) ? htmlspecialchars($venda['numero_pedido']) : '' ?>"
                                            maxlength="50">
                                    </div>

                                    <div class="col-md-4">
                                        <label for="dt_desenho" class="form-label">Data do Desenho</label>
                                        <input type="date" name="dt_desenho" id="dt_desenho" class="form-control"
                                            value="<?= isset($venda['dt_desenho']) ? htmlspecialchars($venda['dt_desenho']) : '' ?>">
                                    </div>

                                    <div class="col-md-4">
                                        <label for="prazo_entrega" class="form-label">Prazo de Entrega (dias)</label>
                                        <input type="number" name="prazo_entrega" id="prazo_entrega"
                                            class="form-control"
                                            value="<?= isset($venda['prazo_entrega']) ? htmlspecialchars($venda['prazo_entrega']) : '' ?>"
                                            min="0">
                                    </div>

                                </div>

                            </div>
                        </div>
                    </div>

                    <!-- VENDEDORES E COMISSÕES -->
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">👥 Vendedores e Comissões</h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <!-- Vendedor Principal -->
                                    <div class="col-md-6">
                                        <label for="id_vendedor" class="form-label">Vendedor Principal *</label>
                                        <select name="id_vendedor" id="id_vendedor" class="form-control">
                                            <option value="">Selecione o vendedor principal</option>
                                            <?php foreach ($vendedores as $vendedor): ?>
                                                <option value="<?= $vendedor['id_vendedor'] ?>"
                                                    <?= $venda['id_vendedor'] == $vendedor['id_vendedor'] ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($vendedor['nome']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>

                                    <div class="col-md-6">
                                        <label for="id_comissao" class="form-label">Comissão do Vendedor
                                            Principal</label>
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

                                    <!-- Segundo Vendedor -->
                                    <div class="col-md-6">
                                        <label for="id_vendedor2" class="form-label">Segundo Vendedor <small
                                                class="text-muted">(Opcional)</small></label>
                                        <select name="id_vendedor2" id="id_vendedor2" class="form-control">
                                            <option value="">Nenhum segundo vendedor</option>
                                            <?php foreach ($vendedores as $vendedor): ?>
                                                <option value="<?= $vendedor['id_vendedor'] ?>"
                                                    <?= $venda['id_vendedor2'] == $vendedor['id_vendedor'] ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($vendedor['nome']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>

                                    <div class="col-md-6">
                                        <label for="id_comissao2" class="form-label">Comissão do Segundo
                                            Vendedor</label>
                                        <select name="id_comissao2" id="id_comissao2" class="form-control">
                                            <option value="">Sem comissão</option>
                                            <?php foreach ($comissoes as $comissao): ?>
                                                <option value="<?= $comissao['id_comissao'] ?>"
                                                    <?= $venda['id_comissao2'] == $comissao['id_comissao'] ? 'selected' : '' ?>>
                                                    <?= $comissao['valor'] ?>%
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-12 mt-3">
                    <button type="submit" class="btn btn-primary">Salvar</button>
                    <a href="vendas_list.php" class="btn btn-secondary">Cancelar</a>
                </div>
            </div>
    </form>

    <!-- Aba 2: Informações da Venda -->
    <div class="tab-pane fade" id="info" role="tabpanel" aria-labelledby="info-tab">
        <div class="row g-3">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">📄 Informações da Venda</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <!-- Matéria-prima -->
                            <div class="col-md-3">
                                <label for="materia_prima"
                                    class="form-label d-flex align-items-center justify-content-between">
                                    <span>Matéria-prima</span>
                                    <a href="../pages/materiaprima_form.php" target="_blank"
                                        class="btn btn-sm btn-outline-primary ms-2"
                                        title="Cadastrar nova matéria-prima">
                                        <i class="bi bi-plus"></i> Novo
                                    </a>
                                </label>
                                <input type="text" name="materia_prima" id="materia_prima" class="form-control"
                                    maxlength="255" autocomplete="off" oninput="buscaMateriaPrima()">
                                <input type="hidden" name="id_materia" id="id_materia">
                                <div id="materia-sugestoes" class="list-group position-absolute w-100"
                                    style="z-index: 1000;"></div>
                            </div>

                            <div class="col-md-3">
                                <label for="peca" class="form-label d-flex align-items-center justify-content-between">
                                    <span>Peça</span>
                                    <a href="../pages/pecas_form.php" target="_blank"
                                        class="btn btn-sm btn-outline-primary ms-2" title="Cadastrar nova peça">
                                        <i class="bi bi-plus"></i> Novo
                                    </a>
                                </label>
                                <input type="text" name="peca" id="peca" class="form-control" maxlength="255"
                                    autocomplete="off" oninput="buscaPeca()">
                                <input type="hidden" name="id_peca" id="id_peca">
                                <div id="peca-sugestoes" class="list-group position-absolute w-100"
                                    style="z-index: 1000;"></div>
                            </div>

                            <!-- Ambiente -->
                            <div class="col-md-3">
                                <label for="ambiente"
                                    class="form-label d-flex align-items-center justify-content-between">
                                    <span>Ambiente</span>
                                    <a href="../pages/ambiente_form.php" target="_blank"
                                        class="btn btn-sm btn-outline-primary ms-2" title="Cadastrar novo ambiente">
                                        <i class="bi bi-plus"></i> Novo
                                    </a>
                                </label>
                                <input type="text" name="ambiente" id="ambiente" class="form-control" maxlength="50"
                                    autocomplete="off" oninput="buscaAmbiente()">
                                <input type="hidden" name="id_ambiente" id="id_ambiente">
                                <div id="ambiente-sugestoes" class="list-group position-absolute w-100"
                                    style="z-index: 1000;"></div>
                            </div>

                            <!-- Quantidade -->
                            <div class="col-md-2">
                                <label for="qtd_itens" class="form-label">Qtd</label>
                                <input type="number" name="qtd_itens" id="qtd_itens" class="form-control" min="1"
                                    value="1">
                            </div>

                        </div>

                        <div class="col-12 mt-3">
                            <button type="button" class="btn btn-primary" id="btnAdicionarItem">Adicionar</button>
                        </div>
                        <hr>
                        <div class="col-12 mt-4">
                            <h5>Itens da Venda</h5>
                            <div class="table-responsive">
                                <table class="table table-bordered table-hover align-middle" id="grid-itens">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Matéria-prima</th>
                                            <th>Peça</th>
                                            <th>Ambiente</th>
                                            <th>Qtd</th>
                                            <th style="width:90px;">Ações</th>
                                        </tr>
                                    </thead>
                                    <tbody id="grid-itens-body">
                                        <tr>
                                            <td colspan="6" class="text-center text-muted">Carregando...</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <script>
            // Debug: listar todos os forms e seus ids ao abrir a aba 3
            document.getElementById('arquivos-tab').addEventListener('shown.bs.tab', function () {
                setTimeout(function () {
                    var forms = document.querySelectorAll('form');
                    var msg = 'FORMS NA PÁGINA:\n';
                    console.log(msg);
                    forms.forEach(function (f, i) {
                        msg += (i + 1) + ': id=' + (f.id || '-') + ', class=' + (f.className || '-') + '\n';
                    });
                    alert(msg);
                }, 300);
            });
            function carregarGridItens() {
                const idVenda = <?= isset($venda['id_venda']) ? (int) $venda['id_venda'] : 'null' ?>;
                const tbody = document.getElementById('grid-itens-body');
                if (!idVenda) {
                    tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted">Salve a venda para adicionar itens.</td></tr>';
                    return;
                }
                fetch('../api/venda_itens_list.php?id_venda=' + idVenda)
                    .then(r => r.json())
                    .then(itens => {
                        if (!Array.isArray(itens) || itens.length === 0) {
                            tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted">Nenhum item cadastrado.</td></tr>';
                            return;
                        }
                        tbody.innerHTML = '';
                        itens.forEach(item => {
                            tbody.innerHTML += `<tr>
                                    <td>${item.nome_materia_prima || '-'}</td>
                                    <td>${item.nome_peca || '-'}</td>
                                    <td>${item.nome_ambiente || '-'}</td>
                                    <td>${item.qtd_itens || '-'}</td>
                                    <td>
                                        <button type="button" class="btn btn-sm btn-warning me-1" title="Editar" onclick="editarItem(event, ${item.id_item})"><i class="bi bi-pencil"></i></button>
                                        <button type="button" class="btn btn-sm btn-danger" title="Remover" onclick="removerItem(event, ${item.id_item})"><i class="bi bi-trash"></i></button>
                                    </td>
                                </tr>`;
                        });
                    })
                    .catch(() => {
                        tbody.innerHTML = '<tr><td colspan="6" class="text-center text-danger">Erro ao carregar itens.</td></tr>';
                    });
            }
            let editandoItemId = null;
            function editarItem(event, id) {
                if (event) event.preventDefault();
                fetch('../api/venda_itens_get.php?id_item=' + id)
                    .then(r => r.json())
                    .then(item => {
                        if (!item || !item.id_item) {
                            alert('Item não encontrado.');
                            return;
                        }
                        document.getElementById('materia_prima').value = item.nome_materia_prima || '';
                        document.getElementById('peca').value = item.nome_peca || '';
                        document.getElementById('ambiente').value = item.nome_ambiente || '';
                        document.getElementById('id_materia').value = item.id_materia || '';
                        document.getElementById('id_peca').value = item.id_peca || '';
                        document.getElementById('id_ambiente').value = item.id_ambiente || '';
                        document.getElementById('qtd_itens').value = item.qtd_itens || 1;
                        editandoItemId = id;
                        const btn = document.getElementById('btnAdicionarItem');
                        btn.textContent = 'Salvar Alterações';
                        btn.classList.remove('btn-primary');
                        btn.classList.add('btn-warning');
                        const infoTab = document.getElementById('info-tab');
                        if (infoTab) infoTab.click();
                        // Remover mensagem de sucesso de venda se estiver visível
                        const successModal = document.getElementById('successModal');
                        if (successModal && successModal.classList.contains('show')) {
                            const modalInstance = bootstrap.Modal.getInstance(successModal);
                            if (modalInstance) modalInstance.hide();
                        }
                        // Remover alertas de sucesso/erro
                        document.querySelectorAll('.alert-success, .alert-danger').forEach(e => e.remove());
                    })
                    .catch(() => alert('Erro ao carregar dados do item.'));
            }
            function removerItem(event, id) {
                if (event) event.preventDefault();
                if (confirm('Deseja remover este item?')) {
                    fetch('../api/venda_itens_remove.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: new URLSearchParams({ id_item: id })
                    })
                        .then(r => r.json())
                        .then(resp => {
                            if (resp.success) {
                                carregarGridItens();
                            } else {
                                alert(resp.error || 'Erro ao remover item.');
                            }
                        })
                        .catch(() => alert('Erro ao remover item.'));
                }
            }
            document.addEventListener('DOMContentLoaded', carregarGridItens);

            // Adicionar item via AJAX
            document.getElementById('btnAdicionarItem').addEventListener('click', function () {
                const materia = document.getElementById('materia_prima').value.trim();
                const peca = document.getElementById('peca').value.trim();
                const ambiente = document.getElementById('ambiente').value.trim();
                const idMateria = document.getElementById('materia_prima').dataset.id || document.getElementById('id_materia').value || '';
                const idPeca = document.getElementById('peca').dataset.id || document.getElementById('id_peca').value || '';
                const idAmbiente = document.getElementById('ambiente').dataset.id || document.getElementById('id_ambiente').value || '';
                document.getElementById('id_materia').value = idMateria;
                document.getElementById('id_peca').value = idPeca;
                document.getElementById('id_ambiente').value = idAmbiente;
                const qtd = document.getElementById('qtd_itens').value || 1;

                // Validar campos obrigatórios da venda
                const dtVenda = document.getElementById('dt_venda').value;
                const cliente = document.getElementById('cliente').value.trim();
                if (!dtVenda || !cliente) {
                    alert('Preencha a data da venda e o nome do cliente antes de adicionar itens.');
                    return;
                }

                let idVenda = <?= isset($venda['id_venda']) ? (int) $venda['id_venda'] : 'null' ?>;
                if (editandoItemId) {
                    fetch('../api/venda_itens_update.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: new URLSearchParams({
                            id_item: editandoItemId,
                            id_venda: idVenda,
                            id_materia: idMateria,
                            id_peca: idPeca,
                            id_ambiente: idAmbiente,
                            nome_materia_prima: materia,
                            nome_peca: peca,
                            nome_ambiente: ambiente,
                            qtd: qtd,
                            qtd_itens: qtd
                        })
                    })
                        .then(r => r.json())
                        .then(resp => {
                            if (resp.success) {
                                carregarGridItens();
                                setTimeout(carregarGridItens, 200);
                                limparCamposItem();
                                // Remover alertas de sucesso/erro
                                document.querySelectorAll('.alert-success, .alert-danger').forEach(e => e.remove());
                            } else {
                                alert(resp.error || 'Erro ao atualizar item.');
                            }
                        })
                        .catch(() => alert('Erro ao atualizar item.'));
                } else {
                    // Adicionar novo item
                    if (!idVenda) {
                        const form = document.querySelector('form');
                        const formData = new FormData(form);
                        fetch('../api/venda_save_ajax.php', {
                            method: 'POST',
                            body: formData
                        })
                            .then(r => r.json())
                            .then(resp => {
                                if (resp.success && resp.id_venda) {
                                    idVenda = resp.id_venda;
                                    window.history.replaceState({}, '', '?id=' + idVenda);
                                    adicionarItem(idVenda, idMateria, idPeca, idAmbiente, materia, peca, ambiente, qtd);
                                    setTimeout(carregarGridItens, 500);
                                } else {
                                    alert(resp.error || 'Erro ao salvar a venda. Verifique os campos obrigatórios.');
                                }
                            })
                            .catch(() => alert('Erro ao salvar a venda.'));
                    } else {
                        adicionarItem(idVenda, idMateria, idPeca, idAmbiente, materia, peca, ambiente, qtd);
                    }
                }
            });

            function limparCamposItem() {
                document.getElementById('materia_prima').value = '';
                document.getElementById('peca').value = '';
                document.getElementById('ambiente').value = '';
                document.getElementById('materia_prima').dataset.id = '';
                document.getElementById('peca').dataset.id = '';
                document.getElementById('ambiente').dataset.id = '';
                document.getElementById('id_materia').value = '';
                document.getElementById('id_peca').value = '';
                document.getElementById('id_ambiente').value = '';
                document.getElementById('qtd_itens').value = 1;
                editandoItemId = null;
                // Restaurar botão
                const btn = document.getElementById('btnAdicionarItem');
                btn.textContent = 'Adicionar';
                btn.classList.remove('btn-warning');
                btn.classList.add('btn-primary');
            }

            function adicionarItem(idVenda, idMateria, idPeca, idAmbiente, nomeMateria, nomePeca, nomeAmbiente, qtd) {
                fetch('../api/venda_itens_add.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({
                        id_venda: idVenda,
                        id_materia: idMateria,
                        id_peca: idPeca,
                        id_ambiente: idAmbiente,
                        nome_materia_prima: nomeMateria,
                        nome_peca: nomePeca,
                        nome_ambiente: nomeAmbiente,
                        qtd: qtd,
                        qtd_itens: qtd
                    })
                })
                    .then(r => r.json())
                    .then(resp => {
                        if (resp.success) {
                            carregarGridItens();
                            // Limpar campos
                            document.getElementById('materia_prima').value = '';
                            document.getElementById('peca').value = '';
                            document.getElementById('ambiente').value = '';
                            document.getElementById('materia_prima').dataset.id = '';
                            document.getElementById('peca').dataset.id = '';
                            document.getElementById('ambiente').dataset.id = '';
                            document.getElementById('id_materia').value = '';
                            document.getElementById('id_peca').value = '';
                            document.getElementById('id_ambiente').value = '';
                            document.getElementById('qtd_itens').value = 1;
                        } else {
                            alert(resp.error || 'Erro ao adicionar item.');
                        }
                    })
                    .catch(() => alert('Erro ao adicionar item.'));
            }
        </script>
    </div>

</div>

<!-- Aba 3: Upload e listagem de PDFs -->
<div class="tab-pane fade" id="arquivos" role="tabpanel" aria-labelledby="arquivos-tab">
    <div class="row g-3">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">📎 Upload de Arquivos PDF</h5>
                </div>
                <div class="card-body">
                    <!-- Formulário de upload de PDF, id único e botão dentro do form -->
                    <form id="formUploadPDF" enctype="multipart/form-data" method="post" action="#" autocomplete="off">
                        <div class="mb-3">
                            <label for="pdf_file" class="form-label">Selecione um arquivo PDF</label>
                            <input type="file" name="pdf_file" id="pdf_file" class="form-control"
                                accept="application/pdf" required>
                        </div>
                        <div class="mb-3">
                            <button type="button" class="btn btn-primary" id="btnUploadPDF">Enviar PDF</button>
                        </div>
                    </form>
                    <hr>
                    <h5>Arquivos Enviados</h5>
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover align-middle" id="grid-arquivos">
                            <thead class="table-light">
                                <tr>
                                    <th>Nome do Arquivo</th>
                                    <th>Ações</th>
                                </tr>
                            </thead>
                            <tbody id="grid-arquivos-body">
                                <tr>
                                    <td colspan="2" class="text-center text-muted">Carregando...</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- O script de upload foi movido para o final do arquivo -->
</div>


<!-- <?php if ($sucesso && $novaVenda): ?>
        <div class="modal fade" id="successModal" tabindex="-1" aria-labelledby="successModalLabel" aria-hidden="true"
            data-bs-backdrop="static" data-bs-keyboard="false">
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
                                <span id="idVendaHidden" style="display:none;">ID da Venda: #<?= $vendaId ?></span>
                            <?php endif; ?>
                        </p>
                    </div>
                    <div class="modal-footer justify-content-center">
                        <button type="button" class="btn btn-success btn-lg" id="btnNovaVenda">
                            <i class="fas fa-plus me-2"></i>OK - Nova Venda
                        </button>
                        <button type="button" class="btn btn-outline-secondary"
                            onclick="window.location.href='vendas_list.php'">
                            <i class="fas fa-list me-2"></i>Ver Vendas
                        </button>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?> -->

<!-- VALORES E PAGAMENTO -->
<!-- <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">💰 Valores e Forma de Pagamento</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <label for="vlr_total" class="form-label">Valor Total *</label>
                <input type="text" name="vlr_total" id="vlr_total" class="form-control money" 
                    value="<?= $venda['vlr_total'] ? number_format($venda['vlr_total'], 2, ',', '.') : '' ?>">
                        </div>

                        <div class="col-md-6">
                            <label for="vlr_entrada" class="form-label">Valor de Entrada</label>
                            <input type="text" name="vlr_entrada" id="vlr_entrada" class="form-control money" 
                                   value="<?= $venda['vlr_entrada'] ? number_format($venda['vlr_entrada'], 2, ',', '.') : '0,00' ?>">
                        </div>

                        <div class="col-md-6">
                            <label for="forma_pg" class="form-label">Forma de Pagamento *</label>
                            <select name="forma_pg" id="forma_pg" class="form-control">
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
                    </div>
                </div>
            </div>
        </div> -->

<!-- <div class="col-12">
    <button type="submit" class="btn btn-primary">Salvar</button>
    <a href="vendas_list.php" class="btn btn-secondary">Cancelar</a>
</div> -->




<style>
    .modal-content {
        border: none;
        border-radius: 15px;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
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

    .card {
        margin-bottom: 1rem;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    }

    .card-header {
        background-color: #f8f9fa;
        border-bottom: 1px solid #dee2e6;
    }
</style>

<script>
    console.log('SCRIPT FINAL PDF: carregado');
    // Funções da aba 3: upload/lista/remover PDF
    function carregarGridArquivos() {
        const idVenda = <?= isset($venda['id_venda']) ? (int) $venda['id_venda'] : 'null' ?>;
        const tbody = document.getElementById('grid-arquivos-body');
        if (!idVenda) {
            tbody.innerHTML = '<tr><td colspan="2" class="text-center text-muted">Salve a venda para enviar arquivos.</td></tr>';
            return;
        }
        fetch('../api/arquivos_list.php?id_venda=' + idVenda)
            .then(r => r.json())
            .then(arquivos => {
                if (!Array.isArray(arquivos) || arquivos.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="2" class="text-center text-muted">Nenhum arquivo enviado.</td></tr>';
                    return;
                }
                tbody.innerHTML = '';
                arquivos.forEach(arq => {
                    tbody.innerHTML += `<tr>
                    <td><a href="/${arq.nome}" target="_blank">${arq.nome.split('/').pop()}</a></td>
                    <td>
                        <button type="button" class="btn btn-sm btn-danger" onclick="removerArquivo(${arq.id_arquivo})">Remover</button>
                    </td>
                </tr>`;
                });
            })
            .catch(() => {
                tbody.innerHTML = '<tr><td colspan="2" class="text-center text-danger">Erro ao carregar arquivos.</td></tr>';
            });
    }

    function removerArquivo(idArquivo) {
    if (!window.confirm('Tem certeza que deseja remover este arquivo? Esta ação não pode ser desfeita.')) return;
        fetch('../api/arquivos_remove.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({ id_arquivo: idArquivo })
        })
            .then(r => r.json())
            .then(resp => {
                if (resp.success) {
                    carregarGridArquivos();
                } else {
                    alert(resp.error || 'Erro ao remover arquivo.');
                }
            })
            .catch(() => alert('Erro ao remover arquivo.'));
    }


    // Delegação de eventos para garantir que o clique funcione mesmo se o botão for recriado
    document.addEventListener('click', function (e) {
        if (e.target && e.target.id === 'btnUploadPDF') {
            e.preventDefault();
            alert('DEBUG: Clique no botão capturado!');
            console.log('submit PDF');
            var formUploadPDF = document.getElementById('formUploadPDF');
            const idVenda = <?= isset($venda['id_venda']) ? (int) $venda['id_venda'] : 'null' ?>;
            if (!idVenda) {
                alert('Salve a venda antes de enviar arquivos.');
                return false;
            }
            if (!formUploadPDF) {
                alert('Formulário de upload não encontrado!');
                return false;
            }
            const formData = new FormData(formUploadPDF);
            formData.append('id_venda', idVenda);
            fetch('../api/arquivos_upload.php', {
                method: 'POST',
                body: formData
            })
                .then(r => r.json())
                .then(resp => {
                    let msg = '';
                    if (resp.success) {
                        carregarGridArquivos();
                        formUploadPDF.reset();
                        msg = 'Arquivo enviado com sucesso!\n';
                    } else {
                        msg = (resp.error || 'Erro ao enviar arquivo.') + '\n';
                    }
                    msg += 'Caminho real: ' + (resp.targetPath || '-') + '\n';
                    msg += 'Permissão da pasta: ' + (resp.perms_base || '-') + '\n';
                    msg += 'is_dir_base: ' + (resp.is_dir_base ? 'sim' : 'não') + '\n';
                    if (resp.debug) msg += 'Debug: ' + JSON.stringify(resp.debug) + '\n';
                    alert(msg);
                })
                .catch(() => alert('Erro ao enviar arquivo.'));
            return false;
        }
    });

    document.addEventListener('DOMContentLoaded', function () {
        carregarGridArquivos();
    });
    // Não há mais bindUploadPDF, só delegação de eventos
</script>



<script>
    // Autocomplete AJAX para ambiente
    let timeoutAmbiente = null;
    function buscaAmbiente() {
        const input = document.getElementById('ambiente');
        const sugestoesDiv = document.getElementById('ambiente-sugestoes');
        if (!input || !sugestoesDiv) return;
        clearTimeout(timeoutAmbiente);
        const termo = input.value;
        if (termo.length === 0 || /^\s+$/.test(termo)) {
            timeoutAmbiente = setTimeout(() => {
                fetch('../api/ambiente.php?q=')
                    .then(r => r.json())
                    .then(dados => {
                        sugestoesDiv.innerHTML = '';
                        if (!Array.isArray(dados) || dados.length === 0) {
                            sugestoesDiv.style.display = 'none';
                            return;
                        }
                        dados.forEach(item => {
                            const btn = document.createElement('button');
                            btn.type = 'button';
                            btn.className = 'list-group-item list-group-item-action';
                            btn.innerHTML = `<b>${item.nome}</b>`;
                            btn.onclick = () => {
                                input.value = item.nome;
                                input.dataset.id = item.id_ambiente || '';
                                sugestoesDiv.innerHTML = '';
                                sugestoesDiv.style.display = 'none';
                            };
                            sugestoesDiv.appendChild(btn);
                        });
                        sugestoesDiv.style.display = 'block';
                    })
                    .catch(() => {
                        sugestoesDiv.innerHTML = '';
                        sugestoesDiv.style.display = 'none';
                    });
            }, 250);
            return;
        }
        if (termo.length < 2) {
            sugestoesDiv.innerHTML = '';
            sugestoesDiv.style.display = 'none';
            return;
        }
        timeoutAmbiente = setTimeout(() => {
            fetch('../api/ambiente.php?q=' + encodeURIComponent(termo))
                .then(r => r.json())
                .then(dados => {
                    sugestoesDiv.innerHTML = '';
                    if (!Array.isArray(dados) || dados.length === 0) {
                        sugestoesDiv.style.display = 'none';
                        return;
                    }
                    dados.forEach(item => {
                        const btn = document.createElement('button');
                        btn.type = 'button';
                        btn.className = 'list-group-item list-group-item-action';
                        btn.innerHTML = `<b>${item.nome}</b>`;
                        btn.onclick = () => {
                            input.value = item.nome;
                            input.dataset.id = item.id_ambiente || '';
                            sugestoesDiv.innerHTML = '';
                            sugestoesDiv.style.display = 'none';
                        };
                        sugestoesDiv.appendChild(btn);
                    });
                    sugestoesDiv.style.display = 'block';
                })
                .catch(() => {
                    sugestoesDiv.innerHTML = '';
                    sugestoesDiv.style.display = 'none';
                });
        }, 250);
    }

    // Autocomplete AJAX para matéria-prima
    let timeoutMateria = null;
    function buscaMateriaPrima() {
        const input = document.getElementById('materia_prima');
        const sugestoesDiv = document.getElementById('materia-sugestoes');
        if (!input || !sugestoesDiv) return;
        clearTimeout(timeoutMateria);
        const termo = input.value;
        if (termo.length === 0 || /^\s+$/.test(termo)) {
            timeoutMateria = setTimeout(() => {
                fetch('../api/materia_prima.php?q=')
                    .then(r => r.json())
                    .then(dados => {
                        sugestoesDiv.innerHTML = '';
                        if (!Array.isArray(dados) || dados.length === 0) {
                            sugestoesDiv.style.display = 'none';
                            return;
                        }
                        dados.forEach(item => {
                            const btn = document.createElement('button');
                            btn.type = 'button';
                            btn.className = 'list-group-item list-group-item-action';
                            btn.innerHTML = `<b>${item.nome}</b>`;
                            btn.onclick = () => {
                                input.value = item.nome;
                                input.dataset.id = item.id_materia || '';
                                sugestoesDiv.innerHTML = '';
                                sugestoesDiv.style.display = 'none';
                            };
                            sugestoesDiv.appendChild(btn);
                        });
                        sugestoesDiv.style.display = 'block';
                    })
                    .catch(() => {
                        sugestoesDiv.innerHTML = '';
                        sugestoesDiv.style.display = 'none';
                    });
            }, 250);
            return;
        }
        if (termo.length < 2) {
            sugestoesDiv.innerHTML = '';
            sugestoesDiv.style.display = 'none';
            return;
        }
        timeoutMateria = setTimeout(() => {
            fetch('../api/materia_prima.php?q=' + encodeURIComponent(termo))
                .then(r => r.json())
                .then(dados => {
                    sugestoesDiv.innerHTML = '';
                    if (!Array.isArray(dados) || dados.length === 0) {
                        sugestoesDiv.style.display = 'none';
                        return;
                    }
                    dados.forEach(item => {
                        const btn = document.createElement('button');
                        btn.type = 'button';
                        btn.className = 'list-group-item list-group-item-action';
                        btn.innerHTML = `<b>${item.nome}</b>`;
                        btn.onclick = () => {
                            input.value = item.nome;
                            input.dataset.id = item.id_materia || '';
                            sugestoesDiv.innerHTML = '';
                            sugestoesDiv.style.display = 'none';
                        };
                        sugestoesDiv.appendChild(btn);
                    });
                    sugestoesDiv.style.display = 'block';
                })
                .catch(() => {
                    sugestoesDiv.innerHTML = '';
                    sugestoesDiv.style.display = 'none';
                });
        }, 250);
    }

    // Autocomplete AJAX para peças
    let timeoutPeca = null;
    function buscaPeca() {
        const input = document.getElementById('peca');
        const sugestoesDiv = document.getElementById('peca-sugestoes');
        if (!input || !sugestoesDiv) return;
        clearTimeout(timeoutPeca);
        const termo = input.value;
        if (termo.length === 0 || /^\s+$/.test(termo)) {
            timeoutPeca = setTimeout(() => {
                fetch('../api/pecas.php?q=')
                    .then(r => r.json())
                    .then(dados => {
                        sugestoesDiv.innerHTML = '';
                        if (!Array.isArray(dados) || dados.length === 0) {
                            sugestoesDiv.style.display = 'none';
                            return;
                        }
                        dados.forEach(item => {
                            const btn = document.createElement('button');
                            btn.type = 'button';
                            btn.className = 'list-group-item list-group-item-action';
                            btn.innerHTML = `<b>${item.tipo}</b> <small class='text-muted'>${item.formapg || ''}</small>`;
                            btn.onclick = () => {
                                input.value = item.tipo;
                                input.dataset.id = item.id_peca || '';
                                sugestoesDiv.innerHTML = '';
                                sugestoesDiv.style.display = 'none';
                            };
                            sugestoesDiv.appendChild(btn);
                        });
                        sugestoesDiv.style.display = 'block';
                    })
                    .catch(() => {
                        sugestoesDiv.innerHTML = '';
                        sugestoesDiv.style.display = 'none';
                    });
            }, 250);
            return;
        }
        if (termo.length < 2) {
            sugestoesDiv.innerHTML = '';
            sugestoesDiv.style.display = 'none';
            return;
        }
        timeoutPeca = setTimeout(() => {
            fetch('../api/pecas.php?q=' + encodeURIComponent(termo))
                .then(r => r.json())
                .then(dados => {
                    sugestoesDiv.innerHTML = '';
                    if (!Array.isArray(dados) || dados.length === 0) {
                        sugestoesDiv.style.display = 'none';
                        return;
                    }
                    dados.forEach(item => {
                        const btn = document.createElement('button');
                        btn.type = 'button';
                        btn.className = 'list-group-item list-group-item-action';
                        btn.innerHTML = `<b>${item.tipo}</b> <small class='text-muted'>${item.formapg || ''}</small>`;
                        btn.onclick = () => {
                            input.value = item.tipo;
                            input.dataset.id = item.id_peca || '';
                            sugestoesDiv.innerHTML = '';
                            sugestoesDiv.style.display = 'none';
                        };
                        sugestoesDiv.appendChild(btn);
                    });
                    sugestoesDiv.style.display = 'block';
                })
                .catch(() => {
                    sugestoesDiv.innerHTML = '';
                    sugestoesDiv.style.display = 'none';
                });
        }, 250);
    }

    // Esconde sugestões ao clicar fora (materia-prima e peca)
    document.addEventListener('click', function (e) {
        const matInput = document.getElementById('materia_prima');
        const matSug = document.getElementById('materia-sugestoes');
        if (matSug && matInput && !matSug.contains(e.target) && e.target !== matInput) {
            matSug.innerHTML = '';
            matSug.style.display = 'none';
        }
        const pecaInput = document.getElementById('peca');
        const pecaSug = document.getElementById('peca-sugestoes');
        if (pecaSug && pecaInput && !pecaSug.contains(e.target) && e.target !== pecaInput) {
            pecaSug.innerHTML = '';
            pecaSug.style.display = 'none';
        }
    });

    // Autocomplete AJAX para o campo cliente (nome, CPF, telefone ou email)
    let timeoutCliente = null;
    function buscanome() {
        const clienteInput = document.getElementById('cliente');
        const sugestoesDiv = document.getElementById('cliente-sugestoes');
        if (!clienteInput || !sugestoesDiv) return;
        clearTimeout(timeoutCliente);
        const termo = clienteInput.value;
        // Se o campo estiver vazio OU só espaço, busca os 25 primeiros
        if (termo.length === 0 || /^\s+$/.test(termo)) {
            timeoutCliente = setTimeout(() => {
                fetch('../api/clientes.php?q=')
                    .then(r => r.json())
                    .then(dados => {
                        sugestoesDiv.innerHTML = '';
                        if (!Array.isArray(dados) || dados.length === 0) {
                            sugestoesDiv.style.display = 'none';
                            return;
                        }
                        dados.forEach(cli => {
                            const item = document.createElement('button');
                            item.type = 'button';
                            item.className = 'list-group-item list-group-item-action';
                            item.innerHTML = `<b>${cli.nome}</b> <small class='text-muted'>${cli.documento} | ${cli.telefone} | ${cli.email}</small>`;
                            item.onclick = () => {
                                clienteInput.value = cli.nome;
                                sugestoesDiv.innerHTML = '';
                                sugestoesDiv.style.display = 'none';
                            };
                            sugestoesDiv.appendChild(item);
                        });
                        sugestoesDiv.style.display = 'block';
                    })
                    .catch(() => {
                        sugestoesDiv.innerHTML = '';
                        sugestoesDiv.style.display = 'none';
                    });
            }, 250);
            return;
        }
        // Busca normal para 2+ caracteres
        if (termo.length < 2) {
            sugestoesDiv.innerHTML = '';
            sugestoesDiv.style.display = 'none';
            return;
        }
        timeoutCliente = setTimeout(() => {
            fetch('../api/clientes.php?q=' + encodeURIComponent(termo))
                .then(r => r.json())
                .then(dados => {
                    sugestoesDiv.innerHTML = '';
                    if (!Array.isArray(dados) || dados.length === 0) {
                        sugestoesDiv.style.display = 'none';
                        return;
                    }
                    dados.forEach(cli => {
                        const item = document.createElement('button');
                        item.type = 'button';
                        item.className = 'list-group-item list-group-item-action';
                        item.innerHTML = `<b>${cli.nome}</b> <small class='text-muted'>${cli.documento} | ${cli.telefone} | ${cli.email}</small>`;
                        item.onclick = () => {
                            clienteInput.value = cli.nome;
                            sugestoesDiv.innerHTML = '';
                            sugestoesDiv.style.display = 'none';
                        };
                        sugestoesDiv.appendChild(item);
                    });
                    sugestoesDiv.style.display = 'block';
                })
                .catch(() => {
                    sugestoesDiv.innerHTML = '';
                    sugestoesDiv.style.display = 'none';
                });
        }, 250);
    }

    // Esconde sugestões ao clicar fora
    document.addEventListener('click', function (e) {
        const clienteInput = document.getElementById('cliente');
        const sugestoesDiv = document.getElementById('cliente-sugestoes');
        const matInput = document.getElementById('materia_prima');
        const matSug = document.getElementById('materia-sugestoes');
        const pecaInput = document.getElementById('peca');
        const pecaSug = document.getElementById('peca-sugestoes');
        const ambienteInput = document.getElementById('ambiente');
        const ambienteSug = document.getElementById('ambiente-sugestoes');


        if (!sugestoesDiv || !clienteInput) return;
        if (!sugestoesDiv.contains(e.target) && e.target !== clienteInput) {
            sugestoesDiv.innerHTML = '';
            sugestoesDiv.style.display = 'none';
        }


        if (matSug && matInput && !matSug.contains(e.target) && e.target !== matInput) {
            matSug.innerHTML = '';
            matSug.style.display = 'none';
        }

        if (pecaSug && pecaInput && !pecaSug.contains(e.target) && e.target !== pecaInput) {
            pecaSug.innerHTML = '';
            pecaSug.style.display = 'none';
        }

        if (ambienteSug && ambienteInput && !ambienteSug.contains(e.target) && e.target !== ambienteInput) {
            ambienteSug.innerHTML = '';
            ambienteSug.style.display = 'none';
        }


    });



    document.addEventListener('DOMContentLoaded', function () {
        // Mostrar modal de sucesso se venda foi cadastrada
        <?php if ($sucesso && $novaVenda): ?>
            const successModal = new bootstrap.Modal(document.getElementById('successModal'));
            successModal.show();

            // Função para limpar formulário e fechar modal
            document.getElementById('btnNovaVenda').addEventListener('click', function () {
                // Limpar todos os campos do formulário
                document.getElementById('vendaForm').reset();

                // Resetar valores específicos
                document.getElementById('dt_venda').value = '<?= date('Y-m-d') ?>';
                document.getElementById('vlr_entrada').value = '0,00';
                document.getElementById('qtd_parcelas').value = '1';

                // Resetar selects para primeira opção
                document.getElementById('id_vendedor').selectedIndex = 0;
                document.getElementById('id_vendedor2').selectedIndex = 0;
                document.getElementById('id_comissao').selectedIndex = 0;
                document.getElementById('id_comissao2').selectedIndex = 0;
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

                // Focar no primeiro campo
                document.getElementById('id_vendedor').focus();

                // Fechar modal
                successModal.hide();
            });
        <?php endif; ?>

        // Máscara para valores monetários
        const moneyInputs = document.querySelectorAll('.money');
        moneyInputs.forEach(input => {
            input.addEventListener('input', function (e) {
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

        if (vlrTotal) vlrTotal.addEventListener('input', validarEntradaVsTotal);
        if (vlrEntrada) vlrEntrada.addEventListener('input', validarEntradaVsTotal);

        // Validação para evitar vendedores iguais
        function validarVendedoresDiferentes() {
            const vendedor1 = document.getElementById('id_vendedor').value;
            const vendedor2 = document.getElementById('id_vendedor2').value;

            if (vendedor1 && vendedor2 && vendedor1 === vendedor2) {
                document.getElementById('id_vendedor2').setCustomValidity('O segundo vendedor deve ser diferente do vendedor principal.');
                document.getElementById('id_vendedor2').style.borderColor = '#dc3545';
            } else {
                document.getElementById('id_vendedor2').setCustomValidity('');
                document.getElementById('id_vendedor2').style.borderColor = '';
            }
        }

        const idVendedor = document.getElementById('id_vendedor');
        const idVendedor2 = document.getElementById('id_vendedor2');
        if (idVendedor) idVendedor.addEventListener('change', validarVendedoresDiferentes);
        if (idVendedor2) idVendedor2.addEventListener('change', validarVendedoresDiferentes);

        // Controle automático de parcelas baseado na forma de pagamento
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
        if (formaPg) formaPg.addEventListener('change', adjustParcelasBasedOnFormaPg);
        if (qtdParcelas) {
            qtdParcelas.addEventListener('change', updateBoletosHelpText);
            qtdParcelas.addEventListener('input', updateBoletosHelpText);
        }

        // Aplicar lógica inicial ao carregar a página
        if (formaPg) adjustParcelasBasedOnFormaPg();
    });
</script>

<?php require_once '../views/footer.php'; ?>