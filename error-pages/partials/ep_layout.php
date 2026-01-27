<?php
// Shared layout for Club Penguin styled error/utility pages.
// This file is intentionally framework-free so it can be used as Apache ErrorDocument target.

declare(strict_types=1);

function ep_response_code(int $code): void {
    if (!headers_sent()) {
        http_response_code($code);
    }
}

function ep_h(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Render a full error/utility page.
 *
 * @param array{
 *   code:int,
 *   title:string,
 *   headline:string,
 *   message:string,
 *   detail?:string,
 *   kicker?:string,
 *   heroVariant?:int,
 *   image?:string,
 *   imageAlt?:string,
 *   actions?:array<int,array{label:string,href:string,variant?:'primary'|'secondary'}>,
 * } $opts
 */
function ep_render(array $opts): void {
    $code = (int)($opts['code'] ?? 500);
    $title = (string)($opts['title'] ?? ('Error ' . $code));
    $headline = (string)($opts['headline'] ?? $title);
    $message = (string)($opts['message'] ?? 'Something went wrong.');
    $detail = (string)($opts['detail'] ?? '');
    $kicker = (string)($opts['kicker'] ?? 'Club Penguin');
    $heroVariant = (int)($opts['heroVariant'] ?? 2);
    $image = (string)($opts['image'] ?? '/assets/pc/emptyisland.9242ab97a345338b54c6.png');
    $imageAlt = (string)($opts['imageAlt'] ?? 'Club Penguin Island');
    $actions = $opts['actions'] ?? [];

    ep_response_code($code);

    // Attempt a sensible "back" URL if available
    $backUrl = $_SERVER['HTTP_REFERER'] ?? '/';

    // Choose a container-top strip (1..4)
    $heroVariant = max(1, min(4, $heroVariant));
    $heroStrip = 'assets/pc/container-top-' . $heroVariant . '.png';

    $pageTitle = $title . ' • Club Penguin';
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title><?php echo ep_h($pageTitle); ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@700;800;900;1000&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="/css/base.css" />
  <style>
    /* Prefer Nunito if available (falls back cleanly) */
    body{ font-family: Nunito, system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif; }
    .ep-heroTop{ background-image:url('<?php echo ep_h($heroStrip); ?>'); }
  </style>
</head>
<body>
  <div class="pc-appRoot">
    <header class="pc-globalHeader">
      <div class="pc-globalHeaderInner">
        <a class="pc-globalLogoText" href="/">Club Penguin</a>
        <nav class="pc-globalNav" aria-label="Primary">
          <a class="pc-globalNavLink" href="/">Home</a>
          <a class="pc-globalNavLink" href="/manage_penguin/">Manage My Penguin</a>
          <a class="pc-globalNavLink" href="/parent_center/">Parent Center</a>
        </nav>
        <div class="pc-globalRight">
          <a class="pc-globalCta" href="<?php echo ep_h($backUrl); ?>">Go back</a>
        </div>
      </div>
    </header>

    <main class="pc-globalContent">
      <div class="ep-wrap">
        <section class="ep-card" role="region" aria-label="Message">
          <div class="ep-heroTop" aria-hidden="true"></div>

          <div class="ep-grid">
            <div>
              <div class="ep-kicker">
                <span><?php echo ep_h($kicker); ?></span>
              </div>

              <div class="ep-code"><?php echo ep_h((string)$code); ?></div>
              <h1 class="ep-title"><?php echo ep_h($headline); ?></h1>
              <p class="ep-text"><?php echo ep_h($message); ?></p>

              <?php if ($detail !== ''): ?>
                <div class="ep-miniNote"><?php echo ep_h($detail); ?></div>
              <?php endif; ?>

              <div class="ep-actions">
                <?php if (empty($actions)): ?>
                  <a class="cp-btn cp-btn--primary" href="/">Return home</a>
                  <a class="cp-btn cp-btn--secondary" href="<?php echo ep_h($backUrl); ?>">Back</a>
                <?php else: ?>
                  <?php foreach ($actions as $a):
                    $label = (string)($a['label'] ?? 'Link');
                    $href = (string)($a['href'] ?? '/');
                    $variant = (string)($a['variant'] ?? 'secondary');
                    $cls = ($variant === 'primary') ? 'cp-btn cp-btn--primary' : 'cp-btn cp-btn--secondary';
                  ?>
                    <a class="<?php echo ep_h($cls); ?>" href="<?php echo ep_h($href); ?>"><?php echo ep_h($label); ?></a>
                  <?php endforeach; ?>
                <?php endif; ?>
              </div>
            </div>

            <aside class="ep-illus" aria-label="Illustration">
              <img src="<?php echo ep_h($image); ?>" alt="<?php echo ep_h($imageAlt); ?>" loading="lazy" />
            </aside>
          </div>
        </section>
      </div>
    </main>

    <footer class="pc-globalFooter">
      <div class="pc-globalFooterInner">
        <p class="pc-globalFooterText">If this keeps happening, please report it in Discord or try again later.</p>
      </div>
    </footer>
  </div>
</body>
</html>
<?php
}
