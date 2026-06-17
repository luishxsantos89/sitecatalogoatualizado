<?php
require_once __DIR__ . '/includes/functions.php';
$page_title = 'Email';

$action = $_GET['action'] ?? 'list';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$pasta = $_GET['pasta'] ?? 'inbox';

// Marcar como lido
if ($action === 'lido' && $id) {
    db()->prepare("UPDATE " . table('emails') . " SET status = 'lido' WHERE id = ?")->execute([$id]);
    header('Location: email.php?action=ver&id=' . $id); exit;
}

// Deletar (mover para lixo)
if ($action === 'delete' && $id) {
    db()->prepare("UPDATE " . table('emails') . " SET pasta = 'trash' WHERE id = ?")->execute([$id]);
    set_flash('success', 'Email movido para a lixeira');
    header('Location: email.php?pasta=' . $pasta); exit;
}

// Criar email de exemplo
if ($action === 'demo') {
    db()->prepare("INSERT INTO " . table('emails') . " (remetente_nome,remetente_email,destinatario_email,assunto,corpo,pasta,status) VALUES (?,?,?,?,?,?,?)")
        ->execute(['Cliente Exemplo','cliente@exemplo.com',get_config('email_contato','admin@sitecatalogo.com'),'Solicitação de Orçamento','Olá, gostaria de solicitar um orçamento para produtos do catálogo. Aguardo retorno.','inbox','nao_lido']);
    set_flash('success', 'Email de demonstração criado!');
    header('Location: email.php'); exit;
}

// Ver email
$email = null;
if ($action === 'ver' && $id) {
    $s = db()->prepare("SELECT * FROM " . table('emails') . " WHERE id = ?"); $s->execute([$id]); $email = $s->fetch();
    if ($email && $email['status'] === 'nao_lido') {
        db()->prepare("UPDATE " . table('emails') . " SET status = 'lido' WHERE id = ?")->execute([$id]);
        $email['status'] = 'lido';
    }
}

$pasta_filtros = ['inbox','sent','drafts','archive','spam','trash'];
$count_inbox = (int)db()->query("SELECT COUNT(*) FROM " . table('emails') . " WHERE pasta='inbox' AND status='nao_lido'")->fetchColumn();

$emails = db()->prepare("SELECT * FROM " . table('emails') . " WHERE pasta = ? ORDER BY data_envio DESC LIMIT 50");
$emails->execute([$pasta]); $lista = $emails->fetchAll();

require_once __DIR__ . '/includes/header.php';
?>
<div class="page-header">
    <h1><i class="fas fa-envelope"></i> E-mails</h1>
    <div style="display:flex;gap:8px;">
        <a href="email.php?action=demo" class="btn btn-light btn-sm" onclick="return confirm('Criar email de demonstração?')"><i class="fas fa-flask"></i> Demo</a>
    </div>
</div>

<div style="display:grid;grid-template-columns:220px 1fr;gap:20px;">
    <!-- Sidebar pastas -->
    <div>
        <div class="card">
            <div class="card-body" style="padding:8px;">
                <?php foreach (['inbox'=>'Entrada','sent'=>'Enviados','drafts'=>'Rascunhos','archive'=>'Arquivo','spam'=>'Spam','trash'=>'Lixeira'] as $p=>$l): ?>
                <a href="email.php?pasta=<?php echo $p; ?>" class="nav-link <?php echo $pasta===$p?'active':''; ?>" style="background:none;color:<?php echo $pasta===$p?'var(--primary)':'var(--gray-700)'; ?>;">
                    <i class="fas fa-<?php echo ['inbox'=>'inbox','sent'=>'paper-plane','drafts'=>'file-alt','archive'=>'archive','spam'=>'ban','trash'=>'trash'][$p]; ?>"></i>
                    <span><?php echo $l; ?></span>
                    <?php if ($p==='inbox' && $count_inbox > 0): ?><span class="nav-badge"><?php echo $count_inbox; ?></span><?php endif; ?>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Lista / Detalhe -->
    <div>
        <?php if ($email): ?>
        <!-- Detalhe do email -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-envelope-open"></i> <?php echo sanitize($email['assunto']); ?></h3>
                <a href="email.php?pasta=<?php echo $pasta; ?>" class="btn btn-sm btn-secondary"><i class="fas fa-arrow-left"></i> Voltar</a>
            </div>
            <div class="card-body">
                <div style="margin-bottom:16px;padding-bottom:16px;border-bottom:1px solid var(--gray-200);">
                    <p><strong>De:</strong> <?php echo sanitize($email['remetente_nome']); ?> &lt;<?php echo sanitize($email['remetente_email']); ?>&gt;</p>
                    <p><strong>Para:</strong> <?php echo sanitize($email['destinatario_email']); ?></p>
                    <p><strong>Data:</strong> <?php echo format_date($email['data_envio'],'d/m/Y H:i'); ?></p>
                </div>
                <div style="white-space:pre-wrap;font-size:0.9375rem;line-height:1.7;color:var(--gray-700);">
                    <?php echo nl2br(sanitize($email['corpo'])); ?>
                </div>
                <div style="margin-top:20px;display:flex;gap:8px;">
                    <?php if (!empty($email['remetente_email'])): ?>
                    <a href="mailto:<?php echo sanitize($email['remetente_email']); ?>" class="btn btn-primary"><i class="fas fa-reply"></i> Responder por Email</a>
                    <?php endif; ?>
                    <a href="email.php?action=delete&id=<?php echo $email['id']; ?>&pasta=<?php echo $pasta; ?>" class="btn btn-danger" onclick="return confirm('Mover para lixeira?')"><i class="fas fa-trash"></i></a>
                </div>
            </div>
        </div>
        <?php else: ?>
        <!-- Lista de emails -->
        <div class="card">
            <div class="card-header"><h3>
                <?php echo ['inbox'=>'Caixa de Entrada','sent'=>'Enviados','drafts'=>'Rascunhos','archive'=>'Arquivo','spam'=>'Spam','trash'=>'Lixeira'][$pasta]??ucfirst($pasta); ?>
                <?php if (count($lista)): ?><span style="font-size:0.8rem;color:var(--gray-400);font-weight:400;margin-left:6px;">(<?php echo count($lista); ?>)</span><?php endif; ?>
            </h3></div>
            <?php if (empty($lista)): ?>
            <div class="empty-state" style="padding:40px;">
                <i class="fas fa-inbox" style="font-size:2.5rem;color:var(--gray-300);"></i>
                <p style="margin-top:12px;color:var(--gray-400);">Nenhum e-mail nesta pasta</p>
            </div>
            <?php else: ?>
            <div>
                <?php foreach ($lista as $e): ?>
                <a href="email.php?action=ver&id=<?php echo $e['id']; ?>&pasta=<?php echo $pasta; ?>"
                   style="display:flex;align-items:center;gap:12px;padding:14px 20px;border-bottom:1px solid var(--gray-100);text-decoration:none;background:<?php echo $e['status']==='nao_lido'?'#eff6ff':'#fff'; ?>;transition:background 0.2s;"
                   onmouseover="this.style.background='var(--gray-50)'" onmouseout="this.style.background='<?php echo $e['status']==='nao_lido'?'#eff6ff':'#fff'; ?>'">
                    <div style="width:36px;height:36px;border-radius:50%;background:var(--primary);display:flex;align-items:center;justify-content:center;color:#fff;font-weight:700;font-size:0.875rem;flex-shrink:0;">
                        <?php echo strtoupper(substr($e['remetente_nome']??'?', 0, 1)); ?>
                    </div>
                    <div style="flex:1;min-width:0;">
                        <div style="display:flex;justify-content:space-between;align-items:center;">
                            <strong style="color:<?php echo $e['status']==='nao_lido'?'var(--gray-900)':'var(--gray-600)'; ?>;font-size:0.875rem;"><?php echo sanitize($e['remetente_nome']??$e['remetente_email']); ?></strong>
                            <small style="color:var(--gray-400);white-space:nowrap;"><?php echo format_date($e['data_envio'],'d/m H:i'); ?></small>
                        </div>
                        <div style="font-size:0.875rem;color:var(--gray-700);font-weight:<?php echo $e['status']==='nao_lido'?'600':'400'; ?>;"><?php echo sanitize($e['assunto']); ?></div>
                        <div style="font-size:0.8rem;color:var(--gray-400);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?php echo sanitize(substr($e['corpo']??'',0,80)); ?>...</div>
                    </div>
                    <?php if ($e['status'] === 'nao_lido'): ?><div style="width:8px;height:8px;border-radius:50%;background:var(--primary);flex-shrink:0;"></div><?php endif; ?>
                </a>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<div class="card" style="margin-top:20px;">
    <div class="card-header"><h3><i class="fas fa-cog"></i> Configuração de Email</h3></div>
    <div class="card-body">
        <p class="text-muted" style="font-size:0.875rem;">Para receber e-mails reais, configure o formulário de contato do site para inserir mensagens nessa caixa de entrada via API ou use um serviço de integração de email (ex: Mailgun, SendGrid) com webhook.</p>
        <div style="display:flex;gap:8px;margin-top:12px;">
            <a href="configuracoes.php" class="btn btn-outline"><i class="fas fa-cog"></i> Configurações do Site</a>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
