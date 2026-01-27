<?php
// Renders a layered penguin avatar using simple HTML layers (no GD required).
// Uses:
//  - assets/img/colours/{colorId}.png  (bottom layer, already tinted)
//  - assets/img/penguin_transparent.png (upper penguin details; not tinted)
//  - Yukon paper sprites for equipped items (media/clothing/paper/{id}.png)

// Ensure config/constants are available no matter include order.
require_once __DIR__ . '/config.php';

function cp_colour_src(int $colorId): string {
  // Prefer the explicit colour sprite pack provided by the site owner.
  // Files live at: assets/img/colours/{id}.png
  // Fallback to colour 1 if missing.
  $id = $colorId > 0 ? $colorId : 1;
  // IMPORTANT: return root-relative paths so this component works
  // when rendered from nested pages like /parent_center/*.php.
  return '/assets/img/colours/' . $id . '.png';
}

function render_penguin_avatar(array $user, int $size = 220): void {
  $colorId = (int)($user['color'] ?? 1);
  $colourSrc = cp_colour_src($colorId);
  // Clothing layers that are paper-doll rendered on the penguin.
  // NOTE: photo/flag are NOT paper layers. Photo is a playercard background,
  // and flag is a pin icon on the card.
  $layers = [
    (int)($user['feet'] ?? 0),
    (int)($user['body'] ?? 0),
    (int)($user['neck'] ?? 0),
    (int)($user['face'] ?? 0),
    (int)($user['head'] ?? 0),
    (int)($user['hand'] ?? 0),
  ];
  $urlBase = rtrim(YUKON_ASSET_BASE_URL, '/');
  $s = max(32, min(512, $size));

  $photoId = (int)($user['photo'] ?? 0);
  $flagId  = (int)($user['flag'] ?? 0);

  $bgStyle = '';
  if ($photoId > 0) {
    // Playercard background images live under /media/clothing/photos/{id}.png
    $bgUrl = $urlBase . '/media/clothing/photos/' . $photoId . '.png';
    $bgStyle = ' background-image:url(' . e($bgUrl) . ');';
  }

  echo '<div class="cp-avatar-stack cp-avatar-card" style="width:' . (int)$s . 'px; height:' . (int)$s . 'px;' . $bgStyle . '">';

  // Wrap the penguin + all wearable layers so we can scale/position them
  // independently from the playercard background and pin icon.
  echo '  <div class="cp-avatar-penguin" aria-hidden="true">';
  // Bottom (already coloured) penguin layer
  echo '    <img class="cp-avatar-colour" alt="" src="' . e($colourSrc) . '" />';
  // Upper penguin details layer (NOT tinted)
  echo '    <img class="cp-avatar-top" alt="" src="/assets/img/penguin_transparent.png" />';
  foreach ($layers as $id) {
    if ($id <= 0) continue;
    $src = $urlBase . '/media/clothing/paper/' . $id . '.png';
    echo '    <img class="cp-avatar-layer" alt="" src="' . e($src) . '" loading="lazy" />';
  }
  echo '  </div>';

  if ($flagId > 0) {
    // Pins/icons live under /media/clothing/icon/120/{id}.png
    $pinSrc = $urlBase . '/media/clothing/icon/120/' . $flagId . '.png';
    echo '  <img class="cp-avatar-pin" alt="" src="' . e($pinSrc) . '" loading="lazy" />';
  }
  echo '</div>';
}
