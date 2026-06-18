<?php
/**
 * SiteCatalogo2 - Banners (v2 - com posições, popup e prazo)
 */
require_once __DIR__ . '/includes/functions.php';
$page_title = 'Banners';

$action = $_GET['action'] ?? 'list';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $posicao = $_POST['posicao'] ?? 'slide';

    $dados = [
        'titulo'          => trim($_POST['titulo'] ?? ''),
        'subtitulo'       => trim($_POST['subtitulo'] ?? ''),
        'link'            => trim($_POST['link'] ?? ''),
        'posicao'         => $posicao,
        'ordem'           => (int)($_POST['ordem'] ?? 0),
        'ativo'           => isset($_POST['ativo']) ? 1 : 0,
        // Popup
        'popup_delay'     => ($posicao === 'popup') ? (int)($_POST['popup_delay'] ?? 0) : 0,
        'popup_fechar'    => ($posicao === 'popup') ? trim($_POST['popup_fechar'] ?? 'botao') : 'botao',
        'popup_freq_max'  => ($posicao === 'popup') ? (int)($_POST['popup_freq_max'] ?? 0) : 0,
        'popup_intervalo' => ($posicao === 'popup') ? (int)($_POST['popup_intervalo'] ?? 0) : 0,
        // Prazo
        'prazo_fixo'      => isset($_POST['prazo_fixo']) ? 1 : 0,
        'data_inicio'     => (!isset($_POST['prazo_fixo']) && !empty($_POST['data_inicio'])) ? $_POST['data_inicio'] : null,
        'data_fim'        => (!isset($_POST['prazo_fixo']) && !empty($_POST['data_fim']))    ? $_POST['data_fim']    : null,
    ];

    if (!empty($_FILES['imagem']['name'])) {
        $up = handle_upload($_FILES['imagem'], 'banners');
        if ($up) {
            if ($id && !empty($_POST['imagem_atual'])) delete_upload($_POST['imagem_atual']);
            $dados['imagem'] = $up;
        }
    } elseif (!$id) {
        set_flash('error', 'Imagem é obrigatória para novos banners');
        header('Location: banners.php'); exit;
    }

    try {
        if ($id) {
            $f = []; $v = [];
            foreach ($dados as $k => $val) { $f[] = "{$k} = ?"; $v[] = $val; }
            $v[] = $id;
            db()->prepare("UPDATE " . table('banners') . " SET " . implode(', ', $f) . " WHERE id = ?")->execute($v);
            set_flash('success', 'Banner atualizado!');
        } else {
            $cols = implode(', ', array_keys($dados));
            $ph   = implode(', ', array_fill(0, count($dados), '?'));
            db()->prepare("INSERT INTO " . table('banners') . " ({$cols}) VALUES ({$ph})")->execute(array_values($dados));
            set_flash('success', 'Banner criado!');
        }
        header('Location: banners.php'); exit;
    } catch (Exception $e) { set_flash('error', 'Erro: ' . $e->getMessage()); }
}

if ($action === 'delete' && $id) {
    $b = db()->prepare("SELECT imagem FROM " . table('banners') . " WHERE id = ?"); $b->execute([$id]); $ban = $b->fetch();
    if ($ban && $ban['imagem']) delete_upload($ban['imagem']);
    db()->prepare("DELETE FROM " . table('banners') . " WHERE id = ?")->execute([$id]);
    set_flash('success', 'Banner excluído!');
    header('Location: banners.php'); exit;
}

$banner = null;
if ($action === 'edit' && $id) {
    $stmt = db()->prepare("SELECT * FROM " . table('banners') . " WHERE id = ?"); $stmt->execute([$id]); $banner = $stmt->fetch();
}

$banners = db()->query("SELECT * FROM " . table('banners') . " ORDER BY posicao, ordem")->fetchAll();

// Helper: label da posição
function posicao_label($p) {
    return match($p) {
        'slide'     => 'Slide (Topo)',
        'categoria' => 'Categoria (Sidebar)',
        'popup'     => 'Popup',
        default     => ucfirst($p),
    };
}

// Helper: label do prazo
function prazo_label($row) {
    if ($row['prazo_fixo'] ?? 0) return '<span style="color:#6b7280;">Fixo</span>';
    $ini = $row['data_inicio'] ?? '';
    $fim = $row['data_fim'] ?? '';
    if (!$ini && !$fim) return '<span style="color:#6b7280;">Fixo</span>';
    $out = '';
    if ($ini) $out .= date('d/m/Y', strtotime($ini));
    $out .= ' → ';
    if ($fim) $out .= date('d/m/Y', strtotime($fim));
    return $out;
}

require_once __DIR__ . '/includes/header.php';
?>

<style>
/* ── Prazo toggle ── */
#bloco_prazo_datas { transition: opacity .2s; }
/* ── Popup extra ── */
#bloco_popup_extra { transition: opacity .2s; }
.form-section-label {
    font-size: .7rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .06em;
    color: var(--gray-400);
    margin: 18px 0 8px;
    border-bottom: 1px solid var(--gray-100);
    padding-bottom: 4px;
}
</style>

<div class="page-header">
    <h1><i class="fas fa-image"></i> Banners</h1>
    <button onclick="abrirModal()" class="btn btn-primary"><i class="fas fa-plus"></i> Novo Banner</button>
</div>

<!-- ═══════════════════════════════════════════════ MODAL ═══════════════════════════════════════════════ -->
<div id="modal" style="display:<?php echo ($action==='edit'||$action==='new')?'flex':'none'; ?>;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:1000;align-items:center;justify-content:center;">
    <div style="background:#fff;border-radius:12px;padding:32px;width:100%;max-width:580px;max-height:90vh;overflow-y:auto;">

        <h3 style="margin-bottom:20px;font-size:1.1rem;font-weight:700;">
            <i class="fas fa-image" style="color:var(--primary);"></i>
            <?php echo $banner?'Editar':'Novo'; ?> Banner
        </h3>

        <form method="POST" action="banners.php<?php echo $id?"?action=edit&id={$id}":"?action=new"; ?>" enctype="multipart/form-data">
            <input type="hidden" name="imagem_atual" value="<?php echo sanitize($banner['imagem']??''); ?>">

            <?php if (!empty($banner['imagem'])): ?>
            <img src="<?php echo uploads_url($banner['imagem']); ?>" style="width:100%;max-height:150px;object-fit:contain;border-radius:8px;margin-bottom:12px;">
            <?php endif; ?>

            <!-- Imagem -->
            <div class="form-group">
                <label>Imagem <?php echo !$banner?'*':''; ?></label>
                <input type="file" name="imagem" accept="image/*" <?php echo !$banner?'required':''; ?>>
                <small class="text-muted" id="img_dica">
                    <?php
                    $pos_atual = $banner['posicao'] ?? 'slide';
                    $dicas = [
                        'slide'     => 'Recomendado: 1200×380px — imagem panorâmica, texto deve ficar no terço esquerdo',
                        'categoria' => 'Recomendado: 300×180px — imagem quadrada ou levemente retangular, sem texto pequeno',
                        'popup'     => 'Recomendado: 600×400px — imagem vertical ou quadrada, texto centralizado e legível',
                    ];
                    echo $dicas[$pos_atual] ?? $dicas['slide'];
                    ?>
                </small>
            </div>

            <!-- Título / Subtítulo -->
            <div class="form-group"><label>Título</label><input type="text" name="titulo" value="<?php echo sanitize($banner['titulo']??''); ?>"></div>
            <div class="form-group"><label>Subtítulo</label><input type="text" name="subtitulo" value="<?php echo sanitize($banner['subtitulo']??''); ?>"></div>
            <div class="form-group"><label>Link (URL)</label><input type="text" name="link" value="<?php echo sanitize($banner['link']??''); ?>" placeholder="https://..."></div>

            <!-- ── Exibição ── -->
            <div class="form-section-label">Exibição</div>
            <div class="form-row form-row-3">
                <div class="form-group">
                    <label>Posição</label>
                    <select name="posicao" id="sel_posicao" onchange="togglePosicao(this.value)">
                        <option value="slide"     <?php echo selected($banner['posicao']??'slide','slide'); ?>>Slide (Topo)</option>
                        <option value="categoria" <?php echo selected($banner['posicao']??'','categoria'); ?>>Categoria (Sidebar)</option>
                        <option value="popup"     <?php echo selected($banner['posicao']??'','popup'); ?>>Popup</option>
                    </select>
                </div>
                <div class="form-group"><label>Ordem</label><input type="number" name="ordem" value="<?php echo (int)($banner['ordem']??0); ?>" min="0"></div>
                <div class="form-group" style="display:flex;align-items:flex-end;">
                    <div class="form-check">
                        <input type="checkbox" name="ativo" id="ativo_ban" <?php echo checked($banner['ativo']??1); ?>>
                        <label for="ativo_ban">Ativo</label>
                    </div>
                </div>
            </div>

            <!-- ── Popup extra ── -->
            <div id="bloco_popup_extra" style="display:<?php echo ($banner['posicao']??'')=='popup'?'block':'none'; ?>">
                <div class="form-section-label"><i class="fas fa-window-restore" style="margin-right:4px;"></i>Configurações do Popup</div>
                <div class="form-row" style="gap:12px;">
                    <div class="form-group" style="flex:1;">
                        <label>Aparecer após (segundos)</label>
                        <input type="number" name="popup_delay" id="popup_delay" min="0" max="300"
                               value="<?php echo (int)($banner['popup_delay']??0); ?>"
                               placeholder="0 = imediato">
                        <small class="text-muted">0 = exibe imediatamente ao carregar</small>
                    </div>
                    <div class="form-group" style="flex:1;">
                        <label>Como fechar o popup</label>
                        <select name="popup_fechar" id="popup_fechar">
                            <option value="botao"   <?php echo selected($banner['popup_fechar']??'botao','botao'); ?>>Botão fechar (×)</option>
                            <option value="fora"    <?php echo selected($banner['popup_fechar']??'','fora'); ?>>Clicar fora</option>
                            <option value="ambos"   <?php echo selected($banner['popup_fechar']??'','ambos'); ?>>Ambos</option>
                        </select>
                    </div>
                </div>
                <div class="form-row" style="gap:12px;margin-top:4px;">
                    <div class="form-group" style="flex:1;">
                        <label>Máximo de exibições por visitante</label>
                        <input type="number" name="popup_freq_max" id="popup_freq_max" min="0"
                               value="<?php echo (int)($banner['popup_freq_max']??0); ?>"
                               placeholder="0 = ilimitado">
                        <small class="text-muted">0 = sempre exibe. Ex: 1 = mostra só 1 vez por visitante</small>
                    </div>
                    <div class="form-group" style="flex:1;">
                        <label>Intervalo entre exibições (horas)</label>
                        <input type="number" name="popup_intervalo" id="popup_intervalo" min="0"
                               value="<?php echo (int)($banner['popup_intervalo']??0); ?>"
                               placeholder="0 = sem intervalo">
                        <small class="text-muted">0 = sem restrição. Ex: 24 = 1 vez por dia</small>
                    </div>
                </div>
                <div style="background:#fef9c3;border:1px solid #fef08a;border-radius:8px;padding:10px 14px;font-size:0.8rem;color:#854d0e;margin-top:4px;">
                    <i class="fas fa-lightbulb" style="margin-right:4px;"></i>
                    <strong>Dica:</strong> Para um popup de promoção por tempo limitado, combine <em>Máx. exibições = 1</em> com o <em>Prazo de exibição</em> abaixo. Para newsletter semanal, use <em>Intervalo = 168</em> (7 dias).
                </div>
            </div>

            <!-- ── Prazo ── -->
            <div class="form-section-label"><i class="fas fa-calendar-alt" style="margin-right:4px;"></i>Prazo de Exibição</div>
            <div class="form-check" style="margin-bottom:12px;">
                <input type="checkbox" name="prazo_fixo" id="prazo_fixo"
                       <?php echo checked($banner['prazo_fixo']??1); ?>
                       onchange="togglePrazo(this.checked)">
                <label for="prazo_fixo">Exibição fixa (sem prazo)</label>
            </div>
            <div id="bloco_prazo_datas" style="display:<?php echo ($banner['prazo_fixo']??1)?'none':'block'; ?>;">
                <div class="form-row" style="gap:12px;">
                    <div class="form-group" style="flex:1;">
                        <label>Data de início</label>
                        <input type="date" name="data_inicio"
                               value="<?php echo sanitize($banner['data_inicio']??''); ?>">
                    </div>
                    <div class="form-group" style="flex:1;">
                        <label>Data de fim</label>
                        <input type="date" name="data_fim"
                               value="<?php echo sanitize($banner['data_fim']??''); ?>">
                    </div>
                </div>
                <small class="text-muted" style="display:block;margin-top:-8px;margin-bottom:12px;">
                    <i class="fas fa-info-circle"></i>
                    Banners fora do prazo serão ocultados automaticamente mesmo estando ativos.
                </small>
            </div>

            <div style="display:flex;gap:10px;margin-top:8px;">
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Salvar</button>
                <button type="button" onclick="fecharModal()" class="btn btn-secondary">Cancelar</button>
            </div>
        </form>
    </div>
</div>

<!-- ═══════════════════════════════════════════════ TABELA ═══════════════════════════════════════════════ -->
<div class="card">
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Imagem</th>
                    <th>Título</th>
                    <th>Posição</th>
                    <th>Prazo</th>
                    <th>Ordem</th>
                    <th>Status</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($banners)): ?>
                <tr><td colspan="7"><div class="empty-state-sm">Nenhum banner cadastrado</div></td></tr>
                <?php else: ?>
                <?php foreach ($banners as $b): ?>
                <?php
                    // Verificar se está dentro do prazo para exibir aviso visual
                    $hoje = date('Y-m-d');
                    $fora_prazo = false;
                    if (!($b['prazo_fixo'] ?? 0)) {
                        if (!empty($b['data_inicio']) && $b['data_inicio'] > $hoje) $fora_prazo = true;
                        if (!empty($b['data_fim'])    && $b['data_fim']    < $hoje) $fora_prazo = true;
                    }
                ?>
                <tr <?php echo $fora_prazo ? 'style="opacity:.55;"' : ''; ?>>
                    <td>
                        <img src="<?php echo uploads_url($b['imagem']); ?>"
                             style="width:120px;height:48px;object-fit:cover;border-radius:6px;">
                    </td>
                    <td>
                        <strong><?php echo sanitize($b['titulo']??'-'); ?></strong>
                        <?php if ($b['link']): ?>
                        <br><small><a href="<?php echo sanitize($b['link']); ?>" target="_blank" style="color:var(--primary);"><?php echo sanitize($b['link']); ?></a></small>
                        <?php endif; ?>
                        <?php if ($b['posicao'] === 'popup'): ?>
                        <br><small style="color:var(--gray-400);">
                            <i class="fas fa-clock"></i> <?php echo (int)($b['popup_delay']??0); ?>s delay &nbsp;
                            <i class="fas fa-redo"></i> <?php echo ((int)($b['popup_freq_max']??0)) === 0 ? 'ilimitado' : (int)$b['popup_freq_max'].'x'; ?> &nbsp;
                            <i class="fas fa-hourglass-half"></i> <?php echo ((int)($b['popup_intervalo']??0)) === 0 ? 'sem intervalo' : (int)$b['popup_intervalo'].'h'; ?>
                        </small>
                        <?php endif; ?>
                        <?php if ($fora_prazo): ?>
                        <br><small style="color:#ef4444;"><i class="fas fa-exclamation-triangle"></i> Fora do prazo</small>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php
                        $icon = match($b['posicao']) {
                            'slide'     => 'fa-images',
                            'categoria' => 'fa-th-list',
                            'popup'     => 'fa-window-restore',
                            default     => 'fa-image',
                        };
                        ?>
                        <i class="fas <?php echo $icon; ?>" style="color:var(--primary);margin-right:4px;"></i>
                        <?php echo posicao_label($b['posicao']); ?>
                    </td>
                    <td style="font-size:.85rem;"><?php echo prazo_label($b); ?></td>
                    <td><?php echo $b['ordem']; ?></td>
                    <td>
                        <span class="badge-status <?php echo $b['ativo']?'status-aprovado':'status-inativo'; ?>">
                            <?php echo $b['ativo']?'Ativo':'Inativo'; ?>
                        </span>
                    </td>
                    <td>
                        <a href="banners.php?action=edit&id=<?php echo $b['id']; ?>" class="btn btn-sm btn-primary">
                            <i class="fas fa-edit"></i>
                        </a>
                        <a href="banners.php?action=delete&id=<?php echo $b['id']; ?>" class="btn btn-sm btn-danger"
                           onclick="return confirm('Excluir banner?')">
                            <i class="fas fa-trash"></i>
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
function abrirModal() {
    document.getElementById('modal').style.display = 'flex';
}
function fecharModal() {
    document.getElementById('modal').style.display = 'none';
}

function togglePosicao(val) {
    document.getElementById('bloco_popup_extra').style.display = (val === 'popup') ? 'block' : 'none';
    var dicas = {
        'slide'     : 'Recomendado: 1200×380px — imagem panorâmica, texto deve ficar no terço esquerdo',
        'categoria' : 'Recomendado: 300×180px — imagem quadrada ou levemente retangular, sem texto pequeno',
        'popup'     : 'Recomendado: 600×400px — imagem vertical ou quadrada, texto centralizado e legível'
    };
    var el = document.getElementById('img_dica');
    if (el) el.textContent = dicas[val] || dicas['slide'];
}

function togglePrazo(fixo) {
    document.getElementById('bloco_prazo_datas').style.display = fixo ? 'none' : 'block';
}

// Inicializar estado correto ao abrir em modo edição
(function() {
    const sel = document.getElementById('sel_posicao');
    if (sel) togglePosicao(sel.value);
    const chk = document.getElementById('prazo_fixo');
    if (chk) togglePrazo(chk.checked);
})();
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>