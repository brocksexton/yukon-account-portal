<?php
require_once __DIR__ . '/includes/child_context.php';
parent_require_login();

$parent = parent_current();
$parentId = (int)$parent['id'];

$uid = (int)($_GET['uid'] ?? 0);
$child = pc_require_child($parentId, $uid);

$page = max(1, (int)($_GET['page'] ?? 1));
$per = 12;
$offset = ($page - 1) * $per;

$stmt = db()->prepare('SELECT COUNT(*) AS c FROM pets WHERE userId = ?');
$stmt->bind_param('i', $uid);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();
$total = (int)($row['c'] ?? 0);

$stmt = db()->prepare('SELECT id, typeId, name, adoptionDate, energy, health, rest FROM pets WHERE userId = ? ORDER BY adoptionDate DESC LIMIT ? OFFSET ?');
$stmt->bind_param('iii', $uid, $per, $offset);
$stmt->execute();
$pets = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$pages = max(1, (int)ceil($total / $per));
if ($page > $pages) $page = $pages;

function pc_puffle_asset_name(int $typeId): string {
  return match ($typeId) {
    0 => 'blue', 1 => 'pink', 2 => 'black', 3 => 'green', 4 => 'purple', 5 => 'red', 6 => 'yellow', 7 => 'white', 8 => 'orange', default => 'blue',
  };
}
function pc_puffle_label(int $typeId): string {
  return match ($typeId) {
    0 => 'Blue', 1 => 'Pink', 2 => 'Black', 3 => 'Green', 4 => 'Purple', 5 => 'Red', 6 => 'Yellow', 7 => 'White', 8 => 'Orange', default => 'Puffle',
  };
}

$pageTitle = 'Parent Center - Puffles - ' . ($child['username'] ?? 'Child');
include __DIR__ . '/includes/parent_header.php';
?>

<div class="pc-shell">
  <div class="pc-card">

    <div class="pc-pageTitleRow">
      <div>
        <h1 class="pc-pageTitle">Puffles</h1>
        <div class="pc-pageSubtitle"><?php echo e($child['username']); ?> • Total: <?php echo number_format($total); ?></div>
      </div>
      <div class="pc-toolbar">
        <a class="pc-btnSm" href="dashboard.php">Back</a>
        <a class="pc-btnSm" href="child.php?uid=<?php echo (int)$uid; ?>">Overview</a>
      </div>
    </div>

    <?php echo pc_child_nav($uid, 'puffles'); ?>

    <div class="pc-tableWrap" style="padding:14px;">
      <?php if (!$pets): ?>
        <div class="pc-empty">No puffles found.</div>
      <?php else: ?>
        <div class="pc-puffleGrid">
          <?php foreach ($pets as $p):
            $tid = (int)($p['typeId'] ?? 0);
            $assetName = pc_puffle_asset_name($tid);
            $assetLabel = pc_puffle_label($tid);
            $img = 'assets/puffles/' . $assetName . '.png';
            $adopted = parent_human_datetime($p['adoptionDate']) ?: (string)($p['adoptionDate'] ?? '');
            $stats = 'Energy ' . (int)$p['energy'] . ' • Health ' . (int)$p['health'] . ' • Rest ' . (int)$p['rest'];
          ?>
            <div class="pc-puffleCard" title="<?php echo e($assetLabel); ?> Puffle">
              <div class="pc-puffleImage" style="background-image:url('<?php echo e($img); ?>');">
                <div class="pc-puffleName" title="<?php echo e($p['name']); ?>"><?php echo e($p['name']); ?></div>
              </div>
              <div class="pc-puffleMeta">
                <div class="pc-puffleAdopted">Adopted: <span><?php echo e($adopted); ?></span></div>
                <div class="pc-puffleStats"><?php echo e($stats); ?></div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>

    <?php
      $prev = max(1, $page - 1);
      $next = min($pages, $page + 1);
      $base = 'child_puffles.php?uid=' . (int)$uid;
    ?>
    <div class="pc-pager">
      <a class="pc-btnSm" href="<?php echo e($base . '&page=' . $prev); ?>" <?php echo $page <= 1 ? 'style="pointer-events:none; opacity:.55;"' : ''; ?>>Prev</a>
      <div class="pc-muted" style="font-weight:900; align-self:center;">Page <?php echo (int)$page; ?> / <?php echo (int)$pages; ?></div>
      <a class="pc-btnSm" href="<?php echo e($base . '&page=' . $next); ?>" <?php echo $page >= $pages ? 'style="pointer-events:none; opacity:.55;"' : ''; ?>>Next</a>
    </div>

  </div>
</div>

<?php require_once __DIR__ . '/includes/parent_footer.php'; ?>
