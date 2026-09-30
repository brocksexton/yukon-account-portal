(() => {
  const qs = (s, r=document) => r.querySelector(s);
  const qsa = (s, r=document) => Array.from(r.querySelectorAll(s));

  const state = {
    slot: 'all',
    search: '',
    page: 1,
    perPage: 48,
    total: 0,
    pages: 1,
    // outfit maps
    current: window.MP_CURRENT_OUTFIT || {},
    preview: JSON.parse(JSON.stringify(window.MP_CURRENT_OUTFIT || {})),
    activeSlot: 'head',
    view: 'preview',
  };

  const el = {
    itemGrid: qs('#mpItems'),
    meta: qs('#mpMeta'),
    search: qs('#mpSearch'),
    chips: qs('#mpChips'),
    prevBtn: qs('#mpPrev'),
    nextBtn: qs('#mpNext'),
    pageLabel: qs('#mpPageLabel'),
    saveBtn: qs('#mpSave'),
    resetBtn: qs('#mpReset'),
    slots: qs('#mpSlots'),
    tabWrap: qs('#mpViewTabs'),
    previewWrap: qs('#mpPreviewWrap'),
    currentWrap: qs('#mpCurrentWrap'),
    compareWrap: qs('#mpCompareWrap'),
    previewImg: qs('#mpPreviewImg'),
    currentImg: qs('#mpCurrentImg'),
    compareA: qs('#mpCompareA'),
    compareB: qs('#mpCompareB'),
  };

  function setActiveSlot(slot){
    state.activeSlot = slot;
    qsa('[data-slot]', el.slots).forEach(b => {
      b.classList.toggle('active', b.dataset.slot === slot);
    });
    // also set right-side filter to this slot
    setFilterChip(slot);
  }

  function setFilterChip(slot){
    state.slot = slot || 'all';
    qsa('[data-filter]', el.chips).forEach(c => {
      c.classList.toggle('active', c.dataset.filter === state.slot);
    });
    state.page = 1;
    loadItems();
  }

  function setView(v){
    state.view = v;
    qsa('[data-view]', el.tabWrap).forEach(t => {
      t.setAttribute('aria-selected', String(t.dataset.view === v));
    });
    el.previewWrap.hidden = v !== 'preview';
    el.currentWrap.hidden = v !== 'current';
    el.compareWrap.hidden = v !== 'compare';
    if (v === 'preview') {
      renderDoll('preview');
    } else if (v === 'current') {
      renderDoll('current');
    } else {
      renderDoll('compare');
    }
  }

  function outfitToQuery(outfit){
    const p = new URLSearchParams();
    Object.entries(outfit).forEach(([k,v]) => {
      p.set(k, String(v || 0));
    });
    return p.toString();
  }

  function renderDoll(which){
    // Server renders a composite HTML widget via api/studio_avatar.php
    const base = '/yukon-account-portal/manage_penguin/api/studio_avatar.php';
    if (which === 'preview') {
      el.previewImg.src = `${base}?` + outfitToQuery(state.preview);
      el.previewImg.classList.remove('mp-pop');
      void el.previewImg.offsetWidth;
      el.previewImg.classList.add('mp-pop');
    } else if (which === 'current') {
      el.currentImg.src = `${base}?` + outfitToQuery(state.current);
    } else {
      el.compareA.src = `${base}?` + outfitToQuery(state.current);
      el.compareB.src = `${base}?` + outfitToQuery(state.preview);
    }
    renderSlots();
    el.saveBtn.disabled = !isDirty();
  }

  function isDirty(){
    const keys = Object.keys(state.current);
    for (const k of keys){
      if (String(state.current[k]||0) !== String(state.preview[k]||0)) return true;
    }
    return false;
  }

  function renderSlots(){
    qsa('[data-slot]', el.slots).forEach(card => {
      const slot = card.dataset.slot;
      const id = (state.preview[slot] || 0);
      const nameEl = qs('.v', card);
      const imgEl = qs('img', card);
      if (id && window.MP_ITEM_INDEX && window.MP_ITEM_INDEX[id]) {
        const it = window.MP_ITEM_INDEX[id];
        nameEl.textContent = `${it.name || ('#' + id)} • #${id}`;
        imgEl.src = it.icon;
      } else {
        nameEl.textContent = 'None';
        imgEl.src = card.dataset.placeholder;
      }
    });
  }

  async function loadItems(){
    const url = new URL('/yukon-account-portal/manage_penguin/api/studio_items.php', window.location.origin);
    url.searchParams.set('slot', state.slot);
    url.searchParams.set('search', state.search);
    url.searchParams.set('page', String(state.page));
    url.searchParams.set('per_page', String(state.perPage));

    el.itemGrid.setAttribute('aria-busy','true');
    const res = await fetch(url.toString(), {credentials:'same-origin'});
    const json = await res.json();

    state.total = json.total || 0;
    state.pages = json.pages || 1;
    state.page = json.page || 1;

    // Build a quick index for equipped slot display
    window.MP_ITEM_INDEX = window.MP_ITEM_INDEX || {};
    for (const it of (json.items||[])) {
      window.MP_ITEM_INDEX[it.id] = it;
    }

    el.meta.textContent = `${state.total} items • Page ${state.page} / ${state.pages}`;
    el.pageLabel.textContent = `Page ${state.page} / ${state.pages}`;
    el.prevBtn.disabled = state.page <= 1;
    el.nextBtn.disabled = state.page >= state.pages;

    el.itemGrid.innerHTML = (json.items||[]).map(it => {
      const safeName = (it.name || ('#' + it.id)).replace(/"/g,'&quot;');
      return `
        <div class="mp-item" data-id="${it.id}" data-slot="${it.slot}">
          <div class="icon"><img loading="lazy" src="${it.icon}" alt="${safeName}"></div>
          <div class="n">${safeName}</div>
          <div class="m">${it.slot_label} • #${it.id}</div>
        </div>
      `;
    }).join('');

    el.itemGrid.setAttribute('aria-busy','false');
  }

  function autoEquip(itemId, slot){
    // Enforce correct slot client-side; server re-validates on save.
    state.preview[slot] = Number(itemId);
    setActiveSlot(slot);
    renderDoll(state.view);
  }

  async function save(){
    if (!isDirty()) return;
    el.saveBtn.disabled = true;
    const res = await fetch('/yukon-account-portal/manage_penguin/api/studio_save.php', {
      method: 'POST',
      credentials: 'same-origin',
      headers: {'Content-Type':'application/json'},
      body: JSON.stringify({
        csrf: window.MP_CSRF,
        outfit: state.preview,
      })
    });
    const json = await res.json();
    if (!json.ok){
      alert(json.error || 'Save failed.');
      el.saveBtn.disabled = false;
      return;
    }
    state.current = JSON.parse(JSON.stringify(state.preview));
    setView('preview');
    renderDoll('preview');
  }

  function reset(){
    state.preview = JSON.parse(JSON.stringify(state.current));
    renderDoll(state.view);
  }

  // Wire events
  el.search.addEventListener('input', (e) => {
    state.search = e.target.value.trim();
    state.page = 1;
    loadItems();
  });

  el.chips.addEventListener('click', (e) => {
    const chip = e.target.closest('[data-filter]');
    if (!chip) return;
    setFilterChip(chip.dataset.filter);
  });

  el.itemGrid.addEventListener('click', (e) => {
    const card = e.target.closest('.mp-item');
    if (!card) return;
    autoEquip(card.dataset.id, card.dataset.slot);
  });

  el.prevBtn.addEventListener('click', () => {
    if (state.page > 1){ state.page -= 1; loadItems(); }
  });
  el.nextBtn.addEventListener('click', () => {
    if (state.page < state.pages){ state.page += 1; loadItems(); }
  });

  el.saveBtn.addEventListener('click', save);
  el.resetBtn.addEventListener('click', reset);

  el.slots.addEventListener('click', (e) => {
    const s = e.target.closest('[data-slot]');
    if (!s) return;
    setActiveSlot(s.dataset.slot);
  });

  el.tabWrap.addEventListener('click', (e) => {
    const t = e.target.closest('[data-view]');
    if (!t) return;
    setView(t.dataset.view);
  });

  // Init
  setActiveSlot('head');
  setFilterChip('all');
  setView('preview');
  renderDoll('preview');
})();
