<?php
// Additional metrics for the overview page.  We count how many outfits
// the current user has saved and how much commission they have earned
// from public outfit purchases. These values are used below in the
// summary card.
$myOutfitCount = 0;
$commCount = 0;
$commTotal = 0;
// Count saved outfits
$stmt = db()->prepare('SELECT COUNT(*) AS c FROM user_outfits WHERE userId = ?');
$stmt->bind_param('i', $user['id']);
$stmt->execute();
$result = $stmt->get_result();
if ($row = $result->fetch_assoc()) {
  $myOutfitCount = (int)$row['c'];
}
$stmt->close();

// Ensure the render_penguin_avatar() function is available. This helper lives
// in the root includes/avatar_component.php. When this page is included
// from within manage_penguin (e.g. index.php) the function may not yet
// exist, which would otherwise trigger a fatal error. If it's missing,
// require the file so the function becomes available.
if (!function_exists('render_penguin_avatar')) {
    $avatarComponentPath = __DIR__ . '/../../includes/avatar_component.php';
    if (file_exists($avatarComponentPath)) {
        require_once $avatarComponentPath;
    }
}
// Sum commission amounts and count commissions. In the latest Yukon
// schema the outfit_commissions table stores the amount earned per
// item purchase and the authorUserId column identifies the outfit
// creator. We filter by authorUserId directly instead of joining on
// user_outfits. Null totals default to 0.
$stmt = db()->prepare('SELECT COALESCE(SUM(amount),0) AS totalAmount, COUNT(*) AS cnt FROM outfit_commissions WHERE authorUserId = ?');
$stmt->bind_param('i', $user['id']);
$stmt->execute();
$result = $stmt->get_result();
if ($row = $result->fetch_assoc()) {
  $commTotal = (int)$row['totalAmount'];
  $commCount = (int)$row['cnt'];
}
$stmt->close();
?>

<div class="mp-grid2">
      <div class="cp-card">
        <h2 class="cp-h2">Your Penguin</h2>
        <div style="display:flex;gap:14px;align-items:center;flex-wrap:wrap">
          <div class="pc-avatarWrap" style="margin:0">
            <?php echo render_penguin_avatar($user, 128); ?>
          </div>
          <div style="min-width:240px">
            <div class="cp-title" style="margin:0"><?php echo e($user['username']); ?></div>
            <div class="cp-subtitle" style="margin-top:4px">Member ID: <?php echo (int)$user['id']; ?></div>
            <div class="cp-text" style="margin-top:8px">
              This is your public penguin profile on the website. Your in-game penguin uses the same wardrobe + inventory.
            </div>
          </div>
        </div>

        <div style="margin-top:14px" class="mp-kpiRow">
          <div class="cp-card mp-kpi">
            <p class="k">Rank</p>
            <p class="v"><?php echo e(rank_label((int)$user['rank'])); ?></p>
          </div>
          <div class="cp-card mp-kpi">
            <p class="k">Color</p>
            <p class="v">#<?php echo (int)$user['color']; ?></p>
          </div>
          <div class="cp-card mp-kpi">
            <p class="k">Inventory Items</p>
            <p class="v"><?php echo (int)$invCount; ?></p>
          </div>
        </div>

        <div class="cp-footnote" style="margin-top:12px">
          Tip: Use the <strong>Studio</strong> to change your outfit. Saved looks now live in <strong>My Outfits</strong>.
        </div>
      </div>

      <div class="cp-card">
        <h2 class="cp-h2">Actions</h2>
        <div class="mp-chips" style="margin-top:10px">
          <!-- Use dashboard tabs instead of separate pages so navigation remains intact -->
          <a class="mp-chip" href="index.php?tab=my_outfits">My outfits</a>
          <a class="mp-chip" href="index.php?tab=commissions">Commission history</a>
          <a class="mp-chip" href="index.php?tab=inventory">
            <?php if (!empty($items)) : ?>
              <img src="<?php echo e(clothing_icon_url((int)$items[0])); ?>" onerror="this.style.display='none'" alt="">
            <?php endif; ?>
            Browse inventory
          </a>
          <a class="mp-chip" href="index.php?tab=security">Change password</a>
          <a class="mp-chip" href="/logout.php">Logout</a>
        </div>

        <div class="cp-footnote" style="margin-top:16px">
          More tools coming soon – we'll be adding friends, badges, redeem codes, email and 2FA to this dashboard.
        </div>
      </div>

      <!-- Summary card for saved outfits and commissions -->
      <div class="cp-card">
        <h2 class="cp-h2">Your Outfits &amp; Earnings</h2>
        <div class="mp-kpiRow" style="margin-top:14px">
          <div class="cp-card mp-kpi">
            <p class="k">Saved Outfits</p>
            <p class="v"><?php echo (int)$myOutfitCount; ?></p>
          </div>
          <div class="cp-card mp-kpi">
            <p class="k">Total Commissions</p>
            <p class="v"><?php echo $commTotal > 0 ? number_format($commTotal) : '0'; ?></p>
          </div>
          <div class="cp-card mp-kpi">
            <p class="k">Commission Payouts</p>
            <p class="v"><?php echo (int)$commCount; ?></p>
          </div>
        </div>
        <div class="cp-footnote" style="margin-top:12px">
          Share your favourite looks by making them public in the Studio. When other players purchase items from your public outfits, you'll earn commissions which appear here.
        </div>
      </div>
    </div>