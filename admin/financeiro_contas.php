<?php
/**
 * SiteCatalogo2 - Contas Bancárias
 */
require_once __DIR__ . '/includes/functions.php';
if (!check_permission('gerente')) { set_flash('error','Acesso restrito ao Financeiro'); header('Location: ./'); exit; }
$page_title = 'Contas Bancárias';

$action = $_GET['action'] ?? 'list';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = trim($_POST['nome'] ?? '');
    if (empty($nome)) { set_flash('error', 'Nome é obrigatório'); }
    else {
        $dados = [
            'nome'         => $nome,
            'tipo'         => $_POST['tipo'] ?? 'corrente',
            'banco'        => trim($_POST['banco'] ?? ''),
            'agencia'      => trim($_POST['agencia'] ?? ''),
            'conta'        => trim($_POST['conta_num'] ?? ''),
            'saldo_inicial'=> (float)str_replace(',', '.', $_POST['saldo_inicial'] ?? 0),
            'cor'          => $_POST['cor'] ?? '#3b82f6',
            'ativo'        => isset($_POST['ativo']) ? 1 : 0,
        ];
        try {
            if ($id) {
                $f = []; $v = [];
                foreach ($dados as $k => $val) { $f[] = "{$k} = ?"; $v[] = $val; }
                $v[] = $id;
                db()->prepare("UPDATE " . table('financeiro_contas') . " SET " . implode(', ', $f) . " WHERE id = ?")->execute($v);
                set_flash('success', 'Conta atualizada!');
            } else {
                $dados['saldo_atual'] = $dados['saldo_inicial'];
                $cols = implode(', ', array_keys($dados));
                $ph = implode(', ', array_fill(0, count($dados), '?'));
                db()->prepare("INSERT INTO " . table('financeiro_contas') . " ({$cols}) VALUES ({$ph})")->execute(array_values($dados));
                set_flash('success', 'Conta criada!');
            }
            header('Location: financeiro_contas.php'); exit;
        } catch (Exception $e) { set_flash('error', 'Erro: ' . $e->getMessage()); }
    }
}

if ($action === 'delete' && $id) {
    try {
        db()->prepare("DELETE FROM " . table('financeiro_contas') . " WHERE id = ?")->execute([$id]);
        set_flash('success', 'Conta excluída!');
    } catch (Exception $e) { set_flash('error', 'Erro: ' . $e->getMessage()); }
    header('Location: financeiro_contas.php'); exit;
}

$conta = null;
if ($action === 'edit' && $id) {
    $stmt = db()->prepare("SELECT * FROM " . table('financeiro_contas') . " WHERE id = ?"); $stmt->execute([$id]); $conta = $stmt->fetch();
}

$contas = db()->query("SELECT c.*, (SELECT COUNT(*) FROM " . table('financeiro_lancamentos') . " WHERE conta_id = c.id) as total_lancamentos FROM " . table('financeiro_contas') . " c ORDER BY c.nome")->fetchAll();

require_once __DIR__ . '/includes/header.php';
?>
<div class="page-header">
    <h1><i class="fas fa-university"></i> Contas Bancárias</h1>
    <button onclick="document.getElementById('modal').style.display='flex'" class="btn btn-primary"><i class="fas fa-plus"></i> Nova Conta</button>
</div>

<div id="modal" style="display:<?php echo ($action==='edit'||$action==='new')?'flex':'none'; ?>;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:1000;align-items:center;justify-content:center;">
    <div style="background:#fff;border-radius:12px;padding:32px;width:100%;max-width:500px;">
        <h3 style="margin-bottom:20px;font-size:1.125rem;font-weight:700;"><i class="fas fa-university" style="color:var(--primary);"></i> <?php echo $conta?'Editar':'Nova'; ?> Conta</h3>
        <form method="POST" action="financeiro_contas.php<?php echo $id?"?action=edit&id={$id}":"?action=new"; ?>">
            <div class="form-group">
                <label>Nome da Conta *</label>
                <input type="text" name="nome" value="<?php echo sanitize($conta['nome']??''); ?>" required placeholder="Ex: Caixa, Bradesco, Nubank...">
            </div>
            <div class="form-row form-row-2">
                <div class="form-group">
                    <label>Tipo</label>
                    <select name="tipo">
                        <?php foreach (['corrente'=>'Conta Corrente','poupanca'=>'Poupança','caixa'=>'Caixa','investimento'=>'Investimento','outros'=>'Outros'] as $v=>$l): ?>
                        <option value="<?php echo $v; ?>" <?php echo selected($conta['tipo']??'corrente',$v); ?>><?php echo $l; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Banco</label>
                    <input type="text" name="banco" value="<?php echo sanitize($conta['banco']??''); ?>" placeholder="Nome do banco">
                </div>
                <div class="form-group">
                    <label>Agência</label>
                    <input type="text" name="agencia" value="<?php echo sanitize($conta['agencia']??''); ?>">
                </div>
                <div class="form-group">
                    <label>Número da Conta</label>
                    <input type="text" name="conta_num" value="<?php echo sanitize($conta['conta']??''); ?>">
                </div>
            </div>
            <?php if (!$id): ?>
            <div class="form-group">
                <label>Saldo Inicial (R$)</label>
                <input type="text" name="saldo_inicial" value="0,00" placeholder="0,00">
            </div>
            <?php endif; ?>
            <div class="form-row form-row-2">
                <div class="form-group">
                    <label>Cor</label>
                    <input type="color" name="cor" value="<?php echo sanitize($conta['cor']??'#3b82f6'); ?>" style="width:60px;height:40px;padding:2px;">
                </div>
                <div class="form-group" style="display:flex;align-items:flex-end;">
                    <div class="form-check">
                        <input type="checkbox" name="ativo" id="ativo_conta" <?php echo checked($conta['ativo']??1); ?>>
                        <label for="ativo_conta">Conta Ativa</label>
                    </div>
                </div>
            </div>
            <div style="display:flex;gap:10px;margin-top:16px;">
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Salvar</button>
                <button type="button" onclick="document.getElementById('modal').style.display='none'" class="btn btn-secondary">Cancelar</button>
            </div>
        </form>
    </div>
</div>

<!-- Cards de saldo -->
<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:16px;margin-bottom:24px;">
    <?php foreach ($contas as $c): ?>
    <div style="background:#fff;border-radius:12px;padding:20px;box-shadow:var(--shadow);border-left:4px solid <?php echo $c['cor']; ?>;">
        <div style="display:flex;justify-content:space-between;align-items:flex-start;">
            <div>
                <div style="font-size:0.75rem;color:var(--gray-500);font-weight:600;text-transform:uppercase;margin-bottom:4px;"><?php echo sanitize($c['nome']); ?></div>
                <div style="font-size:1.5rem;font-weight:700;color:<?php echo (float)$c['saldo_atual']>=0?'var(--gray-900)':'var(--danger)'; ?>">
                    <?php echo format_currency((float)$c['saldo_atual']); ?>
                </div>
                <div style="font-size:0.75rem;color:var(--gray-400);margin-top:4px;">
                    <?php echo $c['banco']?sanitize($c['banco']):''; ?> • <?php echo $c['total_lancamentos']; ?> lançamentos
                </div>
            </div>
            <div style="display:flex;flex-direction:column;gap:4px;">
                <a href="financeiro_contas.php?action=edit&id=<?php echo $c['id']; ?>" class="btn btn-sm btn-light"><i class="fas fa-edit"></i></a>
                <?php if ($c['total_lancamentos'] == 0): ?>
                <a href="financeiro_contas.php?action=delete&id=<?php echo $c['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Excluir conta?')"><i class="fas fa-trash"></i></a>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Tabela -->
<div class="card">
    <div class="card-header"><h3><i class="fas fa-list"></i> Todas as Contas</h3></div>
    <div class="table-responsive">
        <table class="table">
            <thead><tr><th>Nome</th><th>Tipo</th><th>Banco</th><th>Saldo Inicial</th><th>Saldo Atual</th><th>Lançamentos</th><th>Status</th><th>Ações</th></tr></thead>
            <tbody>
                <?php foreach ($contas as $c): ?>
                <tr>
                    <td><strong><?php echo sanitize($c['nome']); ?></strong></td>
                    <td><?php echo ucfirst($c['tipo']); ?></td>
                    <td><?php echo sanitize($c['banco']??'-'); ?></td>
                    <td><?php echo format_currency((float)$c['saldo_inicial']); ?></td>
                    <td style="font-weight:700;color:<?php echo (float)$c['saldo_atual']>=0?'var(--success)':'var(--danger)'; ?>"><?php echo format_currency((float)$c['saldo_atual']); ?></td>
                    <td><?php echo $c['total_lancamentos']; ?></td>
                    <td><span class="badge-status status-<?php echo $c['ativo']?'ativo':'inativo'; ?>"><?php echo $c['ativo']?'Ativa':'Inativa'; ?></span></td>
                    <td>
                        <a href="financeiro_contas.php?action=edit&id=<?php echo $c['id']; ?>" class="btn btn-sm btn-primary"><i class="fas fa-edit"></i></a>
                        <?php if ($c['total_lancamentos'] == 0): ?>
                        <a href="financeiro_contas.php?action=delete&id=<?php echo $c['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Excluir conta?')"><i class="fas fa-trash"></i></a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
