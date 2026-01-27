<?php
// Basic site configuration for XAMPP (Apache + PHP + MySQL)
//
// Setup:
// 1) Put this site folder inside: C:\xampp\htdocs\clubpenguin
// 2) Update DB_* to match your Yukon database
// 3) Visit: http://localhost/clubpenguin/

// --- Database (Yukon) ---
define('DB_HOST', '127.0.0.1');
define('DB_NAME', 'yukon');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_PORT', 3306);

// --- Branding / Links ---
define('SITE_NAME', 'Club Penguin');
define('SITE_TAGLINE', 'A private, invite-only Club Penguin experience for friends.');

// If you host your game client elsewhere, update this.
define('PLAY_URL', '/play/');

// Optional: add your Discord invite for friends (or set to empty string).
define('DISCORD_URL', '');

// --- Yukon asset paths (for website avatar rendering) ---
// Defaults assume Yukon assets are served at /yukon/assets/... on this domain.
// If you prefer filesystem reads (faster and avoids HTTP), set YUKON_ASSET_BASE_PATH.
// Example (Windows XAMPP): C:/xampp/htdocs/yukon/assets
define('YUKON_ASSET_BASE_URL', '/yukon/assets');
define('YUKON_ASSET_BASE_PATH', '');
//define('YUKON_ASSET_BASE_PATH', 'C:/xampp/htdocs/yukon/assets');

// --- Avatar endpoint defaults ---
// These defaults are applied anywhere we render a "profile" style avatar.
// Target look:
// avatar.php?username=demo&profile=true&size=256&scale=1.7&y=26&x=3&background=true
define('PROFILE_AVATAR_DEFAULT_SCALE', 1.7);
define('PROFILE_AVATAR_DEFAULT_X', 3);
define('PROFILE_AVATAR_DEFAULT_Y', 26);
define('PROFILE_AVATAR_DEFAULT_BACKGROUND', true);
define('PROFILE_AVATAR_DEFAULT_PIN', true);

// Footer notice requirements
// (you asked to stress private access + registration disabled)
define('PRIVATE_ACCESS_NOTICE', 'Waddle Around, and Make Friends!');

// Security
// Change this to a long random string.
define('APP_KEY', 'change-me-to-a-long-random-string');

// Session cookie settings
// On localhost you can keep defaults. If you deploy HTTPS on your domain, set COOKIE_SECURE=true.
define('COOKIE_SECURE', false);

