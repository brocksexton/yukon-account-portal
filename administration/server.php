<?php
require_once __DIR__ . '/includes/admin_bootstrap.php';
admin_require_staff();

// Server controls are extra-restricted (rank + IP allowlist)
admin_require_server_control();

$title = 'Server';
$activeNav = 'server';

$cwd = admin_yukon_server_dir();
$statusOut = '';
$logsOut = '';
$note = null;

if (!$cwd) {
    $note = 'Could not locate your yukon-server directory. Set ADMIN_YUKON_SERVER_DIR in /administration/config/server_control.php';
} else {
    // Handle actions
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        csrf_require();
        $action = (string)($_POST['action'] ?? '');
        $cmd = admin_server_cmd($action);
        if (!$cmd) {
            flash_set('error', 'Invalid action.');
            header('Location: ' . admin_url('server.php'));
            exit;
        }

        $timeout = 8;
        if (in_array($action, ['start','stop','restart'], true)) $timeout = 12;
        if ($action === 'logs') $timeout = 4;

        $res = admin_run_cmd($cmd, $cwd, $timeout);
        if (($res['code'] ?? 1) === 0) {
            flash_set('success', strtoupper($action) . ' executed.');
        } else {
            flash_set('error', strtoupper($action) . ' returned a non-zero code.');
        }
        // Store last output for quick peek
        $_SESSION['admin_last_cmd_out'] = $res['out'] ?? '';
        header('Location: ' . admin_url('server.php'));
        exit;
    }

    // Always show status
    $status = admin_run_cmd(admin_server_cmd('list') ?? '', $cwd, 6);
    $statusOut = $status['out'] ?? '';

    // Pull logs (timeboxed) for dashboard view
    $logs = admin_run_cmd(admin_server_cmd('logs') ?? '', $cwd, 3);
    $logsOut = $logs['out'] ?? '';
}

include __DIR__ . '/includes/admin_header.php';
?>
<div class="adm-hero">
  <div class="adm-hero__inner">
    <div>
      <h1>Server Console</h1>
      <p>PM2 / yukon-server controls and live logs (local-only).</p>
    </div>
    <div class="adm-hero__art" aria-hidden="true"></div>
  </div>
</div>

<div class="adm-grid2">
  <section class="adm-card">
    <div class="adm-card__head">
      <div>
        <h2>Status</h2>
        <p>Output from <code>npm run list</code> in yukon-server.</p>
      </div>
      <?php if ($cwd): ?>
        <span class="adm-chip">Dir: <?php echo e($cwd); ?></span>
      <?php endif; ?>
    </div>

    <?php if ($note): ?>
      <div class="adm-alert adm-alert--warn"><?php echo e($note); ?></div>
    <?php endif; ?>

    <?php if ($statusOut): ?>
      <pre class="adm-pre"><?php echo e($statusOut); ?></pre>
    <?php else: ?>
      <div class="adm-muted">No status output.</div>
    <?php endif; ?>

    <?php $last = (string)($_SESSION['admin_last_cmd_out'] ?? ''); ?>
    <?php if ($last): ?>
      <div class="adm-subhead">Last command output</div>
      <pre class="adm-pre adm-pre--soft"><?php echo e($last); ?></pre>
      <?php unset($_SESSION['admin_last_cmd_out']); ?>
    <?php endif; ?>
  </section>

  <section class="adm-card">
    <div class="adm-card__head">
      <div>
        <h2>Controls</h2>
        <p>Whitelisted actions only. Requires Owner/Founder + localhost.</p>
      </div>
    </div>

    <?php if (!$cwd): ?>
      <div class="adm-muted">Set <code>ADMIN_YUKON_SERVER_DIR</code> to enable controls.</div>
    <?php else: ?>
      <div class="adm-actions">
        <form method="post" class="adm-actions__form">
          <input type="hidden" name="csrf" value="<?php echo e(csrf_token()); ?>">
          <button class="adm-btn" name="action" value="start" type="submit">Start</button>
          <button class="adm-btn adm-btn--warn" name="action" value="restart" type="submit">Restart</button>
          <button class="adm-btn adm-btn--danger" name="action" value="stop" type="submit" onclick="return confirm('Stop all production servers?');">Stop</button>
        </form>
      </div>
      <div class="adm-tip">
        Tip: these buttons execute <code>npm run start/stop/restart</code> in your yukon-server folder (PM2).
      </div>
    <?php endif; ?>
  </section>
</div>

<section class="adm-card" style="margin-top:16px;">
  <div class="adm-card__head">
    <div>
      <h2>Live Logs</h2>
      <p>Snapshot from <code>npm run logs</code>. Auto-refreshes while this page is open.</p>
    </div>
    <div class="adm-actions">
      <button class="adm-btn adm-btn--ghost" id="admLogRefresh" type="button">Refresh</button>
    </div>
  </div>

  <?php if (!$cwd): ?>
    <div class="adm-muted">Set <code>ADMIN_YUKON_SERVER_DIR</code> to load logs.</div>
  <?php else: ?>
    <pre class="adm-pre" id="admLogs"><?php echo e($logsOut ?: ''); ?></pre>
    <div class="adm-muted" style="margin-top:8px;">Showing a timeboxed snapshot (prevents hanging requests).</div>
  <?php endif; ?>
</section>

<script>
(function(){
  const logsEl = document.getElementById('admLogs');
  const btn = document.getElementById('admLogRefresh');
  if (!logsEl || !btn) return;

  async function refresh(){
    try{
      const res = await fetch('<?php echo e(admin_url('server_logs.php')); ?>', {credentials:'same-origin'});
      if(!res.ok) throw new Error('HTTP '+res.status);
      const txt = await res.text();
      logsEl.textContent = txt;
    }catch(e){
      // keep quiet; avoid spamming
    }
  }

  btn.addEventListener('click', refresh);
  // auto refresh every 4s
  setInterval(refresh, 4000);
})();
</script>

<?php include __DIR__ . '/includes/admin_footer.php'; ?>
