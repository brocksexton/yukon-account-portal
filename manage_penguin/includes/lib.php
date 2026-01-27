<?php

function mp_site_root_from_script(): string {
    $script = $_SERVER['SCRIPT_NAME'] ?? '';
    $root = rtrim(str_replace('\\', '/', dirname($script)), '/');
    // manage_penguin lives at /<root>/manage_penguin/index.php, so go one level up.
    $root = rtrim(str_replace('\\', '/', dirname($root)), '/');
    if ($root === '.' || $root === '/') $root = '';
    return $root;
}

function mp_rank_label(int $rank): string {
    switch ($rank) {
        case 4: return 'Owner';
        case 3: return 'Administrator';
        case 2: return 'Moderator';
        default: return 'Player';
    }
}

function mp_slot_label(string $slot): string {
    switch ($slot) {
        case 'color': return 'Color';
        case 'head': return 'Head';
        case 'face': return 'Face';
        case 'neck': return 'Neck';
        case 'body': return 'Body';
        case 'hand': return 'Hand';
        case 'feet': return 'Feet';
        case 'photo': return 'Photo';
        case 'flag': return 'Flag';
        default: return ucfirst($slot);
    }
}

function mp_clothing_icon_url(int $id): string {
    if ($id <= 0) return '';
    return ($GLOBALS['YUKON_ROOT'] ?? '/yukon') . '/assets/media/clothing/icon/240/' . $id . '.png';
}

function mp_color_icon_url(int $id): string {
    if ($id <= 0) return '';
    return ($GLOBALS['YUKON_ROOT'] ?? '/yukon') . '/assets/media/color/icon/120/' . $id . '.png';
}

/** Standard CSRF hidden input using site helpers (csrf_token()). */
function mp_csrf_input(): string {
    if (function_exists('csrf_token')) {
        $t = htmlspecialchars((string)csrf_token(), ENT_QUOTES);
        return '<input type="hidden" name="csrf_token" value="' . $t . '">';
    }
    if (isset($_SESSION['csrf_token'])) {
        $t = htmlspecialchars((string)$_SESSION['csrf_token'], ENT_QUOTES);
        return '<input type="hidden" name="csrf_token" value="' . $t . '">';
    }
    return '';
}
