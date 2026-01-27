<?php
require_once __DIR__ . '/includes/parent_bootstrap.php';
parent_require_login();

$parent = parent_current();
$parentId = (int)$parent['id'];
$uid = (int)($_GET['uid'] ?? 0);
$csrf = $_GET['csrf'] ?? '';

if (!csrf_check($csrf)) {
    flash_set('error', 'Invalid request.');
    header('Location: dashboard.php');
    exit;
}

if ($uid > 0) {
    $stmt = db()->prepare('DELETE FROM parent_children WHERE parentId = ? AND userId = ? LIMIT 1');
    $stmt->bind_param('ii', $parentId, $uid);
    $stmt->execute();
    $stmt->close();
    flash_set('success', 'Account unlinked.');
}

header('Location: dashboard.php');
exit;
