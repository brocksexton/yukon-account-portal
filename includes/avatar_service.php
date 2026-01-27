<?php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/config.php';

/**
 * Fetch a user row by uid OR username.
 * Returns associative array with the fields needed for avatar rendering.
 */
function avatar_fetch_user(?int $uid, ?string $username): ?array {
    if ($uid && $uid > 0) {
        $stmt = db()->prepare('SELECT id, username, head, face, neck, body, hand, feet, color, photo, flag FROM users WHERE id = ? LIMIT 1');
        $stmt->bind_param('i', $uid);
        $stmt->execute();
        $res = $stmt->get_result();
        $user = $res->fetch_assoc() ?: null;
        $stmt->close();
        return $user;
    }

    if ($username !== null && $username !== '') {
        $stmt = db()->prepare('SELECT id, username, head, face, neck, body, hand, feet, color, photo, flag FROM users WHERE username = ? LIMIT 1');
        $stmt->bind_param('s', $username);
        $stmt->execute();
        $res = $stmt->get_result();
        $user = $res->fetch_assoc() ?: null;
        $stmt->close();
        return $user;
    }

    return null;
}

/** Parse "true/false/1/0" style query params. */
function avatar_qbool(string $key, ?bool $default = null): ?bool {
    if (!isset($_GET[$key])) return $default;
    $v = strtolower(trim((string)$_GET[$key]));
    if ($v === '1' || $v === 'true' || $v === 'yes' || $v === 'on') return true;
    if ($v === '0' || $v === 'false' || $v === 'no' || $v === 'off') return false;
    return $default;
}

/**
 * Parse size as either "128" or "600x600".
 * Returns [w, h].
 */
function avatar_qsize(string $key = 'size', int $default = 220): array {
    $raw = isset($_GET[$key]) ? trim((string)$_GET[$key]) : '';
    if ($raw === '') return [$default, $default];

    if (preg_match('/^(\d{1,4})x(\d{1,4})$/i', $raw, $m)) {
        $w = (int)$m[1];
        $h = (int)$m[2];
        $w = max(32, min(2048, $w));
        $h = max(32, min(2048, $h));
        return [$w, $h];
    }

    if (preg_match('/^\d{1,4}$/', $raw)) {
        $n = (int)$raw;
        $n = max(32, min(2048, $n));
        return [$n, $n];
    }

    return [$default, $default];
}

function avatar_qfloat(string $key, float $default): float {
    if (!isset($_GET[$key])) return $default;
    $v = (float)$_GET[$key];
    // clamp to sane values
    if ($v < 0.1) $v = 0.1;
    if ($v > 5.0) $v = 5.0;
    return $v;
}

function avatar_qint(string $key, int $default): int {
    if (!isset($_GET[$key])) return $default;
    $v = (int)$_GET[$key];
    if ($v < -5000) $v = -5000;
    if ($v > 5000) $v = 5000;
    return $v;
}

function avatar_colour_src(int $colorId): string {
    $id = $colorId > 0 ? $colorId : 1;
    return 'assets/img/colours/' . $id . '.png';
}

/**
 * Build an SVG string that layers:
 *  - optional background photo
 *  - penguin colour base + penguin_transparent
 *  - wearable paper layers
 *  - optional pin
 * Supports profile circle cropping.
 */
function avatar_build_svg(array $user, array $opts): string {
    $w = (int)$opts['w'];
    $h = (int)$opts['h'];
    $background = (bool)$opts['background'];
    $pin = (bool)$opts['pin'];
    $profile = (bool)$opts['profile'];
    $scale = (float)$opts['scale'];
    $dx = (int)$opts['x'];
    $dy = (int)$opts['y'];

    $urlBase = rtrim(YUKON_ASSET_BASE_URL, '/');

    $colorId = (int)($user['color'] ?? 1);
    $colourSrc = avatar_colour_src($colorId);

    $layers = [
        (int)($user['feet'] ?? 0),
        (int)($user['body'] ?? 0),
        (int)($user['neck'] ?? 0),
        (int)($user['face'] ?? 0),
        (int)($user['head'] ?? 0),
        (int)($user['hand'] ?? 0),
    ];

    $photoId = (int)($user['photo'] ?? 0);
    $flagId  = (int)($user['flag'] ?? 0);

    // Escape for XML attributes
    $xe = function(string $s): string {
        return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    };

    $clipId = 'clip_' . substr(md5(($user['id'] ?? '') . '|' . $w . 'x' . $h . '|' . ($profile ? 'p' : 'c')), 0, 10);

    $svg = '';
    $svg .= '<?xml version="1.0" encoding="UTF-8"?>';
    $svg .= '<svg xmlns="http://www.w3.org/2000/svg" width="' . $w . '" height="' . $h . '" viewBox="0 0 ' . $w . ' ' . $h . '">';

    if ($profile) {
        $r = (int)floor(min($w, $h) / 2);
        $cx = (int)floor($w / 2);
        $cy = (int)floor($h / 2);
        $svg .= '<defs><clipPath id="' . $xe($clipId) . '"><circle cx="' . $cx . '" cy="' . $cy . '" r="' . $r . '" /></clipPath></defs>';
        $svg .= '<g clip-path="url(#' . $xe($clipId) . ')">';
    }

    if ($background && $photoId > 0) {
        $bgUrl = $urlBase . '/media/clothing/photos/' . $photoId . '.png';
        $svg .= '<image href="' . $xe($bgUrl) . '" x="0" y="0" width="' . $w . '" height="' . $h . '" preserveAspectRatio="xMidYMid slice"/>';
    } else {
        // Slightly transparent fill so profile mode looks nice even without bg
        $svg .= '<rect x="0" y="0" width="' . $w . '" height="' . $h . '" fill="transparent"/>';
    }

    // Penguin + wearables group (scaled/translated together)
    $tx = ($w / 2) + $dx;
    $ty = ($h / 2) + $dy;
    $svg .= '<g transform="translate(' . $tx . ' ' . $ty . ') scale(' . $scale . ') translate(' . (-$w / 2) . ' ' . (-$h / 2) . ')">';

    $svg .= '<image href="' . $xe($colourSrc) . '" x="0" y="0" width="' . $w . '" height="' . $h . '" />';
    $svg .= '<image href="' . $xe('assets/img/penguin_transparent.png') . '" x="0" y="0" width="' . $w . '" height="' . $h . '" />';

    foreach ($layers as $id) {
        if ($id <= 0) continue;
        $src = $urlBase . '/media/clothing/paper/' . $id . '.png';
        $svg .= '<image href="' . $xe($src) . '" x="0" y="0" width="' . $w . '" height="' . $h . '" />';
    }
    $svg .= '</g>';

    if ($pin && $flagId > 0) {
        $pinSrc = $urlBase . '/media/clothing/icon/120/' . $flagId . '.png';
        $pinSize = (int)round(min($w, $h) * 0.22);
        $pinX = (int)round(min($w, $h) * 0.06);
        $pinY = (int)round(min($w, $h) * 0.06);
        $svg .= '<image href="' . $xe($pinSrc) . '" x="' . $pinX . '" y="' . $pinY . '" width="' . $pinSize . '" height="' . $pinSize . '" preserveAspectRatio="xMidYMid meet"/>';
    }

    if ($profile) {
        $svg .= '</g>';
    }

    $svg .= '</svg>';
    return $svg;
}
