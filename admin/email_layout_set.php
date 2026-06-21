<?php
/**
 * email_layout_set.php
 * Endpoint AJAX — salva a preferência de layout (lista | dividido)
 * acionado pelos botões de alternância no topo da página de Email.
 */
require_once __DIR__ . '/includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

if (!is_logged_in() || !check_permission('admin')) {
    echo json_encode(['ok' => false, 'erro' => 'Acesso negado.']);
    exit;
}

$layout = $_POST['layout'] ?? 'lista';
if (!in_array($layout, ['lista', 'dividido'], true)) {
    $layout = 'lista';
}

set_config('email_layout', $layout);
echo json_encode(['ok' => true, 'layout' => $layout]);
