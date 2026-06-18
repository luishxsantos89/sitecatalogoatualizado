<?php
require_once __DIR__ . '/includes/functions.php';

// === CONTROLE DE ACESSO ===
require_auth();
if (!check_permission('admin')) {
    header('Location: ' . admin_url());
    exit('Acesso negado.');
}

$page_title = 'SEO';

/**
 * SEO simplificado — apenas 3 campos:
 *  - custom_head_scripts : scripts que vão no <head> (Pixel, GA, GTM, tags, verificações…)
 *  - custom_body_scripts : scripts que vão antes do </body> (chat, remarketing, tracking pesado…)
 *  - custom_css          : CSS personalizado, injetado dentro de <style> no <head>
 */
$seo_fields = [
    'custom_head_scripts' => [
        'label'       => 'Scripts no <head> — Pixel, Tags, Verificações de domínio, etc.',
        'descricao'   => 'Cole aqui o código completo (com as tags &lt;script&gt;…&lt;/script&gt;) fornecido pelo Meta, Google, TikTok, Pinterest, etc. Tudo que precisa ficar no &lt;head&gt; do site.',
        'placeholder' => "<!-- Pixel do Meta / Facebook -->\n<script>\n  !function(f,b,e,v,n,t,s)...\n</script>\n\n<!-- Google Analytics -->\n<script async src=\"https://www.googletagmanager.com/gtag/js?id=G-XXXXXXX\"></script>\n<script>...</script>",
    ],
    'custom_body_scripts' => [
        'label'       => 'Scripts antes de </body> — Chat, Remarketing, scripts pesados',
        'descricao'   => 'Cole aqui scripts que ficam no rodapé da página (chat online, remarketing, scripts de tracking pesados que não bloqueiam o carregamento).',
        'placeholder' => "<!-- Chat online -->\n<script>\n  // código do chat\n</script>",
    ],
    'custom_css' => [
        'label'       => 'CSS Personalizado',
        'descricao'   => 'Cole aqui regras CSS personalizadas. Serão injetadas dentro de uma tag &lt;style&gt; no &lt;head&gt; do site. NÃO inclua as tags &lt;style&gt;.',
        'placeholder' => "/* Exemplo: aumentar o tamanho do título */\n.modal-produto-titulo {\n    font-size: 1.5rem;\n}\n\n/* Esconder algum elemento */\n.minha-classe { display: none; }",
    ],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($seo_fields as $chave => $opts) {
        $valor = isset($_POST['seo'][$chave]) ? trim($_POST['seo'][$chave]) : '';

        try {
            $exists = db()->prepare("SELECT id FROM " . table('configuracoes') . " WHERE chave = ?");
            $exists->execute([$chave]);
            if ($exists->fetch()) {
                // Atualiza diretamente — usar UPDATE puro, sem passar por set_config
                // (set_config sanitiza algumas chaves; aqui queremos o código bruto).
                db()->prepare("UPDATE " . table('configuracoes') . " SET valor = ? WHERE chave = ?")->execute([$valor, $chave]);
            } else {
                db()->prepare("INSERT INTO " . table('configuracoes') . " (chave,valor,descricao,grupo,tipo,ativo) VALUES (?,?,?,?,?,1)")
                    ->execute([$chave, $valor, $opts['label'], 'seo', 'textarea']);
            }
        } catch (Exception $e) {
            set_flash('error', 'Erro ao salvar SEO: ' . $e->getMessage());
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
            Cole aqui qualquer código de tracking (Pixel do Meta, Google Analytics, GTM, TikTok, Pinterest, Hotjar, Clarity, etc.)
            ou CSS personalizado. Tudo é injetado automaticamente no catálogo público.
        </div>

        <form method="POST">

            <!-- ============ SCRIPTS NO HEAD ============ -->
            <h3 style="margin:20px 0 12px;padding-bottom:8px;border-bottom:2px solid var(--gray-200);font-size:1rem;font-weight:700;color:var(--gray-900);">
                <i class="fas fa-code" style="color:var(--primary);margin-right:6px;"></i>
                Scripts no &lt;head&gt; (Pixel, Tags, Analytics)
            </h3>
            <div class="form-group" style="grid-column:1/-1;">
                <label><?php echo $seo_fields['custom_head_scripts']['label']; ?></label>
                <small style="color:var(--gray-500);font-size:0.8rem;display:block;margin-bottom:6px;">
                    <?php echo $seo_fields['custom_head_scripts']['descricao']; ?>
                </small>
                <textarea name="seo[custom_head_scripts]" rows="10"
                          style="font-family:'Courier New', monospace;font-size:0.8125rem;background:#0f172a;color:#e2e8f0;border-color:#334155;width:100%;padding:12px;border-radius:8px;"
                          placeholder="<?php echo htmlspecialchars($seo_fields['custom_head_scripts']['placeholder'], ENT_QUOTES); ?>"
                          spellcheck="false"><?php echo htmlspecialchars($valores['custom_head_scripts'], ENT_QUOTES); ?></textarea>
            </div>

            <!-- ============ SCRIPTS NO BODY ============ -->
            <h3 style="margin:28px 0 12px;padding-bottom:8px;border-bottom:2px solid var(--gray-200);font-size:1rem;font-weight:700;color:var(--gray-900);">
                <i class="fas fa-terminal" style="color:var(--primary);margin-right:6px;"></i>
                Scripts antes de &lt;/body&gt; (Chat, Remarketing, etc.)
            </h3>
            <div class="form-group" style="grid-column:1/-1;">
                <label><?php echo $seo_fields['custom_body_scripts']['label']; ?></label>
                <small style="color:var(--gray-500);font-size:0.8rem;display:block;margin-bottom:6px;">
                    <?php echo $seo_fields['custom_body_scripts']['descricao']; ?>
                </small>
                <textarea name="seo[custom_body_scripts]" rows="10"
                          style="font-family:'Courier New', monospace;font-size:0.8125rem;background:#0f172a;color:#e2e8f0;border-color:#334155;width:100%;padding:12px;border-radius:8px;"
                          placeholder="<?php echo htmlspecialchars($seo_fields['custom_body_scripts']['placeholder'], ENT_QUOTES); ?>"
                          spellcheck="false"><?php echo htmlspecialchars($valores['custom_body_scripts'], ENT_QUOTES); ?></textarea>
            </div>

            <!-- ============ CSS PERSONALIZADO ============ -->
            <h3 style="margin:28px 0 12px;padding-bottom:8px;border-bottom:2px solid var(--gray-200);font-size:1rem;font-weight:700;color:var(--gray-900);">
                <i class="fab fa-css3-alt" style="color:var(--primary);margin-right:6px;"></i>
                CSS Personalizado
            </h3>
            <div class="form-group" style="grid-column:1/-1;">
                <label><?php echo $seo_fields['custom_css']['label']; ?></label>
                <small style="color:var(--gray-500);font-size:0.8rem;display:block;margin-bottom:6px;">
                    <?php echo $seo_fields['custom_css']['descricao']; ?>
                </small>
                <textarea name="seo[custom_css]" rows="10"
                          style="font-family:'Courier New', monospace;font-size:0.8125rem;background:#0f172a;color:#e2e8f0;border-color:#334155;width:100%;padding:12px;border-radius:8px;"
                          placeholder="<?php echo htmlspecialchars($seo_fields['custom_css']['placeholder'], ENT_QUOTES); ?>"
                          spellcheck="false"><?php echo htmlspecialchars($valores['custom_css'], ENT_QUOTES); ?></textarea>
            </div>

            <!-- ============ BOTÃO SALVAR ============ -->
            <div class="form-actions" style="margin-top:30px;display:flex;justify-content:flex-end;gap:10px;">
                <button type="submit" class="btn btn-primary btn-lg" style="padding:12px 28px;font-size:1rem;">
                    <i class="fas fa-save"></i> Salvar Alterações
                </button>
            </div>

        </form>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>