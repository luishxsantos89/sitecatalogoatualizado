<?php
/**
 * SiteCatalogo2 - Página Principal (Catálogo Público)
 */

if (!file_exists(__DIR__ . '/config.php')) {
    die('Sistema não instalado. Execute o SQL de instalação primeiro.');
}

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/functions.php';

session_check();

// Dados do cliente logado
$admin_logado = isset($_SESSION['admin_id']) && $_SESSION['admin_id'] > 0;
$admin_nome   = $admin_logado ? ($_SESSION['admin_nome'] ?? '') : '';
$admin_email  = $admin_logado ? ($_SESSION['admin_email'] ?? '') : '';

$cliente_logado = isset($_SESSION['cliente_id']) && $_SESSION['cliente_id'] > 0;
$cliente_nome   = $cliente_logado ? ($_SESSION['cliente_nome'] ?? '') : '';
$cliente_email  = $cliente_logado ? ($_SESSION['cliente_email'] ?? '') : '';
$cliente_tel    = $cliente_logado ? ($_SESSION['cliente_telefone'] ?? '') : '';

// Configs do site
$site_name       = get_config('site_nome', SITE_NAME);
$site_description= get_config('site_descricao', SITE_DESCRIPTION);
$whatsapp        = get_config('whatsapp', WHATSAPP);
$mostrar_preco   = get_config('mostrar_preco', '1') === '1';
$site_email      = get_config('email_contato', '');
$telefone        = get_config('telefone', '');
$endereco        = get_config('endereco', '');
$horario         = get_config('horario_atendimento', 'Segunda a Sexta: 08h às 18h');
$facebook_url    = get_config('facebook_url', '');
$instagram_url   = get_config('instagram_url', '');
$linkedin_url    = get_config('linkedin_url', '');
$youtube_url     = get_config('youtube_url', '');
$tiktok_url      = get_config('tiktok_url', '');
$twitter_url     = get_config('twitter_url', '');
$telegram_url    = get_config('telegram_url', '');
$pinterest_url   = get_config('pinterest_url', '');
$kwai_url        = get_config('kwai_url', '');
$threads_url     = get_config('threads_url', '');
$logo_cliente    = get_config('logo_cliente', '');
$navbar_tipo     = get_config('navbar_tipo', 'imagem_texto');
$cor_primaria    = get_config('cor_primaria', '#3b82f6');
$categoria_layout= get_config('categoria_layout', 'sidebar');
$toast_position  = get_config('toast_position', 'top-right');
$produtos_navegacao = get_config('produtos_navegacao', 'paginacao');
$produto_visualizacao = get_config('produto_visualizacao', 'modal'); // 'modal' (catálogo simples) ou 'pagina_individual' (estilo WooCommerce)

// Scripts e CSS personalizados (vindos da página SEO)
$custom_head_scripts = get_config('custom_head_scripts', '');
$custom_body_scripts = get_config('custom_body_scripts', '');
$custom_css          = get_config('custom_css', '');

// Leitura direta do banco para garantir que as configs extras são carregadas corretamente
try {
    $stmt_cfg = db()->prepare("SELECT chave, valor FROM " . table('configuracoes') . " WHERE chave IN ('toast_position','produtos_navegacao','produto_visualizacao','custom_head_scripts','custom_body_scripts','custom_css')");
    $stmt_cfg->execute();
    foreach ($stmt_cfg->fetchAll() as $cfg_row) {
        if ($cfg_row['chave'] === 'toast_position' && !empty($cfg_row['valor']))
            $toast_position = $cfg_row['valor'];
        if ($cfg_row['chave'] === 'produtos_navegacao' && !empty($cfg_row['valor']))
            $produtos_navegacao = $cfg_row['valor'];
        if ($cfg_row['chave'] === 'produto_visualizacao' && !empty($cfg_row['valor']))
            $produto_visualizacao = $cfg_row['valor'];
        if ($cfg_row['chave'] === 'custom_head_scripts')
            $custom_head_scripts = (string)$cfg_row['valor'];
        if ($cfg_row['chave'] === 'custom_body_scripts')
            $custom_body_scripts = (string)$cfg_row['valor'];
        if ($cfg_row['chave'] === 'custom_css')
            $custom_css = (string)$cfg_row['valor'];
    }
} catch (Exception $e) {}

// Parâmetros de busca
$busca       = isset($_GET['busca']) ? trim($_GET['busca']) : '';
$categoria_id= isset($_GET['categoria']) ? (int)$_GET['categoria'] : 0;
$page        = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$per_page    = ITEMS_PER_PAGE;

// Aumentar quantidade no carrinho
if (isset($_GET['qty_cart']) && isset($_GET['add_cart'])) {
    $pid = (int)$_GET['add_cart'];
    if (isset($_SESSION['carrinho'][$pid])) {
        $_SESSION['carrinho'][$pid]['qtd']++;
    }
    $ref = $_SERVER['HTTP_REFERER'] ?? '/';
    header('Location: ' . $ref); exit;
}

// Diminuir quantidade no carrinho
if (isset($_GET['qty_minus']) && isset($_GET['pid'])) {
    $pid = (int)$_GET['pid'];
    if (isset($_SESSION['carrinho'][$pid])) {
        $_SESSION['carrinho'][$pid]['qtd']--;
        if ($_SESSION['carrinho'][$pid]['qtd'] <= 0) {
            unset($_SESSION['carrinho'][$pid]);
        }
    }
    $ref = $_SERVER['HTTP_REFERER'] ?? '/';
    header('Location: ' . $ref); exit;
}

// Carrinho de orçamento (sessão)
if (isset($_GET['add_cart']) && !isset($_GET['qty_cart'])) {
    $pid = (int)$_GET['add_cart'];
    if (!isset($_SESSION['carrinho'])) $_SESSION['carrinho'] = [];
    if (!isset($_SESSION['carrinho'][$pid])) {
        $sp = db()->prepare("SELECT id, nome, preco, preco_promocional, unidade, imagem_principal FROM " . table('produtos') . " WHERE id = ? AND ativo = 1");
        $sp->execute([$pid]); $prod = $sp->fetch();
        if ($prod) {
            $_SESSION['carrinho'][$pid] = array_merge($prod, ['qtd' => 1]);
        }
    } else {
        $_SESSION['carrinho'][$pid]['qtd']++;
    }
    $ref = $_SERVER['HTTP_REFERER'] ?? '/';
    header('Location: ' . $ref); exit;
}

if (isset($_GET['remove_cart'])) {
    $pid = (int)$_GET['remove_cart'];
    unset($_SESSION['carrinho'][$pid]);
    $ref = $_SERVER['HTTP_REFERER'] ?? '/';
    header('Location: ' . $ref); exit;
}

if (isset($_GET['clear_cart'])) {
    unset($_SESSION['carrinho']);
    header('Location: /'); exit;
}

// AJAX - Manipular carrinho sem refresh (add, qty+, qty-, remover, limpar)
if (isset($_POST['action']) && $_POST['action'] === 'ajax_cart') {
    header('Content-Type: application/json');

    $op  = $_POST['op'] ?? '';
    $pid = isset($_POST['pid']) ? (int)$_POST['pid'] : 0;

    if (!isset($_SESSION['carrinho'])) $_SESSION['carrinho'] = [];

    switch ($op) {
        case 'add':
            if (!isset($_SESSION['carrinho'][$pid])) {
                $sp = db()->prepare("SELECT id, nome, preco, preco_promocional, unidade, imagem_principal FROM " . table('produtos') . " WHERE id = ? AND ativo = 1");
                $sp->execute([$pid]); $prod = $sp->fetch();
                if ($prod) {
                    $_SESSION['carrinho'][$pid] = array_merge($prod, ['qtd' => 1]);
                }
            } else {
                $_SESSION['carrinho'][$pid]['qtd']++;
            }
            break;

        case 'qty_plus':
            if (isset($_SESSION['carrinho'][$pid])) {
                $_SESSION['carrinho'][$pid]['qtd']++;
            }
            break;

        case 'qty_minus':
            if (isset($_SESSION['carrinho'][$pid])) {
                $_SESSION['carrinho'][$pid]['qtd']--;
                if ($_SESSION['carrinho'][$pid]['qtd'] <= 0) {
                    unset($_SESSION['carrinho'][$pid]);
                }
            }
            break;

        case 'remove':
            unset($_SESSION['carrinho'][$pid]);
            break;

        case 'clear':
            $_SESSION['carrinho'] = [];
            break;
    }

    $carrinho_atual = $_SESSION['carrinho'] ?? [];
    $total_atual = array_sum(array_map(fn($i) => ((float)($i['preco_promocional'] > 0 ? $i['preco_promocional'] : $i['preco'])) * (int)$i['qtd'], $carrinho_atual));

    $mostrar_preco_ajax = $mostrar_preco;
    $cor_primaria_ajax  = $cor_primaria;

    ob_start();
    ?>
    <?php if (empty($carrinho_atual)): ?>
    <div style="text-align:center;padding:60px 20px;color:var(--gray-400);">
        <i class="fas fa-shopping-cart" style="font-size:3rem;margin-bottom:16px;display:block;"></i>
        <p>Nenhum produto no orçamento</p>
    </div>
    <?php else: ?>
    <div style="flex:1;overflow-y:auto;padding:16px;" id="listaCarrinho">
        <?php foreach ($carrinho_atual as $pid_item => $item):
            $preco_item = (float)($item['preco_promocional'] > 0 ? $item['preco_promocional'] : $item['preco']);
        ?>
        <div style="display:flex;gap:12px;padding:12px 0;border-bottom:1px solid var(--gray-100);" data-pid="<?php echo $pid_item; ?>">
            <?php if ($item['imagem_principal']): ?>
            <img src="<?php echo uploads_url($item['imagem_principal']); ?>" style="width:56px;height:56px;object-fit:cover;border-radius:8px;flex-shrink:0;">
            <?php endif; ?>
            <div style="flex:1;min-width:0;">
                <strong style="font-size:0.875rem;display:block;margin-bottom:4px;"><?php echo sanitize($item['nome']); ?></strong>
                <?php if ($mostrar_preco_ajax): ?>
                <p style="font-size:0.8rem;color:var(--gray-500);margin-bottom:6px;"><?php echo format_currency($preco_item); ?> / un</p>
                <?php endif; ?>
                <div class="qty-ctrl">
                    <a href="#" class="qty-btn" title="Diminuir" onclick="event.preventDefault();ajaxCart('qty_minus',<?php echo $pid_item; ?>)">
                        <i class="fas fa-minus" style="font-size:0.7rem;"></i>
                    </a>
                    <span style="min-width:28px;text-align:center;font-weight:700;font-size:0.9rem;"><?php echo $item['qtd']; ?></span>
                    <a href="#" class="qty-btn" title="Aumentar" style="background:<?php echo $cor_primaria_ajax; ?>22;border-color:<?php echo $cor_primaria_ajax; ?>44;color:<?php echo $cor_primaria_ajax; ?>;" onclick="event.preventDefault();ajaxCart('qty_plus',<?php echo $pid_item; ?>)">
                        <i class="fas fa-plus" style="font-size:0.7rem;"></i>
                    </a>
                    <?php if ($mostrar_preco_ajax): ?>
                    <span style="margin-left:8px;font-weight:700;color:<?php echo $cor_primaria_ajax; ?>;font-size:0.875rem;"><?php echo format_currency($preco_item * $item['qtd']); ?></span>
                    <?php endif; ?>
                </div>
            </div>
            <a href="#" style="color:var(--gray-400);font-size:0.875rem;align-self:flex-start;padding:2px;" title="Remover" onclick="event.preventDefault();ajaxCart('remove',<?php echo $pid_item; ?>)"><i class="fas fa-times"></i></a>
        </div>
        <?php endforeach; ?>
    </div>

    <div style="padding:16px;border-top:1px solid var(--gray-200);">
        <?php if ($mostrar_preco_ajax): ?>
        <div style="display:flex;justify-content:space-between;font-weight:700;font-size:1.1rem;margin-bottom:16px;">
            <span>Total:</span>
            <span style="color:<?php echo $cor_primaria_ajax; ?>"><?php echo format_currency($total_atual); ?></span>
        </div>
        <?php endif; ?>
        <button onclick="abrirModalOrcamento()"
           style="display:flex;align-items:center;justify-content:center;gap:10px;background:<?php echo $cor_primaria_ajax; ?>;color:#fff;padding:14px;border-radius:10px;font-weight:700;margin-bottom:8px;font-size:0.9375rem;width:100%;border:none;cursor:pointer;">
            <i class="fas fa-paper-plane" style="font-size:1.25rem;"></i> Enviar Orçamento
        </button>
        <a href="#" onclick="event.preventDefault();ajaxCart('clear',0)" style="display:block;text-align:center;color:var(--gray-400);font-size:0.8rem;margin-top:8px;">Limpar orçamento</a>
    </div>
    <?php endif; ?>
    <?php
    $painel_html = ob_get_clean();

    echo json_encode([
        'ok'          => true,
        'count'       => count($carrinho_atual),
        'total'       => $total_atual,
        'total_fmt'   => format_currency($total_atual),
        'painel_html' => $painel_html,
    ]);
    exit;
}

// AJAX - Salvar orçamento do site
if (isset($_POST['action']) && $_POST['action'] === 'salvar_orcamento') {
    header('Content-Type: application/json');
    $carrinho_atual = $_SESSION['carrinho'] ?? [];
    if (empty($carrinho_atual)) {
        echo json_encode(['ok' => false, 'msg' => 'Carrinho vazio']);
        exit;
    }
    try {
        $codigo = 'ORC-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -5));
        $valor_produtos = 0;
        foreach ($carrinho_atual as $pid => $item) {
            $p = $item['preco_promocional'] > 0 ? $item['preco_promocional'] : $item['preco'];
            $valor_produtos += (float)$p * (int)$item['qtd'];
        }
        $valor_total = $valor_produtos;

        $cliente_id = null;
        $cli_nome  = trim($_POST['cliente_nome'] ?? '');
        $cli_tel   = trim($_POST['cliente_tel'] ?? '');

        if (!empty($cli_tel)) {
            $tel = preg_replace('/\D/', '', $cli_tel);
            $stmt_cli = db()->prepare("SELECT id FROM " . table('clientes') . " WHERE celular = ? LIMIT 1");
            $stmt_cli->execute([$tel]);
            $found = $stmt_cli->fetchColumn();
            if ($found) $cliente_id = $found;
        }

        $whatsapp_empresa = get_config('whatsapp', '');
        $msg_conf = get_config('orcamento_whatsapp_msg', WHATSAPP_DEFAULT_MSG);

        db()->prepare("INSERT INTO " . table('orcamentos') . " (codigo,cliente_id,cliente_nome,cliente_telefone,tipo_contato,status,valor_produtos,valor_servicos,desconto,valor_total,usuario_id) VALUES (?,?,?,?,?,?,?,?,?,?,NULL)")
            ->execute([$codigo,$cliente_id,$cli_nome ?: 'Cliente do Site',$cli_tel,'whatsapp','novo',$valor_produtos,0,0,$valor_total]);

        $orc_id = (int)db()->lastInsertId();

        foreach ($carrinho_atual as $pid => $item) {
            $preco = (float)($item['preco_promocional'] > 0 ? $item['preco_promocional'] : $item['preco']);
            $qtd = (int)$item['qtd'];
            db()->prepare("INSERT INTO " . table('orcamento_itens') . " (orcamento_id,produto_id,produto_nome,quantidade,unidade,preco_unitario,subtotal) VALUES (?,?,?,?,?,?,?)")
                ->execute([$orc_id,$pid,$item['nome'],$qtd,$item['unidade']??'un',$preco,$preco*$qtd]);
        }

        $msg = $msg_conf . "\n\n";
        foreach ($carrinho_atual as $pid => $item) {
            $p = $item['preco_promocional'] > 0 ? $item['preco_promocional'] : $item['preco'];
            $msg .= "• {$item['nome']} (Qtd: {$item['qtd']})" . ($mostrar_preco ? " - " . format_currency((float)$p) : '') . "\n";
        }
        if ($mostrar_preco) $msg .= "\nTotal: " . format_currency($valor_total);
        $msg .= "\n\nCódigo: {$codigo}";

        $wl = $whatsapp_empresa ? whatsapp_link($whatsapp_empresa, $msg) : '';

        echo json_encode(['ok' => true, 'codigo' => $codigo, 'whatsapp_link' => $wl, 'whatsapp_tel' => $whatsapp_empresa]);
    } catch (Exception $e) {
        echo json_encode(['ok' => false, 'msg' => $e->getMessage()]);
    }
    exit;
}

// AJAX - Produtos para scroll infinito
if (isset($_GET['ajax_produtos'])) {
    header('Content-Type: application/json');
    $busca_aj    = isset($_GET['busca']) ? trim($_GET['busca']) : '';
    $cat_aj      = isset($_GET['categoria']) ? (int)$_GET['categoria'] : 0;
    $page_aj     = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
    $per_page_aj = ITEMS_PER_PAGE;

    $where_aj = ["p.ativo = 1"]; $params_aj = [];
    if ($busca_aj) { $where_aj[] = "(p.nome LIKE ? OR p.descricao_curta LIKE ? OR p.sku LIKE ? OR p.tags LIKE ?)"; $like="%{$busca_aj}%"; $params_aj=[$like,$like,$like,$like]; }
    if ($cat_aj)   { $where_aj[] = "p.categoria_id = ?"; $params_aj[] = $cat_aj; }
    $where_sql_aj = implode(' AND ', $where_aj);

    try {
        $cnt = (int)db()->prepare("SELECT COUNT(*) FROM " . table('produtos') . " p WHERE {$where_sql_aj}")->execute($params_aj) ? db()->prepare("SELECT COUNT(*) FROM " . table('produtos') . " p WHERE {$where_sql_aj}") : null;
        $cnt_stmt = db()->prepare("SELECT COUNT(*) FROM " . table('produtos') . " p WHERE {$where_sql_aj}");
        $cnt_stmt->execute($params_aj);
        $total_aj = (int)$cnt_stmt->fetchColumn();
        $pag_aj   = paginate($total_aj, $page_aj, $per_page_aj);
        $off_aj   = $pag_aj['offset'];

        $stmt_aj = db()->prepare("SELECT p.*, c.nome as categoria_nome FROM " . table('produtos') . " p LEFT JOIN " . table('categorias') . " c ON p.categoria_id = c.id WHERE {$where_sql_aj} ORDER BY p.destaque DESC, p.created_at DESC LIMIT {$off_aj}, {$per_page_aj}");
        $stmt_aj->execute($params_aj);
        $prods_aj = $stmt_aj->fetchAll();

        $cor = get_config('cor_primaria', '#3b82f6');
        $show_preco = get_config('mostrar_preco', '1') === '1';
        $items = [];
        foreach ($prods_aj as $p) {
            $preco_f = $p['preco_promocional'] && $p['preco_promocional'] > 0 ? (float)$p['preco_promocional'] : (float)$p['preco'];
            $tem_p   = $p['preco_promocional'] && $p['preco_promocional'] > 0;
            $img_url = $p['imagem_principal'] ? uploads_url($p['imagem_principal']) : '';
            $items[] = [
                'id'            => $p['id'],
                'nome'          => sanitize($p['nome']),
                'imagem'        => $img_url,
                'preco'         => format_currency((float)$p['preco']),
                'preco_final'   => format_currency($preco_f),
                'tem_promo'     => $tem_p,
                'show_preco'    => $show_preco && $p['preco'] > 0,
            ];
        }
        echo json_encode([
            'ok'          => true,
            'items'       => $items,
            'total_pages' => $pag_aj['total_pages'],
            'page'        => $page_aj,
            'cor'         => $cor,
        ]);
    } catch (Exception $e) {
        echo json_encode(['ok' => false, 'msg' => $e->getMessage()]);
    }
    exit;
}

$carrinho = $_SESSION['carrinho'] ?? [];
$total_carrinho = array_sum(array_map(fn($i) => ((float)($i['preco_promocional'] > 0 ? $i['preco_promocional'] : $i['preco'])) * (int)$i['qtd'], $carrinho));

// Dados
// Helper: retorna banners ativos de uma posição, respeitando prazo
function get_banners_ativos(string $posicao, int $limit = 10): array {
    try {
        $stmt = db()->prepare("
            SELECT * FROM " . table('banners') . "
            WHERE ativo = 1
              AND posicao = ?
              AND (
                  prazo_fixo = 1
                  OR (
                      (data_inicio IS NULL OR data_inicio <= CURDATE())
                      AND (data_fim   IS NULL OR data_fim   >= CURDATE())
                  )
              )
            ORDER BY ordem ASC
            LIMIT {$limit}
        ");
        $stmt->execute([$posicao]);
        return $stmt->fetchAll();
    } catch (Exception $e) {
        return [];
    }
}

try {
    $categorias        = db()->query("SELECT * FROM " . table('categorias') . " WHERE ativo = 1 ORDER BY ordem, nome")->fetchAll();
    $banners_slide     = get_banners_ativos('slide', 8);
    $banners_categoria = get_banners_ativos('categoria', 5);
    $banners_popup     = get_banners_ativos('popup', 3);
    // Compatibilidade: mantém $banners apontando para slide
    $banners = $banners_slide;
} catch (Exception $e) { $categorias = []; $banners = []; $banners_slide = []; $banners_categoria = []; $banners_popup = []; }

// Produtos
$where = ["p.ativo = 1"]; $params = [];
if ($busca) { $where[] = "(p.nome LIKE ? OR p.descricao_curta LIKE ? OR p.sku LIKE ? OR p.tags LIKE ?)"; $like="%{$busca}%"; $params=[$like,$like,$like,$like]; }
if ($categoria_id) { $where[] = "p.categoria_id = ?"; $params[] = $categoria_id; }
$where_sql = implode(' AND ', $where);

try {
    $stmt_c = db()->prepare("SELECT COUNT(*) FROM " . table('produtos') . " p WHERE {$where_sql}"); $stmt_c->execute($params); $total = (int)$stmt_c->fetchColumn();
} catch (Exception $e) { $total = 0; }

$pagination = paginate($total, $page, $per_page);
$offset = $pagination['offset'];

try {
    $stmt_p = db()->prepare("SELECT p.*, c.nome as categoria_nome, c.slug as categoria_slug FROM " . table('produtos') . " p LEFT JOIN " . table('categorias') . " c ON p.categoria_id = c.id WHERE {$where_sql} ORDER BY p.destaque DESC, p.created_at DESC LIMIT {$offset}, {$per_page}");
    $stmt_p->execute($params); $produtos = $stmt_p->fetchAll();
} catch (Exception $e) { $produtos = []; }

// Produto em modal
$produto_modal = null; $produto_imagens_modal = [];
if (isset($_GET['produto_id'])) {
    $pid = (int)$_GET['produto_id'];
    $sp = db()->prepare("SELECT p.*, c.nome as categoria_nome FROM " . table('produtos') . " p LEFT JOIN " . table('categorias') . " c ON p.categoria_id = c.id WHERE p.id=? AND p.ativo=1");
    $sp->execute([$pid]); $produto_modal = $sp->fetch();
    if ($produto_modal) {
        // Incremento de visualizações é OPCIONAL — só executa se a coluna existir no banco.
        // Evita Fatal Error em instalações que ainda não rodaram o ALTER TABLE.
        try {
            db()->prepare("UPDATE " . table('produtos') . " SET visualizacoes = visualizacoes + 1 WHERE id = ?")->execute([$pid]);
        } catch (Exception $e) { /* coluna 'visualizacoes' não existe — ignora silenciosamente */ }
        $si = db()->prepare("SELECT * FROM " . table('produto_imagens') . " WHERE produto_id = ? ORDER BY id"); $si->execute([$pid]); $produto_imagens_modal = $si->fetchAll();
    }
}

// Título da página
$titulo_pagina = 'Todos os Produtos';
if ($busca) $titulo_pagina = "Resultados para \"{$busca}\"";
elseif ($categoria_id) {
    $cat_atual = array_values(array_filter($categorias, fn($c) => $c['id'] == $categoria_id));
    if ($cat_atual) $titulo_pagina = $cat_atual[0]['nome'];
}

// Posição do toast para CSS
$toast_positions = [
    'top-left'     => 'top:20px;left:20px;',
    'top-center'   => 'top:20px;left:50%;transform:translateX(-50%);',
    'top-right'    => 'top:20px;right:20px;',
    'bottom-left'  => 'bottom:20px;left:20px;',
    'bottom-center'=> 'bottom:20px;left:50%;transform:translateX(-50%);',
    'bottom-right' => 'bottom:20px;right:20px;',
];
$toast_css = $toast_positions[$toast_position] ?? $toast_positions['top-right'];

// Parse características do produto modal
$caracteristicas_modal = [];
if ($produto_modal && !empty($produto_modal['caracteristicas'])) {
    $decoded = json_decode($produto_modal['caracteristicas'], true);
    if (is_array($decoded)) {
        $caracteristicas_modal = $decoded;
    } else {
        $caracteristicas_modal = array_filter(array_map('trim', explode(',', $produto_modal['caracteristicas'])));
    }
}

// ===== CARROSSEL: produtos da mesma categoria (até 12) =====
$categoria_carrossel = [];
if ($produto_modal && !empty($produto_modal['categoria_id'])) {
    $cid = (int)$produto_modal['categoria_id'];
    if ($cid > 0) {
        try {
            $stmt_car = db()->prepare(
                "SELECT id, nome, imagem_principal, preco, preco_promocional 
                 FROM " . table('produtos') . " 
                 WHERE ativo = 1 AND categoria_id = ? AND id != ? 
                 ORDER BY destaque DESC, created_at DESC 
                 LIMIT 12"
            );
            $stmt_car->execute([$cid, (int)$produto_modal['id']]);
            $categoria_carrossel = $stmt_car->fetchAll();
        } catch (Exception $e) {
            $categoria_carrossel = [];
        }
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo sanitize($site_name); ?> - <?php echo sanitize($site_description); ?></title>
    <meta name="description" content="<?php echo sanitize($site_description); ?>">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        :root { --primary: <?php echo $cor_primaria; ?>; --primary-dark: <?php echo $cor_primaria; ?>dd; }
        /* Carrinho Sidebar animado */
        #painelCarrinho { transition: transform 0.3s ease; }
        /* Categoria inline */
        .cat-inline-bar { background:#fff; border-bottom:1px solid var(--gray-200); overflow-x:auto; position:sticky; top:64px; z-index:50; }
        .cat-inline-bar .inner { display:flex; gap:6px; padding:10px 0; white-space:nowrap; }
        /* Categoria sidebar sticky */
        .cat-sidebar { width: 220px; flex-shrink: 0; position: sticky; top: 80px; align-self: flex-start; max-height: calc(100vh - 100px); overflow-y: auto; }
        .cat-sidebar a { display:flex; align-items:center; gap:10px; padding:10px 14px; border-radius:8px; font-size:0.875rem; font-weight:500; text-decoration:none; transition:all 0.15s; }
        .cat-sidebar a:hover { background: var(--gray-100); }
        /* Badge carrinho */
        .badge-carrinho { position:absolute;top:-6px;right:-6px;background:var(--primary);color:#fff;font-size:0.65rem;font-weight:700;width:18px;height:18px;border-radius:50%;display:flex;align-items:center;justify-content:center; }
        /* Qty controls */
        .qty-ctrl { display:flex;align-items:center;gap:4px; }
        .qty-btn { background:var(--gray-100);border:1px solid var(--gray-200);width:24px;height:24px;border-radius:6px;cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:0.875rem;transition:all 0.15s; }
        .qty-btn:hover { background:var(--primary);color:#fff;border-color:var(--primary); }
        /* Modal overlay animado */
        #overlayCarrinho { backdrop-filter: blur(2px); }

        /* ===== TOAST - POSIÇÃO CONFIGURÁVEL ===== */
        .toast-container {
            position: fixed;
            z-index: 9999;
            <?php echo $toast_css; ?>
            display: flex;
            flex-direction: column;
            gap: 10px;
            pointer-events: none;
            max-width: 400px;
            width: calc(100vw - 40px);
        }
        .toast-item {
            pointer-events: auto;
            background: #ffffff;
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.15), 0 2px 8px rgba(0,0,0,0.08);
            padding: 14px 18px;
            min-width: 280px;
            max-width: 400px;
            display: flex;
            align-items: center;
            gap: 12px;
            border-left: 4px solid var(--primary);
            position: relative;
            overflow: hidden;
            opacity: 0;
            transform: <?php echo (strpos($toast_position,'right')!==false) ? 'translateX(120%)' : (strpos($toast_position,'left')!==false ? 'translateX(-120%)' : 'translateY(-40px)'); ?>;
            transition: opacity 0.35s ease, transform 0.35s cubic-bezier(0.175,0.885,0.32,1.275);
        }
        .toast-item.show {
            opacity: 1;
            transform: translateX(0) translateY(0);
        }
        .toast-item::before {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            height: 3px;
            background: var(--primary);
            width: 100%;
            animation: toastProgress 3s linear forwards;
        }
        .toast-item.success { border-left-color: #22c55e; }
        .toast-item.success::before { background: #22c55e; }
        .toast-item.error { border-left-color: #ef4444; }
        .toast-item.error::before { background: #ef4444; }
        .toast-item.info { border-left-color: #3b82f6; }
        .toast-item.info::before { background: #3b82f6; }
        .toast-icon {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            font-size: 1rem;
        }
        .toast-item.success .toast-icon { background: #dcfce7; color: #16a34a; }
        .toast-item.error .toast-icon { background: #fee2e2; color: #dc2626; }
        .toast-item.info .toast-icon { background: #dbeafe; color: #2563eb; }
        .toast-content { flex: 1; }
        .toast-title { font-weight: 700; font-size: 0.9rem; color: #1f2937; margin-bottom: 2px; }
        .toast-message { font-size: 0.8rem; color: #6b7280; }
        .toast-close {
            background: none;
            border: none;
            color: #9ca3af;
            cursor: pointer;
            font-size: 1rem;
            padding: 4px;
            border-radius: 6px;
            transition: all 0.15s;
            flex-shrink: 0;
        }
        .toast-close:hover { background: #f3f4f6; color: #374151; }
        @keyframes toastProgress {
            from { width: 100%; }
            to { width: 0%; }
        }

        /* ===== MODAL PRODUTO MODERNO ===== */
        .modal-produto-overlay {
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.75);
            backdrop-filter: blur(4px);
            z-index: 2000;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            animation: modalFadeIn 0.3s ease;
        }
        @keyframes modalFadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        .modal-produto-card {
            background: #fff;
            border-radius: 20px;
            width: 70vw;
            height: 70vh;
            max-width: 1200px;
            max-height: 70vh;
            overflow-y: auto;
            position: relative;
            animation: modalSlideUp 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            box-shadow: 0 25px 80px rgba(0,0,0,0.3);
            display: flex;
            flex-direction: column;
        }
        @keyframes modalSlideUp {
            from { opacity: 0; transform: translateY(40px) scale(0.96); }
            to { opacity: 1; transform: translateY(0) scale(1); }
        }
        .modal-produto-close {
            position: sticky;
            top: 10px;
            right: 16px;
            z-index: 10;
            float: right;
            margin: 10px 10px 0 0;
            background: rgba(255,255,255,0.95);
            border: 1px solid #e5e7eb;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            cursor: pointer;
            font-size: 1.2rem;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        .modal-produto-close:hover { background: #f3f4f6; transform: scale(1.1); }
        .modal-produto-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0;
            flex: 1;
            min-height: 0;
        }
        @media (max-width: 768px) {
            .modal-produto-overlay { padding: 0; align-items: flex-end; }
            .modal-produto-grid { grid-template-columns: 1fr; }
            .modal-produto-card {
                width: 100vw;
                height: 92vh;
                max-height: 92vh;
                border-radius: 20px 20px 0 0;
                overflow-y: auto;
            }
            .modal-produto-galeria {
                position: relative !important;
                height: auto !important;
                padding: 20px 20px 0 !important;
            }
            .modal-produto-img-principal {
                max-height: 260px !important;
            }
            .modal-produto-close {
                position: absolute;
                top: 12px;
                right: 12px;
                margin: 0;
                float: none;
            }
            .modal-produto-info { padding: 20px !important; }
            .modal-produto-titulo { font-size: 1.25rem !important; }
        }
        .modal-produto-galeria {
            background: #f9fafb;
            padding: 32px;
            display: flex;
            flex-direction: column;
            gap: 20px;
            position: sticky;
            top: 0;
            align-self: start;
            height: 100%;
            overflow-y: auto;
        }
        .modal-produto-img-principal {
            width: 100%;
            max-height: calc(70vh - 180px);
            object-fit: contain;
            border-radius: 16px;
            background: #fff;
            box-shadow: 0 4px 20px rgba(0,0,0,0.06);
            padding: 20px;
            flex-shrink: 0;
        }
        .modal-produto-thumbs {
            display: flex;
            gap: 10px;
            overflow-x: auto;
            padding-bottom: 4px;
        }
        .modal-produto-thumb {
            width: 72px;
            height: 72px;
            object-fit: cover;
            border-radius: 10px;
            cursor: pointer;
            border: 2px solid transparent;
            transition: all 0.2s;
            background: #fff;
            padding: 4px;
            flex-shrink: 0;
        }
        .modal-produto-thumb:hover, .modal-produto-thumb.active {
            border-color: var(--primary);
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        .modal-produto-info {
            padding: 40px;
            display: flex;
            flex-direction: column;
            gap: 20px;
            height: 100%;
            overflow-y: auto;
        }
        .modal-produto-categoria {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: var(--primary);
            background: var(--primary)10;
            padding: 6px 14px;
            border-radius: 20px;
            width: fit-content;
        }
        .modal-produto-titulo {
            font-size: 1.75rem;
            font-weight: 800;
            color: #111827;
            line-height: 1.2;
        }
        .modal-produto-sku {
            font-size: 0.8rem;
            color: #9ca3af;
            font-weight: 500;
        }
        .modal-produto-preco-area {
            display: flex;
            align-items: baseline;
            gap: 12px;
            flex-wrap: wrap;
        }
        .modal-produto-preco {
            font-size: 2.25rem;
            font-weight: 800;
            color: var(--primary);
        }
        .modal-produto-preco-promo {
            font-size: 1.25rem;
            text-decoration: line-through;
            color: #9ca3af;
            font-weight: 500;
        }
        .modal-produto-preco-unidade {
            font-size: 0.875rem;
            color: #6b7280;
        }
        .modal-produto-descricao-curta {
            font-size: 0.9375rem;
            color: #4b5563;
            line-height: 1.7;
        }
        .modal-produto-btns {
            display: flex;
            flex-direction: column;
            gap: 12px;
            margin-top: 8px;
        }
        .modal-produto-btn-primary {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            background: var(--primary);
            color: #fff;
            padding: 16px 24px;
            border-radius: 12px;
            font-weight: 700;
            font-size: 1rem;
            text-decoration: none;
            border: none;
            cursor: pointer;
            transition: all 0.2s;
            box-shadow: 0 4px 16px var(--primary)40;
        }
        .modal-produto-btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 24px var(--primary)60;
        }
        .modal-produto-btn-whatsapp {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            background: #25d366;
            color: #fff;
            padding: 14px 24px;
            border-radius: 12px;
            font-weight: 600;
            font-size: 0.9375rem;
            text-decoration: none;
            transition: all 0.2s;
            box-shadow: 0 4px 16px rgba(37,211,102,0.3);
        }
        .modal-produto-btn-whatsapp:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 24px rgba(37,211,102,0.4);
        }
        .modal-produto-section {
            border-top: 1px solid #f3f4f6;
            padding-top: 24px;
        }
        .modal-produto-section-title {
            font-size: 1.1rem;
            font-weight: 700;
            color: #111827;
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .modal-produto-caracteristicas {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        .modal-produto-caracteristica {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 0.9rem;
            color: #4b5563;
        }
        .modal-produto-caracteristica i {
            color: #22c55e;
            font-size: 1rem;
            width: 20px;
            text-align: center;
        }
        .modal-produto-descricao-detalhada {
            font-size: 0.9rem;
            color: #4b5563;
            line-height: 1.8;
        }
        .modal-produto-descricao-detalhada p {
            margin-bottom: 12px;
        }

        /* ===== MOBILE CATEGORIAS DRAWER ===== */
        .cat-menu-btn {
            display: none;
            align-items: center;
            gap: 6px;
            padding: 8px 14px;
            background: var(--primary);
            color: #fff;
            border: none;
            border-radius: 8px;
            font-size: 0.875rem;
            font-weight: 600;
            cursor: pointer;
        }
        @media (max-width: 768px) {
            .cat-menu-btn { display: flex; }
        }
        .cat-drawer-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.5);
            z-index: 800;
        }
        .cat-drawer-overlay.open { display: block; }
        .cat-drawer {
            position: fixed;
            top: 0;
            left: 0;
            width: 280px;
            max-width: 85vw;
            height: 100vh;
            background: #fff;
            z-index: 801;
            display: flex;
            flex-direction: column;
            box-shadow: 4px 0 24px rgba(0,0,0,0.18);
            transform: translateX(-100%);
            transition: transform 0.3s cubic-bezier(0.4,0,0.2,1);
        }
        .cat-drawer.open { transform: translateX(0); }
        .cat-drawer-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 18px 20px;
            border-bottom: 1px solid var(--gray-200);
            background: var(--primary);
            color: #fff;
        }
        .cat-drawer-header h3 { font-size: 1rem; font-weight: 700; margin: 0; }
        .cat-drawer-close {
            background: rgba(255,255,255,0.2);
            border: none;
            color: #fff;
            width: 32px;
            height: 32px;
            border-radius: 50%;
            cursor: pointer;
            font-size: 1.1rem;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .cat-drawer-body {
            flex: 1;
            overflow-y: auto;
            padding: 12px;
        }
        .cat-drawer-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 14px;
            border-radius: 10px;
            text-decoration: none;
            font-size: 0.9rem;
            font-weight: 500;
            color: var(--gray-700);
            transition: all 0.15s;
            margin-bottom: 4px;
        }
        .cat-drawer-item:hover { background: var(--gray-100); }
        .cat-drawer-item.active { background: var(--primary); color: #fff; font-weight: 700; }
        .cat-drawer-item .cat-icon { width: 32px; height: 32px; border-radius: 8px; background: var(--gray-100); display: flex; align-items: center; justify-content: center; flex-shrink: 0; font-size: 1.1rem; }
        .cat-drawer-item.active .cat-icon { background: rgba(255,255,255,0.2); color: #fff; }
        .categoria-carousel {
            position: relative;
            overflow: hidden;
            width: 100%;
        }
        .categoria-carousel-track {
            display: flex;
            transition: transform 0.4s cubic-bezier(0.25, 0.46, 0.45, 0.94);
            gap: 14px;
            will-change: transform;
        }
        .categoria-carousel-item {
            flex: 0 0 calc((100% - 42px) / 4);
            min-width: 200px;
            max-width: unset;
            background: #fff;
            border-radius: 14px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.08);
            overflow: hidden;
            border: 1px solid var(--gray-100);
        }
        @media (max-width: 768px) {
            .categoria-carousel-item { flex: 0 0 calc((100% - 14px) / 2) !important; }
        }
        .carousel-btn {
            background: #fff;
            border: 1px solid var(--gray-200);
            width: 36px;
            height: 36px;
            border-radius: 10px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
            color: var(--gray-600);
        }
        .carousel-btn:hover {
            background: var(--primary);
            color: #fff;
            border-color: var(--primary);
        }
        .carousel-btn:disabled {
            opacity: 0.4;
            cursor: not-allowed;
        }

        /* ===== USER MENU ===== */
        .user-menu-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 9px 16px;
            font-size: 0.875rem;
            color: var(--gray-700);
            text-decoration: none;
            transition: background 0.15s;
        }
        .user-menu-item:hover { background: var(--gray-100); }
        .user-menu-item i { width: 16px; text-align: center; color: var(--gray-500); }
        .user-menu-item[style*="ef4444"] i { color: #ef4444; }

        /* ===== LIVE SEARCH NAVBAR ===== */
        .busca-nav-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px 14px;
            cursor: pointer;
            border-bottom: 1px solid var(--gray-100);
            transition: background 0.15s;
        }
        .busca-nav-item:last-child { border-bottom: none; }
        .busca-nav-item:hover { background: var(--gray-50); }
        .busca-nav-item img {
            width: 44px;
            height: 44px;
            object-fit: cover;
            border-radius: 8px;
            flex-shrink: 0;
            background: var(--gray-100);
        }
        .busca-nav-item-info { flex: 1; min-width: 0; }
        .busca-nav-item-info strong { display: block; font-size: 0.875rem; color: #111827; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .busca-nav-item-info small { font-size: 0.75rem; color: var(--gray-400); }
        .busca-nav-item-preco { font-weight: 700; font-size: 0.875rem; color: <?php echo $cor_primaria; ?>; white-space: nowrap; }
        .busca-nav-ver-todos {
            display: block;
            text-align: center;
            padding: 10px;
            font-size: 0.8rem;
            color: <?php echo $cor_primaria; ?>;
            font-weight: 600;
            text-decoration: none;
            border-top: 1px solid var(--gray-100);
        }
        .busca-nav-ver-todos:hover { background: var(--gray-50); }

        /* ===== PÁGINA INDIVIDUAL DO PRODUTO (estilo WooCommerce) ===== */
        .produto-pagina-wrap { max-width: 1200px; margin: 0 auto; padding: 24px 16px 40px; }
        .produto-pagina-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 40px; align-items: flex-start; }
        @media (max-width: 768px) { .produto-pagina-grid { grid-template-columns: 1fr; gap: 24px; } }
        .produto-pagina-galeria img.principal { width: 100%; aspect-ratio: 1; object-fit: cover; border-radius: 14px; background: var(--gray-100); }
        .produto-pagina-thumbs { display: flex; gap: 10px; margin-top: 12px; flex-wrap: wrap; }
        .produto-pagina-thumbs img { width: 70px; height: 70px; object-fit: cover; border-radius: 10px; cursor: pointer; border: 2px solid transparent; transition: border-color .15s; }
        .produto-pagina-thumbs img.active, .produto-pagina-thumbs img:hover { border-color: var(--primary); }
        .produto-pagina-info .categoria { display:inline-flex; align-items:center; gap:6px; background:var(--gray-100); color:var(--gray-700); font-size:.75rem; font-weight:600; padding:5px 10px; border-radius:999px; text-transform:uppercase; letter-spacing:.04em; }
        .produto-pagina-info h1.titulo { font-size: 1.85rem; font-weight: 800; line-height: 1.25; margin: 14px 0 6px; color: var(--gray-900); }
        .produto-pagina-info .sku { color: var(--gray-500); font-size: .85rem; margin-bottom: 14px; }
        .produto-pagina-info .preco-area { display: flex; align-items: baseline; gap: 14px; flex-wrap: wrap; padding: 14px 0; border-top: 1px solid var(--gray-200); border-bottom: 1px solid var(--gray-200); margin-bottom: 18px; }
        .produto-pagina-info .preco-promo { font-size: 1.05rem; color: var(--gray-400); text-decoration: line-through; }
        .produto-pagina-info .preco { font-size: 2rem; font-weight: 900; color: var(--primary); }
        .produto-pagina-info .preco.tem-promo { color: #ef4444; }
        .produto-pagina-info .unidade { color: var(--gray-500); font-size: .9rem; }
        .produto-pagina-info .descricao-curta { color: var(--gray-700); margin-bottom: 18px; line-height: 1.6; }
        .produto-pagina-info .btns { display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 22px; }
        .produto-pagina-info .btns a { flex: 1 1 200px; display: inline-flex; align-items: center; justify-content: center; gap: 8px; padding: 13px 18px; border-radius: 10px; font-weight: 700; text-decoration: none; font-size: .95rem; transition: filter .15s, transform .15s; }
        .produto-pagina-info .btn-primary { background: var(--primary); color: #fff; }
        .produto-pagina-info .btn-primary:hover { filter: brightness(.92); }
        .produto-pagina-info .btn-whats { background: #25d366; color: #fff; }
        .produto-pagina-info .btn-whats:hover { filter: brightness(.92); }
        .produto-pagina-info .btn-voltar { display:inline-flex;align-items:center;gap:6px;color:var(--gray-500);text-decoration:none;font-size:.85rem;font-weight:500;margin-bottom:18px; }
        .produto-pagina-info .btn-voltar:hover { color: var(--primary); }
        .produto-pagina-section { margin-top: 32px; padding-top: 22px; border-top: 1px solid var(--gray-200); }
        .produto-pagina-section h3 { font-size: 1.05rem; font-weight: 700; margin-bottom: 14px; color: var(--gray-900); display:flex; align-items:center; gap:8px; }
        .produto-pagina-caracs { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 8px; }
        .produto-pagina-caracs .item { display: flex; align-items: center; gap: 8px; background: #f0fdf4; color: #166534; padding: 8px 12px; border-radius: 8px; font-size: .875rem; }
        .produto-pagina-descricao { color: var(--gray-700); line-height: 1.7; font-size: .95rem; }

        /* ===== CSS PERSONALIZADO (vindo da página SEO) ===== */
        <?php echo $custom_css; ?>
    </style>

    <!-- ===== SCRIPTS PERSONALIZADOS NO <head> (vindo da página SEO) ===== -->
    <?php echo $custom_head_scripts; ?>
</head>
<body>

<!-- ===== TOAST CONTAINER ===== -->
<div id="toastContainer" class="toast-container"></div>

<!-- Header -->
<header class="site-header">
    <div class="container">
        <div class="header-inner">
            <a href="/" class="logo">
                <?php if ($logo_cliente && in_array($navbar_tipo, ['imagem','imagem_texto'])): ?>
                <img src="<?php echo uploads_url($logo_cliente); ?>" alt="<?php echo sanitize($site_name); ?>" style="max-height:40px;object-fit:contain;">
                <?php else: ?>
                <svg width="32" height="32" viewBox="0 0 36 36" fill="none">
                    <rect width="36" height="36" rx="9" fill="<?php echo $cor_primaria; ?>"/>
                    <path d="M10 24V14l8-5 8 5v10H10z" stroke="white" stroke-width="2" fill="none"/>
                    <circle cx="18" cy="19" r="2.5" fill="white"/>
                </svg>
                <?php endif; ?>
                <?php if (in_array($navbar_tipo, ['texto','imagem_texto'])): ?>
                <span class="logo-text"><?php echo sanitize($site_name); ?></span>
                <?php endif; ?>
            </a>

            <!-- Barra de busca com live-search -->
            <div class="desktop-only busca-wrap-nav" style="flex:1;max-width:420px;margin:0 24px;position:relative;">
                <div style="position:relative;">
                    <input type="text" id="buscaNavInput" value="<?php echo sanitize($busca); ?>" placeholder="Buscar produtos..."
                           autocomplete="off"
                           style="width:100%;padding:10px 16px 10px 42px;border:1px solid var(--gray-200);border-radius:30px;font-size:0.875rem;outline:none;transition:all 0.2s;"
                           onfocus="this.style.borderColor='<?php echo $cor_primaria; ?>';this.style.boxShadow='0 0 0 3px <?php echo $cor_primaria; ?>20'"
                           onblur="this.style.borderColor='var(--gray-200)';this.style.boxShadow='none'">
                    <span style="position:absolute;left:14px;top:50%;transform:translateY(-50%);color:var(--gray-400);pointer-events:none;">
                        <i class="fas fa-search" style="font-size:0.875rem;"></i>
                    </span>
                    <span id="buscaNavSpinner" style="display:none;position:absolute;right:14px;top:50%;transform:translateY(-50%);color:var(--gray-400);">
                        <i class="fas fa-circle-notch fa-spin" style="font-size:0.8rem;"></i>
                    </span>
                </div>
                <!-- Dropdown de resultados -->
                <div id="buscaNavResultados" style="display:none;position:absolute;top:calc(100% + 6px);left:0;right:0;background:#fff;border:1px solid var(--gray-200);border-radius:12px;box-shadow:0 8px 30px rgba(0,0,0,0.12);z-index:600;max-height:360px;overflow-y:auto;"></div>
            </div>

            <div class="header-actions">
                <!-- Botão menu categorias (mobile) -->
                <button class="cat-menu-btn" onclick="abrirCatDrawer()" aria-label="Categorias">
                    <i class="fas fa-bars"></i>
                    <span>Categorias</span>
                </button>

                <?php if (!empty($whatsapp)): ?>
                <a href="<?php echo whatsapp_link($whatsapp); ?>" target="_blank" class="btn btn-whatsapp" style="background:#25d366;color:#fff;padding:8px 16px;border-radius:8px;font-size:0.875rem;font-weight:600;display:flex;align-items:center;gap:6px;text-decoration:none;">
                    <i class="fab fa-whatsapp"></i>
                    <span class="hide-mobile">WhatsApp</span>
                </a>
                <?php endif; ?>

                <!-- Carrinho de orçamento -->
                <button onclick="toggleCarrinho()" style="position:relative;background:none;border:1px solid var(--gray-200);color:var(--gray-700);padding:8px 14px;border-radius:8px;cursor:pointer;display:flex;align-items:center;gap:6px;font-size:0.875rem;">
                    <i class="fas fa-shopping-cart"></i>
                    <span class="hide-mobile">Orçamento</span>
                    <span id="badge-carrinho" class="badge-carrinho" style="<?php echo empty($carrinho) ? 'display:none;' : ''; ?>"><?php echo count($carrinho); ?></span>
                </button>

                <!-- Menu do usuário -->
                <div class="user-menu-wrap" style="position:relative;">
                    <button id="btnUserMenu" onclick="toggleUserMenu()" style="background:none;border:1px solid var(--gray-200);color:var(--gray-700);padding:8px 12px;border-radius:8px;cursor:pointer;display:flex;align-items:center;gap:6px;font-size:0.875rem;transition:all 0.2s;" aria-label="Menu do usuário">
                        <i class="fas fa-user-circle" style="font-size:1.1rem;color:var(--gray-500);"></i>
                        <?php if ($cliente_logado): ?>
                        <span class="hide-mobile" style="max-width:110px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-weight:500;"><?php echo sanitize($cliente_nome ?: 'Minha Conta'); ?></span>
                        <?php endif; ?>
                        <i class="fas fa-chevron-down" style="font-size:0.65rem;color:var(--gray-400);"></i>
                    </button>

                    <!-- Dropdown -->
                    <div id="userMenuDropdown" style="display:none;position:absolute;top:calc(100% + 8px);right:0;width:220px;background:#fff;border:1px solid var(--gray-200);border-radius:14px;box-shadow:0 10px 40px rgba(0,0,0,0.13);z-index:700;overflow:hidden;">
                        <?php if ($cliente_logado): ?>
                        <!-- Cabeçalho logado -->
                        <div style="padding:16px;border-bottom:1px solid var(--gray-100);background:var(--gray-50);">
                            <p style="font-weight:700;font-size:0.9rem;color:#111827;margin:0 0 2px;"><?php echo sanitize($cliente_nome ?: 'Minha Conta'); ?></p>
                            <?php if ($cliente_email): ?>
                            <p style="font-size:0.78rem;color:var(--gray-500);margin:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="<?php echo sanitize($cliente_email); ?>"><?php echo sanitize($cliente_email); ?></p>
                            <?php endif; ?>
                        </div>
                        <!-- Links logado -->
                        <div style="padding:8px 0;">
                            <a href="/cliente/pedidos.php" class="user-menu-item"><i class="fas fa-file-invoice-dollar"></i> Meus Pedidos</a>
                            <a href="/cliente/perfil.php" class="user-menu-item"><i class="fas fa-user-edit"></i> Meu Perfil</a>
                        </div>
                        <div style="padding:8px 0;border-top:1px solid var(--gray-100);">
                            <a href="/cliente/logout.php" class="user-menu-item" style="color:#ef4444;"><i class="fas fa-sign-out-alt"></i> Sair</a>
                        </div>
                        <?php else: ?>
                        <!-- Deslogado -->
                        <div style="padding:16px;border-bottom:1px solid var(--gray-100);background:var(--gray-50);">
                            <p style="font-weight:700;font-size:0.875rem;color:#111827;margin:0 0 2px;">Bem-vindo!</p>
                            <p style="font-size:0.78rem;color:var(--gray-500);margin:0;">Acesse sua conta</p>
                        </div>
                        <div style="padding:8px 0;">
                            <a href="/cliente/login.php" class="user-menu-item"><i class="fas fa-sign-in-alt"></i> Entrar</a>
                            <a href="/cliente/cadastro.php" class="user-menu-item"><i class="fas fa-user-plus"></i> Criar Conta</a>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</header>

<!-- Barra de Categorias INLINE (abaixo do menu) -->
<?php if ($categoria_layout === 'inline'): ?>
<div class="cat-inline-bar">
    <div class="container">
        <div class="inner">
            <a href="/" style="display:inline-flex;align-items:center;gap:6px;padding:7px 14px;border-radius:20px;font-size:0.8125rem;font-weight:500;background:<?php echo !$categoria_id ? $cor_primaria : 'transparent'; ?>;color:<?php echo !$categoria_id ? '#fff' : 'var(--gray-600)'; ?>;text-decoration:none;border:1px solid <?php echo !$categoria_id ? $cor_primaria : 'var(--gray-200)'; ?>;">
                <i class="fas fa-th-large" style="font-size:0.75rem;"></i> Todos
            </a>
            <?php foreach ($categorias as $cat): ?>
            <a href="/?categoria=<?php echo $cat['id']; ?>" style="display:inline-flex;align-items:center;gap:6px;padding:7px 14px;border-radius:20px;font-size:0.8125rem;font-weight:500;background:<?php echo $categoria_id == $cat['id'] ? $cor_primaria : 'transparent'; ?>;color:<?php echo $categoria_id == $cat['id'] ? '#fff' : 'var(--gray-600)'; ?>;text-decoration:none;border:1px solid <?php echo $categoria_id == $cat['id'] ? $cor_primaria : 'var(--gray-200)'; ?>;">
                <?php if (!empty($cat['icone'])): ?>
                <span class="material-icons" style="font-size:0.9rem;"><?php echo sanitize($cat['icone']); ?></span>
                <?php endif; ?>
                <?php echo sanitize($cat['nome']); ?>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Painel do carrinho de orçamento -->
<div id="painelCarrinho" style="display:none;position:fixed;top:0;right:0;width:100%;max-width:420px;height:100vh;background:#fff;box-shadow:-8px 0 30px rgba(0,0,0,0.2);z-index:1000;overflow-y:auto;flex-direction:column;">
    <div style="padding:20px;border-bottom:1px solid var(--gray-200);display:flex;justify-content:space-between;align-items:center;">
        <h3 style="font-size:1.125rem;font-weight:700;"><i class="fas fa-shopping-cart" style="color:<?php echo $cor_primaria; ?>;margin-right:8px;"></i> Orçamento</h3>
        <button onclick="toggleCarrinho()" style="background:none;border:none;font-size:1.25rem;cursor:pointer;color:var(--gray-500);">&times;</button>
    </div>

    <div id="conteudoCarrinho">
    <?php if (empty($carrinho)): ?>
    <div style="text-align:center;padding:60px 20px;color:var(--gray-400);">
        <i class="fas fa-shopping-cart" style="font-size:3rem;margin-bottom:16px;display:block;"></i>
        <p>Nenhum produto no orçamento</p>
    </div>
    <?php else: ?>
    <div style="flex:1;overflow-y:auto;padding:16px;" id="listaCarrinho">
        <?php foreach ($carrinho as $pid => $item): 
            $preco_item = (float)($item['preco_promocional'] > 0 ? $item['preco_promocional'] : $item['preco']);
        ?>
        <div style="display:flex;gap:12px;padding:12px 0;border-bottom:1px solid var(--gray-100);" data-pid="<?php echo $pid; ?>">
            <?php if ($item['imagem_principal']): ?>
            <img src="<?php echo uploads_url($item['imagem_principal']); ?>" style="width:56px;height:56px;object-fit:cover;border-radius:8px;flex-shrink:0;">
            <?php endif; ?>
            <div style="flex:1;min-width:0;">
                <strong style="font-size:0.875rem;display:block;margin-bottom:4px;"><?php echo sanitize($item['nome']); ?></strong>
                <?php if ($mostrar_preco): ?>
                <p style="font-size:0.8rem;color:var(--gray-500);margin-bottom:6px;"><?php echo format_currency($preco_item); ?> / un</p>
                <?php endif; ?>
                <div class="qty-ctrl">
                    <a href="#" class="qty-btn" title="Diminuir" onclick="event.preventDefault();ajaxCart('qty_minus',<?php echo $pid; ?>)">
                        <i class="fas fa-minus" style="font-size:0.7rem;"></i>
                    </a>
                    <span style="min-width:28px;text-align:center;font-weight:700;font-size:0.9rem;"><?php echo $item['qtd']; ?></span>
                    <a href="#" class="qty-btn" title="Aumentar" style="background:<?php echo $cor_primaria; ?>22;border-color:<?php echo $cor_primaria; ?>44;color:<?php echo $cor_primaria; ?>;" onclick="event.preventDefault();ajaxCart('qty_plus',<?php echo $pid; ?>)">
                        <i class="fas fa-plus" style="font-size:0.7rem;"></i>
                    </a>
                    <?php if ($mostrar_preco): ?>
                    <span style="margin-left:8px;font-weight:700;color:<?php echo $cor_primaria; ?>;font-size:0.875rem;"><?php echo format_currency($preco_item * $item['qtd']); ?></span>
                    <?php endif; ?>
                </div>
            </div>
            <a href="#" style="color:var(--gray-400);font-size:0.875rem;align-self:flex-start;padding:2px;" title="Remover" onclick="event.preventDefault();ajaxCart('remove',<?php echo $pid; ?>)"><i class="fas fa-times"></i></a>
        </div>
        <?php endforeach; ?>
    </div>

    <div style="padding:16px;border-top:1px solid var(--gray-200);">
        <?php if ($mostrar_preco): ?>
        <div style="display:flex;justify-content:space-between;font-weight:700;font-size:1.1rem;margin-bottom:16px;">
            <span>Total:</span>
            <span style="color:<?php echo $cor_primaria; ?>"><?php echo format_currency($total_carrinho); ?></span>
        </div>
        <?php endif; ?>
        <button onclick="abrirModalOrcamento()" 
           style="display:flex;align-items:center;justify-content:center;gap:10px;background:<?php echo $cor_primaria; ?>;color:#fff;padding:14px;border-radius:10px;font-weight:700;margin-bottom:8px;font-size:0.9375rem;width:100%;border:none;cursor:pointer;">
            <i class="fas fa-paper-plane" style="font-size:1.25rem;"></i> Enviar Orçamento
        </button>
        <a href="#" onclick="event.preventDefault();ajaxCart('clear',0)" style="display:block;text-align:center;color:var(--gray-400);font-size:0.8rem;margin-top:8px;">Limpar orçamento</a>
    </div>
    <?php endif; ?>
    </div>
</div>
<div id="overlayCarrinho" onclick="toggleCarrinho()" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.4);z-index:999;"></div>

<!-- Modal Enviar Orçamento -->
<div id="modalOrcamento" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.6);z-index:2000;align-items:center;justify-content:center;padding:20px;">
    <div style="background:#fff;border-radius:16px;width:100%;max-width:460px;padding:28px;position:relative;">
        <button onclick="fecharModalOrcamento()" style="position:absolute;top:16px;right:16px;background:var(--gray-100);border:none;width:32px;height:32px;border-radius:50%;cursor:pointer;font-size:1.1rem;">&times;</button>
        <h3 style="font-size:1.125rem;font-weight:700;margin-bottom:6px;"><i class="fas fa-file-invoice-dollar" style="color:<?php echo $cor_primaria; ?>;margin-right:8px;"></i>Confirmar Orçamento</h3>
        <p style="color:var(--gray-500);font-size:0.875rem;margin-bottom:20px;">Informe seus dados para registrarmos o orçamento.</p>

        <div id="orcStep1">
            <?php if ($cliente_logado): ?>
            <!-- Cliente logado: exibe dados e oculta campos -->
            <div style="display:flex;align-items:center;gap:12px;background:var(--gray-50);border:1px solid var(--gray-200);border-radius:10px;padding:12px 14px;margin-bottom:16px;">
                <i class="fas fa-user-check" style="font-size:1.25rem;color:<?php echo $cor_primaria; ?>;flex-shrink:0;"></i>
                <div>
                    <p style="font-weight:700;font-size:0.9rem;color:#111827;margin:0 0 2px;"><?php echo sanitize($cliente_nome); ?></p>
                    <?php if ($cliente_email): ?>
                    <p style="font-size:0.78rem;color:var(--gray-500);margin:0;"><?php echo sanitize($cliente_email); ?></p>
                    <?php endif; ?>
                </div>
            </div>
            <!-- Campos hidden para envio -->
            <input type="hidden" id="orcNome" value="<?php echo sanitize($cliente_nome); ?>">
            <input type="hidden" id="orcTel" value="<?php echo sanitize($cliente_tel); ?>">
            <?php else: ?>
            <!-- Deslogado: exibe campos normais + link de login -->
            <div style="margin-bottom:12px;">
                <label style="display:block;font-size:0.875rem;font-weight:600;margin-bottom:4px;">Seu nome <span style="color:#ef4444;">*</span></label>
                <input type="text" id="orcNome" placeholder="Nome completo" style="width:100%;padding:10px 14px;border:1px solid var(--gray-200);border-radius:8px;font-size:0.875rem;outline:none;" required>
            </div>
            <div style="margin-bottom:14px;">
                <label style="display:block;font-size:0.875rem;font-weight:600;margin-bottom:4px;">WhatsApp / Telefone</label>
                <input type="tel" id="orcTel" placeholder="(11) 99999-9999" style="width:100%;padding:10px 14px;border:1px solid var(--gray-200);border-radius:8px;font-size:0.875rem;outline:none;">
            </div>
            <!-- Link login/cadastro -->
            <div style="background:#eff6ff;border:1px solid #bfdbfe;border-radius:8px;padding:10px 14px;margin-bottom:16px;font-size:0.8rem;color:#1e40af;display:flex;align-items:center;gap:8px;">
                <i class="fas fa-info-circle" style="flex-shrink:0;"></i>
                <span>Tem uma conta? <a href="/cliente/login.php" style="font-weight:700;color:<?php echo $cor_primaria; ?>;text-decoration:underline;">Entrar</a> ou <a href="/cliente/cadastro.php" style="font-weight:700;color:<?php echo $cor_primaria; ?>;text-decoration:underline;">Cadastre-se</a> para agilizar seus orçamentos.</span>
            </div>
            <?php endif; ?>
            <button onclick="registrarOrcamento()" style="width:100%;background:<?php echo $cor_primaria; ?>;color:#fff;border:none;padding:13px;border-radius:10px;font-weight:700;font-size:0.9375rem;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:8px;">
                <i class="fas fa-check-circle"></i> Registrar Orçamento
            </button>
        </div>

        <div id="orcStep2" style="display:none;text-align:center;">
            <div style="font-size:3rem;margin-bottom:12px;">✅</div>
            <h4 style="font-weight:700;margin-bottom:6px;">Orçamento Registrado!</h4>
            <p style="color:var(--gray-500);font-size:0.875rem;margin-bottom:6px;">Código: <strong id="orcCodigo" style="color:<?php echo $cor_primaria; ?>"></strong></p>
            <p style="color:var(--gray-600);font-size:0.875rem;margin-bottom:20px;">Deseja enviar um lembrete via WhatsApp para agilizar o atendimento?</p>
            <div style="display:flex;flex-direction:column;gap:10px;">
                <?php if (!empty($whatsapp)): ?>
                <a id="btnEnviarWpp" href="#" target="_blank"
                   style="display:flex;align-items:center;justify-content:center;gap:10px;background:#25d366;color:#fff;padding:13px;border-radius:10px;font-weight:700;text-decoration:none;font-size:0.9375rem;">
                    <i class="fab fa-whatsapp" style="font-size:1.25rem;"></i> Sim, enviar no WhatsApp
                </a>
                <?php endif; ?>
                <button onclick="fecharELimpar()" style="background:var(--gray-100);color:var(--gray-700);border:none;padding:12px;border-radius:10px;font-weight:600;cursor:pointer;font-size:0.875rem;">
                    Não, apenas registrar
                </button>
            </div>
        </div>

        <div id="orcLoading" style="display:none;text-align:center;padding:20px;">
            <i class="fas fa-spinner fa-spin" style="font-size:2rem;color:<?php echo $cor_primaria; ?>"></i>
            <p style="margin-top:12px;color:var(--gray-500);">Registrando orçamento...</p>
        </div>
    </div>
</div>

<!-- Banners SLIDE (dentro do container, só na home sem filtros) -->
<?php if (!empty($banners_slide) && !$busca && !$categoria_id && $page == 1): ?>
<div class="container" style="padding-top:24px;">
    <div style="border-radius:14px;overflow:hidden;position:relative;background:var(--gray-900);" id="bannerSlider">
        <div id="bannerTrack" style="display:flex;transition:transform 0.6s cubic-bezier(0.25,0.46,0.45,0.94);">
            <?php foreach ($banners_slide as $b): ?>
            <div style="min-width:100%;position:relative;flex-shrink:0;">
                <?php if ($b['link']): ?><a href="<?php echo sanitize($b['link']); ?>"><?php endif; ?>
                <img src="<?php echo uploads_url($b['imagem']); ?>" alt="<?php echo sanitize($b['titulo']??''); ?>"
                     style="width:100%;max-height:380px;min-height:160px;object-fit:cover;display:block;">
                <?php if ($b['link']): ?></a><?php endif; ?>
                <?php if ($b['titulo'] || $b['subtitulo']): ?>
                <div style="position:absolute;bottom:0;left:0;right:0;background:linear-gradient(to top,rgba(0,0,0,0.75),transparent);padding:24px 32px;">
                    <?php if ($b['titulo']): ?><h2 style="color:#fff;font-size:1.6rem;font-weight:700;text-shadow:0 2px 8px rgba(0,0,0,0.4);margin:0;"><?php echo sanitize($b['titulo']); ?></h2><?php endif; ?>
                    <?php if ($b['subtitulo']): ?><p style="color:rgba(255,255,255,0.85);margin-top:6px;font-size:0.95rem;"><?php echo sanitize($b['subtitulo']); ?></p><?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>

        <?php if (count($banners_slide) > 1): ?>
        <button onclick="bannerPrev()" style="position:absolute;left:12px;top:50%;transform:translateY(-50%);background:rgba(255,255,255,0.2);backdrop-filter:blur(4px);border:1px solid rgba(255,255,255,0.3);color:#fff;width:40px;height:40px;border-radius:50%;cursor:pointer;font-size:1rem;display:flex;align-items:center;justify-content:center;transition:all 0.2s;z-index:5;" onmouseover="this.style.background='rgba(255,255,255,0.4)'" onmouseout="this.style.background='rgba(255,255,255,0.2)'">
            <i class="fas fa-chevron-left"></i>
        </button>
        <button onclick="bannerNext()" style="position:absolute;right:12px;top:50%;transform:translateY(-50%);background:rgba(255,255,255,0.2);backdrop-filter:blur(4px);border:1px solid rgba(255,255,255,0.3);color:#fff;width:40px;height:40px;border-radius:50%;cursor:pointer;font-size:1rem;display:flex;align-items:center;justify-content:center;transition:all 0.2s;z-index:5;" onmouseover="this.style.background='rgba(255,255,255,0.4)'" onmouseout="this.style.background='rgba(255,255,255,0.2)'">
            <i class="fas fa-chevron-right"></i>
        </button>
        <div id="bannerDots" style="position:absolute;bottom:12px;left:50%;transform:translateX(-50%);display:flex;gap:8px;z-index:5;">
            <?php foreach ($banners_slide as $bi => $b): ?>
            <button onclick="bannerGoTo(<?php echo $bi; ?>)" id="dot-<?php echo $bi; ?>"
                style="width:<?php echo $bi===0?'24px':'8px'; ?>;height:8px;border-radius:4px;border:none;background:<?php echo $bi===0?'#fff':'rgba(255,255,255,0.45)'; ?>;cursor:pointer;transition:all 0.3s;padding:0;"></button>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<!-- Drawer de Categorias (Mobile) -->
<div id="catDrawerOverlay" class="cat-drawer-overlay" onclick="fecharCatDrawer()"></div>
<div id="catDrawer" class="cat-drawer" role="dialog" aria-label="Categorias">
    <div class="cat-drawer-header">
        <h3><i class="fas fa-th-large" style="margin-right:8px;"></i>Categorias</h3>
        <button class="cat-drawer-close" onclick="fecharCatDrawer()" aria-label="Fechar">&times;</button>
    </div>
    <div class="cat-drawer-body">
        <a href="/" class="cat-drawer-item <?php echo !$categoria_id ? 'active' : ''; ?>" onclick="fecharCatDrawer()">
            <span class="cat-icon"><i class="fas fa-th-large"></i></span>
            Todos os Produtos
        </a>
        <?php foreach ($categorias as $cat): ?>
        <a href="/?categoria=<?php echo $cat['id']; ?>" class="cat-drawer-item <?php echo $categoria_id == $cat['id'] ? 'active' : ''; ?>" onclick="fecharCatDrawer()">
            <span class="cat-icon">
                <?php if (!empty($cat['icone'])): ?>
                <span class="material-icons" style="font-size:1.1rem;"><?php echo sanitize($cat['icone']); ?></span>
                <?php else: ?>
                <i class="fas fa-tag"></i>
                <?php endif; ?>
            </span>
            <?php echo sanitize($cat['nome']); ?>
        </a>
        <?php endforeach; ?>
    </div>
</div>

<?php
// Se estamos no modo "Página Individual do Produto" e existe produto carregado,
// pulamos a renderização do catálogo (grade + sidebar) e mostramos apenas o produto.
$mostrar_catalogo = !($produto_modal && $produto_visualizacao === 'pagina_individual');
?>

<?php if ($produto_modal && $produto_visualizacao === 'pagina_individual'):
    $preco_modal     = (float)($produto_modal['preco_promocional'] > 0 ? $produto_modal['preco_promocional'] : $produto_modal['preco']);
    $tem_promo_modal = !empty($produto_modal['preco_promocional']) && $produto_modal['preco_promocional'] > 0;
    $voltar_url      = '/' . ($categoria_id ? '?categoria=' . $categoria_id : '');
?>
<!-- ============ PÁGINA INDIVIDUAL DO PRODUTO (estilo WooCommerce) ============ -->
<div class="produto-pagina-wrap">

    <!-- Breadcrumb -->
    <nav aria-label="Breadcrumb" style="margin-bottom:18px;">
        <ol style="display:flex;align-items:center;flex-wrap:wrap;gap:6px;list-style:none;padding:10px 14px;background:#fff;border:1px solid var(--gray-200);border-radius:10px;font-size:.825rem;">
            <li><a href="/" style="color:var(--gray-500);text-decoration:none;font-weight:500;"><i class="fas fa-home" style="font-size:.75rem;"></i> Início</a></li>
            <?php if (!empty($produto_modal['categoria_nome'])): ?>
            <li style="color:var(--gray-400);">/</li>
            <li><a href="/?categoria=<?php echo (int)$produto_modal['categoria_id']; ?>" style="color:var(--gray-500);text-decoration:none;font-weight:500;"><?php echo sanitize($produto_modal['categoria_nome']); ?></a></li>
            <?php endif; ?>
            <li style="color:var(--gray-400);">/</li>
            <li style="color:var(--gray-800);font-weight:600;"><?php echo sanitize($produto_modal['nome']); ?></li>
        </ol>
    </nav>

    <a href="<?php echo $voltar_url; ?>" class="btn-voltar" style="display:inline-flex;align-items:center;gap:6px;color:var(--gray-500);text-decoration:none;font-size:.85rem;font-weight:500;margin-bottom:18px;"><i class="fas fa-arrow-left"></i> Voltar ao catálogo</a>

    <div class="produto-pagina-grid">
        <!-- GALERIA -->
        <div class="produto-pagina-galeria">
            <img id="imgPrincipalPag"
                 src="<?php echo $produto_modal['imagem_principal'] ? uploads_url($produto_modal['imagem_principal']) : '/assets/images/no-image.svg'; ?>"
                 alt="<?php echo sanitize($produto_modal['nome']); ?>"
                 class="principal">
            <?php if (!empty($produto_imagens_modal) || $produto_modal['imagem_principal']): ?>
            <div class="produto-pagina-thumbs">
                <?php if ($produto_modal['imagem_principal']): ?>
                <img src="<?php echo uploads_url($produto_modal['imagem_principal']); ?>" class="active"
                     onclick="changeMainImagePag(this, '<?php echo uploads_url($produto_modal['imagem_principal']); ?>')">
                <?php endif; ?>
                <?php foreach ($produto_imagens_modal as $img): ?>
                <img src="<?php echo uploads_url($img['imagem']); ?>"
                     onclick="changeMainImagePag(this, '<?php echo uploads_url($img['imagem']); ?>')">
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- INFO -->
        <div class="produto-pagina-info">
            <?php if (!empty($produto_modal['categoria_nome'])): ?>
            <span class="categoria"><i class="fas fa-tag" style="font-size:.65rem;"></i> <?php echo sanitize($produto_modal['categoria_nome']); ?></span>
            <?php endif; ?>

            <h1 class="titulo"><?php echo sanitize($produto_modal['nome']); ?></h1>

            <?php if (!empty($produto_modal['sku'])): ?>
            <p class="sku">SKU: <?php echo sanitize($produto_modal['sku']); ?></p>
            <?php endif; ?>

            <?php if ($mostrar_preco && $produto_modal['preco'] > 0): ?>
            <div class="preco-area">
                <?php if ($tem_promo_modal): ?>
                <span class="preco-promo"><?php echo format_currency((float)$produto_modal['preco']); ?></span>
                <?php endif; ?>
                <span class="preco <?php echo $tem_promo_modal ? 'tem-promo' : ''; ?>"><?php echo format_currency($preco_modal); ?></span>
                <?php if (!empty($produto_modal['unidade'])): ?>
                <span class="unidade">/ <?php echo sanitize($produto_modal['unidade']); ?></span>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <?php if (!empty($produto_modal['descricao_curta'])): ?>
            <p class="descricao-curta"><?php echo sanitize($produto_modal['descricao_curta']); ?></p>
            <?php endif; ?>

            <div class="btns">
                <a href="#" class="btn-primary"
                   onclick="event.preventDefault();adicionarAoCarrinho(<?php echo (int)$produto_modal['id']; ?>, '<?php echo addslashes(sanitize($produto_modal['nome'])); ?>', '<?php echo uploads_url($produto_modal['imagem_principal'] ?? ''); ?>')">
                    <i class="fas fa-plus"></i> Adicionar ao Orçamento
                </a>
                <?php if (!empty($whatsapp)): ?>
                <a href="<?php echo whatsapp_link($whatsapp, "Olá! Tenho interesse no produto: {$produto_modal['nome']}" . ($produto_modal['sku']?" (SKU: {$produto_modal['sku']})":"") . ($mostrar_preco && $produto_modal['preco']>0?" - Valor: " . format_currency((float)$produto_modal['preco']):"") . ". Podem me ajudar?"); ?>"
                   target="_blank" class="btn-whats">
                    <i class="fab fa-whatsapp" style="font-size:1.2rem;"></i> Perguntar via WhatsApp
                </a>
                <?php endif; ?>
            </div>

            <!-- Características -->
            <?php if (!empty($caracteristicas_modal)): ?>
            <div class="produto-pagina-section">
                <h3><i class="fas fa-check-circle" style="color:#22c55e;"></i> Características</h3>
                <div class="produto-pagina-caracs">
                    <?php foreach ($caracteristicas_modal as $carac): ?>
                    <div class="item"><i class="fas fa-check"></i><span><?php echo sanitize(is_array($carac) ? ($carac['texto'] ?? $carac['nome'] ?? reset($carac)) : $carac); ?></span></div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Descrição detalhada -->
            <?php if (!empty($produto_modal['descricao'])): ?>
            <div class="produto-pagina-section">
                <h3><i class="fas fa-align-left" style="color:var(--primary);"></i> Descrição Detalhada</h3>
                <div class="produto-pagina-descricao"><?php echo nl2br(sanitize($produto_modal['descricao'])); ?></div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Você pode gostar (mesma categoria) -->
    <?php if (!empty($categoria_carrossel)): ?>
    <div class="produto-pagina-section">
        <h3 style="margin-bottom:18px;"><i class="fas fa-th-large" style="color:var(--primary);"></i> Você pode gostar</h3>
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:18px;">
            <?php foreach ($categoria_carrossel as $cprod):
                $preco_final_car = (!empty($cprod['preco_promocional']) && (float)$cprod['preco_promocional'] > 0) ? (float)$cprod['preco_promocional'] : (float)$cprod['preco'];
                $tem_promo_car   = !empty($cprod['preco_promocional']) && (float)$cprod['preco_promocional'] > 0;
            ?>
            <div style="background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 1px 3px rgba(0,0,0,0.08);">
                <a href="/?produto_id=<?php echo (int)$cprod['id']; ?>" style="display:block;aspect-ratio:1;overflow:hidden;background:var(--gray-100);position:relative;">
                    <?php if (!empty($cprod['imagem_principal'])): ?>
                    <img src="<?php echo uploads_url($cprod['imagem_principal']); ?>" alt="<?php echo sanitize($cprod['nome']); ?>" style="width:100%;height:100%;object-fit:cover;">
                    <?php endif; ?>
                    <?php if ($tem_promo_car): ?><span style="position:absolute;top:8px;left:8px;background:#ef4444;color:#fff;font-size:.7rem;font-weight:800;padding:3px 8px;border-radius:12px;">PROMO</span><?php endif; ?>
                </a>
                <div style="padding:12px;">
                    <a href="/?produto_id=<?php echo (int)$cprod['id']; ?>" style="text-decoration:none;color:inherit;">
                        <h6 style="margin:0;font-size:.85rem;font-weight:700;color:#111827;line-height:1.3;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;"><?php echo sanitize($cprod['nome']); ?></h6>
                    </a>
                    <?php if ($mostrar_preco && !empty($cprod['preco'])): ?>
                    <div style="margin-top:8px;display:flex;align-items:baseline;gap:8px;flex-wrap:wrap;">
                        <?php if ($tem_promo_car): ?><span style="text-decoration:line-through;color:var(--gray-400);font-size:.75rem;"><?php echo format_currency((float)$cprod['preco']); ?></span><?php endif; ?>
                        <span style="font-weight:900;color:<?php echo $tem_promo_car ? '#ef4444' : $cor_primaria; ?>;"><?php echo format_currency((float)$preco_final_car); ?></span>
                    </div>
                    <?php endif; ?>
                    <a href="#" onclick="event.preventDefault();adicionarAoCarrinho(<?php echo (int)$cprod['id']; ?>, '<?php echo addslashes(sanitize($cprod['nome'])); ?>', '<?php echo uploads_url($cprod['imagem_principal'] ?? ''); ?>')"
                       style="display:flex;align-items:center;justify-content:center;gap:6px;background:<?php echo $cor_primaria; ?>;color:#fff;border-radius:10px;padding:9px;font-weight:700;font-size:.8rem;text-decoration:none;margin-top:10px;"><i class="fas fa-plus" style="font-size:.7rem;"></i> Adicionar</a>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

</div>

<script>
function changeMainImagePag(thumb, src) {
    document.getElementById('imgPrincipalPag').src = src;
    document.querySelectorAll('.produto-pagina-thumbs img').forEach(t => t.classList.remove('active'));
    thumb.classList.add('active');
}
</script>

<!-- ============ FIM PÁGINA INDIVIDUAL ============ -->

<?php endif; ?>

<?php if ($mostrar_catalogo): ?>
<div class="container" style="display:flex; gap:24px; padding-top:24px; padding-bottom:40px; align-items:flex-start;">

    <!-- Sidebar de Categorias (DESKTOP - apenas no modo sidebar) -->
    <?php if ($categoria_layout === 'sidebar'): ?>
    <aside class="cat-sidebar desktop-only">
        <?php if (!empty($banners_categoria)): ?>
        <div style="margin-bottom:16px;">
            <?php foreach ($banners_categoria as $bc): ?>
            <div style="margin-bottom:10px;border-radius:10px;overflow:hidden;box-shadow:0 1px 4px rgba(0,0,0,0.08);">
                <?php if ($bc['link']): ?><a href="<?php echo sanitize($bc['link']); ?>"><?php endif; ?>
                <img src="<?php echo uploads_url($bc['imagem']); ?>"
                     alt="<?php echo sanitize($bc['titulo']??''); ?>"
                     style="width:100%;display:block;">
                <?php if ($bc['link']): ?></a><?php endif; ?>
                <?php if (!empty($bc['titulo'])): ?>
                <div style="font-size:.75rem;color:var(--gray-500);padding:4px 6px;text-align:center;"><?php echo sanitize($bc['titulo']); ?></div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        <h3 style="font-size:0.875rem;font-weight:700;margin-bottom:12px;color:var(--gray-800);text-transform:uppercase;letter-spacing:0.05em;">Categorias</h3>
        <div style="display:flex;flex-direction:column;gap:4px;">
            <a href="/" style="background:<?php echo !$categoria_id ? $cor_primaria : 'transparent'; ?>;color:<?php echo !$categoria_id ? '#fff' : 'var(--gray-600)'; ?>;border:1px solid <?php echo !$categoria_id ? $cor_primaria : 'transparent'; ?>;">
                <i class="fas fa-th-large" style="width:18px;text-align:center;font-size:0.875rem;"></i> Todos
            </a>
            <?php foreach ($categorias as $cat): ?>
            <a href="/?categoria=<?php echo $cat['id']; ?>" style="background:<?php echo $categoria_id == $cat['id'] ? $cor_primaria : 'transparent'; ?>;color:<?php echo $categoria_id == $cat['id'] ? '#fff' : 'var(--gray-600)'; ?>;border:1px solid <?php echo $categoria_id == $cat['id'] ? $cor_primaria : 'transparent'; ?>;">
                <?php if (!empty($cat['icone'])): ?>
                <span class="material-icons" style="width:18px;text-align:center;font-size:1.1rem;"><?php echo sanitize($cat['icone']); ?></span>
                <?php else: ?>
                <i class="fas fa-tag" style="width:18px;text-align:center;font-size:0.875rem;"></i>
                <?php endif; ?>
                <?php echo sanitize($cat['nome']); ?>
            </a>
            <?php endforeach; ?>
        </div>
    </aside>
    <?php endif; ?>

    <main style="flex:1;min-width:0;">
        <?php
        // ===== BREADCRUMB =====
        $mostrar_breadcrumb = $categoria_id || $busca || $produto_modal;
        if ($mostrar_breadcrumb):
            $cat_atual = null;
            if ($categoria_id) {
                foreach ($categorias as $c) {
                    if ($c['id'] == $categoria_id) { $cat_atual = $c; break; }
                }
            }
        ?>
        <nav aria-label="Breadcrumb" style="margin-bottom:18px;">
            <ol style="display:flex;align-items:center;flex-wrap:wrap;gap:4px;list-style:none;padding:10px 14px;background:#fff;border:1px solid var(--gray-200);border-radius:10px;font-size:0.8125rem;">
                <!-- Home -->
                <li style="display:flex;align-items:center;gap:4px;">
                    <a href="/" style="display:inline-flex;align-items:center;gap:5px;color:var(--gray-500);text-decoration:none;font-weight:500;transition:color 0.15s;" onmouseover="this.style.color='<?php echo $cor_primaria; ?>'" onmouseout="this.style.color='var(--gray-500)'">
                        <svg style="width:14px;height:14px;flex-shrink:0;" fill="none" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m4 12 8-8 8 8M6 10.5V19a1 1 0 0 0 1 1h3v-3a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1v3h3a1 1 0 0 0 1-1v-8.5"/></svg>
                        Início
                    </a>
                </li>

                <?php if ($busca): ?>
                <!-- Busca -->
                <li style="display:flex;align-items:center;gap:4px;">
                    <svg style="width:12px;height:12px;color:var(--gray-400);flex-shrink:0;" fill="none" viewBox="0 0 24 24"><path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m9 5 7 7-7 7"/></svg>
                    <span style="color:var(--gray-700);font-weight:600;">Busca: "<?php echo sanitize($busca); ?>"</span>
                </li>

                <?php elseif ($cat_atual): ?>
                <!-- Categoria -->
                <li style="display:flex;align-items:center;gap:4px;">
                    <svg style="width:12px;height:12px;color:var(--gray-400);flex-shrink:0;" fill="none" viewBox="0 0 24 24"><path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m9 5 7 7-7 7"/></svg>
                    <?php if ($produto_modal): ?>
                    <a href="/?categoria=<?php echo $cat_atual['id']; ?>" style="color:var(--gray-500);text-decoration:none;font-weight:500;transition:color 0.15s;" onmouseover="this.style.color='<?php echo $cor_primaria; ?>'" onmouseout="this.style.color='var(--gray-500)'"><?php echo sanitize($cat_atual['nome']); ?></a>
                    <?php else: ?>
                    <span style="color:var(--gray-700);font-weight:600;"><?php echo sanitize($cat_atual['nome']); ?></span>
                    <?php endif; ?>
                </li>
                <?php endif; ?>

                <?php if ($produto_modal): ?>
                <!-- Produto -->
                <li style="display:flex;align-items:center;gap:4px;">
                    <svg style="width:12px;height:12px;color:var(--gray-400);flex-shrink:0;" fill="none" viewBox="0 0 24 24"><path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m9 5 7 7-7 7"/></svg>
                    <span style="color:var(--gray-700);font-weight:600;"><?php echo sanitize($produto_modal['nome']); ?></span>
                </li>
                <?php endif; ?>
            </ol>

            <!-- Schema.org BreadcrumbList para SEO -->
            <script type="application/ld+json">
            {
              "@context": "https://schema.org",
              "@type": "BreadcrumbList",
              "itemListElement": [
                {
                  "@type": "ListItem",
                  "position": 1,
                  "name": "Início",
                  "item": "<?php echo (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS']==='on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST']; ?>/"
                }
                <?php if ($cat_atual): ?>,
                {
                  "@type": "ListItem",
                  "position": 2,
                  "name": "<?php echo sanitize($cat_atual['nome']); ?>",
                  "item": "<?php echo (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS']==='on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST']; ?>/?categoria=<?php echo $cat_atual['id']; ?>"
                }
                <?php endif; ?>
                <?php if ($produto_modal): ?>,
                {
                  "@type": "ListItem",
                  "position": <?php echo $cat_atual ? 3 : 2; ?>,
                  "name": "<?php echo sanitize($produto_modal['nome']); ?>",
                  "item": "<?php echo (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS']==='on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST']; ?>/?produto_id=<?php echo $produto_modal['id']; ?>"
                }
                <?php endif; ?>
              ]
            }
            </script>
        </nav>
        <?php endif; ?>

        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;flex-wrap:wrap;gap:10px;">
            <div>
                <h1 style="font-size:1.375rem;font-weight:700;color:var(--gray-900);"><?php echo sanitize($titulo_pagina); ?></h1>
                <p style="color:var(--gray-500);font-size:0.875rem;margin-top:2px;"><?php echo $total; ?> produto(s) encontrado(s)</p>
            </div>
            <?php if ($busca || $categoria_id): ?>
            <a href="/" style="color:var(--gray-500);font-size:0.875rem;display:flex;align-items:center;gap:6px;text-decoration:none;padding:7px 12px;border:1px solid var(--gray-200);border-radius:8px;">
                <i class="fas fa-times"></i> Limpar filtro
            </a>
            <?php endif; ?>
        </div>

        <?php if (empty($produtos)): ?>
            <div style="text-align:center;padding:40px 20px;color:var(--gray-400);border:2px dashed var(--gray-200);border-radius:12px;">
                <i class="fas fa-search" style="font-size:3rem;display:block;margin-bottom:16px;"></i>
                <h3 style="font-size:1.25rem;margin-bottom:8px;">Nenhum produto encontrado</h3>
            </div>
        <?php else: ?>
            <div id="gridProdutos" class="grid-produtos" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:20px;">
                <?php foreach ($produtos as $prod): 
                    $preco_final = $prod['preco_promocional'] && $prod['preco_promocional'] > 0 ? (float)$prod['preco_promocional'] : (float)$prod['preco'];
                    $tem_promo = $prod['preco_promocional'] && $prod['preco_promocional'] > 0;
                ?>
                <div style="background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 1px 3px rgba(0,0,0,0.08);transition:all 0.2s;">
                    <a href="/?produto_id=<?php echo $prod['id']; ?>" style="display:block;aspect-ratio:1;overflow:hidden;background:var(--gray-100);position:relative;">
                        <?php if ($prod['imagem_principal']): ?>
                            <img src="<?php echo uploads_url($prod['imagem_principal']); ?>" alt="<?php echo sanitize($prod['nome']); ?>" style="width:100%;height:100%;object-fit:cover;">
                        <?php endif; ?>
                        <?php if ($tem_promo): ?>
                        <span style="position:absolute;top:8px;left:8px;background:#ef4444;color:#fff;font-size:0.7rem;font-weight:700;padding:3px 8px;border-radius:12px;">PROMO</span>
                        <?php endif; ?>
                    </a>
                    <div style="padding:14px;">
                        <h3 style="font-size:0.9rem;font-weight:600;margin-bottom:8px;"><?php echo sanitize($prod['nome']); ?></h3>
                        <?php if ($mostrar_preco && $prod['preco'] > 0): ?>
                            <?php if ($tem_promo): ?>
                            <span style="font-size:0.75rem;text-decoration:line-through;color:var(--gray-400);"><?php echo format_currency((float)$prod['preco']); ?></span>
                            <?php endif; ?>
                            <div style="font-weight:700;color:<?php echo $tem_promo ? '#ef4444' : $cor_primaria; ?>;font-size:<?php echo $tem_promo ? '1rem' : '0.95rem'; ?>;"><?php echo format_currency($preco_final); ?></div>
                        <?php endif; ?>
                        <a href="#" 
                           onclick="event.preventDefault();adicionarAoCarrinho(<?php echo $prod['id']; ?>, '<?php echo addslashes(sanitize($prod['nome'])); ?>', '<?php echo uploads_url($prod['imagem_principal'] ?? ''); ?>')"
                           style="display:flex;align-items:center;justify-content:center;gap:6px;background:<?php echo $cor_primaria; ?>15;color:<?php echo $cor_primaria; ?>;padding:7px;border-radius:8px;text-decoration:none;font-size:0.8rem;font-weight:600;margin-top:8px;border:1px solid <?php echo $cor_primaria; ?>30;transition:all 0.15s;" 
                           onmouseover="this.style.background='<?php echo $cor_primaria; ?>';this.style.color='#fff'" 
                           onmouseout="this.style.background='<?php echo $cor_primaria; ?>15';this.style.color='<?php echo $cor_primaria; ?>'">
                            <i class="fas fa-plus"></i> Adicionar
                        </a>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <div style="margin-top:30px;">
                <?php if ($produtos_navegacao === 'scroll_infinito'): ?>
                <!-- Sentinel para scroll infinito -->
                <div id="scrollSentinel" style="height:40px;display:flex;align-items:center;justify-content:center;">
                    <div id="scrollLoader" style="display:none;">
                        <i class="fas fa-spinner fa-spin" style="font-size:1.5rem;color:var(--primary);"></i>
                    </div>
                    <?php if ($page >= $pagination['total_pages']): ?>
                    <p id="scrollFim" style="color:var(--gray-400);font-size:0.875rem;text-align:center;">Todos os produtos carregados</p>
                    <?php endif; ?>
                </div>
                <input type="hidden" id="scrollPage" value="<?php echo $page; ?>">
                <input type="hidden" id="scrollTotal" value="<?php echo $pagination['total_pages']; ?>">
                <input type="hidden" id="scrollBusca" value="<?php echo sanitize($busca); ?>">
                <input type="hidden" id="scrollCat" value="<?php echo $categoria_id; ?>">
                <?php else: ?>
                <?php echo pagination_links($pagination, '/', array_filter(['busca'=>$busca,'categoria'=>$categoria_id])); ?>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </main>
</div>
<?php endif; // fim if ($mostrar_catalogo) ?>


<!-- ===== MODAL PRODUTO MODERNO (só quando produto_visualizacao = 'modal') ===== -->
<?php if ($produto_modal && $produto_visualizacao === 'modal'): 
    $preco_modal = (float)($produto_modal['preco_promocional'] > 0 ? $produto_modal['preco_promocional'] : $produto_modal['preco']);
    $tem_promo_modal = !empty($produto_modal['preco_promocional']) && $produto_modal['preco_promocional'] > 0;
?>
<div id="modalProduto" class="modal-produto-overlay" onclick="if(event.target===this)window.location='/?categoria=<?php echo $categoria_id; ?>&busca=<?php echo urlencode($busca); ?>'">
    <div class="modal-produto-card" onclick="event.stopPropagation()" style="position:relative;">
        <button onclick="window.location='/?categoria=<?php echo $categoria_id; ?>&busca=<?php echo urlencode($busca); ?>'" class="modal-produto-close" style="position:absolute;top:12px;right:12px;z-index:20;">&times;</button>

        <div class="modal-produto-grid">
            <!-- GALERIA -->
            <div class="modal-produto-galeria">
                <img id="imgPrincipal" 
                     src="<?php echo $produto_modal['imagem_principal'] ? uploads_url($produto_modal['imagem_principal']) : '/assets/images/no-image.svg'; ?>"
                     class="modal-produto-img-principal" 
                     alt="<?php echo sanitize($produto_modal['nome']); ?>">

                <?php if (!empty($produto_imagens_modal) || $produto_modal['imagem_principal']): ?>
                <div class="modal-produto-thumbs">
                    <?php if ($produto_modal['imagem_principal']): ?>
                    <img src="<?php echo uploads_url($produto_modal['imagem_principal']); ?>" 
                         class="modal-produto-thumb active" 
                         onclick="changeMainImage(this, '<?php echo uploads_url($produto_modal['imagem_principal']); ?>')">
                    <?php endif; ?>
                    <?php foreach ($produto_imagens_modal as $img): ?>
                    <img src="<?php echo uploads_url($img['imagem']); ?>" 
                         class="modal-produto-thumb" 
                         onclick="changeMainImage(this, '<?php echo uploads_url($img['imagem']); ?>')">
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>

            <!-- INFORMAÇÕES -->
            <div class="modal-produto-info">
                <?php if ($produto_modal['categoria_nome']): ?>
                <span class="modal-produto-categoria">
                    <i class="fas fa-tag" style="font-size:0.65rem;"></i> <?php echo sanitize($produto_modal['categoria_nome']); ?>
                </span>
                <?php endif; ?>

                <h2 class="modal-produto-titulo"><?php echo sanitize($produto_modal['nome']); ?></h2>

                <?php if ($produto_modal['sku']): ?>
                <p class="modal-produto-sku">SKU: <?php echo sanitize($produto_modal['sku']); ?></p>
                <?php endif; ?>

                <?php if ($mostrar_preco && $produto_modal['preco'] > 0): ?>
                <div class="modal-produto-preco-area">
                    <?php if ($tem_promo_modal): ?>
                    <span class="modal-produto-preco-promo"><?php echo format_currency((float)$produto_modal['preco']); ?></span>
                    <?php endif; ?>
                    <span class="modal-produto-preco"><?php echo format_currency($preco_modal); ?></span>
                    <?php if ($produto_modal['unidade']): ?>
                    <span class="modal-produto-preco-unidade">/ <?php echo sanitize($produto_modal['unidade']); ?></span>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <?php if ($produto_modal['descricao_curta']): ?>
                <p class="modal-produto-descricao-curta"><?php echo sanitize($produto_modal['descricao_curta']); ?></p>
                <?php endif; ?>

                <div class="modal-produto-btns">
                    <a href="#" 
                       onclick="event.preventDefault();adicionarAoCarrinho(<?php echo $produto_modal['id']; ?>, '<?php echo addslashes(sanitize($produto_modal['nome'])); ?>', '<?php echo uploads_url($produto_modal['imagem_principal'] ?? ''); ?>')"
                       class="modal-produto-btn-primary">
                        <i class="fas fa-plus" style="font-size:1.1rem;"></i> Adicionar ao Orçamento
                    </a>
                    <?php if (!empty($whatsapp)): ?>
                    <a href="<?php echo whatsapp_link($whatsapp, "Olá! Tenho interesse no produto: {$produto_modal['nome']}" . ($produto_modal['sku']?" (SKU: {$produto_modal['sku']})":"") . ($mostrar_preco && $produto_modal['preco']>0?" - Valor: " . format_currency((float)$produto_modal['preco']):"") . ". Podem me ajudar?"); ?>" 
                       target="_blank" class="modal-produto-btn-whatsapp">
                        <i class="fab fa-whatsapp" style="font-size:1.25rem;"></i> Perguntar via WhatsApp
                    </a>
                    <?php endif; ?>
                </div>

                <!-- CARACTERÍSTICAS -->
                <?php if (!empty($caracteristicas_modal)): ?>
                <div class="modal-produto-section">
                    <h4 class="modal-produto-section-title">
                        <i class="fas fa-check-circle" style="color: #22c55e;"></i> Características
                    </h4>
                    <div class="modal-produto-caracteristicas">
                        <?php foreach ($caracteristicas_modal as $carac): ?>
                        <div class="modal-produto-caracteristica">
                            <i class="fas fa-check"></i>
                            <span><?php echo sanitize(is_array($carac) ? ($carac['texto'] ?? $carac['nome'] ?? reset($carac)) : $carac); ?></span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- DESCRIÇÃO DETALHADA -->
                <?php if ($produto_modal['descricao']): ?>
                <div class="modal-produto-section">
                    <h4 class="modal-produto-section-title">
                        <i class="fas fa-align-left" style="color: var(--primary);"></i> Descrição Detalhada
                    </h4>
                    <div class="modal-produto-descricao-detalhada">
                        <?php echo nl2br(sanitize($produto_modal['descricao'])); ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- ===== CARROSSEL: produtos da mesma categoria ===== -->
                <?php if (!empty($categoria_carrossel)): ?>
                <div class="modal-produto-section">
                    <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:14px;flex-wrap:wrap;">
                        <h4 class="modal-produto-section-title" style="margin-bottom:0;">
                            <i class="fas fa-th-large" style="color: var(--primary);"></i> Você pode gostar
                        </h4>
                        <div style="display:flex;gap:8px;">
                            <button type="button" class="carousel-btn" id="btnPrevCarousel" onclick="carouselPrev()" aria-label="Anterior">
                                <i class="fas fa-chevron-left"></i>
                            </button>
                            <button type="button" class="carousel-btn" id="btnNextCarousel" onclick="carouselNext()" aria-label="Próximo">
                                <i class="fas fa-chevron-right"></i>
                            </button>
                        </div>
                    </div>

                    <div class="categoria-carousel" id="categoriaCarousel">
                        <div class="categoria-carousel-track" id="categoriaCarouselTrack">
                            <?php foreach ($categoria_carrossel as $cprod):
                                $preco_final_car = (!empty($cprod['preco_promocional']) && (float)$cprod['preco_promocional'] > 0)
                                    ? (float)$cprod['preco_promocional']
                                    : (float)$cprod['preco'];
                                $tem_promo_car = !empty($cprod['preco_promocional']) && (float)$cprod['preco_promocional'] > 0;
                            ?>
                                <div class="categoria-carousel-item">
                                    <a href="/?produto_id=<?php echo (int)$cprod['id']; ?>" style="display:block;text-decoration:none;color:inherit;">
                                        <div style="aspect-ratio:1; background:var(--gray-100); position:relative; overflow:hidden;">
                                            <?php if (!empty($cprod['imagem_principal'])): ?>
                                                <img src="<?php echo uploads_url($cprod['imagem_principal']); ?>" alt="<?php echo sanitize($cprod['nome']); ?>" style="width:100%;height:100%;object-fit:cover;">
                                            <?php endif; ?>
                                            <?php if ($tem_promo_car): ?>
                                                <span style="position:absolute;top:8px;left:8px;background:#ef4444;color:#fff;font-size:0.7rem;font-weight:800;padding:3px 8px;border-radius:12px;">PROMO</span>
                                            <?php endif; ?>
                                        </div>
                                        <div style="padding:12px;">
                                            <h6 style="margin:0;font-size:0.85rem;font-weight:700;color:#111827;line-height:1.3;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;"><?php echo sanitize($cprod['nome']); ?></h6>
                                            <?php if ($mostrar_preco && !empty($cprod['preco'])): ?>
                                                <div style="margin-top:8px;display:flex;align-items:baseline;gap:8px;flex-wrap:wrap;">
                                                    <?php if ($tem_promo_car): ?>
                                                        <span style="text-decoration:line-through;color:var(--gray-400);font-size:0.75rem;"> <?php echo format_currency((float)$cprod['preco']); ?> </span>
                                                    <?php endif; ?>
                                                    <span style="font-weight:900;color:<?php echo $tem_promo_car ? '#ef4444' : $cor_primaria; ?>;"> <?php echo format_currency((float)$preco_final_car); ?> </span>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </a>
                                    <div style="padding:0 12px 12px;">
                                        <a href="#" 
                                           onclick="event.preventDefault();adicionarAoCarrinho(<?php echo (int)$cprod['id']; ?>, '<?php echo addslashes(sanitize($cprod['nome'])); ?>', '<?php echo uploads_url($cprod['imagem_principal'] ?? ''); ?>')"
                                           style="display:flex;align-items:center;justify-content:center;gap:6px;background:<?php echo $cor_primaria; ?>;color:#fff;border:none;border-radius:10px;padding:10px;font-weight:700;font-size:0.8rem;text-decoration:none;cursor:pointer;width:100%;">
                                            <i class="fas fa-plus" style="font-size:0.75rem;"></i> Adicionar
                                        </a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- ===== SEÇÃO EMPRESA - antes do footer ===== -->
<?php
$empresa_sobre   = get_config('empresa_sobre', '');
$empresa_slogan  = get_config('empresa_slogan', '');
try {
    $stmt_emp = db()->prepare("SELECT chave, valor FROM " . table('configuracoes') . " WHERE chave IN ('empresa_sobre','empresa_slogan')");
    $stmt_emp->execute();
    foreach ($stmt_emp->fetchAll() as $r) {
        if ($r['chave'] === 'empresa_sobre' && $r['valor'])  $empresa_sobre  = $r['valor'];
        if ($r['chave'] === 'empresa_slogan' && $r['valor']) $empresa_slogan = $r['valor'];
    }
} catch (Exception $e) {}
?>
<?php if ($empresa_sobre || $telefone || $site_email || !empty($redes ?? [])): ?>
<section style="background:#111827;color:#fff;padding:64px 0;">
    <div class="container">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:60px;align-items:center;">
            <!-- Texto da empresa -->
            <div>
                <p style="color:<?php echo $cor_primaria; ?>;font-size:0.75rem;font-weight:700;letter-spacing:0.12em;text-transform:uppercase;margin-bottom:16px;">QUEM SOMOS</p>
                <h2 style="font-size:clamp(1.75rem,4vw,2.75rem);font-weight:900;line-height:1.15;margin-bottom:24px;"><?php echo sanitize($site_name); ?></h2>
                <?php if ($empresa_sobre): ?>
                <p style="font-size:1rem;line-height:1.75;color:rgba(255,255,255,0.75);margin-bottom:20px;"><?php echo nl2br(sanitize($empresa_sobre)); ?></p>
                <?php else: ?>
                <p style="font-size:1rem;line-height:1.75;color:rgba(255,255,255,0.75);margin-bottom:20px;"><?php echo sanitize($site_description); ?></p>
                <?php endif; ?>
                <?php if ($empresa_slogan): ?>
                <p style="font-style:italic;color:<?php echo $cor_primaria; ?>;font-size:1.1rem;margin-bottom:28px;">"<?php echo sanitize($empresa_slogan); ?>"</p>
                <?php endif; ?>
                <?php if ($whatsapp): ?>
                <a href="<?php echo whatsapp_link($whatsapp); ?>" target="_blank"
                   style="display:inline-flex;align-items:center;gap:10px;background:<?php echo $cor_primaria; ?>;color:#fff;padding:13px 28px;border-radius:8px;font-weight:700;text-decoration:none;font-size:0.9375rem;transition:all 0.2s;"
                   onmouseover="this.style.opacity='0.88'" onmouseout="this.style.opacity='1'">
                    <i class="fab fa-whatsapp" style="font-size:1.2rem;"></i> Fale conosco
                </a>
                <?php endif; ?>
            </div>
            <!-- Contato + Redes -->
            <div>
                <?php if ($telefone || $site_email || $endereco || $whatsapp || $horario): ?>
                <div style="background:rgba(255,255,255,0.06);border:1px solid rgba(255,255,255,0.1);border-radius:16px;padding:28px;margin-bottom:24px;">
                    <h4 style="font-size:1rem;font-weight:700;margin-bottom:18px;color:#fff;">📞 Dados de Contato</h4>
                    <?php if ($telefone): ?>
                    <div style="display:flex;align-items:center;gap:12px;margin-bottom:12px;font-size:0.9rem;color:rgba(255,255,255,0.8);">
                        <span style="width:36px;height:36px;background:<?php echo $cor_primaria; ?>22;border-radius:8px;display:flex;align-items:center;justify-content:center;flex-shrink:0;"><i class="fas fa-phone" style="color:<?php echo $cor_primaria; ?>;font-size:0.875rem;"></i></span>
                        <?php echo sanitize($telefone); ?>
                    </div>
                    <?php endif; ?>
                    <?php if ($whatsapp): ?>
                    <div style="display:flex;align-items:center;gap:12px;margin-bottom:12px;font-size:0.9rem;color:rgba(255,255,255,0.8);">
                        <span style="width:36px;height:36px;background:#25d36622;border-radius:8px;display:flex;align-items:center;justify-content:center;flex-shrink:0;"><i class="fab fa-whatsapp" style="color:#25d366;font-size:1rem;"></i></span>
                        <?php echo format_phone($whatsapp); ?>
                    </div>
                    <?php endif; ?>
                    <?php if ($site_email): ?>
                    <div style="display:flex;align-items:center;gap:12px;margin-bottom:12px;font-size:0.9rem;color:rgba(255,255,255,0.8);">
                        <span style="width:36px;height:36px;background:<?php echo $cor_primaria; ?>22;border-radius:8px;display:flex;align-items:center;justify-content:center;flex-shrink:0;"><i class="fas fa-envelope" style="color:<?php echo $cor_primaria; ?>;font-size:0.875rem;"></i></span>
                        <?php echo sanitize($site_email); ?>
                    </div>
                    <?php endif; ?>
                    <?php if ($endereco): ?>
                    <div style="display:flex;align-items:center;gap:12px;margin-bottom:12px;font-size:0.9rem;color:rgba(255,255,255,0.8);">
                        <span style="width:36px;height:36px;background:<?php echo $cor_primaria; ?>22;border-radius:8px;display:flex;align-items:center;justify-content:center;flex-shrink:0;"><i class="fas fa-map-marker-alt" style="color:<?php echo $cor_primaria; ?>;font-size:0.875rem;"></i></span>
                        <?php echo sanitize($endereco); ?>
                    </div>
                    <?php endif; ?>
                    <?php if ($horario): ?>
                    <div style="display:flex;align-items:center;gap:12px;font-size:0.9rem;color:rgba(255,255,255,0.8);">
                        <span style="width:36px;height:36px;background:<?php echo $cor_primaria; ?>22;border-radius:8px;display:flex;align-items:center;justify-content:center;flex-shrink:0;"><i class="fas fa-clock" style="color:<?php echo $cor_primaria; ?>;font-size:0.875rem;"></i></span>
                        <?php echo sanitize($horario); ?>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
                <?php
                $redes = array_filter([
                    'facebook' => $facebook_url, 'instagram' => $instagram_url,
                    'linkedin' => $linkedin_url,  'youtube'   => $youtube_url,
                    'tiktok'   => $tiktok_url,    'twitter'   => $twitter_url,
                    'telegram' => $telegram_url,  'pinterest' => $pinterest_url,
                    'kwai'     => $kwai_url,       'threads'   => $threads_url,
                ]);
                $rede_icons = [
                    'facebook'=>['fab fa-facebook','#1877f2'],'instagram'=>['fab fa-instagram','#e4405f'],
                    'linkedin'=>['fab fa-linkedin','#0a66c2'],'youtube'  =>['fab fa-youtube','#ff0000'],
                    'tiktok'  =>['fab fa-tiktok','#000'],'twitter'   =>['fab fa-x-twitter','#000'],
                    'telegram'=>['fab fa-telegram','#24a1de'],'pinterest'=>['fab fa-pinterest','#e60023'],
                    'kwai'    =>['fas fa-play-circle','#ff6a00'],'threads'=>['fab fa-threads','#000'],
                ];
                ?>
                <?php if (!empty($redes)): ?>
                <div>
                    <h4 style="font-size:0.875rem;font-weight:700;margin-bottom:12px;color:rgba(255,255,255,0.6);text-transform:uppercase;letter-spacing:0.08em;">Redes Sociais</h4>
                    <div style="display:flex;gap:10px;flex-wrap:wrap;">
                        <?php foreach ($redes as $rede => $url): 
                            [$ico, $cor_rede] = $rede_icons[$rede] ?? ['fas fa-link','#fff'];
                        ?>
                        <a href="<?php echo sanitize($url); ?>" target="_blank"
                           style="width:42px;height:42px;border-radius:10px;background:rgba(255,255,255,0.1);display:flex;align-items:center;justify-content:center;font-size:1.2rem;color:#fff;text-decoration:none;transition:all 0.2s;border:1px solid rgba(255,255,255,0.1);"
                           title="<?php echo ucfirst($rede); ?>"
                           onmouseover="this.style.background='<?php echo $cor_rede; ?>';this.style.borderColor='<?php echo $cor_rede; ?>'"
                           onmouseout="this.style.background='rgba(255,255,255,0.1)';this.style.borderColor='rgba(255,255,255,0.1)'">
                            <i class="<?php echo $ico; ?>"></i>
                        </a>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- Footer -->
<footer style="background:var(--gray-900);color:rgba(255,255,255,0.6);padding:48px 0 0;">
    <div class="container">
        <div style="display:grid;grid-template-columns:1.4fr 2fr 1fr;gap:48px;padding-bottom:40px;border-bottom:1px solid rgba(255,255,255,0.1);">

            <!-- Logo + Descrição -->
            <div>
                <?php if ($logo_cliente && in_array($navbar_tipo, ['imagem','imagem_texto'])): ?>
                <img src="<?php echo uploads_url($logo_cliente); ?>" alt="<?php echo sanitize($site_name); ?>" style="max-height:48px;object-fit:contain;margin-bottom:16px;display:block;filter:brightness(0) invert(1);opacity:0.9;">
                <?php else: ?>
                <h4 style="color:#fff;font-weight:800;font-size:1.2rem;margin-bottom:14px;"><?php echo sanitize($site_name); ?></h4>
                <?php endif; ?>
                <p style="font-size:0.85rem;line-height:1.75;max-width:240px;"><?php echo sanitize($site_description); ?></p>
            </div>

            <!-- Categorias em colunas (estilo Globo) -->
            <?php if (!empty($categorias)): 
                $cats_footer = array_slice($categorias, 0, 12);
                $metade = (int)ceil(count($cats_footer) / 2);
                $col1 = array_slice($cats_footer, 0, $metade);
                $col2 = array_slice($cats_footer, $metade);
            ?>
            <div>
                <h4 style="color:#fff;font-weight:700;font-size:0.875rem;text-transform:uppercase;letter-spacing:0.08em;margin-bottom:18px;">Categorias</h4>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:0 24px;">
                    <div style="display:flex;flex-direction:column;gap:9px;">
                        <a href="/" style="color:rgba(255,255,255,0.6);font-size:0.85rem;text-decoration:none;transition:color 0.15s;" onmouseover="this.style.color='#fff'" onmouseout="this.style.color='rgba(255,255,255,0.6)'">Todos</a>
                        <?php foreach ($col1 as $cat): ?>
                        <a href="/?categoria=<?php echo $cat['id']; ?>" style="color:rgba(255,255,255,0.6);font-size:0.85rem;text-decoration:none;transition:color 0.15s;" onmouseover="this.style.color='#fff'" onmouseout="this.style.color='rgba(255,255,255,0.6)'"><?php echo sanitize($cat['nome']); ?></a>
                        <?php endforeach; ?>
                    </div>
                    <div style="display:flex;flex-direction:column;gap:9px;">
                        <?php foreach ($col2 as $cat): ?>
                        <a href="/?categoria=<?php echo $cat['id']; ?>" style="color:rgba(255,255,255,0.6);font-size:0.85rem;text-decoration:none;transition:color 0.15s;" onmouseover="this.style.color='#fff'" onmouseout="this.style.color='rgba(255,255,255,0.6)'"><?php echo sanitize($cat['nome']); ?></a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Links Úteis -->
            <div>
                <h4 style="color:#fff;font-weight:700;font-size:0.875rem;text-transform:uppercase;letter-spacing:0.08em;margin-bottom:18px;">Links Úteis</h4>
                <div style="display:flex;flex-direction:column;gap:9px;">
                    <a href="/cliente/login.php" style="color:rgba(255,255,255,0.6);font-size:0.85rem;text-decoration:none;transition:color 0.15s;" onmouseover="this.style.color='#fff'" onmouseout="this.style.color='rgba(255,255,255,0.6)'">Área do Cliente</a>
                    <a href="/cliente/cadastro.php" style="color:rgba(255,255,255,0.6);font-size:0.85rem;text-decoration:none;transition:color 0.15s;" onmouseover="this.style.color='#fff'" onmouseout="this.style.color='rgba(255,255,255,0.6)'">Criar Conta</a>
                    <a href="/cliente/pedidos.php" style="color:rgba(255,255,255,0.6);font-size:0.85rem;text-decoration:none;transition:color 0.15s;" onmouseover="this.style.color='#fff'" onmouseout="this.style.color='rgba(255,255,255,0.6)'">Meus Pedidos</a>
                </div>
            </div>
        </div>

        <!-- Barra inferior -->
        <div style="padding:20px 0;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;">
            <p style="font-size:0.8rem;">
                &copy; <?php echo date('Y'); ?> <?php echo sanitize($site_name); ?> — Todos os direitos reservados
            </p>
            <?php if (!empty($redes)): ?>
            <div style="display:flex;gap:14px;">
                <?php if ($facebook_url):  ?><a href="<?php echo sanitize($facebook_url); ?>"  target="_blank" style="color:rgba(255,255,255,0.5);font-size:1.1rem;transition:color 0.15s;" onmouseover="this.style.color='#fff'" onmouseout="this.style.color='rgba(255,255,255,0.5)'"><i class="fab fa-facebook"></i></a><?php endif; ?>
                <?php if ($instagram_url): ?><a href="<?php echo sanitize($instagram_url); ?>" target="_blank" style="color:rgba(255,255,255,0.5);font-size:1.1rem;transition:color 0.15s;" onmouseover="this.style.color='#fff'" onmouseout="this.style.color='rgba(255,255,255,0.5)'"><i class="fab fa-instagram"></i></a><?php endif; ?>
                <?php if ($tiktok_url):    ?><a href="<?php echo sanitize($tiktok_url); ?>"    target="_blank" style="color:rgba(255,255,255,0.5);font-size:1.1rem;transition:color 0.15s;" onmouseover="this.style.color='#fff'" onmouseout="this.style.color='rgba(255,255,255,0.5)'"><i class="fab fa-tiktok"></i></a><?php endif; ?>
                <?php if ($youtube_url):   ?><a href="<?php echo sanitize($youtube_url); ?>"   target="_blank" style="color:rgba(255,255,255,0.5);font-size:1.1rem;transition:color 0.15s;" onmouseover="this.style.color='#fff'" onmouseout="this.style.color='rgba(255,255,255,0.5)'"><i class="fab fa-youtube"></i></a><?php endif; ?>
                <?php if ($linkedin_url):  ?><a href="<?php echo sanitize($linkedin_url); ?>"  target="_blank" style="color:rgba(255,255,255,0.5);font-size:1.1rem;transition:color 0.15s;" onmouseover="this.style.color='#fff'" onmouseout="this.style.color='rgba(255,255,255,0.5)'"><i class="fab fa-linkedin"></i></a><?php endif; ?>
                <?php if ($telegram_url):  ?><a href="<?php echo sanitize($telegram_url); ?>"  target="_blank" style="color:rgba(255,255,255,0.5);font-size:1.1rem;transition:color 0.15s;" onmouseover="this.style.color='#fff'" onmouseout="this.style.color='rgba(255,255,255,0.5)'"><i class="fab fa-telegram"></i></a><?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</footer>

<!-- Alerta sonoro para novos orçamentos (admin logado) -->
<?php 
$alerta_sonoro = get_config('alerta_sonoro_orcamento', '1') === '1';
if ($alerta_sonoro && is_logged_in()):
    try {
        $orc_novos = (int)db()->query("SELECT COUNT(*) FROM " . table('orcamentos') . " WHERE status = 'novo' AND created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)")->fetchColumn();
    } catch (Exception $e) { $orc_novos = 0; }
?>
<script>
(function() {
    let ultimoCount = <?php echo $orc_novos; ?>;

    function tocarSom() {
        try {
            const ctx = new (window.AudioContext || window.webkitAudioContext)();
            const osc = ctx.createOscillator();
            const gain = ctx.createGain();
            osc.connect(gain);
            gain.connect(ctx.destination);
            osc.type = 'sine';
            osc.frequency.setValueAtTime(880, ctx.currentTime);
            osc.frequency.setValueAtTime(660, ctx.currentTime + 0.1);
            osc.frequency.setValueAtTime(880, ctx.currentTime + 0.2);
            gain.gain.setValueAtTime(0.3, ctx.currentTime);
            gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.4);
            osc.start(ctx.currentTime);
            osc.stop(ctx.currentTime + 0.4);
        } catch(e) {}
    }

    function checarOrcamentos() {
        fetch('/api/check_orcamentos.php')
        .then(r => r.json())
        .then(d => {
            if (d.count > ultimoCount) {
                tocarSom();
                const n = d.count - ultimoCount;
                if (window.Notification && Notification.permission === 'granted') {
                    new Notification('📦 Novo Orçamento!', { body: n + ' novo(s) orçamento(s) recebido(s)!' });
                }
                ultimoCount = d.count;
            }
        }).catch(()=>{});
    }

    if (window.Notification && Notification.permission === 'default') {
        Notification.requestPermission();
    }

    setInterval(checarOrcamentos, 30000);
})();
</script>
<?php endif; ?>

<script>
// ===== CARRINHO via AJAX (sem reload da página) =====
function ajaxCart(op, pid) {
    const formData = new FormData();
    formData.append('action', 'ajax_cart');
    formData.append('op', op);
    formData.append('pid', pid);

    fetch('/', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(d => {
            if (!d.ok) return;
            atualizarCarrinhoUI(d);
        })
        .catch(() => {});
}

function adicionarAoCarrinho(pid, nomeProduto, imagemProduto) {
    const formData = new FormData();
    formData.append('action', 'ajax_cart');
    formData.append('op', 'add');
    formData.append('pid', pid);

    fetch('/', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(d => {
            if (!d.ok) return;
            atualizarCarrinhoUI(d);
            showToast(nomeProduto, imagemProduto);
        })
        .catch(() => {
            alert('Erro de conexão. Tente novamente.');
        });
}

function atualizarCarrinhoUI(d) {
    const conteudo = document.getElementById('conteudoCarrinho');
    if (conteudo) conteudo.innerHTML = d.painel_html;

    const badge = document.getElementById('badge-carrinho');
    if (badge) {
        if (d.count > 0) {
            badge.textContent = d.count;
            badge.style.display = '';
        } else {
            badge.style.display = 'none';
        }
    }
}

// ===== TOAST FUNCTION - POSIÇÃO CONFIGURÁVEL =====
function showToast(nomeProduto, imagemProduto) {
    const container = document.getElementById('toastContainer');
    if (!container) return;

    const toast = document.createElement('div');
    toast.className = 'toast-item success';

    const imgHtml = imagemProduto ?
        `<img src="${imagemProduto}" style="width:40px;height:40px;object-fit:cover;border-radius:8px;flex-shrink:0;">` :
        `<div style="width:40px;height:40px;border-radius:8px;background:var(--primary)20;display:flex;align-items:center;justify-content:center;flex-shrink:0;"><i class="fas fa-shopping-bag" style="color:var(--primary);"></i></div>`;

    toast.innerHTML = `
        ${imgHtml}
        <div class="toast-content">
            <div class="toast-title">Produto adicionado!</div>
            <div class="toast-message">${nomeProduto}</div>
        </div>
        <button class="toast-close" onclick="fecharToast(this.parentElement)">
            <i class="fas fa-times"></i>
        </button>
    `;

    container.appendChild(toast);
    toast.offsetHeight; // force reflow
    toast.classList.add('show');

    setTimeout(() => fecharToast(toast), 3000);
}

function fecharToast(toast) {
    if (!toast || !toast.parentElement) return;
    toast.classList.remove('show');
    setTimeout(() => { if (toast.parentElement) toast.remove(); }, 400);
}

// ===== DRAWER DE CATEGORIAS (MOBILE) =====
function abrirCatDrawer() {
    document.getElementById('catDrawer').classList.add('open');
    document.getElementById('catDrawerOverlay').classList.add('open');
    document.body.style.overflow = 'hidden';
}
function fecharCatDrawer() {
    document.getElementById('catDrawer').classList.remove('open');
    document.getElementById('catDrawerOverlay').classList.remove('open');
    document.body.style.overflow = '';
}

// ===== SCROLL INFINITO =====
<?php if ($produtos_navegacao === 'scroll_infinito'): ?>
(function() {
    let carregando = false;
    const corPrimaria = '<?php echo $cor_primaria; ?>';
    const mostrarPreco = <?php echo $mostrar_preco ? 'true' : 'false'; ?>;

    function criarCardProduto(p) {
        const div = document.createElement('div');
        div.style.cssText = 'background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 1px 3px rgba(0,0,0,0.08);transition:all 0.2s;';

        const promoTag = p.tem_promo ? `<span style="position:absolute;top:8px;left:8px;background:#ef4444;color:#fff;font-size:0.7rem;font-weight:700;padding:3px 8px;border-radius:12px;">PROMO</span>` : '';
        const imgHtml  = p.imagem ? `<img src="${p.imagem}" alt="${p.nome}" style="width:100%;height:100%;object-fit:cover;">` : '';
        const precoOld = (p.show_preco && p.tem_promo) ? `<span style="font-size:0.75rem;text-decoration:line-through;color:#9ca3af;">${p.preco}</span>` : '';
        const precoDiv = p.show_preco ? `${precoOld}<div style="font-weight:700;color:${p.tem_promo?'#ef4444':corPrimaria};font-size:${p.tem_promo?'1rem':'0.95rem'};">${p.preco_final}</div>` : '';

        div.innerHTML = `
            <a href="/?produto_id=${p.id}" style="display:block;aspect-ratio:1;overflow:hidden;background:var(--gray-100);position:relative;">
                ${imgHtml}${promoTag}
            </a>
            <div style="padding:14px;">
                <h3 style="font-size:0.9rem;font-weight:600;margin-bottom:8px;">${p.nome}</h3>
                ${precoDiv}
                <a href="#"
                   onclick="event.preventDefault();adicionarAoCarrinho(${p.id},'${p.nome.replace(/'/g,"\\'")}','${p.imagem}')"
                   style="display:flex;align-items:center;justify-content:center;gap:6px;background:${corPrimaria}15;color:${corPrimaria};padding:7px;border-radius:8px;text-decoration:none;font-size:0.8rem;font-weight:600;margin-top:8px;border:1px solid ${corPrimaria}30;transition:all 0.15s;"
                   onmouseover="this.style.background='${corPrimaria}';this.style.color='#fff'"
                   onmouseout="this.style.background='${corPrimaria}15';this.style.color='${corPrimaria}'">
                    <i class="fas fa-plus"></i> Adicionar
                </a>
            </div>`;
        return div;
    }

    function carregarMaisProdutos() {
        const pageEl  = document.getElementById('scrollPage');
        const totalEl = document.getElementById('scrollTotal');
        const loader  = document.getElementById('scrollLoader');
        const grid    = document.getElementById('gridProdutos');
        const fimEl   = document.getElementById('scrollFim');

        if (!pageEl || !grid) return;
        const page  = parseInt(pageEl.value);
        const total = parseInt(totalEl.value);
        if (page >= total || carregando) return;

        carregando = true;
        if (loader) loader.style.display = 'block';

        const nextPage = page + 1;
        const params   = new URLSearchParams(window.location.search);
        params.set('page', nextPage);
        params.set('ajax_produtos', '1');

        fetch('/?' + params.toString())
            .then(r => r.json())
            .then(d => {
                if (!d.ok || !d.items) return;
                d.items.forEach(p => grid.appendChild(criarCardProduto(p)));
                pageEl.value = nextPage;
                if (nextPage >= total && fimEl) fimEl.style.display = 'block';
            })
            .catch(() => {})
            .finally(() => {
                carregando = false;
                if (loader) loader.style.display = 'none';
            });
    }

    const sentinel = document.getElementById('scrollSentinel');
    if (sentinel && 'IntersectionObserver' in window) {
        const observer = new IntersectionObserver(entries => {
            if (entries[0].isIntersecting) carregarMaisProdutos();
        }, { rootMargin: '300px' });
        observer.observe(sentinel);
    }
})();
<?php endif; ?>

// Carrinho toggle
function toggleCarrinho() {
    const painel = document.getElementById('painelCarrinho');
    const overlay = document.getElementById('overlayCarrinho');
    const aberto = painel.style.display === 'flex';
    painel.style.display = aberto ? 'none' : 'flex';
    overlay.style.display = aberto ? 'none' : 'block';
    document.body.style.overflow = aberto ? '' : 'hidden';
}

// Modal orçamento
function abrirModalOrcamento() {
    document.getElementById('modalOrcamento').style.display = 'flex';
    document.getElementById('orcStep1').style.display = 'block';
    document.getElementById('orcStep2').style.display = 'none';
    document.getElementById('orcLoading').style.display = 'none';
}

function fecharModalOrcamento() {
    document.getElementById('modalOrcamento').style.display = 'none';
}

function fecharELimpar() {
    fecharModalOrcamento();
    toggleCarrinho();
}

function registrarOrcamento() {
    const nome = document.getElementById('orcNome').value.trim();
    if (!nome) { alert('Por favor, informe seu nome!'); return; }

    document.getElementById('orcStep1').style.display = 'none';
    document.getElementById('orcLoading').style.display = 'block';

    const formData = new FormData();
    formData.append('action', 'salvar_orcamento');
    formData.append('cliente_nome', nome);
    formData.append('cliente_tel', document.getElementById('orcTel').value.trim());

    fetch('/', { method: 'POST', body: formData })
    .then(r => r.json())
    .then(d => {
        document.getElementById('orcLoading').style.display = 'none';
        if (d.ok) {
            document.getElementById('orcCodigo').textContent = d.codigo;
            if (d.whatsapp_link) {
                document.getElementById('btnEnviarWpp').href = d.whatsapp_link;
                document.getElementById('btnEnviarWpp').style.display = 'flex';
            } else {
                const btnWpp = document.getElementById('btnEnviarWpp');
                if (btnWpp) btnWpp.style.display = 'none';
            }
            document.getElementById('orcStep2').style.display = 'block';
        } else {
            document.getElementById('orcStep1').style.display = 'block';
            alert('Erro: ' + (d.msg || 'Tente novamente'));
        }
    })
    .catch(()=>{
        document.getElementById('orcLoading').style.display = 'none';
        document.getElementById('orcStep1').style.display = 'block';
        alert('Erro de conexão. Tente novamente.');
    });
}

// Troca imagem principal do modal
function changeMainImage(thumb, src) {
    document.getElementById('imgPrincipal').src = src;
    document.querySelectorAll('.modal-produto-thumb').forEach(t => t.classList.remove('active'));
    thumb.classList.add('active');
}

// ===== CARROSSEL DA CATEGORIA - CORRIGIDO =====
(function(){
    const track = document.getElementById('categoriaCarouselTrack');
    const container = document.getElementById('categoriaCarousel');
    const btnPrev = document.getElementById('btnPrevCarousel');
    const btnNext = document.getElementById('btnNextCarousel');

    if (!track || !container) return;

    let currentIndex = 0;
    let itemsPerPage = 4;

    function getItemsPerPage() {
        return window.innerWidth <= 768 ? 2 : 4;
    }

    function getTotalItems() {
        return track.children.length;
    }

    function getMaxIndex() {
        const total = getTotalItems();
        const perPage = getItemsPerPage();
        return Math.max(0, total - perPage);
    }

    function updateCarousel() {
        itemsPerPage = getItemsPerPage();
        const total = getTotalItems();
        const maxIndex = getMaxIndex();

        // Garantir que currentIndex não ultrapasse o máximo
        if (currentIndex > maxIndex) currentIndex = maxIndex;
        if (currentIndex < 0) currentIndex = 0;

        // Calcular largura de cada item + gap
        const item = track.children[0];
        if (!item) return;

        const containerWidth = container.offsetWidth;
        const gap = 14;
        const itemWidth = (containerWidth - (gap * (itemsPerPage - 1))) / itemsPerPage;

        const offset = currentIndex * (itemWidth + gap);
        track.style.transform = `translateX(-${offset}px)`;

        // Atualizar estado dos botões
        if (btnPrev) btnPrev.disabled = currentIndex <= 0;
        if (btnNext) btnNext.disabled = currentIndex >= maxIndex;
    }

    window.carouselPrev = function() {
        currentIndex = Math.max(0, currentIndex - itemsPerPage);
        updateCarousel();
    };

    window.carouselNext = function() {
        const maxIndex = getMaxIndex();
        currentIndex = Math.min(maxIndex, currentIndex + itemsPerPage);
        updateCarousel();
    };

    window.addEventListener('resize', updateCarousel);

    // Inicializar após um pequeno delay para garantir que o DOM está pronto
    setTimeout(updateCarousel, 100);
})();

// Fechar modal produto ao clicar fora
<?php if ($produto_modal): ?>
document.getElementById('modalProduto').addEventListener('click', function(e) {
    if (e.target === this) window.location = '/?categoria=<?php echo $categoria_id; ?>&busca=<?php echo urlencode($busca); ?>';
});
<?php endif; ?>

// ===== USER MENU =====
function toggleUserMenu() {
    const d = document.getElementById('userMenuDropdown');
    d.style.display = d.style.display === 'none' ? 'block' : 'none';
}
document.addEventListener('click', function(e) {
    if (!e.target.closest('.user-menu-wrap')) {
        const d = document.getElementById('userMenuDropdown');
        if (d) d.style.display = 'none';
    }
});

// ===== LIVE SEARCH NAVBAR =====
(function() {
    const input = document.getElementById('buscaNavInput');
    const resultados = document.getElementById('buscaNavResultados');
    const spinner = document.getElementById('buscaNavSpinner');
    if (!input || !resultados) return;

    let timer;

    function escHtml(s) {
        return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }
    function toBRL(n) {
        return 'R$ ' + parseFloat(n).toFixed(2).replace('.',',').replace(/\B(?=(\d{3})+(?!\d))/g,'.');
    }

    input.addEventListener('input', function() {
        clearTimeout(timer);
        const t = this.value.trim();
        if (t.length < 1) { resultados.style.display = 'none'; return; }
        spinner.style.display = 'block';
        timer = setTimeout(() => {
            fetch('/?ajax_produtos=1&busca=' + encodeURIComponent(t) + '&page=1')
            .then(r => r.json())
            .then(data => {
                spinner.style.display = 'none';
                if (!data.ok || !data.items || data.items.length === 0) {
                    resultados.innerHTML = '<div style="padding:14px;color:var(--gray-400);font-size:0.875rem;text-align:center;"><i class="fas fa-search" style="margin-right:6px;"></i>Nenhum produto encontrado</div>';
                    resultados.style.display = 'block';
                    return;
                }
                const html = data.items.slice(0, 8).map(p => {
                    const imgSrc = p.imagem ? escHtml(p.imagem) : '/assets/images/no-image.svg';
                    const preco = p.show_preco ? `<span class="busca-nav-item-preco">${escHtml(p.preco_final)}</span>` : '';
                    const promo = p.tem_promo && p.show_preco ? `<span style="font-size:0.7rem;text-decoration:line-through;color:var(--gray-400);margin-left:4px;">${escHtml(p.preco)}</span>` : '';
                    return `<div class="busca-nav-item" onclick="window.location='/?produto_id=${p.id}'">
                        <img src="${imgSrc}" onerror="this.src='/assets/images/no-image.svg'" alt="">
                        <div class="busca-nav-item-info">
                            <strong>${escHtml(p.nome)}</strong>
                        </div>
                        <div style="display:flex;align-items:center;gap:4px;">${preco}${promo}</div>
                    </div>`;
                }).join('');
                const verTodos = `<a href="/?busca=${encodeURIComponent(t)}" class="busca-nav-ver-todos"><i class="fas fa-search" style="margin-right:4px;"></i>Ver todos os resultados para "${escHtml(t)}"</a>`;
                resultados.innerHTML = html + verTodos;
                resultados.style.display = 'block';
            })
            .catch(() => { spinner.style.display = 'none'; resultados.style.display = 'none'; });
        }, 250);
    });

    // Enter navega para busca completa
    input.addEventListener('keydown', function(e) {
        if (e.key === 'Enter') {
            window.location = '/?busca=' + encodeURIComponent(this.value.trim());
        }
    });

    // Fechar ao clicar fora
    document.addEventListener('click', function(e) {
        if (!e.target.closest('.busca-wrap-nav')) {
            resultados.style.display = 'none';
        }
    });
})();

// Esconder carrinho ao pressionar ESC
document.addEventListener('keydown', e => { 
    if (e.key === 'Escape') { 
        const painel = document.getElementById('painelCarrinho');
        if (painel && painel.style.display === 'flex') toggleCarrinho();
        fecharModalOrcamento(); 
    } 
});

// ===== BANNER SLIDESHOW =====
(function(){
    const track  = document.getElementById('bannerTrack');
    if (!track || track.children.length <= 1) return;

    const total  = track.children.length;
    let current  = 0;
    let timer    = null;

    function goTo(n) {
        current = (n + total) % total;
        track.style.transform = `translateX(-${current * 100}%)`;
        // Atualizar bolinhas
        document.querySelectorAll('[id^="dot-"]').forEach((dot, i) => {
            const ativo = i === current;
            dot.style.width  = ativo ? '24px' : '8px';
            dot.style.background = ativo ? '#fff' : 'rgba(255,255,255,0.45)';
        });
    }

    window.bannerNext = () => { goTo(current + 1); resetTimer(); };
    window.bannerPrev = () => { goTo(current - 1); resetTimer(); };
    window.bannerGoTo = (n) => { goTo(n); resetTimer(); };

    function resetTimer() {
        clearInterval(timer);
        timer = setInterval(() => goTo(current + 1), 5000);
    }

    // Parar no hover
    const slider = document.getElementById('bannerSlider');
    if (slider) {
        slider.addEventListener('mouseenter', () => clearInterval(timer));
        slider.addEventListener('mouseleave', resetTimer);
    }

    // Suporte a swipe no mobile
    let touchStartX = 0;
    track.addEventListener('touchstart', e => { touchStartX = e.touches[0].clientX; }, {passive:true});
    track.addEventListener('touchend',   e => {
        const diff = touchStartX - e.changedTouches[0].clientX;
        if (Math.abs(diff) > 50) diff > 0 ? bannerNext() : bannerPrev();
    }, {passive:true});

    resetTimer();
})();
</script>

<!-- ===== SCRIPTS PERSONALIZADOS antes de </body> (vindo da página SEO) ===== -->
<?php echo $custom_body_scripts; ?>

<!-- ===== BANNERS POPUP ===== -->
<?php foreach ($banners_popup as $bp):
    $bp_id    = 'banner_popup_' . $bp['id'];
    $bp_delay = (int)($bp['popup_delay'] ?? 0) * 1000;
    $bp_fora  = in_array($bp['popup_fechar'] ?? 'botao', ['fora','ambos']) ? 'true' : 'false';
    $bp_botao = in_array($bp['popup_fechar'] ?? 'botao', ['botao','ambos']) ? 'true' : 'false';
?>
<div id="<?php echo $bp_id; ?>"
     style="display:none;position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,.6);align-items:center;justify-content:center;padding:20px;">
    <div style="position:relative;max-width:600px;width:100%;border-radius:14px;overflow:hidden;box-shadow:0 20px 60px rgba(0,0,0,.4);animation:modalFadeIn .3s ease;">
        <?php if ($bp_botao === 'true'): ?>
        <button onclick="fecharBannerPopup('<?php echo $bp_id; ?>')"
                style="position:absolute;top:10px;right:12px;background:rgba(0,0,0,.55);color:#fff;border:none;border-radius:50%;width:32px;height:32px;font-size:1.2rem;cursor:pointer;z-index:1;line-height:1;display:flex;align-items:center;justify-content:center;">&times;</button>
        <?php endif; ?>
        <?php if ($bp['link']): ?><a href="<?php echo sanitize($bp['link']); ?>"><?php endif; ?>
        <img src="<?php echo uploads_url($bp['imagem']); ?>"
             alt="<?php echo sanitize($bp['titulo']??''); ?>"
             style="width:100%;display:block;">
        <?php if ($bp['link']): ?></a><?php endif; ?>
        <?php if (!empty($bp['titulo']) || !empty($bp['subtitulo'])): ?>
        <div style="padding:16px 20px;background:#fff;">
            <?php if (!empty($bp['titulo'])): ?><h3 style="margin:0 0 4px;font-size:1.05rem;font-weight:700;"><?php echo sanitize($bp['titulo']); ?></h3><?php endif; ?>
            <?php if (!empty($bp['subtitulo'])): ?><p style="margin:0;color:var(--gray-500);font-size:.875rem;"><?php echo sanitize($bp['subtitulo']); ?></p><?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
</div>
<script>
(function(){
    var pid = <?php echo json_encode($bp_id); ?>;
    var fecharFora = <?php echo $bp_fora; ?>;
    setTimeout(function(){
        var el = document.getElementById(pid);
        if (!el) return;
        el.style.display = 'flex';
        if (fecharFora) {
            el.addEventListener('click', function(e){ if (e.target === el) fecharBannerPopup(pid); });
        }
    }, <?php echo $bp_delay; ?>);
})();
</script>
<?php endforeach; ?>
<?php if (!empty($banners_popup)): ?>
<script>
function fecharBannerPopup(pid) {
    var el = document.getElementById(pid);
    if (el) el.style.display = 'none';
}
</script>
<?php endif; ?>

</body>
</html>