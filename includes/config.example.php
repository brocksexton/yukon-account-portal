<?php
// Site configuration.
//
// Copy this file to includes/config.php and edit the copy. includes/config.php is
// git-ignored so real credentials never end up in the repository.
//
// Setup:
// 1) Serve this folder as the web ROOT of its own host (many links are root-relative,
//    e.g. /login.php and /assets/...), for example http://localhost/ or http://portal.test/.
// 2) Point DB_* at the same MySQL/MariaDB database your yukon-server uses.
// 3) Make the Yukon client's media reachable at /yukon/assets/media/ (see README).

// --- Database (the Yukon database) ---
define('DB_HOST', '127.0.0.1');
define('DB_NAME', 'yukon');
define('DB_USER', 'yukon_web');      // use a dedicated account, not root
define('DB_PASS', 'change-me');      // placeholder: set your own password
define('DB_PORT', 3306);

// --- Branding / Links ---
define('SITE_NAME', 'Club Penguin');
define('SITE_TAGLINE', 'A private, invite-only Club Penguin experience for friends.');

// Where the "Play" buttons go (the built wizguin/yukon client).
define('PLAY_URL', '/play/');

// Optional: add your Discord invite for friends (or set to empty string).
define('DISCORD_URL', '');

// --- Yukon asset paths (for website avatar rendering) ---
// Defaults assume Yukon assets are served at /yukon/assets/... on this domain.
// If you prefer filesystem reads (faster and avoids HTTP), set YUKON_ASSET_BASE_PATH.
// Example (Windows XAMPP): C:/xampp/htdocs/yukon/assets
define('YUKON_ASSET_BASE_URL', '/yukon/assets');
define('YUKON_ASSET_BASE_PATH', '');

// --- Avatar endpoint defaults ---
// These defaults are applied anywhere we render a "profile" style avatar.
// Target look:
// avatar.php?username=demo&profile=true&size=256&scale=1.7&y=26&x=3&background=true
define('PROFILE_AVATAR_DEFAULT_SCALE', 1.7);
define('PROFILE_AVATAR_DEFAULT_X', 3);
define('PROFILE_AVATAR_DEFAULT_Y', 26);
define('PROFILE_AVATAR_DEFAULT_BACKGROUND', true);
define('PROFILE_AVATAR_DEFAULT_PIN', true);

// Short line shown in the header, footer and login page.
define('PRIVATE_ACCESS_NOTICE', 'Waddle Around, and Make Friends!');

// Security
// Reserved for future signing features; not read anywhere yet. Change it anyway.
define('APP_KEY', 'change-me-to-a-long-random-string');

// Session cookie settings
// On localhost you can keep defaults. If you deploy HTTPS on your domain, set COOKIE_SECURE=true.
define('COOKIE_SECURE', false);

// --- Optional overrides (defaults live in the files named) ---
// administration/includes/admin_bootstrap.php:
// define('ADMIN_MIN_RANK', 3);                   // rank needed to open /administration/
// define('ADMIN_GIVE_ALL_ITEMS_MIN_RANK', 4);
// define('ADMIN_ALLOW_SERVER_CONTROL', false);   // see administration/config/server_control.php
// parent_center/includes/parent_bootstrap.php:
// define('PARENT_CENTER_ALLOW_CREATE', true);
