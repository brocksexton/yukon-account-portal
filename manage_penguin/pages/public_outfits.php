<?php
require_once __DIR__ . '/../includes/manage_header.php';
require_once __DIR__ . '/../includes/studio_lib.php';

// Fetch public outfits
$stmt = db()->prepare("SELECT o.*, u.username FROM user_outfits o JOIN users u ON u.id=o.userId WHERE o.isPublic=1 ORDER BY o.created_at DESC");
$stmt->execute();
$res = $stmt->get_result();
$outfits = [];
while ($row = $res->fetch_assoc()) {
  $outfits[] = $row;
}
$stmt->close();

// Cache owned inventory
// Determine the current user ID from the session. Not all environments define
// current_user_id(), so fall back to current_user() if available. If neither
// function is defined, assume no items are owned.
if (function_exists('current_user')) {
    $cu = current_user();
    $uid = isset($cu['id']) ? (int)$cu['id'] : 0;
    $owned = mp_owned_set($uid);
} elseif (function_exists('current_user_id')) {
    $owned = mp_owned_set((int)current_user_id());
} else {
    $owned = mp_owned_set(0);
}

// Load items data for costs
function mp_items_map(): array {
  static $items = null;
  if ($items !== null) return $items;

  $paths = [
    __DIR__ . '/../../yukon/assets/media/crumbs/en/items.json',
    __DIR__ . '/../data/items.json',
  ];
  foreach ($paths as $p) {
    if (is_file($p)) {
      $raw = file_get_contents($p);
      $decoded = json_decode($raw, true);
      if (is_array($decoded)) { $items = $decoded; return $items; }
    }
  }
  $items = [];
  return $items;
}

function mp_item_cost_from_map(int $itemId, array $itemsMap): int {
  $key = (string)$itemId;
  if (!isset($itemsMap[$key])) return 0;
  $cost = $itemsMap[$key]['cost'] ?? 0;
  return (int)$cost;
}

$itemsMap = mp_items_map();
?>

<link rel="stylesheet" href="<?php echo $h('manage_penguin/css/outfit_store.css'); ?>">

<div class="mp-store">
  <div class="mp-store-header">
    <div>
      <h1 class="mp-store-title">Public Outfits</h1>
      <p class="mp-store-subtitle">Explore outfits shared by other players. Preview each look, then save it to your library—or buy only the items you’re missing.</p>
    </div>
    <div>
      <a class="mp-btn-ghost" href="<?php echo $h('manage_penguin/index.php?tab=my_outfits'); ?>">Back to My Outfits</a>
    </div>
  </div>

  <div class="mp-store-grid">
    <?php if (empty($outfits)): ?>
      <div class="card" style="grid-column:1/-1;">
        <h3 style="margin:0 0 6px;color:#0b2b45;">No public outfits yet</h3>
        <p style="margin:0;color:#4f768f;">Be the first to publish an outfit from the Studio.</p>
      </div>
    <?php endif; ?>

    <?php foreach ($outfits as $o):
      // Build a list of item IDs in this outfit. Exclude the color slot
      // because colors are not purchased or stored in inventories.
      $outfitItems = [];
      foreach (['head','face','neck','body','hand','feet','photo','flag'] as $slotKey) {
        $iid = isset($o[$slotKey]) ? (int)$o[$slotKey] : 0;
        if ($iid > 0) $outfitItems[] = $iid;
      }

      // Missing items
      $missing = array_values(array_diff($outfitItems, $owned));
      $missingCount = count($missing);

      $missingCost = 0;
      foreach ($missing as $iid) {
        $missingCost += mp_item_cost_from_map((int)$iid, $itemsMap);
      }

      $allOwned = ($missingCount === 0);
      $ctaLabel = $allOwned ? 'Save for Free' : ('Buy ' . $missingCount . ' Item' . ($missingCount===1?'':'s') . ' & Save');
      $ctaPill  = $allOwned ? '0 coins' : (number_format($missingCost) . ' coins');

      $thumb = '/yukon/assets/media/clothing/icon/240/0.png';
      if (!empty($o['thumbnailPath'])) {
        $thumb = $o['thumbnailPath'];
      }
      $author = $o['username'] ?? 'Unknown';
      $created = !empty($o['created_at']) ? date('n/j/Y', strtotime($o['created_at'])) : '';
    ?>
      <div class="mp-outfit-card">
        <div class="mp-outfit-thumb">
          <img src="<?php echo htmlspecialchars($thumb); ?>" alt="Outfit thumbnail">
        </div>
        <div class="mp-outfit-meta">
          <h3><?php echo htmlspecialchars($o['name'] ?: 'Untitled Outfit'); ?></h3>
          <p><?php echo htmlspecialchars($o['description'] ?: ''); ?></p>

          <div class="mp-outfit-badges">
            <span class="mp-pill"><?php echo (int)($o['likes'] ?? 0); ?> Likes</span>
            <?php if ($allOwned): ?>
              <span class="mp-pill owned">You own everything</span>
            <?php else: ?>
              <span class="mp-pill missing">Missing: <?php echo $missingCount; ?></span>
            <?php endif; ?>
            <?php if ($created): ?>
              <span class="mp-pill">Created: <?php echo htmlspecialchars($created); ?></span>
            <?php endif; ?>
          </div>

          <div style="margin-top:10px;display:flex;align-items:center;gap:10px;">
            <div style="display:flex;align-items:center;gap:10px;">
              <img src="<?php echo mp_profile_picture_url((int)$o['userId']); ?>" alt="" style="width:42px;height:42px;border-radius:50%;border:2px solid rgba(188,211,228,.9);">
              <div style="line-height:1.2;">
                <div style="font-weight:900;color:#0b2b45;"><?php echo htmlspecialchars($author); ?></div>
                <div style="font-size:12px;color:#4f768f;">Share code: <?php echo htmlspecialchars($o['shareCode']); ?></div>
              </div>
            </div>

            <div style="margin-left:auto;display:flex;align-items:center;gap:10px;">
              <span class="mp-pill">
                <img src="<?php echo $h('manage_penguin/assets/coin.png'); ?>" alt="" style="width:16px;height:16px;">
                <?php echo htmlspecialchars($ctaPill); ?>
              </span>
              <a class="mp-store-cta" href="/manage_penguin/pages/buy_outfit.php?shareCode=<?php echo urlencode($o['shareCode']); ?>">
                <?php echo htmlspecialchars($ctaLabel); ?>
              </a>
            </div>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/manage_footer.php'; ?>
