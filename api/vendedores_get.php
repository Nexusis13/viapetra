<?php
// Buscar vendedores ativos para o select
$stmtVendedores = $pdo->prepare("SELECT id_vendedor, nome FROM vendedores WHERE STATUS = 1 ORDER BY nome");
$stmtVendedores->execute();
$vendedores = $stmtVendedores->fetchAll();
