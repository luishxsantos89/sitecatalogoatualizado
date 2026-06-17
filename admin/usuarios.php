<?php
require_once __DIR__ . '/includes/functions.php';
$page_title = 'Usuários';
if (!check_permission('admin')) { set_flash('error','Acesso negado'); header('Location: ./'); exit; }

$action = $_GET['action'] ?? 'list';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome  = trim($_POST['nome'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $role  = $_POST['role'] ?? 'vendedor';
    $status= $_POST['status'] ?? 'ativo';
    $senha = $_POST['senha'] ?? '';

    if (empty($nome)||empty($email)) { set_flash('error','Nome e e-mail são obrigatórios'); }
    else {
        try {
            $dados = ['nome'=>$nome,'email'=>$email,'role'=>$role,'status'=>$status];
            if (!empty($senha)) $dados['senha'] = password_hash($senha, PASSWORD_DEFAULT);

            if (!empty($_FILES['avatar']['name'])) {
                $up = handle_upload(['name'=>$_FILES['avatar']['name'],'tmp_name'=>$_FILES['avatar']['tmp_name'],'error'=>$_FILES['avatar']['error']], 'avatars');
                if ($up) $dados['avatar'] = $up;
            }

            if ($id) {
                $f=[]; $v=[];
                foreach ($dados as $k=>$val) { $f[]="{$k}=?"; $v[]=$val; }
                $v[]=$id;
                db()->prepare("UPDATE ".table('usuarios')." SET ".implode(',',$f)." WHERE id=?")->execute($v);
                set_flash('success','Usuário atualizado!');
            } else {
                if (empty($senha)) { set_flash('error','Senha é obrigatória para novo usuário'); header('Location: usuarios.php?action=new'); exit; }
                $cols=implode(',',array_keys($dados));
                $ph=implode(',',array_fill(0,count($dados),'?'));
                db()->prepare("INSERT INTO ".table('usuarios')." ({$cols}) VALUES ({$ph})")->execute(array_values($dados));
                set_flash('success','Usuário criado!');
            }
            header('Location: usuarios.php'); exit;
        } catch(Exception $e) { set_flash('error','Erro: '.$e->getMessage()); }
    }
}

if ($action === 'delete' && $id && $id !== ($_SESSION['admin_id']??0)) {
    db()->prepare("DELETE FROM ".table('usuarios')." WHERE id=?")->execute([$id]);
    set_flash('success','Usuário excluído!');
    header('Location: usuarios.php'); exit;
}

$usuario = null;
if (($action==='edit')&&$id) { $s=db()->prepare("SELECT * FROM ".table('usuarios')." WHERE id=?"); $s->execute([$id]); $usuario=$s->fetch(); }

$usuarios = db()->query("SELECT * FROM ".table('usuarios')." ORDER BY nome")->fetchAll();

require_once __DIR__ . '/includes/header.php';
?>
<div class="page-header">
    <h1><i class="fas fa-user-shield"></i> Usuários</h1>
    <?php if ($action !== 'new' && $action !== 'edit'): ?>
    <a href="usuarios.php?action=new" class="btn btn-primary"><i class="fas fa-plus"></i> Novo Usuário</a>
    <?php endif; ?>
</div>

<?php if ($action === 'new' || $action === 'edit'): ?>
<div class="card" style="max-width:600px;">
    <div class="card-header"><h3><i class="fas fa-user"></i> <?php echo $usuario?'Editar':'Novo'; ?> Usuário</h3></div>
    <div class="card-body">
        <form method="POST" action="usuarios.php<?php echo $id?"?action=edit&id={$id}":"?action=new"; ?>" enctype="multipart/form-data">
            <div class="form-group"><label>Nome *</label><input type="text" name="nome" value="<?php echo sanitize($usuario['nome']??''); ?>" required></div>
            <div class="form-group"><label>E-mail *</label><input type="email" name="email" value="<?php echo sanitize($usuario['email']??''); ?>" required></div>
            <div class="form-group"><label>Senha <?php echo $usuario?'(deixe em branco para não alterar)':'*'; ?></label><input type="password" name="senha"></div>
            <div class="form-row form-row-2">
                <div class="form-group">
                    <label>Nível</label>
                    <select name="role">
                        <option value="vendedor" <?php echo selected($usuario['role']??'vendedor','vendedor'); ?>>Vendedor</option>
                        <option value="atendente" <?php echo selected($usuario['role']??'','atendente'); ?>>Atendente</option>
                        <option value="gerente" <?php echo selected($usuario['role']??'','gerente'); ?>>Gerente</option>
                        <option value="admin" <?php echo selected($usuario['role']??'','admin'); ?>>Administrador</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Status</label>
                    <select name="status">
                        <option value="ativo" <?php echo selected($usuario['status']??'ativo','ativo'); ?>>Ativo</option>
                        <option value="inativo" <?php echo selected($usuario['status']??'','inativo'); ?>>Inativo</option>
                    </select>
                </div>
            </div>
            <div class="form-group">
                <label>Avatar</label>
                <?php if (!empty($usuario['avatar'])): ?><img src="<?php echo uploads_url($usuario['avatar']); ?>" style="width:60px;height:60px;border-radius:50%;object-fit:cover;display:block;margin-bottom:8px;"><?php endif; ?>
                <input type="file" name="avatar" accept="image/*">
            </div>
            <div class="form-actions">
                <button type="submit" class="btn btn-primary btn-lg"><i class="fas fa-save"></i> Salvar</button>
                <a href="usuarios.php" class="btn btn-secondary btn-lg">Cancelar</a>
            </div>
        </form>
    </div>
</div>
<?php else: ?>
<!-- Tabela de Permissões por Cargo -->
<div class="card" style="margin-bottom:20px;">
    <div class="card-header"><h3><i class="fas fa-info-circle" style="color:var(--primary)"></i> Permissões por Cargo</h3></div>
    <div class="table-responsive">
        <table class="table" style="font-size:0.85rem;">
            <thead><tr>
                <th>Funcionalidade</th>
                <th style="text-align:center;">Vendedor</th>
                <th style="text-align:center;">Atendente</th>
                <th style="text-align:center;">Gerente</th>
                <th style="text-align:center;">Administrador</th>
            </tr></thead>
            <tbody>
                <?php
                $perms = [
                    ['Acessar o sistema (login)', true, true, true, true],
                    ['Ver catálogo / produtos', true, true, true, true],
                    ['Gerenciar produtos / categorias', false, true, true, true],
                    ['Ver orçamentos', true, true, true, true],
                    ['Criar / editar orçamentos', true, true, true, true],
                    ['Aprovar orçamentos', false, false, true, true],
                    ['Excluir orçamentos', false, false, true, true],
                    ['Alterar atendente do orçamento', false, false, false, true],
                    ['Gerenciar clientes', false, true, true, true],
                    ['Ver estoque', false, true, true, true],
                    ['Movimentar estoque', false, false, true, true],
                    ['Acesso ao Financeiro', false, false, true, true],
                    ['Gerenciar Contas Bancárias', false, false, true, true],
                    ['Relatórios Financeiros', false, false, true, true],
                    ['Configurações do sistema', false, false, false, true],
                    ['Gerenciar usuários', false, false, false, true],
                    ['Banners / SEO', false, false, true, true],
                ];
                foreach ($perms as $row):
                    $label = array_shift($row);
                ?>
                <tr>
                    <td><?php echo $label; ?></td>
                    <?php foreach ($row as $ok): ?>
                    <td style="text-align:center;">
                        <?php if ($ok): ?>
                        <i class="fas fa-check-circle" style="color:#22c55e;font-size:1rem;"></i>
                        <?php else: ?>
                        <i class="fas fa-times-circle" style="color:#ef4444;font-size:1rem;"></i>
                        <?php endif; ?>
                    </td>
                    <?php endforeach; ?>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table">
            <thead><tr><th>Avatar</th><th>Nome</th><th>E-mail</th><th>Nível</th><th>Status</th><th>Último Acesso</th><th>Ações</th></tr></thead>
            <tbody>
                <?php foreach ($usuarios as $u): ?>
                <tr>
                    <td><div class="user-avatar" style="width:40px;height:40px;"><?php if($u['avatar']): ?><img src="<?php echo uploads_url($u['avatar']); ?>"><?php else: ?><i class="fas fa-user"></i><?php endif; ?></div></td>
                    <td><strong><?php echo sanitize($u['nome']); ?></strong></td>
                    <td><?php echo sanitize($u['email']); ?></td>
                    <td><?php echo ucfirst($u['role']); ?></td>
                    <td><span class="badge-status status-<?php echo $u['status']; ?>"><?php echo ucfirst($u['status']); ?></span></td>
                    <td><?php echo $u['ultimo_acesso']?format_date($u['ultimo_acesso'],'d/m/Y H:i'):'-'; ?></td>
                    <td>
                        <a href="usuarios.php?action=edit&id=<?php echo $u['id']; ?>" class="btn btn-sm btn-primary"><i class="fas fa-edit"></i></a>
                        <?php if ($u['id'] !== ($_SESSION['admin_id']??0)): ?>
                        <a href="usuarios.php?action=delete&id=<?php echo $u['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Excluir usuário?')"><i class="fas fa-trash"></i></a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
