<?php
/**
 * SiteCatalogo2 - Área do Cliente
 * Login
 */
require_once __DIR__ . '/includes/auth.php';

// Já logado? manda para pedidos
if (cliente_logado()) {
    header('Location: pedidos.php'); exit;
}

$page_title = 'Entrar';
$hide_sidebar = true;
$cliente_logado_atual = null;

$redirect = $_GET['redirect'] ?? 'pedidos.php';
// Evita open-redirect: aceita apenas caminhos internos relativos
if (!preg_match('#^[a-zA-Z0-9_\-/?=&]+$#', $redirect) || strpos($redirect, '//') === 0) {
    $redirect = 'pedidos.php';
}

$erro = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $identificador = trim($_POST['identificador'] ?? '');
    $senha = (string)($_POST['senha'] ?? '');

    if ($identificador === '' || $senha === '') {
        $erro = 'Preencha e-mail/CPF e senha.';
    } else {
        $cliente = tentar_login_cliente($identificador, $senha);
        if ($cliente) {
            iniciar_sessao_cliente($cliente);
            header('Location: ' . $redirect);
            exit;
        } else {
            $erro = 'Dados de acesso inválidos. Verifique e tente novamente.';
        }
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="auth-wrapper">
    <div class="auth-box">
        <div class="auth-logo">
            <div class="icon">
                <?php if ($logo_cliente): ?>
                <img src="<?php echo uploads_url($logo_cliente); ?>" alt="<?php echo sanitize($site_name); ?>">
                <?php else: ?>
                <i class="fas fa-store"></i>
                <?php endif; ?>
            </div>
            <div class="name"><?php echo sanitize($site_name); ?></div>
        </div>

        <div class="auth-title">Acesse sua conta</div>
        <div class="auth-subtitle">Acompanhe seus pedidos e orçamentos</div>

        <?php if ($erro): ?>
        <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?php echo sanitize($erro); ?></div>
        <?php endif; ?>

        <?php if (!empty($flash) && isset($flash['type']) && $flash['type'] === 'success'): ?>
        <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo sanitize($flash['message']); ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label>E-mail ou CPF/CNPJ</label>
                <input type="text" name="identificador" value="<?php echo sanitize($_POST['identificador'] ?? ''); ?>" placeholder="seuemail@exemplo.com" required autofocus>
            </div>
            <div class="form-group">
                <label>Senha</label>
                <input type="password" name="senha" placeholder="••••••••" required>
            </div>

            <button type="submit" class="btn btn-primary btn-lg btn-block">
                <i class="fas fa-sign-in-alt"></i> Entrar
            </button>
        </form>

        <div class="auth-footer">
            Ainda não tem conta? <a href="cadastro.php">Cadastre-se</a>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>