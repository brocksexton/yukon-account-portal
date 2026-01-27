<?php
if (!isset($pageTitle)) { $pageTitle = SITE_NAME; }
$user = current_user();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1"/>
  <link rel="shortcut icon" href="favicon.ico"/>
  <title><?php echo e($pageTitle); ?></title>

  <!-- Theme CSS (from CPJourney mirror) -->
  <!--
    We load the full CSS bundle set that shipped with the mirror.
    Some page-level styles (like the island banner + card layout) live in
    additional CSS files.
  -->
  <link rel="stylesheet" href="/_next/static/css/1ee9a979f47073eb.css"/>
  <link rel="stylesheet" href="/_next/static/css/3ab61228b64fe6a5.css"/>
  <link rel="stylesheet" href="/_next/static/css/49ac20025ca14b51.css"/>
  <link rel="stylesheet" href="/_next/static/css/5185297529824f4e.css"/>
  <link rel="stylesheet" href="/_next/static/css/68d67f148b6a9c7f.css"/>
  <link rel="stylesheet" href="/_next/static/css/77bfbc40d4308641.css"/>
  <link rel="stylesheet" href="/_next/static/css/b0189531fb64fc75.css"/>
  <link rel="stylesheet" href="/_next/static/css/bde96ae522c14e4d.css"/>
  <link rel="stylesheet" href="/_next/static/css/e951524e14995f5c.css"/>

  <!-- Your small overrides -->
  <link rel="stylesheet" href="/css/cp-custom.css"/>
</head>
<body>
<div class="layout_layout__JVHGL">
  <div class="layout_header__xfrqJ">
    <div class="layout_inner__ZkDmw">
      <div class="layout_logo___NFT7">
        <a href="index.php" class="cp-logoText" aria-label="<?php echo e(SITE_NAME); ?>">
          <span class="cp-logoLine1">CLUB</span>
          <span class="cp-logoLine2">PENGUIN</span>
        </a>
      </div>

      <div class="layout_menu__NFJJt">
        <div class="layout_hamburger__meLMb">
          <div class="layout_hamburgerIcon__ASGjU" id="hamburger"><span></span><span></span><span></span></div>
        </div>

        <div class="layout_links__MWmdl">
          <!-- Use root‑relative paths so links work from nested directories like /manage_penguin/ -->
          <div class="layout_link__qiNnQ"><a href="/index.php" class="mx-3">Home</a></div>
          <div class="layout_link__qiNnQ"><a href="/manage_penguin/" class="mx-3">Manage My Penguin</a></div>
          <div class="layout_link__qiNnQ"><a href="/parent_center/index.php" class="mx-3">Parent Center</a></div>
          <?php if (!$user): ?>
            <div class="layout_link__qiNnQ"><a href="/login.php" class="mx-3">Login</a></div>
          <?php else: ?>
            <div class="layout_link__qiNnQ"><a href="/logout.php" class="mx-3">Logout</a></div>
          <?php endif; ?>
          <?php if (DISCORD_URL): ?>
            <div class="layout_link__qiNnQ"><a href="<?php echo e(DISCORD_URL); ?>" target="_blank" class="mx-3">Discord</a></div>
          <?php endif; ?>
        </div>

        <div class="layout_mobileMenu__9Koe8" id="mobileMenu">
          <div class="layout_mobileLinks__zxSHj">
            <!-- Root‑relative paths for nested pages (e.g. /manage_penguin/) -->
            <a href="/index.php">Home</a>
            <a href="/manage_penguin/">Manage My Penguin</a>
            <a href="/parent_center/index.php">Parent Center</a>
            <?php if (!$user): ?>
              <a href="/login.php">Login</a>
            <?php else: ?>
              <a href="/logout.php">Logout</a>
            <?php endif; ?>
            <?php if (DISCORD_URL): ?>
              <a href="<?php echo e(DISCORD_URL); ?>" target="_blank">Discord</a>
            <?php endif; ?>
          </div>
        </div>

        <div class="layout_announcements__GS0HL">
          <div class="cp-announce"><?php echo e(PRIVATE_ACCESS_NOTICE); ?></div>
        </div>
      </div>

      <div class="layout_buttons__NZGWl">
        <?php if (!$user): ?>
          <div>
            <a href="<?php echo e(PLAY_URL); ?>"><button class="link-button_button__xL9TZ link-button_yellow__Tdvvb"><div class="link-button_text__PoH2c">Play Now<div class="link-button_outline__1volg">Play Now</div></div></button></a>
          </div>
        <?php else: ?>
          <div class="cp-userChip">
            <a class="cp-userChipLink" href="/manage_penguin/" aria-label="Open your profile">
              <span class="cp-userChipAvatarWrap">
                <?php
                  // IMPORTANT: this prints inline SVG markup
                  require_once __DIR__ . '/avatar_svg.php';
                  echo render_avatar_svg($user, [
                    'profile' => true,
                    'size' => 50,
                    'scale' => 2.3,   // slightly larger
                    'x' => -32,         // pull left a bit
                    'y' => -25,        // raise penguin
                    'background' => true,
                    'pin' => false,
                  ]);
                ?>
              </span>
              <span class="cp-userChipText">
                <span class="cp-userChipName"><?php echo e($user['username']); ?></span>
                <span class="cp-userChipSub">My Penguin</span>
              </span>
            </a>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <div class="container p-4 container_container__DpiRT">
    <div class="cp-container-top" style="background-image:url(container-top.png)"></div>

    <?php $flash = flash_get(); if ($flash): ?>
      <div class="cp-flash cp-flash-<?php echo e($flash['type']); ?>"><?php echo e($flash['msg']); ?></div>
    <?php endif; ?>
