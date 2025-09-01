<?php
// $host = '131.221.77.104';
$host = 'localhost';
$db = 'nexusis_viapetr';
$user = 'nexusis_via';
$pass = '036xS4XSLV8o';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Erro na conexão: " . $e->getMessage());
}
?>