<?php
require_once __DIR__ . '/includes/admin_bootstrap.php';
admin_require_staff();

$title = 'Dashboard';
$activeNav = 'dashboard';

$db = db();

// --- Metrics ---
$totalUsers = 0;
$activeBans = 0;
$bans7d = 0;
$newUsers7d = 0;

// Extra widgets
$worlds = [];
$totalPopulation = 0;
$newestUsers = [];
$topModerators30d = [];
$permaBans = 0;
$tempActiveBans = 0;

// Total users
$r = $db->query('SELECT COUNT(*) AS c FROM users');
if ($r) { $row = $r->fetch_assoc(); $totalUsers = (int)($row['c'] ?? 0); }

// New users last 7 days
$r = $db->query('SELECT COUNT(*) AS c FROM users WHERE joinTime >= (NOW() - INTERVAL 7 DAY)');
if ($r) { $row = $r->fetch_assoc(); $newUsers7d = (int)($row['c'] ?? 0); }

// Bans last 7 days (ban records)
$r = $db->query('SELECT COUNT(*) AS c FROM bans WHERE issued >= (NOW() - INTERVAL 7 DAY)');
if ($r) { $row = $r->fetch_assoc(); $bans7d = (int)($row['c'] ?? 0); }

// Active bans: union of permaBan users + unexpired ban records
// We count distinct user IDs across both sources.
$sqlActive = "
    SELECT COUNT(DISTINCT t.userId) AS c
    FROM (
      SELECT id AS userId FROM users WHERE permaBan = 1
      UNION ALL
      SELECT userId FROM bans WHERE expires > NOW()
    ) t
";
$r = $db->query($sqlActive);
if ($r) { $row = $r->fetch_assoc(); $activeBans = (int)($row['c'] ?? 0); }

// Permanent bans (from users)
$r = $db->query('SELECT COUNT(*) AS c FROM users WHERE permaBan = 1');
if ($r) { $row = $r->fetch_assoc(); $permaBans = (int)($row['c'] ?? 0); }

// Active temporary bans (from bans)
$r = $db->query('SELECT COUNT(DISTINCT userId) AS c FROM bans WHERE expires > NOW()');
if ($r) { $row = $r->fetch_assoc(); $tempActiveBans = (int)($row['c'] ?? 0); }

// Worlds / population
$r = $db->query('SELECT id, population FROM worlds ORDER BY population DESC, id ASC');
if ($r) {
    while ($row = $r->fetch_assoc()) {
        $worlds[] = $row;
        $totalPopulation += (int)($row['population'] ?? 0);
    }
}

// Newest users
$r = $db->query('SELECT id, username, joinTime, rank FROM users ORDER BY joinTime DESC LIMIT 8');
if ($r) {
    while ($row = $r->fetch_assoc()) {
        $newestUsers[] = $row;
    }
}

// Top moderators by bans issued (last 30 days)
$sqlTopMods = "
  SELECT b.moderatorId, m.username AS moderatorUser, COUNT(*) AS c
  FROM bans b
  LEFT JOIN users m ON m.id = b.moderatorId
  WHERE b.moderatorId IS NOT NULL
    AND b.issued >= (NOW() - INTERVAL 30 DAY)
  GROUP BY b.moderatorId
  ORDER BY c DESC, b.moderatorId ASC
  LIMIT 5
";
$r = $db->query($sqlTopMods);
if ($r) {
    while ($row = $r->fetch_assoc()) {
        $topModerators30d[] = $row;
    }
}

// Recent bans list
$recentBans = [];
$sqlRecent = "
    SELECT
      b.id,
      b.userId,
      u.username AS bannedUser,
      b.moderatorId,
      m.username AS moderatorUser,
      b.issued,
      b.expires,
      b.message,
      u.permaBan
    FROM bans b
    JOIN users u ON u.id = b.userId
    LEFT JOIN users m ON m.id = b.moderatorId
    ORDER BY b.issued DESC
    LIMIT 10
";
$r = $db->query($sqlRecent);
if ($r) {
    while ($row = $r->fetch_assoc()) {
        $recentBans[] = $row;
    }
}

function adm_fmt_ts($ts): string {
    if (!$ts) return '—';
    // MySQL may send 0000-00-00 00:00:00 for permanent bans in some installs.
    if ($ts === '0000-00-00 00:00:00') return '—';
    $t = strtotime($ts);
    if ($t <= 0) return e($ts);
    return date('Y-m-d H:i', $t);
}

function adm_ban_status(array $b): array {
    $perma = (int)($b['permaBan'] ?? 0) === 1;
    $expires = (string)($b['expires'] ?? '');

    if ($perma || $expires === '0000-00-00 00:00:00') {
        return ['Permanent', 'adm-pill adm-pill--perma'];
    }

    $tExp = strtotime($expires);
    if ($tExp > time()) return ['Active', 'adm-pill adm-pill--active'];
    return ['Expired', 'adm-pill'];
}

function adm_pct(int $part, int $total): int {
    if ($total <= 0) return 0;
    $p = (int)round(($part / $total) * 100);
    if ($p < 0) return 0;
    if ($p > 100) return 100;
    return $p;
}

include __DIR__ . '/includes/admin_header.php';
?>

  <section class="adm-hero">
    <div class="adm-hero__copy">
      <h1 class="adm-h1">Administration Dashboard</h1>
      <p class="adm-muted">High-level overview of your Yukon install (users & moderation).</p>
    </div>
    <div class="adm-hero__art" aria-hidden="true"></div>
  </section>

  <section class="adm-grid">
    <article class="adm-stat">
      <div class="adm-stat__top">
        <div class="adm-stat__label">Total Registered Users</div>
        <div class="adm-stat__icon" aria-hidden="true">👥</div>
      </div>
      <div class="adm-stat__value"><?php echo number_format($totalUsers); ?></div>
      <div class="adm-stat__sub adm-muted">All accounts in <code>users</code></div>
    </article>

    <article class="adm-stat">
      <div class="adm-stat__top">
        <div class="adm-stat__label">Active Bans</div>
        <div class="adm-stat__icon" aria-hidden="true">⛔</div>
      </div>
      <div class="adm-stat__value"><?php echo number_format($activeBans); ?></div>
      <div class="adm-stat__sub adm-muted">Includes <code>users.permaBan</code> + unexpired <code>bans</code></div>
    </article>

    <article class="adm-stat">
      <div class="adm-stat__top">
        <div class="adm-stat__label">Bans (Last 7 Days)</div>
        <div class="adm-stat__icon" aria-hidden="true">🧾</div>
      </div>
      <div class="adm-stat__value"><?php echo number_format($bans7d); ?></div>
      <div class="adm-stat__sub adm-muted">From <code>bans.issued</code></div>
    </article>

    <article class="adm-stat">
      <div class="adm-stat__top">
        <div class="adm-stat__label">New Users (Last 7 Days)</div>
        <div class="adm-stat__icon" aria-hidden="true">✨</div>
      </div>
      <div class="adm-stat__value"><?php echo number_format($newUsers7d); ?></div>
      <div class="adm-stat__sub adm-muted">From <code>users.joinTime</code></div>
    </article>
  </section>

  <section class="adm-grid2">
    <article class="adm-card adm-card--tight">
      <div class="adm-card__head adm-card__head--row">
        <div>
          <h2 class="adm-h2">Worlds & Population</h2>
          <p class="adm-muted">Live server populations from <code>worlds</code>.</p>
        </div>
        <div class="adm-kpi">
          <div class="adm-kpi__label">Total Online</div>
          <div class="adm-kpi__value"><?php echo number_format($totalPopulation); ?></div>
        </div>
      </div>

      <?php if (!$worlds): ?>
        <div class="adm-empty adm-muted">No worlds found.</div>
      <?php else: ?>
        <div class="adm-worlds">
          <?php
            $maxPop = 0;
            foreach ($worlds as $w) { $maxPop = max($maxPop, (int)($w['population'] ?? 0)); }
            if ($maxPop <= 0) $maxPop = 1;
          ?>
          <?php foreach ($worlds as $w):
            $pop = (int)($w['population'] ?? 0);
            $pct = min(100, max(0, (int)round(($pop / $maxPop) * 100)));
          ?>
            <div class="adm-world">
              <div class="adm-world__row">
                <div class="adm-world__name"><?php echo e($w['id'] ?? 'World'); ?></div>
                <div class="adm-world__pop"><?php echo number_format($pop); ?></div>
              </div>
              <div class="adm-bar" aria-hidden="true"><span style="width:<?php echo (int)$pct; ?>%"></span></div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </article>

    <article class="adm-card adm-card--tight">
      <div class="adm-card__head adm-card__head--row">
        <div>
          <h2 class="adm-h2">Newest Accounts</h2>
          <p class="adm-muted">Latest registrations from <code>users.joinTime</code>.</p>
        </div>
      </div>

      <?php if (!$newestUsers): ?>
        <div class="adm-empty adm-muted">No users found.</div>
      <?php else: ?>
        <div class="adm-list">
          <?php foreach ($newestUsers as $u): ?>
            <div class="adm-listItem">
              <div class="adm-listItem__main">
                <div class="adm-listItem__title"><?php echo e($u['username'] ?? ('#' . (int)($u['id'] ?? 0))); ?></div>
                <div class="adm-listItem__sub adm-muted">
                  <?php echo e(rank_title((int)($u['rank'] ?? 0))); ?> · Joined <?php echo e(adm_fmt_ts($u['joinTime'] ?? '')); ?>
                </div>
              </div>
              <div class="adm-listItem__meta adm-muted">ID <?php echo (int)($u['id'] ?? 0); ?></div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </article>
  </section>

  <section class="adm-grid2">
    <article class="adm-card adm-card--tight">
      <div class="adm-card__head adm-card__head--row">
        <div>
          <h2 class="adm-h2">Bans Overview</h2>
          <p class="adm-muted">Active bans by type (permanent vs temporary).</p>
        </div>
      </div>

      <?php
        $banTotal = max(1, (int)$activeBans);
        $permaPct = (int)round(($permaBans / $banTotal) * 100);
        $tempPct = (int)round(($tempActiveBans / $banTotal) * 100);
        // If rounding drift, clamp to 100.
        if (($permaPct + $tempPct) > 100) {
          $over = ($permaPct + $tempPct) - 100;
          if ($tempPct >= $over) $tempPct -= $over;
          else $permaPct = max(0, $permaPct - $over);
        }
      ?>

      <div class="adm-splitBar" aria-hidden="true">
        <span class="adm-splitBar__perma" style="width:<?php echo (int)$permaPct; ?>%"></span>
        <span class="adm-splitBar__temp" style="width:<?php echo (int)$tempPct; ?>%"></span>
      </div>

      <div class="adm-badges">
        <div class="adm-badge">
          <div class="adm-badge__dot adm-badge__dot--perma" aria-hidden="true"></div>
          <div>
            <div class="adm-badge__label">Permanent</div>
            <div class="adm-badge__value"><?php echo number_format($permaBans); ?></div>
          </div>
        </div>
        <div class="adm-badge">
          <div class="adm-badge__dot adm-badge__dot--temp" aria-hidden="true"></div>
          <div>
            <div class="adm-badge__label">Temporary (Active)</div>
            <div class="adm-badge__value"><?php echo number_format($tempActiveBans); ?></div>
          </div>
        </div>
        <div class="adm-badge">
          <div class="adm-badge__dot" aria-hidden="true"></div>
          <div>
            <div class="adm-badge__label">Total Active</div>
            <div class="adm-badge__value"><?php echo number_format($activeBans); ?></div>
          </div>
        </div>
      </div>
    </article>

    <article class="adm-card adm-card--tight">
      <div class="adm-card__head adm-card__head--row">
        <div>
          <h2 class="adm-h2">Top Moderators</h2>
          <p class="adm-muted">Bans issued in the last 30 days.</p>
        </div>
      </div>

      <?php if (!$topModerators30d): ?>
        <div class="adm-empty adm-muted">No moderator ban activity in the last 30 days.</div>
      <?php else: ?>
        <div class="adm-list">
          <?php foreach ($topModerators30d as $m): ?>
            <div class="adm-listItem">
              <div class="adm-listItem__main">
                <div class="adm-listItem__title"><?php echo e($m['moderatorUser'] ?? ('#' . (int)($m['moderatorId'] ?? 0))); ?></div>
                <div class="adm-listItem__sub adm-muted">ID <?php echo (int)($m['moderatorId'] ?? 0); ?></div>
              </div>
              <div class="adm-countPill" title="Bans issued (last 30 days)"><?php echo number_format((int)($m['c'] ?? 0)); ?></div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </article>
  </section>

  <section class="adm-card">
    <div class="adm-card__head">
      <h2 class="adm-h2">Most Recent Bans</h2>
      <p class="adm-muted">Latest 10 ban records (with reason and status).</p>
    </div>

    <div class="adm-tableWrap">
      <table class="adm-table">
        <thead>
          <tr>
            <th>User</th>
            <th>Moderator</th>
            <th>Issued</th>
            <th>Expires</th>
            <th>Reason</th>
            <th>Status</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!$recentBans): ?>
            <tr><td colspan="6" class="adm-muted">No ban records found.</td></tr>
          <?php else: ?>
            <?php foreach ($recentBans as $b):
              [$statusText, $statusCls] = adm_ban_status($b);
              $reason = (string)($b['message'] ?? '');
              if ($reason === '') $reason = '—';
            ?>
              <tr>
                <td>
                  <div class="adm-user">
                    <div class="adm-user__name"><?php echo e($b['bannedUser'] ?? ('#' . (int)$b['userId'])); ?></div>
                    <div class="adm-user__sub adm-muted">ID <?php echo (int)$b['userId']; ?></div>
                  </div>
                </td>
                <td>
                  <?php if (!empty($b['moderatorId'])): ?>
                    <div class="adm-user">
                      <div class="adm-user__name"><?php echo e($b['moderatorUser'] ?? ('#' . (int)$b['moderatorId'])); ?></div>
                      <div class="adm-user__sub adm-muted">ID <?php echo (int)$b['moderatorId']; ?></div>
                    </div>
                  <?php else: ?>
                    <span class="adm-muted">—</span>
                  <?php endif; ?>
                </td>
                <td><?php echo e(adm_fmt_ts($b['issued'] ?? '')); ?></td>
                <td><?php echo e(adm_fmt_ts($b['expires'] ?? '')); ?></td>
                <td class="adm-reason"><?php echo e($reason); ?></td>
                <td><span class="<?php echo e($statusCls); ?>"><?php echo e($statusText); ?></span></td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </section>

  <section class="adm-card adm-card--muted">
    <div class="adm-card__head">
      <h2 class="adm-h2">Notes</h2>
      <p class="adm-muted">
        This is a read-only v1 dashboard. Next steps can include user search, ban management actions (CSRF + audit log),
        and charts for joins/bans over time.
      </p>
    </div>
  </section>

<?php include __DIR__ . '/includes/admin_footer.php'; ?>
