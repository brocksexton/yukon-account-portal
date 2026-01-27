  </div><!-- /container -->

  <div class="layout_floatHack__GVfv2"></div>

  <div class="layout_footerWrapper__mzB1N">
    <div class="layout_footer__D7zM5">
      <div class="layout_inner__ZkDmw">
        <div class="layout_logo___NFT7">
          <a href="index.php" class="cp-logoText cp-logoText--small" aria-label="<?php echo e(SITE_NAME); ?>">
            <span class="cp-logoLine1">CLUB</span>
            <span class="cp-logoLine2">PENGUIN</span>
          </a>
        </div>

        <div class="layout_middle__6lX8s px-4">
          <div class="layout_links__MWmdl">
            <!-- Use root‑relative paths so links work from nested directories like /manage_penguin/ -->
            <a href="/index.php">Home</a>
            <a href="/manage_penguin/">Manage My Penguin</a>
            <a href="/parent_center/index.php">Parent Center</a>
            <a href="/login.php">Login</a>
            <a href="#" onclick="return false;">Invite Only</a>
          </div>
          <div class="layout_disclaimer__GGt6m"><?php echo e(PRIVATE_ACCESS_NOTICE); ?></div>
          <div class="layout_copyright__lI1mk pt-3">&copy; <?php echo e(SITE_NAME); ?> <?php echo date('Y'); ?></div>
        </div>

        <div class="layout_social__H3Ted">
          <?php if (DISCORD_URL): ?>
            <a href="<?php echo e(DISCORD_URL); ?>" target="_blank" class="layout_socialLink__n0Req mx-2">
              <span class="cp-social">Discord</span>
            </a>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</div><!-- /layout_layout -->

<script>
  (function(){
    var hamburger = document.getElementById('hamburger');
    var menu = document.getElementById('mobileMenu');
    if(!hamburger || !menu) return;
    hamburger.addEventListener('click', function(){
      menu.classList.toggle('layout_open__xfXDt');
    });
  })();
</script>
</body>
</html>
