<?php
require_once __DIR__ . '/includes/parent_bootstrap.php';

parent_require_auth();

$parent = parent_current();
$parentPenguin = parent_linked_parent_penguin($parent['id']);
$children = parent_children_for_parent($parent['id']);

// Alerts: child moderation signals (active/perma/recent bans)
$banAlerts = parent_child_ban_alerts((int)$parent['id'], 30);

// Map: userId => alert (for quick lookups in the child table)
// Prefer active/perma over recent if multiple records exist.
$banByUserId = [];
foreach ($banAlerts as $a) {
  if (empty($a['userId'])) continue;
  $uid = (int)$a['userId'];
  $kind = (string)($a['kind'] ?? 'recent');
  if (!isset($banByUserId[$uid])) {
    $banByUserId[$uid] = $a;
    continue;
  }
  $existingKind = (string)($banByUserId[$uid]['kind'] ?? 'recent');
  $rank = ['perma' => 3, 'active' => 2, 'recent' => 1];
  if (($rank[$kind] ?? 0) > ($rank[$existingKind] ?? 0)) {
    $banByUserId[$uid] = $a;
  }
}

// Quick stats
$childCount = count($children);
$totalChildCoins = 0;
foreach ($children as $c) {
  $totalChildCoins += (int)($c['coins'] ?? 0);
}
$activeBanCount = 0;
foreach ($banAlerts as $a) {
  if (($a['kind'] ?? '') === 'active' || ($a['kind'] ?? '') === 'perma') $activeBanCount++;
}

require_once __DIR__ . '/includes/parent_header.php';
?>
<script>
(function () {
  const images = [
    './assets/container-top.png',
    './assets/container-top-1.png',
    './assets/container-top-2.png',
    './assets/container-top-3.png',
    './assets/container-top-4.png'
  ];

  const chosen = images[Math.floor(Math.random() * images.length)];

  const style = document.createElement('style');
  style.innerHTML = `
    .pc-card::before {
      background-image: url("${chosen}") !important;
    }
  `;
  document.head.appendChild(style);
})();
</script>

<div class="pc-shell">
  <div class="pc-card">
    <div class="pc-titleRow">
      <img class="pc-heroIcon" src="assets/pc/parents-icon.png" alt="">
      <div>
        <h1 class="pc-title" style="margin:0;">PARENT CENTER</h1>
        <p class="pc-subtitle" style="margin:6px 0 0;">Manage linked penguin accounts, view playercards, puffles, items, and ban history.</p>
      </div>
    </div>

    <?php if (!empty($_GET['ok'])): ?>
      <div class="pc-alert pc-alert--success">Account linked successfully.</div>
    <?php endif; ?>
    <?php if (!empty($_GET['err'])): ?>
      <div class="pc-alert pc-alert--error"><?php echo e($_GET['err']); ?></div>
    <?php endif; ?>

    <div class="pc-actions pc-actions--toolbar">
      <a class="pc-btn pc-btn--primary" href="claim.php?role=child">Claim Account</a>

      <?php if (!$parentPenguin): ?>
        <a class="pc-btn" href="claim.php?role=parent">Link My Penguin</a>
      <?php endif; ?>

      <a class="pc-btn" href="create_child.php">Create Child Account</a>
    </div>

    <div class="pc-stats">
      <div class="pc-statCard">
        <div class="pc-statLabel">Linked children</div>
        <div class="pc-statValue"><?php echo (int)$childCount; ?></div>
      </div>
      <div class="pc-statCard">
        <div class="pc-statLabel">Child coins (total)</div>
        <div class="pc-statValue"><?php echo number_format((int)$totalChildCoins); ?></div>
      </div>
      <div class="pc-statCard">
        <div class="pc-statLabel">Active bans</div>
        <div class="pc-statValue"><?php echo (int)$activeBanCount; ?></div>
      </div>
    </div>

    <div class="pc-callouts">
      <div class="pc-callout">
        <img class="pc-calloutIcon" src="assets/pc/stay-safe-icon.png" alt="">
        <div>
          <div class="pc-calloutTitle">Stay Safe</div>
          <div class="pc-calloutText">Use this page to review linked accounts, recent activity signals, and ban history.</div>
        </div>
      </div>
      <div class="pc-callout">
        <img class="pc-calloutIcon" src="assets/pc/phone.svg" alt="">
        <div>
          <div class="pc-calloutTitle">Need to manage an account?</div>
          <div class="pc-calloutText">Open a child profile to view their playercard, puffles, inventory, and ban history.</div>
        </div>
      </div>
    </div>

    <?php if ($banAlerts): ?>
      <div class="pc-alertStack" role="region" aria-label="Alerts">
        <?php foreach ($banAlerts as $a):
          $kind = (string)($a['kind'] ?? 'recent');
          $u = (string)($a['username'] ?? 'Unknown');
          $msg = trim((string)($a['message'] ?? ''));

          $class = 'pc-banner';
          $title = '';
          $sub = '';
          if ($kind === 'perma') {
            $class .= ' pc-banner--danger';
            $title = '<strong>' . e($u) . '</strong> is permanently banned.';
            $sub = 'This account cannot log in.';
          } elseif ($kind === 'active') {
            $class .= ' pc-banner--danger';
            $title = '<strong>' . e($u) . '</strong> is currently banned.';
            if (!empty($a['expires_ts'])) {
              $sub = 'Ban expires ' . e(parent_human_datetime((int)$a['expires_ts'])) . '.';
            } else {
              $sub = 'Ban is currently active.';
            }
          } else {
            $class .= ' pc-banner--warning';
            $title = '<strong>' . e($u) . '</strong> was banned recently.';
            $sub = 'Ban has expired, but occurred within the last 30 days.';
          }
        ?>
          <div class="<?php echo e($class); ?>">
            <div class="pc-bannerIcon" aria-hidden="true">
              <img src="assets/pc/badge.svg" alt="">
            </div>
            <div class="pc-bannerBody">
              <div class="pc-bannerTitle"><?php echo $title; ?></div>
              <div class="pc-bannerSub">
                <?php echo e($sub); ?>
                <?php if ($msg !== ''): ?>
                  <span class="pc-bannerMsg">• <?php echo e($msg); ?></span>
                <?php endif; ?>
              </div>
            </div>
            <div class="pc-bannerActions">
              <a class="pc-btnSm" href="child.php?uid=<?php echo (int)$a['userId']; ?>">View</a>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <h2 class="pc-sectionTitle">My Penguin</h2>
    <?php if (!$parentPenguin): ?>
      <p class="pc-muted">No parent penguin linked yet. Use <strong>Link My Penguin</strong> to connect your in-game account.</p>
    <?php else: ?>
      <div class="pc-tableWrap pc-tableWrap--scroll">
        <table class="pc-table">
          <thead>
            <tr>
              <th>Penguin</th>
              <th>Username</th>
              <th>Coins</th>
              <th>Status</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr>
              <td>
                <?php echo parent_profile_avatar_clothed_html($parentPenguin, 96, 'pc-avatar'); ?>
              </td>
              <td>
                <div class="pc-username"><?php echo e($parentPenguin['username']); ?></div>
                <div class="pc-small">Parent account</div>
              </td>
              <td><?php echo e((string)$parentPenguin['coins']); ?></td>
              <td><span class="pc-pill">Active</span></td>
              <td class="pc-actionsCol">
                <a class="pc-btnSm" href="child.php?uid=<?php echo (int)$parentPenguin['userId']; ?>">View</a>
                <a class="pc-btnSm pc-btnSm--danger" href="unlink.php?uid=<?php echo (int)$parentPenguin['userId']; ?>" onclick="return confirm('Unlink your parent penguin?');">Unlink</a>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    <?php endif; ?>

    <h2 class="pc-sectionTitle">Child Accounts</h2>
    <?php if (count($children) === 0): ?>
      <p class="pc-muted">No child accounts linked yet.</p>
    <?php else: ?>
      <div class="pc-tableWrap pc-tableWrap--scroll">
        <table class="pc-table">
          <thead>
            <tr>
              <th>Penguin</th>
              <th>Username</th>
              <th>Coins</th>
              <th>Status</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($children as $c): ?>
              <?php
                $cUid = (int)($c['userId'] ?? 0);
                $alert = $banByUserId[$cUid] ?? null;
                $kind = $alert ? (string)($alert['kind'] ?? 'recent') : '';
                $isCurrentlyBanned = ($kind === 'active' || $kind === 'perma');
                $banTitle = '';
                if ($isCurrentlyBanned && $alert) {
                  if ($kind === 'perma') {
                    $banTitle = 'This account is permanently banned.';
                  } elseif (!empty($alert['expires_ts'])) {
                    $banTitle = 'Banned until ' . parent_human_datetime((int)$alert['expires_ts']);
                  } else {
                    $banTitle = 'This account is currently banned.';
                  }
                  $m = trim((string)($alert['message'] ?? ''));
                  if ($m !== '') $banTitle .= ' • ' . $m;
                }
              ?>

              <tr class="<?php echo $isCurrentlyBanned ? 'pc-row--banned' : ''; ?>">
                <td>
                  <div class="pc-avatarSlot">
                    <?php echo parent_profile_avatar_clothed_html($c, 96, 'pc-avatar'); ?>
                    <?php if ($isCurrentlyBanned): ?>
                      <span class="pc-modBadge" title="<?php echo e($banTitle); ?>">
                        <img src="assets/pc/badge.svg" alt="Banned">
                      </span>
                    <?php endif; ?>
                  </div>
                </td>
                <td>
                  <div class="pc-username"><?php echo e($c['username']); ?></div>
                  <?php if (!empty($c['label'])): ?>
                    <div class="pc-small">Parent label: <?php echo e($c['label']); ?></div>
                  <?php endif; ?>
                </td>
                <td><?php echo e((string)$c['coins']); ?></td>
                <td>
                  <?php if ($isCurrentlyBanned): ?>
                    <span class="pc-pill pc-pill--banned">Banned</span>
                  <?php else: ?>
                    <span class="pc-pill">Active</span>
                  <?php endif; ?>
                </td>
                <td class="pc-actionsCol">
                  <a class="pc-btnSm" href="child.php?uid=<?php echo (int)$c['userId']; ?>">View</a>
                  <a class="pc-btnSm pc-btnSm--danger" href="unlink.php?uid=<?php echo (int)$c['userId']; ?>" onclick="return confirm('Unlink <?php echo e($c['username']); ?>?');">Unlink</a>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>

  </div>
</div>

<?php require_once __DIR__ . '/includes/parent_footer.php'; ?>
