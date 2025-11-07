<?php
define('AUTH_GUARD_RESPONSE_TYPE', 'json');
$account = require __DIR__ . '/../../../backend/account/require_login.php';
$userId = (int)($account['id'] ?? 0);
if ($userId <= 0) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'ユーザー情報を取得できませんでした'], JSON_UNESCAPED_UNICODE);
    exit;
}
header('Content-Type: application/json; charset=utf-8');

$mysqli = new mysqli('localhost', 'backhold', 'backhold', 'back_db1');
if ($mysqli->connect_error) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'DB接続失敗: ' . $mysqli->connect_error], JSON_UNESCAPED_UNICODE);
    exit;
}
$mysqli->set_charset('utf8mb4');

// imageカラムを追加し、ORDER BY に id ASC を追加
$stmt = $mysqli->prepare(
    'SELECT id, DATE_FORMAT(`date`, "%Y-%m-%d") AS day, image FROM db3 WHERE user_id = ? ORDER BY `date` ASC, id ASC'
);
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'ステートメント準備に失敗しました: ' . $mysqli->error], JSON_UNESCAPED_UNICODE);
    $mysqli->close();
    exit;
}

$stmt->bind_param('i', $userId);
if (!$stmt->execute()) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'SQL実行失敗: ' . $stmt->error], JSON_UNESCAPED_UNICODE);
    $stmt->close();
    $mysqli->close();
    exit;
}

$result = $stmt->get_result();
$days = [];   // 例: { "2025-11-01": [12, 13] }
$thumbs = []; // 例: { "2025-11-01": "https://..." }

while ($row = $result->fetch_assoc()) {
    $day = trim($row['day'] ?? '');
    $id = (int)($row['id'] ?? 0);
    $image = $row['image'] ?? ''; // image を取得

    if ($day === '' || $id <= 0) {
        continue;
    }

    if (!isset($days[$day])) {
        $days[$day] = [];
    }
    $days[$day][] = $id;

    // その日のサムネイルURL（最初に見つかった image を採用）
    if (!isset($thumbs[$day]) && !empty($image)) {
        $thumbs[$day] = $image;
    }
}

// thumbs を追加して出力
echo json_encode(
    ['ok' => true, 'days' => $days, 'thumbs' => $thumbs],
    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
);

$stmt->close();
$mysqli->close();