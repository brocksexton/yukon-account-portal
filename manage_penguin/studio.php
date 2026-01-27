<?php
/**
 * manage_penguin/studio.php
 * Outfit Studio v2 (stage + live preview) – rebuilt from the Outfit tab foundation.
 *
 * Notes:
 * - Uses the same server-side save validation as index.php (ownership + slot type via items.json).
 * - UI/JS are self-contained under manage_penguin/assets/studio/.
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_login();

require_once __DIR__ . '/includes/meta.php';

$user = current_user();
$pageTitle = 'Outfit Studio - ' . (defined('SITE_NAME') ? SITE_NAME : 'Club Penguin');

// --- POST: Save outfit (server-side anti-cheat: ownership + correct slot type via items.json) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_outfit') {
    csrf_require();

    $slots = slot_list();

    // Owned set
    $owned = [];
    $stmt = db()->prepare('SELECT itemId FROM inventories WHERE userId = ?');
    $stmt->bind_param('i', $user['id']);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) $owned[(int)$row['itemId']] = true;
    $stmt->close();

    $updates = [];
    foreach ($slots as $slot) {
        $val = isset($_POST['slot_'.$slot]) ? (int)$_POST['slot_'.$slot] : 0;
        if ($val < 0) $val = 0;

        if ($val > 0) {
            $metaSlot = item_slot_of($val, $ITEMS_META);
            if (!$metaSlot) {
                flash('error', 'That item cannot be equipped (missing type metadata in Yukon items.json).');
                header('Location: studio.php'); exit;
            }
            if ($metaSlot !== $slot) {
                flash('error', 'Invalid equip: item #' . $val . ' belongs to ' . slot_label($metaSlot) . ', not ' . slot_label($slot) . '.');
                header('Location: studio.php'); exit;
            }
            if (!isset($owned[$val])) {
                flash('error', 'Invalid equip: you do not own item #' . $val . '.');
                header('Location: studio.php'); exit;
            }
        }
        $updates[$slot] = $val;
    }

    $sql = "UPDATE users SET color=?, head=?, face=?, neck=?, body=?, hand=?, feet=?, photo=?, flag=? WHERE id=?";
    $stmt = db()->prepare($sql);
    $stmt->bind_param(
        'iiiiiiiiii',
        $updates['color'], $updates['head'], $updates['face'], $updates['neck'], $updates['body'],
        $updates['hand'], $updates['feet'], $updates['photo'], $updates['flag'], $user['id']
    );
    $stmt->execute();
    $stmt->close();

    flash('success', 'Outfit saved.');
    header('Location: studio.php'); exit;
}

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

// Small, local escaper (avoid relying on global e())
if (!function_exists('mp_h')) {
    function mp_h(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
}

/**
 * Render a stage-ready avatar stack:
 * - Stage background is handled outside (scene), avatar stays transparent.
 * - Layers match Yukon paper assets; photo is a stage backdrop layer (handled in JS if desired).
 */
function mp_render_stage_avatar(array $state, string $prefix = 'draft'): void {
    $color = (int)($state['color'] ?? 1);
    if ($color < 1) $color = 1;

    $paper = [
        'feet' => (int)($state['feet'] ?? 0),
        'body' => (int)($state['body'] ?? 0),
        'neck' => (int)($state['neck'] ?? 0),
        'face' => (int)($state['face'] ?? 0),
        'head' => (int)($state['head'] ?? 0),
        'hand' => (int)($state['hand'] ?? 0),
    ];

    $flag  = (int)($state['flag']  ?? 0);
    $pin = $flag > 0 ? ('/yukon/assets/media/clothing/icon/120/' . (int)$flag . '.png') : '';

    echo '<div class="os-avatar" data-role="avatar" data-prefix="' . mp_h($prefix) . '">';
    echo '  <div class="os-avatarInner" aria-hidden="true">';
    // Colour layer: use the Yukon penguin colour PNG rather than
    // local /assets/img files. This avoids missing asset issues when
    // the project is hosted without the colours folder. The large
    // colour PNG includes the full silhouette and shading.
    echo '    <img class="os-layer os-colour" data-layer="colour" src="/yukon/assets/media/color/penguin/' . (int)$color . '.png" alt="">';
    echo '    <img class="os-layer os-base" data-layer="top" src="/assets/img/penguin_transparent.png" alt="">';
    foreach ($paper as $slot => $id) {
        $src = $id > 0 ? ('/yukon/assets/media/clothing/paper/' . (int)$id . '.png') : '';
        echo '    <img class="os-layer os-paper" data-layer="' . mp_h($slot) . '" src="' . mp_h($src) . '" alt="" ' . ($id > 0 ? '' : 'style="display:none"') . '>';
    }
    echo '  </div>';
    echo '  <img class="os-pin" data-layer="pin" src="' . mp_h($pin) . '" alt="" ' . ($flag > 0 ? '' : 'style="display:none"') . '>';
    echo '</div>';
}

$sceneKey = 'winter_plaza';
$sceneLabel = 'Winter Plaza';
$sceneImg = '/manage_penguin/assets/studio/scenes/winter_plaza.jpg';

?>
<?php include __DIR__ . '/includes/manage_header.php'; ?>

<link rel="stylesheet" href="/manage_penguin/assets/studio/studio.css?v=2">

    <!-- Animation libraries added for subtle UX improvements -->
    <!-- GSAP 3.x is a high‑performance animation library. According to a 2025 CSS‑Tricks article, version 3.13 of GSAP and all of its plugins became free for commercial use【776772462460850†L24-L30】. -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/gsap.min.js"></script>
    <!-- VanillaTilt provides a lightweight 3D tilt effect and is distributed under the permissive MIT License【90440959444771†L289-L299】. -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/vanilla-tilt/1.8.1/vanilla-tilt.min.js"></script>

    <!-- SweetAlert2 offers beautiful, customizable replacement pop‑ups for alert(), prompt() and confirm().
         It is distributed under the MIT License, which allows free use, modification and distribution【129066206245351†L4-L11】.
         We load version 11.x from jsDelivr. -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.27/dist/sweetalert2.all.min.js"></script>

    <!-- Font Awesome 6 for icons (free). Font Awesome Free is MIT licensed. We omit integrity/crossorigin
         attributes here to avoid hash mismatches that could block the stylesheet. -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" referrerpolicy="no-referrer" />

<div class="os-wrap">
  <div class="os-shell">
    <div class="os-head">
      <div class="os-titleBlock">
        <a href="index.php" class="os-backLink">← Back to Manage My Penguin</a>
        <div class="os-kicker">Manage My Penguin</div>
        <h1 class="os-title">Outfit Studio</h1>
        <div class="os-sub">Browse and try on your wardrobe. Equip items instantly and save your look with ease.</div>
      </div>

      <div class="os-tools">
        <div class="os-sceneCtl">
  <label class="os-sceneLabel" for="osSceneSelect">Scene:</label>
  <select id="osSceneSelect" class="os-sceneSelect">
    <option value="blue">Club Penguin Blue</option>
    <option value="avatar">Avatar Background</option>
    <option value="winter_plaza">Winter Plaza</option>
    <option value="random">Random Colours</option>
  </select>
</div>

<button type="button" class="os-pinToggle" id="osPinToggle" aria-pressed="false" title="Hide/Show your pin without unequipping it">
  Pin: <span id="osPinToggleText">Shown</span>
</button>

        <div class="os-viewTabs" role="tablist" aria-label="View mode">
          <button type="button" class="os-tab is-active" data-view="preview">Preview</button>
          <button type="button" class="os-tab" data-view="current">Current</button>
          <button type="button" class="os-tab" data-view="compare">Compare</button>
        </div>
      </div>
    </div>

    <div class="os-grid">
      <!-- LEFT: Stage -->
      <section class="os-stageCard">
        <div class="os-stageHeader">
          <div class="os-stageHeaderMain">
            <div class="os-stageTitle">Your Outfit Stage</div>
            <!-- Update hint: mention Equip Now / Save to Library instead of Save -->
            <div class="os-stageHint">Select a category and click an item to equip it. When you’re happy, press Equip&nbsp;Now or Save&nbsp;to&nbsp;Library.</div>
          </div>
          <!-- Randomize toggle: moved from actions bar into the stage header. This icon
               opens a small floating menu allowing the user to pick which parts
               of the outfit to randomise. -->
          <div class="os-stageRandom">
            <button type="button" class="os-btn os-btnIcon" id="osRandomizeBtn" title="Randomize">
              <i class="fa-solid fa-shuffle" aria-hidden="true"></i>
            </button>
            <div id="osRandomMenu" class="os-randomMenu" style="display:none;">
              <div class="os-randomMenuHeader">
                <span class="os-randomMenuTitle">Randomize Outfit</span>
                <button type="button" class="os-randomMenuClose" id="osRandomMenuClose" title="Close">
                  <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                </button>
              </div>
              <div class="os-randomOptions" id="osRandomOptions">
                <!-- Checkboxes populated by JS -->
              </div>
              <button type="button" class="os-btn os-btnSoft os-randomGoBtn" id="osRandomGoBtn">
                <i class="fa-solid fa-shuffle" aria-hidden="true"></i> Go
              </button>
            </div>
          </div>
        </div>

        <div class="os-stage" id="osStage" data-scene="blue">
          <div class="os-sceneFill" id="osSceneFill" aria-hidden="true"></div>
          <div class="os-stageFrame" id="osStageFrame">
            <div class="os-sceneMain" id="osSceneMain" aria-hidden="true"></div>
            <div class="os-stageOverlay" aria-hidden="true"></div>

            <div class="os-avatarSlot os-avatarSlot--current" id="osAvatarCurrent">
            <div class="os-avatarLabel">Current</div>
            <?php mp_render_stage_avatar($current, 'current'); ?>
          </div>

          <div class="os-avatarSlot os-avatarSlot--draft is-active" id="osAvatarDraft">
            <div class="os-avatarLabel">Preview</div>
            <?php mp_render_stage_avatar($current, 'draft'); ?>
          </div>
        </div>
      </div>

        <div class="os-equipBar" id="osEquipBar">
  <?php foreach ($slots as $s): $id = (int)$current[$s]; ?>
    <div class="os-equipChipWrap" data-slot="<?php echo mp_h($s); ?>">
      <button type="button" class="os-equipChip" data-slot="<?php echo mp_h($s); ?>" title="<?php echo mp_h(slot_label($s)); ?>">
        <span class="os-equipIcon">
          <img src="<?php echo mp_h($s === 'color' ? ('/yukon/assets/media/color/icon/120/' . (int)$id . '.png') : clothing_icon_url((int)$id)); ?>" onerror="this.style.opacity=0.25" alt="">
        </span>
        <span class="os-equipMeta">
          <span class="os-equipSlot"><?php echo mp_h(slot_label($s)); ?></span>
          <span class="os-equipId" id="osEquip-<?php echo mp_h($s); ?>"><?php echo $id > 0 ? ('#' . (int)$id) : 'None'; ?></span>
        </span>
      </button>

      <button type="button" class="os-unequipBtn" data-unequip="<?php echo mp_h($s); ?>" title="Unequip <?php echo mp_h(slot_label($s)); ?>">
        <img src="/manage_penguin/assets/studio/icons/remove_item.webp" alt="">
      </button>
    </div>
  <?php endforeach; ?>
</div>

        <div class="os-actions">
          <!-- Reset the draft state back to the currently equipped items -->
          <button type="button" class="os-btn os-btnSoft" id="osResetBtn" title="Reset">
            <i class="fa-solid fa-arrow-rotate-left" aria-hidden="true"></i>
            <span class="os-btnText">Reset</span>
          </button>

          <!-- Equip now button performs a quick server call to persist the current draft outfit
               immediately. It bypasses the form submission and returns JSON. -->
          <button type="button" class="os-btn" id="osEquipNowBtn" title="Equip Now">
            <i class="fa-solid fa-check-circle" aria-hidden="true"></i>
            <span class="os-btnText">Equip</span>
          </button>

          <!-- Save to library allows users to store an outfit with a custom name and privacy setting. -->
          <button type="button" class="os-btn" id="osSaveToLibBtn" title="Save to Library">
            <i class="fa-solid fa-bookmark" aria-hidden="true"></i>
            <span class="os-btnText">Save</span>
          </button>


          <!-- Hidden CSRF token for use by the JavaScript API calls. We keep a single input
               with the "csrf_token" name so existing JS (equipNow and saveToLibrary)
               continue to locate it via document.querySelector('input[name="csrf_token"]').
               This input is not visible to the user and is not wrapped in a form. -->
          <input type="hidden" name="csrf_token" value="<?php echo mp_h(csrf_token()); ?>" style="display:none;">

          <!-- The old fallback Save Outfit form has been removed. Saving to the game now
               happens via the Equip Now button which calls the API directly. -->
        </div> <!-- /.os-actions -->

        <!-- Saved outfits preview: shows a few recently saved outfits with quick apply buttons.
             The list is populated via JavaScript using the /api/outfits.php endpoint. Users can
             click "View all" to open a modal with the full list. -->
        <div class="os-savedSection" id="osSavedSection" style="display:none;">
          <div class="os-savedHeader">Your saved outfits</div>
          <div class="os-savedList" id="osSavedList">
            <!-- populated by JS -->
          </div>
          <button type="button" class="os-btn os-btnSoft" id="osSavedViewAllBtn">View All</button>
        </div>
      </section>

      <!-- RIGHT: Browser -->
      <aside class="os-browserCard">
        <div class="os-browserTop">
          <div class="os-chips" id="osSlotChips">
            <button type="button" class="os-chip is-active" data-filter="all">All</button>
            <?php foreach ($slots as $s): ?>
              <button type="button" class="os-chip" data-filter="<?php echo mp_h($s); ?>"><?php echo mp_h(slot_label($s)); ?></button>
            <?php endforeach; ?>
            <!-- Saved filter chip: allows users to view their saved outfits in the right panel
                 instead of the inventory grid. Clicking this chip will show the Saved tab
                 populated via loadSavedTab() in studio.js. -->
            <button type="button" class="os-chip" data-filter="saved">Saved</button>
          </div>

          <div class="os-controls">
            <input id="osSearch" class="os-search" type="search" placeholder="Search by name or #ID…" autocomplete="off">
            <div class="os-count"><span id="osCount">0</span> items</div>
            <div class="os-viewToggle" id="osViewToggle" role="group" aria-label="Inventory view">
              <button type="button" class="os-viewBtn is-active" data-inv="classic">Comfortable</button>
              <button type="button" class="os-viewBtn" data-inv="grid3">3-per-row</button>
            </div>
          </div>
        </div>

        <div class="os-pager" id="osPagerTop" aria-label="Item pages">
          <button type="button" class="os-pageBtn" data-page="prev">Prev</button>
          <div class="os-pageStat"><span id="osPageNow">1</span> / <span id="osPageTotal">1</span></div>
          <button type="button" class="os-pageBtn" data-page="next">Next</button>
        </div>

        <div id="osGrid" class="os-itemGrid">
          <?php
            foreach ($slots as $s) {
              foreach ($ownedBySlot[$s] as $iid) {
                $name = item_display_name($iid, $ITEMS_META);
                $src = ($s === 'color') ? ('/yukon/assets/media/color/icon/120/' . (int)$iid . '.png') : clothing_icon_url($iid);
                echo '<button type="button" class="os-item" title="' . mp_h($name) . '" data-item-id="' . (int)$iid . '" data-slot="' . mp_h($s) . '" data-name="' . mp_h(strtolower($name)) . '">';
                echo '  <img src="' . mp_h($src) . '" onerror="this.style.opacity=0.25" alt="' . mp_h($name) . '">';
                echo '  <div class="os-itemMeta"><div class="os-itemName">' . mp_h($name) . '</div><div class="os-itemId">#' . (int)$iid . '</div></div>';
                echo '</button>';
              }
            }
          ?>
        </div>

        <!-- Saved outfits tab: this container appears when the "Saved" filter is selected.
             It is hidden by default and populated via JavaScript. Each saved outfit is
             rendered as a miniature avatar preview with an apply button. -->
        <div id="osSavedTab" class="os-savedTab" style="display:none;"></div>

        <div class="os-pager" id="osPagerBottom" aria-label="Item pages">
          <button type="button" class="os-pageBtn" data-page="prev">Prev</button>
          <div class="os-pageStat"><span id="osPageNow2">1</span> / <span id="osPageTotal2">1</span></div>
          <button type="button" class="os-pageBtn" data-page="next">Next</button>
        </div>
      </aside>
    </div>
  </div>
</div>

<script>
window.__OS_BOOT__ = <?php echo json_encode([
  'slots' => $slots,
  'current' => $current,
  'scenes' => [
    'winter_plaza' => ['type' => 'image', 'url' => $sceneImg],
    'blue' => ['type' => 'solid', 'color' => '#0b58b1'],
  ],
  'photoBase' => '/yukon/assets/media/clothing/photos/',
]); ?>;
</script>
<script src="/manage_penguin/assets/studio/studio.js?v=2"></script>

<?php include __DIR__ . '/includes/manage_footer.php'; ?>