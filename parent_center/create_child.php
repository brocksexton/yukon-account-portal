<?php
require_once __DIR__ . '/includes/parent_bootstrap.php';
parent_require_login();

if (!PARENT_CENTER_ALLOW_CREATE) {
    http_response_code(403);
    die('Child account creation is disabled.');
}

$parent = parent_current();
$parentId = (int)$parent['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();

    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $email = trim($_POST['email'] ?? '');
    $alias = trim($_POST['alias'] ?? '');

    if ($username === '' || $password === '') {
        flash_set('error', 'Username and password are required.');
        header('Location: create_child.php');
        exit;
    }

    if (!preg_match('/^[a-z0-9_]{3,12}$/i', $username)) {
        flash_set('error', 'Username must be 3-12 characters (letters, numbers, underscore).');
        header('Location: create_child.php');
        exit;
    }

    $stmt = db()->prepare('SELECT id FROM users WHERE username = ? LIMIT 1');
    $stmt->bind_param('s', $username);
    $stmt->execute();
    $exists = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($exists) {
        flash_set('error', 'That username is already taken.');
        header('Location: create_child.php');
        exit;
    }

    $emailVal = $email !== '' ? $email : null;
    if ($emailVal !== null && !filter_var($emailVal, FILTER_VALIDATE_EMAIL)) {
        flash_set('error', 'Invalid email address.');
        header('Location: create_child.php');
        exit;
    }

    $hash = password_hash($password, PASSWORD_BCRYPT);

    // Insert with Yukon defaults (most columns have defaults)
    $stmt = db()->prepare('INSERT INTO users (username, email, password) VALUES (?, ?, ?)');
    $stmt->bind_param('sss', $username, $emailVal, $hash);
    $ok = $stmt->execute();
    $uid = (int)$stmt->insert_id;
    $stmt->close();

    if (!$ok || $uid <= 0) {
        flash_set('error', 'Failed to create the child account. Please try again.');
        header('Location: create_child.php');
        exit;
    }

        $ok = parent_link_account($parentId, $uid, ($alias !== '' ? $alias : null), 'child');
    if (!$ok) {
        flash_set('error', 'Account created, but failed to link to Parent Center.');
        header('Location: dashboard.php');
        exit;
    }

    flash_set('success', 'Child account created and linked.');
    header('Location: child.php?uid=' . $uid);
    exit;
}

$pageTitle = 'Create Child Account - Parent Center';
include __DIR__ . '/includes/parent_header.php';
?>

<div class="cp-card">
  <h1 class="cp-title">Create Child Account</h1>
  <p class="cp-subtitle">Create a new penguin account and link it to your Parent Center.</p>

  <?php if ($msg = flash_get('error')): ?>
    <div class="cp-alert cp-alert--error"><?php echo e($msg); ?></div>
  <?php endif; ?>

  <form method="post" class="cp-form" action="create_child.php">
    <input type="hidden" name="csrf" value="<?php echo e(csrf_token()); ?>"/>

    <label class="cp-label">Child Username</label>
    <input class="cp-input" name="username" maxlength="12" required/>

    <label class="cp-label">Child Password</label>
    <input class="cp-input" type="password" name="password" required/>

    <label class="cp-label">Child Email (optional)</label>
    <input class="cp-input" type="email" name="email" autocomplete="email"/>

    <label class="cp-label">Parent Label (optional)</label>
    <input class="cp-input" name="alias" placeholder="e.g., My Kid, Daughter, etc."/>

    <button class="cp-btn cp-btn--primary" type="submit">Create Account</button>
    <a class="cp-btn" style="background:rgba(0,0,0,.05);" href="dashboard.php">Cancel</a>
  </form>

  <div class="cp-footnote">
    This is a private-access website. Public registration remains disabled.
  </div>
</div>

<?php include __DIR__ . '/includes/parent_footer.php'; ?>
