<?php
/**
 * SiteCatalogo2 - Área do Cliente
 * Meus Pedidos (Orçamentos)
 */
require_once __DIR__ . '/includes/auth.php';

exigir_login_cliente();
$cliente_logado_atual = cliente_logado();

$page_active = 'pedidos';

$action = $_GET['action'] ?? 'list';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// ===== Detalhe de um pedido =====
if ($action === 'view' && $id) {
    $stmt = db()->prepare("SELECT * FROM " . table('orcamentos') . " WHERE id = ? AND cliente_id = ? LIMIT 1");
    $stmt->execute([$id, $cliente_logado_atual['id']]);
    $pedido = $stmt->fetch();

    if (!$pedido) {
        set_flash('error', 'Pedido não encontrado.');
        header('Location: pedidos.php'); exit;
    }

    $page_title = 'Pedido ' . $pedido['codigo'];

    $stmt_itens = db()->prepare("SELECT * FROM " . table('orcamento_itens') . " WHERE orcamento_id = ? ORDER BY id");
    $stmt_itens->execute([$pedido['id']]);
    $itens = $stmt_itens->fetchAll();

    require_once __DIR__ . '/includes/header.php';
    $st = status_pedido_label($pedido['status']);
    ?>

    <div class="page-header">
        <h1><i class="fas fa-file-invoice-dollar"></i> Pedido <?php echo sanitize($pedido['codigo']); ?></h1>
        <a href="pedidos.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Voltar</a>
    </div>

    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-info-circle"></i> Resumo do Pedido</h3>
        </div>
        <div class="card-body">
            <table style="width:100%;border-collapse:collapse;font-size:0.9rem;">
                <tr><td style="padding:8px 0;color:var(--gray-500);width:160px;">Código:</td><td><strong><?php echo sanitize($pedido['codigo']); ?></strong></td></tr>
                <tr><td style="padding:8px 0;color:var(--gray-500);">Status:</td><td><span class="badge-status <?php echo $st['cor']; ?>"><i class="fas <?php echo $st['icon']; ?>"></i> <?php echo sanitize($st['label']); ?></span></td></tr>
                <tr><td style="padding:8px 0;color:var(--gray-500);">Data do pedido:</td><td><?php echo format_date($pedido['created_at'], 'd/m/Y H:i'); ?></td></tr>
                <?php if (!empty($pedido['valor_total'])): ?>
                <tr><td style="padding:8px 0;color:var(--gray-500);">Valor total:</td><td><strong style="color:var(--primary);font-size:1.05rem;"><?php echo format_currency((float)$pedido['valor_total']); ?></strong></td></tr>
                <?php endif; ?>
                <?php if (!empty($pedido['desconto']) && (float)$pedido['desconto'] > 0): ?>
                <tr><td style="padding:8px 0;color:var(--gray-500);">Desconto:</td><td><?php echo format_currency((float)$pedido['desconto']); ?></td></tr>
                <?php endif; ?>
            </table>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-box"></i> Itens do Pedido</h3>
        </div>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr><th>Produto</th><th>Qtd</th><th>Unidade</th><th>Preço Unit.</th><th>Subtotal</th></tr>
                </thead>
                <tbody>
                    <?php if (empty($itens)): ?>
                    <tr><td colspan="5"><div class="empty-state-sm">Nenhum item encontrado para este pedido</div></td></tr>
                    <?php else: ?>
                    <?php foreach ($itens as $item): ?>
                    <tr>
                        <td><strong><?php echo sanitize($item['produto_nome']); ?></strong></td>
                        <td><?php echo (int)$item['quantidade']; ?></td>
                        <td><?php echo sanitize($item['unidade'] ?? 'un'); ?></td>
                        <td><?php echo format_currency((float)$item['preco_unitario']); ?></td>
                        <td><strong><?php echo format_currency((float)$item['subtotal']); ?></strong></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php if (!empty($pedido['observacoes'])): ?>
    <div class="card">
        <div class="card-header"><h3><i class="fas fa-comment-alt"></i> Observações</h3></div>
        <div class="card-body">
            <p style="font-size:0.9rem;color:var(--gray-700);white-space:pre-line;"><?php echo sanitize($pedido['observacoes']); ?></p>
        </div>
    </div>
    <?php endif; ?>

    <?php
    require_once __DIR__ . '/includes/footer.php';
    exit;
}

// ===== Lista de pedidos =====
$page_title = 'Meus Pedidos';

$status_filtro = $_GET['status'] ?? '';
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$per_page = 10;

$where = ["cliente_id = ?"]; $params = [$cliente_logado_atual['id']];
if ($status_filtro) { $where[] = "status = ?"; $params[] = $status_filtro; }
$where_sql = implode(' AND ', $where);

$stmt_count = db()->prepare("SELECT COUNT(*) FROM " . table('orcamentos') . " WHERE {$where_sql}");
$stmt_count->execute($params);
$total = (int)$stmt_count->fetchColumn();

$pagination = paginate($total, $page, $per_page);

$stmt_list = db()->prepare("SELECT * FROM " . table('orcamentos') . " WHERE {$where_sql} ORDER BY created_at DESC LIMIT {$pagination['offset']}, {$per_page}");
$stmt_list->execute($params);
$pedidos = $stmt_list->fetchAll();

require_once __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <h1><i class="fas fa-file-invoice-dollar"></i> Meus Pedidos</h1>
</div>

<div class="card">
    <div class="card-body" style="padding-bottom:0;">
        <form method="GET" class="form-row" style="grid-template-columns: 1fr auto auto; align-items:end; gap:12px;">
            <div class="form-group" style="margin-bottom:14px;">
                <label>Status</label>
                <select name="status">
                    <option value="">Todos os status</option>
                    <option value="novo" <?php echo $status_filtro==='novo'?'selected':''; ?>>Novo / Recebido</option>
                    <option value="em_analise" <?php echo $status_filtro==='em_analise'?'selected':''; ?>>Em análise</option>
                    <option value="aprovado" <?php echo $status_filtro==='aprovado'?'selected':''; ?>>Aprovado</option>
                    <option value="em_producao" <?php echo $status_filtro==='em_producao'?'selected':''; ?>>Em produção</option>
                    <option value="enviado" <?php echo $status_filtro==='enviado'?'selected':''; ?>>Enviado</option>
                    <option value="concluido" <?php echo $status_filtro==='concluido'?'selected':''; ?>>Concluído</option>
                    <option value="cancelado" <?php echo $status_filtro==='cancelado'?'selected':''; ?>>Cancelado</option>
                </select>
            </div>
            <button type="submit" class="btn btn-primary" style="margin-bottom:14px;"><i class="fas fa-search"></i> Filtrar</button>
            <?php if ($status_filtro): ?><a href="pedidos.php" class="btn btn-outline" style="margin-bottom:14px;">Limpar</a><?php endif; ?>
        </form>
    </div>

    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr><th>Código</th><th>Data</th><th>Valor Total</th><th>Status</th><th>Ações</th></tr>
            </thead>
            <tbody>
                <?php if (empty($pedidos)): ?>
                <tr><td colspan="5">
                    <div class="empty-state">
                        <i class="fas fa-file-invoice-dollar"></i>
                        <p>Você ainda não tem pedidos<?php echo $status_filtro ? ' com este status' : ''; ?>.</p>
                        <p style="margin-top:8px;"><a href="/" class="btn btn-primary" style="margin-top:8px;"><i class="fas fa-store"></i> Ir para o catálogo</a></p>
                    </div>
                </td></tr>
                <?php else: ?>
                <?php foreach ($pedidos as $p):
                    $st = status_pedido_label($p['status']);
                ?>
                <tr>
                    <td><strong><?php echo sanitize($p['codigo']); ?></strong></td>
                    <td><?php echo format_date($p['created_at'], 'd/m/Y H:i'); ?></td>
                    <td><?php echo format_currency((float)$p['valor_total']); ?></td>
                    <td><span class="badge-status <?php echo $st['cor']; ?>"><i class="fas <?php echo $st['icon']; ?>"></i> <?php echo sanitize($st['label']); ?></span></td>
                    <td>
                        <a href="pedidos.php?action=view&id=<?php echo $p['id']; ?>" class="btn btn-sm btn-primary" title="Ver detalhes">
                            <i class="fas fa-eye"></i> Ver
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if (!empty($pedidos)): ?>
    <div class="card-body">
        <div style="display:flex;justify-content:space-between;align-items:center;">
            <small class="text-muted" style="color:var(--gray-500);">Mostrando <?php echo $pagination['start']; ?>–<?php echo $pagination['end']; ?> de <?php echo $total; ?> pedido(s)</small>
            <?php echo pagination_links($pagination, 'pedidos.php', array_filter(['status'=>$status_filtro])); ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
