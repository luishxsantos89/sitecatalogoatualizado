<?php
/**
 * email_teste_imap.php
 * Endpoint AJAX — testa conexão IMAP com os dados informados no formulário
 * Chamado via fetch() em configuracoes.php
 */
require_once __DIR__ . '/includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

// Controle de acesso
if (!is_logged_in() || !check_permission('admin')) {
    echo json_encode(['ok' => false, 'erro' => 'Acesso negado.']);
    exit;
}

// Só aceita POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false, 'erro' => 'Método inválido.']);
    exit;
}

// Extensão IMAP disponível?
if (!function_exists('imap_open')) {
    echo json_encode([
        'ok'   => false,
        'erro' => 'Extensão IMAP não habilitada no PHP. Ative extension=imap no php.ini ou via PHP Selector no cPanel.',
    ]);
    exit;
}

$host = trim($_POST['host'] ?? '');
$port = (int)($_POST['port'] ?? 993);
$user = trim($_POST['user'] ?? '');
$pass = $_POST['pass'] ?? '';
$ssl  = ($_POST['ssl'] ?? '1') === '1';

// Validação básica
if (empty($host)) { echo json_encode(['ok' => false, 'erro' => 'Informe o servidor IMAP.']); exit; }
if (empty($user)) { echo json_encode(['ok' => false, 'erro' => 'Informe o usuário IMAP.']);  exit; }
if (empty($pass)) { echo json_encode(['ok' => false, 'erro' => 'Informe a senha IMAP.']);    exit; }

// Porta padrão por SSL
if ($port <= 0) $port = $ssl ? 993 : 143;

// Monta string de conexão IMAP
$flags   = $ssl ? '/ssl/novalidate-cert' : '/notls';
$mailbox = '{' . $host . ':' . $port . '/imap' . $flags . '}INBOX';

// Limpa erros anteriores
@imap_errors();
@imap_alerts();

// Tenta conectar
$conn = @imap_open($mailbox, $user, $pass, 0, 1, [
    'DISABLE_AUTHENTICATOR' => 'GSSAPI',
]);

if ($conn) {
    $check   = @imap_check($conn);
    $total   = $check ? (int)$check->Nmsgs   : 0;
    $recente = $check ? (int)$check->Recent  : 0;
    imap_close($conn);

    echo json_encode([
        'ok'      => true,
        'total'   => $total,
        'recente' => $recente,
        'msg'     => "Conexão estabelecida com sucesso. {$total} mensagem(ns) na INBOX.",
    ]);
} else {
    $erros  = imap_errors()  ?: [];
    $ultimo = imap_last_error();
    $erro_texto = $ultimo ?: (!empty($erros) ? implode(' | ', $erros) : 'Falha na conexão. Verifique host, porta, usuário e senha.');

    // Dicas contextuais
    $dica = '';
    if (str_contains($erro_texto, 'Certificate')) $dica = ' (Dica: tente desmarcar SSL ou use novalidate-cert)';
    if (str_contains($erro_texto, 'LOGIN'))        $dica = ' (Dica: verifique usuário/senha; no Gmail use App Password)';
    if (str_contains($erro_texto, 'Connection'))   $dica = ' (Dica: verifique host e porta)';

    echo json_encode([
        'ok'   => false,
        'erro' => htmlspecialchars($erro_texto . $dica, ENT_QUOTES, 'UTF-8'),
    ]);
}
