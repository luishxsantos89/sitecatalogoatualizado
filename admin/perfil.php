<?php
require_once __DIR__ . '/includes/functions.php';

// === CONTROLE DE ACESSO ===
require_auth();
if (!check_permission('vendedor')) {
    header('Location: ' . admin_url());
    exit('Acesso negado.');
}

$page_title = 'Meu Perfil';

$usuario = db()->prepare("SELECT * FROM " . table('usuarios') . " WHERE id = ?");
$usuario->execute([$_SESSION['admin_id']]); $user = $usuario->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome  = trim($_POST['nome'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $senha_atual = $_POST['senha_atual'] ?? '';
    $nova_senha  = $_POST['nova_senha'] ?? '';

    if (!empty($nova_senha)) {
        if (!password_verify($senha_atual, $user['senha'])) {
            set_flash('error', 'Senha atual incorreta'); header('Location: perfil.php'); exit;
        }
        if (strlen($nova_senha) < 6) { set_flash('error', 'Nova senha deve ter pelo menos 6 caracteres'); header('Location: perfil.php'); exit; }
    }

    $dados = ['nome' => $nome, 'email' => $email];
    if (!empty($nova_senha)) $dados['senha'] = password_hash($nova_senha, PASSWORD_DEFAULT);

    if (!empty($_FILES['avatar']['name'])) {
        $up = handle_upload(['name'=>$_FILES['avatar']['name'],'tmp_name'=>$_FILES['avatar']['tmp_name'],'error'=>$_FILES['avatar']['error']], 'avatars');
        if ($up) { if ($user['avatar']) delete_upload($user['avatar']); $dados['avatar'] = $up; }
    }

    $f=[]; $v=[];
    foreach ($dados as $k=>$val) { $f[]="{$k}=?"; $v[]=$val; }
    $v[] = $_SESSION['admin_id'];
    db()->prepare("UPDATE ".table('usuarios')." SET ".implode(',',$f)." WHERE id=?")->execute($v);
    $_SESSION['admin_nome'] = $nome;
    if (!empty($dados['avatar'])) $_SESSION['admin_avatar'] = $dados['avatar'];
    set_flash('success', 'Perfil atualizado!');
    header('Location: perfil.php'); exit;
}

require_once __DIR__ . '/includes/header.php';
?>
<div class="page-header"><h1><i class="fas fa-user"></i> Meu Perfil</h1></div>
<div class="card" style="max-width:600px;">
    <div class="card-body">
        <form method="POST" enctype="multipart/form-data">
            <div style="text-align:center;margin-bottom:24px;">
                <div class="user-avatar" style="width:80px;height:80px;margin:0 auto 12px;font-size:2rem;">
                    <?php if ($user['avatar']): ?><img src="<?php echo uploads_url($user['avatar']); ?>"><?php else: ?><i class="fas fa-user"></i><?php endif; ?>
                </div>
                <input type="file" name="avatar" accept="image/*">
            </div>
            <div class="form-group"><label>Nome</label><input type="text" name="nome" value="<?php echo sanitize($user['nome']); ?>" required></div>
            <div class="form-group"><label>E-mail</label><input type="email" name="email" value="<?php echo sanitize($user['email']); ?>" required></div>
            <hr style="margin:20px 0;border:none;border-top:1px solid var(--gray-200);">
            <h4 style="margin-bottom:12px;font-size:0.9rem;color:var(--gray-600);">Alterar Senha (opcional)</h4>
            <div class="form-group"><label>Senha Atual</label><input type="password" name="senha_atual" placeholder="Somente se quiser alterar a senha"></div>
            <div class="form-group"><label>Nova Senha</label><input type="password" name="nova_senha" placeholder="Mínimo 6 caracteres"></div>
            <div class="form-actions">
                <button type="submit" class="btn btn-primary btn-lg"><i class="fas fa-save"></i> Salvar</button>
            </div>
        </form>
    </div>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>