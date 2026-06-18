<?php
/**
 * SiteCatalogo2 - Orçamentos (CRUD)
 */
require_once __DIR__ . '/includes/functions.php';
$page_title = 'Orçamentos';

$action = $_GET['action'] ?? 'list';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$is_admin = check_permission('admin');

// AJAX - Buscar produtos
if ($action === 'ajax_buscar_produtos') {
    header('Content-Type: application/json');
    $termo = trim($_GET['termo'] ?? '');
    if (strlen($termo) < 1) { echo json_encode([]); exit; }
    $like = '%' . $termo . '%';
    $stmt = db()->prepare("SELECT id, nome, sku, preco, preco_promocional, unidade, quantidade_estoque, imagem_principal FROM " . table('produtos') . " WHERE (nome LIKE ? OR sku LIKE ?) AND ativo=1 ORDER BY nome LIMIT 20");
    $stmt->execute([$like, $like]);
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    exit;
}

// AJAX - Buscar clientes
if ($action === 'ajax_buscar_cliente') {
    header('Content-Type: application/json');
    $termo = trim($_GET['termo'] ?? '');
    if (strlen($termo) < 1) { echo json_encode([]); exit; }
    $like = '%' . $termo . '%';
    $stmt = db()->prepare("SELECT id, nome_razaosocial as nome, email, celular as telefone, cpf_cnpj, cidade, estado FROM " . table('clientes') . " WHERE (nome_razaosocial LIKE ? OR celular LIKE ? OR email LIKE ? OR cpf_cnpj LIKE ?) ORDER BY nome_razaosocial LIMIT 10");
    $stmt->execute([$like, $like, $like, $like]);
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    exit;
}

// Buscar contas com pix para forma de pagamento
$contas_pix = [];
try {
    $stmt_pix = db()->query("SELECT id, nome, banco, agencia, conta FROM " . table('financeiro_contas') . " WHERE ativo=1 ORDER BY nome");
    $contas_pix = $stmt_pix->fetchAll();
} catch (Exception $e) { $contas_pix = []; }

// Atualizar status
if ($action === 'status' && $id && isset($_GET['status'])) {
    $valid = ['novo','pendente','em_analise','respondido','aprovado','rejeitado','cancelado'];
    $ns = $_GET['status'];
    if (in_array($ns, $valid)) {
        db()->prepare("UPDATE " . table('orcamentos') . " SET status = ? WHERE id = ?")->execute([$ns, $id]);
        // Se aprovado, dar baixa no estoque
        if ($ns === 'aprovado') {
            $itens = db()->prepare("SELECT * FROM " . table('orcamento_itens') . " WHERE orcamento_id = ?"); $itens->execute([$id]);
            foreach ($itens->fetchAll() as $item) {
                if ($item['produto_id']) {
                    $p = db()->prepare("SELECT quantidade_estoque FROM " . table('produtos') . " WHERE id = ?"); $p->execute([$item['produto_id']]); $pd = $p->fetchColumn();
                    if ($pd !== false) {
                        $nq = max(0, (int)$pd - (int)$item['quantidade']);
                        db()->prepare("UPDATE " . table('produtos') . " SET quantidade_estoque = ? WHERE id = ?")->execute([$nq, $item['produto_id']]);
                        db()->prepare("INSERT INTO " . table('produto_estoque') . " (produto_id, tipo, quantidade, quantidade_anterior, motivo, usuario_id) VALUES (?,?,?,?,?,?)")
                            ->execute([$item['produto_id'],'saida',$item['quantidade'],$pd,"Aprovação orçamento #{$id}",$_SESSION['admin_id']??null]);
                    }
                }
            }
            // Gerar lançamento financeiro automaticamente
            $orc = db()->prepare("SELECT * FROM " . table('orcamentos') . " WHERE id = ?"); $orc->execute([$id]); $o = $orc->fetch();
            if ($o && (float)$o['valor_total'] > 0) {
                $existe = db()->prepare("SELECT id FROM " . table('financeiro_lancamentos') . " WHERE orcamento_id = ?"); $existe->execute([$id]);
                if (!$existe->fetch()) {
                    $conta_padrao = db()->query("SELECT id FROM " . table('financeiro_contas') . " WHERE ativo=1 ORDER BY id LIMIT 1")->fetchColumn();
                    $cat_padrao = db()->query("SELECT id FROM " . table('financeiro_categorias') . " WHERE nome='Orçamentos Aprovados' LIMIT 1")->fetchColumn();
                    db()->prepare("INSERT INTO " . table('financeiro_lancamentos') . " (tipo,descricao,valor,data_vencimento,categoria_id,conta_id,cliente_id,orcamento_id,status,usuario_id) VALUES (?,?,?,?,?,?,?,?,?,?)")
                        ->execute(['receita',"Orçamento {$o['codigo']}",(float)$o['valor_total'],date('Y-m-d'),$cat_padrao,$conta_padrao,null,$id,'pendente',$_SESSION['admin_id']??null]);
                }
            }
        }
        set_flash('success', 'Status atualizado!');
    }
    header('Location: orcamentos.php?action=view&id=' . $id); exit;
}

// Atualizar atendente (somente admin)
if ($action === 'atualizar_atendente' && $id && $is_admin) {
    $uid = (int)($_POST['usuario_id'] ?? 0);
    if ($uid > 0) {
        db()->prepare("UPDATE " . table('orcamentos') . " SET usuario_id = ? WHERE id = ?")->execute([$uid, $id]);
        set_flash('success', 'Atendente atualizado!');
    }
    header('Location: orcamentos.php?action=view&id=' . $id); exit;
}

// Deletar
if ($action === 'delete' && $id) {
    db()->prepare("DELETE FROM " . table('orcamento_itens') . " WHERE orcamento_id = ?")->execute([$id]);
    db()->prepare("DELETE FROM " . table('orcamentos') . " WHERE id = ?")->execute([$id]);
    set_flash('success', 'Orçamento excluído!');
    header('Location: orcamentos.php'); exit;
}

// Salvar orçamento manual
if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($action, ['new','save_manual'])) {
    $cliente_nome    = trim($_POST['cliente_nome'] ?? '');
    $cliente_email   = trim($_POST['cliente_email'] ?? '');
    $cliente_telefone= trim($_POST['cliente_telefone'] ?? '');
    $cliente_cpf     = trim($_POST['cliente_cpf_cnpj'] ?? '');
    $cliente_cidade  = trim($_POST['cliente_cidade'] ?? '');
    $cliente_estado  = trim($_POST['cliente_estado'] ?? '');
    $tipo_contato    = $_POST['tipo_contato'] ?? 'whatsapp';
    $forma_pagamento = trim($_POST['forma_pagamento'] ?? '');
    $observacoes     = trim($_POST['observacoes'] ?? '');
    $data_entrega    = !empty($_POST['data_entrega']) ? $_POST['data_entrega'] : null;
    $tabela_preco    = $_POST['tabela_preco'] ?? 'padrao';
    $desconto        = (float)str_replace(',', '.', $_POST['desconto'] ?? 0);
    $valor_servicos  = (float)str_replace(',', '.', $_POST['valor_servicos'] ?? 0);
    $produtos_json   = $_POST['produtos_json'] ?? '[]';

    if (empty($cliente_nome)) { set_flash('error', 'Nome do cliente é obrigatório'); header('Location: orcamentos.php?action=new'); exit; }

    $produtos = json_decode($produtos_json, true);
    if (empty($produtos)) { set_flash('error', 'Adicione pelo menos um produto'); header('Location: orcamentos.php?action=new'); exit; }

    try {
        $codigo = 'ORC-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -5));
        $valor_produtos = 0;
        foreach ($produtos as $p) $valor_produtos += (float)$p['preco'] * (int)$p['qtd'];
        $valor_total = max(0, $valor_produtos + $valor_servicos - $desconto);

        // Tentar associar cliente cadastrado
        $cliente_id = null;
        if (!empty($cliente_telefone)) {
            $tel = preg_replace('/\D/', '', $cliente_telefone);
            $stmt_cli = db()->prepare("SELECT id FROM " . table('clientes') . " WHERE celular = ? OR telefone = ? LIMIT 1");
            $stmt_cli->execute([$tel, $tel]);
            $cli = $stmt_cli->fetchColumn();
            if ($cli) $cliente_id = $cli;
        }

        // CORREÇÃO: Salvar cliente_cidade e cliente_estado separadamente
        db()->prepare("INSERT INTO " . table('orcamentos') . " (codigo,cliente_id,cliente_nome,cliente_email,cliente_telefone,cliente_cpf_cnpj,cliente_cidade,cliente_estado,tipo_contato,forma_pagamento,status,observacoes,data_entrega,tabela_preco,valor_produtos,valor_servicos,desconto,valor_total,usuario_id) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)")
            ->execute([$codigo,$cliente_id,$cliente_nome,$cliente_email,$cliente_telefone,$cliente_cpf,$cliente_cidade,$cliente_estado,$tipo_contato,$forma_pagamento,'novo',$observacoes,$data_entrega,$tabela_preco,$valor_produtos,$valor_servicos,$desconto,$valor_total,$_SESSION['admin_id']??null]);

        $orc_id = db()->lastInsertId();

        foreach ($produtos as $p) {
            $preco = (float)$p['preco'];
            $qtd   = (int)$p['qtd'];
            db()->prepare("INSERT INTO " . table('orcamento_itens') . " (orcamento_id,produto_id,produto_nome,sku,quantidade,unidade,preco_unitario,subtotal) VALUES (?,?,?,?,?,?,?,?)")
                ->execute([$orc_id,$p['id']??null,$p['nome'],$p['sku']??'',$qtd,$p['unidade']??'un',$preco,$preco*$qtd]);
        }

        set_flash('success', "Orçamento criado! Código: {$codigo}");
        header('Location: orcamentos.php?action=view&id=' . $orc_id); exit;
    } catch (Exception $e) { set_flash('error', 'Erro: ' . $e->getMessage()); header('Location: orcamentos.php?action=new'); exit; }
}

// Ver detalhes
$orcamento = null; $itens = [];
$usuarios_list = [];
if ($action === 'view' && $id) {
    $stmt = db()->prepare("SELECT o.*, u.nome as atendente FROM " . table('orcamentos') . " o LEFT JOIN " . table('usuarios') . " u ON o.usuario_id = u.id WHERE o.id = ?");
    $stmt->execute([$id]); $orcamento = $stmt->fetch();
    if ($orcamento) {
        $stmt2 = db()->prepare("SELECT i.*, p.imagem_principal, p.slug FROM " . table('orcamento_itens') . " i LEFT JOIN " . table('produtos') . " p ON i.produto_id = p.id WHERE i.orcamento_id = ?");
        $stmt2->execute([$id]); $itens = $stmt2->fetchAll();
        if ($orcamento['status'] === 'novo') {
            db()->prepare("UPDATE " . table('orcamentos') . " SET status = 'pendente' WHERE id = ?")->execute([$id]);
            $orcamento['status'] = 'pendente';
        }
        if ($is_admin) {
            $usuarios_list = db()->query("SELECT id, nome, role FROM " . table('usuarios') . " WHERE status='ativo' ORDER BY nome")->fetchAll();
        }
    }
}

// Listar
$status_filtro = $_GET['status'] ?? '';
$busca = $_GET['busca'] ?? '';
$page = isset($_GET['page']) ? max(1,(int)$_GET['page']) : 1;

$where = ["1=1"]; $params = [];
if ($status_filtro) { $where[] = "o.status = ?"; $params[] = $status_filtro; }
if ($busca) { $where[] = "(o.cliente_nome LIKE ? OR o.codigo LIKE ?)"; $like="%{$busca}%"; $params=array_merge($params,[$like,$like]); }
$where_sql = implode(' AND ', $where);

$stmt_count = db()->prepare("SELECT COUNT(*) FROM " . table('orcamentos') . " o WHERE {$where_sql}"); $stmt_count->execute($params); $total = (int)$stmt_count->fetchColumn();
$pagination = paginate($total, $page, 20);

$stmt_list = db()->prepare("SELECT o.*, u.nome as atendente, (SELECT COUNT(*) FROM " . table('orcamento_itens') . " WHERE orcamento_id=o.id) as total_itens FROM " . table('orcamentos') . " o LEFT JOIN " . table('usuarios') . " u ON o.usuario_id = u.id WHERE {$where_sql} ORDER BY o.id DESC LIMIT {$pagination['offset']}, 20");
$stmt_list->execute($params); $orcamentos = $stmt_list->fetchAll();

$status_list = ['novo','pendente','em_analise','respondido','aprovado','rejeitado','cancelado'];
$status_counts = [];
foreach ($status_list as $s) { $st = db()->prepare("SELECT COUNT(*) FROM " . table('orcamentos') . " WHERE status=?"); $st->execute([$s]); $status_counts[$s] = (int)$st->fetchColumn(); }

$empresa_nome   = get_config('site_nome','SiteCatalogo2');
$empresa_whatsapp = get_config('whatsapp','');
$logo_cliente    = get_config('logo_cliente', '');

require_once __DIR__ . '/includes/header.php';

if ($action === 'new'):
?>
<div class="page-header">
    <h1><i class="fas fa-file-invoice-dollar"></i> Novo Orçamento</h1>
    <a href="orcamentos.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Voltar</a>
</div>

<style>
.form-section{
    background: #fff;
    border-radius: var(--radius);
    padding: 24px;
    margin-bottom: 20px;
    box-shadow: var(--shadow);
}
.form-section h3{
    margin: 0 0 20px;
    font-size: 1rem;
    font-weight: 600;
    color: var(--gray-800);
    display: flex;
    align-items: center;
    gap: 8px;
}
.form-section h3 i{
    color: var(--primary);
}
.busca-wrap{
    position: relative;
}
.busca-resultados{
    position: absolute;
    top: 100%;
    left: 0;
    right: 0;
    background: #fff;
    border: 1px solid var(--gray-200);
    border-radius: var(--radius);
    box-shadow: var(--shadow-lg);
    z-index: 100;
    max-height: 300px;
    overflow-y: auto;
    display: none;
}
.busca-resultados.show{
    display: block;
}
.busca-item{
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px;
    cursor: pointer;
    border-bottom: 1px solid var(--gray-100);
}
.busca-item:hover{
    background: var(--gray-50);
}
.busca-item img{
    width: 40px;
    height: 40px;
    object-fit: cover;
    border-radius: 6px;
}
.tab-orcamento{
    width: 100%;
    border-collapse: collapse;
    margin-top: 16px;
    font-size: 0.875rem;
}
.tab-orcamento th{
    text-align: left;
    padding: 10px 12px;
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: var(--gray-500);
    background: var(--gray-50);
    border-bottom: 1px solid var(--gray-200);
    white-space: nowrap;
}
.tab-orcamento td{
    padding: 12px;
    border-bottom: 1px solid var(--gray-100);
    vertical-align: middle;
}
.tab-orcamento td input{
    width: 100%;
    padding: 9px 12px;
    border: 1px solid var(--gray-300);
    border-radius: 8px;
    font-family: inherit;
    font-size: 0.875rem;
    color: var(--gray-800);
    background: #fff;
    transition: border-color 0.2s, box-shadow 0.2s;
    outline: none;
}
.tab-orcamento td input:focus{
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(59,130,246,0.15);
}
.btn-remover{
    background: var(--danger);
    color: #fff;
    border: none;
    border-radius: 8px;
    padding: 6px 12px;
    cursor: pointer;
    font-size: 0.8125rem;
    font-weight: 600;
    transition: all 0.2s;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}
.btn-remover:hover{
    background: var(--danger-dark);
    transform: translateY(-1px);
    box-shadow: var(--shadow-md);
}
.btn-remover:active{
    transform: translateY(0);
}
</style>

<form method="POST" action="orcamentos.php?action=save_manual" id="formOrc" onsubmit="return prepararEnvio()">
    <input type="hidden" name="produtos_json" id="produtosJson" value="[]">

    <div class="form-section">
        <h3><i class="fas fa-hashtag"></i> Dados do Orçamento</h3>
        <div class="form-row form-row-4">
            <div class="form-group"><label>Nº</label><input type="text" value="AUTO" disabled style="background:var(--gray-100);"></div>
            <div class="form-group"><label>Data</label><input type="text" value="<?php echo date('d/m/Y'); ?>" disabled style="background:var(--gray-100);"></div>
            <div class="form-group">
                <label>Tabela de Preço</label>
                <select name="tabela_preco">
                    <option value="a_vista">À Vista</option>
                    <option value="parcelado">Parcelado</option>
                    <option value="atacado">Atacado</option>
                </select>
            </div>
            <div class="form-group"><label>Data de Entrega</label><input type="date" name="data_entrega"></div>
        </div>
        <div class="form-row form-row-2">
            <div class="form-group">
                <label>Forma de Pagamento</label>
                <select name="forma_pagamento">
                    <option value="">— Selecione —</option>
                    <?php foreach ($contas_pix as $cp): ?>
                    <option value="<?php echo sanitize($cp['nome'] . ($cp['banco']?' - '.$cp['banco']:'') . ($cp['agencia']?' Ag:'.$cp['agencia']:'') . ($cp['conta']?' Cc:'.$cp['conta']:'')); ?>">
                        <?php echo sanitize($cp['nome']); ?>
                        <?php if ($cp['banco']): ?> — <?php echo sanitize($cp['banco']); ?><?php endif; ?>
                        <?php if ($cp['agencia']): ?> Ag: <?php echo sanitize($cp['agencia']); ?><?php endif; ?>
                    </option>
                    <?php endforeach; ?>
                    <option value="Dinheiro">Dinheiro</option>
                    <option value="Cartão de Débito">Cartão de Débito</option>
                    <option value="Cartão de Crédito">Cartão de Crédito</option>
                    <option value="Boleto Bancário">Boleto Bancário</option>
                </select>
            </div>
            <div class="form-group">
                <label>Contato Preferido</label>
                <select name="tipo_contato">
                    <option value="whatsapp">WhatsApp</option>
                    <option value="email">E-mail</option>
                    <option value="telefone">Telefone</option>
                </select>
            </div>
        </div>
    </div>

    <div class="form-section">
        <h3><i class="fas fa-user"></i> Dados do Cliente</h3>
        <input type="hidden" name="cliente_id" id="clienteIdHidden">
        <div class="form-row form-row-2">
            <div class="form-group busca-wrap">
                <label>Nome Completo * <small style="color:var(--gray-400);font-weight:400;"> — digite para buscar cliente cadastrado</small></label>
                <input type="text" name="cliente_nome" id="clienteNome" required
                       placeholder="Nome do cliente ou busque pelo cadastro..."
                       autocomplete="off" oninput="buscarClienteNome(this.value)">
                <div class="busca-resultados" id="resultadosCliente"></div>
            </div>
            <div class="form-group"><label>E-mail</label><input type="email" name="cliente_email" id="clienteEmail"></div>
        </div>
        <div class="form-row form-row-3">
            <div class="form-group"><label>Telefone / WhatsApp</label><input type="tel" name="cliente_telefone" id="clienteTelefone"></div>
            <div class="form-group"><label>CPF/CNPJ</label><input type="text" name="cliente_cpf_cnpj" id="clienteCpf"></div>
            <div class="form-group" style="display:grid;grid-template-columns:1fr auto;gap:8px;align-items:end;">
                <div>
                    <label>Cidade</label>
                    <input type="text" name="cliente_cidade" id="clienteCidade" placeholder="Ex: Duque de Caxias">
                </div>
                <div>
                    <label>UF</label>
                    <select name="cliente_estado" id="clienteEstado" style="width:72px;">
                        <option value="">—</option>
                        <?php foreach (['AC','AL','AP','AM','BA','CE','DF','ES','GO','MA','MT','MS','MG','PA','PB','PR','PE','PI','RJ','RN','RS','RO','RR','SC','SP','SE','TO'] as $uf): ?>
                        <option value="<?php echo $uf; ?>"><?php echo $uf; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>
    </div>

    <div class="form-section">
        <h3><i class="fas fa-boxes"></i> Produtos</h3>
        <div class="busca-wrap">
            <input type="text" id="buscaProduto" placeholder="Buscar produto por nome ou SKU..." autocomplete="off"
                   style="width:100%;padding:10px;border:1px solid var(--gray-200);border-radius:8px;font-size:0.9rem;">
            <div class="busca-resultados" id="resultadosProduto"></div>
        </div>
        <button type="button" onclick="adicionarManual()" style="margin-top:10px;background:var(--success);color:#fff;border:none;padding:8px 16px;border-radius:8px;cursor:pointer;font-weight:600;display:inline-flex;align-items:center;gap:6px;">
            <i class="fas fa-plus"></i> Adicionar Linha Manual
        </button>

        <table class="tab-orcamento" id="tabelaProdutos">
            <thead>
                <tr>
                    <th width="40">Nº</th><th>Descrição</th><th width="70">Un</th>
                    <th width="90">Qtd</th><th width="120">Valor Unit.</th><th width="120">Total</th><th width="50"></th>
                </tr>
            </thead>
            <tbody id="listaProdutos"></tbody>
            <tfoot>
                <tr style="background:var(--gray-50);font-weight:700;">
                    <td colspan="5" style="text-align:right;padding:10px;">TOTAL PRODUTOS:</td>
                    <td id="totalProd" style="color:var(--primary);padding:10px;font-size:1.1rem;">R$ 0,00</td>
                    <td></td>
                </tr>
            </tfoot>
        </table>
    </div>

    <div class="form-section">
        <h3><i class="fas fa-calculator"></i> Totais</h3>
        <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:16px;">
            <div class="form-group"><label>Valor Produtos</label><input type="text" id="valorProdutos" value="0,00" readonly style="background:var(--gray-100);"></div>
            <div class="form-group"><label>Valor Serviços</label><input type="text" name="valor_servicos" id="valorServicos" value="0,00" oninput="calcTotal()"></div>
            <div class="form-group"><label>Desconto (R$)</label><input type="text" name="desconto" id="desconto" value="0,00" oninput="calcTotal()"></div>
            <div class="form-group"><label style="color:var(--primary);font-weight:700;">TOTAL FINAL</label><input type="text" id="totalFinal" value="0,00" readonly style="background:var(--primary-light);color:var(--primary);font-size:1.25rem;font-weight:700;border:2px solid var(--primary);"></div>
        </div>
    </div>

    <div class="form-section">
        <h3><i class="fas fa-comment-alt"></i> Observações</h3>
        <textarea name="observacoes" style="width:100%;min-height:80px;padding:10px;border:1px solid var(--gray-200);border-radius:8px;font-family:inherit;font-size:0.875rem;resize:vertical;" placeholder="Observações do orçamento..."></textarea>
    </div>

    <div style="display:flex;gap:12px;justify-content:flex-end;">
        <a href="orcamentos.php" class="btn btn-secondary btn-lg"><i class="fas fa-times"></i> Cancelar</a>
        <button type="submit" class="btn btn-success btn-lg"><i class="fas fa-save"></i> Salvar Orçamento</button>
    </div>
</form>

<script>
let itens = [];

function escHtml(s) { return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }
function parseBRL(s) { return parseFloat(String(s).replace(/\./g,'').replace(',','.'))||0; }
function toBRL(n) { return n.toFixed(2).replace('.',',').replace(/\B(?=(\d{3})+(?!\d))/g,'.'); }

function renderTabela() {
    const tbody = document.getElementById('listaProdutos');
    if (itens.length === 0) { tbody.innerHTML = '<tr><td colspan="7" style="text-align:center;color:var(--gray-400);padding:20px;">Nenhum produto adicionado</td></tr>'; calcTotal(); return; }
    tbody.innerHTML = itens.map((item, i) => `
        <tr>
            <td>${i+1}</td>
            <td><input type="text" value="${escHtml(item.nome)}" onchange="itens[${i}].nome=this.value"></td>
            <td><input type="text" value="${escHtml(item.unidade)}" style="text-align:center;" onchange="itens[${i}].unidade=this.value"></td>
            <td><input type="number" value="${item.qtd}" min="1" style="text-align:center;" onchange="itens[${i}].qtd=parseInt(this.value)||1;renderTabela()"></td>
            <td><input type="text" value="${toBRL(item.preco)}" onchange="itens[${i}].preco=parseBRL(this.value);renderTabela()"></td>
            <td style="font-weight:700;color:var(--primary);">R$ ${toBRL(item.preco * item.qtd)}</td>
            <td><button type="button" class="btn-remover" onclick="removerItem(${i})"><i class="fas fa-times"></i></button></td>
        </tr>
    `).join('');
    calcTotal();
}

function removerItem(i) { itens.splice(i,1); renderTabela(); }

function adicionarProduto(p) {
    const preco = p.preco_promocional && parseFloat(p.preco_promocional) > 0 ? p.preco_promocional : p.preco;
    itens.push({ id: p.id, nome: p.nome, sku: p.sku||'', unidade: p.unidade||'un', qtd: 1, preco: parseFloat(preco)||0 });
    renderTabela();
    document.getElementById('buscaProduto').value = '';
    document.getElementById('resultadosProduto').classList.remove('show');
}

function adicionarManual() {
    itens.push({ id: null, nome: 'Produto / Serviço', sku: '', unidade: 'un', qtd: 1, preco: 0 });
    renderTabela();
}

function calcTotal() {
    const totalProd = itens.reduce((s,i) => s + i.preco * i.qtd, 0);
    const servicos = parseBRL(document.getElementById('valorServicos').value);
    const desconto = parseBRL(document.getElementById('desconto').value);
    const total = Math.max(0, totalProd + servicos - desconto);
    document.getElementById('valorProdutos').value = toBRL(totalProd);
    document.getElementById('totalProd').textContent = 'R$ ' + toBRL(totalProd);
    document.getElementById('totalFinal').value = toBRL(total);
}

function prepararEnvio() {
    if (itens.length === 0) { alert('Adicione pelo menos um produto!'); return false; }
    document.getElementById('produtosJson').value = JSON.stringify(itens);
    return true;
}

// Busca de produto
let tbProd;
document.getElementById('buscaProduto').addEventListener('input', function() {
    clearTimeout(tbProd);
    const t = this.value.trim();
    const div = document.getElementById('resultadosProduto');
    if (t.length < 1) { div.classList.remove('show'); return; }
    tbProd = setTimeout(() => {
        fetch('orcamentos.php?action=ajax_buscar_produtos&termo=' + encodeURIComponent(t))
        .then(r=>r.json()).then(prods => {
            div.innerHTML = prods.length ? prods.map(p => {
                const preco = (p.preco_promocional && parseFloat(p.preco_promocional) > 0) ? p.preco_promocional : p.preco;
                const imgSrc = p.imagem_principal ? '/uploads/' + p.imagem_principal : '/assets/images/no-image.svg';
                return `<div class="busca-item" onclick='adicionarProduto(${JSON.stringify(p).replace(/'/g,"\'")})'  >
                    <img src="${imgSrc}" onerror="this.src='/assets/images/no-image.svg'">
                    <div style="flex:1"><strong>${escHtml(p.nome)}</strong><br><small>SKU: ${escHtml(p.sku||'N/A')} | Estoque: ${p.quantidade_estoque}</small></div>
                    <strong style="color:var(--primary);">R$ ${parseFloat(preco).toFixed(2).replace('.',',')}</strong>
                </div>`;
            }).join('') : '<div style="padding:12px;color:var(--gray-400);">Nenhum produto encontrado</div>';
            div.classList.add('show');
        }).catch(()=>{ div.innerHTML = '<div style="padding:12px;color:red;">Erro ao buscar</div>'; div.classList.add('show'); });
    }, 250);
});

// Busca de cliente integrada ao campo Nome
let tbCli;
function buscarClienteNome(val) {
    clearTimeout(tbCli);
    const t = val.trim();
    const div = document.getElementById('resultadosCliente');
    if (t.length < 2) { div.classList.remove('show'); return; }
    tbCli = setTimeout(() => {
        fetch('orcamentos.php?action=ajax_buscar_cliente&termo=' + encodeURIComponent(t))
        .then(r => r.json()).then(clis => {
            if (clis.length) {
                div.innerHTML = clis.map((c, i) =>
                    `<div class="busca-item" data-idx="${i}" style="flex-direction:column;align-items:flex-start;gap:2px;">
                        <strong style="font-size:0.875rem;">${escHtml(c.nome)}</strong>
                        <small style="color:var(--gray-500);">${[c.telefone, c.email, c.cpf_cnpj].filter(Boolean).map(escHtml).join(' · ')}</small>
                    </div>`
                ).join('');
                div._resultados = clis;
                div.querySelectorAll('.busca-item').forEach(el => {
                    el.addEventListener('mousedown', e => {
                        e.preventDefault(); // evita blur antes do click
                        preencherCliente(div._resultados[parseInt(el.dataset.idx)]);
                    });
                });
            } else {
                div.innerHTML = '<div style="padding:10px;color:var(--gray-400);">Nenhum cliente encontrado</div>';
                div._resultados = [];
            }
            div.classList.add('show');
        }).catch(() => { div.innerHTML='<div style="padding:10px;color:red;">Erro ao buscar</div>'; div.classList.add('show'); });
    }, 250);
}

function preencherCliente(c) {
    document.getElementById('clienteNome').value      = c.nome      || '';
    document.getElementById('clienteEmail').value     = c.email     || '';
    document.getElementById('clienteTelefone').value  = c.telefone  || '';
    document.getElementById('clienteCpf').value       = c.cpf_cnpj  || '';
    document.getElementById('clienteIdHidden').value  = c.id        || '';
    // Cidade e Estado separados
    document.getElementById('clienteCidade').value    = c.cidade    || '';
    const selEstado = document.getElementById('clienteEstado');
    if (selEstado && c.estado) {
        // Aceita tanto "RJ" quanto "Rio de Janeiro" — tenta sigla primeiro
        const uf = (c.estado.trim().length === 2) ? c.estado.trim().toUpperCase() : '';
        if (uf) selEstado.value = uf;
    }
    document.getElementById('resultadosCliente').classList.remove('show');
}

// Fecha dropdown ao clicar fora
document.addEventListener('click', e => {
    if (!e.target.closest('.busca-wrap')) {
        document.querySelectorAll('.busca-resultados').forEach(d => d.classList.remove('show'));
    }
});

renderTabela();
</script>

<?php elseif ($action === 'view' && $orcamento): ?>

<div class="page-header">
    <h1><i class="fas fa-file-invoice-dollar"></i> Orçamento <?php echo sanitize($orcamento['codigo']); ?></h1>
    <div style="display:flex;gap:8px;flex-wrap:wrap;">
        <a href="orcamentos.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Voltar</a>
        <?php if (!empty($orcamento['cliente_telefone'])): ?>
        <a href="<?php echo whatsapp_link($orcamento['cliente_telefone'], "Olá! Segue o orçamento {$orcamento['codigo']}"); ?>" target="_blank" class="btn btn-success"><i class="fab fa-whatsapp"></i> WhatsApp</a>
        <?php endif; ?>
        <button onclick="imprimirOrcamento()" class="btn btn-outline"><i class="fas fa-print"></i> Imprimir</button>
        <a href="orcamentos.php?action=delete&id=<?php echo $id; ?>" class="btn btn-danger" onclick="return confirm('Excluir orçamento?')"><i class="fas fa-trash"></i></a>
    </div>
</div>

<div style="display:grid;grid-template-columns:2fr 1fr;gap:20px;">
    <div>
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-info-circle"></i> Dados do Orçamento</h3>
                <span class="badge-status status-<?php echo $orcamento['status']; ?>"><?php echo ucfirst(str_replace('_',' ',$orcamento['status'])); ?></span>
            </div>
            <div class="card-body">
                <div class="form-row form-row-3">
                    <div><small class="text-muted">Cliente</small><p><strong><?php echo sanitize($orcamento['cliente_nome']); ?></strong></p></div>
                    <div><small class="text-muted">E-mail</small><p><?php echo sanitize($orcamento['cliente_email']??'-'); ?></p></div>
                    <div><small class="text-muted">Telefone</small><p><?php echo format_phone($orcamento['cliente_telefone']); ?></p></div>
                    <div><small class="text-muted">Data</small><p><?php echo format_date($orcamento['created_at'],'d/m/Y'); ?></p></div>
                    <div><small class="text-muted">Entrega</small><p><?php echo $orcamento['data_entrega']?format_date($orcamento['data_entrega'],'d/m/Y'):'-'; ?></p></div>
                    <div>
                        <small class="text-muted">Atendente</small>
                        <p>
                            <?php echo sanitize($orcamento['atendente']??'-'); ?>
                            <?php if ($is_admin): ?>
                            <button onclick="document.getElementById('modalAtendente').style.display='flex'" class="btn btn-sm btn-light" style="margin-left:6px;" title="Alterar atendente">
                                <i class="fas fa-edit"></i>
                            </button>
                            <?php endif; ?>
                        </p>
                    </div>
                    <?php if (!empty($orcamento['forma_pagamento'])): ?>
                    <div><small class="text-muted">Forma de Pagamento</small><p><strong><?php echo sanitize($orcamento['forma_pagamento']); ?></strong></p></div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><h3><i class="fas fa-boxes"></i> Itens do Orçamento</h3></div>
            <div class="table-responsive">
                <table class="table">
                    <thead><tr><th>Produto</th><th>SKU</th><th>Qtd</th><th>Un</th><th>Valor Unit.</th><th>Total</th></tr></thead>
                    <tbody>
                        <?php foreach ($itens as $item): ?>
                        <tr>
                            <td>
                                <?php if ($item['imagem_principal']): ?><img src="<?php echo uploads_url($item['imagem_principal']); ?>" style="width:32px;height:32px;object-fit:cover;border-radius:4px;margin-right:6px;vertical-align:middle;"><?php endif; ?>
                                <?php echo sanitize($item['produto_nome']); ?>
                            </td>
                            <td><?php echo sanitize($item['sku']??'-'); ?></td>
                            <td><?php echo $item['quantidade']; ?></td>
                            <td><?php echo sanitize($item['unidade']??'un'); ?></td>
                            <td><?php echo format_currency((float)$item['preco_unitario']); ?></td>
                            <td><strong><?php echo format_currency((float)$item['subtotal']); ?></strong></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <?php if ((float)$orcamento['valor_servicos'] > 0): ?><tr><td colspan="5" style="text-align:right;">Serviços:</td><td><?php echo format_currency((float)$orcamento['valor_servicos']); ?></td></tr><?php endif; ?>
                        <?php if ((float)$orcamento['desconto'] > 0): ?><tr><td colspan="5" style="text-align:right;">Desconto:</td><td style="color:var(--success);">- <?php echo format_currency((float)$orcamento['desconto']); ?></td></tr><?php endif; ?>
                        <tr style="background:var(--gray-50);"><td colspan="5" style="text-align:right;font-weight:700;font-size:1.1rem;">TOTAL:</td><td style="font-weight:700;font-size:1.25rem;color:var(--primary);"><?php echo format_currency((float)$orcamento['valor_total']); ?></td></tr>
                    </tfoot>
                </table>
            </div>
            <?php if ($orcamento['observacoes']): ?>
            <div class="card-body" style="border-top:1px solid var(--gray-200);">
                <strong>Observações:</strong> <?php echo sanitize($orcamento['observacoes']); ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <div>
        <div class="card">
            <div class="card-header"><h3><i class="fas fa-tasks"></i> Atualizar Status</h3></div>
            <div class="card-body">
                <?php foreach ($status_list as $s): ?>
                <a href="orcamentos.php?action=status&id=<?php echo $id; ?>&status=<?php echo $s; ?>"
                   class="btn btn-block <?php echo $orcamento['status']===$s?'btn-primary':'btn-light'; ?>"
                   style="margin-bottom:8px;justify-content:flex-start;">
                    <?php echo ucfirst(str_replace('_',' ',$s)); ?>
                    <?php if ($orcamento['status']===$s): ?><i class="fas fa-check" style="margin-left:auto;"></i><?php endif; ?>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<?php if ($is_admin && !empty($usuarios_list)): ?>
<!-- Modal Alterar Atendente -->
<div id="modalAtendente" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:1000;align-items:center;justify-content:center;">
    <div style="background:#fff;border-radius:12px;padding:28px;width:100%;max-width:420px;">
        <h3 style="margin-bottom:16px;font-size:1.1rem;font-weight:700;"><i class="fas fa-user-edit" style="color:var(--primary);"></i> Alterar Atendente</h3>
        <form method="POST" action="orcamentos.php?action=atualizar_atendente&id=<?php echo $id; ?>">
            <div class="form-group">
                <label>Selecionar Atendente</label>
                <select name="usuario_id" required>
                    <option value="">— Selecione —</option>
                    <?php foreach ($usuarios_list as $u): ?>
                    <option value="<?php echo $u['id']; ?>" <?php echo selected($orcamento['usuario_id']??0, $u['id']); ?>>
                        <?php echo sanitize($u['nome']); ?> (<?php echo ucfirst($u['role']); ?>)
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div style="display:flex;gap:10px;">
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Salvar</button>
                <button type="button" onclick="document.getElementById('modalAtendente').style.display='none'" class="btn btn-secondary">Cancelar</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<!-- JANELA DE IMPRESSÃO - NOVO LAYOUT -->
<script>
function imprimirOrcamento() {
    const orcamento = <?php echo json_encode($orcamento); ?>;
    const itens = <?php echo json_encode($itens); ?>;

    const win = window.open('', '_blank', 'width=900,height=700');

    let htmlItens = '';
    let totalProdutos = 0;
    itens.forEach((item, idx) => {
        const subtotal = parseFloat(item.preco_unitario) * parseInt(item.quantidade);
        totalProdutos += subtotal;
        htmlItens += `<tr>
            <td>${idx + 1}</td>
            <td>${item.produto_nome}</td>
            <td>${item.unidade || 'un'}</td>
            <td>0,000</td>
            <td>0,000</td>
            <td>0,000</td>
            <td style="text-align:right;">R$ ${parseFloat(item.preco_unitario).toFixed(2).replace('.',',')}</td>
            <td style="text-align:right;">${parseInt(item.quantidade).toFixed(3).replace('.',',')}</td>
            <td style="text-align:right;">R$ ${subtotal.toFixed(2).replace('.',',')}</td>
        </tr>`;
    });

    const valorServicos = parseFloat(orcamento.valor_servicos || 0);
    const desconto = parseFloat(orcamento.desconto || 0);
    const valorTotal = parseFloat(orcamento.valor_total || 0);

    const data = new Date(orcamento.created_at);
    const dataStr = data.toLocaleDateString('pt-BR');
    const horaStr = data.toLocaleTimeString('pt-BR');

    // CORREÇÃO: Usar cliente_cidade + cliente_estado em vez de cliente_endereco
    const cidadeEstado = (orcamento.cliente_cidade || 'Não informado') + (orcamento.cliente_estado ? '/' + orcamento.cliente_estado : '');

    const printHtml = `<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<title>Ordem de Servico ${orcamento.codigo}</title>
<style>
    @page { size: A4; margin: 15mm; }
    body { font-size: 11px; color: #000; margin: 0; padding: 15px; background: #fff; }

    .cabecalho-grid {
        display: flex;
        align-items: center;
        justify-content: space-between;
        border-bottom: 2px solid #000;
        padding-bottom: 10px;
        margin-bottom: 15px;
    }
    .col-logo { width: 20%; }
    .col-dados { width: 50%; text-align: center; }
    .col-os { width: 30%; text-align: right; }

    .cabecalho-grid h2 { margin: 0; font-size: 18px; text-transform: uppercase; }
    .cabecalho-grid p { margin: 3px 0; }

    .info-os { display: flex; justify-content: space-between; margin-bottom: 10px; border-bottom: 1px solid #000; padding-bottom: 10px; }
    .tabela-itens { width: 100%; border-collapse: collapse; margin: 10px 0; }
    .tabela-itens th { border-top: 2px solid #000; border-bottom: 2px solid #000; padding: 5px; text-align: left; font-size: 10px; }
    .tabela-itens td { padding: 4px; border-bottom: 1px solid #ccc; }
    .totais { text-align: right; margin-top: 10px; border-top: 2px solid #000; padding-top: 10px; }
    .totais table { width: 300px; margin-left: auto; font-size: 12px; }
    .totais td { padding: 3px 0; }
    .totais .total-geral { font-size: 16px; font-weight: bold; border-top: 2px solid #000; }
    .rodape-impressao { margin-top: 20px; padding-top: 10px; border-top: 1px solid #000; font-size: 9px; text-align: center; }
    .linha-assinatura { border-top: 1px solid #000; width: 200px; margin: 30px auto 5px; text-align: center; padding-top: 5px; }

    @media print {
        body { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    }
</style>
</head>
<body>

<div class="cabecalho-grid">
    <div class="col-logo">
        <img src="<?php echo !empty($logo_cliente) ? uploads_url($logo_cliente) : '/assets/images/logo.png'; ?>" alt="Logo" style="max-width: 80px;">
    </div>
    <div class="col-dados">
        <h2><?php echo get_config('site_nome','LHXS DESIGN'); ?></h2>
        <p>Duque de Caxias/RJ</p>
        <p>Tel: ${orcamento.cliente_telefone || '(21) 97340-8712'} | ${orcamento.cliente_email || 'luishxsantos89@gmail.com'}</p>
    </div>
    <div class="col-os">
        <div style="border:2px solid #000; padding:5px; font-weight:bold; display:inline-block;">
            OS Nº ${orcamento.codigo}
        </div>
    </div>
</div>

<div class="info-os">
    <div>
        <strong>Cliente:</strong> ${orcamento.cliente_nome}<br>
        <strong>Endereco:</strong> ${cidadeEstado}<br>
        <strong>Cidade:</strong> ${cidadeEstado}<br>
        <strong>CPF/CNPJ:</strong> ${orcamento.cliente_cpf_cnpj || 'Não informado'}
    </div>
    <div style="text-align:right;">
        <strong>Data:</strong> ${dataStr}<br>
        <strong>Hora:</strong> ${horaStr}<br>
        <strong>Contato:</strong> ${orcamento.cliente_telefone || 'Não informado'}<br>
        <strong>Tel:</strong> ${orcamento.cliente_telefone || 'Não informado'}
    </div>
</div>

<table class="tabela-itens">
    <thead>
        <tr>
            <th>Nº</th>
            <th>Descricao do Item</th>
            <th>Un</th>
            <th>Largura</th>
            <th>Altura</th>
            <th>MT2</th>
            <th>Valor Unit</th>
            <th>Qtde</th>
            <th>Valor Total</th>
        </tr>
    </thead>
    <tbody>
        ${htmlItens}
    </tbody>
</table>

<div class="totais">
    <table>
        <tr><td><strong>VALOR PRODUTOS:</strong></td><td style="text-align:right;">R$ ${totalProdutos.toFixed(2).replace('.',',')}</td></tr>
        <tr><td><strong>VALOR SERVICOS:</strong></td><td style="text-align:right;">R$ ${valorServicos.toFixed(2).replace('.',',')}</td></tr>
        <tr><td><strong>DESLOCAMENTO:</strong></td><td style="text-align:right;">R$ 0,00</td></tr>
        <tr><td><strong>DESCONTO:</strong></td><td style="text-align:right;">- R$ ${desconto.toFixed(2).replace('.',',')}</td></tr>
        <tr class="total-geral"><td><strong>VALOR TOTAL:</strong></td><td style="text-align:right;"><strong>R$ ${valorTotal.toFixed(2).replace('.',',')}</strong></td></tr>
    </table>
</div>

<div class="info-os" style="margin-top:15px;font-size:10px;">
    <div><strong>Situacao:</strong> ${orcamento.status.toUpperCase().replace('_', ' ')}<br><strong>Servico:</strong> ${orcamento.observacoes || ''}</div>
</div>

<div class="rodape-impressao"><p>Obrigado pela Preferencia</p></div>

<div style="display:flex;justify-content:space-between;margin-top:40px;">
    <div class="linha-assinatura">Assinatura do Cliente</div>
    <div class="linha-assinatura">Assinatura da Grafica</div>
</div>

</body>
</html>`;

    win.document.write(printHtml);
    win.document.close();
    setTimeout(() => { win.print(); }, 500);
}
</script>

<?php else: ?>

<div class="page-header">
    <h1><i class="fas fa-file-invoice-dollar"></i> Orçamentos</h1>
    <a href="orcamentos.php?action=new" class="btn btn-primary"><i class="fas fa-plus"></i> Novo Orçamento</a>
</div>

<!-- Filtros rápidos por status -->
<div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:16px;">
    <a href="orcamentos.php" class="btn btn-sm <?php echo !$status_filtro?'btn-primary':'btn-light'; ?>">Todos (<?php echo array_sum($status_counts); ?>)</a>
    <?php foreach ($status_list as $s): ?>
    <a href="orcamentos.php?status=<?php echo $s; ?>" class="btn btn-sm <?php echo $status_filtro===$s?'btn-primary':'btn-light'; ?>">
        <?php echo ucfirst(str_replace('_',' ',$s)); ?> (<?php echo $status_counts[$s]; ?>)
    </a>
    <?php endforeach; ?>
</div>

<div class="card">
    <div class="card-body" style="padding-bottom:0;">
        <form method="GET" class="filters-bar">
            <input type="hidden" name="status" value="<?php echo sanitize($status_filtro); ?>">
            <div class="form-group"><input type="text" name="busca" value="<?php echo sanitize($busca); ?>" placeholder="Buscar cliente ou código..."></div>
            <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i></button>
        </form>
    </div>
    <div class="table-responsive">
        <table class="table">
            <thead><tr><th>Código</th><th>Cliente</th><th>Atendente</th><th>Itens</th><th>Total</th><th>Status</th><th>Data</th><th>Ações</th></tr></thead>
            <tbody>
                <?php if (empty($orcamentos)): ?>
                <tr><td colspan="8"><div class="empty-state-sm">Nenhum orçamento encontrado</div></td></tr>
                <?php else: ?>
                <?php foreach ($orcamentos as $o): ?>
                <tr>
                    <td><a href="orcamentos.php?action=view&id=<?php echo $o['id']; ?>" style="color:var(--primary);font-weight:600;"><?php echo sanitize($o['codigo']); ?></a></td>
                    <td>
                        <strong><?php echo sanitize($o['cliente_nome']); ?></strong>
                        <?php if ($o['cliente_telefone']): ?><br><small class="text-muted"><?php echo format_phone($o['cliente_telefone']); ?></small><?php endif; ?>
                    </td>
                    <td><small><?php echo sanitize($o['atendente'] ?? '-'); ?></small></td>
                    <td><?php echo $o['total_itens']; ?></td>
                    <td style="font-weight:700;color:var(--primary);"><?php echo format_currency((float)$o['valor_total']); ?></td>
                    <td><span class="badge-status status-<?php echo $o['status']; ?>"><?php echo ucfirst(str_replace('_',' ',$o['status'])); ?></span></td>
                    <td><?php echo format_date($o['created_at'],'d/m/Y'); ?></td>
                    <td>
                        <a href="orcamentos.php?action=view&id=<?php echo $o['id']; ?>" class="btn btn-sm btn-primary" title="Ver"><i class="fas fa-eye"></i></a>
                        <?php if (!empty($o['cliente_telefone'])): ?>
                        <a href="<?php echo whatsapp_link($o['cliente_telefone']); ?>" target="_blank" class="btn btn-sm btn-success" title="WhatsApp"><i class="fab fa-whatsapp"></i></a>
                        <?php endif; ?>
                        <a href="orcamentos.php?action=delete&id=<?php echo $o['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Excluir?')"><i class="fas fa-trash"></i></a>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <div class="card-body">
        <div style="display:flex;justify-content:space-between;align-items:center;">
            <small class="text-muted"><?php echo $total; ?> orçamento(s)</small>
            <?php echo pagination_links($pagination, 'orcamentos.php', array_filter(['status'=>$status_filtro,'busca'=>$busca])); ?>
        </div>
    </div>
</div>

<?php endif; ?>
<?php require_once __DIR__ . '/includes/footer.php'; ?>