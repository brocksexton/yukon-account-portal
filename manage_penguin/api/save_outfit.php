<?php
// manage_penguin/api/save_outfit.php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../includes/studio_lib.php';

header('Content-Type: application/json; charset=utf-8');

require_login();
$user = current_user();
$userId = (int)$user['id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo json_encode(['ok'=>false,'error'=>'Method not allowed']);
  exit;
}

// Uses site's CSRF helper (expects POST field named "csrf")
csrf_require();

$draft = [];
foreach (slot_list() as $s) {
  $draft[$s] = isset($_POST['slot_'.$s]) ? (int)$_POST['slot_'.$s] : 0;
}

global $ITEMS_META;
$ownedSet = mp_owned_set($userId);

[$ok, $err] = mp_validate_outfit($userId, $draft, $ownedSet, $ITEMS_META);
if (!$ok) {
  http_response_code(400);
  echo json_encode(['ok'=>false,'error'=>$err]);
  exit;
}

mp_save_outfit($userId, $draft);

echo json_encode(['ok'=>true]);
