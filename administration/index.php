<?php
require_once __DIR__ . '/includes/admin_bootstrap.php';
// If not logged in, bounce to main login.
if (!is_logged_in()) {
    header('Location: /login.php');
    exit;
}
// If logged in but not staff, show 403 via helper.
admin_require_staff();
header('Location: ' . admin_url('dashboard.php'));
exit;
