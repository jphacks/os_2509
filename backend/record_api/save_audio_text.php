<?php

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

$soundtext = $data['soundtext'] ?? '';
$location = $data['location'] ?? '';

if (empty($soundtext) || empty($location)) {
    echo json_encode([
        'success' => false,
        'error' => '必須項目が不足しています'
    ]);
    exit;
}

// db1テーブルに挿入
$stmt = $conn->prepare("INSERT INTO db1 (date, soundtext, location) VALUES (NOW(), ?, ?)");
$stmt->bind_param("ss", $soundtext, $location);

if ($stmt->execute()) {
    echo json_encode([
        'success' => true,
        'id' => $stmt->insert_id,
        'message' => 'データを保存しました'
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