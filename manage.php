<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/avatar_component.php';
require_login();
$user = current_user();

function rank_label(int $rank): string {
    switch ($rank) {
        case 4: return 'Owner';
        case 3: return 'Administrator';
        case 2: return 'Moderator';
        default: return 'Player';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();

    $action = $_POST['action'] ?? '';

    if ($action === 'change_password') {
        $current = $_POST['current_password'] ?? '';
        $new1 = $_POST['new_password'] ?? '';
        $new2 = $_POST['confirm_password'] ?? '';

        if ($new1 === '' || strlen($new1) < 8) {
            flash_set('error', 'New password must be at least 8 characters.');
            header('Location: manage.php');
            exit;
        }
        if ($new1 !== $new2) {
            flash_set('error', 'New password confirmation does not match.');
            header('Location: manage.php');
            exit;
        }

        // Verify current
        $stmt = db()->prepare('SELECT password FROM users WHERE id = ? LIMIT 1');
        $stmt->bind_param('i', $user['id']);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res->fetch_assoc();
        $stmt->close();

        if (!$row || !password_verify($current, $row['password'])) {
            flash_set('error', 'Current password is incorrect.');
            header('Location: manage.php');
            exit;
        }

        $hash = password_hash($new1, PASSWORD_BCRYPT);
        $stmt = db()->prepare('UPDATE users SET password = ? WHERE id = ?');
        $stmt->bind_param('si', $hash, $user['id']);
        $stmt->execute();
        $stmt->close();

        unset($_SESSION['user_cache']);
        flash_set('success', 'Password updated.');
        header('Location: manage.php');
        exit;
    }
}

// Pull inventory
$items = [];
$stmt = db()->prepare('SELECT itemId FROM inventories WHERE userId = ? ORDER BY itemId ASC LIMIT 100');
$stmt->bind_param('i', $user['id']);
$stmt->execute();
$res = $stmt->get_result();
while ($r = $res->fetch_assoc()) {
    $items[] = (int)$r['itemId'];
}
$stmt->close();

$pageTitle = SITE_NAME . ' - Manage My Penguin';
require_once __DIR__ . '/includes/header.php';
?>

<div class="cp-panel">
  <h2 class="cp-title">Manage My Penguin</h2>
  <p class="cp-sub">Welcome, <b><?php echo e($user['username']); ?></b>. This page pulls your data directly from the Yukon database.</p>

  <div class="cp-grid" style="margin-top:16px">
    <div>
      <div id="avatarMount">
        <?php render_penguin_avatar($user, 220); ?>
      </div>
      <div style="margin-top:12px">
        <a href="logout.php" class="feature-card_button__3CdS5" style="display:inline-block">Logout</a>
      </div>
    </div>

    <div>
      <div class="cp-kv">
        <div><b>Rank</b><span id="rankVal"><?php echo e(rank_label((int)$user['rank'])); ?> (<?php echo (int)$user['rank']; ?>)</span></div>
        <div><b>Coins</b><span id="coinsVal"><?php echo (int)$user['coins']; ?></span></div>
        <div><b>Joined</b><span id="joinVal"><?php echo e($user['joinTime']); ?></span></div>
        <div><b>Ninja</b><span id="ninjaVal">Rank <?php echo (int)$user['ninjaRank']; ?> • Progress <?php echo (int)$user['ninjaProgress']; ?>%</span></div>
      </div>

      <h3 style="margin-top:18px" class="cp-title">Outfit</h3>
      <table class="cp-table">
        <thead><tr><th>Slot</th><th>Item ID</th></tr></thead>
        <tbody>
          <tr><td>Color</td><td id="slot-color"><?php echo (int)$user['color']; ?></td></tr>
          <tr><td>Head</td><td id="slot-head"><?php echo (int)$user['head']; ?></td></tr>
          <tr><td>Face</td><td id="slot-face"><?php echo (int)$user['face']; ?></td></tr>
          <tr><td>Neck</td><td id="slot-neck"><?php echo (int)$user['neck']; ?></td></tr>
          <tr><td>Body</td><td id="slot-body"><?php echo (int)$user['body']; ?></td></tr>
          <tr><td>Hand</td><td id="slot-hand"><?php echo (int)$user['hand']; ?></td></tr>
          <tr><td>Feet</td><td id="slot-feet"><?php echo (int)$user['feet']; ?></td></tr>
          <tr><td>Photo</td><td id="slot-photo"><?php echo (int)$user['photo']; ?></td></tr>
          <tr><td>Flag</td><td id="slot-flag"><?php echo (int)$user['flag']; ?></td></tr>
        </tbody>
      </table>

      <h3 style="margin-top:18px" class="cp-title">Inventory</h3>
      <p class="cp-muted">Showing first 100 inventory item IDs (from <code>inventories</code> table).</p>
      <div style="display:flex;flex-wrap:wrap;gap:8px">
        <?php foreach ($items as $id): ?>
          <span class="cp-pill">#<?php echo (int)$id; ?></span>
        <?php endforeach; ?>
        <?php if (count($items) === 0): ?>
          <span class="cp-pill">No items found</span>
        <?php endif; ?>
      </div>

      <h3 style="margin-top:22px" class="cp-title">Change Password</h3>
      <form method="post" class="cp-form" action="manage.php">
        <input type="hidden" name="csrf" value="<?php echo e(csrf_token()); ?>"/>
        <input type="hidden" name="action" value="change_password"/>

        <label for="current_password">Current password</label>
        <input id="current_password" name="current_password" type="password" required />

        <label for="new_password">New password</label>
        <input id="new_password" name="new_password" type="password" minlength="8" required />

        <label for="confirm_password">Confirm new password</label>
        <input id="confirm_password" name="confirm_password" type="password" minlength="8" required />

        <div style="margin-top:14px">
          <button type="submit" class="link-button_button__xL9TZ link-button_yellow__Tdvvb" style="width:260px">
            <div class="link-button_text__PoH2c">Update<div class="link-button_outline__1volg">Update</div></div>
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
(() => {
  const mount = document.getElementById('avatarMount');
  if (!mount) return;

  let lastState = '';

  async function refresh() {
    try {
      const res = await fetch('api/me.php', { credentials: 'same-origin' });
      if (!res.ok) return;
      const data = await res.json();
      if (!data || !data.ok) return;

      // Update kv
      document.getElementById('coinsVal')?.textContent = data.user.coins;
      document.getElementById('joinVal')?.textContent = data.user.joinTime;
      document.getElementById('rankVal')?.textContent = `${data.user.rankLabel} (${data.user.rank})`;
      document.getElementById('ninjaVal')?.textContent = `Rank ${data.user.ninjaRank} • Progress ${data.user.ninjaProgress}%`;

      // Update outfit ids
      for (const [k, v] of Object.entries(data.outfit || {})) {
        const el = document.getElementById(`slot-${k}`);
        if (el) el.textContent = v;
      }

      const signature = JSON.stringify({ o: data.outfit, c: data.user.coins });
      if (signature !== lastState) {
        lastState = signature;
        // Re-fetch the avatar HTML from server (ensures correct color mapping and layer order)
        const bust = Date.now();
        const ares = await fetch('avatar_fragment.php?t=' + bust, { credentials: 'same-origin' });
        if (ares.ok) {
          mount.innerHTML = await ares.text();
        }
      }
    } catch (e) {
      // Silent
    }
  }

  refresh();
  setInterval(refresh, 4000);
})();
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
