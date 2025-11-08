<?php
declare(strict_types=1);

define('AUTH_GUARD_RESPONSE_TYPE', 'json');
$account = require __DIR__ . '/../../../backend/account/require_login.php';
$userId = (int)$account['id'];

mb_internal_encoding('UTF-8');

const SAVE_DATA_LOG_FILE = __DIR__ . '/save_data_debug.log';

function saveDataLog(string $requestId, string $level, string $message, array $context = []): void
{
    $timestamp = date('Y-m-d H:i:s');
    $line = sprintf('[%s][%s][%s] %s', $timestamp, $level, $requestId, $message);

    if (!empty($context)) {
        $line .= ' ' . json_encode(
            $context,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );
    }

    file_put_contents(
        SAVE_DATA_LOG_FILE,
        $line . PHP_EOL,
        FILE_APPEND | LOCK_EX
    );
}

$requestId = bin2hex(random_bytes(8));

// 各リクエストでログをリセット（上書き）
file_put_contents(SAVE_DATA_LOG_FILE, '', LOCK_EX);

// config.phpを読み込む
// $configPath = __DIR__ . '/home/xs413160/tunagaridiary.com/private/config/config.php';
$configPath = '/home/xs413160/tunagaridiary.com/private/config/config.php';
if (!file_exists($configPath)) {
    die("エラー: config.phpが見つかりません。パス: " . $configPath);
}
require_once $configPath;

// config.phpで定義された定数を使用
$servername = DB_SERVER;
$username   = DB_USERNAME;
$password   = DB_PASSWORD;
$dbname     = DB_NAME;

date_default_timezone_set('Asia/Tokyo');
$currentDatetime = date('Y-m-d H:i:s');

header('Content-Type: application/json; charset=utf-8');

$contentType = $_SERVER['CONTENT_TYPE'] ?? '';
$rawBody = file_get_contents('php://input') ?: '';
$requestData = $_POST;

saveDataLog($requestId, 'INFO', 'リクエストを受信しました', [
    'method' => $_SERVER['REQUEST_METHOD'] ?? 'UNKNOWN',
    'content_type' => $contentType,
    'raw_length' => strlen($rawBody),
    'user_id' => $userId,
]);

if ($rawBody !== '' && stripos($contentType, 'application/json') !== false) {
    $decoded = json_decode($rawBody, true);
    if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
        saveDataLog($requestId, 'ERROR', 'JSONの解析に失敗しました', [
            'error' => json_last_error_msg(),
            'raw_sample' => mb_substr($rawBody, 0, 200),
        ]);

        http_response_code(400);
        echo json_encode([
            'status' => 'error',
            'message' => '無効なJSON形式です。',
            'debug_id' => $requestId,
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $requestData = array_merge($requestData, $decoded);
}

saveDataLog($requestId, 'DEBUG', '解析後のペイロード', [
    'payload' => $requestData,
]);

$soundText = null;
$rawSoundTextLength = 0;
$rawSoundPreview = '';
if (array_key_exists('sound_text', $requestData)) {
    $rawSoundText = (string)$requestData['sound_text'];
    $rawSoundTextLength = mb_strlen($rawSoundText);
    $rawSoundPreview = mb_substr($rawSoundText, 0, 120);
    $trimmed = trim($rawSoundText);
    if ($trimmed !== '') {
        $soundText = $trimmed;
    }
}

if ($rawSoundTextLength > 0) {
    saveDataLog($requestId, 'INFO', '音声テキストを受信しました', [
        'raw_text_length' => $rawSoundTextLength,
        'preview' => $rawSoundPreview,
    ]);
} else {
    saveDataLog($requestId, 'DEBUG', '音声テキストが未入力または空文字です');
}

$latitude  = null;
if (array_key_exists('latitude', $requestData) && $requestData['latitude'] !== '' && $requestData['latitude'] !== null) {
    $latitude = (float)$requestData['latitude'];
}

$longitude = null;
if (array_key_exists('longitude', $requestData) && $requestData['longitude'] !== '' && $requestData['longitude'] !== null) {
    $longitude = (float)$requestData['longitude'];
}

$response = [
    'status' => 'error',
    'message' => '保存に失敗しました。',
    'user_id' => $userId,
    'debug_id' => $requestId,
    'received' => [
        'text_received' => $soundText !== null,
        'raw_text_length' => $rawSoundTextLength,
        'latitude' => $latitude,
        'longitude' => $longitude,
    ],
    'saved' => [
        'db0_location' => false,
        'db1_text' => false,
    ],
];

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $conn = new mysqli($servername, $username, $password, $dbname);
    $conn->set_charset('utf8mb4');
    $conn->begin_transaction();

    saveDataLog($requestId, 'INFO', 'DB接続に成功しました');

    if ($latitude !== null && $longitude !== null) {
        $stmt = $conn->prepare('INSERT INTO db0 (user_id, date, latitude, longitude) VALUES (?, ?, ?, ?)');
        $stmt->bind_param('isdd', $userId, $currentDatetime, $latitude, $longitude);
        $stmt->execute();
        $stmt->close();
        $response['saved']['db0_location'] = true;
        saveDataLog($requestId, 'INFO', '位置情報を保存しました', [
            'latitude' => $latitude,
            'longitude' => $longitude,
        ]);
    } else {
        saveDataLog($requestId, 'DEBUG', '位置情報は受信しませんでした');
    }

    if ($soundText !== null) {
        $stmt = $conn->prepare('INSERT INTO db1 (user_id, date, soundtext) VALUES (?, ?, ?)');
        $stmt->bind_param('iss', $userId, $currentDatetime, $soundText);
        $stmt->execute();
        $stmt->close();
        $response['saved']['db1_text'] = true;
        saveDataLog($requestId, 'INFO', '音声テキストを保存しました', [
            'length' => mb_strlen($soundText),
            'preview' => mb_substr($soundText, 0, 120),
        ]);
    } else {
        saveDataLog($requestId, 'WARN', '音声テキストが有効ではないため保存しませんでした', [
            'raw_text_length' => $rawSoundTextLength,
            'preview' => $rawSoundPreview,
        ]);
    }

    $conn->commit();
    $response['status'] = 'success';
    $response['message'] = 'データを保存しました。';
    saveDataLog($requestId, 'INFO', 'トランザクションをコミットしました', [
        'saved' => $response['saved'],
    ]);
} catch (Throwable $e) {
    if (isset($conn) && $conn instanceof mysqli) {
        $conn->rollback();
    }
    $response['message'] = $e->getMessage();
    saveDataLog($requestId, 'ERROR', '保存処理で例外が発生しました', [
        'error' => $e->getMessage(),
    ]);
} finally {
    if (isset($conn) && $conn instanceof mysqli) {
        $conn->close();
    }
}

echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
saveDataLog($requestId, 'INFO', 'レスポンスを返却しました', [
    'status' => $response['status'],
    'saved' => $response['saved'],
]);
