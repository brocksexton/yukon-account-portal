(function(){
  const cfg = window.MP_CFG;
  if(!cfg) return;

  const slotPicker = document.getElementById('mpSlotPicker');
  const grid = document.getElementById('mpItemGrid');
  const search = document.getElementById('mpItemSearch');
  const catSel = document.getElementById('mpCategoryFilter');
  const countLabel = document.getElementById('mpGridCount');
  const resetBtn = document.getElementById('mpResetOutfit');

  const avColor = document.getElementById('mpAvColor');
  const avFeet = document.getElementById('mpAvFeet');
  const avBody = document.getElementById('mpAvBody');
  const avNeck = document.getElementById('mpAvNeck');
  const avFace = document.getElementById('mpAvFace');
  const avHead = document.getElementById('mpAvHead');
  const avHand = document.getElementById('mpAvHand');
  const avPin  = document.getElementById('mpAvPin');

  const slotVal = (slot) => document.getElementById('mpSlotVal-' + slot);
  const hidden = (slot) => document.getElementById('mpHidden-' + slot);

  const slots = Array.isArray(cfg.slots) ? cfg.slots : [];
  const ownedBySlot = cfg.ownedBySlot || {};
  const current = Object.assign({}, cfg.current || {});
  const state = Object.assign({}, current);

  let activeSlot = 'head';

  function iconUrl(slot, id){
    id = parseInt(id || '0', 10) || 0;
    if(id <= 0) return '';
    if(slot === 'color') return cfg.yukonRoot + '/yukon-account-portal/assets/media/color/icon/120/' + id + '.png';
    return cfg.yukonRoot + '/yukon-account-portal/assets/media/clothing/icon/240/' + id + '.png';
  }
  function paperUrl(id){
    id = parseInt(id || '0', 10) || 0;
    if(id <= 0) return '';
    return cfg.yukonAssetBase + '/media/clothing/paper/' + id + '.png';
  }
  function pinUrl(id){
    id = parseInt(id || '0', 10) || 0;
    if(id <= 0) return '';
    return cfg.yukonAssetBase + '/media/clothing/icon/120/' + id + '.png';
  }

  function applyToPreview(slot, id){
    id = parseInt(id || '0', 10) || 0;

    if(slot === 'color'){
      if(avColor){
        // color preview uses cp_colour_src already; just swap icon in equipped cards.
      }
      return;
    }

    const show = (img, url) => {
      if(!img) return;
      if(!url || id <= 0){
        img.style.display = 'none';
        img.removeAttribute('src');
        return;
      }
      img.style.display = '';
      img.setAttribute('src', url);
    };

    if(slot === 'feet') show(avFeet, paperUrl(id));
    if(slot === 'body') show(avBody, paperUrl(id));
    if(slot === 'neck') show(avNeck, paperUrl(id));
    if(slot === 'face') show(avFace, paperUrl(id));
    if(slot === 'head') show(avHead, paperUrl(id));
    if(slot === 'hand') show(avHand, paperUrl(id));
    if(slot === 'flag') show(avPin, pinUrl(id));
    // photo/background not composited here (handled server-side background image)
  }

  function updateEquippedCard(slot, id){
    const card = document.querySelector('.mp-slot[data-slot=\"' + slot + '\"]');
    if(!card) return;
    card.dataset.id = String(id);
    const img = card.querySelector('img');
    const idP = card.querySelector('.id');
    if(img){
      const url = iconUrl(slot, id);
      if(url){
        img.style.display = '';
        img.src = url;
      } else {
        img.style.display = 'none';
      }
    }
    if(idP){
      idP.textContent = (id > 0) ? ('Item ID: ' + id) : 'None equipped';
    }
  }

  function setSlot(slot){
    activeSlot = slot;
    if(slotPicker){
      [...slotPicker.querySelectorAll('.mp-slotBtn')].forEach(b=>{
        b.classList.toggle('active', b.dataset.slot === slot);
      });
    }
    renderGrid();
  }

  function getVisibleIds(){
    const q = (search && search.value || '').trim().toLowerCase();
    const mode = catSel ? catSel.value : 'slot';

    let ids = [];
    if(mode === 'all') ids = ownedBySlot.all || [];
    else if(mode === 'unknown') ids = ownedBySlot.unknown || [];
    else ids = ownedBySlot[activeSlot] || ownedBySlot.all || [];

    // If we have slot metadata and this slot list is empty, fallback to all
    // If slot lists are empty, do not fall back to all (prevents cross-slot equipping when crumbs metadata is missing).

    if(q){
      // search by #id only (name support can be added once crumbs expose names)
      ids = ids.filter(id => String(id).includes(q));
    }
    return ids;
  }

  function renderGrid(){
    if(!grid) return;
    const ids = getVisibleIds();
    grid.innerHTML = '';
    const frag = document.createDocumentFragment();

    ids.forEach((id)=>{
      const btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'mp-item';
      btn.dataset.itemId = String(id);
      if(state[activeSlot] === id) btn.classList.add('selected');

      const img = document.createElement('img');
      img.alt = '';
      img.loading = 'lazy';
      img.src = iconUrl(activeSlot === 'color' ? 'color' : 'clothing', id);
      // For non-color we still use clothing icon url:
      if(activeSlot !== 'color') img.src = cfg.yukonRoot + '/yukon-account-portal/assets/media/clothing/icon/240/' + id + '.png';
      if(activeSlot === 'color') img.src = cfg.yukonRoot + '/yukon-account-portal/assets/media/color/icon/120/' + id + '.png';

      const cap = document.createElement('div');
      cap.className = 'cap';
      cap.textContent = '#' + id;

      btn.appendChild(img);
      btn.appendChild(cap);

      btn.addEventListener('click', ()=>{
        const itemId = parseInt(btn.dataset.itemId, 10) || 0;
        state[activeSlot] = itemId;
        if(hidden(activeSlot)) hidden(activeSlot).value = String(itemId);
        if(slotVal(activeSlot)) slotVal(activeSlot).textContent = '#' + itemId;

        updateEquippedCard(activeSlot, itemId);
        applyToPreview(activeSlot, itemId);

        // update selection UI
        [...grid.querySelectorAll('.mp-item.selected')].forEach(el=>el.classList.remove('selected'));
        btn.classList.add('selected');
      });

      frag.appendChild(btn);
    });

    grid.appendChild(frag);
    if(countLabel) countLabel.textContent = ids.length + ' items';
  }

  function reset(){
    for(const s of slots){
      state[s] = current[s] || 0;
      if(hidden(s)) hidden(s).value = String(state[s]);
      if(slotVal(s)) slotVal(s).textContent = '#' + state[s];
      updateEquippedCard(s, state[s]);
      applyToPreview(s, state[s]);
    }
    renderGrid();
  }

  // Wire events
  if(slotPicker){
    slotPicker.addEventListener('click', (e)=>{
      const btn = e.target.closest('.mp-slotBtn');
      if(!btn) return;
      setSlot(btn.dataset.slot);
    });
  }
  if(search) search.addEventListener('input', renderGrid);
  if(catSel) catSel.addEventListener('change', renderGrid);
  if(resetBtn) resetBtn.addEventListener('click', reset);

  // Init
  setSlot('head');
  reset();
})();