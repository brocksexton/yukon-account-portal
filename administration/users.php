<?php
require_once __DIR__ . '/includes/admin_bootstrap.php';
admin_require_staff();

$title = 'Users';
$activeNav = 'users';

$q = trim((string)($_GET['q'] ?? ''));
$rank = admin_int($_GET['rank'] ?? 0, 0);

$rows = [];

// Build search
$sql = 'SELECT u.id, u.username, u.email, u.rank, u.permaBan, u.joinTime, u.coins,
               u.head, u.face, u.neck, u.body, u.hand, u.feet, u.color, u.photo, u.flag,
               EXISTS(SELECT 1 FROM bans b WHERE b.userId = u.id AND b.expires > NOW() LIMIT 1) AS tempBanned
        FROM users u';

$where = [];
$params = [];
$types = '';

if ($q !== '') {
    // If numeric, allow exact ID match in addition to text search.
    $where[] = '(u.username LIKE ? OR u.email LIKE ?' . (ctype_digit($q) ? ' OR u.id = ?' : '') . ')';
    $like = '%' . $q . '%';
    $params[] = $like; $types .= 's';
    $params[] = $like; $types .= 's';
    if (ctype_digit($q)) {
        $params[] = (int)$q; $types .= 'i';
    }
}

if ($rank > 0) {
    $where[] = 'u.rank = ?';
    $params[] = $rank; $types .= 'i';
}

if ($where) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}

$sql .= ' ORDER BY u.id DESC LIMIT 60';

$stmt = db()->prepare($sql);
if ($types !== '') {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$res = $stmt->get_result();
while ($r = $res->fetch_assoc()) {
    $rows[] = $r;
}
$stmt->close();

include __DIR__ . '/includes/admin_header.php';
?>

<section class="adm-card" style="padding:18px">
  <div style="display:flex; align-items:flex-end; justify-content:space-between; gap:12px; flex-wrap:wrap">
    <div>
      <h1 class="adm-h1" style="margin:0">User Search</h1>
      <p class="adm-muted" style="margin:6px 0 0">Search by username, email, or ID. Open a user to view profile, bans, and manage coins/items.</p>
    </div>

    <form class="adm-form" method="get" action="<?php echo e(admin_url('users.php')); ?>">
      <div class="adm-field" style="min-width:260px">
        <label class="adm-label" for="q">Search</label>
        <input class="adm-input" id="q" name="q" value="<?php echo e($q); ?>" placeholder="username, email, or ID">
      </div>
      <div class="adm-field" style="min-width:200px">
        <label class="adm-label" for="rank">Rank</label>
        <select class="adm-select" id="rank" name="rank">
          <option value="0" <?php echo $rank === 0 ? 'selected' : ''; ?>>All ranks</option>
          <option value="1" <?php echo $rank === 1 ? 'selected' : ''; ?>>User</option>
          <option value="2" <?php echo $rank === 2 ? 'selected' : ''; ?>>Moderator</option>
          <option value="3" <?php echo $rank === 3 ? 'selected' : ''; ?>>Administrator</option>
          <option value="4" <?php echo $rank === 4 ? 'selected' : ''; ?>>Owner/Founder (4)</option>
        </select>
      </div>
      <button class="adm-btn" type="submit">Search</button>
      <a class="adm-btn adm-btn--ghost" href="<?php echo e(admin_url('users.php')); ?>">Clear</a>
    </form>
  </div>
</section>

<section class="adm-card" style="margin-top:14px">
  <div class="adm-card__head">
    <h2 class="adm-h2">Results</h2>
    <div class="adm-muted" style="font-weight:700"><?php echo count($rows); ?> shown</div>
  </div>

  <div class="adm-tableWrap">
    <table class="adm-table">
      <thead>
        <tr>
          <th>User</th>
          <th>Rank</th>
          <th>Coins</th>
          <th>Joined</th>
          <th>Status</th>
          <th style="width:120px">Action</th>
        </tr>
      </thead>
      <tbody>
      <?php if (!$rows): ?>
        <tr><td colspan="6" class="adm-muted">No users matched your query.</td></tr>
      <?php else: ?>
        <?php foreach ($rows as $u): ?>
          <?php
            $uid = (int)$u['id'];
            $rankNum = (int)($u['rank'] ?? 0);
            $statusBadges = [];
            if ((int)($u['permaBan'] ?? 0) === 1) $statusBadges[] = admin_badge_html('Permanent Ban', 'bad');
            if ((int)($u['tempBanned'] ?? 0) === 1) $statusBadges[] = admin_badge_html('Temp Ban', 'warn');
            if (!$statusBadges) $statusBadges[] = admin_badge_html('OK', 'good');
          ?>
          <tr>
            <td>
              <div style="display:flex; flex-direction:column; gap:2px">
                <strong><?php echo e($u['username']); ?></strong>
                <span class="adm-muted">ID <?php echo $uid; ?><?php if (!empty($u['email'])): ?> · <?php echo e($u['email']); ?><?php endif; ?></span>
              </div>
            </td>
            <td><?php echo e(rank_title($rankNum)); ?></td>
            <td><?php echo number_format((int)($u['coins'] ?? 0)); ?></td>
            <td class="adm-muted"><?php echo e($u['joinTime'] ?? ''); ?></td>
            <td><?php echo implode(' ', $statusBadges); ?></td>
            <td>
              <a class="adm-btn adm-btn--sm" href="<?php echo e(admin_url('user.php?id=' . $uid)); ?>">Open</a>
            </td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</section>

<?php include __DIR__ . '/includes/admin_footer.php'; ?>
