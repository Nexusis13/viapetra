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
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sistema Nexus</title>
    <link rel="shortcut icon" href="../images/nexus.png" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        .login-page {
            background: linear-gradient(135deg, #D50022 0%, #F27798 100%);
            min-height: 100vh;
        }
        .login-card-body {
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
        }
        .login-logo img {
            max-width: 150px;
            height: auto;
        }
        .btn-primary {
            background: #D50022;
            border: none;
            transition: all 0.3s;
        }
        .btn-primary:hover {
            background: #F27798;
            transform: translateY(-2px);
        }
        .input-group-text {
            background: transparent;
            border-right: none;
        }
        .form-control {
            border-left: none;
        }
        .form-control:focus {
            box-shadow: none;
            border-color: #ddd;
        }
        .alert {
            border-radius: 8px;
            border: none;
        }
        .remember-me {
            color: #666;
        }
        /* Tornando a logo responsiva */
        .logo {
            max-width: 100%;
            height: auto;
            width: 150px;
        }

        /* Se você quiser um tamanho fixo em dispositivos maiores, pode adicionar uma media query */
        @media (min-width: 768px) {
            .logo {
                width: 200px;
            }
        }
    </style>
</head>
<body class="hold-transition login-page">
    <div class="login-box">
        <div class="card">
            <div class="card-header text-center p-4 bg-white" style="border-bottom: none;">
                <a href="../index.php" class="h1">
                    <img src="../images/logo-nexus.jpg" alt="Logo Nexus Sistemas" class="logo">
                </a>
            </div>
            <div class="card-body login-card-body p-4">
                <?php if (!empty($erro)): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <i class="fas fa-exclamation-circle"></i> <?= $erro ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <form method="POST" id="loginForm">
                    <div class="input-group mb-3">
                        <div class="input-group-text">
                            <span class="fas fa-user"></span>
                        </div>
                        <input type="text" class="form-control" name="login" placeholder="Usuário" required 
                               value="<?= htmlspecialchars($_POST['login'] ?? '') ?>">
                    </div>

                    <div class="input-group mb-3">
                        <div class="input-group-text">
                            <span class="fas fa-lock"></span>
                        </div>
                        <input type="password" class="form-control" name="senha" id="senha" placeholder="Senha" required>
                        <div class="input-group-text" style="border-left: none; cursor: pointer;" onclick="togglePassword()">
                            <span class="fas fa-eye" id="toggleIcon"></span>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-8">
                            <div class="icheck-primary">
                                <input type="checkbox" id="remember" name="remember">
                                <label for="remember" class="remember-me">
                                    Lembrar-me
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-12">
                            <button type="submit" class="btn btn-primary btn-block">
                                <i class="fas fa-sign-in-alt mr-2"></i> Entrar
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
        <div class="text-center mt-3" style="color: white;">
            &copy; <?= date('Y') ?> Nexus Sistemas - Todos os direitos reservados
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Função para alternar visibilidade da senha
        function togglePassword() {
            const senha = document.getElementById('senha');
            const icon = document.getElementById('toggleIcon');
            
            if (senha.type === 'password') {
                senha.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                senha.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }

        // Animação do botão de login
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            const btn = this.querySelector('button[type="submit"]');
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-circle-notch fa-spin mr-2"></i> Entrando...';
        });

        // Remove mensagens de erro após 5 segundos
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(function(alert) {
                $(alert).fadeOut();
            });
        }, 5000);
    </script>
</body>
</html>