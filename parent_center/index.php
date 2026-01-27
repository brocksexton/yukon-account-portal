<?php
require_once __DIR__ . '/includes/parent_bootstrap.php';

// If already logged in, go straight to the dashboard.
if (parent_is_logged_in()) {
    header('Location: dashboard.php');
    exit;
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();

    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') {
        $error = 'Please enter a valid email and password.';
    } else {
        $stmt = db()->prepare('SELECT id, email, password FROM parents WHERE email = ? LIMIT 1');
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $parent = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$parent || !password_verify($password, (string)$parent['password'])) {
            $error = 'Incorrect email or password.';
        } else {
            $_SESSION['parent_id'] = (int)$parent['id'];
            header('Location: dashboard.php');
            exit;
        }
    }
}

$pageTitle = 'Parent Center - Login';
require_once __DIR__ . '/includes/parent_header.php';
?>

<div class="pc-shell">
  <div class="pc-card pc-authPop">
    <div class="pc-titleRow">
      <img class="pc-heroIcon" src="assets/pc/parents-icon.png" alt="">
      <div>
        <h1 class="pc-title" style="margin:0;">PARENT CENTER</h1>
        <p class="pc-subtitle" style="margin:6px 0 0;">Sign in to manage linked penguin accounts, view playercards, and create child accounts.</p>
      </div>
    </div>

    <?php if ($error): ?>
      <div class="pc-alert pc-alert--error" role="alert"><?php echo e($error); ?></div>
    <?php endif; ?>

    <?php if ($msg = flash_get('success')): ?>
      <div class="pc-alert pc-alert--success" role="status"><?php echo e($msg); ?></div>
    <?php endif; ?>

    <div class="pc-authGrid">
      <section class="pc-authBox" aria-label="Parent Center Login">
        <div class="pc-authBoxTitle">Login</div>

        <form method="post" class="cp-form" action="index.php" autocomplete="on">
          <input type="hidden" name="csrf" value="<?php echo e(csrf_token()); ?>"/>

          <label class="cp-label" for="email">Parent Email</label>
          <input class="cp-input" id="email" name="email" type="email" required autocomplete="email" />

          <label class="cp-label" for="password">Password</label>
          <input class="cp-input" id="password" name="password" type="password" required autocomplete="current-password" />

          <div class="pc-authCtas">
            <button class="pc-btn pc-btn--primary" type="submit">Sign In</button>
            <a class="pc-btn" href="../index.php">Back to Home</a>
          </div>
        </form>

        <div class="pc-authHint">
          Tip: If you are using Parent Center for the first time, use <strong>Create Parent Account</strong>.
        </div>
      </section>

      <aside class="pc-authBox" aria-label="Create Parent Account">
        <div class="pc-authBoxTitle">Create Parent Account</div>
        <p class="pc-muted" style="margin:0 0 12px;">
          NOTICE: Parent Center accounts can only be created by existing, active penguin accounts.
        </p>

        <div class="pc-authCardSmall">
          <ul class="pc-bulletList">
            <li>Requires a valid penguin username + password</li>
            <li>Penguin must not be banned</li>
            <li>Lets you create child accounts without friend codes</li>
            <li>Link/manage multiple child accounts under one dashboard</li>
          </ul>
        </div>

        <div class="pc-authDivider"></div>

        <div class="pc-authCtas">
          <a class="pc-btn pc-btn--primary" href="create_parent.php">Create Parent Account</a>
        </div>

        <div class="pc-authHint">
          You will be asked to verify your in-game penguin account during setup.
        </div>
      </aside>
    </div>

    <div class="pc-footnote" style="margin-top:18px;">
      This is a private, friends-only environment. Parent Center access is intended for families managing linked accounts.
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/includes/parent_footer.php'; ?>
