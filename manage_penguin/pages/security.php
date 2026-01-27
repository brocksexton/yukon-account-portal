<div class="cp-card">
      <h2 class="cp-h2">Security</h2>
      <div class="cp-subtitle">Change your website password. (Minimum 8 characters.)</div>

      <form method="post" class="cp-form" action="index.php?tab=security" style="margin-top:14px;max-width:520px">
        <input type="hidden" name="csrf" value="<?php echo e(csrf_token()); ?>"/>
        <input type="hidden" name="action" value="change_password"/>

        <label class="cp-label" for="current_password">Current password</label>
        <input class="cp-input" id="current_password" name="current_password" type="password" required />

        <label class="cp-label" for="new_password">New password</label>
        <input class="cp-input" id="new_password" name="new_password" type="password" minlength="8" required />

        <label class="cp-label" for="confirm_password">Confirm new password</label>
        <input class="cp-input" id="confirm_password" name="confirm_password" type="password" minlength="8" required />

        <div style="margin-top:14px">
          <button type="submit" class="cp-btn cp-btn--primary">Update password</button>
        </div>
      </form>

      <div class="cp-alert" style="margin-top:16px">
        <div style="font-weight:900">Planned security upgrades</div>
        <ul class="cp-text" style="margin:8px 0 0 18px">
          <li>Email + recovery tools</li>
          <li>2FA (TOTP) and backup codes</li>
          <li>Recent logins + sessions</li>
        </ul>
      </div>
    </div>