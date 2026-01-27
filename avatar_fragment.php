<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/avatar_component.php';
require_login();

// Force refresh so avatar reflects in-game changes
unset($_SESSION['user_cache'], $_SESSION['user_cache_time']);
$user = current_user();

ob_start();
render_penguin_avatar($user, 220);
echo ob_get_clean();
