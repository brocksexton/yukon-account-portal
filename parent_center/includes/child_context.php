<?php
// Shared helpers for Child-related Parent Center pages
require_once __DIR__ . '/parent_bootstrap.php';

/**
 * Ensure the current parent is linked to this child account, and return the child row.
 *
 * @return array Child row (includes parent label/alias).
 */
function pc_require_child(int $parentId, int $uid): array {
  if ($uid <= 0) {
    header('Location: dashboard.php');
    exit;
  }

  $stmt = db()->prepare('
    SELECT pc.userId, pc.alias, u.*
    FROM parent_children pc
    JOIN users u ON u.id = pc.userId
    WHERE pc.parentId = ? AND pc.userId = ?
    LIMIT 1
  ');
  $stmt->bind_param('ii', $parentId, $uid);
  $stmt->execute();
  $child = $stmt->get_result()->fetch_assoc();
  $stmt->close();

  if (!$child) {
    http_response_code(403);
    die('Not linked to this Parent Center.');
  }

  // Normalize for avatar helpers
  $child['userId'] = (int)($child['userId'] ?? $uid);
  return $child;
}

/**
 * Render the shared navigation for Child pages.
 */
function pc_child_nav(int $uid, string $active): string {
  $tabs = [
    'overview'  => ['label' => 'Overview',  'href' => 'child.php?uid=' . $uid],
    'inventory' => ['label' => 'Inventory', 'href' => 'child_inventory.php?uid=' . $uid],
    'puffles'   => ['label' => 'Puffles',   'href' => 'child_puffles.php?uid=' . $uid],
    'bans'      => ['label' => 'Bans',      'href' => 'child_bans.php?uid=' . $uid],
    'chat'      => ['label' => 'Chat History', 'href' => 'child_chat.php?uid=' . $uid],
    'friends'   => ['label' => 'Friends',   'href' => 'child_friends.php?uid=' . $uid],
  ];

  $html = '<nav class="pc-childNav" aria-label="Child pages">';
  foreach ($tabs as $key => $t) {
    $cls = 'pc-childNavLink' . ($key === $active ? ' is-active' : '');
    $html .= '<a class="' . e($cls) . '" href="' . e($t['href']) . '">' . e($t['label']) . '</a>';
  }
  $html .= '</nav>';
  return $html;
}

/**
 * Build an array of currently equipped clothing items (slot => itemId).
 */
function pc_equipped_items(array $child): array {
  $map = [
    'Color' => (int)($child['color'] ?? 0),
    'Head'  => (int)($child['head'] ?? 0),
    'Face'  => (int)($child['face'] ?? 0),
    'Neck'  => (int)($child['neck'] ?? 0),
    'Body'  => (int)($child['body'] ?? 0),
    'Hand'  => (int)($child['hand'] ?? 0),
    'Feet'  => (int)($child['feet'] ?? 0),
    'Photo' => (int)($child['photo'] ?? 0),
    'Flag'  => (int)($child['flag'] ?? 0),
  ];

  // Filter out empty slots (except color, which can be 0/1 depending on schema)
  $out = [];
  foreach ($map as $slot => $id) {
    if ($slot === 'Color') {
      $out[$slot] = $id;
      continue;
    }
    if ($id > 0) $out[$slot] = $id;
  }
  return $out;
}

function pc_clothing_icon_url(int $itemId, int $size = 120): string {
  $base = rtrim((string)YUKON_ASSET_BASE_URL, '/');
  return $base . '/media/clothing/icon/' . (int)$size . '/' . (int)$itemId . '.png';
}
