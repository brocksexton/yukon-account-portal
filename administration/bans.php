<?php
require_once __DIR__ . '/includes/admin_bootstrap.php';
admin_require_staff();

$title = 'Bans';
$activeNav = 'bans';

$me = current_user();

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();
    $action = (string)($_POST['action'] ?? '');
    $userId = admin_int($_POST['userId'] ?? 0, 0);
    $banId  = admin_int($_POST['banId'] ?? 0, 0);
    $reason = trim((string)($_POST['reason'] ?? ''));
    $durationHours = admin_int($_POST['durationHours'] ?? 24, 24);
    $permanent = !empty($_POST['permanent']);
    $modId = (int)($me['id'] ?? 0);

    if ($action === 'issue_ban') {
        if ($userId <= 0) {
            flash_set('error', 'Enter a valid user id.');
            header('Location: ' . admin_url('bans.php'));
            exit;
        }
        $u = admin_get_user($userId);
        if (!$u) {
            flash_set('error', 'User not found.');
            header('Location: ' . admin_url('bans.php'));
            exit;
        }
        if ($reason === '') $reason = 'No reason specified';

        if ($permanent) {
            $stmt = db()->prepare('UPDATE users SET permaBan = 1 WHERE id = ? LIMIT 1');
            $stmt->bind_param('i', $userId);
            $stmt->execute();
            $stmt->close();

            $stmt = db()->prepare('INSERT INTO bans (userId, issued, expires, moderatorId, message) VALUES (?, NOW(), NOW(), ?, ?)');
            $stmt->bind_param('iis', $userId, $modId, $reason);
            $stmt->execute();
            $stmt->close();

            flash_set('success', 'Permanently banned ' . $u['username'] . '.');
        } else {
            $durationHours = max(1, min(8760, $durationHours));
            $stmt = db()->prepare('INSERT INTO bans (userId, issued, expires, moderatorId, message) VALUES (?, NOW(), DATE_ADD(NOW(), INTERVAL ? HOUR), ?, ?)');
            $stmt->bind_param('iiis', $userId, $durationHours, $modId, $reason);
            $stmt->execute();
            $stmt->close();
            flash_set('success', 'Banned ' . $u['username'] . ' for ' . $durationHours . ' hours.');
        }
        header('Location: ' . admin_url('bans.php?userId=' . $userId));
        exit;
    }

    if ($action === 'unban_user') {
        if ($userId <= 0) {
            flash_set('error', 'Invalid user id.');
            header('Location: ' . admin_url('bans.php'));
            exit;
        }
        $u = admin_get_user($userId);
        if (!$u) {
            flash_set('error', 'User not found.');
            header('Location: ' . admin_url('bans.php'));
            exit;
        }

        $stmt = db()->prepare('UPDATE users SET permaBan = 0 WHERE id = ? LIMIT 1');
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $stmt->close();

        $stmt = db()->prepare('UPDATE bans SET expires = NOW() WHERE userId = ? AND expires > NOW()');
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $stmt->close();

        flash_set('success', 'Unbanned ' . $u['username'] . '.');
        header('Location: ' . admin_url('bans.php?userId=' . $userId));
        exit;
    }

    if ($action === 'expire_ban') {
        if ($banId <= 0) {
            flash_set('error', 'Invalid ban id.');
            header('Location: ' . admin_url('bans.php'));
            exit;
        }
        $stmt = db()->prepare('UPDATE bans SET expires = NOW() WHERE id = ? LIMIT 1');
        $stmt->bind_param('i', $banId);
        $stmt->execute();
        $stmt->close();
        flash_set('success', 'Expired ban #' . $banId . '.');
        header('Location: ' . admin_url('bans.php'));
        exit;
    }

    flash_set('error', 'Unknown action.');
    header('Location: ' . admin_url('bans.php'));
    exit;
}

$filterUserId = admin_int($_GET['userId'] ?? 0, 0);

// Active temp bans
$tempBans = [];
$sql = 'SELECT b.id, b.userId, u.username, u.color, u.photo, u.flag, u.head, u.face, u.neck, u.body, u.hand, u.feet, b.issued, b.expires, b.message, b.moderatorId, mu.username AS moderatorName
        FROM bans b
        JOIN users u ON u.id = b.userId
        LEFT JOIN users mu ON mu.id = b.moderatorId
        WHERE b.expires > NOW()' . ($filterUserId > 0 ? ' AND b.userId = ?' : '') . '
        ORDER BY b.issued DESC
        LIMIT 50';
$stmt = db()->prepare($sql);
if ($filterUserId > 0) $stmt->bind_param('i', $filterUserId);
$stmt->execute();
$res = $stmt->get_result();
while ($r = $res->fetch_assoc()) $tempBans[] = $r;
$stmt->close();

// Permanent bans
$permaBans = [];
$sql = 'SELECT u.id, u.username, u.rank, u.joinTime
        FROM users u
        WHERE u.permaBan = 1' . ($filterUserId > 0 ? ' AND u.id = ?' : '') . '
        ORDER BY u.id DESC
        LIMIT 50';
$stmt = db()->prepare($sql);
if ($filterUserId > 0) $stmt->bind_param('i', $filterUserId);
$stmt->execute();
$res = $stmt->get_result();
while ($r = $res->fetch_assoc()) $permaBans[] = $r;
$stmt->close();

include __DIR__ . '/includes/admin_header.php';
?>

<section class="adm-card" style="padding:18px">
  <div style="display:flex; align-items:flex-end; justify-content:space-between; gap:12px; flex-wrap:wrap">
    <div>
      <h1 class="adm-h1" style="margin:0">Ban Management</h1>
      <p class="adm-muted" style="margin:6px 0 0">Issue bans, view active bans, and unban accounts. Filter by user ID if needed.</p>
    </div>
    <form class="adm-form" method="get" action="<?php echo e(admin_url('bans.php')); ?>">
      <div class="adm-field" style="min-width:180px">
        <label class="adm-label" for="userId">Filter User ID</label>
        <input class="adm-input" id="userId" name="userId" value="<?php echo $filterUserId > 0 ? (int)$filterUserId : ''; ?>" placeholder="e.g. 4">
      </div>
      <button class="adm-btn" type="submit">Filter</button>
      <a class="adm-btn adm-btn--ghost" href="<?php echo e(admin_url('bans.php')); ?>">Clear</a>
    </form>
  </div>
</section>

<section class="adm-card" style="margin-top:14px; padding:16px">
  <h2 class="adm-h2" style="margin:0 0 10px">Issue Ban</h2>
  <form class="adm-form" method="post">
    <input type="hidden" name="csrf" value="<?php echo e(csrf_token()); ?>">
    <input type="hidden" name="action" value="issue_ban">
    <div class="adm-field" style="min-width:140px">
      <label class="adm-label">User ID</label>
      <input class="adm-input" type="number" min="1" name="userId" value="<?php echo $filterUserId > 0 ? (int)$filterUserId : ''; ?>" placeholder="ID">
    </div>
    <div class="adm-field" style="min-width:280px">
      <label class="adm-label">Reason</label>
      <input class="adm-input" name="reason" placeholder="Swearing/Profanity">
    </div>
    <div class="adm-field" style="min-width:170px">
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
</section>

<section class="adm-card" style="margin-top:14px">
  <div class="adm-card__head">
    <h2 class="adm-h2">Active Temporary Bans</h2>
    <div class="adm-muted" style="font-weight:800"><?php echo count($tempBans); ?> shown</div>
  </div>
  <div class="adm-tableWrap">
    <table class="adm-table">
      <thead>
        <tr>
          <th>User</th>
          <th>Issued</th>
          <th>Expires</th>
          <th>Moderator</th>
          <th>Reason</th>
          <th style="width:180px">Actions</th>
        </tr>
      </thead>
      <tbody>
      <?php if (!$tempBans): ?>
        <tr><td colspan="6" class="adm-muted">No active temporary bans.</td></tr>
      <?php else: ?>
        <?php foreach ($tempBans as $b): ?>
          <tr>
            <td>
              <div class="adm-userCell">
                <span class="adm-miniAvatar" aria-hidden="true"><?php render_penguin_avatar($b, 44); ?></span>
                <div>
                  <a class="adm-userLink" href="<?php echo e(admin_url('user.php?id=' . (int)$b['userId'])); ?>">
                    <strong><?php echo e($b['username']); ?></strong>
                  </a>
                  <div class="adm-muted">ID <?php echo (int)$b['userId']; ?></div>
                </div>

                <div class="adm-hoverCard" aria-hidden="true">
                  <div class="adm-hoverCard__avatar"><?php render_penguin_avatar($b, 140); ?></div>
                  <div class="adm-hoverCard__meta">
                    <div class="adm-hoverCard__name"><?php echo e($b['username']); ?></div>
                    <div class="adm-muted">User ID <?php echo (int)$b['userId']; ?></div>
                    <div class="adm-hoverCard__actions">
                      <a class="adm-btn adm-btn--ghost" href="<?php echo e(admin_url('user.php?id=' . (int)$b['userId'])); ?>">View</a>
                      <a class="adm-btn" href="<?php echo e(admin_url('bans.php?userId=' . (int)$b['userId'])); ?>">Bans</a>
                    </div>
                  </div>
                </div>
              </div>
            </td>
            <td class="adm-muted"><?php echo e($b['issued']); ?></td>
            <td class="adm-muted"><?php echo e($b['expires']); ?></td>
            <td><strong><?php echo e($b['moderatorName'] ?? ('ID ' . (int)$b['moderatorId'])); ?></strong></td>
            <td><?php echo e($b['message']); ?></td>
            <td>
              <a class="adm-btn adm-btn--sm" href="<?php echo e(admin_url('user.php?id=' . (int)$b['userId'])); ?>">User</a>
              <form method="post" style="display:inline-block; margin-left:6px">
                <input type="hidden" name="csrf" value="<?php echo e(csrf_token()); ?>">
                <input type="hidden" name="action" value="expire_ban">
                <input type="hidden" name="banId" value="<?php echo (int)$b['id']; ?>">
                <button class="adm-btn adm-btn--sm adm-btn--ghost" type="submit" onclick="return confirm('Expire this ban now?');">Expire</button>
              </form>
              <form method="post" style="display:inline-block; margin-left:6px">
                <input type="hidden" name="csrf" value="<?php echo e(csrf_token()); ?>">
                <input type="hidden" name="action" value="unban_user">
                <input type="hidden" name="userId" value="<?php echo (int)$b['userId']; ?>">
                <button class="adm-btn adm-btn--sm adm-btn--ghost" type="submit" onclick="return confirm('Unban user (clears perma + expires active temp bans)?');">Unban</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</section>

<section class="adm-card" style="margin-top:14px">
  <div class="adm-card__head">
    <h2 class="adm-h2">Permanent Bans</h2>
    <div class="adm-muted" style="font-weight:800"><?php echo count($permaBans); ?> shown</div>
  </div>
  <div class="adm-tableWrap">
    <table class="adm-table" style="min-width: 760px">
      <thead>
        <tr>
          <th>User</th>
          <th>Rank</th>
          <th>Joined</th>
          <th style="width:180px">Actions</th>
        </tr>
      </thead>
      <tbody>
      <?php if (!$permaBans): ?>
        <tr><td colspan="4" class="adm-muted">No permanent bans.</td></tr>
      <?php else: ?>
        <?php foreach ($permaBans as $u): ?>
          <tr>
            <td>
              <div class="adm-userCell">
                <span class="adm-miniAvatar" aria-hidden="true"><?php render_penguin_avatar($u, 44); ?></span>
                <div>
                  <a class="adm-userLink" href="<?php echo e(admin_url('user.php?id=' . (int)$u['id'])); ?>">
                    <strong><?php echo e($u['username']); ?></strong>
                  </a>
                  <div class="adm-muted">ID <?php echo (int)$u['id']; ?></div>
                </div>

                <div class="adm-hoverCard" aria-hidden="true">
                  <div class="adm-hoverCard__avatar"><?php render_penguin_avatar($u, 140); ?></div>
                  <div class="adm-hoverCard__meta">
                    <div class="adm-hoverCard__name"><?php echo e($u['username']); ?></div>
                    <div class="adm-muted"><?php echo e(rank_title((int)$u['rank'])); ?> · User ID <?php echo (int)$u['id']; ?></div>
                    <div class="adm-hoverCard__actions">
                      <a class="adm-btn adm-btn--ghost" href="<?php echo e(admin_url('user.php?id=' . (int)$u['id'])); ?>">View</a>
                      <a class="adm-btn" href="<?php echo e(admin_url('bans.php?userId=' . (int)$u['id'])); ?>">Bans</a>
                    </div>
                  </div>
                </div>
              </div>
            </td>
            <td><?php echo e(rank_title((int)$u['rank'])); ?></td>
            <td class="adm-muted"><?php echo e($u['joinTime']); ?></td>
            <td>
              <a class="adm-btn adm-btn--sm" href="<?php echo e(admin_url('user.php?id=' . (int)$u['id'])); ?>">User</a>
              <form method="post" style="display:inline-block; margin-left:6px">
                <input type="hidden" name="csrf" value="<?php echo e(csrf_token()); ?>">
                <input type="hidden" name="action" value="unban_user">
                <input type="hidden" name="userId" value="<?php echo (int)$u['id']; ?>">
                <button class="adm-btn adm-btn--sm adm-btn--ghost" type="submit" onclick="return confirm('Unban this user?');">Unban</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</section>

<?php include __DIR__ . '/includes/admin_footer.php'; ?>
