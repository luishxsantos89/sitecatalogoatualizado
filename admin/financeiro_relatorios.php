<?php
/**
 * SiteCatalogo2 - Relatórios Financeiros
 */
require_once __DIR__ . '/includes/functions.php';
if (!check_permission('gerente')) { set_flash('error','Acesso restrito ao Financeiro'); header('Location: ./'); exit; }
$page_title = 'Relatórios Financeiros';

$ano    = isset($_GET['ano']) ? (int)$_GET['ano'] : (int)date('Y');
$mes    = isset($_GET['mes']) ? (int)$_GET['mes'] : 0; // 0 = ano todo

// Receitas e despesas por mês
$meses_labels = ['Jan','Fev','Mar','Abr','Mai','Jun','Jul','Ago','Set','Out','Nov','Dez'];
$dados_meses = [];
for ($m = 1; $m <= 12; $m++) {
    $mes_str = sprintf('%04d-%02d', $ano, $m);
    $r = (float)db()->query("SELECT COALESCE(SUM(valor),0) FROM " . table('financeiro_lancamentos') . " WHERE tipo='receita' AND status='pago' AND DATE_FORMAT(data_pagamento,'%Y-%m')='{$mes_str}'")->fetchColumn();
    $d = (float)db()->query("SELECT COALESCE(SUM(valor),0) FROM " . table('financeiro_lancamentos') . " WHERE tipo='despesa' AND status='pago' AND DATE_FORMAT(data_pagamento,'%Y-%m')='{$mes_str}'")->fetchColumn();
    $dados_meses[$m] = ['receitas'=>$r, 'despesas'=>$d, 'saldo'=>$r-$d];
}

// Despesas por categoria
$desp_cat = db()->query("SELECT fc.nome, fc.cor, COALESCE(SUM(l.valor),0) as total FROM " . table('financeiro_lancamentos') . " l LEFT JOIN " . table('financeiro_categorias') . " fc ON l.categoria_id = fc.id WHERE l.tipo='despesa' AND l.status='pago' AND YEAR(l.data_pagamento)={$ano} GROUP BY fc.id, fc.nome, fc.cor ORDER BY total DESC LIMIT 10")->fetchAll();

// Receitas por categoria
$rec_cat = db()->query("SELECT fc.nome, fc.cor, COALESCE(SUM(l.valor),0) as total FROM " . table('financeiro_lancamentos') . " l LEFT JOIN " . table('financeiro_categorias') . " fc ON l.categoria_id = fc.id WHERE l.tipo='receita' AND l.status='pago' AND YEAR(l.data_pagamento)={$ano} GROUP BY fc.id, fc.nome, fc.cor ORDER BY total DESC LIMIT 10")->fetchAll();

// Totais anuais
$total_rec_ano = array_sum(array_column($dados_meses, 'receitas'));
$total_des_ano = array_sum(array_column($dados_meses, 'despesas'));
$lucro_ano = $total_rec_ano - $total_des_ano;

// Top clientes pagantes
$top_clientes = db()->query("SELECT c.nome_razaosocial, COALESCE(SUM(l.valor),0) as total FROM " . table('financeiro_lancamentos') . " l LEFT JOIN " . table('clientes') . " c ON l.cliente_id = c.id WHERE l.tipo='receita' AND l.status='pago' AND YEAR(l.data_pagamento)={$ano} AND l.cliente_id IS NOT NULL GROUP BY l.cliente_id ORDER BY total DESC LIMIT 5")->fetchAll();

// Contas
$contas = db()->query("SELECT * FROM " . table('financeiro_contas') . " WHERE ativo=1 ORDER BY nome")->fetchAll();

require_once __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <h1><i class="fas fa-chart-bar"></i> Relatórios Financeiros</h1>
    <form method="GET" style="display:flex;gap:8px;align-items:center;">
        <select name="ano" class="form-control" style="width:auto;" onchange="this.form.submit()">
            <?php for ($y = date('Y'); $y >= date('Y')-5; $y--): ?>
            <option value="<?php echo $y; ?>" <?php echo selected($ano,$y); ?>><?php echo $y; ?></option>
            <?php endfor; ?>
        </select>
    </form>
</div>

<!-- Totais do ano -->
<div class="fin-stat-grid">
    <div class="fin-stat receita">
        <div class="fin-stat-label"><i class="fas fa-arrow-up"></i> Total Receitas <?php echo $ano; ?></div>
        <div class="fin-stat-value"><?php echo format_currency($total_rec_ano); ?></div>
    </div>
    <div class="fin-stat despesa">
        <div class="fin-stat-label"><i class="fas fa-arrow-down"></i> Total Despesas <?php echo $ano; ?></div>
        <div class="fin-stat-value"><?php echo format_currency($total_des_ano); ?></div>
    </div>
    <div class="fin-stat <?php echo $lucro_ano>=0?'saldo':'vencido'; ?>">
        <div class="fin-stat-label"><i class="fas fa-balance-scale"></i> Resultado <?php echo $ano; ?></div>
        <div class="fin-stat-value" style="color:<?php echo $lucro_ano>=0?'var(--success)':'var(--danger)'; ?>"><?php echo format_currency($lucro_ano); ?></div>
    </div>
    <?php foreach ($contas as $c): ?>
    <div class="fin-stat" style="border-color:<?php echo $c['cor']; ?>">
        <div class="fin-stat-label"><i class="fas fa-university"></i> <?php echo sanitize($c['nome']); ?></div>
        <div class="fin-stat-value"><?php echo format_currency((float)$c['saldo_atual']); ?></div>
    </div>
    <?php endforeach; ?>
</div>

<!-- LINHA 1: 2 colunas -->
<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:20px;grid-auto-rows:420px;">
    <!-- Gráfico Mensal -->
    <div class="card" style="display:flex;flex-direction:column;">
        <div class="card-header" style="flex-shrink:0;"><h3><i class="fas fa-chart-line"></i> Fluxo Mensal - <?php echo $ano; ?></h3></div>
        <div class="card-body" style="flex:1;min-height:0;position:relative;padding:16px;">
            <canvas id="chartMensal"></canvas>
        </div>
    </div>

    <!-- Tabela Mensal -->
    <div class="card" style="display:flex;flex-direction:column;overflow:hidden;">
        <div class="card-header" style="flex-shrink:0;"><h3><i class="fas fa-table"></i> Resumo por Mês</h3></div>
        <div class="table-responsive" style="flex:1;overflow-y:auto;">
            <table class="table">
                <thead><tr><th>Mês</th><th>Receitas</th><th>Despesas</th><th>Saldo</th></tr></thead>
                <tbody>
                    <?php foreach ($dados_meses as $m => $dm): ?>
                    <tr>
                        <td><strong><?php echo $meses_labels[$m-1]; ?></strong></td>
                        <td style="color:var(--success);"><?php echo format_currency($dm['receitas']); ?></td>
                        <td style="color:var(--danger);"><?php echo format_currency($dm['despesas']); ?></td>
                        <td style="font-weight:700;color:<?php echo $dm['saldo']>=0?'var(--success)':'var(--danger)'; ?>"><?php echo format_currency($dm['saldo']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr style="background:var(--gray-50);font-weight:700;">
                        <td>TOTAL</td>
                        <td style="color:var(--success);"><?php echo format_currency($total_rec_ano); ?></td>
                        <td style="color:var(--danger);"><?php echo format_currency($total_des_ano); ?></td>
                        <td style="color:<?php echo $lucro_ano>=0?'var(--success)':'var(--danger)'; ?>"><?php echo format_currency($lucro_ano); ?></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>

<!-- LINHA 2: 3 colunas -->
<div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:20px;grid-auto-rows:400px;">
    <!-- Top Clientes -->
    <div class="card" style="display:flex;flex-direction:column;overflow:hidden;">
        <div class="card-header" style="flex-shrink:0;"><h3><i class="fas fa-trophy"></i> Principais Clientes <?php echo $ano; ?></h3></div>
        <div class="card-body" style="flex:1;overflow-y:auto;">
            <?php if (empty($top_clientes)): ?>
            <div class="empty-state-sm">Nenhum dado encontrado</div>
            <?php else: ?>
            <?php foreach ($top_clientes as $cli): ?>
            <div style="display:flex;justify-content:space-between;align-items:center;padding:10px 0;border-bottom:1px solid var(--gray-100);">
                <span><?php echo sanitize($cli['nome_razaosocial']??'Sem nome'); ?></span>
                <strong style="color:var(--success);"><?php echo format_currency((float)$cli['total']); ?></strong>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Despesas por Categoria -->
    <div class="card" style="display:flex;flex-direction:column;overflow:hidden;">
        <div class="card-header" style="flex-shrink:0;"><h3><i class="fas fa-chart-pie"></i> Despesas por Categoria</h3></div>
        <div class="card-body" style="flex:1;overflow-y:auto;">
            <?php if (empty($desp_cat)): ?>
            <div class="empty-state-sm">Nenhum dado</div>
            <?php else: ?>
            <?php $total_desp = array_sum(array_column($desp_cat, 'total')); ?>
            <?php foreach ($desp_cat as $c): $pct = $total_desp > 0 ? round((float)$c['total']/$total_desp*100, 1) : 0; ?>
            <div style="margin-bottom:12px;">
                <div style="display:flex;justify-content:space-between;margin-bottom:4px;">
                    <span style="display:flex;align-items:center;gap:6px;">
                        <span style="width:10px;height:10px;border-radius:50%;background:<?php echo $c['cor']??'#ccc'; ?>;display:inline-block;"></span>
                        <?php echo sanitize($c['nome']??'Sem categoria'); ?>
                    </span>
                    <strong><?php echo format_currency((float)$c['total']); ?> (<?php echo $pct; ?>%)</strong>
                </div>
                <div style="background:var(--gray-200);border-radius:4px;height:6px;">
                    <div style="background:<?php echo $c['cor']??'var(--danger)'; ?>;width:<?php echo $pct; ?>%;height:100%;border-radius:4px;"></div>
                </div>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Receitas por Categoria -->
    <div class="card" style="display:flex;flex-direction:column;overflow:hidden;">
        <div class="card-header" style="flex-shrink:0;"><h3><i class="fas fa-chart-pie"></i> Receitas por Categoria</h3></div>
        <div class="card-body" style="flex:1;overflow-y:auto;">
            <?php if (empty($rec_cat)): ?>
            <div class="empty-state-sm">Nenhum dado</div>
            <?php else: ?>
            <?php $total_rec = array_sum(array_column($rec_cat, 'total')); ?>
            <?php foreach ($rec_cat as $c): $pct = $total_rec > 0 ? round((float)$c['total']/$total_rec*100, 1) : 0; ?>
            <div style="margin-bottom:12px;">
                <div style="display:flex;justify-content:space-between;margin-bottom:4px;">
                    <span style="display:flex;align-items:center;gap:6px;">
                        <span style="width:10px;height:10px;border-radius:50%;background:<?php echo $c['cor']??'#ccc'; ?>;display:inline-block;"></span>
                        <?php echo sanitize($c['nome']??'Sem categoria'); ?>
                    </span>
                    <strong><?php echo format_currency((float)$c['total']); ?> (<?php echo $pct; ?>%)</strong>
                </div>
                <div style="background:var(--gray-200);border-radius:4px;height:6px;">
                    <div style="background:<?php echo $c['cor']??'var(--success)'; ?>;width:<?php echo $pct; ?>%;height:100%;border-radius:4px;"></div>
                </div>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
const ctx = document.getElementById('chartMensal').getContext('2d');
new Chart(ctx, {
    type: 'bar',
    data: {
        labels: <?php echo json_encode($meses_labels); ?>,
        datasets: [
            {
                label: 'Receitas',
                data: <?php echo json_encode(array_values(array_column($dados_meses,'receitas'))); ?>,
                backgroundColor: 'rgba(34,197,94,0.7)',
                borderColor: '#22c55e',
                borderWidth: 1,
                borderRadius: 4,
            },
            {
                label: 'Despesas',
                data: <?php echo json_encode(array_values(array_column($dados_meses,'despesas'))); ?>,
                backgroundColor: 'rgba(239,68,68,0.7)',
                borderColor: '#ef4444',
                borderWidth: 1,
                borderRadius: 4,
            },
            {
                label: 'Saldo',
                data: <?php echo json_encode(array_values(array_column($dados_meses,'saldo'))); ?>,
                type: 'line',
                borderColor: '#3b82f6',
                backgroundColor: 'rgba(59,130,246,0.1)',
                pointBackgroundColor: '#3b82f6',
                borderWidth: 2,
                tension: 0.3,
                fill: false,
            }
        ]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { position: 'top' } },
        scales: {
            y: { ticks: { callback: v => 'R$ ' + v.toLocaleString('pt-BR') } }
        }
    }
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>