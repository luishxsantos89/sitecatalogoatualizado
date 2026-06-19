<?php
/**
 * email.php — Gerenciador de Email Real (IMAP + SMTP)  v2.6
 * Sincroniza via IMAP, envia via SMTP/PHPMailer.
 */
require_once __DIR__ . '/includes/functions.php';

require_auth();
if (!check_permission('admin')) {
    header('Location: ' . admin_url());
    exit('Acesso negado.');
}

$page_title = 'Email';
$action     = $_GET['action'] ?? 'list';
$id         = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$pasta      = $_GET['pasta']   ?? 'inbox';

// ─────────────────────────────────────────────────────────────
// SMTP — lê configurações padronizadas do banco
// ─────────────────────────────────────────────────────────────
function smtp_config(): array {
    $user = get_config('smtp_user', '');
    return [
        'host'       => get_config('smtp_host', ''),
        'port'       => (int)(get_config('smtp_port', 587) ?: 587),
        'encryption' => get_config('smtp_encryption', 'tls'),
        'user'       => $user,
        'pass'       => get_config('smtp_pass', ''),
        'from'       => get_config('email_contato', $user),
        'from_name'  => get_config('site_nome_email', '') ?: get_config('site_nome', 'SiteCatalogo'),
    ];
}

function smtp_configurado(): bool {
    $c = smtp_config();
    return !empty($c['host']) && !empty($c['user']) && !empty($c['pass']);
}

// ─────────────────────────────────────────────────────────────
// IMAP — lê configurações do banco
// ─────────────────────────────────────────────────────────────
function imap_cfg(): array {
    return [
        'host'   => get_config('imap_host', ''),
        'port'   => (int)(get_config('imap_port', 993) ?: 993),
        'ssl'    => get_config('imap_ssl', '1') === '1',
        'user'   => get_config('imap_user', ''),
        'pass'   => get_config('imap_pass', ''),
        'folder' => get_config('imap_folder', 'INBOX'),
    ];
}

function imap_configurado(): bool {
    $c = imap_cfg();
    return !empty($c['host']) && !empty($c['user']) && !empty($c['pass']);
}

function imap_conectar(string $pasta_imap = 'INBOX') {
    $c = imap_cfg();
    if (!imap_configurado()) return null;
    $flags   = $c['ssl'] ? '/ssl/novalidate-cert' : '/notls';
    $mailbox = '{' . $c['host'] . ':' . $c['port'] . '/imap' . $flags . '}' . $pasta_imap;
    return @imap_open($mailbox, $c['user'], $c['pass'], 0, 1) ?: null;
}

function imap_pasta_servidor(string $pasta_local): string {
    $mapa = [
        'inbox'   => get_config('imap_folder',         'INBOX'),
        'sent'    => get_config('imap_folder_sent',    'Sent'),
        'drafts'  => get_config('imap_folder_drafts',  'Drafts'),
        'archive' => get_config('imap_folder_archive', 'Archive'),
        'spam'    => get_config('imap_folder_spam',    'Junk'),
        'trash'   => get_config('imap_folder_trash',   'Trash'),
    ];
    return $mapa[$pasta_local] ?? 'INBOX';
}

function imap_decodificar(string $str): string {
    $parts  = imap_mime_header_decode($str);
    $result = '';
    foreach ($parts as $p) {
        $charset = ($p->charset === 'default') ? 'UTF-8' : $p->charset;
        $result .= mb_convert_encoding($p->text, 'UTF-8', $charset);
    }
    return $result;
}

function imap_corpo($conn, int $uid): string {
    $struct = imap_fetchstructure($conn, $uid, FT_UID);
    $body   = '';

    if (!isset($struct->parts)) {
        // Mensagem simples (sem MIME multipart)
        $raw = imap_fetchbody($conn, $uid, '1', FT_UID);
        if ($struct->encoding == 3)      $raw = base64_decode($raw);
        elseif ($struct->encoding == 4)  $raw = quoted_printable_decode($raw);
        return mb_convert_encoding($raw, 'UTF-8', 'UTF-8');
    }

    // Multipart: prioriza text/plain, aceita text/html como fallback
    $html_body = '';
    foreach ($struct->parts as $i => $part) {
        $subtype = strtolower($part->subtype ?? '');
        if (!in_array($subtype, ['plain', 'html'])) continue;

        $raw = imap_fetchbody($conn, $uid, $i + 1, FT_UID);
        if ($part->encoding == 3)      $raw = base64_decode($raw);
        elseif ($part->encoding == 4)  $raw = quoted_printable_decode($raw);

        $charset = 'UTF-8';
        foreach ($part->parameters ?? [] as $p) {
            if (strtolower($p->attribute) === 'charset') { $charset = $p->value; break; }
        }
        $raw = mb_convert_encoding($raw, 'UTF-8', $charset);

        if ($subtype === 'plain') { $body = $raw; break; }
        if ($subtype === 'html' && empty($html_body)) {
            $html_body = strip_tags(str_replace(['<br>','<br/>','<br />','</p>','</div>'], "\n", $raw));
        }
    }
    return $body ?: $html_body ?: imap_fetchbody($conn, $uid, '1', FT_UID);
}

// ─────────────────────────────────────────────────────────────
// ENVIO SMTP
// ─────────────────────────────────────────────────────────────
function enviar_smtp(string $para, string $assunto, string $corpo, string $reply_to = ''): array {
    $c = smtp_config();
    if (!smtp_configurado()) {
        return ['ok' => false, 'erro' => 'SMTP não configurado. Acesse Configurações → Email.'];
    }

    $phpmailer_paths = [
        __DIR__ . '/../vendor/phpmailer/phpmailer/src/PHPMailer.php',
        __DIR__ . '/vendor/phpmailer/phpmailer/src/PHPMailer.php',
        __DIR__ . '/../PHPMailer/src/PHPMailer.php',
    ];

    foreach ($phpmailer_paths as $path) {
        if (file_exists($path)) {
            require_once $path;
            require_once dirname($path) . '/SMTP.php';
            require_once dirname($path) . '/Exception.php';
            try {
                $mail = new PHPMailer\PHPMailer\PHPMailer(true);
                $mail->isSMTP();
                $mail->Host       = $c['host'];
                $mail->SMTPAuth   = true;
                $mail->Username   = $c['user'];
                $mail->Password   = $c['pass'];
                $mail->SMTPSecure = $c['encryption'];
                $mail->Port       = $c['port'];
                $mail->CharSet    = 'UTF-8';
                $mail->setFrom($c['from'], $c['from_name']);
                $mail->addAddress($para);
                if ($reply_to) $mail->addReplyTo($reply_to);
                $mail->Subject = $assunto;
                $mail->Body    = $corpo;
                $mail->isHTML(false);
                $mail->send();
                return ['ok' => true];
            } catch (Exception $e) {
                return ['ok' => false, 'erro' => $mail->ErrorInfo];
            }
        }
    }

    // Fallback mail()
    $headers  = "From: =?UTF-8?B?" . base64_encode($c['from_name']) . "?= <{$c['from']}>\r\n";
    $headers .= "Reply-To: " . ($reply_to ?: $c['from']) . "\r\n";
    $headers .= "MIME-Version: 1.0\r\nContent-Type: text/plain; charset=UTF-8\r\n";
    $ok = @mail($para, '=?UTF-8?B?' . base64_encode($assunto) . '?=', $corpo, $headers);
    return $ok
        ? ['ok' => true]
        : ['ok' => false, 'erro' => 'Falha no mail() nativo. Instale o PHPMailer para mais controle.'];
}

function salvar_enviado_imap(string $para, string $assunto, string $corpo): void {
    if (!function_exists('imap_open') || !imap_configurado()) return;
    $c           = imap_cfg();
    $sent_folder = imap_pasta_servidor('sent');
    $flags_str   = $c['ssl'] ? '/ssl/novalidate-cert' : '/notls';
    $conn        = @imap_open(
        '{' . $c['host'] . ':' . $c['port'] . '/imap' . $flags_str . '}' . $sent_folder,
        $c['user'], $c['pass'], 0, 1
    );
    if (!$conn) return;
    $raw = "From: {$c['user']}\r\nTo: {$para}\r\nSubject: {$assunto}\r\nDate: " . date('r') . "\r\n\r\n{$corpo}";
    @imap_append($conn, '{' . $c['host'] . ':' . $c['port'] . '/imap' . $flags_str . '}' . $sent_folder, $raw, '\\Seen');
    imap_close($conn);
}

// ─────────────────────────────────────────────────────────────
// SINCRONIZAÇÃO IMAP → BD
// ─────────────────────────────────────────────────────────────
function sincronizar_imap(string $pasta_local = 'inbox'): array {
    if (!function_exists('imap_open')) {
        return ['ok' => false, 'erro' => 'Extensão IMAP não habilitada no PHP.'];
    }
    if (!imap_configurado()) {
        return ['ok' => false, 'erro' => 'IMAP não configurado. Acesse Configurações → Email.'];
    }

    $pasta_imap = imap_pasta_servidor($pasta_local);
    $conn       = imap_conectar($pasta_imap);
    if (!$conn) {
        return ['ok' => false, 'erro' => 'Não foi possível conectar ao servidor IMAP. Verifique as configurações.'];
    }

    $uids  = imap_search($conn, 'ALL', SE_UID) ?: [];
    $novos = 0;
    $total = count($uids);

    // Processa os 100 mais recentes
    foreach (array_slice(array_reverse($uids), 0, 100) as $uid) {
        $existe = db()->prepare("SELECT id FROM " . table('emails') . " WHERE imap_uid = ? AND pasta = ?");
        $existe->execute([$uid, $pasta_local]);
        if ($existe->fetchColumn()) continue;

        $ov = imap_fetch_overview($conn, $uid, FT_UID);
        if (empty($ov)) continue;
        $ov = $ov[0];

        $assunto    = imap_decodificar($ov->subject ?? '(sem assunto)');
        $remetente  = imap_decodificar($ov->from    ?? '');
        $data_envio = date('Y-m-d H:i:s', $ov->udate ?? time());
        $status     = isset($ov->seen) && $ov->seen ? 'lido' : 'nao_lido';
        $corpo      = imap_corpo($conn, $uid);

        // Extrai nome e email do remetente
        $rem_nome = $remetente; $rem_email = '';
        if (preg_match('/"?([^"<>]+)"?\s*<([^>]+)>/', $remetente, $m)) {
            $rem_nome  = trim($m[1]);
            $rem_email = trim($m[2]);
        } elseif (filter_var(trim($remetente), FILTER_VALIDATE_EMAIL)) {
            $rem_email = trim($remetente);
            $rem_nome  = '';
        }

        db()->prepare(
            "INSERT INTO " . table('emails') . "
             (imap_uid, remetente_nome, remetente_email, destinatario_email, assunto, corpo, pasta, status, data_envio)
             VALUES (?,?,?,?,?,?,?,?,?)"
        )->execute([
            $uid, $rem_nome, $rem_email,
            get_config('imap_user', ''),
            $assunto, $corpo, $pasta_local, $status, $data_envio,
        ]);
        $novos++;
    }

    imap_close($conn);
    return ['ok' => true, 'novos' => $novos, 'total' => $total];
}

// ─────────────────────────────────────────────────────────────
// AÇÕES
// ─────────────────────────────────────────────────────────────

// Sincronizar IMAP
if ($action === 'sync') {
    $pasta_sync = $_GET['pasta_sync'] ?? $pasta;
    $res = sincronizar_imap($pasta_sync);
    if ($res['ok']) {
        set_flash('success', "Sincronizado! {$res['novos']} novo(s) de {$res['total']} encontrado(s).");
    } else {
        set_flash('error', $res['erro']);
    }
    header('Location: email.php?pasta=' . $pasta_sync); exit;
}

// Enviar email
if ($action === 'enviar' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $para     = trim($_POST['para']     ?? '');
    $assunto  = trim($_POST['assunto']  ?? '');
    $corpo    = trim($_POST['corpo']    ?? '');
    $reply_id = (int)($_POST['reply_id'] ?? 0);

    if (!filter_var($para, FILTER_VALIDATE_EMAIL)) {
        set_flash('error', 'Endereço de email inválido.');
    } elseif (empty($assunto) || empty($corpo)) {
        set_flash('error', 'Preencha assunto e mensagem.');
    } else {
        $res = enviar_smtp($para, $assunto, $corpo);
        if ($res['ok']) {
            $c = smtp_config();
            db()->prepare(
                "INSERT INTO " . table('emails') . "
                 (remetente_nome, remetente_email, destinatario_email, assunto, corpo, pasta, status, data_envio, reply_to_id)
                 VALUES (?,?,?,?,?,?,?,?,?)"
            )->execute([
                $c['from_name'], $c['from'], $para,
                $assunto, $corpo, 'sent', 'lido',
                date('Y-m-d H:i:s'), $reply_id ?: null,
            ]);
            salvar_enviado_imap($para, $assunto, $corpo);
            set_flash('success', 'Email enviado com sucesso!');
            header('Location: email.php?pasta=sent'); exit;
        } else {
            set_flash('error', 'Erro ao enviar: ' . ($res['erro'] ?? 'Desconhecido'));
        }
    }
    header('Location: email.php?action=compor&para=' . urlencode($para)); exit;
}

// Marcar lido / não lido
if ($action === 'lido' && $id) {
    db()->prepare("UPDATE " . table('emails') . " SET status='lido' WHERE id=?")->execute([$id]);
    header('Location: email.php?action=ver&id=' . $id . '&pasta=' . $pasta); exit;
}
if ($action === 'nao_lido' && $id) {
    db()->prepare("UPDATE " . table('emails') . " SET status='nao_lido' WHERE id=?")->execute([$id]);
    header('Location: email.php?pasta=' . $pasta); exit;
}

// Favoritar
if ($action === 'star' && $id) {
    $atual = (int)db()->query("SELECT starred FROM " . table('emails') . " WHERE id={$id}")->fetchColumn();
    db()->prepare("UPDATE " . table('emails') . " SET starred=? WHERE id=?")->execute([$atual ? 0 : 1, $id]);
    header('Location: email.php?action=ver&id=' . $id . '&pasta=' . $pasta); exit;
}

// Mover para pasta
if ($action === 'mover' && $id) {
    $para_pasta = $_GET['para'] ?? 'trash';
    db()->prepare("UPDATE " . table('emails') . " SET pasta=? WHERE id=?")->execute([$para_pasta, $id]);
    set_flash('success', 'Email movido.');
    header('Location: email.php?pasta=' . $pasta); exit;
}

// Excluir permanentemente
if ($action === 'delete_perm' && $id) {
    db()->prepare("DELETE FROM " . table('emails') . " WHERE id=?")->execute([$id]);
    set_flash('success', 'Email excluído permanentemente.');
    header('Location: email.php?pasta=trash'); exit;
}

// ─────────────────────────────────────────────────────────────
// DADOS PARA EXIBIÇÃO
// ─────────────────────────────────────────────────────────────

// Ver email
$email = null;
if ($action === 'ver' && $id) {
    $s = db()->prepare("SELECT * FROM " . table('emails') . " WHERE id=?");
    $s->execute([$id]); $email = $s->fetch();
    if ($email && $email['status'] === 'nao_lido') {
        db()->prepare("UPDATE " . table('emails') . " SET status='lido' WHERE id=?")->execute([$id]);
        $email['status'] = 'lido';
    }
}

// Compor / Responder
$compor_para = $_GET['para'] ?? ''; $compor_assunto = ''; $compor_corpo = ''; $reply_id = 0;
if ($action === 'responder' && $id) {
    $s = db()->prepare("SELECT * FROM " . table('emails') . " WHERE id=?");
    $s->execute([$id]); $original = $s->fetch();
    if ($original) {
        $reply_id       = $original['id'];
        $compor_para    = $original['remetente_email'];
        $compor_assunto = (str_starts_with($original['assunto'], 'Re:') ? '' : 'Re: ') . $original['assunto'];
        $quoted         = "> " . str_replace("\n", "\n> ", trim($original['corpo']));
        $compor_corpo   = "\n\n— Em " . format_date($original['data_envio'], 'd/m/Y H:i') . ", {$original['remetente_nome']} escreveu:\n" . $quoted;
    }
}

// Contagens
$pasta_labels = ['inbox'=>'Entrada','sent'=>'Enviados','drafts'=>'Rascunhos','archive'=>'Arquivo','spam'=>'Spam','trash'=>'Lixeira'];
$pasta_icons  = ['inbox'=>'inbox','sent'=>'paper-plane','drafts'=>'file-alt','archive'=>'archive','spam'=>'ban','trash'=>'trash'];
$count_inbox  = (int)db()->query("SELECT COUNT(*) FROM " . table('emails') . " WHERE pasta='inbox' AND status='nao_lido'")->fetchColumn();
$count_starred = (int)db()->query("SELECT COUNT(*) FROM " . table('emails') . " WHERE starred=1")->fetchColumn();

// Lista da pasta
$stmt = db()->prepare("SELECT * FROM " . table('emails') . " WHERE pasta=? ORDER BY data_envio DESC LIMIT 100");
$stmt->execute([$pasta]); $lista = $stmt->fetchAll();

$imap_ok   = function_exists('imap_open') && imap_configurado();
$smtp_ok   = smtp_configurado();

require_once __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <h1><i class="fas fa-envelope"></i> E-mails</h1>
    <div style="display:flex;gap:8px;align-items:center;">
        <?php if ($imap_ok): ?>
        <a href="email.php?action=sync&pasta_sync=<?php echo $pasta; ?>" class="btn btn-light btn-sm"
           onclick="return confirm('Sincronizar emails do servidor IMAP agora?')" title="Buscar novos emails">
            <i class="fas fa-sync"></i> Sincronizar
        </a>
        <?php endif; ?>
        <a href="email.php?action=compor" class="btn btn-primary btn-sm">
            <i class="fas fa-pen"></i> Novo Email
        </a>
    </div>
</div>

<?php /* Avisos de configuração */ ?>
<?php if (!function_exists('imap_open')): ?>
<div style="margin-bottom:16px;padding:12px 16px;border-radius:8px;background:#fffbeb;border:1px solid #f59e0b;color:#92400e;font-size:0.875rem;">
    <i class="fas fa-exclamation-triangle"></i>
    <strong>Extensão IMAP não habilitada.</strong> Ative <code>extension=imap</code> no <code>php.ini</code>.
    Em hospedagens cPanel: <em>PHP Selector → Extensions → imap</em>.
</div>
<?php elseif (!imap_configurado()): ?>
<div style="margin-bottom:16px;padding:12px 16px;border-radius:8px;background:#eff6ff;border:1px solid #3b82f6;color:#1e40af;font-size:0.875rem;">
    <i class="fas fa-info-circle"></i>
    <strong>IMAP não configurado.</strong>
    <a href="configuracoes.php#email" style="color:#1d4ed8;font-weight:600;">Configure o servidor IMAP</a> para receber emails reais.
</div>
<?php endif; ?>

<div style="display:grid;grid-template-columns:220px 1fr;gap:20px;">

    <!-- ── Sidebar ── -->
    <div>
        <div class="card">
            <div class="card-body" style="padding:8px;">
                <?php foreach ($pasta_labels as $p => $l): ?>
                <a href="email.php?pasta=<?php echo $p; ?>"
                   class="nav-link <?php echo $pasta === $p ? 'active' : ''; ?>"
                   style="background:none;color:<?php echo $pasta === $p ? 'var(--primary)' : 'var(--gray-700)'; ?>;">
                    <i class="fas fa-<?php echo $pasta_icons[$p]; ?>"></i>
                    <span><?php echo $l; ?></span>
                    <?php if ($p === 'inbox' && $count_inbox > 0): ?>
                    <span class="nav-badge"><?php echo $count_inbox; ?></span>
                    <?php endif; ?>
                </a>
                <?php endforeach; ?>
                <?php if ($count_starred > 0): ?>
                <a href="email.php?pasta=starred" class="nav-link <?php echo $pasta === 'starred' ? 'active' : ''; ?>"
                   style="background:none;color:<?php echo $pasta === 'starred' ? 'var(--primary)' : 'var(--gray-700)'; ?>;">
                    <i class="fas fa-star" style="color:#f59e0b;"></i>
                    <span>Favoritos</span>
                    <span class="nav-badge"><?php echo $count_starred; ?></span>
                </a>
                <?php endif; ?>
            </div>
        </div>

        <!-- Status SMTP / IMAP -->
        <div class="card" style="margin-top:12px;">
            <div class="card-body" style="padding:12px;font-size:0.8rem;">
                <div style="font-weight:600;color:var(--gray-700);margin-bottom:8px;">Conexões</div>
                <div style="display:flex;align-items:center;gap:6px;margin-bottom:5px;">
                    <span style="width:8px;height:8px;border-radius:50%;flex-shrink:0;background:<?php echo $smtp_ok ? '#10b981' : '#ef4444'; ?>;"></span>
                    <span style="color:var(--gray-600);">SMTP <?php echo $smtp_ok ? 'configurado' : 'não configurado'; ?></span>
                </div>
                <div style="display:flex;align-items:center;gap:6px;">
                    <span style="width:8px;height:8px;border-radius:50%;flex-shrink:0;background:<?php echo $imap_ok ? '#10b981' : '#ef4444'; ?>;"></span>
                    <span style="color:var(--gray-600);">IMAP <?php echo $imap_ok ? 'configurado' : 'não configurado'; ?></span>
                </div>
                <a href="configuracoes.php#email" style="display:block;margin-top:10px;font-size:0.75rem;color:var(--primary);">
                    <i class="fas fa-cog"></i> Configurar
                </a>
            </div>
        </div>
    </div>

    <!-- ── Conteúdo Principal ── -->
    <div>

        <?php if ($action === 'compor' || $action === 'responder'): ?>
        <!-- ─── COMPOR / RESPONDER ─── -->
        <div class="card">
            <div class="card-header" style="display:flex;align-items:center;justify-content:space-between;">
                <h3><i class="fas fa-pen"></i> <?php echo $action === 'responder' ? 'Responder Email' : 'Nova Mensagem'; ?></h3>
                <a href="email.php?pasta=<?php echo $pasta; ?>" class="btn btn-sm btn-secondary"><i class="fas fa-arrow-left"></i> Voltar</a>
            </div>
            <div class="card-body">
                <?php if (!$smtp_ok): ?>
                <div style="padding:14px;background:#fffbeb;border:1px solid #f59e0b;border-radius:8px;margin-bottom:16px;font-size:0.875rem;color:#92400e;">
                    <i class="fas fa-exclamation-triangle"></i>
                    <strong>SMTP não configurado.</strong>
                    Configure em <a href="configuracoes.php#email" style="color:#92400e;font-weight:600;">Configurações → Email</a> antes de enviar.
                </div>
                <?php endif; ?>
                <form method="POST" action="email.php?action=enviar">
                    <input type="hidden" name="reply_id" value="<?php echo $reply_id; ?>">
                    <div style="margin-bottom:14px;">
                        <label style="display:block;font-size:0.875rem;font-weight:600;color:var(--gray-700);margin-bottom:4px;">Para *</label>
                        <input type="email" name="para" value="<?php echo sanitize($compor_para); ?>" required
                               placeholder="destinatario@email.com"
                               style="width:100%;padding:10px 12px;border:1px solid var(--gray-300);border-radius:6px;font-size:0.875rem;">
                    </div>
                    <div style="margin-bottom:14px;">
                        <label style="display:block;font-size:0.875rem;font-weight:600;color:var(--gray-700);margin-bottom:4px;">Assunto *</label>
                        <input type="text" name="assunto" value="<?php echo sanitize($compor_assunto); ?>" required
                               placeholder="Assunto do email"
                               style="width:100%;padding:10px 12px;border:1px solid var(--gray-300);border-radius:6px;font-size:0.875rem;">
                    </div>
                    <div style="margin-bottom:16px;">
                        <label style="display:block;font-size:0.875rem;font-weight:600;color:var(--gray-700);margin-bottom:4px;">Mensagem *</label>
                        <textarea name="corpo" required rows="14"
                                  placeholder="Escreva sua mensagem aqui..."
                                  style="width:100%;padding:10px 12px;border:1px solid var(--gray-300);border-radius:6px;font-size:0.875rem;font-family:inherit;resize:vertical;"><?php echo sanitize($compor_corpo); ?></textarea>
                    </div>
                    <div style="display:flex;gap:8px;">
                        <button type="submit" class="btn btn-primary" <?php echo !$smtp_ok ? 'disabled title="Configure o SMTP primeiro"' : ''; ?>>
                            <i class="fas fa-paper-plane"></i> Enviar
                        </button>
                        <a href="email.php?pasta=<?php echo $pasta; ?>" class="btn btn-secondary">Cancelar</a>
                    </div>
                </form>
            </div>
        </div>

        <?php elseif ($email && $action === 'ver'): ?>
        <!-- ─── VER EMAIL ─── -->
        <div class="card">
            <div class="card-header" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px;">
                <h3 style="margin:0;font-size:1rem;flex:1;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                    <i class="fas <?php echo $email['starred'] ? 'fa-star' : 'fa-envelope-open'; ?>" style="<?php echo $email['starred'] ? 'color:#f59e0b;' : ''; ?>"></i>
                    <?php echo sanitize($email['assunto']); ?>
                </h3>
                <div style="display:flex;gap:6px;flex-shrink:0;">
                    <?php if (!empty($email['remetente_email'])): ?>
                    <a href="email.php?action=responder&id=<?php echo $email['id']; ?>&pasta=<?php echo $pasta; ?>"
                       class="btn btn-sm btn-primary"><i class="fas fa-reply"></i> Responder</a>
                    <?php endif; ?>
                    <a href="email.php?action=star&id=<?php echo $email['id']; ?>&pasta=<?php echo $pasta; ?>"
                       class="btn btn-sm btn-light" title="<?php echo $email['starred'] ? 'Remover favorito' : 'Favoritar'; ?>">
                        <i class="fas fa-star" style="<?php echo $email['starred'] ? 'color:#f59e0b;' : ''; ?>"></i>
                    </a>
                    <?php if ($pasta !== 'trash'): ?>
                    <a href="email.php?action=mover&id=<?php echo $email['id']; ?>&para=archive&pasta=<?php echo $pasta; ?>"
                       class="btn btn-sm btn-light" title="Arquivar"><i class="fas fa-archive"></i></a>
                    <a href="email.php?action=mover&id=<?php echo $email['id']; ?>&para=trash&pasta=<?php echo $pasta; ?>"
                       class="btn btn-sm btn-danger" onclick="return confirm('Mover para lixeira?')"><i class="fas fa-trash"></i></a>
                    <?php endif; ?>
                    <a href="email.php?pasta=<?php echo $pasta; ?>" class="btn btn-sm btn-secondary"><i class="fas fa-arrow-left"></i></a>
                </div>
            </div>
            <div class="card-body">
                <div style="margin-bottom:16px;padding:14px;background:var(--gray-50);border-radius:8px;font-size:0.875rem;">
                    <p style="margin:0 0 4px;">
                        <strong>De:</strong>
                        <?php echo sanitize($email['remetente_nome']); ?>
                        <?php if ($email['remetente_email']): ?>
                        &lt;<a href="mailto:<?php echo sanitize($email['remetente_email']); ?>"><?php echo sanitize($email['remetente_email']); ?></a>&gt;
                        <?php endif; ?>
                    </p>
                    <p style="margin:0 0 4px;"><strong>Para:</strong> <?php echo sanitize($email['destinatario_email']); ?></p>
                    <p style="margin:0;"><strong>Data:</strong> <?php echo format_date($email['data_envio'], 'd/m/Y H:i'); ?></p>
                    <?php if (!empty($email['imap_uid'])): ?>
                    <p style="margin:4px 0 0;"><small style="color:var(--gray-400);">UID IMAP: <?php echo (int)$email['imap_uid']; ?></small></p>
                    <?php endif; ?>
                </div>
                <div style="white-space:pre-wrap;font-size:0.9375rem;line-height:1.75;color:var(--gray-700);">
                    <?php echo nl2br(sanitize($email['corpo'])); ?>
                </div>
                <?php if ($pasta === 'trash'): ?>
                <div style="margin-top:20px;padding-top:16px;border-top:1px solid var(--gray-200);display:flex;gap:8px;">
                    <a href="email.php?action=delete_perm&id=<?php echo $email['id']; ?>&pasta=trash"
                       class="btn btn-danger btn-sm"
                       onclick="return confirm('Excluir permanentemente? Esta ação não pode ser desfeita.')">
                        <i class="fas fa-trash-alt"></i> Excluir Permanentemente
                    </a>
                    <a href="email.php?action=mover&id=<?php echo $email['id']; ?>&para=inbox&pasta=trash"
                       class="btn btn-light btn-sm"><i class="fas fa-undo"></i> Restaurar</a>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <?php else: ?>
        <!-- ─── LISTA DE EMAILS ─── -->
        <div class="card">
            <div class="card-header" style="display:flex;align-items:center;justify-content:space-between;">
                <h3 style="margin:0;">
                    <?php echo $pasta_labels[$pasta] ?? ucfirst($pasta); ?>
                    <?php if (count($lista)): ?>
                    <span style="font-size:0.8rem;color:var(--gray-400);font-weight:400;margin-left:6px;">(<?php echo count($lista); ?>)</span>
                    <?php endif; ?>
                </h3>
                <?php if ($imap_ok && !in_array($pasta, ['sent','drafts'])): ?>
                <a href="email.php?action=sync&pasta_sync=<?php echo $pasta; ?>"
                   class="btn btn-light btn-sm" title="Buscar novos emails do servidor">
                    <i class="fas fa-sync-alt"></i>
                </a>
                <?php endif; ?>
            </div>

            <?php if (empty($lista)): ?>
            <div style="padding:48px;text-align:center;">
                <i class="fas fa-inbox" style="font-size:2.5rem;color:var(--gray-300);"></i>
                <p style="margin-top:12px;color:var(--gray-400);">Nenhum e-mail nesta pasta</p>
                <?php if ($imap_ok && !in_array($pasta, ['sent','drafts'])): ?>
                <a href="email.php?action=sync&pasta_sync=<?php echo $pasta; ?>"
                   class="btn btn-light btn-sm" style="margin-top:8px;">
                    <i class="fas fa-sync"></i> Sincronizar agora
                </a>
                <?php endif; ?>
            </div>
            <?php else: ?>
            <div>
                <?php foreach ($lista as $e): ?>
                <?php $unread = $e['status'] === 'nao_lido'; ?>
                <div style="display:flex;align-items:center;gap:0;border-bottom:1px solid var(--gray-100);background:<?php echo $unread ? '#eff6ff' : '#fff'; ?>;"
                     onmouseover="this.style.background='var(--gray-50)'" onmouseout="this.style.background='<?php echo $unread ? '#eff6ff' : '#fff'; ?>'">
                    <!-- Favorito rápido -->
                    <a href="email.php?action=star&id=<?php echo $e['id']; ?>&pasta=<?php echo $pasta; ?>"
                       style="padding:14px 6px 14px 14px;color:<?php echo $e['starred'] ? '#f59e0b' : 'var(--gray-300)'; ?>;"
                       title="Favoritar">
                        <i class="fas fa-star"></i>
                    </a>
                    <!-- Link principal -->
                    <a href="email.php?action=ver&id=<?php echo $e['id']; ?>&pasta=<?php echo $pasta; ?>"
                       style="display:flex;align-items:center;gap:12px;flex:1;min-width:0;text-decoration:none;padding:14px 8px;">
                        <div style="width:36px;height:36px;border-radius:50%;background:var(--primary);display:flex;align-items:center;justify-content:center;color:#fff;font-weight:700;font-size:0.875rem;flex-shrink:0;">
                            <?php echo strtoupper(substr($e['remetente_nome'] ?: $e['remetente_email'] ?: '?', 0, 1)); ?>
                        </div>
                        <div style="flex:1;min-width:0;">
                            <div style="display:flex;justify-content:space-between;align-items:center;gap:8px;">
                                <strong style="color:<?php echo $unread ? 'var(--gray-900)' : 'var(--gray-600)'; ?>;font-size:0.875rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:200px;">
                                    <?php echo sanitize($e['remetente_nome'] ?: $e['remetente_email']); ?>
                                </strong>
                                <small style="color:var(--gray-400);white-space:nowrap;flex-shrink:0;">
                                    <?php echo format_date($e['data_envio'], 'd/m H:i'); ?>
                                </small>
                            </div>
                            <div style="font-size:0.875rem;color:var(--gray-700);font-weight:<?php echo $unread ? '600' : '400'; ?>;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                                <?php echo sanitize($e['assunto']); ?>
                            </div>
                            <div style="font-size:0.8rem;color:var(--gray-400);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                                <?php echo sanitize(substr($e['corpo'] ?? '', 0, 100)); ?>…
                            </div>
                        </div>
                        <?php if ($unread): ?>
                        <div style="width:8px;height:8px;border-radius:50%;background:var(--primary);flex-shrink:0;"></div>
                        <?php endif; ?>
                    </a>
                    <!-- Ações rápidas -->
                    <div style="display:flex;gap:2px;padding-right:12px;flex-shrink:0;">
                        <?php if ($pasta !== 'trash'): ?>
                        <a href="email.php?action=mover&id=<?php echo $e['id']; ?>&para=trash&pasta=<?php echo $pasta; ?>"
                           style="padding:6px 8px;border-radius:6px;color:var(--gray-400);" title="Mover para lixeira"
                           onmouseover="this.style.color='#ef4444'" onmouseout="this.style.color='var(--gray-400)'">
                            <i class="fas fa-trash" style="font-size:0.75rem;"></i>
                        </a>
                        <?php endif; ?>
                        <?php if ($pasta === 'inbox'): ?>
                        <a href="email.php?action=mover&id=<?php echo $e['id']; ?>&para=archive&pasta=inbox"
                           style="padding:6px 8px;border-radius:6px;color:var(--gray-400);" title="Arquivar"
                           onmouseover="this.style.color='var(--primary)'" onmouseout="this.style.color='var(--gray-400)'">
                            <i class="fas fa-archive" style="font-size:0.75rem;"></i>
                        </a>
                        <?php endif; ?>
                        <?php if ($pasta === 'trash'): ?>
                        <a href="email.php?action=delete_perm&id=<?php echo $e['id']; ?>&pasta=trash"
                           style="padding:6px 8px;border-radius:6px;color:var(--gray-400);" title="Excluir permanentemente"
                           onclick="return confirm('Excluir permanentemente?')"
                           onmouseover="this.style.color='#ef4444'" onmouseout="this.style.color='var(--gray-400)'">
                            <i class="fas fa-trash-alt" style="font-size:0.75rem;"></i>
                        </a>
                        <a href="email.php?action=mover&id=<?php echo $e['id']; ?>&para=inbox&pasta=trash"
                           style="padding:6px 8px;border-radius:6px;color:var(--gray-400);" title="Restaurar"
                           onmouseover="this.style.color='#10b981'" onmouseout="this.style.color='var(--gray-400)'">
                            <i class="fas fa-undo" style="font-size:0.75rem;"></i>
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

    </div><!-- /conteúdo -->
</div><!-- /grid -->

<?php require_once __DIR__ . '/includes/footer.php'; ?>