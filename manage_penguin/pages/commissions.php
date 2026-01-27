<?php
// manage_penguin/pages/commissions.php
// Display a history of commission payouts earned from public outfits.

// If $embed is defined and truthy, this page is being included from
// index.php via a tab. In that case omit the manage header/footer and
// only render the commission table content. Otherwise include the
// manage header for a standalone page view.
$embed = isset($embed) && $embed;
if (!$embed) {
    // Provide the full page shell for standalone access
    require_once __DIR__ . '/../includes/manage_header.php';
}

require_login();
$user = current_user();
$pageTitle = 'Outfit Commissions - ' . (defined('SITE_NAME') ? SITE_NAME : 'Manage My Penguin');

// Gather commission records. Each commission links back to the outfit
// that generated it so we can display the outfit name. We order by
// newest payouts first. Limiting results prevents runaway table
// sizes; adjust the limit if you anticipate many commissions.
$records = [];
$totalCoins = 0;
$db = db();
// In Yukon v10 the commission table uses `amount` instead of
// `commissionAmount`, and includes an `authorUserId` field. Filter
// directly by authorUserId to fetch only this player's earnings. Join
// to user_outfits to fetch the outfit name using shareCode.
$stmt = $db->prepare(
    'SELECT c.amount, c.created_at, o.name
     FROM outfit_commissions c
     JOIN user_outfits o ON c.outfitShareCode = o.shareCode
     WHERE c.authorUserId = ?
     ORDER BY c.created_at DESC
     LIMIT 200'
);
$stmt->bind_param('i', $user['id']);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
    $records[] = [
        'name'   => $row['name'],
        'amount' => (int)$row['amount'],
        'date'   => $row['created_at'],
    ];
    $totalCoins += (int)$row['amount'];
}
$stmt->close();
?>

<div class="mp-wrap">
  <!-- Wrap commissions history in a card to improve readability and match the
       Manage My Penguin aesthetic. -->
  <div class="cp-card">
    <h2 class="cp-title">Commission Payouts</h2>
    <p class="cp-subtitle">View your earnings from players who purchase items from your public outfits.</p>

    <?php if (empty($records)) : ?>
      <!-- Display an illustrated empty state when the user hasn't earned any commissions yet. -->
      <div class="cp-empty-banner">
        <img src="/manage_penguin/assets/rookie_wingsup.png" alt="No commissions">
        <h3>No Commissions Yet</h3>
        <p>You don't have a commission history yet. Make some public outfits in the Studio and wait for other players to buy items from your looks!</p>
      </div>
    <?php else : ?>
      <table class="cp-table">
        <thead>
          <tr>
            <th style="width:50%">Outfit</th>
            <th style="width:25%">Coins</th>
            <th style="width:25%">Date</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($records as $rec) : ?>
            <tr>
              <td><?php echo e($rec['name'] ?: 'Untitled'); ?></td>
              <td><?php echo number_format($rec['amount']); ?></td>
              <td><?php echo date('Y-m-d', strtotime($rec['date'])); ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <div class="cp-footnote" style="margin-top:14px">
        Total earned: <strong><?php echo number_format($totalCoins); ?></strong> coin<?php echo ($totalCoins === 1 ? '' : 's'); ?> across <?php echo count($records); ?> commission<?php echo (count($records) === 1 ? '' : 's'); ?>.
      </div>
    <?php endif; ?>
  </div>
</div>

<?php if (!$embed): ?>
<?php require_once __DIR__ . '/../includes/manage_footer.php'; ?>
<?php endif; ?>