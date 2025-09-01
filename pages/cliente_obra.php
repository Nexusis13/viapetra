<?php
require_once '../config/protect.php';
require_once '../config/config.php';


require_once '../views/header.php';
?>


<?php $modo_edicao = false; ?>

<style>
    .form-container {
        padding: 1.5rem;
        max-width: auto;
        margin: 0;
    }

    .pipeline-header {
        background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);
        border-radius: 12px;
        padding: 1rem;
        color: white;
        margin: 1rem 0;
        max-width: auto;
        margin-bottom: 2rem;
    }

    .text-alert {
        color: rgba(243, 13, 13, 0.7) !important;
        ;
    }

        .required {
        color: #e74c3c;
    }

    .maskCPF {
        mask: '999.999.999-99';
    }

    .maskCNPJ {
        mask: '99.999.999/9999-99';
    }
    .maskPhone {
        mask: '(99) 99999-9999';
    }
</style>

<div class="container-fluid px-2">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4 pipeline-header">
        <div>
            <h1 class="text-dark mb-2">
                <h2><?= $modo_edicao ? 'Editar' : 'Cadastrar' ?> Obra</h2>
            </h1>
            <p class="text-muted mb-0 text-alert ">
                'Preencha todas as informações para cadastro de Obras'
            </p>
        </div>
        <div>
            <a href="dashboard.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left me-1"></i> Voltar
            </a>

        </div>
    </div>

    <!-- Header -->
    <div class="justify-content-between align-items-center mb-4 pipeline-header">
        <div>
            <h2>Informações de Cadastro</h2>

        </div>

        <div>

            <form method="post" class="row g-3">
                <div class="col-md-2">
                    <label class="form-label">Tipo Documento</label>
                    <select name="tipo_documento" class="form-control" required>
                        <option value="">Selecione...</option>
                        <option value="CPF" <?= (isset($cliente['tipo_documento']) && $cliente['tipo_documento'] == 'CPF') ? 'selected' : '' ?>>CPF</option>
                        <option value="CNPJ" <?= (isset($cliente['tipo_documento']) && $cliente['tipo_documento'] == 'CNPJ') ? 'selected' : '' ?>>CNPJ</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">CPF / CNPJ <span class="required">*</label>
                    <input type="text" name="documento" class="form-control maskCPF maskCNPJ" required value="<?= htmlspecialchars($cliente['documento'] ?? '') ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Nome <span class="required">*</label>
                    <input type="text" name="nome" class="form-control" required value="<?= htmlspecialchars($cliente['nome'] ?? '') ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Data de Nascimento <span class="required">*</label>
                    <input type="date" name="dt_nacimento" class="form-control" required value="<?= htmlspecialchars($cliente['dt_nacimento'] ?? '') ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Telefone <span class="required">*</span></label>
                    <input type="text" name="tele" class="form-control " required value="<?= htmlspecialchars($cliente['tele'] ?? '') ?>">
                </div>
                <div class="col-md-12">
                    <label class="form-label">Endereço Cliente <span class="required">*</label>
                    <input type="text" name="endereco" class="form-control" required value="<?= htmlspecialchars($cliente['endereco'] ?? '') ?>">
                </div>
                <div class="col-md-12">
                    <label class="form-label">Endereço da Obra <span class="required">*</label>
                    <input type="text" name="end_obra" class="form-control" required value="<?= htmlspecialchars($cliente['end_obra'] ?? '') ?>">
                </div>
                <div class="col-12">
                    <button class="btn btn-primary" type="submit"><?= $modo_edicao ? 'Atualizar' : 'Cadastrar' ?></button>
                    <a href="listauser.php" class="btn btn-secondary">Voltar</a>
                </div>
            </form>
        </div>

        

    </div>

       <!-- Ações Rápidas -->
    <div class="text-center mt-4">
        <a href="crm_dashboard.php" class="btn btn-outline-primary me-2">
            <i class="fas fa-chart-line me-1"></i> Dashboard CRM
        </a>
        <a href="crm_leads.php" class="btn btn-outline-secondary me-2">
            <i class="fas fa-users me-1"></i> Ver Leads
        </a>
        <a href="crm_pipeline.php" class="btn btn-outline-info me-2">
            <i class="fas fa-funnel-dollar me-1"></i> Pipeline
        </a>
        <a href="crm_relatorios.php" class="btn btn-outline-success">
            <i class="fas fa-chart-bar me-1"></i> Relatórios
        </a>
    </div>

</div>

<?php require_once '../views/footer.php'; ?>