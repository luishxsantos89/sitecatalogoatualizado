<?php
/**
 * email_teste_smtp.php
 * Endpoint AJAX — envia um email de teste usando as configuracoes SMTP salvas
 * Chamado via fetch() em configuracoes.php
 * 
 * v2.0: Suporte a senha unificada (senha_unificada) com fallback para smtp_pass individual
 */
require_once __DIR__ . '/includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

// Controle de acesso
if (!is_logged_in() || !check_permission('admin')) {
    echo json_encode(['ok' => false, 'erro' => 'Acesso negado.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false, 'erro' => 'Metodo invalido.']);
    exit;
}

$para = trim($_POST['para'] ?? '');

if (!filter_var($para, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['ok' => false, 'erro' => 'Endereco de email invalido.']);
    exit;
}

// Le configuracoes SMTP do banco (usando get_config diretamente para garantir)
$host       = get_config('smtp_host', '');
$port       = (int)get_config('smtp_port', 587);
$user       = get_config('smtp_user', '');
// NOVO: prioriza senha_unificada, fallback para smtp_pass individual
$pass       = get_config('senha_unificada', '') ?: get_config('smtp_pass', '');
$encryption = get_config('smtp_encryption', 'tls');
$from_email = get_config('email_contato', $user);
$from_name  = get_config('site_nome_email', '') ?: get_config('site_nome', 'SiteCatalogo');

if (empty($host) || empty($user) || empty($pass)) {
    echo json_encode(['ok' => false, 'erro' => 'SMTP nao configurado. Preencha email, senha e servidor antes de testar.']);
    exit;
}

$assunto = '[Teste SMTP] ' . $from_name . ' — ' . date('d/m/Y H:i');
$corpo   = "Este e um email de teste enviado pelo painel de configuracoes do SiteCatalogo.\n\n"
         . "Se voce recebeu esta mensagem, seu SMTP esta funcionando corretamente!\n\n"
         . "Data/hora: " . date('d/m/Y H:i:s') . "\n"
         . "Servidor: {$host}:{$port}\n"
         . "Criptografia: {$encryption}\n"
         . "Remetente: {$from_name} <{$from_email}>";

// —— Tenta PHPMailer ———————————————————————————————————————————
$phpmailer_paths = [
    __DIR__ . '/../vendor/phpmailer/phpmailer/src/PHPMailer.php',
    __DIR__ . '/vendor/phpmailer/phpmailer/src/PHPMailer.php',
    __DIR__ . '/../PHPMailer/src/PHPMailer.php',
];

$phpmailer_loaded = false;
foreach ($phpmailer_paths as $path) {
    if (file_exists($path)) {
        require_once $path;
        require_once dirname($path) . '/SMTP.php';
        require_once dirname($path) . '/Exception.php';
        $phpmailer_loaded = true;
        break;
    }
}

if ($phpmailer_loaded) {
    try {
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        $mail->isSMTP();
        $mail->Host       = $host;
        $mail->SMTPAuth   = true;
        $mail->Username   = $user;
        $mail->Password   = $pass;
        $mail->SMTPSecure = $encryption ?: PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = $port;
        $mail->CharSet    = 'UTF-8';
        $mail->setFrom($from_email, $from_name);
        $mail->addAddress($para);
        $mail->Subject = $assunto;
        $mail->Body    = nl2br($corpo);
        $mail->isHTML(true);
        $mail->AltBody = $corpo;
        $mail->send();

        echo json_encode(['ok' => true, 'msg' => 'Email de teste enviado com sucesso via PHPMailer!']);
    } catch (Exception $e) {
        echo json_encode(['ok' => false, 'erro' => 'PHPMailer: ' . htmlspecialchars($mail->ErrorInfo, ENT_QUOTES, 'UTF-8')]);
    }
    exit;
}

// —— Fallback: mail() nativo ——————————————————————————————————
$headers  = "From: =?UTF-8?B?" . base64_encode($from_name) . "?= <{$from_email}>\r\n";
$headers .= "Reply-To: {$from_email}\r\n";
$headers .= "MIME-Version: 1.0\r\n";
$headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
$headers .= "X-Mailer: PHP/" . phpversion() . "\r\n";

$ok = @mail(
    $para,
    '=?UTF-8?B?' . base64_encode($assunto) . '?=',
    $corpo,
    $headers
);

if ($ok) {
    echo json_encode(['ok' => true, 'msg' => 'Email enviado via mail() nativo. Para mais controle, instale o PHPMailer.']);
} else {
    echo json_encode(['ok' => false, 'erro' => 'Falha no envio via mail(). Configure o PHPMailer ou verifique o servidor.']);
}