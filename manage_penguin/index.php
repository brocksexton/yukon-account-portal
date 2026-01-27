<?php
require_once __DIR__ . '/../includes/bootstrap.php';
// Avatar component lives inside manage_penguin (keeps this module self-contained)
require_once __DIR__ . '/includes/avatar_component.php';

require_login();
require_once __DIR__ . '/includes/meta.php';

$user = current_user();

// Determine which section to display. Only a handful of dashboard
// sections are routed through this file. We have removed the legacy
// "Outfit" tab in favour of the dedicated Outfit Studio and My Outfits
// pages. Any unknown tab will fall back to the overview.
$tab = isset($_GET['tab']) ? strtolower((string)$_GET['tab']) : 'overview';
$allowed = ['overview', 'inventory', 'security', 'my_outfits', 'commissions'];
if (!in_array($tab, $allowed, true)) {
    $tab = 'overview';
}

/** load inventory preview (for overview + hero chips) */
$items = [];
$stmt = db()->prepare('SELECT itemId FROM inventories WHERE userId = ? ORDER BY itemId ASC LIMIT 160');
$stmt->bind_param('i', $user['id']);
$stmt->execute();
$res = $stmt->get_result();
while ($r = $res->fetch_assoc()) $items[] = (int)$r['itemId'];
$stmt->close();

/** count inventory */
$invCount = 0;
$stmt = db()->prepare('SELECT COUNT(*) AS c FROM inventories WHERE userId = ?');
$stmt->bind_param('i', $user['id']);
$stmt->execute();
$res = $stmt->get_result();
if ($r = $res->fetch_assoc()) $invCount = (int)$r['c'];
$stmt->close();

function rank_label(int $rank): string {
    switch ($rank) {
        case 4: return 'Owner';
        case 3: return 'Administrator';
        case 2: return 'Moderator';
        default: return 'Player';
    }
}

// No POST actions are handled here. The former outfit saving logic has
// been removed; outfits are now managed exclusively through the
// dedicated Outfit Studio and My Outfits interfaces.

// random hero art
$heroArts = [
  'blue_penguin_waving.png',
  'sensei_standing_smug.png',
  'gary_shrugs.png',
  'rookie_wingsup.png',
  'tourguide_stand_withpenguin.png',
  'spy_penguin.png'
];
$heroPick = $heroArts[array_rand($heroArts)];
?>

<?php include __DIR__ . '/../includes/header.php'; ?>
<link rel="stylesheet" href="manage.css"/>

<div class="mp-wrap">
  <?php include __DIR__ . '/includes/hero.php'; ?>

  <div class="mp-tabs">
    <a class="mp-tab <?php echo $tab==='overview' ? 'is-active' : ''; ?>" href="index.php?tab=overview">Overview</a>
    <a class="mp-tab <?php echo $tab==='inventory' ? 'is-active' : ''; ?>" href="index.php?tab=inventory">Inventory</a>
    <!-- My Outfits is now a tab embedded on the dashboard -->
    <a class="mp-tab <?php echo $tab==='my_outfits' ? 'is-active' : ''; ?>" href="index.php?tab=my_outfits">My Outfits</a>
    <!-- Studio remains its own page (full‑screen builder) -->
    <a class="mp-tab" href="studio.php">Studio</a>
    <!-- Commissions tab displays commission history inline -->
    <a class="mp-tab <?php echo $tab==='commissions' ? 'is-active' : ''; ?>" href="index.php?tab=commissions">Commissions</a>
    <a class="mp-tab <?php echo $tab==='security' ? 'is-active' : ''; ?>" href="index.php?tab=security">Security</a>
  </div>

  <?php if ($tab === 'overview'): ?>
    <?php include __DIR__ . '/pages/overview.php'; ?>
  <?php endif; ?>


  <?php if ($tab === 'inventory'): ?>
    <?php include __DIR__ . '/pages/inventory.php'; ?>
  <?php endif; ?>

  <?php if ($tab === 'my_outfits'): ?>
    <?php
      // When embedding the My Outfits page on the dashboard, signal to the
      // page that it should omit its own header/footer and adjust API paths.
      $embed = true;
      include __DIR__ . '/pages/my_outfits.php';
    ?>
  <?php endif; ?>

  <?php if ($tab === 'commissions'): ?>
    <?php
      // Embed the commissions page. It will not render its own header/footer.
      $embed = true;
      include __DIR__ . '/pages/commissions.php';
    ?>
  <?php endif; ?>

  <?php if ($tab === 'security'): ?>
    <?php include __DIR__ . '/pages/security.php'; ?>
  <?php endif; ?>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
