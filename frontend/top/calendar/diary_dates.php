<?php
header('Content-Type: application/json; charset=utf-8');

// XAMPPの一般的既定（root / パスワード空）。環境に合わせて変更可。
$mysqli = new mysqli("localhost", "backhold", "backhold", "back_db1");
// 専用ユーザーを使う場合：
// $mysqli = new mysqli("localhost", "backhold", "backhold", "back_db1");

if ($mysqli->connect_error) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>'DB接続失敗: '.$mysqli->connect_error], JSON_UNESCAPED_UNICODE);
  exit;
}

/* ▼ 日付フォーマットを必ず 'YYYY-MM-DD' に固定 */
$sql = "SELECT id, DATE_FORMAT(`date`, '%Y-%m-%d') AS day FROM db3 ORDER BY `date` ASC";
/* JSTで切りたい場合（任意）： */
// $sql = "SELECT id, DATE_FORMAT(CONVERT_TZ(`date`, '+00:00', '+09:00'), '%Y-%m-%d') AS day FROM db3 ORDER BY `date` ASC";

$res = $mysqli->query($sql);
if (!$res) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>'SQL実行失敗: '.$mysqli->error], JSON_UNESCAPED_UNICODE);
  exit;
}

$days = [];
while ($row = $res->fetch_assoc()) {
  $d  = trim($row['day']);  // "2025-10-17" に固定
  $id = (int)$row['id'];
  if ($d === '') continue;
  if (!isset($days[$d])) $days[$d] = [];
  $days[$d][] = $id;
}

echo json_encode(['ok'=>true, 'days'=>$days], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
$mysqli->close();