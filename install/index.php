<?php
/**
 * SiteCatalogo — Instalador Universal v2.3
 * Compatível: localhost, cPanel, Hostgator, Hostinger, VPS, Docker, etc.
 * 
 * CHANGELOG v2.3:
 *   • NÃO cria banco automaticamente (exige banco existente na hospedagem compartilhada)
 *   • Campos de DB editáveis livremente (sem sugestões forçadas)
 *   • Schema lido do arquivo schema.sql externo (não mais hardcoded)
 *   • Enum sc_configuracoes.tipo alinhado com dump real (sem 'password')
 *   • Ordem das colunas em sc_emails alinhada com dump real
 */

define('INSTALL_VERSION', '2.3');
define('ROOT_PATH', dirname(__DIR__));
define('DB_PREFIX_FIXED', 'sc_');

// Se já instalado, redireciona para o admin
if (file_exists(ROOT_PATH . '/config.php') && !isset($_GET['force'])) {
    header('Location: ../admin/login.php'); exit;
}

// ─── Funções auxiliares ───────────────────────────────────────────────

function already_installed(): bool {
    return file_exists(ROOT_PATH . '/config.php');
}

/**
 * Testa conexão com MySQL.
 * Se $dbname for informado, valida que o banco existe.
 */
function test_db(string $host, string $user, string $pass, ?string $dbname = null): array {
    try {
        $dsn = "mysql:host={$host};charset=utf8mb4";
        if ($dbname) {
            $dsn .= ";dbname={$dbname}";
        }
        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => 5,
        ]);
        $version = $pdo->query('SELECT VERSION()')->fetchColumn();
        return ['ok' => true, 'pdo' => $pdo, 'version' => $version];
    } catch (PDOException $e) {
        $msg = $e->getMessage();
        if (str_contains($msg, 'Connection refused') || str_contains($msg, '2002'))
            $msg = "Não foi possível conectar ao servidor MySQL. Verifique o host ({$host}).";
        elseif (str_contains($msg, 'Access denied'))
            $msg = "Usuário ou senha incorretos.";
        elseif (str_contains($msg, 'getaddrinfo'))
            $msg = "Host '{$host}' não encontrado. Verifique o endereço do servidor.";
        elseif (str_contains($msg, 'Unknown database'))
            $msg = "Banco de dados '{$dbname}' não encontrado. Crie-o primeiro no painel da hospedagem e informe o nome completo (ex: usuario_sitecatalogo).";
        return ['ok' => false, 'error' => $msg];
    }
}

/**
 * Valida que o banco existe e está acessível.
 * NÃO cria o banco — em hospedagem compartilhada (Hostgator, etc.) o banco
 * deve ser criado manualmente no cPanel com o prefixo do usuário.
 */
function validate_database(PDO $pdo, string $dbname): array {
    try {
        $pdo->exec("USE `{$dbname}`");
        return ['ok' => true, 'error' => null];
    } catch (PDOException $e) {
        $msg = $e->getMessage();
        if (str_contains($msg, 'Access denied') || str_contains($msg, 'Unknown database')) {
            return ['ok' => false, 'error' => "Banco de dados '{$dbname}' não encontrado ou sem permissão de acesso. No cPanel/Hostgator, crie o banco manualmente primeiro e informe o nome completo (ex: usuario_sitecatalogo)."];
        }
        return ['ok' => false, 'error' => "Erro ao acessar banco '{$dbname}': {$msg}"];
    }
}

/**
 * Executa o schema.sql externo e insere dados iniciais.
 */
function run_schema(PDO $pdo, string $admin_nome, string $admin_email, string $admin_senha): array {
    $errors = [];
    try {
        $schema_file = __DIR__ . '/schema.sql';
        if (!file_exists($schema_file)) {
            return ["Arquivo schema.sql não encontrado na pasta /install/. Verifique se o arquivo foi enviado corretamente."];
        }

        $sql = file_get_contents($schema_file);
        if (empty($sql)) {
            return ["Arquivo schema.sql está vazio."];
        }

        $pdo->exec("SET NAMES utf8mb4");
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");

        // Remove comentários de bloco /* */ e de linha --
        $sql = preg_replace('/\/\*.*?\*\//s', '', $sql);
        $sql = preg_replace('/--[^\n]*/', '', $sql);

        // Divide em statements separados por ;
        $statements = array_filter(array_map('trim', explode(';', $sql)));

        foreach ($statements as $stmt) {
            if (empty($stmt)) continue;
            // Pula SETs isolados que podem causar erro
            if (preg_match('/^SET\s+/i', $stmt) && !preg_match('/^SET\s+FOREIGN_KEY_CHECKS/i', $stmt)) {
                continue;
            }
            try {
                $pdo->exec($stmt);
            } catch (PDOException $e) {
                // Ignora erros de "tabela já existe" ou "banco já existe"
                $msg = $e->getMessage();
                if (str_contains($msg, 'already exists') || str_contains($msg, 'Duplicate entry')) {
                    continue;
                }
                $errors[] = "Erro ao executar schema: " . $msg;
            }
        }

        // ── Usuário admin ────────────────────────────────────────────
        $hash = password_hash($admin_senha, PASSWORD_BCRYPT);
        $st = $pdo->prepare("INSERT INTO sc_usuarios (nome, email, senha, role, status) VALUES (?, ?, ?, 'admin', 'ativo')");
        $st->execute([$admin_nome, $admin_email, $hash]);

    } catch (PDOException $e) {
        $errors[] = $e->getMessage();
    }
    return $errors;
}

function generate_config(string $host, string $user, string $pass, string $dbname, string $site_url): string {
    $secret = bin2hex(random_bytes(32));
    $date   = date('d/m/Y H:i');
    return <<<PHP
<?php
/**
 * SiteCatalogo - Configuração
 * Gerado automaticamente pelo instalador em: {$date}
 * NÃO edite este arquivo manualmente a menos que saiba o que está fazendo.
 */

// ── Banco de dados ──────────────────────────────────────────────────
define('DB_HOST',   '{$host}');
define('DB_NAME',   '{$dbname}');
define('DB_USER',   '{$user}');
define('DB_PASS',   '{$pass}');
define('DB_PREFIX', 'sc_');

// ── URLs ────────────────────────────────────────────────────────────
define('SITE_URL',    '{$site_url}');
define('ADMIN_URL',   '{$site_url}/admin/');
define('ASSETS_URL',  '{$site_url}/assets/');
define('UPLOADS_URL', '{$site_url}/uploads/');

// ── Caminhos ────────────────────────────────────────────────────────
if (!defined('ROOT_PATH'))    define('ROOT_PATH',    __DIR__);
if (!defined('UPLOADS_PATH')) define('UPLOADS_PATH', __DIR__ . '/uploads');

// ── Segurança ───────────────────────────────────────────────────────
define('SECRET_KEY',   '{$secret}');
define('SESSION_NAME', 'sc2_session');

// ── Sistema ─────────────────────────────────────────────────────────
define('SITE_NAME',        'SiteCatalogo');
define('SITE_DESCRIPTION', '');
define('WHATSAPP',         '');
define('WHATSAPP_DEFAULT_MSG', 'Olá! Recebi seu orçamento e entrarei em contato em breve.');

if (!defined('ADMIN_ITEMS_PER_PAGE')) define('ADMIN_ITEMS_PER_PAGE', 20);
if (!defined('ITEMS_PER_PAGE'))       define('ITEMS_PER_PAGE', ADMIN_ITEMS_PER_PAGE);
PHP;
}

function write_config(string $content): bool {
    return file_put_contents(ROOT_PATH . '/config.php', $content) !== false;
}

// ─── Processamento dos POSTs ──────────────────────────────────────────

$step   = (int)($_GET['step'] ?? 1);
$errors = [];
$success = false;

// STEP 2 — Testar conexão
if ($step === 2 && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $db_host  = trim($_POST['db_host'] ?? 'localhost');
    $db_user  = trim($_POST['db_user'] ?? '');
    $db_pass  = $_POST['db_pass'] ?? '';
    $db_name  = trim($_POST['db_name'] ?? '');

    if (empty($db_host)) {
        $errors[] = 'O host do MySQL é obrigatório.';
    }
    if (empty($db_user)) {
        $errors[] = 'O usuário do banco é obrigatório.';
    }
    if (empty($db_name)) {
        $errors[] = 'O nome do banco de dados é obrigatório.';
    }

    if (empty($errors)) {
        // Testa conexão COM o banco informado (valida que ele existe)
        $result = test_db($db_host, $db_user, $db_pass, $db_name);
        if (!$result['ok']) {
            $errors[] = $result['error'];
            $step = 2;
        } else {
            session_start();
            $_SESSION['install_db'] = compact('db_host','db_user','db_pass','db_name');
            header('Location: ?step=3'); exit;
        }
    }
}

// STEP 4 — Instalar
if ($step === 4 && $_SERVER['REQUEST_METHOD'] === 'POST') {
    session_start();
    $db = $_SESSION['install_db'] ?? [];
    $admin_nome  = trim($_POST['admin_nome'] ?? 'Administrador');
    $admin_email = trim($_POST['admin_email'] ?? '');
    $admin_senha = $_POST['admin_senha'] ?? '';
    $site_url    = rtrim(trim($_POST['site_url'] ?? ''), '/');

    if (empty($admin_email) || !filter_var($admin_email, FILTER_VALIDATE_EMAIL))
        $errors[] = 'E-mail inválido.';
    if (strlen($admin_senha) < 8)
        $errors[] = 'Senha deve ter pelo menos 8 caracteres.';
    if (empty($site_url))
        $errors[] = 'URL do site é obrigatória.';
    if (empty($db['db_name']))
        $errors[] = 'Dados do banco não encontrados. Volte ao passo 2.';

    if (empty($errors)) {
        // Reconecta com o banco informado
        $conn = test_db($db['db_host'], $db['db_user'], $db['db_pass'], $db['db_name']);
        if (!$conn['ok']) { 
            $errors[] = $conn['error']; 
        } else {
            $pdo = $conn['pdo'];
            // Valida que o banco existe e está acessível
            $db_result = validate_database($pdo, $db['db_name']);
            if (!$db_result['ok']) {
                $errors[] = $db_result['error'];
            } else {
                $schema_errors = run_schema($pdo, $admin_nome, $admin_email, $admin_senha);
                if (!empty($schema_errors)) {
                    $errors = array_merge($errors, $schema_errors);
                } else {
                    $config = generate_config($db['db_host'], $db['db_user'], $db['db_pass'], $db['db_name'], $site_url);
                    if (!write_config($config)) {
                        $errors[] = 'Não foi possível criar o arquivo config.php. Verifique as permissões da pasta raiz (chmod 755 ou 777).';
                    } else {
                        unset($_SESSION['install_db']);
                        $step = 5;
                        $success = true;
                    }
                }
            }
        }
    }
}

// Detectar URL automaticamente (apenas sugestão, usuário pode editar)
$detected_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http')
    . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Instalação — SiteCatalogo</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Inter',sans-serif;background:linear-gradient(135deg,#eff6ff 0%,#f0fdf4 100%);min-height:100vh;display:flex;align-items:center;justify-content:center;padding:24px;}
.wrap{width:100%;max-width:560px;}
.logo{display:flex;align-items:center;gap:12px;justify-content:center;margin-bottom:32px;}
.logo-icon{width:52px;height:52px;background:linear-gradient(135deg,#3b82f6,#6366f1);border-radius:14px;display:flex;align-items:center;justify-content:center;box-shadow:0 8px 24px rgba(59,130,246,.35);}
.logo-text{font-size:1.5rem;font-weight:800;color:#0f172a;}
.logo-text span{color:#3b82f6;}
.card{background:#fff;border-radius:20px;box-shadow:0 4px 40px rgba(0,0,0,.10);overflow:hidden;}
.card-header{background:linear-gradient(135deg,#3b82f6,#6366f1);padding:28px 32px;color:#fff;}
.card-header h2{font-size:1.25rem;font-weight:700;margin-bottom:4px;}
.card-header p{font-size:0.875rem;opacity:.85;}
.steps{display:flex;gap:0;padding:0 32px;border-bottom:1px solid #f1f5f9;background:#f8fafc;}
.step-item{display:flex;align-items:center;gap:8px;padding:14px 0;flex:1;font-size:0.8rem;font-weight:500;color:#94a3b8;border-bottom:2px solid transparent;transition:all .2s;}
.step-item.active{color:#3b82f6;border-bottom-color:#3b82f6;}
.step-item.done{color:#22c55e;}
.step-num{width:22px;height:22px;border-radius:50%;background:#e2e8f0;display:flex;align-items:center;justify-content:center;font-size:0.7rem;font-weight:700;flex-shrink:0;transition:all .2s;}
.step-item.active .step-num{background:#3b82f6;color:#fff;}
.step-item.done .step-num{background:#22c55e;color:#fff;}
.card-body{padding:32px;}
.form-group{margin-bottom:20px;}
label{display:block;font-size:0.875rem;font-weight:600;color:#374151;margin-bottom:6px;}
label .hint{font-weight:400;color:#9ca3af;font-size:0.8rem;margin-left:6px;}
input[type=text],input[type=password],input[type=email],input[type=url]{width:100%;padding:11px 14px;border:1.5px solid #e5e7eb;border-radius:10px;font-size:0.9rem;font-family:inherit;outline:none;transition:border-color .2s,box-shadow .2s;color:#111827;}
input:focus{border-color:#3b82f6;box-shadow:0 0 0 3px rgba(59,130,246,.15);}
.input-icon{position:relative;}
.input-icon i{position:absolute;left:13px;top:50%;transform:translateY(-50%);color:#9ca3af;font-size:0.875rem;}
.input-icon input{padding-left:38px;}
.btn{display:inline-flex;align-items:center;justify-content:center;gap:8px;padding:12px 24px;border-radius:10px;font-weight:600;font-size:0.9rem;cursor:pointer;border:none;transition:all .2s;text-decoration:none;font-family:inherit;}
.btn-primary{background:linear-gradient(135deg,#3b82f6,#6366f1);color:#fff;width:100%;margin-top:8px;box-shadow:0 4px 16px rgba(59,130,246,.3);}
.btn-primary:hover{opacity:.92;box-shadow:0 6px 20px rgba(59,130,246,.4);}
.btn-secondary{background:#f1f5f9;color:#475569;width:100%;margin-top:12px;}
.btn-secondary:hover{background:#e2e8f0;}
.alert{padding:12px 16px;border-radius:10px;font-size:0.875rem;margin-bottom:20px;display:flex;align-items:flex-start;gap:10px;}
.alert-error{background:#fef2f2;border:1px solid #fecaca;color:#dc2626;}
.alert-success{background:#f0fdf4;border:1px solid #bbf7d0;color:#16a34a;}
.alert-info{background:#eff6ff;border:1px solid #bfdbfe;color:#1d4ed8;}
.alert-warning{background:#fffbeb;border:1px solid #fde68a;color:#92400e;}
.badge-fixed{display:inline-flex;align-items:center;gap:6px;background:#f0fdf4;border:1px solid #bbf7d0;color:#15803d;font-size:0.8rem;font-weight:600;padding:5px 12px;border-radius:20px;margin-bottom:20px;}
.divider{border:none;border-top:1px solid #f1f5f9;margin:20px 0;}
.success-icon{width:80px;height:80px;background:linear-gradient(135deg,#22c55e,#16a34a);border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 24px;box-shadow:0 8px 30px rgba(34,197,94,.35);}
.access-box{background:#f8fafc;border:1px solid #e2e8f0;border-radius:12px;padding:20px;margin:20px 0;}
.access-row{display:flex;justify-content:space-between;align-items:center;padding:8px 0;border-bottom:1px solid #e2e8f0;font-size:0.875rem;}
.access-row:last-child{border-bottom:none;}
.access-row .key{color:#6b7280;font-weight:500;}
.access-row .val{color:#111827;font-weight:700;font-family:monospace;}
.warn-box{background:#fffbeb;border:1px solid #fde68a;border-radius:10px;padding:14px 16px;margin-top:16px;font-size:0.8rem;color:#92400e;display:flex;gap:10px;}
.env-badge{display:inline-flex;align-items:center;gap:6px;background:#eff6ff;border:1px solid #bfdbfe;color:#1d4ed8;font-size:0.75rem;font-weight:600;padding:4px 10px;border-radius:20px;}
.env-detected{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:16px;}
.guide-box{background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;padding:16px;margin-bottom:20px;}
.guide-box h4{font-size:0.9rem;color:#0f172a;margin-bottom:10px;display:flex;align-items:center;gap:8px;}
.guide-box ol{padding-left:20px;font-size:0.82rem;color:#374151;line-height:2;}
.guide-box li{margin-bottom:4px;}
.guide-box code{background:#e2e8f0;padding:2px 6px;border-radius:4px;font-size:0.78rem;}
@media (max-width:480px){
    .hide-xs{display:none;}
    .steps{padding:0 16px;}
    .card-body{padding:24px;}
}
</style>
</head>
<body>
<div class="wrap">
    <div class="logo">
        <div class="logo-icon">
            <svg width="28" height="28" viewBox="0 0 36 36" fill="none"><path d="M10 24V14l8-5 8 5v10H10z" stroke="white" stroke-width="2.2" fill="none"/><circle cx="18" cy="19" r="2.5" fill="white"/></svg>
        </div>
        <div class="logo-text">Site<span>Catalogo</span>2</div>
    </div>
    <div class="card">
        <div class="card-header">
            <h2><i class="fas fa-magic"></i> Assistente de Instalação</h2>
            <p>Configure o sistema em poucos passos — compatível com qualquer servidor.</p>
        </div>

        <div class="steps">
            <?php
            $steps_labels = ['Boas-vindas','Banco de Dados','Confirmação','Conta Admin','Concluído'];
            for ($i = 1; $i <= 5; $i++):
                $cls = $i < $step ? 'done' : ($i == $step ? 'active' : '');
                $icon = $i < $step ? '<i class="fas fa-check" style="font-size:.65rem;"></i>' : $i;
            ?>
            <div class="step-item <?= $cls ?>">
                <span class="step-num"><?= $icon ?></span>
                <span class="hide-xs"><?= $steps_labels[$i-1] ?></span>
            </div>
            <?php endfor; ?>
        </div>

        <div class="card-body">

        <?php if (!empty($errors)): ?>
        <div class="alert alert-error">
            <i class="fas fa-exclamation-circle" style="flex-shrink:0;margin-top:2px;"></i>
            <div><?= implode('<br>', array_map('htmlspecialchars', $errors)) ?></div>
        </div>
        <?php endif; ?>

        <?php if ($step === 1): ?>
        <!-- STEP 1: Boas-vindas -->
        <h3 style="font-size:1.1rem;font-weight:700;margin-bottom:12px;color:#0f172a;">Bem-vindo à instalação!</h3>
        <p style="color:#6b7280;font-size:0.9rem;margin-bottom:20px;">Este assistente funciona em <strong>qualquer servidor</strong>: localhost, cPanel, Hostgator, Hostinger, VPS, Docker, etc.</p>

        <div class="badge-fixed"><i class="fas fa-lock"></i> Prefixo das tabelas: <strong>sc_</strong></div>

        <div class="alert alert-info">
            <i class="fas fa-info-circle" style="flex-shrink:0;margin-top:2px;"></i>
            <div><strong>Antes de começar, você vai precisar de:</strong><br>
            • <strong>Host do MySQL</strong> (geralmente <code>localhost</code>)<br>
            • <strong>Nome do banco de dados</strong> — deve ser criado <strong>manualmente</strong> no painel da hospedagem<br>
            • <strong>Usuário e senha</strong> do banco (com todos os privilégios)<br>
            • A pasta raiz do projeto deve ter permissão de escrita</div>
        </div>

        <div class="guide-box">
            <h4><i class="fas fa-server" style="color:#3b82f6;"></i> cPanel / Hostgator / Hostinger</h4>
            <ol>
                <li>Acesse o cPanel → <strong>Bancos de Dados MySQL</strong></li>
                <li>Crie o <strong>banco</strong> com o nome desejado (ex: <code>usuario_sitecatalogo</code>)</li>
                <li>Crie o <strong>usuário</strong> e <strong>adicione TODOS os privilégios</strong> ao banco</li>
                <li>Volte aqui e informe o <strong>nome completo</strong> do banco no próximo passo</li>
            </ol>
        </div>

        <div class="guide-box">
            <h4><i class="fas fa-desktop" style="color:#22c55e;"></i> Localhost (Laragon, XAMPP, WAMP)</h4>
            <ol>
                <li>Host: <code>localhost</code></li>
                <li>Usuário: <code>root</code> (XAMPP/WAMP) ou seu usuário (Laragon)</li>
                <li>Senha: geralmente vazia no XAMPP</li>
                <li>Banco: crie via phpMyAdmin ou informe um nome existente</li>
            </ol>
        </div>

        <hr class="divider">

        <div style="font-size:0.8rem;color:#9ca3af;margin-bottom:20px;">
            <strong style="color:#374151;">Hospedagens testadas:</strong>
            Hostgator · Hostinger · Locaweb · KingHost · cPanel · localhost (Laragon/XAMPP/WAMP) · Docker · VPS
        </div>

        <a href="?step=2" class="btn btn-primary"><i class="fas fa-arrow-right"></i> Iniciar Instalação</a>

        <?php elseif ($step === 2): ?>
        <!-- STEP 2: Dados do banco -->
        <h3 style="font-size:1.1rem;font-weight:700;margin-bottom:6px;color:#0f172a;">Configurar Banco de Dados</h3>
        <p style="color:#6b7280;font-size:0.875rem;margin-bottom:16px;">Informe os dados de acesso ao MySQL. O banco deve <strong>já existir</strong> — o instalador não cria automaticamente.</p>

        <div class="alert alert-warning">
            <i class="fas fa-exclamation-triangle" style="flex-shrink:0;margin-top:2px;"></i>
            <div><strong>Atenção:</strong> O instalador <strong>não cria o banco automaticamente</strong>.<br>Em hospedagens compartilhadas (Hostgator, Hostinger, etc.), crie o banco manualmente no cPanel primeiro e informe o <strong>nome completo</strong> aqui.</div>
        </div>

        <form method="POST" action="?step=2">
            <div class="form-group">
                <label>Host do MySQL <span class="hint">— ex: localhost</span></label>
                <div class="input-icon">
                    <i class="fas fa-server"></i>
                    <input type="text" name="db_host" value="<?= htmlspecialchars($_POST['db_host'] ?? 'localhost') ?>" placeholder="localhost" required>
                </div>
            </div>
            <div class="form-group">
                <label>Nome do banco de dados <span class="hint">— obrigatório, deve existir</span></label>
                <div class="input-icon">
                    <i class="fas fa-database"></i>
                    <input type="text" name="db_name" value="<?= htmlspecialchars($_POST['db_name'] ?? '') ?>" placeholder="usuario_sitecatalogo" required>
                </div>
                <div style="margin-top:6px;font-size:0.78rem;color:#9ca3af;">
                    💡 No cPanel/Hostgator, o nome inclui o prefixo do usuário (ex: <code>bf22ac49_sitecatalogo</code>).
                </div>
            </div>
            <div class="form-group">
                <label>Usuário do banco</label>
                <div class="input-icon">
                    <i class="fas fa-user"></i>
                    <input type="text" name="db_user" value="<?= htmlspecialchars($_POST['db_user'] ?? '') ?>" placeholder="root" required>
                </div>
            </div>
            <div class="form-group">
                <label>Senha do banco <span class="hint">— pode ser vazia no localhost</span></label>
                <div class="input-icon">
                    <i class="fas fa-key"></i>
                    <input type="password" name="db_pass" value="<?= htmlspecialchars($_POST['db_pass'] ?? '') ?>" placeholder="••••••••">
                </div>
            </div>
            <button type="submit" class="btn btn-primary"><i class="fas fa-plug"></i> Testar Conexão e Continuar</button>
        </form>

        <?php elseif ($step === 3): ?>
        <!-- STEP 3: Confirmação -->
        <?php session_start(); $db = $_SESSION['install_db'] ?? []; ?>
        <h3 style="font-size:1.1rem;font-weight:700;margin-bottom:12px;color:#0f172a;">Conexão estabelecida! ✅</h3>

        <div class="alert alert-success">
            <i class="fas fa-check-circle" style="flex-shrink:0;"></i>
            <div>Conectado com sucesso ao MySQL via <strong><?= htmlspecialchars($db['db_host'] ?? 'localhost') ?></strong>.<br>
            O banco <strong><?= htmlspecialchars($db['db_name'] ?? '') ?></strong> foi validado e está pronto.</div>
        </div>

        <div class="access-box" style="margin-bottom:0;">
            <div class="access-row"><span class="key">Banco de dados</span><span class="val"><?= htmlspecialchars($db['db_name'] ?? '') ?></span></div>
            <div class="access-row"><span class="key">Prefixo das tabelas</span><span class="val">sc_</span></div>
            <div class="access-row"><span class="key">Host</span><span class="val"><?= htmlspecialchars($db['db_host'] ?? '') ?></span></div>
            <div class="access-row"><span class="key">Usuário</span><span class="val"><?= htmlspecialchars($db['db_user'] ?? '') ?></span></div>
        </div>

        <div class="warn-box">
            <i class="fas fa-exclamation-triangle" style="flex-shrink:0;margin-top:2px;"></i>
            <span>Se o banco já tiver tabelas do SiteCatalogo, elas serão <strong>recriadas do zero</strong>. Dados existentes serão perdidos.</span>
        </div>

        <a href="?step=4" class="btn btn-primary" style="margin-top:20px;"><i class="fas fa-arrow-right"></i> Continuar para Conta Admin</a>

        <?php elseif ($step === 4 && !$success): ?>
        <!-- STEP 4: Conta admin -->
        <h3 style="font-size:1.1rem;font-weight:700;margin-bottom:6px;color:#0f172a;">Criar Conta do Administrador</h3>
        <p style="color:#6b7280;font-size:0.875rem;margin-bottom:20px;">Configure suas credenciais de acesso ao painel.</p>

        <form method="POST" action="?step=4">
            <div class="form-group">
                <label>Seu nome</label>
                <div class="input-icon"><i class="fas fa-user"></i>
                <input type="text" name="admin_nome" value="<?= htmlspecialchars($_POST['admin_nome'] ?? 'Administrador') ?>" placeholder="Seu nome completo" required></div>
            </div>
            <div class="form-group">
                <label>E-mail de acesso</label>
                <div class="input-icon"><i class="fas fa-envelope"></i>
                <input type="email" name="admin_email" value="<?= htmlspecialchars($_POST['admin_email'] ?? '') ?>" placeholder="admin@seusite.com" required></div>
            </div>
            <div class="form-group">
                <label>Senha <span class="hint">— mínimo 8 caracteres</span></label>
                <div class="input-icon"><i class="fas fa-lock"></i>
                <input type="password" name="admin_senha" placeholder="••••••••" required minlength="8"></div>
            </div>
            <div class="form-group">
                <label>URL do site <span class="hint">— sem barra no final</span></label>
                <div class="input-icon"><i class="fas fa-globe"></i>
                <input type="text" name="site_url" value="<?= htmlspecialchars($_POST['site_url'] ?? $detected_url) ?>" placeholder="https://seusite.com.br" required></div>
                <div style="margin-top:6px;font-size:0.78rem;color:#9ca3af;">
                    Detectado automaticamente: <strong><?= htmlspecialchars($detected_url) ?></strong> (você pode editar)
                </div>
            </div>
            <button type="submit" class="btn btn-primary"><i class="fas fa-rocket"></i> Instalar Agora!</button>
        </form>

        <?php elseif ($step === 5 || $success): ?>
        <!-- STEP 5: Sucesso -->
        <div style="text-align:center;">
            <div class="success-icon"><i class="fas fa-check" style="color:#fff;font-size:2.5rem;"></i></div>
            <h3 style="font-size:1.25rem;font-weight:800;color:#0f172a;margin-bottom:8px;">Instalação concluída!</h3>
            <p style="color:#6b7280;font-size:0.875rem;">O SiteCatalogo foi instalado com sucesso.</p>
        </div>

        <div class="access-box">
            <div class="access-row"><span class="key">Painel Admin</span><span class="val">/admin/login.php</span></div>
            <div class="access-row"><span class="key">E-mail</span><span class="val"><?= htmlspecialchars($_POST['admin_email'] ?? '') ?></span></div>
            <div class="access-row"><span class="key">Senha</span><span class="val">a que você definiu</span></div>
        </div>

        <div class="warn-box">
            <i class="fas fa-shield-alt" style="flex-shrink:0;margin-top:2px;"></i>
            <span>Por segurança, <strong>remova ou renomeie a pasta <code>/install/</code></strong> após o primeiro acesso.</span>
        </div>

        <a href="../admin/login.php" class="btn btn-primary" style="margin-top:20px;">
            <i class="fas fa-sign-in-alt"></i> Acessar Painel Admin
        </a>
        <?php endif; ?>

        </div>
    </div>

    <p style="text-align:center;color:#94a3b8;font-size:0.78rem;margin-top:20px;">
        SiteCatalogo v<?= INSTALL_VERSION ?> — Instalador Universal
    </p>
</div>
</body>
</html>