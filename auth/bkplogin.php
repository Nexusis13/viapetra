<?php
include '../config/config.php';
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login = $_POST['login'];
    $senha = $_POST['senha'];

    // Verifica se o usuário existe e está ativo
    $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE login = ? AND ativo = 1");
    $stmt->execute([$login]);
    $usuario = $stmt->fetch();

    if ($usuario && password_verify($senha, $usuario['senha'])) {
        $_SESSION['usuario_id'] = $usuario['id'];
        $_SESSION['usuario_nome'] = $usuario['nome'];
        $_SESSION['usuario_tipo'] = $usuario['tipo'];
        header("Location: ../pages/dashboard.php");
        exit;
    } else {
        $erro = "Login inválido ou usuário inativo.";
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8">
  <title>Login</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="container py-5">
  <h2>Login</h2>

  <?php if (!empty($erro)): ?>
    <div class="alert alert-danger"><?= $erro ?></div>
  <?php endif; ?>

  <form method="post" class="w-50">
    <div class="mb-3">
      <label class="form-label">Login</label>
      <input type="text" name="login" class="form-control" required>
    </div>
    <div class="mb-3">
      <label class="form-label">Senha</label>
      <input type="password" name="senha" class="form-control" required>
    </div>
    <button type="submit" class="btn btn-primary">Entrar</button>
  </form>
</body>
</html>
