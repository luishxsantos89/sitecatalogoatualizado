<?php
/**
 * SiteCatalogo2 - Funções do Painel Admin
 */

require_once dirname(dirname(dirname(__FILE__))) . '/includes/functions.php';

// Verificar autenticação em todas as páginas admin
session_check();
if (!is_logged_in()) {
    header('Location: ' . (defined('ADMIN_URL') ? ADMIN_URL : '../') . 'login.php');
    exit;
}

// Página atual para menu ativo
$current_page = basename($_SERVER['PHP_SELF'], '.php');

// Upload helper
function handle_upload(array $file, string $folder = 'produtos'): ?string {
    $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    return upload_file($file, $folder, $allowed);
}

// Gerar slug único (wrapper)
function unique_slug_admin(string $table, string $slug, ?int $exclude_id = null): string {
    return unique_slug($table, $slug, $exclude_id);
}

// Contadores para dashboard
function get_counts(): array {
    try {
        return [
            'produtos'           => (int)db()->query("SELECT COUNT(*) FROM " . table('produtos'))->fetchColumn(),
            'categorias'         => (int)db()->query("SELECT COUNT(*) FROM " . table('categorias'))->fetchColumn(),
            'orcamentos_novos'   => (int)db()->query("SELECT COUNT(*) FROM " . table('orcamentos') . " WHERE status = 'novo'")->fetchColumn(),
            'orcamentos_total'   => (int)db()->query("SELECT COUNT(*) FROM " . table('orcamentos'))->fetchColumn(),
            'clientes'           => (int)db()->query("SELECT COUNT(*) FROM " . table('clientes'))->fetchColumn(),
            'usuarios'           => (int)db()->query("SELECT COUNT(*) FROM " . table('usuarios') . " WHERE status = 'ativo'")->fetchColumn(),
            'estoque_baixo'      => (int)db()->query("SELECT COUNT(*) FROM " . table('produtos') . " WHERE quantidade_estoque <= estoque_minimo AND ativo = 1")->fetchColumn(),
        ];
    } catch (Exception $e) {
        return array_fill_keys(['produtos','categorias','orcamentos_novos','orcamentos_total','clientes','usuarios','estoque_baixo'], 0);
    }
}

// Contar orçamentos pendentes
function count_orcamentos_pendentes(): int {
    try {
        return (int)db()->query("SELECT COUNT(*) FROM " . table('orcamentos') . " WHERE status IN ('novo','pendente')")->fetchColumn();
    } catch (Exception $e) {
        return 0;
    }
}

// Totais financeiros para dashboard
function get_financeiro_resumo(): array {
    try {
        $mes = date('Y-m');
        $receitas = (float)db()->query("SELECT COALESCE(SUM(valor),0) FROM " . table('financeiro_lancamentos') . " WHERE tipo='receita' AND status='pago' AND DATE_FORMAT(data_pagamento,'%Y-%m')='{$mes}'")->fetchColumn();
        $despesas = (float)db()->query("SELECT COALESCE(SUM(valor),0) FROM " . table('financeiro_lancamentos') . " WHERE tipo='despesa' AND status='pago' AND DATE_FORMAT(data_pagamento,'%Y-%m')='{$mes}'")->fetchColumn();
        $pendentes = (float)db()->query("SELECT COALESCE(SUM(valor),0) FROM " . table('financeiro_lancamentos') . " WHERE status='pendente' AND data_vencimento >= CURDATE()")->fetchColumn();
        $vencidos  = (float)db()->query("SELECT COALESCE(SUM(valor),0) FROM " . table('financeiro_lancamentos') . " WHERE status='pendente' AND data_vencimento < CURDATE()")->fetchColumn();
        $saldo     = (float)db()->query("SELECT COALESCE(SUM(saldo_atual),0) FROM " . table('financeiro_contas') . " WHERE ativo=1")->fetchColumn();
        return compact('receitas','despesas','pendentes','vencidos','saldo');
    } catch (Exception $e) {
        return ['receitas'=>0,'despesas'=>0,'pendentes'=>0,'vencidos'=>0,'saldo'=>0];
    }
}
