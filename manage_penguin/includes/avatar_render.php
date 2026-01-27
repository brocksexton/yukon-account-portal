<?php
// manage_penguin/includes/avatar_render.php
// DOM-updatable avatar stack (no GD). Uses Yukon paper-doll assets.
require_once __DIR__ . '/meta.php';

function mp_render_live_avatar(array $state, string $idPrefix = 'draft', int $size = 320): void {
  global $YUKON_WEB;
  $size = max(220, min(520, (int)$size));
  $color = (int)($state['color'] ?? 1);
  if ($color < 1) $color = 1;
  $photo = (int)($state['photo'] ?? 0);
  $flag  = (int)($state['flag']  ?? 0);

  $paperSlots = ['feet','body','neck','head','face','hand']; // typical layering order
  $paper = [];
  foreach ($paperSlots as $s) {
    $id = (int)($state[$s] ?? 0);
    $paper[$s] = $id > 0 ? ($YUKON_WEB . '/assets/media/clothing/paper/' . $id . '.png') : null;
  }

  $bg = $photo > 0 ? (clothing_icon_url($photo)) : null;

  ?>
  <div class="mp-avatar" style="width:<?php echo (int)$size; ?>px;height:<?php echo (int)$size; ?>px" data-avatar="<?php echo e($idPrefix); ?>">
    <div class="mp-avatarFrame">
      <?php if ($bg): ?>
        <img class="mp-avatarBg" src="<?php echo e($bg); ?>" alt="" onerror="this.style.display='none'">
      <?php endif; ?>
      <img class="mp-avatarColor" src="<?php echo e($YUKON_WEB . '/assets/media/color/penguin/' . $color . '.png'); ?>" alt="" onerror="this.style.display='none'">
      <?php foreach ($paperSlots as $s): ?>
        <?php if ($paper[$s]): ?>
          <img class="mp-avatarLayer mp-layer-<?php echo e($s); ?>" src="<?php echo e($paper[$s]); ?>" data-slot="<?php echo e($s); ?>" alt="" onerror="this.style.display='none'">
        <?php else: ?>
          <img class="mp-avatarLayer mp-layer-<?php echo e($s); ?>" src="" data-slot="<?php echo e($s); ?>" alt="" style="display:none">
        <?php endif; ?>
      <?php endforeach; ?>
      <?php if ($flag > 0): ?>
        <img class="mp-avatarFlag" src="<?php echo e(clothing_icon_url($flag)); ?>" alt="" onerror="this.style.display='none'">
      <?php else: ?>
        <img class="mp-avatarFlag" src="" alt="" style="display:none">
      <?php endif; ?>
    </div>
  </div>
  <?php
}
