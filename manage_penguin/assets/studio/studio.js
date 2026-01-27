/* manage_penguin/assets/studio/studio.js
   Outfit Studio v2 – built on the outfit tab logic, with stage view modes */

(function(){
  const boot = window.__OS_BOOT__ || {};
  const slots = boot.slots || ['color','head','face','neck','body','hand','feet','photo','flag'];
  const current = boot.current || {};
  const state = JSON.parse(JSON.stringify(current)); // draft

  const grid = document.getElementById('osGrid');
  const search = document.getElementById('osSearch');
  const countEl = document.getElementById('osCount');
  const chips = document.getElementById('osSlotChips');

  
const stage = document.getElementById('osStage');
const sceneFill = document.getElementById('osSceneFill');
const sceneMain = document.getElementById('osSceneMain');
const sceneSelect = document.getElementById('osSceneSelect');
const pinToggle = document.getElementById('osPinToggle');
const pinToggleText = document.getElementById('osPinToggleText');

const viewToggle = document.getElementById('osViewToggle');
const viewBtns = Array.from(document.querySelectorAll('.os-viewBtn'));


const tabs = Array.from(document.querySelectorAll('.os-tab'));
  const slotCurrent = document.getElementById('osAvatarCurrent');
  const slotDraft = document.getElementById('osAvatarDraft');

  // Pagination
  const PER_PAGE = 60;
  let page = 1;
  let activeFilter = 'all';

  const pageNow = document.getElementById('osPageNow');
  const pageTotal = document.getElementById('osPageTotal');
  const pageNow2 = document.getElementById('osPageNow2');
  const pageTotal2 = document.getElementById('osPageTotal2');

  const pagerTop = document.getElementById('osPagerTop');
  const pagerBottom = document.getElementById('osPagerBottom');

  // Saved tab container for the inventory 'Saved' filter. When the
  // user selects the Saved category, this element will be populated
  // with mini avatar previews instead of showing the normal item grid.
  const savedTabEl = document.getElementById('osSavedTab');

  // Elements related to the randomize menu. These are added in the
  // actions bar in the PHP template. We detect them here and wire
  // interactive behaviour.
  const randomMenu    = document.getElementById('osRandomMenu');
  const randomOptions = document.getElementById('osRandomOptions');
  const randomGoBtn   = document.getElementById('osRandomGoBtn');
  const randomMenuClose = document.getElementById('osRandomMenuClose');

  // Keep track of whether the random menu has been initialised. We only
  // populate the checkboxes once.
  let randomMenuInit = false;

  /**
   * Build a miniature avatar preview for a saved outfit. This function
   * returns a span element with layered images using the same asset
   * paths as the stage avatars. The preview is circular and will
   * automatically size itself based on the --os-avatar-size CSS
   * variable (default 44px).
   *
   * @param {Object} o Outfit record with slot IDs
   * @returns {HTMLElement}
   */
  function buildMiniPreview(o){
    // For backwards compatibility we delegate to buildPlayerCard with
    // a smaller default size. This allows us to render the same
    // layered avatar in a compact form using the same logic as the
    // bigger previews below.
    return buildPlayerCard(o, 44);
  }

  /**
   * Build a layered avatar component representing a penguin outfit. This
   * function creates a container div and inserts layered images for
   * colour, base overlay, clothing paper layers, and pin. The
   * container is square and its size can be specified via the
   * optional size argument (in pixels). A photo (background) is
   * applied via CSS background. The result is a self-contained
   * component that can be appended anywhere in the document.
   *
   * @param {Object} o Outfit record with slot IDs
   * @param {number} size The width/height of the avatar (default 60)
   * @returns {HTMLElement}
   */
  function buildPlayerCard(o, size = 60){
    const wrap = document.createElement('div');
    wrap.className = 'os-playerCard';
    wrap.style.width = size + 'px';
    wrap.style.height = size + 'px';
    wrap.style.position = 'relative';
    wrap.style.borderRadius = '50%';
    wrap.style.overflow = 'hidden';
    wrap.style.border = '3px solid rgba(11,43,69,0.28)';
    wrap.style.background = 'rgba(255,255,255,0.75)';
    wrap.style.boxShadow = '0 2px 4px rgba(0,0,0,0.08)';
    // Photo background (behind all layers)
    // Use the proper photo path. Try the photoBase from boot if available,
    // otherwise fall back to the Yukon photos directory. Previously we
    // referenced the icon/240 path, which shows the paper icon rather
    // than the full background image.
    const photoId = o.photo || 0;
    if (photoId && photoId > 0){
      const base = (typeof boot !== 'undefined' && boot.photoBase) ? boot.photoBase : '/yukon/assets/media/clothing/photos/';
      wrap.style.backgroundImage = 'url(' + base + photoId + '.png)';
      wrap.style.backgroundSize = 'cover';
      wrap.style.backgroundPosition = 'center';
    }
    // Colour/base layer. Attempt Yukon penguin colour asset first;
    // fallback to local colour file on error. If none provided, use colour 1.
    const colorId = (o.color && o.color > 0) ? o.color : 1;
    {
      const colourImg = document.createElement('img');
      colourImg.src = '/yukon/assets/media/color/penguin/' + colorId + '.png';
      // fallback to local colours directory if remote not found
      colourImg.onerror = function(){
        this.onerror = null;
        this.src = '/assets/img/colours/' + colorId + '.png';
      };
      colourImg.alt = '';
      colourImg.style.position = 'absolute';
      colourImg.style.top = '0';
      colourImg.style.left = '0';
      colourImg.style.width = '100%';
      colourImg.style.height = '100%';
      colourImg.style.objectFit = 'cover';
      wrap.appendChild(colourImg);
    }
    // Base penguin overlay (transparent). This ensures the outline
    // remains visible even if the colour image lacks an outline.
    {
      const overlayImg = document.createElement('img');
      overlayImg.src = '/assets/img/penguin_transparent.png';
      overlayImg.alt = '';
      overlayImg.style.position = 'absolute';
      overlayImg.style.top = '0';
      overlayImg.style.left = '0';
      overlayImg.style.width = '100%';
      overlayImg.style.height = '100%';
      overlayImg.style.objectFit = 'cover';
      wrap.appendChild(overlayImg);
    }
    // Paper layers in proper stacking order. Use absolute positioning
    // and contain fit to maintain aspect ratio. Skip zero values.
    ['feet','body','neck','face','head','hand'].forEach(slot => {
      const id = o[slot] || 0;
      if (!id || id <= 0) return;
      const img = document.createElement('img');
      img.src = '/yukon/assets/media/clothing/paper/' + id + '.png';
      img.alt = '';
      img.style.position = 'absolute';
      img.style.top = '0';
      img.style.left = '0';
      img.style.width = '100%';
      img.style.height = '100%';
      img.style.objectFit = 'contain';
      wrap.appendChild(img);
    });
    // Pin (flag). Drawn on top right of the avatar. Only if there is
    // a flag equipped. Use a smaller icon size for the pin to fit.
    const flagId = o.flag || 0;
    if (flagId && flagId > 0){
      const pin = document.createElement('img');
      pin.src = '/yukon/assets/media/clothing/icon/120/' + flagId + '.png';
      pin.alt = '';
      pin.style.position = 'absolute';
      pin.style.top = '6px';
      // Position pin on the left side to mirror the stage preview
      pin.style.left = '6px';
      pin.style.width = Math.max(20, size * 0.3) + 'px';
      pin.style.height = Math.max(20, size * 0.3) + 'px';
      pin.style.objectFit = 'contain';
      wrap.appendChild(pin);
    }
    return wrap;
  }

  /**
   * Populate the saved outfits tab in the inventory when the user
   * selects the "Saved" filter. This fetches the user's saved
   * outfits from the server and constructs a grid of mini previews
   * with click handlers to apply each outfit. If no outfits are
   * saved, a friendly message is shown.
   *
   * @param {number} limit How many outfits to load (default 60)
   */
  function loadSavedTab(limit = 60){
    if (!savedTabEl) return;
    savedTabEl.innerHTML = '';
    fetch('api/outfits.php?limit=' + encodeURIComponent(limit), {
      headers:{ 'Accept': 'application/json' }
    }).then(res => res.json()).then(data => {
      if (!data || !data.ok || !Array.isArray(data.outfits) || data.outfits.length === 0){
        const empty = document.createElement('div');
        empty.className = 'os-savedEmpty';
        empty.textContent = 'No saved outfits yet.';
        savedTabEl.appendChild(empty);
        return;
      }
      // Keep track of shareCode/ID to avoid duplicate tiles (some
      // endpoints may return duplicates). We use the shareCode if
      // available, otherwise fallback to the record ID. The set
      // prevents adding the same outfit twice.
      const seen = new Set();
      data.outfits.forEach(rec => {
        const key = rec.shareCode || rec.id;
        if (key && seen.has(key)) return;
        if (key) seen.add(key);
        // Create the tile container
        const tile = document.createElement('div');
        tile.className = 'os-savedTabItem';
        tile.title = rec.name || 'Saved outfit';
        // Build a larger avatar preview (64px) for the saved tab
        const preview = buildPlayerCard(rec, 72);
        preview.classList.add('os-savedTabPreview');
        // Overlay container for action buttons
        const overlay = document.createElement('div');
        overlay.className = 'os-savedTabOverlay';
        // Equip/apply button
        const eqBtn = document.createElement('button');
        eqBtn.className = 'os-savedTabEquip';
        eqBtn.title = 'Equip this outfit';
        eqBtn.innerHTML = '<i class="fa fa-check"></i>';
        eqBtn.addEventListener('click', (e) => {
          e.preventDefault(); e.stopPropagation();
          applySaved(rec, false);
        });
        overlay.appendChild(eqBtn);
        // Info button to show details
        const infoBtn = document.createElement('button');
        infoBtn.className = 'os-savedTabInfo';
        infoBtn.title = 'View details';
        infoBtn.innerHTML = '<i class="fa fa-info-circle"></i>';
        infoBtn.addEventListener('click', (e) => {
          e.preventDefault(); e.stopPropagation();
          showOutfitInfo(rec);
        });
        overlay.appendChild(infoBtn);
        // Append overlay on top of preview
        preview.appendChild(overlay);
        tile.appendChild(preview);
        // Add the name below the preview
        const label = document.createElement('div');
        label.className = 'os-savedName';
        label.textContent = rec.name || 'Untitled';
        tile.appendChild(label);
        savedTabEl.appendChild(tile);
      });
    }).catch(() => {
      const err = document.createElement('div');
      err.className = 'os-savedEmpty';
      err.textContent = 'Failed to load saved outfits.';
      savedTabEl.appendChild(err);
    });
  }

  // Track the last scene mode and photoId applied to avoid animating the
  // background on every state change. We only animate when the scene
  // actually changes (e.g. switching scenes or changing the photo).
  let lastSceneMode = null;
  let lastPhotoId = null;

  // Store the last generated gradient for the random colours scene so it can
  // persist across re-renders until the user changes to a different
  // background. Without this, a new gradient would be generated every
  // time updateScene() runs (e.g. when equipping items), causing an
  // unexpected flicker.
  let lastRandomGradient = '';

  /**
   * Generate a random pastel colour in HSL format. Pastel colours use a
   * high lightness value and medium saturation, producing soft shades.
   */
  function randomPastel(){
    const hue = Math.floor(Math.random() * 360);
    return `hsl(${hue}, 70%, 80%)`;
  }

  /**
   * Build a CSS linear-gradient with multiple random pastel colours.
   * The gradient angle and colours are randomised each time it is called.
   */
  function buildRandomGradient(){
    const stops = [];
    for (let i = 0; i < 4; i++) stops.push(randomPastel());
    return 'linear-gradient(-45deg, ' + stops.join(', ') + ')';
  }

  function setHidden(){
    slots.forEach(s=>{
      const input = document.getElementById('slot_'+s);
      if (input) input.value = state[s] || 0;
    });
  }

  function setEquipChips(){
  slots.forEach(s=>{
    const id = state[s] || 0;
    const idEl = document.getElementById('osEquip-'+s);
    if (idEl) idEl.textContent = id > 0 ? ('#'+id) : 'None';

    const wrap = document.querySelector('.os-equipChipWrap[data-slot="'+s+'"]');
    if (wrap) wrap.dataset.empty = (id > 0 ? '0' : '1');

    const chip = wrap ? wrap.querySelector('.os-equipChip') : document.querySelector('.os-equipChip[data-slot="'+s+'"]');
    const img = chip ? chip.querySelector('img') : null;

    if (img) {
      img.style.display = '';
      img.src = (s==='color')
        ? ('/yukon/assets/media/color/icon/120/'+id+'.png')
        : ('/yukon/assets/media/clothing/icon/240/'+id+'.png');
      if (!id) {
        // Keep a subtle placeholder when empty
        img.style.opacity = 0.20;
      } else {
        img.style.opacity = 1;
      }
    }
  });
}

  function setAvatar(prefix, source){
    const root = document.querySelector('[data-role="avatar"][data-prefix="'+prefix+'"]');
    if (!root) return;

    // Colour: use Yukon penguin colour asset rather than the local
    // /assets/img/colours path. This prevents 404s and matches the
    // preview styling used elsewhere.
    const c = root.querySelector('[data-layer="colour"]');
    if (c) {
      const cid = (source.color || 1);
      c.src = '/yukon/assets/media/color/penguin/' + cid + '.png';
    }

    // Paper layers
    const map = { head:'head', face:'face', neck:'neck', body:'body', hand:'hand', feet:'feet' };
    Object.keys(map).forEach(slot=>{
      const id = source[slot] || 0;
      const img = root.querySelector('[data-layer="'+slot+'"]');
      if (!img) return;
      if (id > 0){
        img.style.display = '';
        img.src = '/yukon/assets/media/clothing/paper/' + id + '.png';
      } else {
        img.style.display = 'none';
        img.removeAttribute('src');
      }
    });

    // Flag pin
    const pin = root.querySelector('[data-layer="pin"]');
    const flag = source.flag || 0;
    if (pin){
      if (flag > 0){
        pin.style.display = '';
        pin.src = '/yukon/assets/media/clothing/icon/120/' + flag + '.png';
      } else {
        pin.style.display = 'none';
        pin.removeAttribute('src');
      }
    }
  }

  let activeView = 'preview';

  function applyView(view){
    activeView = view || 'preview';
    tabs.forEach(b=>b.classList.toggle('is-active', b.dataset.view === view));

    // Display logic:
    // - preview: only draft centered
    // - current: only current centered
    // - compare: split current (left) + draft (right)
    slotCurrent.classList.remove('is-active');
    slotDraft.classList.remove('is-active');

    slotCurrent.classList.remove('os-avatarSlot--current');
    slotDraft.classList.remove('os-avatarSlot--draft');

    if (view === 'current'){
      slotCurrent.classList.add('is-active');
    } else if (view === 'compare'){
      slotCurrent.classList.add('is-active');
      slotDraft.classList.add('is-active');
      slotCurrent.classList.add('os-avatarSlot--current');
      slotDraft.classList.add('os-avatarSlot--draft');
    } else {
      slotDraft.classList.add('is-active');
    }

    // Always keep visuals up-to-date
    setAvatar('current', current);
    setAvatar('draft', state);
    updateScene();
  }

  function normQuery(q){
    q = (q || '').trim().toLowerCase();
    if (!q) return '';
    return q.replace(/\s+/g,' ');
  }

  function getItems(){
    return Array.from(grid.querySelectorAll('.os-item'));
  }

function updateInventoryHighlights(){
  const items = getItems();
  // Build quick lookup: slot -> id
  const equipped = {};
  slots.forEach(s=>{ equipped[s] = state[s] || 0; });

  items.forEach(it=>{
    const slot = it.dataset.slot;
    const id = parseInt(it.dataset.itemId,10) || 0;
    const isEq = (equipped[slot] || 0) === id && id > 0;
    it.classList.toggle('is-equipped', isEq);
  });
}


  function matches(item, q){
    if (activeFilter !== 'all' && item.dataset.slot !== activeFilter) return false;
    if (!q) return true;
    const id = String(item.dataset.itemId || '');
    const name = String(item.dataset.name || '');
    return id.includes(q.replace('#','')) || name.includes(q);
  }

  function render(){
    // Handle the Saved filter early: display saved outfits instead of
    // rendering the standard inventory grid. When activeFilter is
    // 'saved', we hide the grid, pagers, search and view toggles and
    // show the savedTabEl populated via loadSavedTab().
    if (activeFilter === 'saved') {
      // Hide normal inventory components
      if (grid) grid.style.display = 'none';
      if (pagerTop) pagerTop.style.display = 'none';
      if (pagerBottom) pagerBottom.style.display = 'none';
      if (search && search.parentElement) search.parentElement.style.display = 'none';
      if (countEl && countEl.parentElement) countEl.parentElement.style.display = 'none';
      if (viewToggle) viewToggle.style.display = 'none';
      // Show the saved tab and load its contents
      if (savedTabEl) {
        savedTabEl.style.display = '';
        loadSavedTab();
      }
      return;
    } else {
      // Reset UI visibility when returning to normal filters
      if (grid) grid.style.display = '';
      if (pagerTop) pagerTop.style.display = '';
      if (pagerBottom) pagerBottom.style.display = '';
      if (search && search.parentElement) search.parentElement.style.display = '';
      if (countEl && countEl.parentElement) countEl.parentElement.style.display = '';
      if (viewToggle) viewToggle.style.display = '';
      if (savedTabEl) savedTabEl.style.display = 'none';
    }

    const q = normQuery(search ? search.value : '');
    const items = getItems();
    const visible = items.filter(it=>matches(it,q));

    const totalPages = Math.max(1, Math.ceil(visible.length / PER_PAGE));
    if (page > totalPages) page = totalPages;
    if (page < 1) page = 1;

    const start = (page - 1) * PER_PAGE;
    const end = start + PER_PAGE;

    items.forEach(it=>{ it.style.display = 'none'; });
    visible.slice(start, end).forEach(it=>{ it.style.display = ''; });

    if (countEl) countEl.textContent = String(visible.length);

    if (pageNow) pageNow.textContent = String(page);
    if (pageTotal) pageTotal.textContent = String(totalPages);
    if (pageNow2) pageNow2.textContent = String(page);
    if (pageTotal2) pageTotal2.textContent = String(totalPages);

    // Disable prev/next when needed
    const setPagerState = (root)=>{
      if (!root) return;
      const prev = root.querySelector('[data-page="prev"]');
      const next = root.querySelector('[data-page="next"]');
      if (prev) prev.disabled = (page <= 1);
      if (next) next.disabled = (page >= totalPages);
    };
    setPagerState(pagerTop);
    setPagerState(pagerBottom);

    updateInventoryHighlights();

    // --- Animated inventory rendering ---
    // Apply 3D tilt effects and fade‑in animations when items are shown. We defer
    // to external libraries only if they are available at runtime to avoid
    // breaking functionality on older browsers. See studio.php for CDN inclusions.
    try {
      applyTilt();
      animateGridItems();
    } catch(e){ /* Silently ignore animation errors */ }
  }

  /**
   * Apply VanillaTilt 3D hover effects to visible inventory items. Each item
   * will gently tilt and slightly scale when hovered, creating a polished
   * interactive feel. This function guards against re‑initialising
   * elements that already have a tilt instance attached by checking for
   * `vanillaTilt` on the element. If the global VanillaTilt is not
   * available, it does nothing.
   */
  function applyTilt(){
    if (!window.VanillaTilt) return;
    const items = getItems();
    items.forEach(it=>{
      // Initialise once per element; VanillaTilt stores its instance on the
      // element under the `vanillaTilt` property.
      if (!it.vanillaTilt) {
        window.VanillaTilt.init(it, {
          max: 15,
          speed: 400,
          scale: 1.02,
          glare: false,
          easing: 'cubic-bezier(.03,.98,.52,.99)'
        });
      }
    });
  }

  /**
   * Animate currently visible grid items using GSAP. When the page or filter
   * changes, items fade in and gently slide up to guide the user’s eye. The
   * animation is staggered for a subtle cascading effect. If GSAP is not
   * available, the function simply returns without modifying styles. Items
   * hidden via `display: none` are excluded from the animation.
   */
  function animateGridItems(){
    if (!window.gsap) return;
    const visible = Array.from(grid.querySelectorAll('.os-item')).filter(it=>it.style.display !== 'none');
    if (!visible.length) return;
    // Reset opacity to 0 before animating to avoid flashing pre‑existing values.
    visible.forEach(it=>{ it.style.opacity = 0; it.style.transform = 'translateY(10px)'; });
    // Use GSAP to animate opacity and vertical position with a stagger. We
    // overwrite previous animations to ensure smooth transitions when
    // navigating between pages.
    window.gsap.to(visible, {
      opacity: 1,
      y: 0,
      duration: 0.5,
      ease: 'power2.out',
      stagger: 0.02,
      overwrite: 'auto'
    });
  }

function unequip(slot){
  if (!slot) return;
  state[slot] = 0;
  setHidden();
  setEquipChips();
  setAvatar('draft', state);
  updateScene();
  updateInventoryHighlights();
}

function equip(slot, itemId){

    if (!slot) return;

    const id = parseInt(itemId, 10) || 0;
    state[slot] = id;

    // Photo is not applied to avatar (stage scene will handle later),
    // but we keep it in draft state so Save works.
    setHidden();
    setEquipChips();
    setAvatar('draft', state);
    updateScene();
    updateInventoryHighlights();
  }

  // Unequip buttons in equip bar
const equipBar = document.getElementById('osEquipBar');
if (equipBar){
  equipBar.addEventListener('click', (e)=>{
    const b = e.target.closest('.os-unequipBtn');
    if (!b) return;
    e.preventDefault();
    e.stopPropagation();
    const slot = b.dataset.unequip;
    unequip(slot);
  });
}

// Click item => equip
  if (grid){
    grid.addEventListener('click', (e)=>{
      const btn = e.target.closest('.os-item');
      if (!btn) return;
      equip(btn.dataset.slot, btn.dataset.itemId);
    });
  }

  // Filter chips
  if (chips){
    chips.addEventListener('click', (e)=>{
      const b = e.target.closest('.os-chip');
      if (!b) return;
      activeFilter = b.dataset.filter || 'all';
      page = 1;
      Array.from(chips.querySelectorAll('.os-chip')).forEach(x=>x.classList.toggle('is-active', x===b));
      render();
    });
  }

  // Search
  if (search){
    search.addEventListener('input', ()=>{ page = 1; render(); });
  }

  // Pager
  function bindPager(root){
    if (!root) return;
    root.addEventListener('click', (e)=>{
      const b = e.target.closest('.os-pageBtn');
      if (!b) return;
      const dir = b.dataset.page;
      if (dir === 'prev') page -= 1;
      if (dir === 'next') page += 1;
      render();
    });
  }
  bindPager(pagerTop);
  bindPager(pagerBottom);

  // View tabs
  tabs.forEach(t=>{
    t.addEventListener('click', ()=>applyView(t.dataset.view || 'preview'));
  });

  // Reset
  const resetBtn = document.getElementById('osResetBtn');
  if (resetBtn){
    resetBtn.addEventListener('click', ()=>{
      slots.forEach(s=>{ state[s] = current[s] || 0; });
      setHidden();
      setEquipChips();
      setAvatar('draft', state);
      updateScene();
      render();
    });
  }

  /**
   * Quickly persist the current draft outfit to the server. This function
   * performs an asynchronous fetch to the API endpoint without reloading
   * the page. On success it updates the `current` state to reflect the
   * equipped items and refreshes the UI. If the server returns an error
   * the user is notified via an alert.
   */
  function equipNow(){
    // Compose the outfit payload from the draft state
    const outfit = {};
    slots.forEach(s=>{ outfit[s] = state[s] || 0; });
    const csrfInput = document.querySelector('input[name="csrf_token"]');
    const csrf = csrfInput ? csrfInput.value : '';
    // Show a loading indicator via SweetAlert
    if (window.Swal){
      Swal.fire({
        title:'Equipping outfit…',
        allowOutsideClick:false,
        didOpen: () => { Swal.showLoading(); },
        customClass:{ confirmButton:'os-btn' },
        buttonsStyling:false
      });
    }
    // Post to the studio_save endpoint relative to the current module.  Do not prefix
    // with "/manage_penguin/" so it works whether the site is hosted at the
    // domain root or within a subdirectory.
    fetch('api/studio_save.php', {
      method:'POST',
      headers:{
        'Content-Type':'application/json',
        'X-CSRF-Token': csrf
      },
      body: JSON.stringify({ csrf: csrf, outfit: outfit })
    }).then(res=>res.json()).then(data=>{
      if (!data || !data.ok){
        const err = (data && data.error) ? data.error : 'Failed to equip your outfit.';
        if (window.Swal){
          Swal.fire({
            icon:'error',
            title:'Equip failed',
            text: err,
            customClass:{ confirmButton:'os-btn' },
            buttonsStyling:false
          });
        } else {
          alert(err);
        }
        return;
      }
      // Copy draft state to current and refresh the UI
      slots.forEach(s=>{ current[s] = state[s] || 0; });
      setAvatar('current', current);
      updateScene();
      // Reload saved outfits to reflect the new default
      loadSavedOutfits();
      if (window.Swal){
        Swal.fire({
          icon:'success',
          title:'Outfit equipped!',
          timer:1500,
          showConfirmButton:false,
          customClass:{ confirmButton:'os-btn' },
          buttonsStyling:false
        });
      } else {
        alert('Outfit equipped successfully.');
      }
    }).catch(()=>{
      if (window.Swal){
        Swal.fire({
          icon:'error',
          title:'Network error',
          text:'Unable to equip your outfit.',
          customClass:{ confirmButton:'os-btn' },
          buttonsStyling:false
        });
      } else {
        alert('Network error while equipping your outfit.');
      }
    });
  }

  /**
   * Persist the current draft outfit to the user’s library. Prompts the
   * visitor for a name and optional privacy setting before sending a
   * POST request to the API. On success the saved outfits list is
   * refreshed. Validation of ownership and slot type happens on the
   * server. See manage_penguin/api/outfits.php for details.
   */
  function saveToLibrary(){
    // Use SweetAlert2 to prompt for outfit details (name, description, privacy)
    if (window.Swal){
      Swal.fire({
        title:'Save Outfit',
        html:
          '<input id="swOutfitName" class="swal2-input" placeholder="Name" autocomplete="off">' +
          '<input id="swOutfitDesc" class="swal2-input" placeholder="Description (optional)" autocomplete="off">' +
          '<div style="display:flex;align-items:center;font-size:13px;margin-top:4px;">' +
            '<input type="checkbox" id="swOutfitPublic" style="margin-right:6px;">' +
            '<label for="swOutfitPublic">Public</label>' +
          '</div>',
        focusConfirm:false,
        showCancelButton:true,
        confirmButtonText:'Save',
        customClass:{ confirmButton:'os-btn', cancelButton:'os-btn os-btnSoft' },
        buttonsStyling:false,
        preConfirm: () => {
          const name = document.getElementById('swOutfitName').value.trim();
          const desc = document.getElementById('swOutfitDesc').value.trim();
          const isPublic = document.getElementById('swOutfitPublic').checked;
          if (!name){
            Swal.showValidationMessage('Name cannot be empty');
            return false;
          }
          return {name, desc, isPublic: isPublic ? 1 : 0};
        }
      }).then(result => {
        if (!result || !result.value) return;
        const {name, desc, isPublic} = result.value;
        // Build the outfit payload
        const outfit = {};
        slots.forEach(s=>{ outfit[s] = state[s] || 0; });
        const csrfInput = document.querySelector('input[name="csrf_token"]');
        const csrf = csrfInput ? csrfInput.value : '';
        // Show a loading toast
        Swal.fire({
          title:'Saving outfit…',
          allowOutsideClick:false,
          didOpen:()=>{ Swal.showLoading(); },
          customClass:{ confirmButton:'os-btn' },
          buttonsStyling:false
        });
        // Use relative path for outfits API
        fetch('api/outfits.php', {
          method:'POST',
          headers:{
            'Content-Type':'application/json',
            'X-CSRF-Token': csrf
          },
          body: JSON.stringify({ csrf: csrf, name: name, description: desc, isPublic: isPublic, outfit: outfit })
        }).then(res=>res.json()).then(data=>{
          if (!data || !data.ok){
            const err = (data && data.error) ? data.error : 'Failed to save outfit.';
            Swal.fire({
              icon:'error',
              title:'Save failed',
              text: err,
              customClass:{ confirmButton:'os-btn' },
              buttonsStyling:false
            });
            return;
          }
          loadSavedOutfits();
          Swal.fire({
            icon:'success',
            title:'Saved!',
            text:'Outfit saved successfully.',
            timer:1500,
            showConfirmButton:false,
            customClass:{ confirmButton:'os-btn' },
            buttonsStyling:false
          });
        }).catch(()=>{
          Swal.fire({
            icon:'error',
            title:'Network error',
            text:'Unable to save your outfit.',
            customClass:{ confirmButton:'os-btn' },
            buttonsStyling:false
          });
        });
      });
    } else {
      // Fallback to native prompt/confirm if SweetAlert is unavailable
      let name = prompt('Enter a name for this outfit:', 'My Outfit');
      if (name === null) return; // cancel
      name = name.trim();
      if (!name){
        alert('Name cannot be empty.');
        return;
      }
      const desc = prompt('Description (optional):', '') || '';
      const isPublic = confirm('Make this outfit public?\nClick OK to allow others to see it, or Cancel to keep it private.');
      const outfit = {};
      slots.forEach(s=>{ outfit[s] = state[s] || 0; });
      const csrfInput = document.querySelector('input[name="csrf_token"]');
      const csrf = csrfInput ? csrfInput.value : '';
      fetch('api/outfits.php', {
        method:'POST',
        headers:{
          'Content-Type':'application/json',
          'X-CSRF-Token': csrf
        },
        body: JSON.stringify({ csrf: csrf, name: name, description: desc, isPublic: isPublic ? 1 : 0, outfit: outfit })
      }).then(res=>res.json()).then(data=>{
        if (!data || !data.ok){
          const err = (data && data.error) ? data.error : 'Failed to save outfit.';
          alert(err);
          return;
        }
        loadSavedOutfits();
        alert('Outfit saved successfully.');
      }).catch(()=>{
        alert('Network error while saving your outfit.');
      });
    }
  }

  /**
   * Apply a saved outfit to the draft state and optionally equip it on the
   * server. This helper takes an outfit record and copies each slot into
   * `state`, updates the UI, and optionally calls equipNow() when the
   * second argument is true.
   */
  function applySaved(outfit, autoEquip){
    if (!outfit) return;
    slots.forEach(s=>{ state[s] = parseInt(outfit[s] || 0, 10) || 0; });
    setHidden();
    setEquipChips();
    setAvatar('draft', state);
    updateScene();
    updateInventoryHighlights();
    if (autoEquip) equipNow();
  }

  /**
   * Display a detailed information modal for a saved outfit. This uses
   * SweetAlert2 to render a small preview along with metadata. The
   * preview uses the server-side playerCard if available; otherwise
   * falls back to a client-side layered avatar. It is called by
   * clicking the info button in the saved outfits list. The modal
   * includes the outfit name, description, visibility, likes, share
   * code, created and updated dates, and lists which items are
   * missing from the user's inventory (if any).
   *
   * @param {Object} rec Outfit record returned by the API
   */
  function showOutfitInfo(rec) {
    if (typeof Swal === 'undefined') return;
    // Escape helper to prevent XSS when constructing HTML
    const esc = (str) => {
      if (!str) return '';
      return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
    };
    // Build preview element HTML using client-side layering. We use
    // buildPlayerCard() with a larger size to produce a nicely scaled
    // preview. Relying on the server-generated playerCard may render
    // the photo as an icon rather than the full background image.
    const previewElem = buildPlayerCard(rec, 120);
    const tmpWrap = document.createElement('div');
    tmpWrap.appendChild(previewElem);
    const previewHtml = tmpWrap.innerHTML;
    // Build metadata list
    const meta = [];
    if (rec.shareCode) meta.push('<b>Code:</b> ' + esc(rec.shareCode));
    if (typeof rec.likes !== 'undefined') meta.push('<b>Likes:</b> ' + esc(rec.likes));
    meta.push('<b>Visibility:</b> ' + (rec.isPublic ? 'Public' : 'Private'));
    if (rec.created_at) meta.push('<b>Created:</b> ' + esc(new Date(rec.created_at).toLocaleDateString()));
    if (rec.updated_at && rec.updated_at !== rec.created_at) meta.push('<b>Updated:</b> ' + esc(new Date(rec.updated_at).toLocaleDateString()));
    if (rec.missing && rec.missing > 0) meta.push('<b>Missing items:</b> ' + esc(rec.missing));
    // Compose description (may be empty)
    const desc = (rec.description && rec.description.trim()) ? esc(rec.description.trim()) : '—';
    const html = `
      <div class="os-infoModal">
        <div class="os-infoPreview">${previewHtml}</div>
        <div class="os-infoBody">
          <h3 class="os-infoTitle">${esc(rec.name || 'Untitled')}</h3>
          <p class="os-infoDesc">${desc}</p>
          <p class="os-infoMeta">${meta.join('<br>')}</p>
        </div>
      </div>
    `;
    Swal.fire({
      title: 'Outfit Details',
      html: html,
      width: 480,
      showCloseButton: true,
      focusConfirm: false,
      showConfirmButton: false,
      customClass: {
        popup: 'os-infoPopup',
        htmlContainer: 'os-infoHtml'
      }
    });
  }

  /**
   * Fetch the user’s saved outfits from the server and populate the
   * Saved Outfits section. By default it requests the three most recent
   * outfits. If none are returned the section stays hidden. Each saved
   * record must include an id, name, isPublic flag and the full slot
   * mapping. The server ensures only owned items are stored. The UI
   * attaches a click handler to each apply button.
   */
  function loadSavedOutfits(limit = 3){
    const listEl = document.getElementById('osSavedList');
    const section = document.getElementById('osSavedSection');
    if (!listEl || !section) return;
    // Clear previous
    listEl.innerHTML = '';
    // Use relative path for saved outfits API
    fetch('api/outfits.php?limit=' + encodeURIComponent(limit), {
      headers:{ 'Accept': 'application/json' }
    }).then(res=>res.json()).then(data=>{
      if (!data || !data.ok || !Array.isArray(data.outfits) || data.outfits.length === 0){
        section.style.display = 'none';
        return;
      }
      section.style.display = '';
      const seen = new Set();
      data.outfits.forEach(rec=>{
        const key = rec.shareCode || rec.id;
        if (key && seen.has(key)) return;
        if (key) seen.add(key);
        const item = document.createElement('div');
        item.className = 'os-savedItem';
        // Preview column: use the pre-rendered player card if available
        {
          // Always build a layered avatar preview client-side. Use a
          // slightly larger size for the saved outfit list so the
          // details are clear. The buildPlayerCard function returns
          // a <div> element which we append directly.
          const previewWrap = document.createElement('div');
          previewWrap.className = 'os-savedPreviewWrap';
          previewWrap.appendChild(buildPlayerCard(rec, 72));
          item.appendChild(previewWrap);
        }
        // Info column
        const info = document.createElement('div');
        info.className = 'os-savedInfo';
        const nameDiv = document.createElement('div');
        nameDiv.className = 'os-savedName';
        nameDiv.textContent = rec.name || 'Untitled';
        info.appendChild(nameDiv);
        const descDiv = document.createElement('div');
        descDiv.className = 'os-savedDesc';
        const description = (rec.description || '').trim();
        descDiv.textContent = description ? description : '—';
        info.appendChild(descDiv);
        const meta = document.createElement('div');
        meta.className = 'os-savedMeta';
        // Share code
        if (rec.shareCode){
          const span = document.createElement('span');
          span.innerHTML = '<span class="os-metaLabel">Code:</span> ' + rec.shareCode;
          meta.appendChild(span);
        }
        // Likes
        if (typeof rec.likes !== 'undefined'){
          const span = document.createElement('span');
          span.innerHTML = '<span class="os-metaLabel">Likes:</span> ' + rec.likes;
          meta.appendChild(span);
        }
        // Public/Private
        const pubSpan = document.createElement('span');
        pubSpan.innerHTML = '<span class="os-metaLabel">Visibility:</span> ' + (rec.isPublic ? 'Public' : 'Private');
        meta.appendChild(pubSpan);
        // Created date
        if (rec.created_at){
          const span = document.createElement('span');
          span.innerHTML = '<span class="os-metaLabel">Created:</span> ' + new Date(rec.created_at).toLocaleDateString();
          meta.appendChild(span);
        }
        // Updated date
        if (rec.updated_at && rec.updated_at !== rec.created_at){
          const span = document.createElement('span');
          span.innerHTML = '<span class="os-metaLabel">Updated:</span> ' + new Date(rec.updated_at).toLocaleDateString();
          meta.appendChild(span);
        }
        info.appendChild(meta);
        item.appendChild(info);
        // Action buttons column: include an info button and an apply button
        const actions = document.createElement('div');
        actions.className = 'os-savedActions';
        // Info button (icon only). Clicking it shows outfit details in a modal.
        const infoBtn = document.createElement('button');
        infoBtn.className = 'os-savedInfoBtn';
        infoBtn.title = 'View outfit details';
        infoBtn.innerHTML = '<i class="fa fa-circle-info"></i>';
        infoBtn.addEventListener('click', (e) => {
          e.preventDefault(); e.stopPropagation();
          showOutfitInfo(rec);
        });
        actions.appendChild(infoBtn);
        // Apply button. Shows missing items in the label if any.
        const applyBtn2 = document.createElement('button');
        applyBtn2.className = 'os-savedApplyBtn';
        applyBtn2.textContent = (rec.missing && rec.missing > 0) ? 'Apply (' + rec.missing + ' missing)' : 'Apply';
        applyBtn2.addEventListener('click', (e) => {
          e.preventDefault(); e.stopPropagation();
          applySaved(rec, false);
        });
        actions.appendChild(applyBtn2);
        item.appendChild(actions);
        listEl.appendChild(item);
      });
    }).catch(()=>{
      // On error hide the section silently
      section.style.display = 'none';
    });
  }

  // Wire Equip Now button
  const equipNowBtn = document.getElementById('osEquipNowBtn');
  if (equipNowBtn){
    equipNowBtn.addEventListener('click', ()=>{
      equipNow();
    });
  }

  // Wire Save to Library button
  const saveToLibBtn = document.getElementById('osSaveToLibBtn');
  if (saveToLibBtn){
    saveToLibBtn.addEventListener('click', ()=>{
      saveToLibrary();
    });
  }

  // Wire View All saved outfits button (future modal not yet implemented)
  const savedViewAllBtn = document.getElementById('osSavedViewAllBtn');
  if (savedViewAllBtn){
    savedViewAllBtn.addEventListener('click', ()=>{
      // For now simply reload the list with a larger limit and toggle
      const expanded = savedViewAllBtn.dataset.expanded === '1';
      savedViewAllBtn.dataset.expanded = expanded ? '0' : '1';
      savedViewAllBtn.textContent = expanded ? 'View All' : 'Show Less';
      loadSavedOutfits(expanded ? 3 : 10);
    });
  }

  // Wire Randomize Outfit button
  const randomizeBtn = document.getElementById('osRandomizeBtn');
  if (randomizeBtn) {
    randomizeBtn.addEventListener('click', (e) => {
      e.preventDefault();
      toggleRandomMenu();
    });
  }

  // Wire random menu close button
  if (randomMenuClose) {
    randomMenuClose.addEventListener('click', () => {
      if (randomMenu) randomMenu.style.display = 'none';
    });
  }

  // Wire random menu Go button
  if (randomGoBtn) {
    randomGoBtn.addEventListener('click', () => {
      // Collect selected slots
      if (!randomOptions) return;
      const selected = [];
      slots.forEach(s => {
        const cb = randomOptions.querySelector('input[data-slot="' + s + '"]');
        if (cb && cb.checked) selected.push(s);
      });
      if (selected.length > 0) performRandomization(selected);
    });
  }

  /**
   * Initialise the random menu by creating a checkbox for each slot. This
   * is called once when the random menu is first opened. Checkboxes
   * default to checked so all slots are randomised unless the user
   * deselects them.
   */
  function initRandomMenu(){
    if (randomMenuInit || !randomOptions) return;
    const labels = {
      color: 'Colour',
      head: 'Head',
      face: 'Face',
      neck: 'Neck',
      body: 'Body',
      hand: 'Hand',
      feet: 'Feet',
      photo: 'Photo',
      flag: 'Pin'
    };
    slots.forEach(s => {
      const label = labels[s] || s.charAt(0).toUpperCase() + s.slice(1);
      const wrapper = document.createElement('label');
      const cb = document.createElement('input');
      cb.type = 'checkbox';
      cb.dataset.slot = s;
      cb.checked = true;
      wrapper.appendChild(cb);
      wrapper.appendChild(document.createTextNode(' ' + label));
      randomOptions.appendChild(wrapper);
    });
    randomMenuInit = true;
  }

  /**
   * Toggle visibility of the random menu. When opened, the menu is
   * initialised if it hasn't been yet. Clicking outside the menu is
   * handled via the close button or by clicking the shuffle icon again.
   */
  function toggleRandomMenu(){
    if (!randomMenu) return;
    const isVisible = randomMenu.style.display !== 'none' && randomMenu.style.display !== '';
    if (isVisible){
      randomMenu.style.display = 'none';
    } else {
      initRandomMenu();
      // Position the menu relative to its trigger (the randomise button).
      randomMenu.style.display = 'block';
    }
  }

  /**
   * Show a dialog allowing the user to choose which parts of their outfit
   * to randomize. This uses SweetAlert2 with dynamically generated
   * checkboxes for each slot. When the user confirms, it calls
   * performRandomization() with the selected slots.
   */
  function randomizeOutfit(){
    // Build HTML for checkboxes. Use slot labels from boot.slots and
    // human-readable labels via a map (fallback to capitalising slot).
    const labels = {
      color: 'Colour',
      head: 'Head',
      face: 'Face',
      neck: 'Neck',
      body: 'Body',
      hand: 'Hand',
      feet: 'Feet',
      photo: 'Photo',
      flag: 'Pin'
    };
    let html = '<div style="text-align:left">';
    slots.forEach(s => {
      const label = labels[s] || (s.charAt(0).toUpperCase() + s.slice(1));
      html += '<label style="display:block;margin-bottom:4px;"><input type="checkbox" id="rand_'+s+'" checked> ' + label + '</label>';
    });
    html += '</div>';
    Swal.fire({
      title: 'Randomize Outfit',
      html: html,
      showCancelButton: true,
      confirmButtonText: 'Randomize',
      customClass: { confirmButton: 'os-btn', cancelButton: 'os-btn os-btnSoft' },
      buttonsStyling: false,
      focusConfirm: false,
      preConfirm: () => {
        const selected = [];
        slots.forEach(s => {
          const cb = document.getElementById('rand_' + s);
          if (cb && cb.checked) selected.push(s);
        });
        return selected;
      }
    }).then(result => {
      if (!result.isConfirmed) return;
      const selectedSlots = result.value || [];
      if (selectedSlots.length === 0) return;
      performRandomization(selectedSlots);
    });
  }

  // Cache of owned items grouped by slot. Populated on first randomize.
  let ownedCache = null;

  /**
   * Fetch the user's owned items (via API) and populate the ownedCache.
   * When done, call randomizeNow() to actually pick items for the given
   * slots. If ownedCache is already populated, randomizeNow() is
   * called immediately.
   */
  function performRandomization(selectedSlots){
    if (ownedCache === null){
      // Fetch all owned items (up to 1000). We ask the server for all
      // items in one go to keep the UI snappy. Adjust per as needed.
      fetch('api/owned_items.php?slot=all&per=1000', { headers: { 'Accept': 'application/json' } })
        .then(res => res.json())
        .then(data => {
          if (!data || !data.ok || !Array.isArray(data.items)){
            Swal.fire({ icon:'error', title:'Unable to randomize', text:'Could not load your items.', customClass:{ confirmButton:'os-btn' }, buttonsStyling:false });
            return;
          }
          // Group items by slot for quick lookup
          const grouped = {};
          slots.forEach(s => { grouped[s] = []; });
          data.items.forEach(it => {
            const sl = it.slot || 'unknown';
            if (grouped[sl]) grouped[sl].push(it.id);
          });
          ownedCache = grouped;
          randomizeNow(selectedSlots);
        }).catch(() => {
          Swal.fire({ icon:'error', title:'Network error', text:'Failed to load your items.', customClass:{ confirmButton:'os-btn' }, buttonsStyling:false });
        });
    } else {
      randomizeNow(selectedSlots);
    }
  }

  /**
   * For each slot in selectedSlots, pick a random owned item ID from the
   * ownedCache. If there are no owned items for a slot, the slot is
   * cleared (set to 0). After randomization the UI is updated.
   */
  function randomizeNow(selectedSlots){
    selectedSlots.forEach(s => {
      const items = (ownedCache && ownedCache[s]) ? ownedCache[s] : [];
      if (items && items.length > 0){
        const ridx = Math.floor(Math.random() * items.length);
        const itemId = parseInt(items[ridx], 10) || 0;
        state[s] = itemId;
      } else {
        state[s] = 0;
      }
    });
    setHidden();
    setEquipChips();
    setAvatar('draft', state);
    updateScene();
    updateInventoryHighlights();
  }

  // Load saved outfits is now invoked during boot (see bottom of file). The
  // previous DOMContentLoaded handler has been removed to avoid duplicate
  // requests.

function getScenePref(){
  try { return localStorage.getItem('os_scene') || ''; } catch(e){ return ''; }
}
function setScenePref(v){
  try { localStorage.setItem('os_scene', v); } catch(e){}
}

function setPinHidden(hidden){
  document.body.classList.toggle('os-pin-hidden', !!hidden);
  if (pinToggle) pinToggle.setAttribute('aria-pressed', hidden ? 'true' : 'false');
  if (pinToggleText) pinToggleText.textContent = hidden ? 'Hidden' : 'Shown';
  try { localStorage.setItem('os_pin_hidden', hidden ? '1' : '0'); } catch(e){}
}
function getPinHidden(){
  try { return localStorage.getItem('os_pin_hidden') === '1'; } catch(e){ return false; }
}


function getInvView(){
  try { return localStorage.getItem('os_inv_view') || 'classic'; } catch(e){ return 'classic'; }
}
function setInvView(v){
  try { localStorage.setItem('os_inv_view', v); } catch(e){}
  document.body.dataset.inv = v;
  viewBtns.forEach(b=>{
    const on = (b.dataset.inv === v);
    b.classList.toggle('is-active', on);
    b.setAttribute('aria-pressed', on ? 'true' : 'false');
  });
}

// Wire inventory view toggle (Comfortable vs 3-per-row)
if (viewToggle){
  viewToggle.addEventListener('click', (e)=>{
    const b = e.target.closest('.os-viewBtn');
    if (!b) return;
    e.preventDefault();
    setInvView(b.dataset.inv || 'classic');
  });
}

function sceneUrlForPhoto(photoId){
  const base = boot.photoBase || '/yukon/assets/media/clothing/photos/';
  return base + String(photoId) + '.png';
}

function updateScene(){
    if (!stage || !sceneFill || !sceneMain) return;

    const pref = (sceneSelect && sceneSelect.value) ? sceneSelect.value : 'blue';
    const previewSource = (activeView === 'current') ? current : state;
    const photoId = parseInt(previewSource.photo, 10) || 0;

    // Determine the new mode. If Avatar mode is chosen but no photo is set,
    // gracefully fall back to the default blue scene.
    let mode = pref;
    if (mode === 'avatar' && photoId <= 0) mode = 'blue';

    // Prepare new background values for the stage and scene layers. We
    // compute these ahead of any animation so we can apply them after
    // fading out.
    let newStageBg = '#0b58b1';
    let newFillBg = '';
    let newMainBg = '';

    if (mode === 'winter_plaza'){
      const winterUrl = (boot.scenes && boot.scenes.winter_plaza && boot.scenes.winter_plaza.url) ? boot.scenes.winter_plaza.url : '';
      newStageBg = '#0b58b1';
      newFillBg = winterUrl ? ('url(' + winterUrl + ')') : '';
      newMainBg = newFillBg;
    } else if (mode === 'avatar'){
      const u = sceneUrlForPhoto(photoId);
      newStageBg = '#0b58b1';
      newFillBg = 'url(' + u + ')';
      newMainBg = newFillBg;
    } else {
      // Fallback to solid CP blue. The colour can be customised via boot.scenes.blue.color.
      newStageBg = (boot.scenes && boot.scenes.blue && boot.scenes.blue.color) ? boot.scenes.blue.color : '#0b58b1';
      newFillBg = '';
      newMainBg = '';
    }
    // Random colours mode: reuse an existing gradient unless the user just
    // switched to this mode. Generating a new gradient only when needed
    // avoids changing colours unexpectedly on every re-render (e.g. when
    // equipping items). The stage background falls back to the CP blue.
    if (mode === 'random'){
      if (mode !== lastSceneMode || !lastRandomGradient){
        lastRandomGradient = buildRandomGradient();
      }
      newStageBg = (boot.scenes && boot.scenes.blue && boot.scenes.blue.color) ? boot.scenes.blue.color : '#0b58b1';
      newFillBg = lastRandomGradient;
      newMainBg = lastRandomGradient;
    }

    // Determine whether the scene has actually changed. If not, skip the
    // animation to prevent distracting fades when equipping items. We also
    // update the last seen values for the next comparison.
    const needsAnimation = (mode !== lastSceneMode) || (mode === 'avatar' && photoId !== lastPhotoId);
    lastSceneMode = mode;
    lastPhotoId = photoId;

    if (needsAnimation && window.gsap) {
      // Fade out, apply new backgrounds, then fade back in.
      window.gsap.to([sceneFill, sceneMain], {
        opacity: 0,
        duration: 0.35,
        ease: 'power1.out',
        onComplete: function(){
          stage.style.background = newStageBg;
          sceneFill.style.backgroundImage = newFillBg;
          sceneMain.style.backgroundImage = newMainBg;
          // Apply or remove animated gradient for random mode
          if (mode === 'random'){
            sceneFill.style.backgroundSize = '600% 600%';
            sceneMain.style.backgroundSize = '600% 600%';
            sceneFill.style.animation = 'os-randomBG 20s ease infinite';
            sceneMain.style.animation = 'os-randomBG 20s ease infinite';
          } else {
            sceneFill.style.backgroundSize = '';
            sceneMain.style.backgroundSize = '';
            sceneFill.style.animation = '';
            sceneMain.style.animation = '';
          }
          window.gsap.to([sceneFill, sceneMain], {
            opacity: 1,
            duration: 0.35,
            ease: 'power1.in'
          });
        }
      });
    } else {
      // Apply immediately without animation.
      stage.style.background = newStageBg;
      sceneFill.style.backgroundImage = newFillBg;
      sceneMain.style.backgroundImage = newMainBg;
      if (mode === 'random'){
        sceneFill.style.backgroundSize = '600% 600%';
        sceneMain.style.backgroundSize = '600% 600%';
        sceneFill.style.animation = 'os-randomBG 20s ease infinite';
        sceneMain.style.animation = 'os-randomBG 20s ease infinite';
      } else {
        sceneFill.style.backgroundSize = '';
        sceneMain.style.backgroundSize = '';
        sceneFill.style.animation = '';
        sceneMain.style.animation = '';
      }
    }
}

// Wire scene selector
if (sceneSelect){
  const saved = getScenePref();
  // Default: if user has a photo background, prefer Avatar Background
  const defaultScene = (current.photo && parseInt(current.photo,10) > 0) ? 'avatar' : 'blue';
  sceneSelect.value = saved || defaultScene;

  sceneSelect.addEventListener('change', ()=>{
    setScenePref(sceneSelect.value);
    updateScene();
  });
}

// Wire pin toggle
if (pinToggle){
  setPinHidden(getPinHidden());
  pinToggle.addEventListener('click', ()=>{
    setPinHidden(!document.body.classList.contains('os-pin-hidden'));
  });
}

  // Boot
  setHidden();
  setEquipChips();
  applyView('preview');
  updateScene();
  // inventory layout preference
  setInvView(getInvView());
  render();
  updateInventoryHighlights();
  // Load the initial list of saved outfits once the UI has rendered. This call
  // happens after setting up the view so that the Saved Outfits section can
  // properly insert itself into the DOM if data is returned.
// The legacy bottom saved outfits list is no longer used. We hide the
// section via CSS, and avoid calling loadSavedOutfits() to reduce
// unnecessary API calls.
// try { loadSavedOutfits(); } catch(e){}
})();