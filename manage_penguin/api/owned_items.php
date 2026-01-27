<?php
// manage_penguin/api/owned_items.php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../includes/studio_lib.php';

header('Content-Type: application/json; charset=utf-8');

require_login();
$user = current_user();
$userId = (int)$user['id'];

global $ITEMS_META;

$slot = isset($_GET['slot']) ? strtolower((string)$_GET['slot']) : 'all';
$q    = isset($_GET['q']) ? trim((string)$_GET['q']) : '';
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$per  = isset($_GET['per']) ? max(24, min(240, (int)$_GET['per'])) : 60;

$ownedSet = mp_owned_set($userId);

// Build list using metadata so we can filter by slot and name.
$items = [];
$qLower = strtolower($q);
foreach ($ownedSet as $idStr => $_) {
  $id = (int)$idStr;
  $itemSlot = item_slot_of($id, $ITEMS_META) ?? 'unknown';
  if ($slot !== 'all' && $itemSlot !== $slot) continue;

  $name = item_display_name($id, $ITEMS_META);
  if ($qLower !== '') {
    $hay = strtolower($name);
    if (strpos($hay, $qLower) === false && strpos((string)$id, str_replace('#','',$qLower)) === false) continue;
  }

  $items[] = [
    'id' => $id,
    'name' => $name,
    'slot' => $itemSlot,
    'icon' => ($itemSlot === 'color') ? ($GLOBALS['YUKON_WEB'].'/assets/media/color/icon/120/'.$id.'.png') : clothing_icon_url($id),
  ];
}

// Sort by name then id for stable browsing.
usort($items, function($a,$b){
  $c = strcasecmp($a['name'], $b['name']);
  if ($c !== 0) return $c;
  return $a['id'] <=> $b['id'];
});

$total = count($items);
$totalPages = max(1, (int)ceil($total / $per));
$page = min($page, $totalPages);
$offset = ($page - 1) * $per;
$pageItems = array_slice($items, $offset, $per);

echo json_encode([
  'ok' => true,
  'slot' => $slot,
  'q' => $q,
  'page' => $page,
  'per' => $per,
  'total' => $total,
  'pages' => $totalPages,
  'items' => $pageItems,
], JSON_UNESCAPED_SLASHES);
