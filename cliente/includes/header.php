<?php
/**
 * SiteCatalogo2 - Área do Cliente
 * Header / Sidebar (visual baseado no painel admin)
 *
 * Variáveis esperadas antes do include:
 * - $page_title (string)
 * - $cliente_logado_atual (array|null) já carregado pela página
 * - $hide_sidebar (bool) opcional - true para páginas de login/cadastro
 */

$site_name    = get_config('site_nome', defined('SITE_NAME') ? SITE_NAME : 'Catálogo');
$cor_primaria = get_config('cor_primaria', '#3b82f6');
$logo_cliente = get_config('logo_cliente', '');
$hide_sidebar = $hide_sidebar ?? false;

$flash = function_exists('get_flash') ? get_flash() : null;
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo sanitize($page_title ?? 'Minha Conta'); ?> · <?php echo sanitize($site_name); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        :root {
            --primary: <?php echo $cor_primaria; ?>;
            --primary-dark: <?php echo $cor_primaria; ?>dd;
            --primary-light: <?php echo $cor_primaria; ?>15;
            --gray-50: #f9fafb;
            --gray-100: #f3f4f6;
            --gray-200: #e5e7eb;
            --gray-300: #d1d5db;
            --gray-400: #9ca3af;
            --gray-500: #6b7280;
            --gray-600: #4b5563;
            --gray-700: #374151;
            --gray-800: #1f2937;
            --gray-900: #111827;
            --success: #16a34a;
            --warning: #f59e0b;
            --danger: #ef4444;
            --radius: 12px;
            --shadow: 0 1px 3px rgba(0,0,0,0.08);
            --shadow-lg: 0 10px 25px rgba(0,0,0,0.12);
        }
        * { margin:0; padding:0; box-sizing:border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            background: var(--gray-50);
            color: var(--gray-800);
            display:flex;
            min-height:100vh;
        }
        a { color: inherit; }

        /* Sidebar */
        .sidebar {
            width: 260px;
            background: var(--gray-900);
            color:#fff;
            display:flex;
            flex-direction:column;
            flex-shrink:0;
            position:sticky;
            top:0;
            height:100vh;
            overflow-y:auto;
        }
        .sidebar-brand {
            display:flex;
            align-items:center;
            gap:10px;
            padding:20px;
            font-weight:700;
            font-size:1.05rem;
            border-bottom:1px solid rgba(255,255,255,0.08);
        }
        .sidebar-brand-icon {
            width:36px;height:36px;border-radius:9px;
            background:var(--primary);
            display:flex;align-items:center;justify-content:center;
            flex-shrink:0;
            overflow:hidden;
        }
        .sidebar-brand-icon img { width:100%; height:100%; object-fit:contain; }

        .sidebar-user {
            display:flex; align-items:center; gap:10px;
            padding:16px 20px;
            border-bottom:1px solid rgba(255,255,255,0.08);
        }
        .sidebar-user-avatar {
            width:38px;height:38px;border-radius:50%;
            background:var(--primary);
            display:flex;align-items:center;justify-content:center;
            font-weight:700; font-size:0.9rem; flex-shrink:0;
        }
        .sidebar-user-info p { margin:0; font-size:0.875rem; }
        .sidebar-user-info .nome { font-weight:600; }
        .sidebar-user-info .sub { color: var(--gray-400); font-size:0.75rem; }

        .sidebar-section {
            font-size:0.7rem; text-transform:uppercase; letter-spacing:0.05em;
            color: var(--gray-400); padding:18px 20px 8px;
        }
        .sidebar-link {
            display:flex; align-items:center; gap:12px;
            padding:11px 20px;
            text-decoration:none; color: var(--gray-300);
            font-size:0.9rem; font-weight:500;
            transition: all .15s;
            border-left:3px solid transparent;
        }
        .sidebar-link i { width:18px; text-align:center; }
        .sidebar-link:hover { background: rgba(255,255,255,0.05); color:#fff; }
        .sidebar-link.active {
            background: rgba(255,255,255,0.08);
            color:#fff;
            border-left-color: var(--primary);
        }
        .sidebar-link .badge-count {
            margin-left:auto;
            background: var(--danger);
            color:#fff; font-size:0.7rem; font-weight:700;
            border-radius:99px; padding:2px 7px;
        }
        .sidebar-footer { margin-top:auto; border-top:1px solid rgba(255,255,255,0.08); }

        /* Main content */
        .main {
            flex:1;
            min-width:0;
            padding:28px 32px;
        }
        .page-header {
            display:flex; align-items:center; justify-content:space-between;
            flex-wrap:wrap; gap:12px;
            margin-bottom:22px;
        }
        .page-header h1 {
            font-size:1.5rem; font-weight:700;
            display:flex; align-items:center; gap:10px;
        }
        .page-header h1 i { color: var(--primary); }

        /* Cards */
        .card {
            background:#fff;
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            margin-bottom:20px;
            overflow:hidden;
        }
        .card-header {
            padding:16px 20px;
            border-bottom:1px solid var(--gray-100);
        }
        .card-header h3 { font-size:1rem; font-weight:700; display:flex; align-items:center; gap:8px; }
        .card-header h3 i { color: var(--primary); }
        .card-body { padding:20px; }

        /* Buttons */
        .btn {
            display:inline-flex; align-items:center; gap:8px;
            padding:10px 18px;
            border-radius:8px;
            border:none;
            font-size:0.875rem; font-weight:600;
            cursor:pointer;
            text-decoration:none;
            transition: all .15s;
        }
        .btn-primary { background: var(--primary); color:#fff; }
        .btn-primary:hover { background: var(--primary-dark); }
        .btn-secondary { background: var(--gray-100); color: var(--gray-700); }
        .btn-secondary:hover { background: var(--gray-200); }
        .btn-outline { background:transparent; color: var(--primary); border:1px solid var(--primary); }
        .btn-outline:hover { background: var(--primary-light); }
        .btn-danger { background: var(--danger); color:#fff; }
        .btn-lg { padding:13px 22px; font-size:0.95rem; }
        .btn-block { width:100%; justify-content:center; }
        .btn-sm { padding:6px 12px; font-size:0.8rem; }

        /* Forms */
        .form-group { margin-bottom:16px; }
        .form-group label { display:block; font-size:0.8125rem; font-weight:600; color: var(--gray-700); margin-bottom:6px; }
        .form-group input, .form-group select, .form-group textarea {
            width:100%; padding:11px 14px;
            border:1px solid var(--gray-200); border-radius:8px;
            font-size:0.9rem; font-family:inherit;
            background:#fff; color:var(--gray-800);
            transition: border-color .15s, box-shadow .15s;
        }
        .form-group input:focus, .form-group select:focus, .form-group textarea:focus {
            outline:none; border-color: var(--primary);
            box-shadow: 0 0 0 3px var(--primary-light);
        }
        .form-row { display:grid; gap:14px; }
        .form-row-2 { grid-template-columns: 1fr 1fr; }
        .form-hint { font-size:0.78rem; color: var(--gray-500); margin-top:4px; }

        /* Alerts */
        .alert {
            padding:13px 16px; border-radius:8px;
            font-size:0.875rem; margin-bottom:16px;
            display:flex; align-items:center; gap:10px;
        }
        .alert-success { background:#dcfce7; color:#166534; border:1px solid #bbf7d0; }
        .alert-error { background:#fee2e2; color:#991b1b; border:1px solid #fecaca; }
        .alert-info { background: var(--primary-light); color: var(--primary-dark); border:1px solid var(--primary); }

        /* Table */
        .table-responsive { overflow-x:auto; }
        .table { width:100%; border-collapse:collapse; font-size:0.875rem; }
        .table th {
            text-align:left; padding:12px 16px;
            background: var(--gray-50); color: var(--gray-500);
            font-size:0.75rem; text-transform:uppercase; letter-spacing:.03em;
            border-bottom:1px solid var(--gray-100);
            white-space:nowrap;
        }
        .table td { padding:14px 16px; border-bottom:1px solid var(--gray-100); vertical-align:middle; }
        .table tbody tr:last-child td { border-bottom:none; }
        .table tbody tr:hover { background: var(--gray-50); }

        .empty-state {
            text-align:center; padding:60px 20px; color: var(--gray-400);
        }
        .empty-state i { font-size:2.75rem; margin-bottom:14px; display:block; }
        .empty-state-sm { text-align:center; padding:30px; color: var(--gray-400); font-size:0.875rem; }

        /* Badge status */
        .badge-status {
            display:inline-flex; align-items:center; gap:6px;
            padding:4px 11px; border-radius:99px;
            font-size:0.75rem; font-weight:700;
        }
        .status-novo, .status-em_analise { background:#fef3c7; color:#92400e; }
        .status-aprovado, .status-em_producao { background:#dbeafe; color:#1e40af; }
        .status-enviado { background:#e0e7ff; color:#3730a3; }
        .status-concluido { background:#dcfce7; color:#166534; }
        .status-cancelado { background:#fee2e2; color:#991b1b; }
        .status-ativo { background:#dcfce7; color:#166534; }
        .status-inativo { background:#f3f4f6; color:#6b7280; }
        .status-bloqueado { background:#fee2e2; color:#991b1b; }

        /* Auth pages (login/cadastro, no sidebar) */
        .auth-wrapper {
            flex:1; display:flex; align-items:center; justify-content:center;
            padding:24px;
        }
        .auth-box {
            background:#fff; border-radius: var(--radius);
            box-shadow: var(--shadow-lg);
            width:100%; max-width:440px;
            padding:36px;
        }
        .auth-box.auth-box-lg { max-width:680px; }
        .auth-logo {
            display:flex; align-items:center; justify-content:center; gap:10px;
            margin-bottom:22px;
        }
        .auth-logo .icon {
            width:44px; height:44px; border-radius:11px;
            background: var(--primary);
            display:flex; align-items:center; justify-content:center;
            color:#fff; font-size:1.3rem;
            overflow:hidden;
        }
        .auth-logo .icon img { width:100%; height:100%; object-fit:contain; }
        .auth-logo .name { font-size:1.15rem; font-weight:800; }
        .auth-title { text-align:center; font-size:1.15rem; font-weight:700; margin-bottom:6px; }
        .auth-subtitle { text-align:center; color: var(--gray-500); font-size:0.875rem; margin-bottom:24px; }
        .auth-footer { text-align:center; margin-top:20px; font-size:0.875rem; color: var(--gray-500); }
        .auth-footer a { color: var(--primary); font-weight:600; text-decoration:none; }

        /* Topbar (mobile) */
        .topbar {
            display:none;
            align-items:center; justify-content:space-between;
            background:var(--gray-900); color:#fff;
            padding:14px 16px;
        }
        .topbar-toggle { background:none; border:none; color:#fff; font-size:1.25rem; cursor:pointer; }

        @media (max-width: 900px) {
            body { flex-direction:column; }
            .sidebar {
                position:fixed; left:0; top:0; height:100vh;
                transform:translateX(-100%);
                transition: transform .2s ease;
                z-index:1000;
            }
            .sidebar.open { transform:translateX(0); }
            .topbar { display:flex; }
            .main { padding:18px; }
        }
    </style>
</head>
<body>
<?php if (!$hide_sidebar): ?>
<div class="topbar">
    <button class="topbar-toggle" onclick="document.getElementById('sidebarCliente').classList.toggle('open')">
        <i class="fas fa-bars"></i>
    </button>
    <strong><?php echo sanitize($site_name); ?></strong>
    <a href="logout.php" style="color:#fff;font-size:1.1rem;"><i class="fas fa-sign-out-alt"></i></a>
</div>

<aside class="sidebar" id="sidebarCliente">
    <div class="sidebar-brand">
        <div class="sidebar-brand-icon">
            <?php if ($logo_cliente): ?>
            <img src="<?php echo uploads_url($logo_cliente); ?>" alt="<?php echo sanitize($site_name); ?>">
            <?php else: ?>
            <i class="fas fa-store"></i>
            <?php endif; ?>
        </div>
        <span><?php echo sanitize($site_name); ?></span>
    </div>

    <?php if (!empty($cliente_logado_atual)): ?>
    <div class="sidebar-user">
        <div class="sidebar-user-avatar"><?php echo strtoupper(substr($cliente_logado_atual['nome_razaosocial'], 0, 1)); ?></div>
        <div class="sidebar-user-info">
            <p class="nome"><?php echo sanitize(mb_strimwidth($cliente_logado_atual['nome_razaosocial'], 0, 22, '…')); ?></p>
            <p class="sub">Minha conta</p>
        </div>
    </div>
    <?php endif; ?>

    <div class="sidebar-section">Área do Cliente</div>
    <a href="pedidos.php" class="sidebar-link <?php echo ($page_active ?? '') === 'pedidos' ? 'active' : ''; ?>">
        <i class="fas fa-file-invoice-dollar"></i> Meus Pedidos
    </a>
    <a href="perfil.php" class="sidebar-link <?php echo ($page_active ?? '') === 'perfil' ? 'active' : ''; ?>">
        <i class="fas fa-user-cog"></i> Meus Dados
    </a>
    <a href="/" class="sidebar-link">
        <i class="fas fa-store"></i> Voltar ao Catálogo
    </a>

    <div class="sidebar-footer">
        <a href="logout.php" class="sidebar-link" style="color:#fca5a5;">
            <i class="fas fa-sign-out-alt"></i> Sair
        </a>
    </div>
</aside>
<?php endif; ?>

<div class="main" style="<?php echo $hide_sidebar ? 'display:flex;flex-direction:column;width:100%;padding:0;' : ''; ?>">
