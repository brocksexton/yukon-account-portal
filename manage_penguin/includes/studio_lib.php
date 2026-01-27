<?php
// manage_penguin/includes/studio_lib.php
require_once __DIR__ . '/meta.php';

/**
 * Return a set (assoc array) of owned item IDs for a user.
 * inventories table stores clothing itemIds.
 */
function mp_owned_set(int $userId): array {
  $set = [];
  $stmt = db()->prepare('SELECT itemId FROM inventories WHERE userId = ?');
  $stmt->bind_param('i', $userId);
  $stmt->execute();
  $res = $stmt->get_result();
  while ($row = $res->fetch_assoc()) {
    $id = (int)$row['itemId'];
    if ($id > 0) $set[$id] = true;
  }
  $stmt->close();

  // Some accounts (especially staff/test accounts) can end up with items
  // equipped that aren't yet present in inventories due to admin tooling.
  // Treat currently equipped items as owned for website validation + UI.
  $stmt2 = db()->prepare('SELECT head, face, neck, body, hand, feet, photo, flag FROM users WHERE id = ? LIMIT 1');
  $stmt2->bind_param('i', $userId);
  $stmt2->execute();
  $res2 = $stmt2->get_result();
  if ($res2 && ($u = $res2->fetch_assoc())) {
    foreach (['head','face','neck','body','hand','feet','photo','flag'] as $k) {
      $iid = isset($u[$k]) ? (int)$u[$k] : 0;
      if ($iid > 0) $set[$iid] = true;
    }
  }
  $stmt2->close();

  return $set;
}

/** Color IDs are not always in inventories. Validate by checking color icon exists in Yukon. */
function mp_color_is_valid(int $colorId): bool {
  if ($colorId <= 0) return false;
  global $YUKON_FS;
  if (!$YUKON_FS) return ($colorId >= 1 && $colorId <= 32);
  $p = $YUKON_FS . '/assets/media/color/icon/120/' . $colorId . '.png';
  return is_file($p);
}

/** Validate an outfit draft: ownership + correct slot types from items.json. Returns [ok, errorMsg]. */
function mp_validate_outfit(int $userId, array $draft, array $ownedSet, array $ITEMS_META): array {
  $slots = slot_list();
  foreach ($slots as $slot) {
    $id = isset($draft[$slot]) ? (int)$draft[$slot] : 0;
    if ($id <= 0) continue;

    if ($slot === 'color') {
      if (!mp_color_is_valid($id)) return [false, 'Invalid color selection.'];
      continue;
    }

    // Must have metadata to enforce slot correctness.
    $actual = item_slot_of($id, $ITEMS_META);
    if (!$actual) return [false, "Item #{$id} is missing metadata and cannot be equipped from the website."];
    if ($actual !== $slot) return [false, "Item #{$id} cannot be equipped to {$slot_label($slot)} (belongs to {$slot_label($actual)})."];

    // Ownership: must exist in inventories
    if (!isset($ownedSet[$id])) return [false, "You do not own item #{$id}."];
  }
  return [true, ''];
}

/** Persist outfit draft to users table. */
function mp_save_outfit(int $userId, array $draft): void {
  $fields = ['color','head','face','neck','body','hand','feet','photo','flag'];
  $sets = [];
  $types = '';
  $vals = [];
  foreach ($fields as $f) {
    $sets[] = "{$f} = ?";
    $types .= 'i';
    $vals[] = (int)($draft[$f] ?? 0);
  }
  $types .= 'i';
  $vals[] = $userId;

  $sql = 'UPDATE users SET ' . implode(', ', $sets) . ' WHERE id = ?';
  $stmt = db()->prepare($sql);
  $stmt->bind_param($types, ...$vals);
  $stmt->execute();
  $stmt->close();
}
