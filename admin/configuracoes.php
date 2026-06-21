<?php
/**
 * SiteCatalogo2 - Configurações  (v2.6 — SMTP + IMAP integrados)
 */
require_once __DIR__ . '/includes/functions.php';

// === CONTROLE DE ACESSO ===
require_auth();
if (!check_permission('admin')) {
    header('Location: ' . admin_url());
    exit('Acesso negado.');
}

$page_title = 'Configurações';

// ─── Chaves que podem não existir ainda no banco ───────────────
$chaves_extras = [
    'toast_position', 'produtos_navegacao', 'empresa_sobre', 'empresa_slogan',
    'alerta_sonoro_orcamento', 'produto_visualizacao',
    // SMTP
    'smtp_host','smtp_port','smtp_user','smtp_pass','smtp_encryption','site_nome_email',
    // IMAP
    'imap_host','imap_port','imap_ssl','imap_user','imap_pass','imap_folder',
    'imap_folder_sent','imap_folder_drafts','imap_folder_archive','imap_folder_spam','imap_folder_trash',
];

// ─── Salvar configurações ──────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        foreach ($_POST['config'] as $chave => $valor) {
            $valor = trim($valor);
            if (in_array($chave, $chaves_extras)) {
                try {
                    $existe = db()->prepare("SELECT COUNT(*) FROM " . table('configuracoes') . " WHERE chave = ?");
                    $existe->execute([$chave]);
                    if ((int)$existe->fetchColumn() > 0) {
                        db()->prepare("UPDATE " . table('configuracoes') . " SET valor = ? WHERE chave = ?")->execute([$valor, $chave]);
                    } else {
                        // Detecta grupo e tipo para inserção nova
                        if (in_array($chave, ['smtp_host','smtp_port','smtp_user','smtp_pass','smtp_encryption','site_nome_email','imap_host','imap_port','imap_ssl','imap_user','imap_pass','imap_folder','imap_folder_sent','imap_folder_drafts','imap_folder_archive','imap_folder_spam','imap_folder_trash'])) {
                            $grupo_extra = 'email';
                            $tipo_extra  = in_array($chave, ['smtp_port','imap_port']) ? 'number' : (in_array($chave, ['smtp_pass','imap_pass']) ? 'password' : (in_array($chave, ['smtp_encryption','imap_ssl']) ? 'select' : 'text'));
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

        // Upload de logo
        if (!empty($_FILES['config']['name']['logo_cliente'])) {
            $up = handle_upload([
                'name'     => $_FILES['config']['name']['logo_cliente'],
                'tmp_name' => $_FILES['config']['tmp_name']['logo_cliente'],
                'error'    => $_FILES['config']['error']['logo_cliente'],
            ], 'config');
            if ($up) {
                $old = get_config('logo_cliente');
                if ($old) delete_upload($old);
                set_config('logo_cliente', $up);
            }
        }

        log_activity('update', 'configuracoes', 'Configurações atualizadas');
        set_flash('success', 'Configurações salvas com sucesso!');
    } catch (Exception $e) {
        set_flash('error', 'Erro: ' . $e->getMessage());
    }
    header('Location: configuracoes.php'); exit;
}

// ─── Carregar configurações do banco (exceto email e seo — tratadas separado) ──
$configuracoes = db()->query(
    "SELECT * FROM " . table('configuracoes') . "
     WHERE ativo = 1
       AND grupo NOT IN ('email','seo')
       AND chave NOT IN ('categoria_layout','toast_position','produtos_navegacao','empresa_sobre','empresa_slogan','alerta_sonoro_orcamento','produto_visualizacao')
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

// Campos extras que não vêm do banco mas precisam aparecer
$extra_fields = [
    ['chave'=>'empresa_sobre',           'descricao'=>'Sobre a Empresa (texto exibido na seção "Quem Somos")',  'grupo'=>'geral',     'tipo'=>'textarea','valor'=>get_config('empresa_sobre',''),           'ativo'=>1],
    ['chave'=>'empresa_slogan',          'descricao'=>'Slogan / Frase de Destaque da Empresa',                  'grupo'=>'geral',     'tipo'=>'text',    'valor'=>get_config('empresa_slogan',''),           'ativo'=>1],
    ['chave'=>'produto_visualizacao',    'descricao'=>'Visualização do Produto ao clicar',                      'grupo'=>'aparencia', 'tipo'=>'select',  'valor'=>get_config('produto_visualizacao','modal'), 'ativo'=>1,
     'opcoes'=>json_encode(['modal'=>'Catálogo Simples (modal) — atual','pagina_individual'=>'Página Individual (melhor para SEO)'])],
    ['chave'=>'produtos_navegacao',      'descricao'=>'Navegação de Produtos',                                  'grupo'=>'aparencia', 'tipo'=>'select',  'valor'=>get_config('produtos_navegacao','paginacao'),'ativo'=>1,
     'opcoes'=>json_encode(['paginacao'=>'Paginação (Anterior / Próximo)','scroll_infinito'=>'Scroll Infinito'])],
    ['chave'=>'toast_position',          'descricao'=>'Posição do Toast de Produto Adicionado',                 'grupo'=>'aparencia', 'tipo'=>'select',  'valor'=>get_config('toast_position','bottom-right'), 'ativo'=>1,
     'opcoes'=>json_encode(['bottom-left'=>'Rodapé Esquerdo','bottom-center'=>'Rodapé Centro','bottom-right'=>'Rodapé Direito'])],
    ['chave'=>'alerta_sonoro_orcamento', 'descricao'=>'Alerta Sonoro — Novos Orçamentos',                       'grupo'=>'aparencia', 'tipo'=>'select',  'valor'=>get_config('alerta_sonoro_orcamento','1'),   'ativo'=>1,
     'opcoes'=>json_encode(['1'=>'Ativado','0'=>'Desativado'])],
];

$configuracoes = array_merge($configuracoes, $extra_fields);
usort($configuracoes, function ($a, $b) {
    $order = ['geral'=>1,'contato'=>2,'social'=>3,'aparencia'=>4];
    $oa = $order[$a['grupo']] ?? 5;
    $ob = $order[$b['grupo']] ?? 5;
    return $oa !== $ob ? $oa - $ob : (($a['ordem']??0) - ($b['ordem']??0));
});

require_once __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <h1><i class="fas fa-cog"></i> Configurações</h1>
</div>

<div class="card">
    <div class="card-body">
        <form method="POST" enctype="multipart/form-data">

            <?php /* ── Grupos Geral / Contato / Social / Aparência ── */ ?>
            <?php
            $grupo_atual = '';
            foreach ($configuracoes as $cfg):
                if ($cfg['grupo'] !== $grupo_atual):
                    if ($grupo_atual !== '') echo '</div>';
                    $grupo_atual = $cfg['grupo'];
                    $grupo_nome = ['geral'=>'Configurações Gerais','contato'=>'Dados de Contato','social'=>'Redes Sociais','aparencia'=>'Aparência e Layout'][$grupo_atual] ?? ucfirst($grupo_atual);
            ?>
            <h3 style="margin:28px 0 14px;padding-bottom:8px;border-bottom:2px solid var(--gray-200);color:var(--gray-900);font-size:1rem;font-weight:700;">
                <i class="fas fa-chevron-right" style="color:var(--primary);margin-right:6px;font-size:0.875rem;"></i><?php echo $grupo_nome; ?>
            </h3>
            <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:16px;">
            <?php endif; ?>
                <div class="form-group" style="<?php echo in_array($cfg['tipo'],['textarea','file']) ? 'grid-column:1/-1;' : ''; ?>">
                    <label>
                        <?php if ($cfg['grupo'] === 'social' && isset($social_icons[$cfg['chave']])): [$ico,$cor] = $social_icons[$cfg['chave']]; ?>
                        <i class="<?php echo $ico; ?>" style="color:<?php echo $cor; ?>;margin-right:6px;font-size:1rem;"></i>
                        <?php endif; ?>
                        <?php echo sanitize($cfg['descricao'] ?: $cfg['chave']); ?>
                    </label>
                    <?php if ($cfg['tipo'] === 'textarea'): ?>
                        <textarea name="config[<?php echo $cfg['chave']; ?>]" rows="3"><?php echo sanitize($cfg['valor']); ?></textarea>
                    <?php elseif ($cfg['tipo'] === 'file'): ?>
                        <input type="file" name="config[<?php echo $cfg['chave']; ?>]" accept="image/*">
                        <?php if (!empty($cfg['valor'])): ?>
                        <img src="<?php echo uploads_url($cfg['valor']); ?>" alt="Logo" style="max-height:60px;border-radius:8px;margin-top:8px;display:block;">
                        <?php endif; ?>
                    <?php elseif ($cfg['tipo'] === 'color'): ?>
                        <div style="display:flex;align-items:center;gap:10px;">
                            <input type="color" name="config[<?php echo $cfg['chave']; ?>]" value="<?php echo sanitize($cfg['valor']?:'#3b82f6'); ?>" style="width:60px;height:40px;padding:2px;border:1px solid var(--gray-200);border-radius:6px;">
                            <span style="font-size:0.875rem;color:var(--gray-500);"><?php echo sanitize($cfg['valor']); ?></span>
                        </div>
                    <?php elseif ($cfg['tipo'] === 'select' && !empty($cfg['opcoes'])): ?>
                        <select name="config[<?php echo $cfg['chave']; ?>]">
                            <?php foreach (json_decode($cfg['opcoes'],true)??[] as $v=>$l): ?>
                            <option value="<?php echo $v; ?>" <?php echo selected($cfg['valor'],$v); ?>><?php echo $l; ?></option>
                            <?php endforeach; ?>
                        </select>
                    <?php elseif ($cfg['tipo'] === 'number'): ?>
                        <input type="number" name="config[<?php echo $cfg['chave']; ?>]" value="<?php echo (int)$cfg['valor']; ?>">
                    <?php else: ?>
                        <input type="text" name="config[<?php echo $cfg['chave']; ?>]" value="<?php echo sanitize($cfg['valor']); ?>">
                    <?php endif; ?>
                </div>
            <?php endforeach; if ($grupo_atual) echo '</div>'; ?>

            <?php /* ═══════════════════════════════════════════════════
                       SEÇÃO EMAIL — SMTP + IMAP (id="email" para âncora)
                   ═══════════════════════════════════════════════════ */ ?>
            <h3 id="email" style="margin:28px 0 14px;padding-bottom:8px;border-bottom:2px solid var(--gray-200);color:var(--gray-900);font-size:1rem;font-weight:700;">
                <i class="fas fa-chevron-right" style="color:var(--primary);margin-right:6px;font-size:0.875rem;"></i>Email — Envio e Recebimento
            </h3>

            <?php /* ── Seletor de Provedor (autopreenchimento) ── */ ?>
            <div style="margin-bottom:20px;padding:14px 16px;background:#f5f3ff;border:1px solid #c4b5fd;border-radius:8px;">
                <label style="display:block;font-size:0.8rem;font-weight:700;color:#5b21b6;margin-bottom:6px;">
                    <i class="fas fa-magic"></i> Preenchimento Rápido por Provedor
                </label>
                <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
                    <select id="provedor_select" onchange="aplicarProvedor()" style="flex:1;min-width:220px;padding:9px 12px;border:1px solid #c4b5fd;border-radius:6px;font-size:0.875rem;background:#fff;">
                        <option value="">Selecione um provedor...</option>
                        <option value="gmail">Gmail</option>
                        <option value="outlook">Outlook / Hotmail / Office365</option>
                        <option value="yahoo">Yahoo Mail</option>
                        <option value="icloud">Apple Mail (iCloud)</option>
                        <option value="proton">Proton Mail</option>
                        <option value="zoho">Zoho Mail</option>
                        <option value="aol">AOL Mail</option>
                        <option value="gmx">GMX Mail</option>
                        <option value="yandex">Yandex Mail</option>
                        <option value="titan">Titan Mail</option>
                        <option value="cpanel">Domínio Próprio (cPanel / Hostgator / etc.)</option>
                    </select>
                    <span id="provedor_aviso" style="font-size:0.8rem;color:#5b21b6;"></span>
                </div>
                <p id="provedor_dica" style="margin:8px 0 0;font-size:0.78rem;color:#6d28d9;display:none;"></p>
            </div>

            <?php /* ── SMTP ── */ ?>
            <div style="margin-bottom:8px;">
                <span style="display:inline-block;font-size:0.75rem;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:var(--gray-500);margin-bottom:10px;">
                    <i class="fas fa-paper-plane" style="margin-right:4px;color:#3b82f6;"></i>Envio (SMTP)
                </span>
                <div style="margin-top:4px;padding:10px 12px;background:#eff6ff;border-left:3px solid #3b82f6;border-radius:6px;font-size:0.8rem;color:#1e40af;">
                    <i class="fas fa-circle-info"></i> O SMTP é o serviço que <strong>envia</strong> os emails do sistema. Preencha com o email e a senha que você usa para entrar na sua caixa de email (webmail) — não é a senha do painel admin do site.
                </div>
            </div>
            <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:16px;margin-bottom:24px;margin-top:14px;">
                <div class="form-group">
                    <label>Servidor SMTP <small style="color:var(--gray-400);">endereço do servidor de envio</small></label>
                    <input type="text" id="smtp_host_input" name="config[smtp_host]" value="<?php echo sanitize(get_config('smtp_host','')); ?>" placeholder="mail.seudominio.com">
                </div>
                <div class="form-group">
                    <label>Porta <small style="color:var(--gray-400);">587=TLS · 465=SSL · 25=sem</small></label>
                    <input type="number" id="smtp_port_input" name="config[smtp_port]" value="<?php echo (int)get_config('smtp_port',587) ?: 587; ?>" placeholder="587">
                </div>
                <div class="form-group">
                    <label>Criptografia</label>
                    <select id="smtp_encryption_input" name="config[smtp_encryption]">
                        <?php foreach (['tls'=>'TLS (porta 587)','ssl'=>'SSL (porta 465)',''=>'Nenhuma (porta 25)'] as $v=>$l): ?>
                        <option value="<?php echo $v; ?>" <?php echo selected(get_config('smtp_encryption','tls'),$v); ?>><?php echo $l; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Usuário SMTP (email completo) <small style="color:var(--gray-400);">o email que envia as mensagens</small></label>
                    <input type="email" id="smtp_user_input" name="config[smtp_user]" value="<?php echo sanitize(get_config('smtp_user','')); ?>" placeholder="contato@seudominio.com" oninput="sincronizarEmail(this.value)">
                </div>
                <div class="form-group">
                    <label>Senha SMTP <small style="color:var(--gray-400);">a mesma senha que você usa para entrar nesse email (webmail)</small></label>
                    <div style="position:relative;">
                        <input type="password" id="smtp_pass_input" name="config[smtp_pass]" value="<?php echo sanitize(get_config('smtp_pass','')); ?>" placeholder="••••••••" style="padding-right:40px;width:100%;">
                        <button type="button" onclick="toggleSenha('smtp_pass_input','smtp_pass_eye')" style="position:absolute;right:10px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:var(--gray-400);">
                            <i class="fas fa-eye" id="smtp_pass_eye"></i>
                        </button>

                    </div>
                </div>
                <div class="form-group">
                    <label>Nome do remetente <small style="color:var(--gray-400);">vazio = usa site_nome</small></label>
                    <input type="text" name="config[site_nome_email]" value="<?php echo sanitize(get_config('site_nome_email','')); ?>" placeholder="<?php echo sanitize(get_config('site_nome','SiteCatalogo')); ?>">
                </div>
            </div>

            <?php /* Teste SMTP */ ?>
            <div style="margin-bottom:24px;padding:14px 16px;background:var(--gray-50);border-radius:8px;display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
                <div style="flex:1;min-width:200px;">
                    <label style="display:block;font-size:0.8rem;font-weight:600;color:var(--gray-600);margin-bottom:4px;">Testar envio SMTP</label>
                    <input type="email" id="smtp_test_email" placeholder="seu@email.com" style="width:100%;padding:8px 12px;border:1px solid var(--gray-300);border-radius:6px;font-size:0.875rem;">
                </div>
                <button type="button" class="btn btn-outline btn-sm" style="margin-top:20px;" onclick="testarSmtp()">
                    <i class="fas fa-paper-plane"></i> Enviar teste
                </button>
                <span id="smtp_status" style="font-size:0.875rem;margin-top:20px;"></span>
            </div>

            <?php /* ── IMAP ── */ ?>
            <div style="margin-bottom:8px;">
                <span style="display:inline-block;font-size:0.75rem;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:var(--gray-500);margin-bottom:10px;">
                    <i class="fas fa-inbox" style="margin-right:4px;color:#10b981;"></i>Recebimento (IMAP)
                </span>
                <?php if (!function_exists('imap_open')): ?>
                <div style="display:inline-block;margin-left:12px;padding:4px 10px;background:#fffbeb;border:1px solid #f59e0b;border-radius:6px;font-size:0.75rem;color:#92400e;">
                    <i class="fas fa-exclamation-triangle"></i> Extensão IMAP não habilitada — ative <code>extension=imap</code> no php.ini
                </div>
                <?php else: ?>
                <div style="display:inline-block;margin-left:12px;padding:4px 10px;background:#f0fdf4;border:1px solid #22c55e;border-radius:6px;font-size:0.75rem;color:#166534;">
                    <i class="fas fa-check-circle"></i> Extensão IMAP disponível
                </div>
                <?php endif; ?>
                <div style="margin-top:8px;padding:10px 12px;background:#f0fdf4;border-left:3px solid #22c55e;border-radius:6px;font-size:0.8rem;color:#166534;">
                    <i class="fas fa-circle-info"></i> O IMAP é o serviço que <strong>busca e sincroniza</strong> os emails recebidos para dentro do sistema. Use o mesmo email e a mesma senha de login do webmail — geralmente igual ao SMTP acima.
                </div>
            </div>
            <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:16px;margin-bottom:16px;margin-top:14px;">
                <div class="form-group">
                    <label>Servidor IMAP <small style="color:var(--gray-400);">endereço do servidor de recebimento</small></label>
                    <input type="text" id="imap_host_input" name="config[imap_host]" value="<?php echo sanitize(get_config('imap_host','')); ?>" placeholder="mail.seudominio.com">
                </div>
                <div class="form-group">
                    <label>Porta <small style="color:var(--gray-400);">993=SSL · 143=TLS/sem</small></label>
                    <input type="number" id="imap_port_input" name="config[imap_port]" value="<?php echo (int)get_config('imap_port',993) ?: 993; ?>" placeholder="993">
                </div>
                <div class="form-group">
                    <label>Usar SSL</label>
                    <select id="imap_ssl_input" name="config[imap_ssl]">
                        <option value="1" <?php echo selected(get_config('imap_ssl','1'),'1'); ?>>Sim — SSL/TLS (porta 993)</option>
                        <option value="0" <?php echo selected(get_config('imap_ssl','1'),'0'); ?>>Não — sem SSL (porta 143)</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Usuário IMAP (email completo) <small style="color:var(--gray-400);">o email de onde as mensagens serão lidas</small></label>
                    <input type="email" id="imap_user_input" name="config[imap_user]" value="<?php echo sanitize(get_config('imap_user','')); ?>" placeholder="contato@seudominio.com" oninput="sincronizarEmail(this.value, true)">
                </div>
                <div class="form-group">
                    <label>Senha IMAP <small style="color:var(--gray-400);">a mesma senha que você usa para entrar nesse email (webmail)</small></label>
                    <div style="position:relative;">
                        <input type="password" id="imap_pass_input" name="config[imap_pass]" value="<?php echo sanitize(get_config('imap_pass','')); ?>" placeholder="••••••••" style="padding-right:40px;width:100%;">
                        <button type="button" onclick="toggleSenha('imap_pass_input','imap_pass_eye')" style="position:absolute;right:10px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:var(--gray-400);">
                            <i class="fas fa-eye" id="imap_pass_eye"></i>
                        </button>
                    </div>
                </div>
                <div class="form-group">
                    <label>Pasta padrão</label>
                    <input type="text" name="config[imap_folder]" value="<?php echo sanitize(get_config('imap_folder','INBOX')); ?>" placeholder="INBOX">
                </div>
            </div>

            <?php /* Pastas especiais IMAP */ ?>
            <details style="margin-bottom:16px;">
                <summary style="cursor:pointer;font-size:0.875rem;color:var(--gray-600);font-weight:600;padding:6px 0;user-select:none;">
                    <i class="fas fa-folder-open" style="color:var(--gray-400);margin-right:4px;"></i>
                    Nomes das pastas especiais no servidor
                    <small style="font-weight:400;color:var(--gray-400);"> — Gmail: Sent Mail / Spam / Trash · cPanel: Sent / Junk / Trash</small>
                </summary>
                <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:12px;margin-top:12px;padding:14px;background:var(--gray-50);border-radius:8px;">
                    <?php foreach ([
                        'imap_folder_sent'    => ['Enviados',  'Sent'],
                        'imap_folder_drafts'  => ['Rascunhos', 'Drafts'],
                        'imap_folder_archive' => ['Arquivo',   'Archive'],
                        'imap_folder_spam'    => ['Spam',      'Junk'],
                        'imap_folder_trash'   => ['Lixeira',   'Trash'],
                    ] as $key => [$label, $default]): ?>
                    <div class="form-group" style="margin:0;">
                        <label style="font-size:0.8rem;"><?php echo $label; ?></label>
                        <input type="text" id="<?php echo $key; ?>_input" name="config[<?php echo $key; ?>]"
                               value="<?php echo sanitize(get_config($key, $default)); ?>"
                               placeholder="<?php echo $default; ?>">
                    </div>
                    <?php endforeach; ?>
                </div>
            </details>

            <?php /* Teste IMAP */ ?>
            <div style="margin-bottom:28px;padding:14px 16px;background:var(--gray-50);border-radius:8px;display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
                <div style="font-size:0.875rem;color:var(--gray-600);font-weight:600;">
                    <i class="fas fa-plug" style="color:#10b981;margin-right:4px;"></i>Testar conexão IMAP
                </div>
                <button type="button" class="btn btn-outline btn-sm" onclick="testarImap()" <?php echo !function_exists('imap_open') ? 'disabled title="Extensão IMAP não disponível"' : ''; ?>>
                    <i class="fas fa-sync"></i> Testar agora
                </button>
                <span id="imap_status" style="font-size:0.875rem;"></span>
            </div>

            <div class="form-actions" style="margin-top:8px;">
                <button type="submit" class="btn btn-primary btn-lg"><i class="fas fa-save"></i> Salvar Configurações</button>
            </div>
        </form>
    </div>
</div>

<script>
// ════════════════════════════════════════════════════════════
// AUTOPREENCHIMENTO POR PROVEDOR
// ════════════════════════════════════════════════════════════
const PROVEDORES = {
    gmail: {
        nome: 'Gmail',
        smtp: { host: 'smtp.gmail.com', port: 587, encryption: 'tls' },
        imap: { host: 'imap.gmail.com', port: 993, ssl: '1',
                folders: { sent: '[Gmail]/Sent Mail', drafts: '[Gmail]/Drafts', archive: '[Gmail]/All Mail', spam: '[Gmail]/Spam', trash: '[Gmail]/Trash' } },
        dica: 'No Gmail você precisa gerar uma "Senha de App" em myaccount.google.com/apppasswords (não use a senha normal da conta). Ative a verificação em duas etapas primeiro.',
    },
    outlook: {
        nome: 'Outlook / Hotmail / Office365',
        smtp: { host: 'smtp.office365.com', port: 587, encryption: 'tls' },
        imap: { host: 'outlook.office365.com', port: 993, ssl: '1',
                folders: { sent: 'Sent Items', drafts: 'Drafts', archive: 'Archive', spam: 'Junk Email', trash: 'Deleted Items' } },
        dica: 'Contas Microsoft 365/Outlook podem exigir "Senha de aplicativo" se a autenticação multifator estiver ativa.',
    },
    yahoo: {
        nome: 'Yahoo Mail',
        smtp: { host: 'smtp.mail.yahoo.com', port: 587, encryption: 'tls' },
        imap: { host: 'imap.mail.yahoo.com', port: 993, ssl: '1',
                folders: { sent: 'Sent', drafts: 'Draft', archive: 'Archive', spam: 'Bulk Mail', trash: 'Trash' } },
        dica: 'O Yahoo exige "Senha de app" gerada em Configurações da Conta → Segurança.',
    },
    icloud: {
        nome: 'Apple Mail (iCloud)',
        smtp: { host: 'smtp.mail.me.com', port: 587, encryption: 'tls' },
        imap: { host: 'imap.mail.me.com', port: 993, ssl: '1',
                folders: { sent: 'Sent Messages', drafts: 'Drafts', archive: 'Archive', spam: 'Junk', trash: 'Deleted Messages' } },
        dica: 'Use uma "Senha específica de app" gerada em appleid.apple.com — a senha normal da Apple ID não funciona aqui.',
    },
    proton: {
        nome: 'Proton Mail',
        smtp: { host: '127.0.0.1', port: 1025, encryption: '' },
        imap: { host: '127.0.0.1', port: 1143, ssl: '0',
                folders: { sent: 'Sent', drafts: 'Drafts', archive: 'Archive', spam: 'Spam', trash: 'Trash' } },
        dica: 'Proton Mail exige o "Proton Mail Bridge" (app desktop) rodando no servidor para expor SMTP/IMAP local — não há acesso direto sem ele (plano pago).',
    },
    zoho: {
        nome: 'Zoho Mail',
        smtp: { host: 'smtp.zoho.com', port: 587, encryption: 'tls' },
        imap: { host: 'imap.zoho.com', port: 993, ssl: '1',
                folders: { sent: 'Sent', drafts: 'Drafts', archive: 'Archive', spam: 'Spam', trash: 'Trash' } },
        dica: 'Ative o acesso IMAP em Configurações → Mail Accounts → IMAP Access no painel Zoho.',
    },
    aol: {
        nome: 'AOL Mail',
        smtp: { host: 'smtp.aol.com', port: 587, encryption: 'tls' },
        imap: { host: 'imap.aol.com', port: 993, ssl: '1',
                folders: { sent: 'Sent', drafts: 'Drafts', archive: 'Archive', spam: 'Spam', trash: 'Trash' } },
        dica: 'AOL também exige "Senha de app" gerada nas configurações de segurança da conta.',
    },
    gmx: {
        nome: 'GMX Mail',
        smtp: { host: 'smtp.gmx.com', port: 587, encryption: 'tls' },
        imap: { host: 'imap.gmx.com', port: 993, ssl: '1',
                folders: { sent: 'Sent', drafts: 'Drafts', archive: 'Archive', spam: 'Spam', trash: 'Trash' } },
        dica: 'No GMX, habilite "POP3/IMAP" em Configurações → POP3/IMAP antes de conectar.',
    },
    yandex: {
        nome: 'Yandex Mail',
        smtp: { host: 'smtp.yandex.com', port: 587, encryption: 'tls' },
        imap: { host: 'imap.yandex.com', port: 993, ssl: '1',
                folders: { sent: 'Sent', drafts: 'Drafts', archive: 'Archive', spam: 'Spam', trash: 'Trash' } },
        dica: 'No Yandex, ative "Permitir acesso IMAP" em Configurações → Clientes de e-mail.',
    },
    titan: {
        nome: 'Titan Mail',
        smtp: { host: 'smtp.titan.email', port: 587, encryption: 'tls' },
        imap: { host: 'imap.titan.email', port: 993, ssl: '1',
                folders: { sent: 'Sent', drafts: 'Drafts', archive: 'Archive', spam: 'Spam', trash: 'Trash' } },
        dica: 'Titan Mail (usado por Hostinger e outros) usa a senha normal da caixa postal.',
    },
    cpanel: {
        nome: 'Domínio Próprio (cPanel)',
        smtp: { host: 'mail.SEUDOMINIO.com', port: 587, encryption: 'tls' },
        imap: { host: 'mail.SEUDOMINIO.com', port: 993, ssl: '1',
                folders: { sent: 'Sent', drafts: 'Drafts', archive: 'Archive', spam: 'Junk', trash: 'Trash' } },
        dica: 'Substitua "SEUDOMINIO.com" pelo seu domínio real (ex: mail.minhaempresa.com.br). Em hospedagens como Hostgator, o host também pode ser o IP do servidor — confira em cPanel → Contas de Email → Configurar Cliente de Email.',
    },
};

function aplicarProvedor() {
    const key = document.getElementById('provedor_select').value;
    const aviso = document.getElementById('provedor_aviso');
    const dicaEl = document.getElementById('provedor_dica');
    if (!key) { dicaEl.style.display = 'none'; aviso.textContent = ''; return; }

    const p = PROVEDORES[key];
    if (!p) return;

    // SMTP
    document.getElementById('smtp_host_input').value = p.smtp.host;
    document.getElementById('smtp_port_input').value = p.smtp.port;
    document.getElementById('smtp_encryption_input').value = p.smtp.encryption;

    // IMAP
    document.getElementById('imap_host_input').value = p.imap.host;
    document.getElementById('imap_port_input').value = p.imap.port;
    document.getElementById('imap_ssl_input').value = p.imap.ssl;

    // Pastas especiais
    const f = p.imap.folders;
    document.getElementById('imap_folder_sent_input').value    = f.sent;
    document.getElementById('imap_folder_drafts_input').value  = f.drafts;
    document.getElementById('imap_folder_archive_input').value = f.archive;
    document.getElementById('imap_folder_spam_input').value    = f.spam;
    document.getElementById('imap_folder_trash_input').value   = f.trash;

    // Aplica o email já digitado (se houver) ao usuário SMTP/IMAP
    const emailAtual = document.getElementById('smtp_user_input').value || document.getElementById('imap_user_input').value;
    if (emailAtual) {
        document.getElementById('smtp_user_input').value = emailAtual;
        document.getElementById('imap_user_input').value = emailAtual;
    }

    // Domínio Próprio (cPanel): se já existe um email preenchido, troca SEUDOMINIO.com pelo domínio real na hora
    if (key === 'cpanel' && emailAtual && emailAtual.includes('@')) {
        const dominioReal = emailAtual.split('@')[1];
        if (dominioReal && dominioReal.includes('.')) {
            const hostCorrigido = 'mail.' + dominioReal.toLowerCase().trim();
            document.getElementById('smtp_host_input').value = hostCorrigido;
            document.getElementById('imap_host_input').value = hostCorrigido;
        }
    }

    aviso.innerHTML = '<i class="fas fa-check-circle" style="color:#10b981;"></i> Campos preenchidos para ' + p.nome;
    dicaEl.innerHTML = '<i class="fas fa-info-circle"></i> ' + p.dica;
    dicaEl.style.display = 'block';
}

// Detecta provedor automaticamente pelo domínio do email digitado
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

function sincronizarEmail(valor, fromImap) {
    // Espelha o email entre os campos SMTP e IMAP
    const alvo = fromImap ? 'smtp_user_input' : 'imap_user_input';
    const campoAlvo = document.getElementById(alvo);
    if (campoAlvo && !campoAlvo.value) campoAlvo.value = valor;

    const partes = valor.split('@');
    if (partes.length !== 2 || !partes[1]) return;
    const dominio = partes[1].toLowerCase().trim();
    const select = document.getElementById('provedor_select');

    // Se o provedor ativo é "Domínio Próprio", troca o placeholder SEUDOMINIO.com
    // pelo domínio real que a pessoa está digitando no email — em todos os hosts.
    if (select.value === 'cpanel' && dominio.includes('.')) {
        const hostFields = ['smtp_host_input', 'imap_host_input'];
        hostFields.forEach(id => {
            const campo = document.getElementById(id);
            if (!campo) return;
            // Só troca se ainda contém o placeholder OU já contém um domínio (mantém prefixo mail.)
            if (campo.value.includes('SEUDOMINIO.com') || campo.value.includes('.')) {
                campo.value = 'mail.' + dominio;
            }
        });
        const aviso = document.getElementById('provedor_aviso');
        aviso.innerHTML = '<i class="fas fa-check-circle" style="color:#10b981;"></i> Servidor ajustado para mail.' + dominio;
        return;
    }

    // Auto-detecta provedor pelo domínio (só sugere, não força) — apenas quando nada foi escolhido ainda
    const provedorKey = DOMINIO_PARA_PROVEDOR[dominio];
    if (provedorKey && select.value !== provedorKey) {
        select.value = provedorKey;
        aplicarProvedor();
    } else if (!provedorKey && select.value === '') {
        // Domínio próprio detectado, sugere cPanel sem forçar
        const aviso = document.getElementById('provedor_aviso');
        aviso.innerHTML = '<i class="fas fa-lightbulb" style="color:#f59e0b;"></i> Domínio próprio detectado — selecione "Domínio Próprio (cPanel)" para preencher automaticamente com mail.' + dominio;
    }
}
</script>

<script>
// Mostrar/ocultar senhas
function toggleSenha(inputId, iconId) {
    const inp  = document.getElementById(inputId);
    const icon = document.getElementById(iconId);
    if (inp.type === 'password') {
        inp.type = 'text';
        icon.className = 'fas fa-eye-slash';
    } else {
        inp.type = 'password';
        icon.className = 'fas fa-eye';
    }
}

// Teste SMTP
function testarSmtp() {
    const para   = document.getElementById('smtp_test_email').value.trim();
    const status = document.getElementById('smtp_status');
    if (!para) { status.innerHTML = '<span style="color:#ef4444">Informe um email de destino.</span>'; return; }
    status.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Enviando...';
    fetch('email_teste_smtp.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ para })
    })
    .then(r => r.json())
    .then(d => {
        status.innerHTML = d.ok
            ? '<span style="color:#10b981"><i class="fas fa-check-circle"></i> Enviado! Verifique a caixa de entrada.</span>'
            : '<span style="color:#ef4444"><i class="fas fa-times-circle"></i> ' + d.erro + '</span>';
    })
    .catch(() => { status.innerHTML = '<span style="color:#ef4444"><i class="fas fa-times-circle"></i> Erro de rede.</span>'; });
}

// Teste IMAP
function testarImap() {
    const status = document.getElementById('imap_status');
    const host   = document.querySelector('[name="config[imap_host]"]').value.trim();
    const port   = document.querySelector('[name="config[imap_port]"]').value.trim();
    const user   = document.querySelector('[name="config[imap_user]"]').value.trim();
    const pass   = document.querySelector('[name="config[imap_pass]"]').value.trim();
    const ssl    = document.querySelector('[name="config[imap_ssl]"]').value;
    if (!host || !user) { status.innerHTML = '<span style="color:#ef4444">Preencha host e usuário IMAP primeiro.</span>'; return; }
    status.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Testando...';
    fetch('email_teste_imap.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ host, port, user, pass, ssl })
    })
    .then(r => r.json())
    .then(d => {
        status.innerHTML = d.ok
            ? '<span style="color:#10b981"><i class="fas fa-check-circle"></i> Conexão OK! ' + d.total + ' mensagens encontradas.</span>'
            : '<span style="color:#ef4444"><i class="fas fa-times-circle"></i> ' + d.erro + '</span>';
    })
    .catch(() => { status.innerHTML = '<span style="color:#ef4444"><i class="fas fa-times-circle"></i> Erro de rede.</span>'; });
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>