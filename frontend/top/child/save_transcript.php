<?php
declare(strict_types=1);

define('AUTH_GUARD_RESPONSE_TYPE', 'json');
$account = require __DIR__ . '/../../../backend/account/require_login.php';
$userId = (int)$account['id'];

header('Content-Type: application/json; charset=utf-8');

if (!isset($_POST['sound_text']) || trim((string)$_POST['sound_text']) === '') {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'sound_text が指定されていません。'], JSON_UNESCAPED_UNICODE);
    exit;
}

$soundText = (string)$_POST['sound_text'];

$servername = "localhost";
$username   = "backhold";
$password   = "backhold";
$dbname     = "back_db1";

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $conn = new mysqli($servername, $username, $password, $dbname);
    $conn->set_charset('utf8mb4');

    $stmt = $conn->prepare('INSERT INTO db1 (user_id, date, soundtext) VALUES (?, NOW(), ?)');
    $stmt->bind_param('is', $userId, $soundText);
    $stmt->execute();

    echo json_encode([
        'status' => 'success',
        'message' => '音声テキストを保存しました。',
        'user_id' => $userId,
        'id' => $stmt->insert_id,
    ], JSON_UNESCAPED_UNICODE);

    $stmt->close();
    $conn->close();
} catch (Throwable $e) {
    if (isset($conn) && $conn instanceof mysqli) {
        $conn->close();
    }
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
}
