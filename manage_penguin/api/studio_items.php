<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_login();

require_once __DIR__ . '/../includes/meta.php';

header('Content-Type: application/json; charset=utf-8');

$user = current_user();
$uid = (int)$user['id'];

$slot = strtolower(trim((string)($_GET['slot'] ?? 'all')));
$q    = trim((string)($_GET['q'] ?? ''));
$page = max(1, (int)($_GET['page'] ?? 1));
$per  = (int)($_GET['per'] ?? 36);
$per  = min(72, max(12, $per));

// slot aliases
$allowedSlots = ['all','color','head','face','neck','body','hand','feet','photo','flag','pin'];
if (!in_array($slot, $allowedSlots, true)) $slot = 'all';

// Load owned items (wearables + colors) from inventories table
$db = db();
$stmt = $db->prepare('SELECT items FROM inventories WHERE user_id = ? LIMIT 1');
$stmt->bind_param('i', $uid);
$stmt->execute();
$res = $stmt->get_result();
$row = $res ? $res->fetch_assoc() : null;
$ownedIds = [];
if ($row && isset($row['items'])) {
  $ownedIds = array_values(array_filter(array_map('intval', preg_split('/\s*,\s*/', (string)$row['items']))));
}

// Always include colors (they are not stored in inventories on some schemas)
$colors = [1,2,3,4,5,6,7,8,9,10,11,12,13,14,15,16];
foreach ($colors as $cid) { $ownedIds[] = $cid; }
$ownedIds = array_values(array_unique($ownedIds));

// Filter / map
$items = [];
$qlow = mb_strtolower($q);
foreach ($ownedIds as $id) {
  $id = (int)$id;

  $meta = item_meta($id);
  if (!$meta) {
    // if no meta, we can still show clothing icon fallback (some servers keep custom items outside crumbs)
    $meta = ['id' => $id, 'name' => 'Item #' . $id];
  }

  $itemSlot = item_slot_of($id) ?? 'unknown';
  if ($slot !== 'all') {
    // allow 'pin' as alias of 'flag' sometimes; allow 'flag'
    if ($slot === 'pin') {
      if (!in_array($itemSlot, ['pin','flag'], true)) continue;
    } else {
      if ($itemSlot !== $slot) continue;
    }
  }

  if ($q !== '') {
    $name = (string)($meta['name'] ?? '');
    $hay = mb_strtolower($name . ' #' . $id);
    if (mb_strpos($hay, $qlow) === false) continue;
  }

  $items[] = [
    'id' => $id,
    'name' => (string)($meta['name'] ?? ('Item #' . $id)),
    'slot' => $itemSlot,
    'icon' => clothing_icon_url($id),
  ];
}

// Stable ordering: colors first, then id
usort($items, function($a, $b){
  $sa = $a['slot'] === 'color' ? 0 : 1;
  $sb = $b['slot'] === 'color' ? 0 : 1;
  if ($sa !== $sb) return $sa <=> $sb;
  return $a['id'] <=> $b['id'];
});

$total = count($items);
$offset = ($page - 1) * $per;
$pageItems = array_slice($items, $offset, $per);

echo json_encode([
  'ok' => true,
  'slot' => $slot,
  'q' => $q,
  'page' => $page,
  'per' => $per,
  'total' => $total,
  'items' => $pageItems,
], JSON_UNESCAPED_SLASHES);
