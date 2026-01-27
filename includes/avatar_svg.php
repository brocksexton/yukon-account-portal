<?php
require_once __DIR__ . '/config.php';

/**
 * Inline SVG avatar renderer for embedding directly in HTML.
 * This avoids browser restrictions that can prevent SVG-with-external-images
 * from rendering when used via <img src="avatar.php?...">.
 *
 * Notes:
 * - 'profile' mode defaults are tuned for a 256x256 output and automatically
 *   scaled down for smaller sizes unless x/y are explicitly provided.
 * - Pin is disabled by default in profile mode (can be enabled explicitly).
 */
function render_avatar_svg(array $user, array $opts = []): string {
    $provided = $opts;

    // Defaults (tuned to your preferred 256 profile settings)
    $defaults = [
        'profile' => true,
        'size' => 96,
        'scale' => 1.7,
        'x' => 3,
        'y' => 26,
        'background' => true,
        // Profile pics should NOT include pin by default
        'pin' => false,
    ];

    $opts = array_merge($defaults, $opts);

    $profile = (bool)$opts['profile'];

    // Normalize size
    $size = (int)$opts['size'];
    if ($size <= 0) { $size = 96; }

    // If size passed as "WxH" elsewhere, allow that too (we'll use min dimension)
    if (is_string($opts['size']) && preg_match('/^(\d+)x(\d+)$/i', $opts['size'], $m)) {
        $size = (int)min((int)$m[1], (int)$m[2]);
    }
    $opts['size'] = $size;

    // Auto-scale x/y for smaller sizes in profile mode unless explicitly provided.
    // This keeps framing consistent between 256 (manage card) and small header avatars (50-96).
    if ($profile) {
        $ratio = $size / 256.0;

        if (!array_key_exists('x', $provided)) {
            $opts['x'] = (int)round($defaults['x'] * $ratio);
        }
        if (!array_key_exists('y', $provided)) {
            $opts['y'] = (int)round($defaults['y'] * $ratio);
        }

        // Tiny sizes (e.g., 50px in header) often look better nudged up slightly.
        // Apply a small uplift unless y was explicitly provided.
        if (!array_key_exists('y', $provided) && $size <= 64) {
            $opts['y'] = max(-20, $opts['y'] - 3);
        }
    }

    // Resolve user fields (support both DB shapes)
    $uid   = (int)($user['id'] ?? $user['uid'] ?? 0);
    $color = (int)($user['color'] ?? 1);

    $photo = (int)($user['photo'] ?? 0);
    $flag  = (int)($user['flag'] ?? 0);

    // Outfit slot ordering for correct layering
    $slots = [
        'feet' => (int)($user['feet'] ?? 0),
        'body' => (int)($user['body'] ?? 0),
        'neck' => (int)($user['neck'] ?? 0),
        'face' => (int)($user['face'] ?? 0),
        'head' => (int)($user['head'] ?? 0),
        'hand' => (int)($user['hand'] ?? 0),
    ];

    // Local assets
    $colBase = "/assets/img/colours/{$color}.png";
    $topPng  = "/assets/img/penguin_transparent.png";

    // Yukon assets base
    $yukon = defined('YUKON_ASSET_BASE_URL') ? rtrim(YUKON_ASSET_BASE_URL, '/') : '/yukon/assets';

    $photoUrl = "{$yukon}/media/clothing/photos/{$photo}.png";
    $pinUrl   = "{$yukon}/media/clothing/icon/120/{$flag}.png";
    $paperUrl = fn(int $id) => "{$yukon}/media/clothing/paper/{$id}.png";

    // Optional circular clip for profile mode
    $clipId = "clip_{$uid}_{$size}_".substr(md5($uid.'|'.$size), 0, 6);

    ob_start();
?>
<svg
  xmlns="http://www.w3.org/2000/svg"
  width="<?= (int)$opts['size'] ?>"
  height="<?= (int)$opts['size'] ?>"
  viewBox="0 0 <?= (int)$opts['size'] ?> <?= (int)$opts['size'] ?>"
  aria-hidden="true"
  role="img"
>
  <?php if ($profile): ?>
  <defs>
    <clipPath id="<?= htmlspecialchars($clipId, ENT_QUOTES) ?>">
      <circle cx="<?= (int)$opts['size'] / 2 ?>" cy="<?= (int)$opts['size'] / 2 ?>" r="<?= (int)$opts['size'] / 2 ?>" />
    </clipPath>
  </defs>
  <?php endif; ?>

  <g <?= $profile ? 'clip-path="url(#'.htmlspecialchars($clipId, ENT_QUOTES).')"' : '' ?>>

    <?php if (!empty($opts['background']) && $photo > 0): ?>
      <image href="<?= htmlspecialchars($photoUrl, ENT_QUOTES) ?>" x="0" y="0" width="<?= (int)$opts['size'] ?>" height="<?= (int)$opts['size'] ?>" />
    <?php endif; ?>

    <!-- Penguin stack -->
    <g transform="translate(<?= (int)$opts['x'] ?> <?= (int)$opts['y'] ?>) scale(<?= (float)$opts['scale'] ?>)">

      <!-- coloured base -->
      <image href="<?= htmlspecialchars($colBase, ENT_QUOTES) ?>" x="0" y="0" width="<?= (int)$opts['size'] ?>" height="<?= (int)$opts['size'] ?>" />

      <!-- top overlay (never tinted) -->
      <image href="<?= htmlspecialchars($topPng, ENT_QUOTES) ?>" x="0" y="0" width="<?= (int)$opts['size'] ?>" height="<?= (int)$opts['size'] ?>" />

      <!-- clothing paper layers -->
      <?php foreach ($slots as $itemId): ?>
        <?php if ($itemId > 0): ?>
          <image href="<?= htmlspecialchars($paperUrl($itemId), ENT_QUOTES) ?>" x="0" y="0" width="<?= (int)$opts['size'] ?>" height="<?= (int)$opts['size'] ?>" />
        <?php endif; ?>
      <?php endforeach; ?>

    </g>

    <?php if (!empty($opts['pin']) && $flag > 0): ?>
      <image href="<?= htmlspecialchars($pinUrl, ENT_QUOTES) ?>" x="<?= (int)((int)$opts['size'] - 32) ?>" y="8" width="24" height="24" />
    <?php endif; ?>

  </g>
</svg>
<?php
    return ob_get_clean();
}
