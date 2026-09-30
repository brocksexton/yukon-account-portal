<?php
// Administration server-control configuration.
// If you want the Administration portal to display server status / logs and allow start/stop,
// set the path to your yukon-server install (where package.json lives).

// Example (Windows / XAMPP):
// define('ADMIN_YUKON_SERVER_DIR', 'C:\\xampp\\htdocs\\yukon-server');

// Example (Linux):
// define('ADMIN_YUKON_SERVER_DIR', '/var/www/yukon-server');

// Off by default. The Server page runs `npm run start/stop/restart/logs` in your yukon-server folder.
// Only turn it on for a local install. Behind a reverse proxy every request can appear to come
// from 127.0.0.1, which would defeat the IP allowlist below.
// define('ADMIN_ALLOW_SERVER_CONTROL', true);

// Security: keep this local-only unless you REALLY know what you're doing.
define('ADMIN_SERVER_CONTROL_IP_ALLOWLIST', '127.0.0.1,::1');

// Recommended: require Owner/Founder (rank 4+) for server controls.
define('ADMIN_SERVER_CONTROL_MIN_RANK', 4);
