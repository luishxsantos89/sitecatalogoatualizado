<?php
/**
 * SiteCatalogo2 - Configuracoes (v4.0 — Unificacao: Email+Senha unicos, autopreenchimento de provedor)
 * 
 * Correcoes v4.0:
 * - Campo unico de Email/Usuario -> preenche automaticamente SMTP e IMAP
 * - Campo unico de Senha -> preenche automaticamente SMTP e IMAP
 * - Autopreenchimento de provedor -> ao digitar o email, detecta o provedor e preenche todos os servidores
 * - Campos individuais ainda disponiveis em "Configuracao Avancada" (colapsado)
 * - Anti-autofill reforcado: honeypot + random field names + JS clear
 */
require_once __DIR__ . '/includes/functions.php';

// === CONTROLE DE ACESSO ===
require_auth();
if (!check_permission('admin')) {
    header('Location: ' . admin_url());
    exit('Acesso negado.');
}

$page_title = 'Configuracoes';

// ——— Chaves que podem nao existir ainda no banco ———————————————
$chaves_extras = [
    'toast_position', 'produtos_navegacao', 'empresa_sobre', 'empresa_slogan',
    'alerta_sonoro_orcamento', 'produto_visualizacao',
    // SMTP
    'smtp_host','smtp_port','smtp_user','smtp_pass','smtp_encryption','site_nome_email',
    // IMAP
    'imap_host','imap_port','imap_ssl','imap_user','imap_pass','imap_folder',
    'imap_folder_sent','imap_folder_drafts','imap_folder_archive','imap_folder_spam','imap_folder_trash',
    // Assinatura / Layout / Sincronizacao
    'email_assinatura_tipo','email_assinatura_html','email_assinatura_imagem',
    'email_layout','email_sync_auto','email_sync_intervalo',
    // NOVO: campos unificados
    'email_unificado','senha_unificada',
];

// ——— Salvar configuracoes —————————————————————————
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // ANTI-AUTOFILL: pega valor do campo REAL (nome randomico por sessao)
        $session_key = $_SESSION['email_field_key'] ?? 'real';
        $smtp_pass_real = trim($_POST[$session_key . '_smtp_pass'] ?? '');
        $imap_pass_real = trim($_POST[$session_key . '_imap_pass'] ?? '');
        $senha_unificada_real = trim($_POST[$session_key . '_senha_unificada'] ?? '');

        // Se senha unificada foi digitada, aplica em ambos SMTP e IMAP
        if ($senha_unificada_real !== '') {
            $_POST['config']['smtp_pass'] = $senha_unificada_real;
            $_POST['config']['imap_pass'] = $senha_unificada_real;
        }

        // Se vazio, mantem a senha atual (nao apaga)
        if ($smtp_pass_real === '') {
            $smtp_pass_atual = get_config('smtp_pass', '');
            if ($smtp_pass_atual !== '') $_POST['config']['smtp_pass'] = $smtp_pass_atual;
        } else {
            $_POST['config']['smtp_pass'] = $smtp_pass_real;
        }

        if ($imap_pass_real === '') {
            $imap_pass_atual = get_config('imap_pass', '');
            if ($imap_pass_atual !== '') $_POST['config']['imap_pass'] = $imap_pass_atual;
        } else {
            $_POST['config']['imap_pass'] = $imap_pass_real;
        }

        foreach ($_POST['config'] as $chave => $valor) {
            $valor = trim($valor);
            if (in_array($chave, $chaves_extras)) {
                try {
                    $existe = db()->prepare("SELECT COUNT(*) FROM " . table('configuracoes') . " WHERE chave = ?");
                    $existe->execute([$chave]);
                    if ((int)$existe->fetchColumn() > 0) {
                        db()->prepare("UPDATE " . table('configuracoes') . " SET valor = ? WHERE chave = ?")->execute([$valor, $chave]);
                    } else {
                        $chaves_email = ['smtp_host','smtp_port','smtp_user','smtp_pass','smtp_encryption','site_nome_email',
                            'imap_host','imap_port','imap_ssl','imap_user','imap_pass','imap_folder',
                            'imap_folder_sent','imap_folder_drafts','imap_folder_archive','imap_folder_spam','imap_folder_trash',
                            'email_assinatura_tipo','email_assinatura_html','email_assinatura_imagem',
                            'email_layout','email_sync_auto','email_sync_intervalo',
                            'email_unificado','senha_unificada'];
                        if (in_array($chave, $chaves_email)) {
                            $grupo_extra = 'email';
                            $tipo_extra  = in_array($chave, ['smtp_port','imap_port','email_sync_intervalo']) ? 'number'
                                         : (in_array($chave, ['smtp_pass','imap_pass','senha_unificada']) ? 'password'
                                         : (in_array($chave, ['smtp_encryption','imap_ssl','email_assinatura_tipo','email_layout','email_sync_auto']) ? 'select'
                                         : (in_array($chave, ['email_assinatura_html']) ? 'textarea'
                                         : (in_array($chave, ['email_assinatura_imagem']) ? 'file' : 'text'))));
                        } elseif (in_array($chave, ['empresa_sobre','empresa_slogan'])) {
                            $grupo_extra = 'geral';
                            $tipo_extra  = ($chave === 'empresa_sobre') ? 'textarea' : 'text';
                        } else {
                            $grupo_extra = 'aparencia';
                            $tipo_extra  = 'select';
                        }
                        db()->prepare("INSERT INTO " . table('configuracoes') . " (chave, valor, grupo, tipo, ativo, ordem) VALUES (?,?,?,?,1,99)")
                            ->execute([$chave, $valor, $grupo_extra, $tipo_extra]);
                    }
                } catch (Exception $e2) { /* fallback silencioso */ }
            } else {
                set_config($chave, $valor);
            }
        }

        // Upload de arquivos (logo, assinatura em imagem, etc.)
        if (!empty($_FILES['config']['name'])) {
            foreach ($_FILES['config']['name'] as $campo => $nome_arquivo) {
                if (empty($nome_arquivo)) continue;
                $up = handle_upload([
                    'name'     => $_FILES['config']['name'][$campo],
                    'tmp_name' => $_FILES['config']['tmp_name'][$campo],
                    'error'    => $_FILES['config']['error'][$campo],
                ], 'config');
                if ($up) {
                    $old = get_config($campo);
                    if ($old) delete_upload($old);
                    set_config($campo, $up);
                }
            }
        }

        log_activity('update', 'configuracoes', 'Configuracoes atualizadas');
        set_flash('success', 'Configuracoes salvas com sucesso!');
    } catch (Exception $e) {
        set_flash('error', 'Erro: ' . $e->getMessage());
    }
    header('Location: configuracoes.php'); exit;
}

// Gera chave randomica para nomes de campos (anti-autofill)
if (empty($_SESSION['email_field_key'])) {
    $_SESSION['email_field_key'] = 'f' . substr(md5(uniqid()), 0, 8);
}
$fk = $_SESSION['email_field_key'];

// ——— Carregar configuracoes do banco (EXCETO email e seo) ——
$email_chaves_sql = "'email_layout','email_sync_auto','email_sync_intervalo','email_assinatura_tipo','email_assinatura_html','email_assinatura_imagem','smtp_host','smtp_port','smtp_user','smtp_pass','smtp_encryption','site_nome_email','imap_host','imap_port','imap_ssl','imap_user','imap_pass','imap_folder','imap_folder_sent','imap_folder_drafts','imap_folder_archive','imap_folder_spam','imap_folder_trash','email_unificado','senha_unificada'";

$configuracoes = db()->query(
    "SELECT * FROM " . table('configuracoes') . "
     WHERE ativo = 1
       AND grupo NOT IN ('email','seo')
       AND chave NOT IN ('categoria_layout','toast_position','produtos_navegacao','empresa_sobre','empresa_slogan','alerta_sonoro_orcamento','produto_visualizacao'," . $email_chaves_sql . ")
     ORDER BY CASE WHEN grupo='geral' THEN 1 WHEN grupo='contato' THEN 2 WHEN grupo='social' THEN 3 WHEN grupo='aparencia' THEN 4 ELSE 5 END, ordem, id"
)->fetchAll();

$social_icons = [
    'facebook_url'  => ['fab fa-facebook',    '#1877f2'],
    'instagram_url' => ['fab fa-instagram',   '#e4405f'],
    'linkedin_url'  => ['fab fa-linkedin',    '#0a66c2'],
    'youtube_url'   => ['fab fa-youtube',     '#ff0000'],
    'tiktok_url'    => ['fab fa-tiktok',      '#000000'],
    'twitter_url'   => ['fab fa-x-twitter',   '#000000'],
    'pinterest_url' => ['fab fa-pinterest',   '#e60023'],
    'telegram_url'  => ['fab fa-telegram',    '#24a1de'],
    'kwai_url'      => ['fas fa-play-circle', '#ff6a00'],
    'threads_url'   => ['fab fa-threads',     '#000000'],
    'discord_url'   => ['fab fa-discord',     '#5865f2'],
    'snapchat_url'  => ['fab fa-snapchat',    '#fffc00'],
];

$extra_fields = [
    ['chave'=>'empresa_sobre',           'descricao'=>'Sobre a Empresa (texto exibido na secao "Quem Somos")',  'grupo'=>'geral',     'tipo'=>'textarea','valor'=>get_config('empresa_sobre',''),           'ativo'=>1],
    ['chave'=>'empresa_slogan',          'descricao'=>'Slogan / Frase de Destaque da Empresa',                  'grupo'=>'geral',     'tipo'=>'text',    'valor'=>get_config('empresa_slogan',''),           'ativo'=>1],
    ['chave'=>'produto_visualizacao',    'descricao'=>'Visualizacao do Produto ao clicar',                      'grupo'=>'aparencia', 'tipo'=>'select',  'valor'=>get_config('produto_visualizacao','modal'), 'ativo'=>1,
     'opcoes'=>json_encode(['modal'=>'Catalogo Simples (modal) — atual','pagina_individual'=>'Pagina Individual (melhor para SEO)'])],
    ['chave'=>'produtos_navegacao',      'descricao'=>'Navegacao de Produtos',                                  'grupo'=>'aparencia', 'tipo'=>'select',  'valor'=>get_config('produtos_navegacao','paginacao'),'ativo'=>1,
     'opcoes'=>json_encode(['paginacao'=>'Paginacao (Anterior / Proximo)','scroll_infinito'=>'Scroll Infinito'])],
    ['chave'=>'toast_position',          'descricao'=>'Posicao do Toast de Produto Adicionado',                 'grupo'=>'aparencia', 'tipo'=>'select',  'valor'=>get_config('toast_position','bottom-right'), 'ativo'=>1,
     'opcoes'=>json_encode(['bottom-left'=>'Rodape Esquerdo','bottom-center'=>'Rodape Centro','bottom-right'=>'Rodape Direito'])],
    ['chave'=>'alerta_sonoro_orcamento', 'descricao'=>'Alerta Sonoro — Novos Orcamentos',                       'grupo'=>'aparencia', 'tipo'=>'select',  'valor'=>get_config('alerta_sonoro_orcamento','1'),   'ativo'=>1,
     'opcoes'=>json_encode(['1'=>'Ativado','0'=>'Desativado'])],
];

$configuracoes = array_merge($configuracoes, $extra_fields);
usort($configuracoes, function ($a, $b) {
    $order = ['geral'=>1,'contato'=>2,'social'=>3,'aparencia'=>4];
    $oa = $order[$a['grupo']] ?? 5;
    $ob = $order[$b['grupo']] ?? 5;
    return $oa !== $ob ? $oa - $ob : (($a['ordem']??0) - ($b['ordem']??0));
});

// ——— Carregar configs de EMAIL separadamente ——
$email_unificado = get_config('email_unificado', '');
$senha_unificada = get_config('senha_unificada', '');

$email_configs = [
    'email_unificado' => $email_unificado,
    'senha_unificada' => $senha_unificada,
    'smtp_host' => get_config('smtp_host',''),
    'smtp_port' => get_config('smtp_port',587),
    'smtp_user' => get_config('smtp_user',''),
    'smtp_pass' => get_config('smtp_pass',''),
    'smtp_encryption' => get_config('smtp_encryption','tls'),
    'site_nome_email' => get_config('site_nome_email',''),
    'imap_host' => get_config('imap_host',''),
    'imap_port' => get_config('imap_port',993),
    'imap_ssl' => get_config('imap_ssl','1'),
    'imap_user' => get_config('imap_user',''),
    'imap_pass' => get_config('imap_pass',''),
    'imap_folder' => get_config('imap_folder','INBOX'),
    'imap_folder_sent' => get_config('imap_folder_sent','Sent'),
    'imap_folder_drafts' => get_config('imap_folder_drafts','Drafts'),
    'imap_folder_archive' => get_config('imap_folder_archive','Archive'),
    'imap_folder_spam' => get_config('imap_folder_spam','Spam'),
    'imap_folder_trash' => get_config('imap_folder_trash','Trash'),
    'email_sync_auto' => get_config('email_sync_auto','0'),
    'email_sync_intervalo' => get_config('email_sync_intervalo',5),
    'email_assinatura_tipo' => get_config('email_assinatura_tipo','nenhuma'),
    'email_assinatura_html' => get_config('email_assinatura_html',''),
    'email_assinatura_imagem' => get_config('email_assinatura_imagem',''),
    'email_layout' => get_config('email_layout','lista'),
];

require_once __DIR__ . '/includes/header.php';
?>

<style>
.config-tabs {
    display: flex;
    gap: 4px;
    border-bottom: 2px solid var(--gray-200);
    margin-bottom: 24px;
    padding: 0 4px;
    flex-wrap: wrap;
}
.config-tab {
    padding: 12px 20px;
    border: none;
    background: transparent;
    color: var(--gray-500);
    font-weight: 600;
    font-size: 0.875rem;
    cursor: pointer;
    border-bottom: 3px solid transparent;
    transition: all 0.2s;
    display: flex;
    align-items: center;
    gap: 8px;
    border-radius: 8px 8px 0 0;
}
.config-tab:hover {
    color: var(--primary);
    background: var(--gray-50);
}
.config-tab.active {
    color: var(--primary);
    border-bottom-color: var(--primary);
    background: var(--primary-50);
}
.config-tab .badge {
    background: var(--gray-200);
    color: var(--gray-600);
    font-size: 0.7rem;
    padding: 2px 8px;
    border-radius: 12px;
}
.config-tab.active .badge {
    background: var(--primary);
    color: #fff;
}
.config-panel {
    display: none;
    animation: fadeIn 0.3s ease;
}
.config-panel.active {
    display: block;
}
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(8px); }
    to { opacity: 1; transform: translateY(0); }
}
.config-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 20px;
}
.config-grid .form-group.full-width {
    grid-column: 1 / -1;
}
.email-section {
    margin-bottom: 28px;
    padding: 20px;
    background: #fff;
    border-radius: 12px;
    border: 1px solid var(--gray-200);
}
.email-section-title {
    font-size: 0.8rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    color: var(--gray-500);
    margin-bottom: 16px;
    display: flex;
    align-items: center;
    gap: 8px;
}
.email-section-title i { font-size: 1rem; }
.email-info-box {
    padding: 12px 16px;
    border-radius: 8px;
    font-size: 0.82rem;
    margin-bottom: 16px;
    display: flex;
    align-items: flex-start;
    gap: 10px;
}
.email-info-box.smtp {
    background: #eff6ff;
    border-left: 3px solid #3b82f6;
    color: #1e40af;
}
.email-info-box.imap {
    background: #f0fdf4;
    border-left: 3px solid #22c55e;
    color: #166534;
}
.email-info-box.sync {
    background: #fffbeb;
    border-left: 3px solid #f59e0b;
    color: #92400e;
}
.email-info-box.signature {
    background: #f5f3ff;
    border-left: 3px solid #8b5cf6;
    color: #5b21b6;
}
.email-info-box.unified {
    background: #ecfdf5;
    border-left: 3px solid #10b981;
    color: #065f46;
}
.provider-selector {
    margin-bottom: 20px;
    padding: 16px;
    background: #f5f3ff;
    border: 1px solid #c4b5fd;
    border-radius: 10px;
}
.provider-selector label {
    display: block;
    font-size: 0.8rem;
    font-weight: 700;
    color: #5b21b6;
    margin-bottom: 8px;
}
.provider-selector select {
    padding: 10px 14px;
    border: 1px solid #c4b5fd;
    border-radius: 8px;
    font-size: 0.875rem;
    background: #fff;
    width: 100%;
    max-width: 400px;
}
.provider-dica {
    margin-top: 8px;
    font-size: 0.78rem;
    color: #6d28d9;
    display: none;
}
.provider-dica.visible { display: block; }
.test-box {
    padding: 16px;
    background: var(--gray-50);
    border-radius: 10px;
    display: flex;
    align-items: center;
    gap: 16px;
    flex-wrap: wrap;
    margin-top: 16px;
}
.test-box label {
    font-size: 0.85rem;
    font-weight: 600;
    color: var(--gray-700);
}
.status-indicator {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 0.78rem;
    font-weight: 600;
}
.status-indicator.ok { background: #dcfce7; color: #166534; }
.status-indicator.error { background: #fee2e2; color: #991b1b; }
.status-indicator.warning { background: #fef3c7; color: #92400e; }

.password-field {
    position: relative;
}
.password-field input[type="text"].pass-mask {
    padding-right: 44px !important;
    -webkit-text-security: disc !important;
    text-security: disc !important;
}
.password-field input[type="text"].pass-mask.revealed {
    -webkit-text-security: none !important;
    text-security: none !important;
}
.password-field .toggle-btn {
    position: absolute;
    right: 10px;
    top: 50%;
    transform: translateY(-50%);
    background: none;
    border: none;
    cursor: pointer;
    color: var(--gray-400);
    padding: 4px;
    font-size: 0.9rem;
}
.password-field .toggle-btn:hover { color: var(--primary); }

.folders-details { margin-top: 12px; }
.folders-details summary {
    cursor: pointer;
    font-size: 0.85rem;
    color: var(--gray-600);
    font-weight: 600;
    padding: 8px 0;
    user-select: none;
    list-style: none;
}
.folders-details summary::-webkit-details-marker { display: none; }
.folders-details summary::before {
    content: '▶';
    margin-right: 6px;
    font-size: 0.7rem;
    transition: transform 0.2s;
    display: inline-block;
}
.folders-details[open] summary::before { transform: rotate(90deg); }
.folders-details .folders-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 12px;
    padding: 16px;
    background: var(--gray-50);
    border-radius: 8px;
}

.unified-section {
    background: linear-gradient(135deg, #f0fdf4 0%, #ecfdf5 100%);
    border: 2px solid #10b981;
    border-radius: 12px;
    padding: 24px;
    margin-bottom: 24px;
}
.unified-section .section-header {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 20px;
}
.unified-section .section-header i {
    font-size: 1.4rem;
    color: #10b981;
}
.unified-section .section-header h3 {
    font-size: 1.1rem;
    font-weight: 700;
    color: #065f46;
    margin: 0;
}
.unified-section .section-header p {
    font-size: 0.82rem;
    color: #059669;
    margin: 0;
}
.unified-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
}
.unified-grid .form-group:first-child {
    grid-column: 1 / -1;
}
@media (max-width: 768px) {
    .unified-grid { grid-template-columns: 1fr; }
    .config-grid { grid-template-columns: 1fr; }
    .config-tabs {
        overflow-x: auto;
        flex-wrap: nowrap;
        -webkit-overflow-scrolling: touch;
    }
    .config-tab {
        white-space: nowrap;
        padding: 10px 14px;
        font-size: 0.8rem;
    }
}

.advanced-section {
    margin-top: 24px;
    border: 1px dashed var(--gray-300);
    border-radius: 10px;
    overflow: hidden;
}
.advanced-section summary {
    padding: 14px 20px;
    background: var(--gray-50);
    font-size: 0.85rem;
    font-weight: 600;
    color: var(--gray-600);
    cursor: pointer;
    list-style: none;
    user-select: none;
    display: flex;
    align-items: center;
    gap: 8px;
}
.advanced-section summary::-webkit-details-marker { display: none; }
.advanced-section summary::before {
    content: '▶';
    font-size: 0.7rem;
    transition: transform 0.2s;
    display: inline-block;
}
.advanced-section[open] summary::before { transform: rotate(90deg); }
.advanced-section .advanced-content {
    padding: 20px;
    background: #fff;
}

.color-field {
    display: flex;
    align-items: center;
    gap: 10px;
}
.color-field input[type="color"] {
    width: 60px;
    height: 40px;
    padding: 2px;
    border: 1px solid var(--gray-200);
    border-radius: 6px;
    cursor: pointer;
}
.color-field .color-value {
    font-size: 0.8rem;
    color: var(--gray-500);
    font-family: monospace;
}
.social-field label {
    display: flex;
    align-items: center;
    gap: 8px;
}
.social-field label i {
    font-size: 1.1rem;
    width: 20px;
    text-align: center;
}
</style>

<div class="page-header">
    <h1><i class="fas fa-cog"></i> Configuracoes</h1>
</div>

<div class="card">
    <div class="card-body">
        <form method="POST" enctype="multipart/form-data" autocomplete="off" id="configForm">
            <input type="password" name="fake_pass" style="position:absolute;left:-9999px;opacity:0;" tabindex="-1" autocomplete="new-password">

            <div class="config-tabs">
                <button type="button" class="config-tab active" data-tab="geral" onclick="switchTab('geral')">
                    <i class="fas fa-building"></i> Geral
                </button>
                <button type="button" class="config-tab" data-tab="contato" onclick="switchTab('contato')">
                    <i class="fas fa-address-card"></i> Contato
                </button>
                <button type="button" class="config-tab" data-tab="social" onclick="switchTab('social')">
                    <i class="fas fa-share-nodes"></i> Redes Sociais
                </button>
                <button type="button" class="config-tab" data-tab="aparencia" onclick="switchTab('aparencia')">
                    <i class="fas fa-paint-brush"></i> Aparência
                </button>
                <button type="button" class="config-tab" data-tab="email" onclick="switchTab('email')">
                    <i class="fas fa-envelope"></i> Email
                    <?php 
                    $email_ok = !empty($email_configs['email_unificado']) && !empty($email_configs['senha_unificada']);
                    ?>
                    <span class="badge" id="emailBadge" style="background: <?php echo $email_ok ? '#22c55e' : '#ef4444'; ?>;">
                        <?php echo $email_ok ? 'OK' : '!'; ?>
                    </span>
                </button>
            </div>

            <div id="tab-geral" class="config-panel active">
                <div class="config-grid">
                    <?php foreach ($configuracoes as $cfg): ?>
                        <?php if ($cfg['grupo'] !== 'geral') continue; ?>
                        <div class="form-group <?php echo in_array($cfg['tipo'], ['textarea','file']) ? 'full-width' : ''; ?>">
                            <label><?php echo sanitize($cfg['descricao'] ?: $cfg['chave']); ?></label>
                            <?php renderField($cfg); ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div id="tab-contato" class="config-panel">
                <div class="config-grid">
                    <?php foreach ($configuracoes as $cfg): ?>
                        <?php if ($cfg['grupo'] !== 'contato') continue; ?>
                        <div class="form-group <?php echo in_array($cfg['tipo'], ['textarea','file']) ? 'full-width' : ''; ?>">
                            <label><?php echo sanitize($cfg['descricao'] ?: $cfg['chave']); ?></label>
                            <?php renderField($cfg); ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div id="tab-social" class="config-panel">
                <div class="config-grid">
                    <?php foreach ($configuracoes as $cfg): ?>
                        <?php if ($cfg['grupo'] !== 'social') continue; ?>
                        <div class="form-group social-field">
                            <label>
                                <?php if (isset($social_icons[$cfg['chave']])): [$ico,$cor] = $social_icons[$cfg['chave']]; ?>
                                    <i class="<?php echo $ico; ?>" style="color:<?php echo $cor; ?>"></i>
                                <?php endif; ?>
                                <?php echo sanitize($cfg['descricao'] ?: $cfg['chave']); ?>
                            </label>
                            <?php renderField($cfg); ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div id="tab-aparencia" class="config-panel">
                <div class="config-grid">
                    <?php foreach ($configuracoes as $cfg): ?>
                        <?php if ($cfg['grupo'] !== 'aparencia') continue; ?>
                        <div class="form-group <?php echo in_array($cfg['tipo'], ['textarea','file']) ? 'full-width' : ''; ?>">
                            <label><?php echo sanitize($cfg['descricao'] ?: $cfg['chave']); ?></label>
                            <?php renderField($cfg); ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- ABA: EMAIL (v4.0) -->
            <div id="tab-email" class="config-panel">

                <div class="unified-section">
                    <div class="section-header">
                        <i class="fas fa-bolt"></i>
                        <div>
                            <h3>Configuracao Rapida</h3>
                            <p>Digite seu email e senha. O sistema detecta o provedor e preenche tudo automaticamente.</p>
                        </div>
                    </div>
                    <div class="unified-grid">
                        <div class="form-group">
                            <label><i class="fas fa-envelope" style="color: #10b981; margin-right: 6px;"></i> Email / Usuario <small style="color:var(--gray-400)">sera usado para SMTP e IMAP</small></label>
                            <input type="email" 
                                   id="email_unificado" 
                                   name="config[email_unificado]" 
                                   value="<?php echo sanitize($email_configs['email_unificado']); ?>" 
                                   placeholder="contato@seudominio.com"
                                   oninput="processarEmailUnificado(this.value)"
                                   autocomplete="off"
                                   style="font-size: 1rem; padding: 12px 14px;">
                        </div>
                        <div class="form-group">
                            <label><i class="fas fa-lock" style="color: #10b981; margin-right: 6px;"></i> Senha <small style="color:var(--gray-400)">sera usada para SMTP e IMAP</small></label>
                            <div class="password-field">
                                <input type="text" 
                                       id="senha_unificada"
                                       name="<?php echo $fk; ?>_senha_unificada"
                                       class="pass-mask"
                                       value="" 
                                       placeholder="Digite a senha do seu email"
                                       autocomplete="off"
                                       autocorrect="off"
                                       autocapitalize="off"
                                       spellcheck="false"
                                       oninput="sincronizarSenhaUnificada(this.value)"
                                       style="font-size: 1rem; padding: 12px 14px;">
                                <button type="button" class="toggle-btn" onclick="toggleSenha('senha_unificada', this)">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                            <small style="color:var(--gray-400);font-size:0.75rem;margin-top:4px;display:block;">
                                <i class="fas fa-info-circle"></i> 
                                <?php if ($email_configs['senha_unificada']): ?>
                                    Senha ja salva. Digite apenas se quiser alterar.
                                <?php else: ?>
                                    Digite a senha do seu webmail para salvar.
                                <?php endif; ?>
                            </small>
                        </div>
                    </div>
                    <div id="unified_status" style="margin-top: 16px; display: none;">
                        <div class="email-info-box unified">
                            <i class="fas fa-check-circle" style="margin-top: 2px;"></i>
                            <div>
                                <strong>Provedor detectado:</strong> <span id="provedor_detectado">-</span><br>
                                <small>Servidores SMTP e IMAP preenchidos automaticamente.</small>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="email-section" style="background: #f5f3ff;">
                    <div class="email-section-title" style="color: #5b21b6;">
                        <i class="fas fa-magic" style="color: #8b5cf6;"></i> Preenchimento Rapido por Provedor
                    </div>
                    <div class="provider-selector">
                        <label><i class="fas fa-server"></i> Selecione seu provedor de email</label>
                        <select id="provedor_select" onchange="aplicarProvedor()">
                            <option value="">Selecione um provedor...</option>
                            <option value="gmail">&#128231; Gmail (Google)</option>
                            <option value="outlook">&#128231; Outlook / Hotmail / Office365 (Microsoft)</option>
                            <option value="yahoo">&#128231; Yahoo Mail</option>
                            <option value="icloud">&#128231; Apple Mail (iCloud)</option>
                            <option value="proton">&#128274; Proton Mail</option>
                            <option value="zoho">&#128231; Zoho Mail</option>
                            <option value="aol">&#128231; AOL Mail</option>
                            <option value="gmx">&#128231; GMX Mail</option>
                            <option value="yandex">&#128231; Yandex Mail</option>
                            <option value="titan">&#128231; Titan Mail (Hostinger)</option>
                            <option value="cpanel">&#127970; Dominio Proprio (cPanel / Hostgator / etc.)</option>
                        </select>
                        <p id="provedor_dica" class="provider-dica"></p>
                    </div>
                </div>

                <div class="email-section">
                    <div class="email-section-title">
                        <i class="fas fa-table-columns" style="color: #8b5cf6;"></i> Layout da Caixa de Email
                    </div>
                    <div class="email-info-box signature">
                        <i class="fas fa-circle-info" style="margin-top: 2px;"></i>
                        <div>
                            Escolha como a lista de emails sera exibida no painel. 
                            <strong>Lista</strong> = classico (clica para abrir). 
                            <strong>Dividido</strong> = lista + leitura lado a lado (estilo Outlook/Gmail).
                        </div>
                    </div>
                    <div class="config-grid" style="max-width: 400px;">
                        <div class="form-group">
                            <label>Layout padrao da caixa de email</label>
                            <select name="config[email_layout]" id="email_layout">
                                <option value="lista" <?php echo selected($email_configs['email_layout'],'lista'); ?>>&#128203; Lista (classico)</option>
                                <option value="dividido" <?php echo selected($email_configs['email_layout'],'dividido'); ?>>&#9707; Dividido (lista + leitura lado a lado)</option>
                            </select>
                        </div>
                    </div>
                </div>

                <input type="hidden" id="smtp_user" name="config[smtp_user]" value="<?php echo sanitize($email_configs['smtp_user']); ?>">
                <input type="hidden" id="imap_user" name="config[imap_user]" value="<?php echo sanitize($email_configs['imap_user']); ?>">
                <input type="hidden" id="smtp_host" name="config[smtp_host]" value="<?php echo sanitize($email_configs['smtp_host']); ?>">
                <input type="hidden" id="smtp_port" name="config[smtp_port]" value="<?php echo (int)$email_configs['smtp_port'] ?: 587; ?>">
                <input type="hidden" id="smtp_encryption" name="config[smtp_encryption]" value="<?php echo sanitize($email_configs['smtp_encryption']); ?>">
                <input type="hidden" id="imap_host" name="config[imap_host]" value="<?php echo sanitize($email_configs['imap_host']); ?>">
                <input type="hidden" id="imap_port" name="config[imap_port]" value="<?php echo (int)$email_configs['imap_port'] ?: 993; ?>">
                <input type="hidden" id="imap_ssl" name="config[imap_ssl]" value="<?php echo sanitize($email_configs['imap_ssl']); ?>">

                <div class="email-section">
                    <div class="email-section-title">
                        <i class="fas fa-vial" style="color: #f59e0b;"></i> Testar Conexoes
                    </div>
                    <div class="config-grid" style="grid-template-columns: 1fr 1fr;">
                        <div class="test-box" style="margin-top: 0;">
                            <div style="flex: 1; min-width: 250px;">
                                <label>Testar envio SMTP</label>
                                <input type="email" id="smtp_test_email" placeholder="digite um email para testar" 
                                       style="width: 100%; margin-top: 4px;">
                            </div>
                            <button type="button" class="btn btn-outline btn-sm" onclick="testarSmtp()">
                                <i class="fas fa-paper-plane"></i> Enviar teste
                            </button>
                            <span id="smtp_status"></span>
                        </div>
                        <div class="test-box" style="margin-top: 0;">
                            <div style="font-weight: 600; color: var(--gray-700);">
                                <i class="fas fa-plug" style="color: #10b981; margin-right: 4px;"></i>Testar conexao IMAP
                            </div>
                            <button type="button" class="btn btn-outline btn-sm" onclick="testarImap()" 
                                    <?php echo !function_exists('imap_open') ? 'disabled title="Extensao IMAP nao disponivel"' : ''; ?>>
                                <i class="fas fa-sync"></i> Testar agora
                            </button>
                            <span id="imap_status"></span>
                        </div>
                    </div>
                </div>

                <div class="email-section">
                    <div class="email-section-title">
                        <i class="fas fa-clock" style="color: #f59e0b;"></i> Sincronizacao Automatica
                    </div>
                    <div class="email-info-box sync">
                        <i class="fas fa-circle-info" style="margin-top: 2px;"></i>
                        <div>
                            Quando ativada, o sistema verifica novos emails sozinho enquanto a pagina estiver aberta. 
                            Para sincronizar com o navegador fechado, configure o <code>email_cron.php</code> no agendador de tarefas do servidor.
                        </div>
                    </div>
                    <div class="config-grid" style="max-width: 600px;">
                        <div class="form-group">
                            <label>Sincronizacao automatica</label>
                            <select name="config[email_sync_auto]">
                                <option value="0" <?php echo selected($email_configs['email_sync_auto'],'0'); ?>>Desativada</option>
                                <option value="1" <?php echo selected($email_configs['email_sync_auto'],'1'); ?>>Ativada</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Intervalo <small style="color:var(--gray-400)">em minutos</small></label>
                            <input type="number" name="config[email_sync_intervalo]" min="1" max="60" 
                                   value="<?php echo (int)$email_configs['email_sync_intervalo'] ?: 5; ?>">
                        </div>
                    </div>
                </div>

                <div class="email-section">
                    <div class="email-section-title">
                        <i class="fas fa-signature" style="color: #8b5cf6;"></i> Assinatura Digital
                    </div>
                    <div class="email-info-box signature">
                        <i class="fas fa-circle-info" style="margin-top: 2px;"></i>
                        <div>
                            A assinatura e adicionada automaticamente ao final de cada email enviado. 
                            Escolha texto formatado (HTML) ou uma imagem (PNG/JPG).
                        </div>
                    </div>
                    <div class="form-group" style="max-width: 400px;">
                        <label>Tipo de assinatura</label>
                        <select name="config[email_assinatura_tipo]" id="assinatura_tipo" onchange="alternarTipoAssinatura(this.value)">
                            <option value="nenhuma" <?php echo selected($email_configs['email_assinatura_tipo'],'nenhuma'); ?>>Nenhuma</option>
                            <option value="html" <?php echo selected($email_configs['email_assinatura_tipo'],'html'); ?>>HTML / Texto formatado</option>
                            <option value="imagem" <?php echo selected($email_configs['email_assinatura_tipo'],'imagem'); ?>>Imagem</option>
                        </select>
                    </div>

                    <div id="assinatura_html_box" style="<?php echo $email_configs['email_assinatura_tipo'] === 'html' ? '' : 'display:none;'; ?>">
                        <label style="display: block; font-size: 0.875rem; font-weight: 600; color: var(--gray-700); margin-bottom: 6px;">
                            Texto da assinatura <small style="color:var(--gray-400); font-weight: 400;">aceita tags HTML: &lt;b&gt;, &lt;br&gt;, &lt;a&gt;</small>
                        </label>
                        <textarea name="config[email_assinatura_html]" rows="5" 
                                  style="width: 100%; padding: 10px 12px; border: 1px solid var(--gray-300); border-radius: 6px; font-size: 0.875rem; font-family: monospace;"
                                  placeholder="Atenciosamente,
Joao Silva
Minha Empresa | (11) 99999-9999"><?php echo sanitize($email_configs['email_assinatura_html']); ?></textarea>
                    </div>

                    <div id="assinatura_imagem_box" style="<?php echo $email_configs['email_assinatura_tipo'] === 'imagem' ? '' : 'display:none;'; ?>">
                        <label style="display: block; font-size: 0.875rem; font-weight: 600; color: var(--gray-700); margin-bottom: 6px;">Imagem da assinatura</label>
                        <input type="file" name="config[email_assinatura_imagem]" accept="image/*">
                        <?php if ($email_configs['email_assinatura_imagem']): ?>
                        <img src="<?php echo uploads_url($email_configs['email_assinatura_imagem']); ?>" 
                             alt="Assinatura atual" 
                             style="max-height: 70px; border-radius: 6px; margin-top: 10px; display: block; border: 1px solid var(--gray-200); padding: 6px;">
                        <p style="font-size: 0.78rem; color: var(--gray-400); margin-top: 6px;">Envie uma nova imagem para substituir.</p>
                        <?php endif; ?>
                    </div>
                </div>

                <details class="advanced-section">
                    <summary>
                        <i class="fas fa-sliders"></i> Configuracao Avancada — Servidores SMTP e IMAP individuais
                        <small style="font-weight: 400; color: var(--gray-400); margin-left: auto;">Para casos especiais (SMTP e IMAP diferentes)</small>
                    </summary>
                    <div class="advanced-content">

                        <div class="email-section" style="margin-bottom: 16px;">
                            <div class="email-section-title">
                                <i class="fas fa-paper-plane" style="color: #3b82f6;"></i> Envio (SMTP) — Avancado
                            </div>
                            <div class="email-info-box smtp">
                                <i class="fas fa-circle-info" style="margin-top: 2px;"></i>
                                <div>
                                    Preencha apenas se o servidor SMTP for diferente do detectado automaticamente.
                                </div>
                            </div>
                            <div class="config-grid">
                                <div class="form-group">
                                    <label>Servidor SMTP <small style="color:var(--gray-400)">ex: smtp.gmail.com</small></label>
                                    <input type="text" id="adv_smtp_host" name="config[smtp_host]" 
                                           value="<?php echo sanitize($email_configs['smtp_host']); ?>" 
                                           placeholder="smtp.gmail.com"
                                           onchange="document.getElementById('smtp_host').value = this.value">
                                </div>
                                <div class="form-group">
                                    <label>Porta <small style="color:var(--gray-400)">587=TLS · 465=SSL · 25=sem</small></label>
                                    <input type="number" id="adv_smtp_port" name="config[smtp_port]" 
                                           value="<?php echo (int)$email_configs['smtp_port'] ?: 587; ?>" 
                                           placeholder="587"
                                           onchange="document.getElementById('smtp_port').value = this.value">
                                </div>
                                <div class="form-group">
                                    <label>Criptografia</label>
                                    <select id="adv_smtp_encryption" name="config[smtp_encryption]"
                                            onchange="document.getElementById('smtp_encryption').value = this.value">
                                        <option value="tls" <?php echo selected($email_configs['smtp_encryption'],'tls'); ?>>TLS (porta 587)</option>
                                        <option value="ssl" <?php echo selected($email_configs['smtp_encryption'],'ssl'); ?>>SSL (porta 465)</option>
                                        <option value="" <?php echo selected($email_configs['smtp_encryption'],''); ?>>Nenhuma (porta 25)</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Usuario SMTP <small style="color:var(--gray-400)">sobrepoem o email unificado</small></label>
                                    <input type="email" id="adv_smtp_user" name="config[smtp_user]" 
                                           value="<?php echo sanitize($email_configs['smtp_user']); ?>" 
                                           placeholder="contato@seudominio.com"
                                           onchange="document.getElementById('smtp_user').value = this.value">
                                </div>
                                <div class="form-group">
                                    <label>Senha SMTP <small style="color:var(--gray-400)">sobrepoem a senha unificada</small></label>
                                    <div class="password-field">
                                        <input type="text" 
                                               id="adv_smtp_pass"
                                               name="<?php echo $fk; ?>_smtp_pass"
                                               class="pass-mask"
                                               value="" 
                                               placeholder="Digite a senha SMTP"
                                               autocomplete="off"
                                               autocorrect="off"
                                               autocapitalize="off"
                                               spellcheck="false">
                                        <button type="button" class="toggle-btn" onclick="toggleSenha('adv_smtp_pass', this)">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </div>
                                    <small style="color:var(--gray-400);font-size:0.75rem;margin-top:4px;display:block;">
                                        <i class="fas fa-info-circle"></i> 
                                        <?php if ($email_configs['smtp_pass']): ?>
                                            Senha ja salva. Digite apenas se quiser alterar.
                                        <?php else: ?>
                                            Digite a senha para salvar.
                                        <?php endif; ?>
                                    </small>
                                </div>
                                <div class="form-group">
                                    <label>Nome do remetente <small style="color:var(--gray-400)">vazio = usa site_nome</small></label>
                                    <input type="text" name="config[site_nome_email]" 
                                           value="<?php echo sanitize($email_configs['site_nome_email']); ?>" 
                                           placeholder="<?php echo sanitize(get_config('site_nome','Minha Empresa')); ?>">
                                </div>
                            </div>
                        </div>

                        <div class="email-section">
                            <div class="email-section-title">
                                <i class="fas fa-inbox" style="color: #10b981;"></i> Recebimento (IMAP) — Avancado
                                <?php if (!function_exists('imap_open')): ?>
                                    <span class="status-indicator error" style="margin-left: auto;">
                                        <i class="fas fa-exclamation-triangle"></i> Extensao IMAP nao habilitada
                                    </span>
                                <?php else: ?>
                                    <span class="status-indicator ok" style="margin-left: auto;">
                                        <i class="fas fa-check-circle"></i> IMAP disponivel
                                    </span>
                                <?php endif; ?>
                            </div>
                            <div class="email-info-box imap">
                                <i class="fas fa-circle-info" style="margin-top: 2px;"></i>
                                <div>
                                    Preencha apenas se o servidor IMAP for diferente do detectado automaticamente.
                                </div>
                            </div>
                            <div class="config-grid">
                                <div class="form-group">
                                    <label>Servidor IMAP <small style="color:var(--gray-400)">ex: imap.gmail.com</small></label>
                                    <input type="text" id="adv_imap_host" name="config[imap_host]" 
                                           value="<?php echo sanitize($email_configs['imap_host']); ?>" 
                                           placeholder="imap.gmail.com"
                                           onchange="document.getElementById('imap_host').value = this.value">
                                </div>
                                <div class="form-group">
                                    <label>Porta <small style="color:var(--gray-400)">993=SSL · 143=TLS/sem</small></label>
                                    <input type="number" id="adv_imap_port" name="config[imap_port]" 
                                           value="<?php echo (int)$email_configs['imap_port'] ?: 993; ?>" 
                                           placeholder="993"
                                           onchange="document.getElementById('imap_port').value = this.value">
                                </div>
                                <div class="form-group">
                                    <label>Usar SSL</label>
                                    <select id="adv_imap_ssl" name="config[imap_ssl]"
                                            onchange="document.getElementById('imap_ssl').value = this.value">
                                        <option value="1" <?php echo selected($email_configs['imap_ssl'],'1'); ?>>Sim — SSL/TLS (porta 993)</option>
                                        <option value="0" <?php echo selected($email_configs['imap_ssl'],'0'); ?>>Nao — sem SSL (porta 143)</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Usuario IMAP <small style="color:var(--gray-400)">sobrepoem o email unificado</small></label>
                                    <input type="email" id="adv_imap_user" name="config[imap_user]" 
                                           value="<?php echo sanitize($email_configs['imap_user']); ?>" 
                                           placeholder="contato@seudominio.com"
                                           onchange="document.getElementById('imap_user').value = this.value">
                                </div>
                                <div class="form-group">
                                    <label>Senha IMAP <small style="color:var(--gray-400)">sobrepoem a senha unificada</small></label>
                                    <div class="password-field">
                                        <input type="text" 
                                               id="adv_imap_pass"
                                               name="<?php echo $fk; ?>_imap_pass"
                                               class="pass-mask"
                                               value="" 
                                               placeholder="Digite a senha IMAP"
                                               autocomplete="off"
                                               autocorrect="off"
                                               autocapitalize="off"
                                               spellcheck="false">
                                        <button type="button" class="toggle-btn" onclick="toggleSenha('adv_imap_pass', this)">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </div>
                                    <small style="color:var(--gray-400);font-size:0.75rem;margin-top:4px;display:block;">
                                        <i class="fas fa-info-circle"></i> 
                                        <?php if ($email_configs['imap_pass']): ?>
                                            Senha ja salva. Digite apenas se quiser alterar.
                                        <?php else: ?>
                                            Digite a senha para salvar.
                                        <?php endif; ?>
                                    </small>
                                </div>
                                <div class="form-group">
                                    <label>Pasta padrao</label>
                                    <input type="text" name="config[imap_folder]" 
                                           value="<?php echo sanitize($email_configs['imap_folder']); ?>" 
                                           placeholder="INBOX">
                                </div>
                            </div>

                            <details class="folders-details">
                                <summary>
                                    <i class="fas fa-folder-open" style="color: var(--gray-400); margin-right: 4px;"></i>
                                    Nomes das pastas especiais no servidor
                                    <small style="font-weight: 400; color: var(--gray-400);">
                                        — Gmail: [Gmail]/Sent Mail · cPanel: Sent / Junk / Trash
                                    </small>
                                </summary>
                                <div class="folders-grid">
                                    <?php foreach ([
                                        'imap_folder_sent'    => ['Enviados',  'Sent'],
                                        'imap_folder_drafts'  => ['Rascunhos', 'Drafts'],
                                        'imap_folder_archive' => ['Arquivo',   'Archive'],
                                        'imap_folder_spam'    => ['Spam',      'Junk'],
                                        'imap_folder_trash'   => ['Lixeira',   'Trash'],
                                    ] as $key => [$label, $default]): ?>
                                    <div class="form-group" style="margin: 0;">
                                        <label style="font-size: 0.8rem;"><?php echo $label; ?></label>
                                        <input type="text" id="<?php echo $key; ?>" name="config[<?php echo $key; ?>]"
                                               value="<?php echo sanitize(get_config($key, $default)); ?>"
                                               placeholder="<?php echo $default; ?>">
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </details>
                        </div>
                    </div>
                </details>

            </div>

            <div class="form-actions" style="margin-top: 32px; padding-top: 20px; border-top: 2px solid var(--gray-200);">
                <button type="submit" class="btn btn-primary btn-lg">
                    <i class="fas fa-save"></i> Salvar Todas as Configuracoes
                </button>
                <span style="margin-left: 12px; color: var(--gray-400); font-size: 0.85rem;">
                    <i class="fas fa-info-circle"></i> As alteracoes sao salvas em todas as abas de uma vez
                </span>
            </div>
        </form>
    </div>
</div>

<?php
function renderField($cfg) {
    $val = $cfg['valor'] ?? '';
    $chave = $cfg['chave'];

    if ($cfg['tipo'] === 'textarea'): ?>
        <textarea name="config[<?php echo $chave; ?>]" rows="3"><?php echo sanitize($val); ?></textarea>
    <?php elseif ($cfg['tipo'] === 'file'): ?>
        <input type="file" name="config[<?php echo $chave; ?>]" accept="image/*">
        <?php if (!empty($val)): ?>
        <img src="<?php echo uploads_url($val); ?>" alt="" style="max-height: 60px; border-radius: 8px; margin-top: 8px; display: block;">
        <?php endif; ?>
    <?php elseif ($cfg['tipo'] === 'color'): ?>
        <div class="color-field">
            <input type="color" name="config[<?php echo $chave; ?>]" 
                   value="<?php echo sanitize($val) ?: '#3b82f6'; ?>">
            <span class="color-value"><?php echo sanitize($val); ?></span>
        </div>
    <?php elseif ($cfg['tipo'] === 'select' && !empty($cfg['opcoes'])): ?>
        <select name="config[<?php echo $chave; ?>]">
            <?php foreach (json_decode($cfg['opcoes'], true) ?? [] as $v => $l): ?>
            <option value="<?php echo $v; ?>" <?php echo selected($val, $v); ?>><?php echo $l; ?></option>
            <?php endforeach; ?>
        </select>
    <?php elseif ($cfg['tipo'] === 'number'): ?>
        <input type="number" name="config[<?php echo $chave; ?>]" value="<?php echo (int)$val; ?>">
    <?php else: ?>
        <input type="text" name="config[<?php echo $chave; ?>]" value="<?php echo sanitize($val); ?>">
    <?php endif;
}
?>

<script>
function switchTab(tabId) {
    document.querySelectorAll('.config-tab').forEach(t => t.classList.remove('active'));
    document.querySelectorAll('.config-panel').forEach(p => p.classList.remove('active'));
    document.querySelector('.config-tab[data-tab="' + tabId + '"]').classList.add('active');
    document.getElementById('tab-' + tabId).classList.add('active');
    localStorage.setItem('config_tab_ativa', tabId);
}
(function() {
    const tabSalva = localStorage.getItem('config_tab_ativa');
    if (tabSalva) switchTab(tabSalva);
})();

function toggleSenha(inputId, btn) {
    const inp = document.getElementById(inputId);
    const icon = btn.querySelector('i');
    if (inp.classList.contains('revealed')) {
        inp.classList.remove('revealed');
        icon.className = 'fas fa-eye';
    } else {
        inp.classList.add('revealed');
        icon.className = 'fas fa-eye-slash';
    }
}

function alternarTipoAssinatura(tipo) {
    document.getElementById('assinatura_html_box').style.display = (tipo === 'html') ? 'block' : 'none';
    document.getElementById('assinatura_imagem_box').style.display = (tipo === 'imagem') ? 'block' : 'none';
}

const PROVEDORES = {
    gmail: {
        nome: 'Gmail',
        smtp: { host: 'smtp.gmail.com', port: 587, encryption: 'tls' },
        imap: { host: 'imap.gmail.com', port: 993, ssl: '1',
                folders: { sent: '[Gmail]/Sent Mail', drafts: '[Gmail]/Drafts', archive: '[Gmail]/All Mail', spam: '[Gmail]/Spam', trash: '[Gmail]/Trash' } },
        dica: 'No Gmail voce precisa gerar uma "Senha de App" em myaccount.google.com/apppasswords (nao use a senha normal da conta). Ative a verificacao em duas etapas primeiro.',
    },
    outlook: {
        nome: 'Outlook / Hotmail / Office365',
        smtp: { host: 'smtp.office365.com', port: 587, encryption: 'tls' },
        imap: { host: 'outlook.office365.com', port: 993, ssl: '1',
                folders: { sent: 'Sent Items', drafts: 'Drafts', archive: 'Archive', spam: 'Junk Email', trash: 'Deleted Items' } },
        dica: 'Contas Microsoft 365/Outlook podem exigir "Senha de aplicativo" se a autenticacao multifator estiver ativa.',
    },
    yahoo: {
        nome: 'Yahoo Mail',
        smtp: { host: 'smtp.mail.yahoo.com', port: 587, encryption: 'tls' },
        imap: { host: 'imap.mail.yahoo.com', port: 993, ssl: '1',
                folders: { sent: 'Sent', drafts: 'Draft', archive: 'Archive', spam: 'Bulk Mail', trash: 'Trash' } },
        dica: 'O Yahoo exige "Senha de app" gerada em Configuracoes da Conta -> Seguranca.',
    },
    icloud: {
        nome: 'Apple Mail (iCloud)',
        smtp: { host: 'smtp.mail.me.com', port: 587, encryption: 'tls' },
        imap: { host: 'imap.mail.me.com', port: 993, ssl: '1',
                folders: { sent: 'Sent Messages', drafts: 'Drafts', archive: 'Archive', spam: 'Junk', trash: 'Deleted Messages' } },
        dica: 'Use uma "Senha especifica de app" gerada em appleid.apple.com — a senha normal da Apple ID nao funciona aqui.',
    },
    proton: {
        nome: 'Proton Mail',
        smtp: { host: '127.0.0.1', port: 1025, encryption: '' },
        imap: { host: '127.0.0.1', port: 1143, ssl: '0',
                folders: { sent: 'Sent', drafts: 'Drafts', archive: 'Archive', spam: 'Spam', trash: 'Trash' } },
        dica: 'Proton Mail exige o "Proton Mail Bridge" (app desktop) rodando no servidor para expor SMTP/IMAP local — nao ha acesso direto sem ele (plano pago).',
    },
    zoho: {
        nome: 'Zoho Mail',
        smtp: { host: 'smtp.zoho.com', port: 587, encryption: 'tls' },
        imap: { host: 'imap.zoho.com', port: 993, ssl: '1',
                folders: { sent: 'Sent', drafts: 'Drafts', archive: 'Archive', spam: 'Spam', trash: 'Trash' } },
        dica: 'Ative o acesso IMAP em Configuracoes -> Mail Accounts -> IMAP Access no painel Zoho.',
    },
    aol: {
        nome: 'AOL Mail',
        smtp: { host: 'smtp.aol.com', port: 587, encryption: 'tls' },
        imap: { host: 'imap.aol.com', port: 993, ssl: '1',
                folders: { sent: 'Sent', drafts: 'Drafts', archive: 'Archive', spam: 'Spam', trash: 'Trash' } },
        dica: 'AOL tambem exige "Senha de app" gerada nas configuracoes de seguranca da conta.',
    },
    gmx: {
        nome: 'GMX Mail',
        smtp: { host: 'smtp.gmx.com', port: 587, encryption: 'tls' },
        imap: { host: 'imap.gmx.com', port: 993, ssl: '1',
                folders: { sent: 'Sent', drafts: 'Drafts', archive: 'Archive', spam: 'Spam', trash: 'Trash' } },
        dica: 'No GMX, habilite "POP3/IMAP" em Configuracoes -> POP3/IMAP antes de conectar.',
    },
    yandex: {
        nome: 'Yandex Mail',
        smtp: { host: 'smtp.yandex.com', port: 587, encryption: 'tls' },
        imap: { host: 'imap.yandex.com', port: 993, ssl: '1',
                folders: { sent: 'Sent', drafts: 'Drafts', archive: 'Archive', spam: 'Spam', trash: 'Trash' } },
        dica: 'No Yandex, ative "Permitir acesso IMAP" em Configuracoes -> Clientes de e-mail.',
    },
    titan: {
        nome: 'Titan Mail',
        smtp: { host: 'smtp.titan.email', port: 587, encryption: 'tls' },
        imap: { host: 'imap.titan.email', port: 993, ssl: '1',
                folders: { sent: 'Sent', drafts: 'Drafts', archive: 'Archive', spam: 'Spam', trash: 'Trash' } },
        dica: 'Titan Mail (usado por Hostinger e outros) usa a senha normal da caixa postal.',
    },
    cpanel: {
        nome: 'Dominio Proprio (cPanel)',
        smtp: { host: 'mail.SEUDOMINIO.com', port: 587, encryption: 'tls' },
        imap: { host: 'mail.SEUDOMINIO.com', port: 993, ssl: '1',
                folders: { sent: 'Sent', drafts: 'Drafts', archive: 'Archive', spam: 'Junk', trash: 'Trash' } },
        dica: 'Substitua "SEUDOMINIO.com" pelo seu dominio real. Em hospedagens como Hostgator, o host tambem pode ser o IP do servidor — confira em cPanel -> Contas de Email -> Configurar Cliente de Email.',
    },
};

const DOMINIO_PARA_PROVEDOR = {
    'gmail.com': 'gmail', 'googlemail.com': 'gmail',
    'outlook.com': 'outlook', 'hotmail.com': 'outlook', 'live.com': 'outlook', 'msn.com': 'outlook',
    'yahoo.com': 'yahoo', 'yahoo.com.br': 'yahoo', 'ymail.com': 'yahoo',
    'icloud.com': 'icloud', 'me.com': 'icloud', 'mac.com': 'icloud',
    'proton.me': 'proton', 'protonmail.com': 'proton',
    'zoho.com': 'zoho',
    'aol.com': 'aol',
    'gmx.com': 'gmx', 'gmx.net': 'gmx',
    'yandex.com': 'yandex', 'yandex.ru': 'yandex',
    'titan.email': 'titan',
};

function processarEmailUnificado(email) {
    const emailTrim = email.trim();

    // Preenche SMTP e IMAP com o email unificado
    document.getElementById('smtp_user').value = emailTrim;
    document.getElementById('imap_user').value = emailTrim;
    document.getElementById('adv_smtp_user').value = emailTrim;
    document.getElementById('adv_imap_user').value = emailTrim;

    // Detecta provedor pelo dominio
    const partes = emailTrim.split('@');
    if (partes.length !== 2 || !partes[1]) {
        document.getElementById('unified_status').style.display = 'none';
        return;
    }

    const dominio = partes[1].toLowerCase().trim();
    const provedorKey = DOMINIO_PARA_PROVEDOR[dominio];
    const select = document.getElementById('provedor_select');

    if (provedorKey) {
        select.value = provedorKey;
        aplicarProvedor();
        document.getElementById('provedor_detectado').textContent = PROVEDORES[provedorKey].nome;
        document.getElementById('unified_status').style.display = 'block';
    } else if (dominio.includes('.')) {
        // Dominio proprio: assume cPanel
        select.value = 'cpanel';
        const host = 'mail.' + dominio;
        document.getElementById('smtp_host').value = host;
        document.getElementById('imap_host').value = host;
        document.getElementById('adv_smtp_host').value = host;
        document.getElementById('adv_imap_host').value = host;
        document.getElementById('provedor_detectado').textContent = 'Dominio Proprio (' + dominio + ')';
        document.getElementById('unified_status').style.display = 'block';
    }

    atualizarEmailBadge();
}

function sincronizarSenhaUnificada(senha) {
    // A senha unificada eh enviada pelo campo real_smtp_pass e real_imap_pass
    // Ja tratado no PHP - nao precisa fazer nada no JS
    atualizarEmailBadge();
}

function aplicarProvedor() {
    const key = document.getElementById('provedor_select').value;
    const dicaEl = document.getElementById('provedor_dica');
    if (!key) { dicaEl.classList.remove('visible'); return; }

    const p = PROVEDORES[key];
    if (!p) return;

    document.getElementById('smtp_host').value = p.smtp.host;
    document.getElementById('smtp_port').value = p.smtp.port;
    document.getElementById('smtp_encryption').value = p.smtp.encryption;
    document.getElementById('imap_host').value = p.imap.host;
    document.getElementById('imap_port').value = p.imap.port;
    document.getElementById('imap_ssl').value = p.imap.ssl;

    document.getElementById('adv_smtp_host').value = p.smtp.host;
    document.getElementById('adv_smtp_port').value = p.smtp.port;
    document.getElementById('adv_smtp_encryption').value = p.smtp.encryption;
    document.getElementById('adv_imap_host').value = p.imap.host;
    document.getElementById('adv_imap_port').value = p.imap.port;
    document.getElementById('adv_imap_ssl').value = p.imap.ssl;

    const f = p.imap.folders;
    document.getElementById('imap_folder_sent').value    = f.sent;
    document.getElementById('imap_folder_drafts').value  = f.drafts;
    document.getElementById('imap_folder_archive').value = f.archive;
    document.getElementById('imap_folder_spam').value    = f.spam;
    document.getElementById('imap_folder_trash').value   = f.trash;

    const emailAtual = document.getElementById('email_unificado').value;
    if (emailAtual) {
        document.getElementById('smtp_user').value = emailAtual;
        document.getElementById('imap_user').value = emailAtual;
        document.getElementById('adv_smtp_user').value = emailAtual;
        document.getElementById('adv_imap_user').value = emailAtual;
    }

    if (key === 'cpanel' && emailAtual && emailAtual.includes('@')) {
        const dominio = emailAtual.split('@')[1];
        if (dominio && dominio.includes('.')) {
            const host = 'mail.' + dominio.toLowerCase().trim();
            document.getElementById('smtp_host').value = host;
            document.getElementById('imap_host').value = host;
            document.getElementById('adv_smtp_host').value = host;
            document.getElementById('adv_imap_host').value = host;
        }
    }

    dicaEl.innerHTML = '<i class="fas fa-info-circle"></i> ' + p.dica;
    dicaEl.classList.add('visible');
}

function testarSmtp() {
    const para = document.getElementById('smtp_test_email').value.trim();
    const status = document.getElementById('smtp_status');
    if (!para) { 
        status.innerHTML = '<span class="status-indicator error"><i class="fas fa-times-circle"></i> Informe um email de destino.</span>'; 
        return; 
    }

    const host = document.getElementById('smtp_host').value.trim();
    const user = document.getElementById('smtp_user').value.trim();
    const pass = document.getElementById('senha_unificada').value.trim() || document.getElementById('adv_smtp_pass').value.trim();

    if (!host || !user || !pass) {
        status.innerHTML = '<span class="status-indicator error"><i class="fas fa-times-circle"></i> Preencha email, senha e servidor SMTP primeiro.</span>';
        return;
    }

    status.innerHTML = '<span class="status-indicator warning"><i class="fas fa-spinner fa-spin"></i> Enviando...</span>';
    fetch('email_teste_smtp.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ para })
    })
    .then(r => r.json())
    .then(d => {
        status.innerHTML = d.ok
            ? '<span class="status-indicator ok"><i class="fas fa-check-circle"></i> Enviado! Verifique a caixa de entrada.</span>'
            : '<span class="status-indicator error"><i class="fas fa-times-circle"></i> ' + (d.erro || 'Erro desconhecido') + '</span>';
    })
    .catch(() => { 
        status.innerHTML = '<span class="status-indicator error"><i class="fas fa-times-circle"></i> Erro de rede.</span>'; 
    });
}

function testarImap() {
    const status = document.getElementById('imap_status');
    const host = document.getElementById('imap_host').value.trim();
    const port = document.getElementById('imap_port').value.trim();
    const user = document.getElementById('imap_user').value.trim();
    const pass = document.getElementById('senha_unificada').value.trim() || document.getElementById('adv_imap_pass').value.trim();
    const ssl = document.getElementById('imap_ssl').value;

    if (!host || !user) { 
        status.innerHTML = '<span class="status-indicator error"><i class="fas fa-times-circle"></i> Preencha email e servidor IMAP primeiro.</span>'; 
        return; 
    }

    status.innerHTML = '<span class="status-indicator warning"><i class="fas fa-spinner fa-spin"></i> Testando...</span>';
    fetch('email_teste_imap.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ host, port, user, pass, ssl })
    })
    .then(r => r.json())
    .then(d => {
        status.innerHTML = d.ok
            ? '<span class="status-indicator ok"><i class="fas fa-check-circle"></i> Conexao OK! ' + d.total + ' mensagens encontradas.</span>'
            : '<span class="status-indicator error"><i class="fas fa-times-circle"></i> ' + (d.erro || 'Erro desconhecido') + '</span>';
    })
    .catch(() => { 
        status.innerHTML = '<span class="status-indicator error"><i class="fas fa-times-circle"></i> Erro de rede.</span>'; 
    });
}

function atualizarEmailBadge() {
    const email = document.getElementById('email_unificado').value.trim();
    const senha = document.getElementById('senha_unificada').value.trim();
    const badge = document.getElementById('emailBadge');

    if (email && senha) {
        badge.style.background = '#22c55e';
        badge.textContent = 'OK';
    } else {
        badge.style.background = '#ef4444';
        badge.textContent = '!';
    }
}

['email_unificado', 'senha_unificada'].forEach(id => {
    document.getElementById(id)?.addEventListener('input', atualizarEmailBadge);
});

// ANTI-AUTOFILL: limpa campos apos carregamento
(function() {
    setTimeout(function() {
        var sp = document.getElementById('senha_unificada');
        var ip = document.getElementById('adv_smtp_pass');
        var ii = document.getElementById('adv_imap_pass');
        if (sp) sp.value = '';
        if (ip) ip.value = '';
        if (ii) ii.value = '';
    }, 100);
    setTimeout(function() {
        var sp = document.getElementById('senha_unificada');
        var ip = document.getElementById('adv_smtp_pass');
        var ii = document.getElementById('adv_imap_pass');
        if (sp) sp.value = '';
        if (ip) ip.value = '';
        if (ii) ii.value = '';
    }, 500);
    setTimeout(function() {
        var sp = document.getElementById('senha_unificada');
        var ip = document.getElementById('adv_smtp_pass');
        var ii = document.getElementById('adv_imap_pass');
        if (sp) sp.value = '';
        if (ip) ip.value = '';
        if (ii) ii.value = '';
    }, 1500);
})();

// Inicializa com email ja salvo
(function() {
    const emailSalvo = document.getElementById('email_unificado').value;
    if (emailSalvo) {
        processarEmailUnificado(emailSalvo);
    }
})();
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>