<?php
/**
 * ARQUIVO: recibos_multiplos.php
 * Criar este arquivo para gerar múltiplos recibos em uma única página
 */

require_once '../config/protect.php';
require_once '../config/config.php';

// Capturar parâmetros
$data_inicio = $_GET['data_inicio'] ?? '';
$data_fim = $_GET['data_fim'] ?? '';
$todos_vendedores = $_GET['todos_vendedores'] ?? '';

// Buscar vendedores com comissão no período
$params = [];
$filtros = [];

if ($data_inicio) {
    $filtros[] = "DATE(v.dt_venda) >= ? OR DATE(b.dt_vencimento) >= ?";
    $params[] = $data_inicio;
    $params[] = $data_inicio;
}

if ($data_fim) {
    $filtros[] = "DATE(v.dt_venda) <= ? OR DATE(b.dt_vencimento) <= ?";
    $params[] = $data_fim;
    $params[] = $data_fim;
}

$where_clause = '';
if (!empty($filtros)) {
    $where_clause = 'WHERE ' . implode(' AND ', $filtros);
}

// Query para buscar vendedores com comissão
$sql = "
    SELECT DISTINCT
        vd.id_vendedor,
        vd.nome,
        vd.tipochave,
        vd.chave
    FROM vendedores vd
    WHERE EXISTS (
        SELECT 1 FROM vendas v 
        LEFT JOIN comissao c ON v.id_comissao = c.id_comissao
        WHERE v.id_vendedor = vd.id_vendedor 
        AND (
            (v.forma_pg IN ('PIX', 'Cartão Débito', 'Dinheiro', 'À Vista')) OR
            (v.forma_pg IN ('Parcelado', 'Permuta Parcelado', 'Cartão de Crédito') AND v.vlr_entrada > 0)
        )
        " . ($data_inicio ? "AND DATE(v.dt_venda) >= '$data_inicio'" : '') . "
        " . ($data_fim ? "AND DATE(v.dt_venda) <= '$data_fim'" : '') . "
        
        UNION
        
        SELECT 1 FROM boleto b
        INNER JOIN vendas v2 ON b.id_venda = v2.id_venda
        WHERE b.id_vendedor = vd.id_vendedor 
        AND b.status = 'Pago'
        " . ($data_inicio ? "AND DATE(b.dt_vencimento) >= '$data_inicio'" : '') . "
        " . ($data_fim ? "AND DATE(b.dt_vencimento) <= '$data_fim'" : '') . "
    )
    AND vd.STATUS = 1
    ORDER BY vd.nome
";

$stmt = $pdo->prepare($sql);
$stmt->execute();
$vendedores = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Função auxiliar para buscar comissão de um vendedor
function buscarComissaoVendedor($pdo, $id_vendedor, $data_inicio = '', $data_fim = '') {
    $params = [$id_vendedor];
    $filtros_vendas = ["v.id_vendedor = ?"];
    $filtros_boletos = ["b.id_vendedor = ?"];

    if ($data_inicio) {
        $filtros_vendas[] = "DATE(v.dt_venda) >= ?";
        $filtros_boletos[] = "DATE(b.dt_vencimento) >= ?";
        $params[] = $data_inicio;
        $params[] = $data_inicio;
    }

    if ($data_fim) {
        $filtros_vendas[] = "DATE(v.dt_venda) <= ?";
        $filtros_boletos[] = "DATE(b.dt_vencimento) <= ?";
        $params[] = $data_fim;
        $params[] = $data_fim;
    }

    $where_vendas = 'WHERE ' . implode(' AND ', $filtros_vendas);
    $where_boletos = 'WHERE ' . implode(' AND ', $filtros_boletos);

    // Query para somar comissões
    $sql = "
        SELECT SUM(comissao_calculada) as total FROM (
            SELECT 
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
            
            UNION ALL
            
            SELECT 
                (b.valor * COALESCE(c.valor, 0) / 100) as comissao_calculada
            FROM boleto b
            INNER JOIN vendas v ON b.id_venda = v.id_venda
            LEFT JOIN comissao c ON v.id_comissao = c.id_comissao
            $where_boletos
            AND b.status = 'Pago'
        ) as todas_comissoes
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
    
    return [
        'total' => $resultado['total'] ?: 0
    ];
}

// Função para gerar número do recibo
function gerarNumeroRecibo($id_vendedor, $mes, $ano) {
    return sprintf("%04d%02d%03d", $ano, $mes, $id_vendedor);
}

// Função para valor por extenso
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

// Função para formatar chave PIX
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
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recibos de Comissão - <?= date('m/Y') ?></title>
    <style>
        body {
            margin: 0;
            padding: 0;
            font-family: 'Courier New', monospace;
            font-size: 12px;
            line-height: 1.3;
            color: #000;
            background: white;
        }
        
        .recibo-container {
            width: 100%;
            max-width: 800px;
            margin: 0 auto 30px auto;
            background: white;
            padding: 20px;
            page-break-after: always;
            box-sizing: border-box;
        }
        
        .recibo-container:last-child {
            page-break-after: auto;
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
            padding: 8px;
            text-align: left;
        }
        
        .text-center { text-align: center; }
        .text-end { text-align: right; }
        .text-uppercase { text-transform: uppercase; }
        .mb-0 { margin-bottom: 0; }
        .mb-2 { margin-bottom: 8px; }
        .mb-3 { margin-bottom: 16px; }
        .mb-4 { margin-bottom: 24px; }
        .mt-3 { margin-top: 16px; }
        .mt-5 { margin-top: 32px; }
        .pb-1 { padding-bottom: 4px; }
        .pb-2 { padding-bottom: 8px; }
        .pt-1 { padding-top: 4px; }
        .pt-4 { padding-top: 24px; }
        .ps-2 { padding-left: 8px; }
        .p-2 { padding: 8px; }
        .p-3 { padding: 16px; }
        .d-flex { display: flex; }
        .justify-content-between { justify-content: space-between; }
        .align-items-start { align-items: flex-start; }
        
        .recibo-titulo h2 {
            font-family: 'Courier New', monospace;
            letter-spacing: 3px;
            font-size: 24px;
            font-weight: bold;
            display: inline-block;
            margin: 0;
            padding: 8px 16px;
        }
        
        .row {
            display: flex;
            flex-wrap: wrap;
            margin: -8px;
        }
        
        .col-6 {
            flex: 0 0 50%;
            padding: 8px;
        }
        
        .loading {
            text-align: center;
            padding: 50px;
            font-size: 18px;
        }
        
        .no-print {
            display: block;
        }
        
        @media print {
            .no-print {
                display: none !important;
            }
            
            body {
                margin: 0;
                padding: 0;
            }
            
            .recibo-container {
                margin: 0;
                padding: 15px;
                page-break-after: always;
            }
            
            .recibo-container:last-child {
                page-break-after: auto;
            }
        }
        
        @media screen {
            body {
                background: #f5f5f5;
                padding: 20px;
            }
            
            .recibo-container {
                border: 1px solid #ddd;
                box-shadow: 0 2px 4px rgba(0,0,0,0.1);
                margin-bottom: 30px;
            }
            
            .toolbar {
                position: fixed;
                top: 20px;
                right: 20px;
                z-index: 1000;
                background: white;
                padding: 15px;
                border-radius: 8px;
                box-shadow: 0 2px 8px rgba(0,0,0,0.2);
            }
        }
    </style>
</head>
<body>
    <!-- Barra de ferramentas (não aparece na impressão) -->
    <div class="toolbar no-print">
        <button onclick="window.print()" style="background: #28a745; color: white; border: none; padding: 10px 20px; border-radius: 4px; cursor: pointer; margin-bottom: 10px; width: 100%;">
            🖨️ Imprimir Todos
        </button>
        <button onclick="window.close()" style="background: #6c757d; color: white; border: none; padding: 10px 20px; border-radius: 4px; cursor: pointer; width: 100%;">
            ✖️ Fechar
        </button>
        <div style="margin-top: 10px; font-size: 12px; color: #666;">
            Total: <?= count($vendedores) ?> recibo(s)
        </div>
    </div>

    <?php if (empty($vendedores)): ?>
        <div class="loading">
            ⚠️ Nenhum vendedor com comissão encontrado no período selecionado.
        </div>
    <?php else: ?>
        <?php foreach ($vendedores as $vendedor): ?>
            <?php
            // Buscar dados de comissão para este vendedor específico
            $comissao_data = buscarComissaoVendedor($pdo, $vendedor['id_vendedor'], $data_inicio, $data_fim);
            $total_comissao = $comissao_data['total'];
            
            if ($total_comissao <= 0) continue; // Pular vendedores sem comissão
            
            $numero_recibo = gerarNumeroRecibo($vendedor['id_vendedor'], date('m'), date('Y'));
            $data_atual = date('d \d\e F \d\e Y');
            $periodo_inicio = $data_inicio ?: date('Y-m-01');
            $mes_ano = date('m/Y', strtotime($periodo_inicio));
            ?>
            
            <div class="recibo-container">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <div class="recibo-titulo">
                        <h2 class="border">R E C I B O</h2>
                    </div>
                    <div class="text-end">
                        <div class="border p-2" style="min-width: 120px; display: inline-block; margin-right: 10px;">
                            <strong>Nº</strong><br>
                            <span style="font-size: 14px;"><?= $numero_recibo ?></span>
                        </div>
                        <div class="border p-2" style="min-width: 150px; display: inline-block;">
                            <strong>VALOR R$</strong><br>
                            <span style="font-size: 16px; font-weight: bold;"><?= number_format($total_comissao, 2, ',', '.') ?></span>
                        </div>
                    </div>
                </div>

                <div class="border p-3">
                    <!-- Dados do Pagador -->
                    <div class="mb-3">
                        <div class="border-bottom pb-2 mb-2">
                            <strong>RECEBI (EMOS) DE:</strong>
                        </div>
                        <div class="ps-2">
                            VIA PETRA MÁRMORES E GRANITOS LTDA.
                        </div>
                    </div>

                    <!-- Endereço -->
                    <div class="mb-3">
                        <div class="border-bottom pb-2 mb-2">
                            <strong>ENDEREÇO:</strong>
                        </div>
                        <div class="ps-2">
                            RUA DO PORTO 04, QD. 06, LT. 01, RESIDENCIAL PORTO SEGURO, RIO VERDE/GO.
                        </div>
                    </div>

                    <!-- Valor por extenso -->
                    <div class="mb-3">
                        <div class="border-bottom pb-2 mb-2">
                            <strong>A IMPORTÂNCIA DE:</strong>
                        </div>
                        <div class="ps-2 text-uppercase">
                            <?= valorPorExtenso($total_comissao) ?>
                        </div>
                    </div>

                    <!-- Referente a -->
                    <div class="mb-4">
                        <div class="border-bottom pb-2 mb-2">
                            <strong>REFERENTE A:</strong>
                        </div>
                        <div class="ps-2">
                            PAGAMENTO PROPORCIONALIDADE COMISSÃO <?= strtoupper($mes_ano) ?>
                        </div>
                    </div>

                    <!-- Dados bancários -->
                    <div class="mb-4">
                        <table class="table-bordered mb-0" style="width: 60%;">
                            <tr>
                                <td style="width: 40%;"><strong>CHEQUE Nº</strong></td>
                                <td style="width: 30%; text-align: center;"><strong>BANCO</strong></td>
                                <td style="width: 30%; text-align: center;"><strong>AGÊNCIA</strong></td>
                            </tr>
                            <tr>
                                <td style="height: 40px;"></td>
                                <td></td>
                                <td></td>
                            </tr>
                        </table>
                    </div>

                    <!-- Dados do recebedor e data -->
                    <div class="row">
                        <div class="col-6">
                            <div class="border p-3">
                                <div class="mb-2">
                                    <strong>NOME EMITENTE:</strong><br>
                                    <span style="font-size: 16px;"><?= strtoupper($vendedor['nome']) ?></span>
                                </div>
                                
                                <div class="row mt-3">
                                    <div class="col-6">
                                        <div class="border-bottom pb-1">
                                            <small><strong><?= strtoupper($vendedor['tipochave']) ?> Nº</strong></small>
                                        </div>
                                        <div class="pt-1">
                                            <span style="font-size: 14px;"><?= formatarChavePix($vendedor['tipochave'], $vendedor['chave']) ?></span>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="border-bottom pb-1">
                                            <small><strong>RG Nº E ÓRGÃO EMISSOR</strong></small>
                                        </div>
                                        <div class="pt-1" style="height: 25px;">
                                            <!-- Campo vazio para preenchimento manual -->
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="mt-3">
                                    <div class="border-bottom pb-1">
                                        <small><strong>END.:</strong></small>
                                    </div>
                                    <div class="pt-1" style="height: 25px;">
                                        <!-- Campo vazio para preenchimento manual -->
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-6">
                            <div class="border p-3 text-center">
                                <div class="mb-3">
                                    <strong>LOCAL / DATA</strong>
                                </div>
                                <div style="font-size: 16px; margin-top: 20px;">
                                    Rio Verde/GO, <?= $data_atual ?>
                                </div>
                                
                                <div class="mt-5 pt-4" style="border-top: 1px solid #000; margin-top: 60px;">
                                    <small>ASSINATURA</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

    <script>
        // Auto-imprimir ao carregar (opcional)
        // window.onload = function() {
        //     setTimeout(() => window.print(), 1000);
        // };
        
        // Fechar janela após imprimir
        window.onafterprint = function() {
            if (confirm('Impressão concluída. Deseja fechar esta janela?')) {
                window.close();
            }
        };
    </script>
</body>
</html>