<?php
/**
 * ARQUIVO: recibo_comissao.php
 * Versão compacta com detalhamento anexo
 */

require_once '../config/protect.php';
require_once '../config/config.php';

// Verificar se foi solicitado um vendedor específico
$id_vendedor = $_GET['vendedor'] ?? null;
$data_inicio = $_GET['data_inicio'] ?? '';
$data_fim = $_GET['data_fim'] ?? '';
$print_mode = $_GET['print'] ?? false;
$preview_mode = $_GET['preview'] ?? false;

if (!$id_vendedor) {
    header('Location: relatorio_comissao.php');
    exit;
}

// Buscar dados do vendedor
$sqlVendedor = "SELECT * FROM vendedores WHERE id_vendedor = ? AND STATUS = 1";
$stmtVendedor = $pdo->prepare($sqlVendedor);
$stmtVendedor->execute([$id_vendedor]);
$vendedor = $stmtVendedor->fetch();

if (!$vendedor) {
    header('Location: relatorio_comissao.php?erro=Vendedor não encontrado');
    exit;
}

// Construir parâmetros separados para cada query
$params_vendas = [$id_vendedor];
$filtros_vendas = ["v.id_vendedor = ?"];

$params_boletos = [$id_vendedor];
$filtros_boletos = ["b.id_vendedor = ?"];

if ($data_inicio) {
    $filtros_vendas[] = "DATE(v.dt_venda) >= ?";
    $params_vendas[] = $data_inicio;
    
    $filtros_boletos[] = "DATE(b.dt_vencimento) >= ?";
    $params_boletos[] = $data_inicio;
}

if ($data_fim) {
    $filtros_vendas[] = "DATE(v.dt_venda) <= ?";
    $params_vendas[] = $data_fim;
    
    $filtros_boletos[] = "DATE(b.dt_vencimento) <= ?";
    $params_boletos[] = $data_fim;
}

$where_vendas = 'WHERE ' . implode(' AND ', $filtros_vendas);
$where_boletos = 'WHERE ' . implode(' AND ', $filtros_boletos);

// Query para vendas à vista e entradas
$sql_vendas = "
    SELECT 
        'venda' as origem,
        v.id_venda,
        v.cliente,
        v.dt_venda as data_referencia,
        v.vlr_total,
        v.vlr_entrada,
        v.forma_pg,
        v.qtd_parcelas,
        COALESCE(c.valor, 0) AS percentual_comissao,
        NULL as id_boleto,
        NULL as n_parcela,
        NULL as dt_vencimento,
        NULL as status_boleto,
        
        CASE 
            WHEN v.forma_pg IN ('PIX', 'Cartão Débito', 'Dinheiro', 'À Vista') 
            THEN 'venda_avista'
            WHEN v.forma_pg IN ('Parcelado', 'Permuta Parcelado', 'Cartão de Crédito') AND v.vlr_entrada > 0
            THEN 'entrada'
            ELSE 'outros'
        END as tipo_comissao,
        
        CASE 
            WHEN v.forma_pg IN ('PIX', 'Cartão Débito', 'Dinheiro', 'À Vista') 
            THEN v.vlr_total
            WHEN v.forma_pg IN ('Parcelado', 'Permuta Parcelado', 'Cartão de Crédito') AND v.vlr_entrada > 0
            THEN v.vlr_entrada
            ELSE 0
        END as valor_base_comissao,
        
        CASE 
            WHEN v.forma_pg IN ('PIX', 'Cartão Débito', 'Dinheiro', 'À Vista') 
            THEN (v.vlr_total * COALESCE(c.valor, 0) / 100)
            WHEN v.forma_pg IN ('Parcelado', 'Permuta Parcelado', 'Cartão de Crédito') AND v.vlr_entrada > 0
            THEN (v.vlr_entrada * COALESCE(c.valor, 0) / 100)
            ELSE 0
        END as comissao_calculada
        
    FROM vendas v
    LEFT JOIN comissao c ON v.id_comissao = c.id_comissao
    $where_vendas
    AND (
        v.forma_pg IN ('PIX', 'Cartão Débito', 'Dinheiro', 'À Vista') OR 
        (v.forma_pg IN ('Parcelado', 'Permuta Parcelado', 'Cartão de Crédito') AND v.vlr_entrada > 0)
    )
";

// Query para boletos pagos
$sql_boletos = "
    SELECT 
        'boleto' as origem,
        v.id_venda,
        v.cliente,
        b.dt_vencimento as data_referencia,
        v.vlr_total,
        v.vlr_entrada,
        v.forma_pg,
        v.qtd_parcelas,
        COALESCE(c.valor, 0) AS percentual_comissao,
        b.id_boleto,
        b.n_parcela,
        b.dt_vencimento,
        b.status as status_boleto,
        
        'boleto_pago' as tipo_comissao,
        b.valor as valor_base_comissao,
        (b.valor * COALESCE(c.valor, 0) / 100) as comissao_calculada
        
    FROM boleto b
    INNER JOIN vendas v ON b.id_venda = v.id_venda
    LEFT JOIN comissao c ON v.id_comissao = c.id_comissao
    $where_boletos
    AND b.status = 'Pago'
";

// Executar as queries separadamente e combinar os resultados
$dados_comissao = [];

// Buscar vendas
$stmt_vendas = $pdo->prepare($sql_vendas);
$stmt_vendas->execute($params_vendas);
$dados_vendas = $stmt_vendas->fetchAll(PDO::FETCH_ASSOC);

// Buscar boletos
$stmt_boletos = $pdo->prepare($sql_boletos);
$stmt_boletos->execute($params_boletos);
$dados_boletos = $stmt_boletos->fetchAll(PDO::FETCH_ASSOC);

// Combinar os resultados
$dados_comissao = array_merge($dados_vendas, $dados_boletos);

// Ordenar por data de referência (mais recente primeiro)
usort($dados_comissao, function($a, $b) {
    return strtotime($b['data_referencia']) - strtotime($a['data_referencia']);
});

// Calcular totais
$total_comissao = 0;
$total_valor_base = 0;
foreach ($dados_comissao as $item) {
    $total_comissao += $item['comissao_calculada'];
    $total_valor_base += $item['valor_base_comissao'];
}

// Funções auxiliares
function formatarMoeda($valor) {
    return 'R$ ' . number_format((float)$valor, 2, ',', '.');
}

function formatarData($data) {
    return $data ? date('d/m/Y', strtotime($data)) : 'N/A';
}

function gerarNumeroRecibo($id_vendedor, $mes, $ano) {
    return sprintf("%04d%02d%03d", $ano, $mes, $id_vendedor);
}

function valorPorExtenso($valor) {
    $valor = number_format($valor, 2, '.', '');
    $partes = explode('.', $valor);
    $reais = (int)$partes[0];
    $centavos = (int)$partes[1];
    
    $extenso_reais = numeroParaExtenso($reais);
    $extenso_centavos = numeroParaExtenso($centavos);
    
    $resultado = '';
    if ($reais > 0) {
        $resultado .= $extenso_reais . ($reais == 1 ? ' real' : ' reais');
    }
    
    if ($centavos > 0) {
        if ($reais > 0) $resultado .= ' e ';
        $resultado .= $extenso_centavos . ($centavos == 1 ? ' centavo' : ' centavos');
    }
    
    if ($reais == 0 && $centavos == 0) {
        $resultado = 'zero reais';
    }
    
    return $resultado;
}

function numeroParaExtenso($numero) {
    $unidades = ['', 'um', 'dois', 'três', 'quatro', 'cinco', 'seis', 'sete', 'oito', 'nove'];
    $dezenas = ['', '', 'vinte', 'trinta', 'quarenta', 'cinquenta', 'sessenta', 'setenta', 'oitenta', 'noventa'];
    $especiais = ['dez', 'onze', 'doze', 'treze', 'quatorze', 'quinze', 'dezesseis', 'dezessete', 'dezoito', 'dezenove'];
    $centenas = ['', 'cento', 'duzentos', 'trezentos', 'quatrocentos', 'quinhentos', 'seiscentos', 'setecentos', 'oitocentos', 'novecentos'];
    
    if ($numero == 0) return 'zero';
    if ($numero == 100) return 'cem';
    
    $resultado = '';
    
    // Centenas
    $c = intval($numero / 100);
    if ($c > 0) {
        $resultado .= $centenas[$c];
    }
    
    $resto = $numero % 100;
    
    if ($resto > 0 && $c > 0) {
        $resultado .= ' e ';
    }
    
    // Dezenas e unidades
    if ($resto >= 10 && $resto <= 19) {
        $resultado .= $especiais[$resto - 10];
    } else {
        $d = intval($resto / 10);
        $u = $resto % 10;
        
        if ($d > 0) {
            $resultado .= $dezenas[$d];
        }
        
        if ($u > 0) {
            if ($d > 0) $resultado .= ' e ';
            $resultado .= $unidades[$u];
        }
    }
    
    return $resultado;
}

function formatarChavePix($tipo, $chave) {
    switch ($tipo) {
        case 'CPF':
            return preg_replace('/(\d{3})(\d{3})(\d{3})(\d{2})/', '$1.$2.$3-$4', $chave);
        case 'CNPJ':
            return preg_replace('/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/', '$1.$2.$3/$4-$5', $chave);
        case 'Telefone':
            return preg_replace('/(\d{2})(\d{4,5})(\d{4})/', '($1) $2-$3', $chave);
        default:
            return $chave;
    }
}

// Gerar dados do recibo
$numero_recibo = gerarNumeroRecibo($id_vendedor, date('m'), date('Y'));
$data_atual = date('d \d\e F \d\e Y');
$periodo_inicio = $data_inicio ?: date('Y-m-01');
$periodo_fim = $data_fim ?: date('Y-m-t');
$mes_ano = date('m/Y', strtotime($periodo_inicio));

// Se é modo de impressão ou preview, mostrar layout compacto
if ($print_mode || $preview_mode) {
    ?>
    <!DOCTYPE html>
    <html lang="pt-BR">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Recibo de Comissão - <?= htmlspecialchars($vendedor['nome']) ?></title>
        <style>
            body {
                margin: 0;
                padding: 15px;
                font-family: 'Courier New', monospace;
                font-size: 11px;
                line-height: 1.2;
                color: #000;
                background: white;
            }
            
            .recibo-page {
                width: 100%;
                max-width: 210mm; /* A4 width */
                margin: 0 auto;
            }
            
            .recibo-container {
                width: 100%;
                max-width: 400px; /* Layout mais quadrado */
                margin: 0 auto 30px auto;
                background: white;
                page-break-after: always;
            }
            
            .detalhamento-container {
                width: 100%;
                margin: 30px auto 0 auto;
                background: white;
            }
            
            .border {
                border: 2px solid #000;
            }
            
            .border-bottom {
                border-bottom: 1px solid #000;
            }
            
            .table-bordered {
                border: 1px solid #000;
                border-collapse: collapse;
                width: 100%;
            }
            
            .table-bordered td,
            .table-bordered th {
                border: 1px solid #000;
                padding: 4px;
                text-align: left;
                font-size: 9px;
            }
            
            .text-center { text-align: center; }
            .text-end { text-align: right; }
            .text-uppercase { text-transform: uppercase; }
            .mb-0 { margin-bottom: 0; }
            .mb-2 { margin-bottom: 8px; }
            .mb-3 { margin-bottom: 12px; }
            .mb-4 { margin-bottom: 16px; }
            .mt-3 { margin-top: 12px; }
            .mt-4 { margin-top: 16px; }
            .pb-1 { padding-bottom: 3px; }
            .pb-2 { padding-bottom: 6px; }
            .pt-1 { padding-top: 3px; }
            .pt-2 { padding-top: 6px; }
            .ps-2 { padding-left: 6px; }
            .p-2 { padding: 6px; }
            .p-3 { padding: 10px; }
            
            .recibo-titulo h2 {
                font-family: 'Courier New', monospace;
                letter-spacing: 2px;
                font-size: 18px;
                font-weight: bold;
                display: inline-block;
                margin: 0;
                padding: 6px 12px;
            }
            
            .header-info {
                display: flex;
                justify-content: space-between;
                align-items: flex-start;
                margin-bottom: 12px;
            }
            
            .numero-valor {
                display: flex;
                gap: 8px;
            }
            
            .campo-pequeno {
                min-width: 80px;
                display: inline-block;
            }
            
            .campo-medio {
                min-width: 100px;
                display: inline-block;
            }
            
            .dados-recebedor {
                display: flex;
                gap: 8px;
                margin-top: 12px;
            }
            
            .col-esquerda {
                flex: 1;
            }
            
            .col-direita {
                width: 150px;
            }
            
            .linha-dupla {
                display: flex;
                gap: 8px;
                margin-top: 8px;
            }
            
            .linha-dupla > div {
                flex: 1;
            }
            
            @media print {
                body {
                    margin: 0;
                    padding: 10px;
                }
                
                .recibo-container:last-child {
                    page-break-after: auto;
                }
            }
            
            <?php if ($print_mode): ?>
            @page {
                margin: 1cm;
                size: A4;
            }
            <?php endif; ?>
        </style>
        <?php if ($print_mode): ?>
        <script>
            window.onload = function() {
                window.print();
                window.onafterprint = function() {
                    window.close();
                };
            };
        </script>
        <?php endif; ?>
    </head>
    <body>
        <div class="recibo-page">
            <!-- RECIBO COMPACTO -->
            <div class="recibo-container">
                <div class="header-info">
                    <div class="recibo-titulo">
                        <h2 class="border">R E C I B O</h2>
                    </div>
                    <div class="numero-valor">
                        <div class="border p-2 campo-pequeno">
                            <strong>Nº</strong><br>
                            <span style="font-size: 12px;"><?= $numero_recibo ?></span>
                        </div>
                        <div class="border p-2 campo-medio">
                            <strong>VALOR R$</strong><br>
                            <span style="font-size: 14px; font-weight: bold;"><?= number_format($total_comissao, 2, ',', '.') ?></span>
                        </div>
                    </div>
                </div>

                <div class="border p-3">
                    <!-- Dados do Pagador -->
                    <div class="mb-3">
                        <div class="border-bottom pb-1 mb-2">
                            <strong>RECEBI (EMOS) DE:</strong>
                        </div>
                        <div class="ps-2">
                            VIA PETRA MÁRMORES E GRANITOS LTDA.
                        </div>
                    </div>

                    <!-- Endereço -->
                    <div class="mb-3">
                        <div class="border-bottom pb-1 mb-2">
                            <strong>ENDEREÇO:</strong>
                        </div>
                        <div class="ps-2">
                            RUA DO PORTO 04, QD. 06, LT. 01, RESIDENCIAL PORTO SEGURO, RIO VERDE/GO.
                        </div>
                    </div>

                    <!-- Valor por extenso -->
                    <div class="mb-3">
                        <div class="border-bottom pb-1 mb-2">
                            <strong>A IMPORTÂNCIA DE:</strong>
                        </div>
                        <div class="ps-2 text-uppercase">
                            <?= valorPorExtenso($total_comissao) ?>
                        </div>
                    </div>

                    <!-- Referente a -->
                    <div class="mb-3">
                        <div class="border-bottom pb-1 mb-2">
                            <strong>REFERENTE A:</strong>
                        </div>
                        <div class="ps-2">
                            PAGAMENTO PROPORCIONALIDADE COMISSÃO <?= strtoupper($mes_ano) ?>
                        </div>
                    </div>

                    <!-- Dados bancários compactos -->
                    <div class="mb-3">
                        <table class="table-bordered mb-0" style="width: 100%;">
                            <tr>
                                <td style="width: 40%;"><strong>CHEQUE Nº</strong></td>
                                <td style="width: 30%; text-align: center;"><strong>BANCO</strong></td>
                                <td style="width: 30%; text-align: center;"><strong>AGÊNCIA</strong></td>
                            </tr>
                            <tr>
                                <td style="height: 25px;"></td>
                                <td></td>
                                <td></td>
                            </tr>
                        </table>
                    </div>

                    <!-- Dados do recebedor compactos -->
                    <div class="dados-recebedor">
                        <div class="col-esquerda">
                            <div class="border p-2">
                                <div class="mb-2">
                                    <strong>NOME EMITENTE:</strong><br>
                                    <span style="font-size: 14px;"><?= strtoupper($vendedor['nome']) ?></span>
                                </div>
                                
                                <div class="linha-dupla">
                                    <div>
                                        <div class="border-bottom pb-1">
                                            <small><strong><?= strtoupper($vendedor['tipochave']) ?></strong></small>
                                        </div>
                                        <div class="pt-1">
                                            <span style="font-size: 10px;"><?= formatarChavePix($vendedor['tipochave'], $vendedor['chave']) ?></span>
                                        </div>
                                    </div>
                                    <div>
                                        <div class="border-bottom pb-1">
                                            <small><strong>RG Nº</strong></small>
                                        </div>
                                        <div class="pt-1" style="height: 15px;">
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="mt-3">
                                    <div class="border-bottom pb-1">
                                        <small><strong>END.:</strong></small>
                                    </div>
                                    <div class="pt-1" style="height: 15px;">
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-direita">
                            <div class="border p-2 text-center" style="height: 100%;">
                                <div class="mb-2">
                                    <strong>LOCAL / DATA</strong>
                                </div>
                                <div style="font-size: 12px; margin: 15px 0;">
                                    Rio Verde/GO<br><?= $data_atual ?>
                                </div>
                                
                                <div class="mt-4 pt-2" style="border-top: 1px solid #000; margin-top: 40px;">
                                    <small>ASSINATURA</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- DETALHAMENTO DA COMISSÃO -->
            <div class="detalhamento-container">
                <div class="border p-3">
                    <h3 class="text-center mb-3" style="font-size: 14px; letter-spacing: 1px;">
                        DETALHAMENTO DA COMISSÃO - <?= strtoupper($vendedor['nome']) ?>
                    </h3>
                    
                    <div class="mb-3 text-center">
                        <strong>Período:</strong> <?= formatarData($periodo_inicio) ?> a <?= formatarData($periodo_fim) ?> | 
                        <strong>Total de Itens:</strong> <?= count($dados_comissao) ?> | 
                        <strong>Valor Base:</strong> <?= formatarMoeda($total_valor_base) ?>
                    </div>
                    
                    <table class="table-bordered">
                        <thead>
                            <tr style="background-color: #f0f0f0;">
                                <th>Data</th>
                                <th>Cliente</th>
                                <th>Tipo</th>
                                <th>Forma Pagto</th>
                                <th>Valor Base</th>
                                <th>%</th>
                                <th>Comissão</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($dados_comissao as $item): ?>
                            <tr>
                                <td><?= date('d/m/Y', strtotime($item['data_referencia'])) ?></td>
                                <td><?= htmlspecialchars(substr($item['cliente'], 0, 25)) ?><?= strlen($item['cliente']) > 25 ? '...' : '' ?></td>
                                <td>
                                    <?php
                                    switch ($item['tipo_comissao']) {
                                        case 'venda_avista': 
                                            echo 'À Vista'; 
                                            break;
                                        case 'entrada': 
                                            echo 'Entrada'; 
                                            break;
                                        case 'boleto_pago': 
                                            echo 'Boleto (' . $item['n_parcela'] . '/' . $item['qtd_parcelas'] . ')'; 
                                            break;
                                        default: 
                                            echo 'Outros';
                                    }
                                    ?>
                                </td>
                                <td><?= htmlspecialchars($item['forma_pg']) ?></td>
                                <td><?= formatarMoeda($item['valor_base_comissao']) ?></td>
                                <td><?= number_format($item['percentual_comissao'], 1) ?>%</td>
                                <td><strong><?= formatarMoeda($item['comissao_calculada']) ?></strong></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr style="background-color: #f0f0f0;">
                                <th colspan="6" class="text-end">TOTAL DA COMISSÃO:</th>
                                <th><strong><?= formatarMoeda($total_comissao) ?></strong></th>
                            </tr>
                        </tfoot>
                    </table>
                    
                    <div class="mt-4 text-center">
                        <small>
                            <strong>Observações:</strong> Este detalhamento complementa o recibo acima e deve ser anexado para controle interno.<br>
                            <strong>Gerado em:</strong> <?= date('d/m/Y H:i:s') ?> | 
                            <strong>Sistema:</strong> Via Petra - Gestão de Vendas e Comissões
                        </small>
                    </div>
                </div>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// Se não é modo de impressão, mostrar a página completa com detalhes
require_once '../views/header.php';
?>

<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="mb-1">Recibo de Comissão</h2>
            <p class="text-muted mb-0"><?= htmlspecialchars($vendedor['nome']) ?></p>
        </div>
        <div>
            <a href="relatorio_comissao.php?data_inicio=<?= $data_inicio ?>&data_fim=<?= $data_fim ?>" class="btn btn-secondary me-2">
                ← Voltar ao Relatório
            </a>
            <button onclick="imprimirRecibo()" class="btn btn-primary">
                <i class="fas fa-print"></i> Imprimir Recibo + Detalhamento
            </button>
        </div>
    </div>

    <?php if ($total_comissao <= 0): ?>
        <div class="alert alert-warning">
            <h5>⚠️ Nenhuma comissão encontrada</h5>
            <p class="mb-0">Não há comissões para este vendedor no período selecionado.</p>
        </div>
    <?php else: ?>
        <!-- Resumo do Recibo -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">📊 Resumo da Comissão</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4">
                        <strong>Período:</strong> <?= formatarData($periodo_inicio) ?> até <?= formatarData($periodo_fim) ?>
                    </div>
                    <div class="col-md-4">
                        <strong>Total de Itens:</strong> <?= count($dados_comissao) ?><br>
                        <strong>Valor Base Total:</strong> <?= formatarMoeda($total_valor_base) ?>
                    </div>
                    <div class="col-md-4">
                        <strong>Total da Comissão:</strong> <span class="h5 text-success"><?= formatarMoeda($total_comissao) ?></span><br>
                        <strong>Número do Recibo:</strong> <?= $numero_recibo ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Preview dos Documentos -->
        <div class="row">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">📄 Preview do Recibo</h5>
                    </div>
                    <div class="card-body">
                        <div class="border p-3" style="background-color: #f8f9fa; font-size: 11px;">
                            <div class="text-center mb-2">
                                <div class="border border-dark d-inline-block px-2 py-1">
                                    <strong style="letter-spacing: 1px;">R E C I B O</strong>
                                </div>
                                <div class="float-end">
                                    <small><strong>Nº:</strong> <?= $numero_recibo ?></small><br>
                                    <small><strong>Valor:</strong> <?= formatarMoeda($total_comissao) ?></small>
                                </div>
                            </div>
                            <div class="clearfix mb-2"></div>
                            <div style="font-size: 10px;">
                                <div><strong>RECEBI DE:</strong> VIA PETRA MÁRMORES E GRANITOS LTDA.</div>
                                <div><strong>IMPORTÂNCIA:</strong> <?= strtoupper(substr(valorPorExtenso($total_comissao), 0, 40)) ?>...</div>
                                <div><strong>REFERENTE A:</strong> COMISSÃO <?= strtoupper($mes_ano) ?></div>
                                <div class="mt-2">
                                    <strong><?= strtoupper($vendedor['nome']) ?></strong><br>
                                    <small><?= $vendedor['tipochave'] ?>: <?= formatarChavePix($vendedor['tipochave'], $vendedor['chave']) ?></small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">📋 Preview do Detalhamento</h5>
                    </div>
                    <div class="card-body">
                        <div class="border p-3" style="background-color: #f8f9fa; font-size: 10px;">
                            <div class="text-center mb-2">
                                <strong>DETALHAMENTO DA COMISSÃO</strong><br>
                                <small><?= strtoupper($vendedor['nome']) ?></small>
                            </div>
                            <table class="table table-sm table-bordered" style="font-size: 8px;">
                                <thead>
                                    <tr>
                                        <th>Data</th>
                                        <th>Cliente</th>
                                        <th>Tipo</th>
                                        <th>Comissão</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php $count = 0; foreach ($dados_comissao as $item): if($count >= 3) break; $count++; ?>
                                    <tr>
                                        <td><?= date('d/m', strtotime($item['data_referencia'])) ?></td>
                                        <td><?= htmlspecialchars(substr($item['cliente'], 0, 15)) ?></td>
                                        <td>
                                            <?php
                                            switch ($item['tipo_comissao']) {
                                                case 'venda_avista': echo 'À Vista'; break;
                                                case 'entrada': echo 'Entrada'; break;
                                                case 'boleto_pago': echo 'Boleto'; break;
                                            }
                                            ?>
                                        </td>
                                        <td><?= formatarMoeda($item['comissao_calculada']) ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <?php if (count($dados_comissao) > 3): ?>
                                    <tr>
                                        <td colspan="3" class="text-center"><small>... e mais <?= count($dados_comissao) - 3 ?> itens</small></td>
                                        <td><strong><?= formatarMoeda($total_comissao) ?></strong></td>
                                    </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Detalhamento Completo -->
        <div class="card mt-4">
            <div class="card-header">
                <h5 class="mb-0">📋 Detalhamento Completo da Comissão</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-striped table-hover mb-0">
                        <thead class="table-dark">
                            <tr>
                                <th>Data</th>
                                <th>Cliente</th>
                                <th>Tipo</th>
                                <th>Forma Pagamento</th>
                                <th>Valor Base</th>
                                <th>%</th>
                                <th>Comissão</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($dados_comissao as $item): ?>
                            <tr>
                                <td><?= date('d/m/Y', strtotime($item['data_referencia'])) ?></td>
                                <td><?= htmlspecialchars($item['cliente']) ?></td>
                                <td>
                                    <?php
                                    switch ($item['tipo_comissao']) {
                                        case 'venda_avista': 
                                            echo '<span class="badge bg-primary">Venda à Vista</span>'; 
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
                                <td><?= formatarMoeda($item['valor_base_comissao']) ?></td>
                                <td><?= number_format($item['percentual_comissao'], 1) ?>%</td>
                                <td class="fw-bold text-success"><?= formatarMoeda($item['comissao_calculada']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot class="table-secondary">
                            <tr>
                                <th colspan="6" class="text-end">TOTAL DA COMISSÃO:</th>
                                <th class="text-success"><?= formatarMoeda($total_comissao) ?></th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
        
        <!-- Informações Adicionais -->
        <div class="card mt-4">
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <h6 class="text-primary">📄 Sobre o Recibo</h6>
                        <ul class="list-unstyled small">
                            <li>✅ Layout compacto e quadrado</li>
                            <li>✅ Todas as informações obrigatórias</li>
                            <li>✅ Formatação profissional para impressão</li>
                            <li>✅ Campos para preenchimento manual</li>
                        </ul>
                    </div>
                    <div class="col-md-6">
                        <h6 class="text-info">📋 Sobre o Detalhamento</h6>
                        <ul class="list-unstyled small">
                            <li>✅ Lista completa das comissões</li>
                            <li>✅ Detalhes de cada transação</li>
                            <li>✅ Para controle interno e auditoria</li>
                            <li>✅ Anexar junto com o recibo</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
function imprimirRecibo() {
    const url = new URL(window.location.href);
    url.searchParams.set('print', '1');
    window.open(url.toString(), '_blank', 'width=800,height=600');
}
</script>

<?php require_once '../views/footer.php'; ?>