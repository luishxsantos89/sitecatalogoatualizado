<?php
/**
 * SiteCatalogo2 - Dashboard
 */
require_once __DIR__ . '/includes/functions.php';

// === CONTROLE DE ACESSO ===
require_auth();
if (!check_permission('vendedor')) {
    header('Location: ' . admin_url());
    exit('Acesso negado.');
}

$page_title = 'Dashboard';

$counts = get_counts();
$fin = get_financeiro_resumo();

// Emails não lidos
$emails_novos = 0;
try {
    $emails_novos = (int)db()->query("SELECT COUNT(*) FROM " . table('emails') . " WHERE pasta = 'inbox' AND status = 'nao_lido'")->fetchColumn();
} catch (Exception $e) {}

// Últimos orçamentos
try {
    $stmt = db()->query("SELECT o.*, (SELECT COUNT(*) FROM " . table('orcamento_itens') . " WHERE orcamento_id = o.id) as total_itens FROM " . table('orcamentos') . " o ORDER BY o.created_at DESC LIMIT 5");
    $ultimos_orcamentos = $stmt->fetchAll();
} catch (Exception $e) { $ultimos_orcamentos = []; }

// Estoque baixo
try {
    $stmt = db()->query("SELECT p.*, c.nome as categoria_nome FROM " . table('produtos') . " p LEFT JOIN " . table('categorias') . " c ON p.categoria_id = c.id WHERE p.quantidade_estoque <= p.estoque_minimo AND p.ativo = 1 ORDER BY p.quantidade_estoque ASC LIMIT 5");
    $estoque_baixo_list = $stmt->fetchAll();
} catch (Exception $e) { $estoque_baixo_list = []; }

// Últimos lançamentos financeiros
try {
    $stmt = db()->query("SELECT l.*, fc.nome as categoria_nome FROM " . table('financeiro_lancamentos') . " l LEFT JOIN " . table('financeiro_categorias') . " fc ON l.categoria_id = fc.id ORDER BY l.created_at DESC LIMIT 5");
    $ultimos_lancamentos = $stmt->fetchAll();
} catch (Exception $e) { $ultimos_lancamentos = []; }

require_once __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <h1><i class="fas fa-chart-pie"></i> Dashboard</h1>
    <p class="text-muted">Visão geral do seu catálogo — <?php echo date('d/m/Y'); ?></p>
</div>

<!-- Stats Cards -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon blue"><i class="fas fa-box-open"></i></div>
        <div class="stat-info">
            <span class="stat-number"><?php echo $counts['produtos']; ?></span>
            <span class="stat-label">Produtos</span>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon purple"><i class="fas fa-tags"></i></div>
        <div class="stat-info">
            <span class="stat-number"><?php echo $counts['categorias']; ?></span>
            <span class="stat-label">Categorias</span>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon orange"><i class="fas fa-file-invoice-dollar"></i></div>
        <div class="stat-info">
            <span class="stat-number"><?php echo $counts['orcamentos_novos']; ?></span>
            <span class="stat-label">Orçamentos Novos</span>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon green"><i class="fas fa-users"></i></div>
        <div class="stat-info">
            <span class="stat-number"><?php echo $counts['clientes']; ?></span>
            <span class="stat-label">Clientes</span>
        </div>
    </div>
    <!-- em fase de teste
    <div class="stat-card">
        <div class="stat-icon blue"><i class="fas fa-envelope"></i></div>
        <div class="stat-info">
            <span class="stat-number"><?php echo $emails_novos; ?></span>
            <span class="stat-label">Emails Novos</span>
        </div>
    </div>-->
    <div class="stat-card">
        <div class="stat-icon red"><i class="fas fa-exclamation-triangle"></i></div>
        <div class="stat-info">
            <span class="stat-number"><?php echo $counts['estoque_baixo']; ?></span>
            <span class="stat-label">Estoque Baixo</span>
        </div>
    </div>
</div>

<!-- Financeiro Resumo -->
<h2 style="font-size:1rem;font-weight:700;color:var(--gray-800);margin-bottom:12px;">
    <i class="fas fa-dollar-sign" style="color:var(--primary);"></i> Financeiro - <?php echo date('m/Y'); ?>
</h2>
<div class="fin-stat-grid" style="margin-bottom:24px;">
    <div class="fin-stat saldo">
        <div class="fin-stat-label"><i class="fas fa-university"></i> Saldo Total</div>
        <div class="fin-stat-value"><?php echo format_currency($fin['saldo']); ?></div>
        <div class="fin-stat-sub">Soma de todas as contas</div>
    </div>
    <div class="fin-stat receita">
        <div class="fin-stat-label"><i class="fas fa-arrow-up"></i> Receitas do Mês</div>
        <div class="fin-stat-value"><?php echo format_currency($fin['receitas']); ?></div>
        <div class="fin-stat-sub">Lançamentos pagos</div>
    </div>
    <div class="fin-stat despesa">
        <div class="fin-stat-label"><i class="fas fa-arrow-down"></i> Despesas do Mês</div>
        <div class="fin-stat-value"><?php echo format_currency($fin['despesas']); ?></div>
        <div class="fin-stat-sub">Lançamentos pagos</div>
    </div>
    <div class="fin-stat pendente">
        <div class="fin-stat-label"><i class="fas fa-clock"></i> A Receber/Pagar</div>
        <div class="fin-stat-value" style="color:var(--warning);"><?php echo format_currency($fin['pendentes']); ?></div>
        <div class="fin-stat-sub">Lançamentos pendentes</div>
    </div>
    <div class="fin-stat vencido">
        <div class="fin-stat-label"><i class="fas fa-exclamation-circle"></i> Vencidos</div>
        <div class="fin-stat-value"><?php echo format_currency($fin['vencidos']); ?></div>
        <div class="fin-stat-sub">Precisam de atenção!</div>
    </div>
</div>

<div class="dashboard-grid">
    <!-- Últimos Orçamentos -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-file-invoice-dollar"></i> Últimos Orçamentos</h3>
            <a href="orcamentos.php" class="btn btn-sm btn-secondary">Ver Todos</a>
        </div>
        <div class="card-body" style="padding:0;">
            <?php if (empty($ultimos_orcamentos)): ?>
            <div class="empty-state-sm"><p>Nenhum orçamento ainda</p></div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table table-sm">
                    <thead><tr><th>Código</th><th>Cliente</th><th>Status</th><th>Total</th><th>Data</th></tr></thead>
                    <tbody>
                        <?php foreach ($ultimos_orcamentos as $o): ?>
                        <tr>
                            <td><a href="orcamentos.php?action=view&id=<?php echo $o['id']; ?>" style="color:var(--primary);font-weight:600;"><?php echo sanitize($o['codigo']); ?></a></td>
                            <td><?php echo sanitize($o['cliente_nome']); ?></td>
                            <td><span class="badge-status status-<?php echo $o['status']; ?>"><?php echo ucfirst(str_replace('_',' ',$o['status'])); ?></span></td>
                            <td><?php echo format_currency((float)$o['valor_total']); ?></td>
                            <td><?php echo format_date($o['created_at'], 'd/m/Y'); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Últimos Lançamentos -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-dollar-sign"></i> Últimos Lançamentos</h3>
            <a href="financeiro.php" class="btn btn-sm btn-secondary">Ver Todos</a>
        </div>
        <div class="card-body" style="padding:0;">
            <?php if (empty($ultimos_lancamentos)): ?>
            <div class="empty-state-sm"><p>Nenhum lançamento registrado</p></div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table table-sm">
                    <thead><tr><th>Descrição</th><th>Tipo</th><th>Valor</th><th>Status</th></tr></thead>
                    <tbody>
                        <?php foreach ($ultimos_lancamentos as $l): ?>
                        <tr>
                            <td><?php echo sanitize($l['descricao']); ?></td>
                            <td><span class="badge-status status-<?php echo $l['tipo']; ?>"><?php echo ucfirst($l['tipo']); ?></span></td>
                            <td style="font-weight:600;color:<?php echo $l['tipo']==='receita'?'var(--success)':'var(--danger)'; ?>"><?php echo format_currency((float)$l['valor']); ?></td>
                            <td><span class="badge-status status-<?php echo $l['status']; ?>"><?php echo ucfirst($l['status']); ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Estoque Baixo -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-exclamation-triangle"></i> Estoque Baixo</h3>
            <a href="estoque.php" class="btn btn-sm btn-secondary">Ver Todos</a>
        </div>
        <div class="card-body" style="padding:0;">
            <?php if (empty($estoque_baixo_list)): ?>
            <div class="empty-state-sm"><p>Todos os produtos com estoque ok!</p></div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table table-sm">
                    <thead><tr><th>Produto</th><th>Atual</th><th>Mínimo</th></tr></thead>
                    <tbody>
                        <?php foreach ($estoque_baixo_list as $p): ?>
                        <tr>
                            <td><?php echo sanitize($p['nome']); ?></td>
                            <td><span class="text-danger"><strong><?php echo $p['quantidade_estoque']; ?></strong></span></td>
                            <td><?php echo $p['estoque_minimo']; ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>