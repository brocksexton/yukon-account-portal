<?php
if (!is_file(__DIR__ . '/config.php')) {
    http_response_code(500);
    die('Missing includes/config.php. Copy includes/config.example.php to includes/config.php and edit it.');
}
require_once __DIR__ . '/config.php';

// Secure-ish session defaults
$cookieParams = session_get_cookie_params();
@session_set_cookie_params([
    'lifetime' => 0,
    'path' => $cookieParams['path'] ?? '/',
    'domain' => $cookieParams['domain'] ?? '',
    'secure' => COOKIE_SECURE,
    'httponly' => true,
    'samesite' => 'Lax'
]);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// --- DB ---
function db(): mysqli {
    static $conn = null;
    if ($conn instanceof mysqli) {
        return $conn;
    }

    // Try the configured host first. If it's a loopback IP, also try 'localhost'
    // (XAMPP/MySQL can be bound differently, and some installs prefer a socket).
    $hostsToTry = [DB_HOST];
    if (DB_HOST === '127.0.0.1') {
        $hostsToTry[] = 'localhost';
    }

    $lastErr = null;
    foreach ($hostsToTry as $h) {
        $tmp = @new mysqli($h, DB_USER, DB_PASS, DB_NAME, DB_PORT);
        if (!$tmp->connect_error) {
            $conn = $tmp;
            break;
        }
        $lastErr = $tmp->connect_error;
    }

    if (!($conn instanceof mysqli) || $conn->connect_error) {
        http_response_code(500);
        // Avoid leaking credentials. Provide actionable troubleshooting.
        $msg = 'Database connection failed. Ensure MySQL/MariaDB is running and that includes/config.php is correct.';
        if (!empty($lastErr)) {
            $msg .= ' Error: ' . $lastErr;
        }
        die($msg);
    }

    $conn->set_charset('utf8mb4');
    return $conn;
}

function e(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

// Build a consistent profile-avatar URL using the avatar.php endpoint.
// Used for nav/header avatars and anywhere we want a circular "headshot".
function profile_avatar_url(array $user, int $size = 96): string {
    $uid = (int)($user['id'] ?? 0);
    if ($uid <= 0) {
        return 'avatar.php?profile=true&size=' . $size;
    }

    $params = [
        'uid' => $uid,
        'profile' => 'true',
        'size' => $size,
        'scale' => (string)PROFILE_AVATAR_DEFAULT_SCALE,
        'x' => (string)PROFILE_AVATAR_DEFAULT_X,
        'y' => (string)PROFILE_AVATAR_DEFAULT_Y,
        'background' => PROFILE_AVATAR_DEFAULT_BACKGROUND ? 'true' : 'false',
        'pin' => PROFILE_AVATAR_DEFAULT_PIN ? 'true' : 'false',
    ];

    return 'avatar.php?' . http_build_query($params);
}

// CSRF token helpers
function csrf_token(): string {
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_require(): void {
    $token = $_POST['csrf'] ?? '';
    if (!hash_equals($_SESSION['csrf'] ?? '', $token)) {
        http_response_code(400);
        die('Invalid request (CSRF)');
    }
}

// Auth helpers
function is_logged_in(): bool {
    return !empty($_SESSION['uid']);
}

function current_user(): ?array {
    if (!is_logged_in()) {
        return null;
    }

    // Cache briefly to reduce DB churn, but keep it fresh enough that
    // in-game changes show up without forcing logout/login.
    $ttlSeconds = 3;
    if (!empty($_SESSION['user_cache']) && is_array($_SESSION['user_cache'])) {
        $t = (int)($_SESSION['user_cache_time'] ?? 0);
        if ($t > 0 && (time() - $t) < $ttlSeconds) {
            return $_SESSION['user_cache'];
        }
    }

    $stmt = db()->prepare('SELECT id, username, email, rank, joinTime, coins, head, face, neck, body, hand, feet, color, photo, flag, ninjaRank, ninjaProgress FROM users WHERE id = ? LIMIT 1');
    $stmt->bind_param('i', $_SESSION['uid']);
    $stmt->execute();
    $res = $stmt->get_result();
    $user = $res->fetch_assoc() ?: null;
    $stmt->close();

    $_SESSION['user_cache'] = $user;
    $_SESSION['user_cache_time'] = time();
    return $user;
}

function require_login(): void {
    if (!is_logged_in()) {
        // Redirect unauthenticated users to the root-level login page.
        // Using an absolute path prevents nested modules (e.g. /manage_penguin) from looking for
        // a local login.php file and instead always points back to the main login page.
        header('Location: /login.php');
        exit;
    }
}

function login_user(int $userId): void {
    session_regenerate_id(true);
    $_SESSION['uid'] = $userId;
    unset($_SESSION['user_cache']);
}

function logout_user(): void {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], COOKIE_SECURE, true);
    }
    session_destroy();
}

function flash_set(string $type, string $msg): void {
    $_SESSION['flash'] = ['type' => $type, 'msg' => $msg];
}

function flash_get(?string $type = null) {
    if (empty($_SESSION['flash'])) return null;
    $f = $_SESSION['flash'];
    if ($type === null) {
        // legacy: return full payload
        unset($_SESSION['flash']);
        return $f;
    }
    // typed: return message string if matches
    $msg = null;
    if (is_array($f) && ($f['type'] ?? null) === $type) {
        $msg = (string)($f['msg'] ?? '');
        unset($_SESSION['flash']);
    }
    return $msg;
}
