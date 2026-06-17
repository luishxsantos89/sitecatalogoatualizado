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

$carrinho = $_SESSION['carrinho'] ?? [];
$total_carrinho = array_sum(array_map(fn($i) => ((float)($i['preco_promocional'] > 0 ? $i['preco_promocional'] : $i['preco'])) * (int)$i['qtd'], $carrinho));

// Dados
try {
    $categorias = db()->query("SELECT * FROM " . table('categorias') . " WHERE ativo = 1 ORDER BY ordem, nome")->fetchAll();
    $banners    = db()->query("SELECT * FROM " . table('banners') . " WHERE ativo = 1 AND posicao = 'home_topo' ORDER BY ordem LIMIT 5")->fetchAll();
} catch (Exception $e) { $categorias = []; $banners = []; }

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
        db()->prepare("UPDATE " . table('produtos') . " SET visualizacoes = visualizacoes + 1 WHERE id = ?")->execute([$pid]);
        $si = db()->prepare("SELECT * FROM " . table('produto_imagens') . " WHERE produto_id = ? ORDER BY ordem"); $si->execute([$pid]); $produto_imagens_modal = $si->fetchAll();
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

        /* ===== TOAST TAILWIND - TEMPO AUMENTADO PARA 6s ===== */
        .toast-container {
            position: fixed;
            z-index: 9999;
            <?php echo $toast_css; ?>
            display: flex;
            flex-direction: column;
            gap: 10px;
            pointer-events: none;
        }
        .toast-item {
            pointer-events: auto;
            background: #ffffff;
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.15), 0 2px 8px rgba(0,0,0,0.08);
            padding: 14px 18px;
            min-width: 300px;
            max-width: 400px;
            display: flex;
            align-items: center;
            gap: 12px;
            border-left: 4px solid var(--primary);
            animation: toastSlideIn 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275), toastFadeOut 0.4s ease 5.6s forwards;
            position: relative;
            overflow: hidden;
        }
        .toast-item::before {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            height: 3px;
            background: var(--primary);
            width: 100%;
            animation: toastProgress 6s linear forwards;
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
        @keyframes toastSlideIn {
            from { opacity: 0; transform: translateX(30px) scale(0.95); }
            to { opacity: 1; transform: translateX(0) scale(1); }
        }
        @keyframes toastFadeOut {
            from { opacity: 1; transform: translateX(0) scale(1); }
            to { opacity: 0; transform: translateX(30px) scale(0.95); }
        }
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
            position: absolute;
            top: 16px;
            right: 16px;
            z-index: 10;
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
            height: 100%;
            min-height: 0;
        }
        @media (max-width: 768px) {
            .modal-produto-grid { grid-template-columns: 1fr; }
            .modal-produto-card { 
                width: 95vw; 
                height: 85vh; 
                max-height: 85vh; 
                border-radius: 16px 16px 0 0; 
            }
            .modal-produto-img-principal {
                max-height: 40vh;
            }
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

        /* ===== CARROSSEL DA CATEGORIA - ESTILOS ===== */
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
    </style>
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

            <!-- Barra de busca -->
            <form action="/" method="GET" class="desktop-only" style="flex:1;max-width:420px;margin:0 24px;" >
                <div style="position:relative;">
                    <input type="text" name="busca" value="<?php echo sanitize($busca); ?>" placeholder="Buscar produtos..."
                           style="width:100%;padding:10px 16px 10px 42px;border:1px solid var(--gray-200);border-radius:30px;font-size:0.875rem;outline:none;transition:all 0.2s;"
                           onfocus="this.style.borderColor='<?php echo $cor_primaria; ?>';this.style.boxShadow='0 0 0 3px <?php echo $cor_primaria; ?>20'"
                           onblur="this.style.borderColor='var(--gray-200)';this.style.boxShadow='none'">
                    <button type="submit" style="position:absolute;left:14px;top:50%;transform:translateY(-50%);background:none;border:none;color:var(--gray-400);cursor:pointer;padding:0;">
                        <i class="fas fa-search" style="font-size:0.875rem;"></i>
                    </button>
                </div>
            </form>

            <div class="header-actions">
                <!-- Área do Cliente -->
                <a href="<?php echo !empty($_SESSION['cliente_id']) ? '/cliente/pedidos.php' : '/cliente/login.php'; ?>" 
                   style="background:none;border:1px solid var(--gray-200);color:var(--gray-700);padding:8px 14px;border-radius:8px;display:flex;align-items:center;gap:6px;font-size:0.875rem;text-decoration:none;">
                    <i class="fas fa-user"></i>
                    <span class="hide-mobile"><?php echo !empty($_SESSION['cliente_id']) ? 'Minha Conta' : 'Entrar'; ?></span>
                </a>

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
                    <?php if (!empty($carrinho)): ?>
                    <span id="badge-carrinho" class="badge-carrinho"><?php echo count($carrinho); ?></span>
                    <?php endif; ?>
                </button>
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
                    <a href="/?pid=<?php echo $pid; ?>&qty_minus=1&pid=<?php echo $pid; ?>" class="qty-btn" title="Diminuir">
                        <i class="fas fa-minus" style="font-size:0.7rem;"></i>
                    </a>
                    <span style="min-width:28px;text-align:center;font-weight:700;font-size:0.9rem;"><?php echo $item['qtd']; ?></span>
                    <a href="/?add_cart=<?php echo $pid; ?>&qty_cart=1" class="qty-btn" title="Aumentar" style="background:<?php echo $cor_primaria; ?>22;border-color:<?php echo $cor_primaria; ?>44;color:<?php echo $cor_primaria; ?>;">
                        <i class="fas fa-plus" style="font-size:0.7rem;"></i>
                    </a>
                    <?php if ($mostrar_preco): ?>
                    <span style="margin-left:8px;font-weight:700;color:<?php echo $cor_primaria; ?>;font-size:0.875rem;"><?php echo format_currency($preco_item * $item['qtd']); ?></span>
                    <?php endif; ?>
                </div>
            </div>
            <a href="/?remove_cart=<?php echo $pid; ?>" style="color:var(--gray-400);font-size:0.875rem;align-self:flex-start;padding:2px;" title="Remover"><i class="fas fa-times"></i></a>
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
        <a href="/?clear_cart=1" style="display:block;text-align:center;color:var(--gray-400);font-size:0.8rem;margin-top:8px;">Limpar orçamento</a>
    </div>
    <?php endif; ?>
</div>
<div id="overlayCarrinho" onclick="toggleCarrinho()" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.4);z-index:999;"></div>

<!-- Modal Enviar Orçamento -->
<div id="modalOrcamento" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.6);z-index:2000;align-items:center;justify-content:center;padding:20px;">
    <div style="background:#fff;border-radius:16px;width:100%;max-width:460px;padding:28px;position:relative;">
        <button onclick="fecharModalOrcamento()" style="position:absolute;top:16px;right:16px;background:var(--gray-100);border:none;width:32px;height:32px;border-radius:50%;cursor:pointer;font-size:1.1rem;">&times;</button>
        <h3 style="font-size:1.125rem;font-weight:700;margin-bottom:6px;"><i class="fas fa-file-invoice-dollar" style="color:<?php echo $cor_primaria; ?>;margin-right:8px;"></i>Confirmar Orçamento</h3>
        <p style="color:var(--gray-500);font-size:0.875rem;margin-bottom:20px;">Informe seus dados para registrarmos o orçamento.</p>

        <div id="orcStep1">
            <div style="margin-bottom:12px;">
                <label style="display:block;font-size:0.875rem;font-weight:600;margin-bottom:4px;">Seu nome <span style="color:#ef4444;">*</span></label>
                <input type="text" id="orcNome" placeholder="Nome completo" style="width:100%;padding:10px 14px;border:1px solid var(--gray-200);border-radius:8px;font-size:0.875rem;outline:none;" required>
            </div>
            <div style="margin-bottom:16px;">
                <label style="display:block;font-size:0.875rem;font-weight:600;margin-bottom:4px;">WhatsApp / Telefone</label>
                <input type="tel" id="orcTel" placeholder="(11) 99999-9999" style="width:100%;padding:10px 14px;border:1px solid var(--gray-200);border-radius:8px;font-size:0.875rem;outline:none;">
            </div>
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

<!-- Banners (só na home sem filtros) -->
<?php if (!empty($banners) && !$busca && !$categoria_id && $page == 1): ?>
<div style="background:var(--gray-100);">
    <div class="container" style="padding-top:16px;padding-bottom:16px;">
        <?php if (count($banners) === 1): ?>
        <div style="border-radius:16px;overflow:hidden;position:relative;">
            <?php $b = $banners[0]; ?>
            <img src="<?php echo uploads_url($b['imagem']); ?>" alt="<?php echo sanitize($b['titulo']??''); ?>" style="width:100%;max-height:380px;object-fit:cover;">
            <?php if ($b['titulo'] || $b['subtitulo']): ?>
            <div style="position:absolute;bottom:0;left:0;right:0;background:linear-gradient(to top,rgba(0,0,0,0.7),transparent);padding:30px 32px;">
                <?php if ($b['titulo']): ?><h2 style="color:#fff;font-size:1.75rem;font-weight:700;"><?php echo sanitize($b['titulo']); ?></h2><?php endif; ?>
                <?php if ($b['subtitulo']): ?><p style="color:rgba(255,255,255,0.8);margin-top:6px;"><?php echo sanitize($b['subtitulo']); ?></p><?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
        <?php else: ?>
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:16px;">
            <?php foreach ($banners as $b): ?>
            <div style="border-radius:12px;overflow:hidden;">
                <?php if ($b['link']): ?><a href="<?php echo sanitize($b['link']); ?>"><?php endif; ?>
                <img src="<?php echo uploads_url($b['imagem']); ?>" alt="<?php echo sanitize($b['titulo']??''); ?>" style="width:100%;height:180px;object-fit:cover;">
                <?php if ($b['link']): ?></a><?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<!-- Categorias MOBILE (barra horizontal - sempre aparece no mobile) -->
<div class="mobile-only" style="background:#fff; border-bottom:1px solid var(--gray-200); overflow-x:auto; margin-bottom:20px;">
    <div class="container">
        <div style="display:flex; gap:4px; padding:8px 0; white-space:nowrap;">
            <a href="/" style="display:inline-flex;align-items:center;gap:6px;padding:7px 14px;border-radius:20px;font-size:0.8125rem;font-weight:500;background:<?php echo !$categoria_id?$cor_primaria:'transparent'; ?>;color:<?php echo !$categoria_id?'#fff':'var(--gray-600)'; ?>;text-decoration:none;border:1px solid <?php echo !$categoria_id?$cor_primaria:'var(--gray-200)'; ?>;">
                <i class="fas fa-th-large" style="font-size:0.75rem;"></i> Todos
            </a>
            <?php foreach ($categorias as $cat): ?>
            <a href="/?categoria=<?php echo $cat['id']; ?>" style="display:inline-flex;align-items:center;gap:6px;padding:7px 14px;border-radius:20px;font-size:0.8125rem;font-weight:500;background:<?php echo $categoria_id==$cat['id']?$cor_primaria:'transparent'; ?>;color:<?php echo $categoria_id==$cat['id']?'#fff':'var(--gray-600)'; ?>;text-decoration:none;border:1px solid <?php echo $categoria_id==$cat['id']?$cor_primaria:'var(--gray-200)'; ?>;">
                <?php if (!empty($cat['icone'])): ?>
                <span class="material-icons" style="font-size:0.85rem;"><?php echo sanitize($cat['icone']); ?></span>
                <?php endif; ?>
                <?php echo sanitize($cat['nome']); ?>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<div class="container" style="display:flex; gap:24px; padding-top:24px; padding-bottom:40px; align-items:flex-start;">

    <!-- Sidebar de Categorias (DESKTOP - apenas no modo sidebar) -->
    <?php if ($categoria_layout === 'sidebar'): ?>
    <aside class="cat-sidebar desktop-only">
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
            <div class="grid-produtos" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:20px;">
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
                        <a href="/?add_cart=<?php echo $prod['id']; ?>&ref=<?php echo $categoria_id; ?>" 
                           onclick="showToast('<?php echo addslashes(sanitize($prod['nome'])); ?>', '<?php echo uploads_url($prod['imagem_principal'] ?? ''); ?>')"
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
                <?php echo pagination_links($pagination, '/', array_filter(['busca'=>$busca,'categoria'=>$categoria_id])); ?>
            </div>
        <?php endif; ?>
    </main>
</div>


<!-- ===== MODAL PRODUTO MODERNO ===== -->
<?php if ($produto_modal): 
    $preco_modal = (float)($produto_modal['preco_promocional'] > 0 ? $produto_modal['preco_promocional'] : $produto_modal['preco']);
    $tem_promo_modal = !empty($produto_modal['preco_promocional']) && $produto_modal['preco_promocional'] > 0;
?>
<div id="modalProduto" class="modal-produto-overlay" onclick="if(event.target===this)window.location='/?categoria=<?php echo $categoria_id; ?>&busca=<?php echo urlencode($busca); ?>'">
    <div class="modal-produto-card" onclick="event.stopPropagation()">
        <button onclick="window.location='/?categoria=<?php echo $categoria_id; ?>&busca=<?php echo urlencode($busca); ?>'" class="modal-produto-close">&times;</button>

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
                    <a href="/?add_cart=<?php echo $produto_modal['id']; ?>" 
                       onclick="showToast('<?php echo addslashes(sanitize($produto_modal['nome'])); ?>', '<?php echo uploads_url($produto_modal['imagem_principal'] ?? ''); ?>')"
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
                                        <a href="/?add_cart=<?php echo (int)$cprod['id']; ?>" 
                                           onclick="showToast('<?php echo addslashes(sanitize($cprod['nome'])); ?>', '<?php echo uploads_url($cprod['imagem_principal'] ?? ''); ?>')"
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

<!-- Footer -->
<footer style="background:var(--gray-900);color:rgba(255,255,255,0.7);padding:40px 0 20px;">
    <div class="container">
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:32px;margin-bottom:32px;">
            <div>
                <h4 style="color:#fff;font-weight:700;margin-bottom:12px;"><?php echo sanitize($site_name); ?></h4>
                <p style="font-size:0.875rem;line-height:1.7;"><?php echo sanitize($site_description); ?></p>
            </div>
            <?php if ($telefone || $site_email || $endereco): ?>
            <div>
                <h4 style="color:#fff;font-weight:700;margin-bottom:12px;">Contato</h4>
                <?php if ($telefone): ?><p style="font-size:0.875rem;margin-bottom:6px;"><i class="fas fa-phone" style="margin-right:6px;color:<?php echo $cor_primaria; ?>"></i> <?php echo sanitize($telefone); ?></p><?php endif; ?>
                <?php if ($whatsapp): ?><p style="font-size:0.875rem;margin-bottom:6px;"><i class="fab fa-whatsapp" style="margin-right:6px;color:#25d366;"></i> <?php echo format_phone($whatsapp); ?></p><?php endif; ?>
                <?php if ($site_email): ?><p style="font-size:0.875rem;margin-bottom:6px;"><i class="fas fa-envelope" style="margin-right:6px;color:<?php echo $cor_primaria; ?>"></i> <?php echo sanitize($site_email); ?></p><?php endif; ?>
                <?php if ($horario): ?><p style="font-size:0.875rem;"><i class="fas fa-clock" style="margin-right:6px;color:<?php echo $cor_primaria; ?>"></i> <?php echo sanitize($horario); ?></p><?php endif; ?>
            </div>
            <?php endif; ?>
            <?php 
            $redes = array_filter([
                'facebook' => $facebook_url,
                'instagram' => $instagram_url,
                'linkedin' => $linkedin_url,
                'youtube' => $youtube_url,
                'tiktok' => $tiktok_url,
                'twitter' => $twitter_url,
                'telegram' => $telegram_url,
                'pinterest' => $pinterest_url,
                'kwai' => $kwai_url,
                'threads' => $threads_url,
            ]);
            ?>
            <?php if (!empty($redes)): ?>
            <div>
                <h4 style="color:#fff;font-weight:700;margin-bottom:12px;">Redes Sociais</h4>
                <div style="display:flex;gap:12px;flex-wrap:wrap;">
                    <?php if ($facebook_url): ?><a href="<?php echo sanitize($facebook_url); ?>" target="_blank" style="color:rgba(255,255,255,0.7);font-size:1.5rem;" title="Facebook"><i class="fab fa-facebook"></i></a><?php endif; ?>
                    <?php if ($instagram_url): ?><a href="<?php echo sanitize($instagram_url); ?>" target="_blank" style="color:rgba(255,255,255,0.7);font-size:1.5rem;" title="Instagram"><i class="fab fa-instagram"></i></a><?php endif; ?>
                    <?php if ($tiktok_url): ?><a href="<?php echo sanitize($tiktok_url); ?>" target="_blank" style="color:rgba(255,255,255,0.7);font-size:1.5rem;" title="TikTok"><i class="fab fa-tiktok"></i></a><?php endif; ?>
                    <?php if ($twitter_url): ?><a href="<?php echo sanitize($twitter_url); ?>" target="_blank" style="color:rgba(255,255,255,0.7);font-size:1.5rem;" title="Twitter/X"><i class="fab fa-x-twitter"></i></a><?php endif; ?>
                    <?php if ($youtube_url): ?><a href="<?php echo sanitize($youtube_url); ?>" target="_blank" style="color:rgba(255,255,255,0.7);font-size:1.5rem;" title="YouTube"><i class="fab fa-youtube"></i></a><?php endif; ?>
                    <?php if ($telegram_url): ?><a href="<?php echo sanitize($telegram_url); ?>" target="_blank" style="color:rgba(255,255,255,0.7);font-size:1.5rem;" title="Telegram"><i class="fab fa-telegram"></i></a><?php endif; ?>
                    <?php if ($pinterest_url): ?><a href="<?php echo sanitize($pinterest_url); ?>" target="_blank" style="color:rgba(255,255,255,0.7);font-size:1.5rem;" title="Pinterest"><i class="fab fa-pinterest"></i></a><?php endif; ?>
                    <?php if ($linkedin_url): ?><a href="<?php echo sanitize($linkedin_url); ?>" target="_blank" style="color:rgba(255,255,255,0.7);font-size:1.5rem;" title="LinkedIn"><i class="fab fa-linkedin"></i></a><?php endif; ?>
                    <?php if ($kwai_url): ?><a href="<?php echo sanitize($kwai_url); ?>" target="_blank" style="color:rgba(255,255,255,0.7);font-size:1.4rem;font-weight:700;" title="Kwai">Kw</a><?php endif; ?>
                    <?php if ($threads_url): ?><a href="<?php echo sanitize($threads_url); ?>" target="_blank" style="color:rgba(255,255,255,0.7);font-size:1.5rem;" title="Threads"><i class="fab fa-threads"></i></a><?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
        <div style="border-top:1px solid rgba(255,255,255,0.1);padding-top:20px;text-align:center;font-size:0.8125rem;">
            &copy; <?php echo date('Y'); ?> <?php echo sanitize($site_name); ?> — Todos os direitos reservados
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
// ===== TOAST FUNCTION - INFERIOR DIREITO COM ENTRADA SUAVE =====
function showToast(nomeProduto, imagemProduto) {
    const container = document.getElementById('toastContainer');
    if (!container) return;

    const toast = document.createElement('div');
    toast.className = 'toast-item success'; // Começa oculto pelo CSS

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

    // TRUQUE: Força o navegador a registrar a posição inicial (oculta) antes de animar
    toast.offsetHeight; 

    // Adiciona a classe que dispara a animação de deslizar e fade-in
    toast.classList.add('show');

    // Agenda a saída automática após 3 segundos (3000ms)
    setTimeout(() => {
        fecharToast(toast);
    }, 6000);
}

// Função única para fechar com suavidade (tanto no clique quanto no automático)
function fecharToast(toast) {
    if (!toast || !toast.parentElement) return;
    
    // Remove a classe para iniciar a animação de saída (deslizar de volta para a direita)
    toast.classList.remove('show');
    
    // Espera os 500ms da animação do CSS acabar antes de remover o HTML definitivamente
    setTimeout(() => {
        if (toast.parentElement) toast.remove();
    }, 6000);
}

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

// Esconder carrinho ao pressionar ESC
document.addEventListener('keydown', e => { 
    if (e.key === 'Escape') { 
        const painel = document.getElementById('painelCarrinho');
        if (painel && painel.style.display === 'flex') toggleCarrinho();
        fecharModalOrcamento(); 
    } 
});
</script>

</body>
</html>