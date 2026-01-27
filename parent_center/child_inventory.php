<?php
require_once __DIR__ . '/includes/child_context.php';
parent_require_login();

$parent = parent_current();
$parentId = (int)$parent['id'];

$uid = (int)($_GET['uid'] ?? 0);
$child = pc_require_child($parentId, $uid);

$q = trim((string)($_GET['q'] ?? ''));
$page = max(1, (int)($_GET['page'] ?? 1));
$per = 144; // 12x12-ish grid
$offset = ($page - 1) * $per;

$where = 'userId = ?';
$types = 'i';
$params = [$uid];

if ($q !== '' && ctype_digit($q)) {
  $where .= ' AND itemId = ?';
  $types .= 'i';
  $params[] = (int)$q;
}

// total
$sqlCount = 'SELECT COUNT(*) AS c FROM inventories WHERE ' . $where;
$stmt = db()->prepare($sqlCount);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();
$total = (int)($row['c'] ?? 0);

// page data
$sql = 'SELECT itemId FROM inventories WHERE ' . $where . ' ORDER BY itemId ASC LIMIT ? OFFSET ?';
$stmt = db()->prepare($sql);
$types2 = $types . 'ii';
$params2 = array_merge($params, [$per, $offset]);
$stmt->bind_param($types2, ...$params2);
$stmt->execute();
$items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$pages = max(1, (int)ceil($total / $per));
if ($page > $pages) $page = $pages;

$pageTitle = 'Parent Center - Inventory - ' . ($child['username'] ?? 'Child');
include __DIR__ . '/includes/parent_header.php';
?>

<div class="pc-shell">
  <div class="pc-card">

    <div class="pc-pageTitleRow">
      <div>
        <h1 class="pc-pageTitle">Inventory</h1>
        <div class="pc-pageSubtitle"><?php echo e($child['username']); ?> • <?php echo number_format($total); ?> item(s)</div>
      </div>

      <div class="pc-toolbar">
        <a class="pc-btnSm" href="dashboard.php">Back</a>
        <a class="pc-btnSm" href="child.php?uid=<?php echo (int)$uid; ?>">Overview</a>
      </div>
    </div>

    <?php echo pc_child_nav($uid, 'inventory'); ?>

    <div class="pc-tableWrap" style="padding:14px;">
      <form method="get" class="pc-toolbar" style="justify-content:space-between;">
        <input type="hidden" name="uid" value="<?php echo (int)$uid; ?>">
        <div style="display:flex; gap:8px; flex-wrap:wrap; align-items:center;">
          <input class="pc-searchInput" name="q" value="<?php echo e($q); ?>" placeholder="Search itemId (exact)" inputmode="numeric" pattern="[0-9]*" aria-label="Search inventory by itemId">
          <button class="pc-btnSm" type="submit">Search</button>
          <?php if ($q !== ''): ?>
            <a class="pc-btnSm" href="child_inventory.php?uid=<?php echo (int)$uid; ?>">Clear</a>
          <?php endif; ?>
        </div>
        <div class="pc-muted" style="font-weight:900;">Page <?php echo (int)$page; ?> / <?php echo (int)$pages; ?></div>
      </form>

      <?php if (!$items): ?>
        <div class="pc-empty" style="margin-top:12px;">No items found.</div>
      <?php else: ?>
        <div class="pc-invGrid" style="margin-top:12px;">
          <?php foreach ($items as $it): $itemId = (int)$it['itemId']; ?>
            <div class="pc-invItem">
              <img alt="" src="<?php echo e(pc_clothing_icon_url($itemId, 120)); ?>" loading="lazy">
              <div class="pc-invId"><?php echo $itemId; ?></div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>

    <?php
      $base = 'child_inventory.php?uid=' . (int)$uid;
      if ($q !== '') $base .= '&q=' . urlencode($q);
      $prev = max(1, $page - 1);
      $next = min($pages, $page + 1);
    ?>
    <div class="pc-pager">
      <a class="pc-btnSm" href="<?php echo e($base . '&page=' . $prev); ?>" aria-label="Previous page" <?php echo $page <= 1 ? 'style="pointer-events:none; opacity:.55;"' : ''; ?>>Prev</a>
      <a class="pc-btnSm" href="<?php echo e($base . '&page=' . $next); ?>" aria-label="Next page" <?php echo $page >= $pages ? 'style="pointer-events:none; opacity:.55;"' : ''; ?>>Next</a>
    </div>

  </div>
</div>

<?php require_once __DIR__ . '/includes/parent_footer.php'; ?>
