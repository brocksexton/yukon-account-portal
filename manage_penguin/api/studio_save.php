<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_login();

require_once __DIR__ . '/../includes/meta.php';

header('Content-Type: application/json; charset=utf-8');

$user = current_user();
$uid = (int)$user['id'];

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!is_array($data)) {
  http_response_code(400);
  echo json_encode(['ok'=>false,'error'=>'Invalid JSON']);
  exit;
}

// CSRF: accept either header or body token
$csrf = (string)($data['csrf'] ?? '');
$csrfHeader = (string)($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
if (!$csrf) $csrf = $csrfHeader;
if (!$csrf || !hash_equals(csrf_token(), $csrf)) {
  http_response_code(403);
  echo json_encode(['ok'=>false,'error'=>'CSRF failed']);
  exit;
}

$slots = ['color','head','face','neck','body','hand','feet','photo','flag'];
$outfit = [];
foreach ($slots as $s) {
  $outfit[$s] = (int)($data['outfit'][$s] ?? 0);
}

// Ownership: user must own every non-zero wearable (except color, which is also in inventories)
$db = db();
$stmt = $db->prepare('SELECT itemId FROM inventories WHERE userId = ?');
$stmt->bind_param('i', $uid);
$stmt->execute();
$res = $stmt->get_result();
$owned = [];
while ($row = $res->fetch_assoc()) { $owned[(int)$row['itemId']] = true; }
$stmt->close();

$violations = [];
foreach ($outfit as $slot => $itemId) {
  if ($itemId <= 0) continue;
  if (!isset($owned[$itemId])) {
    $violations[] = ['slot'=>$slot,'itemId'=>$itemId,'reason'=>'not_owned'];
    continue;
  }

  // Slot validation using items.json metadata when available. The item_slot_of
  // function requires the items metadata as the second argument. Without it,
  // PHP throws a "Too few arguments" fatal error. Use the global $ITEMS_META
  // loaded from includes/meta.php.
  global $ITEMS_META;
  $expected = item_slot_of($itemId, $ITEMS_META);
  if ($expected !== null && $expected !== '' && $expected !== $slot) {
    $violations[] = ['slot'=>$slot,'itemId'=>$itemId,'reason'=>'wrong_slot','expected'=>$expected];
  }
}

if (!empty($violations)) {
  http_response_code(422);
  echo json_encode(['ok'=>false,'error'=>'Invalid outfit','violations'=>$violations]);
  exit;
}

// Save: write to users table
$stmt = $db->prepare('UPDATE users SET color=?, head=?, face=?, neck=?, body=?, hand=?, feet=?, photo=?, flag=? WHERE id=?');
$stmt->bind_param(
  'iiiiiiiiii',
  $outfit['color'],
  $outfit['head'],
  $outfit['face'],
  $outfit['neck'],
  $outfit['body'],
  $outfit['hand'],
  $outfit['feet'],
  $outfit['photo'],
  $outfit['flag'],
  $uid
);
$stmt->execute();
$ok = $stmt->affected_rows >= 0;
$stmt->close();

echo json_encode(['ok'=>$ok]);
