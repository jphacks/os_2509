<?php
header('Content-Type: application/json; charset=utf-8');

/* ログイン必須にしている場合はそのまま、未設定なら安全にスルー */
$auth_guard = __DIR__ . '/../../../backend/account/require_login.php';
if (file_exists($auth_guard)) {
  define('AUTH_GUARD_RESPONSE_TYPE', 'json');
  require $auth_guard;
}

/* DB接続 */
$mysqli = new mysqli("localhost", "backhold", "backhold", "back_db1");
if ($mysqli->connect_error) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>'DB接続失敗: '.$mysqli->connect_error], JSON_UNESCAPED_UNICODE);
  exit;
}

/* 日付を YYYY-MM-DD に固定しつつ image も取得 */
$sql = "SELECT id,
               DATE_FORMAT(`date`, '%Y-%m-%d') AS day,
               image
        FROM db3
        ORDER BY `date` ASC, id ASC";
$res = $mysqli->query($sql);
if (!$res) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>'SQL失敗: '.$mysqli->error], JSON_UNESCAPED_UNICODE);
  exit;
}

$days   = [];   // 例: { "2025-11-01": [12, 13] }
$thumbs = [];   // 例: { "2025-11-01": "https://..." }

while ($row = $res->fetch_assoc()) {
  $d = trim($row['day']);
  if ($d === '') continue;

  $id = (int)$row['id'];
  if (!isset($days[$d])) $days[$d] = [];
  $days[$d][] = $id;

  // その日のサムネイルURL（最初に見つかった image を採用）
  if (!isset($thumbs[$d]) && !empty($row['image'])) {
    $thumbs[$d] = $row['image'];
  }
}

echo json_encode(
  ['ok'=>true, 'days'=>$days, 'thumbs'=>$thumbs],
  JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
);

$mysqli->close();
