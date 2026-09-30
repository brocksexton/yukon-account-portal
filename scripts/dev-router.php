<?php
// Router for PHP's built-in web server, for local development only.
//
//   YUKON_CLIENT_DIR=/path/to/yukon php -S 127.0.0.1:8080 scripts/dev-router.php
//
// It mimics the parts of .htaccess the site relies on:
//  - /yukon/assets/media/... is served from YUKON_CLIENT_DIR (your wizguin/yukon
//    checkout with its media assets merged in); the rest of /yukon is blocked
//  - extensionless URLs map to .php files
//  - directories serve index.php
// Run it from the repository root so the document root is the site root.
$root = $_SERVER['DOCUMENT_ROOT'];
$uri  = rawurldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/');

$mime = [
  'png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'webp' => 'image/webp',
  'svg' => 'image/svg+xml', 'gif' => 'image/gif', 'ico' => 'image/x-icon', 'json' => 'application/json',
  'css' => 'text/css', 'js' => 'application/javascript', 'woff' => 'font/woff', 'woff2' => 'font/woff2', 'ttf' => 'font/ttf',
];

if (preg_match('#^/yukon/(assets/media/.+)$#', $uri, $m)) {
  $base = getenv('YUKON_CLIENT_DIR') ?: '';
  $file = $base !== '' ? realpath($base . '/' . $m[1]) : false;
  if ($file && is_file($file) && str_starts_with(str_replace('\\', '/', $file), str_replace('\\', '/', realpath($base)))) {
    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    header('Content-Type: ' . ($mime[$ext] ?? 'application/octet-stream'));
    header('Cache-Control: public, max-age=3600');
    readfile($file);
    return true;
  }
  http_response_code(404);
  return true;
}
if (preg_match('#^/(yukon|yukon-server)(/|$)#', $uri)) { http_response_code(403); return true; }

$path = $root . $uri;
if (is_dir($path)) {
  if (is_file(rtrim($path, '/') . '/index.php')) {
    $_SERVER['SCRIPT_NAME'] = rtrim($uri, '/') . '/index.php';
    $_SERVER['SCRIPT_FILENAME'] = rtrim($path, '/') . '/index.php';
    chdir(dirname($_SERVER['SCRIPT_FILENAME']));
    require $_SERVER['SCRIPT_FILENAME'];
    return true;
  }
}
if (is_file($path)) {
  if (str_ends_with($path, '.php')) return false; // let the built-in server run it
  return false; // static file
}
if (is_file($path . '.php')) {
  $_SERVER['SCRIPT_NAME'] = $uri . '.php';
  $_SERVER['SCRIPT_FILENAME'] = $path . '.php';
  chdir(dirname($_SERVER['SCRIPT_FILENAME']));
  require $_SERVER['SCRIPT_FILENAME'];
  return true;
}
http_response_code(404);
if (is_file($root . '/error-pages/404.php')) { require $root . '/error-pages/404.php'; }
return true;
