<?php
require_once __DIR__ . '/includes/admin_bootstrap.php';
admin_require_staff();

$activeNav = 'users';

$id = admin_int($_GET['id'] ?? 0, 0);
if ($id <= 0) {
    flash_set('error', 'Missing user id.');
    header('Location: ' . admin_url('users.php'));
    exit;
}

$me = current_user();

// Quick item presets (edit/extend as needed).
// These are just convenience bundles for admins; they all grant into `inventories` (unique ownership).
//
// Tip: IDs differ across CPPS forks. To reduce "missing icon" situations, we *try* to filter bundles
// to only item IDs that have a local clothing icon present (if your repo includes icons on disk).
function admin_filter_existing_item_ids(array $ids): array {
    $candidateDirs = [
        __DIR__ . '/../assets/media/clothing/icon/240',
        __DIR__ . '/../assets/media/clothing/icon/120',
        __DIR__ . '/../assets/media/clothing/icon',
        __DIR__ . '/../yukon/assets/media/clothing/icon/240',
        __DIR__ . '/../public/assets/media/clothing/icon/240',
    ];

    $dirs = [];
    foreach ($candidateDirs as $d) {
        $real = realpath($d);
        if ($real && is_dir($real)) $dirs[] = $real;
    }

    // If we can't validate against local icons, just return the original list.
    if (!$dirs) {
        $out = array_values(array_unique(array_map('intval', $ids)));
        sort($out, SORT_NUMERIC);
        return $out;
    }

    $out = [];
    foreach ($ids as $id) {
        $id = (int)$id;
        if ($id <= 0) continue;

        $found = false;
        foreach ($dirs as $d) {
            if (is_file($d . '/' . $id . '.png')) { $found = true; break; }
        }
        if ($found) $out[] = $id;
    }

    $out = array_values(array_unique($out));
    sort($out, SORT_NUMERIC);

    // If validation produced an empty list (e.g., icons stored elsewhere), fall back to the original IDs.
    if (!$out) {
        $out = array_values(array_unique(array_map('intval', $ids)));
        sort($out, SORT_NUMERIC);
    }

    return $out;
}

$givePresets = [
    'colors_1_15' => [
        'group' => 'Colours',
        'label' => 'Give All Colours (IDs 1–15)',
        'ids'   => range(1, 15),
    ],

    'tour_guide_428' => [
        'group' => 'Roles & badges',
        'label' => 'Tour Guide Hat (428)',
        'ids'   => admin_filter_existing_item_ids([428]),
    ],
    'psa_800' => [
        'group' => 'Roles & badges',
        'label' => 'PSA / Secret Agent item (800)',
        'ids'   => admin_filter_existing_item_ids([800]),
    ],
    'roles_tour_and_psa' => [
        'group' => 'Roles & badges',
        'label' => 'Roles: Tour Guide + PSA (428 + 800)',
        'ids'   => admin_filter_existing_item_ids([428, 800]),
    ],

    'classic_accessories' => [
        'group' => 'Classic bundles',
        'label' => 'Classic Accessories Pack (masks/ties/scarves)',
        'ids'   => admin_filter_existing_item_ids([116,117,118,119,120,122,125,162,166,169,172,174,179]),
    ],
    'classic_face_items' => [
        'group' => 'Classic bundles',
        'label' => 'Classic Face Items Pack (Black/Diva Sunglasses + Monocle)',
        'ids'   => admin_filter_existing_item_ids([101,117,119]),
    ],
    'classic_hoodies' => [
        'group' => 'Classic bundles',
        'label' => 'Classic Hoodies Pack (Black/Pink/Green)',
        'ids'   => admin_filter_existing_item_ids([221,222,223]),
    ],
    'classic_free_hats' => [
        'group' => 'Classic bundles',
        'label' => 'Classic Free Hats Pack (Party + Santa + 1st/2nd Anniversary)',
        'ids'   => admin_filter_existing_item_ids([413,414,444,430]),
    ],
    'classic_free_headgear' => [
        'group' => 'Classic bundles',
        'label' => 'Classic Free Headgear Pack (Miners Helmet + Blue Propeller Cap)',
        'ids'   => admin_filter_existing_item_ids([429,431]),
    ],
    'music_jam_kit' => [
        'group' => 'Themed kits',
        'label' => 'Music Jam Kit (Headphones)',
        'ids'   => admin_filter_existing_item_ids([481,1164,1867]),
    ],
    'detective_kit' => [
        'group' => 'Themed kits',
        'label' => "Detective Kit (Detective's Coat + Monocle)",
        'ids'   => admin_filter_existing_item_ids([4012,119]),
    ],
    'worksite_kit' => [
        'group' => 'Themed kits',
        'label' => 'Worksite Kit (Miners Helmet + Red Hard Hat)',
        'ids'   => admin_filter_existing_item_ids([429,674]),
    ],
    'viking_helms' => [
        'group' => 'Themed kits',
        'label' => 'Viking Helmets Pack',
        'ids'   => admin_filter_existing_item_ids([452,456,460]),
    ],
];

/**
 * Parse a comma/space separated list of integers.
 * Supports ranges like "1-15".
 * Returns a unique, sorted list of positive ints.
 */
function admin_parse_item_id_list(string $raw): array {
    $raw = trim($raw);
    if ($raw === '') return [];

    // Split on commas / whitespace / newlines
    $parts = preg_split('/[\s,]+/', $raw, -1, PREG_SPLIT_NO_EMPTY);
    if (!$parts) return [];

    $out = [];
    $capTotal = 50000; // safety cap

    foreach ($parts as $p) {
        $p = trim($p);
        if ($p === '') continue;

        // Range support (e.g. 1-15)
        if (preg_match('/^(\d{1,9})\s*-\s*(\d{1,9})$/', $p, $m)) {
            $a = (int)$m[1];
            $b = (int)$m[2];
            if ($a <= 0 || $b <= 0) continue;

            if ($a > $b) { $tmp = $a; $a = $b; $b = $tmp; }
            $len = $b - $a + 1;
            if ($len > 20000) $len = 20000; // cap a single range

            for ($i = 0; $i < $len; $i++) {
                $val = $a + $i;
                $out[] = $val;
                if (count($out) >= $capTotal) break 2;
            }
            continue;
        }

        if (!ctype_digit($p)) continue;
        $val = (int)$p;
        if ($val <= 0) continue;
        $out[] = $val;

        if (count($out) >= $capTotal) break;
    }

    $out = array_values(array_unique($out));
    sort($out, SORT_NUMERIC);
    return $out;
}

/**
 * Bulk grant items into `inventories` using chunked INSERT IGNORE.
 * Returns number of *new* rows inserted (approx; duplicates are ignored).
 */
function admin_grant_items(int $userId, array $itemIds): int {
    $userId = (int)$userId;
    if ($userId <= 0) return 0;

    $ids = [];
    foreach ($itemIds as $id) {
        $id = (int)$id;
        if ($id > 0) $ids[] = $id;
    }
    if (!$ids) return 0;

    $ids = array_values(array_unique($ids));
    sort($ids, SORT_NUMERIC);

    $db = db();
    $inserted = 0;
    $chunkSize = 500;

    for ($offset = 0; $offset < count($ids); $offset += $chunkSize) {
        $chunk = array_slice($ids, $offset, $chunkSize);

        $vals = [];
        foreach ($chunk as $itemId) {
            $vals[] = '(' . $userId . ',' . (int)$itemId . ')';
        }

        $sql = 'INSERT IGNORE INTO inventories (userId, itemId) VALUES ' . implode(',', $vals);
        $db->query($sql);
        if ($db->errno) {
            // Stop early on SQL errors.
            break;
        }
        $inserted += (int)$db->affected_rows;
    }

    return $inserted;
}

/**
 * Attempt to discover all clothing item IDs.
 * Strategy:
 *  1) If a suitable DB table exists (items/clothing/etc), read IDs from it.
 *  2) Fallback: scan clothing icon directories for numeric PNG filenames.
 */
function admin_discover_all_clothing_item_ids(): array {
    $db = db();

    $tableCandidates = ['items', 'clothing', 'clothing_items', 'clothes', 'catalog_items', 'shop_items'];
    foreach ($tableCandidates as $t) {
        $tEsc = $db->real_escape_string($t);
        $res = $db->query("SHOW TABLES LIKE '{$tEsc}'");
        if (!$res || $res->num_rows <= 0) continue;

        $col = null;
        $colsRes = $db->query("SHOW COLUMNS FROM `{$t}`");
        if ($colsRes) {
            while ($c = $colsRes->fetch_assoc()) {
                $f = (string)($c['Field'] ?? '');
                if (in_array($f, ['id','itemId','item_id','clothingId','clothing_id'], true)) { $col = $f; break; }
            }
        }
        if (!$col) continue;

        $ids = [];
        $q = $db->query("SELECT `{$col}` AS id FROM `{$t}`");
        if ($q) {
            while ($row = $q->fetch_assoc()) {
                $v = (int)($row['id'] ?? 0);
                if ($v > 0) $ids[] = $v;
            }
        }
        $ids = array_values(array_unique($ids));
        sort($ids, SORT_NUMERIC);
        if ($ids) return $ids;
    }

    // Asset scan fallback (most reliable for Yukon deployments that store clothing as files)
    $dirs = [
        __DIR__ . '/../assets/media/clothing/icon/240',
        __DIR__ . '/../assets/media/clothing/icon/120',
        __DIR__ . '/../assets/media/clothing/icon',
    ];

    $ids = [];
    foreach ($dirs as $d) {
        $real = @realpath($d);
        if (!$real || !is_dir($real)) continue;

        foreach (glob(rtrim($real, '/\\') . DIRECTORY_SEPARATOR . '*.png') ?: [] as $file) {
            $base = pathinfo($file, PATHINFO_FILENAME);
            if (ctype_digit($base)) {
                $v = (int)$base;
                if ($v > 0) $ids[] = $v;
            }
        }
    }

    $ids = array_values(array_unique($ids));
    sort($ids, SORT_NUMERIC);
    return $ids;
}


// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();
    $action = (string)($_POST['action'] ?? '');

    // Re-check user exists at action time
    $target = admin_get_user($id);
    if (!$target) {
        flash_set('error', 'User not found.');
        header('Location: ' . admin_url('users.php'));
        exit;
    }

    if ($action === 'add_coins') {
        $amount = admin_int($_POST['amount'] ?? 0, 0);
        if ($amount <= 0) {
            flash_set('warning', 'Enter a positive coin amount.');
        } else {
            $stmt = db()->prepare('UPDATE users SET coins = coins + ? WHERE id = ? LIMIT 1');
            $stmt->bind_param('ii', $amount, $id);
            $stmt->execute();
            $stmt->close();
            flash_set('success', 'Added ' . number_format($amount) . ' coins to ' . $target['username'] . '.');
        }
        header('Location: ' . admin_url('user.php?id=' . $id));
        exit;
    }

    if ($action === 'give_item') {
        $itemId = admin_int($_POST['itemId'] ?? 0, 0);
        if ($itemId <= 0) {
            flash_set('warning', 'Enter a valid item id.');
        } else {
            // inventories has no quantity; treat as unique ownership.
            $stmt = db()->prepare('INSERT IGNORE INTO inventories (userId, itemId) VALUES (?, ?)');
            $stmt->bind_param('ii', $id, $itemId);
            $stmt->execute();
            $stmt->close();
            flash_set('success', 'Gave item ' . $itemId . ' to ' . $target['username'] . '.');
        }
        header('Location: ' . admin_url('user.php?id=' . $id));
        exit;
    }

    if ($action === 'give_preset') {
        $key = (string)($_POST['preset'] ?? '');
        if ($key === '' || !isset($givePresets[$key])) {
            flash_set('warning', 'Choose a valid preset.');
        } else {
            $ids = (array)($givePresets[$key]['ids'] ?? []);
            $inserted = admin_grant_items($id, $ids);
            $label = (string)($givePresets[$key]['label'] ?? $key);
            flash_set('success', 'Granted preset: ' . $label . ' (added ' . number_format($inserted) . ' new items) to ' . $target['username'] . '.');
        }
        header('Location: ' . admin_url('user.php?id=' . $id));
        exit;
    }

    if ($action === 'give_items_bulk') {
        $raw = (string)($_POST['itemIds'] ?? '');
        $ids = admin_parse_item_id_list($raw);
        if (!$ids) {
            flash_set('warning', 'Enter at least one valid item id (you can paste comma/space-separated IDs, and ranges like 1-15).');
        } else {
            $inserted = admin_grant_items($id, $ids);
            flash_set('success', 'Granted ' . number_format(count($ids)) . ' item IDs (added ' . number_format($inserted) . ' new items) to ' . $target['username'] . '.');
        }
        header('Location: ' . admin_url('user.php?id=' . $id));
        exit;
    }

    if ($action === 'give_all_items') {
        if (!admin_can_give_all_items($me)) {
            flash_set('error', 'Insufficient permissions for "Give all items" (requires rank ' . (int)ADMIN_GIVE_ALL_ITEMS_MIN_RANK . '+).');
            header('Location: ' . admin_url('user.php?id=' . $id));
            exit;
        }

        $phrase = trim((string)($_POST['confirmPhrase'] ?? ''));
        $userConfirm = trim((string)($_POST['confirmUser'] ?? ''));
        $expectedPhrase = 'GIVE ALL';

        if ($phrase !== $expectedPhrase || strcasecmp($userConfirm, (string)$target['username']) !== 0) {
            flash_set('warning', 'Confirmation failed. Type "' . $expectedPhrase . '" and the target username exactly.');
            header('Location: ' . admin_url('user.php?id=' . $id));
            exit;
        }

        $allIds = admin_discover_all_clothing_item_ids();
        if (!$allIds) {
            flash_set('error', 'Could not discover item IDs for a full grant. (No items table found, and no clothing icon directories detected.)');
            header('Location: ' . admin_url('user.php?id=' . $id));
            exit;
        }

        $inserted = admin_grant_items($id, $allIds);
        flash_set('success', 'Granted ALL discovered clothing items (' . number_format(count($allIds)) . ' candidates; added ' . number_format($inserted) . ' new items) to ' . $target['username'] . '.');
        header('Location: ' . admin_url('user.php?id=' . $id));
        exit;
    }

    if ($action === 'ban') {
        $reason = trim((string)($_POST['reason'] ?? ''));
        $durationHours = admin_int($_POST['durationHours'] ?? 24, 24);
        $permanent = !empty($_POST['permanent']);

        if ($reason === '') {
            $reason = 'No reason specified';
        }

        $modId = (int)($me['id'] ?? 0);

        if ($permanent) {
            $stmt = db()->prepare('UPDATE users SET permaBan = 1 WHERE id = ? LIMIT 1');
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $stmt->close();

            // Log a ban record for history (expires = NOW)
            $stmt = db()->prepare('INSERT INTO bans (userId, issued, expires, moderatorId, message) VALUES (?, NOW(), NOW(), ?, ?)');
            $stmt->bind_param('iis', $id, $modId, $reason);
            $stmt->execute();
            $stmt->close();

            flash_set('success', 'Permanently banned ' . $target['username'] . '.');
        } else {
            $durationHours = max(1, min(24 * 365, $durationHours));
            $stmt = db()->prepare('INSERT INTO bans (userId, issued, expires, moderatorId, message) VALUES (?, NOW(), DATE_ADD(NOW(), INTERVAL ? HOUR), ?, ?)');
            $stmt->bind_param('iiis', $id, $durationHours, $modId, $reason);
            $stmt->execute();
            $stmt->close();
            flash_set('success', 'Banned ' . $target['username'] . ' for ' . $durationHours . ' hours.');
        }

        header('Location: ' . admin_url('user.php?id=' . $id));
        exit;
    }

    if ($action === 'unban') {
        // Clear permanent + expire any active temp bans
        $stmt = db()->prepare('UPDATE users SET permaBan = 0 WHERE id = ? LIMIT 1');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->close();

        $stmt = db()->prepare('UPDATE bans SET expires = NOW() WHERE userId = ? AND expires > NOW()');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->close();

        flash_set('success', 'Unbanned ' . $target['username'] . '.');
        header('Location: ' . admin_url('user.php?id=' . $id));
        exit;
    }

    flash_set('error', 'Unknown action.');
    header('Location: ' . admin_url('user.php?id=' . $id));
    exit;
}

$user = admin_get_user($id);
if (!$user) {
    $title = 'User not found';
    include __DIR__ . '/includes/admin_header.php';
    echo '<section class="adm-card" style="padding:18px"><h1 class="adm-h1" style="margin:0">User not found</h1><p class="adm-muted" style="margin:8px 0 0">This account may have been deleted.</p><div class="adm-actions" style="margin-top:12px"><a class="adm-btn" href="' . e(admin_url('users.php')) . '">Back to user search</a></div></section>';
    include __DIR__ . '/includes/admin_footer.php';
    exit;
}

$title = $user['username'] . ' · User';

// Derived data
$isPerma = (int)($user['permaBan'] ?? 0) === 1;
$isTemp  = admin_user_temp_banned($id);

$invCount = 0;
$stmt = db()->prepare('SELECT COUNT(*) FROM inventories WHERE userId = ?');
$stmt->bind_param('i', $id);
$stmt->execute();
$stmt->bind_result($invCount);
$stmt->fetch();
$stmt->close();

$buddyCount = 0;
$stmt = db()->prepare('SELECT COUNT(*) FROM buddies WHERE userId = ?');
$stmt->bind_param('i', $id);
$stmt->execute();
$stmt->bind_result($buddyCount);
$stmt->fetch();
$stmt->close();

// Recent bans
$banRows = [];
$stmt = db()->prepare('SELECT b.id, b.issued, b.expires, b.message, b.moderatorId,
                               mu.username AS moderatorName
                        FROM bans b
                        LEFT JOIN users mu ON mu.id = b.moderatorId
                        WHERE b.userId = ?
                        ORDER BY b.issued DESC
                        LIMIT 12');
$stmt->bind_param('i', $id);
$stmt->execute();
$res = $stmt->get_result();
while ($r = $res->fetch_assoc()) $banRows[] = $r;
$stmt->close();

// Include playercard renderer
require_once __DIR__ . '/../includes/avatar_component.php';

include __DIR__ . '/includes/admin_header.php';
?>

<div style="display:flex; align-items:center; justify-content:space-between; gap:12px; flex-wrap:wrap">
  <div>
    <h1 class="adm-h1" style="margin:0"><?php echo e($user['username']); ?> <span class="adm-muted" style="font-weight:800">(ID <?php echo (int)$user['id']; ?>)</span></h1>
    <p class="adm-muted" style="margin:6px 0 0">
      <?php echo e(rank_title((int)$user['rank'])); ?> · Joined <?php echo e($user['joinTime']); ?>
      <?php if (!empty($user['email'])): ?> · <?php echo e($user['email']); ?><?php endif; ?>
    </p>
  </div>
  <div style="display:flex; gap:10px; align-items:center; flex-wrap:wrap">
    <?php
      if ($isPerma) echo admin_badge_html('Permanent Ban', 'bad');
      else if ($isTemp) echo admin_badge_html('Temp Ban Active', 'warn');
      else echo admin_badge_html('OK', 'good');
    ?>
    <a class="adm-btn adm-btn--ghost" href="<?php echo e(admin_url('users.php')); ?>">Back to search</a>
  </div>
</div>

<section class="adm-card" style="margin-top:14px; padding:18px">
  <div class="adm-split">
    <div>
      <h2 class="adm-h2" style="margin-top:0">Playercard</h2>
      <div>
        <?php render_penguin_avatar($user, 220); ?>
      </div>
      <div class="adm-muted" style="margin-top:10px">Photo: <?php echo (int)$user['photo']; ?> · Pin: <?php echo (int)$user['flag']; ?></div>
    </div>

    <div>
      <h2 class="adm-h2" style="margin-top:0">Overview</h2>
      <div class="adm-kv">
        <div class="adm-kvItem"><div class="adm-k">Coins</div><div class="adm-v"><?php echo number_format((int)$user['coins']); ?></div></div>
        <div class="adm-kvItem"><div class="adm-k">Items Owned</div><div class="adm-v"><?php echo number_format((int)$invCount); ?></div></div>
        <div class="adm-kvItem"><div class="adm-k">Buddies</div><div class="adm-v"><?php echo number_format((int)$buddyCount); ?></div></div>
        <div class="adm-kvItem"><div class="adm-k">Rank</div><div class="adm-v"><?php echo e(rank_title((int)$user['rank'])); ?> (<?php echo (int)$user['rank']; ?>)</div></div>
      </div>

      <div style="margin-top:16px">
        <h2 class="adm-h2">Manage</h2>
        <div class="adm-card" style="padding:14px; background: rgba(255,255,255,0.55)">
          <form class="adm-form" method="post">
            <input type="hidden" name="csrf" value="<?php echo e(csrf_token()); ?>">
            <input type="hidden" name="action" value="add_coins">
            <div class="adm-field" style="min-width:200px">
              <label class="adm-label">Add Coins</label>
              <input class="adm-input" type="number" min="1" name="amount" placeholder="e.g. 5000">
            </div>
            <button class="adm-btn" type="submit">Add</button>
          </form>
        </div>

        <div class="adm-card" style="padding:14px; background: rgba(255,255,255,0.55); margin-top:10px">
          <form class="adm-form" method="post">
            <input type="hidden" name="csrf" value="<?php echo e(csrf_token()); ?>">
            <input type="hidden" name="action" value="give_item">
            <div class="adm-field" style="min-width:200px">
              <label class="adm-label">Give Item (clothing)</label>
              <input class="adm-input" type="number" min="1" name="itemId" placeholder="Item ID (e.g. 800)">
            </div>
            <button class="adm-btn" type="submit">Give</button>
          </form>
          <div class="adm-muted" style="margin-top:8px">This writes to <code>inventories</code> (unique ownership, no quantity).</div>
        </div>
        <div class="adm-card" style="padding:14px; background: rgba(255,255,255,0.55); margin-top:10px">
          <form class="adm-form" method="post">
            <input type="hidden" name="csrf" value="<?php echo e(csrf_token()); ?>">
            <input type="hidden" name="action" value="give_preset">
            <div class="adm-field" style="min-width:320px">
              <label class="adm-label">Quick Grant Preset</label>
              <select class="adm-input" name="preset" required>
                <?php
                  $presetsByGroup = [];
                  foreach ($givePresets as $k => $p) {
                    $g = (string)($p['group'] ?? 'Presets');
                    if (!isset($presetsByGroup[$g])) $presetsByGroup[$g] = [];
                    $presetsByGroup[$g][$k] = $p;
                  }
                ?>
                <option value="">Choose a bundle…</option>
                <?php foreach ($presetsByGroup as $groupLabel => $items): ?>
                  <optgroup label="<?php echo e($groupLabel); ?>">
                    <?php foreach ($items as $k => $p): ?>
                      <option value="<?php echo e($k); ?>"><?php echo e((string)($p['label'] ?? $k)); ?></option>
                    <?php endforeach; ?>
                  </optgroup>
                <?php endforeach; ?>
              </select>
            </div>
            <button class="adm-btn" type="submit">Grant</button>
          </form>
          <div class="adm-muted" style="margin-top:8px">Adds all items in the preset to <code>inventories</code>. Existing items are ignored.</div>
        </div>

        <div class="adm-card" style="padding:14px; background: rgba(255,255,255,0.55); margin-top:10px">
          <form class="adm-form" method="post">
            <input type="hidden" name="csrf" value="<?php echo e(csrf_token()); ?>">
            <input type="hidden" name="action" value="give_items_bulk">
            <div class="adm-field" style="min-width:420px">
              <label class="adm-label">Bulk Grant (IDs / ranges)</label>
              <textarea class="adm-input" name="itemIds" rows="2" placeholder="e.g. 1-15, 221, 428, 800"></textarea>
            </div>
            <button class="adm-btn" type="submit">Grant</button>
          </form>
          <div class="adm-muted" style="margin-top:8px">Paste comma/space-separated IDs. Ranges like <code>1-15</code> are supported.</div>
        </div>

        <div class="adm-card" style="padding:14px; background: rgba(255,255,255,0.55); margin-top:10px">
          <?php if (admin_can_give_all_items($me)): ?>
            <form class="adm-form" method="post" id="giveAllItemsForm">
              <input type="hidden" name="csrf" value="<?php echo e(csrf_token()); ?>">
              <input type="hidden" name="action" value="give_all_items">

              <div class="adm-field" style="min-width:180px">
                <label class="adm-label">Confirm phrase</label>
                <input class="adm-input" name="confirmPhrase" placeholder="Type: GIVE ALL">
              </div>

              <div class="adm-field" style="min-width:220px">
                <label class="adm-label">Confirm username</label>
                <input class="adm-input" name="confirmUser" placeholder="<?php echo e($user['username']); ?>">
              </div>

              <button class="adm-btn" type="submit" style="background: rgba(180,35,24,0.92); border-color: rgba(180,35,24,0.92);">Give ALL clothing items</button>
            </form>
            <div class="adm-muted" style="margin-top:8px">
              <strong>Danger:</strong> Grants every discovered clothing ID (items table if present, otherwise scans <code>/assets/media/clothing/icon/*</code> for numeric PNGs).
              This can be large and is intended for dev/testing accounts only.
            </div>
          <?php else: ?>
            <div class="adm-muted">
              <strong>Give ALL clothing items</strong> is restricted to rank <?php echo (int)ADMIN_GIVE_ALL_ITEMS_MIN_RANK; ?>+.
            </div>
          <?php endif; ?>
        </div>


        <div class="adm-card" style="padding:14px; background: rgba(255,255,255,0.55); margin-top:10px">
          <form class="adm-form" method="post">
            <input type="hidden" name="csrf" value="<?php echo e(csrf_token()); ?>">
            <input type="hidden" name="action" value="ban">
            <div class="adm-field" style="min-width:260px">
              <label class="adm-label">Reason</label>
              <input class="adm-input" name="reason" placeholder="Swearing/Profanity">
            </div>
            <div class="adm-field" style="min-width:180px">
              <label class="adm-label">Duration (hours)</label>
              <input class="adm-input" type="number" min="1" max="8760" name="durationHours" value="24">
            </div>
            <div class="adm-field" style="min-width:180px">
              <label class="adm-label">Permanent</label>
              <label style="display:flex; gap:8px; align-items:center; height:42px; padding:0 10px; border-radius:12px; border:1px solid rgba(188,211,228,0.9); background:rgba(255,255,255,0.85)">
                <input type="checkbox" name="permanent" value="1"> <span class="adm-muted" style="font-weight:800">Perma-ban</span>
              </label>
            </div>
            <button class="adm-btn" type="submit">Ban</button>
          </form>

          <form method="post" style="margin-top:10px">
            <input type="hidden" name="csrf" value="<?php echo e(csrf_token()); ?>">
            <input type="hidden" name="action" value="unban">
            <button class="adm-btn adm-btn--ghost" type="submit" onclick="return confirm('Unban this user (clears perma + expires temp bans)?');">Unban</button>
          </form>
        </div>
      </div>
    </div>
  </div>
</section>

<section class="adm-card" style="margin-top:14px">
  <div class="adm-card__head">
    <h2 class="adm-h2">Ban History</h2>
    <a class="adm-btn adm-btn--ghost" href="<?php echo e(admin_url('bans.php?userId=' . (int)$id)); ?>">Open in Ban Management</a>
  </div>
  <div class="adm-tableWrap">
    <table class="adm-table" style="min-width: 760px">
      <thead>
        <tr>
          <th>Issued</th>
          <th>Expires</th>
          <th>Moderator</th>
          <th>Reason</th>
          <th>Status</th>
        </tr>
      </thead>
      <tbody>
      <?php if (!$banRows): ?>
        <tr><td colspan="5" class="adm-muted">No bans recorded.</td></tr>
      <?php else: ?>
        <?php foreach ($banRows as $b): ?>
          <?php
            $expires = (string)($b['expires'] ?? '');
            $isActive = $expires !== '' && strtotime($expires) > time();
            $status = $isActive ? admin_badge_html('Active', 'warn') : admin_badge_html('Expired', 'neutral');
          ?>
          <tr>
            <td class="adm-muted"><?php echo e($b['issued'] ?? ''); ?></td>
            <td class="adm-muted"><?php echo e($expires); ?></td>
            <td><strong><?php echo e($b['moderatorName'] ?? ('ID ' . (int)$b['moderatorId'])); ?></strong></td>
            <td><?php echo e($b['message'] ?? ''); ?></td>
            <td><?php echo $status; ?></td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</section>

<script>
(function(){
  var form = document.getElementById('giveAllItemsForm');
  if (!form) return;

  form.addEventListener('submit', function(e){
    var phrase = (form.querySelector('input[name="confirmPhrase"]') || {}).value || '';
    var user   = (form.querySelector('input[name="confirmUser"]') || {}).value || '';

    phrase = phrase.trim();
    user = user.trim();

    var expectedPhrase = 'GIVE ALL';
    var expectedUser = <?php echo json_encode((string)$user['username']); ?>;

    if (phrase !== expectedPhrase || user.toLowerCase() !== String(expectedUser).toLowerCase()) {
      e.preventDefault();
      alert('Confirmation failed.\n\nType: ' + expectedPhrase + '\nAnd the username: ' + expectedUser);
      return;
    }

    if (!confirm('This will grant ALL discovered clothing items to ' + expectedUser + '.\n\nContinue?')) {
      e.preventDefault();
      return;
    }
  });
})();
</script>

<?php include __DIR__ . '/includes/admin_footer.php'; ?>
