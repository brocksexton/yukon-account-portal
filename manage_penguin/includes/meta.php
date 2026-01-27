<?php
// manage_penguin/includes/meta.php
// Loads Yukon items metadata and provides slot mapping + asset URL helpers.

$YUKON_FS = realpath(__DIR__ . '/../../yukon');
$YUKON_WEB = '/yukon';

// Fallback crumbs/items location bundled with manage_penguin.
$MP_CRUMBS_FS = realpath(__DIR__ . '/../assets/crumbs/en');

$ITEMS_META = [];

// Prefer Yukon items.json (source-of-truth for item types/cost/member).
$ITEMS_META_PATH = $YUKON_FS ? ($YUKON_FS . '/assets/media/crumbs/en/items.json') : null;
// If Yukon isn't available, use the bundled copy.
if ((!$ITEMS_META_PATH || !is_file($ITEMS_META_PATH)) && $MP_CRUMBS_FS) {
    $ITEMS_META_PATH = $MP_CRUMBS_FS . '/items.json';
}
if ($ITEMS_META_PATH && is_file($ITEMS_META_PATH)) {
    $raw = file_get_contents($ITEMS_META_PATH);
    $decoded = json_decode($raw, true);
    if (is_array($decoded)) {
        foreach ($decoded as $k => $v) {
            $id = (int)$k;
            if ($id > 0 && is_array($v)) $ITEMS_META[$id] = $v;
        }
    }
}

// Yukon item 'type' mapping (from items.json)
// 1=color, 2=head, 3=face, 4=neck, 5=body, 6=hand, 7=feet, 8=flag, 9=background/photo
function item_type_to_slot(?int $type): ?string {
    switch ((int)$type) {
        case 1: return 'color';
        case 2: return 'head';
        case 3: return 'face';
        case 4: return 'neck';
        case 5: return 'body';
        case 6: return 'hand';
        case 7: return 'feet';
        case 8: return 'flag';
        case 9: return 'photo';
        default: return null;
    }
}

function clothing_icon_url(int $id): string {
    global $YUKON_WEB;
    $id = (int)$id;
    if ($id <= 0) return '';
    return $YUKON_WEB . '/assets/media/clothing/icon/240/' . $id . '.png';
}

function item_display_name(int $id, array $ITEMS_META): string {
    if (isset($ITEMS_META[$id]['name'])) return (string)$ITEMS_META[$id]['name'];
    return '#' . $id;
}

function item_slot_of(int $id, array $ITEMS_META): ?string {
    if (!isset($ITEMS_META[$id])) return null;
    $type = isset($ITEMS_META[$id]['type']) ? (int)$ITEMS_META[$id]['type'] : null;
    return item_type_to_slot($type);
}

function slot_label(string $slot): string {
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

function slot_list(): array {
    return ['color','head','face','neck','body','hand','feet','photo','flag'];
}
