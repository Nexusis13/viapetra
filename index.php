<?php
session_start();
if (isset($_SESSION['logado']) && $_SESSION['logado'] === true) {
    header('Location: pages/dashboard.php');
    exit;
}
header('Location: auth/login.php');
exit;
