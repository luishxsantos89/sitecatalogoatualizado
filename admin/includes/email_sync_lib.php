<?php
/**
 * includes/email_sync_lib.php
 * Biblioteca compartilhada de funcoes SMTP/IMAP.
 * Usada tanto por admin/email.php (interface) quanto por
 * admin/email_cron.php (sincronizacao automatica via cron job).
 * Mantem a logica em um unico lugar — sem duplicacao de codigo.
 * 
 * v2.0: Adicionada funcao senha_unificada() para suporte a senha unica
 */

if (function_exists('smtp_config')) return; // evita redeclaracao se incluido 2x

// ————————————————————————————————————————————————————————————
// SANITIZACAO DE HTML PARA EXIBICAO
// Emails recebidos via IMAP vem de remetentes externos nao confiaveis
// e podem conter <script>, iframes, formularios, etc. Esta funcao
// remove tudo que nao seja formatacao visual basica antes de exibir.
// ————————————————————————————————————————————————————————————
function sanitize_email_html(string $html): string {
    if (empty($html)) return '';

    // Remove blocos inteiros de script/style/iframe/object/embed/form (e seu conteudo)
    $html = preg_replace('#<(script|style|iframe|object|embed|form|link|meta)\b[^>]*>.*?</\1>#is', '', $html);
    $html = preg_replace('#<(script|style|iframe|object|embed|form|link|meta)\b[^>]*/?>#is', '', $html);

    // Mantem apenas tags de formatacao/estrutura seguras
    $tags_permitidas = '<p><br><div><span><b><strong><i><em><u><strike><s><ul><ol><li><a><blockquote>'
                      . '<h1><h2><h3><h4><h5><h6><img><table><thead><tbody><tr><td><th><hr><pre><code><font>';
    $html = strip_tags($html, $tags_permitidas);

    // Remove atributos de evento inline (onclick, onerror, onload, etc.)
    $html = preg_replace('/\son\w+\s*=\s*"[^"]*"/i', '', $html);
    $html = preg_replace("/\son\w+\s*=\s*'[^']*'/i", '', $html);
    $html = preg_replace('/\son\w+\s*=\s*[^\s>]+/i', '', $html);

    // Neutraliza javascript: e data: em href/src (mantem http/https/mailto/cid)
    $html = preg_replace('%(href|src)\s*=\s*[\'"]javascript:[^\'"]*[\'"]%i', '$1="#"', $html);
    $html = preg_replace('%(href|src)\s*=\s*[\'"]data:text/html[^\'"]*[\'"]%i', '$1="#"', $html);

    return $html;
}

// ————————————————————————————————————————————————————————————
// NOVO v2.0: SENHA UNIFICADA
// Retorna a senha a ser usada, priorizando senha_unificada
// com fallback para senhas individuais (smtp_pass / imap_pass)
// ————————————————————————————————————————————————————————————
function senha_unificada(string $tipo = 'smtp'): string {
    // Tipo pode ser 'smtp' ou 'imap'
    $senha_unificada = get_config('senha_unificada', '');

    if (!empty($senha_unificada)) {
        return $senha_unificada;
    }

    // Fallback: senha individual do tipo solicitado
    if ($tipo === 'smtp') {
        return get_config('smtp_pass', '');
    }

    return get_config('imap_pass', '');
}

// ————————————————————————————————————————————————————————————
// SMTP — le configuracoes padronizadas do banco
// ————————————————————————————————————————————————————————————
function smtp_config(): array {
    $user = get_config('smtp_user', '');
    // NOVO v2.0: usa senha_unificada() para obter a senha correta
    $pass = senha_unificada('smtp');
    return [
        'host'       => get_config('smtp_host', ''),
        'port'       => (int)(get_config('smtp_port', 587) ?: 587),
        'encryption' => get_config('smtp_encryption', 'tls'),
        'user'       => $user,
        'pass'       => $pass,
        'from'       => get_config('email_contato', $user),
        'from_name'  => get_config('site_nome_email', '') ?: get_config('site_nome', 'SiteCatalogo'),
    ];
}

function smtp_configurado(): bool {
    $c = smtp_config();
    return !empty($c['host']) && !empty($c['user']) && !empty($c['pass']);
}

// ————————————————————————————————————————————————————————————
// IMAP — le configuracoes do banco
// ————————————————————————————————————————————————————————————
function imap_cfg(): array {
    // NOVO v2.0: usa senha_unificada() para obter a senha correta
    $pass = senha_unificada('imap');
    return [
        'host'   => get_config('imap_host', ''),
        'port'   => (int)(get_config('imap_port', 993) ?: 993),
        'ssl'    => get_config('imap_ssl', '1') === '1',
        'user'   => get_config('imap_user', ''),
        'pass'   => $pass,
        'folder' => get_config('imap_folder', 'INBOX'),
    ];
}

function imap_configurado(): bool {
    $c = imap_cfg();
    return !empty($c['host']) && !empty($c['user']) && !empty($c['pass']);
}

function imap_conectar(string $pasta_imap = 'INBOX') {
    $c = imap_cfg();
    if (!imap_configurado()) return null;
    $flags   = $c['ssl'] ? '/ssl/novalidate-cert' : '/notls';
    $mailbox = '{' . $c['host'] . ':' . $c['port'] . '/imap' . $flags . '}' . $pasta_imap;
    return @imap_open($mailbox, $c['user'], $c['pass'], 0, 1) ?: null;
}

function imap_pasta_servidor(string $pasta_local): string {
    $mapa = [
        'inbox'   => get_config('imap_folder',         'INBOX'),
        'sent'    => get_config('imap_folder_sent',    'Sent'),
        'drafts'  => get_config('imap_folder_drafts',  'Drafts'),
        'archive' => get_config('imap_folder_archive', 'Archive'),
        'spam'    => get_config('imap_folder_spam',    'Junk'),
        'trash'   => get_config('imap_folder_trash',   'Trash'),
    ];
    return $mapa[$pasta_local] ?? 'INBOX';
}

function imap_decodificar(string $str): string {
    $parts  = imap_mime_header_decode($str);
    $result = '';
    foreach ($parts as $p) {
        $charset = ($p->charset === 'default') ? 'UTF-8' : $p->charset;
        $result .= mb_convert_encoding($p->text, 'UTF-8', $charset);
    }
    return $result;
}

// Retorna [texto_puro, html_ou_vazio]
function imap_corpo($conn, int $uid): array {
    $struct = imap_fetchstructure($conn, $uid, FT_UID);
    $plain  = '';
    $html   = '';

    if (!isset($struct->parts)) {
        $raw = imap_fetchbody($conn, $uid, '1', FT_UID);
        if ($struct->encoding == 3)      $raw = base64_decode($raw);
        elseif ($struct->encoding == 4)  $raw = quoted_printable_decode($raw);
        $raw = mb_convert_encoding($raw, 'UTF-8', 'UTF-8');
        if (strtolower($struct->subtype ?? '') === 'html') {
            $html  = $raw;
            $plain = trim(strip_tags(str_replace(['<br>','<br/>','<br />','</p>','</div>'], "\n", $raw)));
        } else {
            $plain = $raw;
        }
        return [$plain, $html];
    }

    foreach ($struct->parts as $i => $part) {
        $subtype = strtolower($part->subtype ?? '');
        if (!in_array($subtype, ['plain', 'html'])) continue;

        $raw = imap_fetchbody($conn, $uid, $i + 1, FT_UID);
        if ($part->encoding == 3)      $raw = base64_decode($raw);
        elseif ($part->encoding == 4)  $raw = quoted_printable_decode($raw);

        $charset = 'UTF-8';
        foreach ($part->parameters ?? [] as $p) {
            if (strtolower($p->attribute) === 'charset') { $charset = $p->value; break; }
        }
        $raw = @mb_convert_encoding($raw, 'UTF-8', $charset) ?: $raw;

        if ($subtype === 'plain' && empty($plain)) $plain = $raw;
        if ($subtype === 'html'  && empty($html))  $html  = $raw;
    }

    if (empty($plain) && !empty($html)) {
        $plain = trim(strip_tags(str_replace(['<br>','<br/>','<br />','</p>','</div>'], "\n", $html)));
    }
    if (empty($plain)) {
        $plain = imap_fetchbody($conn, $uid, '1', FT_UID);
    }
    return [$plain, $html];
}

// ————————————————————————————————————————————————————————————
// ASSINATURA DIGITAL
// ————————————————————————————————————————————————————————————
function montar_assinatura(): string {
    $tipo = get_config('email_assinatura_tipo', 'nenhuma');
    if ($tipo === 'html') {
        $html = get_config('email_assinatura_html', '');
        return $html ? "\n\n--\n" . $html : '';
    }
    if ($tipo === 'imagem') {
        $img = get_config('email_assinatura_imagem', '');
        if ($img) {
            $url = uploads_url($img);
            return "\n\n--\n<img src=\"{$url}\" alt=\"Assinatura\" style=\"max-width:320px;display:block;\">";
        }
    }
    return '';
}

// ————————————————————————————————————————————————————————————
// ENVIO SMTP
// ————————————————————————————————————————————————————————————
function enviar_smtp(string $para, string $assunto, string $corpo_html, string $reply_to = '', array $anexos = []): array {
    $c = smtp_config();
    if (!smtp_configurado()) {
        return ['ok' => false, 'erro' => 'SMTP nao configurado. Acesse Configuracoes -> Email.'];
    }

    $phpmailer_paths = [
        __DIR__ . '/../vendor/phpmailer/phpmailer/src/PHPMailer.php',
        __DIR__ . '/vendor/phpmailer/phpmailer/src/PHPMailer.php',
        __DIR__ . '/../PHPMailer/src/PHPMailer.php',
    ];

    $corpo_texto = trim(strip_tags(str_replace(['<br>','<br/>','<br />','</p>','</div>'], "\n", $corpo_html)));

    foreach ($phpmailer_paths as $path) {
        if (file_exists($path)) {
            require_once $path;
            require_once dirname($path) . '/SMTP.php';
            require_once dirname($path) . '/Exception.php';
            try {
                $mail = new PHPMailer\PHPMailer\PHPMailer(true);
                $mail->isSMTP();
                $mail->Host       = $c['host'];
                $mail->SMTPAuth   = true;
                $mail->Username   = $c['user'];
                $mail->Password   = $c['pass'];
                $mail->SMTPSecure = $c['encryption'];
                $mail->Port       = $c['port'];
                $mail->CharSet    = 'UTF-8';
                $mail->setFrom($c['from'], $c['from_name']);
                $mail->addAddress($para);
                if ($reply_to) $mail->addReplyTo($reply_to);
                $mail->Subject  = $assunto;
                $mail->isHTML(true);
                $mail->Body     = nl2br($corpo_html);
                $mail->AltBody  = $corpo_texto;

                // Anexos
                foreach ($anexos as $anexo) {
                    if (!empty($anexo['caminho']) && file_exists($anexo['caminho'])) {
                        $mail->addAttachment($anexo['caminho'], $anexo['nome'] ?? basename($anexo['caminho']));
                    }
                }

                $mail->send();
                return ['ok' => true];
            } catch (Exception $e) {
                return ['ok' => false, 'erro' => $mail->ErrorInfo];
            }
        }
    }

    // Fallback mail() — HTML basico (sem suporte a anexos no fallback)
    $boundary = md5(uniqid());
    $headers  = "From: =?UTF-8?B?" . base64_encode($c['from_name']) . "?= <{$c['from']}>\n";
    $headers .= "Reply-To: " . ($reply_to ?: $c['from']) . "\n";
    $headers .= "MIME-Version: 1.0\nContent-Type: text/html; charset=UTF-8\n";
    $ok = @mail($para, '=?UTF-8?B?' . base64_encode($assunto) . '?=', nl2br($corpo_html), $headers);
    return $ok
        ? ['ok' => true]
        : ['ok' => false, 'erro' => 'Falha no mail() nativo. Instale o PHPMailer para mais controle.'];
}

function salvar_enviado_imap(string $para, string $assunto, string $corpo): void {
    if (!function_exists('imap_open') || !imap_configurado()) return;
    $c           = imap_cfg();
    $sent_folder = imap_pasta_servidor('sent');
    $flags_str   = $c['ssl'] ? '/ssl/novalidate-cert' : '/notls';
    $conn        = @imap_open(
        '{' . $c['host'] . ':' . $c['port'] . '/imap' . $flags_str . '}' . $sent_folder,
        $c['user'], $c['pass'], 0, 1
    );
    if (!$conn) return;
    $raw = "From: {$c['user']}\nTo: {$para}\nSubject: {$assunto}\nMIME-Version: 1.0\nContent-Type: text/html; charset=UTF-8\nDate: " . date('r') . "\n\n{$corpo}";
    @imap_append($conn, '{' . $c['host'] . ':' . $c['port'] . '/imap' . $flags_str . '}' . $sent_folder, $raw, '\\Seen');
    imap_close($conn);
}

// ————————————————————————————————————————————————————————————
// SINCRONIZACAO IMAP -> BD
// ————————————————————————————————————————————————————————————
function sincronizar_imap(string $pasta_local = 'inbox'): array {
    if (!function_exists('imap_open')) {
        return ['ok' => false, 'erro' => 'Extensao IMAP nao habilitada no PHP.'];
    }
    if (!imap_configurado()) {
        return ['ok' => false, 'erro' => 'IMAP nao configurado. Acesse Configuracoes -> Email.'];
    }

    $pasta_imap = imap_pasta_servidor($pasta_local);
    $conn       = imap_conectar($pasta_imap);
    if (!$conn) {
        return ['ok' => false, 'erro' => 'Nao foi possivel conectar ao servidor IMAP. Verifique as configuracoes.'];
    }

    $uids  = imap_search($conn, 'ALL', SE_UID) ?: [];
    $novos = 0;
    $total = count($uids);

    foreach (array_slice(array_reverse($uids), 0, 100) as $uid) {
        $existe = db()->prepare("SELECT id FROM " . table('emails') . " WHERE imap_uid = ? AND pasta = ?");
        $existe->execute([$uid, $pasta_local]);
        if ($existe->fetchColumn()) continue;

        $ov = imap_fetch_overview($conn, $uid, FT_UID);
        if (empty($ov)) continue;
        $ov = $ov[0];

        $assunto    = imap_decodificar($ov->subject ?? '(sem assunto)');
        $remetente  = imap_decodificar($ov->from    ?? '');
        $data_envio = date('Y-m-d H:i:s', $ov->udate ?? time());
        $status     = isset($ov->seen) && $ov->seen ? 'lido' : 'nao_lido';
        [$corpo, $corpo_html] = imap_corpo($conn, $uid);

        // Protecao: corta corpos absurdamente grandes
        if (mb_strlen($corpo) > 500000) {
            $corpo = mb_substr($corpo, 0, 500000) . "\n[Mensagem truncada — conteudo muito extenso]";
        }
        if (mb_strlen($corpo_html) > 1000000) {
            $corpo_html = mb_substr($corpo_html, 0, 1000000);
        }
        $corpo      = str_replace("\x00", '', $corpo);
        $corpo_html = str_replace("\x00", '', $corpo_html);
        $assunto    = mb_substr(str_replace("\x00", '', $assunto), 0, 490);

        $rem_nome = $remetente; $rem_email = '';
        if (preg_match('/"?([^"<>]+)"?\s*<([^>]+)>/', $remetente, $m)) {
            $rem_nome  = trim($m[1]);
            $rem_email = trim($m[2]);
        } elseif (filter_var(trim($remetente), FILTER_VALIDATE_EMAIL)) {
            $rem_email = trim($remetente);
            $rem_nome  = '';
        }

        try {
            db()->prepare(
                "INSERT INTO " . table('emails') . "
                 (imap_uid, remetente_nome, remetente_email, destinatario_email, assunto, corpo, corpo_html, pasta, status, data_envio)
                 VALUES (?,?,?,?,?,?,?,?,?,?)"
            )->execute([
                $uid, $rem_nome, $rem_email,
                get_config('imap_user', ''),
                $assunto, $corpo, $corpo_html, $pasta_local, $status, $data_envio,
            ]);
            $novos++;
        } catch (PDOException $e) {
            error_log('Falha ao sincronizar email UID ' . $uid . ': ' . $e->getMessage());
            continue;
        }
    }

    imap_close($conn);
    set_config('email_ultima_sync', date('Y-m-d H:i:s'));
    return ['ok' => true, 'novos' => $novos, 'total' => $total];
}