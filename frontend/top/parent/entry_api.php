<?php
/**
 * entry_api.php
 * - JSON: id/date で日記1件を返す
 * - 画像は DB の TEXT カラム（URL文字列）をそのまま `image_url` で返す
 * - サーバ側キャッシュは no-store
 */

@ini_set('display_errors', 0);

function db() {
  $mysqli = new mysqli("localhost", "backhold", "backhold", "back_db1");
  if ($mysqli->connect_error) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    echo json_encode(['ok'=>false, 'error'=>'DB接続失敗: '.$mysqli->connect_error], JSON_UNESCAPED_UNICODE);
    exit;
  }
  $mysqli->set_charset("utf8mb4");
  return $mysqli;
}

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

$mysqli = db();

$id   = isset($_GET['id'])   ? intval($_GET['id']) : null;
$date = isset($_GET['date']) ? trim($_GET['date']) : null;
if ($date && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) { $date = null; }

if ($id) {
  $stmt = $mysqli->prepare("SELECT id, DATE_FORMAT(`date`, '%Y-%m-%d') AS day, sentence, place, image FROM db3 WHERE id=? LIMIT 1");
  if(!$stmt){ http_response_code(500); echo json_encode(['ok'=>false,'error'=>'SQL準備失敗'], JSON_UNESCAPED_UNICODE); $mysqli->close(); exit; }
  $stmt->bind_param("i", $id);
} elseif ($date) {
  $stmt = $mysqli->prepare("SELECT id, DATE_FORMAT(`date`, '%Y-%m-%d') AS day, sentence, place, image FROM db3 WHERE DATE(`date`) = ? ORDER BY id ASC LIMIT 1");
  if(!$stmt){ http_response_code(500); echo json_encode(['ok'=>false,'error'=>'SQL準備失敗'], JSON_UNESCAPED_UNICODE); $mysqli->close(); exit; }
  $stmt->bind_param("s", $date);
} else {
  http_response_code(400);
  echo json_encode(['ok'=>false, 'error'=>'id または date を指定してください'], JSON_UNESCAPED_UNICODE);
  $mysqli->close();
  exit;
}

if (!$stmt->execute()) {
  http_response_code(500);
  echo json_encode(['ok'=>false, 'error'=>'SQL実行失敗: '.$mysqli->error], JSON_UNESCAPED_UNICODE);
  $stmt->close(); $mysqli->close();
  exit;
}
$res = $stmt->get_result();
$row = $res->fetch_assoc();
$stmt->close();
$mysqli->close();

if (!$row) {
  echo json_encode([
    'ok'        => true,
    'id'        => null,
    'date'      => ($date ?: null),
    'sentence'  => null,
    'image_url' => null
  ], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
  exit;
}

$image_url = null;
if (!is_null($row['image']) && $row['image'] !== '') {
  $image_url = trim((string)$row['image']);
  if ($image_url === '') $image_url = null;
}

echo json_encode([
  'ok'        => true,
  'id'        => intval($row['id']),
  'date'      => $row['day'],
  'sentence'  => $row['sentence'] ?? null,
  'image_url' => $image_url
], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
