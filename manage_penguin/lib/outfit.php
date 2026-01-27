<?php
require_once __DIR__ . '/crumbs.php';

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
    return mp_yukon_root_url() . '/assets/media/clothing/icon/240/' . $id . '.png';
}

function mp_color_icon_url(int $id): string {
    if ($id <= 0) return '';
    return mp_yukon_root_url() . '/assets/media/color/icon/120/' . $id . '.png';
}

/**
 * Load owned inventory item IDs for a user.
 */
function mp_owned_items(int $userId): array {
    $owned = [];
    $stmt = db()->prepare('SELECT itemId FROM inventories WHERE userId = ?');
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()) $owned[(int)$r['itemId']] = true;
    $stmt->close();
    return $owned;
}

/**
 * Validate + normalize outfit submission.
 * Enforces:
 *  - Ownership for all non-color slots (0 allowed)
 *  - Slot correctness via crumbs map (blocks unknown/mismatched)
 *  - Color correctness via colors.json
 *
 * Returns [ok, message, values]
 */
function mp_validate_outfit_submission(int $userId, array $postSlots, string $lang='en'): array {
    $slots = ['color','head','face','neck','body','hand','feet','photo','flag'];

    $owned = mp_owned_items($userId);
    $slotMap = mp_item_slot_map($lang);
    $validColors = mp_valid_color_ids($lang);

    // Security posture: if crumbs map is missing, we refuse to save outfit
    if (empty($slotMap)) {
        return [false, 'Outfit saving is temporarily unavailable (item metadata not loaded).', []];
    }

    $vals = [];
    foreach ($slots as $slot) {
        $v = isset($postSlots[$slot]) ? (int)$postSlots[$slot] : 0;
        if ($v < 0) $v = 0;

        if ($slot === 'color') {
            if ($v !== 0 && !isset($validColors[$v])) {
                return [false, 'Invalid color selection (#' . (int)$v . ').', []];
            }
            $vals[$slot] = $v;
            continue;
        }

        if ($v !== 0) {
            if (!isset($owned[$v])) {
                return [false, 'Invalid selection: you do not own item #' . (int)$v . '.', []];
            }
            $actualSlot = $slotMap[$v] ?? null;
            if ($actualSlot === null) {
                return [false, 'Item #' . (int)$v . ' is missing metadata and cannot be equipped via the website yet.', []];
            }
            if ($actualSlot !== $slot) {
                return [false, 'Item #' . (int)$v . ' belongs to ' . mp_slot_label($actualSlot) . ' and cannot be equipped in ' . mp_slot_label($slot) . '.', []];
            }
        }
        $vals[$slot] = $v;
    }

    return [true, 'OK', $vals];
}

function mp_save_outfit(int $userId, array $vals): void {
    $stmt = db()->prepare('UPDATE users SET color=?, head=?, face=?, neck=?, body=?, hand=?, feet=?, photo=?, flag=? WHERE id=?');
    $stmt->bind_param(
        'iiiiiiiiii',
        $vals['color'],
        $vals['head'],
        $vals['face'],
        $vals['neck'],
        $vals['body'],
        $vals['hand'],
        $vals['feet'],
        $vals['photo'],
        $vals['flag'],
        $userId
    );
    $stmt->execute();
    $stmt->close();
}
