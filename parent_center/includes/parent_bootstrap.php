<?php
// Parent Center bootstrap
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/avatar_component.php';

// --- Parent Center config defaults ---
if (!defined('PARENT_CENTER_ALLOW_CREATE')) {
    define('PARENT_CENTER_ALLOW_CREATE', true);
}

function parent_is_logged_in(): bool {
    return isset($_SESSION['parent_id']) && is_numeric($_SESSION['parent_id']);
}

function parent_require_login(): void {
    if (!parent_is_logged_in()) {
        header('Location: index.php');
        exit;
    }
}

function parent_current(): ?array {
    if (!parent_is_logged_in()) return null;
    $pid = (int)$_SESSION['parent_id'];
    $stmt = db()->prepare('SELECT id, email, createdAt FROM parents WHERE id = ? LIMIT 1');
    $stmt->bind_param('i', $pid);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

// --- Schema feature detection ---
// Some installs may not yet have the `role` column on `parent_children`.
// We detect it once per request to keep queries compatible.
function parent_children_has_role_column(): bool {
    static $has = null;
    if ($has !== null) return $has;

    $db = db();
    $dbNameRes = $db->query('SELECT DATABASE() AS dbname');
    $dbNameRow = $dbNameRes ? $dbNameRes->fetch_assoc() : null;
    $dbName = $dbNameRow ? (string)$dbNameRow['dbname'] : '';
    if ($dbName === '') {
        $has = false;
        return $has;
    }

    $stmt = $db->prepare('SELECT COUNT(*) AS c FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = "parent_children" AND COLUMN_NAME = "role"');
    $stmt->bind_param('s', $dbName);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $stmt->close();
    $has = ($row && (int)$row['c'] > 0);
    return $has;
}

function parent_linked_parent_penguin(int $parentId): ?array {
    if (!parent_children_has_role_column()) return null;
    $stmt = db()->prepare('
        SELECT pc.userId, pc.alias, pc.claimedAt, pc.role,
               u.id AS userId2, u.username, u.joinTime, u.coins, u.rank, u.color, u.head, u.face, u.neck, u.body, u.hand, u.feet, u.photo, u.flag, u.ninjaRank, u.ninjaProgress, u.permaBan
        FROM parent_children pc
        JOIN users u ON u.id = pc.userId
        WHERE pc.parentId = ? AND pc.role = "parent"
        ORDER BY pc.claimedAt DESC
        LIMIT 1
    ');
    $stmt->bind_param('i', $parentId);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $stmt->close();
    if (!$row) return null;
    // Normalize keys
    $row['userId'] = (int)$row['userId'];
    return $row;
}

function parent_children(int $parentId): array {
    $hasRole = parent_children_has_role_column();
    $sql = '
        SELECT pc.userId, pc.alias, pc.claimedAt' . ($hasRole ? ', pc.role' : '') . ',
               u.id AS userId2, u.username, u.joinTime, u.coins, u.rank, u.color, u.head, u.face, u.neck, u.body, u.hand, u.feet, u.photo, u.flag, u.ninjaRank, u.ninjaProgress, u.permaBan
        FROM parent_children pc
        JOIN users u ON u.id = pc.userId
        WHERE pc.parentId = ?' . ($hasRole ? ' AND pc.role = "child"' : '') . '
        ORDER BY pc.claimedAt DESC
    ';
    $stmt = db()->prepare($sql);
    $stmt->bind_param('i', $parentId);
    $stmt->execute();
    $res = $stmt->get_result();
    $rows = $res->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    foreach ($rows as &$r) {
        // Ensure consistent key for avatar helper
        $r['userId'] = (int)$r['userId'];
    }
    return $rows;
}

function parent_child_by_username(int $parentId, string $username): ?array {
    $hasRole = parent_children_has_role_column();
    $sql = '
        SELECT pc.userId, pc.alias' . ($hasRole ? ', pc.role' : '') . ', u.*
        FROM parent_children pc
        JOIN users u ON u.id = pc.userId
        WHERE pc.parentId = ? AND u.username = ?' . ($hasRole ? ' AND pc.role = "child"' : '') . '
        LIMIT 1
    ';
    $stmt = db()->prepare($sql);
    $stmt->bind_param('is', $parentId, $username);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res->fetch_assoc();
    $stmt->close();
    if ($row) {
        $row['userId'] = (int)$row['userId'];
    }
    return $row ?: null;
}

function parent_avatar_url(array $child, int $size = 220, bool $profile = false): string {
    // Use your existing avatar endpoint with your preferred defaults baked in
    // Yukon query results are not consistent about the key used for the penguin id.
    // Prefer `userId` (what our queries select), but accept common fallbacks.
    $uid = 0;
    if (isset($child['userId'])) $uid = (int)$child['userId'];
    elseif (isset($child['userId2'])) $uid = (int)$child['userId2'];
    elseif (isset($child['id'])) $uid = (int)$child['id'];
    elseif (isset($child['penguin_id'])) $uid = (int)$child['penguin_id'];

    $qs = [
        'uid' => $uid,
        'size' => $profile ? $size : "{$size}x{$size}",
        'profile' => $profile ? 'true' : 'false',
    ];
    if ($profile) {
        // Profile defaults (tuned for 256, size-aware handled by renderer)
        $qs['scale'] = 1.7;
        $qs['x'] = 3;
        // Smaller avatars need a slightly higher crop so the face sits centered.
        $qs['y'] = ($size <= 96) ? 18 : 26;
        $qs['background'] = 'true';
        $qs['pin'] = 'false'; // requested: no pin on profile pictures
    } else {
        $qs['background'] = 'true';
        $qs['pin'] = 'true';
    }
    // Use absolute-from-root to avoid relative path issues inside /parent_center/.
    return '/avatar.php?' . http_build_query($qs);
}

/**
 * Render a small circular profile avatar using static PNG layers.
 *
 * We intentionally avoid /avatar.php (SVG renderer) here because browsers can block
 * externally-referenced resources inside SVGs when embedded via <img>, which results
 * in a blank/transparent render. The main playercard uses render_penguin_avatar()
 * (layered PNGs) and is safe.
 *
 * Layers:
 *  - /assets/img/colours/{color}.png   (base colour)
 *  - /assets/img/penguin_transparent.png (outline/shine)
 */
function parent_profile_avatar_html(array $child, int $size = 96, string $extraClass = ''): string {
    $color = isset($child['color']) ? (int)$child['color'] : 1;
    if ($color < 1) $color = 1;

    $base = (string)('/assets/img/colours/' . $color . '.png');
    $top  = (string)('/assets/img/penguin_transparent.png');

    $cls = trim('pc-miniAvatar ' . $extraClass);

    // Note: using fixed width/height avoids layout shift while images load.
    $html = '<span class="' . e($cls) . '" style="--pc-avatar-size:' . (int)$size . 'px">'
          .   '<img class="pc-miniAvatar__layer" src="' . e($base) . '" alt="" loading="lazy" decoding="async">'
          .   '<img class="pc-miniAvatar__layer" src="' . e($top)  . '" alt="" loading="lazy" decoding="async">'
          . '</span>';
    return $html;
}

/**
 * Render a small circular profile avatar INCLUDING equipped clothing layers.
 *
 * We still avoid /avatar.php (SVG) for the same security/blank-render reasons,
 * but we do include the paper-doll clothing layers as normal PNGs.
 *
 * Layers:
 *  - /assets/img/colours/{color}.png
 *  - /assets/img/penguin_transparent.png
 *  - {YUKON_ASSET_BASE_URL}/media/clothing/paper/{id}.png (feet/body/neck/face/head/hand)
 */
function parent_profile_avatar_clothed_html(array $child, int $size = 96, string $extraClass = ''): string {
    $color = isset($child['color']) ? (int)$child['color'] : 1;
    if ($color < 1) $color = 1;

    $base = '/assets/img/colours/' . $color . '.png';
    $top  = '/assets/img/penguin_transparent.png';

    // Clothing layers order mirrors includes/avatar_component.php
    $layers = [
        (int)($child['feet'] ?? 0),
        (int)($child['body'] ?? 0),
        (int)($child['neck'] ?? 0),
        (int)($child['face'] ?? 0),
        (int)($child['head'] ?? 0),
        (int)($child['hand'] ?? 0),
    ];
    $urlBase = rtrim(YUKON_ASSET_BASE_URL, '/');

    $cls = trim('pc-miniAvatar pc-miniAvatar--clothed ' . $extraClass);

    $html = '<span class="' . e($cls) . '" style="--pc-avatar-size:' . (int)$size . 'px">'
          .   '<span class="pc-miniAvatar__stack" aria-hidden="true">'
          .     '<img class="pc-miniAvatar__layer" src="' . e($base) . '" alt="" loading="lazy" decoding="async">'
          .     '<img class="pc-miniAvatar__layer" src="' . e($top)  . '" alt="" loading="lazy" decoding="async">';

    foreach ($layers as $id) {
        if ($id <= 0) continue;
        $src = $urlBase . '/media/clothing/paper/' . $id . '.png';
        $html .= '<img class="pc-miniAvatar__layer" src="' . e($src) . '" alt="" loading="lazy" decoding="async">';
    }

    $html .=   '</span>'
          . '</span>';
    return $html;
}

// --- Alerts / moderation helpers ---

/**
 * Parse a Yukon-ish timestamp.
 * Accepts unix epoch seconds, MySQL DATETIME strings, or anything strtotime() can parse.
 */
function parent_parse_time($value): ?int {
    if ($value === null) return null;
    if (is_int($value)) return $value > 0 ? $value : null;
    if (is_float($value)) return ((int)$value) > 0 ? (int)$value : null;
    $s = trim((string)$value);
    if ($s === '') return null;
    if (ctype_digit($s)) {
        $n = (int)$s;
        return $n > 0 ? $n : null;
    }
    $ts = strtotime($s);
    return ($ts !== false) ? $ts : null;
}

/**
 * Human-friendly datetime formatting for the Parent Center UI.
 *
 * Examples:
 *  - 2026-01-21 19:00 -> January 21, 2026 @ 7 PM
 *  - 2026-01-21 19:26 -> January 21, 2026 @ 7:26 PM
 */
function parent_human_datetime($value): string {
    $ts = parent_parse_time($value);
    if ($ts === null) return '';

    // Convert to server timezone (keeps behaviour consistent with existing pages).
    $dt = new DateTime('@' . $ts);
    $dt->setTimezone(new DateTimeZone(date_default_timezone_get()));

    $minutes = $dt->format('i');
    $time = $dt->format('g') . ($minutes !== '00' ? (':' . $minutes) : '') . ' ' . $dt->format('A');
    return $dt->format('F j, Y') . ' @ ' . $time;
}

/**
 * Get a list of ban-related alerts for linked child accounts.
 *
 * Returns rows:
 *  - username
 *  - userId
 *  - kind: 'perma' | 'active' | 'recent'
 *  - issued_ts, expires_ts
 *  - message
 */
function parent_child_ban_alerts(int $parentId, int $recentDays = 30): array {
    $children = parent_children($parentId);
    if (!$children) return [];

    // Build a map so we can reference permaBan + username quickly.
    $byId = [];
    $ids = [];
    foreach ($children as $c) {
        $uid = (int)($c['userId'] ?? 0);
        if ($uid <= 0) continue;
        $ids[] = $uid;
        $byId[$uid] = $c;
    }
    if (!$ids) return [];

    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $types = str_repeat('i', count($ids));

    // Pull the most recent ban per user.
    // NOTE: This query is compatible with MySQL 5.7+ (no window functions).
    $sql = "
        SELECT b.userId, b.issued, b.expires, b.message
        FROM bans b
        INNER JOIN (
            SELECT userId, MAX(issued) AS maxIssued
            FROM bans
            WHERE userId IN ($placeholders)
            GROUP BY userId
        ) x ON x.userId = b.userId AND x.maxIssued = b.issued
    ";

    $stmt = db()->prepare($sql);
    // Bind dynamically
    $stmt->bind_param($types, ...$ids);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $now = time();
    $recentCutoff = $now - ($recentDays * 86400);

    $alerts = [];

    // Perma bans first (even if there is no bans row)
    foreach ($byId as $uid => $c) {
        if ((int)($c['permaBan'] ?? 0) === 1) {
            $alerts[] = [
                'userId' => $uid,
                'username' => (string)($c['username'] ?? 'Unknown'),
                'kind' => 'perma',
                'issued_ts' => null,
                'expires_ts' => null,
                'message' => 'This account is permanently banned.'
            ];
        }
    }

    foreach ($rows as $r) {
        $uid = (int)($r['userId'] ?? 0);
        if ($uid <= 0 || !isset($byId[$uid])) continue;
        // If perma ban is set, we already alert and we don't need to duplicate.
        if ((int)($byId[$uid]['permaBan'] ?? 0) === 1) continue;

        $issuedTs = parent_parse_time($r['issued'] ?? null);
        $expiresTs = parent_parse_time($r['expires'] ?? null);
        $isActive = ($expiresTs !== null) ? ($expiresTs >= $now) : false;
        $isRecent = ($issuedTs !== null) ? ($issuedTs >= $recentCutoff) : false;

        if ($isActive || $isRecent) {
            $alerts[] = [
                'userId' => $uid,
                'username' => (string)($byId[$uid]['username'] ?? 'Unknown'),
                'kind' => $isActive ? 'active' : 'recent',
                'issued_ts' => $issuedTs,
                'expires_ts' => $expiresTs,
                'message' => (string)($r['message'] ?? '')
            ];
        }
    }

    // Sort: perma -> active -> recent, newest first inside each group
    $rank = ['perma' => 0, 'active' => 1, 'recent' => 2];
    usort($alerts, function($a, $b) use ($rank) {
        $ra = $rank[$a['kind']] ?? 9;
        $rb = $rank[$b['kind']] ?? 9;
        if ($ra !== $rb) return $ra <=> $rb;
        $ta = (int)($a['issued_ts'] ?? 0);
        $tb = (int)($b['issued_ts'] ?? 0);
        return $tb <=> $ta;
    });

    return $alerts;
}


function csrf_check(string $token): bool {
    return isset($_SESSION['csrf']) && hash_equals((string)$_SESSION['csrf'], (string)$token);
}


// Back-compat alias
function parent_require_auth(): void {
    parent_require_login();
}


// Back-compat alias (older dashboard)
function parent_children_for_parent(int $parentId): array {
    return parent_children($parentId);
}

// -----------------------------------------------------------------------------
// Parent account creation guardrails
// -----------------------------------------------------------------------------

/**
 * Link a penguin user account to a Parent Center profile.
 *
 * This is used by Create Child Account and Parent Account creation.
 *
 * @param int $parentId Parent Center account id
 * @param int $userId   Yukon user id
 * @param ?string $alias Optional label shown in the UI
 * @param string $role  'child' or 'parent'
 * @return bool
 */
function parent_link_account(int $parentId, int $userId, ?string $alias = null, string $role = 'child'): bool {
    $role = strtolower(trim($role));
    if ($role !== 'parent') $role = 'child';

    // Prevent duplicates
    $stmt = db()->prepare('SELECT 1 FROM parent_children WHERE parentId = ? AND userId = ? LIMIT 1');
    $stmt->bind_param('ii', $parentId, $userId);
    $stmt->execute();
    $exists = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($exists) return true;

    $aliasVal = ($alias !== null && trim($alias) !== '') ? trim($alias) : null;

    if (parent_children_has_role_column()) {
        $stmt = db()->prepare('INSERT INTO parent_children (parentId, userId, alias, role, claimedAt) VALUES (?, ?, ?, ?, NOW())');
        $stmt->bind_param('iiss', $parentId, $userId, $aliasVal, $role);
    } else {
        // Older schema without role support.
        $stmt = db()->prepare('INSERT INTO parent_children (parentId, userId, alias, claimedAt) VALUES (?, ?, ?, NOW())');
        $stmt->bind_param('iis', $parentId, $userId, $aliasVal);
    }

    $ok = $stmt->execute();
    $stmt->close();
    return (bool)$ok;
}

/**
 * Returns true if a user is currently banned (temporary active ban) or uses a "zero" expiry.
 * NOTE: Permanent bans are handled via users.permaBan.
 */
function parent_user_has_active_ban(int $userId): bool {
    $stmt = db()->prepare('SELECT 1 FROM bans WHERE userId = ? AND (expires = "0000-00-00 00:00:00" OR expires >= NOW()) LIMIT 1');
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return (bool)$row;
}

/**
 * Authenticate a penguin account (Yukon users table) and enforce "active" status.
 *
 * Rules:
 *  - username/password must match
 *  - permaBan must be 0
 *  - no active ban record (expires >= now OR zero-date expiry)
 */
function parent_penguin_authenticate(string $username, string $password, ?string &$error = null): ?array {
    $username = trim($username);
    if ($username === '' || $password === '') {
        $error = 'Enter a penguin username and password.';
        return null;
    }

    $stmt = db()->prepare('SELECT id, username, password, permaBan FROM users WHERE username = ? LIMIT 1');
    $stmt->bind_param('s', $username);
    $stmt->execute();
    $u = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$u || !password_verify($password, (string)$u['password'])) {
        $error = 'Could not verify that penguin account. Double-check the username and password.';
        return null;
    }

    $uid = (int)$u['id'];

    if ((int)($u['permaBan'] ?? 0) === 1) {
        $error = 'That penguin account is permanently banned and cannot be used.';
        return null;
    }

    if (parent_user_has_active_ban($uid)) {
        $error = 'That penguin account is currently banned and cannot be used.';
        return null;
    }

    return $u;
}

/**
 * Returns true if this penguin is already linked as a "parent" to any Parent Center account.
 *
 * This prevents using a single penguin to create multiple Parent Center accounts.
 */
function parent_penguin_is_linked_as_parent(int $userId): bool {
    if (parent_children_has_role_column()) {
        $stmt = db()->prepare('SELECT 1 FROM parent_children WHERE userId = ? AND role = "parent" LIMIT 1');
        $stmt->bind_param('i', $userId);
    } else {
        // Older schema can't distinguish roles; treat any link as a parent link.
        $stmt = db()->prepare('SELECT 1 FROM parent_children WHERE userId = ? LIMIT 1');
        $stmt->bind_param('i', $userId);
    }

    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return (bool)$row;
}
