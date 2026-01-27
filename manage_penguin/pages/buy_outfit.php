<?php
require_once __DIR__ . '/../includes/manage_header.php';

require_login();

// Accept both ?shareCode=XXXX and legacy ?share=XXXX
$share = trim((string)($_GET['shareCode'] ?? $_GET['share'] ?? ''));
if ($share === '') {
  http_response_code(400);
  die('Missing share code.');
}

// Share codes are generated as hex; strip anything unexpected.
$share = preg_replace('/[^a-f0-9]/i', '', (string)$share);

$csrf = csrf_token();

// Build preview URL (same host) for the outfit paper render
$preview = $h('yukon/assets/media/paper/outfit/' . urlencode($share) . '.png');

?>
<link rel="stylesheet" href="<?php echo $h('manage_penguin/css/outfit_store.css'); ?>">

<div class="mp-checkout">
  <div class="mp-checkout-head">
    <div>
      <h1>Outfit Checkout</h1>
      <p class="mp-subtitle">Review items, confirm cost, and save this look to your library.</p>
    </div>
    <div class="mp-checkout-actions">
      <a class="mp-link" href="<?php echo $h('manage_penguin/index.php?tab=my_outfits'); ?>">Back to Manage My Penguin</a>
      <a class="mp-link" href="<?php echo $h('manage_penguin/pages/public_outfits.php'); ?>">Public Outfits</a>
    </div>
  </div>

  <div class="mp-checkout-grid">
    <section class="mp-card" aria-label="Outfit preview">
      <div class="mp-card-head">
        <div>
          <h2 id="outfitTitle" style="margin:0">Outfit</h2>
          <div class="mp-subtle" id="outfitMeta">Share code: <span class="mp-mono"><?= htmlspecialchars($share) ?></span></div>
        </div>
        <div class="mp-pill" id="ownershipPill">Checking ownership…</div>
      </div>

      <div class="mp-preview-wrap">
        <div class="mp-preview-frame">
          <img id="outfitPreview" src="<?= htmlspecialchars($preview) ?>" alt="Outfit preview" loading="lazy" onerror="this.style.opacity=.35;this.alt='Preview unavailable';" />
        </div>
      </div>

      <div class="mp-section">
        <h3 class="mp-section-title">Items in this outfit</h3>
        <div id="cartList" class="mp-cart" role="list"></div>
      </div>
    </section>

    <aside class="mp-card mp-summary" aria-label="Order summary">
      <div class="mp-card-head">
        <div>
          <h2 style="margin:0">Order Summary</h2>
          <div class="mp-subtle">Pay using Penguin Coins</div>
        </div>
        <div class="mp-coin-badge">
          <img src="<?php echo $h('manage_penguin/assets/coin.png'); ?>" alt="Coin" />
          <span id="yourBalance">—</span>
        </div>
      </div>

      <div class="mp-summary-lines">
        <div class="mp-line"><span>Outfit total value</span><span id="totalValue">—</span></div>
        <div class="mp-line"><span>Already owned discount</span><span id="ownedDiscount">—</span></div>
        <div class="mp-divider"></div>
        <div class="mp-line mp-total"><span>Total due now</span><span id="totalDue">—</span></div>
        <div class="mp-subtle" id="afterPurchase">—</div>
      </div>

      <div class="mp-cta">
        <button id="confirmBtn" class="mp-btn-primary" disabled>
          <img src="<?php echo $h('manage_penguin/assets/coin.png'); ?>" alt="Coin" />
          <span id="confirmText">Loading…</span>
        </button>
        <div class="mp-help" id="helpText">We only charge for items you don’t already own.</div>
      </div>

      <div class="mp-fineprint">
        <div class="mp-subtle">Tip: Items you already own will be marked as <strong>Owned</strong> and cost <strong>0 coins</strong> in the cart.</div>
      </div>
    </aside>
  </div>
</div>

<script>
(() => {
  const shareCode = <?= json_encode($share) ?>;
  const csrfToken = <?= json_encode($csrf) ?>;
  const cartList = document.getElementById('cartList');
  const outfitTitle = document.getElementById('outfitTitle');
  const outfitMeta = document.getElementById('outfitMeta');
  const ownershipPill = document.getElementById('ownershipPill');
  const yourBalance = document.getElementById('yourBalance');
  const totalValue = document.getElementById('totalValue');
  const ownedDiscount = document.getElementById('ownedDiscount');
  const totalDue = document.getElementById('totalDue');
  const afterPurchase = document.getElementById('afterPurchase');
  const confirmBtn = document.getElementById('confirmBtn');
  const confirmText = document.getElementById('confirmText');
  const helpText = document.getElementById('helpText');

  // API endpoint for quoting and purchasing outfits. Build using PHP helper
  // so it respects the site root when installed in a subdirectory.
  const apiUrl = <?= json_encode($h('manage_penguin/api/outfit_purchase.php')); ?>;

  const fmt = (n) => new Intl.NumberFormat().format(Number(n || 0));

  function row(item) {
    const el = document.createElement('div');
    el.className = 'mp-cart-row' + (item.owned ? ' is-owned' : '');

    const icon = item.iconUrl ? `<img class="mp-item-icon" src="${item.iconUrl}" alt="">` : `<div class="mp-item-icon mp-item-icon--blank"></div>`;
    const badge = item.owned ? `<span class="mp-badge mp-badge--owned">Owned</span>` : `<span class="mp-badge mp-badge--missing">Missing</span>`;

    el.innerHTML = `
      <div class="mp-cart-left">
        ${icon}
        <div>
          <div class="mp-item-name">${escapeHtml(item.name || ('Item ' + item.itemId))}</div>
          <div class="mp-item-meta">${escapeHtml(item.slotLabel || 'Item')} • ID ${escapeHtml(String(item.itemId))}</div>
        </div>
      </div>
      <div class="mp-cart-right">
        ${badge}
        <div class="mp-price">
          ${item.owned ? `<span class="mp-was">${fmt(item.cost)}c</span>` : ``}
          <span class="mp-now">${fmt(item.charged)}c</span>
        </div>
      </div>
    `;
    return el;
  }

  function escapeHtml(s) {
    return String(s).replace(/[&<>"']/g, (c) => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
  }

  async function post(action) {
    // Map UI actions to API actions: 'quote' calls quote; any other action triggers purchase & save.
    const mapped = (action === 'quote') ? 'quote' : 'purchase_and_save';
    const r = await fetch(apiUrl, {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-CSRF-Token': csrfToken },
      body: new URLSearchParams({ action: mapped, shareCode, csrf: csrfToken })
    });
    const data = await r.json().catch(() => null);
    if (!r.ok || !data) {
      const msg = (data && data.error) ? data.error : `Request failed (${r.status})`;
      throw new Error(msg);
    }
    if (!data.ok) throw new Error(data.error || 'Request failed');
    return data;
  }

  function setPill(mode, missing, totalDueNow) {
    ownershipPill.classList.remove('is-ok','is-warn');
    if (mode === 'allOwned') {
      ownershipPill.classList.add('is-ok');
      ownershipPill.textContent = 'All items owned • Free save';
    } else {
      ownershipPill.classList.add('is-warn');
      ownershipPill.textContent = `${missing} missing • ${fmt(totalDueNow)}c due`;
    }
  }

  function setCTA(totalDueNow, canAfford) {
    if (totalDueNow <= 0) {
      confirmText.textContent = 'Save Outfit for Free';
      helpText.textContent = 'You already own everything in this outfit — saving is free.';
      confirmBtn.disabled = false;
      confirmBtn.dataset.mode = 'save';
      return;
    }

    confirmText.textContent = 'Confirm Purchase & Save';
    confirmBtn.dataset.mode = 'purchase';

    if (!canAfford) {
      confirmBtn.disabled = true;
      helpText.textContent = 'You do not have enough coins for this purchase.';
      return;
    }

    confirmBtn.disabled = false;
    helpText.textContent = 'You will only be charged for missing items.';
  }

  async function load() {
    cartList.innerHTML = '<div class="mp-subtle">Loading cart…</div>';
    try {
      const q = await post('quote');

      // Header
      outfitTitle.textContent = q.outfit.name || 'Outfit';
      outfitMeta.innerHTML = `Share code: <span class="mp-mono">${escapeHtml(q.outfit.shareCode)}</span> • By ${escapeHtml(q.outfit.author || 'Unknown')}`;

      // Cart
      cartList.innerHTML = '';
      ((q.quote && q.quote.items) ? q.quote.items : []).forEach(it => cartList.appendChild(row(it)));
      if (!q.quote || !q.quote.items || q.quote.items.length === 0) {
        cartList.innerHTML = '<div class="mp-subtle">This outfit does not contain any wearable items.</div>';
      }

      // Totals
      yourBalance.textContent = `${fmt(q.quote.coins)}c`;
      totalValue.textContent = `${fmt(q.quote.totalValue)}c`;
      const discount = Number(q.quote.totalValue || 0) - Number(q.quote.totalCharged || 0);
      ownedDiscount.textContent = `-${fmt(discount)}c`;
      totalDue.textContent = `${fmt(q.quote.totalCharged)}c`;

      const after = Number(q.quote.coins || 0) - Number(q.quote.totalCharged || 0);
      afterPurchase.textContent = `After checkout: ${fmt(after)}c`;

      setPill(q.quote.totalCharged <= 0 ? 'allOwned' : 'missing', (q.quote.missingItems ? q.quote.missingItems.length : 0), q.quote.totalCharged || 0);
      setCTA(Number(q.quote.totalCharged || 0), !!q.quote.canAfford);

    } catch (e) {
      cartList.innerHTML = `<div class="mp-error">${escapeHtml(e.message)}</div>`;
      ownershipPill.textContent = 'Unable to load';
      confirmText.textContent = 'Unavailable';
      confirmBtn.disabled = true;
    }
  }

  confirmBtn.addEventListener('click', async () => {
    const mode = confirmBtn.dataset.mode;
    const action = (mode === 'save') ? 'save' : 'purchase';

    confirmBtn.disabled = true;
    const prev = confirmText.textContent;
    confirmText.textContent = (action === 'save') ? 'Saving…' : 'Processing…';

    try {
      const res = await post(action);
      // Always send users back to their outfits.
      window.location.href = '/manage_penguin/index.php?tab=my_outfits&purchase=1';
    } catch (e) {
      confirmText.textContent = prev;
      confirmBtn.disabled = false;
      alert(e.message);
    }
  });

  load();
})();
</script>

<?php require_once __DIR__ . '/../includes/manage_footer.php'; ?>
