<?php
// Manage My Penguin header (CP-styled)
require_once __DIR__ . '/../../includes/bootstrap.php';
if (!isset($pageTitle)) { $pageTitle = 'Manage My Penguin - ' . SITE_NAME; }

// Robust URL helper (works at / or subfolder installs)
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host   = $_SERVER['HTTP_HOST'] ?? '';
$origin = $host ? ($scheme . '://' . $host) : '';

$script = $_SERVER['SCRIPT_NAME'] ?? '';

// IMPORTANT:
// Pages inside /manage_penguin/pages/* are nested deeper than /manage_penguin/*.
// We want $siteRoot to resolve to the web root (""), not "/manage_penguin",
// so that asset URLs like /manage_penguin/css/* do not become /manage_penguin/manage_penguin/css/*.
//
// Example:
//   SCRIPT_NAME: /manage_penguin/pages/buy_outfit.php
//   dirname x1 : /manage_penguin/pages
//   dirname x2 : /manage_penguin
//   dirname x3 : /
$siteRoot = rtrim(str_replace('\\', '/', dirname(dirname(dirname($script)))), '/');
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
  <title><?php echo e($pageTitle); ?></title>

  <!-- Manage My Penguin styles (self-contained; avoids fragile hashed CSS refs) -->
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Bangers&family=Nunito:wght@400;600;700;800;900&display=swap"/>
  <link rel="stylesheet" href="<?php echo $h('manage_penguin/css/cp-custom.css'); ?>"/>
  <link rel="stylesheet" href="<?php echo $h('manage_penguin/manage.css'); ?>"/>
</head>
<body>
<?php
// If your main site has a shared header include, you can uncomment this line:
// require_once __DIR__ . '/../../includes/header.php';
?>
