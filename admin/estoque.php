<?php
/**
 * SiteCatalogo2 - Estoque
 */
require_once __DIR__ . '/includes/functions.php';
$page_title = 'Estoque';

$action = $_GET['action'] ?? 'list';

// ========== IMPORTAÇÃO DE ESTOQUE POR PLANILHA ==========
if ($action === 'importar_planilha' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');

    if (empty($_FILES['planilha']['tmp_name'])) {
        echo json_encode(['ok' => false, 'msg' => 'Nenhum arquivo enviado.']);
        exit;
    }

    $ext = strtolower(pathinfo($_FILES['planilha']['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['csv', 'xlsx', 'xls'])) {
        echo json_encode(['ok' => false, 'msg' => 'Formato não suportado. Use CSV, XLS ou XLSX.']);
        exit;
    }

    $resultado = ['ok' => true, 'atualizados' => 0, 'erros' => []];

    try {
        $handle = fopen($_FILES['planilha']['tmp_name'], 'r');
        if (!$handle) {
            echo json_encode(['ok' => false, 'msg' => 'Não foi possível ler o arquivo.']);
            exit;
        }

        // Detectar delimitador
        $primeira_linha = fgets($handle);
        rewind($handle);
        $delimitador = strpos($primeira_linha, ';') !== false ? ';' : ',';

        // Ler cabeçalho
        $cabecalho = fgetcsv($handle, 0, $delimitador);
        if (!$cabecalho) {
            echo json_encode(['ok' => false, 'msg' => 'Arquivo vazio ou formato inválido.']);
            exit;
        }

        // Normalizar cabeçalho
        $cabecalho = array_map(function($h) {
            return strtolower(trim(str_replace([' ', '_', '-'], '', \mb_convert_encoding($h, 'UTF-8', 'UTF-8'))));
        }, $cabecalho);

        // Mapear colunas (somente campos relacionados ao estoque)
        $map = [];
        $colunas_esperadas = [
            'sku'                => ['sku', 'codigo', 'referencia', 'ref'],
            'nome'               => ['nome', 'produto', 'descricao', 'titulo'],
            'quantidade_estoque' => ['quantidadeestoque', 'estoque', 'qtd', 'quantidade', 'qtdestoque', 'estoqueatual', 'novoestoque'],
            'estoque_minimo'     => ['estoqueminimo', 'minimo', 'estmin'],
            'motivo'             => ['motivo'],
            'observacoes'        => ['observacoes', 'obs', 'observacao'],
        ];

        foreach ($cabecalho as $idx => $col) {
            foreach ($colunas_esperadas as $campo => $sinonimos) {
                if (in_array($col, $sinonimos)) {
                    $map[$campo] = $idx;
                    break;
                }
            }
        }

        if (!isset($map['sku']) && !isset($map['nome'])) {
            echo json_encode(['ok' => false, 'msg' => 'É necessário ao menos a coluna "SKU" ou "Nome" para identificar o produto. Cabeçalho detectado: ' . implode(', ', $cabecalho)]);
            exit;
        }

        if (!isset($map['quantidade_estoque']) && !isset($map['estoque_minimo'])) {
            echo json_encode(['ok' => false, 'msg' => 'É necessário ao menos a coluna "Quantidade Estoque" ou "Estoque Mínimo". Cabeçalho detectado: ' . implode(', ', $cabecalho)]);
            exit;
        }

        $linha_num = 1;
        while (($dados = fgetcsv($handle, 0, $delimitador)) !== false) {
            $linha_num++;

            // Pular linhas vazias
            if (empty(array_filter($dados, 'trim'))) continue;

            $sku  = isset($map['sku'])  ? trim($dados[$map['sku']] ?? '') : '';
            $nome = isset($map['nome']) ? trim(mb_convert_encoding($dados[$map['nome']] ?? '', 'UTF-8', 'UTF-8')) : '';

            if (empty($sku) && empty($nome)) {
                $resultado['erros'][] = "Linha {$linha_num}: SKU e Nome vazios";
                continue;
            }

            // Localizar produto
            $produto = null;
            if (!empty($sku)) {
                $stmt_ex = db()->prepare("SELECT id, nome, quantidade_estoque, estoque_minimo FROM " . table('produtos') . " WHERE sku = ? LIMIT 1");
                $stmt_ex->execute([$sku]);
                $produto = $stmt_ex->fetch();
            }
            if (!$produto && !empty($nome)) {
                $stmt_ex = db()->prepare("SELECT id, nome, quantidade_estoque, estoque_minimo FROM " . table('produtos') . " WHERE nome = ? LIMIT 1");
                $stmt_ex->execute([$nome]);
                $produto = $stmt_ex->fetch();
            }

            if (!$produto) {
                $identificador = !empty($sku) ? "SKU '{$sku}'" : "nome '{$nome}'";
                $resultado['erros'][] = "Linha {$linha_num}: produto com {$identificador} não encontrado";
                continue;
            }

            $ant = (int)$produto['quantidade_estoque'];
            $nova_qtd = isset($map['quantidade_estoque']) && $dados[$map['quantidade_estoque']] !== ''
                ? (int)$dados[$map['quantidade_estoque']]
                : $ant;

            $novo_minimo = isset($map['estoque_minimo']) && $dados[$map['estoque_minimo']] !== ''
                ? (int)$dados[$map['estoque_minimo']]
                : (int)$produto['estoque_minimo'];

            $motivo = isset($map['motivo']) ? trim(mb_convert_encoding($dados[$map['motivo']] ?? '', 'UTF-8', 'UTF-8')) : 'ajuste_inventario';
            if (empty($motivo)) $motivo = 'ajuste_inventario';
            $obs = isset($map['observacoes']) ? trim(mb_convert_encoding($dados[$map['observacoes']] ?? '', 'UTF-8', 'UTF-8')) : 'Importação por planilha';
            if (empty($obs)) $obs = 'Importação por planilha';

            // Atualizar produto
            db()->prepare("UPDATE " . table('produtos') . " SET quantidade_estoque = ?, estoque_minimo = ? WHERE id = ?")
                ->execute([$nova_qtd, $novo_minimo, $produto['id']]);

            // Registrar movimentação se a quantidade mudou
            if ($nova_qtd !== $ant) {
                db()->prepare("INSERT INTO " . table('produto_estoque') . " (produto_id, quantidade) VALUES (?, ?)")
                    ->execute([$produto['id'], $nova_qtd]);
            }

            $resultado['atualizados']++;
        }

        fclose($handle);

        $resultado['msg'] = "Importação concluída! {$resultado['atualizados']} produto(s) atualizado(s).";
        if (!empty($resultado['erros'])) {
            $resultado['msg'] .= " " . count($resultado['erros']) . " erro(s) encontrado(s).";
        }

        echo json_encode($resultado);
        exit;

    } catch (Exception $e) {
        echo json_encode(['ok' => false, 'msg' => 'Erro: ' . $e->getMessage()]);
        exit;
    }
}

// ========== DOWNLOAD MODELO DE PLANILHA DE ESTOQUE ==========
if ($action === 'download_modelo') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="modelo_estoque.csv"');

    $output = fopen('php://output', 'w');
    fputcsv($output, [
        'SKU', 'Nome', 'Quantidade Estoque', 'Estoque Mínimo', 'Motivo', 'Observações'
    ], ';');

    fputcsv($output, [
        'CV-001',
        'Cartão de Visita Couchê 250g',
        '120',
        '10',
        'ajuste_inventario',
        'Contagem mensal'
    ], ';');

    fclose($output);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'movimentar') {
    $pid  = (int)($_POST['produto_id'] ?? 0);
    $tipo = $_POST['tipo'] ?? 'entrada';
    $qtd  = (int)($_POST['quantidade'] ?? 0);

    if ($pid <= 0) {
        set_flash('error', 'Selecione um produto');
    } elseif ($qtd <= 0) {
        set_flash('error', 'Quantidade deve ser maior que zero');
    } else {
        $sel = db()->prepare("SELECT id, quantidade_estoque FROM " . table('produtos') . " WHERE id = ?");
        $sel->execute([$pid]);
        $p = $sel->fetch();

        if (!$p) {
            set_flash('error', 'Produto não encontrado');
        } else {
            $ant  = (int)$p['quantidade_estoque'];
            $nova = $tipo === 'entrada' ? $ant + $qtd : max(0, $ant - $qtd);

            // UPDATE principal — fora de try/catch para não engolir erro real
            $upd = db()->prepare("UPDATE " . table('produtos') . " SET quantidade_estoque = ? WHERE id = ?");
            $upd->execute([$nova, $pid]);

            // Histórico: tenta com colunas extras, depois mínimo, nunca bloqueia
            try {
                db()->prepare("INSERT INTO " . table('produto_estoque') . " (produto_id, tipo, quantidade, quantidade_anterior, motivo, usuario_id) VALUES (?,?,?,?,?,?)")
                    ->execute([$pid, $tipo, $qtd, $ant, 'movimentacao_manual', $_SESSION['admin_id'] ?? null]);
            } catch (Exception $e1) {
                try {
                    db()->prepare("INSERT INTO " . table('produto_estoque') . " (produto_id, quantidade) VALUES (?,?)")
                        ->execute([$pid, $nova]);
                } catch (Exception $e2) { /* histórico opcional */ }
            }

            set_flash('success', "Estoque atualizado! {$ant} → {$nova}");
        }
    }
    header('Location: estoque.php'); exit;
}

$filtro = $_GET['filtro'] ?? '';
$busca  = $_GET['busca'] ?? '';
$page   = max(1, (int)($_GET['page'] ?? 1));

$where = ["1=1"]; $params = [];
if ($busca) { $where[] = "(p.nome LIKE ? OR p.sku LIKE ?)"; $like="%{$busca}%"; $params=[$like,$like]; }
if ($filtro === 'baixo') $where[] = "p.quantidade_estoque > 0 AND p.quantidade_estoque <= p.estoque_minimo AND p.estoque_minimo > 0";
if ($filtro === 'zero')  $where[] = "p.quantidade_estoque = 0";
$where_sql = implode(' AND ', $where);

// Contar total corretamente
$stmt_c = db()->prepare("SELECT COUNT(*) FROM " . table('produtos') . " p WHERE {$where_sql}");
$stmt_c->execute($params);
$total = (int)$stmt_c->fetchColumn();

$pagination = paginate($total, $page, 20);

// Listar produtos — usar ORDER BY p.nome para evitar erro em bancos sem essa coluna indexada
// Re-ordenação por quantidade_estoque é feita em PHP abaixo
$stmt = db()->prepare(
    "SELECT p.*, c.nome as categoria_nome
     FROM " . table('produtos') . " p
     LEFT JOIN " . table('categorias') . " c ON p.categoria_id = c.id
     WHERE {$where_sql}
     ORDER BY p.quantidade_estoque ASC, p.nome ASC
     LIMIT {$pagination['offset']}, 20"
);
$stmt->execute($params);
$produtos = $stmt->fetchAll();

// Não reordenar em PHP — ORDER BY já faz isso no banco


// Histórico recente
try {
    $historico = db()->query("SELECT e.id, e.produto_id, e.quantidade, p.nome as produto_nome FROM " . table('produto_estoque') . " e LEFT JOIN " . table('produtos') . " p ON e.produto_id = p.id ORDER BY e.id DESC LIMIT 15")->fetchAll();
} catch(Exception $e) { $historico = []; }

require_once __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <h1><i class="fas fa-warehouse"></i> Controle de Estoque</h1>
    <div style="display:flex;gap:8px;flex-wrap:wrap;">
        <button onclick="abrirModalImportar()" class="btn btn-success"><i class="fas fa-file-excel"></i> Importar Planilha</button>
    </div>
</div>

<!-- Modal Importar Planilha de Estoque -->
<div id="modalImportar" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.6);z-index:2000;align-items:center;justify-content:center;padding:20px;">
    <div style="background:#fff;border-radius:var(--radius);width:100%;max-width:520px;padding:28px;position:relative;box-shadow:var(--shadow-lg);">
        <button onclick="fecharModalImportar()" style="position:absolute;top:16px;right:16px;background:var(--gray-100);border:none;width:32px;height:32px;border-radius:50%;cursor:pointer;font-size:1.1rem;display:flex;align-items:center;justify-content:center;">&times;</button>

        <h3 style="font-size:1.125rem;font-weight:700;margin-bottom:6px;"><i class="fas fa-file-excel" style="color:var(--success);margin-right:8px;"></i>Atualizar Estoque por Planilha</h3>
        <p style="color:var(--gray-500);font-size:0.875rem;margin-bottom:20px;">Envie um CSV, XLS ou XLSX com SKU/Nome do produto e a nova quantidade de estoque (e/ou estoque mínimo).</p>

        <div style="margin-bottom:16px;">
            <a href="estoque.php?action=download_modelo" class="btn btn-outline btn-sm" style="font-size:0.8125rem;">
                <i class="fas fa-download"></i> Baixar modelo de planilha
            </a>
        </div>

        <div id="dropZone" 
             style="border:2px dashed var(--gray-300);border-radius:var(--radius);padding:40px 20px;text-align:center;transition:all 0.2s;cursor:pointer;background:var(--gray-50);"
             ondragover="event.preventDefault();this.style.borderColor='var(--primary)';this.style.background='var(--primary-light)';"
             ondragleave="this.style.borderColor='var(--gray-300)';this.style.background='var(--gray-50)';"
             ondrop="event.preventDefault();handleDrop(event);"
             onclick="document.getElementById('inputPlanilha').click()">
            <i class="fas fa-cloud-upload-alt" style="font-size:2.5rem;color:var(--gray-400);margin-bottom:12px;display:block;"></i>
            <p style="font-weight:600;color:var(--gray-700);margin-bottom:4px;">Arraste o arquivo aqui</p>
            <p style="font-size:0.8125rem;color:var(--gray-500);">ou clique para selecionar (CSV, XLS, XLSX)</p>
        </div>

        <input type="file" id="inputPlanilha" accept=".csv,.xls,.xlsx" style="display:none;" onchange="handleFileSelect(this)">

        <div id="arquivoSelecionado" style="display:none;margin-top:12px;padding:10px 14px;background:var(--primary-light);border-radius:8px;border:1px solid var(--primary);display:flex;align-items:center;gap:10px;">
            <i class="fas fa-file-csv" style="color:var(--primary);font-size:1.25rem;"></i>
            <div style="flex:1;">
                <p id="nomeArquivo" style="font-weight:600;font-size:0.875rem;color:var(--gray-800);margin:0;"></p>
                <p id="tamanhoArquivo" style="font-size:0.75rem;color:var(--gray-500);margin:0;"></p>
            </div>
            <button onclick="limparArquivo()" style="background:none;border:none;color:var(--danger);cursor:pointer;font-size:0.875rem;padding:4px;"><i class="fas fa-times"></i></button>
        </div>

        <div id="importarLoading" style="display:none;text-align:center;padding:20px;">
            <i class="fas fa-spinner fa-spin" style="font-size:2rem;color:var(--primary);"></i>
            <p style="margin-top:12px;color:var(--gray-500);font-size:0.875rem;">Atualizando estoque, aguarde...</p>
        </div>

        <div id="importarResultado" style="display:none;margin-top:16px;"></div>

        <div style="display:flex;gap:10px;margin-top:20px;" id="botoesImportar">
            <button onclick="enviarPlanilha()" id="btnEnviar" class="btn btn-success" style="flex:1;" disabled>
                <i class="fas fa-upload"></i> Atualizar Agora
            </button>
            <button onclick="fecharModalImportar()" class="btn btn-secondary">Cancelar</button>
        </div>
    </div>
</div>

<!-- Modal de movimentação -->
<div id="modalMov" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:1000;align-items:center;justify-content:center;">
    <div style="background:#fff;border-radius:12px;padding:32px;width:100%;max-width:420px;">
        <h3 style="margin-bottom:20px;font-size:1.1rem;font-weight:700;"><i class="fas fa-exchange-alt" style="color:var(--primary);"></i> Movimentar Estoque</h3>
        <form method="POST">
            <input type="hidden" name="acao" value="movimentar">
            <input type="hidden" name="produto_id" id="movProdId">
            <div class="form-group">
                <label>Produto</label>
                <input type="text" id="movProdNome" readonly style="background:var(--gray-100);">
            </div>
            <div class="form-row form-row-2">
                <div class="form-group">
                    <label>Tipo</label>
                    <select name="tipo">
                        <option value="entrada">📦 Entrada</option>
                        <option value="saida">📤 Saída</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Quantidade *</label>
                    <input type="number" name="quantidade" min="1" value="1" required>
                </div>
            </div>
            <div style="display:flex;gap:10px;">
                <button type="submit" class="btn btn-primary"><i class="fas fa-check"></i> Confirmar</button>
                <button type="button" onclick="fecharModalMov()" class="btn btn-secondary">Cancelar</button>
            </div>
        </form>
    </div>
</div>

<div class="dashboard-grid">
    <!-- Lista de produtos -->
    <div class="card" style="grid-column:1/-1;">
        <div class="card-body" style="padding-bottom:0;">
            <form method="GET" class="filters-bar" id="filterForm">
                <div class="form-group"><input type="text" name="busca" value="<?php echo sanitize($busca); ?>" placeholder="Buscar produto..."></div>
                <div class="form-group">
                    <select name="filtro">
                        <option value="">Todos os produtos</option>
                        <option value="baixo" <?php echo selected($filtro,'baixo'); ?>>Estoque Baixo</option>
                        <option value="zero" <?php echo selected($filtro,'zero'); ?>>Sem Estoque</option>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Filtrar</button>
                <?php if ($busca||$filtro): ?><a href="estoque.php" class="btn btn-outline">Limpar</a><?php endif; ?>
            </form>
        </div>
        <div class="table-responsive">
            <table class="table">
                <thead><tr><th>Produto</th><th>SKU</th><th>Categoria</th><th>Estoque</th><th>Mínimo</th><th>Situação</th><th>Ações</th></tr></thead>
                <tbody>
                    <?php if (empty($produtos)): ?>
                    <tr><td colspan="7"><div class="empty-state-sm">Nenhum produto encontrado</div></td></tr>
                    <?php else: ?>
                    <?php foreach ($produtos as $p):
                        $baixo = $p['quantidade_estoque'] <= $p['estoque_minimo'] && $p['estoque_minimo'] > 0;
                        $zero  = $p['quantidade_estoque'] == 0;
                    ?>
                    <tr style="<?php echo $zero?'background:#fff5f5;':($baixo?'background:#fffbeb;':''); ?>">
                        <td><strong><?php echo sanitize($p['nome']); ?></strong></td>
                        <td><?php echo sanitize($p['sku']??'-'); ?></td>
                        <td><?php echo sanitize($p['categoria_nome']??'-'); ?></td>
                        <td>
                            <strong style="font-size:1.1rem;color:<?php echo $zero?'var(--danger)':($baixo?'var(--warning)':'var(--success)'); ?>">
                                <?php echo $p['quantidade_estoque']; ?>
                            </strong>
                            <small class="text-muted"> <?php echo sanitize($p['unidade']??'un'); ?></small>
                        </td>
                        <td><?php echo $p['estoque_minimo']; ?></td>
                        <td>
                            <?php if ($zero): ?>
                            <span class="badge-status status-cancelado">Sem estoque</span>
                            <?php elseif ($baixo): ?>
                            <span class="badge-status status-em_analise">Estoque baixo</span>
                            <?php else: ?>
                            <span class="badge-status status-aprovado">Normal</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <button onclick="abrirMov(<?php echo $p['id']; ?>,'<?php echo addslashes($p['nome']); ?>')"
                                    class="btn btn-sm btn-primary">
                                <i class="fas fa-exchange-alt"></i> Movimentar
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <div class="card-body">
            <?php echo pagination_links($pagination, 'estoque.php', array_filter(['busca'=>$busca,'filtro'=>$filtro])); ?>
        </div>
    </div>
</div>

<!-- Histórico -->
<div class="card">
    <div class="card-header"><h3><i class="fas fa-history"></i> Histórico de Movimentações</h3></div>
    <div class="table-responsive">
        <table class="table table-sm">
            <thead><tr><th>Produto</th><th>Estoque Registrado</th></tr></thead>
            <tbody>
                <?php if (empty($historico)): ?>
                <tr><td colspan="2"><div class="empty-state-sm">Nenhuma movimentação registrada</div></td></tr>
                <?php else: ?>
                <?php foreach ($historico as $h): ?>
                <tr>
                    <td><?php echo sanitize($h['produto_nome'] ?? '-'); ?></td>
                    <td><strong><?php echo (int)$h['quantidade']; ?></strong></td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
function abrirMov(id, nome) {
    document.getElementById('movProdId').value = id;
    document.getElementById('movProdNome').value = nome;
    document.getElementById('modalMov').style.display = 'flex';
}

// ===== IMPORTAÇÃO DE ESTOQUE POR PLANILHA =====
let arquivoSelecionado = null;

function abrirModalImportar() {
    document.getElementById('modalImportar').style.display = 'flex';
    limparArquivo();
}

function fecharModalImportar() {
    document.getElementById('modalImportar').style.display = 'none';
    limparArquivo();
}

function handleDrop(e) {
    const files = e.dataTransfer.files;
    if (files.length > 0) {
        processarArquivo(files[0]);
    }
}

function handleFileSelect(input) {
    if (input.files.length > 0) {
        processarArquivo(input.files[0]);
    }
}

function processarArquivo(file) {
    const extensoes = ['csv', 'xls', 'xlsx'];
    const ext = file.name.split('.').pop().toLowerCase();

    if (!extensoes.includes(ext)) {
        alert('Formato não suportado. Use CSV, XLS ou XLSX.');
        return;
    }

    arquivoSelecionado = file;
    document.getElementById('nomeArquivo').textContent = file.name;
    document.getElementById('tamanhoArquivo').textContent = (file.size / 1024).toFixed(1) + ' KB';
    document.getElementById('arquivoSelecionado').style.display = 'flex';
    document.getElementById('dropZone').style.display = 'none';
    document.getElementById('btnEnviar').disabled = false;
}

function limparArquivo() {
    arquivoSelecionado = null;
    document.getElementById('inputPlanilha').value = '';
    document.getElementById('arquivoSelecionado').style.display = 'none';
    document.getElementById('dropZone').style.display = 'block';
    document.getElementById('btnEnviar').disabled = true;
    document.getElementById('importarResultado').style.display = 'none';
}

function enviarPlanilha() {
    if (!arquivoSelecionado) return;

    const formData = new FormData();
    formData.append('planilha', arquivoSelecionado);

    document.getElementById('botoesImportar').style.display = 'none';
    document.getElementById('importarLoading').style.display = 'block';
    document.getElementById('importarResultado').style.display = 'none';

    fetch('estoque.php?action=importar_planilha', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(d => {
        document.getElementById('importarLoading').style.display = 'none';
        document.getElementById('botoesImportar').style.display = 'flex';

        const resultado = document.getElementById('importarResultado');
        resultado.style.display = 'block';

        if (d.ok) {
            let html = `<div style="padding:12px 16px;border-radius:8px;background:#dcfce7;color:#166534;border:1px solid #bbf7d0;font-size:0.875rem;">`;
            html += `<strong><i class="fas fa-check-circle" style="margin-right:6px;"></i>${d.msg}</strong>`;
            if (d.erros && d.erros.length > 0) {
                html += `<ul style="margin-top:8px;margin-bottom:0;padding-left:20px;font-size:0.8rem;">`;
                d.erros.slice(0, 5).forEach(e => html += `<li>${e}</li>`);
                if (d.erros.length > 5) html += `<li>...e mais ${d.erros.length - 5} erro(s)</li>`;
                html += `</ul>`;
            }
            html += `</div>`;
            resultado.innerHTML = html;

            // Recarregar página após 2 segundos se deu certo
            if (d.atualizados > 0) {
                setTimeout(() => window.location.reload(), 2000);
            }
        } else {
            resultado.innerHTML = `<div style="padding:12px 16px;border-radius:8px;background:#fee2e2;color:#991b1b;border:1px solid #fecaca;font-size:0.875rem;"><strong><i class="fas fa-exclamation-circle" style="margin-right:6px;"></i>Erro:</strong> ${d.msg}</div>`;
        }
    })
    .catch(() => {
        document.getElementById('importarLoading').style.display = 'none';
        document.getElementById('botoesImportar').style.display = 'flex';
        document.getElementById('importarResultado').style.display = 'block';
        document.getElementById('importarResultado').innerHTML = `<div style="padding:12px 16px;border-radius:8px;background:#fee2e2;color:#991b1b;border:1px solid #fecaca;font-size:0.875rem;"><strong><i class="fas fa-exclamation-circle" style="margin-right:6px;"></i>Erro de conexão.</strong> Tente novamente.</div>`;
    });
}

// Fechar modal ao clicar fora
document.getElementById('modalImportar').addEventListener('click', function(e) {
    if (e.target === this) fecharModalImportar();
});

// ESC para fechar
document.addEventListener('keydown', e => {
    if (e.key === 'Escape') {
        fecharModalImportar();
    }
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>