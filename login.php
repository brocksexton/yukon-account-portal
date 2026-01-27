<?php
require_once __DIR__ . '/includes/bootstrap.php';

if (is_logged_in()) {
    header('Location: manage.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();

    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        flash_set('error', 'Please enter your username and password.');
        header('Location: login.php');
        exit;
    }

    $stmt = db()->prepare('SELECT id, password, permaBan FROM users WHERE username = ? LIMIT 1');
    $stmt->bind_param('s', $username);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res->fetch_assoc();
    $stmt->close();

    if (!$row || !password_verify($password, $row['password'])) {
        flash_set('error', 'Invalid login. (Invite-only: make sure you were given an account.)');
        header('Location: login.php');
        exit;
    }

    if ((int)$row['permaBan'] === 1) {
        flash_set('error', 'This account is banned.');
        header('Location: login.php');
        exit;
    }

    login_user((int)$row['id']);
    flash_set('success', 'Welcome back!');
    header('Location: manage.php');
    exit;
}

$pageTitle = SITE_NAME . ' - Login';
require_once __DIR__ . '/includes/header.php';
?>

<div class="cp-panel" style="max-width:620px">
  <h2 class="cp-title">Penguin Login</h2>
  <p class="cp-sub">Sign in to manage your account. Registration is disabled (invite-only).</p>

  <form method="post" class="cp-form" action="login.php" autocomplete="off">
    <input type="hidden" name="csrf" value="<?php echo e(csrf_token()); ?>"/>

    <label for="username">Username</label>
    <input id="username" name="username" maxlength="12" required />

    <label for="password">Password</label>
    <input id="password" name="password" type="password" required />

    <div style="margin-top:14px;display:flex;gap:12px;flex-wrap:wrap;align-items:center">
      <button type="submit" class="link-button_button__xL9TZ link-button_yellow__Tdvvb" style="width:200px">
        <div class="link-button_text__PoH2c">Login<div class="link-button_outline__1volg">Login</div></div>
      </button>
      <span class="cp-muted"><?php echo e(PRIVATE_ACCESS_NOTICE); ?></span>
    </div>
  </form>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
