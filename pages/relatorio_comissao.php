<?php
require_once '../config/protect.php';
require_once '../config/config.php';

// Captura os filtros da URL
$data_inicio = $_GET['data_inicio'] ?? '';
$data_fim = $_GET['data_fim'] ?? '';
$vendedor_filtro = $_GET['vendedor'] ?? '';
$status_filtro = $_GET['status'] ?? '';
$tipo_relatorio = $_GET['tipo'] ?? 'resumo';

// Busca os vendedores disponíveis para o filtro
$sql_vendedores = "SELECT DISTINCT id_vendedor, nome FROM vendedores WHERE STATUS = 1 ORDER BY nome";
$stmt_vendedores = $pdo->prepare($sql_vendedores);
$stmt_vendedores->execute();
$vendedores_disponiveis = $stmt_vendedores->fetchAll(PDO::FETCH_ASSOC);

// CONSULTA PARA RESUMO - UNINDO VENDAS/ENTRADAS + BOLETOS PARA AMBOS OS VENDEDORES
$params_resumo = [];
$filtros_resumo = [];

// Parâmetros para vendas/entradas
if ($data_inicio) {
    $filtros_resumo[] = "DATE(v.dt_venda) >= ?";
    $params_resumo[] = $data_inicio;
}
if ($data_fim) {
    $filtros_resumo[] = "DATE(v.dt_venda) <= ?";
    $params_resumo[] = $data_fim;
}
if ($vendedor_filtro) {
    $filtros_resumo[] = "(v.id_vendedor = ? OR v.id_vendedor2 = ?)";
    $params_resumo[] = $vendedor_filtro;
    $params_resumo[] = $vendedor_filtro;
}

$where_vendas = '';
if (!empty($filtros_resumo)) {
    $where_vendas = 'WHERE ' . implode(' AND ', $filtros_resumo);
}

// Parâmetros para boletos (usando o filtro de vencimento)
$params_boletos = [];
$filtros_boletos = [];

if ($data_inicio) {
    $filtros_boletos[] = "DATE(b.dt_vencimento) >= ?";
    $params_boletos[] = $data_inicio;
}
if ($data_fim) {
    $filtros_boletos[] = "DATE(b.dt_vencimento) <= ?";
    $params_boletos[] = $data_fim;
}
if ($vendedor_filtro) {
    $filtros_boletos[] = "b.id_vendedor = ?";
    $params_boletos[] = $vendedor_filtro;
}

$where_boletos = '';
if (!empty($filtros_boletos)) {
    $where_boletos = 'WHERE ' . implode(' AND ', $filtros_boletos);
}

// SQL do relatório resumido - ATUALIZADO PARA DOIS VENDEDORES
$sql_resumo = "
    SELECT 
        vd.id_vendedor,
        vd.nome AS nome_vendedor,
        vd.celular,
        vd.tipochave,
        vd.chave,
        
        COALESCE(SUM(vendas.vendas_avista), 0) AS vendas_avista,
        COALESCE(SUM(vendas.vendas_entrada), 0) AS vendas_entrada,
        COALESCE(SUM(boletos.boletos_pagos), 0) AS boletos_pagos,
        
        COALESCE(SUM(vendas.valor_vendas_avista), 0) AS valor_vendas_avista,
        COALESCE(SUM(vendas.valor_entradas), 0) AS valor_entradas,
        COALESCE(SUM(boletos.valor_boletos_pagos), 0) AS valor_boletos_pagos,
        
        COALESCE(SUM(vendas.comissao_vendas_avista), 0) AS comissao_vendas_avista,
        COALESCE(SUM(vendas.comissao_entradas), 0) AS comissao_entradas,
        COALESCE(SUM(boletos.comissao_boletos_pagos), 0) AS comissao_boletos_pagos,
        
        COALESCE(AVG(vendas.percentual_comissao), COALESCE(AVG(boletos.percentual_comissao), 0)) AS percentual_comissao
        
    FROM vendedores vd
    
    LEFT JOIN (
        -- UNIÃO DE VENDEDORES 1 E 2 DAS VENDAS
        SELECT 
            vendedor_id as id_vendedor,
            SUM(vendas_avista) as vendas_avista,
            SUM(vendas_entrada) as vendas_entrada,
            SUM(valor_vendas_avista) as valor_vendas_avista,
            SUM(valor_entradas) as valor_entradas,
            SUM(comissao_vendas_avista) as comissao_vendas_avista,
            SUM(comissao_entradas) as comissao_entradas,
            AVG(percentual_comissao) as percentual_comissao
        FROM (
            -- VENDEDOR PRINCIPAL
            SELECT 
                v.id_vendedor as vendedor_id,
                SUM(CASE WHEN v.forma_pg IN ('PIX', 'Cartão de Débito', 'Dinheiro', 'À Vista') THEN 1 ELSE 0 END) AS vendas_avista,
                SUM(CASE WHEN v.forma_pg IN ('Parcelado', 'Permuta Parcelado', 'Cartão de Crédito') AND v.vlr_entrada > 0 THEN 1 ELSE 0 END) AS vendas_entrada,
                
                SUM(CASE WHEN v.forma_pg IN ('PIX', 'Cartão de Débito', 'Dinheiro', 'À Vista') THEN v.vlr_total ELSE 0 END) AS valor_vendas_avista,
                SUM(CASE WHEN v.forma_pg IN ('Parcelado', 'Permuta Parcelado', 'Cartão de Crédito') AND v.vlr_entrada > 0 THEN v.vlr_entrada ELSE 0 END) AS valor_entradas,
                
                SUM(CASE WHEN v.forma_pg IN ('PIX', 'Cartão de Débito', 'Dinheiro', 'À Vista') THEN (v.vlr_total * COALESCE(c1.valor, 0) / 100) ELSE 0 END) AS comissao_vendas_avista,
                SUM(CASE WHEN v.forma_pg IN ('Parcelado', 'Permuta Parcelado', 'Cartão de Crédito') AND v.vlr_entrada > 0 THEN (v.vlr_entrada * COALESCE(c1.valor, 0) / 100) ELSE 0 END) AS comissao_entradas,
                
                AVG(COALESCE(c1.valor, 0)) AS percentual_comissao
                
            FROM vendas v
            LEFT JOIN comissao c1 ON v.id_comissao = c1.id_comissao
            $where_vendas
            AND (
                v.forma_pg IN ('PIX', 'Cartão de Débito', 'Dinheiro', 'À Vista') OR 
                (v.forma_pg IN ('Parcelado', 'Permuta Parcelado', 'Cartão de Crédito') AND v.vlr_entrada > 0)
            )
            GROUP BY v.id_vendedor
            
            UNION ALL
            
            -- SEGUNDO VENDEDOR
            SELECT 
                v.id_vendedor2 as vendedor_id,
                SUM(CASE WHEN v.forma_pg IN ('PIX', 'Cartão de Débito', 'Dinheiro', 'À Vista') THEN 1 ELSE 0 END) AS vendas_avista,
                SUM(CASE WHEN v.forma_pg IN ('Parcelado', 'Permuta Parcelado', 'Cartão de Crédito') AND v.vlr_entrada > 0 THEN 1 ELSE 0 END) AS vendas_entrada,
                
                SUM(CASE WHEN v.forma_pg IN ('PIX', 'Cartão de Débito', 'Dinheiro', 'À Vista') THEN v.vlr_total ELSE 0 END) AS valor_vendas_avista,
                SUM(CASE WHEN v.forma_pg IN ('Parcelado', 'Permuta Parcelado', 'Cartão de Crédito') AND v.vlr_entrada > 0 THEN v.vlr_entrada ELSE 0 END) AS valor_entradas,
                
                SUM(CASE WHEN v.forma_pg IN ('PIX', 'Cartão de Débito', 'Dinheiro', 'À Vista') THEN (v.vlr_total * COALESCE(c2.valor, 0) / 100) ELSE 0 END) AS comissao_vendas_avista,
                SUM(CASE WHEN v.forma_pg IN ('Parcelado', 'Permuta Parcelado', 'Cartão de Crédito') AND v.vlr_entrada > 0 THEN (v.vlr_entrada * COALESCE(c2.valor, 0) / 100) ELSE 0 END) AS comissao_entradas,
                
                AVG(COALESCE(c2.valor, 0)) AS percentual_comissao
                
            FROM vendas v
            LEFT JOIN comissao c2 ON v.id_comissao2 = c2.id_comissao
            $where_vendas
            AND v.id_vendedor2 IS NOT NULL
            AND (
                v.forma_pg IN ('PIX', 'Cartão de Débito', 'Dinheiro', 'À Vista') OR 
                (v.forma_pg IN ('Parcelado', 'Permuta Parcelado', 'Cartão de Crédito') AND v.vlr_entrada > 0)
            )
            GROUP BY v.id_vendedor2
        ) combined_vendas
        GROUP BY vendedor_id
    ) vendas ON vd.id_vendedor = vendas.id_vendedor
    
    LEFT JOIN (
        SELECT 
            b.id_vendedor,
            COUNT(*) AS boletos_pagos,
            SUM(b.valor) AS valor_boletos_pagos,
            SUM(b.valor * COALESCE(c.valor, 0) / 100) AS comissao_boletos_pagos,
            AVG(COALESCE(c.valor, 0)) AS percentual_comissao
            
        FROM boleto b
        INNER JOIN vendas v ON b.id_venda = v.id_venda
        LEFT JOIN comissao c ON v.id_comissao = c.id_comissao
        $where_boletos
        AND b.status = 'Pago'
        GROUP BY b.id_vendedor
    ) boletos ON vd.id_vendedor = boletos.id_vendedor
    
    WHERE vd.STATUS = 1
    AND (
        vendas.id_vendedor IS NOT NULL OR 
        boletos.id_vendedor IS NOT NULL
    )
    GROUP BY vd.id_vendedor, vd.nome, vd.celular, vd.tipochave, vd.chave
    ORDER BY vd.nome
";

// Unir os parâmetros - duplicar para as duas consultas de vendas
$params_final = array_merge($params_resumo, $params_resumo, $params_boletos);

$stmt_resumo = $pdo->prepare($sql_resumo);
$stmt_resumo->execute($params_final);
$dados_resumo = $stmt_resumo->fetchAll(PDO::FETCH_ASSOC);

// Calcular totais gerais
$total_vendas_avista = !empty($dados_resumo) ? array_sum(array_column($dados_resumo, 'vendas_avista')) : 0;
$total_vendas_entrada = !empty($dados_resumo) ? array_sum(array_column($dados_resumo, 'vendas_entrada')) : 0;
$total_boletos_pagos = !empty($dados_resumo) ? array_sum(array_column($dados_resumo, 'boletos_pagos')) : 0;
$total_valor_vendas = !empty($dados_resumo) ? (
    array_sum(array_column($dados_resumo, 'valor_vendas_avista')) + 
    array_sum(array_column($dados_resumo, 'valor_entradas')) +
    array_sum(array_column($dados_resumo, 'valor_boletos_pagos'))
) : 0;
$total_comissao_paga = !empty($dados_resumo) ? (
    array_sum(array_column($dados_resumo, 'comissao_vendas_avista')) + 
    array_sum(array_column($dados_resumo, 'comissao_entradas')) +
    array_sum(array_column($dados_resumo, 'comissao_boletos_pagos'))
) : 0;

// CONSULTA DETALHADA - ATUALIZADA PARA DOIS VENDEDORES
$dados_detalhados = [];
if ($tipo_relatorio === 'detalhado') {
    // Filtros para status específico no relatório detalhado
    $filtro_status_vendas = '';
    $filtro_status_boletos = '';
    
    if ($status_filtro === 'avista') {
        $filtro_status_vendas = "AND v.forma_pg IN ('PIX', 'Cartão de Débito', 'Dinheiro', 'À Vista')";
        $filtro_status_boletos = "AND 1=0"; // Não incluir boletos
    } elseif ($status_filtro === 'entrada') {
        $filtro_status_vendas = "AND v.forma_pg IN ('Parcelado', 'Permuta Parcelado', 'Cartão de Crédito') AND v.vlr_entrada > 0";
        $filtro_status_boletos = "AND 1=0"; // Não incluir boletos
    } elseif ($status_filtro === 'boletos') {
        $filtro_status_vendas = "AND 1=0"; // Não incluir vendas
        $filtro_status_boletos = ""; // Incluir apenas boletos
    }
    
    // Detalhes de vendas/entradas - VENDEDOR PRINCIPAL
    $sql_det_vendas_v1 = "
        SELECT 
            'venda' as origem,
            v.id_venda,
            v.cliente,
            v.dt_venda as data_referencia,
            v.vlr_total,
            v.vlr_entrada,
            v.forma_pg,
            v.qtd_parcelas,
            vd.nome AS nome_vendedor,
            COALESCE(c1.valor, 0) AS percentual_comissao,
            NULL as id_boleto,
            NULL as n_parcela,
            NULL as dt_vencimento,
            NULL as status_boleto,
            '1' as posicao_vendedor,
            
            CASE 
                WHEN v.forma_pg IN ('PIX', 'Cartão de Débito', 'Dinheiro', 'À Vista') 
                THEN 'venda_avista'
                WHEN v.forma_pg IN ('Parcelado', 'Permuta Parcelado', 'Cartão de Crédito') AND v.vlr_entrada > 0
                THEN 'entrada'
                ELSE 'outros'
            END as tipo_comissao,
            
            CASE 
                WHEN v.forma_pg IN ('PIX', 'Cartão de Débito', 'Dinheiro', 'À Vista') 
                THEN v.vlr_total
                WHEN v.forma_pg IN ('Parcelado', 'Permuta Parcelado', 'Cartão de Crédito') AND v.vlr_entrada > 0
                THEN v.vlr_entrada
                ELSE 0
            END as valor_base_comissao,
            
            CASE 
                WHEN v.forma_pg IN ('PIX', 'Cartão de Débito', 'Dinheiro', 'À Vista') 
                THEN (v.vlr_total * COALESCE(c1.valor, 0) / 100)
                WHEN v.forma_pg IN ('Parcelado', 'Permuta Parcelado', 'Cartão de Crédito') AND v.vlr_entrada > 0
                THEN (v.vlr_entrada * COALESCE(c1.valor, 0) / 100)
                ELSE 0
            END as comissao_calculada
            
        FROM vendas v
        INNER JOIN vendedores vd ON v.id_vendedor = vd.id_vendedor
        LEFT JOIN comissao c1 ON v.id_comissao = c1.id_comissao
        $where_vendas
        AND (
            v.forma_pg IN ('PIX', 'Cartão de Débito', 'Dinheiro', 'À Vista') OR 
            (v.forma_pg IN ('Parcelado', 'Permuta Parcelado', 'Cartão de Crédito') AND v.vlr_entrada > 0)
        )
        $filtro_status_vendas
    ";
    
    // Detalhes de vendas/entradas - SEGUNDO VENDEDOR
    $sql_det_vendas_v2 = "
        SELECT 
            'venda' as origem,
            v.id_venda,
            v.cliente,
            v.dt_venda as data_referencia,
            v.vlr_total,
            v.vlr_entrada,
            v.forma_pg,
            v.qtd_parcelas,
            vd2.nome AS nome_vendedor,
            COALESCE(c2.valor, 0) AS percentual_comissao,
            NULL as id_boleto,
            NULL as n_parcela,
            NULL as dt_vencimento,
            NULL as status_boleto,
            '2' as posicao_vendedor,
            
            CASE 
                WHEN v.forma_pg IN ('PIX', 'Cartão de Débito', 'Dinheiro', 'À Vista') 
                THEN 'venda_avista'
                WHEN v.forma_pg IN ('Parcelado', 'Permuta Parcelado', 'Cartão de Crédito') AND v.vlr_entrada > 0
                THEN 'entrada'
                ELSE 'outros'
            END as tipo_comissao,
            
            CASE 
                WHEN v.forma_pg IN ('PIX', 'Cartão de Débito', 'Dinheiro', 'À Vista') 
                THEN v.vlr_total
                WHEN v.forma_pg IN ('Parcelado', 'Permuta Parcelado', 'Cartão de Crédito') AND v.vlr_entrada > 0
                THEN v.vlr_entrada
                ELSE 0
            END as valor_base_comissao,
            
            CASE 
                WHEN v.forma_pg IN ('PIX', 'Cartão de Débito', 'Dinheiro', 'À Vista') 
                THEN (v.vlr_total * COALESCE(c2.valor, 0) / 100)
                WHEN v.forma_pg IN ('Parcelado', 'Permuta Parcelado', 'Cartão de Crédito') AND v.vlr_entrada > 0
                THEN (v.vlr_entrada * COALESCE(c2.valor, 0) / 100)
                ELSE 0
            END as comissao_calculada
            
        FROM vendas v
        INNER JOIN vendedores vd2 ON v.id_vendedor2 = vd2.id_vendedor
        LEFT JOIN comissao c2 ON v.id_comissao2 = c2.id_comissao
        $where_vendas
        AND v.id_vendedor2 IS NOT NULL
        AND (
            v.forma_pg IN ('PIX', 'Cartão de Débito', 'Dinheiro', 'À Vista') OR 
            (v.forma_pg IN ('Parcelado', 'Permuta Parcelado', 'Cartão de Crédito') AND v.vlr_entrada > 0)
        )
        $filtro_status_vendas
    ";
    
    // Detalhes de boletos (somente vendedor principal por enquanto - pode ser estendido se necessário)
    $sql_det_boletos = "
        SELECT 
            'boleto' as origem,
            v.id_venda,
            v.cliente,
            b.dt_vencimento as data_referencia,
            v.vlr_total,
            v.vlr_entrada,
            v.forma_pg,
            v.qtd_parcelas,
            vd.nome AS nome_vendedor,
            COALESCE(c.valor, 0) AS percentual_comissao,
            b.id_boleto,
            b.n_parcela,
            b.dt_vencimento,
            b.status as status_boleto,
            '1' as posicao_vendedor,
            
            'boleto_pago' as tipo_comissao,
            b.valor as valor_base_comissao,
            (b.valor * COALESCE(c.valor, 0) / 100) as comissao_calculada
            
        FROM boleto b
        INNER JOIN vendas v ON b.id_venda = v.id_venda
        INNER JOIN vendedores vd ON b.id_vendedor = vd.id_vendedor
        LEFT JOIN comissao c ON v.id_comissao = c.id_comissao
        $where_boletos
        AND b.status = 'Pago'
        $filtro_status_boletos
    ";
    
    // União dos detalhes
    $sql_detalhado = "
        ($sql_det_vendas_v1)
        UNION ALL
        ($sql_det_vendas_v2)
        UNION ALL
        ($sql_det_boletos)
        ORDER BY data_referencia DESC, nome_vendedor, id_venda, posicao_vendedor, n_parcela
    ";
    
    // Duplicar parâmetros para as duas consultas de vendas + uma de boletos
    $params_detalhado = array_merge($params_resumo, $params_resumo, $params_boletos);
    
    $stmt_detalhado = $pdo->prepare($sql_detalhado);
    $stmt_detalhado->execute($params_detalhado);
    $dados_detalhados = $stmt_detalhado->fetchAll(PDO::FETCH_ASSOC);
}

// Calcular totais do relatório detalhado
$valor_total_detalhado = !empty($dados_detalhados) ? array_sum(array_column($dados_detalhados, 'valor_base_comissao')) : 0;
$comissao_total_detalhada = !empty($dados_detalhados) ? array_sum(array_column($dados_detalhados, 'comissao_calculada')) : 0;

// Funções auxiliares
function formatarMoeda($valor) {
    return 'R$ ' . number_format((float)$valor, 2, ',', '.');
}

function formatarData($data) {
    return $data ? date('d/m/Y', strtotime($data)) : 'N/A';
}

function formatarStatus($status) {
    $status_map = [
        'avista' => 'À Vista',
        'entrada' => 'Entrada',
        'boletos' => 'Boletos Pagos',
        'venda_avista' => 'À Vista',
        'boleto_pago' => 'Boleto Pago'
    ];
    return $status_map[$status] ?? ucfirst($status);
}

function getBadgeClass($status) {
    $classes = [
        'venda_avista' => 'bg-primary',
        'entrada' => 'bg-info',
        'boleto_pago' => 'bg-success'
    ];
    return $classes[$status] ?? 'bg-secondary';
}

function getTipoDisplay($tipo) {
    $tipos = [
        'venda_avista' => 'À Vista',
        'entrada' => 'Entrada',
        'boleto_pago' => 'Boleto Pago'
    ];
    return $tipos[$tipo] ?? $tipo;
}

date_default_timezone_set('America/Sao_Paulo');
require_once '../views/header.php';
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Relatório de Comissões</title>
    <style>
        @media print {
            .no-print { display: none !important; }
            body { background: white !important; font-size: 11px; }
            .print-header { display: block !important; text-align: center; margin-bottom: 10px; }
            .table th, .table td { padding: 4px 6px; font-size: 10px; }
        }
        
        body { background-color: #f8f9fa; }
        .print-header { display: none; }
        .header-section { background: white; padding: 2rem 0; margin-bottom: 2rem; }
        .filtros-card { background: white; border-radius: 8px; padding: 1.5rem; margin-bottom: 2rem; }
        .stats-card { background: white; border-radius: 8px; padding: 1.5rem; text-align: center; }
        .content-card { background: white; border-radius: 8px; margin-bottom: 2rem; }
        
        .card-hover:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
        }

        .btn-recibo {
            background: linear-gradient(45deg, #28a745, #20c997);
            border: none;
            color: white;
            font-weight: 500;
        }

        .btn-recibo:hover {
            background: linear-gradient(45deg, #218838, #1ea97c);
            color: white;
            transform: translateY(-1px);
        }

        .stats-comissao {
            background: linear-gradient(135deg, #e8f5e8, #f0fff0);
            border-left: 4px solid #28a745;
            padding: 1rem;
            border-radius: 0.5rem;
        }

        .badge-vendedor {
            font-size: 0.7em;
            padding: 0.2em 0.4em;
            margin-left: 0.3em;
        }

        @media print {
            .no-print {
                display: none !important;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <div class="header-section no-print">
        <div class="container">
            <div class="row align-items-center">
                <div class="col">
                    <h2 class="mb-0">Relatório de Comissões</h2>
                    <p class="text-muted mb-0">Comissões a pagar no período selecionado - Sistema com Dois Vendedores</p>
                </div>
                <div class="col-auto">
                    <a href="vendas_list.php" class="btn btn-outline-secondary me-2">Voltar</a>
                    <button onclick="window.print()" class="btn btn-primary">Imprimir</button>
                </div>
            </div>
        </div>
    </div>
    
    <div class="container">
        <!-- Header para impressão -->
        <div class="print-header">
            <h1>RELATÓRIO DE COMISSÕES A PAGAR</h1>
            <p>Sistema de Gestão de Vendas e Comissões - Dois Vendedores</p>
            <p><strong>Critério:</strong> Vendas à vista + Entradas + Boletos pagos no período</p>
        </div>

        <!-- Filtros -->
        <div class="filtros-card no-print">
            <h5 class="mb-3">Filtros do Relatório</h5>
            <div class="alert alert-info mb-3">
                <strong>Nova Lógica de Comissão com Dois Vendedores:</strong><br>
                • <strong>Vendas À Vista</strong>: Comissão calculada pela data da venda (para ambos os vendedores)<br>
                • <strong>Entradas</strong>: Comissão da entrada calculada pela data da venda (para ambos os vendedores)<br>
                • <strong>Boletos Pagos</strong>: Comissão calculada pela data de vencimento (vendedor principal dos boletos)<br>
                • <strong>Sistema</strong>: Suporte para vendedor principal + segundo vendedor com comissões independentes
            </div>
            <form method="GET" class="row g-3">
                <div class="col-md-2">
                    <label class="form-label">Data Início</label>
                    <input type="date" class="form-control" name="data_inicio" value="<?= htmlspecialchars($data_inicio) ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Data Fim</label>
                    <input type="date" class="form-control" name="data_fim" value="<?= htmlspecialchars($data_fim) ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Vendedor</label>
                    <select class="form-select" name="vendedor">
                        <option value="">Todos os Vendedores</option>
                        <?php foreach ($vendedores_disponiveis as $vendedor): ?>
                            <option value="<?= $vendedor['id_vendedor'] ?>" <?= $vendedor_filtro == $vendedor['id_vendedor'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($vendedor['nome']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Tipo</label>
                    <select class="form-select" name="status">
                        <option value="">Todos</option>
                        <option value="avista" <?= $status_filtro === 'avista' ? 'selected' : '' ?>>À Vista</option>
                        <option value="entrada" <?= $status_filtro === 'entrada' ? 'selected' : '' ?>>Entradas</option>
                        <option value="boletos" <?= $status_filtro === 'boletos' ? 'selected' : '' ?>>Boletos Pagos</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Tipo de Relatório</label>
                    <select class="form-select" name="tipo">
                        <option value="resumo" <?= $tipo_relatorio === 'resumo' ? 'selected' : '' ?>>Resumo</option>
                        <option value="detalhado" <?= $tipo_relatorio === 'detalhado' ? 'selected' : '' ?>>Detalhado</option>
                    </select>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <div class="w-100">
                        <button type="submit" class="btn btn-primary w-100 mb-2">Aplicar Filtros</button>
                        <a href="<?= strtok($_SERVER["REQUEST_URI"], '?') ?>" class="btn btn-outline-secondary w-100">Limpar</a>
                    </div>
                </div>
            </form>
        </div>

        <!-- Estatísticas -->
        <div class="row mb-4 justify-content-center no-print">
            <div class="col-md-2">
                <div class="stats-card">
                    <h3 class="text-primary mb-1"><?= number_format($total_vendas_avista) ?></h3>
                    <small class="text-muted">Vendas À Vista</small>
                </div>
            </div>
            <div class="col-md-2">
                <div class="stats-card">
                    <h3 class="text-info mb-1"><?= number_format($total_vendas_entrada) ?></h3>
                    <small class="text-muted">Entradas</small>
                </div>
            </div>
            <div class="col-md-2">
                <div class="stats-card">
                    <h3 class="text-success mb-1"><?= number_format($total_boletos_pagos) ?></h3>
                    <small class="text-muted">Boletos Pagos</small>
                </div>
            </div>
            <div class="col-md-2">
                <div class="stats-card">
                    <h3 class="text-warning mb-1"><?= formatarMoeda($total_valor_vendas) ?></h3>
                    <small class="text-muted">Valor Total</small>
                </div>
            </div>
            <div class="col-md-2">
                <div class="stats-card">
                    <h3 class="text-success mb-1"><?= formatarMoeda($total_comissao_paga) ?></h3>
                    <small class="text-muted">Total Comissão</small>
                </div>
            </div>
        </div>

        <!-- Conteúdo Principal -->
        <?php if ($tipo_relatorio === 'resumo'): ?>
            <!-- Relatório Resumido -->
            <div class="content-card">
                <div class="p-3">
                    <h5 class="mb-3">Resumo por Vendedor <span class="badge bg-info">Sistema com Dois Vendedores</span></h5>
                    <?php if (empty($dados_resumo)): ?>
                        <div class="alert alert-info mb-0">
                            Nenhuma comissão encontrada com os filtros aplicados.
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>Vendedor</th>
                                        <th>Contato</th>
                                        <th>Chave PIX</th>
                                        <th class="text-center">À Vista</th>
                                        <th class="text-center">Entradas</th>
                                        <th class="text-center">Boletos</th>
                                        <th class="text-end">%</th>
                                        <th class="text-end">Vlr À Vista</th>
                                        <th class="text-end">Vlr Entradas</th>
                                        <th class="text-end">Vlr Boletos</th>
                                        <th class="text-end">Total Comissão</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($dados_resumo as $linha): ?>
                                    <?php 
                                    $total_comissao_vendedor = $linha['comissao_vendas_avista'] + $linha['comissao_entradas'] + $linha['comissao_boletos_pagos'];
                                    ?>
                                    <tr>
                                        <td>
                                            <strong><?= htmlspecialchars($linha['nome_vendedor']) ?></strong>
                                            <br><small class="text-muted">ID: <?= $linha['id_vendedor'] ?></small>
                                        </td>
                                        <td><?= htmlspecialchars($linha['celular'] ?? 'N/A') ?></td>
                                        <td><small><?= htmlspecialchars($linha['chave'] ?? 'N/A') ?></small></td>
                                        <td class="text-center">
                                            <strong><?= number_format($linha['vendas_avista']) ?></strong>
                                        </td>
                                        <td class="text-center">
                                            <strong class="text-info"><?= number_format($linha['vendas_entrada']) ?></strong>
                                        </td>
                                        <td class="text-center">
                                            <strong class="text-success"><?= number_format($linha['boletos_pagos']) ?></strong>
                                        </td>
                                        <td class="text-end">
                                            <span class="badge bg-info"><?= number_format($linha['percentual_comissao'], 1) ?>%</span>
                                        </td>
                                        <td class="text-end">
                                            <strong><?= formatarMoeda($linha['valor_vendas_avista']) ?></strong>
                                        </td>
                                        <td class="text-end">
                                            <strong><?= formatarMoeda($linha['valor_entradas']) ?></strong>
                                        </td>
                                        <td class="text-end">
                                            <strong><?= formatarMoeda($linha['valor_boletos_pagos']) ?></strong>
                                        </td>
                                        <td class="text-end">
                                            <strong class="text-primary"><?= formatarMoeda($total_comissao_vendedor) ?></strong>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot>
                                    <tr class="table-dark">
                                        <th colspan="3">TOTAL GERAL</th>
                                        <th class="text-center"><?= number_format($total_vendas_avista) ?></th>
                                        <th class="text-center"><?= number_format($total_vendas_entrada) ?></th>
                                        <th class="text-center"><?= number_format($total_boletos_pagos) ?></th>
                                        <th></th>
                                        <th class="text-end"><?= formatarMoeda(!empty($dados_resumo) ? array_sum(array_column($dados_resumo, 'valor_vendas_avista')) : 0) ?></th>
                                        <th class="text-end"><?= formatarMoeda(!empty($dados_resumo) ? array_sum(array_column($dados_resumo, 'valor_entradas')) : 0) ?></th>
                                        <th class="text-end"><?= formatarMoeda(!empty($dados_resumo) ? array_sum(array_column($dados_resumo, 'valor_boletos_pagos')) : 0) ?></th>
                                        <th class="text-end"><?= formatarMoeda($total_comissao_paga) ?></th>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- SEÇÃO DE GERAÇÃO DE RECIBOS -->
            <?php if ($tipo_relatorio === 'resumo' && !empty($dados_resumo)): ?>
                <div class="card mt-4">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0">📄 Geração de Recibos Individuais</h5>
                    </div>
                    <div class="card-body">
                        <p class="text-muted mb-3">
                            Gere recibos individuais para cada vendedor com base no período filtrado. 
                            Os recibos seguem o modelo oficial da empresa e podem ser impressos para assinatura.
                            <span class="badge bg-info">Sistema atualizado para dois vendedores</span>
                        </p>
                        
                        <div class="row">
                            <?php foreach ($dados_resumo as $linha): ?>
                                <?php 
                                $total_comissao_vendedor = $linha['comissao_vendas_avista'] + 
                                                         $linha['comissao_entradas'] + 
                                                         $linha['comissao_boletos_pagos'];
                                ?>
                                <?php if ($total_comissao_vendedor > 0): ?>
                                <div class="col-md-6 col-lg-4 mb-3">
                                    <div class="card border-success card-hover">
                                        <div class="card-body">
                                            <h6 class="card-title text-success">
                                                <i class="fas fa-user"></i> <?= htmlspecialchars($linha['nome_vendedor']) ?>
                                            </h6>
                                            <div class="mb-2">
                                                <small class="text-muted">Comissão Total:</small><br>
                                                <strong class="text-success h5"><?= formatarMoeda($total_comissao_vendedor) ?></strong>
                                            </div>
                                            <div class="mb-3">
                                                <small class="text-muted">PIX:</small><br>
                                                <small><?= $linha['tipochave'] ?>: <?= htmlspecialchars($linha['chave']) ?></small>
                                            </div>
                                            
                                            <div class="d-grid gap-2">
                                                <a href="recibo_comissao.php?vendedor=<?= $linha['id_vendedor'] ?>&data_inicio=<?= $data_inicio ?>&data_fim=<?= $data_fim ?>" 
                                                   class="btn btn-success btn-sm">
                                                    <i class="fas fa-receipt"></i> Gerar Recibo
                                                </a>
                                                <button class="btn btn-outline-success btn-sm" 
                                                        onclick="imprimirReciboDireto(<?= $linha['id_vendedor'] ?>)">
                                                    <i class="fas fa-print"></i> Imprimir Direto
                                                </button>
                                                <button class="btn btn-info btn-sm" 
                                                        onclick="mostrarPreviewRecibo(<?= $linha['id_vendedor'] ?>, '<?= htmlspecialchars($linha['nome_vendedor']) ?>')">
                                                    <i class="fas fa-eye"></i> Preview
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                        
                        <!-- Botões para gerar todos os recibos -->
                        <div class="text-center mt-4">
                            <button class="btn btn-success btn-lg btn-recibo" onclick="gerarTodosRecibos()">
                                <i class="fas fa-file-invoice"></i> Gerar Todos os Recibos em PDF
                            </button>
                            <button class="btn btn-outline-success btn-lg ms-2" onclick="imprimirTodosRecibos()">
                                <i class="fas fa-print"></i> Imprimir Todos
                            </button>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

        <?php else: ?>
            <!-- Relatório Detalhado -->
            <div class="content-card">
                <div class="p-3">
                    <h5 class="mb-3">Relatório Detalhado <span class="badge bg-info">Dois Vendedores</span> (<?= number_format(count($dados_detalhados)) ?> registros)</h5>
                    <?php if (empty($dados_detalhados)): ?>
                        <div class="alert alert-info mb-0">
                            Nenhuma comissão encontrada com os filtros aplicados.
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead class="table-dark">
                                    <tr>
                                        <th>Vendedor</th>
                                        <th>Cliente</th>
                                        <th>Data</th>
                                        <th>Tipo</th>
                                        <th>Forma Pagto</th>
                                        <th>Valor Base</th>
                                        <th>%</th>
                                        <th>Comissão</th>
                                        <th>Posição</th>
                                        <th>Obs</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($dados_detalhados as $item): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($item['nome_vendedor']) ?></td>
                                        <td><?= htmlspecialchars($item['cliente']) ?></td>
                                        <td><?= formatarData($item['data_referencia']) ?></td>
                                        <td>
                                            <?php
                                            switch ($item['tipo_comissao']) {
                                                case 'venda_avista': 
                                                    echo '<span class="badge bg-primary">À Vista</span>'; 
                                                    break;
                                                case 'entrada': 
                                                    echo '<span class="badge bg-info">Entrada</span>'; 
                                                    break;
                                                case 'boleto_pago': 
                                                    echo '<span class="badge bg-success">Boleto Pago (' . $item['n_parcela'] . '/' . $item['qtd_parcelas'] . ')</span>'; 
                                                    break;
                                                default: 
                                                    echo '<span class="badge bg-secondary">Outros</span>';
                                            }
                                            ?>
                                        </td>
                                        <td><?= htmlspecialchars($item['forma_pg']) ?></td>
                                        <td class="text-end"><?= formatarMoeda($item['valor_base_comissao']) ?></td>
                                        <td class="text-center"><?= number_format($item['percentual_comissao'], 1) ?>%</td>
                                        <td class="text-end"><strong class="text-success"><?= formatarMoeda($item['comissao_calculada']) ?></strong></td>
                                        <td class="text-center">
                                            <?php if ($item['posicao_vendedor'] == '1'): ?>
                                                <span class="badge bg-primary badge-vendedor">Principal</span>
                                            <?php elseif ($item['posicao_vendedor'] == '2'): ?>
                                                <span class="badge bg-secondary badge-vendedor">Segundo</span>
                                            <?php else: ?>
                                                <span class="badge bg-dark badge-vendedor">N/A</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($item['tipo_comissao'] === 'entrada'): ?>
                                                <small class="text-info">Entrada: <?= formatarMoeda($item['vlr_entrada']) ?> | Total: <?= formatarMoeda($item['vlr_total']) ?></small>
                                            <?php elseif ($item['tipo_comissao'] === 'venda_avista'): ?>
                                                <small class="text-primary">Venda total: <?= formatarMoeda($item['vlr_total']) ?></small>
                                            <?php elseif ($item['tipo_comissao'] === 'boleto_pago'): ?>
                                                <small class="text-success">Boleto venc: <?= formatarData($item['dt_vencimento']) ?> | Entrada: <?= formatarMoeda($item['vlr_entrada']) ?></small>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot>
                                    <tr class="table-dark">
                                        <th colspan="5">TOTAL GERAL</th>
                                        <th class="text-end"><?= formatarMoeda($valor_total_detalhado) ?></th>
                                        <th></th>
                                        <th class="text-end"><?= formatarMoeda($comissao_total_detalhada) ?></th>
                                        <th></th>
                                        <th><?= number_format(count($dados_detalhados)) ?> registros</th>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
        
        <!-- Informações complementares -->
        <div class="row no-print">
            <div class="col-md-3">
                <strong class="text-primary">Vendas À Vista</strong>
                <p class="small mb-2">PIX, Cartão Débito, Dinheiro</p>
                <p class="small text-muted">Comissão calculada sobre o valor total na <strong>data da venda</strong></p>
            </div>
            <div class="col-md-3">
                <strong class="text-info">Entradas</strong>
                <p class="small mb-2">Valor de entrada de vendas parceladas</p>
                <p class="small text-muted">Comissão calculada sobre o valor da entrada na <strong>data da venda</strong></p>
            </div>
            <div class="col-md-3">
                <strong class="text-success">Boletos Pagos</strong>
                <p class="small mb-2">Parcelas com status 'Pago'</p>
                <p class="small text-muted">Comissão calculada sobre o valor do boleto na <strong>data de vencimento</strong></p>
            </div>
            <div class="col-md-3">
                <strong class="text-warning">Dois Vendedores</strong>
                <p class="small mb-2">Vendedor principal + segundo vendedor</p>
                <p class="small text-muted">Comissões independentes calculadas para cada vendedor</p>
            </div>
        </div>
    </div>

    <!-- JAVASCRIPT PARA FUNCIONALIDADE DOS RECIBOS -->
    <script>
        // Script para melhorar a experiência do usuário
        document.addEventListener('DOMContentLoaded', function() {
            // Adicionar feedback visual aos filtros
            const form = document.querySelector('form');
            if (form) {
                form.addEventListener('submit', function() {
                    const submitBtn = form.querySelector('button[type="submit"]');
                    if (submitBtn) {
                        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Carregando...';
                        submitBtn.disabled = true;
                    }
                });
            }

            // Validação de datas
            const dataInicio = document.querySelector('input[name="data_inicio"]');
            const dataFim = document.querySelector('input[name="data_fim"]');
            
            function validarDatas() {
                if (dataInicio.value && dataFim.value && dataInicio.value > dataFim.value) {
                    alert('A data de início não pode ser maior que a data de fim!');
                    dataFim.value = '';
                }
            }
            
            if (dataInicio && dataFim) {
                dataInicio.addEventListener('change', validarDatas);
                dataFim.addEventListener('change', validarDatas);
            }
        });

        // Função para imprimir recibo direto
        function imprimirReciboDireto(idVendedor) {
            const params = new URLSearchParams({
                vendedor: idVendedor,
                data_inicio: '<?= $data_inicio ?>',
                data_fim: '<?= $data_fim ?>',
                print: '1'
            });
            
            const url = 'recibo_comissao.php?' + params.toString();
            window.open(url, '_blank', 'width=800,height=600');
        }

        // Função para gerar todos os recibos
        function gerarTodosRecibos() {
            const vendedores = <?= json_encode(array_map(function($linha) {
                return [
                    'id' => $linha['id_vendedor'],
                    'nome' => $linha['nome_vendedor'],
                    'comissao' => $linha['comissao_vendas_avista'] + $linha['comissao_entradas'] + $linha['comissao_boletos_pagos']
                ];
            }, array_filter($dados_resumo, function($linha) {
                return ($linha['comissao_vendas_avista'] + $linha['comissao_entradas'] + $linha['comissao_boletos_pagos']) > 0;
            }))) ?>;
            
            if (vendedores.length === 0) {
                alert('Nenhum vendedor com comissão encontrado.');
                return;
            }
            
            if (confirm(`Deseja gerar recibos para ${vendedores.length} vendedor(es)?`)) {
                // Abrir uma nova janela com todos os recibos
                const params = new URLSearchParams({
                    data_inicio: '<?= $data_inicio ?>',
                    data_fim: '<?= $data_fim ?>',
                    todos_vendedores: '1'
                });
                
                window.open('recibos_multiplos.php?' + params.toString(), '_blank');
            }
        }

        // Função para imprimir todos os recibos
        function imprimirTodosRecibos() {
            const vendedores = <?= json_encode(array_map(function($linha) {
                return $linha['id_vendedor'];
            }, array_filter($dados_resumo, function($linha) {
                return ($linha['comissao_vendas_avista'] + $linha['comissao_entradas'] + $linha['comissao_boletos_pagos']) > 0;
            }))) ?>;
            
            if (vendedores.length === 0) {
                alert('Nenhum vendedor com comissão encontrado.');
                return;
            }
            
            if (confirm(`Deseja imprimir recibos para ${vendedores.length} vendedor(es)?`)) {
                vendedores.forEach((idVendedor, index) => {
                    setTimeout(() => {
                        imprimirReciboDireto(idVendedor);
                    }, index * 1000); // Delay de 1 segundo entre cada impressão
                });
            }
        }

        // Função para mostrar preview do recibo em modal
        function mostrarPreviewRecibo(idVendedor, nomeVendedor) {
            const params = new URLSearchParams({
                vendedor: idVendedor,
                data_inicio: '<?= $data_inicio ?>',
                data_fim: '<?= $data_fim ?>',
                preview: '1'
            });
            
            // Criar modal para preview
            const modal = document.createElement('div');
            modal.className = 'modal fade';
            modal.innerHTML = `
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Preview do Recibo - ${nomeVendedor}</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <iframe src="recibo_comissao.php?${params.toString()}" 
                                    width="100%" height="500" frameborder="0"></iframe>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                            <button type="button" class="btn btn-success" onclick="imprimirReciboDireto(${idVendedor})">
                                Imprimir
                            </button>
                        </div>
                    </div>
                </div>
            `;
            
            document.body.appendChild(modal);
            const modalInstance = new bootstrap.Modal(modal);
            modalInstance.show();
            
            // Remover modal após fechar
            modal.addEventListener('hidden.bs.modal', () => {
                document.body.removeChild(modal);
            });
        }
    </script>
</body>
</html>

<?php require_once '../views/footer.php'; ?>