/* manage_penguin/studio.js */
(function(){
  const cfg = window.MP_STUDIO || {};
  const slots = cfg.slots || [];
  const current = cfg.current || {};
  const state = JSON.parse(JSON.stringify(current)); // draft
  let mode = 'preview';

  const grid = document.getElementById('mpGrid');
  const chips = document.getElementById('mpChips');
  const search = document.getElementById('mpSearch');
  const perSel = document.getElementById('mpPer');

  const pageEl = document.getElementById('mpPage');
  const pagesEl = document.getElementById('mpPages');
  const pageEl2 = document.getElementById('mpPage2');
  const pagesEl2 = document.getElementById('mpPages2');
  const totalEl = document.getElementById('mpTotal');
  const showingEl = document.getElementById('mpShowing');

  const prevBtn = document.getElementById('mpPrev');
  const nextBtn = document.getElementById('mpNext');
  const prevBtn2 = document.getElementById('mpPrev2');
  const nextBtn2 = document.getElementById('mpNext2');

  const resetBtn = document.getElementById('mpReset');
  const saveForm = document.getElementById('mpSaveForm');
  const saveNote = document.getElementById('mpSaveNote');

  const modeBtns = Array.from(document.querySelectorAll('.mp-modeBtn'));
  const modePanels = Array.from(document.querySelectorAll('[data-mode-panel]'));

  const compareSlider = document.getElementById('mpCompareSlider');
  const compareTop = document.getElementById('mpCompareTop');

  let activeSlot = 'all';
  let page = 1;
  let pages = 1;

  // --- helpers ---
  function slotLabel(slot){
    return slot.charAt(0).toUpperCase() + slot.slice(1);
  }

  function setMode(next){
    mode = next;
    modeBtns.forEach(b => b.classList.toggle('is-active', b.dataset.mode === mode));
    modePanels.forEach(p => {
      p.style.display = (p.getAttribute('data-mode-panel') === mode) ? '' : 'none';
    });
    // ensure compare slider applies clip
    if (mode === 'compare') applyCompareClip();
  }

  function applyCompareClip(){
    if (!compareTop || !compareSlider) return;
    const v = parseInt(compareSlider.value || '50', 10);
    // clip-path inset(top right bottom left). We want left side visible up to v%.
    const right = 100 - v;
    compareTop.style.clipPath = `inset(0 ${right}% 0 0)`;
  }

  function iconFor(slot, id){
    if (!id || id <= 0) return '';
    if (slot === 'color') return `/yukon/assets/media/color/icon/120/${id}.png`;
    return `/yukon/assets/media/clothing/icon/240/${id}.png`;
  }

  function paperFor(id){
    if (!id || id <= 0) return '';
    return `/yukon/assets/media/clothing/paper/${id}.png`;
  }

  function getAvatarRoot(prefix){
    return document.querySelector(`.mp-avatar[data-avatar="${prefix}"]`);
  }

  function setAvatar(prefix, draft){
    const root = getAvatarRoot(prefix);
    if (!root) return;

    const frame = root.querySelector('.mp-avatarFrame');
    const colorImg = root.querySelector('.mp-avatarColor');
    const flagImg = root.querySelector('.mp-avatarFlag');
    const bgImg = root.querySelector('.mp-avatarBg');

    const colorId = (draft.color && draft.color > 0) ? draft.color : 1;
    if (colorImg) {
      colorImg.src = `/yukon/assets/media/color/penguin/${colorId}.png`;
      colorImg.style.display = '';
    }

    // photo background (uses clothing icon as existing behavior)
    const photoId = (draft.photo && draft.photo > 0) ? draft.photo : 0;
    if (bgImg){
      if (photoId > 0){
        bgImg.src = iconFor('photo', photoId);
        bgImg.style.display = '';
      } else {
        bgImg.style.display = 'none';
      }
    }

    const flagId = (draft.flag && draft.flag > 0) ? draft.flag : 0;
    if (flagImg){
      if (flagId > 0){
        flagImg.src = iconFor('flag', flagId);
        flagImg.style.display = '';
      } else {
        flagImg.style.display = 'none';
      }
    }

    // paper layers
    const layerSlots = ['feet','body','neck','head','face','hand'];
    layerSlots.forEach(s => {
      const img = root.querySelector(`img[data-slot="${s}"]`);
      if (!img) return;
      const id = draft[s] || 0;
      if (id > 0){
        img.src = paperFor(id);
        img.style.display = '';
      } else {
        img.style.display = 'none';
      }
    });
  }

  function setEquipTiles(){
    const tiles = document.querySelectorAll('.mp-equipTile');
    tiles.forEach(t => {
      const slot = t.getAttribute('data-slot');
      const id = state[slot] || 0;
      const val = t.querySelector('.mp-equipVal');
      const img = t.querySelector('img');
      if (val) val.textContent = id > 0 ? `#${id}` : 'None';
      if (img){
        const src = iconFor(slot, id);
        img.src = src;
        img.style.opacity = src ? '1' : '0.25';
      }
      const hidden = document.getElementById(`mpHidden-${slot}`);
      if (hidden) hidden.value = id;
    });
  }

  function toast(msg, ok=true){
    if (!saveNote) return;
    saveNote.textContent = msg;
    saveNote.style.color = ok ? 'rgba(11,43,69,0.75)' : '#b00020';
    if (window.gsap){
      gsap.fromTo(saveNote, {opacity:0, y:6}, {opacity:1, y:0, duration:0.25, ease:'power2.out'});
    }
  }

  // --- data loading ---
  async function loadPage(){
    grid.innerHTML = `<div class="mp-skel">Loading your wardrobe…</div>`;
    const per = parseInt(perSel.value || '60', 10) || 60;
    const q = (search.value || '').trim();
    const url = new URL(cfg.apiOwned || 'api/owned_items.php', window.location.href);
    url.searchParams.set('slot', activeSlot);
    url.searchParams.set('q', q);
    url.searchParams.set('page', String(page));
    url.searchParams.set('per', String(per));

    const res = await fetch(url.toString(), {credentials:'same-origin'});
    const data = await res.json();
    if (!data.ok) throw new Error(data.error || 'Load failed');

    pages = data.pages || 1;
    page = data.page || 1;

    pageEl.textContent = String(page);
    pagesEl.textContent = String(pages);
    pageEl2.textContent = String(page);
    pagesEl2.textContent = String(pages);
    totalEl.textContent = String(data.total || 0);
    showingEl.textContent = String((data.items || []).length);

    prevBtn.disabled = (page <= 1);
    prevBtn2.disabled = (page <= 1);
    nextBtn.disabled = (page >= pages);
    nextBtn2.disabled = (page >= pages);

    // Render cards
    const frag = document.createDocumentFragment();
    (data.items || []).forEach(it => {
      const btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'mp-card';
      btn.dataset.id = String(it.id);
      btn.dataset.slot = it.slot || 'unknown';
      btn.dataset.name = (it.name || '').toLowerCase();

      // Atropos wrapper for a subtle tilt without being tacky
      btn.innerHTML = `
        <div class="atropos mp-tilt">
          <div class="atropos-scale">
            <div class="atropos-rotate">
              <div class="atropos-inner">
                <div class="mp-cardImg" data-atropos-offset="4"><img src="${it.icon}" alt=""></div>
                <div class="mp-cardName" data-atropos-offset="2" title="${escapeHtml(it.name || ('#'+it.id))}">${escapeHtml(it.name || ('#'+it.id))}</div>
                <div class="mp-cardId" data-atropos-offset="1">#${it.id}</div>
              </div>
            </div>
          </div>
        </div>
      `;
      // selected highlighting
      if ((state[it.slot] || 0) === it.id) btn.classList.add('is-selected');

      btn.addEventListener('click', () => equipItem(it));
      frag.appendChild(btn);
    });

    grid.innerHTML = '';
    grid.appendChild(frag);

    // animations
    if (window.gsap){
      gsap.fromTo(Array.from(grid.children), {opacity:0, y:10}, {opacity:1, y:0, duration:0.25, stagger:0.015, ease:'power2.out'});
    }
    // tilt
    if (window.Atropos){
      grid.querySelectorAll('.mp-tilt').forEach(el => {
        try{ Atropos({el, shadow:false, highlight:false}); } catch(e){}
      });
    }
  }

  function escapeHtml(s){
    return String(s).replace(/[&<>"']/g, (c)=>({ "&":"&amp;","<":"&lt;",">":"&gt;",'"':"&quot;","'":"&#039;" }[c]));
  }

  function equipItem(it){
    const slot = it.slot || 'unknown';
    if (!slots.includes(slot)){
      toast('This item cannot be equipped from the website yet.', false);
      return;
    }
    state[slot] = parseInt(it.id, 10);
    setEquipTiles();
    setAvatar('draft', state);
    if (mode === 'compare') applyCompareClip();

    // highlight selected in grid
    grid.querySelectorAll('.mp-card').forEach(c => {
      const s = c.dataset.slot;
      const id = parseInt(c.dataset.id, 10);
      c.classList.toggle('is-selected', (state[s]||0) === id);
    });

    if (window.gsap){
      gsap.fromTo(getAvatarRoot('draft'), {scale:0.98}, {scale:1, duration:0.18, ease:'power2.out'});
    }
  }

  // --- UI events ---
  chips.addEventListener('click', (e) => {
    const btn = e.target.closest('.mp-chip');
    if (!btn) return;
    chips.querySelectorAll('.mp-chip').forEach(b => b.classList.remove('is-active'));
    btn.classList.add('is-active');
    activeSlot = btn.dataset.slot || 'all';
    page = 1;
    loadPage().catch(err => toast(err.message || 'Failed to load', false));
  });

  let searchT;
  search.addEventListener('input', () => {
    clearTimeout(searchT);
    searchT = setTimeout(() => {
      page = 1;
      loadPage().catch(err => toast(err.message || 'Failed to load', false));
    }, 180);
  });

  perSel.addEventListener('change', () => {
    page = 1;
    loadPage().catch(err => toast(err.message || 'Failed to load', false));
  });

  function go(delta){
    page = Math.max(1, Math.min(pages, page + delta));
    loadPage().catch(err => toast(err.message || 'Failed to load', false));
  }
  prevBtn.addEventListener('click', () => go(-1));
  nextBtn.addEventListener('click', () => go(1));
  prevBtn2.addEventListener('click', () => go(-1));
  nextBtn2.addEventListener('click', () => go(1));

  resetBtn.addEventListener('click', () => {
    Object.keys(current).forEach(k => state[k] = current[k]);
    setEquipTiles();
    setAvatar('draft', state);
    toast('Reset to currently equipped.');
    // refresh selection highlights
    grid.querySelectorAll('.mp-card').forEach(c => {
      const s = c.dataset.slot;
      const id = parseInt(c.dataset.id, 10);
      c.classList.toggle('is-selected', (state[s]||0) === id);
    });
  });

  document.querySelectorAll('[data-unequip]').forEach(b => {
    b.addEventListener('click', () => {
      const slot = b.getAttribute('data-unequip');
      if (!slot) return;
      state[slot] = (slot === 'color') ? (current.color || 1) : 0;
      setEquipTiles();
      setAvatar('draft', state);
      if (mode === 'compare') applyCompareClip();
      loadPage().catch(()=>{}); // update selection highlights if visible
    });
  });

  modeBtns.forEach(b => b.addEventListener('click', () => setMode(b.dataset.mode)));
  if (compareSlider) compareSlider.addEventListener('input', applyCompareClip);

  // Save (AJAX, graceful fallback)
  if (saveForm){
    saveForm.addEventListener('submit', async (e) => {
      // Keep traditional submit if fetch fails
      e.preventDefault();
      const fd = new FormData(saveForm);
      // overwrite with current draft
      slots.forEach(s => fd.set('slot_'+s, String(state[s]||0)));
      try{
        const res = await fetch('api/save_outfit.php', {
          method:'POST',
          body: fd,
          credentials:'same-origin'
        });
        const data = await res.json();
        if (!data.ok) throw new Error(data.error || 'Save failed');
        toast('Saved! Your penguin will use this outfit in-game.');
        // Promote draft -> current
        Object.keys(current).forEach(k => current[k] = state[k]);
        setAvatar('current', current);
      }catch(err){
        toast(err.message || 'Save failed', false);
        // fallback to full submit
        try{ saveForm.submit(); } catch(_){}
      }
    });
  }

  // initial
  setMode('preview');
  setEquipTiles();
  setAvatar('draft', state);
  setAvatar('current', current);
  applyCompareClip();
  loadPage().catch(err => toast(err.message || 'Failed to load', false));
})();
