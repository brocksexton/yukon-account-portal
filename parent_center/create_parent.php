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

    $parentEmail = trim($_POST['parent_email'] ?? '');
    $parentPassword = $_POST['parent_password'] ?? '';
    $parentPassword2 = $_POST['parent_password2'] ?? '';

    $penguinUsername = trim($_POST['penguin_username'] ?? '');
    $penguinPassword = $_POST['penguin_password'] ?? '';
    $alias = trim($_POST['alias'] ?? '');

    if (!filter_var($parentEmail, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid parent email address.';
    } elseif ($parentPassword === '' || strlen($parentPassword) < 8) {
        $error = 'Parent password must be at least 8 characters.';
    } elseif ($parentPassword2 === '' || !hash_equals($parentPassword, $parentPassword2)) {
        $error = 'Parent passwords do not match.';
    } else {
        // Check parent email uniqueness.
        $stmt = db()->prepare('SELECT id FROM parents WHERE email = ? LIMIT 1');
        $stmt->bind_param('s', $parentEmail);
        $stmt->execute();
        $existsParent = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($existsParent) {
            $error = 'That parent email is already registered. Try signing in instead.';
        } else {
            // Verify the penguin account exists, password matches, and it is not banned.
            $penguinErr = null;
            $penguin = parent_penguin_authenticate($penguinUsername, $penguinPassword, $penguinErr);

            if (!$penguin) {
                $error = $penguinErr ?: 'Unable to verify the penguin account.';
            } else {
                $penguinId = (int)$penguin['id'];

                // Prevent one penguin from being used to create multiple Parent Center accounts.
                if (parent_penguin_is_linked_as_parent($penguinId)) {
                    $error = 'That penguin is already linked to an existing Parent Center account.';
                } else {
                    // Create the parent account and link the penguin as the "parent" role.
                    $hash = password_hash($parentPassword, PASSWORD_BCRYPT);

                    try {
                        db()->begin_transaction();

                        $stmt = db()->prepare('INSERT INTO parents (email, password, createdAt) VALUES (?, ?, NOW())');
                        $stmt->bind_param('ss', $parentEmail, $hash);
                        $ok = $stmt->execute();
                        $parentId = (int)$stmt->insert_id;
                        $stmt->close();

                        if (!$ok || $parentId <= 0) {
                            throw new Exception('Failed to create the parent account.');
                        }

                        $aliasVal = ($alias !== '' ? $alias : 'My Penguin');
                        $okLink = parent_link_account($parentId, $penguinId, $aliasVal, 'parent');
                        if (!$okLink) {
                            throw new Exception('Parent account created, but failed to link your penguin.');
                        }

                        db()->commit();

                        // Log them in.
                        $_SESSION['parent_id'] = $parentId;
                        flash_set('success', 'Parent account created. Welcome to the Parent Center.');
                        header('Location: dashboard.php');
                        exit;
                    } catch (Throwable $e) {
                        // Best-effort rollback; ignore failures.
                        try { db()->rollback(); } catch (Throwable $ignored) {}
                        $error = $e->getMessage();
                    }
                }
            }
        }
    }
}

$pageTitle = 'Create Parent Account - Parent Center';
require_once __DIR__ . '/includes/parent_header.php';
?>

<div class="pc-shell">
  <div class="pc-card pc-authPop">
    <div class="pc-titleRow">
      <img class="pc-heroIcon" src="assets/pc/parents-icon.png" alt="">
      <div>
        <h1 class="pc-title" style="margin:0;">CREATE PARENT ACCOUNT</h1>
        <p class="pc-subtitle" style="margin:6px 0 0;">Parent accounts require an existing, active penguin account.</p>
      </div>
    </div>

    <?php if ($error): ?>
      <div class="pc-alert pc-alert--error" role="alert"><?php echo e($error); ?></div>
    <?php endif; ?>

    <div class="pc-authGrid">
      <section class="pc-authBox" aria-label="Parent Center Details">
        <div class="pc-authBoxTitle">Parent Center Details</div>

        <form method="post" class="cp-form" action="create_parent.php" autocomplete="on">
          <input type="hidden" name="csrf" value="<?php echo e(csrf_token()); ?>"/>

          <label class="cp-label" for="parent_email">Parent Email</label>
          <input class="cp-input" id="parent_email" name="parent_email" type="email" required autocomplete="email" value="<?php echo e($_POST['parent_email'] ?? ''); ?>"/>

          <label class="cp-label" for="parent_password">Parent Password</label>
          <input class="cp-input" id="parent_password" name="parent_password" type="password" required autocomplete="new-password"/>

          <label class="cp-label" for="parent_password2">Confirm Password</label>
          <input class="cp-input" id="parent_password2" name="parent_password2" type="password" required autocomplete="new-password"/>

          <label class="cp-label" for="alias">Label for your penguin (optional)</label>
          <input class="cp-input" id="alias" name="alias" maxlength="32" placeholder="e.g., My Account" value="<?php echo e($_POST['alias'] ?? ''); ?>"/>

          <div class="pc-authDivider"></div>

          <div class="pc-authBoxTitle" style="margin-top:0;">Verify Your Penguin Account</div>
          <p class="pc-muted" style="margin:0 0 12px;">
            This must be an existing penguin account that is not banned. If you do not have a penguin account yet, you must be invited through the friends-only registration flow.
          </p>

          <label class="cp-label" for="penguin_username">Penguin Username</label>
          <input class="cp-input" id="penguin_username" name="penguin_username" maxlength="12" required value="<?php echo e($_POST['penguin_username'] ?? ''); ?>"/>

          <label class="cp-label" for="penguin_password">Penguin Password</label>
          <input class="cp-input" id="penguin_password" name="penguin_password" type="password" required autocomplete="current-password"/>

          <div class="pc-authCtas">
            <button class="pc-btn pc-btn--primary" type="submit">Create Parent Account</button>
            <a class="pc-btn" href="index.php">Back to Login</a>
          </div>
        </form>

        <div class="pc-authHint">
          Security note: Penguin passwords are verified against the game database and are not stored by the Parent Center.
        </div>
      </section>

      <aside class="pc-authBox" aria-label="What Parents Can Do">
        <div class="pc-authBoxTitle">What parents can do</div>
        <div class="pc-authCardSmall">
          <ul class="pc-bulletList">
            <li>Create child accounts without using friend registration codes</li>
            <li>Link existing child accounts (username + password)</li>
            <li>View playercards, friends lists, inventory, and puffles</li>
            <li>See ban history and safety alerts in one place</li>
          </ul>
        </div>

        <div class="pc-authDivider"></div>

        <div class="pc-authBoxTitle">Requirements</div>
        <div class="pc-authCardSmall">
          <ul class="pc-bulletList">
            <li>Must have an existing penguin account</li>
            <li>Penguin must be active (not permanently or currently banned)</li>
            <li>One penguin can only be used for one Parent Center account</li>
          </ul>
        </div>

        <div class="pc-authHint">
          Once created, you can add multiple children under your Parent Center dashboard.
        </div>
      </aside>
    </div>

    <div class="pc-footnote" style="margin-top:18px;">
      Parents will be able to create child accounts that are not tied to friend registration codes. Public registration remains invite-only.
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/includes/parent_footer.php'; ?>
