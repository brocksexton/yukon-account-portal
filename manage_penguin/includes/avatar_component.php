<?php
// manage_penguin/includes/avatar_component.php
// Small helper used by studio.php to render a DOM-updatable paper-doll avatar.
// Returns HTML (string) so it can be used inside templates.

require_once __DIR__ . '/meta.php';

if (!function_exists('mp_h')) {
  function mp_h($v): string {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
  }
}

/**
 * Render an avatar component.
 *
 * Expected keys in $state:
 *  color, head, face, neck, body, hand, feet, photo, flag
 */
function mp_avatar_component(string $idPrefix, array $state, int $size = 320): string {
  global $YUKON_WEB;

  $size = max(220, min(520, (int)$size));
  $color = (int)($state['color'] ?? 1);
  if ($color < 1) $color = 1;

  $photo = (int)($state['photo'] ?? 0);
  $flag  = (int)($state['flag']  ?? 0);

  // Layer order should match Yukon paper-doll expectations.
  $paperSlots = ['feet','body','neck','head','face','hand'];
  $paper = [];
  foreach ($paperSlots as $s) {
    $id = (int)($state[$s] ?? 0);
    $paper[$s] = $id > 0 ? ($YUKON_WEB . '/assets/media/clothing/paper/' . $id . '.png') : '';
  }

  // "Photo" is treated like a background image in this UI (uses icon/240).
  $bg = $photo > 0 ? clothing_icon_url($photo) : '';
  $flagUrl = $flag > 0 ? clothing_icon_url($flag) : '';

  ob_start();
  ?>
  <div class="mp-avatar" style="width:<?php echo (int)$size; ?>px;height:<?php echo (int)$size; ?>px" data-avatar="<?php echo mp_h($idPrefix); ?>">
    <div class="mp-avatarFrame">
      <img class="mp-avatarBg" src="<?php echo mp_h($bg); ?>" alt="" style="<?php echo $bg ? '' : 'display:none'; ?>" onerror="this.style.display='none'">

      <img class="mp-avatarColor" src="<?php echo mp_h($YUKON_WEB . '/assets/media/color/penguin/' . $color . '.png'); ?>" alt="" onerror="this.style.display='none'">

      <?php foreach ($paperSlots as $s): ?>
        <?php $src = $paper[$s]; ?>
        <img class="mp-avatarLayer mp-layer-<?php echo mp_h($s); ?>" src="<?php echo mp_h($src); ?>" data-slot="<?php echo mp_h($s); ?>" alt="" style="<?php echo $src ? '' : 'display:none'; ?>" onerror="this.style.display='none'">
      <?php endforeach; ?>

      <img class="mp-avatarFlag" src="<?php echo mp_h($flagUrl); ?>" alt="" style="<?php echo $flagUrl ? '' : 'display:none'; ?>" onerror="this.style.display='none'">
    </div>
  </div>
  <?php
  return trim((string)ob_get_clean());
}
