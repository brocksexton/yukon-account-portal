<div class="mp-hero cp-card cp-card--wide">
    <div class="mp-heroLeft">
      <img class="mp-heroArt" src="assets/<?php echo e($heroPick); ?>" alt="Mascot"/>
      <div style="min-width:0">
        <h1 class="mp-heroTitle">Manage My Penguin</h1>
        <div class="mp-heroSub">
          <span style="display:inline-flex;align-items:center;gap:10px;flex-wrap:wrap">
            <span>Welcome back, <strong><?php echo e($user['username']); ?></strong></span>
            <span class="cp-chip"><?php echo e(rank_label((int)$user['rank'])); ?></span>
            <span class="cp-chip">Inventory: <?php echo (int)$invCount; ?></span>
          </span>
        </div>
      </div>
    </div>
    <div class="cp-actions">
      <a class="cp-btn cp-btn--secondary" href="/parent_center/">Parent Center</a>
      <a class="cp-btn cp-btn--primary" href="<?php echo e(PLAY_URL); ?>">Play</a>
    </div>
  </div>

  