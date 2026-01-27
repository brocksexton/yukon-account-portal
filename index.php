<?php
require_once __DIR__ . '/includes/bootstrap.php';
$pageTitle = SITE_NAME . ' - Home';
require_once __DIR__ . '/includes/header.php';

$user = current_user();

function rank_label(int $rank): string {
    switch ($rank) {
        case 4: return 'Owner';
        case 3: return 'Administrator';
        case 2: return 'Moderator';
        default: return 'Player';
    }
}

function fmt_date_from_ts($ts): string {
    $n = (int)$ts;
    if ($n <= 0) return '—';
    // Guard if DB stored milliseconds
    if ($n > 2000000000) $n = (int)round($n / 1000);
    return date('M j, Y', $n);
}

/**
 * Phase 1 (placeholder): example posts for the "What's New" panel.
 * Phase 2 (WordPress): replace this function with a small fetch from your WP REST API,
 * then map the returned posts into the same array shape.
 */
function home_news_posts(): array {
    return [
        [
            'title' => 'Outfit Creators: Public Store Preview',
            'date'  => 'Coming soon',
            'tag'   => 'Outfits',
            'excerpt' => 'We’re preparing a public outfit gallery where creators can publish looks and (later) collect commissions.',
            'href'  => '#',
        ],
        [
            'title' => 'Quality-of-life Updates',
            'date'  => 'In progress',
            'tag'   => 'Website',
            'excerpt' => 'Homepage and tools are getting a polish pass: better layouts, clearer actions, and subtle animations.',
            'href'  => '#',
        ],
        [
            'title' => 'Party Announcements',
            'date'  => 'When ready',
            'tag'   => 'Events',
            'excerpt' => 'Events will be posted in our blog feed once WordPress is wired in. Keep an eye on “What’s New”.',
            'href'  => '#',
        ],
    ];
}
?>

<div class="cp-hero">
  <div class="cp-hero-inner">
    <div class="cp-hero-pill cp-anim" style="--d:80ms">
      <?php if ($user): ?>
        <h2>Welcome back, <?php echo e($user['username']); ?>!</h2>
        <p>Ready to waddle? Jump in, tweak your look, or check what’s new.</p>
        <div class="cp-hero-actions">
          <a href="<?php echo e(PLAY_URL); ?>">
            <button class="link-button_button__xL9TZ link-button_white__w6vRI cp-btn-blue" style="min-width:220px">
              <div class="link-button_text__PoH2c">Play Now<div class="link-button_outline__1volg">Play Now</div></div>
            </button>
          </a>
          <a href="/manage_penguin/">
            <button class="link-button_button__xL9TZ link-button_white__w6vRI" style="min-width:220px">
              <div class="link-button_text__PoH2c">Manage My Penguin<div class="link-button_outline__1volg">Manage My Penguin</div></div>
            </button>
          </a>
          <?php if (DISCORD_URL): ?>
            <a href="<?php echo e(DISCORD_URL); ?>" target="_blank" rel="noopener">
              <button class="link-button_button__xL9TZ link-button_white__w6vRI" style="min-width:220px">
                <div class="link-button_text__PoH2c">Discord<div class="link-button_outline__1volg">Discord</div></div>
              </button>
            </a>
          <?php endif; ?>
        </div>
      <?php else: ?>
        <h2>Waddle Around and Meet New Friends!</h2>
        <p><?php echo e(SITE_TAGLINE); ?></p>
        <div class="cp-hero-actions">
          <a href="login.php">
            <button class="link-button_button__xL9TZ link-button_yellow__Tdvvb" style="min-width:220px">
              <div class="link-button_text__PoH2c">Login<div class="link-button_outline__1volg">Login</div></div>
            </button>
          </a>
          <a href="<?php echo e(PLAY_URL); ?>">
            <button class="link-button_button__xL9TZ link-button_white__w6vRI cp-btn-blue" style="min-width:220px">
              <div class="link-button_text__PoH2c">Play Now<div class="link-button_outline__1volg">Play Now</div></div>
            </button>
          </a>
        </div>
        <div class="cp-hero-meta">
          <span class="cp-pill" style="background:rgba(255,255,255,0.92)">Friends-only • Invite code required</span>
          <span class="cp-pill" style="background:rgba(255,255,255,0.92)">Existing players can log in</span>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php if ($user): ?>
  <?php require_once __DIR__ . '/includes/avatar_component.php'; ?>

  <div class="cp-home-wrap">
    <div class="cp-home-grid">

      <section class="cp-home-card cp-panel cp-anim" style="--d:140ms">
        <div class="cp-home-cardHeader">
          <h3 class="cp-title" style="margin:0">Your Penguin</h3>
          <div class="cp-sub" style="margin-top:2px">Account overview and quick actions.</div>
        </div>

        <div class="cp-home-profile">
          <div class="cp-home-avatar">
            <?php render_penguin_avatar($user, 240); ?>
          </div>

          <div class="cp-home-stats">
            <div class="cp-kv">
              <div><b>Coins</b><?php echo number_format((int)($user['coins'] ?? 0)); ?></div>
              <div><b>Rank</b><?php echo e(rank_label((int)($user['rank'] ?? 0))); ?></div>
              <div><b>Member Since</b><?php echo e(fmt_date_from_ts($user['joinTime'] ?? 0)); ?></div>
              <div><b>Ninja</b><?php echo ((int)($user['ninjaRank'] ?? 0) > 0) ? ('Rank ' . (int)$user['ninjaRank']) : 'Not started'; ?></div>
            </div>

            <div class="cp-home-shortcuts">
              <a class="feature-card_button__3CdS5" href="/manage_penguin/">Manage My Penguin</a>
              <a class="feature-card_button__3CdS5" href="/manage_penguin/studio.php">Outfit Studio</a>
              <a class="feature-card_button__3CdS5" href="/manage_penguin/pages/public_outfits.php">Public Outfits Store</a>
              <a class="feature-card_button__3CdS5 cp-disabledLink" href="#" onclick="return false;" aria-disabled="true" title="Coming soon">Collect Commissions (Soon)</a>
            </div>

            <div class="cp-home-note">
              Tip: If your outfit looks outdated, open Outfit Studio once to sync your latest in-game items.
            </div>
          </div>
        </div>
      </section>

      <section class="cp-home-card cp-panel cp-anim" style="--d:200ms">
        <div class="cp-home-cardHeader">
          <h3 class="cp-title" style="margin:0">What’s New</h3>
          <div class="cp-sub" style="margin-top:2px">News posts will come from WordPress once it’s connected.</div>
        </div>

        <div class="cp-posts">
          <?php foreach (home_news_posts() as $i => $p): ?>
            <a class="cp-post" href="<?php echo e($p['href']); ?>" onclick="<?php echo ($p['href'] === '#') ? 'return false;' : ''; ?>" style="--d:<?php echo (260 + ($i * 70)); ?>ms">
              <div class="cp-postTop">
                <span class="cp-postTag"><?php echo e($p['tag']); ?></span>
                <span class="cp-postDate"><?php echo e($p['date']); ?></span>
              </div>
              <div class="cp-postTitle"><?php echo e($p['title']); ?></div>
              <div class="cp-postExcerpt"><?php echo e($p['excerpt']); ?></div>
              <div class="cp-postMore">Read more</div>
            </a>
          <?php endforeach; ?>
        </div>

        <div class="cp-home-note" style="margin-top:14px">
          Currently Displaying Dummy Data.
        </div>
      </section>

    </div>
  </div>

<?php else: ?>

  <div class="cp-home-wrap" id="invite-codes">
    <div class="cp-home-grid">

      <section class="cp-home-card cp-panel cp-anim" style="--d:140ms">
        <h3 class="cp-title" style="margin-top:0">How Joining Works</h3>
        <p class="cp-sub" style="margin-top:0">
          This is a friends-only island. New accounts require a <b>registration code</b> from someone already inside.
          Each code can be used for <b>up to two registrations</b>. When those are used, the owner can request more.
        </p>

        <div class="cp-home-steps">
          <div class="cp-home-step">
            <div class="cp-home-stepNum">1</div>
            <div class="cp-home-stepBody">
              <b>Get a registration code</b>
              <div>A friend shares their unique code with you.</div>
            </div>
          </div>
          <div class="cp-home-step">
            <div class="cp-home-stepNum">2</div>
            <div class="cp-home-stepBody">
              <b>Create your penguin</b>
              <div>Registration will require a valid code (setup coming soon).</div>
            </div>
          </div>
          <div class="cp-home-step">
            <div class="cp-home-stepNum">3</div>
            <div class="cp-home-stepBody">
              <b>Log in and play</b>
              <div>Once your account exists, log in here and launch the game.</div>
            </div>
          </div>
        </div>

        <div class="cp-home-inlineCta">
          <a href="login.php" class="feature-card_button__3CdS5">Login</a>
          <a href="<?php echo e(PLAY_URL); ?>" class="feature-card_button__3CdS5">Play Now</a>
        </div>
      </section>

      <section class="cp-home-card cp-panel cp-anim" style="--d:200ms">
        <h3 class="cp-title" style="margin-top:0">What You Can Do</h3>
        <div class="cp-home-featureGrid">
          <div class="cp-home-miniFeature">
            <b>Waddle & chat</b>
            <div>Hang out in rooms, emote, and meet friends.</div>
          </div>
          <div class="cp-home-miniFeature">
            <b>Customize your penguin</b>
            <div>Change color, style outfits, and show off your vibe.</div>
          </div>
          <div class="cp-home-miniFeature">
            <b>Manage your account</b>
            <div>View your penguin card, coins, and account settings.</div>
          </div>
          <div class="cp-home-miniFeature">
            <b>Parent Center</b>
            <div>Linked accounts and safety tools for families.</div>
          </div>
        </div>
      </section>

    </div>
  </div>

<?php endif; ?>

<div class="index_cardContainer__wj_HB cp-home-cards cp-anim" style="--d:240ms">
  <div class="row g-0">
    <div class="col-12 col-lg-2.4 p-0">
      <div class="feature-card_card__uPPhs">
        <div class="feature-card_left__QM9QG"><img src="_next/about3c22.webp" alt="About" style="width:100%;height:auto;max-width:100%"/></div>
        <div class="feature-card_right__X0gx3">
          <h3 class="feature-card_heading__R5fVX"><?php echo $user ? 'Your Community' : 'Invite Codes'; ?></h3>
          <div class="feature-card_body__EED8H">
            <?php if ($user): ?>
              Private friend-group server. Keep it cozy, keep it fun.
            <?php else: ?>
              Friends-only access. A registration code is required to create an account.
            <?php endif; ?>
          </div>
          <?php if ($user && DISCORD_URL): ?>
            <a href="<?php echo e(DISCORD_URL); ?>" target="_blank" rel="noopener" class="feature-card_button__3CdS5">Open Discord</a>
          <?php else: ?>
            <a href="#invite-codes" class="feature-card_button__3CdS5">How invites work</a>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <div class="col-12 col-lg-2.4 p-0">
      <div class="feature-card_card__uPPhs">
        <div class="feature-card_left__QM9QG"><img src="_next/activities591f.webp" alt="Manage" style="width:100%;height:auto;max-width:100%"/></div>
        <div class="feature-card_right__X0gx3">
          <h3 class="feature-card_heading__R5fVX">Manage My Penguin</h3>
          <div class="feature-card_body__EED8H">View your penguin, coins, outfit, and account settings.</div>
          <a href="/manage_penguin/" class="feature-card_button__3CdS5"><?php echo $user ? 'Open' : 'Login required'; ?></a>
        </div>
      </div>
    </div>

    <div class="col-12 col-lg-2.4 p-0">
      <div class="feature-card_card__uPPhs">
        <div class="feature-card_left__QM9QG"><img src="_next/parties56f4.webp" alt="Play" style="width:100%;height:auto;max-width:100%"/></div>
        <div class="feature-card_right__X0gx3">
          <h3 class="feature-card_heading__R5fVX">Play Now</h3>
          <div class="feature-card_body__EED8H">Jump into the island and relive your childhood. Waddle on!</div>
          <a href="<?php echo e(PLAY_URL); ?>" class="feature-card_button__3CdS5">Launch</a>
        </div>
      </div>
    </div>

    <div class="col-12 col-lg-2.4 p-0">
      <div class="feature-card_card__uPPhs">
        <div class="feature-card_left__QM9QG"><img src="_next/community1266.webp" alt="Safety" style="width:100%;height:auto;max-width:100%"/></div>
        <div class="feature-card_right__X0gx3">
          <h3 class="feature-card_heading__R5fVX">Parent Center</h3>
          <div class="feature-card_body__EED8H">Tools for linked accounts, safety, and a more family-friendly experience.</div>
          <a href="/parent_center/index.php" class="feature-card_button__3CdS5">Open</a>
        </div>
      </div>
    </div>

  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
