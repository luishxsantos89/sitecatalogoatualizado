<?php
/**
 * SiteCatalogo2 - Funções Globais
 */

require_once __DIR__ . '/db.php';

// ==================== SEGURANÇA ====================

function sanitize($data): string {
    if ($data === null) return '';
    return htmlspecialchars(strip_tags(trim((string)$data)), ENT_QUOTES, 'UTF-8');
}

function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string {
    return '<input type="hidden" name="csrf_token" value="' . csrf_token() . '">';
}

function csrf_validate(): bool {
    if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token'])) return false;
    return hash_equals($_SESSION['csrf_token'], $_POST['csrf_token']);
}

// ==================== SESSÃO ====================

function session_check(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_name(defined('SESSION_NAME') ? SESSION_NAME : 'sitecatalogo2_session');
        session_start();
    }
}

function is_logged_in(): bool {
    session_check();
    if (!isset($_SESSION['admin_id']) || $_SESSION['admin_id'] <= 0) return false;
    // Verificar se o usuário ainda está ativo no banco
    try {
        $stmt = db()->prepare("SELECT status FROM " . table('usuarios') . " WHERE id = ? LIMIT 1");
        $stmt->execute([$_SESSION['admin_id']]);
        $u = $stmt->fetch();
        if (!$u || $u['status'] !== 'ativo') {
            session_unset(); session_destroy();
            return false;
        }
    } catch (Exception $e) {}
    return true;
}

function require_auth(): void {
    if (!is_logged_in()) {
        header('Location: ' . (defined('ADMIN_URL') ? ADMIN_URL : '/admin/') . 'login.php');
        exit;
    }
}

function check_permission(string $min_level = 'vendedor'): bool {
    if (!is_logged_in()) return false;
    $levels = ['vendedor' => 1, 'atendente' => 2, 'gerente' => 3, 'admin' => 4];
    $user_level = $_SESSION['admin_nivel'] ?? 'vendedor';
    return ($levels[$user_level] ?? 0) >= ($levels[$min_level] ?? 0);
}

// ==================== URLS ====================

function site_url(string $path = ''): string {
    $base = defined('SITE_URL') ? SITE_URL : '/';
    return rtrim($base, '/') . '/' . ltrim($path, '/');
}

function admin_url(string $path = ''): string {
    $base = defined('ADMIN_URL') ? ADMIN_URL : '/admin/';
    return rtrim($base, '/') . '/' . ltrim($path, '/');
}

function assets_url(string $path = ''): string {
    $base = defined('ASSETS_URL') ? ASSETS_URL : '/assets/';
    return rtrim($base, '/') . '/' . ltrim($path, '/');
}

function uploads_url(string $path = ''): string {
    if (empty($path)) return defined('UPLOADS_URL') ? UPLOADS_URL : '/uploads/';
    $base = defined('UPLOADS_URL') ? UPLOADS_URL : '/uploads/';
    return rtrim($base, '/') . '/' . ltrim($path, '/');
}

function uploads_path(string $path = ''): string {
    $base = defined('UPLOADS_PATH') ? UPLOADS_PATH : ROOT_PATH . '/uploads';
    if (empty($path)) return $base;
    return rtrim($base, '/') . '/' . ltrim($path, '/');
}

// ==================== FORMATAÇÃO ====================

function format_currency(float $value, string $currency = 'BRL'): string {
    $moeda_conf = get_config('moeda', $currency);
    $currency = $moeda_conf ?: $currency;
    if ($currency === 'USD') return '$ ' . number_format($value, 2, '.', ',');
    if ($currency === 'EUR') return '€ ' . number_format($value, 2, ',', '.');
    return 'R$ ' . number_format($value, 2, ',', '.');
}

function format_date(?string $date, string $format = 'd/m/Y H:i'): string {
    if (empty($date)) return '-';
    try {
        return date($format, strtotime($date));
    } catch (Exception $e) {
        return '-';
    }
}

function format_phone(?string $phone): string {
    if (empty($phone)) return '-';
    $phone = preg_replace('/\D/', '', $phone);
    if (strlen($phone) === 11) return '(' . substr($phone, 0, 2) . ') ' . substr($phone, 2, 5) . '-' . substr($phone, 7);
    if (strlen($phone) === 10) return '(' . substr($phone, 0, 2) . ') ' . substr($phone, 2, 4) . '-' . substr($phone, 6);
    return $phone;
}

function format_cpf_cnpj(?string $doc): string {
    if (empty($doc)) return '-';
    $doc = preg_replace('/\D/', '', $doc);
    if (strlen($doc) === 11) return substr($doc, 0, 3) . '.' . substr($doc, 3, 3) . '.' . substr($doc, 6, 3) . '-' . substr($doc, 9);
    if (strlen($doc) === 14) return substr($doc, 0, 2) . '.' . substr($doc, 2, 3) . '.' . substr($doc, 5, 3) . '/' . substr($doc, 8, 4) . '-' . substr($doc, 12);
    return $doc;
}

function slugify(string $text): string {
    $text = iconv('UTF-8', 'ASCII//TRANSLIT', $text);
    $text = preg_replace('/[^a-zA-Z0-9\s-]/', '', $text);
    $text = strtolower(trim($text));
    $text = preg_replace('/[\s-]+/', '-', $text);
    return $text ?: 'item';
}

// ==================== UPLOAD ====================

function upload_file(array $file, string $folder = 'produtos', array $allowed = ['jpg','jpeg','png','gif','webp']): ?string {
    if ($file['error'] !== UPLOAD_ERR_OK) return null;
    if ($file['size'] > 10 * 1024 * 1024) return null; // 10MB max

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed)) return null;

    $filename = uniqid() . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
    $dir = uploads_path($folder);
    if (!is_dir($dir)) mkdir($dir, 0755, true);

    $path = $dir . '/' . $filename;
    if (move_uploaded_file($file['tmp_name'], $path)) {
        return $folder . '/' . $filename;
    }
    return null;
}

function delete_upload(string $path): bool {
    $full = uploads_path($path);
    if (file_exists($full)) return unlink($full);
    return false;
}

// ==================== PAGINAÇÃO ====================

function paginate(int $total, int $page = 1, int $per_page = 12): array {
    $page = max(1, $page);
    $per_page = max(1, $per_page);
    $total_pages = $total > 0 ? (int)ceil($total / $per_page) : 1;
    $page = min($page, $total_pages);
    $offset = ($page - 1) * $per_page;
    return [
        'page'        => $page,
        'per_page'    => $per_page,
        'total'       => $total,
        'total_pages' => $total_pages,
        'offset'      => max(0, $offset),
        'has_prev'    => $page > 1,
        'has_next'    => $page < $total_pages,
        'prev_page'   => $page - 1,
        'next_page'   => $page + 1,
        'start'       => $total > 0 ? $offset + 1 : 0,
        'end'         => min($offset + $per_page, $total)
    ];
}

function pagination_links(array $pag, string $base_url, array $params = []): string {
    if ($pag['total_pages'] <= 1) return '';
    $q = http_build_query($params);
    $sep = $q ? '&' : '';
    $html = '<nav class="pagination-nav"><div class="pagination">';
    if ($pag['has_prev']) {
        $html .= '<a href="' . $base_url . '?' . $q . $sep . 'page=' . $pag['prev_page'] . '" class="page-link prev">&larr; Anterior</a>';
    } else {
        $html .= '<span class="page-link prev disabled">&larr; Anterior</span>';
    }
    $start = max(1, $pag['page'] - 2);
    $end = min($pag['total_pages'], $pag['page'] + 2);
    for ($i = $start; $i <= $end; $i++) {
        if ($i === $pag['page']) {
            $html .= '<span class="page-link active">' . $i . '</span>';
        } else {
            $html .= '<a href="' . $base_url . '?' . $q . $sep . 'page=' . $i . '" class="page-link">' . $i . '</a>';
        }
    }
    if ($pag['has_next']) {
        $html .= '<a href="' . $base_url . '?' . $q . $sep . 'page=' . $pag['next_page'] . '" class="page-link next">Próximo &rarr;</a>';
    } else {
        $html .= '<span class="page-link next disabled">Próximo &rarr;</span>';
    }
    $html .= '</div></nav>';
    return $html;
}

// ==================== FLASH MESSAGES ====================

function set_flash(string $type, string $message): void {
    session_check();
    $_SESSION['flash'][$type] = $message;
}

function get_flash(): array {
    session_check();
    $flash = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $flash;
}

function show_flash(): string {
    $flash = get_flash();
    if (empty($flash)) return '';
    $html = '';
    foreach ($flash as $type => $message) {
        $class = $type === 'error' ? 'alert-danger' : 'alert-' . $type;
        $icon = match($type) {
            'success' => 'fa-check-circle',
            'error'   => 'fa-exclamation-circle',
            'warning' => 'fa-exclamation-triangle',
            default   => 'fa-info-circle'
        };
        $html .= '<div class="alert ' . $class . '"><i class="fas ' . $icon . '"></i> ' . sanitize($message) . '</div>';
    }
    return $html;
}

// ==================== CONFIGURAÇÕES ====================

function get_config(string $key, $default = null) {
    static $cache = [];
    if (isset($cache[$key])) return $cache[$key] ?? $default;
    try {
        $stmt = db()->prepare("SELECT valor FROM " . table('configuracoes') . " WHERE chave = ? AND ativo = 1 LIMIT 1");
        $stmt->execute([$key]);
        $result = $stmt->fetch();
        $cache[$key] = $result ? $result['valor'] : null;
        return $cache[$key] ?? $default;
    } catch (Exception $e) {
        return $default;
    }
}

function set_config(string $key, string $value): bool {
    try {
        $stmt = db()->prepare("UPDATE " . table('configuracoes') . " SET valor = ? WHERE chave = ?");
        return $stmt->execute([$value, $key]);
    } catch (Exception $e) {
        return false;
    }
}

// ==================== LOGS ====================

function log_activity(string $acao, string $tabela, string $descricao = ''): void {
    try {
        $user_id = $_SESSION['admin_id'] ?? null;
        $ip = $_SERVER['REMOTE_ADDR'] ?? null;
        $stmt = db()->prepare("INSERT INTO " . table('atividades_log') . " (acao, tabela, descricao, usuario_id, ip_address) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$acao, $tabela, $descricao, $user_id, $ip]);
    } catch (Exception $e) {}
}

// ==================== HELPERS ====================

function selected($current, $value): string {
    return $current == $value ? 'selected' : '';
}

function checked($condition): string {
    return $condition ? 'checked' : '';
}

function active_class(string $current, string $page): string {
    return $current === $page ? 'active' : '';
}

function whatsapp_link(string $phone, string $message = ''): string {
    $phone = preg_replace('/\D/', '', $phone);
    $url = 'https://wa.me/' . $phone;
    if ($message) $url .= '?text=' . urlencode($message);
    return $url;
}

// Gerar slug único
function unique_slug(string $table, string $slug, ?int $exclude_id = null): string {
    $original = $slug;
    $counter = 1;
    while (true) {
        $sql = "SELECT id FROM " . table($table) . " WHERE slug = ?";
        $params = [$slug];
        if ($exclude_id) { $sql .= " AND id != ?"; $params[] = $exclude_id; }
        $stmt = db()->prepare($sql);
        $stmt->execute($params);
        if (!$stmt->fetch()) return $slug;
        $slug = $original . '-' . $counter++;
    }
}
