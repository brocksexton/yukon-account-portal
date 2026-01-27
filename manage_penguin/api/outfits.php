<?php
// manage_penguin/api/outfits.php
//
// This endpoint exposes CRUD operations for saved outfits. Users can
// persist multiple outfit configurations with a name and privacy flag,
// retrieve them and later apply them. All operations require the user
// to be authenticated and pass CSRF validation when appropriate.

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../includes/studio_lib.php';
// Include the avatar component to render player cards for each outfit. This helper
// depends on meta.php to provide the $YUKON_WEB base path and is used here
// to generate fully layered avatar HTML on the server. Without this, the
// client must reconstruct each outfit on the fly, which can lead to
// missing assets or inconsistent rendering. See mp_avatar_component() in
// manage_penguin/includes/avatar_component.php for details.
require_once __DIR__ . '/../includes/avatar_component.php';

header('Content-Type: application/json; charset=utf-8');

require_login();
$user = current_user();
$userId = (int)$user['id'];

// Ensure the outfits table exists with the latest schema. We attempt to
// create the table lazily on first use. If it already exists in an older
// format, we add any missing columns quietly. New columns support share
// codes, descriptions, likes and timestamps.
function ensure_outfit_table(){
  static $checked = false;
  if ($checked) return;
  $checked = true;
  $db = db();
  // Create table with full schema if it does not exist.
  $db->query("CREATE TABLE IF NOT EXISTS user_outfits (
    id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
    userId INT(11) NOT NULL,
    shareCode VARCHAR(16) NOT NULL,
    sourceShareCode VARCHAR(16) DEFAULT NULL,
    name VARCHAR(50) NOT NULL,
    description VARCHAR(255) NOT NULL DEFAULT '',
    isPublic TINYINT(1) NOT NULL DEFAULT 0,
    likes INT(11) NOT NULL DEFAULT 0,
    color INT(11) NOT NULL DEFAULT 1,
    head INT(11) NOT NULL DEFAULT 0,
    face INT(11) NOT NULL DEFAULT 0,
    neck INT(11) NOT NULL DEFAULT 0,
    body INT(11) NOT NULL DEFAULT 0,
    hand INT(11) NOT NULL DEFAULT 0,
    feet INT(11) NOT NULL DEFAULT 0,
    photo INT(11) NOT NULL DEFAULT 0,
    flag INT(11) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_shareCode (shareCode),
    KEY idx_userId (userId),
    CONSTRAINT fk_user_outfits_user FOREIGN KEY (userId) REFERENCES users(id) ON DELETE CASCADE
  ) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci");
  // The table is created with all required columns by default. Previously we attempted to
  // add missing columns on every request using ALTER TABLE calls. However, if the
  // columns already exist (as is the case after running the provided migration SQL),
  // those ALTER queries throw "duplicate column" errors. To avoid runtime fatals
  // (as seen in production), we simply omit the legacy ALTER statements. If you
  // upgrade from an older schema, please run the migration SQL once instead of
  // relying on these at‑runtime modifications.
}

// Generate a unique share code consisting of hexadecimal characters. The
// generated code is checked against the database to avoid collisions. The
// default length of eight characters yields 16^8 possible codes (~4 billion).
function mp_generate_share_code(int $length = 8): string {
  $db = db();
  do {
    $bytes = random_bytes((int)ceil($length / 2));
    $code = substr(bin2hex($bytes), 0, $length);
    $stmt = $db->prepare("SELECT id FROM user_outfits WHERE shareCode = ? LIMIT 1");
    $stmt->bind_param("s", $code);
    $stmt->execute();
    $res = $stmt->get_result();
    $exists = $res ? $res->fetch_assoc() : null;
    $stmt->close();
  } while ($exists);
  return $code;
}

// Attempt to create the table if missing
ensure_outfit_table();

// GET: return list of saved outfits for the current user. Accepts a `limit`
// query parameter. The `limit` is clamped between 1 and 100.
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
  // If public=1, return a paginated list of public outfits from all users
  if (isset($_GET['public']) && (int)$_GET['public'] === 1) {
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    if ($limit < 1) $limit = 1;
    if ($limit > 100) $limit = 100;
    if ($page < 1) $page = 1;
    $offset = ($page - 1) * $limit;
    $db = db();
    // Join user_outfits with users to fetch author and their current look
    $sql = 'SELECT uo.id, uo.shareCode, uo.name, uo.description, uo.likes, uo.created_at, uo.updated_at, '
      . 'uo.color, uo.head, uo.face, uo.neck, uo.body, uo.hand, uo.feet, uo.photo, uo.flag, '
      . 'uo.userId AS authorId, usr.username AS authorName, '
      . 'usr.color AS author_color, usr.head AS author_head, usr.face AS author_face, usr.neck AS author_neck, usr.body AS author_body, '
      . 'usr.hand AS author_hand, usr.feet AS author_feet, usr.photo AS author_photo, usr.flag AS author_flag '
      . 'FROM user_outfits uo INNER JOIN users usr ON usr.id = uo.userId WHERE uo.isPublic = 1 ORDER BY uo.id DESC LIMIT ? OFFSET ?';
    $stmt = $db->prepare($sql);
    $stmt->bind_param('ii', $limit, $offset);
    $stmt->execute();
    $res = $stmt->get_result();
    $outfits = [];
    // Compute owned set once
    $ownedSetPub = mp_owned_set($userId);
    $slots = ['color','head','face','neck','body','hand','feet','photo','flag'];
    while ($row = $res->fetch_assoc()) {
      $out = [];
      $out['id']         = (int)$row['id'];
      $out['shareCode']  = (string)$row['shareCode'];
      $out['name']       = (string)$row['name'];
      $out['description']= (string)$row['description'];
      $out['likes']      = (int)$row['likes'];
      $out['created_at'] = $row['created_at'];
      $out['updated_at'] = $row['updated_at'];
      // Slot values
      foreach ($slots as $s) {
        $out[$s] = (int)$row[$s];
      }
      // Author details
      $author = [];
      $author['id']       = (int)$row['authorId'];
      $author['username'] = (string)$row['authorName'];
      $author['color']    = (int)$row['author_color'];
      $author['head']     = (int)$row['author_head'];
      $author['face']     = (int)$row['author_face'];
      $author['neck']     = (int)$row['author_neck'];
      $author['body']     = (int)$row['author_body'];
      $author['hand']     = (int)$row['author_hand'];
      $author['feet']     = (int)$row['author_feet'];
      $author['photo']    = (int)$row['author_photo'];
      $author['flag']     = (int)$row['author_flag'];
      $out['author'] = $author;
      // Determine missing items for this outfit relative to current user
      // Compute how many items the current user is missing relative to this
      // outfit. We ignore the colour slot because colours are not treated
      // as items you must own. Without this exclusion, the API reported
      // you were missing colours even when you had them equipped.
      $missing = 0;
      foreach ($slots as $s) {
        if ($s === 'color') continue;
        $itemId = (int)$row[$s];
        // Use isset() on the owned set since the array keys are item IDs and
        // the values are booleans. in_array() would incorrectly search the
        // boolean values instead of keys, causing false missing counts.
        if ($itemId > 0 && !isset($ownedSetPub[$itemId])) {
          $missing++;
        }
      }
      $out['missing'] = $missing;
      // Pre-render a player card and a small preview using server-side helper.
      // We wrap the generated markup in a single-line string; it is safe to
      // embed directly into HTML via innerHTML. The idPrefix ensures
      // unique element IDs when multiple avatars appear on one page.
      $state = [];
      foreach ($slots as $s) $state[$s] = $out[$s];
      // Use a predictable prefix based on shareCode so IDs stay unique.
      $prefix = 'outfit_' . $out['shareCode'];
      $out['playerCard'] = mp_avatar_component($prefix, $state, 200);
      // Use a smaller size (80px) for mini previews. This yields a compact
      // player card that fits nicely in the Saved tab or other small
      // displays. See mp_avatar_component() for details on sizing.
      $out['playerCardSmall'] = mp_avatar_component($prefix . '_sm', $state, 80);
      $outfits[] = $out;
    }
    $stmt->close();
    echo json_encode(['ok' => true, 'outfits' => $outfits]);
    exit;
  }
  // Default: return the current user's saved outfits, including imported ones
  $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 3;
  if ($limit < 1) $limit = 1;
  if ($limit > 100) $limit = 100;
  $db = db();
  $stmt = $db->prepare('SELECT id, shareCode, sourceShareCode, name, description, isPublic, likes, created_at, updated_at, color, head, face, neck, body, hand, feet, photo, flag FROM user_outfits WHERE userId = ? ORDER BY id DESC LIMIT ?');
  $stmt->bind_param('ii', $userId, $limit);
  $stmt->execute();
  $res = $stmt->get_result();
  $outfits = [];
  $slots = ['color','head','face','neck','body','hand','feet','photo','flag'];
  // Compute owned set for user
  $ownedSetUser = mp_owned_set($userId);
  while ($row = $res->fetch_assoc()) {
    $out = [];
    $out['id']         = (int)$row['id'];
    $out['shareCode']  = (string)$row['shareCode'];
    $out['sourceShareCode'] = $row['sourceShareCode'];
    $out['name']       = (string)$row['name'];
    $out['description']= (string)$row['description'];
    $out['isPublic']   = (int)$row['isPublic'];
    $out['likes']      = (int)$row['likes'];
    $out['created_at'] = $row['created_at'];
    $out['updated_at'] = $row['updated_at'];
    foreach ($slots as $s) {
      $out[$s] = (int)$row[$s];
    }
    // Missing items count relative to user's inventory. Ignore the colour
    // slot when counting missing items; colours are always available and
    // not stored in the inventories table. Without this, users were
    // incorrectly told they were missing their own colour.
    $missing = 0;
    foreach ($slots as $s) {
      if ($s === 'color') continue;
      $itemId = (int)$row[$s];
      // Check ownership by key existence. Using in_array() on an
      // associative set would fail because the array values are booleans,
      // not the item IDs themselves.
      if ($itemId > 0 && !isset($ownedSetUser[$itemId])) {
        $missing++;
      }
    }
    $out['missing'] = $missing;
    // Pre-render server-side avatar cards
    $state = [];
    foreach ($slots as $s) $state[$s] = $out[$s];
    $prefix = 'outfit_' . $out['shareCode'];
    $out['playerCard'] = mp_avatar_component($prefix, $state, 200);
    $out['playerCardSmall'] = mp_avatar_component($prefix . '_sm', $state, 120);
    $outfits[] = $out;
  }
  $stmt->close();
  echo json_encode(['ok' => true, 'outfits' => $outfits]);
  exit;
}

// POST: save a new outfit. Expects JSON body with:
// - csrf: CSRF token matching the session
// - name: outfit name
// - isPublic: 1 or 0
// - outfit: an object with slot keys (color, head, face, neck, body, hand, feet, photo, flag)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $raw = file_get_contents('php://input');
  $data = json_decode($raw, true);
  if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['ok'=>false,'error'=>'Invalid JSON']);
    exit;
  }
  $csrf = isset($data['csrf']) ? (string)$data['csrf'] : '';
  // Accept CSRF in header for flexibility
  $csrfHeader = isset($_SERVER['HTTP_X_CSRF_TOKEN']) ? (string)$_SERVER['HTTP_X_CSRF_TOKEN'] : '';
  if (!$csrf) $csrf = $csrfHeader;
  if (!$csrf || !hash_equals(csrf_token(), $csrf)) {
    http_response_code(403);
    echo json_encode(['ok'=>false,'error'=>'CSRF failed']);
    exit;
  }
  $name = trim((string)($data['name'] ?? ''));
  // Optional description for the outfit. If omitted default to empty string.
  $description = isset($data['description']) ? trim((string)$data['description']) : '';
  if ($name === '') {
    http_response_code(400);
    echo json_encode(['ok'=>false,'error'=>'Name is required']);
    exit;
  }
  $isPublic = isset($data['isPublic']) ? (int)$data['isPublic'] : 0;
  $draft = (isset($data['outfit']) && is_array($data['outfit'])) ? $data['outfit'] : [];
  // Validate outfit: ownership and slot types
  global $ITEMS_META;
  $ownedSet = mp_owned_set($userId);
  [$ok, $err] = mp_validate_outfit($userId, $draft, $ownedSet, $ITEMS_META);
  if (!$ok) {
    http_response_code(422);
    echo json_encode(['ok'=>false,'error'=>$err]);
    exit;
  }
  // Insert outfit record. Generate a unique share code and include description and
  // initial likes count. If provided, persist sourceShareCode to indicate a
  // copied outfit. Build the query dynamically to avoid SQL injection.
  $slots = ['color','head','face','neck','body','hand','feet','photo','flag'];
  $shareCode = mp_generate_share_code(8);
  // Accept sourceShareCode if copying from a public outfit
  $sourceShare = isset($data['sourceShareCode']) && trim((string)$data['sourceShareCode']) !== '' ? trim((string)$data['sourceShareCode']) : null;
  $fields = '`userId`, `shareCode`, `sourceShareCode`, `name`, `description`, `isPublic`, `likes`';
  $placeholders = '?, ?, ?, ?, ?, ?, ?';
  $types = 'issssii';
  $values = [$userId, $shareCode, $sourceShare, $name, $description, $isPublic, 0];
  foreach ($slots as $s) {
    $fields .= ', `' . $s . '`';
    $placeholders .= ', ?';
    $types .= 'i';
    $values[] = (int)($draft[$s] ?? 0);
  }
  $sql = 'INSERT INTO user_outfits (' . $fields . ') VALUES (' . $placeholders . ')';
  $db = db();
  $stmt = $db->prepare($sql);
  $stmt->bind_param($types, ...$values);
  $stmt->execute();
  $newId = $stmt->insert_id;
  $stmt->close();
  echo json_encode(['ok'=>true,'id'=>$newId,'shareCode'=>$shareCode]);
  exit;
}

// Unsupported method
http_response_code(405);
echo json_encode(['ok'=>false,'error'=>'Method not allowed']);
exit;