<?php
// manage_penguin/api/outfit_purchase.php
// Quote and purchase public outfits. Purchasing buys missing items (using coins)
// and saves a private copy of the outfit to the buyer's library.

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../includes/studio_lib.php';

header('Content-Type: application/json; charset=utf-8');

require_login();
$user = current_user();
$userId = (int)$user['id'];

function ensure_outfit_table(){
  static $checked = false;
  if ($checked) return;
  $checked = true;
  $db = db();
  $db->query("CREATE TABLE IF NOT EXISTS user_outfits (
    id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
    userId INT(11) NOT NULL,
    shareCode VARCHAR(16) NOT NULL,
    sourceShareCode VARCHAR(16) DEFAULT NULL,
    name VARCHAR(50) NOT NULL,
    description VARCHAR(255) NOT NULL DEFAULT '',
    isPublic TINYINT(1) NOT NULL DEFAULT 0,
    likes INT(11) NOT NULL DEFAULT 0,
    color INT(11) NOT NULL DEFAULT 1,
    head INT(11) NOT NULL DEFAULT 0,
    face INT(11) NOT NULL DEFAULT 0,
    neck INT(11) NOT NULL DEFAULT 0,
    body INT(11) NOT NULL DEFAULT 0,
    hand INT(11) NOT NULL DEFAULT 0,
    feet INT(11) NOT NULL DEFAULT 0,
    photo INT(11) NOT NULL DEFAULT 0,
    flag INT(11) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_shareCode (shareCode),
    KEY idx_userId (userId)
  ) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci");
}

function gen_share_code($len = 8){
  $db = db();
  for ($i=0; $i<10; $i++){
    $code = bin2hex(random_bytes((int)ceil($len/2)));
    $code = substr($code, 0, $len);
    $stmt = $db->prepare('SELECT id FROM user_outfits WHERE shareCode=? LIMIT 1');
    $stmt->bind_param('s', $code);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res && $res->num_rows === 0){
      return $code;
    }
  }
  return bin2hex(random_bytes(4));
}

function load_items_map(){
  static $cache = null;
  if ($cache !== null) return $cache;

  $paths = [
    __DIR__ . '/../../yukon/assets/media/crumbs/en/items.json',
    __DIR__ . '/../assets/crumbs/en/items.json',
  ];
  foreach ($paths as $p){
    if (is_file($p)){
      $raw = file_get_contents($p);
      $decoded = json_decode($raw, true);
      if (is_array($decoded)){
        $cache = $decoded;
        return $cache;
      }
    }
  }
  $cache = [];
  return $cache;
}

function get_user_coins($userId){
  $db = db();
  $stmt = $db->prepare('SELECT coins FROM users WHERE id=? LIMIT 1');
  $stmt->bind_param('i', $userId);
  $stmt->execute();
  $row = $stmt->get_result()->fetch_assoc();
  return (int)($row['coins'] ?? 0);
}

function get_owned_items($userId){
  // Uses the inventories table (comma-separated items) as used elsewhere in Manage Penguin.
  $db = db();
  $stmt = $db->prepare('SELECT items FROM inventories WHERE user_id=? LIMIT 1');
  $stmt->bind_param('i', $userId);
  $stmt->execute();
  $row = $stmt->get_result()->fetch_assoc();
  $itemsStr = $row['items'] ?? '';
  $owned = [];
  foreach (explode(',', $itemsStr) as $chunk){
    $chunk = trim($chunk);
    if ($chunk === '') continue;
    $id = (int)$chunk;
    if ($id > 0) $owned[$id] = true;
  }
  return $owned;
}

function upsert_inventory_add($userId, $newItemIds){
  $db = db();
  $owned = get_owned_items($userId);
  foreach ($newItemIds as $id){
    $id = (int)$id;
    if ($id > 0) $owned[$id] = true;
  }
  $items = implode(',', array_keys($owned));

  // Try update first
  $stmt = $db->prepare('UPDATE inventories SET items=? WHERE user_id=?');
  $stmt->bind_param('si', $items, $userId);
  $stmt->execute();
  if ($stmt->affected_rows === 0){
    // Ensure row exists
    $stmt2 = $db->prepare('INSERT INTO inventories (user_id, items) VALUES (?, ?)');
    $stmt2->bind_param('is', $userId, $items);
    @$stmt2->execute();
  }
}

function fetch_public_outfit($shareCode){
  $db = db();
  $stmt = $db->prepare('SELECT o.*, u.username FROM user_outfits o JOIN users u ON u.id=o.userId WHERE o.isPublic=1 AND o.shareCode=? LIMIT 1');
  $stmt->bind_param('s', $shareCode);
  $stmt->execute();
  $row = $stmt->get_result()->fetch_assoc();
  return $row ?: null;
}

function build_quote($userId, $outfit){
  $itemsMap = load_items_map();
  $ownedSet = get_owned_items($userId);

  $slots = [
    'head' => 'Head',
    'face' => 'Face',
    'neck' => 'Neck',
    'body' => 'Body',
    'hand' => 'Hand',
    'feet' => 'Feet',
    'photo' => 'Photo',
    'flag' => 'Flag',
  ];

  $items = [];
  $missing = [];
  $totalValue = 0;
  $totalCharged = 0;

  foreach ($slots as $key => $label){
    $id = (int)($outfit[$key] ?? 0);
    if ($id <= 0) continue;

    $meta = $itemsMap[(string)$id] ?? null;
    $name = is_array($meta) && isset($meta['name']) ? $meta['name'] : ('Item #' . $id);
    $cost = is_array($meta) && isset($meta['cost']) ? (int)$meta['cost'] : 0;

    $isOwned = isset($ownedSet[$id]);
    $charged = $isOwned ? 0 : $cost;

    $items[] = [
      'slot' => $key,
      'slotLabel' => $label,
      'id' => $id,
      'name' => $name,
      'cost' => $cost,
      'charged' => $charged,
      'owned' => $isOwned,
      'icon' => "/yukon/assets/media/clothing/icon/240/{$id}.png",
    ];

    $totalValue += $cost;
    $totalCharged += $charged;
    if (!$isOwned) $missing[] = $id;
  }

  $coins = get_user_coins($userId);
  $canAfford = ($coins >= $totalCharged);

  return [
    'items' => $items,
    'missingItems' => $missing,
    'totalValue' => $totalValue,
    'totalCharged' => $totalCharged,
    'coins' => $coins,
    'canAfford' => $canAfford,
  ];
}

$action = $_POST['action'] ?? 'quote';
$shareCode = $_POST['shareCode'] ?? ($_GET['shareCode'] ?? '');
$shareCode = preg_replace('/[^a-f0-9]/i', '', (string)$shareCode);

if ($shareCode === ''){
  http_response_code(400);
  echo json_encode(['ok'=>false,'error'=>'Missing shareCode']);
  exit;
}

$outfit = fetch_public_outfit($shareCode);
if (!$outfit){
  http_response_code(404);
  echo json_encode(['ok'=>false,'error'=>'Outfit not found']);
  exit;
}

$quote = build_quote($userId, $outfit);

if ($action === 'quote'){
  echo json_encode([
    'ok' => true,
    'outfit' => [
      'shareCode' => $outfit['shareCode'],
      'name' => $outfit['name'],
      'description' => $outfit['description'] ?? '',
      'author' => $outfit['username'] ?? 'Unknown',
      'preview' => "/manage_penguin/assets/outfit_previews/{$outfit['shareCode']}.png",
    ],
    'quote' => $quote,
  ]);
  exit;
}

if ($action !== 'purchase_and_save'){
  http_response_code(400);
  echo json_encode(['ok'=>false,'error'=>'Unsupported action']);
  exit;
}

// Purchase requires CSRF.
csrf_validate();

$db = db();
$needsCharge = ((int)$quote['totalCharged'] > 0);
if ($needsCharge && !$quote['canAfford']){
  http_response_code(400);
  echo json_encode(['ok'=>false,'error'=>'Not enough coins']);
  exit;
}

ensure_outfit_table();

try {
  $db->begin_transaction();

  // Deduct coins if needed
  if ($needsCharge){
    $charge = (int)$quote['totalCharged'];
    $stmt = $db->prepare('UPDATE users SET coins = coins - ? WHERE id = ? AND coins >= ?');
    $stmt->bind_param('iii', $charge, $userId, $charge);
    $stmt->execute();
    if ($stmt->affected_rows < 1){
      throw new Exception('Unable to deduct coins');
    }
  }

  // Grant missing items
  if (!empty($quote['missingItems'])){
    upsert_inventory_add($userId, $quote['missingItems']);
  }

  // Save a private copy into the buyer's library
  $newShare = gen_share_code(8);
  $name = $outfit['name'] ?: 'Saved Outfit';
  $name = mb_substr($name, 0, 50);

  $stmt = $db->prepare('INSERT INTO user_outfits (userId, shareCode, sourceShareCode, name, description, isPublic, likes, color, head, face, neck, body, hand, feet, photo, flag)
                        VALUES (?, ?, ?, ?, ?, 0, 0, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
  $source = $outfit['shareCode'];
  $desc = '';
  $color = (int)($outfit['color'] ?? 1);
  $head = (int)($outfit['head'] ?? 0);
  $face = (int)($outfit['face'] ?? 0);
  $neck = (int)($outfit['neck'] ?? 0);
  $body = (int)($outfit['body'] ?? 0);
  $hand = (int)($outfit['hand'] ?? 0);
  $feet = (int)($outfit['feet'] ?? 0);
  $photo = (int)($outfit['photo'] ?? 0);
  $flag = (int)($outfit['flag'] ?? 0);
  $stmt->bind_param('issssiiiiiiiiiii', $userId, $newShare, $source, $name, $desc, $color, $head, $face, $neck, $body, $hand, $feet, $photo, $flag);
  $stmt->execute();
  $newId = (int)$stmt->insert_id;

  $db->commit();

  $newCoins = get_user_coins($userId);
  echo json_encode([
    'ok' => true,
    'savedOutfitId' => $newId,
    'savedShareCode' => $newShare,
    'charged' => (int)$quote['totalCharged'],
    'coins' => $newCoins,
  ]);
} catch (Throwable $e){
  if ($db->errno) {
    // ignore
  }
  @$db->rollback();
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>'Purchase failed','detail'=>$e->getMessage()]);
}
