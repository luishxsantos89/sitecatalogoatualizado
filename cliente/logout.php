<?php
/**
 * SiteCatalogo2 - Área do Cliente
 * Logout
 */
require_once __DIR__ . '/includes/auth.php';

encerrar_sessao_cliente();
set_flash('success', 'Você saiu da sua conta.');
header('Location: /');
exit;