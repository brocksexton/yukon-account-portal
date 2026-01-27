<?php
require_once __DIR__ . '/includes/child_context.php';
parent_require_login();

$parent = parent_current();
$parentId = (int)$parent['id'];

$uid = (int)($_GET['uid'] ?? 0);
$child = pc_require_child($parentId, $uid);

// Overview pulls a small preview; dedicated pages show full data.
// Pets (Puffles)
$stmt = db()->prepare('SELECT id, typeId, name, adoptionDate, energy, health, rest FROM pets WHERE userId = ? ORDER BY adoptionDate DESC LIMIT 6');
$stmt->bind_param('i', $uid);
$stmt->execute();
$pets = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$stmt = db()->prepare('SELECT COUNT(*) AS c FROM pets WHERE userId = ?');
$stmt->bind_param('i', $uid);
$stmt->execute();
$petsCountRow = $stmt->get_result()->fetch_assoc();
$stmt->close();
$petsCount = (int)($petsCountRow['c'] ?? 0);

// Inventory (clothing)
$stmt = db()->prepare('SELECT itemId FROM inventories WHERE userId = ? ORDER BY itemId ASC LIMIT 10');
$stmt->bind_param('i', $uid);
$stmt->execute();
$inv = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Role flags (based on ownership of specific items)
// 800 => PSA (Secret Agent)
// 428 => Tour Guide
$hasPSA = false;
$hasTourGuide = false;
$stmt = db()->prepare('SELECT itemId FROM inventories WHERE userId = ? AND itemId IN (800, 428)');
$stmt->bind_param('i', $uid);
$stmt->execute();
$roleItems = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
if ($roleItems) {
  foreach ($roleItems as $ri) {
    $iid = (int)($ri['itemId'] ?? 0);
    if ($iid === 800) $hasPSA = true;
    if ($iid === 428) $hasTourGuide = true;
  }
}

$stmt = db()->prepare('SELECT COUNT(*) AS c FROM inventories WHERE userId = ?');
$stmt->bind_param('i', $uid);
$stmt->execute();
$invCountRow = $stmt->get_result()->fetch_assoc();
$stmt->close();
$invCount = (int)($invCountRow['c'] ?? 0);

// Bans
$stmt = db()->prepare('SELECT id, issued, expires, moderatorId, message FROM bans WHERE userId = ? ORDER BY issued DESC LIMIT 10');
$stmt->bind_param('i', $uid);
$stmt->execute();
$bans = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$stmt = db()->prepare('SELECT COUNT(*) AS c FROM bans WHERE userId = ?');
$stmt->bind_param('i', $uid);
$stmt->execute();
$banCountRow = $stmt->get_result()->fetch_assoc();
$stmt->close();
$banCount = (int)($banCountRow['c'] ?? 0);

// Current ban info (top-of-page alert)
// IMPORTANT: don't assume the most-recent ban is the active one. Scan for any unexpired ban.
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

// For display strings
$banForBanner = $isPerma ? ($bans[0] ?? null) : $currentBan;
$banIssuedTs = $banForBanner ? parent_parse_time($banForBanner['issued'] ?? null) : null;
$banExpiresTs = $banForBanner ? parent_parse_time($banForBanner['expires'] ?? null) : null;

// Puffle typeId -> asset name mapping (drop images like blue.png, red.png, black.png, etc into /parent_center/assets/puffles/)
function pc_puffle_asset_name(int $typeId): string {
  // Classic Club Penguin puffle color IDs:
  // 0 blue, 1 pink, 2 black, 3 green, 4 purple, 5 red, 6 yellow, 7 white, 8 orange
  return match ($typeId) {
    0 => 'blue',
    1 => 'pink',
    2 => 'black',
    3 => 'green',
    4 => 'purple',
    5 => 'red',
    6 => 'yellow',
    7 => 'white',
    8 => 'orange',
    default => 'blue',
  };
}

function pc_puffle_label(int $typeId): string {
  return match ($typeId) {
    0 => 'Blue',
    1 => 'Pink',
    2 => 'Black',
    3 => 'Green',
    4 => 'Purple',
    5 => 'Red',
    6 => 'Yellow',
    7 => 'White',
    8 => 'Orange',
    default => 'Puffle',
  };
}

$pageTitle = 'Parent Center - ' . ($child['username'] ?? 'Child');
include __DIR__ . '/includes/parent_header.php';

// Big playercard uses layered PNGs (your existing renderer)
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

    <!-- Header -->
    <div class="pc-actions" style="justify-content:space-between; margin-bottom:14px;">
      <div>
        <!--<h1 class="pc-title" style="margin:0;"><?php echo e($child['username']); ?></h1>-->
        <div style="display:flex; gap:14px; align-items:center; margin-bottom:12px;">
          <div class="pc-avatarSlot" style="width:96px; height:96px;">
            <?php echo parent_profile_avatar_clothed_html($child, 96, 'pc-avatar'); ?>
            <?php if ($isCurrentlyBanned): ?>
              <span class="pc-modBadge" title="This account is banned.">
                <img src="assets/badge.svg" alt="Banned">
              </span>
            <?php endif; ?>
          </div>

          <div style="min-width:0;">
            <div style="font-weight:1000; font-size:18px; color:var(--cp-ink); line-height:1.15;">
              <?php echo e($child['username']); ?>
            </div>
            <div class="pc-muted" style="margin:4px 0 0;">
              Coins: <strong><?php echo number_format((int)($child['coins'] ?? 0)); ?></strong>
              &nbsp;•&nbsp;
              Rank: <strong><?php echo (int)($child['rank'] ?? 0); ?></strong>
            </div>

            <div style="margin-top:8px;">
              <?php if ($isCurrentlyBanned): ?>
                <span class="pc-pill pc-pill--banned">Banned</span>
              <?php else: ?>
                <span class="pc-pill">Active</span>
              <?php endif; ?>
              <?php if ($isPerma): ?>
                <span class="pc-pill pc-pill--banned" style="margin-left:6px;">Perma</span>
              <?php endif; ?>
            </div>

            <?php if ($hasPSA || $hasTourGuide): ?>
              <div class="pc-roleBadges" aria-label="Special roles" style="margin-top:10px;">
                <?php if ($hasPSA): ?>
                  <span class="pc-rolePill" title="PSA (Secret Agent)">
                    <img class="pc-roleIcon" src="/yukon/assets/media/clothing/icon/240/800.png" alt="PSA">
                    <span class="pc-roleText">PSA Agent</span>
                  </span>
                <?php endif; ?>
                <?php if ($hasTourGuide): ?>
                  <span class="pc-rolePill" title="Tour Guide">
                    <img class="pc-roleIcon" src="/yukon/assets/media/clothing/icon/240/428.png" alt="Tour Guide">
                    <span class="pc-roleText">Tour Guide</span>
                  </span>
                <?php endif; ?>
              </div>
            <?php endif; ?>
          </div>
        </div>
        <?php if (!empty($child['alias'])): ?>
          <div class="pc-muted" style="margin:6px 0 0;">Parent label: <strong><?php echo e($child['alias']); ?></strong></div>
        <?php endif; ?>
      </div>

      <div class="pc-actions">
        <a class="pc-btnSm" href="dashboard.php">Back</a>
        <a class="pc-btnSm" href="/manage_penguin/">Open Manage My Penguin</a>
      </div>
    </div>

    <?php echo pc_child_nav($uid, 'overview'); ?>

    <!-- BANNED banner (very obvious) -->
    <?php if ($isCurrentlyBanned): ?>
      <?php
        // Use the *active* ban (or perma flag) for the banner.
        $banMsg = $banForBanner ? trim((string)($banForBanner['message'] ?? '')) : '';
        $banTitle = '';
        $banSub = '';

        if ($isPerma) {
          $banTitle = '<strong>' . e($child['username']) . '</strong> is permanently banned.';
          $banSub = 'This account cannot log in.';
        } else {
          $banTitle = '<strong>' . e($child['username']) . '</strong> is currently banned.';
          if ($banExpiresTs !== null) {
            $banSub = 'Ban expires ' . parent_human_datetime((int)$banExpiresTs) . '.';
          } else {
            $banSub = 'Ban is currently active.';
          }
        }
      ?>
      <div class="pc-banner pc-banner--danger" role="alert" aria-label="Ban alert" style="margin:10px 0 18px;">
        <div class="pc-bannerIcon" aria-hidden="true">
          <img src="assets/badge.svg" alt="">
        </div>
        <div class="pc-bannerBody">
          <div class="pc-bannerTitle"><?php echo $banTitle; ?></div>
          <div class="pc-bannerSub">
            <?php echo e($banSub); ?>
            <?php if ($banMsg !== ''): ?>
              <span class="pc-bannerMsg">• <?php echo e($banMsg); ?></span>
            <?php endif; ?>
          </div>
        </div>
        <div class="pc-bannerActions">
          <a class="pc-btnSm" href="child_bans.php?uid=<?php echo (int)$uid; ?>">View ban history</a>
        </div>
      </div>
    <?php endif; ?>

    <!-- Profile hero -->
    <div class="pc-grid2">
      <!-- Left: Avatar + Playercard -->
      <div>
        <?php $equipped = pc_equipped_items($child); unset($equipped['Color']); ?>
        <div class="pc-playercardBox pc-playercardBox--stack">
          <div class="pc-playercardWrap">
            <?php render_penguin_avatar($child, 300); ?>
          </div>

          <div class="pc-playercardDivider" aria-hidden="true"></div>

          <div class="pc-playercardEquip">
            <div class="pc-equippedHeader" style="margin:0 0 10px;">
              <div class="pc-equippedTitle">Equipped</div>
              <div class="pc-equippedHint">Items currently in-use.</div>
            </div>

            <?php if (!$equipped): ?>
              <div class="pc-empty" style="margin:0;">No equipped items.</div>
            <?php else: ?>
              <div class="pc-equippedGrid">
                <?php foreach ($equipped as $slot => $itemId): ?>
                  <div class="pc-equippedItem" title="<?php echo e($slot); ?>: <?php echo (int)$itemId; ?>">
                    <img alt="" src="<?php echo e(pc_clothing_icon_url((int)$itemId, 120)); ?>" loading="lazy">
                    <div class="pc-equippedSlot"><?php echo e($slot); ?></div>
                    <div class="pc-equippedId"><?php echo (int)$itemId; ?></div>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <!-- Right: Account info -->
      <div>
        <div style="<?php echo $isCurrentlyBanned ? 'border:2px solid rgba(220,53,69,0.22); background:rgba(220,53,69,0.04);' : ''; ?> border-radius:18px;">
          <div class="pc-tableWrap" style="min-width:0;">
            <table class="pc-table" style="min-width:0;">
              <thead>
                <tr><th colspan="2">Account Info</th></tr>
              </thead>
              <tbody>
                <tr>
                  <td style="width:160px; font-weight:900;">Joined</td>
                  <td><?php echo e(parent_human_datetime($child['joinTime']) ?: (string)$child['joinTime']); ?></td>
                </tr>
                <tr>
                  <td style="font-weight:900;">Ninja</td>
                  <td>Rank <?php echo (int)($child['ninjaRank'] ?? 0); ?> • Progress <?php echo (int)($child['ninjaProgress'] ?? 0); ?>%</td>
                </tr>
                <!--<tr>
                  <td style="font-weight:900;">Outfit</td>
                  <td style="line-height:1.35;">
                    See <strong>Currently Equipped</strong> inside the playercard box.
                  </td>
                </tr>-->
              </tbody>
            </table>
          </div>
        </div>

        <!-- Overview KPIs + quick actions -->
        <div style="margin-top:14px;">
          <div class="pc-tableWrap" style="padding:14px;">
            <div class="pc-sectionHeaderRow">
              <div class="pc-sectionHeaderTitle">Overview</div>
              <!--<div class="pc-sectionHeaderHint">Use the tabs to view detailed pages.</div>-->
            </div>

            <div class="pc-kpiGrid">
              <div class="pc-kpi">
                <div class="pc-kpiLabel">Clothing items</div>
                <div class="pc-kpiValue"><?php echo number_format($invCount); ?></div>
                <div class="pc-kpiSub">Preview shows first <?php echo count($inv); ?></div>
              </div>

              <div class="pc-kpi">
                <div class="pc-kpiLabel">Puffles</div>
                <div class="pc-kpiValue"><?php echo number_format($petsCount); ?></div>
                <div class="pc-kpiSub">Preview shows latest <?php echo count($pets); ?></div>
              </div>

              <div class="pc-kpi">
                <div class="pc-kpiLabel">Ban records</div>
                <div class="pc-kpiValue"><?php echo number_format($banCount); ?></div>
                <div class="pc-kpiSub"><?php echo $isCurrentlyBanned ? 'Currently banned' : 'Not currently banned'; ?></div>
              </div>
            </div>

            <!--<div style="display:flex; gap:10px; flex-wrap:wrap; margin-top:12px;">
              <a class="pc-btnSm" href="child_inventory.php?uid=<?php echo (int)$uid; ?>">Open inventory</a>
              <a class="pc-btnSm" href="child_puffles.php?uid=<?php echo (int)$uid; ?>">Open puffles</a>
              <a class="pc-btnSm" href="child_bans.php?uid=<?php echo (int)$uid; ?>">Open bans</a>
              <a class="pc-btnSm" href="child_chat.php?uid=<?php echo (int)$uid; ?>">Chat history</a>
              <a class="pc-btnSm" href="child_friends.php?uid=<?php echo (int)$uid; ?>">Friends</a>
            </div>-->
          </div>

            <div style="margin-top:14px;" class="pc-panel">
              <div class="pc-panelHeader">Clothing Inventory Preview</div>
              <div style="padding:14px 16px 16px;">
                <?php if (!$inv): ?>
                  <div class="pc-empty">No clothing items found.</div>
                <?php else: ?>
                  <div class="pc-invGrid">
                    <?php foreach ($inv as $it): $itemId = (int)$it['itemId']; ?>
                      <div class="pc-invItem">
                        <img alt="" src="<?php echo e(pc_clothing_icon_url($itemId, 120)); ?>" loading="lazy">
                        <div class="pc-invId"><?php echo $itemId; ?></div>
                      </div>
                    <?php endforeach; ?>
                  </div>
                  <div style="margin-top:10px; display:flex; justify-content:flex-end;">
                    <a class="pc-btnSm" href="child_inventory.php?uid=<?php echo (int)$uid; ?>">View all (<?php echo number_format($invCount); ?>)</a>
                  </div>
                <?php endif; ?>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Preview: Inventory + Bans (lightweight) -->
    <div class="pc-grid2b" style="margin-top:14px;">
      <div class="pc-panel">
        <div class="pc-panelHeader">Ban Preview</div>
        <div style="padding:14px 16px 16px;">
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
            <div style="margin-top:10px; display:flex; justify-content:flex-end;">
              <a class="pc-btnSm" href="child_bans.php?uid=<?php echo (int)$uid; ?>">View all (<?php echo number_format($banCount); ?>)</a>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>

  </div>
</div>

<?php require_once __DIR__ . '/includes/parent_footer.php'; ?>
