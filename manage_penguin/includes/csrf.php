<?php
// manage_penguin/includes/csrf.php
// Lightweight CSRF helpers scoped to manage_penguin.

if (session_status() !== PHP_SESSION_ACTIVE) {
    @session_start();
}

function mp_csrf_token(): string {
    if (empty($_SESSION['mp_csrf']) || !is_string($_SESSION['mp_csrf'])) {
        $_SESSION['mp_csrf'] = bin2hex(random_bytes(32));
    }
    return (string)$_SESSION['mp_csrf'];
}

function mp_csrf_field(): string {
    $t = mp_csrf_token();
    return '<input type="hidden" name="mp_csrf" value="' . htmlspecialchars($t, ENT_QUOTES, 'UTF-8') . '">';
}

function mp_csrf_verify(): void {
    $posted = $_POST['mp_csrf'] ?? '';
    $expected = $_SESSION['mp_csrf'] ?? '';
    if (!is_string($posted) || !is_string($expected) || $posted === '' || !hash_equals($expected, $posted)) {
        http_response_code(400);
        die('Invalid request (CSRF).');
    }
}
