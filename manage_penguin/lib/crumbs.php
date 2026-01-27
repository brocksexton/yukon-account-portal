<?php
require_once __DIR__ . '/paths.php';

function mp_read_json(string $path): ?array {
    if (!is_file($path)) return null;
    $raw = @file_get_contents($path);
    if ($raw === false) return null;
    $data = json_decode($raw, true);
    return is_array($data) ? $data : null;
}

function mp_items_crumbs(string $lang = 'en'): ?array {
    $dir = mp_crumbs_fs_dir($lang);
    // Prefer items.json if present, otherwise fall back to crumbs.json
    $items = mp_read_json($dir . '/items.json');
    if (is_array($items)) return $items;
    $crumbs = mp_read_json($dir . '/crumbs.json');
    if (!is_array($crumbs)) return null;
    // common patterns: { items: [...] } or { data: { items: [...] } }
    if (isset($crumbs['items']) && is_array($crumbs['items'])) return $crumbs['items'];
    if (isset($crumbs['data']['items']) && is_array($crumbs['data']['items'])) return $crumbs['data']['items'];
    return null;
}

function mp_colors_crumbs(string $lang = 'en'): ?array {
    $dir = mp_crumbs_fs_dir($lang);
    $colors = mp_read_json($dir . '/colors.json');
    if (is_array($colors)) return $colors;
    $crumbs = mp_read_json($dir . '/crumbs.json');
    if (!is_array($crumbs)) return null;
    if (isset($crumbs['colors']) && is_array($crumbs['colors'])) return $crumbs['colors'];
    if (isset($crumbs['data']['colors']) && is_array($crumbs['data']['colors'])) return $crumbs['data']['colors'];
    return null;
}

// Map numeric item types to manage_penguin slots.
// This is the standard Club Penguin mapping used by most CPPSes.
function mp_type_to_slot($type): ?string {
    $t = (int)$type;
    switch ($t) {
        case 1: return 'head';
        case 2: return 'face';
        case 3: return 'neck';
        case 4: return 'body';
        case 5: return 'hand';
        case 6: return 'feet';
        case 7: return 'flag';  // pins/flags
        case 8: return 'photo'; // backgrounds/photos
        default: return null;
    }
}

/**
 * Build an itemId => slot map using crumbs.
 * Supports common item schemas:
 *  - { id: 123, type: 1, name: '...' }
 *  - { id: 123, slot: 'head' }
 *  - { itemId: 123, type: 1 }
 */
function mp_item_slot_map(string $lang = 'en'): array {
    static $cache = [];
    if (isset($cache[$lang])) return $cache[$lang];

    $items = mp_items_crumbs($lang);
    $map = [];
    if (!is_array($items)) { $cache[$lang] = $map; return $map; }

    foreach ($items as $it) {
        if (!is_array($it)) continue;
        $id = $it['id'] ?? ($it['itemId'] ?? null);
        if ($id === null) continue;
        $id = (int)$id;
        if ($id <= 0) continue;

        if (isset($it['slot']) && is_string($it['slot'])) {
            $slot = strtolower(trim($it['slot']));
            // normalize a few common synonyms
            if ($slot === 'pin') $slot = 'flag';
            if ($slot === 'background') $slot = 'photo';
            if (in_array($slot, ['head','face','neck','body','hand','feet','flag','photo'], true)) {
                $map[$id] = $slot;
                continue;
            }
        }

        if (isset($it['type'])) {
            $slot = mp_type_to_slot($it['type']);
            if ($slot) $map[$id] = $slot;
        }
    }

    $cache[$lang] = $map;
    return $map;
}

function mp_item_name_map(string $lang = 'en'): array {
    static $cache = [];
    if (isset($cache[$lang])) return $cache[$lang];
    $items = mp_items_crumbs($lang);
    $names = [];
    if (is_array($items)) {
        foreach ($items as $it) {
            if (!is_array($it)) continue;
            $id = $it['id'] ?? ($it['itemId'] ?? null);
            if ($id === null) continue;
            $id = (int)$id;
            if ($id <= 0) continue;
            $name = $it['name'] ?? ($it['label'] ?? null);
            if (is_string($name) && $name !== '') $names[$id] = $name;
        }
    }
    $cache[$lang] = $names;
    return $names;
}

function mp_valid_color_ids(string $lang = 'en'): array {
    $colors = mp_colors_crumbs($lang);
    $ids = [];
    if (!is_array($colors)) return $ids;
    foreach ($colors as $c) {
        if (!is_array($c)) continue;
        $id = $c['id'] ?? ($c['colorId'] ?? null);
        if ($id === null) continue;
        $id = (int)$id;
        if ($id > 0) $ids[$id] = true;
    }
    return $ids;
}
