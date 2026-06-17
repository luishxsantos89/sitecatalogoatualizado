<?php
/**
 * SiteCatalogo2 - Login do Painel Admin
 */
require_once __DIR__ . '/../includes/functions.php';
session_check();

if (is_logged_in()) {
    header('Location: ./');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login = trim($_POST['login'] ?? '');
    $senha = $_POST['senha'] ?? '';

    if (empty($login) || empty($senha)) {
        $error = 'Preencha todos os campos.';
    } else {
        try {
            $stmt = db()->prepare("SELECT * FROM " . table('usuarios') . " WHERE email = ? AND status = 'ativo' LIMIT 1");
            $stmt->execute([$login]);
            $user = $stmt->fetch();

            $senha_ok = false;
            if ($user) {
                if (password_verify($senha, $user['senha'])) {
                    $senha_ok = true;
                } elseif ($user['senha'] === $senha) {
                    // Texto plano - migrar para hash
                    $senha_ok = true;
                    $novo_hash = password_hash($senha, PASSWORD_DEFAULT);
                    db()->prepare("UPDATE " . table('usuarios') . " SET senha = ? WHERE id = ?")->execute([$novo_hash, $user['id']]);
                }
            }

            if ($senha_ok) {
                $_SESSION['admin_id']    = $user['id'];
                $_SESSION['admin_nome']  = $user['nome'];
                $_SESSION['admin_login'] = $user['email'];
                $_SESSION['admin_email'] = $user['email'];
                $_SESSION['admin_nivel'] = $user['role'];
                $_SESSION['admin_avatar']= $user['avatar'];

                db()->prepare("UPDATE " . table('usuarios') . " SET ultimo_acesso = NOW() WHERE id = ?")->execute([$user['id']]);
                log_activity('login', 'auth', "Usuário {$user['email']} fez login");
                header('Location: ./');
                exit;
            } else {
                $error = 'E-mail ou senha incorretos.';
            }
        } catch (Exception $e) {
            $error = 'Erro no sistema. Tente novamente.';
        }
    }
}

$site_name = get_config('site_nome', 'SiteCatalogo2');
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?php echo sanitize($site_name); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="assets/css/admin.css">
    
    <style>
        /* AJUSTE DO PLACEHOLDER APÓS O ÍCONE */
        .input-icon input {
            padding-left: 30px !important; /* Cria o espaço de 10px após o término do ícone */
        }
    </style>
</head>
<body class="login-page">
    <div class="login-wrapper">
        <div class="login-card">
            <div class="login-header">
                <svg width="48" height="48" viewBox="0 0 36 36" fill="none">
                    <rect width="36" height="36" rx="9" fill="#3b82f6"/>
                    <path d="M10 24V14l8-5 8 5v10H10z" stroke="white" stroke-width="2" fill="none"/>
                    <circle cx="18" cy="19" r="2.5" fill="white"/>
                </svg>
                <h1><?php echo sanitize($site_name); ?></h1>
                <p>Painel Administrativo</p>
            </div>

            <?php if ($error): ?>
            <div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> <?php echo sanitize($error); ?></div>
            <?php endif; ?>

            <form method="POST" class="login-form">
                <div class="form-group">
                    <label for="login">E-mail</label>
                    <div class="input-icon">
                        <i class="fas fa-envelope"></i>
                        <input type="email" id="login" name="login" required autofocus placeholder="email@dominio.com.br" value="<?php echo sanitize($_POST['login'] ?? ''); ?>">
                    </div>
                </div>
                <div class="form-group">
                    <label for="senha">Senha</label>
                    <div class="input-icon">
                        <i class="fas fa-lock"></i>
                        <input type="password" id="senha" name="senha" required placeholder="*********">
                    </div>
                </div>
                <button type="submit" class="btn btn-primary btn-block btn-lg" style="margin-top:8px;">
                    <i class="fas fa-sign-in-alt"></i> Entrar
                </button>
            </form>

            <div class="login-footer">
                <p style="font-size:0.8rem;color:#94a3b8;margin-top:16px;">
                    <!--Padrão: admin@sitecatalogo.com / password-->
                </p>
                <a href="../">&larr; Voltar para o site</a>
            </div>
        </div>
    </div>
</body>
</html>