<?php
/**
 * SiteCatalogo2 - Produtos (CRUD completo) + Importação/Exportação por Planilha
 */
require_once __DIR__ . '/includes/functions.php';

// === CONTROLE DE ACESSO ===
require_auth();
if (!check_permission('gerente')) {
    header('Location: ' . admin_url());
    exit('Acesso negado.');
}

$page_title = 'Produtos';

// ========== HELPER: URL CORRETA PARA UPLOADS ==========
// Garante que as imagens apontem para /admin/uploads/ corretamente
if (!function_exists('produto_imagem_url')) {
    function produto_imagem_url(?string $caminho): string {
        if (empty($caminho)) return '';
        // Se já for URL completa, retorna como está
        if (preg_match('/^https?:\/\//i', $caminho)) return $caminho;
        // Remove barras duplicadas e garante caminho correto
        $caminho = ltrim($caminho, '/');
        return '/admin/uploads/' . $caminho;
    }
}


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

    $resultado = ['ok' => true, 'importados' => 0, 'erros' => [], 'atualizados' => 0];

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

        // Mapear colunas
        $map = [];
        $colunas_esperadas = [
            'nome' => ['nome', 'produto', 'descricao', 'titulo'],
            'sku' => ['sku', 'codigo', 'referencia', 'ref'],
            'preco' => ['preco', 'valor', 'precovenda'],
            'preco_promocional' => ['precopromocional', 'promocional', 'precopromo', 'oferta'],
            'custo' => ['custo', 'precocusto'],
            'quantidade_estoque' => ['quantidadeestoque', 'estoque', 'qtd', 'quantidade', 'qtdestoque'],
            'estoque_minimo' => ['estoqueminimo', 'minimo', 'estmin'],
            'categoria_id' => ['categoriaid', 'categoria', 'cat', 'idcategoria'],
            'unidade' => ['unidade', 'und', 'un'],
            'descricao_curta' => ['descricaocurta', 'resumo', 'sinopse'],
            'descricao' => ['descricao', 'detalhes', 'texto'],
            'tags' => ['tags', 'etiquetas', 'palavraschave'],
            'ativo' => ['ativo', 'status', 'situacao'],
            'destaque' => ['destaque', 'destacado', 'emdestaque'],
            'imagem_url' => ['imagemurl', 'imagem', 'urlimagem', 'imageurl', 'foto', 'fotoprincipal', 'imagemprincipal'],
        ];

        // Função para baixar imagem de URL e converter para WebP
        function baixarImagemWebp(string $url): ?string {
            if (empty(trim($url))) return null;

            $is_url = preg_match('/^https?:\/\//i', $url);
            if (!$is_url) return null;

            $upload_dir = __DIR__ . '/uploads/produtos/';
            if (!is_dir($upload_dir)) @mkdir($upload_dir, 0755, true);

            $ctx = stream_context_create([
                'http' => [
                    'timeout'    => 15,
                    'user_agent' => 'Mozilla/5.0 (compatible; SiteCatalogo/2.0)',
                    'follow_location' => true,
                ],
                'ssl' => ['verify_peer' => false, 'verify_peer_name' => false],
            ]);

            $raw = @file_get_contents($url, false, $ctx);
            if (!$raw || strlen($raw) < 100) return null;

            $img = @imagecreatefromstring($raw);
            if (!$img) return null;

            $nome_arquivo = 'imp_' . uniqid() . '.webp';
            $caminho_full = $upload_dir . $nome_arquivo;

            $ok = @imagewebp($img, $caminho_full, 82);
            imagedestroy($img);

            if (!$ok || !file_exists($caminho_full)) return null;

            return 'produtos/' . $nome_arquivo;
        }

        foreach ($cabecalho as $idx => $col) {
            foreach ($colunas_esperadas as $campo => $sinonimos) {
                if (in_array($col, $sinonimos)) {
                    $map[$campo] = $idx;
                    break;
                }
            }
        }

        if (!isset($map['nome'])) {
            echo json_encode(['ok' => false, 'msg' => 'Coluna "nome" não encontrada no cabeçalho. Cabeçalho detectado: ' . implode(', ', $cabecalho)]);
            exit;
        }

        $linha_num = 1;
        while (($dados = fgetcsv($handle, 0, $delimitador)) !== false) {
            $linha_num++;

            if (empty(array_filter($dados, 'trim'))) continue;

            $nome = trim($dados[$map['nome']] ?? '');
            if (empty($nome)) {
                $resultado['erros'][] = "Linha {$linha_num}: nome vazio";
                continue;
            }

            $nome = mb_convert_encoding($nome, 'UTF-8', 'UTF-8');

            $sku = isset($map['sku']) ? trim($dados[$map['sku']]) : '';
            $preco = isset($map['preco']) ? (float)str_replace(['.', ','], ['', '.'], $dados[$map['preco']]) : 0;
            $preco_promocional = isset($map['preco_promocional']) && !empty($dados[$map['preco_promocional']]) ? (float)str_replace(['.', ','], ['', '.'], $dados[$map['preco_promocional']]) : null;
            $custo = isset($map['custo']) && !empty($dados[$map['custo']]) ? (float)str_replace(['.', ','], ['', '.'], $dados[$map['custo']]) : null;
            $quantidade_estoque = isset($map['quantidade_estoque']) ? (int)$dados[$map['quantidade_estoque']] : 0;
            $estoque_minimo = isset($map['estoque_minimo']) ? (int)$dados[$map['estoque_minimo']] : 5;
            $unidade = isset($map['unidade']) ? trim($dados[$map['unidade']]) : 'un';
            $descricao_curta = isset($map['descricao_curta']) ? trim(mb_convert_encoding($dados[$map['descricao_curta']], 'UTF-8', 'UTF-8')) : '';
            $descricao = isset($map['descricao']) ? trim(mb_convert_encoding($dados[$map['descricao']], 'UTF-8', 'UTF-8')) : '';
            $tags = isset($map['tags']) ? trim(mb_convert_encoding($dados[$map['tags']], 'UTF-8', 'UTF-8')) : '';
            $ativo = isset($map['ativo']) ? (in_array(strtolower(trim($dados[$map['ativo']])), ['1', 'sim', 's', 'yes', 'ativo', 'true']) ? 1 : 0) : 1;
            $destaque = isset($map['destaque']) ? (in_array(strtolower(trim($dados[$map['destaque']])), ['1', 'sim', 's', 'yes', 'destaque', 'true']) ? 1 : 0) : 0;

            $imagem_importada = null;
            if (isset($map['imagem_url']) && !empty(trim($dados[$map['imagem_url']] ?? ''))) {
                $img_url = trim($dados[$map['imagem_url']]);
                $webp_path = baixarImagemWebp($img_url);
                if ($webp_path) {
                    $imagem_importada = $webp_path;
                } else {
                    $resultado['erros'][] = "Linha {$linha_num}: não foi possível baixar/converter a imagem de '{$img_url}'";
                }
            }

            $categoria_id = null;
            if (isset($map['categoria_id']) && !empty($dados[$map['categoria_id']])) {
                $cat_valor = trim($dados[$map['categoria_id']]);
                if (is_numeric($cat_valor) && (int)$cat_valor > 0) {
                    // Verificar se a categoria existe no banco
                    $stmt_check = db()->prepare("SELECT id FROM " . table('categorias') . " WHERE id = ? LIMIT 1");
                    $stmt_check->execute([(int)$cat_valor]);
                    if ($stmt_check->fetchColumn()) {
                        $categoria_id = (int)$cat_valor;
                    } else {
                        $resultado['erros'][] = "Linha {$linha_num}: categoria ID '{$cat_valor}' não encontrada no banco";
                    }
                } elseif (!is_numeric($cat_valor)) {
                    $stmt_cat = db()->prepare("SELECT id FROM " . table('categorias') . " WHERE nome = ? AND ativo = 1 LIMIT 1");
                    $stmt_cat->execute([$cat_valor]);
                    $cat_id = $stmt_cat->fetchColumn();
                    if ($cat_id) {
                        $categoria_id = (int)$cat_id;
                    } else {
                        $resultado['erros'][] = "Linha {$linha_num}: categoria '{$cat_valor}' não encontrada";
                    }
                }
            }

            $slug = slugify($nome);

            $existente = null;
            if (!empty($sku)) {
                $stmt_ex = db()->prepare("SELECT id FROM " . table('produtos') . " WHERE sku = ? LIMIT 1");
                $stmt_ex->execute([$sku]);
                $existente = $stmt_ex->fetchColumn();
            }
            if (!$existente) {
                $stmt_ex = db()->prepare("SELECT id FROM " . table('produtos') . " WHERE nome = ? LIMIT 1");
                $stmt_ex->execute([$nome]);
                $existente = $stmt_ex->fetchColumn();
            }

            if ($existente) {
                // Garantir que categoria_id seja NULL se inválido
                if ($categoria_id !== null && $categoria_id <= 0) {
                    $categoria_id = null;
                }

                $sql_update = "UPDATE " . table('produtos') . " SET 
                    nome = ?, slug = ?, descricao = ?, descricao_curta = ?, 
                    preco = ?, preco_promocional = ?, custo = ?, 
                    quantidade_estoque = ?, estoque_minimo = ?, unidade = ?,
                    categoria_id = ?, tags = ?, ativo = ?, destaque = ?, updated_at = NOW()";
                $params_upd = [
                    $nome, $slug, $descricao, $descricao_curta,
                    $preco, $preco_promocional, $custo,
                    $quantidade_estoque, $estoque_minimo, $unidade,
                    $categoria_id, $tags, $ativo, $destaque
                ];
                if ($imagem_importada) {
                    $sql_update .= ", imagem_principal = ?";
                    $params_upd[] = $imagem_importada;
                }
                $sql_update .= " WHERE id = ?";
                $params_upd[] = $existente;
                db()->prepare($sql_update)->execute($params_upd);
                $resultado['atualizados']++;
            } else {
                $slug = unique_slug('produtos', $slug);
                // Garantir que categoria_id seja NULL se inválido
                if ($categoria_id !== null && $categoria_id <= 0) {
                    $categoria_id = null;
                }

                db()->prepare("INSERT INTO " . table('produtos') . " 
                    (nome, slug, sku, descricao, descricao_curta, preco, preco_promocional, custo,
                     quantidade_estoque, estoque_minimo, unidade, categoria_id, tags, ativo, destaque,
                     imagem_principal, created_at, updated_at)
                    VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW(),NOW())")
                    ->execute([
                        $nome, $slug, $sku, $descricao, $descricao_curta, $preco, $preco_promocional, $custo,
                        $quantidade_estoque, $estoque_minimo, $unidade, $categoria_id, $tags, $ativo, $destaque,
                        $imagem_importada
                    ]);
                $resultado['importados']++;
            }
        }

        fclose($handle);

        $total = $resultado['importados'] + $resultado['atualizados'];
        $resultado['msg'] = "Importação concluída! {$resultado['importados']} novo(s), {$resultado['atualizados']} atualizado(s).";

        if (!empty($resultado['erros'])) {
            $resultado['msg'] .= " " . count($resultado['erros']) . " aviso(s)/erro(s) encontrado(s).";
        }

        // Se teve erros de categoria, adicionar dica
        $erros_categoria = array_filter($resultado['erros'], function($e) {
            return stripos($e, 'categoria') !== false;
        });
        if (!empty($erros_categoria)) {
            $resultado['msg'] .= " Dica: deixe a coluna 'Categoria ID' em branco ou use um ID/nome válido.";
        }

        echo json_encode($resultado);
        exit;

    } catch (Exception $e) {
        echo json_encode(['ok' => false, 'msg' => 'Erro: ' . $e->getMessage()]);
        exit;
    }
}



// ========== EXPORTAR PRODUTOS EM PLANILHA ==========
if ($action === 'exportar') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="produtos_' . date('Y-m-d') . '.csv"');

    $output = fopen('php://output', 'w');
    fputcsv($output, [
        'ID', 'Nome', 'SKU', 'Categoria', 'Preço', 'Preço Promocional', 'Custo',
        'Quantidade Estoque', 'Estoque Mínimo', 'Unidade', 'Descrição Curta',
        'Tags', 'Ativo', 'Destaque', 'Data Criação'
    ], ';');

    try {
        $stmt_exp = db()->query("
            SELECT p.*, c.nome as categoria_nome
            FROM " . table('produtos') . " p
            LEFT JOIN " . table('categorias') . " c ON p.categoria_id = c.id
            ORDER BY p.nome
        ");
        foreach ($stmt_exp->fetchAll() as $p) {
            fputcsv($output, [
                $p['id'],
                $p['nome'],
                $p['sku'] ?? '',
                $p['categoria_nome'] ?? '',
                number_format((float)$p['preco'], 2, ',', '.'),
                $p['preco_promocional'] ? number_format((float)$p['preco_promocional'], 2, ',', '.') : '',
                $p['custo'] ? number_format((float)$p['custo'], 2, ',', '.') : '',
                (int)$p['quantidade_estoque'],
                (int)$p['estoque_minimo'],
                $p['unidade'] ?? 'un',
                $p['descricao_curta'] ?? '',
                $p['tags'] ?? '',
                $p['ativo'] ? 'Sim' : 'Não',
                $p['destaque'] ? 'Sim' : 'Não',
                $p['created_at'] ?? '',
            ], ';');
        }
    } catch (Exception $e) {}

    fclose($output);
    exit;
}

/* =========================
   Processar formulário normal (SALVAR/EDITAR PRODUTO)
   ========================= */
$special_actions = ['importar_planilha', 'exportar', 'delete'];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !in_array($action, $special_actions)) {
    $acao = $_POST['acao'] ?? '';

    if ($acao === 'salvar') {
        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;

        $nome = trim($_POST['nome'] ?? '');

        if (empty($nome)) {
            set_flash('error', 'Nome do produto é obrigatório');
        } else {
            $dados = [
                'nome'               => $nome,
                'slug'               => slugify($nome),
                'descricao'          => trim($_POST['descricao'] ?? ''),
                'descricao_curta'    => trim($_POST['descricao_curta'] ?? ''),
                'sku'                => trim($_POST['sku'] ?? ''),
                'preco'              => (float)str_replace(',', '.', $_POST['preco'] ?? 0),
                'preco_promocional'  => !empty($_POST['preco_promocional']) ? (float)str_replace(',', '.', $_POST['preco_promocional']) : null,
                'custo'              => !empty($_POST['custo']) ? (float)str_replace(',', '.', $_POST['custo']) : null,
                'unidade'            => $_POST['unidade'] ?? 'un',
                'quantidade_estoque' => (int)($_POST['quantidade_estoque'] ?? 0),
                'estoque_minimo'     => (int)($_POST['estoque_minimo'] ?? 5),
                'destaque'           => isset($_POST['destaque']) ? 1 : 0,
                'ativo'              => isset($_POST['ativo']) ? 1 : 0,
                'categoria_id'       => (!empty($_POST['categoria_id']) && (int)$_POST['categoria_id'] > 0) ? (int)$_POST['categoria_id'] : null,
                'tags'               => trim($_POST['tags'] ?? ''),
            ];

            try {
                // Upload imagem principal
                if (!empty($_FILES['imagem']['name'])) {
                    $upload = handle_upload($_FILES['imagem'], 'produtos');
                    if ($upload) {
                        if ($id && !empty($_POST['imagem_atual'])) delete_upload($_POST['imagem_atual']);
                        $dados['imagem_principal'] = $upload;
                    }
                }

                // Processar imagens adicionais
                $imagens_adicionais = [];
                if (!empty($_FILES['imagens']['name'][0])) {
                    foreach ($_FILES['imagens']['tmp_name'] as $i => $tmp) {
                        if ($_FILES['imagens']['error'][$i] === UPLOAD_ERR_OK) {
                            $fake = ['name'=>$_FILES['imagens']['name'][$i],'tmp_name'=>$tmp,'error'=>0,'size'=>$_FILES['imagens']['size'][$i]??0,'type'=>$_FILES['imagens']['type'][$i]??''];
                            $up = handle_upload($fake, 'produtos');
                            if ($up) $imagens_adicionais[] = $up;
                        }
                    }
                }
                if (!empty($imagens_adicionais)) {
                    $dados['imagens'] = json_encode($imagens_adicionais);
                }

                if ($id) {
                    $dados['slug'] = unique_slug('produtos', $dados['slug'], $id);
                    $fields = []; $values = [];
                    foreach ($dados as $k => $v) { $fields[] = "{$k} = ?"; $values[] = $v; }
                    $values[] = $id;

                    $stmt_up = db()->prepare("UPDATE " . table('produtos') . " SET " . implode(', ', $fields) . " WHERE id = ?");
                    $stmt_up->execute($values);

                    // Imagens adicionais na tabela separada
                    foreach ($imagens_adicionais as $img) {
                        db()->prepare("INSERT INTO " . table('produto_imagens') . " (produto_id, imagem) VALUES (?, ?)")->execute([$id, $img]);
                    }

                    log_activity('update', 'produtos', "Produto #{$id} atualizado");
                    set_flash('success', 'Produto atualizado com sucesso!');
                } else {
                    $dados['slug'] = unique_slug('produtos', $dados['slug']);
                    $cols = implode(', ', array_keys($dados));
                    $ph = implode(', ', array_fill(0, count($dados), '?'));
                    db()->prepare("INSERT INTO " . table('produtos') . " ({$cols}) VALUES ({$ph})")->execute(array_values($dados));
                    $id = (int)db()->lastInsertId();

                    foreach ($imagens_adicionais as $img) {
                        db()->prepare("INSERT INTO " . table('produto_imagens') . " (produto_id, imagem) VALUES (?, ?)")->execute([$id, $img]);
                    }

                    log_activity('create', 'produtos', "Produto #{$id} criado");
                    set_flash('success', 'Produto criado com sucesso!');
                }
                header('Location: produtos.php'); exit;
            } catch (Exception $e) {
                set_flash('error', 'Erro: ' . $e->getMessage());
            }
        }
    }
}

// Deletar
if ($action === 'delete' && $id) {
    try {
        $prod = db()->prepare("SELECT imagem_principal, imagens FROM " . table('produtos') . " WHERE id = ?");
        $prod->execute([$id]); $p = $prod->fetch();
        if ($p) {
            if ($p['imagem_principal']) delete_upload($p['imagem_principal']);
            if ($p['imagens']) foreach (json_decode($p['imagens'], true) ?? [] as $img) delete_upload($img);
        }
        $imgs = db()->prepare("SELECT imagem FROM " . table('produto_imagens') . " WHERE produto_id = ?");
        $imgs->execute([$id]);
        foreach ($imgs->fetchAll() as $img) delete_upload($img['imagem']);

        db()->prepare("DELETE FROM " . table('produtos') . " WHERE id = ?")->execute([$id]);
        log_activity('delete', 'produtos', "Produto #{$id} excluído");
        set_flash('success', 'Produto excluído!');
    } catch (Exception $e) { set_flash('error', 'Erro: ' . $e->getMessage()); }
    header('Location: produtos.php'); exit;
}

// Buscar produto para editar
$produto = null;
$produto_imagens = [];
if (($action === 'edit' || $action === 'view') && $id) {
    $stmt = db()->prepare("SELECT * FROM " . table('produtos') . " WHERE id = ?");
    $stmt->execute([$id]); $produto = $stmt->fetch();

    $stmt2 = db()->prepare("SELECT * FROM " . table('produto_imagens') . " WHERE produto_id = ? ORDER BY id");
    $stmt2->execute([$id]); $produto_imagens = $stmt2->fetchAll();
}

// Listar categorias
$categorias = db()->query("SELECT id, nome FROM " . table('categorias') . " WHERE ativo = 1 ORDER BY nome")->fetchAll();

// Listagem
$busca = $_GET['busca'] ?? '';
$cat_filtro = isset($_GET['cat']) ? (int)$_GET['cat'] : 0;
$page = isset($_GET['page']) ? max(1,(int)$_GET['page']) : 1;

$where = ["1=1"]; $params = [];
if ($busca) { $where[] = "(p.nome LIKE ? OR p.sku LIKE ?)"; $like="%{$busca}%"; $params=[$like,$like]; }
if ($cat_filtro) { $where[] = "p.categoria_id = ?"; $params[] = $cat_filtro; }
$where_sql = implode(' AND ', $where);

$stmt_count = db()->prepare("SELECT COUNT(*) FROM " . table('produtos') . " p WHERE {$where_sql}");
$stmt_count->execute($params);
$total = (int)$stmt_count->fetchColumn();

$pagination = paginate($total, $page, ADMIN_ITEMS_PER_PAGE);
$offset = $pagination['offset'];

$stmt_list = db()->prepare("SELECT p.*, c.nome as categoria_nome FROM " . table('produtos') . " p LEFT JOIN " . table('categorias') . " c ON p.categoria_id = c.id WHERE {$where_sql} ORDER BY p.id DESC LIMIT {$offset}, " . ADMIN_ITEMS_PER_PAGE);
$stmt_list->execute($params);
$produtos = $stmt_list->fetchAll();

require_once __DIR__ . '/includes/header.php';

if ($action === 'edit' || $action === 'new'):
?>
<div class="page-header">
    <h1><i class="fas fa-<?php echo $action==='edit'?'edit':'plus'; ?>"></i> <?php echo $action==='edit'?'Editar Produto':'Novo Produto'; ?></h1>
    <a href="produtos.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Voltar</a>
</div>

<?php echo show_flash(); ?>

<form method="POST" enctype="multipart/form-data" action="produtos.php<?php echo $action==='edit' ? '?action=edit&id='.(int)$id : ''; ?>">
    <input type="hidden" name="acao" value="salvar">
    <?php if ($produto): ?>
        <input type="hidden" name="id" value="<?php echo (int)($produto['id'] ?? 0); ?>">
        <input type="hidden" name="imagem_atual" value="<?php echo sanitize($produto['imagem_principal']??''); ?>">
    <?php endif; ?>

    <div class="form-row" style="grid-template-columns:2fr 1fr;gap:20px;">
        <div>
            <div class="card">
                <div class="card-header"><h3><i class="fas fa-info-circle"></i> Informações Básicas</h3></div>
                <div class="card-body">
                    <div class="form-group">
                        <label>Nome do Produto *</label>
                        <input type="text" name="nome" value="<?php echo sanitize($produto['nome']??''); ?>" required>
                    </div>
                    <div class="form-row form-row-2">
                        <div class="form-group">
                            <label>SKU / Código</label>
                            <input type="text" name="sku" value="<?php echo sanitize($produto['sku']??''); ?>" placeholder="Ex: PROD-001">
                        </div>
                        <div class="form-group">
                            <label>Categoria</label>
                            <select name="categoria_id">
                                <option value="">— Sem categoria —</option>
                                <?php foreach ($categorias as $cat): ?>
                                <option value="<?php echo $cat['id']; ?>" <?php echo selected($produto['categoria_id']??'', $cat['id']); ?>><?php echo sanitize($cat['nome']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Descrição Curta</label>
                        <textarea name="descricao_curta" rows="2"><?php echo sanitize($produto['descricao_curta']??''); ?></textarea>
                    </div>
                    <div class="form-group">
                        <label>Descrição Completa</label>
                        <textarea name="descricao" rows="5"><?php echo sanitize($produto['descricao']??''); ?></textarea>
                    </div>
                    <div class="form-group">
                        <label>Tags (separadas por vírgula)</label>
                        <input type="text" name="tags" value="<?php echo sanitize($produto['tags']??''); ?>" placeholder="tag1, tag2, tag3">
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><h3><i class="fas fa-search"></i> SEO</h3></div>
                <div class="card-body">
                    <p style="font-size:0.875rem;color:var(--gray-500);display:flex;align-items:center;gap:8px;">
                        <i class="fas fa-info-circle" style="color:var(--primary);"></i>
                        O título SEO é gerado automaticamente pelo nome do produto. Para campos SEO avançados (meta descrição, keywords), adicione as colunas <code>seo_title</code> e <code>seo_description</code> na tabela <code>sc_produtos</code>.
                    </p>
                </div>
            </div>
        </div>

        <div>
            <div class="card">
                <div class="card-header"><h3><i class="fas fa-dollar-sign"></i> Preços</h3></div>
                <div class="card-body">
                    <div class="form-group">
                        <label>Preço de Venda (R$)</label>
                        <input type="text" name="preco" value="<?php echo number_format((float)($produto['preco']??0),2,',','.'); ?>" placeholder="0,00">
                    </div>
                    <div class="form-group">
                        <label>Preço Promocional (R$)</label>
                        <input type="text" name="preco_promocional" value="<?php echo (!empty($produto['preco_promocional']) && $produto['preco_promocional'] > 0) ? number_format((float)$produto['preco_promocional'],2,',','.') : ''; ?>" placeholder="Deixe em branco para não usar">
                    </div>
                    <div class="form-group">
                        <label>Custo (R$)</label>
                        <input type="text" name="custo" value="<?php echo (!empty($produto['custo']) && $produto['custo'] > 0) ? number_format((float)$produto['custo'],2,',','.') : ''; ?>" placeholder="Custo interno">
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><h3><i class="fas fa-warehouse"></i> Estoque</h3></div>
                <div class="card-body">
                    <div class="form-group">
                        <label>Quantidade em Estoque</label>
                        <input type="number" name="quantidade_estoque" value="<?php echo (int)($produto['quantidade_estoque']??0); ?>" min="0">
                    </div>
                    <div class="form-group">
                        <label>Estoque Mínimo</label>
                        <input type="number" name="estoque_minimo" value="<?php echo (int)($produto['estoque_minimo']??5); ?>" min="0">
                    </div>
                    <div class="form-group">
                        <label>Unidade</label>
                        <select name="unidade">
                            <?php foreach (['un'=>'Unidade','kg'=>'Kg','m'=>'Metro','m2'=>'m²','l'=>'Litro','cx'=>'Caixa','pc'=>'Peça','par'=>'Par'] as $v=>$l): ?>
                            <option value="<?php echo $v; ?>" <?php echo selected($produto['unidade']??'un',$v); ?>><?php echo $l; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><h3><i class="fas fa-toggle-on"></i> Configurações</h3></div>
                <div class="card-body">
                    <div class="form-check" style="margin-bottom:12px;">
                        <input type="checkbox" name="ativo" id="ativo" <?php echo checked($produto['ativo']??1); ?>>
                        <label for="ativo">Produto Ativo</label>
                    </div>
                    <div class="form-check">
                        <input type="checkbox" name="destaque" id="destaque" <?php echo checked($produto['destaque']??0); ?>>
                        <label for="destaque">Produto em Destaque</label>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><h3><i class="fas fa-image"></i> Imagem Principal</h3></div>
                <div class="card-body">
                    <?php if (!empty($produto['imagem_principal'])): ?>
                    <img src="<?php echo produto_imagem_url($produto['imagem_principal']); ?>" style="max-width:100%;border-radius:8px;margin-bottom:10px;">
                    <?php endif; ?>
                    <input type="file" name="imagem" accept="image/*">
                </div>
            </div>

            <div class="card">
                <div class="card-header"><h3><i class="fas fa-images"></i> Imagens Adicionais</h3></div>
                <div class="card-body">
                    <?php if (!empty($produto_imagens)): ?>
                    <div style="display:flex;flex-wrap:wrap;gap:8px;margin-bottom:10px;">
                        <?php foreach ($produto_imagens as $img): ?>
                        <img src="<?php echo produto_imagem_url($img['imagem']); ?>" style="width:60px;height:60px;object-fit:cover;border-radius:6px;">
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                    <input type="file" name="imagens[]" accept="image/*" multiple>
                    <small class="text-muted">Selecione múltiplas imagens</small>
                </div>
            </div>
        </div>
    </div>

    <div class="form-actions">
        <button type="submit" class="btn btn-primary btn-lg"><i class="fas fa-save"></i> Salvar Produto</button>
        <a href="produtos.php" class="btn btn-secondary btn-lg"><i class="fas fa-times"></i> Cancelar</a>
    </div>
</form>

<?php elseif ($action === 'list'): ?>

<div class="page-header">
    <h1><i class="fas fa-box-open"></i> Produtos</h1>
    <div style="display:flex;gap:8px;flex-wrap:wrap;">
        <button onclick="abrirModalImportar()" class="btn btn-success"><i class="fas fa-file-excel"></i> Importar Planilha</button>
        <a href="produtos.php?action=exportar" class="btn btn-outline"><i class="fas fa-file-export"></i> Exportar Planilha</a>
        <a href="produtos.php?action=new" class="btn btn-primary"><i class="fas fa-plus"></i> Novo Produto</a>
    </div>
</div>

<!-- Modal Importar Planilha -->
<div id="modalImportar" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.6);z-index:2000;align-items:center;justify-content:center;padding:20px;">
    <div style="background:#fff;border-radius:var(--radius);width:100%;max-width:520px;padding:28px;position:relative;box-shadow:var(--shadow-lg);">
        <button onclick="fecharModalImportar()" style="position:absolute;top:16px;right:16px;background:var(--gray-100);border:none;width:32px;height:32px;border-radius:50%;cursor:pointer;font-size:1.1rem;display:flex;align-items:center;justify-content:center;">&times;</button>

        <h3 style="font-size:1.125rem;font-weight:700;margin-bottom:6px;"><i class="fas fa-file-excel" style="color:var(--success);margin-right:8px;"></i>Importar Produtos por Planilha</h3>
        <p style="color:var(--gray-500);font-size:0.875rem;margin-bottom:20px;">Envie um arquivo CSV, XLS ou XLSX com os dados dos produtos. Use a coluna <strong>Imagem URL</strong> para importar imagens — elas serão baixadas automaticamente e convertidas para <strong>WebP</strong>.</p>

        <div style="margin-bottom:16px;">
            <a href="includes/produtos.csv" download class="btn btn-outline btn-sm" style="font-size:0.8125rem;">
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
            <p style="margin-top:12px;color:var(--gray-500);font-size:0.875rem;">Importando produtos, aguarde...</p>
        </div>

        <div id="importarResultado" style="display:none;margin-top:16px;"></div>

        <div style="display:flex;gap:10px;margin-top:20px;" id="botoesImportar">
            <button onclick="enviarPlanilha()" id="btnEnviar" class="btn btn-success" style="flex:1;" disabled>
                <i class="fas fa-upload"></i> Importar Agora
            </button>
            <button onclick="fecharModalImportar()" class="btn btn-secondary">Cancelar</button>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-body" style="padding-bottom:0;">
        <form method="GET" class="filters-bar">
            <div class="form-group">
                <input type="text" name="busca" value="<?php echo sanitize($busca); ?>" placeholder="Buscar produto...">
            </div>
            <div class="form-group">
                <select name="cat">
                    <option value="">Todas categorias</option>
                    <?php foreach ($categorias as $cat): ?>
                    <option value="<?php echo $cat['id']; ?>" <?php echo selected($cat_filtro,$cat['id']); ?>><?php echo sanitize($cat['nome']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Filtrar</button>
            <?php if ($busca || $cat_filtro): ?>
            <a href="produtos.php" class="btn btn-outline"><i class="fas fa-times"></i> Limpar</a>
            <?php endif; ?>
        </form>
    </div>
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Imagem</th><th>Nome</th><th>SKU</th><th>Categoria</th>
                    <th>Preço</th><th>Estoque</th><th>Status</th><th>Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($produtos)): ?>
                <tr><td colspan="8"><div class="empty-state-sm">Nenhum produto encontrado</div></td></tr>
                <?php else: ?>
                <?php foreach ($produtos as $p): ?>
                <tr style="<?php echo !$p['ativo']?'background:#fff5f5;':''; ?>">
                    <td>
                        <?php if ($p['imagem_principal']): ?>
                        <img src="<?php echo produto_imagem_url($p['imagem_principal']); ?>" style="width:48px;height:48px;object-fit:cover;border-radius:6px;">
                        <?php else: ?>
                        <div style="width:48px;height:48px;background:var(--gray-100);border-radius:6px;display:flex;align-items:center;justify-content:center;color:var(--gray-400);"><i class="fas fa-image"></i></div>
                        <?php endif; ?>
                    </td>
                    <td>
                        <strong><?php echo sanitize($p['nome']); ?></strong>
                        <?php if ($p['destaque']): ?><span class="badge" style="background:#fef3c7;color:#92400e;margin-left:6px;">Destaque</span><?php endif; ?>
                    </td>
                    <td><?php echo sanitize($p['sku']??'-'); ?></td>
                    <td><?php echo sanitize($p['categoria_nome']??'-'); ?></td>
                    <td>
                        <?php if ($p['preco_promocional'] && $p['preco_promocional'] > 0): ?>
                        <span style="text-decoration:line-through;color:var(--gray-400);font-size:0.8em;"><?php echo format_currency((float)$p['preco']); ?></span><br>
                        <strong style="color:var(--danger);"><?php echo format_currency((float)$p['preco_promocional']); ?></strong>
                        <?php else: ?>
                        <?php echo format_currency((float)$p['preco']); ?>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span style="color:<?php echo $p['quantidade_estoque'] <= $p['estoque_minimo']?'var(--danger)':'var(--success)'; ?>;font-weight:600;">
                            <?php echo $p['quantidade_estoque']; ?>
                        </span>
                        <small class="text-muted">/ mín <?php echo $p['estoque_minimo']; ?></small>
                    </td>
                    <td><span class="badge-status <?php echo $p['ativo']?'status-ativo':'status-inativo'; ?>"><?php echo $p['ativo']?'Ativo':'Inativo'; ?></span></td>
                    <td>
                        <a href="produtos.php?action=edit&id=<?php echo $p['id']; ?>" class="btn btn-sm btn-primary" title="Editar"><i class="fas fa-edit"></i></a>
                        <a href="produtos.php?action=delete&id=<?php echo $p['id']; ?>" class="btn btn-sm btn-danger" title="Excluir" onclick="return confirm('Excluir produto?')"><i class="fas fa-trash"></i></a>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <div class="card-body">
        <div style="display:flex;justify-content:space-between;align-items:center;">
            <small class="text-muted">Mostrando <?php echo $pagination['start']; ?>–<?php echo $pagination['end']; ?> de <?php echo $total; ?> produtos</small>
            <?php echo pagination_links($pagination, 'produtos.php', array_filter(['busca'=>$busca,'cat'=>$cat_filtro])); ?>
        </div>
    </div>
</div>

<script>
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

    fetch('produtos.php?action=importar_planilha', {
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

            if (d.importados > 0 || d.atualizados > 0) {
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
    if (e.key === 'Escape') fecharModalImportar();
});
</script>

<?php endif; ?>
<?php require_once __DIR__ . '/includes/footer.php'; ?>