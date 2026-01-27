<?php
require_once __DIR__ . '/includes/child_context.php';
parent_require_login();

$parent = parent_current();
$parentId = (int)$parent['id'];

$uid = (int)($_GET['uid'] ?? 0);
$child = pc_require_child($parentId, $uid);

$pageTitle = 'Parent Center - Chat History - ' . ($child['username'] ?? 'Child');
include __DIR__ . '/includes/parent_header.php';
?>

<div class="pc-shell">
  <div class="pc-card">
    <div class="pc-pageTitleRow">
      <div>
        <h1 class="pc-pageTitle">Chat History</h1>
        <div class="pc-pageSubtitle"><?php echo e($child['username']); ?></div>
      </div>
      <div class="pc-toolbar">
        <a class="pc-btnSm" href="dashboard.php">Back</a>
        <a class="pc-btnSm" href="child.php?uid=<?php echo (int)$uid; ?>">Overview</a>
      </div>
    </div>

    <?php echo pc_child_nav($uid, 'chat'); ?>

    <div class="pc-note">
      This page is a placeholder.
      <br><br>
      If you'd like, we can implement chat history by reading the game's chat log table (or log files) and adding:
      <ul style="margin:8px 0 0 18px;">
        <li>date range filter</li>
        <li>room/server filter</li>
        <li>profanity/highlight flags</li>
      </ul>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/includes/parent_footer.php'; ?>
