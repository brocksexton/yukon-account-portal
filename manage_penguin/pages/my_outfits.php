<?php
// manage_penguin/pages/my_outfits.php
// Display the current user's saved outfits with management actions.

// When embedded into the dashboard (index.php?tab=my_outfits) the variable
// $embed will be defined. In embedded mode we skip the manage header/footer
// so navigation remains intact. Otherwise we include manage_header.php to
// provide a full page shell.
$embed = isset($embed) && $embed;
if (!$embed) {
    require_once __DIR__ . '/../includes/manage_header.php';
}
// Load the avatar component used by this page. It resides within this module.
require_once __DIR__ . '/../includes/avatar_component.php';

require_login();
$user = current_user();
$pageTitle = 'My Saved Outfits - ' . SITE_NAME;
?>

<div class="mp-wrap">
  <!-- Wrap the entire My Outfits section in a card to provide consistent
       padding, rounded corners and a light background matching the
       dashboard aesthetic. -->
  <div class="cp-card">
    <h2 class="cp-title">My Saved Outfits</h2>
    <p class="cp-subtitle">Browse, apply, rename or delete the outfits you've saved in the Outfit Studio.</p>

    <!-- Offer a link to view all public outfits. This helps users discover
         what other players are wearing and encourages sharing. -->
    <div style="margin:12px 0 24px;">
      <a class="cp-btn cp-btn--secondary" href="public_outfits.php">Browse public outfits</a>
    </div>

    <!-- Use a vertical list container here. Using the generic mp-itemGrid
         (auto-fill grid) causes wide outfit cards to overflow and visually
         overlap when embedded inside index.php tabs. -->
    <div id="myOutfitsList" class="mp-outfitList"></div>
  </div>
</div>

<!-- Load SweetAlert2 for nicer prompts and confirmations -->
<script src="//cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
// Fetch and render the user's outfits on page load.
(function() {
  const listEl = document.getElementById('myOutfitsList');
  if (!listEl) return;
  const csrfToken = '<?php echo e(csrf_token()); ?>';

  /**
   * Build a layered penguin preview for a given outfit. This mirrors the
   * layering logic used in the Outfit Studio. Colour, base overlay,
   * clothing items and pin are drawn. Returns a DOM element.
   *
   * @param {Object} o Outfit record
   * @param {number} size Pixel size of the preview (default 120)
   */
  function buildPreview(o, size = 120) {
    const wrap = document.createElement('div');
    wrap.className = 'mp-avatar';
    wrap.style.width = size + 'px';
    wrap.style.height = size + 'px';
    wrap.style.position = 'relative';
    wrap.style.borderRadius = '16px';
    wrap.style.overflow = 'hidden';
    wrap.style.boxShadow = '0 6px 16px rgba(0,0,0,0.12)';
    // Photo background: use the proper clothing photos directory. In the
    // Outfit Studio we set photoBase in __OS_BOOT__. For this page we
    // explicitly reference the Yukon photos directory instead of the
    // icon path. Without this change the preview shows the paper icon
    // rather than the actual background image.
    const photoId = o.photo || 0;
    if (photoId && photoId > 0) {
      wrap.style.backgroundImage = 'url(/yukon/assets/media/clothing/photos/' + photoId + '.png)';
      wrap.style.backgroundSize = 'cover';
      wrap.style.backgroundPosition = 'center';
    }
    // Colour/base
    const colourId = (o.color && o.color > 0) ? o.color : 1;
    const colourImg = document.createElement('img');
    colourImg.src = '/yukon/assets/media/color/penguin/' + colourId + '.png';
    colourImg.onerror = function(){
      this.onerror = null;
      this.src = '/assets/img/colours/' + colourId + '.png';
    };
    colourImg.alt = '';
    colourImg.style.position = 'absolute';
    colourImg.style.top = '0';
    colourImg.style.left = '0';
    colourImg.style.width = '100%';
    colourImg.style.height = '100%';
    colourImg.style.objectFit = 'cover';
    wrap.appendChild(colourImg);
    // Transparent overlay
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
    // Clothing paper layers
    ['feet','body','neck','face','head','hand'].forEach(slot => {
      const id = o[slot] || 0;
      if (!id) return;
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
    // Pin
    const flagId = o.flag || 0;
    if (flagId > 0) {
      const pinImg = document.createElement('img');
      pinImg.src = '/yukon/assets/media/clothing/icon/120/' + flagId + '.png';
      pinImg.alt = '';
      pinImg.style.position = 'absolute';
      pinImg.style.top = '6px';
      pinImg.style.right = '6px';
      pinImg.style.width = Math.max(24, size * 0.25) + 'px';
      pinImg.style.height = Math.max(24, size * 0.25) + 'px';
      pinImg.style.objectFit = 'contain';
      wrap.appendChild(pinImg);
    }
    return wrap;
  }

  /**
   * Render all outfits into the list. Each card shows a preview, name,
   * description and metadata, along with buttons to apply, rename and delete.
   * Imported outfits (o.sourceShareCode) cannot be renamed or changed to/from public.
   */
  function renderOutfits(data) {
    listEl.innerHTML = '';
    if (!data || !data.ok || !Array.isArray(data.outfits) || data.outfits.length === 0) {
      const empty = document.createElement('p');
      empty.className = 'cp-muted';
      empty.textContent = 'You have no saved outfits. Use the Outfit Studio to save some!';
      listEl.appendChild(empty);
      return;
    }
    data.outfits.forEach(o => {
      const card = document.createElement('div');
      // Use cp-card for base styling and mp-outfitCard for the grid layout.
      card.className = 'cp-card mp-outfitCard';

      // Preview
      const prevWrap = document.createElement('div');
      const prev = buildPreview(o, 160);
      prevWrap.appendChild(prev);
      card.appendChild(prevWrap);

      // Info area
      const info = document.createElement('div');
      info.style.minWidth = '0';
      const nameEl = document.createElement('div');
      nameEl.style.fontWeight = '900';
      nameEl.style.fontSize = '16px';
      nameEl.style.color = '#0b2b45';
      nameEl.textContent = o.name || 'Untitled';
      info.appendChild(nameEl);
      const descEl = document.createElement('div');
      descEl.style.fontSize = '13px';
      descEl.style.color = 'rgba(11,43,69,0.75)';
      descEl.textContent = (o.description || '').trim() ? o.description : '—';
      info.appendChild(descEl);
      // Metadata row: show visibility, likes, created/updated dates
      const meta = document.createElement('div');
      meta.style.marginTop = '8px';
      meta.style.fontSize = '12px';
      meta.style.color = 'rgba(11,43,69,0.6)';
      meta.innerHTML =
        '<span style="font-weight:800;margin-right:6px;">Visibility:</span>' + (o.isPublic ? 'Public' : 'Private') +
        ' &nbsp; <span style="font-weight:800;margin-right:6px;">Likes:</span>' + (o.likes || 0) +
        ' &nbsp; <span style="font-weight:800;margin-right:6px;">Created:</span>' + (o.created_at ? new Date(o.created_at).toLocaleDateString() : '') +
        (o.updated_at && o.updated_at !== o.created_at ? (' &nbsp; <span style="font-weight:800;margin-right:6px;">Updated:</span>' + new Date(o.updated_at).toLocaleDateString()) : '');
      info.appendChild(meta);
      // Imported indicator
      if (o.sourceShareCode) {
        const src = document.createElement('div');
        src.style.fontSize = '11px';
        src.style.color = 'rgba(11,43,69,0.5)';
        src.style.marginTop = '4px';
        src.innerHTML = '<em>Imported from another user; edits disabled</em>';
        info.appendChild(src);
      }
      card.appendChild(info);

      // Action buttons
      const actions = document.createElement('div');
      actions.className = 'mp-outfitActions';

      // Apply button: warn if missing items
      const applyBtn = document.createElement('button');
      applyBtn.className = 'cp-btn cp-btn--primary';
      applyBtn.textContent = (o.missing && o.missing > 0) ? 'Equip (missing ' + o.missing + ')' : 'Apply';
      applyBtn.addEventListener('click', () => {
        if (o.missing && o.missing > 0) {
          Swal.fire({ icon:'warning', title:'Missing items', text:'You are missing ' + o.missing + ' item' + (o.missing > 1 ? 's' : '') + ' from this outfit. Equipping may fail.', customClass:{ confirmButton:'cp-btn' }, buttonsStyling:false });
        }
        const outfit = {};
        ['color','head','face','neck','body','hand','feet','photo','flag'].forEach(s => { outfit[s] = o[s] || 0; });
        Swal.fire({ title: 'Equipping outfit…', allowOutsideClick: false, didOpen: () => Swal.showLoading(), customClass: { confirmButton: 'cp-btn' }, buttonsStyling: false });
        fetch(apiRoot + '/studio_save.php', {
          method:'POST',
          headers:{ 'Content-Type':'application/json', 'X-CSRF-Token': csrfToken },
          body: JSON.stringify({ csrf: csrfToken, outfit: outfit })
        }).then(res => res.json()).then(data => {
          if (!data || !data.ok) {
            Swal.fire({ icon: 'error', title: 'Equip failed', text: (data && data.error) ? data.error : 'Failed to equip.', customClass: { confirmButton: 'cp-btn' }, buttonsStyling: false });
            return;
          }
          Swal.fire({ icon:'success', title:'Outfit equipped!', timer:1500, showConfirmButton:false, customClass:{ confirmButton:'cp-btn' }, buttonsStyling:false });
        }).catch(() => {
          Swal.fire({ icon:'error', title:'Network error', text:'Unable to equip.', customClass:{ confirmButton:'cp-btn' }, buttonsStyling:false });
        });
      });
      actions.appendChild(applyBtn);

      // Rename button: only if user created (no sourceShareCode)
      if (!o.sourceShareCode) {
        const renameBtn = document.createElement('button');
        renameBtn.className = 'cp-btn cp-btn--secondary';
        renameBtn.textContent = 'Rename';
        renameBtn.addEventListener('click', () => {
          Swal.fire({
            title: 'Rename Outfit',
            input: 'text',
            inputLabel: 'New name',
            inputValue: o.name,
            showCancelButton: true,
            confirmButtonText: 'Save',
            customClass: { confirmButton: 'cp-btn', cancelButton: 'cp-btn cp-btn-soft' },
            buttonsStyling: false,
            preConfirm: (val) => {
              if (!val || !val.trim()) {
                Swal.showValidationMessage('Name cannot be empty');
                return false;
              }
              return val.trim();
            }
          }).then(result => {
            if (!result.isConfirmed) return;
            const newName = result.value.trim();
            fetch(apiRoot + '/outfits.php', {
              method:'PUT',
              headers:{ 'Content-Type':'application/json', 'X-CSRF-Token': csrfToken },
              body: JSON.stringify({ csrf: csrfToken, id: o.id, name: newName, description: o.description || '', isPublic: o.isPublic })
            }).then(res=>res.json()).then(data => {
              if (!data || !data.ok){
                Swal.fire({ icon:'error', title:'Update failed', text:(data && data.error) ? data.error : 'Could not rename.', customClass:{ confirmButton:'cp-btn' }, buttonsStyling:false });
                return;
              }
              loadOutfits();
              Swal.fire({ icon:'success', title:'Renamed', timer:1200, showConfirmButton:false });
            }).catch(() => {
              Swal.fire({ icon:'error', title:'Network error', text:'Unable to update.', customClass:{ confirmButton:'cp-btn' }, buttonsStyling:false });
            });
          });
        });
        actions.appendChild(renameBtn);
      }

      // Toggle public/private: only if user created
      if (!o.sourceShareCode) {
        const toggleBtn = document.createElement('button');
        toggleBtn.className = 'cp-btn cp-btn-soft';
        toggleBtn.textContent = o.isPublic ? 'Make Private' : 'Make Public';
        toggleBtn.addEventListener('click', () => {
          const newVisibility = o.isPublic ? 0 : 1;
          fetch(apiRoot + '/outfits.php', {
            method:'PUT',
            headers:{ 'Content-Type':'application/json', 'X-CSRF-Token': csrfToken },
            body: JSON.stringify({ csrf: csrfToken, id: o.id, name: o.name, description: o.description || '', isPublic: newVisibility })
          }).then(res=>res.json()).then(data => {
            if (!data || !data.ok){
              Swal.fire({ icon:'error', title:'Update failed', text:(data && data.error) ? data.error : 'Could not update.', customClass:{ confirmButton:'cp-btn' }, buttonsStyling:false });
              return;
            }
            loadOutfits();
          }).catch(() => {
            Swal.fire({ icon:'error', title:'Network error', text:'Unable to update.', customClass:{ confirmButton:'cp-btn' }, buttonsStyling:false });
          });
        });
        actions.appendChild(toggleBtn);
      }

      // Delete button
      const deleteBtn = document.createElement('button');
      deleteBtn.className = 'cp-btn cp-btn-soft';
      deleteBtn.style.background = 'rgba(255,60,60,0.14)';
      deleteBtn.style.color = '#8a0f0f';
      deleteBtn.textContent = 'Delete';
      deleteBtn.addEventListener('click', () => {
        Swal.fire({
          title:'Delete outfit?',
          text:'This action cannot be undone.',
          icon:'warning',
          showCancelButton:true,
          confirmButtonText:'Delete',
          customClass:{ confirmButton:'cp-btn', cancelButton:'cp-btn cp-btn-soft' },
          buttonsStyling:false
        }).then(res => {
          if (!res.isConfirmed) return;
          fetch(apiRoot + '/outfits.php?id=' + encodeURIComponent(o.id), {
            method:'DELETE',
            headers:{ 'X-CSRF-Token': csrfToken }
          }).then(resp => resp.json()).then(data => {
            if (!data || !data.ok){
              Swal.fire({ icon:'error', title:'Delete failed', text:(data && data.error) ? data.error : 'Unable to delete.', customClass:{ confirmButton:'cp-btn' }, buttonsStyling:false });
              return;
            }
            loadOutfits();
            Swal.fire({ icon:'success', title:'Deleted', timer:1000, showConfirmButton:false });
          }).catch(() => {
            Swal.fire({ icon:'error', title:'Network error', text:'Unable to delete.', customClass:{ confirmButton:'cp-btn' }, buttonsStyling:false });
          });
        });
      });
      actions.appendChild(deleteBtn);

      card.appendChild(actions);
      listEl.appendChild(card);
    });
  }

  // Determine the API prefix. When embedded in the dashboard the base
  // URL is manage_penguin/, so API calls should use "api/...". When
  // this page is loaded standalone (not embedded) it lives in pages/, so
  // calls must go up one directory ("../api/..."). PHP sets $embed
  // accordingly above.
  const apiPrefix = <?php echo json_encode($embed ? '' : '..'); ?>;
  // Compute the full API root. If apiPrefix is empty (embedded in
  // /manage_penguin/) we do not prepend a slash so the URL resolves to
  // 'manage_penguin/api/...'. Otherwise we go up one directory ("../api").
  const apiRoot = apiPrefix ? apiPrefix + '/api' : 'api';

  // Load outfits from API
  function loadOutfits(){
    fetch(apiRoot + '/outfits.php?limit=100', { headers:{ 'Accept':'application/json' }})
      .then(res=>res.json()).then(data=>{ renderOutfits(data); }).catch(() => {
        listEl.innerHTML = '<p class="cp-muted">Failed to load outfits.</p>';
      });
  }

  // Kick off
  loadOutfits();
})();
</script>

<?php if (!$embed): ?>
<?php require_once __DIR__ . '/../includes/manage_footer.php'; ?>
<?php endif; ?>