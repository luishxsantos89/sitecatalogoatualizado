<?php
/**
 * email_get.php
 * Endpoint AJAX — retorna os dados de um email em JSON,
 * usado pelo popup de composição ao clicar em "Responder"
 * dentro do layout dividido (sem precisar recarregar a página).
 */
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/email_sync_lib.php'; // para sanitize_email_html()

header('Content-Type: application/json; charset=utf-8');

if (!is_logged_in() || !check_permission('admin')) {
    echo json_encode(['ok' => false, 'erro' => 'Acesso negado.']);
    exit;
}

$id = (int)($_GET['id'] ?? 0);
if (!$id) {
    echo json_encode(['ok' => false, 'erro' => 'ID inválido.']);
    exit;
}

$s = db()->prepare("SELECT * FROM " . table('emails') . " WHERE id = ?");
$s->execute([$id]);
$email = $s->fetch();

if (!$email) {
    echo json_encode(['ok' => false, 'erro' => 'Email não encontrado.']);
    exit;
}

// Importante: corpo_html pode vir de um remetente externo não confiável (IMAP).
// Sempre sanitizar antes de devolver, já que o JS insere isso via innerHTML.
echo json_encode([
    'ok'              => true,
    'id'              => (int)$email['id'],
    'assunto_texto'   => $email['assunto'], // para o campo de input (.value, sem HTML)
    'assunto'         => htmlspecialchars($email['assunto'], ENT_QUOTES, 'UTF-8'), // para exibição em innerHTML
    'remetente_nome'  => htmlspecialchars($email['remetente_nome'], ENT_QUOTES, 'UTF-8'),
    'remetente_email' => $email['remetente_email'], // vai para input.value, não precisa escapar entidades HTML
    'corpo'           => $email['corpo'], // texto puro, será escapado no JS antes de inserir
    'corpo_html'      => sanitize_email_html($email['corpo_html'] ?? ''),
    'data_formatada'  => format_date($email['data_envio'], 'd/m/Y H:i'),
]);
