<?php
require_once __DIR__ . '/includes/child_context.php';
parent_require_login();

$parent = parent_current();
$parentId = (int)$parent['id'];

$uid = (int)($_GET['uid'] ?? 0);
$child = pc_require_child($parentId, $uid);

$page = max(1, (int)($_GET['page'] ?? 1));
$per = 25;
$offset = ($page - 1) * $per;

// Total bans
$stmt = db()->prepare('SELECT COUNT(*) AS c FROM bans WHERE userId = ?');
$stmt->bind_param('i', $uid);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();
$total = (int)($row['c'] ?? 0);

// Bans page
$stmt = db()->prepare('SELECT id, issued, expires, moderatorId, message FROM bans WHERE userId = ? ORDER BY issued DESC LIMIT ? OFFSET ?');
$stmt->bind_param('iii', $uid, $per, $offset);
$stmt->execute();
$bans = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$pages = max(1, (int)ceil($total / $per));
if ($page > $pages) $page = $pages;

// Current ban detection
$now = time();
$isPerma = ((int)($child['permaBan'] ?? 0) === 1);
$currentBan = null;
if (!$isPerma && $bans) {
  foreach ($bans as $b) {
    $expiresTs = parent_parse_time($b['expires'] ?? null);
    if ($expiresTs !== null && $expiresTs >= $now) {
      $currentBan = $b;
      break;
    }
  }
}
$isCurrentlyBanned = $isPerma || ($currentBan !== null);

$pageTitle = 'Parent Center - Bans - ' . ($child['username'] ?? 'Child');
include __DIR__ . '/includes/parent_header.php';
?>

<div class="pc-shell">
  <div class="pc-card">

    <div class="pc-pageTitleRow">
      <div>
        <h1 class="pc-pageTitle">Bans</h1>
        <div class="pc-pageSubtitle"><?php echo e($child['username']); ?> • Total: <?php echo number_format($total); ?></div>
      </div>
      <div class="pc-toolbar">
        <a class="pc-btnSm" href="dashboard.php">Back</a>
        <a class="pc-btnSm" href="child.php?uid=<?php echo (int)$uid; ?>">Overview</a>
      </div>
    </div>

    <?php echo pc_child_nav($uid, 'bans'); ?>

    <?php if ($isCurrentlyBanned): ?>
      <div class="pc-note" role="alert" style="margin:10px 0 12px;">
        <?php if ($isPerma): ?>
          <strong><?php echo e($child['username']); ?></strong> is permanently banned.
        <?php else: ?>
          <strong><?php echo e($child['username']); ?></strong> currently has an active ban.
        <?php endif; ?>
      </div>
    <?php endif; ?>

    <div class="pc-tableWrap" style="padding:14px;">
      <?php if (!$bans): ?>
        <div class="pc-empty">No ban records found.</div>
      <?php else: ?>
        <div class="pc-banList">
          <?php foreach ($bans as $b):
            $issuedTs = parent_parse_time($b['issued'] ?? null);
            $expiresTs = parent_parse_time($b['expires'] ?? null);
            $active = (!$isPerma && $expiresTs !== null && $expiresTs >= $now);
            $reason = trim((string)($b['message'] ?? ''));
          ?>
            <div class="pc-banCard <?php echo $active ? 'is-active' : ''; ?>">
              <div class="pc-banTop">
                <div class="pc-banTitle">
                  <?php if ($active): ?>
                    <span class="pc-pill pc-pill--banned">Active ban</span>
                  <?php else: ?>
                    <span class="pc-pill">Past ban</span>
                  <?php endif; ?>
                </div>
                <div class="pc-banMeta">
                  <span><strong>Issued:</strong> <?php echo e($issuedTs ? parent_human_datetime((int)$issuedTs) : (string)($b['issued'] ?? '')); ?></span>
                  <span class="pc-banDot">•</span>
                  <span><strong>Expires:</strong> <?php echo e($isPerma ? 'Permanent' : ($expiresTs ? parent_human_datetime((int)$expiresTs) : (string)($b['expires'] ?? ''))); ?></span>
                </div>
              </div>
              <div class="pc-banReason"><?php echo e($reason !== '' ? $reason : '—'); ?></div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>

    <?php
      $prev = max(1, $page - 1);
      $next = min($pages, $page + 1);
      $base = 'child_bans.php?uid=' . (int)$uid;
    ?>
    <div class="pc-pager">
      <a class="pc-btnSm" href="<?php echo e($base . '&page=' . $prev); ?>" <?php echo $page <= 1 ? 'style="pointer-events:none; opacity:.55;"' : ''; ?>>Prev</a>
      <div class="pc-muted" style="font-weight:900; align-self:center;">Page <?php echo (int)$page; ?> / <?php echo (int)$pages; ?></div>
      <a class="pc-btnSm" href="<?php echo e($base . '&page=' . $next); ?>" <?php echo $page >= $pages ? 'style="pointer-events:none; opacity:.55;"' : ''; ?>>Next</a>
    </div>

  </div>
</div>

<?php require_once __DIR__ . '/includes/parent_footer.php'; ?>
