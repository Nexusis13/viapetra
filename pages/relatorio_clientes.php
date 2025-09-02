<?php
require_once '../config/config.php';


// FORMATAR O DOCUMENTO(CPF/CNPJ)
if (!function_exists('formatarDocumento')) {
    function formatarDocumento($doc) {
        $doc = preg_replace('/\D/', '', $doc);
        if (strlen($doc) === 11) {
            // CPF: 000.000.000-00
            return preg_replace("/(\d{3})(\d{3})(\d{3})(\d{2})/", "$1.$2.$3-$4", $doc);
        } elseif (strlen($doc) === 14) {
            // CNPJ: 00.000.000/0000-00
            return preg_replace("/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/", "$1.$2.$3/$4-$5", $doc);
        }
        return htmlspecialchars($doc);
    }
}


$clientes = $pdo->query("SELECT client_id, tipo, documento, nome, dt_nascimento, telefone, endereco, end_obra, status FROM clientes ORDER BY nome ASC")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Lista de Clientes</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 10px;}
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #ccc; padding: 6px 8px; }
        th { background: #eee; }
    </style>
</head>
<body>
    <h2>Lista de Clientes</h2>
    <table>
        <thead>
            <tr>
                <th>CPF/CNPJ</th>
                <th>Nome</th>
                <th>Data de Nascimento</th>
                <th>Telefone</th>
                <th>Endereço</th>
                <th>Endereço da Obra</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($clientes as $c): ?>
            <tr>
                <td><?= formatarDocumento($c['documento']) ?></td>
                <td><?= htmlspecialchars($c['nome']) ?></td>
                <td><?= !empty($c['dt_nascimento']) ? date('d/m/Y', strtotime($c['dt_nascimento'])) : '' ?></td>
                <td><?= htmlspecialchars($c['telefone']) ?></td>
                <td><?= htmlspecialchars($c['endereco']) ?></td>
                <td><?= htmlspecialchars($c['end_obra']) ?></td>
                <td><?= $c['status'] ? 'Ativo' : 'Inativo' ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <script>
        window.onload = function() {
            window.print();
        }
    </script>
</body>
</html>