<?php
require_once __DIR__ . '/includes/parent_bootstrap.php';
unset($_SESSION['parent_id']);
flash_set('success', 'Parent Center logged out.');
header('Location: index.php');
exit;
