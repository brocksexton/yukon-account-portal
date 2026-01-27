<?php
// Dynamic avatar endpoint.
// Renders an SVG that layers penguin colour + transparent overlay + worn items.
// Supports optional playercard background + pin and a circular profile crop mode.

require_once __DIR__ . '/includes/avatar_service.php';

// Identify user
$uid = isset($_GET['uid']) ? (int)$_GET['uid'] : null;
$username = isset($_GET['username']) ? trim((string)$_GET['username']) : null;

// Options
[$w, $h] = avatar_qsize('size', 220);

$profile = avatar_qbool('profile', false) ?? false;

// Defaults: full card mode has bg+pin on.
// Profile mode uses your preferred "profile picture" defaults.
$backgroundDefault = $profile ? (bool)PROFILE_AVATAR_DEFAULT_BACKGROUND : true;
$pinDefault = $profile ? (bool)PROFILE_AVATAR_DEFAULT_PIN : true;

$background = avatar_qbool('background', $backgroundDefault);
$pin = avatar_qbool('pin', $pinDefault);

// Fine tuning
$scaleDefault = $profile ? (float)PROFILE_AVATAR_DEFAULT_SCALE : 1.22;
$scale = avatar_qfloat('scale', $scaleDefault);

$xDefault = $profile ? (int)PROFILE_AVATAR_DEFAULT_X : 0;
$x = avatar_qint('x', $xDefault);

$yDefault = $profile ? (int)PROFILE_AVATAR_DEFAULT_Y : 14;
$y = avatar_qint('y', $yDefault);

// Track which values were explicitly overridden (used by builder)
$scaleOverridden = isset($_GET['scale']);
$xOverridden = isset($_GET['x']);
$yOverridden = isset($_GET['y']);

// Profile avatars at small sizes (e.g. 96px in header) need a slightly higher framing
// than larger renders (e.g. 256px). If the caller didn't override y, apply a size-aware
// default that better matches in-game positioning.
if ($profile && !$yOverridden) {
    $maxSide = max($w, $h);
    // Smaller renders (header icons) need the penguin framed higher than 256px.
    // Allow negative y so we can truly raise the stack when needed.
    if ($maxSide <= 96) {
        $y = (int)PROFILE_AVATAR_DEFAULT_Y - 30;
    } elseif ($maxSide <= 128) {
        $y = (int)PROFILE_AVATAR_DEFAULT_Y - 24;
    }
}

$user = avatar_fetch_user($uid, $username);
if (!$user) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Avatar user not found";
    exit;
}

$svg = avatar_build_svg($user, [
    'w' => $w,
    'h' => $h,
    'background' => (bool)$background,
    'pin' => (bool)$pin,
    'profile' => (bool)$profile,
    'scale' => (float)$scale,
    'x' => (int)$x,
    'y' => (int)$y,
    'scale_overridden' => $scaleOverridden,
    'x_overridden' => $xOverridden,
    'y_overridden' => $yOverridden,
]);

// Cache lightly (avatars may change in-game)
header('Content-Type: image/svg+xml; charset=utf-8');
header('Cache-Control: private, max-age=5');
echo $svg;
