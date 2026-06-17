<?php
/**
 * SiteCatalogo2 - Configurações
 */
require_once __DIR__ . '/includes/functions.php';
$page_title = 'Configurações';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Chaves extras que podem não existir ainda na tabela do banco
        $chaves_extras = [
            'toast_position',
            'produtos_navegacao',
            'empresa_sobre',
            'empresa_slogan',
            'alerta_sonoro_orcamento',
            'produto_visualizacao',
        ];

        foreach ($_POST['config'] as $chave => $valor) {
            $valor = trim($valor);
            if (in_array($chave, $chaves_extras)) {
                // Usa INSERT ... ON DUPLICATE KEY UPDATE para garantir que funciona mesmo sem linha no banco
                try {
                    $existe = db()->prepare("SELECT COUNT(*) FROM " . table('configuracoes') . " WHERE chave = ?");
                    $existe->execute([$chave]);
                    if ((int)$existe->fetchColumn() > 0) {
                        db()->prepare("UPDATE " . table('configuracoes') . " SET valor = ? WHERE chave = ?")->execute([$valor, $chave]);
                    } else {
                        // Descobre o grupo correto
                        $grupo_extra = ($chave === 'empresa_sobre' || $chave === 'empresa_slogan') ? 'geral' : 'aparencia';
                        $tipo_extra  = ($chave === 'empresa_sobre') ? 'textarea' : (($chave === 'empresa_slogan') ? 'text' : 'select');
                        db()->prepare("INSERT INTO " . table('configuracoes') . " (chave, valor, grupo, tipo, ativo, ordem) VALUES (?, ?, ?, ?, 1, 99)")->execute([$chave, $valor, $grupo_extra, $tipo_extra]);
                    }
                } catch (Exception $e2) {
                    // fallback silencioso
                }
            } else {
                set_config($chave, $valor);
            }
        }
        if (!empty($_FILES['config']['name']['logo_cliente'])) {
            $up = handle_upload(['name'=>$_FILES['config']['name']['logo_cliente'],'tmp_name'=>$_FILES['config']['tmp_name']['logo_cliente'],'error'=>$_FILES['config']['error']['logo_cliente']], 'config');
            if ($up) { $old = get_config('logo_cliente'); if ($old) delete_upload($old); set_config('logo_cliente', $up); }
        }
        log_activity('update', 'configuracoes', 'Configurações atualizadas');
        set_flash('success', 'Configurações salvas com sucesso!');
    } catch (Exception $e) { set_flash('error', 'Erro: ' . $e->getMessage()); }
    header('Location: configuracoes.php'); exit;
}

$configuracoes = db()->query("SELECT * FROM " . table('configuracoes') . " WHERE ativo = 1 AND grupo != 'email' AND grupo != 'seo' AND chave != 'categoria_layout' AND chave NOT IN ('toast_position','produtos_navegacao','empresa_sobre','empresa_slogan','alerta_sonoro_orcamento','produto_visualizacao') ORDER BY CASE WHEN grupo='geral' THEN 1 WHEN grupo='contato' THEN 2 WHEN grupo='social' THEN 3 WHEN grupo='aparencia' THEN 4 ELSE 5 END, ordem, id")->fetchAll();

// Ícones para redes sociais
$social_icons = [
    'facebook_url'  => ['fab fa-facebook', '#1877f2'],
    'instagram_url' => ['fab fa-instagram', '#e4405f'],
    'linkedin_url'  => ['fab fa-linkedin', '#0a66c2'],
    'youtube_url'   => ['fab fa-youtube', '#ff0000'],
    'tiktok_url'    => ['fab fa-tiktok', '#000000'],
    'twitter_url'   => ['fab fa-x-twitter', '#000000'],
    'pinterest_url' => ['fab fa-pinterest', '#e60023'],
    'telegram_url'  => ['fab fa-telegram', '#24a1de'],
    'kwai_url'      => ['fas fa-play-circle', '#ff6a00'],
    'threads_url'   => ['fab fa-threads', '#000000'],
    'discord_url'   => ['fab fa-discord', '#5865f2'],
    'snapchat_url'  => ['fab fa-snapchat', '#fffc00'],
];

// Campos extras que não vêm do banco mas precisam aparecer na configuração
$extra_fields = [
    [
        'chave'    => 'empresa_sobre',
        'descricao'=> 'Sobre a Empresa (texto exibido na seção "Quem Somos")',
        'grupo'    => 'geral',
        'tipo'     => 'textarea',
        'valor'    => get_config('empresa_sobre', ''),
        'ativo'    => 1,
    ],
    [
        'chave'    => 'empresa_slogan',
        'descricao'=> 'Slogan / Frase de Destaque da Empresa',
        'grupo'    => 'geral',
        'tipo'     => 'text',
        'valor'    => get_config('empresa_slogan', ''),
        'ativo'    => 1,
    ],
    [
        'chave' => 'produto_visualizacao',
        'descricao' => 'Forma de Visualização do Produto (ao clicar em um produto)',
        'grupo' => 'aparencia',
        'tipo' => 'select',
        'valor' => get_config('produto_visualizacao', 'modal'),
        'opcoes' => json_encode([
            'modal'             => 'Catálogo Simples (modal sobre a lista) — atual',
            'pagina_individual' => 'Página Individual do Produto (similar ao WooCommerce) — melhor para SEO',
        ]),
        'ativo' => 1,
    ],
    [
        'chave' => 'produtos_navegacao',
        'descricao' => 'Navegação de Produtos',
        'grupo' => 'aparencia',
        'tipo' => 'select',
        'valor' => get_config('produtos_navegacao', 'paginacao'),
        'opcoes' => json_encode([
            'paginacao'       => 'Paginação (botões Anterior / Próximo)',
            'scroll_infinito' => 'Scroll Infinito (carrega ao rolar a página)',
        ]),
        'ativo' => 1,
    ],
    [
        'chave' => 'toast_position',
        'descricao' => 'Posição do Toast de Produto Adicionado',
        'grupo' => 'aparencia',
        'tipo' => 'select',
        'valor' => get_config('toast_position', 'bottom-right'),
        'opcoes' => json_encode([
            'bottom-left'  => 'Rodapé Esquerdo',
            'bottom-center'=> 'Rodapé Centro',
            'bottom-right' => 'Rodapé Direito',
        ]),
        'ativo' => 1,
    ],
    [
        'chave' => 'alerta_sonoro_orcamento',
        'descricao' => 'Alerta Sonoro — Novos Orçamentos (toca um som quando um novo orçamento chega; só funciona logado no admin)',
        'grupo' => 'aparencia',
        'tipo' => 'select',
        'valor' => get_config('alerta_sonoro_orcamento', '1'),
        'opcoes' => json_encode([
            '1' => 'Ativado',
            '0' => 'Desativado',
        ]),
        'ativo' => 1,
    ],
];

// Mesclar campos extras
$configuracoes = array_merge($configuracoes, $extra_fields);

// Reordenar
usort($configuracoes, function($a, $b) {
    $order = ['geral' => 1, 'contato' => 2, 'social' => 3, 'aparencia' => 4];
    $oa = $order[$a['grupo']] ?? 5;
    $ob = $order[$b['grupo']] ?? 5;
    if ($oa !== $ob) return $oa - $ob;
    return ($a['ordem'] ?? 0) - ($b['ordem'] ?? 0);
});

require_once __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <h1><i class="fas fa-cog"></i> Configurações</h1>
</div>

<div class="card">
    <div class="card-body">
        <form method="POST" enctype="multipart/form-data">
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
                <div class="form-group" style="<?php echo in_array($cfg['tipo'],['textarea','file'])?'grid-column:1/-1;':''; ?>">
                    <label>
                        <?php if ($cfg['grupo'] === 'social' && isset($social_icons[$cfg['chave']])): 
                            [$ico, $cor] = $social_icons[$cfg['chave']]; ?>
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

            <div class="form-actions" style="margin-top:24px;">
                <button type="submit" class="btn btn-primary btn-lg"><i class="fas fa-save"></i> Salvar Configurações</button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>