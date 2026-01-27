<?php
require_once __DIR__ . '/includes/parent_bootstrap.php';
parent_require_login();

$parent = parent_current();
$parentId = (int)$parent['id'];

$defaultRole = strtolower(trim($_GET['role'] ?? 'child'));
if (!in_array($defaultRole, ['child', 'parent'], true)) $defaultRole = 'child';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $alias = trim($_POST['alias'] ?? '');
    $role = strtolower(trim($_POST['role'] ?? $defaultRole));
    if (!in_array($role, ['child', 'parent'], true)) {
    }

    if ($username === '' || $password === '') {
        flash_set('error', $role === 'parent'
            ? 'Enter your penguin username and password to link it.'
            : 'Enter the child\'s username and password to claim the account.');
        header('Location: claim.php');
        exit;
    }

    $stmt = db()->prepare('SELECT id, password FROM users WHERE username = ? LIMIT 1');
    $stmt->bind_param('s', $username);
    $stmt->execute();
    $u = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$u || !password_verify($password, $u['password'])) {
        flash_set('error', 'Could not verify that account. Double-check the username and password.');
        header('Location: claim.php');
        exit;
    }

    $uid = (int)$u['id'];

    // Prevent duplicates
    $stmt = db()->prepare('SELECT 1 FROM parent_children WHERE parentId = ? AND userId = ? LIMIT 1');
    $stmt->bind_param('ii', $parentId, $uid);
    $stmt->execute();
    $exists = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($exists) {
        flash_set('error', 'That account is already linked to this Parent Center.');
        header('Location: dashboard.php');
        exit;
    }

    // Enforce one "parent" penguin link per parent, when schema supports it.
    if ($role === 'parent' && parent_children_has_role_column()) {
        $stmt = db()->prepare('SELECT 1 FROM parent_children WHERE parentId = ? AND role = "parent" LIMIT 1');
        $stmt->bind_param('i', $parentId);
        $stmt->execute();
        $already = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($already) {
            flash_set('error', 'A parent penguin is already linked. Unlink it first to change.');
            header('Location: dashboard.php');
            exit;
        }
    }

    $aliasVal = $alias !== '' ? $alias : null;
    if (parent_children_has_role_column()) {
        $stmt = db()->prepare('INSERT INTO parent_children (parentId, userId, alias, role, claimedAt) VALUES (?, ?, ?, ?, NOW())');
        $stmt->bind_param('iiss', $parentId, $uid, $aliasVal, $role);
    } else {
        $stmt = db()->prepare('INSERT INTO parent_children (parentId, userId, alias, claimedAt) VALUES (?, ?, ?, NOW())');
        $stmt->bind_param('iis', $parentId, $uid, $aliasVal);
    }
    $ok = $stmt->execute();
    $stmt->close();

    if (!$ok) {
        flash_set('error', 'Failed to link account. Please try again.');
        header('Location: claim.php');
        exit;
    }

    flash_set('success', 'Account linked successfully.');
    header('Location: dashboard.php');
    exit;
}

$pageTitle = 'Claim Account - Parent Center';
include __DIR__ . '/includes/parent_header.php';
?>

<div class="cp-card">
  <h1 class="cp-title">Claim Account</h1>
  <p class="cp-subtitle">
    <?php if ($defaultRole === 'parent'): ?>
      Link your own in-game penguin to your Parent Center profile by verifying your login once.
    <?php else: ?>
      Link an existing child penguin to your Parent Center by verifying their login once.
    <?php endif; ?>
  </p>

  <?php if ($msg = flash_get('error')): ?>
    <div class="cp-alert cp-alert--error"><?php echo e($msg); ?></div>
  <?php endif; ?>

  <form method="post" class="cp-form" action="claim.php">
    <input type="hidden" name="csrf" value="<?php echo e(csrf_token()); ?>"/>
    <input type="hidden" name="role" value="<?php echo e($defaultRole); ?>"/>
    <label class="cp-label"><?php echo $defaultRole === 'parent' ? 'Your Penguin Username' : 'Child Username'; ?></label>
    <input class="cp-input" name="username" maxlength="12" required/>

    <label class="cp-label"><?php echo $defaultRole === 'parent' ? 'Your Penguin Password' : 'Child Password'; ?></label>
    <input class="cp-input" type="password" name="password" required/>

    <label class="cp-label">Parent Label (optional)</label>
    <input class="cp-input" name="alias" placeholder="e.g., Son, Daughter, Name, etc."/>

    <button class="cp-btn cp-btn--primary" type="submit">Link Account</button>
    <a class="cp-btn" style="background:rgba(0,0,0,.05);" href="dashboard.php">Cancel</a>
  </form>

  <div class="cp-footnote">
    This website is private-access. Parent Center is not intended to circumvent the private registration, and all associated accounts will be banned.
  </div>
</div>

<?php include __DIR__ . '/includes/parent_footer.php'; ?>