<?php
// manage_penguin/pages/outfit.php
// "Studio"-style outfit viewer: icon grid browsing + live playercard-ish preview.

$slots = slot_list();

// Load owned item IDs
$owned = [];
$stmt = db()->prepare('SELECT itemId FROM inventories WHERE userId = ? ORDER BY itemId ASC');
$stmt->bind_param('i', $user['id']);
$stmt->execute();
$res = $stmt->get_result();
while ($r = $res->fetch_assoc()) $owned[] = (int)$r['itemId'];
$stmt->close();

// Group owned wearable items by slot using Yukon items.json type
$ownedBySlot = [];
foreach ($slots as $s) $ownedBySlot[$s] = [];
foreach ($owned as $iid) {
  $slot = item_slot_of($iid, $ITEMS_META);
  if (!$slot) continue;
  $ownedBySlot[$slot][] = $iid;
}

// Current equipped state
$current = [];
foreach ($slots as $s) $current[$s] = (int)($user[$s] ?? 0);

// Helper: render a DOM-updatable avatar stack (no GD)
function mp_render_live_avatar(array $state, string $idPrefix = 'draft', int $size = 260): void {
  $size = max(180, min(420, (int)$size));
  $color = (int)($state['color'] ?? 1);
  if ($color < 1) $color = 1;
  $photo = (int)($state['photo'] ?? 0);
  $flag  = (int)($state['flag']  ?? 0);
  $paper = [
    'feet' => (int)($state['feet'] ?? 0),
    'body' => (int)($state['body'] ?? 0),
    'neck' => (int)($state['neck'] ?? 0),
    'face' => (int)($state['face'] ?? 0),
    'head' => (int)($state['head'] ?? 0),
    'hand' => (int)($state['hand'] ?? 0),
  ];

  $bg = '';
  if ($photo > 0) $bg = '/yukon/assets/media/clothing/photos/' . $photo . '.png';

  echo '<div class="mp-cardStage" id="mpStage-' . e($idPrefix) . '">';
  echo '  <div class="mp-stageTitle">' . ($idPrefix === 'current' ? 'Currently Equipped' : 'Preview Changes') . '</div>';
  echo '  <div class="cp-avatar-stack cp-avatar-card mp-avatarBig" style="width:' . (int)$size . 'px;height:' . (int)$size . 'px;' . ($bg ? ('background-image:url(' . e($bg) . ');') : '') . '" data-role="avatar" data-prefix="' . e($idPrefix) . '">';
  echo '    <div class="cp-avatar-penguin" aria-hidden="true">';
  // Colour layer: use the Yukon penguin colour asset instead of
  // /assets/img/colours. This prevents missing asset errors on hosts
  // without the colours folder and matches the preview styling in the
  // Outfit Studio.
  echo '      <img class="cp-avatar-colour" data-layer="colour" src="/yukon/assets/media/color/penguin/' . (int)$color . '.png" alt="">';
  echo '      <img class="cp-avatar-top" data-layer="top" src="/assets/img/penguin_transparent.png" alt="">';
  foreach ($paper as $slot => $id) {
    $src = $id > 0 ? ('/yukon/assets/media/clothing/paper/' . (int)$id . '.png') : '';
    echo '      <img class="cp-avatar-layer" data-layer="' . e($slot) . '" src="' . e($src) . '" alt="" ' . ($id > 0 ? '' : 'style="display:none"') . '>';
  }
  echo '    </div>';
  $pin = $flag > 0 ? ('/yukon/assets/media/clothing/icon/120/' . (int)$flag . '.png') : '';
  echo '    <img class="cp-avatar-pin" data-layer="pin" src="' . e($pin) . '" alt="" ' . ($flag > 0 ? '' : 'style="display:none"') . '>';
  echo '  </div>';
  echo '</div>';
}

?>

<div class="mp-studio">
  <div class="mp-studioLeft">
    <div class="cp-card mp-sticky mp-studioPanel">
      <div class="mp-studioHeader">
        <div>
          <h2 class="cp-h2" style="margin:0">Outfit Studio</h2>
          <div class="cp-subtitle" style="margin-top:4px">Browse your owned items like in-game, equip instantly, and save. Server verifies ownership + correct slot types.</div>
        </div>

        <div class="mp-studioHeaderRight">
          <div class="mp-toggle" role="tablist" aria-label="Preview mode">
            <!-- Default view is Preview. Users can switch to Compare/Current. -->
            <button type="button" class="mp-toggleBtn" id="mpViewSplit">Compare</button>
            <button type="button" class="mp-toggleBtn" id="mpViewCurrent">Current</button>
            <button type="button" class="mp-toggleBtn is-active" id="mpViewDraft">Preview</button>
          </div>
        </div>
      </div>

      <div class="mp-stageWrap" id="mpStageWrap">
        <?php mp_render_live_avatar($current, 'current', 260); ?>
        <?php mp_render_live_avatar($current, 'draft', 260); ?>
      </div>

      <div class="mp-equipGrid" id="mpEquipGrid">
        <?php foreach ($slots as $s): $id = (int)$current[$s]; ?>
          <div class="mp-equipTile" data-slot="<?php echo e($s); ?>">
            <div class="mp-equipIcon">
              <img src="<?php echo e($s === 'color' ? ('/yukon/assets/media/color/icon/120/' . (int)$id . '.png') : clothing_icon_url((int)$id)); ?>" onerror="this.style.display='none'" alt="">
            </div>
            <div class="mp-equipMeta">
              <div class="mp-equipSlot"><?php echo e(slot_label($s)); ?></div>
              <div class="mp-equipId" id="mpEquip-<?php echo e($s); ?>"><?php echo $id > 0 ? ('#' . (int)$id) : 'None'; ?></div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>

      <div class="mp-studioActions">
        <button type="button" class="cp-btn cp-btn-soft" id="mpResetBtn">Reset</button>
        <form method="post" style="display:inline">
          <input type="hidden" name="csrf_token" value="<?php echo e(csrf_token()); ?>">
          <?php foreach ($slots as $s): ?>
            <input type="hidden" name="slot_<?php echo e($s); ?>" id="slot_<?php echo e($s); ?>" value="<?php echo (int)$current[$s]; ?>">
          <?php endforeach; ?>
          <button class="cp-btn" name="action" value="save_outfit">Save Outfit</button>
        </form>
      </div>
    </div>
  </div>

  <div class="mp-studioRight">
    <div class="cp-card">
      <div class="mp-browserTop">
        <div class="mp-slotChips" id="mpSlotChips">
          <button type="button" class="mp-chip is-active" data-filter="all">All</button>
          <?php foreach ($slots as $s): ?>
            <button type="button" class="mp-chip" data-filter="<?php echo e($s); ?>"><?php echo e(slot_label($s)); ?></button>
          <?php endforeach; ?>
        </div>

      <div class="mp-browserControls">
          <input id="mpSearch" class="cp-input" type="search" placeholder="Search by name or #ID…" autocomplete="off">
          <div class="mp-count"><span id="mpCount">0</span> items</div>
        </div>
      </div>

      <div class="mp-pager" id="mpPagerTop" aria-label="Item pages">
        <button type="button" class="mp-pageBtn" data-page="prev">Prev</button>
        <div class="mp-pageStat"><span id="mpPageNow">1</span> / <span id="mpPageTotal">1</span></div>
        <button type="button" class="mp-pageBtn" data-page="next">Next</button>
      </div>

      <div id="mpGrid" class="mp-itemGrid mp-itemGrid--big">
        <?php
          foreach ($slots as $s) {
            foreach ($ownedBySlot[$s] as $iid) {
              $name = item_display_name($iid, $ITEMS_META);
              $src = ($s === 'color') ? ('/yukon/assets/media/color/icon/120/' . (int)$iid . '.png') : clothing_icon_url($iid);
              echo '<button type="button" class="mp-item" data-item-id="' . (int)$iid . '" data-slot="' . e($s) . '" data-name="' . e(strtolower($name)) . '">';
              echo '  <img src="' . e($src) . '" onerror="this.style.opacity=0.25" alt="' . e($name) . '">';
              echo '  <div class="mp-itemMeta"><div class="mp-itemName">' . e($name) . '</div><div class="mp-itemId">#' . (int)$iid . '</div></div>';
              echo '</button>';
            }
          }
        ?>
      </div>

      <div class="mp-pager" id="mpPagerBottom" aria-label="Item pages">
        <button type="button" class="mp-pageBtn" data-page="prev">Prev</button>
        <div class="mp-pageStat"><span id="mpPageNow2">1</span> / <span id="mpPageTotal2">1</span></div>
        <button type="button" class="mp-pageBtn" data-page="next">Next</button>
      </div>

    </div>
  </div>
</div>

<script>
(function(){
  const slots = <?php echo json_encode($slots); ?>;
  const current = <?php echo json_encode($current); ?>;
  const state = JSON.parse(JSON.stringify(current)); // draft state

  const grid = document.getElementById('mpGrid');
  const search = document.getElementById('mpSearch');
  const countEl = document.getElementById('mpCount');
  const chips = document.getElementById('mpSlotChips');
  let activeFilter = 'all';

  // Pagination (prevents infinite scroll when inventories get large)
  const PER_PAGE = 60;
  let page = 1;
  const pageNow = document.getElementById('mpPageNow');
  const pageTotal = document.getElementById('mpPageTotal');
  const pageNow2 = document.getElementById('mpPageNow2');
  const pageTotal2 = document.getElementById('mpPageTotal2');
  const pagerTop = document.getElementById('mpPagerTop');
  const pagerBottom = document.getElementById('mpPagerBottom');

  function setHidden(){
    slots.forEach(s=>{
      const input = document.getElementById('slot_'+s);
      if (input) input.value = state[s] || 0;
    });
  }

  function setEquipTiles(){
    slots.forEach(s=>{
      const id = state[s] || 0;
      const idEl = document.getElementById('mpEquip-'+s);
      if (idEl) idEl.textContent = id > 0 ? ('#'+id) : 'None';
      const tile = document.querySelector('.mp-equipTile[data-slot="'+s+'"]');
      const img = tile ? tile.querySelector('img') : null;
      if (img) {
        img.style.display = '';
        img.src = (s==='color')
          ? ('/yukon/assets/media/color/icon/120/'+id+'.png')
          : ('/yukon/assets/media/clothing/icon/240/'+id+'.png');
      }
    });
  }

  function setAvatar(prefix){
    const root = document.querySelector('[data-role="avatar"][data-prefix="'+prefix+'"]');
    if (!root) return;
    const photo = state.photo || 0;
    root.style.backgroundImage = photo > 0 ? ('url(/yukon/assets/media/clothing/photos/'+photo+'.png)') : '';

    const colour = root.querySelector('[data-layer="colour"]');
    if (colour) {
      // Use Yukon colour penguin asset rather than /assets/img/colours
      colour.src = '/yukon/assets/media/color/penguin/' + (state.color || 1) + '.png';
    }

    ['feet','body','neck','face','head','hand'].forEach(slot=>{
      const el = root.querySelector('[data-layer="'+slot+'"]');
      const id = state[slot] || 0;
      if (!el) return;
      if (id > 0) {
        el.style.display = '';
        el.src = '/yukon/assets/media/clothing/paper/'+id+'.png';
      } else {
        el.style.display = 'none';
        el.src = '';
      }
    });

    const pin = root.querySelector('[data-layer="pin"]');
    const flag = state.flag || 0;
    if (pin) {
      if (flag > 0) {
        pin.style.display = '';
        pin.src = '/yukon/assets/media/clothing/icon/120/'+flag+'.png';
      } else {
        pin.style.display = 'none';
        pin.src = '';
      }
    }
  }

  function updateAll(){
    setHidden();
    setEquipTiles();
    setAvatar('draft');
  }

  function matches(btn){
    const q = (search.value||'').trim().toLowerCase();
    const slot = btn.dataset.slot || '';
    const name = btn.dataset.name || '';
    const id = btn.dataset.itemId || '';
    if (activeFilter !== 'all' && slot !== activeFilter) return false;
    if (!q) return true;
    return name.includes(q) || id.includes(q.replace('#',''));
  }

  function clamp(n, a, b){ return Math.max(a, Math.min(b, n)); }

  function filterGrid(){
    const all = Array.from(grid.querySelectorAll('.mp-item'));
    const visible = all.filter(matches);

    const totalPages = Math.max(1, Math.ceil(visible.length / PER_PAGE));
    page = clamp(page, 1, totalPages);

    const start = (page - 1) * PER_PAGE;
    const end = start + PER_PAGE;

    // Hide everything, then show the current page slice.
    all.forEach(btn => { btn.style.display = 'none'; });
    visible.slice(start, end).forEach(btn => { btn.style.display = ''; });

    countEl.textContent = visible.length;

    // Pager UI
    if (pageNow) pageNow.textContent = String(page);
    if (pageTotal) pageTotal.textContent = String(totalPages);
    if (pageNow2) pageNow2.textContent = String(page);
    if (pageTotal2) pageTotal2.textContent = String(totalPages);

    const showPager = visible.length > PER_PAGE;
    if (pagerTop) pagerTop.style.display = showPager ? '' : 'none';
    if (pagerBottom) pagerBottom.style.display = showPager ? '' : 'none';

    const prevDisabled = page <= 1;
    const nextDisabled = page >= totalPages;
    document.querySelectorAll('.mp-pager .mp-pageBtn[data-page="prev"]').forEach(b=>b.disabled = prevDisabled);
    document.querySelectorAll('.mp-pager .mp-pageBtn[data-page="next"]').forEach(b=>b.disabled = nextDisabled);
  }

  // Item click: auto-slot based on metadata encoded in the button
  grid.addEventListener('click', (e)=>{
    const btn = e.target.closest('.mp-item');
    if (!btn) return;
    const id = parseInt(btn.dataset.itemId||'0',10);
    const slot = btn.dataset.slot;
    if (!id || !slot) return;
    state[slot] = id;
    updateAll();
  });

  chips.addEventListener('click', (e)=>{
    const b = e.target.closest('.mp-chip');
    if (!b) return;
    activeFilter = b.dataset.filter || 'all';
    page = 1;
    chips.querySelectorAll('.mp-chip').forEach(x=>x.classList.toggle('is-active', x===b));
    filterGrid();
  });

  search.addEventListener('input', ()=>{ page = 1; filterGrid(); });

  function pagerClick(e){
    const btn = e.target.closest('.mp-pageBtn');
    if (!btn) return;
    const dir = btn.dataset.page;
    if (dir === 'prev') page -= 1;
    if (dir === 'next') page += 1;
    filterGrid();
    // Keep the preview in view when paging.
    document.getElementById('mpGrid')?.scrollIntoView({behavior:'smooth', block:'start'});
  }
  pagerTop?.addEventListener('click', pagerClick);
  pagerBottom?.addEventListener('click', pagerClick);

  document.getElementById('mpResetBtn')?.addEventListener('click', ()=>{
    Object.keys(current).forEach(k=>state[k]=current[k]);
    search.value='';
    activeFilter='all';
    page = 1;
    chips.querySelectorAll('.mp-chip').forEach(x=>x.classList.toggle('is-active', x.dataset.filter==='all'));
    filterGrid();
    updateAll();
  });

  // View toggle: split/current/draft
  function setView(mode){
    const wrap = document.getElementById('mpStageWrap');
    wrap.dataset.view = mode;
    document.getElementById('mpViewSplit').classList.toggle('is-active', mode==='split');
    document.getElementById('mpViewCurrent').classList.toggle('is-active', mode==='current');
    document.getElementById('mpViewDraft').classList.toggle('is-active', mode==='draft');
  }
  document.getElementById('mpViewSplit')?.addEventListener('click', ()=>setView('split'));
  document.getElementById('mpViewCurrent')?.addEventListener('click', ()=>setView('current'));
  document.getElementById('mpViewDraft')?.addEventListener('click', ()=>setView('draft'));

  // init
  setAvatar('current');
  setAvatar('draft');
  setHidden();
  filterGrid();
  updateAll();
  // Default to Preview (draft)
  setView('draft');
})();
</script>
