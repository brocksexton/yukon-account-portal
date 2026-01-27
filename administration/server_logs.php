<?php
require_once __DIR__ . '/includes/admin_bootstrap.php';
admin_require_staff();
admin_require_server_control();

header('Content-Type: text/plain; charset=utf-8');

$cwd = admin_yukon_server_dir();
if (!$cwd) {
    http_response_code(500);
    echo "ADMIN_YUKON_SERVER_DIR not set or yukon-server not found.";
    exit;
}

$cmd = admin_server_cmd('logs');
$res = admin_run_cmd($cmd ?? '', $cwd, 3);
echo ($res['out'] ?? '');
