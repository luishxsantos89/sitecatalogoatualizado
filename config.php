<?php
/**
 * SiteCatalogo - Configuração
 * Gerado automaticamente pelo instalador em: 18/06/2026 16:37
 * NÃO edite este arquivo manualmente a menos que saiba o que está fazendo.
 */

// ── Banco de dados ──────────────────────────────────────────────────
define('DB_HOST',   'localhost');
define('DB_NAME',   'sitecatalogo');
define('DB_USER',   'root');
define('DB_PASS',   '');
define('DB_PREFIX', 'sc_');

// ── URLs ────────────────────────────────────────────────────────────
define('SITE_URL',    'http://sitecatalogo.test');
define('ADMIN_URL',   'http://sitecatalogo.test/admin/');
define('ASSETS_URL',  'http://sitecatalogo.test/assets/');
define('UPLOADS_URL', 'http://sitecatalogo.test/uploads/');

// ── Caminhos ────────────────────────────────────────────────────────
if (!defined('ROOT_PATH'))    define('ROOT_PATH',    __DIR__);
if (!defined('UPLOADS_PATH')) define('UPLOADS_PATH', __DIR__ . '/uploads');

// ── Segurança ───────────────────────────────────────────────────────
define('SECRET_KEY',   '4d342f819455b974e09c234705379469894874b19361eafed1ef6bc770e0b347');
define('SESSION_NAME', 'sc2_session');

// ── Sistema ─────────────────────────────────────────────────────────
define('SITE_NAME',        'SiteCatalogo');
define('SITE_DESCRIPTION', '');
define('WHATSAPP',         '');
define('WHATSAPP_DEFAULT_MSG', 'Olá! Recebi seu orçamento e entrarei em contato em breve.');

if (!defined('ADMIN_ITEMS_PER_PAGE')) define('ADMIN_ITEMS_PER_PAGE', 20);
if (!defined('ITEMS_PER_PAGE'))       define('ITEMS_PER_PAGE', ADMIN_ITEMS_PER_PAGE);