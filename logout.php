<?php
require_once __DIR__ . '/includes/bootstrap.php';
logout_user();
flash_set('info', 'You have been logged out.');
header('Location: index.php');
exit;
