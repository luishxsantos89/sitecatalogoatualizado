<?php
/**
 * email_cron.php
 * Script para sincronização automática de email via CRON do servidor.
 * Roda sem precisar do navegador aberto (diferente do setInterval do JS,
 * que só sincroniza enquanto a página de Email está aberta no navegador).
 *
 * COMO CONFIGURAR (cPanel → Cron Jobs):
 *   */5 * * * * php /home/SEU_USUARIO/public_html/admin/email_cron.php
 *
 * No Laragon/Windows (Agendador de Tarefas):
 *   Programa: C:\laragon\bin\php\php-x.x.x\php.exe
 *   Argumentos: C:\laragon\www\sitecatalogo\admin\email_cron.php
 *   Repetir a cada: 5 minutos
 *
 * Este script SÓ executa por linha de comando (CLI) ou com uma chave
 * secreta na URL — nunca diretamente pelo navegador sem autenticação,
 * por segurança.
 */

// Permite execução via CLI (cron real) OU via HTTP com chave secreta
$via_cli = (php_sapi_name() === 'cli');
$chave_secreta = 'altere_esta_chave_2026'; // ⚠ TROQUE por uma chave única antes de usar via HTTP

if (!$via_cli) {
    $chave_informada = $_GET['key'] ?? '';
    if (!hash_equals($chave_secreta, $chave_informada)) {
        http_response_code(403);
        exit('Acesso negado.');
    }
    header('Content-Type: text/plain; charset=utf-8');
}

require_once __DIR__ . '/includes/functions.php';

// Reaproveita a lógica de sincronização do email.php
// (definições de imap_cfg, sincronizar_imap, etc.)
require_once __DIR__ . '/includes/email_sync_lib.php';

if (get_config('email_sync_auto', '0') !== '1') {
    echo "[" . date('Y-m-d H:i:s') . "] Sincronização automática está DESATIVADA nas configurações. Nada a fazer.\n";
    exit;
}

if (!function_exists('imap_open')) {
    echo "[" . date('Y-m-d H:i:s') . "] ERRO: Extensão IMAP não habilitada no PHP.\n";
    exit(1);
}

$resultado = sincronizar_imap('inbox');

if ($resultado['ok']) {
    echo "[" . date('Y-m-d H:i:s') . "] OK — {$resultado['novos']} novo(s) email(ns) de {$resultado['total']} encontrados.\n";
} else {
    echo "[" . date('Y-m-d H:i:s') . "] ERRO — {$resultado['erro']}\n";
    exit(1);
}
