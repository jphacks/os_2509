<?php
/**
 * 位置情報（緯度経度）保存API
 * db0テーブルに緯度経度を保存します
 */

// 先頭に追加
require_once '../config/config.php';

header('Content-Type: application/json; charset=utf-8');

$servername = "localhost";
$username = "backhold";
$password = "backhold";
$dbname = "back_db1";

// データベース接続
$conn = new mysqli($servername, $username, $password, $dbname);

// 接続確認
if ($conn->connect_error) {
    echo json_encode([
        'success' => false,
        'error' => '接続失敗: ' . $conn->connect_error
    ]);
    exit;
}

// 文字コード設定
$conn->set_charset("utf8mb4");

// POSTデータを取得
$json = file_get_contents('php://input');
$data = json_decode($json, true);

if (!$data) {
    echo json_encode([
        'success' => false,
        'error' => '無効なデータ'
    ]);
    exit;
}

$latitude = $data['latitude'] ?? null;
$longitude = $data['longitude'] ?? null;

// バリデーション
if ($latitude === null || $longitude === null) {
    echo json_encode([
        'success' => false,
        'error' => '緯度または経度が指定されていません'
    ]);
    exit;
}

// 緯度の範囲チェック (-90 ≤ latitude ≤ 90)
if ($latitude < -90 || $latitude > 90) {
    echo json_encode([
        'success' => false,
        'error' => '緯度の値が無効です（-90～90の範囲で指定してください）'
    ]);
    exit;
}

// 経度の範囲チェック (-180 ≤ longitude ≤ 180)
if ($longitude < -180 || $longitude > 180) {
    echo json_encode([
        'success' => false,
        'error' => '経度の値が無効です（-180～180の範囲で指定してください）'
    ]);
    exit;
}

// db0テーブルに挿入
$stmt = $conn->prepare("INSERT INTO db0 (date, latitude, longitude) VALUES (NOW(), ?, ?)");
$stmt->bind_param("dd", $latitude, $longitude);

if ($stmt->execute()) {
    echo json_encode([
        'success' => true,
        'id' => $stmt->insert_id,
        'message' => '位置情報を保存しました',
        'data' => [
            'latitude' => $latitude,
            'longitude' => $longitude
        ]
    ]);
} else {
    echo json_encode([
        'success' => false,
        'error' => $stmt->error
    ]);
}

$stmt->close();
$conn->close();
?>