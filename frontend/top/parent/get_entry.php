<?php
define('AUTH_GUARD_RESPONSE_TYPE', 'json');
$account = require __DIR__ . '/../../../backend/account/require_login.php';
$userId = (int)($account['id'] ?? 0);
if ($userId <= 0) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'message' => 'ユーザー情報を取得できませんでした', 'entry' => null], JSON_UNESCAPED_UNICODE);
    exit;
}

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

// ========== config.phpを読み込む ==========
$configPath = '/home/xs413160/tunagaridiary.com/private/config/config.php';
if (!file_exists($configPath)) {
    echo json_encode(['ok' => false, 'message' => 'config.phpが見つかりません', 'entry' => null], JSON_UNESCAPED_UNICODE);
    exit;
}
require_once $configPath;

// config.phpで定義された定数を使用
$servername = DB_SERVER;
$username   = DB_USERNAME;
$password   = DB_PASSWORD;
$dbname     = DB_NAME;
// ==========================================

$response = ['ok' => false, 'message' => '', 'entry' => null];

$entryId = isset($_GET['id']) ? trim($_GET['id']) : null;
$entryDate = isset($_GET['date']) ? trim($_GET['date']) : null;
$entryId = ($entryId === '') ? null : $entryId;
$entryDate = ($entryDate === '') ? null : $entryDate;

if ($entryId === null && $entryDate === null) {
    $response['message'] = 'IDまたは日付が指定されていません。';
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $conn = new mysqli($servername, $username, $password, $dbname);
    if ($conn->connect_error) {
        throw new Exception('データベース接続失敗: ' . $conn->connect_error);
    }
    $conn->set_charset('utf8mb4');

    if ($entryId !== null) {
        $id = (int)$entryId;
        $stmt = $conn->prepare('SELECT id, date, sentence, place, image FROM db3 WHERE id = ? AND user_id = ? LIMIT 1');
        $stmt->bind_param('ii', $id, $userId);
    } else {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $entryDate)) {
            throw new InvalidArgumentException('日付の形式が不正です。');
        }
        $stmt = $conn->prepare('SELECT id, date, sentence, place, image FROM db3 WHERE user_id = ? AND DATE(date) = ? ORDER BY date DESC, id DESC LIMIT 1');
        $stmt->bind_param('is', $userId, $entryDate);
    }

    $stmt->execute();
    $result = $stmt->get_result();

    if ($result && $result->num_rows > 0) {
        $response['ok'] = true;
        $response['entry'] = $result->fetch_assoc();
    } else {
        $response['message'] = '該当するエントリーが見つかりませんでした。';
    }

    $stmt->close();
    $conn->close();

} catch (InvalidArgumentException $e) {
    $response['message'] = $e->getMessage();
} catch (Exception $e) {
    $response['message'] = 'サーバーエラー: ' . $e->getMessage();
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);
