<?php
/**
 * email_reader_ajax.php
 * Endpoint AJAX — retorna o HTML de leitura de um email para o layout dividido.
 * Chamado por carregarEmailNoPainel(id) via fetch() no email.php
 */
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/email_sync_lib.php';

header('Content-Type: text/html; charset=utf-8');

// Controle de acesso
if (!is_logged_in() || !check_permission('admin')) {
    echo '<div style="padding:20px;color:#ef4444;">Acesso negado.</div>';
    exit;
}

$id = (int)($_GET['id'] ?? 0);
if (!$id) {
    echo '<div style="padding:20px;color:#ef4444;">ID inválido.</div>';
    exit;
}

// Busca o email
$s = db()->prepare("SELECT * FROM " . table('emails') . " WHERE id = ?");
$s->execute([$id]);
$email = $s->fetch();

if (!$email) {
    echo '<div style="padding:20px;color:#ef4444;">Email não encontrado.</div>';
    exit;
}

// Marca como lido se ainda não estiver
if ($email['status'] === 'nao_lido') {
    db()->prepare("UPDATE " . table('emails') . " SET status='lido' WHERE id=?")->execute([$id]);
    $email['status'] = 'lido';
}

// Pasta atual
$pasta = $_GET['pasta'] ?? $email['pasta'] ?? 'inbox';

// Layout
$layout_email = get_config('email_layout', 'lista');

// Todas as etiquetas
$todas_etiquetas = db()->query("SELECT * FROM sc_email_etiquetas ORDER BY nome")->fetchAll();

// Etiquetas deste email
function etiquetas_do_email(int $email_id): array {
    $s = db()->prepare(
        "SELECT et.* FROM sc_email_etiquetas et
         INNER JOIN sc_email_etiqueta_rel rel ON rel.etiqueta_id = et.id
         WHERE rel.email_id = ?"
    );
    $s->execute([$email_id]);
    return $s->fetchAll();
}
$email['etiquetas'] = etiquetas_do_email($email['id']);

// Agora inclui o template de leitura
include __DIR__ . '/includes/email_reader.php';