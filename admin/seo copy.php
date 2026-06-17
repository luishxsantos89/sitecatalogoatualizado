<?php
require_once __DIR__ . '/includes/functions.php';
$page_title = 'SEO';

/**
 * Links de ajuda para cada rede social (como obter pixel/tag)
 */
$help_links = [
    'Rastreamento Google'      => 'https://support.google.com/analytics/answer/9304153',
    'Rastreamento Meta'        => 'https://business.facebook.com/business/help/952192844843766',
    'Rastreamento TikTok'      => 'https://ads.tiktok.com/help/article/pixel',
    'Rastreamento Pinterest'   => 'https://help.pinterest.com/pt/business/article/pinterest-tag',
    'Rastreamento YouTube/Google Ads' => 'https://support.google.com/google-ads/answer/6095821',
    'Rastreamento Kwai'        => 'https://www.kwai.com/business-center/help-center',
    'Rastreamento Snapchat'    => 'https://business.snapchat.com/en-US/pixel',
    'Rastreamento X (Twitter)' => 'https://business.x.com/en/help/campaign-measurement-and-analytics/conversion-tracking',
    'Rastreamento LinkedIn'    => 'https://www.linkedin.com/help/lms/answer/a425295',
];

/**
 * Campos SEO
 *  - tipo 'text'         => input
 *  - tipo 'textarea'     => textarea normal (Meta Descrição, robots.txt)
 *  - tipo 'script'       => textarea para COLAR código <script>...</script> completo
 *                           (Pixel, GA, GTM, Hotjar, Clarity, etc.)
 */
$seo_fields = [
    // BÁSICO
    'seo_title'        => ['label'=>'Título SEO (aparece na aba do navegador e no Google)','tipo'=>'text','grupo'=>'Básico'],
    'seo_description'  => ['label'=>'Meta Descrição (resumo que aparece no Google, 120-160 caracteres)','tipo'=>'textarea','grupo'=>'Básico'],
    'seo_keywords'     => ['label'=>'Meta Keywords (palavras-chave separadas por vírgula)','tipo'=>'text','grupo'=>'Básico'],

    // RASTREAMENTO - Google
    'google_analytics'    => ['label'=>'Google Analytics ID (atalho - será montado o script automaticamente)','tipo'=>'text','grupo'=>'Rastreamento Google','placeholder'=>'G-XXXXXXXXXX'],
    'google_tag_manager'  => ['label'=>'Google Tag Manager ID (atalho - será montado o script automaticamente)','tipo'=>'text','grupo'=>'Rastreamento Google','placeholder'=>'GTM-XXXXXXX'],

    // RASTREAMENTO - Meta (Facebook / Instagram)
    'meta_facebook_pixel'   => ['label'=>'Meta Pixel ID (Facebook / Instagram)','tipo'=>'text','grupo'=>'Rastreamento Meta','placeholder'=>'1234567890123456'],
    'meta_conversion_api'   => ['label'=>'Meta Conversion API Token (opcional)','tipo'=>'text','grupo'=>'Rastreamento Meta','placeholder'=>'Token de acesso da API de Conversões'],

    // RASTREAMENTO - TikTok
    'tiktok_pixel'        => ['label'=>'TikTok Pixel ID','tipo'=>'text','grupo'=>'Rastreamento TikTok','placeholder'=>'XXXXXXXXXXXXXXXXXXXX'],
    'tiktok_events_api'     => ['label'=>'TikTok Events API Token (opcional)','tipo'=>'text','grupo'=>'Rastreamento TikTok','placeholder'=>'Token da API de Eventos'],

    // RASTREAMENTO - Pinterest
    'pinterest_tag'       => ['label'=>'Pinterest Tag ID','tipo'=>'text','grupo'=>'Rastreamento Pinterest','placeholder'=>'123456789012'],

    // RASTREAMENTO - YouTube / Google Ads
    'google_ads_id'       => ['label'=>'Google Ads Conversion ID','tipo'=>'text','grupo'=>'Rastreamento YouTube/Google Ads','placeholder'=>'AW-XXXXXXXXX'],
    'google_ads_label'    => ['label'=>'Google Ads Conversion Label (opcional)','tipo'=>'text','grupo'=>'Rastreamento YouTube/Google Ads','placeholder'=>'xxxxxxxxxxxxxx'],

    // RASTREAMENTO - Kwai
    'kwai_pixel'          => ['label'=>'Kwai Pixel ID','tipo'=>'text','grupo'=>'Rastreamento Kwai','placeholder'=>'KWAI-XXXXXXXX'],

    // RASTREAMENTO - Snapchat
    'snapchat_pixel'      => ['label'=>'Snapchat Pixel ID','tipo'=>'text','grupo'=>'Rastreamento Snapchat','placeholder'=>'xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx'],

    // RASTREAMENTO - X (Twitter)
    'x_pixel'             => ['label'=>'X (Twitter) Pixel ID','tipo'=>'text','grupo'=>'Rastreamento X (Twitter)','placeholder'=>'o_xxxxxx'],
    'x_conversion_id'     => ['label'=>'X (Twitter) Conversion ID (opcional)','tipo'=>'text','grupo'=>'Rastreamento X (Twitter)','placeholder'=>'tw-xxxx-xxxx'],

    // RASTREAMENTO - LinkedIn
    'linkedin_insight'    => ['label'=>'LinkedIn Insight Tag ID','tipo'=>'text','grupo'=>'Rastreamento LinkedIn','placeholder'=>'1234567'],
    'linkedin_conversion' => ['label'=>'LinkedIn Conversion ID (opcional)','tipo'=>'text','grupo'=>'Rastreamento LinkedIn','placeholder'=>'12345678'],

    // SCRIPTS PERSONALIZADOS - cola o código completo
    'custom_head_scripts' => [
        'label'=>'Scripts personalizados no <head> (cole o código completo, ex: Pixel, GA4, Clarity, Hotjar, verificações de domínio…)',
        'tipo'=>'script',
        'grupo'=>'Scripts Personalizados',
        'placeholder'=>"<!-- Cole aqui scripts que devem ficar no <head> -->\n<script>\n  // seu código\n</script>"
    ],
    'custom_body_scripts' => [
        'label'=>'Scripts personalizados antes de </body> (ex: chat, remarketing, scripts de tracking pesados)',
        'tipo'=>'script',
        'grupo'=>'Scripts Personalizados',
        'placeholder'=>"<!-- Cole aqui scripts que devem ficar no rodapé -->\n<script>\n  // seu código\n</script>"
    ],
    'gtm_noscript' => [
        'label'=>'Código <noscript> do GTM (opcional - só se você usa GTM e quer também a tag noscript)',
        'tipo'=>'script',
        'grupo'=>'Scripts Personalizados',
        'placeholder'=>'<noscript><iframe src="https://www.googletagmanager.com/ns.html?id=GTM-XXXXXXX" ...></iframe></noscript>'
    ],

    // SOCIAL
    'og_image'    => ['label'=>'Imagem OG (Open Graph) - aparece ao compartilhar no WhatsApp / Facebook','tipo'=>'text','grupo'=>'Social','placeholder'=>'URL completa da imagem (1200x630px)'],

    // TÉCNICO
    'robots_txt'  => ['label'=>'robots.txt (conteúdo do arquivo robots.txt)','tipo'=>'textarea','grupo'=>'Técnico'],
    'sitemap_url' => ['label'=>'URL do Sitemap','tipo'=>'text','grupo'=>'Técnico','placeholder'=>'https://seusite.com/sitemap.xml'],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($_POST['seo'] as $chave => $valor) {
        // NÃO sanitiza scripts - precisa do HTML/JS literal
        $is_script = isset($seo_fields[$chave]) && $seo_fields[$chave]['tipo'] === 'script';
        $valor_salvar = $is_script ? trim($valor) : trim($valor);

        $exists = db()->prepare("SELECT id FROM " . table('configuracoes') . " WHERE chave = ?");
        $exists->execute([$chave]);
        if ($exists->fetch()) {
            set_config($chave, $valor_salvar);
        } else {
            $tipo = isset($seo_fields[$chave]) && in_array($seo_fields[$chave]['tipo'], ['textarea','script']) ? 'textarea' : 'text';
            db()->prepare("INSERT INTO " . table('configuracoes') . " (chave,valor,descricao,grupo,tipo,ativo) VALUES (?,?,?,?,?,1)")
                ->execute([$chave, $valor_salvar, $seo_fields[$chave]['label'] ?? $chave, 'seo', $tipo]);
        }
    }
    set_flash('success', 'Configurações SEO salvas! As alterações já estão ativas no site.');
    header('Location: seo.php'); exit;
}

// Carregar valores SEO
$valores = [];
foreach ($seo_fields as $chave => $opts) {
    $valores[$chave] = get_config($chave, '');
}

require_once __DIR__ . '/includes/header.php';
?>
<div class="page-header"><h1><i class="fas fa-search"></i> SEO</h1></div>
<div class="card">
    <div class="card-body">
        <div style="background:#fef3c7;border-left:4px solid #f59e0b;padding:12px 16px;border-radius:6px;margin-bottom:20px;font-size:0.875rem;color:#92400e;">
            <strong><i class="fas fa-lightbulb"></i> Dica:</strong>
            Você pode usar os IDs simples (Pixel ID, GA ID) que o sistema monta o script automaticamente,
            <strong>OU</strong> colar o código <code>&lt;script&gt;...&lt;/script&gt;</code> completo
            nos campos "Scripts personalizados". Se você preencher os dois, ambos serão injetados.
        </div>
        <form method="POST">
            <?php
            $grupo_atual = '';
            foreach ($seo_fields as $chave => $opts):
                if ($opts['grupo'] !== $grupo_atual):
                    if ($grupo_atual !== '') echo '</div>';
                    $grupo_atual = $opts['grupo'];
            ?>
            <h3 style="margin:20px 0 12px;padding-bottom:8px;border-bottom:2px solid var(--gray-200);font-size:1rem;font-weight:700;color:var(--gray-900);">
                <?php echo $grupo_atual; ?>
                <?php if (isset($help_links[$grupo_atual])): ?>
                <a href="<?php echo $help_links[$grupo_atual]; ?>" target="_blank" rel="noopener noreferrer"
                   style="font-size:0.75rem;font-weight:400;color:var(--primary);text-decoration:none;margin-left:8px;vertical-align:middle;">
                    <i class="fas fa-external-link-alt"></i> Como obter?
                </a>
                <?php endif; ?>
            </h3>
            <div class="form-row form-row-2">
            <?php endif; ?>
                <div class="form-group" style="<?php echo in_array($opts['tipo'],['textarea','script'])?'grid-column:1/-1;':''; ?>">
                    <label><?php echo $opts['label']; ?></label>
                    <?php if ($opts['tipo'] === 'textarea'): ?>
                    <textarea name="seo[<?php echo $chave; ?>]" rows="3" placeholder="<?php echo $opts['placeholder']??''; ?>"><?php echo sanitize($valores[$chave]); ?></textarea>
                    <?php elseif ($opts['tipo'] === 'script'): ?>
                    <textarea name="seo[<?php echo $chave; ?>]" rows="8"
                              style="font-family:'Courier New', monospace;font-size:0.8125rem;background:#0f172a;color:#e2e8f0;border-color:#334155;"
                              placeholder="<?php echo htmlspecialchars($opts['placeholder']??'', ENT_QUOTES); ?>"
                              spellcheck="false"><?php echo htmlspecialchars($valores[$chave], ENT_QUOTES); ?></textarea>
                    <small style="color:var(--gray-500);font-size:0.75rem;display:block;margin-top:4px;">
                        ⚠️ Cole o código exatamente como o fornecedor (Meta, Google, etc.) disponibiliza — incluindo as tags <code>&lt;script&gt;</code>.
                    </small>
                    <?php else: ?>
                    <input type="text" name="seo[<?php echo $chave; ?>]" value="<?php echo sanitize($valores[$chave]); ?>" placeholder="<?php echo $opts['placeholder']??''; ?>">
                    <?php endif; ?>
                </div>
            <?php endforeach; if ($grupo_atual) echo '</div>'; ?>
            <div class="form-actions" style="margin-top:24px;">
                <button type="submit" class="btn btn-primary btn-lg"><i class="fas fa-save"></i> Salvar SEO</button>
            </div>
        </form>
    </div>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>