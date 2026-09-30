<?php
// Administration Portal bootstrap (standalone from Parent Center)
// Reuses Yukon core bootstrap + DB + auth helpers.

require_once __DIR__ . '/../../includes/bootstrap.php';

// Optional server-control settings (see administration/config/server_control.php).
if (is_file(__DIR__ . '/../config/server_control.php')) {
    require_once __DIR__ . '/../config/server_control.php';
}

// --- Admin config defaults ---
if (!defined('ADMIN_MIN_RANK')) {
    // Rank threshold for accessing the administration portal.
    // Adjust to match your server's staff rank scheme.
    define('ADMIN_MIN_RANK', 3);
}

// Where this portal lives (path from web root). Change if you move it.
if (!defined('ADMIN_BASE_PATH')) {
    define('ADMIN_BASE_PATH', '/administration');
}


// --- Dangerous actions (extra confirmation + rank gates) ---
if (!defined('ADMIN_GIVE_ALL_ITEMS_MIN_RANK')) {
    // Minimum rank required to use "Give all items" actions.
    // Owner/Founder is 4 by default in this portal.
    define('ADMIN_GIVE_ALL_ITEMS_MIN_RANK', 4);
}

function admin_can_give_all_items(?array $u = null): bool {
    $u = $u ?? current_user();
    if (!$u) return false;
    return (int)($u['rank'] ?? 0) >= (int)ADMIN_GIVE_ALL_ITEMS_MIN_RANK;
}


// --- Server control (optional, local-only by default) ---
if (!defined('ADMIN_ALLOW_SERVER_CONTROL')) {
    // Enable the Administration "Server" page (view logs, start/stop) - strongly recommended local-only.
    // Off by default: it runs npm commands in your yukon-server folder.
    define('ADMIN_ALLOW_SERVER_CONTROL', false);
}
if (!defined('ADMIN_SERVER_CONTROL_MIN_RANK')) {
    // Extra rank gate for server controls (start/stop/restart/logs).
    define('ADMIN_SERVER_CONTROL_MIN_RANK', 4);
}
if (!defined('ADMIN_SERVER_CONTROL_IP_ALLOWLIST')) {
    // Comma-separated list of allowed client IPs for server control pages/endpoints.
    // Keep this tight (localhost) unless you fully trust your network + HTTPS.
    define('ADMIN_SERVER_CONTROL_IP_ALLOWLIST', '127.0.0.1,::1');
}
function admin_is_staff(?array $u = null): bool {
    $u = $u ?? current_user();
    if (!$u) return false;
    return (int)($u['rank'] ?? 0) >= (int)ADMIN_MIN_RANK;
}

function admin_require_staff(): void {
    require_login();
    $u = current_user();
    if (!$u || !admin_is_staff($u)) {
        http_response_code(403);
        $title = 'Access denied';
        $message = 'You do not have permission to access the Administration Portal.';
        include __DIR__ . '/admin_header.php';
        echo '<div class="adm-shell"><div class="adm-card">'
           . '<h1 class="adm-h1">' . e($title) . '</h1>'
           . '<p class="adm-muted">' . e($message) . '</p>'
           . '<div class="adm-actions">'
           . '  <a class="adm-btn" href="/">Back to site</a>'
           . '</div>'
           . '</div></div>';
        include __DIR__ . '/admin_footer.php';
        exit;
    }
}

function admin_url(string $path = ''): string {
    $path = ltrim($path, '/');
    return rtrim(ADMIN_BASE_PATH, '/') . '/' . $path;
}

// Human-friendly titles for ranks (shown in the top-right user chip).
// Rank mapping requested:
// 1 = User, 2 = Moderator, 3 = Administrator, 4+ = Owner/Founder
function rank_title(int $rank): string {
    if ($rank >= 4) return 'Owner/Founder';
    if ($rank === 3) return 'Administrator';
    if ($rank === 2) return 'Moderator';
    if ($rank === 1) return 'User';
    return 'Guest';
}

function admin_flash_html(): string {
    $f = flash_get();
    if (!$f || !is_array($f)) return '';
    $type = (string)($f['type'] ?? 'info');
    $msg  = (string)($f['msg'] ?? '');
    if ($msg === '') return '';

    $cls = 'adm-alert';
    if ($type === 'success') $cls .= ' adm-alert--success';
    elseif ($type === 'error') $cls .= ' adm-alert--error';
    elseif ($type === 'warning') $cls .= ' adm-alert--warning';

    return '<div class="' . e($cls) . '">' . e($msg) . '</div>';
}

// --- Convenience helpers for admin pages ---

function admin_int($v, int $default = 0): int {
    if ($v === null || $v === '') return $default;
    if (is_numeric($v)) return (int)$v;
    return $default;
}

function admin_get_user(int $userId): ?array {
    if ($userId <= 0) return null;
    $stmt = db()->prepare('SELECT id, username, email, rank, permaBan, joinTime, coins, head, face, neck, body, hand, feet, color, photo, flag, ninjaRank, ninjaProgress FROM users WHERE id = ? LIMIT 1');
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res->fetch_assoc() ?: null;
    $stmt->close();
    return $row;
}

function admin_user_temp_banned(int $userId): bool {
    if ($userId <= 0) return false;
    $stmt = db()->prepare('SELECT 1 FROM bans WHERE userId = ? AND expires > NOW() LIMIT 1');
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $res = $stmt->get_result();
    $yes = (bool)$res->fetch_row();
    $stmt->close();
    return $yes;
}

function admin_badge_html(string $label, string $variant = 'neutral'): string {
    $cls = 'adm-badge';
    if ($variant === 'good') $cls .= ' adm-badge--good';
    elseif ($variant === 'warn') $cls .= ' adm-badge--warn';
    elseif ($variant === 'bad') $cls .= ' adm-badge--bad';
    elseif ($variant === 'info') $cls .= ' adm-badge--info';
    return '<span class="' . e($cls) . '">' . e($label) . '</span>';
}


// ---- Server control helpers ----
function admin_client_ip(): string {
    // Respect common proxy headers only if you trust your reverse proxy; otherwise REMOTE_ADDR is safest.
    return (string)($_SERVER['REMOTE_ADDR'] ?? '');
}

function admin_server_control_allowed(?array $u): bool {
    if (!ADMIN_ALLOW_SERVER_CONTROL) return false;
    if (!$u) return false;
    if ((int)($u['rank'] ?? 0) < (int)ADMIN_SERVER_CONTROL_MIN_RANK) return false;

    $ip = admin_client_ip();
    $allow = array_filter(array_map('trim', explode(',', (string)ADMIN_SERVER_CONTROL_IP_ALLOWLIST)));
    return in_array($ip, $allow, true);
}

function admin_require_server_control(): void {
    $u = current_user();
    if (!admin_is_staff($u)) {
        http_response_code(403);
        exit('Forbidden');
    }
    if (!admin_server_control_allowed($u)) {
        http_response_code(403);
        exit('Server controls are disabled or not permitted from your IP.');
    }
}

/**
 * Attempt to locate your yukon-server directory (where package.json lives).
 * You can override by defining ADMIN_YUKON_SERVER_DIR in /administration/config/server_control.php
 */
function admin_yukon_server_dir(): ?string {
    if (defined('ADMIN_YUKON_SERVER_DIR')) {
        $d = (string)ADMIN_YUKON_SERVER_DIR;
        if ($d && is_dir($d) && is_file(rtrim($d, '/\\\\') . DIRECTORY_SEPARATOR . 'package.json')) return $d;
        return null;
    }

    $candidates = [
        // common sibling locations
        __DIR__ . '/../../yukon-server',
        __DIR__ . '/../../server',
        __DIR__ . '/../../../yukon-server',
        // windows/xampp common
        'C:\\\\xampp\\\\htdocs\\\\yukon-server',
        'C:\\\\xampp\\\\htdocs\\\\server',
    ];

    foreach ($candidates as $cand) {
        $d = $cand;
        if (is_dir($d) && is_file(rtrim($d, '/\\\\') . DIRECTORY_SEPARATOR . 'package.json')) {
            return $d;
        }
    }
    return null;
}

function admin_npm_bin(): string {
    // On Windows, npm is usually npm.cmd
    if (stripos(PHP_OS_FAMILY, 'Windows') !== false) return 'npm.cmd';
    return 'npm';
}

/**
 * Run a whitelisted command in a given working directory.
 * Returns ['code'=>int,'out'=>string]
 */
function admin_run_cmd(string $cmd, string $cwd, int $timeoutSeconds = 6): array {
    $descriptorspec = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];

    $pipes = [];
    $proc = @proc_open($cmd, $descriptorspec, $pipes, $cwd, null, ['bypass_shell' => false]);

    if (!is_resource($proc)) {
        return ['code' => 1, 'out' => 'Failed to start process.'];
    }

    // non-blocking so we can time out safely
    stream_set_blocking($pipes[1], false);
    stream_set_blocking($pipes[2], false);

    $out = '';
    $start = microtime(true);

    while (true) {
        $status = proc_get_status($proc);
        $out .= stream_get_contents($pipes[1]);
        $out .= stream_get_contents($pipes[2]);

        if (!$status['running']) break;

        if ((microtime(true) - $start) > $timeoutSeconds) {
            // hard stop
            @proc_terminate($proc);
            break;
        }
        usleep(100000);
    }

    foreach ($pipes as $p) { if (is_resource($p)) @fclose($p); }
    $code = @proc_close($proc);
    if (!is_int($code)) $code = 0;

    return ['code' => $code, 'out' => trim($out)];
}

function admin_server_cmd(string $action): ?string {
    $npm = admin_npm_bin();
    // WHITELIST ONLY
    $map = [
        'list' => $npm . ' run list --silent',
        'start' => $npm . ' run start --silent',
        'stop' => $npm . ' run stop --silent',
        'restart' => $npm . ' run restart --silent',
        // logs tends to stream; we timebox it in admin_run_cmd()
        'logs' => $npm . ' run logs --silent',
    ];
    return $map[$action] ?? null;
}

