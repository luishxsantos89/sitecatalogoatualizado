<?php
/**
 * includes/email_reader.php
 * Bloco de leitura de um email — reutilizado no layout "lista" (página cheia)
 * e no layout "dividido" (painel direito). Espera $email, $pasta, $todas_etiquetas.
 */
if (!$email) return;
?>
<div class="card" style="border:none;box-shadow:none;">
    <div class="card-header" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px;">
        <h3 style="margin:0;font-size:1rem;flex:1;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
            <i class="fas <?php echo $email['starred'] ? 'fa-star' : 'fa-envelope-open'; ?>" style="<?php echo $email['starred'] ? 'color:#f59e0b;' : ''; ?>"></i>
            <?php echo sanitize($email['assunto']); ?>
        </h3>
        <div style="display:flex;gap:6px;flex-shrink:0;align-items:center;">
            <?php if (!empty($email['remetente_email'])): ?>
            <button type="button" class="btn btn-sm btn-primary" onclick="abrirCompositorResposta(<?php echo $email['id']; ?>)"><i class="fas fa-reply"></i> Responder</button>
            <?php endif; ?>

            <!-- Etiquetas -->
            <div class="label-picker">
                <button type="button" class="btn btn-sm btn-light" onclick="toggleLabelPicker(event, <?php echo $email['id']; ?>)" title="Etiquetas"><i class="fas fa-tag"></i></button>
                <div class="label-picker-menu" id="label_menu_<?php echo $email['id']; ?>">
                    <?php foreach ($todas_etiquetas as $et): $ativa = in_array($et['id'], array_column($email['etiquetas'], 'id')); ?>
                    <div class="label-picker-item" onclick="aplicarEtiqueta(<?php echo $email['id']; ?>, <?php echo $et['id']; ?>, this)">
                        <span class="label-dot" style="background:<?php echo sanitize($et['cor']); ?>;"></span>
                        <span style="flex:1;"><?php echo sanitize($et['nome']); ?></span>
                        <i class="fas fa-check" style="color:#10b981;<?php echo $ativa?'':'display:none;'; ?>"></i>
                    </div>
                    <?php endforeach; ?>
                    <?php if (empty($todas_etiquetas)): ?>
                    <p style="font-size:0.75rem;color:var(--gray-400);padding:6px;margin:0;">Nenhuma etiqueta criada</p>
                    <?php endif; ?>
                </div>
            </div>

            <a href="email.php?action=star&id=<?php echo $email['id']; ?>&pasta=<?php echo $pasta; ?>"
               class="btn btn-sm btn-light" title="<?php echo $email['starred'] ? 'Remover favorito' : 'Favoritar'; ?>">
                <i class="fas fa-star" style="<?php echo $email['starred'] ? 'color:#f59e0b;' : ''; ?>"></i>
            </a>
            <?php if ($pasta !== 'trash'): ?>
            <a href="email.php?action=mover&id=<?php echo $email['id']; ?>&para=archive&pasta=<?php echo $pasta; ?>" class="btn btn-sm btn-light" title="Arquivar"><i class="fas fa-archive"></i></a>
            <a href="email.php?action=mover&id=<?php echo $email['id']; ?>&para=trash&pasta=<?php echo $pasta; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Mover para lixeira?')"><i class="fas fa-trash"></i></a>
            <?php endif; ?>
            <?php if ($layout_email === 'lista'): ?>
            <a href="email.php?pasta=<?php echo $pasta; ?>" class="btn btn-sm btn-secondary"><i class="fas fa-arrow-left"></i></a>
            <?php endif; ?>
        </div>
    </div>
    <div class="card-body">
        <div style="margin-bottom:16px;padding:14px;background:var(--gray-50);border-radius:8px;font-size:0.875rem;">
            <p style="margin:0 0 4px;">
                <strong>De:</strong> <?php echo sanitize($email['remetente_nome']); ?>
                <?php if ($email['remetente_email']): ?>&lt;<a href="mailto:<?php echo sanitize($email['remetente_email']); ?>"><?php echo sanitize($email['remetente_email']); ?></a>&gt;<?php endif; ?>
            </p>
            <p style="margin:0 0 4px;"><strong>Para:</strong> <?php echo sanitize($email['destinatario_email']); ?></p>
            <p style="margin:0;"><strong>Data:</strong> <?php echo format_date($email['data_envio'], 'd/m/Y H:i'); ?></p>
        </div>

        <?php if (!empty($email['etiquetas'])): ?>
        <div style="margin-bottom:14px;display:flex;gap:5px;flex-wrap:wrap;">
            <?php foreach ($email['etiquetas'] as $et): ?>
            <span class="tag-badge" style="background:<?php echo sanitize($et['cor']); ?>;font-size:0.72rem;padding:3px 9px;"><?php echo sanitize($et['nome']); ?></span>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <?php if (!empty($email['corpo_html'])): ?>
        <div style="font-size:0.9375rem;line-height:1.75;color:var(--gray-700);overflow-wrap:break-word;">
            <?php echo $email['corpo_html']; ?>
        </div>
        <?php else: ?>
        <div style="white-space:pre-wrap;font-size:0.9375rem;line-height:1.75;color:var(--gray-700);">
            <?php echo nl2br(sanitize($email['corpo'])); ?>
        </div>
        <?php endif; ?>

        <?php if ($pasta === 'trash'): ?>
        <div style="margin-top:20px;padding-top:16px;border-top:1px solid var(--gray-200);display:flex;gap:8px;">
            <a href="email.php?action=delete_perm&id=<?php echo $email['id']; ?>&pasta=trash" class="btn btn-danger btn-sm" onclick="return confirm('Excluir permanentemente? Esta ação não pode ser desfeita.')"><i class="fas fa-trash-alt"></i> Excluir Permanentemente</a>
            <a href="email.php?action=mover&id=<?php echo $email['id']; ?>&para=inbox&pasta=trash" class="btn btn-light btn-sm"><i class="fas fa-undo"></i> Restaurar</a>
        </div>
        <?php endif; ?>
    </div>
</div>