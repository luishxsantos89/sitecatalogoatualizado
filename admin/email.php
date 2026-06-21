<?php
/**
 * email.php — Gerenciador de Email Real (IMAP + SMTP)  v3.1
 * Sincroniza via IMAP, envia via SMTP/PHPMailer.
 * v3.1: Correção enviar_smtp() — anexos agora passados corretamente (5º parâmetro)
 * v3.0: Correções de layout, SMTP, assinatura, etiquetas, CSS mensagens
 */
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/email_sync_lib.php';

require_auth();
if (!check_permission('admin')) {
    header('Location: ' . admin_url());
    exit('Acesso negado.');
}

$page_title = 'Email';
$action     = $_GET['action'] ?? 'list';
$id         = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$pasta      = $_GET['pasta']   ?? 'inbox';

// ——— Paginação ———————————————————————————————————————————————
$page     = max(1, (int)($_GET['page'] ?? 1));
$per_page = 20;
$offset   = ($page - 1) * $per_page;

// ═══════════════════════════════════════════════════════════════
// AÇÕES
// ═══════════════════════════════════════════════════════════════

// Sincronizar IMAP (manual ou via AJAX)
if ($action === 'sync') {
    $pasta_sync = $_GET['pasta_sync'] ?? $pasta;
    $res = sincronizar_imap($pasta_sync);
    if ($_GET['ajax'] ?? false) {
        header('Content-Type: application/json');
        echo json_encode($res);
        exit;
    }
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
    $incluir_assinatura = ($_POST['incluir_assinatura'] ?? '1') === '1';

    if (!filter_var($para, FILTER_VALIDATE_EMAIL)) {
        set_flash('error', 'Endereço de email inválido.');
        header('Location: email.php?action=compor&para=' . urlencode($para)); exit;
    }
    if (empty($assunto) || empty(trim(strip_tags($corpo)))) {
        set_flash('error', 'Preencha assunto e mensagem.');
        header('Location: email.php?action=compor&para=' . urlencode($para)); exit;
    }

    // Remove tags potencialmente perigosas
    $tags_permitidas = '<p><br><div><span><b><strong><i><em><u><strike><s><ul><ol><li><a><blockquote><h1><h2><h3><h4><img><table><thead><tbody><tr><td><th><hr><pre><code><font><center><style>';
    $corpo = strip_tags($corpo, $tags_permitidas);
    $corpo = preg_replace('/\s*on\w+\s*=\s*"[^"]*"/i', '', $corpo);
    $corpo = preg_replace('/\s*on\w+\s*=\s*'.chr(39).'[^'.chr(39).']*'.chr(39).'/i', '', $corpo);
    $js_regex = '/(href|src)\s*=\s*'.chr(34).chr(39).'javascript:[^'.chr(34).chr(39).']*'.chr(34).chr(39).'/i';
    $corpo = preg_replace($js_regex, '$1="#"', $corpo);

    $corpo_final = $corpo . ($incluir_assinatura ? montar_assinatura() : '');

    // Processar anexos
    $anexos_enviados = [];
    if (!empty($_FILES['anexos']) && !empty($_FILES['anexos']['name'][0])) {
        $upload_dir = __DIR__ . '/../uploads/email_anexos/';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
        foreach ($_FILES['anexos']['tmp_name'] as $idx => $tmp) {
            if ($_FILES['anexos']['error'][$idx] === UPLOAD_ERR_OK && is_uploaded_file($tmp)) {
                $nome_original = basename($_FILES['anexos']['name'][$idx]);
                $ext = pathinfo($nome_original, PATHINFO_EXTENSION);
                $nome_seguro = uniqid('anexo_') . ($ext ? '.' . $ext : '');
                $destino = $upload_dir . $nome_seguro;
                if (move_uploaded_file($tmp, $destino)) {
                    $anexos_enviados[] = ['nome' => $nome_original, 'arquivo' => $nome_seguro, 'caminho' => $destino];
                }
            }
        }
    }

    $res = enviar_smtp($para, $assunto, $corpo_final, '', $anexos_enviados);
    if ($res['ok']) {
        $c = smtp_config();
        $corpo_texto = trim(strip_tags(str_replace(['<br>','<br/>','<br />','</p>','</div>'], "
", $corpo_final)));
        $stmt = db()->prepare(
            "INSERT INTO " . table('emails') . "
             (remetente_nome, remetente_email, destinatario_email, assunto, corpo, corpo_html, pasta, status, data_envio, reply_to_id, anexos)
             VALUES (?,?,?,?,?,?,?,?,?,?,?)"
        );
        $anexos_json = !empty($anexos_enviados) ? json_encode($anexos_enviados) : null;
        $stmt->execute([
            $c['from_name'], $c['from'], $para,
            $assunto, $corpo_texto, $corpo_final, 'sent', 'lido',
            date('Y-m-d H:i:s'), $reply_id ?: null, $anexos_json
        ]);
        salvar_enviado_imap($para, $assunto, $corpo_final);
        set_flash('success', 'Email enviado com sucesso!' . (count($anexos_enviados) ? ' (' . count($anexos_enviados) . ' anexo(s))' : ''));
        header('Location: email.php?pasta=sent&enviado=1'); exit;
    } else {
        // Remove anexos se falhou o envio
        foreach ($anexos_enviados as $a) {
            if (file_exists($a['caminho'])) unlink($a['caminho']);
        }
        set_flash('error', 'Erro ao enviar: ' . ($res['erro'] ?? 'Desconhecido'));
        header('Location: email.php?action=compor&para=' . urlencode($para)); exit;
    }
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

// ── Ações em massa ──
if ($action === 'massa' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $ids = array_filter(array_map('intval', $_POST['ids'] ?? []));
    $op  = $_POST['operacao'] ?? '';
    header('Content-Type: application/json');

    if (empty($ids) || empty($op)) {
        echo json_encode(['ok' => false, 'erro' => 'Nenhum email selecionado.']);
        exit;
    }
    $placeholders = implode(',', array_fill(0, count($ids), '?'));

    try {
        if ($op === 'lido') {
            db()->prepare("UPDATE " . table('emails') . " SET status='lido' WHERE id IN ($placeholders)")->execute($ids);
        } elseif ($op === 'nao_lido') {
            db()->prepare("UPDATE " . table('emails') . " SET status='nao_lido' WHERE id IN ($placeholders)")->execute($ids);
        } elseif ($op === 'arquivar') {
            db()->prepare("UPDATE " . table('emails') . " SET pasta='archive' WHERE id IN ($placeholders)")->execute($ids);
        } elseif ($op === 'excluir') {
            db()->prepare("UPDATE " . table('emails') . " SET pasta='trash' WHERE id IN ($placeholders)")->execute($ids);
        } elseif ($op === 'excluir_perm') {
            db()->prepare("DELETE FROM " . table('emails') . " WHERE id IN ($placeholders)")->execute($ids);
        } elseif ($op === 'star') {
            db()->prepare("UPDATE " . table('emails') . " SET starred=1 WHERE id IN ($placeholders)")->execute($ids);
        } else {
            echo json_encode(['ok' => false, 'erro' => 'Operação inválida.']);
            exit;
        }
        echo json_encode(['ok' => true, 'afetados' => count($ids)]);
    } catch (PDOException $e) {
        echo json_encode(['ok' => false, 'erro' => $e->getMessage()]);
    }
    exit;
}

// ── Etiquetas: criar nova ──
if ($action === 'etiqueta_criar' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $nome = trim($_POST['nome'] ?? '');
    $cor  = trim($_POST['cor']  ?? '#3b82f6');
    if (empty($nome)) { echo json_encode(['ok' => false, 'erro' => 'Nome obrigatório.']); exit; }
    try {
        db()->prepare("INSERT INTO sc_email_etiquetas (nome, cor) VALUES (?, ?)")->execute([$nome, $cor]);
        echo json_encode(['ok' => true, 'id' => db()->lastInsertId(), 'nome' => $nome, 'cor' => $cor]);
    } catch (PDOException $e) {
        echo json_encode(['ok' => false, 'erro' => 'Já existe uma etiqueta com esse nome.']);
    }
    exit;
}

// ── Etiquetas: aplicar/remover de um email ──
if ($action === 'etiqueta_toggle' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $email_id    = (int)($_POST['email_id']    ?? 0);
    $etiqueta_id = (int)($_POST['etiqueta_id'] ?? 0);
    if (!$email_id || !$etiqueta_id) { echo json_encode(['ok' => false]); exit; }

    $existe = db()->prepare("SELECT 1 FROM sc_email_etiqueta_rel WHERE email_id=? AND etiqueta_id=?");
    $existe->execute([$email_id, $etiqueta_id]);
    if ($existe->fetchColumn()) {
        db()->prepare("DELETE FROM sc_email_etiqueta_rel WHERE email_id=? AND etiqueta_id=?")->execute([$email_id, $etiqueta_id]);
        echo json_encode(['ok' => true, 'aplicada' => false]);
    } else {
        db()->prepare("INSERT INTO sc_email_etiqueta_rel (email_id, etiqueta_id) VALUES (?,?)")->execute([$email_id, $etiqueta_id]);
        echo json_encode(['ok' => true, 'aplicada' => true]);
    }
    exit;
}

// ── Etiquetas: editar ──
if ($action === 'etiqueta_editar' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $etiqueta_id = (int)($_POST['etiqueta_id'] ?? 0);
    $nome = trim($_POST['nome'] ?? '');
    $cor  = trim($_POST['cor']  ?? '#3b82f6');
    if (!$etiqueta_id || empty($nome)) {
        echo json_encode(['ok' => false, 'erro' => 'ID e nome obrigatórios.']);
        exit;
    }
    try {
        db()->prepare("UPDATE sc_email_etiquetas SET nome=?, cor=? WHERE id=?")->execute([$nome, $cor, $etiqueta_id]);
        echo json_encode(['ok' => true, 'id' => $etiqueta_id, 'nome' => $nome, 'cor' => $cor]);
    } catch (PDOException $e) {
        echo json_encode(['ok' => false, 'erro' => 'Erro ao atualizar etiqueta.']);
    }
    exit;
}

// ── Etiquetas: excluir etiqueta ──
if ($action === 'etiqueta_excluir' && $id) {
    db()->prepare("DELETE FROM sc_email_etiquetas WHERE id=?")->execute([$id]);
    set_flash('success', 'Etiqueta excluída.');
    header('Location: email.php?pasta=' . $pasta); exit;
}

// ═══════════════════════════════════════════════════════════════
// DADOS PARA EXIBIÇÃO
// ═══════════════════════════════════════════════════════════════

// Todas as etiquetas existentes
$todas_etiquetas = db()->query("SELECT * FROM sc_email_etiquetas ORDER BY nome")->fetchAll();

// Função para buscar etiquetas de um email
function etiquetas_do_email(int $email_id): array {
    $s = db()->prepare(
        "SELECT et.* FROM sc_email_etiquetas et
         INNER JOIN sc_email_etiqueta_rel rel ON rel.etiqueta_id = et.id
         WHERE rel.email_id = ?"
    );
    $s->execute([$email_id]);
    return $s->fetchAll();
}

// Ver email
$email = null;
if ($action === 'ver' && $id) {
    $s = db()->prepare("SELECT * FROM " . table('emails') . " WHERE id=?");
    $s->execute([$id]); $email = $s->fetch();
    if ($email && $email['status'] === 'nao_lido') {
        db()->prepare("UPDATE " . table('emails') . " SET status='lido' WHERE id=?")->execute([$id]);
        $email['status'] = 'lido';
    }
    if ($email) $email['etiquetas'] = etiquetas_do_email($email['id']);
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
        $corpo_original = !empty($original['corpo_html']) ? $original['corpo_html'] : nl2br(sanitize($original['corpo']));
        $compor_corpo   = '<br><br>— Em ' . format_date($original['data_envio'], 'd/m/Y H:i') . ', ' . sanitize($original['remetente_nome']) . ' escreveu:<br>'
                        . '<blockquote style="border-left:3px solid #d1d5db;padding-left:12px;color:#6b7280;margin:8px 0;">' . $corpo_original . '</blockquote>';
    }
}

// Layout escolhido (lista | dividido) — agora via GET para evitar arquivo extra
$layout_email = get_config('email_layout', 'lista');
if (isset($_GET['layout'])) {
    $layout_email = in_array($_GET['layout'], ['lista', 'dividido']) ? $_GET['layout'] : 'lista';
    // Salva preferência no banco — CORRIGIDO: usa sc_configuracoes, não sc_configs
    $existe = db()->prepare("SELECT COUNT(*) FROM " . table('configuracoes') . " WHERE chave='email_layout'");
    $existe->execute([]);
    if ((int)$existe->fetchColumn() > 0) {
        db()->prepare("UPDATE " . table('configuracoes') . " SET valor=? WHERE chave='email_layout'")
            ->execute([$layout_email]);
    } else {
        db()->prepare("INSERT INTO " . table('configuracoes') . " (chave, valor, grupo, tipo, ativo, ordem) VALUES (?,?,'email','select',1,99)")
            ->execute(['email_layout', $layout_email]);
    }
}

// Contagens
$pasta_labels = ['inbox'=>'Entrada','sent'=>'Enviados','drafts'=>'Rascunhos','archive'=>'Arquivo','spam'=>'Spam','trash'=>'Lixeira'];
$pasta_icons  = ['inbox'=>'inbox','sent'=>'paper-plane','drafts'=>'file-alt','archive'=>'archive','spam'=>'ban','trash'=>'trash'];
$count_inbox  = (int)db()->query("SELECT COUNT(*) FROM " . table('emails') . " WHERE pasta='inbox' AND status='nao_lido'")->fetchColumn();
$count_starred = (int)db()->query("SELECT COUNT(*) FROM " . table('emails') . " WHERE starred=1")->fetchColumn();

// Lista da pasta (com filtro de etiqueta opcional)
$filtro_etiqueta = (int)($_GET['etiqueta'] ?? 0);
if ($filtro_etiqueta) {
    $count_stmt = db()->prepare(
        "SELECT COUNT(*) FROM " . table('emails') . " e
         INNER JOIN sc_email_etiqueta_rel rel ON rel.email_id = e.id
         WHERE e.pasta = ? AND rel.etiqueta_id = ?"
    );
    $count_stmt->execute([$pasta, $filtro_etiqueta]);
    $total_emails = (int)$count_stmt->fetchColumn();
    $total_pages  = max(1, (int)ceil($total_emails / $per_page));
    $page = min($page, $total_pages);
    $offset = ($page - 1) * $per_page;

    $stmt = db()->prepare(
        "SELECT e.* FROM " . table('emails') . " e
         INNER JOIN sc_email_etiqueta_rel rel ON rel.email_id = e.id
         WHERE e.pasta = ? AND rel.etiqueta_id = ?
         ORDER BY e.data_envio DESC LIMIT ? OFFSET ?"
    );
    $stmt->execute([$pasta, $filtro_etiqueta, $per_page, $offset]);
} else {
    $count_stmt = db()->prepare("SELECT COUNT(*) FROM " . table('emails') . " WHERE pasta=?");
    $count_stmt->execute([$pasta]);
    $total_emails = (int)$count_stmt->fetchColumn();
    $total_pages  = max(1, (int)ceil($total_emails / $per_page));
    $page = min($page, $total_pages);
    $offset = ($page - 1) * $per_page;

    $stmt = db()->prepare("SELECT * FROM " . table('emails') . " WHERE pasta=? ORDER BY data_envio DESC LIMIT ? OFFSET ?");
    $stmt->execute([$pasta, $per_page, $offset]);
}
$lista = $stmt->fetchAll();

// Anexa etiquetas a cada item da lista
foreach ($lista as &$e) {
    $e['etiquetas'] = etiquetas_do_email($e['id']);
}
unset($e);

$imap_ok = function_exists('imap_open') && imap_configurado();
$smtp_ok = smtp_configurado();
$sync_auto = get_config('email_sync_auto', '0') === '1';
$sync_intervalo = (int)get_config('email_sync_intervalo', 5);

// Assinatura para pré-visualização no compose
$assinatura_preview = montar_assinatura();


// ═══════════════════════════════════════════════════════════════
// FUNÇÃO: Processar corpo do email para exibição HTML
// ═══════════════════════════════════════════════════════════════
function processar_corpo_email($corpo_html, $corpo_texto) {
    if (!empty($corpo_html)) {
        $html = $corpo_html;
    } else {
        $html = nl2br(htmlspecialchars($corpo_texto ?? '', ENT_QUOTES, 'UTF-8'));
    }
    $html = preg_replace('/<script[^>]*>(.*?)<\/script>/is', '', $html);
    $html = preg_replace('/\s*on\w+\s*=\s*"[^"]*"/i', '', $html);
    $html = preg_replace("/\s*on\w+\s*=\s*'[^']*'/i", '', $html);
    return $html;
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <h1><i class="fas fa-envelope"></i> E-mails</h1>
    <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
        <?php if ($imap_ok): ?>
        <button type="button" class="btn btn-light btn-sm" onclick="sincronizarAgora()" id="btn_sync">
            <i class="fas fa-sync" id="sync_icon"></i> <span id="sync_label">Sincronizar</span>
        </button>
        <?php endif; ?>
        <div style="display:flex;align-items:center;gap:4px;background:var(--gray-100);padding:3px;border-radius:8px;">
            <a href="email.php?pasta=<?php echo $pasta; ?>&layout=lista" 
               class="btn-layout <?php echo $layout_email==='lista'?'active':''; ?>" title="Lista">
                <i class="fas fa-list"></i>
            </a>
            <a href="email.php?pasta=<?php echo $pasta; ?>&layout=dividido"
               class="btn-layout <?php echo $layout_email==='dividido'?'active':''; ?>" title="Dividido (lista + leitura lado a lado)">
                <i class="fas fa-table-columns"></i>
            </a>
        </div>
        <button type="button" class="btn btn-primary btn-sm" onclick="abrirCompositor()">
            <i class="fas fa-pen"></i> Novo Email
        </button>
    </div>
</div>

<style>
/* ═══════════════════════════════════════════════════════════════
   ESTILOS CORRIGIDOS - v3.0
   ═══════════════════════════════════════════════════════════════ */

.btn-layout { 
    display:inline-flex; align-items:center; justify-content:center;
    border:none; background:transparent; padding:7px 10px; border-radius:6px; 
    cursor:pointer; color:var(--gray-500); font-size:0.875rem; text-decoration:none;
}
.btn-layout:hover { background:var(--gray-200); color:var(--gray-700); }
.btn-layout.active { background:#fff; color:var(--primary); box-shadow:0 1px 2px rgba(0,0,0,0.08); }

.email-row { 
    display:flex; align-items:center; border-bottom:1px solid var(--gray-100); 
    transition:background .15s; cursor:pointer; position:relative;
}
.email-row:hover { background:var(--gray-50); }
.email-row.selected { background:#eff6ff; }
.email-row.unread .email-subject { font-weight:600; color:var(--gray-900); }

.toolbar-massa { 
    display:none; align-items:center; gap:6px; padding:10px 14px; 
    background:#eff6ff; border-bottom:1px solid var(--gray-200); flex-wrap:wrap; 
}
.toolbar-massa.show { display:flex; }
.toolbar-massa button { 
    background:none; border:1px solid var(--gray-300); border-radius:6px; 
    padding:6px 10px; font-size:0.8rem; color:var(--gray-700); cursor:pointer; 
    display:flex; align-items:center; gap:5px; 
}
.toolbar-massa button:hover { background:#fff; border-color:var(--primary); color:var(--primary); }

.tag-badge { 
    display:inline-flex; align-items:center; gap:3px; font-size:0.68rem; font-weight:600; 
    padding:2px 7px; border-radius:10px; color:#fff; white-space:nowrap; 
}

.split-layout { 
    display:grid; grid-template-columns:380px 1fr; gap:0; 
    height:calc(100vh - 280px); min-height:500px; 
}
.split-list { 
    overflow-y:auto; border-right:1px solid var(--gray-200); 
    background:#fff;
}
.split-reader { 
    overflow-y:auto; padding:20px; background:#fff; 
}

/* ═══════════════════════════════════════════════════════════════
   POPUP DE COMPOSIÇÃO
   ═══════════════════════════════════════════════════════════════ */
#compose_overlay { 
    display:none; position:fixed; inset:0; background:rgba(0,0,0,0.35); 
    z-index:1000; align-items:center; justify-content:center; padding:24px; 
}
#compose_overlay.show { display:flex; }
#compose_window { 
    width:680px; max-width:calc(100vw - 48px); height:640px; max-height:calc(100vh - 48px); 
    background:#fff; border-radius:10px; 
    box-shadow:0 8px 30px rgba(0,0,0,0.25); display:flex; 
    flex-direction:column; margin:0; transition:all 0.3s ease; 
}
#compose_window.maximized { 
    width:90vw !important; height:90vh !important; max-width:90vw !important; 
    max-height:90vh !important; border-radius:10px !important; margin:auto !important; 
}
#compose_window.maximized #compose_header { border-radius:10px 10px 0 0 !important; }
#compose_window.minimized { height:48px !important; max-height:48px !important; overflow:hidden !important; }
#compose_window.minimized #compose_body, #compose_window.minimized #compose_footer { display:none !important; }

#compose_header { 
    display:flex; align-items:center; justify-content:space-between; 
    padding:12px 16px; background:#3b4453; color:#fff; 
    border-radius:10px 10px 0 0; cursor:default; flex-shrink:0;
}
#compose_header h4 { margin:0; font-size:0.9rem; font-weight:600; }
#compose_header .actions { display:flex; gap:10px; }
#compose_header .actions i { cursor:pointer; opacity:0.85; font-size:0.85rem; }
#compose_header .actions i:hover { opacity:1; }

#compose_body { flex:1; display:flex; flex-direction:column; overflow:hidden; }

.compose_field { 
    display:flex; align-items:center; border-bottom:1px solid var(--gray-100); 
    padding:9px 16px; flex-shrink:0;
}
.compose_field label { width:55px; font-size:0.82rem; color:var(--gray-500); flex-shrink:0; }
.compose_field input { flex:1; border:none; outline:none; font-size:0.875rem; background:transparent; text-transform:none !important; }

/* ═══════════════════════════════════════════════════════════════
   TOOLBAR DO EDITOR - ESPAÇO INTERNO CORRIGIDO
   ═══════════════════════════════════════════════════════════════ */
#editor_toolbar { 
    display:flex; gap:8px; padding:12px 16px; border-bottom:1px solid var(--gray-100); 
    flex-wrap:wrap; background:var(--gray-50); align-items:center; flex-shrink:0;
}
#editor_toolbar button { 
    border:none; background:none; width:36px; height:36px; border-radius:6px; 
    cursor:pointer; color:var(--gray-600); font-size:0.95rem; 
    display:flex; align-items:center; justify-content:center;
    transition: all 0.15s ease;
}
#editor_toolbar button:hover { background:var(--gray-200); color:var(--gray-900); }
#editor_toolbar button:active { transform: scale(0.95); }

#editor_toolbar select { 
    border:1px solid var(--gray-200); border-radius:6px; font-size:0.875rem; 
    padding:6px 10px; margin-right:4px; background:#fff; color:var(--gray-700); 
    min-width:110px; height:36px; cursor:pointer;
}
#editor_toolbar select:focus { outline:none; border-color:var(--primary); }

#editor_toolbar input[type="color"] { 
    width:36px; height:36px; border:none; padding:2px; background:none; 
    cursor:pointer; border-radius:6px; 
}

#editor_toolbar .toolbar-separator {
    width:1px; height:24px; background:var(--gray-300); margin:0 4px;
}

#editor_corpo { 
    flex:1; overflow-y:auto; padding:16px 18px; font-size:0.95rem; 
    line-height:1.7; outline:none; min-height:200px; text-transform:none !important;
}

#compose_footer { 
    display:flex; align-items:center; justify-content:space-between; 
    padding:12px 16px; border-top:1px solid var(--gray-100); flex-shrink:0;
    background:#fff;
}

/* ═══════════════════════════════════════════════════════════════
   ASSINATURA NO EDITOR
   ═══════════════════════════════════════════════════════════════ */
#assinatura-preview-compose {
    margin-top:20px; padding-top:14px; border-top:2px solid var(--gray-200);
}
#assinatura-preview-compose .assinatura-label {
    font-weight:600; color:var(--gray-400); font-size:0.75rem; 
    margin-bottom:8px; text-transform:uppercase; letter-spacing:0.05em;
}
#assinatura-preview-compose .assinatura-conteudo {
    color:var(--gray-600); font-size:0.85rem; line-height:1.6;
}

.label-picker { position:relative; display:inline-block; }
.label-picker-menu { 
    display:none; position:absolute; top:100%; right:0; background:#fff; 
    border:1px solid var(--gray-200); border-radius:8px; 
    box-shadow:0 4px 16px rgba(0,0,0,0.12); padding:8px; z-index:50; min-width:200px; 
}
.label-picker-menu.show { display:block; }
.label-picker-item { 
    display:flex; align-items:center; gap:8px; padding:6px 8px; 
    border-radius:6px; cursor:pointer; font-size:0.8rem; 
}
.label-picker-item:hover { background:var(--gray-50); }
.label-dot { width:10px; height:10px; border-radius:50%; flex-shrink:0; }

/* ═══════════════════════════════════════════════════════════════
   LEITOR DE EMAIL - CSS FORMATADO
   ═══════════════════════════════════════════════════════════════ */
.email-reader-content {
    font-size:0.95rem; line-height:1.7; color:var(--gray-800);
    word-break:break-word; overflow-wrap:break-word;
}
.email-reader-content * { max-width:100%; box-sizing:border-box; }
.email-reader-content p { margin:0 0 12px 0; }
.email-reader-content br { display:block; margin:4px 0; }
.email-reader-content a { color:var(--primary); text-decoration:underline; }
.email-reader-content a:hover { color:var(--primary-dark); }
.email-reader-content blockquote,
.email-reader-content blockquote * {
    border-left:3px solid var(--gray-300) !important; padding-left:12px !important; 
    margin:8px 0 !important; color:var(--gray-600) !important; font-style:italic !important;
}
.email-reader-content ul, .email-reader-content ol { margin:8px 0; padding-left:24px; }
.email-reader-content li { margin:4px 0; }
.email-reader-content table { border-collapse:collapse; width:100%; margin:12px 0; display:block; overflow-x:auto; }
.email-reader-content td, .email-reader-content th { 
    border:1px solid var(--gray-200); padding:8px; 
}
.email-reader-content img { max-width:100%; height:auto; border-radius:4px; display:block; }
.email-reader-content h1, .email-reader-content h2, .email-reader-content h3, .email-reader-content h4 {
    margin:16px 0 8px; color:var(--gray-900); font-weight:600;
}
.email-reader-content pre, .email-reader-content code {
    background:var(--gray-100); padding:12px; border-radius:6px;
    overflow-x:auto; font-size:0.85rem; font-family:monospace; white-space:pre-wrap;
}
/* Reset de CSS inline que pode vir de emails */
.email-reader-content [style*="margin"] { /* mantém inline */ }
.email-reader-content div { margin:0; padding:0; }
.email-reader-content span[style*="color"] { /* preserva cores inline */ }
/* Suporte a emails com layout em tabela */
.email-reader-content table { display:table !important; width:auto !important; max-width:100% !important; }
.email-reader-content td, .email-reader-content th { display:table-cell !important; }
.email-reader-content tr { display:table-row !important; }
.email-reader-content tbody, .email-reader-content thead { display:table-row-group !important; }
/* Reset de fontes inline */
.email-reader-content [style*="font-family"] { font-family:inherit !important; }
/* Classes comuns de provedores de email */
.email-reader-content .ExternalClass, .email-reader-content .ReadMsgBody { width:100% !important; }
.email-reader-content .yshortcuts { color:inherit !important; }
/* Esconde CSS que pode vir como texto */
.email-reader-content style, .email-reader-content script, .email-reader-content noscript { display:none !important; }
/* Preserva links */
.email-reader-content a[href] { color:#3b82f6 !important; text-decoration:underline !important; }
/* Classes Gmail */
.email-reader-content .gmail_extra { border-left:2px solid #d1d5db; padding-left:12px; margin-top:12px; color:#6b7280; }
.email-reader-content .gmail_quote { border-left:2px solid #d1d5db; padding-left:12px; margin:8px 0; }
.email-reader-content .gmail_attr { color:#9ca3af; font-size:0.85rem; margin-bottom:8px; }
/* Classes Outlook */
.email-reader-content .MsoNormal { margin:0; line-height:normal; }
.email-reader-content .MsoHyperlink { color:#3b82f6 !important; text-decoration:underline !important; }
/* Apple Mail */
.email-reader-content .Apple-interchange-newline { display:block; }
.email-reader-content .Apple-style-span { /* mantém inline */ }
/* Esconde CSS cru que aparece como texto */
.email-reader-content style { display:none !important; }
.email-reader-content script { display:none !important; }
/* Classes de email providers */
.email-reader-content .gmail_extra, .email-reader-content .gmail_quote { 
    border-left:2px solid var(--gray-300); padding-left:12px; margin-top:12px; 
}

/* Animações */
@keyframes spin { from { transform:rotate(0deg); } to { transform:rotate(360deg); } }
.fa-spin { animation:spin 1s linear infinite; }

/* Scrollbar customizada */
.split-list::-webkit-scrollbar, .split-reader::-webkit-scrollbar, #editor_corpo::-webkit-scrollbar {
    width:6px;
}
.split-list::-webkit-scrollbar-thumb, .split-reader::-webkit-scrollbar-thumb, #editor_corpo::-webkit-scrollbar-thumb {
    background:var(--gray-300); border-radius:3px;
}
.split-list::-webkit-scrollbar-thumb:hover, .split-reader::-webkit-scrollbar-thumb:hover, #editor_corpo::-webkit-scrollbar-thumb:hover {
    background:var(--gray-400);
}

/* Anexos no compositor */
.anexo-tag {
    display:inline-flex; align-items:center; gap:5px; 
    padding:4px 10px; background:var(--gray-100); border:1px solid var(--gray-200);
    border-radius:6px; font-size:0.78rem; color:var(--gray-700);
    max-width:200px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;
}
.anexo-tag i { color:var(--primary); font-size:0.75rem; }
.anexo-tag .remove { cursor:pointer; color:var(--gray-400); margin-left:2px; }
.anexo-tag .remove:hover { color:#ef4444; }
</style>

<?php if (!function_exists('imap_open')): ?>
<div style="margin-bottom:16px;padding:12px 16px;border-radius:8px;background:#fffbeb;border:1px solid #f59e0b;color:#92400e;font-size:0.875rem;">
    <i class="fas fa-exclamation-triangle"></i>
    <strong>Extensão IMAP não habilitada.</strong> Ative <code>extension=imap</code> no <code>php.ini</code>.
</div>
<?php elseif (!imap_configurado()): ?>
<div style="margin-bottom:16px;padding:12px 16px;border-radius:8px;background:#eff6ff;border:1px solid #3b82f6;color:#1e40af;font-size:0.875rem;">
    <i class="fas fa-info-circle"></i>
    <strong>IMAP não configurado.</strong>
    <a href="configuracoes.php#email" style="color:#1d4ed8;font-weight:600;">Configure o servidor IMAP</a> para receber emails reais.
</div>
<?php endif; ?>

<div style="display:grid;grid-template-columns:220px 1fr;gap:20px;">

    <!-- ══ Sidebar ══ -->
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

        <!-- Etiquetas -->
        <div class="card" style="margin-top:12px;">
            <div class="card-body" style="padding:10px 12px;">
                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px;">
                    <span style="font-weight:600;color:var(--gray-700);font-size:0.8rem;">Etiquetas</span>
                    <div style="display:flex;gap:8px;align-items:center;">
                        <i class="fas fa-plus-circle" style="color:var(--primary);cursor:pointer;font-size:0.85rem;" onclick="abrirNovaEtiqueta()" title="Nova etiqueta"></i>
                        <i class="fas fa-cog" style="color:var(--gray-500);cursor:pointer;font-size:0.85rem;" onclick="abrirGerenciarEtiquetas()" title="Gerenciar etiquetas"></i>
                    </div>
                </div>
                <?php foreach ($todas_etiquetas as $et): ?>
                <a href="email.php?pasta=<?php echo $pasta; ?>&etiqueta=<?php echo $et['id']; ?>"
                   style="display:flex;align-items:center;gap:7px;padding:5px 6px;border-radius:6px;text-decoration:none;font-size:0.8rem;color:var(--gray-700);<?php echo $filtro_etiqueta==$et['id']?'background:var(--gray-100);':''; ?>">
                    <span class="label-dot" style="background:<?php echo sanitize($et['cor']); ?>;"></span>
                    <?php echo sanitize($et['nome']); ?>
                </a>
                <?php endforeach; ?>
                <?php if (empty($todas_etiquetas)): ?>
                <p style="font-size:0.75rem;color:var(--gray-400);margin:4px 0 0;">Nenhuma etiqueta ainda</p>
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

<?php if ($sync_auto && $imap_ok): ?>
                <div style="margin-top:6px;font-size:0.72rem;color:#10b981;"><i class="fas fa-clock"></i> Auto-sync a cada <?php echo $sync_intervalo; ?> min</div>
                <?php endif; ?>
                <a href="configuracoes.php#email" style="display:block;margin-top:10px;font-size:0.75rem;color:var(--primary);">
                    <i class="fas fa-cog"></i> Configurar
                </a>
            </div>
        </div>
    </div>

    <!-- ══ Conteúdo Principal ══ -->
    <div>

        <?php if ($email && $action === 'ver' && $layout_email === 'lista'): ?>
        <?php include __DIR__ . '/includes/email_reader.php'; ?>

        <?php elseif ($layout_email === 'dividido' && $action !== 'compor' && $action !== 'responder'): ?>
        <!-- ═══ LAYOUT DIVIDIDO: lista + leitura lado a lado ═══ -->
        <div class="card" style="overflow:hidden;">
            <div class="card-header" style="display:flex;align-items:center;justify-content:space-between;flex-shrink:0;">
                <h3 style="margin:0;"><?php echo $pasta_labels[$pasta] ?? ucfirst($pasta); ?>
                    <?php if (count($lista)): ?><span style="font-size:0.8rem;color:var(--gray-400);font-weight:400;"> (<?php echo count($lista); ?>)</span><?php endif; ?>
                </h3>
            </div>
            <div class="toolbar-massa" id="toolbar_massa">
                <span id="massa_count" style="font-size:0.8rem;color:var(--gray-600);font-weight:600;margin-right:6px;">0 selecionado(s)</span>
                <button onclick="acaoMassa('lido')"><i class="fas fa-envelope-open"></i> Lida</button>
                <button onclick="acaoMassa('nao_lido')"><i class="fas fa-envelope"></i> Não lida</button>
                <button onclick="acaoMassa('arquivar')"><i class="fas fa-archive"></i> Arquivar</button>
                <button onclick="acaoMassa('excluir')"><i class="fas fa-trash"></i> Excluir</button>
                <button onclick="limparSelecao()" style="margin-left:auto;border:none;color:var(--gray-400);"><i class="fas fa-times"></i></button>
            </div>
            <div class="split-layout" style="padding:0;">
                <div class="split-list">
                    <?php if (empty($lista)): ?>
                    <div style="padding:40px;text-align:center;color:var(--gray-400);">
                        <i class="fas fa-inbox" style="font-size:2rem;"></i>
                        <p style="margin-top:10px;font-size:0.875rem;">Nenhum e-mail</p>
                    </div>
                    <?php else: foreach ($lista as $e): $unread = $e['status']==='nao_lido'; ?>
                    <div class="email-row <?php echo $unread?'unread':''; ?>" data-id="<?php echo $e['id']; ?>"
                         onclick="if(!event.target.closest('.row-check')) carregarEmailNoPainel(<?php echo $e['id']; ?>)" style="padding:11px 12px;">
                        <input type="checkbox" class="row-check" onclick="toggleSelecao(<?php echo $e['id']; ?>, this)" style="margin-right:10px;">
                        <div style="flex:1;min-width:0;">
                            <div style="display:flex;justify-content:space-between;gap:6px;">
                                <strong class="email-subject" style="font-size:0.82rem;color:<?php echo $unread?'var(--gray-900)':'var(--gray-600)'; ?>;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:160px;"><?php echo sanitize($e['remetente_nome'] ?: $e['remetente_email']); ?></strong>
                                <small style="color:var(--gray-400);font-size:0.7rem;white-space:nowrap;"><?php echo format_date($e['data_envio'],'d/m H:i'); ?></small>
                            </div>
                            <div class="email-subject" style="font-size:0.8rem;color:var(--gray-700);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?php echo sanitize($e['assunto']); ?></div>
                            <?php if (!empty($e['etiquetas'])): ?>
                            <div style="margin-top:4px;display:flex;gap:3px;flex-wrap:wrap;">
                                <?php foreach ($e['etiquetas'] as $et): ?>
                                <span class="tag-badge" style="background:<?php echo sanitize($et['cor']); ?>;"><?php echo sanitize($et['nome']); ?></span>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; endif; ?>

                <!-- Paginação -->
                <?php if ($total_pages > 1): ?>
                <div style="display:flex;align-items:center;justify-content:center;gap:4px;padding:10px;border-top:1px solid var(--gray-100);flex-wrap:wrap;background:#fff;">
                    <?php if ($page > 1): ?>
                    <a href="email.php?pasta=<?php echo $pasta; ?>&page=<?php echo $page - 1; ?><?php echo $filtro_etiqueta ? '&etiqueta=' . $filtro_etiqueta : ''; ?>"
                       class="btn btn-light btn-sm" style="padding:4px 8px;font-size:0.75rem;"><i class="fas fa-chevron-left"></i></a>
                    <?php endif; ?>
                    <span style="font-size:0.78rem;color:var(--gray-500);">
                        <strong><?php echo $page; ?></strong>/<?php echo $total_pages; ?>
                    </span>
                    <?php if ($page < $total_pages): ?>
                    <a href="email.php?pasta=<?php echo $pasta; ?>&page=<?php echo $page + 1; ?><?php echo $filtro_etiqueta ? '&etiqueta=' . $filtro_etiqueta : ''; ?>"
                       class="btn btn-light btn-sm" style="padding:4px 8px;font-size:0.75rem;"><i class="fas fa-chevron-right"></i></a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
                </div>
                <div class="split-reader" id="split_reader">
                    <div style="display:flex;align-items:center;justify-content:center;height:100%;color:var(--gray-300);flex-direction:column;" id="split_reader_empty">
                        <i class="fas fa-envelope-open-text" style="font-size:3rem;"></i>
                        <p style="margin-top:10px;font-size:0.875rem;">Selecione um email para visualizar</p>
                    </div>
                    <div id="split_reader_content" style="display:none;"></div>
                </div>
            </div>
        </div>

        <?php elseif ($action === 'compor' || $action === 'responder'): ?>
        <div class="card"><div class="card-body" style="text-align:center;padding:60px;">
            <i class="fas fa-pen" style="font-size:2rem;color:var(--gray-300);"></i>
            <p style="margin-top:12px;color:var(--gray-400);">Abrindo o compositor...</p>
            <script>document.addEventListener('DOMContentLoaded', () => abrirCompositor(<?php echo json_encode($compor_para); ?>, <?php echo json_encode($compor_assunto); ?>, <?php echo json_encode($compor_corpo); ?>, <?php echo (int)$reply_id; ?>));</script>
        </div></div>

        <?php else: ?>
        <!-- ═══ LAYOUT LISTA (padrão) ═══ -->
        <div class="card">
            <div class="card-header" style="display:flex;align-items:center;justify-content:space-between;">
                <h3 style="margin:0;">
                    <?php echo $pasta_labels[$pasta] ?? ucfirst($pasta); ?>
                    <?php if (count($lista)): ?><span style="font-size:0.8rem;color:var(--gray-400);font-weight:400;margin-left:6px;">(<?php echo count($lista); ?>)</span><?php endif; ?>
                </h3>
                <?php if ($imap_ok && !in_array($pasta, ['sent','drafts'])): ?>
                <a href="email.php?action=sync&pasta_sync=<?php echo $pasta; ?>" class="btn btn-light btn-sm" title="Buscar novos emails"><i class="fas fa-sync-alt"></i></a>
                <?php endif; ?>
            </div>
            <div class="toolbar-massa" id="toolbar_massa">
                <label style="display:flex;align-items:center;gap:6px;font-size:0.8rem;color:var(--gray-600);cursor:pointer;">
                    <input type="checkbox" id="select_all" onclick="toggleSelecionarTodos(this)"> Selecionar todos
                </label>
                <span id="massa_count" style="font-size:0.8rem;color:var(--gray-600);font-weight:600;">0 selecionado(s)</span>
                <button onclick="acaoMassa('lido')"><i class="fas fa-envelope-open"></i> Marcar lida</button>
                <button onclick="acaoMassa('nao_lido')"><i class="fas fa-envelope"></i> Não lida</button>
                <button onclick="acaoMassa('arquivar')"><i class="fas fa-archive"></i> Arquivar</button>
                <button onclick="acaoMassa('excluir')"><i class="fas fa-trash"></i> Excluir</button>
                <button onclick="limparSelecao()" style="margin-left:auto;border:none;color:var(--gray-400);"><i class="fas fa-times"></i> Cancelar</button>
            </div>

            <?php if (empty($lista)): ?>
            <div style="padding:48px;text-align:center;">
                <i class="fas fa-inbox" style="font-size:2.5rem;color:var(--gray-300);"></i>
                <p style="margin-top:12px;color:var(--gray-400);">Nenhum e-mail nesta pasta</p>
                <?php if ($imap_ok && !in_array($pasta, ['sent','drafts'])): ?>
                <a href="email.php?action=sync&pasta_sync=<?php echo $pasta; ?>" class="btn btn-light btn-sm" style="margin-top:8px;"><i class="fas fa-sync"></i> Sincronizar agora</a>
                <?php endif; ?>
            </div>
            <?php else: ?>
            <div>
                <?php foreach ($lista as $e): $unread = $e['status'] === 'nao_lido'; ?>
                <div class="email-row <?php echo $unread?'unread':''; ?>" data-id="<?php echo $e['id']; ?>" style="background:<?php echo $unread ? '#eff6ff' : '#fff'; ?>;">
                    <input type="checkbox" class="row-check" onclick="toggleSelecao(<?php echo $e['id']; ?>, this)" style="margin:0 4px 0 14px;flex-shrink:0;">
                    <a href="email.php?action=star&id=<?php echo $e['id']; ?>&pasta=<?php echo $pasta; ?>"
                       style="padding:14px 6px;color:<?php echo $e['starred'] ? '#f59e0b' : 'var(--gray-300)'; ?>;flex-shrink:0;" title="Favoritar" onclick="event.stopPropagation()">
                        <i class="fas fa-star"></i>
                    </a>
                    <a href="email.php?action=ver&id=<?php echo $e['id']; ?>&pasta=<?php echo $pasta; ?>"
                       style="display:flex;align-items:center;gap:12px;flex:1;min-width:0;text-decoration:none;padding:14px 8px;">
                        <div style="width:36px;height:36px;border-radius:50%;background:var(--primary);display:flex;align-items:center;justify-content:center;color:#fff;font-weight:700;font-size:0.875rem;flex-shrink:0;">
                            <?php echo strtoupper(substr($e['remetente_nome'] ?: $e['remetente_email'] ?: '?', 0, 1)); ?>
                        </div>
                        <div style="flex:1;min-width:0;">
                            <div style="display:flex;justify-content:space-between;align-items:center;gap:8px;">
                                <strong class="email-subject" style="font-size:0.875rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:200px;"><?php echo sanitize($e['remetente_nome'] ?: $e['remetente_email']); ?></strong>
                                <small style="color:var(--gray-400);white-space:nowrap;flex-shrink:0;"><?php echo format_date($e['data_envio'], 'd/m H:i'); ?></small>
                            </div>
                            <div class="email-subject" style="font-size:0.875rem;color:var(--gray-700);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?php echo sanitize($e['assunto']); ?></div>
                            <div style="font-size:0.8rem;color:var(--gray-400);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?php echo sanitize(substr($e['corpo'] ?? '', 0, 100)); ?>…</div>
                            <?php if (!empty($e['etiquetas'])): ?>
                            <div style="margin-top:4px;display:flex;gap:4px;flex-wrap:wrap;">
                                <?php foreach ($e['etiquetas'] as $et): ?>
                                <span class="tag-badge" style="background:<?php echo sanitize($et['cor']); ?>;"><?php echo sanitize($et['nome']); ?></span>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>
                        </div>
                        <?php if ($unread): ?><div style="width:8px;height:8px;border-radius:50%;background:var(--primary);flex-shrink:0;"></div><?php endif; ?>
                    </a>
                    <div style="display:flex;gap:2px;padding-right:12px;flex-shrink:0;">
                        <?php if ($pasta !== 'trash'): ?>
                        <a href="email.php?action=mover&id=<?php echo $e['id']; ?>&para=trash&pasta=<?php echo $pasta; ?>" style="padding:6px 8px;border-radius:6px;color:var(--gray-400);" title="Lixeira" onclick="event.stopPropagation()"><i class="fas fa-trash" style="font-size:0.75rem;"></i></a>
                        <?php endif; ?>
                        <?php if ($pasta === 'inbox'): ?>
                        <a href="email.php?action=mover&id=<?php echo $e['id']; ?>&para=archive&pasta=inbox" style="padding:6px 8px;border-radius:6px;color:var(--gray-400);" title="Arquivar" onclick="event.stopPropagation()"><i class="fas fa-archive" style="font-size:0.75rem;"></i></a>
                        <?php endif; ?>
                        <?php if ($pasta === 'trash'): ?>
                        <a href="email.php?action=delete_perm&id=<?php echo $e['id']; ?>&pasta=trash" style="padding:6px 8px;border-radius:6px;color:var(--gray-400);" title="Excluir" onclick="event.stopPropagation();return confirm('Excluir permanentemente?')"><i class="fas fa-trash-alt" style="font-size:0.75rem;"></i></a>
                        <a href="email.php?action=mover&id=<?php echo $e['id']; ?>&para=inbox&pasta=trash" style="padding:6px 8px;border-radius:6px;color:var(--gray-400);" title="Restaurar" onclick="event.stopPropagation()"><i class="fas fa-undo" style="font-size:0.75rem;"></i></a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <!-- Paginação -->
            <?php if ($total_pages > 1): ?>
            <div style="display:flex;align-items:center;justify-content:center;gap:6px;padding:16px;border-top:1px solid var(--gray-100);flex-wrap:wrap;">
                <?php if ($page > 1): ?>
                <a href="email.php?pasta=<?php echo $pasta; ?>&page=<?php echo $page - 1; ?><?php echo $filtro_etiqueta ? '&etiqueta=' . $filtro_etiqueta : ''; ?>"
                   class="btn btn-light btn-sm"><i class="fas fa-chevron-left"></i> Anterior</a>
                <?php endif; ?>
                <span style="font-size:0.85rem;color:var(--gray-500);">
                    Página <strong><?php echo $page; ?></strong> de <?php echo $total_pages; ?> 
                    <span style="color:var(--gray-400);">(<?php echo $total_emails; ?> total)</span>
                </span>
                <?php if ($page < $total_pages): ?>
                <a href="email.php?pasta=<?php echo $pasta; ?>&page=<?php echo $page + 1; ?><?php echo $filtro_etiqueta ? '&etiqueta=' . $filtro_etiqueta : ''; ?>"
                   class="btn btn-light btn-sm">Próxima <i class="fas fa-chevron-right"></i></a>
                <?php endif; ?>
                <div style="display:flex;gap:3px;margin-left:8px;">
                    <?php 
                    $start_page = max(1, $page - 2);
                    $end_page   = min($total_pages, $page + 2);
                    for ($i = $start_page; $i <= $end_page; $i++): 
                    ?>
                    <a href="email.php?pasta=<?php echo $pasta; ?>&page=<?php echo $i; ?><?php echo $filtro_etiqueta ? '&etiqueta=' . $filtro_etiqueta : ''; ?>"
                       class="btn btn-sm <?php echo $i === $page ? 'btn-primary' : 'btn-light'; ?>"
                       style="min-width:36px;padding:5px 10px;font-size:0.8rem;"><?php echo $i; ?></a>
                    <?php endfor; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

    </div>
</div>

<!-- ════════════════════════════════════════════════════════════
     POPUP DE COMPOSIÇÃO
     ════════════════════════════════════════════════════════════ -->
<div id="compose_overlay">
    <div id="compose_window">
        <div id="compose_header">
            <h4 id="compose_title"><i class="fas fa-pen"></i> Nova Mensagem</h4>
            <div class="actions">
                <i class="fas fa-minus" onclick="minimizarCompositor()" title="Minimizar"></i>
                <i class="fas fa-expand" onclick="maximizarCompositor()" title="Expandir / Tela cheia" id="btn_maximize"></i>
                <i class="fas fa-times" onclick="fecharCompositor()" title="Fechar"></i>
            </div>
        </div>
        <form id="compose_form" method="POST" action="email.php?action=enviar" enctype="multipart/form-data" style="flex:1;display:flex;flex-direction:column;overflow:hidden;">
            <input type="hidden" name="reply_id" id="compose_reply_id" value="0">
            <input type="hidden" name="corpo" id="compose_corpo_hidden">
            <div id="compose_body">
                <div class="compose_field">
                    <label>Para</label>
                    <input type="email" name="para" id="compose_para" required placeholder="destinatario@email.com">
                </div>
                <div class="compose_field">
                    <label>Assunto</label>
                    <input type="text" name="assunto" id="compose_assunto" required placeholder="Assunto do email">
                </div>

                <!-- Toolbar corrigida com mais espaço -->
                <div id="editor_toolbar">
                    <select onchange="execCmd('fontName', this.value);" title="Fonte">
                        <option value="Arial">Arial</option>
                        <option value="Georgia">Georgia</option>
                        <option value="'Courier New'">Courier New</option>
                        <option value="Verdana">Verdana</option>
                        <option value="Tahoma">Tahoma</option>
                    </select>
                    <select onchange="execCmd('fontSize', this.value);" title="Tamanho" style="width:60px;">
                        <option value="2">Pequeno</option>
                        <option value="3" selected>Normal</option>
                        <option value="5">Grande</option>
                        <option value="7">Enorme</option>
                    </select>
                    <span class="toolbar-separator"></span>
                    <button type="button" onclick="execCmd('bold')" title="Negrito"><i class="fas fa-bold"></i></button>
                    <button type="button" onclick="execCmd('italic')" title="Itálico"><i class="fas fa-italic"></i></button>
                    <button type="button" onclick="execCmd('underline')" title="Sublinhado"><i class="fas fa-underline"></i></button>
                    <button type="button" onclick="execCmd('strikeThrough')" title="Tachado"><i class="fas fa-strikethrough"></i></button>
                    <span class="toolbar-separator"></span>
                    <input type="color" onchange="document.execCommand('foreColor', false, this.value); editorCorpo.focus();" title="Cor do texto" style="width:32px;height:32px;border:none;padding:0;background:none;cursor:pointer;">
                    <span class="toolbar-separator"></span>
                    <button type="button" onclick="execCmd('insertUnorderedList')" title="Lista"><i class="fas fa-list-ul"></i></button>
                    <button type="button" onclick="execCmd('insertOrderedList')" title="Lista numerada"><i class="fas fa-list-ol"></i></button>
                    <button type="button" onclick="execCmd('justifyLeft')" title="Alinhar esquerda"><i class="fas fa-align-left"></i></button>
                    <button type="button" onclick="execCmd('justifyCenter')" title="Centralizar"><i class="fas fa-align-center"></i></button>
                    <button type="button" onclick="execCmd('justifyRight')" title="Alinhar direita"><i class="fas fa-align-right"></i></button>
                    <span class="toolbar-separator"></span>
                    <button type="button" onclick="inserirLink()" title="Inserir link"><i class="fas fa-link"></i></button>
                    <button type="button" onclick="execCmd('removeFormat')" title="Limpar formatação"><i class="fas fa-text-slash"></i></button>
                </div>

                <div id="editor_corpo" contenteditable="true" data-placeholder="Escreva sua mensagem..."></div>

                <?php if ($assinatura_preview): ?>
                <div style="padding:10px 16px;border-top:1px solid var(--gray-100);display:flex;align-items:center;gap:8px;font-size:0.78rem;color:var(--gray-500);background:#fafafa;">
                    <label style="display:flex;align-items:center;gap:6px;cursor:pointer;">
                        <input type="checkbox" id="incluir_assinatura" name="incluir_assinatura" value="1" checked onchange="document.getElementById('incluir_assinatura_hidden').value=this.checked?'1':'0'; toggleAssinaturaPreview();">
                        Incluir assinatura
                    </label>
                    <input type="hidden" id="incluir_assinatura_hidden" name="incluir_assinatura" value="1">
                </div>
                <?php endif; ?>
            </div>
            <div id="compose_footer">
                <div style="display:flex;align-items:center;gap:10px;flex:1;">
                    <button type="button" class="btn btn-light btn-sm" onclick="document.getElementById('compose_anexos').click()" title="Anexar arquivo">
                        <i class="fas fa-paperclip"></i> Anexar
                    </button>
                    <input type="file" id="compose_anexos" name="anexos[]" multiple style="display:none;" onchange="atualizarAnexos(this)">
                    <div id="anexos_lista" style="display:flex;gap:6px;flex-wrap:wrap;align-items:center;"></div>
                </div>
                <button type="submit" class="btn btn-primary" id="btn_enviar_compose" style="padding:9px 22px;">
                    <i class="fas fa-paper-plane"></i> Enviar
                </button>
                <span id="compose_status" style="font-size:0.8rem;"></span>
            </div>
        </form>
    </div>
</div>

<!-- Modal Nova Etiqueta -->
<div id="etiqueta_overlay" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.35);z-index:1100;align-items:center;justify-content:center;">
    <div style="background:#fff;border-radius:10px;padding:20px;width:320px;max-width:90vw;box-shadow:0 4px 20px rgba(0,0,0,0.15);">
        <h4 style="margin:0 0 14px;font-size:0.95rem;"><i class="fas fa-tag"></i> Nova Etiqueta</h4>
        <div style="margin-bottom:10px;">
            <label style="display:block;font-size:0.8rem;color:var(--gray-600);margin-bottom:4px;">Nome</label>
            <input type="text" id="nova_etiqueta_nome" style="width:100%;padding:8px 10px;border:1px solid var(--gray-300);border-radius:6px;font-size:0.875rem;" placeholder="Ex: Urgente">
        </div>
        <div style="margin-bottom:16px;">
            <label style="display:block;font-size:0.8rem;color:var(--gray-600);margin-bottom:4px;">Cor</label>
            <input type="color" id="nova_etiqueta_cor" value="#3b82f6" style="width:50px;height:36px;border:none;border-radius:6px;cursor:pointer;">
        </div>
        <div style="display:flex;gap:8px;justify-content:flex-end;">
            <button type="button" class="btn btn-secondary btn-sm" onclick="fecharNovaEtiqueta()">Cancelar</button>
            <button type="button" class="btn btn-primary btn-sm" onclick="salvarNovaEtiqueta()">Criar</button>
        </div>
    </div>
</div>

<!-- Modal Gerenciar Etiquetas -->
<div id="etiqueta_gerenciar_overlay" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.35);z-index:1100;align-items:center;justify-content:center;">
    <div style="background:#fff;border-radius:10px;padding:20px;width:420px;max-width:90vw;max-height:80vh;overflow-y:auto;box-shadow:0 4px 20px rgba(0,0,0,0.15);">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;">
            <h4 style="margin:0;font-size:0.95rem;"><i class="fas fa-tags"></i> Gerenciar Etiquetas</h4>
            <i class="fas fa-times" style="cursor:pointer;color:var(--gray-400);font-size:1rem;" onclick="fecharGerenciarEtiquetas()"></i>
        </div>
        <div id="etiqueta_gerenciar_lista">
            <?php foreach ($todas_etiquetas as $et): ?>
            <div class="etiqueta-edit-row" data-id="<?php echo $et['id']; ?>" style="display:flex;align-items:center;gap:10px;padding:10px;border-bottom:1px solid var(--gray-100);">
                <input type="color" class="et-cor" value="<?php echo sanitize($et['cor']); ?>" style="width:32px;height:32px;border:none;border-radius:6px;cursor:pointer;flex-shrink:0;">
                <input type="text" class="et-nome" value="<?php echo sanitize($et['nome']); ?>" style="flex:1;padding:6px 10px;border:1px solid var(--gray-200);border-radius:6px;font-size:0.875rem;">
                <button type="button" class="btn btn-sm btn-primary" onclick="salvarEdicaoEtiqueta(this.closest('.etiqueta-edit-row'))" style="padding:5px 10px;font-size:0.75rem;"><i class="fas fa-save"></i></button>
                <button type="button" class="btn btn-sm btn-danger" onclick="excluirEtiquetaGerenciar(<?php echo $et['id']; ?>, this.closest('.etiqueta-edit-row'))" style="padding:5px 10px;font-size:0.75rem;"><i class="fas fa-trash"></i></button>
            </div>
            <?php endforeach; ?>
            <?php if (empty($todas_etiquetas)): ?>
            <p style="text-align:center;color:var(--gray-400);font-size:0.875rem;padding:20px;">Nenhuma etiqueta criada ainda.</p>
            <?php endif; ?>
        </div>
        <div style="margin-top:16px;display:flex;gap:8px;justify-content:flex-end;">
            <button type="button" class="btn btn-secondary btn-sm" onclick="fecharGerenciarEtiquetas()">Fechar</button>
        </div>
    </div>
</div>
<script>
// ════════════════════════════════════════════════════════════
// LAYOUT (lista / dividido) — CORRIGIDO: agora usa GET
// ════════════════════════════════════════════════════════════
function mudarLayout(layout) {
    const url = new URL(window.location.href);
    url.searchParams.set('layout', layout);
    window.location.href = url.toString();
}

// ════════════════════════════════════════════════════════════
// POPUP DE COMPOSIÇÃO — CORRIGIDO
// ════════════════════════════════════════════════════════════
let composeDirty = false;
const editorCorpo = document.getElementById('editor_corpo');
const ASSINATURA_HTML = <?php echo json_encode($assinatura_preview ?: ''); ?>;
const ASSINATURA_CONTAINER_ID = 'assinatura-preview-compose';

function abrirCompositor(para, assunto, corpoHtml, replyId) {
    document.getElementById('compose_overlay').classList.add('show');
    document.getElementById('compose_title').innerHTML = '<i class="fas fa-pen"></i> Nova Mensagem';
    document.getElementById('compose_para').value    = para || '';
    document.getElementById('compose_assunto').value = assunto || '';
    document.getElementById('compose_reply_id').value = replyId || 0;
    editorCorpo.innerHTML = corpoHtml || '';
    document.getElementById('compose_status').textContent = '';
    composeDirty = false;

    // Insere assinatura preview se checkbox estiver marcado
    setTimeout(() => {
        const chk = document.getElementById('incluir_assinatura');
        if (chk && chk.checked) inserirAssinaturaPreview();
    }, 50);
    setTimeout(() => document.getElementById('compose_para').focus(), 100);
}

function abrirCompositorResposta(emailId) {
    fetch('email_get.php?id=' + emailId)
        .then(r => r.json())
        .then(d => {
            if (!d.ok) { alert('Não foi possível carregar o email.'); return; }
            document.getElementById('compose_overlay').classList.add('show');
            document.getElementById('compose_title').innerHTML = '<i class="fas fa-reply"></i> Responder';
            document.getElementById('compose_para').value     = d.remetente_email;
            document.getElementById('compose_assunto').value  = (d.assunto_texto.startsWith('Re:') ? '' : 'Re: ') + d.assunto_texto;
            document.getElementById('compose_reply_id').value = d.id;
            const corpoOriginal = d.corpo_html || ('<div style="white-space:pre-wrap;">' + (d.corpo || '').replace(/</g,'&lt;') + '</div>');
            editorCorpo.innerHTML = '<br><br>— Em ' + d.data_formatada + ', ' + d.remetente_nome + ' escreveu:<br>'
                + '<blockquote style="border-left:3px solid #d1d5db;padding-left:12px;color:#6b7280;margin:8px 0;">' + corpoOriginal + '</blockquote>';
            composeDirty = false;
            setTimeout(() => {
                const chk = document.getElementById('incluir_assinatura');
                if (chk && chk.checked) inserirAssinaturaPreview();
            }, 50);
            setTimeout(() => editorCorpo.focus(), 100);
        })
        .catch(() => alert('Erro de rede ao carregar o email.'));
}

function fecharCompositor() {
    const temConteudo = document.getElementById('compose_para').value.trim()
        || document.getElementById('compose_assunto').value.trim()
        || editorCorpo.innerText.trim();
    if (temConteudo && composeDirty) {
        if (!confirm('Descartar esta mensagem?')) return;
    }
    document.getElementById('compose_overlay').classList.remove('show');
    document.getElementById('compose_form').reset();
    editorCorpo.innerHTML = '';
    // Remove assinatura preview
    const container = document.getElementById(ASSINATURA_CONTAINER_ID);
    if (container) container.remove();
}

// ════════════════════════════════════════════════════════════
// ASSINATURA DIGITAL — preview no editor
// ════════════════════════════════════════════════════════════
function inserirAssinaturaPreview() {
    if (!ASSINATURA_HTML) return;
    let container = document.getElementById(ASSINATURA_CONTAINER_ID);
    if (!container) {
        container = document.createElement('div');
        container.id = ASSINATURA_CONTAINER_ID;
        container.innerHTML = '<div class="assinatura-label">— Assinatura</div><div class="assinatura-conteudo">' + ASSINATURA_HTML + '</div>';
        editorCorpo.appendChild(container);
    }
}

function removerAssinaturaPreview() {
    const container = document.getElementById(ASSINATURA_CONTAINER_ID);
    if (container) container.remove();
}

function toggleAssinaturaPreview() {
    const checkbox = document.getElementById('incluir_assinatura');
    if (checkbox && checkbox.checked) {
        inserirAssinaturaPreview();
    } else {
        removerAssinaturaPreview();
    }
}

function minimizarCompositor() {
    const win = document.getElementById('compose_window');
    win.classList.toggle('minimized');
    const btn = document.getElementById('btn_maximize');
    if (btn) btn.className = 'fas fa-expand';
}

function maximizarCompositor() {
    const win = document.getElementById('compose_window');
    const btn = document.getElementById('btn_maximize');
    if (win.classList.contains('maximized')) {
        win.classList.remove('maximized');
        if (btn) btn.className = 'fas fa-expand';
        if (btn) btn.title = 'Expandir / Tela cheia';
    } else {
        win.classList.remove('minimized');
        win.classList.add('maximized');
        if (btn) btn.className = 'fas fa-compress';
        if (btn) btn.title = 'Restaurar tamanho';
    }
}

['input', 'keyup'].forEach(evt => {
    const para = document.getElementById('compose_para');
    const assunto = document.getElementById('compose_assunto');
    if (para) para.addEventListener(evt, () => composeDirty = true);
    if (assunto) assunto.addEventListener(evt, () => composeDirty = true);
    if (editorCorpo) editorCorpo.addEventListener(evt, () => composeDirty = true);
});

// Impede fechar clicando fora (só pelo X ou enviando)
document.getElementById('compose_overlay').addEventListener('mousedown', function(e) {
    if (e.target === this) {
        const win = document.getElementById('compose_window');
        win.style.transform = 'scale(1.01)';
        setTimeout(() => win.style.transform = 'scale(1)', 100);
    }
});

// Editor toolbar
function execCmd(cmd, val) {
    editorCorpo.focus();
    document.execCommand('styleWithCSS', false, true);
    document.execCommand(cmd, false, val || null);
    editorCorpo.focus();
}
function inserirLink() {
    const sel = window.getSelection();
    const url = prompt('URL do link:', 'https://');
    if (url) {
        editorCorpo.focus();
        document.execCommand('styleWithCSS', false, true);
        document.execCommand('createLink', false, url);
        editorCorpo.focus();
    }
}

// Envio: serializa o HTML do editor para o campo hidden antes do submit
document.getElementById('compose_form').addEventListener('submit', function(e) {
    document.getElementById('compose_corpo_hidden').value = editorCorpo.innerHTML;
    const btn = document.getElementById('btn_enviar_compose');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Enviando...';
    composeDirty = false;
});

// CSS do placeholder do editor
const styleEditor = document.createElement('style');
styleEditor.textContent = '#editor_corpo:empty:before { content: attr(data-placeholder); color: #9ca3af; }';
document.head.appendChild(styleEditor);

// ════════════════════════════════════════════════════════════
// SELEÇÃO EM MASSA
// ════════════════════════════════════════════════════════════
let selecionados = new Set();

function toggleSelecao(id, checkbox) {
    if (checkbox.checked) selecionados.add(id); else selecionados.delete(id);
    atualizarToolbarMassa();
    const row = document.querySelector('.email-row[data-id="' + id + '"]');
    if (row) row.classList.toggle('selected', checkbox.checked);
}

function toggleSelecionarTodos(checkbox) {
    document.querySelectorAll('.row-check').forEach(cb => {
        cb.checked = checkbox.checked;
        const row = cb.closest('.email-row');
        if (!row) return;
        const id = parseInt(row.dataset.id);
        if (checkbox.checked) selecionados.add(id); else selecionados.delete(id);
        row.classList.toggle('selected', checkbox.checked);
    });
    atualizarToolbarMassa();
}

function atualizarToolbarMassa() {
    const toolbar = document.getElementById('toolbar_massa');
    const count = document.getElementById('massa_count');
    if (!toolbar) return;
    if (selecionados.size > 0) {
        toolbar.classList.add('show');
        if (count) count.textContent = selecionados.size + ' selecionado(s)';
    } else {
        toolbar.classList.remove('show');
    }
}

function limparSelecao() {
    selecionados.clear();
    document.querySelectorAll('.row-check').forEach(cb => cb.checked = false);
    document.querySelectorAll('.email-row.selected').forEach(r => r.classList.remove('selected'));
    const selectAll = document.getElementById('select_all');
    if (selectAll) selectAll.checked = false;
    atualizarToolbarMassa();
}

function acaoMassa(operacao) {
    if (selecionados.size === 0) return;
    if ((operacao === 'excluir' || operacao === 'excluir_perm') && !confirm('Confirma esta ação para ' + selecionados.size + ' email(ns)?')) return;

    const params = new URLSearchParams();
    params.append('operacao', operacao);
    selecionados.forEach(id => params.append('ids[]', id));

    fetch('email.php?action=massa', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: params.toString()
    })
    .then(r => r.json())
    .then(d => {
        if (d.ok) location.reload();
        else alert('Erro: ' + (d.erro || 'desconhecido'));
    })
    .catch(() => alert('Erro de rede.'));
}

// ════════════════════════════════════════════════════════════
// LAYOUT DIVIDIDO: carregar email no painel direito via AJAX
// ════════════════════════════════════════════════════════════
function carregarEmailNoPainel(id) {
    const contentDiv = document.getElementById('split_reader_content');
    const emptyDiv = document.getElementById('split_reader_empty');

    if (contentDiv) contentDiv.innerHTML = '<div style="display:flex;justify-content:center;align-items:center;height:100%;"><i class="fas fa-spinner fa-spin" style="font-size:1.5rem;color:var(--gray-300);"></i></div>';
    if (emptyDiv) emptyDiv.style.display = 'none';
    if (contentDiv) contentDiv.style.display = 'block';

    fetch('email_reader_ajax.php?id=' + id)
        .then(r => r.text())
        .then(html => {
            if (contentDiv) contentDiv.innerHTML = html;
            // Marca como lido visualmente
            const row = document.querySelector('.email-row[data-id="' + id + '"]');
            if (row) {
                row.classList.remove('unread');
                row.style.background = '#fff';
                const subject = row.querySelector('.email-subject');
                if (subject) subject.style.fontWeight = '400';
            }
        })
        .catch(() => {
            if (contentDiv) contentDiv.innerHTML = '<div style="text-align:center;color:var(--gray-400);padding:40px;"><i class="fas fa-exclamation-circle" style="font-size:2rem;"></i><p>Erro ao carregar email</p></div>';
        });
}

// Fallback: se email_reader_ajax.php não existir, redireciona
function abrirNaLeitura(id) {
    const url = new URL(window.location);
    url.searchParams.set('action', 'ver');
    url.searchParams.set('id', id);
    window.location.href = url.toString();
}

// ════════════════════════════════════════════════════════════
// ETIQUETAS
// ════════════════════════════════════════════════════════════
function toggleLabelPicker(evt, emailId) {
    evt.stopPropagation();
    const menu = document.getElementById('label_menu_' + emailId);
    if (!menu) return;
    document.querySelectorAll('.label-picker-menu.show').forEach(m => { if (m !== menu) m.classList.remove('show'); });
    menu.classList.toggle('show');
}
document.addEventListener('click', () => document.querySelectorAll('.label-picker-menu.show').forEach(m => m.classList.remove('show')));

function aplicarEtiqueta(emailId, etiquetaId, el) {
    fetch('email.php?action=etiqueta_toggle', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ email_id: emailId, etiqueta_id: etiquetaId })
    })
    .then(r => r.json())
    .then(d => {
        if (d.ok) {
            const check = el.querySelector('.fa-check');
            if (check) check.style.display = d.aplicada ? 'inline' : 'none';
        }
    });
}

function abrirNovaEtiqueta() { 
    const overlay = document.getElementById('etiqueta_overlay');
    if (overlay) overlay.style.display = 'flex'; 
}
function fecharNovaEtiqueta() { 
    const overlay = document.getElementById('etiqueta_overlay');
    if (overlay) overlay.style.display = 'none'; 
}
function salvarNovaEtiqueta() {
    const nomeInput = document.getElementById('nova_etiqueta_nome');
    const corInput = document.getElementById('nova_etiqueta_cor');
    const nome = nomeInput ? nomeInput.value.trim() : '';
    const cor  = corInput ? corInput.value : '#3b82f6';
    if (!nome) { alert('Digite um nome para a etiqueta.'); return; }
    fetch('email.php?action=etiqueta_criar', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ nome, cor })
    })
    .then(r => r.json())
    .then(d => {
        if (d.ok) location.reload();
        else alert(d.erro || 'Erro ao criar etiqueta.');
    });
}

// ════════════════════════════════════════════════════════════
// GERENCIAR ETIQUETAS (editar / excluir com confirmação)
// ════════════════════════════════════════════════════════════
function abrirGerenciarEtiquetas() {
    const overlay = document.getElementById('etiqueta_gerenciar_overlay');
    if (overlay) overlay.style.display = 'flex';
}
function fecharGerenciarEtiquetas() {
    const overlay = document.getElementById('etiqueta_gerenciar_overlay');
    if (overlay) overlay.style.display = 'none';
}

function salvarEdicaoEtiqueta(row) {
    const id   = row.dataset.id;
    const nome = row.querySelector('.et-nome').value.trim();
    const cor  = row.querySelector('.et-cor').value;
    if (!nome) { alert('Nome da etiqueta não pode ficar vazio.'); return; }

    const btn = row.querySelector('.btn-primary');
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
    btn.disabled = true;

    fetch('email.php?action=etiqueta_editar', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ etiqueta_id: id, nome, cor })
    })
    .then(r => r.json())
    .then(d => {
        btn.innerHTML = '<i class="fas fa-save"></i>';
        btn.disabled = false;
        if (d.ok) {
            btn.innerHTML = '<i class="fas fa-check"></i>';
            btn.style.background = '#22c55e';
            setTimeout(() => {
                btn.innerHTML = '<i class="fas fa-save"></i>';
                btn.style.background = '';
            }, 1500);
        } else {
            alert(d.erro || 'Erro ao salvar.');
        }
    })
    .catch(() => {
        btn.innerHTML = '<i class="fas fa-save"></i>';
        btn.disabled = false;
        alert('Erro de rede.');
    });
}

function excluirEtiquetaGerenciar(id, row) {
    if (!confirm('Excluir esta etiqueta permanentemente?\n\nOs emails que a usam perderão a marcação.')) return;

    const btn = row.querySelector('.btn-danger');
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
    btn.disabled = true;

    fetch('email.php?action=etiqueta_excluir&id=' + id)
    .then(() => {
        row.style.transition = 'all 0.3s';
        row.style.opacity = '0';
        row.style.transform = 'translateX(-20px)';
        setTimeout(() => row.remove(), 300);
    })
    .catch(() => {
        btn.innerHTML = '<i class="fas fa-trash"></i>';
        btn.disabled = false;
        alert('Erro de rede.');
    });
}

// ════════════════════════════════════════════════════════════
// SINCRONIZAÇÃO MANUAL (AJAX, sem recarregar a página)
// ════════════════════════════════════════════════════════════
function sincronizarAgora() {
    const icon  = document.getElementById('sync_icon');
    const label = document.getElementById('sync_label');
    const btn   = document.getElementById('btn_sync');
    if (!btn) return;
    btn.disabled = true;
    icon.classList.add('fa-spin');
    label.textContent = 'Sincronizando...';

    fetch('email.php?action=sync&pasta_sync=<?php echo $pasta; ?>&ajax=1')
        .then(r => r.json())
        .then(d => {
            icon.classList.remove('fa-spin');
            btn.disabled = false;
            if (d.ok) {
                label.textContent = 'Sincronizar';
                if (d.novos > 0) location.reload();
            } else {
                label.textContent = 'Erro';
                alert(d.erro);
            }
        })
        .catch(() => { 
            icon.classList.remove('fa-spin'); 
            btn.disabled = false; 
            label.textContent = 'Sincronizar'; 
        });
}

// ════════════════════════════════════════════════════════════
// ANEXOS NO COMPOSITOR
// ════════════════════════════════════════════════════════════
function atualizarAnexos(input) {
    const lista = document.getElementById('anexos_lista');
    if (!lista) return;
    lista.innerHTML = '';
    if (input.files && input.files.length > 0) {
        for (let i = 0; i < input.files.length; i++) {
            const file = input.files[i];
            const tag = document.createElement('span');
            tag.className = 'anexo-tag';
            tag.innerHTML = '<i class="fas fa-file"></i> ' + escapeHtml(file.name) + ' <span class="remove" onclick="removerAnexo(' + i + ')"><i class="fas fa-times"></i></span>';
            lista.appendChild(tag);
        }
    }
}
function removerAnexo(index) {
    const input = document.getElementById('compose_anexos');
    if (!input) return;
    const dt = new DataTransfer();
    for (let i = 0; i < input.files.length; i++) {
        if (i !== index) dt.items.add(input.files[i]);
    }
    input.files = dt.files;
    atualizarAnexos(input);
}
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

<?php if ($sync_auto && $imap_ok): ?>
// Sincronização automática
setInterval(() => {
    fetch('email.php?action=sync&pasta_sync=inbox&ajax=1')
        .then(r => r.json())
        .then(d => { if (d.ok && d.novos > 0) location.reload(); })
        .catch(() => {});
}, <?php echo $sync_intervalo * 60 * 1000; ?>);
<?php endif; ?>
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>