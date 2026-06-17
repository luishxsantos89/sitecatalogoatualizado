<?php
/**
 * API - Verificar novos orçamentos (para alerta sonoro)
 */
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/functions.php';

header('Content-Type: application/json');

// Apenas usuários logados
session_check();
if (!is_logged_in()) {
    echo json_encode(['count' => 0, 'error' => 'unauthorized']);
    exit;
}

try {
    $count = (int)db()->query("SELECT COUNT(*) FROM " . table('orcamentos') . " WHERE status = 'novo' AND created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)")->fetchColumn();
    echo json_encode(['count' => $count, 'ok' => true]);
} catch (Exception $e) {
    echo json_encode(['count' => 0, 'ok' => false]);
}
