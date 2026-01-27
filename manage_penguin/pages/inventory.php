<?php
// manage_penguin/pages/inventory.php
// Server-side pagination
$perPage = 120;
$page = isset($_GET['p']) ? max(1, (int)$_GET['p']) : 1;
$offset = ($page-1)*$perPage;

$total=0;
$stmt = db()->prepare('SELECT COUNT(*) AS c FROM inventories WHERE userId = ?');
$stmt->bind_param('i', $user['id']);
$stmt->execute();
$res=$stmt->get_result();
if($row=$res->fetch_assoc()) $total=(int)$row['c'];
$stmt->close();

$owned=[];
$stmt = db()->prepare('SELECT itemId FROM inventories WHERE userId = ? ORDER BY itemId ASC LIMIT ? OFFSET ?');
$stmt->bind_param('iii', $user['id'], $perPage, $offset);
$stmt->execute();
$res=$stmt->get_result();
while($r=$res->fetch_assoc()) $owned[]=(int)$r['itemId'];
$stmt->close();

$slots = slot_list();
$slotCounts = array_fill_keys($slots, 0);
foreach ($owned as $id) {
  $s = item_slot_of($id, $ITEMS_META);
  if($s && isset($slotCounts[$s])) $slotCounts[$s]++;
}

$from = $total ? ($offset+1) : 0;
$to = min($offset+$perPage, $total);
?>

<div class="cp-card">
  <h2 class="cp-h2">Inventory</h2>
  <div class="cp-subtitle">Showing <?php echo (int)$from; ?>–<?php echo (int)$to; ?> of <?php echo (int)$total; ?> items.</div>

  <div class="mp-invTools">
    <input id="invSearch" class="cp-input" type="search" placeholder="Search by name or ID..." autocomplete="off">
    <select id="invSlot" class="cp-input">
      <option value="all">All</option>
      <?php foreach ($slots as $s): ?>
        <option value="<?php echo e($s); ?>"><?php echo e(slot_label($s)); ?></option>
      <?php endforeach; ?>
      <option value="unknown">Unknown type</option>
    </select>
  </div>

  <div id="invGrid" class="mp-itemGrid" style="margin-top:14px">
    <?php foreach ($owned as $iid):
      $slot = item_slot_of($iid, $ITEMS_META) ?? 'unknown';
      $name = item_display_name($iid, $ITEMS_META);
    ?>
      <div class="mp-item mp-itemStatic" data-item-id="<?php echo (int)$iid; ?>" data-slot="<?php echo e($slot); ?>" data-name="<?php echo e(strtolower($name)); ?>">
        <img src="<?php echo e(clothing_icon_url($iid)); ?>" onerror="this.style.opacity=0.25" alt="<?php echo e($name); ?>">
        <div class="mp-itemMeta">
          <div class="mp-itemName"><?php echo e($name); ?></div>
          <div class="mp-itemId">#<?php echo (int)$iid; ?></div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>

  <div class="mp-pager">
    <?php if ($page > 1): ?>
      <a class="cp-btn cp-btn-soft" href="index.php?tab=inventory&p=<?php echo (int)($page-1); ?>">Prev</a>
    <?php else: ?>
      <span></span>
    <?php endif; ?>

    <div class="mp-pageInfo">Page <?php echo (int)$page; ?> of <?php echo max(1, (int)ceil($total/$perPage)); ?></div>

    <?php if ($offset+$perPage < $total): ?>
      <a class="cp-btn cp-btn-soft" href="index.php?tab=inventory&p=<?php echo (int)($page+1); ?>">Next</a>
    <?php else: ?>
      <span></span>
    <?php endif; ?>
  </div>
</div>

<script>
(function(){
  const search=document.getElementById('invSearch');
  const slot=document.getElementById('invSlot');
  const grid=document.getElementById('invGrid');

  function apply(){
    const q=(search.value||'').trim().toLowerCase();
    const s=slot.value;
    grid.querySelectorAll('.mp-itemStatic').forEach(el=>{
      const name=el.dataset.name||'';
      const id=el.dataset.itemId||'';
      const slotv=el.dataset.slot||'unknown';
      let ok=true;
      if(s!=='all' && slotv!==s) ok=false;
      if(ok && q) ok = name.includes(q) || id.includes(q);
      el.style.display = ok ? '' : 'none';
    });
  }
  search.addEventListener('input', apply);
  slot.addEventListener('change', apply);
})();
</script>
