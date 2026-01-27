<?php
// Admin header
$me = current_user();
$activeNav = $activeNav ?? '';
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?php echo isset($title) ? e($title) . ' · ' : ''; ?>Administration</title>
  <link rel="stylesheet" href="<?php echo e(admin_url('assets/admin.css')); ?>">
</head>
<body>
<header class="adm-topbar">
  <div class="adm-topbar__bg" aria-hidden="true"></div>
  <div class="adm-topbar__inner">
    <a class="adm-brand" href="<?php echo e(admin_url('dashboard.php')); ?>">
      <span class="adm-brand__mark" aria-hidden="true"></span>
      <span class="adm-brand__text">Administration</span>
    </a>

    <nav class="adm-nav">
      <a class="adm-nav__link <?php echo $activeNav === 'dashboard' ? 'is-active' : ''; ?>" href="<?php echo e(admin_url('dashboard.php')); ?>">Dashboard</a>
      <a class="adm-nav__link <?php echo $activeNav === 'users' ? 'is-active' : ''; ?>" href="<?php echo e(admin_url('users.php')); ?>">Users</a>
      <a class="adm-nav__link <?php echo $activeNav === 'bans' ? 'is-active' : ''; ?>" href="<?php echo e(admin_url('bans.php')); ?>">Bans</a>
      <?php if (admin_server_control_allowed($me)): ?>
        <a class="adm-nav__link <?php echo $activeNav === 'server' ? 'is-active' : ''; ?>" href="<?php echo e(admin_url('server.php')); ?>">Server</a>
      <?php endif; ?>
      <a class="adm-nav__link" href="/">Main Site</a>
      <a class="adm-nav__link" href="/parent_center/">Parent Center</a>
    </nav>

    <div class="adm-me">
      <?php if ($me): ?>
        <?php
          $rankNum = (int)($me['rank'] ?? 0);
          $rankLabel = rank_title($rankNum);
        ?>
        <div class="adm-me__meta">
          <div class="adm-me__name"><?php echo e($me['username'] ?? ''); ?></div>
          <div class="adm-me__sub"><?php echo e($rankLabel); ?></div>
        </div>
        <span class="adm-me__avatar" aria-hidden="true">
          <?php
            // Match the main site header behavior: render the penguin as inline SVG so
            // it never "disappears" (white penguins on white backgrounds).
            require_once __DIR__ . '/../../includes/avatar_svg.php';
            echo render_avatar_svg($me, [
              'profile' => true,
              'size' => 46,
              'scale' => 2.25,
              'x' => -30,
              'y' => -24,
              'background' => true,
              'pin' => false,
            ]);
          ?>
        </span>
      <?php else: ?>
        <a class="adm-btn adm-btn--ghost" href="/login.php">Log in</a>
      <?php endif; ?>
    </div>
  </div>
</header>
<main class="adm-main">
  <div class="adm-shell">
    <?php echo admin_flash_html(); ?>
