<?php
/**
 * SiteCatalogo2 - Banners
 */
require_once __DIR__ . '/includes/functions.php';
$page_title = 'Banners';

$action = $_GET['action'] ?? 'list';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $dados = [
        'titulo'    => trim($_POST['titulo'] ?? ''),
        'subtitulo' => trim($_POST['subtitulo'] ?? ''),
        'link'      => trim($_POST['link'] ?? ''),
        'posicao'   => $_POST['posicao'] ?? 'home_topo',
        'ordem'     => (int)($_POST['ordem'] ?? 0),
        'ativo'     => isset($_POST['ativo']) ? 1 : 0,
    ];

    if (!empty($_FILES['imagem']['name'])) {
        $up = handle_upload($_FILES['imagem'], 'banners');
        if ($up) {
            if ($id && !empty($_POST['imagem_atual'])) delete_upload($_POST['imagem_atual']);
            $dados['imagem'] = $up;
        }
    } elseif (!$id) {
        set_flash('error', 'Imagem é obrigatória para novos banners');
        header('Location: banners.php'); exit;
    }

    try {
        if ($id) {
            $f = []; $v = [];
            foreach ($dados as $k => $val) { $f[] = "{$k} = ?"; $v[] = $val; }
            $v[] = $id;
            db()->prepare("UPDATE " . table('banners') . " SET " . implode(', ', $f) . " WHERE id = ?")->execute($v);
            set_flash('success', 'Banner atualizado!');
        } else {
            $cols = implode(', ', array_keys($dados));
            $ph = implode(', ', array_fill(0, count($dados), '?'));
            db()->prepare("INSERT INTO " . table('banners') . " ({$cols}) VALUES ({$ph})")->execute(array_values($dados));
            set_flash('success', 'Banner criado!');
        }
        header('Location: banners.php'); exit;
    } catch (Exception $e) { set_flash('error', 'Erro: ' . $e->getMessage()); }
}

if ($action === 'delete' && $id) {
    $b = db()->prepare("SELECT imagem FROM " . table('banners') . " WHERE id = ?"); $b->execute([$id]); $ban = $b->fetch();
    if ($ban && $ban['imagem']) delete_upload($ban['imagem']);
    db()->prepare("DELETE FROM " . table('banners') . " WHERE id = ?")->execute([$id]);
    set_flash('success', 'Banner excluído!');
    header('Location: banners.php'); exit;
}

$banner = null;
if ($action === 'edit' && $id) {
    $stmt = db()->prepare("SELECT * FROM " . table('banners') . " WHERE id = ?"); $stmt->execute([$id]); $banner = $stmt->fetch();
}

$banners = db()->query("SELECT * FROM " . table('banners') . " ORDER BY posicao, ordem")->fetchAll();

require_once __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <h1><i class="fas fa-image"></i> Banners</h1>
    <button onclick="document.getElementById('modal').style.display='flex'" class="btn btn-primary"><i class="fas fa-plus"></i> Novo Banner</button>
</div>

<div id="modal" style="display:<?php echo ($action==='edit'||$action==='new')?'flex':'none'; ?>;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:1000;align-items:center;justify-content:center;">
    <div style="background:#fff;border-radius:12px;padding:32px;width:100%;max-width:550px;max-height:90vh;overflow-y:auto;">
        <h3 style="margin-bottom:20px;font-size:1.1rem;font-weight:700;"><i class="fas fa-image" style="color:var(--primary);"></i> <?php echo $banner?'Editar':'Novo'; ?> Banner</h3>
        <form method="POST" action="banners.php<?php echo $id?"?action=edit&id={$id}":"?action=new"; ?>" enctype="multipart/form-data">
            <input type="hidden" name="imagem_atual" value="<?php echo sanitize($banner['imagem']??''); ?>">
            <?php if (!empty($banner['imagem'])): ?>
            <img src="<?php echo uploads_url($banner['imagem']); ?>" style="width:100%;max-height:150px;object-fit:contain;border-radius:8px;margin-bottom:12px;">
            <?php endif; ?>
            <div class="form-group">
                <label>Imagem <?php echo !$banner?'*':''; ?></label>
                <input type="file" name="imagem" accept="image/*" <?php echo !$banner?'required':''; ?>>
                <small class="text-muted">Recomendado: 1200x400px</small>
            </div>
            <div class="form-group"><label>Título</label><input type="text" name="titulo" value="<?php echo sanitize($banner['titulo']??''); ?>"></div>
            <div class="form-group"><label>Subtítulo</label><input type="text" name="subtitulo" value="<?php echo sanitize($banner['subtitulo']??''); ?>"></div>
            <div class="form-group"><label>Link (URL)</label><input type="text" name="link" value="<?php echo sanitize($banner['link']??''); ?>" placeholder="https://..."></div>
            <div class="form-row form-row-3">
                <div class="form-group">
                    <label>Posição</label>
                    <select name="posicao">
                        <option value="home_topo" <?php echo selected($banner['posicao']??'home_topo','home_topo'); ?>>Home Topo</option>
                        <option value="home_meio" <?php echo selected($banner['posicao']??'','home_meio'); ?>>Home Meio</option>
                        <option value="sidebar" <?php echo selected($banner['posicao']??'','sidebar'); ?>>Sidebar</option>
                    </select>
                </div>
                <div class="form-group"><label>Ordem</label><input type="number" name="ordem" value="<?php echo (int)($banner['ordem']??0); ?>" min="0"></div>
                <div class="form-group" style="display:flex;align-items:flex-end;">
                    <div class="form-check"><input type="checkbox" name="ativo" id="ativo_ban" <?php echo checked($banner['ativo']??1); ?>><label for="ativo_ban">Ativo</label></div>
                </div>
            </div>
            <div style="display:flex;gap:10px;">
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Salvar</button>
                <button type="button" onclick="document.getElementById('modal').style.display='none'" class="btn btn-secondary">Cancelar</button>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table">
            <thead><tr><th>Imagem</th><th>Título</th><th>Posição</th><th>Ordem</th><th>Status</th><th>Ações</th></tr></thead>
            <tbody>
                <?php if (empty($banners)): ?>
                <tr><td colspan="6"><div class="empty-state-sm">Nenhum banner cadastrado</div></td></tr>
                <?php else: ?>
                <?php foreach ($banners as $b): ?>
                <tr>
                    <td><img src="<?php echo uploads_url($b['imagem']); ?>" style="width:120px;height:48px;object-fit:cover;border-radius:6px;"></td>
                    <td>
                        <strong><?php echo sanitize($b['titulo']??'-'); ?></strong>
                        <?php if ($b['link']): ?><br><small><a href="<?php echo sanitize($b['link']); ?>" target="_blank" style="color:var(--primary);"><?php echo sanitize($b['link']); ?></a></small><?php endif; ?>
                    </td>
                    <td><?php echo ucfirst(str_replace('_',' ',$b['posicao'])); ?></td>
                    <td><?php echo $b['ordem']; ?></td>
                    <td><span class="badge-status <?php echo $b['ativo']?'status-aprovado':'status-inativo'; ?>"><?php echo $b['ativo']?'Ativo':'Inativo'; ?></span></td>
                    <td>
                        <a href="banners.php?action=edit&id=<?php echo $b['id']; ?>" class="btn btn-sm btn-primary"><i class="fas fa-edit"></i></a>
                        <a href="banners.php?action=delete&id=<?php echo $b['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Excluir banner?')"><i class="fas fa-trash"></i></a>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
