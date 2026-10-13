<?php
declare(strict_types=1);

function env_string(string $key, string $default): string
{
    $value = getenv($key);
    return is_string($value) && $value !== '' ? $value : $default;
}

function db(): mysqli
{
    static $db = null;
    if ($db instanceof mysqli) {
        return $db;
    }
    mysqli_report(MYSQLI_REPORT_OFF);
    $mysqli = new mysqli(
        env_string('DB_HOST', 'db'),
        env_string('DB_USER', 'lab'),
        env_string('DB_PASS', 'lab_app_local'),
        env_string('DB_NAME', 'lab')
    );
    if ($mysqli->connect_errno) {
        http_response_code(500);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Database is not ready.';
        exit;
    }
    $mysqli->set_charset('utf8mb4');
    $db = $mysqli;
    return $db;
}

function secrets(): array
{
    static $secrets = null;
    if (is_array($secrets)) {
        return $secrets;
    }
    $path = env_string('SECRETS_PATH', '/var/www/secrets/flags.php');
    $loaded = is_file($path) ? require $path : [];
    $secrets = is_array($loaded) ? $loaded : [];
    return $secrets;
}

function secret(string $code): ?string
{
    $value = secrets()[$code] ?? null;
    return is_string($value) ? $value : null;
}

function start_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    session_name('RANGESESS');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => false,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
    if (!isset($_COOKIE['range_pref'])) {
        setcookie('range_pref', 'theme=night', [
            'expires' => 0,
            'path' => '/',
            'secure' => false,
            'httponly' => false,
            'samesite' => 'Lax',
        ]);
    }
}

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function clip(string $value, int $max): string
{
    if (strlen($value) <= $max) {
        return $value;
    }
    return substr($value, 0, $max);
}

function current_user(): ?array
{
    static $loaded = false;
    static $user = null;
    if ($loaded) {
        return $user;
    }
    $loaded = true;
    $uid = $_SESSION['uid'] ?? 0;
    $id = (int) $uid;
    if ($id <= 0) {
        return null;
    }
    $stmt = db()->prepare(
        'SELECT id, username, email, avatar, bio, role, listed, csrf_proof, created_at FROM users WHERE id = ?'
    );
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $user = $row ?: null;
    return $user;
}

function safe_next(string $next): string
{
    if (
        $next === ''
        || $next[0] !== '/'
        || str_starts_with($next, '//')
        || str_contains($next, '://')
        || str_contains($next, '\\')
        || str_contains($next, "\n")
        || str_contains($next, "\r")
    ) {
        return '/';
    }
    return $next;
}

function require_login(): array
{
    $user = current_user();
    if (!$user) {
        $next = $_SERVER['REQUEST_URI'] ?? '/';
        header('Location: /login?next=' . urlencode(safe_next(is_string($next) ? $next : '/')));
        exit;
    }
    return $user;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf']) || !is_string($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['csrf'];
}

function csrf_ok(): bool
{
    $sent = $_POST['csrf'] ?? '';
    return is_string($sent) && hash_equals(csrf_token(), $sent);
}

function flash(string $message, string $type = 'info'): void
{
    $_SESSION['flash'][] = [$type, $message];
}

function take_flash(): array
{
    $items = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return is_array($items) ? $items : [];
}

function redact(string $value): string
{
    $clean = preg_replace('/FLAG\{[^}]+\}/', 'FLAG{REDACTED}', $value);
    return is_string($clean) ? $clean : $value;
}

function log_event(string $level, string $message): void
{
    $level = clip($level, 16);
    $message = clip(redact($message), 500);
    $stmt = db()->prepare('INSERT INTO lab_logs (level, message, created_at) VALUES (?, ?, NOW())');
    if (!$stmt) {
        return;
    }
    $stmt->bind_param('ss', $level, $message);
    $stmt->execute();
    $stmt->close();
}

function record_solve(string $code): void
{
    $user = current_user();
    if (!$user) {
        return;
    }
    $uid = (int) $user['id'];
    $stmt = db()->prepare('INSERT IGNORE INTO solves (user_id, flag_code, solved_at) VALUES (?, ?, NOW())');
    if (!$stmt) {
        return;
    }
    $stmt->bind_param('is', $uid, $code);
    $stmt->execute();
    if ($stmt->affected_rows > 0) {
        log_event('score', 'solved ' . $code);
    }
    $stmt->close();
}

function award(string $code): ?string
{
    $flag = secret($code);
    if ($flag === null) {
        return null;
    }
    record_solve($code);
    return $flag;
}

function observe_flag_value(string $value): void
{
    $value = trim($value);
    if (!preg_match('/^FLAG\{[A-Z0-9_]+\}$/', $value)) {
        return;
    }
    $stmt = db()->prepare('SELECT slot FROM lab_vault WHERE token = ?');
    if ($stmt) {
        $stmt->bind_param('s', $value);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($row) {
            record_solve((string) $row['slot']);
            return;
        }
    }
    $stmt = db()->prepare('SELECT label FROM search_locker WHERE secret = ?');
    if (!$stmt) {
        return;
    }
    $stmt->bind_param('s', $value);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($row) {
        record_solve((string) $row['label']);
    }
}

function solved_map(): array
{
    $user = current_user();
    if (!$user) {
        return [];
    }
    $uid = (int) $user['id'];
    $stmt = db()->prepare('SELECT flag_code FROM solves WHERE user_id = ?');
    if (!$stmt) {
        return [];
    }
    $stmt->bind_param('i', $uid);
    $stmt->execute();
    $result = $stmt->get_result();
    $codes = [];
    while ($row = $result->fetch_assoc()) {
        $codes[(string) $row['flag_code']] = true;
    }
    $stmt->close();
    return $codes;
}

function flag_for_code(string $code): ?string
{
    if ($code === 'sqli_post') {
        $stmt = db()->prepare('SELECT token FROM lab_vault WHERE slot = ?');
        if (!$stmt) {
            return null;
        }
        $stmt->bind_param('s', $code);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row ? (string) $row['token'] : null;
    }
    if ($code === 'sqli_search') {
        $stmt = db()->prepare('SELECT secret FROM search_locker WHERE label = ?');
        if (!$stmt) {
            return null;
        }
        $stmt->bind_param('s', $code);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row ? (string) $row['secret'] : null;
    }
    return secret($code);
}

function storage_dir(string $subdir): string
{
    $root = env_string('STORAGE_PATH', '/var/www/storage');
    $dir = $root . '/' . $subdir;
    if (!is_dir($dir) && !mkdir($dir, 0750, true) && !is_dir($dir)) {
        throw new RuntimeException('Storage is not available.');
    }
    return $dir;
}

function strip_danger(string $value): string
{
    $previous = null;
    $current = $value;
    while ($current !== $previous) {
        $previous = $current;
        $current = preg_replace('/\/\*.*?\*\//s', '', $current) ?? $current;
        $current = str_replace(['--', '#', ';'], '', $current);
        $current = preg_replace(
            '/drop|delete|insert|update|alter|grant|truncate|rename|outfile|dumpfile|load_file|sleep|benchmark|information_schema|get_lock/i',
            '',
            $current
        ) ?? $current;
    }
    return $current;
}

function filter_once(string $value, array $patterns): string
{
    foreach ($patterns as $pattern) {
        $value = preg_replace($pattern, '', $value, 1) ?? $value;
    }
    return $value;
}

function xss_vector(string $value): bool
{
    if (!preg_match('/[<>]/', $value)) {
        return false;
    }
    if (preg_match('/<\s*script\b/i', $value)) {
        return true;
    }
    if (preg_match('/\bon[a-z0-9]+\s*=/i', $value)) {
        return true;
    }
    if (preg_match('/srcdoc\s*=/i', $value)) {
        return true;
    }
    if (preg_match('/<\s*(svg|math|iframe|object|embed|details|video|audio)\b/i', $value)) {
        return true;
    }
    return false;
}

function dom_xss_vector(string $value): bool
{
    if (preg_match('/\bon[a-z0-9]+\s*=/i', $value)) {
        return true;
    }
    if (preg_match('/srcdoc\s*=/i', $value)) {
        return true;
    }
    return (bool) preg_match('/<\s*(svg|math|iframe|object|embed|details)\b/i', $value);
}

function local_only_markup(string $value): string
{
    $clean = preg_replace('#\b(?:https?:)?//+#i', '', $value);
    return is_string($clean) ? $clean : $value;
}

function reflect_filter(string $value): string
{
    return local_only_markup(filter_once($value, [
        '/script/i',
        '/onerror/i',
        '/onload/i',
        '/javascript\s*:/i',
    ]));
}

function legacy_comment(string $value): string
{
    return local_only_markup(filter_once($value, [
        '/script/i',
        '/onerror/i',
    ]));
}

function finfo_mime(string $path): string
{
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($path);
    return is_string($mime) ? $mime : 'application/octet-stream';
}

function ext_of(string $name): string
{
    $base = basename(str_replace('\\', '/', $name));
    $pos = strrpos($base, '.');
    if ($pos === false) {
        return '';
    }
    return strtolower(substr($base, $pos + 1));
}

function extension_tokens(string $name): array
{
    $base = basename(str_replace('\\', '/', $name));
    if (!preg_match_all('/\.([a-z0-9]+)/i', $base, $matches)) {
        return [];
    }
    return array_map(static fn (string $ext): string => strtolower($ext), $matches[1]);
}

function dummy_password_hash(): string
{
    return '$2y$10$zlJALPBrBAkuSue8GH5XJep5p.VN5DZQUm7zZBlK0GaDBpDlHXFKe';
}
