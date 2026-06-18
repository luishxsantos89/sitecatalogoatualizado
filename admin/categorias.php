<?php
/**
 * SiteCatalogo2 - Categorias (CRUD)
 */
require_once __DIR__ . '/includes/functions.php';
$page_title = 'Categorias';

$action = $_GET['action'] ?? 'list';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// ========== IMPORTAÇÃO POR PLANILHA ==========
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

    $resultado = ['ok' => true, 'importados' => 0, 'atualizados' => 0, 'erros' => []];

    try {
        $handle = fopen($_FILES['planilha']['tmp_name'], 'r');
        if (!$handle) { echo json_encode(['ok' => false, 'msg' => 'Não foi possível ler o arquivo.']); exit; }

        $primeira_linha = fgets($handle);
        rewind($handle);
        $delimitador = strpos($primeira_linha, ';') !== false ? ';' : ',';

        $cabecalho = fgetcsv($handle, 0, $delimitador);
        if (!$cabecalho) { echo json_encode(['ok' => false, 'msg' => 'Arquivo vazio ou formato inválido.']); exit; }

        $cabecalho = array_map(function($h) {
            return strtolower(trim(str_replace([' ', '_', '-'], '', mb_convert_encoding($h, 'UTF-8', 'UTF-8'))));
        }, $cabecalho);

        $map = [];
        $colunas_esperadas = [
            'nome'      => ['nome', 'categoria', 'titulo', 'name'],
            'descricao' => ['descricao', 'obs', 'observacao'],
            'icone'     => ['icone', 'icon', 'materialicon'],
            'ordem'     => ['ordem', 'order', 'posicao'],
            'ativo'     => ['ativo', 'status', 'situacao', 'active'],
        ];

        foreach ($cabecalho as $idx => $col) {
            foreach ($colunas_esperadas as $campo => $sinonimos) {
                if (in_array($col, $sinonimos)) { $map[$campo] = $idx; break; }
            }
        }

        if (!isset($map['nome'])) {
            echo json_encode(['ok' => false, 'msg' => 'Coluna "Nome" não encontrada. Cabeçalho detectado: ' . implode(', ', $cabecalho)]);
            exit;
        }

        $linha_num = 1;
        while (($dados = fgetcsv($handle, 0, $delimitador)) !== false) {
            $linha_num++;
            if (empty(array_filter($dados, 'trim'))) continue;

            $nome_imp = trim(mb_convert_encoding($dados[$map['nome']] ?? '', 'UTF-8', 'UTF-8'));
            if (empty($nome_imp)) { $resultado['erros'][] = "Linha {$linha_num}: nome vazio"; continue; }

            $desc_imp  = isset($map['descricao']) ? trim(mb_convert_encoding($dados[$map['descricao']] ?? '', 'UTF-8', 'UTF-8')) : '';
            $icone_imp = isset($map['icone'])     ? trim($dados[$map['icone']] ?? 'category') : 'category';
            $ordem_imp = isset($map['ordem'])     ? (int)$dados[$map['ordem']] : 0;
            $ativo_imp = isset($map['ativo'])
                ? (in_array(strtolower(trim($dados[$map['ativo']])), ['1','sim','s','yes','ativo','true']) ? 1 : 0)
                : 1;
            if (empty($icone_imp)) $icone_imp = 'category';

            $stmt_ex = db()->prepare("SELECT id FROM " . table('categorias') . " WHERE nome = ? LIMIT 1");
            $stmt_ex->execute([$nome_imp]);
            $existente = $stmt_ex->fetchColumn();

            if ($existente) {
                $slug_imp = unique_slug('categorias', slugify($nome_imp), $existente);
                db()->prepare("UPDATE " . table('categorias') . " SET nome=?, slug=?, descricao=?, icone=?, ordem=?, ativo=? WHERE id=?")
                    ->execute([$nome_imp, $slug_imp, $desc_imp, $icone_imp, $ordem_imp, $ativo_imp, $existente]);
                $resultado['atualizados']++;
            } else {
                $slug_imp = unique_slug('categorias', slugify($nome_imp));
                db()->prepare("INSERT INTO " . table('categorias') . " (nome, slug, descricao, icone, ordem, ativo) VALUES (?,?,?,?,?,?)")
                    ->execute([$nome_imp, $slug_imp, $desc_imp, $icone_imp, $ordem_imp, $ativo_imp]);
                $resultado['importados']++;
            }
        }
        fclose($handle);

        $resultado['msg'] = "Importação concluída! {$resultado['importados']} nova(s), {$resultado['atualizados']} atualizada(s).";
        if (!empty($resultado['erros'])) $resultado['msg'] .= " " . count($resultado['erros']) . " erro(s).";
        echo json_encode($resultado);
        exit;

    } catch (Exception $e) {
        echo json_encode(['ok' => false, 'msg' => 'Erro: ' . $e->getMessage()]);
        exit;
    }
}



// ========== EXPORTAR CATEGORIAS EM PLANILHA ==========
if ($action === 'exportar') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="categorias_' . date('Y-m-d') . '.csv"');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['ID', 'Nome', 'Slug', 'Descrição', 'Ícone', 'Ordem', 'Ativo', 'Total Produtos'], ';');
    try {
        $stmt_exp = db()->query("SELECT c.*, (SELECT COUNT(*) FROM " . table('produtos') . " WHERE categoria_id = c.id) as total_produtos FROM " . table('categorias') . " c ORDER BY c.ordem, c.nome");
        foreach ($stmt_exp->fetchAll() as $cat) {
            fputcsv($output, [
                $cat['id'], $cat['nome'], $cat['slug'], $cat['descricao'] ?? '',
                $cat['icone'] ?? 'category', (int)$cat['ordem'],
                $cat['ativo'] ? 'Sim' : 'Não', (int)$cat['total_produtos'],
            ], ';');
        }
    } catch (Exception $e) {}
    fclose($output);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = trim($_POST['nome'] ?? '');
    if (empty($nome)) {
        set_flash('error', 'Nome é obrigatório');
    } else {
        $dados = [
            'nome'    => $nome,
            'slug'    => slugify($nome),
            'descricao'=> trim($_POST['descricao'] ?? ''),
            'icone'   => trim($_POST['icone'] ?? 'category'),
            'ordem'   => (int)($_POST['ordem'] ?? 0),
            'ativo'   => isset($_POST['ativo']) ? 1 : 0,
        ];

        try {
            if ($id) {
                $dados['slug'] = unique_slug('categorias', $dados['slug'], $id);
                $f = []; $v = [];
                foreach ($dados as $k => $val) { $f[] = "{$k} = ?"; $v[] = $val; }
                $v[] = $id;
                db()->prepare("UPDATE " . table('categorias') . " SET " . implode(', ', $f) . " WHERE id = ?")->execute($v);
                set_flash('success', 'Categoria atualizada!');
            } else {
                $dados['slug'] = unique_slug('categorias', $dados['slug']);
                $cols = implode(', ', array_keys($dados));
                $ph = implode(', ', array_fill(0, count($dados), '?'));
                db()->prepare("INSERT INTO " . table('categorias') . " ({$cols}) VALUES ({$ph})")->execute(array_values($dados));
                set_flash('success', 'Categoria criada!');
            }
            header('Location: categorias.php'); exit;
        } catch (Exception $e) { set_flash('error', 'Erro: ' . $e->getMessage()); }
    }
}

if ($action === 'delete' && $id) {
    try {
        db()->prepare("DELETE FROM " . table('categorias') . " WHERE id = ?")->execute([$id]);
        set_flash('success', 'Categoria excluída!');
    } catch (Exception $e) { set_flash('error', 'Erro: ' . $e->getMessage()); }
    header('Location: categorias.php'); exit;
}

$categoria = null;
if (($action === 'edit') && $id) {
    $stmt = db()->prepare("SELECT * FROM " . table('categorias') . " WHERE id = ?"); $stmt->execute([$id]); $categoria = $stmt->fetch();
}

$categorias = db()->query("SELECT c.*, (SELECT COUNT(*) FROM " . table('produtos') . " WHERE categoria_id = c.id) as total_produtos FROM " . table('categorias') . " c ORDER BY c.ordem, c.nome")->fetchAll();

// Lista de ícones Material Icons populares para categorias
$icones_sugeridos = [
    'category' => 'Category',
    'shopping_bag' => 'Shopping Bag',
    'inventory_2' => 'Inventory',
    'local_offer' => 'Oferta',
    'devices' => 'Dispositivos',
    'phone_android' => 'Celular',
    'computer' => 'Computador',
    'tv' => 'TV',
    'home' => 'Casa',
    'chair' => 'Móveis',
    'bed' => 'Cama',
    'kitchen' => 'Cozinha',
    'checkroom' => 'Roupas',
    'diamond' => 'Joias',
    'watch' => 'Relógios',
    'sports_soccer' => 'Esportes',
    'fitness_center' => 'Academia',
    'directions_car' => 'Automóveis',
    'build' => 'Ferramentas',
    'electrical_services' => 'Elétrica',
    'plumbing' => 'Hidráulica',
    'yard' => 'Jardim',
    'restaurant' => 'Alimentação',
    'local_grocery_store' => 'Mercado',
    'spa' => 'Beleza',
    'medical_services' => 'Saúde',
    'school' => 'Educação',
    'library_books' => 'Livros',
    'toys' => 'Brinquedos',
    'child_care' => 'Bebês',
    'pets' => 'Pets',
    'agriculture' => 'Agro',
    'construction' => 'Construção',
    'precision_manufacturing' => 'Indústria',
    'palette' => 'Arte',
    'music_note' => 'Música',
    'photo_camera' => 'Fotografia',
    'sports_esports' => 'Games',
    'flight' => 'Viagem',
    'sailing' => 'Náutica',
];

require_once __DIR__ . '/includes/header.php';
?>

<link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
<style>
.icone-grid { display: grid; grid-template-columns: repeat(6, 1fr); gap: 8px; max-height: 320px; overflow-y: auto; padding: 4px; }
.icone-item { display: flex; flex-direction: column; align-items: center; gap: 4px; padding: 8px 4px; border-radius: 8px; cursor: pointer; border: 2px solid transparent; font-size: 0.7rem; color: var(--gray-600); transition: all 0.15s; }
.icone-item:hover { background: var(--gray-100); border-color: var(--primary); }
.icone-item.selected { background: var(--primary-light, #eff6ff); border-color: var(--primary); color: var(--primary); font-weight: 600; }
.icone-item .material-icons { font-size: 28px; }
.busca-icone { width: 100%; padding: 8px 12px; border: 1px solid var(--gray-200); border-radius: 8px; margin-bottom: 10px; font-size: 0.875rem; }
.preview-icone { display: flex; align-items: center; gap: 8px; padding: 10px; background: var(--gray-50); border-radius: 8px; margin-bottom: 10px; border: 1px solid var(--gray-200); }
.preview-icone .material-icons { font-size: 32px; color: var(--primary); }
</style>

<div class="page-header">
    <h1><i class="fas fa-tags"></i> Categorias</h1>
    <div style="display:flex;gap:8px;flex-wrap:wrap;">
        <button onclick="abrirModalImportarCat()" class="btn btn-success"><i class="fas fa-file-excel"></i> Importar Planilha</button>
        <a href="categorias.php?action=exportar" class="btn btn-outline"><i class="fas fa-file-export"></i> Exportar Planilha</a>
        <button onclick="abrirModal()" class="btn btn-primary"><i class="fas fa-plus"></i> Nova Categoria</button>
    </div>
</div>

<!-- Modal Importar Planilha de Categorias -->
<div id="modalImportarCat" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.6);z-index:2000;align-items:center;justify-content:center;padding:20px;">
    <div style="background:#fff;border-radius:var(--radius);width:100%;max-width:520px;padding:28px;position:relative;box-shadow:var(--shadow-lg);">
        <button onclick="fecharModalImportarCat()" style="position:absolute;top:16px;right:16px;background:var(--gray-100);border:none;width:32px;height:32px;border-radius:50%;cursor:pointer;font-size:1.1rem;display:flex;align-items:center;justify-content:center;">&times;</button>

        <h3 style="font-size:1.125rem;font-weight:700;margin-bottom:6px;"><i class="fas fa-file-excel" style="color:var(--success);margin-right:8px;"></i>Importar Categorias por Planilha</h3>
        <p style="color:var(--gray-500);font-size:0.875rem;margin-bottom:20px;">Envie um arquivo CSV, XLS ou XLSX com os dados das categorias. Categorias existentes (mesmo nome) serão atualizadas.</p>

        <div style="margin-bottom:16px;">
            <a href="includes/categorias.csv" download class="btn btn-outline btn-sm" style="font-size:0.8125rem;">
                <i class="fas fa-download"></i> Baixar modelo de planilha
            </a>
        </div>

        <div id="dropZoneCat"
             style="border:2px dashed var(--gray-300);border-radius:var(--radius);padding:40px 20px;text-align:center;transition:all 0.2s;cursor:pointer;background:var(--gray-50);"
             ondragover="event.preventDefault();this.style.borderColor='var(--primary)';this.style.background='var(--primary-light)';"
             ondragleave="this.style.borderColor='var(--gray-300)';this.style.background='var(--gray-50)';"
             ondrop="event.preventDefault();handleDropCat(event);"
             onclick="document.getElementById('inputPlanilhaCat').click()">
            <i class="fas fa-cloud-upload-alt" style="font-size:2.5rem;color:var(--gray-400);margin-bottom:12px;display:block;"></i>
            <p style="font-weight:600;color:var(--gray-700);margin-bottom:4px;">Arraste o arquivo aqui</p>
            <p style="font-size:0.8125rem;color:var(--gray-500);">ou clique para selecionar (CSV, XLS, XLSX)</p>
        </div>

        <input type="file" id="inputPlanilhaCat" accept=".csv,.xls,.xlsx" style="display:none;" onchange="handleFileSelectCat(this)">

        <div id="arquivoSelecionadoCat" style="display:none;margin-top:12px;padding:10px 14px;background:var(--primary-light);border-radius:8px;border:1px solid var(--primary);align-items:center;gap:10px;">
            <i class="fas fa-file-csv" style="color:var(--primary);font-size:1.25rem;"></i>
            <div style="flex:1;">
                <p id="nomeArquivoCat" style="font-weight:600;font-size:0.875rem;color:var(--gray-800);margin:0;"></p>
                <p id="tamanhoArquivoCat" style="font-size:0.75rem;color:var(--gray-500);margin:0;"></p>
            </div>
            <button onclick="limparArquivoCat()" style="background:none;border:none;color:var(--danger);cursor:pointer;font-size:0.875rem;padding:4px;"><i class="fas fa-times"></i></button>
        </div>

        <div id="importarLoadingCat" style="display:none;text-align:center;padding:20px;">
            <i class="fas fa-spinner fa-spin" style="font-size:2rem;color:var(--primary);"></i>
            <p style="margin-top:12px;color:var(--gray-500);font-size:0.875rem;">Importando categorias, aguarde...</p>
        </div>

        <div id="importarResultadoCat" style="display:none;margin-top:16px;"></div>

        <div style="display:flex;gap:10px;margin-top:20px;" id="botoesImportarCat">
            <button onclick="enviarPlanilhaCat()" id="btnEnviarCat" class="btn btn-success" style="flex:1;" disabled>
                <i class="fas fa-upload"></i> Importar Agora
            </button>
            <button onclick="fecharModalImportarCat()" class="btn btn-secondary">Cancelar</button>
        </div>
    </div>
</div>

<!-- Modal Form -->
<div id="formModal" style="display:<?php echo ($action==='edit'||$action==='new')?'flex':'none'; ?>;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:1000;align-items:center;justify-content:center;">
    <div style="background:#fff;border-radius:12px;padding:32px;width:100%;max-width:580px;max-height:92vh;overflow-y:auto;">
        <h3 style="margin-bottom:20px;font-size:1.125rem;font-weight:700;color:var(--gray-900);">
            <i class="fas fa-tags" style="color:var(--primary);"></i> <span id="modalTitulo"><?php echo $categoria?'Editar':'Nova'; ?></span> Categoria
        </h3>
        <form method="POST" action="categorias.php<?php echo $id?"?action=edit&id={$id}":"?action=new"; ?>" id="formCategoria">
            <input type="hidden" name="icone" id="iconeInput" value="<?php echo sanitize($categoria['icone'] ?? 'category'); ?>">
            <div class="form-group">
                <label>Nome *</label>
                <input type="text" name="nome" id="nomeInput" value="<?php echo sanitize($categoria['nome']??''); ?>" required>
            </div>
            <div class="form-group">
                <label>Descrição</label>
                <textarea name="descricao" rows="2"><?php echo sanitize($categoria['descricao']??''); ?></textarea>
            </div>

            <div class="form-group">
                <label>Ícone (Material Icons)</label>
                <div class="preview-icone">
                    <span class="material-icons" id="previewIcone" style="color:var(--primary);"><?php echo sanitize($categoria['icone'] ?? 'category'); ?></span>
                    <div>
                        <strong id="previewNomeIcone"><?php echo $icones_sugeridos[$categoria['icone'] ?? 'category'] ?? ($categoria['icone'] ?? 'category'); ?></strong><br>
                        <small class="text-muted">Ícone selecionado</small>
                    </div>
                </div>

                <input type="text" class="busca-icone" id="buscaIcone" placeholder="&#xf002; Buscar ícone ou digitar nome..." oninput="filtrarIcones(this.value)">

                <div class="icone-grid" id="iconeGrid">
                    <?php foreach ($icones_sugeridos as $ico => $label): ?>
                    <div class="icone-item <?php echo ($categoria['icone']??'category') === $ico ? 'selected' : ''; ?>"
                         onclick="selecionarIcone('<?php echo $ico; ?>', '<?php echo $label; ?>')"
                         data-nome="<?php echo strtolower($label . ' ' . $ico); ?>"
                         title="<?php echo $label; ?>">
                        <span class="material-icons"><?php echo $ico; ?></span>
                        <span><?php echo $label; ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>

                <div style="margin-top:8px;">
                    <small class="text-muted">
                        <i class="fas fa-info-circle"></i>
                        Ou digite um nome de ícone diretamente:
                        <a href="https://fonts.google.com/icons" target="_blank">Ver todos os ícones</a>
                    </small>
                    <div style="display:flex;gap:8px;margin-top:6px;">
                        <input type="text" id="iconeCustom" placeholder="Ex: local_pizza, star, home..." style="flex:1;padding:7px 10px;border:1px solid var(--gray-200);border-radius:8px;font-size:0.875rem;" value="">
                        <button type="button" onclick="usarCustom()" class="btn btn-outline" style="white-space:nowrap;"><span class="material-icons" style="font-size:16px;vertical-align:middle;">preview</span> Usar</button>
                    </div>
                </div>
            </div>

            <div class="form-row form-row-2">
                <div class="form-group">
                    <label>Ordem</label>
                    <input type="number" name="ordem" value="<?php echo (int)($categoria['ordem']??0); ?>" min="0">
                </div>
                <div class="form-group" style="display:flex;align-items:flex-end;">
                    <div class="form-check">
                        <input type="checkbox" name="ativo" id="ativo_cat" <?php echo checked($categoria['ativo']??1); ?>>
                        <label for="ativo_cat">Ativa</label>
                    </div>
                </div>
            </div>
            <div style="display:flex;gap:10px;">
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Salvar</button>
                <button type="button" onclick="fecharModal()" class="btn btn-secondary">Cancelar</button>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr><th>Ícone</th><th>Nome</th><th>Slug</th><th>Ordem</th><th>Produtos</th><th>Status</th><th>Ações</th></tr>
            </thead>
            <tbody>
                <?php if (empty($categorias)): ?>
                <tr><td colspan="7"><div class="empty-state-sm">Nenhuma categoria ainda</div></td></tr>
                <?php else: ?>
                <?php foreach ($categorias as $cat): ?>
                <tr>
                    <td>
                        <div style="width:48px;height:48px;background:var(--primary-light,#eff6ff);border-radius:10px;display:flex;align-items:center;justify-content:center;">
                            <span class="material-icons" style="color:var(--primary);font-size:26px;"><?php echo sanitize($cat['icone'] ?? 'category'); ?></span>
                        </div>
                    </td>
                    <td><strong><?php echo sanitize($cat['nome']); ?></strong></td>
                    <td><code style="font-size:0.8em;background:var(--gray-100);padding:2px 6px;border-radius:4px;"><?php echo sanitize($cat['slug']); ?></code></td>
                    <td><?php echo $cat['ordem']; ?></td>
                    <td><span class="badge" style="background:var(--primary-light,#eff6ff);color:var(--primary);"><?php echo $cat['total_produtos']; ?></span></td>
                    <td><span class="badge-status <?php echo $cat['ativo']?'status-ativo':'status-inativo'; ?>"><?php echo $cat['ativo']?'Ativa':'Inativa'; ?></span></td>
                    <td>
                        <a href="categorias.php?action=edit&id=<?php echo $cat['id']; ?>" class="btn btn-sm btn-primary" onclick="editarCategoria(<?php echo $cat['id']; ?>)"><i class="fas fa-edit"></i></a>
                        <a href="categorias.php?action=delete&id=<?php echo $cat['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Excluir categoria?')"><i class="fas fa-trash"></i></a>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
function abrirModal(editando) {
    document.getElementById('formModal').style.display = 'flex';
}
function fecharModal() {
    document.getElementById('formModal').style.display = 'none';
}

function selecionarIcone(nome, label) {
    document.getElementById('iconeInput').value = nome;
    document.getElementById('previewIcone').textContent = nome;
    document.getElementById('previewNomeIcone').textContent = label || nome;
    document.querySelectorAll('.icone-item').forEach(el => el.classList.remove('selected'));
    event.currentTarget.classList.add('selected');
}

function usarCustom() {
    const v = document.getElementById('iconeCustom').value.trim();
    if (!v) return;
    document.getElementById('iconeInput').value = v;
    document.getElementById('previewIcone').textContent = v;
    document.getElementById('previewNomeIcone').textContent = v;
    document.querySelectorAll('.icone-item').forEach(el => el.classList.remove('selected'));
}

function filtrarIcones(q) {
    q = q.toLowerCase().trim();
    document.querySelectorAll('.icone-item').forEach(el => {
        const nm = el.getAttribute('data-nome') || '';
        el.style.display = (!q || nm.includes(q)) ? '' : 'none';
    });
    if (q && !q.includes(' ')) {
        document.getElementById('iconeInput').value = q;
        document.getElementById('previewIcone').textContent = q;
    }
}

// ===== IMPORTAÇÃO DE CATEGORIAS POR PLANILHA =====
let arquivoSelecionadoCat = null;

function abrirModalImportarCat() {
    document.getElementById('modalImportarCat').style.display = 'flex';
    limparArquivoCat();
}

function fecharModalImportarCat() {
    document.getElementById('modalImportarCat').style.display = 'none';
    limparArquivoCat();
}

function handleDropCat(e) {
    if (e.dataTransfer.files.length > 0) processarArquivoCat(e.dataTransfer.files[0]);
}

function handleFileSelectCat(input) {
    if (input.files.length > 0) processarArquivoCat(input.files[0]);
}

function processarArquivoCat(file) {
    const ext = file.name.split('.').pop().toLowerCase();
    if (!['csv','xls','xlsx'].includes(ext)) { alert('Formato não suportado. Use CSV, XLS ou XLSX.'); return; }
    arquivoSelecionadoCat = file;
    document.getElementById('nomeArquivoCat').textContent = file.name;
    document.getElementById('tamanhoArquivoCat').textContent = (file.size / 1024).toFixed(1) + ' KB';
    document.getElementById('arquivoSelecionadoCat').style.display = 'flex';
    document.getElementById('dropZoneCat').style.display = 'none';
    document.getElementById('btnEnviarCat').disabled = false;
}

function limparArquivoCat() {
    arquivoSelecionadoCat = null;
    document.getElementById('inputPlanilhaCat').value = '';
    document.getElementById('arquivoSelecionadoCat').style.display = 'none';
    document.getElementById('dropZoneCat').style.display = 'block';
    document.getElementById('btnEnviarCat').disabled = true;
    document.getElementById('importarResultadoCat').style.display = 'none';
}

function enviarPlanilhaCat() {
    if (!arquivoSelecionadoCat) return;
    const formData = new FormData();
    formData.append('planilha', arquivoSelecionadoCat);

    document.getElementById('botoesImportarCat').style.display = 'none';
    document.getElementById('importarLoadingCat').style.display = 'block';
    document.getElementById('importarResultadoCat').style.display = 'none';

    fetch('categorias.php?action=importar_planilha', { method: 'POST', body: formData })
    .then(r => r.json())
    .then(d => {
        document.getElementById('importarLoadingCat').style.display = 'none';
        document.getElementById('botoesImportarCat').style.display = 'flex';
        const resultado = document.getElementById('importarResultadoCat');
        resultado.style.display = 'block';
        if (d.ok) {
            let html = `<div style="padding:12px 16px;border-radius:8px;background:#dcfce7;color:#166534;border:1px solid #bbf7d0;font-size:0.875rem;"><strong><i class="fas fa-check-circle" style="margin-right:6px;"></i>${d.msg}</strong>`;
            if (d.erros && d.erros.length > 0) {
                html += `<ul style="margin-top:8px;margin-bottom:0;padding-left:20px;font-size:0.8rem;">`;
                d.erros.slice(0, 5).forEach(e => html += `<li>${e}</li>`);
                if (d.erros.length > 5) html += `<li>...e mais ${d.erros.length - 5} erro(s)</li>`;
                html += `</ul>`;
            }
            html += `</div>`;
            resultado.innerHTML = html;
            if (d.importados > 0 || d.atualizados > 0) setTimeout(() => window.location.reload(), 2000);
        } else {
            resultado.innerHTML = `<div style="padding:12px 16px;border-radius:8px;background:#fee2e2;color:#991b1b;border:1px solid #fecaca;font-size:0.875rem;"><strong><i class="fas fa-exclamation-circle" style="margin-right:6px;"></i>Erro:</strong> ${d.msg}</div>`;
        }
    })
    .catch(() => {
        document.getElementById('importarLoadingCat').style.display = 'none';
        document.getElementById('botoesImportarCat').style.display = 'flex';
        document.getElementById('importarResultadoCat').style.display = 'block';
        document.getElementById('importarResultadoCat').innerHTML = `<div style="padding:12px 16px;border-radius:8px;background:#fee2e2;color:#991b1b;border:1px solid #fecaca;font-size:0.875rem;"><strong><i class="fas fa-exclamation-circle" style="margin-right:6px;"></i>Erro de conexão.</strong> Tente novamente.</div>`;
    });
}

document.getElementById('modalImportarCat').addEventListener('click', function(e) {
    if (e.target === this) fecharModalImportarCat();
});

document.addEventListener('keydown', e => { if (e.key === 'Escape') { fecharModal(); fecharModalImportarCat(); } });
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>