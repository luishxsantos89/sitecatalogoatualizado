<?php
/**
 * SiteCatalogo2 - Admin Header
 */
$admin_nome   = $_SESSION['admin_nome'] ?? 'Admin';
$admin_nivel  = $_SESSION['admin_nivel'] ?? 'admin';
$admin_avatar = $_SESSION['admin_avatar'] ?? '';
$orcamentos_pendentes = count_orcamentos_pendentes();
$site_name = get_config('site_nome', 'SiteCatalogo2');

// Emails não lidos
$emails_nao_lidos = 0;
try {
    $emails_nao_lidos = (int)db()->query("SELECT COUNT(*) FROM " . table('emails') . " WHERE pasta = 'inbox' AND status = 'nao_lido'")->fetchColumn();
} catch (Exception $e) { $emails_nao_lidos = 0; }

// Financeiro - vencimentos hoje/vencidos
$fin_alertas = 0;
try {
    $fin_alertas = (int)db()->query("SELECT COUNT(*) FROM " . table('financeiro_lancamentos') . " WHERE status='pendente' AND data_vencimento <= CURDATE()")->fetchColumn();
} catch (Exception $e) { $fin_alertas = 0; }

// Página atual para menu ativo
if (!isset($current_page)) $current_page = basename($_SERVER['PHP_SELF'], '.php');
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title ?? 'Painel'; ?> - <?php echo sanitize($site_name); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="<?php echo defined('ADMIN_URL') ? ADMIN_URL : '../admin/'; ?>assets/css/admin.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        /* Override para integração Tailwind + admin.css customizado */
        [x-cloak] { display: none !important; }
    </style>
</head>
<body>
<div class="admin-wrapper">
    <!-- Sidebar -->
    <aside class="admin-sidebar" id="sidebar">
        <div class="sidebar-header">
            <a href="./" class="sidebar-logo">
                <svg width="32" height="32" viewBox="0 0 36 36" fill="none">
                    <rect width="36" height="36" rx="9" fill="#3b82f6"/>
                    <path d="M10 24V14l8-5 8 5v10H10z" stroke="white" stroke-width="2" fill="none"/>
                    <circle cx="18" cy="19" r="2.5" fill="white"/>
                </svg>
                <span><?php echo sanitize($site_name); ?></span>
            </a>
            <button class="sidebar-close" id="sidebarClose"><i class="fas fa-times"></i></button>
        </div>

        <nav class="sidebar-nav">
            <a href="./" class="nav-link <?php echo active_class($current_page, 'index'); ?>">
                <i class="fas fa-chart-pie"></i><span>Dashboard</span>
            </a>

            <div class="nav-divider"><span>Catálogo</span></div>

            <a href="produtos.php" class="nav-link <?php echo active_class($current_page, 'produtos'); ?>">
                <i class="fas fa-box-open"></i><span>Produtos</span>
            </a>
            <a href="categorias.php" class="nav-link <?php echo active_class($current_page, 'categorias'); ?>">
                <i class="fas fa-tags"></i><span>Categorias</span>
            </a>
            <a href="estoque.php" class="nav-link <?php echo active_class($current_page, 'estoque'); ?>">
                <i class="fas fa-warehouse"></i><span>Estoque</span>
            </a>
            <a href="banners.php" class="nav-link <?php echo active_class($current_page, 'banners'); ?>">
                <i class="fas fa-image"></i><span>Banners</span>
            </a>

            <div class="nav-divider"><span>Comercial</span></div>

            <a href="orcamentos.php" class="nav-link <?php echo active_class($current_page, 'orcamentos'); ?>">
                <i class="fas fa-file-invoice-dollar"></i><span>Orçamentos</span>
                <?php if ($orcamentos_pendentes > 0): ?>
                <span class="nav-badge"><?php echo $orcamentos_pendentes; ?></span>
                <?php endif; ?>
            </a>
            <a href="clientes.php" class="nav-link <?php echo active_class($current_page, 'clientes'); ?>">
                <i class="fas fa-users"></i><span>Clientes</span>
            </a>

            <?php if (check_permission('gerente')): ?>
            <div class="nav-divider"><span>Financeiro</span></div>

            <a href="financeiro.php" class="nav-link <?php echo (in_array($current_page, ['financeiro','fin_lancamento','fin_contas','fin_relatorios']) ? 'active' : ''); ?>">
                <i class="fas fa-dollar-sign"></i><span>Financeiro</span>
                <?php if ($fin_alertas > 0): ?>
                <span class="nav-badge red"><?php echo $fin_alertas; ?></span>
                <?php endif; ?>
            </a>
            <a href="financeiro_contas.php" class="nav-link <?php echo active_class($current_page, 'financeiro_contas'); ?>">
                <i class="fas fa-university"></i><span>Contas Bancárias</span>
            </a>
            <a href="financeiro_relatorios.php" class="nav-link <?php echo active_class($current_page, 'financeiro_relatorios'); ?>">
                <i class="fas fa-chart-bar"></i><span>Relatórios</span>
            </a>
            <?php endif; ?>

            <div class="nav-divider"><span>Sistema</span></div>
            
                <a href="email.php" class="nav-link <?php echo active_class($current_page, 'email'); ?>">
                    <i class="fas fa-envelope"></i><span>Email</span>
                    <?php if ($emails_nao_lidos > 0): ?>
                    <span class="nav-badge"><?php echo $emails_nao_lidos; ?></span>
                    <?php endif; ?>
                </a>
           
            <?php if (check_permission('admin')): ?>
            <a href="usuarios.php" class="nav-link <?php echo active_class($current_page, 'usuarios'); ?>">
                <i class="fas fa-user-shield"></i><span>Usuários</span>
            </a>
            <?php endif; ?>
            <a href="configuracoes.php" class="nav-link <?php echo active_class($current_page, 'configuracoes'); ?>">
                <i class="fas fa-cog"></i><span>Configurações</span>
            </a>
            <a href="seo.php" class="nav-link <?php echo active_class($current_page, 'seo'); ?>">
                <i class="fas fa-search"></i><span>SEO</span>
            </a>
        </nav>

        <div class="sidebar-footer">
            <a href="../" target="_blank" class="nav-link">
                <i class="fas fa-external-link-alt"></i><span>Ver Site</span>
            </a>
            <a href="logout.php" class="nav-link text-danger">
                <i class="fas fa-sign-out-alt"></i><span>Sair</span>
            </a>
        </div>
    </aside>

    <!-- Main -->
    <div class="admin-main">
        <!-- Topbar -->
        <header class="admin-topbar">
            <button class="menu-toggle" id="menuToggle"><i class="fas fa-bars"></i></button>

            <div class="topbar-right">
                <?php if ($fin_alertas > 0): ?>
                <a href="financeiro.php?filtro=vencidos" class="topbar-alert" title="<?php echo $fin_alertas; ?> lançamentos vencidos/vencem hoje">
                    <i class="fas fa-exclamation-triangle"></i>
                    <span class="topbar-badge"><?php echo $fin_alertas; ?></span>
                </a>
                <?php endif; ?>
                <div class="dropdown">
                    <button class="user-menu" id="userMenu">
                        <div class="user-avatar">
                            <?php if ($admin_avatar): ?>
                            <img src="<?php echo uploads_url($admin_avatar); ?>" alt="">
                            <?php else: ?>
                            <i class="fas fa-user"></i>
                            <?php endif; ?>
                        </div>
                        <div class="user-info">
                            <span class="user-name"><?php echo sanitize($admin_nome); ?></span>
                            <span class="user-role"><?php echo ucfirst($admin_nivel); ?></span>
                        </div>
                        <i class="fas fa-chevron-down"></i>
                    </button>
                    <div class="dropdown-menu" id="userDropdown">
                        <a href="perfil.php"><i class="fas fa-user"></i> Meu Perfil</a>
                        <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Sair</a>
                    </div>
                </div>
            </div>
        </header>

        <!-- Page Content -->
        <div class="admin-content">
            <?php echo show_flash(); ?>
<?php
// Alerta sonoro para novos orçamentos no painel admin
$alerta_sonoro_admin = get_config('alerta_sonoro_orcamento', '1') === '1';
if ($alerta_sonoro_admin):
?>
<script>
(function() {
    let ultimoCount = <?php echo (int)db()->query("SELECT COUNT(*) FROM " . table('orcamentos') . " WHERE status='novo'")->fetchColumn(); ?>;

    function tocarAlerta() {
        try {
            const ctx = new (window.AudioContext || window.webkitAudioContext)();
            function nota(freq, start, dur) {
                const o = ctx.createOscillator();
                const g = ctx.createGain();
                o.connect(g); g.connect(ctx.destination);
                o.type = 'sine';
                o.frequency.value = freq;
                g.gain.setValueAtTime(0.25, ctx.currentTime + start);
                g.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + start + dur);
                o.start(ctx.currentTime + start);
                o.stop(ctx.currentTime + start + dur);
            }
            nota(880, 0, 0.12);
            nota(1100, 0.13, 0.12);
            nota(880, 0.26, 0.18);
        } catch(e) {}
    }

    function mostrarNotificacao(n) {
        if (window.Notification && Notification.permission === 'granted') {
            new Notification('🛒 Novo Orçamento Recebido!', {
                body: n + ' novo(s) orçamento(s) aguardando atendimento!',
                icon: '/assets/images/logo.png'
            });
        }
        // Toast na tela
        const toast = document.createElement('div');
        toast.style.cssText = 'position:fixed;top:20px;right:20px;z-index:9999;background:#22c55e;color:#fff;padding:14px 20px;border-radius:12px;box-shadow:0 4px 20px rgba(0,0,0,0.2);font-weight:600;font-size:0.9375rem;display:flex;align-items:center;gap:10px;animation:slideIn 0.3s ease;';
        toast.innerHTML = '<i class="fas fa-bell"></i> ' + n + ' novo(s) orçamento(s) recebido(s)!';
        document.body.appendChild(toast);
        setTimeout(() => toast.remove(), 5000);
    }

    function verificarOrcamentos() {
        fetch('/api/check_orcamentos.php')
        .then(r => r.json())
        .then(d => {
            if (d.count > ultimoCount) {
                const novos = d.count - ultimoCount;
                tocarAlerta();
                mostrarNotificacao(novos);
                ultimoCount = d.count;
                // Atualizar badge do menu
                const badges = document.querySelectorAll('.nav-badge');
                badges.forEach(b => {
                    if (b.closest('a[href="orcamentos.php"]')) {
                        b.textContent = d.count;
                    }
                });
            }
        }).catch(()=>{});
    }

    if (window.Notification && Notification.permission === 'default') {
        Notification.requestPermission();
    }
    setInterval(verificarOrcamentos, 30000);
    
    // Estilo da animação toast
    const style = document.createElement('style');
    style.textContent = '@keyframes slideIn { from { opacity:0; transform:translateX(20px); } to { opacity:1; transform:translateX(0); } }';
    document.head.appendChild(style);
})();
</script>
<?php endif; ?>
