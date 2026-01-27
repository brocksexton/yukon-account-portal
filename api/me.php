<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_login();

// Force refresh (skip cache)
unset($_SESSION['user_cache'], $_SESSION['user_cache_time']);
$user = current_user();
if (!$user) {
  http_response_code(401);
  header('Content-Type: application/json');
  echo json_encode(['ok' => false]);
  exit;
}

$urlBase = rtrim(YUKON_ASSET_BASE_URL, '/');

$outfit = [
  'color' => (int)$user['color'],
  'head'  => (int)$user['head'],
  'face'  => (int)$user['face'],
  'neck'  => (int)$user['neck'],
  'body'  => (int)$user['body'],
  'hand'  => (int)$user['hand'],
  'feet'  => (int)$user['feet'],
  'photo' => (int)$user['photo'],
  'flag'  => (int)$user['flag'],
];

// Layer urls
$layers = [];
foreach (['feet','body','neck','face','head','hand'] as $slot) {
  $id = (int)$outfit[$slot];
  if ($id > 0) {
    $layers[$slot] = $urlBase . '/media/clothing/paper/' . $id . '.png';
  } else {
    $layers[$slot] = null;
  }
}

$photoUrl = $outfit['photo'] > 0 ? $urlBase . '/media/clothing/photos/' . $outfit['photo'] . '.png' : null;
$flagUrl  = $outfit['flag']  > 0 ? $urlBase . '/media/clothing/icon/120/' . $outfit['flag']  . '.png' : null;

header('Content-Type: application/json');
echo json_encode([
  'ok' => true,
  'ts' => time(),
  'user' => [
    'id' => (int)$user['id'],
    'username' => $user['username'],
    'rank' => (int)$user['rank'],
    'rankLabel' => (function(int $rank): string {
      switch ($rank) {
        case 4: return 'Owner';
        case 3: return 'Administrator';
        case 2: return 'Moderator';
        default: return 'Player';
      }
    })((int)$user['rank']),
    'coins' => (int)$user['coins'],
    'joinTime' => $user['joinTime'],
    'ninjaRank' => (int)$user['ninjaRank'],
    'ninjaProgress' => (int)$user['ninjaProgress'],
  ],
  'outfit' => $outfit,
  'assets' => [
    'photo' => $photoUrl,
    'flag' => $flagUrl,
    'layers' => $layers,
  ],
]);
