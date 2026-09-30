<?php
require_once __DIR__ . '/../includes/parent_bootstrap.php';
if (!isset($pageTitle)) { $pageTitle = 'Parent Center - ' . SITE_NAME; }
$parent = parent_current();

// -----------------------------------------------------------------------------
// Robust URL helpers
//
// This project can be hosted at the domain root (/) OR inside a subfolder
// (e.g. /cp). Parent Center lives in /parent_center, so we compute the install
// root based on the current script path and prefix all asset links with it.
//
// Important: avoid protocol-relative URLs (//host/...) because those can turn
// "//css/..." into a DNS lookup for the host "css".
// -----------------------------------------------------------------------------
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host   = $_SERVER['HTTP_HOST'] ?? '';
$origin = $host ? ($scheme . '://' . $host) : '';

$script   = $_SERVER['SCRIPT_NAME'] ?? '';
$siteRoot = rtrim(str_replace('\\', '/', dirname(dirname($script))), '/');
if ($siteRoot === '.' || $siteRoot === '/') { $siteRoot = ''; }

$h = function(string $path) use ($siteRoot): string {
  return $siteRoot . '/' . ltrim($path, '/');
};
$u = function(string $path) use ($origin, $h): string {
  $p = $h($path);
  return $origin ? ($origin . $p) : $p;
};
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1"/>
  <link rel="shortcut icon" href="<?php echo e($u('favicon.ico')); ?>"/>
  <title><?php echo e($pageTitle); ?></title>

  <!-- Base theme: Bootstrap 5 (reset, grid, utilities) + css/theme.css -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" crossorigin="anonymous"/>
  <link rel="stylesheet" href="<?php echo e($u('css/theme.css')); ?>"/>

  <link rel="stylesheet" href="<?php echo e($u('css/cp-custom.css')); ?>"/>
  <link rel="stylesheet" href="<?php echo e($u('parent_center/parent.css')); ?>"/>
  <link rel="stylesheet" href="<?php echo e($u('parent_center/assets/parent_center.css')); ?>"/>

  <!-- Parent Center modular additions (keep parent_center.css stable; add new styles here) -->
  <link rel="stylesheet" href="<?php echo e($u('parent_center/assets/pc_child.css')); ?>"/>
  <link rel="stylesheet" href="<?php echo e($u('parent_center/assets/pc_child_pages.css')); ?>"/>
  <link rel="stylesheet" href="<?php echo e($u('parent_center/assets/pc_auth.css')); ?>"/>
</head>
<body>
<div class="pc-appRoot">
  <!-- Parent Center uses a stable, custom header so it doesn't depend on Next.js hashed classnames. -->
  <header class="pc-globalHeader" role="banner">
    <div class="pc-globalHeaderInner">
      <div class="pc-globalLogo">
        <a href="<?php echo e($h('index.php')); ?>" class="cp-logoText" aria-label="<?php echo e(SITE_NAME); ?>">
          <span class="cp-logoLine1">CLUB</span>
          <span class="cp-logoLine2">PENGUIN</span>
        </a>
      </div>

      <nav class="pc-globalNav" aria-label="Primary">
        <a class="pc-globalNavLink" href="<?php echo e($h('index.php')); ?>">Home</a>
        <a class="pc-globalNavLink" href="<?php echo e($h('manage_penguin/')); ?>">Manage My Penguin</a>
        <a class="pc-globalNavLink" href="<?php echo e($h('parent_center/dashboard.php')); ?>">Parent Center</a>
        <?php if ($parent): ?>
          <a class="pc-globalNavLink" href="<?php echo e($h('parent_center/logout.php')); ?>">Parent Logout</a>
        <?php endif; ?>
      </nav>

      <div class="pc-globalRight">
        <?php if ($parent): ?>
          <div class="cp-userChip">
            <a class="cp-userChipLink" href="<?php echo e($h('parent_center/dashboard.php')); ?>" aria-label="Open Parent Center">
              <span class="cp-userChipText">
                <span class="cp-userChipName"><?php echo e($parent['email']); ?></span>
                <span class="cp-userChipSub">Parent Center</span>
              </span>
            </a>
          </div>
        <?php else: ?>
          <a class="pc-globalCta" href="<?php echo e($h('parent_center/index.php')); ?>" aria-label="Open Parent Center">Parent Center</a>
        <?php endif; ?>
      </div>
    </div>
  </header>

  <main class="pc-globalContent" role="main">
