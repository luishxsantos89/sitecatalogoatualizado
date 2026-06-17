<?php
/**
 * SiteCatalogo2 - Financeiro (Lançamentos)
 */
require_once __DIR__ . '/includes/functions.php';
if (!check_permission('gerente')) { set_flash('error','Acesso restrito ao Financeiro'); header('Location: ./'); exit; }
$page_title = 'Financeiro';

$action = $_GET['action'] ?? 'list';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// AJAX - Baixar lançamento (marcar como pago)
if ($action === 'baixar' && $id) {
    try {
        $lanc = db()->prepare("SELECT * FROM " . table('financeiro_lancamentos') . " WHERE id = ?");
        $lanc->execute([$id]); $l = $lanc->fetch();
        if ($l && $l['status'] === 'pendente') {
            $data_pag = $_GET['data'] ?? date('Y-m-d');
            db()->prepare("UPDATE " . table('financeiro_lancamentos') . " SET status = 'pago', data_pagamento = ? WHERE id = ?")->execute([$data_pag, $id]);

            // Atualizar saldo da conta
            if ($l['conta_id']) {
                $sinal = $l['tipo'] === 'receita' ? 1 : -1;
                db()->prepare("UPDATE " . table('financeiro_contas') . " SET saldo_atual = saldo_atual + (? * ?) WHERE id = ?")->execute([$sinal, $l['valor'], $l['conta_id']]);
            }
            set_flash('success', 'Lançamento baixado com sucesso!');
        }
    } catch (Exception $e) { set_flash('error', 'Erro: ' . $e->getMessage()); }
    header('Location: financeiro.php'); exit;
}

// Cancelar
if ($action === 'cancelar' && $id) {
    try {
        $l = db()->prepare("SELECT * FROM " . table('financeiro_lancamentos') . " WHERE id = ?");
        $l->execute([$id]); $lanc = $l->fetch();
        if ($lanc && $lanc['status'] === 'pago' && $lanc['conta_id']) {
            // Estornar saldo
            $sinal = $lanc['tipo'] === 'receita' ? -1 : 1;
            db()->prepare("UPDATE " . table('financeiro_contas') . " SET saldo_atual = saldo_atual + (? * ?) WHERE id = ?")->execute([$sinal, $lanc['valor'], $lanc['conta_id']]);
        }
        db()->prepare("UPDATE " . table('financeiro_lancamentos') . " SET status = 'cancelado' WHERE id = ?")->execute([$id]);
        set_flash('success', 'Lançamento cancelado!');
    } catch (Exception $e) { set_flash('error', 'Erro ao cancelar'); }
    header('Location: financeiro.php'); exit;
}

// Deletar
if ($action === 'delete' && $id) {
    try {
        $l = db()->prepare("SELECT * FROM " . table('financeiro_lancamentos') . " WHERE id = ?");
        $l->execute([$id]); $lanc = $l->fetch();
        if ($lanc && $lanc['status'] === 'pago' && $lanc['conta_id']) {
            $sinal = $lanc['tipo'] === 'receita' ? -1 : 1;
            db()->prepare("UPDATE " . table('financeiro_contas') . " SET saldo_atual = saldo_atual + (? * ?) WHERE id = ?")->execute([$sinal, $lanc['valor'], $lanc['conta_id']]);
        }
        db()->prepare("DELETE FROM " . table('financeiro_lancamentos') . " WHERE id = ?")->execute([$id]);
        set_flash('success', 'Lançamento excluído!');
    } catch (Exception $e) { set_flash('error', 'Erro: ' . $e->getMessage()); }
    header('Location: financeiro.php'); exit;
}

// Salvar lançamento
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $descricao     = trim($_POST['descricao'] ?? '');
    $tipo          = $_POST['tipo'] ?? 'despesa';
    $valor         = (float)str_replace(',', '.', $_POST['valor'] ?? 0);
    $data_venc     = $_POST['data_vencimento'] ?? date('Y-m-d');
    $data_pag      = !empty($_POST['data_pagamento']) ? $_POST['data_pagamento'] : null;
    $categoria_id  = !empty($_POST['categoria_id']) ? (int)$_POST['categoria_id'] : null;
    $conta_id      = !empty($_POST['conta_id']) ? (int)$_POST['conta_id'] : null;
    $cliente_id    = !empty($_POST['cliente_id']) ? (int)$_POST['cliente_id'] : null;
    $orcamento_id  = !empty($_POST['orcamento_id']) ? (int)$_POST['orcamento_id'] : null;
    $status        = $_POST['status'] ?? 'pendente';
    $forma_pag     = trim($_POST['forma_pagamento'] ?? '');
    $parcelas      = max(1, (int)($_POST['parcelas'] ?? 1));
    $observacoes   = trim($_POST['observacoes'] ?? '');

    if (empty($descricao) || $valor <= 0) {
        set_flash('error', 'Descrição e valor são obrigatórios');
    } else {
        try {
            if ($id) {
                // Editar
                $old = db()->prepare("SELECT * FROM " . table('financeiro_lancamentos') . " WHERE id = ?"); $old->execute([$id]); $oldl = $old->fetch();

                // Estornar saldo antigo se era pago
                if ($oldl && $oldl['status'] === 'pago' && $oldl['conta_id']) {
                    $sinal = $oldl['tipo'] === 'receita' ? -1 : 1;
                    db()->prepare("UPDATE " . table('financeiro_contas') . " SET saldo_atual = saldo_atual + (? * ?) WHERE id = ?")->execute([$sinal, $oldl['valor'], $oldl['conta_id']]);
                }

                db()->prepare("UPDATE " . table('financeiro_lancamentos') . " SET tipo=?,descricao=?,valor=?,data_vencimento=?,data_pagamento=?,categoria_id=?,conta_id=?,cliente_id=?,orcamento_id=?,status=?,forma_pagamento=?,observacoes=?,usuario_id=? WHERE id = ?")
                    ->execute([$tipo,$descricao,$valor,$data_venc,$data_pag,$categoria_id,$conta_id,$cliente_id,$orcamento_id,$status,$forma_pag,$observacoes,$_SESSION['admin_id']??null,$id]);

                // Aplicar saldo novo
                if ($status === 'pago' && $conta_id) {
                    $sinal = $tipo === 'receita' ? 1 : -1;
                    db()->prepare("UPDATE " . table('financeiro_contas') . " SET saldo_atual = saldo_atual + (? * ?) WHERE id = ?")->execute([$sinal, $valor, $conta_id]);
                }
                set_flash('success', 'Lançamento atualizado!');
                header('Location: financeiro.php'); exit;
            } else {
                // Criar (com suporte a parcelas)
                $grupo = ($parcelas > 1) ? bin2hex(random_bytes(8)) : null;
                for ($p = 1; $p <= $parcelas; $p++) {
                    $dvenc = $parcelas > 1 ? date('Y-m-d', strtotime("+".($p-1)." month", strtotime($data_venc))) : $data_venc;
                    $dpag  = ($p === 1 && $data_pag) ? $data_pag : null;
                    $st    = ($dpag || $status === 'pago') ? 'pago' : 'pendente';
                    $desc  = $parcelas > 1 ? "{$descricao} ({$p}/{$parcelas})" : $descricao;

                    db()->prepare("INSERT INTO " . table('financeiro_lancamentos') . " (tipo,descricao,valor,data_vencimento,data_pagamento,categoria_id,conta_id,cliente_id,orcamento_id,status,forma_pagamento,parcelas,parcela_atual,grupo_parcelas,observacoes,usuario_id) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)")
                        ->execute([$tipo,$desc,$valor,$dvenc,$dpag,$categoria_id,$conta_id,$cliente_id,$orcamento_id,$st,$forma_pag,$parcelas,$p,$grupo,$observacoes,$_SESSION['admin_id']??null]);

                    // Atualizar saldo da conta se pago
                    if ($st === 'pago' && $conta_id) {
                        $sinal = $tipo === 'receita' ? 1 : -1;
                        db()->prepare("UPDATE " . table('financeiro_contas') . " SET saldo_atual = saldo_atual + (? * ?) WHERE id = ?")->execute([$sinal, $valor, $conta_id]);
                    }
                }
                $msg = $parcelas > 1 ? "{$parcelas} parcelas criadas!" : 'Lançamento criado!';
                set_flash('success', $msg);
                header('Location: financeiro.php'); exit;
            }
        } catch (Exception $e) { set_flash('error', 'Erro: ' . $e->getMessage()); }
    }
}

// Buscar lançamento para editar
$lancamento = null;
if ($action === 'edit' && $id) {
    $stmt = db()->prepare("SELECT * FROM " . table('financeiro_lancamentos') . " WHERE id = ?");
    $stmt->execute([$id]); $lancamento = $stmt->fetch();
}

// Dados para filtros e forms
$categorias = db()->query("SELECT * FROM " . table('financeiro_categorias') . " WHERE ativo = 1 ORDER BY tipo, nome")->fetchAll();
$contas = db()->query("SELECT * FROM " . table('financeiro_contas') . " WHERE ativo = 1 ORDER BY nome")->fetchAll();
$clientes_list = db()->query("SELECT id, nome_razaosocial FROM " . table('clientes') . " ORDER BY nome_razaosocial LIMIT 200")->fetchAll();

// Filtros
$filtro_tipo   = $_GET['tipo'] ?? '';
$filtro_status = $_GET['filtro'] ?? $_GET['status'] ?? '';
$filtro_mes    = $_GET['mes'] ?? date('Y-m');
$filtro_conta  = isset($_GET['conta']) ? (int)$_GET['conta'] : 0;
$page = isset($_GET['page']) ? max(1,(int)$_GET['page']) : 1;

$where = ["1=1"]; $params = [];
if ($filtro_tipo) { $where[] = "l.tipo = ?"; $params[] = $filtro_tipo; }
if ($filtro_mes) { $where[] = "DATE_FORMAT(l.data_vencimento,'%Y-%m') = ?"; $params[] = $filtro_mes; }
if ($filtro_conta) { $where[] = "l.conta_id = ?"; $params[] = $filtro_conta; }
if ($filtro_status === 'vencidos') {
    $where[] = "l.status = 'pendente' AND l.data_vencimento < CURDATE()";
} elseif ($filtro_status) {
    $where[] = "l.status = ?"; $params[] = $filtro_status;
}
$where_sql = implode(' AND ', $where);

$stmt_count = db()->prepare("SELECT COUNT(*) FROM " . table('financeiro_lancamentos') . " l WHERE {$where_sql}");
$stmt_count->execute($params); $total = (int)$stmt_count->fetchColumn();
$pagination = paginate($total, $page, 25);

$stmt_list = db()->prepare("SELECT l.*, fc.nome as categoria_nome, fc.cor as categoria_cor, fco.nome as conta_nome, c.nome_razaosocial as cliente_nome FROM " . table('financeiro_lancamentos') . " l LEFT JOIN " . table('financeiro_categorias') . " fc ON l.categoria_id = fc.id LEFT JOIN " . table('financeiro_contas') . " fco ON l.conta_id = fco.id LEFT JOIN " . table('clientes') . " c ON l.cliente_id = c.id WHERE {$where_sql} ORDER BY l.data_vencimento ASC, l.id DESC LIMIT {$pagination['offset']}, 25");
$stmt_list->execute($params); $lancamentos = $stmt_list->fetchAll();

// Totais do período
$stmt_tot = db()->prepare("SELECT tipo, status, COALESCE(SUM(valor),0) as total FROM " . table('financeiro_lancamentos') . " l WHERE {$where_sql} GROUP BY tipo, status");
$stmt_tot->execute($params); $totais_raw = $stmt_tot->fetchAll();
$totais = ['receita_pago'=>0,'receita_pendente'=>0,'despesa_pago'=>0,'despesa_pendente'=>0];
foreach ($totais_raw as $t) {
    $key = $t['tipo'] . '_' . ($t['status'] === 'pago' ? 'pago' : 'pendente');
    if (isset($totais[$key])) $totais[$key] += (float)$t['total'];
}
$saldo_mes = $totais['receita_pago'] - $totais['despesa_pago'];

require_once __DIR__ . '/includes/header.php';

if ($action === 'edit' || $action === 'new'):
?>
<div class="page-header">
    <h1><i class="fas fa-<?php echo $id?'edit':'plus'; ?>"></i> <?php echo $id?'Editar':'Novo'; ?> Lançamento</h1>
    <a href="financeiro.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Voltar</a>
</div>

<div class="card" style="max-width:800px;">
    <div class="card-body">
        <form method="POST" action="financeiro.php<?php echo $id?"?action=edit&id={$id}":"?action=new"; ?>">
            <div class="form-row form-row-3">
                <div class="form-group">
                    <label>Tipo *</label>
                    <select name="tipo" required>
                        <option value="receita" <?php echo selected($lancamento['tipo']??'','receita'); ?>>📈 Receita</option>
                        <option value="despesa" <?php echo selected($lancamento['tipo']??'despesa','despesa'); ?>>📉 Despesa</option>
                    </select>
                </div>
                <div class="form-group" style="grid-column:1/-1;">
                    <label>Descrição *</label>
                    <input type="text" name="descricao" value="<?php echo sanitize($lancamento['descricao']??''); ?>" required placeholder="Ex: Pagamento de fornecedor, Venda #001...">
                </div>
                <div class="form-group">
                    <label>Valor (R$) *</label>
                    <input type="text" name="valor" value="<?php echo $lancamento?number_format((float)$lancamento['valor'],2,',','.'):''; ?>" required placeholder="0,00">
                </div>
                <div class="form-group">
                    <label>Data de Vencimento *</label>
                    <input type="date" name="data_vencimento" value="<?php echo $lancamento['data_vencimento']??date('Y-m-d'); ?>" required>
                </div>
                <div class="form-group">
                    <label>Data de Pagamento</label>
                    <input type="date" name="data_pagamento" value="<?php echo $lancamento['data_pagamento']??''; ?>">
                    <small class="text-muted">Preencha para marcar como pago</small>
                </div>
            </div>

            <div class="form-row form-row-2">
                <div class="form-group">
                    <label>Categoria</label>
                    <select name="categoria_id">
                        <option value="">— Sem categoria —</option>
                        <?php
                        $tipo_atual = null;
                        foreach ($categorias as $cat):
                            if ($cat['tipo'] !== $tipo_atual) {
                                if ($tipo_atual !== null) echo '</optgroup>';
                                echo '<optgroup label="' . ($cat['tipo']==='receita'?'Receitas':'Despesas') . '">';
                                $tipo_atual = $cat['tipo'];
                            }
                        ?>
                        <option value="<?php echo $cat['id']; ?>" <?php echo selected($lancamento['categoria_id']??'',$cat['id']); ?>><?php echo sanitize($cat['nome']); ?></option>
                        <?php endforeach; if ($tipo_atual) echo '</optgroup>'; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Conta Bancária</label>
                    <select name="conta_id">
                        <option value="">— Sem conta —</option>
                        <?php foreach ($contas as $conta): ?>
                        <option value="<?php echo $conta['id']; ?>" <?php echo selected($lancamento['conta_id']??'',$conta['id']); ?>><?php echo sanitize($conta['nome']); ?> (<?php echo format_currency((float)$conta['saldo_atual']); ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Cliente</label>
                    <select name="cliente_id">
                        <option value="">— Nenhum —</option>
                        <?php foreach ($clientes_list as $cli): ?>
                        <option value="<?php echo $cli['id']; ?>" <?php echo selected($lancamento['cliente_id']??'',$cli['id']); ?>><?php echo sanitize($cli['nome_razaosocial']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Forma de Pagamento</label>
                    <select name="forma_pagamento">
                        <option value="">—</option>
                        <?php foreach (['dinheiro'=>'Dinheiro','pix'=>'PIX','cartao_debito'=>'Cartão Débito','cartao_credito'=>'Cartão Crédito','transferencia'=>'Transferência','boleto'=>'Boleto','cheque'=>'Cheque'] as $v=>$l): ?>
                        <option value="<?php echo $v; ?>" <?php echo selected($lancamento['forma_pagamento']??'',$v); ?>><?php echo $l; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <?php if (!$id): ?>
            <div class="form-group">
                <label>Parcelar em</label>
                <select name="parcelas">
                    <?php for ($i=1;$i<=12;$i++): ?>
                    <option value="<?php echo $i; ?>"><?php echo $i===1?'Sem parcelamento':"{$i}x"; ?></option>
                    <?php endfor; ?>
                </select>
                <small class="text-muted">Para parcelado, o valor informado será repetido a cada mês</small>
            </div>
            <?php endif; ?>

            <div class="form-group">
                <label>Status</label>
                <select name="status">
                    <option value="pendente" <?php echo selected($lancamento['status']??'pendente','pendente'); ?>>Pendente</option>
                    <option value="pago" <?php echo selected($lancamento['status']??'','pago'); ?>>Pago / Recebido</option>
                    <option value="cancelado" <?php echo selected($lancamento['status']??'','cancelado'); ?>>Cancelado</option>
                </select>
            </div>

            <div class="form-group">
                <label>Observações</label>
                <textarea name="observacoes" rows="2"><?php echo sanitize($lancamento['observacoes']??''); ?></textarea>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary btn-lg"><i class="fas fa-save"></i> Salvar</button>
                <a href="financeiro.php" class="btn btn-secondary btn-lg">Cancelar</a>
            </div>
        </form>
    </div>
</div>

<?php else: ?>

<div class="page-header">
    <h1><i class="fas fa-dollar-sign"></i> Financeiro</h1>
    <a href="financeiro.php?action=new" class="btn btn-success"><i class="fas fa-plus"></i> Novo Lançamento</a>
</div>

<!-- Resumo do período -->
<div class="fin-stat-grid">
    <div class="fin-stat receita">
        <div class="fin-stat-label"><i class="fas fa-arrow-up"></i> Receitas Pagas</div>
        <div class="fin-stat-value"><?php echo format_currency($totais['receita_pago']); ?></div>
    </div>
    <div class="fin-stat despesa">
        <div class="fin-stat-label"><i class="fas fa-arrow-down"></i> Despesas Pagas</div>
        <div class="fin-stat-value"><?php echo format_currency($totais['despesa_pago']); ?></div>
    </div>
    <div class="fin-stat <?php echo $saldo_mes >= 0 ? 'saldo' : 'vencido'; ?>">
        <div class="fin-stat-label"><i class="fas fa-balance-scale"></i> Saldo do Período</div>
        <div class="fin-stat-value" style="color:<?php echo $saldo_mes>=0?'var(--success)':'var(--danger)'; ?>"><?php echo format_currency($saldo_mes); ?></div>
    </div>
    <div class="fin-stat pendente">
        <div class="fin-stat-label"><i class="fas fa-clock"></i> Receitas a Receber</div>
        <div class="fin-stat-value" style="color:var(--warning);"><?php echo format_currency($totais['receita_pendente']); ?></div>
    </div>
    <div class="fin-stat vencido">
        <div class="fin-stat-label"><i class="fas fa-exclamation-circle"></i> Despesas a Pagar</div>
        <div class="fin-stat-value"><?php echo format_currency($totais['despesa_pendente']); ?></div>
    </div>
</div>

<!-- Filtros -->
<div class="card">
    <div class="card-body" style="padding-bottom:0;">
        <form method="GET" class="filters-bar" style="flex-wrap:wrap;">
            <div class="form-group">
                <label style="font-size:0.75rem;">Mês</label>
                <input type="month" name="mes" value="<?php echo $filtro_mes; ?>">
            </div>
            <div class="form-group">
                <label style="font-size:0.75rem;">Tipo</label>
                <select name="tipo">
                    <option value="">Todos</option>
                    <option value="receita" <?php echo selected($filtro_tipo,'receita'); ?>>Receitas</option>
                    <option value="despesa" <?php echo selected($filtro_tipo,'despesa'); ?>>Despesas</option>
                </select>
            </div>
            <div class="form-group">
                <label style="font-size:0.75rem;">Status</label>
                <select name="filtro">
                    <option value="">Todos</option>
                    <option value="pendente" <?php echo selected($filtro_status,'pendente'); ?>>Pendentes</option>
                    <option value="pago" <?php echo selected($filtro_status,'pago'); ?>>Pagos</option>
                    <option value="vencidos" <?php echo selected($filtro_status,'vencidos'); ?>>Vencidos</option>
                    <option value="cancelado" <?php echo selected($filtro_status,'cancelado'); ?>>Cancelados</option>
                </select>
            </div>
            <div class="form-group">
                <label style="font-size:0.75rem;">Conta</label>
                <select name="conta">
                    <option value="">Todas contas</option>
                    <?php foreach ($contas as $c): ?>
                    <option value="<?php echo $c['id']; ?>" <?php echo selected($filtro_conta,$c['id']); ?>><?php echo sanitize($c['nome']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div style="display:flex;align-items:flex-end;gap:8px;">
                <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Filtrar</button>
                <a href="financeiro.php" class="btn btn-outline">Limpar</a>
            </div>
        </form>
    </div>

    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Tipo</th><th>Descrição</th><th>Categoria</th><th>Vencimento</th>
                    <th>Valor</th><th>Conta</th><th>Status</th><th>Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($lancamentos)): ?>
                <tr><td colspan="8"><div class="empty-state-sm"><i class="fas fa-inbox"></i> Nenhum lançamento encontrado</div></td></tr>
                <?php else: ?>
                <?php foreach ($lancamentos as $l):
                    $vencido = $l['status'] === 'pendente' && $l['data_vencimento'] < date('Y-m-d');
                    $hoje = $l['status'] === 'pendente' && $l['data_vencimento'] === date('Y-m-d');
                ?>
                <tr style="<?php echo $vencido?'background:#fff5f5;':($hoje?'background:#fffbeb;':''); ?>">
                    <td>
                        <span class="badge-status status-<?php echo $l['tipo']; ?>">
                            <?php echo $l['tipo']==='receita'?'↑ Receita':'↓ Despesa'; ?>
                        </span>
                    </td>
                    <td>
                        <strong><?php echo sanitize($l['descricao']); ?></strong>
                        <?php if ($l['cliente_nome']): ?><br><small class="text-muted"><?php echo sanitize($l['cliente_nome']); ?></small><?php endif; ?>
                        <?php if ($l['parcelas'] > 1): ?><br><small class="text-muted">Parcela <?php echo $l['parcela_atual'].'/'.$l['parcelas']; ?></small><?php endif; ?>
                    </td>
                    <td>
                        <?php if ($l['categoria_nome']): ?>
                        <span style="display:inline-flex;align-items:center;gap:5px;">
                            <span style="width:8px;height:8px;border-radius:50%;background:<?php echo $l['categoria_cor']??'#ccc'; ?>;display:inline-block;"></span>
                            <?php echo sanitize($l['categoria_nome']); ?>
                        </span>
                        <?php else: ?><span class="text-muted">—</span><?php endif; ?>
                    </td>
                    <td>
                        <span style="color:<?php echo $vencido?'var(--danger)':($hoje?'var(--warning)':'inherit'); ?>;font-weight:<?php echo ($vencido||$hoje)?'600':'400'; ?>;">
                            <?php echo format_date($l['data_vencimento'],'d/m/Y'); ?>
                            <?php if ($vencido): ?><br><small style="color:var(--danger);">VENCIDO</small><?php endif; ?>
                            <?php if ($hoje): ?><br><small style="color:var(--warning);">HOJE</small><?php endif; ?>
                        </span>
                    </td>
                    <td style="font-weight:700;color:<?php echo $l['tipo']==='receita'?'var(--success)':'var(--danger)'; ?>">
                        <?php echo format_currency((float)$l['valor']); ?>
                        <?php if ($l['data_pagamento']): ?><br><small class="text-muted" style="font-weight:400;">Pago: <?php echo format_date($l['data_pagamento'],'d/m/Y'); ?></small><?php endif; ?>
                    </td>
                    <td><?php echo sanitize($l['conta_nome']??'—'); ?></td>
                    <td>
                        <span class="badge-status status-<?php echo $vencido?'vencido':$l['status']; ?>">
                            <?php echo $vencido?'Vencido':ucfirst($l['status']); ?>
                        </span>
                    </td>
                    <td>
                        <div style="display:flex;gap:4px;flex-wrap:wrap;">
                            <?php if ($l['status'] === 'pendente'): ?>
                            <a href="financeiro.php?action=baixar&id=<?php echo $l['id']; ?>&data=<?php echo date('Y-m-d'); ?>"
                               class="btn btn-sm btn-success" title="Baixar/Marcar como Pago"
                               onclick="return confirm('Marcar como pago hoje?')">
                                <i class="fas fa-check"></i>
                            </a>
                            <?php endif; ?>
                            <a href="financeiro.php?action=edit&id=<?php echo $l['id']; ?>" class="btn btn-sm btn-primary" title="Editar"><i class="fas fa-edit"></i></a>
                            <a href="financeiro.php?action=delete&id=<?php echo $l['id']; ?>" class="btn btn-sm btn-danger" title="Excluir" onclick="return confirm('Excluir lançamento?')"><i class="fas fa-trash"></i></a>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <div class="card-body">
        <div style="display:flex;justify-content:space-between;align-items:center;">
            <small class="text-muted"><?php echo $total; ?> lançamento(s)</small>
            <?php echo pagination_links($pagination, 'financeiro.php', array_filter(['mes'=>$filtro_mes,'tipo'=>$filtro_tipo,'filtro'=>$filtro_status,'conta'=>$filtro_conta])); ?>
        </div>
    </div>
</div>

<?php endif; ?>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
