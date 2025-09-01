<?php
require_once '../config/protect.php';
include '../views/header.php';
?>
<div class="container">
  <div class="jumbotron">
    <h1>Bem-vindo, <?= $_SESSION['usuario_nome'] ?>!</h1>
    <p class="lead">Utilize o menu acima para navegar pelo sistema.</p>
  </div>
</div>
<?php include '../views/footer.php'; ?>
