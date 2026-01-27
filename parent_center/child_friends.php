<?php
require_once __DIR__ . '/includes/child_context.php';
parent_require_login();

$parent = parent_current();
$parentId = (int)$parent['id'];

$uid = (int)($_GET['uid'] ?? 0);
$child = pc_require_child($parentId, $uid);

$page = max(1, (int)($_GET['page'] ?? 1));
$per = 24;
$offset = ($page - 1) * $per;

// Total friends
$stmt = db()->prepare('SELECT COUNT(*) AS c FROM buddies WHERE userId = ?');
$stmt->bind_param('i', $uid);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();
$total = (int)($row['c'] ?? 0);

// Friends list (buddies)
// Also detect "mutual" buddies (friend has you added back)
$stmt = db()->prepare('
  SELECT
    u.id AS userId,
    u.username,
    u.joinTime,
    u.rank,
    u.coins,
    u.color,u.head,u.face,u.neck,u.body,u.hand,u.feet,u.photo,u.flag,
    u.ninjaRank,u.ninjaProgress,
    CASE WHEN b2.userId IS NULL THEN 0 ELSE 1 END AS isMutual
  FROM buddies b
  JOIN users u ON u.id = b.buddyId
  LEFT JOIN buddies b2 ON b2.userId = u.id AND b2.buddyId = ?
  WHERE b.userId = ?
  ORDER BY u.username ASC
  LIMIT ? OFFSET ?
');
$stmt->bind_param('iiii', $uid, $uid, $per, $offset);
$stmt->execute();
$friends = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$pages = max(1, (int)ceil($total / $per));
if ($page > $pages) $page = $pages;

$pageTitle = 'Parent Center - Friends - ' . ($child['username'] ?? 'Child');
include __DIR__ . '/includes/parent_header.php';
?>

<div class="pc-shell">
  <div class="pc-card">
    <div class="pc-pageTitleRow">
      <div>
        <h1 class="pc-pageTitle">Friends</h1>
        <div class="pc-pageSubtitle"><?php echo e($child['username']); ?> • Total: <?php echo number_format($total); ?></div>
      </div>
      <div class="pc-toolbar">
        <a class="pc-btnSm" href="dashboard.php">Back</a>
        <a class="pc-btnSm" href="child.php?uid=<?php echo (int)$uid; ?>">Overview</a>
      </div>
    </div>

    <?php echo pc_child_nav($uid, 'friends'); ?>

    <div class="pc-tableWrap" style="padding:14px;">
      <?php if (!$friends): ?>
        <div class="pc-empty">No friends found.</div>
      <?php else: ?>
        <div class="pc-friendsGrid">
          <?php foreach ($friends as $f):
            $mutual = ((int)($f['isMutual'] ?? 0) === 1);
          ?>
            <div class="pc-friendCard">
              <div class="pc-friendTop">
                <?php echo parent_profile_avatar_clothed_html($f, 56, 'pc-friendAvatar'); ?>
                <div class="pc-friendMeta">
                  <div class="pc-friendNameRow">
                    <div class="pc-friendName"><?php echo e($f['username'] ?? ''); ?></div>
                    <?php if ($mutual): ?>
                      <span class="pc-pill" title="This friend has added <?php echo e($child['username']); ?> back.">Mutual</span>
                    <?php endif; ?>
                  </div>
                  <div class="pc-friendSub">
                    Coins <strong><?php echo number_format((int)($f['coins'] ?? 0)); ?></strong>
                    <span class="pc-dot">•</span>
                    Rank <strong><?php echo (int)($f['rank'] ?? 0); ?></strong>
                    <span class="pc-dot">•</span>
                    Ninja <strong><?php echo (int)($f['ninjaRank'] ?? 0); ?></strong> (<?php echo (int)($f['ninjaProgress'] ?? 0); ?>%)
                  </div>
                </div>
              </div>

              <div class="pc-friendBottom">
                <div class="pc-muted" style="font-weight:900;">
                  Joined: <?php echo e(parent_human_datetime($f['joinTime']) ?: (string)($f['joinTime'] ?? '')); ?>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>

    <?php
      $prev = max(1, $page - 1);
      $next = min($pages, $page + 1);
      $base = 'child_friends.php?uid=' . (int)$uid;
    ?>
    <div class="pc-pager">
      <a class="pc-btnSm" href="<?php echo e($base . '&page=' . $prev); ?>" <?php echo $page <= 1 ? 'style="pointer-events:none; opacity:.55;"' : ''; ?>>Prev</a>
      <div class="pc-muted" style="font-weight:900; align-self:center;">Page <?php echo (int)$page; ?> / <?php echo (int)$pages; ?></div>
      <a class="pc-btnSm" href="<?php echo e($base . '&page=' . $next); ?>" <?php echo $page >= $pages ? 'style="pointer-events:none; opacity:.55;"' : ''; ?>>Next</a>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/includes/parent_footer.php'; ?>
